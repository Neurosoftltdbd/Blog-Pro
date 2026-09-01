<?php
/**
 * WooCommerce SEO + GEO structured data (JSON-LD).
 *
 * Loaded from woocommerce-support.php. Everything here lives in the
 * woocommerce/ layer and only ADDS to / ENRICHES what WooCommerce core
 * already emits — it never duplicates a node (duplicate @ids confuse
 * Google and generative engines).
 *
 * What WooCommerce core already outputs (we enrich, not replace):
 *   - Product  (name, sku, description, image, offers, aggregateRating,
 *              review, gtin)  → wp_footer, @id = {permalink}#product
 *   - BreadcrumbList          → @id = {permalink}#breadcrumb
 *   - WebSite                 → @id = {home}#website
 *
 * What the theme (inc/schema.php) already outputs:
 *   - WebSite + Organization  → @id = {home}#website / #organization
 *
 * What THIS file adds:
 *   Product page (is_product):
 *     - Enriches the core Product node: brand, category, keywords,
 *       all gallery images, attributes as additionalProperty, weight /
 *       dimensions, specific gtin fields, itemCondition.
 *     - Enriches each Offer: seller → Organization @id reference,
 *       shippingDetails, hasMerchantReturnPolicy, availability.
 *     - A WebPage node whose mainEntity references the Product and
 *       whose breadcrumb references the BreadcrumbList (the "page
 *       entity" glue generative engines use to resolve a URL).
 *     - A FAQPage built from product attributes + filterable Q&A.
 *   Shop / category / tag (is_shop || is_product_taxonomy):
 *     - CollectionPage + ItemList of the products on the page.
 *   Organization (reference-only):
 *     - sameAs (social profiles) + contactPoint, merged by @id with the
 *       theme's Organization node.
 *
 * Every block is filterable:
 *   blogpro_wc_schema_product        (array $markup, WC_Product)
 *   blogpro_wc_schema_offer          (array $offer, WC_Product)
 *   blogpro_wc_schema_shipping       (array $shipping, WC_Product)
 *   blogpro_wc_schema_return_policy  (array $policy, WC_Product)
 *   blogpro_wc_schema_product_faq    (array $faq, WC_Product)
 *   blogpro_wc_schema_organization   (array $org)
 *   blogpro_wc_schema_enabled        (bool $enabled, string $context)
 *
 * @package BlogPro
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ===================================================================
 * 0. Master switch — bail everywhere if disabled.
 * ================================================================= */
if ( ! apply_filters( 'blogpro_wc_schema_enabled', true, 'all' ) ) {
	return;
}

/**
 * Helper: is a given schema context enabled?
 *
 * @param string $context product|offer|webpage|faq|archive|organization.
 * @return bool
 */
function blogpro_wc_schema_enabled( $context ) {
	$enabled = apply_filters( 'blogpro_wc_schema_enabled', true, $context );
	return (bool) $enabled;
}

/**
 * Helper: absolute URL for an attachment in the resizer form (WebP,
 * srcset-friendly) so schema images match what the page actually
 * serves. Falls back to the metadata URL.
 *
 * @param int $attachment_id
 * @return string
 */
function blogpro_wc_schema_image_url( $attachment_id ) {
	if ( ! $attachment_id ) return '';
	// Prefer the largest resizer width; the resizer rewrites on the fly.
	$src = wp_get_attachment_image_src( $attachment_id, 'full' );
	return $src ? $src[0] : wp_get_attachment_url( $attachment_id );
}

/* ===================================================================
 * 1. Enrich the WooCommerce Product node.
 *    Core builds it at woocommerce_single_product_summary (p60) and
 *    runs it through woocommerce_structured_data_product. We add the
 *    fields Google + LLMs look for that core omits.
 * ================================================================= */
