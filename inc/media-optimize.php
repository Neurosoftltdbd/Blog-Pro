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
add_filter( 'wp_generate_attachment_metadata', 'blogpro_generate_webp', 10, 2 );

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
	if ( file_exists( $webp ) ) @unlink( $webp );

	// WebP of each registered size
	$metadata = wp_get_attachment_metadata( $post_id );
	if ( ! empty( $metadata['sizes'] ) ) {
		$dir = trailingslashit( $info['dirname'] );
		foreach ( $metadata['sizes'] as $size ) {
			$ext  = pathinfo( $size['file'], PATHINFO_EXTENSION );
			$base = basename( $size['file'], '.' . $ext );
			$webp_size = $dir . $base . '.webp';
			if ( file_exists( $webp_size ) ) @unlink( $webp_size );
		}
	}
}
add_action( 'delete_attachment', 'blogpro_delete_webp_on_attachment_removal' );

/* 2. Picture-element fallback (replace blogpro_maybe_use_webp) */
function blogpro_maybe_use_picture( $html ) {
    return preg_replace_callback(
        '/<img([^>]+)(src|srcset)=["\']([^"\']+\.(jpe?g|png))["\']([^>]*)>/i',
        function ( $m ) {
            $webp_url = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $m[3] );
            $path = str_replace( content_url(), WP_CONTENT_DIR, $webp_url );
            if ( ! file_exists( $path ) ) {
                return $m[0];
            }
            // Rebuild whole <img> — replace every .jpg/.png URL with .webp (covers src+srcset)
            $img_tag = '<img' . $m[1] . $m[2] . '="' . esc_url( $webp_url ) . '"' . $m[5] . '>';
            $img_tag = preg_replace( '/\.(jpe?g|png)(\s|")/i', '.webp$2', $img_tag );
            return '<picture>'
                 . '<source type="image/webp" srcset="' . esc_url( $webp_url ) . '">' . $img_tag . '</picture>';
        },
        $html
    );
}
add_filter('the_content', 'blogpro_maybe_use_picture', 20);
add_filter('post_thumbnail_html', 'blogpro_maybe_use_picture', 20);



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
function blogpro_lcp_image_attributes( $attr ) {
	if ( is_singular() ) {
		$attr['loading']      = 'eager';
		$attr['fetchpriority'] = 'high';
	}
	return $attr;
}
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

/* 3. Lazy-load iframes (YouTube/Vimeo embeds) and defer their weight. */
function blogpro_lazy_iframes( $html ) {
	if ( false === strpos( $html, 'loading=' ) ) {
		$html = str_replace( '<iframe ', '<iframe loading="lazy" ', $html );
	}
	return $html;
}
add_filter( 'embed_oembed_html', 'blogpro_lazy_iframes', 10, 1 );
add_filter( 'the_content', 'blogpro_lazy_iframes', 15 );

/* 4. Self-hosted <video> — use metadata-only preload so the whole file
      doesn't download until the visitor presses play. */
function blogpro_video_preload( $html, $atts ) {
	if ( false === strpos( $html, 'preload=' ) ) {
		$html = str_replace( '<video ', '<video preload="metadata" ', $html );
	}
	return $html;
}
add_filter( 'wp_video_shortcode', 'blogpro_video_preload', 10, 2 );

/* 5. Strip bulky image metadata (EXIF/XMP) on upload to shrink file size
      without touching visible quality. */
add_filter( 'image_editor_output_format', function ( $formats ) {
	$formats['image/jpeg'] = 'image/jpeg';
	return $formats;
} );

/* 6. Cap max upload dimensions so nobody accidentally serves a 6000px
      camera photo to a 600px card. */
add_filter( 'big_image_size_threshold', function () { return 1600; } );

/* 7. Responsive `sizes` attribute tuned to this theme's actual layouts
      instead of WP's generic (max-width: X) 100vw guess. */
