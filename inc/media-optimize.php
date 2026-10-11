<?php
/**
 * Image & video optimization — no plugin. Handles lazy-loading,
 * async decoding, correct sizing/srcset, LCP priority hints, and
 * lighter video embeds.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** WebP quality for every converted file: originals, sub-sizes and responsive variants. */
if ( ! defined( 'BLOGPRO_WEBP_QUALITY' ) ) {
	define( 'BLOGPRO_WEBP_QUALITY', 82 );
}

/** Widths of the responsive WebP variants (full size is always written). */
define( 'BLOGPRO_WEBP_WIDTHS', array( 320, 640, 1024 ) );

/* 1. Core quality for JPEG/PNG sub-sizes WordPress still writes. */
add_filter( 'wp_editor_set_quality', function ( $quality, $mime ) {
	if ( in_array( $mime, array( 'image/jpeg', 'image/webp' ), true ) ) return BLOGPRO_WEBP_QUALITY;
	if ( 'image/png' === $mime ) return 10; // 10 = GD compression level 9 (max)
	return $quality;
}, 10, 2 );

/**
 * Creates every WebP for one attachment: the full file, each registered size,
 * and the 320/640 responsive variants. JPEG/PNG originals and thumbnails are kept,
 * since core metadata still points at them.
 *
 * Runs on new uploads (wp_generate_attachment_metadata) and from the
 * "Optimize Existing Images" bulk tool. Returns the number of files written.
 */
function blogpro_convert_attachment_to_webp( $attachment_id, $metadata = null ) {
	if ( ! function_exists( 'imagewebp' ) ) return 0; // GD without WebP support — skip silently

	$mime = get_post_mime_type( $attachment_id );
	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) return 0;

	$file = get_attached_file( $attachment_id );
	if ( ! $file ) return 0;

	$count = blogpro_convert_to_webp( $file ) ? 1 : 0;

	if ( null === $metadata ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );
	}
	if ( ! empty( $metadata['sizes'] ) ) {
		$dir = trailingslashit( dirname( $file ) );
		foreach ( $metadata['sizes'] as $size ) {
			if ( blogpro_convert_to_webp( $dir . $size['file'] ) ) $count++;
		}
	}

	$count += blogpro_generate_responsive_webp( $file, $mime );
	return $count;
}
add_filter( 'wp_generate_attachment_metadata', function ( $metadata, $attachment_id ) {
	blogpro_convert_attachment_to_webp( $attachment_id, $metadata );
	return $metadata;
}, 20, 2 );

/**
 * Convert every raster upload to WebP before WordPress saves it. The original
 * is never stored: the uploaded temp file is overwritten with the WebP bytes,
 * and the name and type are changed to match.
 *
 * Detects the type from file content, not the extension. Non-raster uploads
 * (SVG, PDF, ...) pass through unchanged. If conversion fails, the upload is
 * rejected instead of falling back to the original.
 */
function blogpro_webp_upload_prefilter( $file ) {
	if ( ! function_exists( 'imagewebp' ) ) return $file;
	if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) return $file;

	$info = @getimagesize( $file['tmp_name'] );
	if ( ! $info || ! in_array( $info[2], array( IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP ), true ) ) {
		return $file;
	}
	if ( IMAGETYPE_WEBP === $info[2] ) {
		$file['type'] = 'image/webp';
		return $file;
	}

	$old_limit = ini_set( 'memory_limit', '512M' );
	$image     = blogpro_load_gd_image( $file['tmp_name'] );
	$ok        = $image && imagewebp( $image, $file['tmp_name'], BLOGPRO_WEBP_QUALITY );
	if ( $image ) imagedestroy( $image );
	ini_set( 'memory_limit', $old_limit );

	if ( ! $ok || 0 === filesize( $file['tmp_name'] ) ) {
		$file['error'] = __( 'Blog Pro could not convert this image to WebP, so the upload was stopped.', 'blog-pro' );
		return $file;
	}

	$file['name'] = pathinfo( $file['name'], PATHINFO_FILENAME ) . '.webp';
	$file['type'] = 'image/webp';
	$file['size'] = filesize( $file['tmp_name'] );
	return $file;
}
add_filter( 'wp_handle_upload_prefilter', 'blogpro_webp_upload_prefilter' );

