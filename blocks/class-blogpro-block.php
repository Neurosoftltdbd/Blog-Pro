<?php
/**
 * FAQ block registration + legacy per-post integration.
 *
 * Registers the global block `blog-pro/faq` from metadata in
 * blocks/faq/block.json. The block is dynamic: render.php renders
 * server-side, index.js is the editor-only script.
 *
 * Also keeps the per-post FAQ metabox used by single.php for legacy posts:
 * when a FAQ metabox is saved and the post content does NOT already contain
 * a blog-pro/faq block, one is appended with the metabox items. The block
 * itself is fully usable in the editor/post content independent of this.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register all theme blocks from metadata.
 */
function blogpro_register_faq_block() {
	register_block_type( BLOGPRO_DIR . '/blocks/faq' );
}
add_action( 'init', 'blogpro_register_faq_block' );

function blogpro_register_toc_block() {
	register_block_type( BLOGPRO_DIR . '/blocks/toc' );
}
add_action( 'init', 'blogpro_register_toc_block' );

function blogpro_register_contact_block() {
    register_block_type( BLOGPRO_DIR . '/blocks/contact-form' );
}
add_action( 'init', 'blogpro_register_contact_block' );

/* ---------------------------------------------------------------------
 * TOC shared machinery
 *
 * Collects H2/H3 headings from the raw post content (cached per post per
 * request), assigns slugged anchor IDs, and injects both the id and
 * scroll-mt-24 (sticky header offset) into the rendered headings via a
 * the_content filter. The blog-pro/toc render.php reads the same cache,
 * so its links always match.
 * ------------------------------------------------------------------- */
/**
 * Byte ranges of every <details>…</details> element in $html (no nesting
 * support — fine for accordion markup). Headings inside them (FAQ questions)
 * are excluded from the TOC: hidden/collapsible content makes confusing
 * anchors, and rendered block output nests them too so scanner and annotator
 * stay aligned.
 *
 * @param string $html
 * @return array[] each [start, end]
 */
function blogpro_toc_details_spans( $html ) {
	$spans = array();
	$off   = 0;
	while ( ( $open = stripos( $html, '<details', $off ) ) !== false ) {
		$close = stripos( $html, '</details>', $open );
		if ( $close === false ) {
			break;
		}
		$spans[] = array( $open, $close + 10 );
		$off     = $close + 10;
	}
	return $spans;
}

