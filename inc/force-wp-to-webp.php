<?php
/**
 * Force WebP-master serving across WordPress's core image functions.
 *
 * Every core image API (wp_get_attachment_image, the_post_thumbnail,
 * wp_get_attachment_image_url, get_the_post_thumbnail_url, etc.) flows
 * through the wp_get_attachment_image_src filter, so intercepting it here
 * swaps every returned URL to the attachment's WebP master
 * ({original-name}.webp, created by inc/media-optimize.php at upload).
 *
 * This only rewrites the URL. It never deletes or renames anything, and it
 * silently falls back to the original file when no WebP master exists
 * (older uploads, native .webp attachments, non-image files).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'wp_get_attachment_image_src', 'blogpro_force_webp_src', 10, 4 );

function blogpro_force_webp_src( $image, $attachment_id, $size, $icon ) {
	// $image = array( url, width, height, is_intermediate ) or false.
	if ( ! $image || empty( $image[0] ) ) return $image;

	// Only rewrite image URLs that point at a real jpg/png master; leave
	// everything else (native .webp uploads, svg, non-images) untouched.
	$url = $image[0];
	if ( ! preg_match( '/\.(jpe?g|png)$/i', $url ) ) return $image;

	// Resolve to the master WebP on disk ({original-name}.webp next to the
	// source file). Fall back to the original URL if there's no master.
	$file = get_attached_file( $attachment_id );
	if ( ! $file ) return $image;

	$master = pathinfo( $file, PATHINFO_DIRNAME ) . '/' . pathinfo( $file, PATHINFO_FILENAME ) . '.webp';
	if ( ! file_exists( $master ) || filesize( $master ) < 1 ) return $image;

	$image[0] = str_replace(
		pathinfo( $file, PATHINFO_FILENAME ) . '.' . pathinfo( $file, PATHINFO_EXTENSION ),
		pathinfo( $file, PATHINFO_FILENAME ) . '.webp',
		$url
	);

	return $image;
}
