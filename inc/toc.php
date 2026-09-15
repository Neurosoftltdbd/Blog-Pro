<?php
/**
 * Automatic Table of Contents.
 *
 * Desktop (lg+): sticky right sidebar rendered by single.php around the
 * content column. Mobile: injected after the first paragraph by a
 * the_content filter, collapsed by default (details/summary).
 *
 * Headings and anchor ids come from blogpro_toc_headings()
 * (blocks/class-blogpro-block.php) — the same cache the the_content
 * annotation pass uses, so links always match real anchors.
 *
 * The manual blog-pro/toc block renders through blogpro_toc_nav() too,
 * so all three surfaces share one markup source. When a post already
 * contains the block, the automatic TOC stays off (author intent wins).
 *
 * Structured data: WebPageElement hasPart items are added to the
 * BlogPosting graph node (see blogpro_schema_blogposting in schema.php).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Should the automatic TOC show for this post?
 *
 * Rules: singular 'post' type, at least `blogpro_toc_min_headings` (3)
 * H2/H3 sections, no manual blog-pro/toc block in the content, and the
 * per-post `_blogpro_toc_disable` meta not set.
 *
 * @param int|null $post_id Defaults to the current post in the loop.
 */
function blogpro_toc_auto_enabled( $post_id = null ) {
	static $memo = array();

	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		return false;
	}
	if ( isset( $memo[ $post_id ] ) ) {
		return $memo[ $post_id ];
	}

	$enabled = false;
	if ( '1' !== get_post_meta( $post_id, '_blogpro_toc_disable', true ) ) {
		$content = (string) get_post_field( 'post_content', $post_id );
		if ( false === strpos( $content, '<!-- wp:blog-pro/toc' ) ) {
			$min   = absint( apply_filters( 'blogpro_toc_min_headings', 3 ) );
			$heads = blogpro_toc_headings( $post_id );
			$enabled = count( $heads ) >= max( 2, $min );
		}
	}
	$memo[ $post_id ] = $enabled;
	return $enabled;
}

/**
 * Render a TOC <nav> card.
 *
 * @param array $args {
 *     @type int        $post_id     Headings source post (default: current).
 *     @type string     $title       Card heading. '' hides it.
 *     @type array|null $headings    Pre-built list (block override). null = look up.
 *     @type bool       $collapsible Mobile variant — <details>, collapsed by default.
 *     @type bool       $compact     Tighter type/padding for the sidebar column.
 *     @type bool       $progress    Reading-progress bar (driven by view.js).
 *     @type string     $class       Extra classes on the <nav> (e.g. 'lg:hidden').
 *     @type string     $attrs       Full attribute string for <nav> (block wrapper).
 * }
 * @return string HTML, or '' when there are no headings.
 */
