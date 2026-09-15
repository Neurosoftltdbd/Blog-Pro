<?php
/**
 * Author-controlled GEO content boxes: Key Takeaways + Sources.
 *
 * Both are save-only metaboxes — post_content is never mutated (revisions
 * are disabled theme-wide, so content-appends would be unrecoverable).
 * Cards render at display time through the_content:
 *
 *   - Key Takeaways → top of the post, after the first paragraph (same
 *     slot idea as the mobile TOC). Bullets live in <ul><li>, so the
 *     "first </p>" target stays the real intro paragraph.
 *   - Sources & References → end of the post, before the auto-FAQ block
 *     (FAQ appends at priority 20).
 *
 * Injected headings carry an explicit id= on purpose: the TOC annotation
 * pass (blogpro_toc_annotate_headings, @12) skips id-present headings, so
 * card headings never shift the ordinal pairing between raw-content scan
 * results and rendered markup — and never enter the TOC list (raw scan
 * can't see them).
 *
 * Schema: takeaways → BlogPosting.abstract, sources → citation[] —
 * see blogpro_schema_blogposting() in inc/schema.php.
 *
 * Also: table scroll wrappers (mobile UX — wide tables break layout).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------------
 * Shared metabox plumbing
 * ------------------------------------------------------------------- */

/**
 * Guards shared by all Blog Pro metabox saves.
 */
function blogpro_metabox_save_guard( $post_id, $nonce_name, $nonce_action ) {
	if ( ! isset( $_POST[ $nonce_name ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ), $nonce_action ) ) {
		return false;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return false;
	}
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return false;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return false;
	}
	return true;
}

/**
 * Meta boxes attach to every public post type that has an editor.
 *
 * @return string[]
 */
function blogpro_metabox_post_types() {
	$types = array();
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
		if ( post_type_supports( $type->name, 'editor' ) ) {
			$types[] = $type->name;
		}
	}
	return $types;
}

/* ---------------------------------------------------------------------
 * Key Takeaways
 * ------------------------------------------------------------------- */

function blogpro_takeaways_add_metabox() {
	foreach ( blogpro_metabox_post_types() as $type ) {
		add_meta_box(
			'blogpro-takeaways',
			__( 'Key Takeaways', 'blog-pro' ),
			'blogpro_takeaways_render_metabox',
			$type,
			'side',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'blogpro_takeaways_add_metabox' );

function blogpro_takeaways_render_metabox( $post ) {
	wp_nonce_field( 'blogpro_takeaways_save', 'blogpro_takeaways_nonce' );
	?>
	<p class="description"><?php esc_html_e( 'One takeaway per line. Shown as a scannable summary box near the top of the post and used as the article abstract for search engines and AI answer engines.', 'blog-pro' ); ?></p>
	<textarea name="blogpro_takeaways" rows="6" class="widefat" placeholder="<?php esc_attr_e( 'Hybrid buses cut fuel costs by 40%.', 'blog-pro' ); ?>"><?php echo esc_textarea( (string) get_post_meta( $post->ID, '_blogpro_takeaways', true ) ); ?></textarea>
	<?php
}

function blogpro_takeaways_save( $post_id ) {
	if ( ! blogpro_metabox_save_guard( $post_id, 'blogpro_takeaways_nonce', 'blogpro_takeaways_save' ) ) {
		return;
	}
	$raw = isset( $_POST['blogpro_takeaways'] ) ? sanitize_textarea_field( wp_unslash( $_POST['blogpro_takeaways'] ) ) : '';
	if ( '' === trim( $raw ) ) {
		delete_post_meta( $post_id, '_blogpro_takeaways' );
		return;
	}
	update_post_meta( $post_id, '_blogpro_takeaways', $raw );
}
add_action( 'save_post', 'blogpro_takeaways_save', 20 );

/**
 * Parse takeaways into plain strings, one per non-empty line.
 *
 * @param int $post_id
 * @return string[]
 */
function blogpro_takeaways_for_post( $post_id ) {
	$raw   = (string) get_post_meta( $post_id, '_blogpro_takeaways', true );
	$items = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );
		// allow optional "- " bullet prefix from authors who paste lists
		$line = preg_replace( '/^[-*•]\s*/u', '', $line );
		if ( '' !== $line ) {
			$items[] = $line;
		}
	}
	return $items;
}

/**
 * Render the Key Takeaways card. Public: llms.txt reuses it as summary text.
 *
 * @param int $post_id
 * @return string HTML or '' when nothing to show.
 */
