<?php
/**
 * FAQ optimisation — single source of FAQ output per post.
 *
 * Two FAQ sources exist in this theme:
 *   1. The blog-pro/faq block placed in the post content (editor).
 *   2. The legacy FAQ metabox (blocks/class-blogpro-block.php), whose
 *      items are rendered as a blog-pro/faq block.
 *
 * This module appends the metabox FAQ to the post content automatically —
 * but ONLY when the content does not already contain a blog-pro/faq
 * block. When a block exists it wins, so visitors never see two FAQ
 * sections (and duplicate FAQPage schema is never emitted).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Does the post content contain a FAQ (block or hand-written HTML)?
 *
 * Recognises:
 *   - the blog-pro/faq block: `<!-- wp:blog-pro/faq … -->` (self-closing
 *     or wrapping)
 *   - hand-written FAQ HTML: a `.faq-group` wrapper containing <details>
 *     items (existing convention, e.g. post 208)
 *
 * @param int $post_id
 * @return bool
 */
function blogpro_faq_block_in_content( $post_id ) {
	$content = strtolower( (string) get_post_field( 'post_content', $post_id ) );
	return false !== strpos( $content, '<!-- wp:blog-pro/faq' ) ||
	       false !== strpos( $content, 'faq-group' ) ||
	       false !== strpos( $content, 'faq-accordion' );
}

/**
 * the_content filter — append the metabox FAQ when appropriate.
 *
 * Rules:
 *   - single posts only (not pages, archives, feeds, excerpts)
 *   - only inside the main loop (no Related Posts widgets etc.)
 *   - content has no blog-pro/faq block already
 *   - the metabox has at least one valid item
 *
 * @param string $content
 * @return string
 */
function blogpro_faq_content_filter( $content ) {
	if ( '' === trim( (string) $content ) ) {
		return $content;
	}
	if ( is_admin() || ! is_singular( 'post' ) || is_feed() || ! in_the_loop() ) {
		return $content;
	}

	if ( ! apply_filters( 'blogpro_faq_auto_append', true, get_the_ID() ) ) {
		return $content;
	}

	$post_id   = get_the_ID();
	$items     = blogpro_faq_for_post( $post_id );
	if ( ! $items || blogpro_faq_block_in_content( $post_id ) ) {
		return $content;
	}

	$block = '<!-- wp:blog-pro/faq {"title":"' . esc_attr__( 'Frequently Asked Questions', 'blog-pro' ) . '","items":' . wp_json_encode( $items ) . '} /-->';

	return $content . "\n\n" . do_blocks( $block );
}
add_filter( 'the_content', 'blogpro_faq_content_filter', 20 );

/**
 * Keep the metabox save handler in sync: never auto-append a block when
 * the content already has one. (Used as a shared helper; the save handler
 * in blocks/class-blogpro-block.php applies the same inline check.)
 *
 * @param string $content
 * @return bool
 */
function blogpro_faq_content_has_block( $content ) {
	return false !== strpos( (string) $content, '<!-- wp:blog-pro/faq' );
}

/**
 * Gate the legacy blogpro_faq_block() render helper: false when the post
 * content already has a blog-pro/faq block.
 *
 * @param bool $render
 * @param int  $post_id
 * @return bool
 */
function blogpro_faq_suppress_duplicate( $render, $post_id ) {
	if ( $render && blogpro_faq_block_in_content( $post_id ) ) {
		return false;
	}
	return $render;
}
add_filter( 'blogpro_faq_auto_append', 'blogpro_faq_suppress_duplicate', 10, 2 );

/* ---------------------------------------------------------------------
 * One-time migration: replace hand-written `<div class="faq-group"><details>…`
 * FAQ markup in existing posts with the blog-pro/faq block.
 *
 * Used from a one-off admin page (Tools → Blog Pro FAQ Migration) that
 * runs the same batched-AJAX pattern as the image optimizer, so it is
 * safe on shared hosting and resumeable (already-converted posts skipped).
 * ------------------------------------------------------------------- */

/**
 * Extract FAQ items from `<div class="faq-group">…</div>`.
 *
 * Handles nested <details> in both single-line and multi-line forms:
 *   <details> <summary>Q</summary> <p>A</p> </details>
 *   <details>\n<summary>Q</summary>\n<p>A</p>\n</details>
 *
 * @param string $content
 * @return array<int, array{question: string, answer: string}>
 */
