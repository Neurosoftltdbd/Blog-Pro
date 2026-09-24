<?php
/**
 * llms.txt — llmstxt.org index for AI answer engines.
 *
 * Served at /llms.txt (virtual, via rewrite below). Cached in a transient
 * (1 h) because building it walks posts + terms; busted on any content
 * save so it's never stale by more than a write.
 *
 * Structure: site blurb → categories with their posts (title, URL, real
 * excerpt/takeaways, updated date) → featured pages → key documents →
 * site info → contact. Markdown link text is escaped so brackets in
 * titles can't corrupt the file.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Rewrite rule for /llms.txt → ?blogpro_llm=1
function blogpro_add_llm_rewrite() {
	add_rewrite_rule( '^llms\.txt$', 'index.php?blogpro_llm=1', 'top' );
}
add_action( 'init', 'blogpro_add_llm_rewrite' );

// Register query var
function blogpro_llm_query_vars( $vars ) {
	$vars[] = 'blogpro_llm';
	return $vars;
}
add_filter( 'query_vars', 'blogpro_llm_query_vars' );

/**
 * Escape markdown link label text (titles with [ ] or parentheses would
 * otherwise break the [title](url) syntax AI parsers rely on).
 */
function blogpro_llm_md( $text ) {
	$text = preg_replace( '/\s+/u', ' ', trim( (string) $text ) );
	return str_replace( array( '[', ']', '(', ')', '`', '*' ), array( '\[', '\]', '\(', '\)', '`', '*' ), $text );
}

/**
 * One-line summary for a post: Key Takeaways box > manual excerpt >
 * meta description > trimmed body. Never raw block markup.
 */
function blogpro_llm_summary( $post ) {
	if ( function_exists( 'blogpro_takeaways_for_post' ) ) {
		$takeaways = blogpro_takeaways_for_post( $post->ID );
		if ( $takeaways ) {
			return implode( ' ', $takeaways );
		}
	}
	if ( has_excerpt( $post ) ) {
		return wp_strip_all_tags( $post->post_excerpt );
	}
	if ( function_exists( 'blogpro_trim_description' ) ) {
		return blogpro_trim_description( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
	}
	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 40 );
}

/**
 * Build the llms.txt markdown (pure function — cacheable).
 *
 * @return string
 */
