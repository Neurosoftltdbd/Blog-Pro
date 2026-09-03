<?php
/**
 * Dynamic robots.txt (virtual — no physical file needed, WP serves this
 * at /robots.txt automatically via the `do_robots` hook). Points crawlers
 * at the theme's sitemap and blocks non-content paths.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Build the robots.txt content.
 *
 * @param string $output Core's default output.
 * @param bool   $public Whether the site is public.
 * @return string
 */
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

/**
 * Serve robots.txt for subdirectory installs.
 *
 * WP core only registers the /robots.txt rewrite rule when the site is
 * installed at the ROOT path (class-wp-rewrite.php: "robots.txt -- only if
 * installed at the root"), so subdirectory installs 404 on /robots.txt.
 * We register our own rewrite rule + query var, and serve the content with
 * proper text/plain + caching headers when the query var is set.
 */
function blogpro_robots_query_var( $vars ) {
	$vars[] = 'blogpro_robots';
	return $vars;
}
add_filter( 'query_vars', 'blogpro_robots_query_var' );

function blogpro_robots_rewrite_rule() {
	add_rewrite_rule( '^robots\.txt$', 'index.php?blogpro_robots=1', 'top' );
}
add_action( 'init', 'blogpro_robots_rewrite_rule' );

function blogpro_serve_robots( $wp ) {
	if ( empty( $wp->query_vars['blogpro_robots'] ) ) {
		return;
	}
	// If a physical robots.txt exists in the site root, let it serve (do not
	// override user-authored content) — LiteSpeed/Nginx may also map it.
	if ( file_exists( ABSPATH . 'robots.txt' ) ) {
		return;
	}

	// Build output defensively: a fatal inside any plugin's robots_txt filter
	// would 500 the request for crawlers (cached browser copies still 200,
	// hiding it). Fall back to a minimal valid file instead.
	$output = '';
	try {
		$output = (string) apply_filters( 'robots_txt', "User-agent: *\nDisallow: /", (bool) get_option( 'blog_public' ) );
	} catch ( \Throwable $e ) {
		$output = '';
	}
	if ( trim( $output ) === '' ) {
		$output = "User-agent: *\nDisallow: /wp-admin/\n";
	}

	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	echo $output; // phpcs:ignore WordPress.Security.EscapeOutput -- robots.txt output, escaped by design.
	exit;
}
add_action( 'parse_request', 'blogpro_serve_robots' );

/**
 * Flush rewrite rules once when the theme is switched/updated, so the
 * robots.txt rule exists without a manual Settings → Permalinks save.
 * Cheap guard: only flushes if the rule marker is missing.
 */
function blogpro_robots_maybe_flush() {
	global $wp_rewrite;
	$rules = $wp_rewrite->rewrite_rules();
	if ( ! isset( $rules['^robots\.txt$'] ) ) {
		$wp_rewrite->flush_rules( false );
	}
}
add_action( 'after_switch_theme', 'blogpro_robots_maybe_flush' );