function blogpro_wc_schema_enrich_product( $markup, $product ) {
	if ( ! $product instanceof WC_Product ) return $markup;
	if ( ! blogpro_wc_schema_enabled( 'product' ) ) return $markup;

	/* ---- Brand: product_brand term, else store name ---- */
	$brand = '';
	if ( taxonomy_exists( 'product_brand' ) ) {
		$brand_terms = wp_get_object_terms( $product->get_id(), 'product_brand', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
			$brand = $brand_terms[0];
		}
	}
	if ( ! $brand ) {
		$brand = get_bloginfo( 'name' );
	}
	if ( $brand ) {
		$markup['brand'] = array(
			'@type' => 'Brand',
			'name'  => $brand,
		);
	}

	/* ---- All images (featured + gallery) as an array ---- */
	$images = array();
	if ( $product->get_image_id() ) {
		$images[] = blogpro_wc_schema_image_url( $product->get_image_id() );
	}
	foreach ( $product->get_gallery_image_ids() as $gid ) {
		$url = blogpro_wc_schema_image_url( $gid );
		if ( $url && ! in_array( $url, $images, true ) ) {
			$images[] = $url;
		}
	}
	if ( $images ) {
		$markup['image'] = count( $images ) === 1 ? $images[0] : $images;
	}

	/* ---- Category (primary product_cat) ---- */
	$cats = get_the_terms( $product->get_id(), 'product_cat' );
	if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
		$markup['category'] = esc_html( $cats[0]->name );
	}

	/* ---- Keywords (tags) ---- */
	$tags = get_the_terms( $product->get_id(), 'product_tag' );
	if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
		$markup['keywords'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
	}

	/* ---- Attributes → additionalProperty (Property-Value pairs).
	 *      Strong GEO signal: lets engines answer "is it red / size L". */
	$props = array();
	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute instanceof WC_Product_Attribute ) continue;
		if ( ! $attribute->get_visible() ) continue;
		$name  = wc_attribute_label( $attribute->get_name() );
		$value = $attribute->is_taxonomy()
			? implode( ', ', wp_list_pluck( $attribute->get_terms(), 'name' ) )
			: implode( ', ', (array) $attribute->get_options() );
		if ( '' === $name || '' === $value ) continue;
		$props[] = array(
			'@type'               => 'PropertyValue',
			'propertyID'          => $name,
			'value'               => $value,
		);
	}
	if ( $props ) {
		$markup['additionalProperty'] = $props;
	}

	/* ---- Weight / dimensions (if the merchant filled them in) ---- */
	$units = apply_filters( 'woocommerce_weight_unit', get_option( 'woocommerce_weight_unit', 'kg' ) );
	if ( $product->has_weight() ) {
		$markup['weight'] = array(
			'@type'         => 'QuantitativeValue',
			'value'         => wc_format_decimal( $product->get_weight(), wc_get_weight_decimals() ),
			'unitCode'      => blogpro_wc_schema_unit_code( $units ),
			'unitText'      => $units,
		);
	}
	if ( $product->has_dimensions() ) {
		$dims  = $product->get_dimensions( false ); // array length,width,height
		$len_u = get_option( 'woocommerce_dimension_unit', 'cm' );
		if ( $dims ) {
			$markup['depth'] = array( '@type' => 'QuantitativeValue', 'value' => (float) $dims['length'], 'unitCode' => blogpro_wc_schema_unit_code( $len_u ), 'unitText' => $len_u );
			$markup['width'] = array( '@type' => 'QuantitativeValue', 'value' => (float) $dims['width'],  'unitCode' => blogpro_wc_schema_unit_code( $len_u ), 'unitText' => $len_u );
			$markup['height']= array( '@type' => 'QuantitativeValue', 'value' => (float) $dims['height'], 'unitCode' => blogpro_wc_schema_unit_code( $len_u ), 'unitText' => $len_u );
		}
	}

	/* ---- Specific GTIN fields (Google prefers gtin13 over gtin) ---- */
	$gtin = $product->get_global_unique_id();
	if ( $gtin ) {
		$len = strlen( $gtin );
		if ( in_array( $len, array( 8, 12, 13, 14 ), true ) ) {
			$markup[ 'gtin' . $len ] = $gtin;
		}
	}

	/* ---- MPN (manufacturer part number) ---- */
	$mpn = get_post_meta( $product->get_id(), '_mpn', true );
	if ( $mpn ) {
		$markup['mpn'] = $mpn;
	}

	/* ---- Condition (defaults to NewCondition) ---- */
	$condition = get_post_meta( $product->get_id(), '_condition', true );
	$markup['itemCondition'] = 'https://schema.org/' . ( $condition ? $condition : 'NewCondition' );

	/**
	 * Filter the enriched Product schema.
	 */
	return apply_filters( 'blogpro_wc_schema_product', $markup, $product );
}
add_filter( 'woocommerce_structured_data_product', 'blogpro_wc_schema_enrich_product', 20, 2 );

/* ===================================================================
 * 2. Enrich each Offer node.
 *    Core runs offers through woocommerce_structured_data_product_offer.
 *    We link the seller to the theme's Organization by @id (no dup),
 *    and attach shipping + return policy (Google Merchant requirements).
 * ================================================================= */
