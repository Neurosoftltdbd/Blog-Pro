<?php
/**
 * Product Card (Loop)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

// Ensure visibility
if ( empty( $product ) || ! $product->is_visible() ) return;
?>

<li class="product type-product product-card bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition-shadow duration-300 flex flex-col">
    <?php
    /**
     * woocommerce_before_shop_loop_item_title hook
     */
    do_action( 'woocommerce_before_shop_loop_item_title' );
    ?>

    <div class="product-image-wrapper relative">
        <a href="<?php the_permalink(); ?>">
            <?php
            // Product thumbnail
            $image_size = 'blogpro-card';
            if ( has_post_thumbnail() ) {
                echo wp_get_attachment_image( get_post_thumbnail_id(), $image_size, false, [
                    'class' => 'w-full h-56 object-cover',
                    'loading' => 'lazy',
                    'alt' => get_the_title(),
                ] );
            } else {
                echo wc_placeholder_img( $image_size );
            }
            ?>
        </a>

        <?php
        // Sale badge
        if ( $product->is_on_sale() ) {
            echo '<span class="absolute top-3 left-3 bg-red-500 text-white text-xs font-semibold px-2 py-1 rounded">' . esc_html__( 'Sale', 'blog-pro' ) . '</span>';
        }
        ?>
    </div>

    <div class="product-content p-4 flex flex-col flex-grow">
        <div class="product-meta mb-2">
            <?php
            /**
             * woocommerce_shop_loop_item_title hook
             */
            do_action( 'woocommerce_shop_loop_item_title' );
            ?>
        </div>

        <div class="product-price-wrapper mb-3">
            <?php
            /**
             * woocommerce_after_shop_loop_item_title hook
             */
            do_action( 'woocommerce_after_shop_loop_item_title' );
            ?>
        </div>

        <div class="product-actions mt-auto pt-3 border-t border-gray-100">
            <?php
            /**
             * woocommerce_after_shop_loop_item hook
             */
            do_action( 'woocommerce_after_shop_loop_item' );
            ?>
        </div>
    </div>
</li>