/* Types the converter replaces. Animated GIFs lose their animation (GD reads one frame). */
define( 'BLOGPRO_CONVERTIBLE_MIMES', array( 'image/jpeg', 'image/png', 'image/gif' ) );

/**
 * Converts one existing attachment's main file to WebP at best quality, points
 * the attachment, its sizes and post content at the WebP files, then deletes
 * the old JPEG/PNG/GIF files.
 *
 * New files and metadata are written before anything old is deleted. If
 * metadata generation fails, the attachment is restored and nothing is removed.
 *
 * @return string 'converted', 'skipped' or 'failed'
 */
function blogpro_convert_original_to_webp( $attachment_id ) {
	global $wpdb;
	if ( ! function_exists( 'imagewebp' ) ) return 'failed';

	$mime = get_post_mime_type( $attachment_id );
	if ( ! in_array( $mime, BLOGPRO_CONVERTIBLE_MIMES, true ) ) return 'skipped';

	$old_file = get_attached_file( $attachment_id );
	if ( ! $old_file || ! file_exists( $old_file ) ) return 'skipped';

	$old_url  = wp_get_attachment_url( $attachment_id );
	$old_meta = wp_get_attachment_metadata( $attachment_id ) ?: array();
	$dir      = pathinfo( $old_file, PATHINFO_DIRNAME );
	$stem     = pathinfo( $old_file, PATHINFO_FILENAME );

	// Use the stem's WebP name unless another attachment already owns it.
	$new_file = $dir . '/' . $stem . '.webp';
	if ( blogpro_webp_path_in_use( $new_file, $attachment_id ) ) {
		$new_file = $dir . '/' . wp_unique_filename( $dir, $stem . '.webp' );
	}

	$old_limit = ini_set( 'memory_limit', '512M' );
	$image     = blogpro_load_gd_image( $old_file );
	$ok        = $image && imagewebp( $image, $new_file, BLOGPRO_WEBP_QUALITY );
	if ( $image ) imagedestroy( $image );
	ini_set( 'memory_limit', $old_limit );

	if ( ! $ok || ! file_exists( $new_file ) || 0 === filesize( $new_file ) ) {
		@unlink( $new_file );
		return 'failed';
	}

	// Point the attachment at the WebP file.
	update_attached_file( $attachment_id, $new_file );
	$wpdb->update( $wpdb->posts, array( 'post_mime_type' => 'image/webp' ), array( 'ID' => $attachment_id ) );
	clean_attachment_cache( $attachment_id );
	$new_url = wp_get_attachment_url( $attachment_id );
	$wpdb->update( $wpdb->posts, array( 'guid' => $new_url ), array( 'ID' => $attachment_id ) );

	// Old responsive variants (-320, -640) came from the old master. Remove them so they're rebuilt.
	$new_stem = pathinfo( $new_file, PATHINFO_FILENAME );
	foreach ( BLOGPRO_WEBP_WIDTHS as $width ) {
		@unlink( $dir . '/' . $new_stem . '-' . $width . '.webp' );
	}

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$new_meta = wp_generate_attachment_metadata( $attachment_id, $new_file );
	if ( is_wp_error( $new_meta ) || empty( $new_meta ) ) {
		blogpro_rollback_conversion( $attachment_id, $old_file, $old_url, $mime, $new_file );
		return 'failed';
	}
	wp_update_attachment_metadata( $attachment_id, $new_meta );

	// Repoint post content: main file, plus each old size by name. A size missing from the new metadata falls back to the full WebP.
	$new_sizes = $new_meta['sizes'] ?? array();
	$old_sizes = $old_meta['sizes'] ?? array();
	$pairs     = array( $old_url => $new_url );
	foreach ( $old_sizes as $name => $size ) {
		$target = isset( $new_sizes[ $name ] )
			? trailingslashit( dirname( $new_url ) ) . $new_sizes[ $name ]['file']
			: $new_url;
		$pairs[ trailingslashit( dirname( $old_url ) ) . $size['file'] ] = $target;
	}
	blogpro_replace_attachment_urls( $pairs, $stem );

	// Delete old files last. Anything the new metadata still uses is kept.
	$keep = array( $new_file );
	foreach ( $new_sizes as $size ) {
		$keep[] = $dir . '/' . $size['file'];
	}
	$stale = array( $old_file );
	foreach ( $old_sizes as $size ) {
		$stale[] = $dir . '/' . $size['file'];
	}
	if ( ! empty( $old_meta['original_image'] ) ) {
		$stale[] = $dir . '/' . $old_meta['original_image'];
	}
	foreach ( array_unique( $stale ) as $path ) {
		if ( ! in_array( $path, $keep, true ) && file_exists( $path ) ) @unlink( $path );
	}

	return 'converted';
}