function blogpro_responsive_sizes( $sizes, $size, $image_src, $image_meta, $attachment_id ) {
	if ( is_singular() ) {
		return '(max-width: 820px) 100vw, 820px';
	}
	return '(max-width: 480px) 100vw, (max-width: 900px) 50vw, 480px';
}
add_filter( 'wp_calculate_image_sizes', 'blogpro_responsive_sizes', 10, 5 );


// disable '1536x1536' and '2048x2048' intermediate sizes — nothing in this theme uses them
add_filter( 'wp_loaded', function () {
	remove_image_size( '1536x1536' );
	remove_image_size( '2048x2048' );
} );

/* 8. Register a featured-image size matching this theme's actual
      display width, so srcset has a properly-sized candidate. */
add_image_size( 'blogpro-featured', 820, 461, true );

add_filter( 'image_size_names_choose', function( $sizes ) {
	$sizes['blogpro-featured'] = __( 'Blog Pro Featured' );
	return $sizes;
} );

/* 9. On-the-fly WebP resizing — one original per upload. The browser
      asks for the width it needs (srcset), PHP resizes it (height auto,
      from aspect ratio) and serves WebP. Derived sizes are cached on
      disk so repeat requests are a plain readfile, not a re-resize. */
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'blogpro_img_id';
	$vars[] = 'blogpro_w';
	return $vars;
} );

add_action( 'init', function () {
	// add_rewrite_rule( '^blogpro-img/(\d+)/(\d+)/?$', 'index.php?blogpro_img_id=$matches[1]&blogpro_w=$matches[2]', 'top' );
	add_rewrite_rule( '^blogpro-img/(\d+)/(\d+)\.webp$', 'index.php?blogpro_img_id=$matches[1]&blogpro_w=$matches[2]', 'top' );
} );

add_action( 'after_switch_theme', 'flush_rewrite_rules' );
add_action( 'init', function () {
	// one-time flush when this rewrite rule was added after theme activation
	if ( get_option( 'blogpro_rewrite_flush_v' ) !== BLOGPRO_VERSION ) {
		flush_rewrite_rules();
		update_option( 'blogpro_rewrite_flush_v', BLOGPRO_VERSION );
	}
}, 99 );

add_action( 'parse_request', function ( $wp ) {
	// note: get_query_var() reads $wp_query->query_vars, which is not yet
	// populated during parse_request — read the $wp object's vars directly
	$id = absint( isset( $wp->query_vars['blogpro_img_id'] ) ? $wp->query_vars['blogpro_img_id'] : 0 );
	if ( ! $id ) return; // not an image request
	$width = absint( isset( $wp->query_vars['blogpro_w'] ) ? $wp->query_vars['blogpro_w'] : 0 ) ?: 1600;
	blogpro_serve_resized_webp( $id, $width );
	exit;
}, 0 );

