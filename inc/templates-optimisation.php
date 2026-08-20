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

/**
 * Lazy load images, iframes, and videos in post content.
 */
function blogpro_lazy_load_media( $content ) {
	// Lazy load images
	$content = preg_replace_callback(
		'/<img[^>]+>/i',
		function( $matches ) {
			$img = $matches[0];
			if ( strpos( $img, 'loading=' ) === false ) {
				$img = str_replace( '<img', '<img loading="lazy"', $img );
			}
			return $img;
		},
		$content
	);

	// Lazy load iframes
	$content = preg_replace_callback(
		'/<iframe[^>]+>/i',
		function( $matches ) {
			$iframe = $matches[0];
			if ( strpos( $iframe, 'loading=' ) === false ) {
				$iframe = str_replace( '<iframe', '<iframe loading="lazy"', $iframe );
			}
			return $iframe;
		},
		$content
	);

	// Lazy load videos (preload="none" + loading="lazy")
	$content = preg_replace_callback(
		'/<video[^>]+>/i',
		function( $matches ) {
			$video = $matches[0];
			if ( strpos( $video, 'preload=' ) === false ) {
				$video = str_replace( '<video', '<video preload="none"', $video );
			}
			if ( strpos( $video, 'loading=' ) === false ) {
				$video = str_replace( '<video', '<video loading="lazy"', $video );
			}
			return $video;
		},
		$content
	);

	return $content;
}
add_filter( 'the_content', 'blogpro_lazy_load_media', 99 );
add_filter( 'widget_text_content', 'blogpro_lazy_load_media', 99 );
