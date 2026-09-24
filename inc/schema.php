<?php
/**
 * JSON-LD structured data. Outputs Organization/WebSite schema sitewide,
 * BlogPosting schema on single posts, and BreadcrumbList everywhere but
 * the homepage. Also useful for GEO (Generative Engine Optimization) —
 * AI answer engines lean heavily on clean, explicit schema.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function blogpro_schema_website() {
	return array(
		'@type' => 'WebSite',
		'@id'   => home_url( '/#website' ),
		'url'   => home_url( '/' ),
		'name'  => get_bloginfo( 'name' ),
		'description' => get_bloginfo( 'description' ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			// EntryPoint format is required by current Google spec for Sitelinks
			// Searchbox eligibility (the older string-only target still works
			// but generates a Rich Result Test warning).
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		),
	);
}

function blogpro_schema_organization() {
	$logo = get_custom_logo() ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) : '';
	$org  = array(
		'@type' => 'Organization',
		'@id'   => home_url( '/#organization' ),
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);
	if ( $logo ) {
		// Full ImageObject with dimensions preferred by Google Knowledge Panel.
		$logo_id = get_theme_mod( 'custom_logo' );
		$logo_src = $logo_id ? wp_get_attachment_image_src( $logo_id, 'full' ) : null;
		$logo_obj = array( '@type' => 'ImageObject', 'url' => $logo );
		if ( $logo_src ) {
			$logo_obj['width']  = (int) $logo_src[1];
			$logo_obj['height'] = (int) $logo_src[2];
		}
		$org['logo'] = $logo_obj;
	}
	// foundingDate — helps knowledge panels and AI entity grounding.
	$founding_year = get_theme_mod( 'blogpro_org_founding_year', '' );
	if ( $founding_year ) {
		$org['foundingDate'] = sanitize_text_field( $founding_year );
	}
	// contactPoint — used by Google Business and AI answer engines.
	$contact_email = get_theme_mod( 'blogpro_org_contact_email', '' );
	$contact_phone = get_theme_mod( 'blogpro_org_contact_phone', '' );
	if ( $contact_email || $contact_phone ) {
		$contact = array(
			'@type'           => 'ContactPoint',
			'contactType'     => 'customer support',
			'availableLanguage' => get_bloginfo( 'language' ),
		);
		if ( $contact_email ) $contact['email']       = sanitize_email( $contact_email );
		if ( $contact_phone ) $contact['telephone']   = sanitize_text_field( $contact_phone );
		$org['contactPoint'] = $contact;
	}
	if ( function_exists( 'blogpro_entity_same_as' ) ) {
		$same_as = blogpro_entity_same_as();
		if ( $same_as ) {
			$org['sameAs'] = $same_as;
		}
	}
	return $org;
}

function blogpro_schema_breadcrumbs() {
	$items = array( array( '@type' => 'ListItem', 'position' => 1, 'name' => __( 'Home', 'blog-pro' ), 'item' => home_url( '/' ) ) );
	$pos = 2;

	if ( is_singular( 'post' ) ) {
		$cats = get_the_category();
		if ( ! empty( $cats ) ) {
			$items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => $cats[0]->name, 'item' => get_category_link( $cats[0]->term_id ) );
		}
		$items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => get_the_title() );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => single_term_title( '', false ) );
	} elseif ( is_page() ) {
		$items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => get_the_title() );
	} else {
		return null;
	}

	return array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
}

function blogpro_schema_blogposting() {
	global $post;
	// 'blogpro-hero' isn't registered (see functions.php) — fall back to the
	// full master, matching what the og:image tag emits.
	$image    = '';
	$image_w  = null;
	$image_h  = null;
	if ( has_post_thumbnail() ) {
		if ( function_exists( 'blogpro_social_image_data' ) ) {
			$social_data = blogpro_social_image_data();
			$image       = $social_data['url'];
			$image_w     = $social_data['w'];
			$image_h     = $social_data['h'];
		}
		if ( ! $image ) {
			$image = wp_get_attachment_image_url( get_post_thumbnail_id(), 'full' );
		}
	}
	$word_count      = str_word_count( wp_strip_all_tags( $post->post_content ) );
	$reading_minutes = max( 1, (int) ceil( $word_count / 200 ) );

	$schema = array(
		'@type'            => 'BlogPosting',
		'@id'              => get_permalink() . '#article',
		'mainEntityOfPage' => get_permalink(),
		'headline'         => get_the_title(),
		'description'      => blogpro_get_meta_description(),
		'datePublished'    => get_the_date( 'c' ),
		'dateModified'     => get_the_modified_date( 'c' ),
		'author'           => blogpro_schema_person( $post->post_author ),
		'publisher'        => array( '@id' => home_url( '/#organization' ) ),
		'wordCount'        => $word_count,
		'timeRequired'     => 'PT' . $reading_minutes . 'M',
		'copyrightYear'    => (int) get_the_date( 'Y' ),
		'copyrightHolder'  => array( '@id' => home_url( '/#organization' ) ),
		'inLanguage'       => get_bloginfo( 'language' ),
	);
	if ( $image ) {
		// Full ImageObject with dimensions — Google requires width+height
		// for image rich results eligibility on BlogPosting.
		$img_obj = array(
			'@type' => 'ImageObject',
			'url'   => $image,
		);
		if ( $image_w && $image_h ) {
			$img_obj['width']  = (int) $image_w;
			$img_obj['height'] = (int) $image_h;
		}
		$schema['image'] = $img_obj;
	}
	$cats = get_the_category();
	if ( ! empty( $cats ) ) {
		$schema['articleSection'] = wp_list_pluck( $cats, 'name' );
	}
	// keywords from post tags — improves topical relevance signals.
	$tags = get_the_tags();
	if ( $tags && ! is_wp_error( $tags ) ) {
		$schema['keywords'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
	}
	// about — primary topic as a typed Thing. AI engines use this to
	// anchor the article to a knowledge graph node (strongest GEO signal).
	$primary_topic = '';
	if ( ! empty( $cats ) ) {
		$primary_topic = $cats[0]->name;
	} elseif ( $tags && ! is_wp_error( $tags ) ) {
		$primary_topic = $tags[0]->name;
	}
	if ( $primary_topic ) {
		$schema['about'] = array(
			'@type' => 'Thing',
			'name'  => $primary_topic,
		);
	}
	// mentions — entities linked from the post content (internal and
	// Wikipedia/authoritative external links). Helps AI cluster content
	// topically and verify factual claims.
	if ( preg_match_all( '/<a\b[^>]*\bhref=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', $post->post_content, $link_matches, PREG_SET_ORDER ) ) {
		$mentions = array();
		$seen     = array();
		foreach ( $link_matches as $m ) {
			$href  = esc_url_raw( trim( $m[1] ) );
			$label = trim( wp_strip_all_tags( $m[2] ) );
			if ( ! $href || ! $label || isset( $seen[ $href ] ) ) continue;
			// Only external links or internal anchors to other posts (not
			// category/tag/admin/feed URLs) qualify as entity mentions.
			$is_external = ( 0 !== strpos( $href, home_url() ) );
			$is_internal_post = ( 0 === strpos( $href, home_url() )
				&& ! preg_match( '#/(wp-admin|wp-login|feed|tag|category)(/|$)#i', $href ) );
			if ( ! $is_external && ! $is_internal_post ) continue;
			$seen[ $href ] = true;
			$mentions[]    = array( '@type' => 'Thing', 'name' => $label, 'url' => $href );
			if ( count( $mentions ) >= 10 ) break; // cap at 10 to keep JSON lean
		}
		if ( $mentions ) {
			$schema['mentions'] = $mentions;
		}
	}
	// speakable — tells Google Assistant / AI Overviews which parts to read aloud.
	// CSS selectors target the elements that hold the headline and intro paragraph.
	$schema['speakable'] = array(
		'@type'          => 'SpeakableSpecification',
		'cssSelector'    => array( 'h1.entry-title', '.entry-content > p:first-of-type', '.entry-summary' ),
	);
	// Key Takeaways → abstract (GEO: the block AI engines quote verbatim).
	if ( function_exists( 'blogpro_takeaways_for_post' ) ) {
		$takeaways = blogpro_takeaways_for_post( $post->ID );
		if ( $takeaways ) {
			$schema['abstract'] = implode( ' ', $takeaways );
		}
	}
	// Sources → citation[] (provenance signals engines can verify).
	if ( function_exists( 'blogpro_sources_for_post' ) ) {
		$sources = blogpro_sources_for_post( $post->ID );
		if ( $sources ) {
			$schema['citation'] = array_map( function ( $s ) {
				return array( '@type' => 'CreativeWork', 'name' => $s['label'], 'url' => $s['url'] );
			}, $sources );
		}
	}
	// TOC sections → hasPart (WebPageElement per heading anchor).
	if ( function_exists( 'blogpro_toc_schema_parts' ) ) {
		$parts = blogpro_toc_schema_parts( $post->ID );
		if ( $parts ) {
			$schema['hasPart'] = $parts;
		}
	}
	return $schema;
}

/**
 * Person node for an author — name, archive URL, jobTitle and sameAs
 * identity links when the profile provides them.
 *
 * @param int $user_id
 * @return array
 */
