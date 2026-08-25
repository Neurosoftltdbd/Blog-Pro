<?php
/**
 * Dynamic SEO meta tags — title, description, canonical, Open Graph,
 * Twitter Card, robots directives. No plugin required.
 *
 * Per-post overrides: if you want manual control on a given post, save
 * post meta keys `_blogpro_meta_title` / `_blogpro_meta_description` and
 * they'll be used automatically (a simple metabox for this can be added
 * later; the fallback logic below already covers 95% of blog needs).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function blogpro_get_meta_description() {
	if ( is_singular() ) {
		global $post;
		$custom = get_post_meta( $post->ID, '_blogpro_meta_description', true );
		if ( $custom ) return wp_strip_all_tags( $custom );

		$excerpt = has_excerpt( $post->ID ) ? get_the_excerpt( $post ) : wp_strip_all_tags( $post->post_content );
		$excerpt = wp_strip_all_tags( $excerpt );
		return wp_trim_words( $excerpt, 32, '…' );
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$desc = term_description();
		if ( $desc ) return wp_trim_words( wp_strip_all_tags( $desc ), 32, '…' );
		return sprintf( __( 'Browse all posts about %s.', 'blog-pro' ), single_term_title( '', false ) );
	}

	if ( is_home() || is_front_page() ) {
		$tagline = get_bloginfo( 'description' );
		return $tagline ? $tagline : sprintf( __( 'Latest articles from %s.', 'blog-pro' ), get_bloginfo( 'name' ) );
	}

	if ( is_author() ) {
		$bio = get_the_author_meta( 'description' );
		return $bio ? wp_trim_words( $bio, 32, '…' ) : sprintf( __( 'Posts by %s.', 'blog-pro' ), get_the_author() );
	}

	return get_bloginfo( 'description' );
}

function blogpro_get_meta_title() {
	if ( is_singular() ) {
		global $post;
		$custom = get_post_meta( $post->ID, '_blogpro_meta_title', true );
		if ( $custom ) return $custom;
		return get_the_title() . ' | ' . get_bloginfo( 'name' );
	}
	if ( is_home() || is_front_page() ) {
		return get_bloginfo( 'name' ) . ' | ' . get_bloginfo( 'description' );
	}
	if ( is_category() || is_tag() || is_tax() ) {
		return single_term_title( '', false ) . ' | ' . get_bloginfo( 'name' );
	}
	if ( is_search() ) {
		return sprintf( __( 'Search results for "%s" | %s', 'blog-pro' ), get_search_query(), get_bloginfo( 'name' ) );
	}
	if ( is_404() ) {
		return __( 'Page not found', 'blog-pro' ) . ' | ' . get_bloginfo( 'name' );
	}
	if ( is_author() ) {
		return sprintf( __( 'Posts by %s | %s', 'blog-pro' ), get_the_author(), get_bloginfo( 'name' ) );
	}
	return wp_get_archive_title() . ' | ' . get_bloginfo( 'name' );
}

function blogpro_get_canonical_url() {
	if ( is_singular() ) return get_permalink();
	if ( is_home() || is_front_page() ) return home_url( '/' );
	if ( is_category() || is_tag() || is_tax() ) return get_term_link( get_queried_object() );
	if ( is_author() ) return get_author_posts_url( get_queried_object_id() );
	global $wp;
	return home_url( add_query_arg( array(), $wp->request ) );
}

function blogpro_get_social_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		return wp_get_attachment_image_url( get_post_thumbnail_id(), 'blogpro-hero' );
	}
	$site_icon = get_site_icon_url( 512 );
	return $site_icon ? $site_icon : '';
}

function blogpro_output_meta_tags() {
	$description = esc_attr( blogpro_get_meta_description() );
	$canonical   = esc_url( blogpro_get_canonical_url() );
	$title       = esc_attr( blogpro_get_meta_title() );
	$image       = blogpro_get_social_image();
	$site_name   = esc_attr( get_bloginfo( 'name' ) );

	echo "\n<!-- Blog Pro SEO meta -->\n";
	echo '<meta name="description" content="' . $description . '">' . "\n";
	echo '<link rel="canonical" href="' . $canonical . '">' . "\n";

	// Robots directives
	if ( is_search() || is_404() ) {
		echo '<meta name="robots" content="noindex,follow">' . "\n";
	} elseif ( is_paged() ) {
		echo '<meta name="robots" content="index,follow,noarchive">' . "\n";
	} else {
		echo '<meta name="robots" content="index,follow,max-image-preview:large">' . "\n";
	}

	// Open Graph
	echo '<meta property="og:type" content="' . ( is_singular( 'post' ) ? 'article' : 'website' ) . '">' . "\n";
	echo '<meta property="og:title" content="' . $title . '">' . "\n";
	echo '<meta property="og:description" content="' . $description . '">' . "\n";
	echo '<meta property="og:url" content="' . $canonical . '">' . "\n";
	echo '<meta property="og:site_name" content="' . $site_name . '">' . "\n";
	if ( $image ) echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";

	if ( is_singular( 'post' ) ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '">' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . "\n";
		foreach ( get_the_category() as $cat ) {
			echo '<meta property="article:section" content="' . esc_attr( $cat->name ) . '">' . "\n";
		}
	}

	// Twitter Card
	echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	echo '<meta name="twitter:title" content="' . $title . '">' . "\n";
	echo '<meta name="twitter:description" content="' . $description . '">' . "\n";
	if ( $image ) echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";

	echo "<!-- /Blog Pro SEO meta -->\n";
}
add_action( 'wp_head', 'blogpro_output_meta_tags', 2 );

/* Override the default <title> with our computed value for full control. */
add_filter( 'pre_get_document_title', 'blogpro_get_meta_title' );

