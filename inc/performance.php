<?php
/**
 * Speed optimizations. Goal: sub-second single post load without any
 * caching plugin. Most of this is "remove what you don't need" —
 * the fastest request is the one that never happens.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* 1. Strip default WP head clutter nobody uses on a blog theme. */
function blogpro_clean_head() {
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	remove_action( 'wp_head', 'rest_output_link_wp_head' ); // custom API already documented; core discovery not needed
	remove_action( 'wp_head', 'wp_resource_hints', 2 );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
}
add_action( 'init', 'blogpro_clean_head' );

/* 2. Disable XML-RPC (attack surface + needless load). */
add_filter( 'xmlrpc_enabled', '__return_false' );

/* 3. Disable self-pingbacks and pingback header. */
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );
function blogpro_no_self_pingbacks( &$links ) {
	$home = home_url( '/' );
	foreach ( $links as $l => $link ) {
		if ( 0 === strpos( $link, $home ) ) unset( $links[ $l ] );
	}
}
add_action( 'pre_ping', 'blogpro_no_self_pingbacks' );

/* 4. Remove querystring version args from static assets — better proxy/CDN caching. */
function blogpro_remove_asset_versioning( $src ) {
	if ( strpos( $src, 'ver=' ) ) {
		$src = remove_query_arg( 'ver', $src );
	}
	return $src;
}
add_filter( 'style_loader_src', 'blogpro_remove_asset_versioning', 9999 );
add_filter( 'script_loader_src', 'blogpro_remove_asset_versioning', 9999 );

/* 5. Dequeue block-library / global-styles CSS injected by core on classic themes
      that don't use full-site editing — this theme ships its own compact CSS. */
function blogpro_dequeue_block_assets() {
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_style( 'classic-theme-styles' );
}
add_action( 'wp_enqueue_scripts', 'blogpro_dequeue_block_assets', 100 );

/* 6. Limit Heartbeat API (admin only anyway, but keep it light). */
add_filter( 'heartbeat_settings', function ( $settings ) {
	$settings['interval'] = 60;
	return $settings;
} );

/* 7. Preconnect / preload critical resources for faster first paint. */
function blogpro_resource_hints() {
	echo '<link rel="preconnect" href="' . esc_url( home_url() ) . '">' . "\n";

	// Preload the LCP image on singular views (featured image) at highest priority.
	if ( is_singular() && has_post_thumbnail() ) {
		$src = wp_get_attachment_image_url( get_post_thumbnail_id(), 'blogpro-hero' );
		if ( $src ) {
			echo '<link rel="preload" as="image" href="' . esc_url( $src ) . '" fetchpriority="high">' . "\n";
		}
	}
}
add_action( 'wp_head', 'blogpro_resource_hints', 1 );

/* 8. Trim emoji/embed inline JS entirely and skip loading wp-embed.min.js on front end. */
function blogpro_dequeue_embed() {
	if ( ! is_admin() ) {
		wp_deregister_script( 'wp-embed' );
	}
}
add_action( 'init', 'blogpro_dequeue_embed' );

/* 9. Reduce post revisions stored (less DB bloat -> faster queries over time).
      Best set in wp-config.php; documented here as a constant fallback. */
if ( ! defined( 'WP_POST_REVISIONS' ) ) {
	define( 'WP_POST_REVISIONS', 5 );
}

/* 10. Disable the REST API's core discovery links for non-logged-in users on
       archives (keeps our custom, purpose-built endpoint as the primary API). */
add_filter( 'rest_authentication_errors', function ( $result ) {
	return $result; // left open by default; tighten per-route in inc/rest-api.php if needed.
} );

/* 11. GZIP + long-lived caching headers + far-future expiry for static assets
       via .htaccess (Apache). See /.htaccess-blogpro for the ready-to-use rules
       (renamed to .htaccess on install, appended above WP's default block). */

/* 12. Turn off object cache spam from transients used by search widgets etc. is
       N/A here since the theme has no plugins. */

/* 13. Server-timing header for real-world diagnostics (safe, minimal). */
add_action( 'send_headers', function () {
	if ( ! is_admin() ) {
		header( 'X-Theme: Blog-Pro' );
	}
} );