function blogpro_takeaways_card( $post_id ) {
	if ( ! apply_filters( 'blogpro_takeaways_auto_show', true, $post_id ) ) {
		return '';
	}
	$items = blogpro_takeaways_for_post( $post_id );
	if ( ! $items ) {
		return '';
	}

	$lis = '';
	foreach ( $items as $item ) {
		$lis .= '<li class="flex items-start gap-2.5">'
			. '<svg class="w-5 h-5 shrink-0 text-emerald-500 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>'
			. '<span class="text-sm md:text-base text-gray-800 leading-snug">' . esc_html( $item ) . '</span>'
			. '</li>';
	}

	return '<aside class="bp-takeaways my-8 lg:my-10 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 md:p-6" aria-label="' . esc_attr__( 'Key takeaways', 'blog-pro' ) . '">'
		. '<h2 id="key-takeaways" class="text-lg md:text-xl font-extrabold text-gray-900 flex items-center gap-2.5 mb-3 scroll-mt-24">'
		. '<span class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0" aria-hidden="true">'
		. '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M9 18h6M10 22h4M12 2a7 7 0 00-4 12.7c.6.5 1 1.4 1 2.3h6c0-.9.4-1.8 1-2.3A7 7 0 0012 2z"/></svg>'
		. '</span>'
		. esc_html__( 'Key Takeaways', 'blog-pro' )
		. '</h2>'
		. '<ul class="list-none! pl-0 space-y-2.5 m-0">' . $lis . '</ul>'
		. '</aside>';
}

/* ---------------------------------------------------------------------
 * Sources & References
 * ------------------------------------------------------------------- */

function blogpro_sources_add_metabox() {
	foreach ( blogpro_metabox_post_types() as $type ) {
		add_meta_box(
			'blogpro-sources',
			__( 'Sources & References', 'blog-pro' ),
			'blogpro_sources_render_metabox',
			$type,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'blogpro_sources_add_metabox' );

function blogpro_sources_render_metabox( $post ) {
	wp_nonce_field( 'blogpro_sources_save', 'blogpro_sources_nonce' );
	?>
	<p class="description"><?php esc_html_e( 'One per line, as Title => URL. Rendered as a "Sources & References" list at the end of the post and exposed as citation markup for search and AI engines.', 'blog-pro' ); ?></p>
	<textarea name="blogpro_sources" rows="5" class="widefat" placeholder="<?php esc_attr_e( 'RTA Mobility Report 2025 => https://example.org/report', 'blog-pro' ); ?>"><?php echo esc_textarea( (string) get_post_meta( $post->ID, '_blogpro_sources', true ) ); ?></textarea>
	<?php
}

function blogpro_sources_save( $post_id ) {
	if ( ! blogpro_metabox_save_guard( $post_id, 'blogpro_sources_nonce', 'blogpro_sources_save' ) ) {
		return;
	}
	$raw = isset( $_POST['blogpro_sources'] ) ? sanitize_textarea_field( wp_unslash( $_POST['blogpro_sources'] ) ) : '';
	if ( '' === trim( $raw ) ) {
		delete_post_meta( $post_id, '_blogpro_sources' );
		return;
	}
	update_post_meta( $post_id, '_blogpro_sources', $raw );
}
add_action( 'save_post', 'blogpro_sources_save', 20 );

/**
 * Parse "Label => URL" lines. Lines with no valid http(s) URL are dropped.
 *
 * @param int $post_id
 * @return array[] each array( 'label' => string, 'url' => string )
 */
function blogpro_sources_for_post( $post_id ) {
	$raw   = (string) get_post_meta( $post_id, '_blogpro_sources', true );
	$items = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || false === strpos( $line, '=>' ) ) {
			continue;
		}
		list( $label, $url ) = array_map( 'trim', explode( '=>', $line, 2 ) );
		$url = esc_url_raw( $url );
		if ( '' === $label || '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
			continue;
		}
		$items[] = array( 'label' => sanitize_text_field( $label ), 'url' => $url );
	}
	return $items;
}

/**
 * Render the Sources & References section.
 *
 * @param int $post_id
 * @return string HTML or '' when empty.
 */
