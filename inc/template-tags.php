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
	echo '<span class="text-gray-500 text-sm">' . esc_html( get_the_date() ) . '</span>';
	echo ' &#10625; <span class="text-gray-500 text-sm">' . esc_html( blogpro_reading_time() ) . '</span>';
	echo ' &#10625; <span class="text-gray-500 text-sm">' . esc_html__( 'By', 'blog-pro' ) . ' <a href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '" class="text-indigo-600 hover:text-indigo-800 font-medium no-underline">' . esc_html( get_the_author() ) . '</a></span>';
	$categories = get_the_category();
	if ( ! empty( $categories ) ) {
		$category = $categories[0];
		echo ' &#10625; <span class="text-gray-500 text-sm">' . esc_html__( 'On', 'blog-pro' ) . ' <a href="' . esc_url( get_category_link( $category ) ) . '" class="text-indigo-600 hover:text-indigo-800 font-medium no-underline">' . esc_html( $category->name ) . '</a></span>';
	}
}

function blogpro_pagination() {
	$links = paginate_links( array(
		'prev_text' => __( '&larr; Newer', 'blog-pro' ),
		'next_text' => __( 'Older &rarr;', 'blog-pro' ),
		'type'      => 'array',
	) );
	if ( empty( $links ) ) return;
	echo '<nav class="flex items-center gap-2 mt-8 [&_.page-numbers]:px-4 [&_.page-numbers]:py-2 [&_.page-numbers]:rounded-xl [&_.page-numbers]:font-medium [&_.page-numbers]:transition-colors [&_.page-numbers.current]:bg-indigo-600 [&_.page-numbers.current]:text-white [&_.page-numbers]:text-gray-700 [&_.page-numbers]:hover:bg-indigo-50 [&_a]:no-underline" aria-label="' . esc_attr__( 'Posts pagination', 'blog-pro' ) . '">';
	foreach ( $links as $link ) {
		echo $link;
	}
	echo '</nav>';
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
