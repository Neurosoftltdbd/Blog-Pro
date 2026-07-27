<?php
/**
 * Dynamic robots.txt (virtual — no physical file needed, WP serves this
 * at /robots.txt automatically via the `do_robots` hook). Points crawlers
 * at the theme's sitemap and blocks non-content paths.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function blogpro_robots_txt( $output, $public ) {
	if ( '0' == $public ) {
		return "User-agent: *\nDisallow: /\n";
	}

	$lines   = array();
	$lines[] = 'User-agent: *';
	$lines[] = 'Allow: /';
	$lines[] = 'Disallow: /wp-admin/';
	$lines[] = 'Disallow: /wp-includes/';
	$lines[] = 'Disallow: /?s=';
	$lines[] = 'Disallow: /search/';
	$lines[] = 'Allow: /wp-admin/admin-ajax.php';
	$lines[] = '';
	$lines[] = 'Sitemap: ' . home_url( '/sitemap.xml' );

	return implode( "\n", $lines ) . "\n";
}
add_filter( 'robots_txt', 'blogpro_robots_txt', 10, 2 );