/** True when another attachment already uses this absolute file path. */
function blogpro_webp_path_in_use( $abs_path, $attachment_id ) {
	global $wpdb;
	$upload = wp_upload_dir();
	$rel    = ltrim( str_replace( trailingslashit( wp_normalize_path( $upload['basedir'] ) ), '', wp_normalize_path( $abs_path ) ), '/' );
	return (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s AND post_id != %d LIMIT 1",
		$rel,
		$attachment_id
	) );
}

/** Restores the attachment to its original file after a failed metadata step. */
function blogpro_rollback_conversion( $attachment_id, $old_file, $old_url, $old_mime, $new_file ) {
	global $wpdb;
	update_attached_file( $attachment_id, $old_file );
	$wpdb->update( $wpdb->posts, array( 'post_mime_type' => $old_mime, 'guid' => $old_url ), array( 'ID' => $attachment_id ) );
	clean_attachment_cache( $attachment_id );
	if ( file_exists( $new_file ) ) @unlink( $new_file );
}

/**
 * Replaces old file URLs with new ones in post_content, including the escaped
 * form (\/) used in block JSON. Returns the number of posts changed.
 *
 * @param array  $pairs old URL => new URL
 * @param string $stem  file stem, used to narrow the search
 */
function blogpro_replace_attachment_urls( $pairs, $stem ) {
	global $wpdb;
	if ( empty( $pairs ) ) return 0;

	$posts = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s",
		'%' . $wpdb->esc_like( $stem ) . '%'
	) );

	$changed = 0;
	foreach ( $posts as $post ) {
		$content = $post->post_content;
		foreach ( $pairs as $old => $new ) {
			$content = str_replace(
				array( $old, addcslashes( $old, '/' ) ),
				array( $new, addcslashes( $new, '/' ) ),
				$content
			);
		}
		if ( $content !== $post->post_content ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $post->ID ) );
			clean_post_cache( $post->ID );
			$changed++;
		}
	}
	return $changed;
}

/**
 * Writes one WebP file from a JPEG/PNG source. Skips files that already exist
 * and are non-empty, and deletes 0-byte leftovers so a failed write is retried.
 */
function blogpro_convert_to_webp( $path ) {
	if ( ! file_exists( $path ) ) return false;
	$info = pathinfo( $path );
	if ( empty( $info['extension'] ) ) return false;
	$dest = $info['dirname'] . '/' . $info['filename'] . '.webp';
	if ( file_exists( $dest ) ) {
		if ( filesize( $dest ) > 0 ) return false; // already converted
		@unlink( $dest );
	}

	$old_limit = ini_set( 'memory_limit', '256M' ); // GD decompresses the whole image into memory
	$image     = blogpro_load_gd_image( $path );
	if ( ! $image ) { ini_set( 'memory_limit', $old_limit ); return false; }

	$ok = imagewebp( $image, $dest, BLOGPRO_WEBP_QUALITY );
	imagedestroy( $image );
	ini_set( 'memory_limit', $old_limit );

	if ( ! $ok || ! file_exists( $dest ) || 0 === filesize( $dest ) ) {
		@unlink( $dest ); // GD can report success while writing 0 bytes
		return false;
	}
	return true;
}

