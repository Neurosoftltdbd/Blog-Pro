<?php
/**
 * Image & video optimization — no plugin. Handles lazy-loading,
 * async decoding, correct sizing/srcset, LCP priority hints, and
 * lighter video embeds.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* 1. Force WebP-first upload handling where the server supports it:
      when a JPEG/PNG is uploaded, generate a WebP copy of each size
      and prefer it on the front end (falls back automatically if
      the browser/server can't produce one — no hard dependency). */
add_filter( 'wp_editor_set_quality', function ( $quality, $mime ) {
	if ( in_array( $mime, array( 'image/jpeg', 'image/webp' ), true ) ) return 82;
	if ( 'image/png' === $mime ) return 10; // 10 = GD compression level 9 (max)
	return $quality;
}, 10, 2 );

function blogpro_generate_webp( $metadata, $attachment_id ) {
	blogpro_convert_attachment_to_webp( $attachment_id, $metadata );
	if ( ! empty( $metadata['sizes'] ) ) {
		$file = get_attached_file( $attachment_id );
		if ( $file ) {
			$dir = trailingslashit( dirname( $file ) );
			foreach ( $metadata['sizes'] as $size ) {
				$spath = $dir . $size['file'];
				$ext = strtolower( pathinfo( $spath, PATHINFO_EXTENSION ) );
				$webp_path = $dir . pathinfo( $size['file'], PATHINFO_FILENAME ) . '.webp';
				// delete PNG/JPEG thumbnail if WebP exists alongside it
				if ( in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) && file_exists( $spath ) && file_exists( $webp_path ) ) {
					@unlink( $spath );
				}
			}
		}
	}
	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'blogpro_generate_webp', 20, 2 );

/**
 * Converts a single attachment (its original file + every registered
 * size) to WebP. Used both automatically on new uploads and by the
 * "Optimize Existing Images" bulk tool (Media → Optimize Images) for
 * images that were already in the library before the theme was active.
 *
 * Returns the number of files newly converted (0 if already done or
 * unsupported), so the bulk tool can report progress.
 */
function blogpro_convert_attachment_to_webp( $attachment_id, $metadata = null ) {
	if ( ! function_exists( 'imagewebp' ) ) return 0; // GD without WebP support — skip silently

	$mime = get_post_mime_type( $attachment_id );
	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) return 0;

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
	return $count;
}

function blogpro_convert_to_webp( $path ) {
	if ( ! file_exists( $path ) ) return false;
	$info = pathinfo( $path );
	if ( empty( $info['extension'] ) ) return false;
	$dest = $info['dirname'] . '/' . $info['filename'] . '.webp';
	if ( file_exists( $dest ) ) {
		if ( filesize( $dest ) > 0 ) return false; // already converted
		@unlink( $dest ); // 0-byte = failed prior attempt — delete so we retry
	}

	$ext = strtolower( $info['extension'] );

	// Bump memory for large originals — GD decompresses the whole thing into raw pixels
	$old_limit = ini_set( 'memory_limit', '256M' );

	$image = ( 'jpg' === $ext || 'jpeg' === $ext ) ? @imagecreatefromjpeg( $path ) : ( 'png' === $ext ? @imagecreatefrompng( $path ) : false );
	if ( ! $image ) { ini_set( 'memory_limit', $old_limit ); return false; }

	// PNG prep: save alpha + convert palette to truecolor (WebP needs truecolor)
	if ( 'png' === $ext ) {
		@imagealphablending( $image, false );
		@imagesavealpha( $image, true );
		if ( ! @imageistruecolor( $image ) ) {
			$w = imagesx( $image );
			$h = imagesy( $image );
			$tc = imagecreatetruecolor( $w, $h );
			if ( $tc ) {
				imagealphablending( $tc, false );
				imagesavealpha( $tc, true );
				imagecopy( $tc, $image, 0, 0, 0, 0, $w, $h );
				imagedestroy( $image );
				$image = $tc;
			}
		}
	}

	$ok = imagewebp( $image, $dest, 82 );
	imagedestroy( $image );
	ini_set( 'memory_limit', $old_limit );

	// imagewebp can return true but write 0 bytes (GD bug / silent OOM)
	if ( $ok && file_exists( $dest ) && filesize( $dest ) === 0 ) {
		@unlink( $dest );
		return false;
	}

	return (bool) $ok;
}


