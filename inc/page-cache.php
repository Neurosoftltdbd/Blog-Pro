<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Full-page file cache. No plugin.
 *
 * Anonymous GET requests for singular posts, pages and the front page are
 * saved as HTML under wp-content/cache/blogpro-pages/. A cache hit is served
 * before WordPress loads its queries, so TTFB drops to the cost of one file read.
 *
 * Cache is cleared when a post, page, term or comment changes, and on theme
 * switch. Logged-in users, POST requests, carts, forms and query-string
 * requests are never cached.
 */

define( 'BLOGPRO_PAGE_CACHE_TTL', HOUR_IN_SECONDS * 12 );

/** Directory for cached pages, created on first write. */
function blogpro_page_cache_dir() {
	return WP_CONTENT_DIR . '/cache/blogpro-pages';
}

/** Cache file path for the current URL, host-aware. */
function blogpro_page_cache_file() {
	$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( $_SERVER['HTTP_HOST'] ) : '';
	$path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '/';
	return blogpro_page_cache_dir() . '/' . md5( $host . $path ) . '.html';
}

/** True when this request may be read from or written to the cache. */
function blogpro_page_cache_allowed() {
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return false;
	if ( ! empty( $_GET ) ) return false; // search, filters, tracking params

	// Runs on init, before the user is loaded, so check the auth cookie directly.
	foreach ( array_keys( $_COOKIE ) as $name ) {
		// Logged-in users, commenters, protected posts and WooCommerce sessions need live pages.
		if ( 0 === strpos( $name, 'wordpress_logged_in_' ) || 0 === strpos( $name, 'comment_author' )
			|| 0 === strpos( $name, 'wp-postpass' ) || 0 === strpos( $name, 'woocommerce_' ) ) {
			return false;
		}
	}

	// Cart and checkout are only known after the query runs. Checked again at store time.
	return true;
}

/** Serve a cached page early and exit. Runs before WordPress queries anything. */
function blogpro_page_cache_serve() {
	if ( ! blogpro_page_cache_allowed() ) return;

	$file = blogpro_page_cache_file();
	if ( ! is_readable( $file ) || ( time() - filemtime( $file ) ) > BLOGPRO_PAGE_CACHE_TTL ) return;

	header( 'X-Blogpro-Cache: HIT' );
	header( 'Cache-Control: public, max-age=300' );
	readfile( $file );
	exit;
}
add_action( 'init', 'blogpro_page_cache_serve', 0 );

/** Start capturing output for cacheable pages. */
function blogpro_page_cache_start() {
	if ( ! blogpro_page_cache_allowed() ) return;
	if ( ! ( is_singular() || is_front_page() || is_home() ) ) return;
	if ( 200 !== http_response_code() ) return;
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) return;

	ob_start( 'blogpro_page_cache_store' );
}
add_action( 'template_redirect', 'blogpro_page_cache_start', 0 );

/** Output buffer callback: store the finished HTML, then return it unchanged. */
function blogpro_page_cache_store( $buffer ) {
	// Only full HTML documents, and never a response with a nonce or a
	// per-visitor form. Those pages would leak one visitor's token to the next.
	if ( false === stripos( $buffer, '</html>' ) ) return $buffer;
	if ( false !== strpos( $buffer, 'name="_wpnonce"' ) ) return $buffer;

	$dir = blogpro_page_cache_dir();
	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) return $buffer;

	$file = blogpro_page_cache_file();
	$tmp  = $file . '.' . uniqid( '', true ) . '.tmp';
	if ( false !== file_put_contents( $tmp, $buffer ) ) {
		rename( $tmp, $file ); // Atomic on same filesystem, so readers never see a partial file.
	}
	return $buffer;
}

/** Clear the whole cache. Cheap and safe: the next visit rebuilds the page. */
function blogpro_page_cache_flush() {
	$dir = blogpro_page_cache_dir();
	if ( ! is_dir( $dir ) ) return;
	foreach ( glob( $dir . '/*.html' ) ?: array() as $file ) {
		@unlink( $file );
	}
}
add_action( 'save_post', 'blogpro_page_cache_flush' );
add_action( 'deleted_post', 'blogpro_page_cache_flush' );
add_action( 'edit_terms', 'blogpro_page_cache_flush' );
add_action( 'comment_post', 'blogpro_page_cache_flush' );
add_action( 'wp_update_comment_count', 'blogpro_page_cache_flush' );
add_action( 'switch_theme', 'blogpro_page_cache_flush' );
add_action( 'update_option_sidebars_widgets', 'blogpro_page_cache_flush' );
add_action( 'wp_update_nav_menu', 'blogpro_page_cache_flush' );
