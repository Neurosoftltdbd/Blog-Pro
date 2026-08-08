<?php
/**
 * Custom, lightweight REST API for blog posts. This trims the response
 * down to exactly what a blog front-end needs (title, excerpt, image,
 * reading time, etc.) instead of WP core's heavier /wp/v2/posts payload —
 * smaller payloads mean faster fetches for any headless/AJAX use.
 *
 * Namespace: blogpro/v1
 *   GET /wp-json/blogpro/v1/posts              list (paged, filterable)
 *   GET /wp-json/blogpro/v1/posts/{id}          single post by ID
 *   GET /wp-json/blogpro/v1/posts/{slug}        single post by slug
 *   GET /wp-json/blogpro/v1/posts/featured      featured posts (tagged "featured")
 *   GET /wp-json/blogpro/v1/categories          category list
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function blogpro_format_post_for_api( $post ) {
	$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
	$content    = wp_strip_all_tags( $post->post_content );
	$word_count = str_word_count( $content );
	$tags = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );

	return array(
		'id'            => $post->ID,
		'title'         => get_the_title( $post ),
		'slug'          => $post->post_name,
		'excerpt'       => wp_strip_all_tags( has_excerpt( $post->ID ) ? get_the_excerpt( $post ) : wp_trim_words( $content, 30 ) ),
		'content'       => apply_filters( 'the_content', $post->post_content ),
		'link'          => get_permalink( $post ),
		'date'          => get_the_date( 'c', $post ),
		'modified'      => get_the_modified_date( 'c', $post ),
		'author'        => array(
			'name' => get_the_author_meta( 'display_name', $post->post_author ),
			'url' => get_author_posts_url( $post->post_author ),
			'avatar' => get_avatar_url( $post->post_author ),
		),
		'featured_image'=> get_the_post_thumbnail_url( $post, 'blogpro-card' ) ?: null,
		'categories'    => $categories,
		'tags' 			=> $tags,	
		'reading_time'  => max( 1, (int) ceil( $word_count / 200 ) ),
	);
}

function blogpro_single_post_response( $post ) {
	if ( ! $post || 'publish' !== $post->post_status || 'post' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Post not found', array( 'status' => 404 ) );
	}
	$data            = blogpro_format_post_for_api( $post );
	$data['content'] = apply_filters( 'the_content', $post->post_content );
	return new WP_REST_Response( $data, 200 );
}

function blogpro_register_rest_routes() {
	register_rest_route( 'blogpro/v1', '/posts', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'page'     => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
			'per_page' => array( 'default' => 10, 'sanitize_callback' => 'absint' ),
			'category' => array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'search'   => array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'slug'     => array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
		),
		'callback' => function ( $request ) {
			$args = array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'paged'          => $request['page'],
				'posts_per_page' => min( 50, $request['per_page'] ),
				's'              => $request['search'],
				'slug'           => $request['slug'],
			);
			if ( $request['category'] ) {
				$args['category_name'] = $request['category'];
			}
			$query = new WP_Query( $args );
			$items = array_map( 'blogpro_format_post_for_api', $query->posts );

			return new WP_REST_Response( array(
				'items'       => $items,
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
			), 200 );
		},
	) );

	register_rest_route( 'blogpro/v1', '/posts/featured', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( $request ) {
			$query = new WP_Query( array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'tag'            => 'featured',
				'posts_per_page' => 5,
			) );
			return new WP_REST_Response( array_map( 'blogpro_format_post_for_api', $query->posts ), 200 );
		},
	) );

	register_rest_route( 'blogpro/v1', '/posts/(?P<id>\d+)', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( $request ) {
			$post = get_post( (int) $request['id'] );
			return blogpro_single_post_response( $post );
		},
	) );

	register_rest_route( 'blogpro/v1', '/posts/(?P<slug>[a-z0-9-]+)', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( $request ) {
			$post = get_page_by_path( $request['slug'], OBJECT, 'post' );
			return blogpro_single_post_response( $post );
		},
	) );

	register_rest_route( 'blogpro/v1', '/categories', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true ) );
			if ( is_wp_error( $terms ) ) return new WP_REST_Response( array(), 200 );
			$out = array_map( function ( $t ) {
				return array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count, 'link' => get_term_link( $t ) );
			}, $terms );
			return new WP_REST_Response( $out, 200 );
		},
	) );
}
add_action( 'rest_api_init', 'blogpro_register_rest_routes' );

/* Cache the list endpoint response briefly at the HTTP layer (helps if
   fronted by any CDN/reverse proxy, harmless otherwise). */
add_filter( 'rest_post_dispatch', function ( $response, $server, $request ) {
	if ( 0 === strpos( $request->get_route(), '/blogpro/v1/' ) && ! is_user_logged_in() ) {
		$response->header( 'Cache-Control', 'public, max-age=120' );
	}
	return $response;
}, 10, 3 );
