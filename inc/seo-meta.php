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

/**
 * Back-compat URL-only accessor — the fallback chain now lives in
 * blogpro_social_image_data() (URL + real pixel dims in one pass).
 */
function blogpro_get_social_image() {
	return blogpro_social_image_data()['url'];
}

/**
 * Social image + its real pixel dimensions, in one pass.
 *
 * Uses wp_get_attachment_image_src('thumbnail') — the theme's pipeline keeps
 * no intermediate crops (intermediate_image_sizes filter), so this resolves
 * to the WebP master and core reports the true size in the same array, no
 * extra disk probing. For fallbacks (site icon, logo, banner) the size is
 * read from the file once and memoized.
 *
 * @return array{url:string,w:?(int),h:?(int)}
 */
function blogpro_social_image_data() {
	static $cache = array();

	if ( is_singular() && has_post_thumbnail() ) {
		$att_id = get_post_thumbnail_id();
		$key    = 'thumb-' . $att_id;
		if ( ! isset( $cache[ $key ] ) ) {
			$src = wp_get_attachment_image_src( $att_id, 'thumbnail' );
			$cache[ $key ] = $src
				? array( 'url' => $src[0], 'w' => (int) $src[1], 'h' => (int) $src[2] )
				: array( 'url' => '', 'w' => null, 'h' => null );
		}
		if ( $cache[ $key ]['url'] ) {
			return $cache[ $key ];
		}
	}

	$site_icon = get_site_icon_url( 512 );
	if ( $site_icon ) {
		return blogpro_social_image_probe( $cache, $site_icon );
	}
	$custom_logo = get_theme_mod( 'custom_logo' );
	if ( $custom_logo ) {
		$src = wp_get_attachment_image_src( $custom_logo, 'full' );
		if ( $src ) {
			return blogpro_social_image_probe( $cache, $src[0], (int) $src[1], (int) $src[2] );
		}
	}
	$default = apply_filters( 'blogpro_default_og_image', '' );
	if ( $default && file_exists( str_replace( BLOGPRO_URI, BLOGPRO_DIR, $default ) ) ) {
		return blogpro_social_image_probe( $cache, $default );
	}
	$banner_path = BLOGPRO_DIR . '/assets/images/banner.png';
	if ( file_exists( $banner_path ) ) {
		return blogpro_social_image_probe( $cache, BLOGPRO_URI . '/assets/images/banner.png' );
	}
	return array( 'url' => '', 'w' => null, 'h' => null );
}

/**
 * Memoized dimension lookup for a fallback image URL.
 *
 * @param array  $cache
 * @param string $url
 * @param ?int   $w
 * @param ?int   $h
 * @return array
 */
function blogpro_social_image_probe( &$cache, $url, $w = null, $h = null ) {
	$key = 'url-' . $url;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	if ( null === $w || null === $h ) {
		$path = str_replace( array( content_url(), BLOGPRO_URI ), array( WP_CONTENT_DIR, BLOGPRO_DIR ), wp_parse_url( $url, PHP_URL_PATH ) ?: '' );
		// subdirectory installs: try uploads basedir mapping too
		if ( '' === $path || ! file_exists( $path ) ) {
			$upload = wp_upload_dir();
			$rel    = ltrim( str_replace( $upload['baseurl'], '', $url ), '/' );
			$path   = ( $rel !== $url ) ? trailingslashit( $upload['basedir'] ) . $rel : '';
		}
		if ( $path && file_exists( $path ) ) {
			$size = @wp_getimagesize( $path );
			if ( $size ) {
				$w = (int) $size[0];
				$h = (int) $size[1];
			}
		}
	}
	$cache[ $key ] = array( 'url' => $url, 'w' => $w ?: null, 'h' => $h ?: null );
	return $cache[ $key ];
}

/**
 * Alt text for the social image — the featured image's own alt (auto-filled
 * from the filename on upload), falling back to the post title.
 *
 * @return string
 */
function blogpro_social_image_alt() {
	if ( is_singular() && has_post_thumbnail() ) {
		$alt = trim( (string) get_post_meta( get_post_thumbnail_id(), '_wp_attachment_image_alt', true ) );
		return $alt ? $alt : wp_strip_all_tags( get_the_title() );
	}
	return get_bloginfo( 'name' );
}