function blogpro_wc_schema_enrich_offer( $offer, $product ) {
	if ( ! $product instanceof WC_Product ) return $offer;
	if ( ! blogpro_wc_schema_enabled( 'offer' ) ) return $offer;

	/* ---- Seller → reference the theme's Organization node ---- */
	$offer['seller'] = array( '@id' => home_url( '/' ) . '#organization' );

	/* ---- Item condition ---- */
	if ( empty( $offer['itemCondition'] ) ) {
		$condition = get_post_meta( $product->get_id(), '_condition', true );
		$offer['itemCondition'] = 'https://schema.org/' . ( $condition ? $condition : 'NewCondition' );
	}

	/* ---- Shipping details ---- */
	$shipping = blogpro_wc_schema_shipping_details( $product );
	if ( $shipping ) {
		$offer['shippingDetails'] = $shipping;
	}

	/* ---- Merchant return policy ---- */
	$return = blogpro_wc_schema_return_policy( $product );
	if ( $return ) {
		$offer['hasMerchantReturnPolicy'] = $return;
	}

	/**
	 * Filter the enriched Offer schema.
	 */
	return apply_filters( 'blogpro_wc_schema_offer', $offer, $product );
}
add_filter( 'woocommerce_structured_data_product_offer', 'blogpro_wc_schema_enrich_offer', 20, 2 );

/**
 * Build a ShippingRateSettings array from store settings.
 * Kept conservative: only emits when a store country is configured.
 *
 * @param WC_Product $product
 * @return array
 */
function blogpro_wc_schema_shipping_details( $product ) {
	$base = wc_get_base_location();
	$country = ! empty( $base['country'] ) ? $base['country'] : '';
	if ( ! $country ) return array();

	$currency = get_woocommerce_currency();

	$shipping = array(
		array(
			'@type'             => 'ShippingRateSettings',
			'shippingDestination' => array(
				'@type'           => 'DefinedRegion',
				'addressCountry'  => $country,
			),
			'shippingOriginAddress' => array(
				'@type'           => 'PostalAddress',
				'addressCountry'  => $country,
			),
			'shippingLabel'     => __( 'Standard shipping', 'blog-pro' ),
			'shippingRate'      => array(
				'@type'        => 'MonetaryAmount',
				'currency'     => $currency,
				'value'        => 0, // Free/unknown → 0; filter to set real rates.
			),
			'freeShippingThreshold' => array(
				'@type'        => 'MonetaryAmount',
				'currency'     => $currency,
				'value'        => 0,
			),
		),
	);

	/**
	 * Filter the shipping details attached to every product offer.
	 */
	return apply_filters( 'blogpro_wc_schema_shipping', $shipping, $product );
}

/**
 * Build a MerchantReturnPolicy if returns are enabled in WC settings.
 *
 * @param WC_Product $product
 * @return array
 */
function blogpro_wc_schema_return_policy( $product ) {
	// Only emit when the merchant turned on returns instructions.
	if ( 'yes' !== get_option( 'woocommerce_enable_returns_instructions', 'no' ) ) {
		return array();
	}

	$days   = absint( get_option( 'woocommerce_returns_within', 0 ) );
	$refund = get_option( 'woocommerce_returns_refund', '' );

	$policy = array(
		'@type'                 => 'MerchantReturnPolicy',
		'applicableCountry'     => ( function_exists( 'wc_get_base_location' ) ? ( wc_get_base_location()['country'] ?: 'US' ) : 'US' ),
		'returnPolicyCategory'  => 'https://schema.org/MerchantReturnFiniteReturnWindow',
		'merchantReturnDays'    => $days ? $days : 30,
		'returnMethod'          => 'https://schema.org/ReturnByMail',
		'returnFees'             => 'https://schema.org/FreeReturnFees',
	);
	if ( $refund ) {
		// WC option values: refund | store_credit | no_refund.
		$refund_map = array(
			'refund'       => 'FullRefund',
			'store_credit' => 'StoreRefund',
			'no_refund'    => 'NoRefund',
		);
		if ( isset( $refund_map[ $refund ] ) ) {
			$policy['refundType'] = 'https://schema.org/' . $refund_map[ $refund ];
		}
	}

	/**
	 * Filter the merchant return policy attached to every product offer.
	 */
	return apply_filters( 'blogpro_wc_schema_return_policy', $policy, $product );
}

