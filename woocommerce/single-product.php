<?php
/**
 * Single Product Template
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

    <?php while ( have_posts() ) : the_post(); ?>

        <?php wc_get_template_part( 'content', 'single-product' ); ?>

    <?php endwhile; // end of the loop. ?>

    <?php
    /**
     * woocommerce_after_main_content hook
     */
    do_action( 'woocommerce_after_main_content' );
    ?>
</div>

<?php get_footer();