function blogpro_serve_resized_webp( $id, $width ) {
	if ( ! function_exists( 'imagewebp' ) ) wp_die( '', '', array( 'response' => 500 ) );

	$mime = get_post_mime_type( $id );
	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
		wp_die( '', '', array( 'response' => 404 ) );
	}
	$file = get_attached_file( $id );
	if ( ! $file || ! file_exists( $file ) ) wp_die( '', '', array( 'response' => 404 ) );

	$width     = max( 16, min( 2560, $width ) );
	$upload    = wp_upload_dir();
	$cache_dir = trailingslashit( $upload['basedir'] ) . 'blogpro-cache';
	// $cache = $cache_dir . '/' . $id . '-' . $width . '.webp';
	
	// Cache file named after the source image (original-name-width.webp, e.g.
	// holiday-480.webp) instead of a numeric ID, so generated files are easy
	// to recognize. The width stays in the name so every variant is distinct.
	// URL/behaviour is unchanged — only the on-disk name differs.
	$base  = sanitize_file_name( pathinfo( $file, PATHINFO_FILENAME ) );
	$cache = $cache_dir . '/' . $base . '-' . $width . '.webp';

	if ( ! file_exists( $cache ) ) {
		$orig = wp_getimagesize( $file );
		if ( ! $orig ) wp_die( '', '', array( 'response' => 500 ) );
		$w = $orig[0];
		$h = $orig[1];
		$width = min( $width, $w ); // never upscale
		$height = (int) round( $h * $width / $w ); // height auto

		$old_limit = ini_set( 'memory_limit', '256M' );
		if ( 'image/png' === $mime ) {
			$src = @imagecreatefrompng( $file );
		} elseif ( 'image/webp' === $mime ) {
			$src = @imagecreatefromwebp( $file );
		} else {
			$src = @imagecreatefromjpeg( $file );
		}
		if ( ! $src ) { ini_set( 'memory_limit', $old_limit ); wp_die( '', '', array( 'response' => 500 ) ); }
		$dst = imagecreatetruecolor( $width, $height );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $width, $height, $w, $h );
		imagedestroy( $src );
		wp_mkdir_p( $cache_dir );
		$ok = imagewebp( $dst, $cache, 82 );
		imagedestroy( $dst );
		ini_set( 'memory_limit', $old_limit );
		if ( ! $ok || ! file_exists( $cache ) || filesize( $cache ) === 0 ) {
			@unlink( $cache );
			wp_die( '', '', array( 'response' => 500 ) );
		}
	}

	header( 'Content-Type: image/webp' );
	header( 'Content-Length: ' . filesize( $cache ) );
	header( 'Cache-Control: public, max-age=31536000, immutable' ); // URL is deterministic — safe to cache forever
	readfile( $cache );
}

/**
 * Rewrite every content <img> to responsive resizer URLs. Fixes legacy
 * imports (no size metadata → single-candidate srcset → browser downloads
 * the full original) and gives all content images a proper srcset.
 * Height stays auto (aspect preserved by the resizer); srcset width caps
 * at the original so nothing is upscaled.
 */
function blogpro_responsive_content_images( $content ) {
	return preg_replace_callback(
		'/<img\b[^>]*>/i',
		function ( $m ) {
			$img = $m[0];

			// already handled (our own output)
			if ( false !== strpos( $img, '/blogpro-img/' ) ) return $img;

			if ( ! preg_match( '/src=["\']([^"\']+)["\']/i', $img, $src_m ) ) return $img;
			$src_url = $src_m[1];
			$url_parts = wp_parse_url( $src_url );
			if ( ! isset( $url_parts['path'] ) ) return $img;

			// path relative to the WP root (handles subdirectory installs)
			$site_path = (string) wp_parse_url( site_url(), PHP_URL_PATH );
			$rel       = isset( $url_parts['path'] ) ? preg_replace( '#^' . preg_quote( rtrim( $site_path, '/' ), '#' ) . '#', '', $url_parts['path'] ) : '';
			$path = wp_normalize_path( untrailingslashit( ABSPATH ) . $rel );
			if ( ! file_exists( $path ) || ! is_readable( $path ) ) return $img;

			$id = attachment_url_to_postid( $src_url );
			if ( ! $id ) {
				// imported file not matched by URL — resolve by realpath (normalized,
				// since realpath() returns OS separators)
				$base = wp_normalize_path( untrailingslashit( wp_upload_dir()['basedir'] ) );
				$real = realpath( $path );
				if ( ! $real ) return $img;
				$rel = ltrim( str_replace( $base, '', wp_normalize_path( $real ) ), '/' );
				if ( $rel === wp_normalize_path( $real ) ) return $img; // not under uploads
				global $wpdb;
				$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1", $rel ) );
				if ( ! $id ) {
					// file may be a WebP copy whose attachment row is the .png/.jpg original
					$alt = preg_replace( '/\.webp$/i', '.png', $rel );
					$alt = ( $alt !== $rel ) ? $alt : preg_replace( '/\.webp$/i', '.jpg', $rel );
					if ( $alt !== $rel ) {
						$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1", $alt ) );
					}
				}
			}
			if ( ! $id ) return $img;

			$mime = get_post_mime_type( $id );
			if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) return $img;

			if ( preg_match( '/width=["\'](\d+)["\']/i', $img, $wm ) ) {
				$orig_w = (int) $wm[1];
			} else {
				$orig_w = (int) wp_getimagesize( $path )[0];
			}
			if ( $orig_w < 1 ) return $img;

			$widths = array( 320, 480, 768, 1024, 1280, 1600 );
			$widths = array_values( array_filter( $widths, function ( $w ) use ( $orig_w ) { return $w < $orig_w; } ) );
			$widths[] = $orig_w;

			$base   = home_url( '/blogpro-img/' . $id . '/' );
			$srcset = implode( ', ', array_map( function ( $w ) use ( $base ) {
				return esc_url( $base . $w . '/' ) . ' ' . $w . 'w';
			}, $widths ) );

			$img = preg_replace( '/\bsrc=["\'][^"\']*["\']/i', 'src="' . esc_url( $base . $widths[0] . '/' ) . '"', $img );
			if ( false !== stripos( $img, 'srcset=' ) ) {
				$img = preg_replace( '/\bsrcset=["\'][^"\']*["\']/i', 'srcset="' . esc_attr( $srcset ) . '"', $img );
			} else {
				$img = preg_replace( '/\bsrc=["\'][^"\']*["\']/i', 'srcset="' . esc_attr( $srcset ) . '" src="' . esc_url( $base . $widths[0] . '/' ) . '"', $img );
			}
			if ( false !== stripos( $img, 'sizes=' ) ) {
				$img = preg_replace( '/\bsizes=["\'][^"\']*["\']/i', 'sizes="(max-width: 820px) 100vw, 820px"', $img );
			} else {
				$img = str_replace( ' srcset="', ' sizes="(max-width: 820px) 100vw, 820px" srcset="', $img );
			}
			return $img;
		},
		$content
	);
}
// priority 10: must run BEFORE blogpro_maybe_use_picture (20) — once src is a
// /blogpro-img/ URL it has no .jpg/.png extension, so the picture filter skips it
add_filter( 'the_content', 'blogpro_responsive_content_images', 10 );