function blogpro_sources_section( $post_id ) {
	if ( ! apply_filters( 'blogpro_sources_auto_show', true, $post_id ) ) {
		return '';
	}
	$items = blogpro_sources_for_post( $post_id );
	if ( ! $items ) {
		return '';
	}

	$lis = '';
	$n   = 0;
	foreach ( $items as $item ) {
		$n++;
		$lis .= '<li class="flex items-start gap-2">'
			. '<sup class="text-indigo-600 font-bold shrink-0">[' . $n . ']</sup>'
			. '<a href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener noreferrer nofollow" class="text-indigo-600 hover:text-indigo-800 underline underline-offset-2 wrap-break-word">' . esc_html( $item['label'] ) . '</a>'
			. '</li>';
	}

	return '<section class="bp-sources mt-14 lg:mt-16 rounded-2xl border border-gray-200 bg-gray-50/60 p-5 md:p-6" aria-label="' . esc_attr__( 'Sources and references', 'blog-pro' ) . '">'
		. '<h2 id="sources" class="text-lg md:text-xl font-extrabold text-gray-900 flex items-center gap-2.5 mb-3 scroll-mt-24">'
		. '<span class="w-7 h-7 rounded-lg bg-gray-900 text-white flex items-center justify-center shrink-0" aria-hidden="true">'
		. '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M4 19.5A2.5 2.5 0 016.5 17H20M4 19.5A2.5 2.5 0 006.5 22H20V2H6.5A2.5 2.5 0 004 4.5v15z"/></svg>'
		. '</span>'
		. esc_html__( 'Sources & References', 'blog-pro' )
		. '</h2>'
		. '<ol class="list-none! pl-0 space-y-2 m-0">' . $lis . '</ol>'
		. '</section>';
}

/* ---------------------------------------------------------------------
 * REST exposure (web-mcp / block editor parity)
 * ------------------------------------------------------------------- */
function blogpro_content_blocks_register_meta() {
	$args = array(
		'type' => 'string', 'single' => true, 'show_in_rest' => true,
		'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		},
	);
	foreach ( blogpro_metabox_post_types() as $type ) {
		register_post_meta( $type, '_blogpro_takeaways', $args );
		register_post_meta( $type, '_blogpro_sources', $args );
	}
}
add_action( 'init', 'blogpro_content_blocks_register_meta' );

/* ---------------------------------------------------------------------
 * Front-end injection — takeaways top, sources bottom.
 * Priority 15: after heading annotation (12) and the mobile TOC (13) —
 * the TOC card lands after the first </p> first, then takeaways is
 * inserted at the same slot, giving intro → takeaways → TOC → body.
 * The auto-FAQ (20) still appends after our sources section.
 * ------------------------------------------------------------------- */
function blogpro_content_blocks_inject( $content ) {
	if ( is_admin() || is_feed() || is_embed() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( ! is_singular( array( 'post', 'page' ) ) ) {
		return $content;
	}

	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return $content;
	}

	$takeaways = blogpro_takeaways_card( $post_id );
	if ( '' !== $takeaways ) {
		// Insert after the first paragraph so the lede reads first;
		// fall back to prepending when the post opens without one.
		$pos = strpos( $content, '</p>' );
		if ( false !== $pos ) {
			$content = substr_replace( $content, "\n" . $takeaways . "\n", $pos + 4, 0 );
		} else {
			$content = $takeaways . "\n" . $content;
		}
	}

	$sources = blogpro_sources_section( $post_id );
	if ( '' !== $sources ) {
		$content = $content . "\n" . $sources;
	}

	return $content;
}
add_filter( 'the_content', 'blogpro_content_blocks_inject', 15 );

/* ---------------------------------------------------------------------
 * Table scroll wrappers — wide tables make the whole page scroll
 * horizontally on phones. Wrap bare <table>s in a scroll container.
 * Gutenberg already wraps block tables (.wp-block-table): skip those.
 * ------------------------------------------------------------------- */
function blogpro_wrap_tables( $content ) {
	if ( false === stripos( (string) $content, '<table' ) ) {
		return $content;
	}
	// Whole table pairs at once — wrapping opens/closes in the same pass,
	// so the count always balances. Tables aren't nested, so the lazy
	// non-greedy match ends at the correct </table>.
	return preg_replace_callback(
		'/(<table\b([^>]*)>.*?<\/table>)/is',
		function ( $m ) {
			if ( false !== stripos( $m[2], 'wp-block-table' ) ) {
				return $m[1]; // Gutenberg wraps these itself
			}
			return '<div class="bp-table-scroll overflow-x-auto">' . $m[1] . '</div>';
		},
		$content
	);
}
add_filter( 'the_content', 'blogpro_wrap_tables', 11 );