function blogpro_toc_headings( $post_id = 0 ) {
	static $cache = array();

	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( ! $post_id ) {
		return array();
	}
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$content = (string) get_post_field( 'post_content', $post_id );
	$spans   = blogpro_toc_details_spans( $content );
	preg_match_all( '/<h([23])([^>]*)>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

	$heads = array();
	$seen  = array();
	foreach ( $matches as $m ) {
		$at = (int) $m[0][1];
		foreach ( $spans as $span ) {
			if ( $at >= $span[0] && $at < $span[1] ) {
				continue 2; // heading inside an accordion (FAQ) — not a section
			}
		}
		if ( preg_match( '/\bid=/i', $m[2][0] ) ) {
			continue; // already anchors — leave them alone
		}
		$text = trim( wp_strip_all_tags( html_entity_decode( $m[3][0], ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
		if ( '' === $text ) {
			continue;
		}
		$slug  = sanitize_title( $text );
		if ( '' === $slug ) {
			$slug = 'section';
		}
		$base = $slug;
		$n    = 1;
		while ( isset( $seen[ $slug ] ) ) {
			$n++;
			$slug = $base . '-' . $n;
		}
		$seen[ $slug ] = true;

		$heads[] = array(
			'level' => (int) $m[1][0],
			'text'  => $text,
			'id'    => $slug,
		);
	}

	$cache[ $post_id ] = $heads;
	return $heads;
}

/**
 * Inject anchor ids + smooth-scroll offset into post/page headings.
 *
 * @param string $content
 * @return string
 */
function blogpro_toc_annotate_headings( $content ) {
	if ( '' === trim( (string) $content ) || ! is_singular( array( 'post', 'page' ) ) || ! in_the_loop() ) {
		return $content;
	}

	$heads = blogpro_toc_headings();
	if ( ! $heads ) {
		return $content;
	}

	// Skip ranges must mirror blogpro_toc_headings(): accordions (FAQ
	// questions, even hand-written in raw HTML) and TOC nav link lists
	// (the automatic mobile card is injected before this pass).
	$spans   = blogpro_toc_details_spans( $content );
	if ( false !== stripos( $content, '<nav' ) ) {
		$off = 0;
		while ( ( $open = stripos( $content, '<nav', $off ) ) !== false ) {
			$close = stripos( $content, '</nav>', $open );
			if ( $close === false ) {
				break;
			}
			$spans[] = array( $open, $close + 6 );
			$off     = $close + 6;
		}
	}

	$idx   = 0;
	$count = count( $heads );

	// Offset-based rewrite (preg_replace_callback can't see byte offsets,
	// needed for the accordion/nav skip ranges). Replace from the end so
	// earlier offsets stay valid.
	preg_match_all( '/<h([23])([^>]*)>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
	$edits = array();
	foreach ( $matches as $m ) {
		$at = (int) $m[0][1];
		$skip = false;
		foreach ( $spans as $span ) {
			if ( $at >= $span[0] && $at < $span[1] ) {
				$skip = true;
				break;
			}
		}
		if ( $skip ) {
			continue;
		}
		if ( preg_match( '/\bid=/i', $m[2][0] ) ) {
			continue; // already has an id (e.g. TOC's own title) — keep as-is
		}
		$text = trim( wp_strip_all_tags( html_entity_decode( $m[3][0], ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
		if ( '' === $text || $idx >= $count ) {
			continue;
		}

		$heading = $heads[ $idx++ ];
		$attrs   = $m[2][0];

		// Merge scroll-mt-24 into an existing class attribute or add one.
		if ( preg_match( '/class=(["\'])(.*?)\1/i', $attrs, $cm ) ) {
			$attrs = str_replace( $cm[0], 'class=' . $cm[1] . 'scroll-mt-24 ' . $cm[2] . $cm[1], $attrs );
		} else {
			$attrs .= ' class="scroll-mt-24"';
		}

		$replacement = '<h' . $m[1][0] . ' id="' . esc_attr( $heading['id'] ) . '"' . $attrs . '>' . $m[3][0] . '</h' . $m[1][0] . '>';
		$edits[] = array( $at, strlen( $m[0][0] ), $replacement );
	}

	for ( $i = count( $edits ) - 1; $i >= 0; $i-- ) {
		$content = substr_replace( $content, $edits[ $i ][2], $edits[ $i ][0], $edits[ $i ][1] );
	}
	return $content;
}
add_filter( 'the_content', 'blogpro_toc_annotate_headings', 12 );

/* ---------------------------------------------------------------------
 * Legacy per-post FAQ metabox (Question => Answer lines)
 * ------------------------------------------------------------------- */
function blogpro_faq_parse( $raw ) {
	$items = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || false === strpos( $line, '=>' ) ) {
			continue;
		}
		list( $question, $answer ) = array_map( 'trim', explode( '=>', $line, 2 ) );
		if ( '' === $question || '' === $answer ) {
			continue;
		}
		$items[] = array(
			'question' => $question,
			'answer'   => $answer,
		);
	}
	return $items;
}

/**
 * Get FAQ items for a post (stored in `_blogpro_faq` meta).
 *
 * @param int $post_id
 * @return array<int, array{question: string, answer: string}>
 */
function blogpro_faq_for_post( $post_id ) {
	return blogpro_faq_parse( (string) get_post_meta( $post_id, '_blogpro_faq', true ) );
}

/**
 * Render the FAQ block for a post (legacy single.php caller).
 *
 * @param int $post_id
 */
function blogpro_faq_block( $post_id = null ) {
	if ( null === $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id ) {
		return;
	}

	// If the post content already has a blog-pro/faq block, it wins —
	// don't render the legacy metabox FAQ on top of it (no duplicates).
	// Filterable so themes/plugins can override the decision.
	if ( ! apply_filters( 'blogpro_faq_auto_append', true, $post_id ) ) {
		return;
	}

	$items = blogpro_faq_for_post( $post_id );
	if ( ! $items ) {
		return;
	}
	echo do_blocks( '<!-- wp:blog-pro/faq {"title":"' . esc_attr__( 'Frequently Asked Questions', 'blog-pro' ) . '","items":' . wp_json_encode( $items ) . '} /-->' );
}

function blogpro_faq_add_metabox() {
	add_meta_box(
		'blogpro-faq',
		__( 'FAQ', 'blog-pro' ),
		'blogpro_faq_render_metabox',
		'post',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'blogpro_faq_add_metabox' );

function blogpro_faq_render_metabox( $post ) {
	wp_nonce_field( 'blogpro_faq_save', 'blogpro_faq_nonce' );
	?>
	<p class="description"><?php esc_html_e( 'Legacy field: one item per line, Question => Answer. Rendered as a blog-pro/faq block at the end of this post. Prefer adding the FAQ block directly in the content.', 'blog-pro' ); ?></p>
	<textarea name="blogpro_faq" rows="6" class="widefat" placeholder="<?php esc_attr_e( "How do I get started? => Read the guide below.", 'blog-pro' ); ?>"><?php echo esc_textarea( (string) get_post_meta( $post->ID, '_blogpro_faq', true ) ); ?></textarea>
	<?php
}

function blogpro_faq_save( $post_id ) {
	if ( ! isset( $_POST['blogpro_faq_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['blogpro_faq_nonce'] ) ), 'blogpro_faq_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$raw = isset( $_POST['blogpro_faq'] ) ? sanitize_textarea_field( wp_unslash( $_POST['blogpro_faq'] ) ) : '';
	if ( '' === trim( $raw ) ) {
		delete_post_meta( $post_id, '_blogpro_faq' );
		return;
	}
	update_post_meta( $post_id, '_blogpro_faq', $raw );

	// If the post content has no FAQ block yet, append one with these items.
	$content = get_post_field( 'post_content', $post_id );
	if ( false === strpos( (string) $content, '<!-- wp:blog-pro/faq' ) ) {
		$items  = blogpro_faq_parse( $raw );
		$block  = '<!-- wp:blog-pro/faq {"title":"' . esc_attr__( 'Frequently Asked Questions', 'blog-pro' ) . '","items":' . wp_json_encode( $items ) . '} /-->';
		wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => $content . "\n\n" . $block,
		) );
	}
}
add_action( 'save_post', 'blogpro_faq_save' );
