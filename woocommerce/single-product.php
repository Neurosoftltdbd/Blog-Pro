<?php
/**
 * Single Product Template
 *
 * NOTE: The page wrapper (<div class="w-full max-w-7xl ...">) is
 * emitted by wcom-support.php via blogpro_wcom_wrapper_before/after
 * on the woocommerce_before_main_content / _after_main_content
 * hooks. Do not duplicate it here.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

/**
 * woocommerce_before_main_content hook
 * (emits the theme wrapper open + WC breadcrumb)
 */
do_action( 'woocommerce_before_main_content' );

while ( have_posts() ) :
    the_post();
    wc_get_template_part( 'content', 'single-product' );
endwhile; // end of the loop.

/**
 * woocommerce_after_main_content hook
 * (closes the theme wrapper)
 */
do_action( 'woocommerce_after_main_content' );

get_footer();
