<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function blogpro_reading_time( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	$content = get_post_field( 'post_content', $post_id );
	$words   = str_word_count( wp_strip_all_tags( $content ) );
	$minutes = max( 1, (int) ceil( $words / 200 ) );
	return sprintf( _n( '%d min read', '%d min read', $minutes, 'blog-pro' ), $minutes );
}

function blogpro_posted_on() {
	echo '<span class="text-gray-500 text-sm">' . esc_html( get_the_modified_date() ) . '</span>';
	echo ' &#10625; <span class="text-gray-500 text-sm">' . esc_html( blogpro_reading_time() ) . '</span>';
	echo ' &#10625; <span class="text-gray-500 text-sm">' . esc_html__( 'By', 'blog-pro' ) . ' <a href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '" class="text-indigo-600 hover:text-indigo-800 font-medium no-underline">' . esc_html( get_the_author() ) . '</a></span>';
	$categories = get_the_category();
	if ( ! empty( $categories ) ) {
		$category = $categories[0];
		echo ' &#10625; <span class="text-gray-500 text-sm">' . esc_html__( 'On', 'blog-pro' ) . ' <a href="' . esc_url( get_category_link( $category ) ) . '" class="text-indigo-600 hover:text-indigo-800 font-medium no-underline">' . esc_html( $category->name ) . '</a></span>';
	}
}

/**
 * Prev/next post navigation — keeps readers (and PageRank) flowing through
 * the archive instead of bouncing at the post end. Built on
 * get_previous_post()/get_next_post() so markup is fully controllable
 * (*_post_link()'s %link can't carry classes).
 */
function blogpro_post_nav() {
	$link_class = 'group block no-underline rounded-2xl border border-gray-200 p-5 hover:border-indigo-300 hover:shadow-md transition-all duration-200';
	$prev_post  = get_previous_post( true );
	$next_post  = get_next_post( true );

	if ( ! $prev_post && ! $next_post ) {
		return;
	}

	echo '<nav class="grid grid-cols-1 sm:grid-cols-2 gap-4 my-12" aria-label="' . esc_attr__( 'Post navigation', 'blog-pro' ) . '">';

	if ( $prev_post instanceof WP_Post ) {
		echo '<a href="' . esc_url( get_permalink( $prev_post ) ) . '" rel="prev" class="' . $link_class . '">'
			. '<span class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">&larr; ' . esc_html__( 'Previous', 'blog-pro' ) . '</span>'
			. '<span class="block font-bold text-gray-900 leading-snug line-clamp-2 group-hover:text-indigo-600 transition-colors">' . esc_html( get_the_title( $prev_post ) ) . '</span>'
			. '</a>';
	} else {
		echo '<span aria-hidden="true"></span>';
	}

	if ( $next_post instanceof WP_Post ) {
		echo '<a href="' . esc_url( get_permalink( $next_post ) ) . '" rel="next" class="' . $link_class . '">'
			. '<span class="flex items-center justify-end gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">' . esc_html__( 'Next', 'blog-pro' ) . ' &rarr;</span>'
			. '<span class="block text-right font-bold text-gray-900 leading-snug line-clamp-2 group-hover:text-indigo-600 transition-colors">' . esc_html( get_the_title( $next_post ) ) . '</span>'
			. '</a>';
	}

	echo '</nav>';
}

/**
 * Archive pagination — bounded window (prev · 1 … N-2 N N+1 … T · next),
 * semantic labels, mobile-friendly. All visuals live in the .bp-pg*
 * component classes (input.css), never inline arbitrary-variant strings.
 *
 * Mobile (<sm): number buttons + dots are hidden via CSS; the nav shows
 * "Page X of Y" + working Newer/Older, so it never overflows.
 */
