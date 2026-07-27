<?php
/**
 * Dynamic SEO meta tags — title, description, canonical, Open Graph,
 * Twitter Card, robots directives. No plugin required.
 *
 * Per-post overrides: if you want manual control on a given post, save
 * post meta keys `_blogpro_meta_title` / `_blogpro_meta_description` and
 * they'll be used automatically (a simple metabox for this can be added
 * later; the fallback logic below already covers 95% of blog needs).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function blogpro_get_meta_description() {
	if ( is_singular() ) {
		global $post;
		$custom = get_post_meta( $post->ID, '_blogpro_meta_description', true );
		if ( $custom ) return wp_strip_all_tags( $custom );

		$excerpt = has_excerpt( $post->ID ) ? get_the_excerpt( $post ) : wp_strip_all_tags( $post->post_content );
		$excerpt = wp_strip_all_tags( $excerpt );
		return wp_trim_words( $excerpt, 32, '…' );
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$desc = term_description();
		if ( $desc ) return wp_trim_words( wp_strip_all_tags( $desc ), 32, '…' );
		return sprintf( __( 'Browse all posts about %s.', 'blog-pro' ), single_term_title( '', false ) );
	}

	if ( is_home() || is_front_page() ) {
		$tagline = get_bloginfo( 'description' );
		return $tagline ? $tagline : sprintf( __( 'Latest articles from %s.', 'blog-pro' ), get_bloginfo( 'name' ) );
	}

	if ( is_author() ) {
		$bio = get_the_author_meta( 'description' );
		return $bio ? wp_trim_words( $bio, 32, '…' ) : sprintf( __( 'Posts by %s.', 'blog-pro' ), get_the_author() );
	}

	return get_bloginfo( 'description' );
}

function blogpro_get_meta_title() {
	if ( is_singular() ) {
		global $post;
		$custom = get_post_meta( $post->ID, '_blogpro_meta_title', true );
		if ( $custom ) return $custom;
		return get_the_title() . ' | ' . get_bloginfo( 'name' );
	}
	if ( is_home() || is_front_page() ) {
		return get_bloginfo( 'name' ) . ' | ' . get_bloginfo( 'description' );
	}
	if ( is_category() || is_tag() || is_tax() ) {
		return single_term_title( '', false ) . ' | ' . get_bloginfo( 'name' );
	}
	if ( is_search() ) {
		return sprintf( __( 'Search results for "%s" | %s', 'blog-pro' ), get_search_query(), get_bloginfo( 'name' ) );
	}
	if ( is_404() ) {
		return __( 'Page not found', 'blog-pro' ) . ' | ' . get_bloginfo( 'name' );
	}
	return wp_get_document_title();
}

function blogpro_get_canonical_url() {
	if ( is_singular() ) return get_permalink();
	if ( is_home() || is_front_page() ) return home_url( '/' );
	if ( is_category() || is_tag() || is_tax() ) return get_term_link( get_queried_object() );
	if ( is_author() ) return get_author_posts_url( get_queried_object_id() );
	global $wp;
	return home_url( add_query_arg( array(), $wp->request ) );
}

function blogpro_get_social_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		return wp_get_attachment_image_url( get_post_thumbnail_id(), 'blogpro-hero' );
	}
	$site_icon = get_site_icon_url( 512 );
	return $site_icon ? $site_icon : '';
}

function blogpro_output_meta_tags() {
	$description = esc_attr( blogpro_get_meta_description() );
	$canonical   = esc_url( blogpro_get_canonical_url() );
	$title       = esc_attr( blogpro_get_meta_title() );
	$image       = blogpro_get_social_image();
	$site_name   = esc_attr( get_bloginfo( 'name' ) );

	echo "\n<!-- Blog Pro SEO meta -->\n";
	echo '<meta name="description" content="' . $description . '">' . "\n";
	echo '<link rel="canonical" href="' . $canonical . '">' . "\n";

	// Robots directives
	if ( is_search() || is_404() ) {
		echo '<meta name="robots" content="noindex,follow">' . "\n";
	} elseif ( is_paged() ) {
		echo '<meta name="robots" content="index,follow,noarchive">' . "\n";
	} else {
		echo '<meta name="robots" content="index,follow,max-image-preview:large">' . "\n";
	}

	// Open Graph
	echo '<meta property="og:type" content="' . ( is_singular( 'post' ) ? 'article' : 'website' ) . '">' . "\n";
	echo '<meta property="og:title" content="' . $title . '">' . "\n";
	echo '<meta property="og:description" content="' . $description . '">' . "\n";
	echo '<meta property="og:url" content="' . $canonical . '">' . "\n";
	echo '<meta property="og:site_name" content="' . $site_name . '">' . "\n";
	if ( $image ) echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";

	if ( is_singular( 'post' ) ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '">' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . "\n";
		foreach ( get_the_category() as $cat ) {
			echo '<meta property="article:section" content="' . esc_attr( $cat->name ) . '">' . "\n";
		}
	}

	// Twitter Card
	echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	echo '<meta name="twitter:title" content="' . $title . '">' . "\n";
	echo '<meta name="twitter:description" content="' . $description . '">' . "\n";
	if ( $image ) echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";

	echo "<!-- /Blog Pro SEO meta -->\n";
}
add_action( 'wp_head', 'blogpro_output_meta_tags', 2 );

/* Override the default <title> with our computed value for full control. */
add_filter( 'pre_get_document_title', 'blogpro_get_meta_title' );