function blogpro_output_meta_tags() {
	$description = esc_attr( blogpro_get_meta_description() );
	$canonical   = esc_url( blogpro_get_canonical_url() );
	$title       = esc_attr( blogpro_get_meta_title() );
	$social      = blogpro_social_image_data();
	$image       = $social['url'];
	if ( ! $image ) $image = BLOGPRO_URI . '/assets/images/banner.png';
	$site_name   = esc_attr( get_bloginfo( 'name' ) );

	echo "\n<!-- Blog Pro SEO meta -->\n";
	echo '<meta name="description" content="' . $description . '">' . "\n";
	echo '<link rel="canonical" href="' . $canonical . '">' . "\n";
	// Referrer policy — controls how much URL info is sent when users
	// click outbound links. strict-origin-when-cross-origin is the
	// recommended balance: full path for same-origin, origin-only cross-origin.
	echo '<meta name="referrer" content="strict-origin-when-cross-origin">' . "\n";
	// llms-txt discovery — lets AI crawlers auto-discover the file without
	// already knowing the URL. HTTP Link header covers crawlers that read
	// headers before HTML; the <link> tag covers browser-based parsers.
	$llms_url = home_url( '/llms.txt' );
	echo '<link rel="llms-txt" href="' . esc_url( $llms_url ) . '">' . "\n";
	if ( ! headers_sent() ) {
		header( 'Link: <' . esc_url_raw( $llms_url ) . '>; rel="llms-txt"', false );
	}

	// Robots directives
	$noindex = false;
	if ( is_search() || is_404() ) {
		$noindex = true;
		echo '<meta name="robots" content="noindex,follow">' . "\n";
	} elseif ( ( is_category() || is_tag() || is_tax() ) && ! is_paged() ) {
		// Thin-content protection: archives with ≤1 post are duplicate/stub
		// pages with almost no unique value — noindex them to protect crawl
		// budget and avoid thin-content penalties.
		$queried = get_queried_object();
		if ( $queried && isset( $queried->count ) && (int) $queried->count <= 1 ) {
			$noindex = true;
			echo '<meta name="robots" content="noindex,follow">' . "\n";
		}
	}
	if ( ! $noindex ) {
		if ( is_paged() ) {
			// Paginated archives: still indexable; noarchive + max-image-preview.
			echo '<meta name="robots" content="index,follow,noarchive,max-image-preview:large">' . "\n";
		} else {
			echo '<meta name="robots" content="index,follow,max-image-preview:large">' . "\n";
		}
	}

	// Pagination — rel=prev/next (Google dropped these in 2019 but Bing
	// still uses them for series/paginated archive signals).
	if ( is_paged() ) {
		global $paged, $wp_query;
		$max_page = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 1;
		if ( $paged > 1 ) {
			echo '<link rel="prev" href="' . esc_url( get_pagenum_link( $paged - 1 ) ) . '">' . "\n";
		}
		if ( $paged < $max_page ) {
			echo '<link rel="next" href="' . esc_url( get_pagenum_link( $paged + 1 ) ) . '">' . "\n";
		}
	}

	// Theme colour — tells mobile browsers what colour to use for the browser chrome.
	$theme_color = get_theme_mod( 'blogpro_theme_color', '#4f46e5' );
	echo '<meta name="theme-color" content="' . esc_attr( $theme_color ) . '">' . "\n";

	// Open Graph
	// Determine og:type — profile for author pages, article for posts, website elsewhere.
	$og_type = 'website';
	if ( is_singular( 'post' ) )  $og_type = 'article';
	if ( is_author() )            $og_type = 'profile';
	echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '">' . "\n";
	echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";
	echo '<meta property="og:title" content="' . $title . '">' . "\n";
	echo '<meta property="og:description" content="' . $description . '">' . "\n";
	echo '<meta property="og:url" content="' . $canonical . '">' . "\n";
	echo '<meta property="og:site_name" content="' . $site_name . '">' . "\n";

	// og:profile tags — enriches Facebook/LinkedIn person cards on author archives.
	if ( is_author() ) {
		$author_obj = get_queried_object();
		if ( $author_obj ) {
			$name_parts = explode( ' ', $author_obj->display_name, 2 );
			echo '<meta property="profile:first_name" content="' . esc_attr( $name_parts[0] ) . '">' . "\n";
			if ( isset( $name_parts[1] ) ) {
				echo '<meta property="profile:last_name" content="' . esc_attr( $name_parts[1] ) . '">' . "\n";
			}
			echo '<meta property="profile:username" content="' . esc_attr( $author_obj->user_login ) . '">' . "\n";
		}
	}
	if ( $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
		// og:image:secure_url — Facebook and LinkedIn crawlers prefer this
		// explicit HTTPS variant alongside og:image for HTTPS-only sites.
		echo '<meta property="og:image:secure_url" content="' . esc_url( set_url_scheme( $image, 'https' ) ) . '">' . "\n";
		// Dimensions + alt help Facebook/X prefetch the right rendition and
		// stop them reserving layout (bigger, less-cropped cards).
		if ( $social['w'] && $social['h'] ) {
			echo '<meta property="og:image:width" content="' . (int) $social['w'] . '">' . "\n";
			echo '<meta property="og:image:height" content="' . (int) $social['h'] . '">' . "\n";
		}
		// MIME type — prevents crawlers wasting time on format detection.
		$img_ext  = strtolower( pathinfo( wp_parse_url( $image, PHP_URL_PATH ) ?: '', PATHINFO_EXTENSION ) );
		$mime_map = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
			'gif'  => 'image/gif',
			'avif' => 'image/avif',
		);
		if ( isset( $mime_map[ $img_ext ] ) ) {
			echo '<meta property="og:image:type" content="' . esc_attr( $mime_map[ $img_ext ] ) . '">' . "\n";
		}
		$img_alt = blogpro_social_image_alt();
		if ( $img_alt ) {
			echo '<meta property="og:image:alt" content="' . esc_attr( $img_alt ) . '">' . "\n";
		}
	}

	if ( is_singular( 'post' ) ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '">' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . "\n";
		echo '<meta property="article:author" content="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . "\n";
		echo '<meta property="article:publisher" content="' . esc_url( home_url( '/' ) ) . '">' . "\n";
		foreach ( get_the_category() as $cat ) {
			echo '<meta property="article:section" content="' . esc_attr( $cat->name ) . '">' . "\n";
		}
		// Post tags as article:tag (each tag gets its own meta element).
		$tags = get_the_tags();
		if ( $tags && ! is_wp_error( $tags ) ) {
			foreach ( $tags as $tag ) {
				echo '<meta property="article:tag" content="' . esc_attr( $tag->name ) . '">' . "\n";
			}
		}
	}

	// Per-post author credit (used by Google News, Bing News and some aggregators).
	if ( is_singular( 'post' ) ) {
		echo '<meta name="author" content="' . esc_attr( get_the_author() ) . '">' . "\n";
		// news_keywords — required for Google News inclusion; comma-separated
		// list of the post's tags (same signal, human-readable for editors).
		$news_tags = get_the_tags();
		if ( $news_tags && ! is_wp_error( $news_tags ) ) {
			$kw = implode( ', ', wp_list_pluck( $news_tags, 'name' ) );
			echo '<meta name="news_keywords" content="' . esc_attr( $kw ) . '">' . "\n";
		}
	}

	// Hreflang — hook for multilingual plugins (WPML, Polylang) or custom
	// language switching to inject <link rel="alternate" hreflang="..."> tags.
	do_action( 'blogpro_hreflang_tags' );

	// Twitter / X Card
	echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	$twitter_site = get_theme_mod( 'blogpro_twitter_site', '' );
	if ( $twitter_site ) {
		echo '<meta name="twitter:site" content="' . esc_attr( $twitter_site ) . '">' . "\n";
	}
	// Per-post author Twitter handle (stored in user meta `twitter` or `_blogpro_twitter`).
	if ( is_singular( 'post' ) ) {
		global $post;
		$author_twitter = get_the_author_meta( 'twitter', $post->post_author );
		if ( ! $author_twitter ) {
			$author_twitter = get_user_meta( $post->post_author, '_blogpro_twitter', true );
		}
		if ( $author_twitter ) {
			// Normalise — ensure it starts with @.
			$author_twitter = '@' . ltrim( $author_twitter, '@' );
			echo '<meta name="twitter:creator" content="' . esc_attr( $author_twitter ) . '">' . "\n";
		}
	}
	echo '<meta name="twitter:title" content="' . $title . '">' . "\n";
	echo '<meta name="twitter:description" content="' . $description . '">' . "\n";
	if ( $image ) {
		echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
		$img_alt = blogpro_social_image_alt();
		if ( $img_alt ) {
			echo '<meta name="twitter:image:alt" content="' . esc_attr( $img_alt ) . '">' . "\n";
		}
	}

	echo "<!-- /Blog Pro SEO meta -->\n";
}
add_action( 'wp_head', 'blogpro_output_meta_tags', 2 );

