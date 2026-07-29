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
	return in_array( $mime, array( 'image/jpeg', 'image/webp' ), true ) ? 82 : $quality;
}, 10, 2 );

function blogpro_generate_webp( $metadata, $attachment_id ) {
	blogpro_convert_attachment_to_webp( $attachment_id, $metadata );
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
	if ( file_exists( $dest ) ) return false; // already converted — nothing new to do

	$ext = strtolower( $info['extension'] );
	$image = ( 'jpg' === $ext || 'jpeg' === $ext ) ? @imagecreatefromjpeg( $path ) : ( 'png' === $ext ? @imagecreatefrompng( $path ) : false );
	if ( ! $image ) return false;
	$ok = imagewebp( $image, $dest, 82 );
	imagedestroy( $image );
	return (bool) $ok;
}


# 1️⃣ AVIF conversion (optional)
# Add a new function after blogpro_convert_to_webp()
function blogpro_convert_to_avif( $path ) {
    if ( ! function_exists('imageavif') ) return false;
    $info = pathinfo($path);
    $dest = $info['dirname'] . '/' . $info['filename'] . '.avif';
    if ( file_exists($dest) ) return false;
    $ext  = strtolower($info['extension']);
    $img  = ( $ext === 'jpg' || $ext === 'jpeg' )
            ? @imagecreatefromjpeg($path)
            : ( $ext === 'png' ? @imagecreatefrompng($path) : false );
    if ( ! $img ) return false;
    $ok = imageavif($img, $dest, 50); // quality 0‑100
    imagedestroy($img);
    return (bool) $ok;
}
# Call it from blogpro_convert_attachment_to_webp()
# (run after WebP conversion so AVIF is preferred if present).

# 2️⃣ Picture‑element fallback (replace blogpro_maybe_use_webp)
function blogpro_maybe_use_picture( $html ) {
    return preg_replace_callback(
        '/<img([^>]+)(src|srcset)=["\']([^"\']+\.(jpe?g|png))["\']([^>]*)>/i',
        function ( $m ) {
            $webp = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $m[3] );
            $path = str_replace( content_url(), WP_CONTENT_DIR, $webp );
            if ( ! file_exists( $path ) ) {
                return $m[0];
            }
            // Rebuild the <img> tag: keep all attributes except replace src (or srcset) with WebP URL
            $new_img = '<img' . $m[1] . 'src="' . esc_url( $webp ) . '"' . $m[5] . '>';
            return '<picture>'
                 . '<source type="image/webp" srcset="' . esc_url( $webp ) . '">' . $new_img . '</picture>';
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
add_filter( 'post_thumbnail_html', function ( $html ) {
	if ( is_singular() ) {
		$html = str_replace( ' loading="lazy"', ' loading="eager" fetchpriority="high"', $html );
	}
	return $html;
} );

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
