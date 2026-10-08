<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Minify html output.
 *
 * @package Blog pro
 * @author Md. Nur Hossain Repon
 * @since 1.0.0
 */

function blogpro_minify_html_output_buffer_callback( $buffer ) {
	// Non-HTML responses (robots.txt, sitemaps, feeds, redirects) must not
	// be minified — regex passes would corrupt plain text and can fatal.
	// if ( 0 !== stripos( ltrim( $buffer ), '<!doctype' ) && 0 !== stripos( ltrim( $buffer ), '<html' ) ) {
	// 	return $buffer;
	// }
	$search = array(
		'/\>[^\S ]+/s',     // strip whitespaces after tags, except space
		'/[^\S ]+\</s',     // strip whitespaces before tags, except space
		'/(\s)+/s',         // shorten multiple whitespace sequences
		'/<!--(.|\s)*?-->/' // Remove HTML comments
	);

	$replace = array(
		'>',
		'<',
		'\\1',
		''
	);

	$buffer = preg_replace( $search, $replace, $buffer );

	return $buffer;
}

function blogpro_minify_html_output_start() {
    ob_start( "blogpro_minify_html_output_buffer_callback" );
}
add_action( 'template_redirect', 'blogpro_minify_html_output_start' );