/**
 * Noindex RSS/Atom feeds via X-Robots-Tag HTTP header.
 *
 * Feed URLs (/feed/, /comments/feed/) have no SEO value and waste crawl
 * budget. Noindexing them via the HTTP header (not a meta tag — feeds are
 * XML, not HTML) prevents them appearing in search results.
 */
function blogpro_noindex_feeds() {
	if ( is_feed() && ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}
}
add_action( 'wp', 'blogpro_noindex_feeds' );

/* Override the default <title> with our computed value for full control. */
add_filter( 'pre_get_document_title', 'blogpro_get_meta_title' );

/**
 * Remove the WordPress generator <meta> tag — no need to advertise WP version.
 */
remove_action( 'wp_head', 'wp_generator' );

/**
 * Favicon / icon fallback.
 *
 * WordPress core emits <link rel="icon"> via wp_site_icon() only when a
 * Site Icon is set in Settings → Site Identity. If none is set, output a
 * fallback from the theme's own assets so the browser tab is never blank.
 *
 * Priority 99 — runs AFTER wp_site_icon() (priority 1) so we only act
 * when core didn't output anything.
 */
function blogpro_favicon_fallback() {
	// If WP already emitted a site icon, nothing to do.
	if ( has_site_icon() ) {
		return;
	}

	// Prefer an SVG then fall back to a PNG — both live in theme assets.
	$svg = BLOGPRO_DIR . '/assets/images/favicon.svg';
	$png = BLOGPRO_DIR . '/assets/images/icon.png';
	$ico = BLOGPRO_DIR . '/assets/images/favicon.ico';

	if ( file_exists( $svg ) ) {
		$url = BLOGPRO_URI . '/assets/images/favicon.svg';
		echo '<link rel="icon" href="' . esc_url( $url ) . '" type="image/svg+xml">' . "\n";
	} elseif ( file_exists( $png )  ) {
		$url = BLOGPRO_URI . '/assets/images/icon.png';
		echo '<link rel="icon" href="' . esc_url( $url ) . '" type="image/png">' . "\n";
	} elseif ( file_exists( $ico ) ) {
		$url = BLOGPRO_URI . '/assets/images/favicon.ico';
		echo '<link rel="icon" href="' . esc_url( $url ) . '" type="image/x-icon">' . "\n";
	}

	// Apple Touch Icon — 180×180 PNG preferred.
	$touch = BLOGPRO_DIR . '/assets/images/apple-touch-icon.png';
	if ( file_exists( $touch ) ) {
		echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( BLOGPRO_URI . '/assets/images/apple-touch-icon.png' ) . '">' . "\n";
	} elseif ( file_exists( $png ) ) {
		echo '<link rel="apple-touch-icon" href="' . esc_url( BLOGPRO_URI . '/assets/images/icon.png' ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'blogpro_favicon_fallback', 99 );

/**
 * Register Customizer settings for social / SEO handles.
 *
 * Adds:
 *  - blogpro_twitter_site    — site-level @handle (e.g. @MySiteName)
 *  - blogpro_theme_color     — browser chrome / PWA theme colour
 */
function blogpro_customizer_seo_settings( $wp_customize ) {
	$wp_customize->add_section( 'blogpro_seo', array(
		'title'    => __( 'SEO & Social', 'blog-pro' ),
		'priority' => 160,
	) );

	// Twitter/X site handle
	$wp_customize->add_setting( 'blogpro_twitter_site', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'blogpro_twitter_site', array(
		'label'       => __( 'Twitter / X Site Handle', 'blog-pro' ),
		'description' => __( 'Include the @ sign, e.g. @MySiteName', 'blog-pro' ),
		'section'     => 'blogpro_seo',
		'type'        => 'text',
	) );

	// Theme colour
	$wp_customize->add_setting( 'blogpro_theme_color', array(
		'default'           => '#4f46e5',
		'sanitize_callback' => 'sanitize_hex_color',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'blogpro_theme_color', array(
		'label'   => __( 'Browser Theme Colour', 'blog-pro' ),
		'section' => 'blogpro_seo',
	) ) );
}
add_action( 'customize_register', 'blogpro_customizer_seo_settings' );

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