/**
 * Loads a JPEG, PNG, GIF or WebP as a truecolor GD image, keeping alpha.
 * Type comes from file content. Returns false for anything else.
 */
function blogpro_load_gd_image( $path ) {
	$info = @getimagesize( $path );
	if ( ! $info ) return false;

	switch ( $info[2] ) {
		case IMAGETYPE_JPEG: $image = @imagecreatefromjpeg( $path ); break;
		case IMAGETYPE_PNG:  $image = @imagecreatefrompng( $path ); break;
		case IMAGETYPE_GIF:  $image = @imagecreatefromgif( $path ); break;
		case IMAGETYPE_WEBP: $image = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false; break;
		default: return false;
	}
	if ( ! $image ) return false;

	if ( imageistruecolor( $image ) ) {
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		return $image;
	}

	// WebP needs truecolor, so copy palette images into a truecolor canvas.
	$w  = imagesx( $image );
	$h  = imagesy( $image );
	$tc = imagecreatetruecolor( $w, $h );
	imagealphablending( $tc, false );
	imagesavealpha( $tc, true );
	imagecopy( $tc, $image, 0, 0, 0, 0, $w, $h );
	imagedestroy( $image );
	return $tc;
}

/**
 * Writes the 320w and 640w WebP variants next to the full-size file.
 * Widths at or above the original are skipped. Returns the number written.
 */
function blogpro_generate_responsive_webp( $file, $mime ) {
	$orig = wp_getimagesize( $file );
	if ( ! $orig ) return 0;

	$orig_w = (int) $orig[0];
	$orig_h = (int) $orig[1];
	$info   = pathinfo( $file );
	$dir    = $info['dirname'];
	$base   = sanitize_file_name( $info['filename'] );

	$generated = 0;
	foreach ( BLOGPRO_WEBP_WIDTHS as $width ) {
		if ( $width >= $orig_w ) continue;
		$dest = $dir . '/' . $base . '-' . $width . '.webp';
		if ( file_exists( $dest ) && filesize( $dest ) > 0 ) continue;

		$old_limit = ini_set( 'memory_limit', '256M' );
		$src       = blogpro_load_gd_image( $file );
		if ( ! $src ) { ini_set( 'memory_limit', $old_limit ); continue; }

		$height = (int) round( $orig_h * $width / $orig_w );
		$dst    = imagecreatetruecolor( $width, $height );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $width, $height, $orig_w, $orig_h );
		imagedestroy( $src );

		$ok = imagewebp( $dst, $dest, BLOGPRO_WEBP_QUALITY );
		imagedestroy( $dst );
		ini_set( 'memory_limit', $old_limit );

		if ( $ok && file_exists( $dest ) && filesize( $dest ) > 0 ) {
			$generated++;
		} else {
			@unlink( $dest );
		}
	}
	return $generated;
}

/**
 * Every WebP file one attachment owns: the full file, each registered size,
 * and each responsive variant (-320, -640). Paths are de-duplicated.
 *
 * @return string[] Absolute paths. Files may not exist.
 */
function blogpro_attachment_webp_paths( $attachment_id ) {
	$file = get_attached_file( $attachment_id );
	if ( ! $file ) return array();

	$dir  = trailingslashit( dirname( $file ) );
	$base = pathinfo( $file, PATHINFO_FILENAME );
	$stems = array( $base );

	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( ! empty( $metadata['sizes'] ) ) {
		foreach ( $metadata['sizes'] as $size ) {
			$stems[] = pathinfo( $size['file'], PATHINFO_FILENAME );
		}
	}
	foreach ( BLOGPRO_WEBP_WIDTHS as $width ) {
		$stems[] = $base . '-' . $width;
	}

	$paths = array();
	foreach ( array_unique( $stems ) as $stem ) {
		$paths[] = $dir . $stem . '.webp';
	}
	return $paths;
}

