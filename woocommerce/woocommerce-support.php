<?php
/**
 * WooCommerce Support
 * Loads only when WooCommerce plugin is active.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Exit if WooCommerce is not active
if ( ! class_exists( 'WooCommerce' ) ) return;

/* ---------------------------------------------------------------
 * 1. Declare WooCommerce theme support
 *
 * Gallery note: flexslider / photoswipe are NOT enabled. Their JS
 * restructures the gallery DOM and their CSS is dequeued below, so
 * enabling them leaves the gallery collapsed. The theme provides a
 * lightweight vanilla-JS gallery instead (section 22c) with the same
 * classes, so zoom extensions that check theme support still work.
 * ------------------------------------------------------------- */
function blogpro_wcom_setup() {
    add_theme_support( 'woocommerce' );
}
add_action( 'after_setup_theme', 'blogpro_wcom_setup' );

/* ---------------------------------------------------------------
 * 2. Remove WooCommerce default styles (use Tailwind instead)
 * ------------------------------------------------------------- */
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

/* ---------------------------------------------------------------
 * 3. Dequeue WC scripts on non-shop pages (performance)
 * ------------------------------------------------------------- */
function blogpro_wcom_dequeue_scripts() {
    if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) return;
    wp_dequeue_script( 'wc-cart-fragments' );
}
add_action( 'wp_enqueue_scripts', 'blogpro_wcom_dequeue_scripts', 100 );

/* ---------------------------------------------------------------
 * 3b. SEO + GEO structured data (JSON-LD). Enriches WooCommerce's
 *     own Product/Offer/Breadcrumb nodes and adds WebPage, FAQPage,
 *     CollectionPage/ItemList and Organization references.
 * ------------------------------------------------------------- */
require_once BLOGPRO_DIR . '/woocommerce/wc-schema-markup.php';

/* ---------------------------------------------------------------
 * 4. Wrap WooCommerce content with our container
 *    Defer remove_action until after_setup_theme priority 11 so it
 *    runs AFTER WC's template-hooks registration (which is also
 *    bound to after_setup_theme at the default priority 10).
 * ------------------------------------------------------------- */
function blogpro_wcom_wrappers() {
    remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
    remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
    remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
    remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
    remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
    add_action( 'woocommerce_before_main_content', 'blogpro_wcom_wrapper_before' );
    add_action( 'woocommerce_after_main_content', 'blogpro_wcom_wrapper_after' );
}
add_action( 'after_setup_theme', 'blogpro_wcom_wrappers', 11 );

function blogpro_wcom_wrapper_before() {
    echo '<div class="w-full max-w-7xl mx-auto px-4 md:px-0 py-8">';
}
function blogpro_wcom_wrapper_after() {
    echo '</div>';
}

/* ---------------------------------------------------------------
 * 5. Cart icon in header
 * ------------------------------------------------------------- */
function blogpro_wcom_cart_icon() {
    if ( ! WC()->cart ) return;
    $count = WC()->cart->get_cart_contents_count();
    $url   = wc_get_cart_url();
    ?>
    <a href="<?php echo esc_url( $url ); ?>" class="relative text-gray-600 hover:text-indigo-600 transition-colors blogpro-cart-icon" aria-label="<?php esc_attr_e( 'View cart', 'blog-pro' ); ?>">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        <?php if ( $count > 0 ) : ?>
            <span class="absolute -top-2 -right-2 bg-indigo-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                <?php echo esc_html( $count ); ?>
            </span>
        <?php endif; ?>
    </a>
    <?php
}

/* ---------------------------------------------------------------
 * 6. Fragment refresh: update cart count when items added/removed
 * ------------------------------------------------------------- */
function blogpro_wcom_cart_fragments( $fragments ) {
    $count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    ob_start();
    ?>
    <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="relative text-gray-600 hover:text-indigo-600 transition-colors blogpro-cart-icon" aria-label="<?php esc_attr_e( 'View cart', 'blog-pro' ); ?>">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        <?php if ( $count > 0 ) : ?>
            <span class="absolute -top-2 -right-2 bg-indigo-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                <?php echo esc_html( $count ); ?>
            </span>
        <?php endif; ?>
    </a>
    <?php
    $fragments['.blogpro-cart-icon'] = ob_get_clean();
    return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'blogpro_wcom_cart_fragments' );

/* ---------------------------------------------------------------
 * 7. Form fields — Tailwind classes via the WC field-args filter
 * ------------------------------------------------------------- */
