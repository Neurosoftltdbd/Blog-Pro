<?php
/**
 * Cart Template
 */
if ( ! defined( 'ABSPATH' ) ) exit;

wc_print_notices();

do_action( 'woocommerce_before_cart' ); ?>

<form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
    <?php do_action( 'woocommerce_before_cart_table' ); ?>

    <table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents w-full text-left" cellspacing="0">
        <thead>
            <tr class="border-b border-gray-200">
                <th class="pb-3 font-medium text-gray-700"><?php esc_html_e( 'Product', 'blog-pro' ); ?></th>
                <th class="pb-3 font-medium text-gray-700"><?php esc_html_e( 'Price', 'blog-pro' ); ?></th>
                <th class="pb-3 font-medium text-gray-700"><?php esc_html_e( 'Quantity', 'blog-pro' ); ?></th>
                <th class="pb-3 font-medium text-gray-700"><?php esc_html_e( 'Subtotal', 'blog-pro' ); ?></th>
                <th class="pb-3 font-medium text-gray-700">&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            <?php do_action( 'woocommerce_before_cart_contents' ); ?>

            <?php
            foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

                if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                    $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                    ?>
                    <tr class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
                        <td class="product-name py-4 border-b border-gray-100" data-title="<?php esc_attr_e( 'Product', 'woocommerce' ); ?>">
                            <div class="flex items-start gap-3">
                            <?php
                            /**
                             * Cart item thumbnail — uses the theme's WebP
                             * resizer via blogpro_responsive_img. The default
                             * $_product->get_image() returns a single-size
                             * WC image, which the media-opt's picture wrapper
                             * will serve as WebP but without a responsive
                             * srcset — so a 3G phone still downloads the
                             * full-size original. The resizer emits the full
                             * 320/480/768/1024/1280/1600 srcset that the
                             * sizes attribute below can pick from.
                             */
                            $thumb_id  = $_product->get_image_id();
                            $thumb_alt = $_product->get_name();
                            $thumbnail = $thumb_id
                                ? blogpro_responsive_img( $thumb_id, array(
                                    'class' => 'w-20 h-20 object-cover rounded-lg border border-gray-100',
                                    'alt'   => esc_attr( $thumb_alt ),
                                    'sizes' => '80px',
                                ) )
                                : wc_placeholder_img( 'woocommerce_thumbnail' );
                            $thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $thumbnail, $cart_item, $cart_item_key );

                            if ( $product_permalink ) {
                                printf( '<a href="%s" class="block shrink-0">%s</a>', esc_url( $product_permalink ), $thumbnail );
                            } else {
                                echo '<span class="block shrink-0">' . $thumbnail . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                            ?>
                            <div class="mt-2">
                            <?php
                            if ( $product_permalink ) {
                                printf( '<a href="%s" class="block font-medium text-indigo-600 hover:text-indigo-800">%s</a>', esc_url( $product_permalink ), $_product->get_name() );
                            } else {
                                echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) );
                            }

                            do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );

                            // Meta data
                            echo wc_get_formatted_cart_item_data( $cart_item );
                            ?>
                            </div>
                            </div>
                        </td>

                        <td class="product-price py-4 border-b border-gray-100" data-title="<?php esc_attr_e( 'Price', 'woocommerce' ); ?>">
                            <?php
                            echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key );
                            ?>
                        </td>

                        <td class="product-quantity py-4 border-b border-gray-100" data-title="<?php esc_attr_e( 'Quantity', 'woocommerce' ); ?>">
                            <?php
                            if ( $_product->is_sold_individually() ) {
                                $product_quantity = sprintf( '1 <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item_key );
                            } else {
                                $product_quantity = woocommerce_quantity_input( array(
                                    'input_name'  => "cart[{$cart_item_key}][qty]",
                                    'input_value' => $cart_item['quantity'],
                                    'max_value'   => $_product->get_max_purchase_quantity(),
                                    'min_value'   => '0',
                                    'product_name' => $_product->get_name(),
                                ), $_product, false );
                            }
                            echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item );
                            ?>
                        </td>

                        <td class="product-subtotal py-4 border-b border-gray-100" data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>">
                            <?php
                            echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key );
                            ?>
                        </td>

                        <td class="product-remove py-4 border-b border-gray-100" data-title="<?php esc_attr_e( 'Remove item', 'woocommerce' ); ?>">
                            <?php
                            echo apply_filters(
                                'woocommerce_cart_item_remove_link',
                                sprintf(
                                    '<a href="%s" class="text-red-500 hover:text-red-700" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
                                    esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                    esc_attr__( 'Remove this item', 'blog-pro' ),
                                    esc_attr( $product_id ),
                                    esc_attr( $_product->get_sku() )
                                ),
                                $cart_item_key
                            );
                            ?>
                        </td>
                    </tr>
                    <?php
                }
            }
            ?>

            <?php do_action( 'woocommerce_cart_contents' ); ?>

            <tr>
                <td colspan="5" class="actions py-4">
                    <?php if ( wc_coupons_enabled() ) { ?>
                        <div class="coupon inline-block mr-4">
                            <label for="coupon_code" class="sr-only"><?php esc_html_e( 'Coupon code', 'blog-pro' ); ?></label>
                            <input type="text" name="coupon_code" class="input-text px-4 py-2 border border-gray-200 rounded-lg" id="coupon_code" placeholder="<?php esc_attr_e( 'Coupon code', 'blog-pro' ); ?>" />
                            <button type="submit" class="button ml-2 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'blog-pro' ); ?>"><?php esc_html_e( 'Apply coupon', 'blog-pro' ); ?></button>
                            <?php do_action( 'woocommerce_cart_coupon' ); ?>
                        </div>
                    <?php } ?>

                    <button type="submit" class="button bg-gray-900 text-white px-6 py-2 rounded-lg hover:bg-gray-700" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'blog-pro' ); ?>"><?php esc_html_e( 'Update cart', 'blog-pro' ); ?></button>

                    <?php do_action( 'woocommerce_cart_actions' ); ?>

                    <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
                </td>
            </tr>

            <?php do_action( 'woocommerce_after_cart_contents' ); ?>
        </tbody>
    </table>

    <?php do_action( 'woocommerce_after_cart_table' ); ?>
</form>

<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>

<div class="cart-collaterals mt-12">
    <?php
    /**
     * woocommerce_cart_collaterals hook
     */
    do_action( 'woocommerce_cart_collaterals' );
    ?>
</div>

<?php do_action( 'woocommerce_after_cart' );