<?php
/**
 * Single Product Content
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

if ( ! $product ) return;
?>

<div class="product-single grid grid-cols-1 md:grid-cols-2 gap-12">
    <!-- Product Gallery -->
    <div class="product-gallery">
        <?php
        /**
         * woocommerce_before_single_product_summary hook
         */
        do_action( 'woocommerce_before_single_product_summary' );
        ?>
    </div>

    <!-- Product Summary -->
    <div class="product-summary">
        <div class="product-header mb-6">
            <?php
            /**
             * woocommerce_single_product_summary hook
             */
            do_action( 'woocommerce_single_product_summary' );
            ?>
        </div>
    </div>
</div>

<?php
/**
 * woocommerce_after_single_product_summary hook
 */
do_action( 'woocommerce_after_single_product_summary' );