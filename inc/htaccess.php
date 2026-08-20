<?php
/**
 * Writes the theme's performance rules (gzip compression, far-future
 * browser caching, security headers) into the site's root .htaccess on
 * activation, and cleanly removes them if the theme is switched away
 * from. Uses WordPress core's own insert_with_markers() — the same safe
 * mechanism WP uses for its own rewrite rules — so it won't clobber
 * anything else in the file, and it silently does nothing on servers
 * that don't use Apache (Nginx, LiteSpeed in Nginx-compat mode, etc.)
 * since .htaccess has no effect there anyway.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BLOGPRO_HTACCESS_MARKER', 'Blog Pro Performance' );

function blogpro_htaccess_rules() {
	return array(
		// Force HTTPS: redirect all http:// requests to https://. Covers both
		// direct SSL and setups behind a proxy/load balancer.
		'<IfModule mod_rewrite.c>',
		"\tRewriteEngine On",
		"\tRewriteCond %{HTTPS} !=on",
		"\tRewriteCond %{HTTP:X-Forwarded-Proto} !https",
		"\tRewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]",
		'</IfModule>',
		'',
		'<IfModule mod_deflate.c>',
		"\tAddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript",
		"\tAddOutputFilterByType DEFLATE application/javascript application/x-javascript application/json",
		"\tAddOutputFilterByType DEFLATE image/svg+xml application/xml application/rss+xml",
		'</IfModule>',
		'',
		'<IfModule mod_expires.c>',
		"\tExpiresActive On",
		"\tExpiresByType image/jpg \"access plus 1 year\"",
		"\tExpiresByType image/jpeg \"access plus 1 year\"",
		"\tExpiresByType image/png \"access plus 1 year\"",
		"\tExpiresByType image/webp \"access plus 1 year\"",
		"\tExpiresByType image/svg+xml \"access plus 1 year\"",
		"\tExpiresByType video/mp4 \"access plus 1 year\"",
		"\tExpiresByType text/css \"access plus 1 month\"",
		"\tExpiresByType application/javascript \"access plus 1 month\"",
		"\tExpiresByType font/woff2 \"access plus 1 year\"",
		"\tExpiresByType text/html \"access plus 0 seconds\"",
		'</IfModule>',
		'',
		'<IfModule mod_headers.c>',
		"\t<FilesMatch \"\\.(jpg|jpeg|png|webp|svg|css|js|woff2|mp4)$\">",
		"\t\tHeader set Cache-Control \"public, max-age=31536000, immutable\"",
		"\t</FilesMatch>",
		"\tHeader set X-Content-Type-Options \"nosniff\"",
		"\tHeader set Referrer-Policy \"strict-origin-when-cross-origin\"",
		'</IfModule>',
		'',
		'<IfModule mod_mime.c>',
		"\tAddType image/webp .webp",
		"\tAddType font/woff2 .woff2",
		'</IfModule>',
	);
}

function blogpro_get_htaccess_path() {
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	return get_home_path() . '.htaccess';
}

function blogpro_write_htaccess_rules() {
	// Only makes sense on Apache/LiteSpeed running in Apache-compat mode.
	if ( isset( $_SERVER['SERVER_SOFTWARE'] ) && stripos( $_SERVER['SERVER_SOFTWARE'], 'nginx' ) !== false ) {
		return;
	}
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}

	$path = blogpro_get_htaccess_path();

	// insert_with_markers() creates the file if missing, and is safe to
	// call repeatedly — it replaces only the content between its own
	// BEGIN/END marker comments, leaving everything else untouched.
	if ( is_writable( dirname( $path ) ) && ( ! file_exists( $path ) || is_writable( $path ) ) ) {
		insert_with_markers( $path, BLOGPRO_HTACCESS_MARKER, blogpro_htaccess_rules() );
	}
}

function blogpro_remove_htaccess_rules() {
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	$path = blogpro_get_htaccess_path();
	if ( file_exists( $path ) && is_writable( $path ) ) {
		insert_with_markers( $path, BLOGPRO_HTACCESS_MARKER, array() );
	}
}
