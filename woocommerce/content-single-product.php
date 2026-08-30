<?php
/**
 * Single Product Content
 *
 * Renders the product's <div id="product-N" class="product ...">
 * wrapper. Many WC extensions (addons, product tabs meta, custom
 * CSS) rely on this wrapper being present, so we always emit it.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

if ( ! $product ) return;
?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'product-single', $product ); ?>>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
        <!-- Product Gallery -->
        <div class="product-gallery relative">
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
    ?>
</div>
