<?php
/**
 * Video & embed optimisation — playback robustness + lazy embeds.
 *
 * (Split out of inc/media-optimize.php so all video behavior lives here.)
 *
 * Fixes the failure modes that break the native player on this theme:
 *
 * 1. <video> without width/height — the element collapses when CSS
 *    constrains the wrapper, which reads as "player broken / can't seek".
 *    Intrinsic size is read from the attachment metadata and baked in;
 *    a 16:9 aspect-ratio wrapper keeps it stable.
 * 2. loading="lazy" on <video> — lazy videos don't paint their first
 *    frame in some browsers and Safari refuses to load them at all.
 *    Stripped here; preload="metadata" enforced instead (poster frame
 *    visible, full file still deferred until play).
 * 3. Wrong/missing MIME on Windows (XAMPP) Apache for .mp4/.m4v/.webm —
 *    a bad Content-Type makes Safari/Chrome refuse playback. Corrected
 *    via wp_mime_types + explicit AddType lines in .htaccess.
 * 4. playsinline — without it iOS opens the fullscreen system player,
 *    where controls look broken.
 *
 * Also: YouTube/Vimeo iframes lazy-load (moved from media-optimize.php).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------------
 * 1. MIME correctness (WP-side + server-side)
 * ------------------------------------------------------------------ */

/**
 * Correct/complete video MIME mappings — Windows/XAMPP Apache ships
 * video/quicktime for .mov and may miss .mp4 entirely; .m4v must map to
 * video/mp4 for Safari.
 */
function blogpro_fix_video_mime_types( $mimes ) {
	$mimes['mp4']  = 'video/mp4';
	$mimes['m4v']  = 'video/mp4';
	$mimes['mov']  = 'video/quicktime';
	$mimes['qt']   = 'video/quicktime';
	$mimes['webm'] = 'video/webm';
	$mimes['ogv']  = 'video/ogg';
	return $mimes;
}
add_filter( 'wp_mime_types', 'blogpro_fix_video_mime_types' );

/* ------------------------------------------------------------------
 * 2. Self-hosted <video> hardening
 * ------------------------------------------------------------------ */

/**
 * Poster enrichment: when [video src=…mp4] points at a library video that
 * has a featured image, use it as poster so the player paints immediately.
 * Never fall back to the video URL itself (an mp4 poster is invalid).
 */
function blogpro_video_shortcode_atts( $atts ) {
	if ( empty( $atts['src'] ) || ! empty( $atts['poster'] ) ) {
		return $atts;
	}
	$id    = attachment_url_to_postid( $atts['src'] );
	$thumb = $id ? get_post_thumbnail_id( $id ) : 0;
	if ( $thumb ) {
		$img = wp_get_attachment_image_url( $thumb, 'medium_large' );
		if ( $img && preg_match( '/\.(jpe?g|png|webp)$/i', $img ) ) {
			$atts['poster'] = $img;
		}
	}
	return $atts;
}
add_filter( 'shortcode_atts_video', 'blogpro_video_shortcode_atts', 10, 1 );

/**
 * ISO-8601 clock duration → readable ("PT1H30M" → "1 hr 30 min").
 * Shared with the blog-pro/howto block (guarded there too).
 *
 * @param string $iso
 * @return string
 */
if ( ! function_exists( 'blogpro_howto_human_duration' ) ) {
	function blogpro_howto_human_duration( $iso ) {
		if ( ! preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', (string) $iso, $m ) ) {
			return '';
		}
		$parts = array();
		if ( ! empty( $m[1] ) ) $parts[] = $m[1] . ' hr';
		if ( ! empty( $m[2] ) ) $parts[] = $m[2] . ' min';
		if ( ! empty( $m[3] ) ) $parts[] = $m[3] . ' sec';
		return $parts ? implode( ' ', $parts ) : '';
	}
}

/**
 * Robustness pass over every rendered <video>:
 *  - drop loading="lazy" (breaks first-frame paint on several engines)
 *  - enforce preload="metadata"
 *  - add playsinline + controls
 *  - add width/height from the source attachment metadata when missing
 *  - wrap in a 16:9-safe container (.bp-video-wrap, CSS below)
 *
 * Idempotent — tags already carrying .bp-video are skipped, so running
 * on both wp_video_shortcode and the_content is safe.
 */