/**
 * Map a WooCommerce unit label to a schema.org UN/CEFACT unitCode.
 *
 * @param string $unit e.g. kg, g, lb, cm, mm, in.
 * @return string
 */
function blogpro_wc_schema_unit_code( $unit ) {
	$map = array(
		'kg' => 'KGM', 'g' => 'GRM', 'mg' => 'MGM', 'lb' => 'LBR', 'oz' => 'ONZ',
		'm'  => 'MTR', 'cm' => 'CMT', 'mm' => 'MMT', 'in' => 'INH', 'ft' => 'FUT', 'yd' => 'YRD',
	);
	return isset( $map[ strtolower( $unit ) ] ) ? $map[ strtolower( $unit ) ] : $unit;
}

/* ===================================================================
 * 3. Product page — WebPage glue + FAQPage.
 *    Printed in wp_head (before core's footer Product node) so the
 *    page entity is discoverable early. mainEntity / breadcrumb are
 *    @id references into core's nodes — no duplication.
 * ================================================================= */
function blogpro_wc_schema_product_page() {
	if ( ! is_product() ) return;

	$product = wc_get_product( get_the_ID() );
	if ( ! $product ) return;

	$permalink = get_permalink( $product->get_id() );
	$graph     = array();

	/* ---- WebPage (the page entity) ---- */
	if ( blogpro_wc_schema_enabled( 'webpage' ) ) {
		$webpage = array(
			'@type'           => 'WebPage',
			'@id'             => $permalink . '#webpage',
			'url'             => $permalink,
			'name'            => wp_strip_all_tags( wp_get_document_title() ),
			'isPartOf'        => array( '@id' => home_url( '/' ) . '#website' ),
			'about'           => array( '@id' => $permalink . '#product' ),
			'mainEntity'      => array( '@id' => $permalink . '#product' ),
			'breadcrumb'      => array( '@id' => $permalink . '#breadcrumb' ),
			'inLanguage'      => get_bloginfo( 'language' ),
			'dateModified'    => get_the_modified_date( 'c', $product->get_id() ),
		);
		$desc = $product->get_short_description() ? $product->get_short_description() : $product->get_description();
		if ( $desc ) {
			$webpage['description'] = wp_strip_all_tags( do_shortcode( $desc ) );
		}
		$graph[] = $webpage;
	}

	/* ---- FAQPage from attributes + filterable Q&A ---- */
	if ( blogpro_wc_schema_enabled( 'faq' ) ) {
		$faq = blogpro_wc_schema_product_faq( $product );
		if ( $faq ) {
			$graph[] = array(
				'@type'           => 'FAQPage',
				'@id'             => $permalink . '#faq',
				'mainEntity'      => $faq,
				'isPartOf'        => array( '@id' => $permalink . '#webpage' ),
			);
		}
	}

	if ( ! $graph ) return;

	blogpro_wc_schema_print( $graph );
}
add_action( 'wp_head', 'blogpro_wc_schema_product_page', 20 );

/**
 * Build FAQ Q&A pairs from product attributes + shipping/return facts.
 * Only includes answers we can state truthfully from real data.
 *
 * @param WC_Product $product
 * @return array of Question nodes.
 */
function blogpro_wc_schema_product_faq( $product ) {
	$questions = array();

	// Attribute-derived Q&A.
	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_visible() ) continue;
		$name  = wc_attribute_label( $attribute->get_name() );
		$value = $attribute->is_taxonomy()
			? implode( ', ', wp_list_pluck( $attribute->get_terms(), 'name' ) )
			: implode( ', ', (array) $attribute->get_options() );
		if ( '' === $name || '' === $value ) continue;
		$questions[] = array(
			'@type'          => 'Question',
			'name'           => sprintf( /* translators: %s: attribute name */ __( 'What %s is this?', 'blog-pro' ), strtolower( $name ) ),
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => sprintf( /* translators: 1: attribute name, 2: value */ __( 'This product\'s %1$s is %2$s.', 'blog-pro' ), strtolower( $name ), $value ),
			),
		);
	}

	// Stock availability Q&A.
	if ( $product->managing_stock() ) {
		$questions[] = array(
			'@type'          => 'Question',
			'name'           => __( 'Is this product in stock?', 'blog-pro' ),
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $product->is_in_stock()
					? __( 'Yes, this product is currently in stock.', 'blog-pro' )
					: __( 'This product is currently out of stock.', 'blog-pro' ),
			),
		);
	}

	/**
	 * Filter the FAQ Q&A pairs for a product.
	 *
	 * @param array      $questions Array of Question schema nodes.
	 * @param WC_Product $product   The product.
	 */
	return apply_filters( 'blogpro_wc_schema_product_faq', $questions, $product );
}