/**
 * Responsive <img> served by the resizer above. Emits a srcset of
 * widths up to the original, height auto — one upload, no size queue.
 */
function blogpro_responsive_img( $attachment_id, $args = array() ) {
	$src = wp_get_attachment_image_src( $attachment_id, 'full' );
	if ( ! $src ) return '';

	$orig_w = (int) $src[1];
	$orig_h = (int) $src[2];
	$widths = array( 320, 480, 768, 1024, 1280, 1600 );
	$widths = array_values( array_filter( $widths, function ( $w ) use ( $orig_w ) { return $w < $orig_w; } ) );
	$widths[] = $orig_w;

	$base   = home_url( '/blogpro-img/' . $attachment_id . '/' );
	$srcset = implode( ', ', array_map( function ( $w ) use ( $base ) {
		return esc_url( $base . $w . '/' ) . ' ' . $w . 'w';
	}, $widths ) );

	$attrs  = array(
		'class'    => isset( $args['class'] ) ? $args['class'] : '',
		'width'    => $orig_w, // intrinsic ratio for CLS; CSS (w-full h-auto) overrides display size
		'height'   => $orig_h,
		'alt'      => isset( $args['alt'] ) ? $args['alt'] : '',
		'sizes'    => isset( $args['sizes'] ) ? $args['sizes'] : '100vw',
		'loading'  => isset( $args['loading'] ) ? $args['loading'] : 'lazy',
		'decoding' => 'async',
	);

	return sprintf(
		'<img src="%s" srcset="%s" width="%d" height="%d" sizes="%s" alt="%s" loading="%s" decoding="async" class="%s">',
		esc_url( $base . $widths[0] . '/' ),
		$srcset,
		$attrs['width'],
		$attrs['height'],
		esc_attr( $attrs['sizes'] ),
		esc_attr( $attrs['alt'] ),
		esc_attr( $attrs['loading'] ),
		esc_attr( $attrs['class'] )
	);
}