/** Removes every WebP file of an attachment when it's deleted from the Media Library. */
function blogpro_delete_webp_on_attachment_removal( $post_id ) {
	foreach ( blogpro_attachment_webp_paths( $post_id ) as $path ) {
		if ( file_exists( $path ) ) @unlink( $path );
	}
}
add_action( 'delete_attachment', 'blogpro_delete_webp_on_attachment_removal' );





/* Serve the .webp version automatically in front-end markup when present. */
// function blogpro_maybe_use_webp( $html ) {
// 	return preg_replace_callback( '/(src|srcset)="([^"]+\.(jpe?g|png))"/i', function ( $m ) {
// 		$webp = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $m[2] );
// 		$path = str_replace( content_url(), WP_CONTENT_DIR, $webp );
// 		return file_exists( $path ) ? $m[1] . '="' . $webp . '"' : $m[0];
// 	}, $html );
// }
// add_filter( 'the_content', 'blogpro_maybe_use_webp', 20 );
// add_filter( 'post_thumbnail_html', 'blogpro_maybe_use_webp', 20 );

/* 2. Lazy-load + async decode all content/thumbnail images (WP 5.5+
      already lazy-loads by default; this reinforces decoding + explicit
      dimensions, which core doesn't always add). */
function blogpro_add_img_attributes( $attr, $attachment = null, $size = null ) {
	$attr['loading']  = isset( $attr['loading'] ) ? $attr['loading'] : 'lazy';
	$attr['decoding'] = 'async';
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'blogpro_add_img_attributes', 10, 3 );

/** Widths of the responsive WebP variants. The full-size file is always written too. */
function blogpro_responsive_widths() {
	return array( 320, 640, 1024 );
}

/** Public URL for a file inside wp-content/uploads. */
function blogpro_upload_url( $path ) {
	$upload = wp_upload_dir();
	$rel    = str_replace( trailingslashit( wp_normalize_path( $upload['basedir'] ) ), '', wp_normalize_path( $path ) );
	return trailingslashit( $upload['baseurl'] ) . ltrim( $rel, '/' );
}

/** True when a WebP file exists and is not a 0-byte failed write. */
function blogpro_webp_ready( $webp ) {
	return file_exists( $webp ) && filesize( $webp ) > 0;
}

/**
 * WebP src, srcset and dimensions for an attachment, built from files on disk.
 * Empty array when the full-size WebP is missing, so callers keep the original.
 *
 * @return array{src: string, srcset: string, width: int, height: int}|array
 */
function blogpro_webp_sources( $attachment_id ) {
	$file = get_attached_file( $attachment_id );
	$orig = $file ? wp_getimagesize( $file ) : false;
	if ( ! $orig ) return array();

	$info   = pathinfo( $file );
	$dir    = $info['dirname'];
	$base   = sanitize_file_name( $info['filename'] );
	$orig_w = (int) $orig[0];

	$full = $dir . '/' . $base . '.webp';
	if ( ! blogpro_webp_ready( $full ) ) return array();

	$entries = array();
	foreach ( blogpro_responsive_widths() as $w ) {
		$path = $dir . '/' . $base . '-' . $w . '.webp';
		if ( $w < $orig_w && blogpro_webp_ready( $path ) ) {
			$entries[] = esc_url( blogpro_upload_url( $path ) ) . ' ' . $w . 'w';
		}
	}
	$entries[] = esc_url( blogpro_upload_url( $full ) ) . ' ' . $orig_w . 'w';

	return array(
		'src'    => esc_url( blogpro_upload_url( $full ) ),
		'srcset' => implode( ', ', $entries ),
		'width'  => $orig_w,
		'height' => (int) $orig[1],
	);
}