/**
 * Derive a human-readable label from a media filename.
 * "my-fancy_image_2x.png" -> "My Fancy Image 2x"
 */
function blogpro_filename_to_label( $filename ) {
	$label = pathinfo( $filename, PATHINFO_FILENAME );
	$label = preg_replace( '/[_\-.]+/', ' ', $label );
	$label = preg_replace( '/\s{2,}/', ' ', trim( $label ) );
	return ucwords( strtolower( $label ) );
}

/**
 * Add missing accessibility attributes to media and links in content:
 * - <img> without alt/title -> alt + (img)title set to a readable filename
 * - <img> with empty alt="" -> title filled with filename (keep explicit
 *   empty alt — that's a deliberate decorative-image marker)
 * - <a> without distinguishable label/aria -> aria-label from link text or
 *   link text or fallback
 */
function blogpro_fix_content_attributes( $content ) {
	if ( ! $content ) {
		return $content;
	}

	// Images: alt/title fallback from filename.
	$content = preg_replace_callback(
		'/<img\b[^>]*>/i',
		function ( $matches ) {
			$img = $matches[0];

			// src may be quoted with either quote char.
			if ( ! preg_match( '/\bsrc=(["\'])(.*?)\1/i', $img, $m ) || empty( $m[2] ) ) {
				return $img;
			}
			$src = $m[2];
			$label = blogpro_filename_to_label( basename( parse_url( $src, PHP_URL_PATH ) ) );

			$has_alt = preg_match( '/\balt=/i', $img );
			$has_title = preg_match( '/\btitle=/i', $img );
			$alt_empty = preg_match( '/\balt\s*=\s*(["\'])\1/i', $img );

			if ( ! $has_alt ) {
				$img = preg_replace( '/<img/i', '<img alt="' . esc_attr( $label ) . '"', $img, 1 );
			} elseif ( $alt_empty ) {
				// Decorative image — keep alt="", but add a title for hover.
				if ( ! $has_title ) {
					$img = preg_replace( '/<img/i', '<img title="' . esc_attr( $label ) . '"', $img, 1 );
				}
			} elseif ( ! $has_title ) {
				$img = preg_replace( '/<img/i', '<img title="' . esc_attr( $label ) . '"', $img, 1 );
			}

			return $img;
		},
		$content
	);

	// Links: aria-label fallback when the link has no distinguishable text.
	$content = preg_replace_callback(
		'/<a\b[^>]*>(.*?)<\/a>/is',
		function ( $matches ) {
			$link = $matches[0];
			$inner = $matches[1];

			if ( preg_match( '/\b(?:aria-label|aria-labelledby|title)=/i', $link ) ) {
				return $link; // Already labeled.
			}

			$text = trim( wp_strip_all_tags( $inner ) );
			// Link has no visible text (icon/blank-only) — give it a label.
			if ( '' === $text ) {
				$href = '';
				if ( preg_match( '/\bhref=(["\'])(.*?)\1/i', $link, $m ) ) {
					$href = $m[2];
				}
				$label = $href ? blogpro_filename_to_label( basename( parse_url( $href, PHP_URL_PATH ) ) ) : __( 'Link', 'blog-pro' );
				$link = preg_replace( '/<a/i', '<a aria-label="' . esc_attr( $label ) . '"', $link, 1 );
			}

			return $link;
		},
		$content
	);

	return $content;
}
add_filter( 'the_content', 'blogpro_fix_content_attributes', 10 );

/**
 * Whole-site pass: run the same attribute fixups over the final rendered
 * buffer (templates, Elementor output, header/footer, widgets) — not just
 * post content. Logged-in users / admin are excluded so the editor and
 * dashboard are never touched.
 */