function blogpro_tidy_videos( $html ) {
	if ( false === stripos( (string) $html, '<video' ) ) {
		return $html;
	}

	return preg_replace_callback( '/<video\b[^>]*>(?:(?!<\/video>).)*<\/video>/is', function ( $m ) {
		$tag = $m[0];

		if ( false !== stripos( $tag, 'bp-video' ) ) {
			return $tag; // already tidied by the shortcode or content pass
		}

		// Kill lazy — a lazy <video> paints nothing until near the viewport,
		// and Safari may not load it at all.
		$tag = preg_replace( '/\s+loading=(["\'])(?:lazy|eager)\1/i', '', $tag );

		// preload: metadata (poster frame ready, bytes deferred).
		if ( preg_match( '/\s+preload=(["\'])\w+\1/i', $tag ) ) {
			$tag = preg_replace( '/\s+preload=(["\'])\w+\1/i', ' preload="metadata"', $tag );
		} else {
			$tag = preg_replace( '/<video/i', '<video preload="metadata"', $tag, 1 );
		}

		// iOS/Android inline playback + explicit controls.
		if ( ! preg_match( '/\splaysinline\b/i', $tag ) ) {
			$tag = preg_replace( '/<video/i', '<video playsinline', $tag, 1 );
		}
		if ( ! preg_match( '/\scontrols(=|\s|>)/i', $tag ) ) {
			$tag = preg_replace( '/<video/i', '<video controls', $tag, 1 );
		}

		// Intrinsic size from the attachment behind the src (best effort).
		if ( ! preg_match( '/\swidth=(["\'])\d+\1/i', $tag ) && preg_match( '/\ssrc=(["\'])(.*?)\1/i', $tag, $sm ) ) {
			$id   = attachment_url_to_postid( $sm[2] );
			$meta = $id ? wp_get_attachment_metadata( $id ) : false;
			if ( $meta && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				$tag = preg_replace( '/<video/i', '<video width="' . (int) $meta['width'] . '" height="' . (int) $meta['height'] . '"', $tag, 1 );
			}
		}

		// Class hook for CSS — utilities: fill the wrapper, contain the frame.
		if ( preg_match( '/\sclass=(["\'])(.*?)\1/i', $tag ) ) {
			$tag = preg_replace( '/\sclass=(["\'])(.*?)\1/i', ' class="bp-video block w-full h-full object-contain bg-slate-900 $2"', $tag, 1 );
		} else {
			$tag = preg_replace( '/<video/i', '<video class="bp-video block w-full h-full object-contain bg-slate-900"', $tag, 1 );
		}

		return '<div class="bp-video-wrap relative max-w-full mx-auto my-8 rounded-2xl overflow-hidden bg-slate-900 aspect-video">' . $tag . '</div>';
	}, $html );
}
add_filter( 'wp_video_shortcode', 'blogpro_tidy_videos', 20, 2 );
add_filter( 'the_content', 'blogpro_tidy_videos', 20 );

/* ------------------------------------------------------------------
 * 3. Embedded video iframes (YouTube/Vimeo) — lazy-load
 * ------------------------------------------------------------------ */

/**
 * Add loading="lazy" to iframes that don't declare one yet. Covers
 * oEmbed output and hand-pasted iframe markup in post content.
 */
function blogpro_lazy_iframes( $html ) {
	if ( false === strpos( (string) $html, '<iframe' ) ) {
		return $html;
	}
	return preg_replace_callback( '/<iframe\b[^>]*>/i', function ( $m ) {
		if ( preg_match( '/\sloading=/i', $m[0] ) ) {
			return $m[0];
		}
		return preg_replace( '/<iframe/i', '<iframe loading="lazy"', $m[0], 1 );
	}, $html );
}
add_filter( 'embed_oembed_html', 'blogpro_lazy_iframes', 10, 1 );
add_filter( 'the_content', 'blogpro_lazy_iframes', 15 );

/* ------------------------------------------------------------------
 * 4. VideoObject structured data
 *
 * Layout styling is Tailwind utilities baked onto the markup above
 * (aspect-video wrapper + object-contain video). The two rules
 * Tailwind can't express — an @supports aspect-ratio fallback and the
 * iOS media-controls hint — live in the theme stylesheet (style.css).
 * ------------------------------------------------------------------ */

/**
 * Scan a post's content for self-hosted <video> sources and build
 * VideoObject nodes — the schema video rich results and AI engines use
 * to understand what's in the post, keyed to real attachment data
 * (duration, dimensions, upload date) instead of guesswork.
 *
 * @param int $post_id
 * @return array[] VideoObject-ready arrays
 */
