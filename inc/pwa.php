<?php
/**
 * Progressive Web App (PWA) integration – manifest, service worker,
 * offline support, and install prompt. No external libraries.
 *
 * Serves /manifest.json and /sw.js via rewrite rules, enqueues
 * registration script, and provides theme-color meta.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register rewrite rules for PWA assets.
 */
function blogpro_pwa_rewrite_rules() {
	add_rewrite_rule( '^manifest\.json$', 'index.php?blogpro_pwa_manifest=1', 'top' );
	add_rewrite_rule( '^sw\.js$', 'index.php?blogpro_pwa_sw=1', 'top' );
}
add_action( 'init', 'blogpro_pwa_rewrite_rules' );

/**
 * Add query vars for PWA handlers.
 */
add_filter( 'query_vars', function( $vars ) {
	$vars[] = 'blogpro_pwa_manifest';
	$vars[] = 'blogpro_pwa_sw';
	return $vars;
} );

/**
 * Serve manifest.json dynamically.
 */
function blogpro_pwa_serve_manifest() {
	if ( ! get_query_var( 'blogpro_pwa_manifest' ) ) return;

	header( 'Content-Type: application/json; charset=UTF-8' );
	header( 'Cache-Control: public, max-age=86400' );

	$site_name   = get_bloginfo( 'name' );
	$description = get_bloginfo( 'description' );
	$theme_color = get_theme_mod( 'pwa_theme_color', '#1a1a2e' );
	$bg_color    = get_theme_mod( 'pwa_background_color', '#ffffff' );
	$start_url   = home_url( '/' );
	$icon_url    = get_site_icon_url( 512 );
	$icon_192    = get_site_icon_url( 192 );
	$icon_512    = get_site_icon_url( 512 );

	// Fallback icons if no site icon.
	if ( ! $icon_192 ) {
		$icon_192 = get_template_directory_uri() . '/assets/images/icon.png';
		$icon_512 = get_template_directory_uri() . '/assets/images/icon.png';
	}

	$manifest = array(
		'name'             => $site_name,
		'short_name'       => substr( $site_name, 0, 12 ),
		'description'      => $description,
		'start_url'        => $start_url,
		'display'          => 'standalone',
		'orientation'      => 'portrait',
		'theme_color'      => $theme_color,
		'background_color' => $bg_color,
		'icons'            => array(
			array(
				'src'   => $icon_192,
				'sizes' => '192x192',
				'type'  => 'image/png',
			),
			array(
				'src'   => $icon_512,
				'sizes' => '512x512',
				'type'  => 'image/png',
			),
		),
	);

	// Allow filtering.
	$manifest = apply_filters( 'blogpro_pwa_manifest', $manifest );

	echo wp_json_encode( $manifest );
	exit;
}
add_action( 'template_redirect', 'blogpro_pwa_serve_manifest', 1 );