function blogpro_pagination() {
	global $wp_query;

	$total = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 0;
	if ( $total < 2 ) {
		return;
	}
	$page = (int) max( 1, get_query_var( 'paged' ) );

	// prev_text "Newer" = page n-1 (newer posts); "Older" = page n+1.
	$links = paginate_links( array(
		'prev_text' => __( 'Newer', 'blog-pro' ),
		'next_text' => __( 'Older', 'blog-pro' ),
		'mid_size'  => 2,
		'end_size'  => 1,
		'type'      => 'array',
	) );
	if ( empty( $links ) ) {
		return;
	}

	$out = '';
	foreach ( $links as $raw ) {
		$out .= blogpro_pagination_item( $raw );
	}

	echo '<nav class="bp-pg-nav py-12" aria-label="' . esc_attr__( 'Posts pagination', 'blog-pro' ) . '><span class="bp-pg-status" aria-hidden="true">'
		. sprintf( esc_html__( 'Page %1$d of %2$d', 'blog-pro' ), $page, $total )
		. '</span>' . $out . '</nav>';
}

/**
 * Rebuild one paginate_links() item (WP ships single-quoted classes) into
 * the theme's .bp-pg component markup with proper aria wiring.
 *
 * @param string $raw Raw item HTML from paginate_links( 'array' ).
 * @return string
 */
function blogpro_pagination_item( $raw ) {
	$raw = trim( $raw );

	// Dots — plain "&hellip;" string or classed wrapper; decorative only.
	if ( false !== stripos( $raw, '&hellip;' ) || preg_match( '/class=["\'][^"\']*\bdots\b/', $raw ) ) {
		return '<span class="bp-pg bp-pg--dots" aria-hidden="true">&hellip;</span>';
	}

	// Current page — not a link, so aria-current + label do the job.
	if ( preg_match( '/class=["\'][^"\']*\bcurrent\b/', $raw ) ) {
		preg_match( '/>([^<]*)</', $raw, $n );
		$num = (int) $n[1];
		return '<span class="bp-pg bp-pg--num bp-pg--current" aria-current="page" aria-label="'
			. esc_attr( sprintf( __( 'Page %d', 'blog-pro' ), $num ) ) . '">'
			. esc_html( $num ) . '</span>';
	}

	// Adjacent (prev/next) — live anchor or disabled placeholder.
	if ( preg_match( '/class=["\'][^"\']*\b(prev|next)\b/', $raw, $dm ) ) {
		$dir  = $dm[1];
		$aria = 'prev' === $dir ? __( 'Newer posts', 'blog-pro' ) : __( 'Older posts', 'blog-pro' );
		$text = esc_html( preg_replace( '/<[^>]+>/s', '', $raw ) ); // "← Newer" → escaped text

		if ( '<a' === substr( $raw, 0, 2 ) ) {
			preg_match( '/href=["\']([^"\']+)["\']/', $raw, $hm );
			$cls = 'bp-pg bp-pg--adjacent bp-pg--' . $dir;
			return $hm
				? '<a class="' . $cls . '" href="' . esc_url( $hm[1] ) . '" aria-label="' . esc_attr( $aria ) . '">' . $text . '</a>'
				: '<span class="' . $cls . '" aria-disabled="true" aria-label="' . esc_attr( $aria ) . '">' . $text . '</span>';
		}
		return '<span class="bp-pg bp-pg--adjacent bp-pg--' . $dir . ' bp-pg--disabled" aria-disabled="true" aria-label="'
			. esc_attr( $aria ) . '">' . $text . '</span>';
	}

	// Plain number link.
	if ( '<a' === substr( $raw, 0, 2 ) ) {
		preg_match( '/href=["\']([^"\']+)["\']/', $raw, $hm );
		$num = preg_replace( '/<[^>]+>/s', '', $raw );
		if ( $hm ) {
			return '<a class="bp-pg bp-pg--num" href="' . esc_url( $hm[1] ) . '" aria-label="'
				. esc_attr( sprintf( __( 'Page %s', 'blog-pro' ), $num ) ) . '">'
				. esc_html( $num ) . '</a>';
		}
	}

	// Unknown shape — never emit unstyled/broken markup.
	return '';
}

function blogpro_related_posts( $post_id, $limit = 3 ) {
	$cats = wp_get_post_categories( $post_id );
	if ( empty( $cats ) ) return new WP_Query();
	return new WP_Query( array(
		'category__in'   => $cats,
		'post__not_in'   => array( $post_id ),
		'posts_per_page' => $limit,
		'ignore_sticky_posts' => true,
		'no_found_rows'  => true,
	) );
}

