<?php
/**
 * Minimal, fast XML sitemap served at /sitemap.xml — no plugin, no
 * database-heavy query builder. Includes posts, pages, and a lastmod
 * date so search engines can prioritize recrawls efficiently.
 *
 * Note: WP core also auto-generates /wp-sitemap.xml since 5.5. We
 * disable that here in favor of this single, simpler, cacheable file
 * so there's exactly one sitemap to submit to Search Console.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'wp_sitemaps_enabled', '__return_false' );

function blogpro_register_sitemap_rewrite() {
	add_rewrite_rule( '^sitemap\.xml$', 'index.php?blogpro_sitemap=1', 'top' );
}
add_action( 'init', 'blogpro_register_sitemap_rewrite' );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'blogpro_sitemap';
	return $vars;
} );

/**
 * Collects image URLs for a post's <image:image> sitemap entries:
 * the featured image (full size, not the cropped card/hero variant —
 * Google prefers the original for image search) plus any images
 * embedded directly in the post content.
 */
function blogpro_sitemap_images_for_post( $post ) {
	$images = array();

	if ( has_post_thumbnail( $post ) ) {
		$thumb_id  = get_post_thumbnail_id( $post );
		$thumb_url = wp_get_attachment_image_url( $thumb_id, 'full' );
		if ( $thumb_url ) {
			$images[] = array(
				'loc'   => $thumb_url,
				'title' => get_the_title( $post ),
			);
		}
	}

	if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $post->post_content, $matches ) ) {
		foreach ( $matches[1] as $src ) {
			// Skip if it's the same as the featured image (already added) or an external/CDN URL not on this site.
			if ( ! empty( $images ) && $src === $images[0]['loc'] ) continue;
			$images[] = array( 'loc' => $src );
			if ( count( $images ) >= 10 ) break; // sitemap protocol allows up to 1000, 10 is a sane cap per post
		}
	}

	return $images;
}

function blogpro_maybe_render_sitemap() {
	if ( ! get_query_var( 'blogpro_sitemap' ) ) return;

	header( 'Content-Type: application/xml; charset=UTF-8' );

	// Check cache
	$cached = get_transient( 'blogpro_sitemap_xml' );
	if ( $cached !== false ) {
		echo $cached;
		exit;
	}

	ob_start();

	$urls = array();

	$lastmod_timestamp = get_lastpostmodified( 'U' );
	$lastmod = $lastmod_timestamp ? date( 'c', $lastmod_timestamp ) : '';
	$home_entry = array( 'loc' => home_url( '/' ), 'lastmod' => $lastmod, 'priority' => '1.0' );
	if ( has_custom_logo() ) {
		$logo_url = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
		if ( $logo_url ) {
			$home_entry['images'] = array( array( 'loc' => $logo_url, 'title' => get_bloginfo( 'name' ) ) );
		}
	}
	$urls[] = $home_entry;

	// Posts
	$posts = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 2000,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	foreach ( $posts as $p ) {
		$entry = array(
			'loc'      => get_permalink( $p ),
			'lastmod'  => get_the_modified_date( 'c', $p ),
			'priority' => '0.8',
			'images'   => blogpro_sitemap_images_for_post( $p ),
		);
		$urls[] = $entry;
	}

	// Pages
	$pages = get_posts( array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => 500,
		'no_found_rows'  => true,
	) );
	foreach ( $pages as $p ) {
		$urls[] = array(
			'loc'      => get_permalink( $p ),
			'lastmod'  => get_the_modified_date( 'c', $p ),
			'priority' => '0.6',
		);
	}

	// Categories
	$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true ) );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			$urls[] = array( 'loc' => get_term_link( $t ), 'lastmod' => '', 'priority' => '0.5' );
		}
	}

	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
	foreach ( $urls as $u ) {
		echo "\t<url>\n";
		echo "\t\t<loc>" . esc_url( $u['loc'] ) . "</loc>\n";
		if ( ! empty( $u['lastmod'] ) ) {
			echo "\t\t<lastmod>" . esc_html( $u['lastmod'] ) . "</lastmod>\n";
		}
		echo "\t\t<priority>" . esc_html( $u['priority'] ) . "</priority>\n";
		if ( ! empty( $u['images'] ) ) {
			foreach ( $u['images'] as $img ) {
				echo "\t\t<image:image>\n";
				echo "\t\t\t<image:loc>" . esc_url( $img['loc'] ) . "</image:loc>\n";
				if ( ! empty( $img['title'] ) ) {
					echo "\t\t\t<image:title>" . esc_html( $img['title'] ) . "</image:title>\n";
				}
				echo "\t\t</image:image>\n";
			}
		}
		echo "\t</url>\n";
	}
	echo '</urlset>';

	$xml = ob_get_clean();
	set_transient( 'blogpro_sitemap_xml', $xml, DAY_IN_SECONDS );
	echo $xml;
	exit;
}
// Priority 0: must run BEFORE core's redirect_canonical (default 10), which
// treats /sitemap.xml as a page and 301s it to /sitemap.xml/ — crawlers then
// index the redirected URL and the sitemap entry mismatches.
add_action( 'template_redirect', 'blogpro_maybe_render_sitemap', 0 );

// Invalidate cache when content changes
add_action( 'save_post', function() { delete_transient( 'blogpro_sitemap_xml' ); } );
add_action( 'edited_terms', function() { delete_transient( 'blogpro_sitemap_xml' ); } );
add_action( 'customize_save_after', function() { delete_transient( 'blogpro_sitemap_xml' ); } );