function blogpro_schema_person( $user_id ) {
	$person = array(
		'@type' => 'Person',
		'name'  => get_the_author_meta( 'display_name', $user_id ),
		'url'   => get_author_posts_url( $user_id ),
	);
	$job_title = trim( (string) get_user_meta( $user_id, 'blogpro_job_title', true ) );
	if ( $job_title ) {
		$person['jobTitle'] = $job_title;
	}
	// knowsAbout — topics the author writes about, derived from the
	// categories they have published posts in. AI engines use this to
	// assess per-topic author authority (E-E-A-T expertise signal).
	$author_cats = get_categories( array(
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 10,
	) );
	if ( $author_cats && ! is_wp_error( $author_cats ) ) {
		// Filter to only categories where this author has actually published.
		$knows = array();
		foreach ( $author_cats as $cat ) {
			$count = (int) $cat->count;
			if ( $count < 1 ) continue;
			// Quick check: does this author have posts in this cat?
			$check = get_posts( array(
				'author'         => $user_id,
				'category'       => $cat->term_id,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'fields'         => 'ids',
			) );
			if ( $check ) {
				$knows[] = $cat->name;
			}
		}
		if ( $knows ) {
			$person['knowsAbout'] = $knows;
		}
	}
	if ( function_exists( 'blogpro_author_same_as' ) ) {
		$same_as = blogpro_author_same_as( $user_id );
		if ( $same_as ) {
			$person['sameAs'] = $same_as;
		}
	}
	return $person;
}