function blogpro_fix_buffer_attributes( $buffer ) {
	if ( is_admin() || is_user_logged_in() || did_action( 'elementor/loaded' ) && ! empty( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
		return $buffer;
	}
	// Non-HTML responses (robots.txt, sitemaps, feeds, redirects, images)
	// must pass through untouched — this buffer pass assumes an HTML doc.
	// (Robots/sitemap/REST handlers exit before template_redirect, so this is
	// a cheap safety net for anything that slips through, not a hot path.)
	// if ( 0 !== stripos( ltrim( $buffer ), '<!doctype' ) && 0 !== stripos( ltrim( $buffer ), '<html' ) ) {
	// 	return $buffer;
	// }

	// Images.
	$buffer = preg_replace_callback(
		'/<img\b[^>]*>/i',
		function ( $matches ) {
			$img = $matches[0];
			if ( ! preg_match( '/\bsrc=(["\'])(.*?)\1/i', $img, $m ) || empty( $m[2] ) ) {
				return $img;
			}
			$label = blogpro_filename_to_label( basename( parse_url( $m[2], PHP_URL_PATH ) ) );
			if ( ! preg_match( '/\balt=/i', $img ) ) {
				$img = preg_replace( '/<img/i', '<img alt="' . esc_attr( $label ) . '"', $img, 1 );
			}
			if ( ! preg_match( '/\btitle=/i', $img ) ) {
				$img = preg_replace( '/<img/i', '<img title="' . esc_attr( $label ) . '"', $img, 1 );
			}
			return $img;
		},
		$buffer
	);

	// Links and buttons with no discernible text. Note: a `title` attribute
	// alone does NOT satisfy "discernible text" (PageSpeed/Lighthouse flags
	// it) — only aria-label / aria-labelledby do.
	$buffer = preg_replace_callback(
		'/<(a|button)\b[^>]*>(.*?)<\/(a|button)>/is',
		function ( $matches ) {
			$el  = $matches[0];
			$tag = strtolower( $matches[1] );

			if ( preg_match( '/\b(?:aria-label|aria-labelledby)=/i', $el ) ) {
				return $el; // Already has an accessible name.
			}

			$inner = $matches[2];

			// Accessible text: img alt counts (links wrapping images).
			$text = trim( wp_strip_all_tags( $inner ) );
			if ( '' === $text ) {
				if ( preg_match( '/<img\b[^>]*\balt=(["\'])(.*?)\1/i', $inner, $m ) ) {
					$text = trim( $m[2] );
				}
			}
			if ( '' !== $text ) {
				return $el; // Has discernible text.
			}

			// Icon-only / image-only (no alt): derive a label from href/src.
			$url = '';
			if ( preg_match( '/\b(?:href|src)=(["\'])(.*?)\1/i', $el, $m ) ) {
				$url = $m[2];
			}
			if ( '' === $url && preg_match( '/<img\b[^>]*\bsrc=(["\'])(.*?)\1/i', $inner, $m ) ) {
				$url = $m[2];
			}
			$label = $url ? blogpro_filename_to_label( basename( parse_url( $url, PHP_URL_PATH ) ) ) : __( 'Link', 'blog-pro' );
			$el = preg_replace( '/<' . $tag . '/i', '<' . $tag . ' aria-label="' . esc_attr( $label ) . '"', $el, 1 );

			return $el;
		},
		$buffer
	);

	// Ensure a <main> landmark exists. Elementor header/footer theme
	// builders replace header.php/footer.php — which is where this theme's
	// <main id="main"> lives — so pages rendered by Elementor have none.
	//
	// Strategy: wrap the content BETWEEN the header region and the first
	// <script> (wp_footer prints scripts last). Handles both an Elementor
	// header wrapper and a classic theme header. No-op when <main> exists.
	if ( ! preg_match( '/<main\b/i', $buffer ) ) {
		// Content start: after </header> if present, else after <body>.
		if ( preg_match( '/<\/header>/i', $buffer, $m, PREG_OFFSET_CAPTURE ) ) {
			$content_start = $m[0][1] + strlen( $m[0][0] );
		} elseif ( preg_match( '/<body[^>]*>/i', $buffer, $m, PREG_OFFSET_CAPTURE ) ) {
			$content_start = $m[0][1] + strlen( $m[0][0] );
		} else {
			// No header/body found — fall back to before </body>.
			$buffer = str_replace( '</body>', '</main></body>', $buffer );
			$buffer = str_replace( '<body', '<body><main id="main">', $buffer, 1 );
			return $buffer;
		}

		// Content end: before the first <script> after content start.
		$script_rel = strpos( $buffer, '<script', $content_start );
		$content_end = ( false !== $script_rel ) ? $script_rel : strlen( $buffer );

		$main_open  = '<main id="main">';
		$buffer = substr_replace( $buffer, $main_open, $content_start, 0 );
		$buffer = substr_replace( $buffer, '</main>', $content_end + strlen( $main_open ), 0 );
	}

	return $buffer;
}

function blogpro_fix_buffer_start() {
	ob_start( 'blogpro_fix_buffer_attributes' );
}
add_action( 'template_redirect', 'blogpro_fix_buffer_start' );