/* ===================================================================
 * 4. Shop / category / tag — CollectionPage + ItemList.
 * ================================================================= */
function blogpro_wc_schema_archive() {
	if ( ! ( is_shop() || is_product_taxonomy() ) ) return;
	if ( ! blogpro_wc_schema_enabled( 'archive' ) ) return;

	$term = ( is_product_taxonomy() ) ? get_queried_object() : null;
	$name = ( $term instanceof WP_Term ) ? $term->name : wp_strip_all_tags( get_bloginfo( 'name' ) );
	$url  = ( $term instanceof WP_Term ) ? get_term_link( $term ) : wc_get_page_permalink( 'shop' );
	if ( is_wp_error( $url ) ) return;

	$collection = array(
		'@type'    => 'CollectionPage',
		'@id'      => $url . '#collection',
		'url'      => $url,
		'name'     => $name,
		'isPartOf' => array( '@id' => home_url( '/' ) . '#website' ),
		'breadcrumb' => array( '@id' => $url . '#breadcrumb' ),
	);
	if ( $term instanceof WP_Term && ! empty( $term->description ) ) {
		$collection['description'] = wp_strip_all_tags( $term->description );
	}

	$items = array();
	if ( have_posts() ) {
		$i = 1;
		while ( have_posts() ) {
			the_post();
			$p = wc_get_product( get_the_ID() );
			if ( ! $p ) continue;
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i++,
				'url'      => get_permalink(),
				'name'     => $p->get_name(),
			);
		}
		rewind_posts();
	}

	$graph = array( $collection );
	if ( $items ) {
		$graph[] = array(
			'@type'            => 'ItemList',
			'@id'              => $url . '#itemlist',
			'mainEntityOfPage' => array( '@id' => $url . '#collection' ),
			'numberOfItems'    => (int) ( wc_get_loop_prop( 'total' ) ? wc_get_loop_prop( 'total' ) : count( $items ) ),
			'itemListElement'  => $items,
		);
	}

	blogpro_wc_schema_print( $graph );
}
add_action( 'wp_head', 'blogpro_wc_schema_archive', 20 );

/* ===================================================================
 * 5. Organization enrichment (reference-only, merged by @id).
 *    Adds sameAs + contactPoint to the theme's Organization node.
 * ================================================================= */
function blogpro_wc_schema_organization() {
	if ( ! ( is_product() || is_shop() || is_product_taxonomy() ) ) return;
	if ( ! blogpro_wc_schema_enabled( 'organization' ) ) return;

	$org = array(
		'@type' => 'Organization',
		'@id'   => home_url( '/' ) . '#organization',
	);

	// Social profiles from the theme's social settings if present.
	$same_as = array();
	foreach ( array( 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'pinterest' ) as $network ) {
		$url = get_option( 'blogpro_' . $network . '_url', '' );
		if ( $url ) $same_as[] = esc_url( $url );
	}
	if ( $same_as ) {
		$org['sameAs'] = $same_as;
	}

	// Contact point from WooCommerce store address.
	$phone = get_option( 'woocommerce_phone_number', '' );
	$email = get_option( 'woocommerce_email', '' );
	if ( $phone || $email ) {
		$contact = array(
			'@type'             => 'ContactPoint',
			'contactType'       => 'customer support',
			'availableLanguage' => array( get_bloginfo( 'language' ) ),
		);
		if ( $phone ) $contact['telephone'] = $phone;
		if ( $email ) $contact['email']     = $email;
		$org['contactPoint'] = $contact;
	}

	// Only print if we actually added something beyond the bare @id.
	if ( count( $org ) <= 2 ) return;

	/**
	 * Filter the Organization reference node.
	 */
	$org = apply_filters( 'blogpro_wc_schema_organization', $org );

	blogpro_wc_schema_print( array( $org ) );
}
add_action( 'wp_head', 'blogpro_wc_schema_organization', 21 );

/* ===================================================================
 * Utility: print a JSON-LD @graph block.
 * ================================================================= */
if ( ! function_exists( 'blogpro_wc_schema_print' ) ) {
	/**
	 * Echo a <script type="application/ld+json"> @graph block.
	 *
	 * @param array $nodes Array of schema nodes.
	 */
	function blogpro_wc_schema_print( $nodes ) {
		if ( ! $nodes ) return;
		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
	}
}