/**
 * WebPage node — describes the page that wraps the content.
 * Required for full Knowledge Graph integration and breadcrumb
 * association. Works on all page types, not just posts.
 *
 * @return array
 */
function blogpro_schema_webpage() {
	$canonical = function_exists( 'blogpro_get_canonical_url' ) ? blogpro_get_canonical_url() : home_url( '/' );
	$title     = function_exists( 'blogpro_get_meta_title' )    ? blogpro_get_meta_title()    : get_bloginfo( 'name' );

	// Choose the most specific @type for the current context.
	if ( is_singular( 'post' ) ) {
		$type = 'Article';
	} elseif ( is_front_page() || is_home() ) {
		$type = 'WebPage';
	} elseif ( is_search() ) {
		$type = 'SearchResultsPage';
	} elseif ( is_author() ) {
		$type = 'ProfilePage';
	} else {
		$type = 'WebPage';
	}

	$node = array(
		'@type'      => $type,
		'@id'        => $canonical . '#webpage',
		'url'        => $canonical,
		'name'       => $title,
		'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
		'inLanguage' => get_bloginfo( 'language' ),
	);

	// Publisher cross-reference.
	$node['about'] = array( '@id' => home_url( '/#organization' ) );

	// Date signals — only meaningful on singular pages.
	if ( is_singular() ) {
		$node['datePublished'] = get_the_date( 'c' );
		$node['dateModified']  = get_the_modified_date( 'c' );
	}

	// Breadcrumb cross-reference — wired to the BreadcrumbList node.
	if ( ! is_front_page() ) {
		$node['breadcrumb'] = array( '@id' => $canonical . '#breadcrumb' );
	}

	// Thumbnail as primaryImageOfPage for image-search eligibility.
	if ( is_singular() && has_post_thumbnail() && function_exists( 'blogpro_social_image_data' ) ) {
		$img = blogpro_social_image_data();
		if ( $img['url'] ) {
			$img_node = array( '@type' => 'ImageObject', 'url' => $img['url'] );
			if ( $img['w'] && $img['h'] ) {
				$img_node['width']  = (int) $img['w'];
				$img_node['height'] = (int) $img['h'];
			}
			$node['primaryImageOfPage'] = $img_node;
			$node['thumbnailUrl']       = $img['url'];
		}
	}

	return $node;
}