function blogpro_llm_build() {
	$site_name = get_bloginfo( 'name' );
	$site_desc = get_bloginfo( 'description' );

	$output  = "# {$site_name}\n";
	if ( $site_desc ) {
		$output .= "> {$site_desc}\n\n";
	}
	$latest = get_posts( array( 'posts_per_page' => 1, 'orderby' => 'modified', 'order' => 'DESC', 'post_status' => 'publish' ) );
	$output .= "> Language: " . get_bloginfo( 'language' ) . "\n";
	$output .= "> Sitemap: " . home_url( '/sitemap.xml' ) . "\n";
	if ( $latest ) {
		$output .= "> Last-Updated: " . mysql2date( 'c', $latest[0]->post_modified_gmt, false ) . "Z\n";
	}
	$output .= "\n";

	/**
	 * Filter the max posts listed per category.
	 *
	 * @param int $per_cat
	 */
	$per_cat  = absint( apply_filters( 'blogpro_llm_posts_per_category', 15 ) );
	$cats     = get_categories( array( 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 12 ) );

	if ( $cats ) {
		foreach ( $cats as $cat ) {
			$posts = get_posts( array(
				'category'       => $cat->term_id,
				'posts_per_page' => $per_cat,
				'post_status'    => 'publish',
				'orderby'        => 'modified',
			) );
			if ( ! $posts ) {
				continue;
			}
			$heading = $cat->name . ' (' . $cat->count . ')';
			$output .= "## " . blogpro_llm_md( $heading ) . "\n";
			if ( $cat->description ) {
				$output .= "> " . blogpro_llm_md( $cat->description ) . "\n";
			}
			foreach ( $posts as $p ) {
				$title = blogpro_llm_md( get_the_title( $p ) );
				$url   = get_permalink( $p );
				$summ  = blogpro_llm_md( blogpro_llm_summary( $p ) );
				$output .= "- [{$title}]({$url}): {$summ}\n";
			}
			$output .= "\n";
		}
	}

	// ---- Uncategorized (safety net: posts outside the category loop) ----
	$loose_posts = $cats ? get_posts( array(
		'category__not_in' => wp_list_pluck( $cats, 'term_id' ),
		'posts_per_page'   => $per_cat,
		'orderby'          => 'modified',
		'post_status'      => 'publish',
	) ) : array();
	if ( $loose_posts ) {
		$output .= "## More Posts\n";
		foreach ( $loose_posts as $p ) {
			$output .= '- [' . blogpro_llm_md( get_the_title( $p ) ) . '](' . get_permalink( $p ) . "): " . blogpro_llm_md( blogpro_llm_summary( $p ) ) . "\n";
		}
		$output .= "\n";
	}

	// ---- Featured Pages (high priority) ----
	$featured_pages = get_posts( array(
		'post_type'      => 'page',
		'meta_key'       => 'featured',
		'meta_value'     => '1',
		'posts_per_page' => 20,
		'post_status'    => 'publish',
	) );
	if ( $featured_pages ) {
		$output .= "## Featured Pages\n";
		foreach ( $featured_pages as $fp ) {
			$output .= '- [' . blogpro_llm_md( get_the_title( $fp ) ) . '](' . get_permalink( $fp ) . ")\n";
		}
		$output .= "\n";
	}

	// ---- Key Documents (capped — was unbounded -1 over the whole library) ----
	$docs = get_posts( array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'application/pdf',
		'posts_per_page' => 25,
		'post_status'    => 'inherit',
	) );
	if ( $docs ) {
		$output .= "## Key Documents\n";
		foreach ( $docs as $doc ) {
			$output .= '- [' . blogpro_llm_md( $doc->post_title ) . '](' . wp_get_attachment_url( $doc ) . ")\n";
		}
		$output .= "\n";
	}

	// ---- Site metadata ----
	$output .= "## Site Info\n";
	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( $logo_url ) {
			$output .= "Logo: {$logo_url}\n";
		}
	}
	$authors = get_users( array( 'fields' => array( 'ID', 'display_name', 'description' ), 'number' => 5, 'has_published_posts' => 'post' ) );
	foreach ( $authors as $author ) {
		$output .= "Author: {$author->display_name}\n";
		if ( ! empty( $author->description ) ) {
			$output .= "Bio: " . blogpro_llm_md( $author->description ) . "\n";
		}
	}

	// ---- Contact ----
	$contact_email = get_option( 'admin_email' );
	$contact_phone = get_theme_mod( 'contact_phone' );
	$output .= "\n## Contact\n";
	if ( $contact_email ) {
		$output .= "Email: {$contact_email}\n";
	}
	if ( $contact_phone ) {
		$output .= "Phone: {$contact_phone}\n";
	}

	return $output;
}

/**
 * Transient name for the cached llms.txt.
 */
function blogpro_llm_cache_key() {
	return 'blogpro_llms_txt';
}

/**
 * Serve the dynamic markdown — cache-first.
 */
function blogpro_serve_dynamic_llm() {
	if ( get_query_var( 'blogpro_llm' ) !== '1' ) return;

	$output = get_transient( blogpro_llm_cache_key() );
	if ( false === $output ) {
		$output = blogpro_llm_build();
		set_transient( blogpro_llm_cache_key(), $output, HOUR_IN_SECONDS );
	}

	if ( ! headers_sent() ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		// Prevent the llms.txt file itself appearing in search results
		// while remaining readable by AI crawlers (they read the body).
		header( 'X-Robots-Tag: noindex, nofollow' );
	}
	echo $output; // phpcs:ignore WordPress.Security.EscapeOutput -- static markdown index for AI crawlers.
	exit;
}
add_action( 'template_redirect', 'blogpro_serve_dynamic_llm' );

/**
 * Bust the cache whenever content changes (cheap invalidation — rebuild
 * happens lazily on the next request).
 */
function blogpro_llm_invalidate( $post_id = 0 ) {
	delete_transient( blogpro_llm_cache_key() );
}
add_action( 'save_post', 'blogpro_llm_invalidate' );
add_action( 'edit_category', 'blogpro_llm_invalidate' );
add_action( 'switch_theme', 'blogpro_llm_invalidate' );