/**
 * Swap src/srcset to WebP in wp_get_attachment_image() output.
 * Covers logo, widgets, and any image rendered outside the_content.
 */
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
	if ( is_admin() || ! $attachment instanceof WP_Post ) return $attr;

	$webp = blogpro_webp_sources( $attachment->ID );
	if ( $webp ) {
		$attr['src']    = $webp['src'];
		$attr['srcset'] = $webp['srcset'];
	}
	return $attr;
}, 15, 2 );

/**
 * Content images (wp-image-N inside the_content) carry only the full-size WebP.
 * Core builds srcset from intermediate sizes, which this theme removes, so
 * add the 320w/640w WebP variants here. Runs only when src is still the
 * attachment's own file.
 */
add_filter( 'wp_content_img_tag', function ( $filtered_image, $context, $attachment_id ) {
	if ( is_admin() || false === strpos( $filtered_image, 'wp-image-' ) ) return $filtered_image;

	$webp = blogpro_webp_sources( (int) $attachment_id );
	if ( ! $webp ) return $filtered_image;

	$filtered_image = preg_replace( '/\ssrcset="[^"]*"/', '', $filtered_image );
	$filtered_image = preg_replace( '/\ssrc="[^"]*"/', ' src="' . esc_url( $webp['src'] ) . '" srcset="' . esc_attr( $webp['srcset'] ) . '" sizes="' . esc_attr( blogpro_content_img_sizes() ) . '"', $filtered_image, 1 );
	return $filtered_image;
}, 10, 3 );

/* The single LCP image (post header thumbnail) should NOT be lazy —
   it should load eagerly with high priority so it paints first. */
function blogpro_lcp_image_attributes( $attr, $attachment = null ) {
	// LCP only: the featured image of the singular post in the main loop.
	// Every wp_get_attachment_image() on the page passes through here —
	// galleries and content images must stay lazy/normal priority.
	if ( is_singular() && in_the_loop() && is_main_query() && $attachment ) {
		$post_id = get_queried_object_id();
		if ( $attachment instanceof WP_Post && (int) get_post_thumbnail_id( $post_id ) === (int) $attachment->ID ) {
			$attr['loading']       = 'eager';
			$attr['fetchpriority'] = 'high';
		}
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'blogpro_lcp_image_attributes', 9, 2 );
// add_filter( 'post_thumbnail_html', function ( $html ) {
// 	if ( is_singular() ) {
// 		$html = str_replace( ' loading="lazy"', ' loading="eager" fetchpriority="high"', $html );
// 	}
// 	return $html;
// } );
add_filter( 'post_thumbnail_html', function ( $html, $post_id ) {
	if ( is_singular() && $post_id === get_queried_object_id() && in_the_loop() && is_main_query() ) {
		$html = str_replace( ' loading="lazy"', ' loading="eager" fetchpriority="high"', $html );
	}
	return $html;
}, 10, 2 );

/* 3 & 4 moved to inc/video-optimisation.php — lazy embed iframes and
   self-hosted <video> hardening (preload/mime/sizing/schema) all live
   in the video module now. */

/* 5. Strip bulky image metadata (EXIF/XMP) on upload to shrink file size
      without touching visible quality. */
/* Every size WordPress generates (thumbnails, medium, large, theme sizes) is WebP. */
add_filter( 'image_editor_output_format', function ( $formats ) {
	foreach ( array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ) as $mime ) {
		$formats[ $mime ] = 'image/webp';
	}
	return $formats;
} );

/* 6. Cap max upload dimensions so nobody accidentally serves a 6000px
      camera photo to a 600px card. */
/**
 * On upload: fill alt, title and caption from the file name.
 * WordPress core does NOT set alt automatically from filename — we do it here.
 * Runs late (add_attachment fires before metadata/generation) so every field
 * is populated before the WebP pass and the media modal reads them.
 */