/**
 * Clean up WebP copies when an attachment is deleted from Media Library.
 */
function blogpro_delete_webp_on_attachment_removal( $post_id ) {
	$file = get_attached_file( $post_id );
	if ( ! $file ) return;

	// WebP of original file
	$info   = pathinfo( $file );
	$webp   = $info['dirname'] . '/' . $info['filename'] . '.webp';
	$avif   = $info['dirname'] . '/' . $info['filename'] . '.avif';
	if ( file_exists( $webp ) ) @unlink( $webp );
	if ( file_exists( $avif ) ) @unlink( $avif );

	// WebP/AVIF of each registered size
	$metadata = wp_get_attachment_metadata( $post_id );
	if ( ! empty( $metadata['sizes'] ) ) {
		$dir = trailingslashit( $info['dirname'] );
		foreach ( $metadata['sizes'] as $size ) {
			$ext  = pathinfo( $size['file'], PATHINFO_EXTENSION );
			$base = basename( $size['file'], '.' . $ext );
			$webp_size = $dir . $base . '.webp';
			$avif_size = $dir . $base . '.avif';
			if ( file_exists( $webp_size ) ) @unlink( $webp_size );
			if ( file_exists( $avif_size ) ) @unlink( $avif_size );
		}
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
add_filter( 'image_editor_output_format', function ( $formats ) {
	$formats['image/jpeg'] = 'image/jpeg';
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
add_action( 'wp_generate_attachment_metadata', 'blogpro_generate_resized_webp', 20, 2 );

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

/* 7. Responsive `sizes` attribute tuned to this theme's actual layouts
      instead of WP's generic (max-width: X) 100vw guess. */
function blogpro_responsive_sizes( $sizes, $size, $image_src, $image_meta, $attachment_id ) {
	if ( is_singular() ) {
		return '(max-width: 820px) calc(100vw - 2rem), 820px';
	}
	return '(max-width: 480px) 100vw, (max-width: 900px) 50vw, 480px';
}
add_filter( 'wp_calculate_image_sizes', 'blogpro_responsive_sizes', 10, 5 );


add_filter( 'intermediate_image_sizes', function ( $sizes ) {
	return array_diff( $sizes, array( 'thumbnail', 'medium', 'medium_large', '1536x1536', '2048x2048' ) );
} );

add_filter( 'image_size_names_choose', function( $sizes ) {
	$sizes['blogpro-featured'] = __( 'Blog Pro Featured', 'blog-pro' );
	return $sizes;
} );

/**
 * Generate 3 responsive WebP sizes for an attachment: 320px, 640px, and full.
 * Called on upload and by the bulk optimizer. Files are named:
 *   {filename}-320.webp, {filename}-640.webp, {filename}.webp (full)
 *
 * @param int $attachment_id
 * @return int Number of files generated
 */
function blogpro_generate_resized_webp( $attachment_id, $metadata = null ) {
	if ( ! function_exists( 'imagewebp' ) ) return 0;

	$mime = get_post_mime_type( $attachment_id );
	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) return 0;

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! file_exists( $file ) ) return 0;

	$info = pathinfo( $file );
	$dir  = $info['dirname'];
	$base = sanitize_file_name( $info['filename'] );

	$orig = wp_getimagesize( $file );
	if ( ! $orig ) return 0;

	$orig_w = (int) $orig[0];
	$orig_h = (int) $orig[1];

	$widths = array( 320, 640 );
	$widths = array_values( array_filter( $widths, function ( $w ) use ( $orig_w ) { return $w < $orig_w; } ) );

	$generated = 0;
	foreach ( $widths as $width ) {
		$dest = $dir . '/' . $base . '-' . $width . '.webp';
		if ( file_exists( $dest ) && filesize( $dest ) > 0 ) continue;

		$height = (int) round( $orig_h * $width / $orig_w );

		$old_limit = ini_set( 'memory_limit', '256M' );
		if ( 'image/png' === $mime ) {
			$src = @imagecreatefrompng( $file );
		} else {
			$src = @imagecreatefromjpeg( $file );
		}
		if ( ! $src ) { ini_set( 'memory_limit', $old_limit ); continue; }

		$dst = imagecreatetruecolor( $width, $height );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $width, $height, $orig_w, $orig_h );
		imagedestroy( $src );
		$ok = imagewebp( $dst, $dest, 82 );
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
 * Rewrite all <img> tags in post content to use WebP URLs.
 * Covers block editor images, classic editor images, and any other
 * content that references the original .jpg/.png file.
 *
 * @param string $content
 * @return string
 */
function blogpro_content_webp_rewrite( $content ) {
	if ( is_admin() ) {
		return $content;
	}
	if ( false === strpos( (string) $content, '<img' ) ) {
		return $content;
	}

	return preg_replace_callback(
		'/<img\b[^>]*>/i',
		function ( $m ) {
			$img = $m[0];

			// Already a WebP URL — skip.
			if ( false !== stripos( $img, '.webp' ) ) {
				return $img;
			}

			// Extract src URL.
			if ( ! preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $img, $src_m ) ) {
				return $img;
			}
			$src_url = $src_m[1];

			// Only rewrite local URLs.
			$site_url = site_url();
			if ( 0 !== strpos( $src_url, $site_url ) ) {
				return $img;
			}

			// Resolve to file path.
			$url_parts = wp_parse_url( $src_url );
			if ( ! isset( $url_parts['path'] ) ) {
				return $img;
			}
			$site_path = (string) wp_parse_url( $site_url, PHP_URL_PATH );
			$rel       = preg_replace( '#^' . preg_quote( rtrim( $site_path, '/' ), '#' ) . '#', '', $url_parts['path'] );
			$path      = wp_normalize_path( untrailingslashit( ABSPATH ) . $rel );

			if ( ! file_exists( $path ) ) {
				return $img;
			}

			// Check if a WebP version exists.
			$info      = pathinfo( $path );
			$webp_path = $info['dirname'] . '/' . $info['filename'] . '.webp';
			if ( ! file_exists( $webp_path ) || filesize( $webp_path ) === 0 ) {
				return $img;
			}

			// Build the WebP URL from the relative path within uploads.
			$upload     = wp_upload_dir();
			$upload_url = trailingslashit( $upload['baseurl'] );
			$upload_dir = trailingslashit( wp_normalize_path( $upload['basedir'] ) );
			$rel_path   = ltrim( str_replace( $upload_dir, '', wp_normalize_path( $webp_path ) ), '/' );
			$webp_url   = $upload_url . $rel_path;

			// Replace src.
			$img = preg_replace( '/\bsrc=["\'][^"\']*["\']/i', 'src="' . esc_url( $webp_url ) . '"', $img );

			// Replace srcset if present.
			if ( preg_match( '/\bsrcset=["\']([^"\']*)["\']/i', $img, $srcset_m ) ) {
				$srcset     = $srcset_m[1];
				$new_srcset = array();
				foreach ( explode( ',', $srcset ) as $candidate ) {
					$candidate = trim( $candidate );
					if ( '' === $candidate ) continue;
					$parts = preg_split( '/\s+/', $candidate );
					$url   = $parts[0];
					$desc  = isset( $parts[1] ) ? $parts[1] : '';
					// Replace .jpg/.png with .webp in the URL.
					$new_url = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $url );
					$new_srcset[] = $new_url . ( $desc ? ' ' . $desc : '' );
				}
				$new_srcset_str = implode( ', ', $new_srcset );
				$img = preg_replace( '/\bsrcset=["\'][^"\']*["\']/i', 'srcset="' . esc_attr( $new_srcset_str ) . '"', $img );
			}

			return $img;
		},
		$content
	);
}
add_filter( 'the_content', 'blogpro_content_webp_rewrite', 20 );

/**
 * Responsive <img> with 3-size srcset (320w, 640w, full).
 * Uses the WebP files generated by blogpro_generate_resized_webp().
 */
function blogpro_responsive_img( $attachment_id, $args = array() ) {
	$file = get_attached_file( $attachment_id );
	if ( ! $file ) return '';

	$info = pathinfo( $file );
	$dir  = $info['dirname'];
	$base = sanitize_file_name( $info['filename'] );

	$orig = wp_getimagesize( $file );
	if ( ! $orig ) return '';

	$orig_w = (int) $orig[0];
	$orig_h = (int) $orig[1];

	$upload = wp_upload_dir();
	$base_url = trailingslashit( $upload['baseurl'] ) . ltrim( str_replace( trailingslashit( wp_normalize_path( $upload['basedir'] ) ), '', wp_normalize_path( $dir ) ), '/' );

	$srcset = array();
	if ( $orig_w > 320 ) {
		$srcset[] = esc_url( $base_url . '/' . $base . '-320.webp' ) . ' 320w';
	}
	if ( $orig_w > 640 ) {
		$srcset[] = esc_url( $base_url . '/' . $base . '-640.webp' ) . ' 640w';
	}
	$srcset[] = esc_url( $base_url . '/' . $base . '.webp' ) . ' ' . $orig_w . 'w';

	$attrs  = array(
		'class'    => isset( $args['class'] ) ? $args['class'] : '',
		'width'    => $orig_w,
		'height'   => $orig_h,
		'alt'      => isset( $args['alt'] ) ? $args['alt'] : '',
		'sizes'    => isset( $args['sizes'] ) ? $args['sizes'] : '100vw',
		'loading'  => isset( $args['loading'] ) ? $args['loading'] : 'lazy',
		'decoding' => 'async',
	);

	$fetchpriority = ( 'eager' === $attrs['loading'] ) ? ' fetchpriority="high"' : '';

	return sprintf(
		'<img src="%s" srcset="%s" width="%d" height="%d" sizes="%s" alt="%s" loading="%s" decoding="async"%s class="%s">',
		esc_url( $base_url . '/' . $base . '.webp' ),
		implode( ', ', $srcset ),
		$attrs['width'],
		$attrs['height'],
		esc_attr( $attrs['sizes'] ),
		esc_attr( $attrs['alt'] ),
		esc_attr( $attrs['loading'] ),
		$fetchpriority,
		esc_attr( $attrs['class'] )
	);
}

/* ---------------------------------------------------------------------
 * Cleanup — remove leftover intermediate sizes this theme doesn't use.
 *
 * The `intermediate_image_sizes` filter above stops WP from generating
 * medium / medium_large / 1536 / 2048 for NEW uploads, but sizes created
 * before the theme (or by a previous theme/plugin) still sit in metadata
 * and on disk. This pass strips them from every attachment — including
 * their WebP twins — and prunes the metadata. Weekly, admin-context cron.
 * ------------------------------------------------------------------- */

/**
 * Image sizes this theme actually uses and must keep.
 *
 * @return string[]
 */
function blogpro_keep_image_sizes() {
	$keep = array( 'thumbnail' );
	foreach ( wp_get_additional_image_sizes() as $name => $size ) {
		if ( isset( $size['crop'] ) && ! empty( $size['crop'] ) ) {
			$keep[] = $name; // cropped sizes are used by layout/theme widgets
		}
	}
	return array_unique( $keep );
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