function blogpro_faq_extract_group( $content ) {
	$items = array();
	if ( ! preg_match( '/<div\s+class="faq-group"[^>]*>(.*?)<\/div>\s*<!--\s*\/wp:html\s*-->/is', $content, $m ) ) {
		if ( ! preg_match( '/<div\s+class="faq-group"[^>]*>(.*?)<\/div>/is', $content, $m ) ) {
			return $items;
		}
	}
	$inner = $m[1];
	if ( ! preg_match_all( '/<details[^>]*>(.*?)<\/details>/is', $inner, $details ) ) {
		return $items;
	}
	foreach ( $details[1] as $d ) {
		if ( ! preg_match( '/<summary[^>]*>(.*?)<\/summary>/is', $d, $qs ) ) {
			continue;
		}
		$q = trim( wp_strip_all_tags( html_entity_decode( $qs[1], ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
		if ( '' === $q ) {
			continue;
		}
		$body = preg_replace( '/<summary[^>]*>.*?<\/summary>/is', '', $d );
		$a = trim( wp_strip_all_tags( html_entity_decode( $body, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
		if ( '' === $a ) {
			continue;
		}
		$items[] = array( 'question' => $q, 'answer' => $a );
	}
	return $items;
}

/**
 * Replace the faq-group HTML in a post's content with the block.
 *
 * @param string $content
 * @param array  $items
 * @return string
 */
function blogpro_faq_replace_with_block( $content, $items ) {
	$block = '<!-- wp:blog-pro/faq {"title":"' . esc_attr__( 'Frequently Asked Questions', 'blog-pro' ) . '","items":' . wp_json_encode( $items ) . '} /-->';

	// Strip a FAQ heading block sitting directly before the faq-group
	// wrapper, including an optional wp:html block that may wrap the
	// wrapper. Matches h2/h3/h4 with text "Frequently asked questions"
	// / "FAQ" / "faqs".
	$content = preg_replace(
		'/(<!--\s*wp:heading[^>]*-->\s*<h[234][^>]*>\s*(?:Frequently\s*asked\s*questions?|faqs?|FAQ?)\s*<\/h[234]>\s*<!--\s*\/wp:heading\s*-->)\s*(?=(?:<!--\s*wp:html\s*-->)?\s*<div\s+class="faq-group")/is',
		'',
		$content
	);
	// Same, but tolerate the wrapper block's opening/closing markers
	// sitting between the heading and the div.
	$content = preg_replace(
		'/(<h[234][^>]*>\s*(?:Frequently\s*asked\s*questions?|faqs?|FAQ?)\s*<\/h[234]>\s*<!--\s*\/wp:heading\s*-->)\s*(?=(?:<!--\s*wp:html\s*-->)?\s*<div\s+class="faq-group")/is',
		'',
		$content
	);

	$replaced = preg_replace( '/<div\s+class="faq-group"[^>]*>.*?<\/div>\s*<!--\s*\/wp:html\s*-->/is', $block, $content, 1, $n );
	if ( ! $n ) {
		$replaced = preg_replace( '/<div\s+class="faq-group"[^>]*>.*?<\/div>/is', $block, $content, 1, $n );
	}
	return array( $replaced, $n );
}

/**
 * Migrate one post (or report only). Sets `_blogpro_faq_migrated` so the
 * migration can be resumed across batches.
 *
 * @param int  $post_id
 * @param bool $dry_run
 * @return array{ok: bool, action: string, reason: string}
 */
function blogpro_faq_migrate_post( $post_id, $dry_run = true ) {
	$content = (string) get_post_field( 'post_content', $post_id );

	if ( blogpro_faq_block_in_content( $post_id ) && ! preg_match( '/<div\s+class="faq-group"/i', $content ) ) {
		return array( 'ok' => false, 'action' => 'skipped', 'reason' => 'already-block' );
	}
	if ( get_post_meta( $post_id, '_blogpro_faq_migrated', true ) ) {
		return array( 'ok' => false, 'action' => 'skipped', 'reason' => 'migrated-before' );
	}
	if ( ! preg_match( '/<div\s+class="faq-group"/i', $content ) ) {
		return array( 'ok' => false, 'action' => 'skipped', 'reason' => 'no-faq' );
	}

	$items = blogpro_faq_extract_group( $content );
	if ( ! $items ) {
		return array( 'ok' => false, 'action' => 'skipped', 'reason' => 'no-items' );
	}

	if ( $dry_run ) {
		return array( 'ok' => true, 'action' => 'convert', 'reason' => '' );
	}

	list( $new_content, $n ) = blogpro_faq_replace_with_block( $content, $items );
	if ( ! $n ) {
		return array( 'ok' => false, 'action' => 'skipped', 'reason' => 'replace-failed' );
	}

	wp_update_post( array(
		'ID'           => $post_id,
		'post_content' => $new_content,
	) );
	update_post_meta( $post_id, '_blogpro_faq_migrated', time() );

	return array( 'ok' => true, 'action' => 'converted', 'reason' => '' );
}

/**
 * Candidate ids: posts containing a faq-group wrapper (deterministic,
 * offset pagination, no found_rows).
 *
 * @param int $offset
 * @param int $limit
 * @return array<int>
 */
function blogpro_faq_migrate_candidates( $offset, $limit ) {
	global $wpdb;
	$ids = array();
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_type = 'post' AND post_status = 'publish'
		   AND post_content LIKE %s
		 ORDER BY ID ASC
		 LIMIT %d OFFSET %d",
		'%faq-group%', $limit, $offset
	) );
	foreach ( (array) $rows as $r ) {
		$ids[] = (int) $r->ID;
	}
	return $ids;
}

function blogpro_faq_migrate_count() {
	check_ajax_referer( 'blogpro_faq_migrate', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	global $wpdb;
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_content LIKE '%faq-group%'" );
	wp_send_json_success( array( 'total' => $total ) );
}
add_action( 'wp_ajax_blogpro_faq_migrate_count', 'blogpro_faq_migrate_count' );

function blogpro_faq_migrate_batch() {
	check_ajax_referer( 'blogpro_faq_migrate', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	$offset  = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
	$batch   = isset( $_POST['batch'] ) ? max( 1, min( 20, absint( $_POST['batch'] ) ) ) : 5;
	$dry_run = ! empty( $_POST['dry_run'] );

	$ids = blogpro_faq_migrate_candidates( $offset, $batch );

	$results = array(
		'converted' => 0,
		'skipped'   => 0,
		'processed' => count( $ids ),
		'more'      => count( $ids ) === $batch,
	);
	foreach ( $ids as $id ) {
		$r = blogpro_faq_migrate_post( $id, $dry_run );
		if ( 'converted' === $r['action'] ) {
			$results['converted']++;
		} else {
			$results['skipped']++;
		}
	}

	wp_send_json_success( $results );
}
add_action( 'wp_ajax_blogpro_faq_migrate_batch', 'blogpro_faq_migrate_batch' );

function blogpro_faq_migrate_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Blog Pro FAQ Migration', 'blog-pro' ); ?></h1>
		<p><?php esc_html_e( 'Replaces hand-written .faq-group <details> markup in existing posts with the blog-pro/faq block. Runs in small batches (safe for shared hosting), skips already-converted posts, and is safe to re-run.', 'blog-pro' ); ?></p>
		<div id="blogpro-faq-migrate-status" class="notice notice-info"><p><?php esc_html_e( 'Press Start to scan for posts to convert.', 'blog-pro' ); ?></p></div>
		<button id="blogpro-faq-migrate-start" class="button button-primary"><?php esc_html_e( 'Start Migration', 'blog-pro' ); ?></button>
		<button id="blogpro-faq-migrate-dry" class="button"><?php esc_html_e( 'Dry Run', 'blog-pro' ); ?></button>
		<p id="blogpro-faq-migrate-progress" class="description"></p>
	</div>
	<script>
	(function () {
		var status = document.getElementById('blogpro-faq-migrate-status');
		var progress = document.getElementById('blogpro-faq-migrate-progress');
		var start = document.getElementById('blogpro-faq-migrate-start');
		var dry = document.getElementById('blogpro-faq-migrate-dry');
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'blogpro_faq_migrate' ) ); ?>;
		var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var batch = 5, running = false, total = 0, converted = 0, skipped = 0;

		function request( action, data, cb ) {
			var body = new URLSearchParams();
			body.set('action', action);
			body.set('nonce', nonce);
			body.set('batch', batch);
			for ( var k in data ) body.set( k, data[k] );
			fetch( ajax, { method: 'POST', credentials: 'same-origin', body: body } )
				.then(function ( r ) { return r.json(); })
				.then(function ( j ) {
					if ( j && j.success ) cb( j.data );
					else throw new Error( j && j.data ? j.data : 'Request failed' );
				})
				.catch(function ( e ) {
					status.className = 'notice notice-error';
					status.innerHTML = '<p>' + e.message + '</p>';
					running = false;
				});
		}

		function run( dry ) {
			running = true;
			start.disabled = dry.disabled = true;
			request( 'blogpro_faq_migrate_count', {}, function ( data ) {
				total = data.total;
				if ( ! total ) {
					status.className = 'notice notice-success';
					status.innerHTML = '<p><?php esc_html_e( 'No posts with faq-group markup found.', 'blog-pro' ); ?></p>';
					running = false;
					start.disabled = dry.disabled = false;
					return;
				}
				progress.textContent = '0 / ' + total;
				convert( 0, dry );
			} );
		}

		function convert( offset, dry ) {
			request( 'blogpro_faq_migrate_batch', { offset: offset, dry_run: dry ? '1' : '0' }, function ( data ) {
				converted += data.converted;
				skipped += data.skipped;
				progress.textContent = 'Processed ' + ( offset + data.processed ) + ' / ' + total + ' — converted: ' + converted + ', skipped: ' + skipped;
				if ( data.more ) {
					convert( offset + batch, dry );
				} else {
					status.className = 'notice notice-success';
					status.innerHTML = '<p><?php esc_html_e( 'Migration complete.', 'blog-pro' ); ?></p>';
					running = false;
					start.disabled = dry.disabled = false;
				}
			} );
		}

		start.addEventListener( 'click', function () { if ( !running ) run( false ); } );
		dry.addEventListener( 'click', function () { if ( !running ) run( true ); } );
	})();
	</script>
	<?php
}

function blogpro_faq_migrate_menu() {
	add_management_page(
		__( 'FAQ Migration', 'blog-pro' ),
		__( 'FAQ Migration', 'blog-pro' ),
		'manage_options',
		'blogpro-faq-migrate',
		'blogpro_faq_migrate_page'
	);
}
add_action( 'admin_menu', 'blogpro_faq_migrate_menu' );
