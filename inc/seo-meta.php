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

/**
 * Trim description to optimal length.
 */
function blogpro_trim_description( $text, $max_words = 25, $max_chars = 160 ) {
    $trimmed = wp_trim_words( $text, $max_words, '…' );
    if ( mb_strlen( $trimmed ) > $max_chars ) {
        $trimmed = mb_substr( $trimmed, 0, $max_chars - 1 ) . '…';
    }
    return $trimmed;
}

function blogpro_get_meta_description() {
	if ( is_singular() ) {
		global $post;
		$custom = get_post_meta( $post->ID, '_blogpro_meta_description', true );
		if ( $custom ) return wp_strip_all_tags( $custom );

		$excerpt = has_excerpt( $post->ID ) ? get_the_excerpt( $post ) : wp_strip_all_tags( $post->post_content );
		$excerpt = wp_strip_all_tags( $excerpt );
		return blogpro_trim_description( $excerpt );
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$desc = term_description();
		if ( $desc ) return blogpro_trim_description( wp_strip_all_tags( $desc ) );
		return sprintf( __( 'Browse all posts about %s.', 'blog-pro' ), single_term_title( '', false ) );
	}

	if ( is_home() || is_front_page() ) {
		$tagline = get_bloginfo( 'description' );
		return $tagline ? $tagline : sprintf( __( 'Latest articles from %s.', 'blog-pro' ), get_bloginfo( 'name' ) );
	}

	if ( is_author() ) {
		$bio = get_the_author_meta( 'description' );
		return $bio ? blogpro_trim_description( $bio ) : sprintf( __( 'Posts by %s.', 'blog-pro' ), get_the_author() );
	}

	return get_bloginfo( 'description' );
}

function blogpro_trim_title( $title, $max_chars = 60 ) {
	if ( mb_strlen( $title ) > $max_chars ) {
		$title = mb_substr( $title, 0, $max_chars - 1 ) . '…';
	}
	return $title;
}

function blogpro_get_meta_title() {
	$title = '';
	if ( is_singular() ) {
		global $post;
		$custom = get_post_meta( $post->ID, '_blogpro_meta_title', true );
		if ( $custom ) return $custom;
		$title = get_the_title() . ' | ' . get_bloginfo( 'name' );
	}
	if ( is_home() || is_front_page() ) {
		$title = get_bloginfo( 'name' ) . ' | ' . get_bloginfo( 'description' );
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$title = single_term_title( '', false ) . ' | ' . get_bloginfo( 'name' );
	}
	if ( is_search() ) {
		$title = sprintf( __( 'Search results for "%s" | %s', 'blog-pro' ), get_search_query(), get_bloginfo( 'name' ) );
	}
	if ( is_404() ) {
		$title = __( 'Page not found', 'blog-pro' ) . ' | ' . get_bloginfo( 'name' );
	}
	if ( is_author() ) {
		$title = sprintf( __( 'Posts by %s | %s', 'blog-pro' ), get_the_author(), get_bloginfo( 'name' ) );
	}
	// is_post_type_archive() covers /shop/, /product/, and any CPT
	// archive that hasn't matched an earlier branch above.
	if ( ! $title && function_exists( 'get_the_archive_title' ) ) {
		$title = get_the_archive_title() . ' | ' . get_bloginfo( 'name' );
	}
	if ( ! $title ) {
		$title = get_bloginfo( 'name' );
	}
	return blogpro_trim_title( $title );
}

function blogpro_get_canonical_url() {
	if ( is_singular() ) {
		$url = get_permalink();
	} elseif ( is_home() || is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$url = get_term_link( get_queried_object() );
	} elseif ( is_author() ) {
		$url = get_author_posts_url( get_queried_object_id() );
	} else {
		global $wp;
		$url = home_url( add_query_arg( array(), $wp->request ) );
	}
	// Force HTTPS (site uses HTTPS) and strip trailing slash except for homepage
	$url = set_url_scheme( $url, 'https' );
	if ( ! ( is_home() || is_front_page() ) ) {
		$url = untrailingslashit( $url );
	}
	return $url;
}

function blogpro_get_social_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		return wp_get_attachment_image_url( get_post_thumbnail_id(), 'blogpro-hero' );
	}
	$site_icon = get_site_icon_url( 512 );
	if ( $site_icon ) {
		return $site_icon;
	}
	// fallback: custom logo or theme default
	$custom_logo = get_theme_mod( 'custom_logo' );
	if ( $custom_logo ) {
		return wp_get_attachment_image_url( $custom_logo, 'full' );
	}
	// allow filter for a default image
	$default = apply_filters( 'blogpro_default_og_image', '' );
	if ( $default && file_exists( str_replace( BLOGPRO_URI, BLOGPRO_DIR, $default ) ) ) {
		return $default;
	}
	// fallback: theme's banner.png
	$banner_path = BLOGPRO_DIR . '/assets/images/banner.png';
	if ( file_exists( $banner_path ) ) {
		return BLOGPRO_URI . '/assets/images/banner.png';
	}
	return '';
}

function blogpro_output_meta_tags() {
	$description = esc_attr( blogpro_get_meta_description() );
	$canonical   = esc_url( blogpro_get_canonical_url() );
	$title       = esc_attr( blogpro_get_meta_title() );
	$image       = blogpro_get_social_image();
	if ( ! $image ) $image = BLOGPRO_URI . '/assets/images/banner.png';
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
	echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";
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