function blogpro_auto_image_fields( $attachment_id ) {
	$file = get_attached_file( $attachment_id );
	if ( ! $file ) return;

	// Only images — leave PDFs, audio, video alone.
	if ( 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) return;

	$meta = wp_get_attachment_metadata( $attachment_id );
	$slug = isset( $meta['file'] ) ? $meta['file'] : $file;

	// base name, minus any directory and extension, minus WP's "-1024x512" suffixes
	$name = pathinfo( basename( $slug ), PATHINFO_FILENAME );
	$name = preg_replace( '/[-_]\d+x\d+$/', '', $name );

	// humanise: "bus_rental_dubai-2" → "Bus Rental Dubai 2"
	$phrase = trim( preg_replace( '/[-_.]+/', ' ', preg_replace( '/[^A-Za-z0-9]+/', '-', $name ) ) );
	$phrase = preg_replace( '/\s+/', ' ', $phrase );
	if ( '' === $phrase ) return;

	$alt = ucfirst( $phrase );

	// alt — keep a manually-set value if one already exists
	$cur_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	if ( '' === trim( (string) $cur_alt ) ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
	}

	// title + caption + description — WP's default title is the raw filename
	// ("IMG_2048"), so replace it only while it still matches that; edited
	// titles, captions and descriptions are left alone.
	$post   = get_post( $attachment_id );
	$update = array( 'ID' => $attachment_id );
	if ( $post ) {
		$raw_title = strtolower( pathinfo( basename( $file ), PATHINFO_FILENAME ) );
		if ( '' === trim( (string) $post->post_title ) || strtolower( (string) $post->post_title ) === $raw_title ) {
			$update['post_title'] = $alt;
		}
		// caption mirrors the content image caption, so only set it when empty
		if ( '' === trim( (string) $post->post_excerpt ) ) {
			$update['post_excerpt'] = $alt;
		}
		// description — WP leaves it empty; fill with the same phrase
		if ( '' === trim( (string) $post->post_content ) ) {
			$update['post_content'] = $alt;
		}
	}
	if ( count( $update ) > 1 ) {
		wp_update_post( $update );
	}
}
add_action( 'add_attachment', 'blogpro_auto_image_fields', 99, 1 );

/**
 * Backfill alt/title/caption/description for images already in the library
 * whose fields are still empty. Idempotent — filled fields are skipped.
 *
 * @return array stats: 'updated' => attachment count.
 */
function blogpro_backfill_image_fields() {
	global $wpdb;
	$stats   = array( 'updated' => 0 );
	$att_ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
	);
	foreach ( $att_ids as $att_id ) {
		$att_id = (int) $att_id;
		$post   = get_post( $att_id );
		if ( ! $post ) continue;
		$has_alt = '' !== trim( (string) get_post_meta( $att_id, '_wp_attachment_image_alt', true ) );
		if ( $has_alt && $post->post_content && $post->post_excerpt ) continue;
		blogpro_auto_image_fields( $att_id );
		$stats['updated']++;
	}
	return $stats;
}

add_filter( 'big_image_size_threshold', function () { return 1600; } );

/* Theme image size. REST `featured_image` requests blogpro-card. Nothing else needs a custom size. */
add_action( 'after_setup_theme', function () {
	add_image_size( 'blogpro-card', 480, 320, true );
} );

/* 7. Responsive `sizes` attribute tuned to this theme's actual layouts
      instead of WP's generic (max-width: X) 100vw guess. */
/**
 * `sizes` for post body + hero images. Matches the rendered column width
 * (~665px on single posts, PageSpeed reports displayed size 665px), so the
 * browser does not pick the 1280w file for a 665px slot.
 */
function blogpro_content_img_sizes() {
	return '(max-width: 820px) calc(100vw - 2rem), 665px';
}

function blogpro_responsive_sizes( $sizes, $size, $image_src, $image_meta, $attachment_id ) {
	if ( is_singular() ) {
		return blogpro_content_img_sizes();
	}
	return '(max-width: 480px) 100vw, (max-width: 900px) 50vw, 480px';
}
add_filter( 'wp_calculate_image_sizes', 'blogpro_responsive_sizes', 10, 5 );


