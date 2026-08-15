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
 * ------------------------------------------------------------- */
function blogpro_wcom_setup() {
    add_theme_support( 'woocommerce', [
        'thumbnail_image_width' => 400,
        'single_image_width'    => 800,
    ] );
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );
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
 * 4. Wrap WooCommerce content with our container
 * ------------------------------------------------------------- */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

function blogpro_wcom_wrapper_before() {
    echo '<div class="w-full max-w-7xl mx-auto px-4 md:px-0 py-8">';
}
function blogpro_wcom_wrapper_after() {
    echo '</div>';
}
add_action( 'woocommerce_before_main_content', 'blogpro_wcom_wrapper_before' );
add_action( 'woocommerce_after_main_content', 'blogpro_wcom_wrapper_after' );

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