function blogpro_toc_nav( $args = array() ) {
	$defaults = array(
		'post_id'     => 0,
		'title'       => __( 'Table of Contents', 'blog-pro' ),
		'headings'    => null,
		'collapsible' => false,
		'compact'     => false,
		'progress'    => false,
		'class'       => '',
		'attrs'       => '',
	);
	$a = wp_parse_args( $args, $defaults );

	$heads = $a['headings'];
	if ( null === $heads ) {
		$heads = blogpro_toc_headings( $a['post_id'] );
	}
	if ( empty( $heads ) || ! is_array( $heads ) ) {
		return '';
	}

	$attrs = (string) $a['attrs'];
	if ( '' !== $attrs ) {
		// Block wrapper attrs (already escaped by get_block_wrapper_attributes()).
		// Merge .bp-toc in so the view script finds this nav.
		if ( preg_match( '/\bclass=(["\'])(.*?)\1/i', $attrs ) ) {
			$attrs = preg_replace( '/\bclass=(["\'])(.*?)\1/i', 'class="bp-toc $2"', $attrs, 1 );
		} else {
			$attrs .= ' class="bp-toc"';
		}
		$nav_open = '<nav ' . $attrs . ' aria-label="' . esc_attr__( 'Table of contents', 'blog-pro' ) . '">';
	} else {
		$nav_class = 'bp-toc' . ( $a['class'] ? ' ' . $a['class'] : '' );
		$nav_open  = '<nav class="' . esc_attr( $nav_class ) . '" aria-label="' . esc_attr__( 'Table of contents', 'blog-pro' ) . '">';
	}

	$pad      = $a['compact'] ? 'p-4' : 'p-5 md:p-6';
	$text     = $a['compact'] ? 'text-[13px]' : 'text-sm md:text-[15px]';
	$li_space = $a['compact'] ? 'space-y-0.5' : 'space-y-1';

	$icon = '<span class="w-7 h-7 md:w-8 md:h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0" aria-hidden="true">'
		. '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M4 6h16M4 12h10M4 18h7"/></svg>'
		. '</span>';

	$badge = '<span class="text-[11px] md:text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 md:px-2.5 md:py-1 rounded-full shrink-0">'
		. (int) count( $heads ) . ' ' . esc_html__( 'sections', 'blog-pro' ) . '</span>';

	$bar = '';
	if ( $a['progress'] ) {
		$bar = '<div class="h-1 rounded-full bg-indigo-100 overflow-hidden mb-3" aria-hidden="true">'
			. '<div class="bp-toc__bar h-full bg-indigo-600" style="width:0%"></div></div>';
	}

	$list = '<ol class="relative ' . $li_space . '">' . "\n";
	foreach ( $heads as $i => $head ) {
		$indent = 3 === (int) $head['level'] ? ' class="pl-4 md:pl-6"' : '';
		$list  .= "\t" . '<li' . $indent . '>'
			. '<a href="#' . esc_attr( $head['id'] ) . '" class="bp-toc__link group flex items-start gap-2.5 md:gap-3 py-1.5 px-2 -mx-2 rounded-lg transition-colors duration-200 hover:bg-indigo-50 no-underline">'
			. '<span class="mt-0.5 shrink-0 w-5 h-5 rounded-md bg-indigo-100 text-indigo-700 text-[11px] font-bold flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-200" aria-hidden="true">' . (int) ( $i + 1 ) . '</span>'
			. '<span class="' . $text . ' font-medium leading-snug group-hover:text-indigo-700 transition-colors duration-200">' . esc_html( $head['text'] ) . '</span>'
			. '</a></li>' . "\n";
	}
	$list .= '</ol>';

	$card = '<div class="bg-linear-to-br from-white to-indigo-50/50 border border-indigo-100 rounded-2xl ' . $pad . ' shadow-sm">' . $bar;

	if ( $a['collapsible'] ) {
		$card .= '<details class="group">'
			. '<summary class="flex items-center justify-between gap-3 cursor-pointer list-none [&::-webkit-details-marker]:hidden select-none">'
			. '<span class="flex items-center gap-2.5 min-w-0">' . $icon
			. '<span class="text-base md:text-lg font-bold text-gray-900 truncate">' . esc_html( $a['title'] ) . '</span></span>'
			. '<span class="flex items-center gap-2 shrink-0">' . $badge
			. '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-indigo-600 transition-transform duration-300 group-open:rotate-180" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>'
			. '</span></summary>'
			. '<div class="mt-3 pt-3 border-t border-indigo-100">' . $list . '</div>'
			. '</details>';
	} else {
		$card .= '<div class="flex items-center justify-between gap-3 mb-3">'
			. ( $a['title'] ? '<span class="flex items-center gap-2.5 min-w-0">' . $icon
				. '<span class="text-lg md:text-xl font-bold text-gray-900 truncate">' . esc_html( $a['title'] ) . '</span></span>' : '<span></span>' )
			. $badge . '</div>' . $list;
	}
	$card .= '</div>';

	return $nav_open . $card . '</nav>';
}

/**
 * Mobile placement: insert the collapsible TOC after the first paragraph.
 * Runs after heading annotation (12). Desktop sidebar comes from single.php.
 */
function blogpro_toc_mobile_inject( $content ) {
	if ( is_admin() || is_feed() || is_embed() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$post_id = get_the_ID();
	if ( ! blogpro_toc_auto_enabled( $post_id ) ) {
		return $content;
	}

	$nav = blogpro_toc_nav( array(
		'post_id'     => $post_id,
		'collapsible' => true,
		'progress'    => true,
		'class'       => 'lg:hidden my-8',
	) );
	if ( '' === $nav ) {
		return $content;
	}

	$pos = strpos( $content, '</p>' );
	if ( false === $pos ) {
		return $nav . $content; // no paragraph — prepend
	}
	return substr_replace( $content, "\n" . $nav . "\n", $pos + 4, 0 );
}
add_filter( 'the_content', 'blogpro_toc_mobile_inject', 13 );

/**
 * TOC view script for the automatic (no-block) case — same file the
 * blog-pro/toc block registers as its viewScript.
 */
function blogpro_toc_enqueue() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	if ( ! blogpro_toc_auto_enabled( get_queried_object_id() ) ) {
		return;
	}
	wp_enqueue_script( 'blogpro-toc', BLOGPRO_URI . '/blocks/toc/view.js', array(), BLOGPRO_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'blogpro_toc_enqueue', 20 );

/**
 * WebPageElement hasPart items for the BlogPosting schema node.
 *
 * @param int $post_id
 * @return array
 */
function blogpro_toc_schema_parts( $post_id ) {
	if ( ! blogpro_toc_auto_enabled( $post_id ) ) {
		return array();
	}
	$heads = blogpro_toc_headings( $post_id );
	if ( ! $heads ) {
		return array();
	}
	$base  = get_permalink( $post_id );
	$parts = array();
	foreach ( $heads as $head ) {
		$parts[] = array(
			'@type' => 'WebPageElement',
			'name'  => $head['text'],
			'url'   => $base . '#' . $head['id'],
		);
	}
	return $parts;
}
