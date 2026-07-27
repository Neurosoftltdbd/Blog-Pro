<?php
/**
 * JSON-LD structured data. Outputs Organization/WebSite schema sitewide,
 * BlogPosting schema on single posts, and BreadcrumbList everywhere but
 * the homepage. Also useful for GEO (Generative Engine Optimization) —
 * AI answer engines lean heavily on clean, explicit schema.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function blogpro_schema_website() {
	return array(
		'@type' => 'WebSite',
		'@id'   => home_url( '/#website' ),
		'url'   => home_url( '/' ),
		'name'  => get_bloginfo( 'name' ),
		'description' => get_bloginfo( 'description' ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => home_url( '/?s={search_term_string}' ),
			'query-input' => 'required name=search_term_string',
		),
	);
}

function blogpro_schema_organization() {
	$logo = get_custom_logo() ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) : '';
	$org  = array(
		'@type' => 'Organization',
		'@id'   => home_url( '/#organization' ),
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);
	if ( $logo ) {
		$org['logo'] = array( '@type' => 'ImageObject', 'url' => $logo );
	}
	return $org;
}

function blogpro_schema_breadcrumbs() {
	$items = array( array( '@type' => 'ListItem', 'position' => 1, 'name' => __( 'Home', 'blog-pro' ), 'item' => home_url( '/' ) ) );
	$pos = 2;

	if ( is_singular( 'post' ) ) {
		$cats = get_the_category();
		if ( ! empty( $cats ) ) {
			$items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => $cats[0]->name, 'item' => get_category_link( $cats[0]->term_id ) );
		}
		$items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => get_the_title() );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => single_term_title( '', false ) );
	} elseif ( is_page() ) {
		$items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => get_the_title() );
	} else {
		return null;
	}

	return array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
}

function blogpro_schema_blogposting() {
	global $post;
	$image = has_post_thumbnail() ? wp_get_attachment_image_url( get_post_thumbnail_id(), 'blogpro-hero' ) : '';
	$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );

	$schema = array(
		'@type'            => 'BlogPosting',
		'@id'              => get_permalink() . '#article',
		'mainEntityOfPage' => get_permalink(),
		'headline'         => get_the_title(),
		'description'      => blogpro_get_meta_description(),
		'datePublished'    => get_the_date( 'c' ),
		'dateModified'     => get_the_modified_date( 'c' ),
		'author'           => array(
			'@type' => 'Person',
			'name'  => get_the_author(),
			'url'   => get_author_posts_url( $post->post_author ),
		),
		'publisher'        => array( '@id' => home_url( '/#organization' ) ),
		'wordCount'        => $word_count,
		'inLanguage'       => get_bloginfo( 'language' ),
	);
	if ( $image ) {
		$schema['image'] = array( '@type' => 'ImageObject', 'url' => $image );
	}
	$cats = get_the_category();
	if ( ! empty( $cats ) ) {
		$schema['articleSection'] = wp_list_pluck( $cats, 'name' );
	}
	return $schema;
}

function blogpro_output_schema() {
	$graph = array( blogpro_schema_website(), blogpro_schema_organization() );

	$breadcrumbs = blogpro_schema_breadcrumbs();
	if ( $breadcrumbs ) $graph[] = $breadcrumbs;

	if ( is_singular( 'post' ) ) {
		$graph[] = blogpro_schema_blogposting();
	}

	$output = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);

	echo '<script type="application/ld+json">' . wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'blogpro_output_schema', 3 );