function blogpro_output_schema() {
	$graph = array( blogpro_schema_website(), blogpro_schema_organization() );

	// WebPage node on every URL (required for breadcrumb wiring + KG).
	$graph[] = blogpro_schema_webpage();

	// BreadcrumbList — @id matches the WebPage breadcrumb reference above.
	$breadcrumbs = blogpro_schema_breadcrumbs();
	if ( $breadcrumbs ) {
		$canonical   = function_exists( 'blogpro_get_canonical_url' ) ? blogpro_get_canonical_url() : home_url( '/' );
		$breadcrumbs['@id'] = $canonical . '#breadcrumb';
		$graph[] = $breadcrumbs;
	}

	if ( is_singular( 'post' ) ) {
		$graph[] = blogpro_schema_blogposting();
	}

	/**
	 * Filter the @graph nodes before output. Feature modules append their
	 * own entities here (e.g. inc/video-optimisation.php adds VideoObject
	 * nodes for videos embedded in the post).
	 *
	 * @param array $graph
	 */
	$graph = apply_filters( 'blogpro_schema_graph', $graph );

	// Strip any null values that crept in through conditional fields.
	$graph = array_map( function( $node ) {
		return array_filter( $node, function( $v ) { return null !== $v; } );
	}, $graph );

	$output = array(
		'@context' => 'https://schema.org',
		'@graph'   => array_values( $graph ),
	);

	echo '<script type="application/ld+json">' . wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'blogpro_output_schema', 3 );

/**
 * Register Customizer settings for Organization entity enrichment.
 *
 * Adds founding year, contact email, and contact phone — used in the
 * Organization schema node above and surfaced in knowledge panels / AI answers.
 */
function blogpro_customizer_org_settings( $wp_customize ) {
	// Section already created by seo-meta.php (blogpro_seo); add controls there.
	$section = 'blogpro_seo';

	$wp_customize->add_setting( 'blogpro_org_founding_year', array(
		'default'           => '',
		'sanitize_callback' => 'absint',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'blogpro_org_founding_year', array(
		'label'       => __( 'Organisation Founding Year', 'blog-pro' ),
		'description' => __( 'e.g. 2018 — used in Organization schema foundingDate.', 'blog-pro' ),
		'section'     => $section,
		'type'        => 'number',
	) );

	$wp_customize->add_setting( 'blogpro_org_contact_email', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_email',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'blogpro_org_contact_email', array(
		'label'   => __( 'Organisation Contact Email', 'blog-pro' ),
		'section' => $section,
		'type'    => 'email',
	) );

	$wp_customize->add_setting( 'blogpro_org_contact_phone', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'blogpro_org_contact_phone', array(
		'label'       => __( 'Organisation Contact Phone', 'blog-pro' ),
		'description' => __( 'E.164 format preferred, e.g. +8801700000000', 'blog-pro' ),
		'section'     => $section,
		'type'        => 'tel',
	) );
}
add_action( 'customize_register', 'blogpro_customizer_org_settings' );