function blogpro_wcom_form_field_args( $args, $key, $value ) {
    $input_base = 'block w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none transition-colors';
    $select     = $input_base; // native arrow kept — select2 replaces country/state anyway
    $label      = 'block text-sm font-medium text-gray-700 mb-1.5';

    if ( ! is_array( $args['class'] ) )       $args['class']       = (array) $args['class'];
    if ( ! is_array( $args['label_class'] ) ) $args['label_class'] = (array) $args['label_class'];
    if ( ! is_array( $args['input_class'] ) ) $args['input_class'] = (array) $args['input_class'];

    switch ( $args['type'] ) {
        case 'country':
        case 'state':
            $args['input_class'][] = $select;
            break;
        case 'textarea':
            $args['input_class'][] = $input_base;
            break;
        case 'checkbox':
            // The label IS the wrapper for checkboxes — flex layout only.
            $args['label_class'][] = 'flex items-start gap-2 text-sm text-gray-700 cursor-pointer select-none';
            $args['input_class'][] = 'mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500';
            break;
        case 'select':
            $args['input_class'][] = $select;
            break;
        default:
            // text, tel, email, password, number …
            $args['input_class'][] = $input_base;
            break;
    }

    // Wrap-row class — kept as form-row (plugins + select2) but styled
    // as a vertical stack with consistent spacing.
    $args['class'][] = 'blogpro-field-row block mb-4';
    if ( 'checkbox' !== $args['type'] ) {
        $args['label_class'][] = $label;
    }

    return $args;
}
add_filter( 'woocommerce_form_field_args', 'blogpro_wcom_form_field_args', 10, 3 );

/* ---------------------------------------------------------------
 * 8. Quantity inputs — Tailwind classes on the stock <input class="qty">
 *    and the wrapping <div class="quantity">.
 * ------------------------------------------------------------- */
function blogpro_wcom_qty_classes( $classes ) {
    // Stock: input-text qty text — keep them, add Tailwind. This runs
    // for cart qty inputs too (no stepper there), so keep a clean base
    // and let the stepper template add the borderless variant.
    $classes[] = 'text-sm text-gray-900 bg-white text-center transition-colors';
    return array_values( array_unique( $classes ) );
}
add_filter( 'woocommerce_quantity_input_classes', 'blogpro_wcom_qty_classes' );

/* ---------------------------------------------------------------
 * 9. "Proceed to checkout" button — match Tailwind's primary button
 *    without forking the proceed-to-checkout-button template.
 * ------------------------------------------------------------- */
function blogpro_wcom_order_button_html( $html ) {
    $primary = 'inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-6 py-3.5 text-base font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2';
    return preg_replace( '/class="([^"]*button[^"]*)"/i', 'class="$1 ' . $primary . '"', $html );
}
add_filter( 'woocommerce_order_button_html', 'blogpro_wcom_order_button_html' );

/* ---------------------------------------------------------------
 * 10. Place-order button (in checkout/payment.php) — same treatment.
 * ------------------------------------------------------------- */
add_filter( 'woocommerce_pay_order_button_html', 'blogpro_wcom_order_button_html' );

/* Proceed-to-checkout anchor in cart/cart.php carries a different class
 * ("checkout-button button alt wc-forward") — we override the template
 * at /woocommerce/cart/proceed-to-checkout-button.php so the markup
 * uses Tailwind directly, no string surgery on stock HTML. */

/* ---------------------------------------------------------------
 * 11. Cart page buttons (update cart / apply coupon) are styled
 *     directly in the /woocommerce/cart/cart.php template override.
 * ------------------------------------------------------------- */

/* ---------------------------------------------------------------
 * 11b. Loop add-to-cart button — Tailwind via the args filter.
 *      Stock classes (button, product_type_simple, ajax_add_to_cart)
 *      are kept; WC's AJAX handler targets them.
 * ------------------------------------------------------------- */
function blogpro_wcom_loop_add_to_cart_args( $args ) {
    $args['class']   = trim( ( isset( $args['class'] ) ? $args['class'] : 'button' ) . ' blogpro-loop-atc inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 no-underline' );
    return $args;
}
add_filter( 'woocommerce_loop_add_to_cart_args', 'blogpro_wcom_loop_add_to_cart_args' );

/* Out-of-stock loop buttons: WC renders them with .disabled — swap to
 * a muted style so they don't look like a broken primary CTA. */
function blogpro_wcom_loop_add_to_cart_link( $html, $product ) {
    if ( $product && ! $product->is_in_stock() ) {
        $html = str_replace( 'bg-indigo-600', 'bg-gray-200 text-gray-500 pointer-events-none', $html );
        $html = str_replace( 'hover:bg-indigo-700', '', $html );
    }
    return $html;
}
add_filter( 'woocommerce_loop_add_to_cart_link', 'blogpro_wcom_loop_add_to_cart_link', 10, 2 );

/* ---------------------------------------------------------------
 * 11c. Shop catalog filters (archive-product.php filter panel).
 *      All GET-driven, server-side — no JS required:
 *       - per_page        → posts_per_page on the main query
 *       - filter_on_sale  → meta_query on _sale_price
 *       - filter_in_stock → tax_query on product_visibility
 *      (min_price / max_price are handled natively by WC core.)
 * ------------------------------------------------------------- */