/**
 * wp_list_comments callback — custom comment markup with threaded-reply
 * indentation. Lives here (guarded) rather than in comments.php so the
 * function can't fatal-redeclare when the template renders twice.
 *
 * Indentation is inline style: dynamic per-depth Tailwind classes don't
 * exist in the compiled build (content scan can't see runtime strings).
 */
if ( ! function_exists( 'blogpro_comment_markup' ) ) {
	function blogpro_comment_markup( $comment, $args, $depth ) {
		$indent = $depth > 0 ? ' style="margin-left:' . min( 4, $depth ) . 'rem"' : '';
		?>
		<li<?php echo $indent; // phpcs:ignore WordPress.Security.EscapeOutput -- computed integer rem value. ?> <?php comment_class( 'border-b border-gray-100 pb-8 last:border-b-0 last:pb-0' ); ?> id="comment-<?php comment_ID(); ?>">
			<div class="flex gap-4">
				<div class="shrink-0">
					<?php echo get_avatar( $comment, 48, '', '', array( 'class' => 'rounded-full ring-2 ring-white shadow-sm' ) ); ?>
				</div>
				<div class="flex-1 min-w-0">
					<div class="flex flex-wrap items-center gap-x-3 gap-y-1">
						<span class="font-bold text-gray-900 text-[15px]"><?php comment_author(); ?></span>
						<span class="text-sm text-gray-700"><?php comment_date(); ?></span>
						<?php edit_comment_link( __( 'Edit', 'blog-pro' ), '<span class="text-xs text-indigo-500">', '</span>' ); ?>
					</div>
					<?php if ( '0' === $comment->comment_approved ) : ?>
						<p class="mt-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"><?php esc_html_e( 'Your comment is awaiting moderation.', 'blog-pro' ); ?></p>
					<?php endif; ?>
					<div class="mt-2 text-gray-700 leading-relaxed text-sm [&_p]:mb-3 [&_p:last-child]:mb-0"><?php comment_text(); ?></div>
					<div class="mt-3">
						<?php
						comment_reply_link( array_merge( $args, array(
							'depth'      => $depth,
							'max_depth'  => $args['max_depth'],
							'reply_text' => '<span class="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">' .
											'<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>' .
											__( 'Reply', 'blog-pro' ) .
										   '</span>',
						) ) );
						?>
					</div>
				</div>
			</div>
		<?php
	}
}

function blogpro_breadcrumbs() {
	if ( is_front_page() ) return;
	echo '<nav class="flex items-center flex-wrap gap-2 text-sm text-gray-500 mb-8 [&_a]:text-indigo-600 [&_a]:hover:text-indigo-800 [&_a]:font-medium [&_a]:no-underline [&_span]:text-gray-700" aria-label="' . esc_attr__( 'Breadcrumb', 'blog-pro' ) . '"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'blog-pro' ) . '</a>';
	if ( is_singular( 'post' ) ) {
		$cats = get_the_category();
		if ( ! empty( $cats ) ) {
			echo ' > <a href="' . esc_url( get_category_link( $cats[0]->term_id ) ) . '">' . esc_html( $cats[0]->name ) . '</a>';
		}
		// echo ' > <span>' . esc_html( get_the_title() ) . '</span>';
	} elseif ( is_page() ) {
		echo ' > <span>' . esc_html( get_the_title() ) . '</span>';
	} elseif ( is_category() || is_tag() || is_tax() ) {
		echo ' > <span>' . esc_html( single_term_title( '', false ) ) . '</span>';
	}
	echo '</nav>';
}

function blogpro_featured_query( $limit = 4 ) {
	$q = new WP_Query( array(
		'post_type'      => 'post',
		'posts_per_page' => $limit,
		'tag'            => 'featured',
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
	if ( ! $q->have_posts() ) {
		// Fallback: most recent posts if nothing is tagged "featured" yet.
		$q = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => $limit, 'no_found_rows' => true, 'orderby'        => 'date','order' => 'DESC', ) );
	}
	return $q;
}

// Social share moved to inc/social-share.php (networks registry + admin settings).