add_filter( 'intermediate_image_sizes', function ( $sizes ) {
	// Keep the sizes the theme requests (see blogpro_keep_image_sizes()). Drop the rest.
	return array_diff( $sizes, array( '1536x1536', '2048x2048' ) );
} );

add_filter( 'image_size_names_choose', function( $sizes ) {
	return $sizes;
} );


/**
 * Responsive <img> with 3-size srcset (320w, 640w, full).
 * Uses the WebP files generated by blogpro_convert_attachment_to_webp().
 */
function blogpro_responsive_img( $attachment_id, $args = array() ) {
	$webp = blogpro_webp_sources( $attachment_id );
	if ( ! $webp ) return '';

	$loading       = isset( $args['loading'] ) ? $args['loading'] : 'lazy';
	$fetchpriority = ( 'eager' === $loading ) ? ' fetchpriority="high"' : '';

	return sprintf(
		'<img src="%s" srcset="%s" width="%d" height="%d" sizes="%s" alt="%s" loading="%s" decoding="async"%s class="%s">',
		$webp['src'],
		$webp['srcset'],
		$webp['width'],
		$webp['height'],
		esc_attr( isset( $args['sizes'] ) ? $args['sizes'] : '100vw' ),
		esc_attr( isset( $args['alt'] ) ? $args['alt'] : '' ),
		esc_attr( $loading ),
		$fetchpriority,
		esc_attr( isset( $args['class'] ) ? $args['class'] : '' )
	);
}

/* ---------------------------------------------------------------------
 * Cleanup — remove leftover intermediate sizes this theme doesn't use.
 *
 * The `intermediate_image_sizes` filter above stops WP from generating
 * medium / medium_large / 1536 / 2048 for NEW uploads, but sizes created
 * before the theme (or by a previous theme/plugin) still sit in metadata
 * and on disk. This pass strips them from every attachment — including
 * their WebP twins — and prunes the metadata. Runs only from the Cleanup admin page.
 * ------------------------------------------------------------------- */

/**
 * Image sizes this theme actually uses and must keep.
 *
 * @return string[]
 */
function blogpro_keep_image_sizes() {
	// thumbnail: SEO, medium_large: video schema, large: social share + video schema,
	// blogpro-card: REST featured_image. Everything else is removed.
	return array( 'thumbnail', 'medium_large', 'large', 'blogpro-card' );
}

/**
 * One pass: remove unused intermediate sizes from every image attachment.
 * Returns stats for reporting.
 *
 * @return array
 */
function blogpro_cleanup_unused_image_sizes() {
	global $wpdb;
	$stats = array( 'images' => 0, 'bytes' => 0 );

	$keep        = blogpro_keep_image_sizes();
	$attachments = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
	);

	foreach ( $attachments as $att_id ) {
		$meta = wp_get_attachment_metadata( (int) $att_id );
		if ( empty( $meta['sizes'] ) || empty( $meta['file'] ) ) {
			continue;
		}
		$att_file = get_attached_file( (int) $att_id );
		if ( ! $att_file ) {
			continue;
		}
		$dir = trailingslashit( dirname( $att_file ) );

		foreach ( $meta['sizes'] as $name => $size ) {
			if ( in_array( $name, $keep, true ) ) {
				continue;
			}
			$file = $dir . $size['file'];
			foreach ( array( $file, $dir . pathinfo( $file, PATHINFO_FILENAME ) . '.webp' ) as $target ) {
				if ( $target && file_exists( $target ) && @unlink( $target ) ) {
					$stats['bytes'] += filesize( $target ) ?: 0;
					$stats['images']++;
				}
			}
			unset( $meta['sizes'][ $name ] );
		}
		wp_update_attachment_metadata( (int) $att_id, $meta );
	}

	return $stats;
}