function blogpro_video_objects_for_post( $post_id ) {
	$content = (string) get_post_field( 'post_content', $post_id );
	if ( false === stripos( $content, '<video' ) && false === stripos( $content, 'wp-video' ) ) {
		return array();
	}

	$urls = array();
	if ( preg_match_all( '/<(?:video|source)\b[^>]*\ssrc=(["\'])(.*?)\1/i', $content, $m ) ) {
		$urls = $m[2];
	}

	$nodes = array();
	$seen  = array();
	foreach ( $urls as $url ) {
		$id = attachment_url_to_postid( $url );
		if ( ! $id || isset( $seen[ $id ] ) ) {
			continue; // not a library file, or already emitted
		}
		$seen[ $id ] = true;

		$mime = (string) get_post_mime_type( $id );
		if ( 0 !== strpos( $mime, 'video/' ) ) {
			continue;
		}
		$meta = wp_get_attachment_metadata( $id );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		// Media Library "Description" is the attachment's post_content.
		$desc = wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) );

		$node = array(
			'@type'       => 'VideoObject',
			'name'        => get_the_title( $id ),
			'description' => $desc ? $desc : get_the_title( $id ),
			'thumbnailUrl' => '',
			'uploadDate'  => get_the_date( 'c', $id ),
			'contentUrl'  => wp_get_attachment_url( $id ),
		);

		// Dimensions/duration come from WP's own video metadata pass.
		if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			$node['width']  = (int) $meta['width'];
			$node['height'] = (int) $meta['height'];
		}
		if ( ! empty( $meta['length'] ) ) {
			$node['duration'] = 'PT' . (int) round( (float) $meta['length'] ) . 'S';
		}
		if ( ! empty( $meta['length_formatted'] ) ) {
			$node['durationText'] = $meta['length_formatted'];
		}

		// Poster: video featured image if set, else the attachment itself
		// can't produce a frame — omit rather than point at the mp4.
		$thumb_id = get_post_thumbnail_id( $id );
		if ( $thumb_id ) {
			$node['thumbnailUrl'] = wp_get_attachment_image_url( $thumb_id, 'large' ) ?: '';
		}
		if ( '' === $node['thumbnailUrl'] ) {
			unset( $node['thumbnailUrl'] );
			// thumbnailUrl is required for valid VideoObject — without a
			// poster image the node would fail validation; skip it.
			continue;
		}

		$nodes[] = $node;
	}
	return $nodes;
}

/**
 * Append VideoObject nodes to the schema @graph on singular posts that
 * embed library videos.
 *
 * @param array $graph
 * @return array
 */
function blogpro_schema_videos( $graph ) {
	if ( ! is_singular( 'post' ) ) {
		return $graph;
	}
	$videos = blogpro_video_objects_for_post( get_queried_object_id() );
	if ( ! $videos ) {
		return $graph;
	}

	// Attach as the article's subjectVideo + add standalone nodes.
	foreach ( $graph as $i => $node ) {
		if ( isset( $node['@type'] ) && 'BlogPosting' === $node['@type'] ) {
			$graph[ $i ]['video'] = 1 === count( $videos ) ? $videos[0] : $videos;
			break;
		}
	}
	foreach ( $videos as $video ) {
		$graph[] = $video;
	}
	return $graph;
}
add_filter( 'blogpro_schema_graph', 'blogpro_schema_videos' );

/* ------------------------------------------------------------------
 * 5. .htaccess video MIME rules
 * ------------------------------------------------------------------ */

/**
 * Append video AddType lines to the theme's marker block (filter added
 * to blogpro_htaccess_rules() in inc/htaccess.php).
 */
function blogpro_video_htaccess_rules( $rules ) {
	$rules[] = '';
	$rules[] = '<IfModule mod_mime.c>';
	$rules[] = "\tAddType video/mp4 .mp4 .m4v";
	$rules[] = "\tAddType video/webm .webm";
	$rules[] = "\tAddType video/ogg .ogv .ogg";
	$rules[] = '</IfModule>';
	return $rules;
}
add_filter( 'blogpro_htaccess_rules', 'blogpro_video_htaccess_rules' );

/**
 * Re-write .htaccess once per theme version so the new AddType lines land
 * without a manual re-save.
 */
function blogpro_video_maybe_refresh_htaccess() {
	if ( get_option( 'blogpro_htaccess_video_v' ) !== BLOGPRO_VERSION ) {
		if ( function_exists( 'blogpro_write_htaccess_rules' ) ) {
			blogpro_write_htaccess_rules();
		}
		update_option( 'blogpro_htaccess_video_v', BLOGPRO_VERSION );
	}
}
add_action( 'init', 'blogpro_video_maybe_refresh_htaccess', 99 );