function blogpro_wcom_product_query( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) return;
    if ( ! ( is_post_type_archive( 'product' ) || is_tax( array( 'product_cat', 'product_tag' ) ) || ( is_search() && 'product' === $query->get( 'post_type' ) ) ) ) return;

    /* WC core reads $_GET['min_price'] / $_GET['max_price'] with
     * floatval() — an EMPTY string becomes 0, which would filter the
     * whole catalogue out when only one bound was typed. Drop empty
     * price params before WC's own handling (this filter runs at the
     * default priority 10; WC's price clause reads $_GET in the same
     * pass, so sanitising here is early enough). */
    if ( isset( $_GET['min_price'] ) && '' === trim( (string) $_GET['min_price'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        unset( $_GET['min_price'], $_REQUEST['min_price'] );
    }
    if ( isset( $_GET['max_price'] ) && '' === trim( (string) $_GET['max_price'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        unset( $_GET['max_price'], $_REQUEST['max_price'] );
    }

    // Per page — clamp to sane bounds so a crafted URL can't ask for 9999.
    if ( isset( $_GET['per_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $per_page = absint( wp_unslash( $_GET['per_page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $per_page >= 1 && $per_page <= 60 ) {
            $query->set( 'posts_per_page', $per_page );
        }
    }

    // On sale only. wc_get_product_ids_on_sale() is WC's own source of
    // truth (covers variable products whose sale price lives on the
    // variations); a meta_query on _sale_price would miss those.
    if ( ! empty( $_GET['filter_on_sale'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $sale_ids = wc_get_product_ids_on_sale();
        $sale_ids = array_map( 'absint', (array) $sale_ids );
        if ( $sale_ids ) {
            $existing = (array) $query->get( 'post__in' );
            $existing = array_filter( array_map( 'absint', $existing ) );
            $query->set( 'post__in', $existing ? array_intersect( $existing, $sale_ids ) : $sale_ids );
        } else {
            $query->set( 'post__in', array( 0 ) ); // no sales at all → empty result
        }
    }

    // In stock only.
    if ( ! empty( $_GET['filter_in_stock'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tax_query = (array) $query->get( 'tax_query' );
        $tax_query[] = array(
            'taxonomy' => 'product_visibility',
            'field'    => 'name',
            'terms'    => 'outofstock',
            'operator' => 'NOT IN',
        );
        $query->set( 'tax_query', $tax_query );
    }
}
add_action( 'woocommerce_product_query', 'blogpro_wcom_product_query' );

/* ---------------------------------------------------------------
 * 12. Product loop start/end — the /woocommerce/loop/loop-start.php
 *     + loop-end.php template overrides replace the stock
 *     <ul class="products"> with the same 1→2→3→4 col Tailwind grid
 *     /shop/ uses, so related / up-sell / cross-sell sections match.
 * ------------------------------------------------------------- */

/* ---------------------------------------------------------------
 * 13. Payment-method radios — handled by the /woocommerce/checkout/
 *     payment-method.php template override (<li> gets Tailwind classes
 *     directly there; no string surgery on stock HTML needed).
 * ------------------------------------------------------------- */

/* ---------------------------------------------------------------
 * 14. Star-rating widget — stock HTML uses a fixed-width <span> over
 *     a star background. Re-render as inline SVG so it scales with
 *     Tailwind text size, and matches the indigo accent elsewhere.
 * ------------------------------------------------------------- */
function blogpro_wcom_get_rating_html( $html, $rating, $count ) {
    if ( 0 >= $rating ) return $html;

    $rating = max( 0, min( 5, (float) $rating ) );
    $pct    = ( $rating / 5 ) * 100;

    $stars = '';
    for ( $i = 1; $i <= 5; $i++ ) {
        $fill = $rating >= $i ? 'text-amber-400' : ( $rating >= $i - 0.5 ? 'text-amber-300' : 'text-gray-200' );
        $stars .= '<svg class="w-4 h-4 ' . $fill . '" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 00-.364 1.118l1.287 3.957c.299.921-.756 1.688-1.539 1.118l-3.366-2.446a1 1 0 00-1.176 0l-3.367 2.446c-.782.57-1.838-.197-1.539-1.118l1.287-3.957a1 1 0 00-.364-1.118L2.05 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.299-3.957z"/></svg>';
    }

    /* translators: %s: rating */
    $label = sprintf( esc_html__( 'Rated %s out of 5', 'woocommerce' ), $rating );
    $count_html = $count
        ? ' <span class="text-xs text-gray-500">(' . intval( $count ) . ')</span>'
        : '';

    return '<span class="inline-flex items-center gap-0.5" role="img" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '">' . $stars . $count_html . '</span>';
}
add_filter( 'woocommerce_product_get_rating_html', 'blogpro_wcom_get_rating_html', 10, 3 );

/* ---------------------------------------------------------------
 * 15. Stock product-loop image — single_add_to_cart etc. emit a <img>
 *     directly via $_product->get_image(). The media-optimize whole-
 *     page buffer pass rewrites it to /blogpro-img/. No filter needed.
 * ------------------------------------------------------------- */

/* ---------------------------------------------------------------
 * 16. Single-product gallery images — ID-based resizer, everywhere.
 *
 * Gallery markup is generated directly by template overrides:
 *   single-product/product-image.php
 *   single-product/product-thumbnails.php
 * Both call blogpro_responsive_img() with attachment IDs (WebP srcset).
 * No filter needed here.
 * ------------------------------------------------------------- */

/* ---------------------------------------------------------------
 * 17. Product tabs (description / reviews / additional) — the
 *     /woocommerce/single-product/tabs/tabs.php template override
 *     emits Tailwind pill styling on the <ul class="tabs wc-tabs">.
 * ------------------------------------------------------------- */

/* ---------------------------------------------------------------
 * 18. Stock "in stock" / "out of stock" badges — Tailwind classes
 *     live in the /woocommerce/single-product/stock.php override.
 * ------------------------------------------------------------- */

/* ---------------------------------------------------------------
 * 19. Order notes textarea + create-account checkbox on checkout.
 *     The textarea inherits .input-text from form_field_args above;
 *     the create-account field is built inline in form-billing.php
 *     (we override that template), so no global filter needed.
 * ------------------------------------------------------------- */

/* ---------------------------------------------------------------
 * 20. Empty cart / order received — handled via templates below.
 * ------------------------------------------------------------- */

/* ---------------------------------------------------------------
 * 21. Store-specific CSS that Tailwind utilities cannot express —
 *     the responsive <table> → stacked-card pattern for the cart /
 *     checkout tables and the WC select2 dropdown sizing. WC's own
 *     stylesheet was dequeued above presumably leaving these tables
 *     unscaled on phones, so this is the whole replacement.
 * ------------------------------------------------------------- */
function blogpro_wcom_inline_css() {
	$css = '
	@media (max-width: 767px) {
		.woocommerce table.shop_table_responsive thead { display: none; }
		.woocommerce table.shop_table_responsive tbody tr td {
			display: block;
			text-align: right !important;
			clear: both;
			padding-left: 45%;
			min-height: 2.5rem;
		}
		.woocommerce table.shop_table_responsive tbody tr td.product-name { text-align: left !important; padding-left: 0; }
		.woocommerce table.shop_table_responsive tbody tr td::before {
			content: attr(data-title);
			float: left;
			font-weight: 500;
			color: #4b5563;
		}
		.woocommerce table.shop_table_responsive tbody tr td.actions::before { content: none; }
		.woocommerce table.shop_table_responsive tbody tr td.actions { text-align: left !important; padding-left: 0; }
		.woocommerce table.shop_table_responsive tbody tr td {
			border-top: 1px solid #f3f4f6;
		}
		.woocommerce table.shop_table_responsive.wc-shipping-totals tbody tr td::before { float: none; display: block; }
	}
	.select2-container--default .select2-selection--single {
		height: 42px;
		border-radius: 0.5rem;
		border-color: #e5e7eb;
	}
	.select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 40px; color: #111827; }
	.select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
	.woocommerce-checkout-review-order-table tbody td.product-thumbnail { width: 80px; }

	/* Shop: notices inside the toolbar span the full row (they fire from
	   woocommerce_before_shop_loop at priority 10, inside the toolbar). */
	.shop-toolbar .woocommerce-message,
	.shop-toolbar .woocommerce-info,
	.shop-toolbar .woocommerce-error,
	.shop-toolbar .woocommerce-notices-wrapper {
		flex-basis: 100%;
		order: -1;
	}

	/* Shop: list view (toggled by #blogpro-shop-view buttons — no-JS
	   default is grid; the class only appears when JS adds it). */
	#blogpro-products.blogpro-view-list { grid-template-columns: 1fr !important; }
	#blogpro-products.blogpro-view-list > .product-card { flex-direction: row; }
	#blogpro-products.blogpro-view-list > .product-card .blogpro-card-media { width: 280px; max-width: 45%; aspect-ratio: 1/1; flex-shrink: 0; }
	#blogpro-products.blogpro-view-list > .product-card .blogpro-card-body { padding: 1.25rem; }
	@media (max-width: 640px) {
		#blogpro-products.blogpro-view-list > .product-card { flex-direction: column; }
		#blogpro-products.blogpro-view-list > .product-card .blogpro-card-media { width: 100%; max-width: 100%; aspect-ratio: 4/3; }
	}

	/* Shop: pagination pills (paginate_links renders <ul class="page-numbers">). */
	.woocommerce-pagination ul.page-numbers { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.375rem; list-style: none; margin: 0; padding: 0; }
	.woocommerce-pagination ul.page-numbers li { margin: 0; }
	.woocommerce-pagination .page-numbers { display: inline-flex; align-items: center; justify-content: center; min-width: 2.5rem; height: 2.5rem; padding: 0 0.625rem; border-radius: 0.5rem; border: 1px solid #e5e7eb; background: #fff; color: #374151; font-size: 0.875rem; font-weight: 500; text-decoration: none; transition: color .15s, border-color .15s, background-color .15s; }
	.woocommerce-pagination .page-numbers:hover { border-color: #a5b4fc; color: #4f46e5; }
	.woocommerce-pagination .page-numbers.current { background: #4f46e5; border-color: #4f46e5; color: #fff; }
	.woocommerce-pagination .page-numbers.dots { border: none; }

	/* Shop: category chips horizontal scroll affordance. */
	.blogpro-cat-chips { scrollbar-width: thin; }

	/* Shop: mobile filter drawer — the aside is off-canvas below md
	   until JS adds .blogpro-drawer-open (desktop: sticky column). */
	@media (max-width: 767px) {
		#shop-filters { box-shadow: 4px 0 24px rgba(0,0,0,.15); }
		#shop-filters.blogpro-drawer-open { transform: translateX(0); }
	}
	@media (min-width: 768px) {
		#shop-filters { transform: none !important; }
	}

	/* Shop: dual-thumb price slider — two stacked native ranges with a
	   transparent track; the coloured fill is drawn on the wrapper via
	   the --bp-fill-* custom properties the JS updates. */
	.blogpro-price-slider { --bp-fill-left: 0%; --bp-fill-right: 0%; }
	.blogpro-price-slider::before {
		content: "";
		position: absolute; left: 0; right: 0; top: 50%; height: 4px;
		transform: translateY(-50%);
		border-radius: 9999px;
		background: #e5e7eb;
	}
	.blogpro-price-slider::after {
		content: "";
		position: absolute; top: 50%; height: 4px;
		left: var(--bp-fill-left); right: var(--bp-fill-right);
		transform: translateY(-50%);
		border-radius: 9999px;
		background: #4f46e5;
	}
	.blogpro-range {
		position: absolute; left: 0; right: 0; top: 0; width: 100%; height: 100%;
		margin: 0;
		-webkit-appearance: none; appearance: none;
		background: transparent;
		pointer-events: none;
	}
	.blogpro-range::-webkit-slider-thumb {
		-webkit-appearance: none; appearance: none;
		pointer-events: auto;
		width: 16px; height: 16px; border-radius: 9999px;
		background: #fff; border: 2px solid #4f46e5;
		box-shadow: 0 1px 2px rgba(0,0,0,.15);
		cursor: pointer;
	}
	.blogpro-range::-moz-range-thumb {
		pointer-events: auto;
		width: 14px; height: 14px; border-radius: 9999px;
		background: #fff; border: 2px solid #4f46e5;
		box-shadow: 0 1px 2px rgba(0,0,0,.15);
		cursor: pointer;
	}
	.blogpro-range:focus-visible::-webkit-slider-thumb { outline: 2px solid #6366f1; outline-offset: 2px; }
	.blogpro-range:focus-visible::-moz-range-thumb { outline: 2px solid #6366f1; outline-offset: 2px; }

	/* Shop: <details> accordion chevron rotation. */
	details[open] > summary .details-open\:rotate-180 { transform: rotate(180deg); }

	/* Shop: hide number-input spinners for a cleaner filter row. */
	#blogpro-filter-form input[type=number]::-webkit-outer-spin-button,
	#blogpro-filter-form input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
	#blogpro-filter-form input[type=number] { -moz-appearance: textfield; appearance: textfield; }

	/* Single product: price del/ins (WC core stylesheet is dequeued). */
	.woocommerce div.product p.price del,
	.woocommerce div.product p.price ins { color: #9ca3af; font-weight: 400; text-decoration: line-through; }
	.woocommerce div.product p.price ins { background: transparent; text-decoration: none; color: inherit; }
	.woocommerce div.product p.price .woocommerce-Price-amount { color: inherit; }

	/* Single product: reviews tab. */
	.woocommerce .woocommerce-Reviews .commentlist { list-style: none; margin: 0; padding: 0; }
	.woocommerce .woocommerce-Reviews .comment_container .avatar { border-radius: 9999px; flex-shrink: 0; background: #f3f4f6; }
	.woocommerce .woocommerce-Reviews .comment-text .comment-content { margin-top: .375rem; color: #4b5563; font-size: .875rem; line-height: 1.65; }
	.woocommerce .woocommerce-Reviews .star-rating { margin-bottom: .25rem; }
	.woocommerce #review_form .form-submit .submit { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; border-radius: .5rem; background: #4f46e5; padding: .625rem 1.5rem; font-size: .875rem; font-weight: 600; color: #fff; border: none; cursor: pointer; transition: background-color .15s; }
	.woocommerce #review_form .form-submit .submit:hover { background: #4338ca; }

	/* Single product: gallery. Flexslider is NOT enabled (no CSS for
	   it) — the product-image.php template uses a Tailwind grid and a
	   small vanilla JS thumbnail-swap. Only the lightbox trigger button
	   (WC prepends it via JS when a plugin enables it) gets styled. */
	.woocommerce div.product .woocommerce-product-gallery .woocommerce-product-gallery__trigger { position: absolute; top: .75rem; right: .75rem; z-index: 9; display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: 9999px; background: rgba(255,255,255,.9); backdrop-filter: blur(4px); box-shadow: 0 1px 3px rgba(0,0,0,.12); text-decoration: none; font-size: 1rem; line-height: 1; }

	/* Single product: mobile sticky add-to-cart bar. Hidden off-canvas
	   by default; JS slides it in (.blogpro-sticky-show) once the real
	   add-to-cart form scrolls out of view. No-JS: stays hidden. */
	#blogpro-sticky-atc.blogpro-sticky-show { transform: translateY(0); }
	@media (min-width: 1024px) {
		#blogpro-sticky-atc { display: none; }
	}

	/* Single product: gallery thumb active ring. Tailwind cannot see
	   the JS-added "ring-2 ring-indigo-300" classes (it only scans
	   .php/.html), so the ring is declared here for the JS-added hooks. */
	.woocommerce-product-gallery__image.blogpro-thumb-active {
		box-shadow: 0 0 0 2px #fff, 0 0 0 4px #a5b4fc;
	}
	';
	// wp_add_inline_style nests the CSS before the </style> tag of the handle.
	if ( wp_style_is( 'blogpro-tailwind', 'enqueued' ) || wp_style_is( 'blogpro-tailwind', 'registered' ) ) {
		wp_add_inline_style( 'blogpro-tailwind', $css );
	}
}
add_action( 'wp_enqueue_scripts', 'blogpro_wcom_inline_css', 20 );

/* ---------------------------------------------------------------
 * 21b. Dedicated inline-JS handle.
 *
 * The theme's blogpro_script_attributes() filter (functions.php) runs
 * str_replace(' src', ' defer src') over the script_loader_tag of any
 * handle flagged 'defer'. blogpro-main IS flagged, so attaching our
 * inline JS to it corrupts every " srcset"/" src" inside the code
 * (→ JS SyntaxError → the whole IIFE dies). We register a separate
 * handle with NO defer data and attach the inline to that instead.
 * ------------------------------------------------------------- */
function blogpro_wcom_register_js_handle() {
    // false src = no file, inline only. Depends on blogpro-main so it
    // loads after the theme's main.js.
    wp_register_script( 'blogpro-wc', false, array( 'blogpro-main' ), BLOGPRO_VERSION ?? '1.0', true );
    wp_enqueue_script( 'blogpro-wc' );
}
add_action( 'wp_enqueue_scripts', 'blogpro_wcom_register_js_handle', 20 );

/* ---------------------------------------------------------------
 * 22. Shop-page JS — mobile filter drawer, price slider sync,
 *     grid/list view switch. Inlined (no extra HTTP request).
 *     Progressive enhancement: with JS off the sidebar is a normal
 *     column on desktop and the filter form still submits; the
 *     mobile drawer simply never opens (toggle hidden below md).
 * ------------------------------------------------------------- */
function blogpro_wcom_shop_js() {
	if ( ! is_shop() && ! is_product_taxonomy() && ! ( is_search() && 'product' === get_query_var( 'post_type' ) ) ) return;

	$js = '
	(function () {
		"use strict";
		var doc = document;

		/* ---- Mobile filter drawer --------------------------------- */
		var aside    = doc.getElementById( "shop-filters" );
		var overlay  = doc.getElementById( "blogpro-drawer-overlay" );
		var openBtn  = doc.querySelector( ".blogpro-filter-toggle" );
		var closeBtn = doc.querySelector( ".blogpro-drawer-close" );

		function drawer( open ) {
			if ( ! aside ) return;
			aside.classList.toggle( "blogpro-drawer-open", open );
			if ( overlay ) {
				overlay.classList.toggle( "opacity-100", open );
				overlay.classList.toggle( "pointer-events-auto", open );
				overlay.classList.toggle( "opacity-0", ! open );
				overlay.classList.toggle( "pointer-events-none", ! open );
			}
			if ( openBtn ) openBtn.setAttribute( "aria-expanded", open ? "true" : "false" );
			doc.body.classList.toggle( "overflow-hidden", open );
			if ( open ) {
				var first = aside.querySelector( "input, select, a, button" );
				if ( first ) first.focus();
			} else if ( openBtn ) {
				openBtn.focus();
			}
		}
		if ( openBtn && aside ) {
			openBtn.addEventListener( "click", function () { drawer( ! aside.classList.contains( "blogpro-drawer-open" ) ); });
		}
		if ( closeBtn ) closeBtn.addEventListener( "click", function () { drawer( false ); });
		if ( overlay )  overlay.addEventListener( "click", function () { drawer( false ); });
		doc.addEventListener( "keydown", function ( e ) {
			if ( "Escape" === e.key && aside && aside.classList.contains( "blogpro-drawer-open" ) ) drawer( false );
		});
		// Close the drawer after a filter form submit on mobile.
		var filterForm = doc.getElementById( "blogpro-filter-form" );
		if ( filterForm && aside ) {
			filterForm.addEventListener( "submit", function () {
				if ( window.matchMedia( "(max-width: 767px)" ).matches ) drawer( false );
			});
		}

		/* ---- Price slider <-> number inputs sync ------------------ */
		var rMin = doc.getElementById( "bp-range-min" );
		var rMax = doc.getElementById( "bp-range-max" );
		var nMin = doc.getElementById( "bp-min-input" );
		var nMax = doc.getElementById( "bp-max-input" );
		if ( rMin && rMax && nMin && nMax ) {
			var lo = parseFloat( rMin.min ), hi = parseFloat( rMax.max );
			var span = hi - lo || 1; // guard: single-price catalogues → hi === lo
			var syncFromRanges = function () {
				var a = parseFloat( rMin.value ), b = parseFloat( rMax.value );
				if ( a > b ) { var t = a; a = b; b = t; }
				nMin.value = a === lo ? "" : a;
				nMax.value = b === hi ? "" : b;
				var pctA = ( ( a - lo ) / span ) * 100;
				var pctB = ( ( b - lo ) / span ) * 100;
				rMin.parentElement.style.setProperty( "--bp-fill-left", pctA + "%" );
				rMin.parentElement.style.setProperty( "--bp-fill-right", ( 100 - pctB ) + "%" );
			};
			var syncFromNumbers = function () {
				var a = nMin.value === "" ? lo : parseFloat( nMin.value );
				var b = nMax.value === "" ? hi : parseFloat( nMax.value );
				if ( isNaN( a ) ) a = lo;
				if ( isNaN( b ) ) b = hi;
				rMin.value = a; rMax.value = b;
				syncFromRanges();
			};
			rMin.addEventListener( "input", syncFromRanges );
			rMax.addEventListener( "input", syncFromRanges );
			nMin.addEventListener( "input", syncFromNumbers );
			nMax.addEventListener( "input", syncFromNumbers );
			syncFromNumbers();
		}

		/* ---- Grid / list view switch ------------------------------ */
		var grid    = doc.getElementById( "blogpro-products" );
		var buttons = doc.querySelectorAll( ".blogpro-view-btn" );
		if ( grid && buttons.length ) {
			var current = "grid";
			try { current = localStorage.getItem( "blogpro-shop-view" ) || "grid"; } catch ( e ) {}
			var apply = function ( view ) {
				grid.classList.toggle( "blogpro-view-list", "list" === view );
				buttons.forEach( function ( b ) {
					var active = b.dataset.blogproView === view;
					b.setAttribute( "aria-pressed", active ? "true" : "false" );
					b.classList.toggle( "bg-indigo-50", active );
					b.classList.toggle( "text-indigo-600", active );
					b.classList.toggle( "text-gray-500", ! active );
				});
			};
			apply( current );
			buttons.forEach( function ( b ) {
				b.addEventListener( "click", function () {
					var view = b.dataset.blogproView;
					try { localStorage.setItem( "blogpro-shop-view", view ); } catch ( e ) {}
					apply( view );
				});
			});
		}

		/* ---- Live region: product count for screen readers -------- */
		var count = doc.querySelector( "#blogpro-live-count" );
		var items = doc.querySelectorAll( "#blogpro-products .product-card" );
		if ( count && items.length ) {
			count.textContent = items.length + ( items.length === 1 ? " product" : " products" ) + " shown";
		}
	})();
	';
	wp_add_inline_script( 'blogpro-wc', $js, 'after' );
}
add_action( 'wp_enqueue_scripts', 'blogpro_wcom_shop_js', 30 );

/* ---------------------------------------------------------------
 * 22b. Single-product JS — mobile sticky add-to-cart bar. Slides in
 *      once the real add-to-cart form scrolls above the viewport
 *      (IntersectionObserver, no scroll listeners). No-JS: bar stays
 *      off-canvas.
 * ------------------------------------------------------------- */
function blogpro_wcom_single_product_js() {
	if ( ! is_product() ) return;

	$js = '
	(function () {
		"use strict";
		var doc = document;

		/* ---- Quantity steppers (− / +) ---------------------------- */
		function step( input, dir ) {
			var min = parseFloat( input.min );
			var max = parseFloat( input.max );
			var st  = parseFloat( input.step ) || 1;
			var val = parseFloat( input.value );
			if ( isNaN( val ) ) val = isNaN( min ) ? st : min;
			val += dir * st;
			if ( ! isNaN( min ) && val < min ) val = min;
			if ( ! isNaN( max ) && max > 0 && val > max ) val = max;
			input.value = val;
			input.dispatchEvent( new Event( "change", { bubbles: true } ) );
		}
		doc.addEventListener( "click", function ( e ) {
			var btn = e.target.closest( ".blogpro-qty-minus, .blogpro-qty-plus" );
			if ( ! btn ) return;
			var wrap = btn.closest( ".blogpro-qty" );
			var input = wrap && wrap.querySelector( "input.qty" );
			if ( input && ! input.readOnly ) {
				step( input, btn.classList.contains( "blogpro-qty-minus" ) ? -1 : 1 );
			}
		});

		/* ---- Gallery: thumbnail → main image swap ----------------- */
		// Slide markup: <div class="woocommerce-product-gallery__image"
		//   data-large_image="FULL_URL" data-large_image_width="…">
		//   <a href="FULL_URL"><img …></a></div>
		// The thumb <img> ALREADY uses the resizer (/blogpro-img/) —
		// guaranteed to load. Swap src/srcset from it; data-large_image
		// (metadata URL) is only a fallback.
		var gallery = doc.querySelector( ".woocommerce-product-gallery" );
		if ( gallery ) {
			var mainSlide = gallery.querySelector( ".woocommerce-product-gallery__image:first-child" );
			var thumbSlides = gallery.querySelectorAll( ".woocommerce-product-gallery__wrapper .woocommerce-product-gallery__image" );

			// Largest URL in a srcset string (for the link + data attrs).
			var biggestSrc = function ( srcset, fallback ) {
				if ( ! srcset ) return fallback;
				var best = fallback, bestW = 0;
				srcset.split( "," ).forEach( function ( chunk ) {
					var pair = chunk.trim().split( " " );
					var w = parseInt( pair[1], 10 ) || 0;
					if ( w > bestW ) { bestW = w; best = pair[0]; }
				});
				return best;
			};

			thumbSlides.forEach( function ( slide, idx ) {
				if ( idx === 0 ) return; // first slide IS the main image

				slide.addEventListener( "click", function ( ev ) {
					ev.preventDefault();

					if ( ! mainSlide ) return;

					var tImg = slide.querySelector( "img" );
					var mImg = mainSlide.querySelector( "img" );
					var tSrcset = tImg && tImg.getAttribute( "srcset" );
					var tSrc = tImg ? tImg.src : "";
					var bigSrc = biggestSrc( tSrcset, tSrc );
					var tAlt = slide.getAttribute( "data-thumb-alt" ) || ( tImg && tImg.alt ) || "";

					if ( mImg && tImg ) {
						mImg.src = bigSrc || tSrc;
						if ( tSrcset ) {
							mImg.setAttribute( "srcset", tSrcset );
							if ( tImg.getAttribute( "sizes" ) ) mImg.setAttribute( "sizes", tImg.getAttribute( "sizes" ) );
						} else {
							mImg.removeAttribute( "srcset" );
							mImg.removeAttribute( "sizes" );
						}
						if ( tAlt ) mImg.alt = tAlt;
					}

					// Keep the anchor + zoom data in sync with the swap
					// (resizer URL, not metadata).
					var mainSlideLink = mainSlide.querySelector( "a" );
					if ( mainSlideLink && bigSrc ) mainSlideLink.setAttribute( "href", bigSrc );
					mainSlide.setAttribute( "data-large_image", bigSrc || "" );

					// Active ring on the selected thumb.
					thumbSlides.forEach( function ( t ) {
						t.classList.remove( "blogpro-thumb-active", "ring-2", "ring-indigo-300" );
					});
					slide.classList.add( "blogpro-thumb-active", "ring-2", "ring-indigo-300" );
				});
			});

			// Mark the first thumb active by default.
			if ( thumbSlides.length ) thumbSlides[0].classList.add( "blogpro-thumb-active", "ring-2", "ring-indigo-300" );
		}

		/* ---- Mobile sticky add-to-cart bar ------------------------ */
		var bar  = doc.getElementById( "blogpro-sticky-atc" );
		var form = doc.getElementById( "blogpro-atc-form" );
		if ( ! bar || ! form || ! ( "IntersectionObserver" in window ) ) return;

		// Only meaningful on mobile widths where the bar exists.
		var mq = window.matchMedia( "(max-width: 1023px)" );
		var io = new IntersectionObserver( function ( entries ) {
			var e = entries[0];
			var show = mq.matches && ! e.isIntersecting && e.boundingClientRect.top < 0;
			bar.classList.toggle( "blogpro-sticky-show", show );
			bar.setAttribute( "aria-hidden", show ? "false" : "true" );
		}, { threshold: 0 } );
		io.observe( form );

		// Hide the bar after a successful AJAX add-to-cart (the cart
		// fragment + notice already tell the user).
		doc.body.addEventListener( "added_to_cart", function () {
			bar.classList.remove( "blogpro-sticky-show" );
			bar.setAttribute( "aria-hidden", "true" );
		});

		/* ---- Sticky button: simple products add directly ---------- */
		var stickyBtn = doc.getElementById( "blogpro-sticky-atc-btn" );
		if ( stickyBtn && ! form.classList.contains( "variations_form" ) ) {
			stickyBtn.addEventListener( "click", function ( e ) {
				var real = form.querySelector( "button.single_add_to_cart_button, button[name=add-to-cart]" );
				if ( real ) {
					e.preventDefault();
					real.click(); // routes through the WC AJAX handler
				}
				// No button found → default anchor scroll to the form.
			});
		}
	})();
	';
	wp_add_inline_script( 'blogpro-wc', $js, 'after' );
}
add_action( 'wp_enqueue_scripts', 'blogpro_wcom_single_product_js', 30 );

