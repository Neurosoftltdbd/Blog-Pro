<?php
/**
 * Shop / Category / Archive Product Template
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header(); ?>

<div class="w-full max-w-7xl mx-auto px-4 md:px-0 py-8">
    <?php
    /**
     * woocommerce_before_main_content hook
     */
    do_action( 'woocommerce_before_main_content' );
    ?>

    <header class="woocommerce-products-header mb-8">
        <?php if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>
            <h1 class="woocommerce-products-header__title page-title text-3xl font-bold tracking-tighter text-indigo-600 mb-4">
                <?php woocommerce_page_title(); ?>
            </h1>
        <?php endif; ?>

        <?php
        /**
         * woocommerce_archive_description hook
         */
        do_action( 'woocommerce_archive_description' );
        ?>
    </header>

    <?php
    if ( woocommerce_product_loop() ) {
        /**
         * woocommerce_before_shop_loop hook
         */
        do_action( 'woocommerce_before_shop_loop' );

        woocommerce_product_loop_start();

        if ( wc_get_loop_prop( 'total' ) ) {
            while ( have_posts() ) {
                the_post();
                wc_get_template_part( 'content', 'product' );
            }
        }

        woocommerce_product_loop_end();

        /**
         * woocommerce_after_shop_loop hook
         */
        do_action( 'woocommerce_after_shop_loop' );
    } else {
        /**
         * woocommerce_no_products_found hook
         */
        do_action( 'woocommerce_no_products_found' );
    }

    /**
     * woocommerce_after_main_content hook
     */
    do_action( 'woocommerce_after_main_content' );
    ?>

</div>

<?php get_footer();