<?php
/**
 * Review order table — checkout order summary
 *
 * Adds a product image column (the stock template is text-only) using
 * the theme's blogpro_responsive_img() helper so the thumbnails are
 * served as WebP via the /blogpro-img/ resizer with a full responsive
 * srcset.
 *
 * NOTE: The page wrapper (<div class="w-full max-w-7xl ...">) is
 * emitted by wcom-support.php via blogpro_wcom_wrapper_before/after
 * on the woocommerce_before_main_content / _after_main_content
 * hooks. Do not duplicate it here.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<table class="shop_table shop_table_responsive woocommerce-checkout-review-order-table w-full text-left">
    <thead>
        <tr class="border-b border-gray-200">
            <th class="product-thumbnail py-3 text-sm font-medium text-gray-700" colspan="2"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
            <th class="product-total py-3 text-sm font-medium text-gray-700 text-right"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        do_action( 'woocommerce_review_order_before_cart_contents' );

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

            /**
             * Filter whether this cart item is visible in the checkout review order table.
             *
             * @since 2.1.0
             * @param bool   $visible       Whether the cart item is visible. Default true.
             * @param array  $cart_item     The cart item data.
             * @param string $cart_item_key The cart item key.
             */
            $visible = apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key );

            if ( $_product instanceof WC_Product && $_product->exists() && $cart_item['quantity'] > 0 && $visible ) {
                $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                ?>
                <tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?> border-b border-gray-100">
                    <td class="product-thumbnail py-4 w-20 align-top">
                        <?php
                        $thumb_id = $_product->get_image_id();
                        $thumb    = $thumb_id
                            ? blogpro_responsive_img( $thumb_id, array(
                                'class' => 'w-16 h-16 object-cover rounded-lg border border-gray-100',
                                'alt'   => esc_attr( $_product->get_name() ),
                                'sizes' => '64px',
                            ) )
                            : wc_placeholder_img( 'woocommerce_thumbnail' );
                        $thumb = apply_filters( 'woocommerce_cart_item_thumbnail', $thumb, $cart_item, $cart_item_key );
                        if ( $product_permalink ) {
                            printf( '<a href="%s" class="block">%s</a>', esc_url( $product_permalink ), $thumb );
                        } else {
                            echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        ?>
                    </td>
                    <td class="product-name py-4 align-top" data-title="<?php esc_attr_e( 'Product', 'woocommerce' ); ?>">
                        <?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ) . '&nbsp;'; ?>
                        <?php echo apply_filters( 'woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity text-gray-600 text-sm">' . sprintf( '&times;&nbsp;%s', $cart_item['quantity'] ) . '</strong>', $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </td>
                    <td class="product-total py-4 align-top text-right" data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>">
                        <?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </td>
                </tr>
                <?php
            }
        }

        do_action( 'woocommerce_review_order_after_cart_contents' );
        ?>
    </tbody>
    <tfoot>

        <tr class="cart-subtotal">
            <th colspan="2" class="py-2 text-sm font-medium text-gray-700"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
            <td class="py-2 text-right" data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>"><?php wc_cart_totals_subtotal_html(); ?></td>
        </tr>

        <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
            <tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
                <th colspan="2" class="py-2 text-sm font-medium text-gray-700"><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
                <td class="py-2 text-right" data-title="<?php esc_attr_e( 'Coupon', 'woocommerce' ); ?>"><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>

            <?php do_action( 'woocommerce_review_order_before_shipping' ); ?>

            <?php wc_cart_totals_shipping_html(); ?>

            <?php do_action( 'woocommerce_review_order_after_shipping' ); ?>

        <?php endif; ?>

        <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
            <tr class="fee">
                <th colspan="2" class="py-2 text-sm font-medium text-gray-700"><?php echo esc_html( $fee->name ); ?></th>
                <td class="py-2 text-right" data-title="<?php echo esc_attr( $fee->name ); ?>"><?php wc_cart_totals_fee_html( $fee ); ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
            <?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
                <?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
                    <tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
                        <th colspan="2" class="py-2 text-sm font-medium text-gray-700"><?php echo esc_html( $tax->label ); ?></th>
                        <td class="py-2 text-right"><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr class="tax-total">
                    <th colspan="2" class="py-2 text-sm font-medium text-gray-700"><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
                    <td class="py-2 text-right"><?php wc_cart_totals_taxes_total_html(); ?></td>
                </tr>
            <?php endif; ?>
        <?php endif; ?>

        <?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

        <tr class="order-total border-t border-gray-200">
            <th colspan="2" class="py-4 text-base font-bold text-gray-900"><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
            <td class="py-4 text-right text-base font-bold text-gray-900" data-title="<?php esc_attr_e( 'Total', 'woocommerce' ); ?>"><?php wc_cart_totals_order_total_html(); ?></td>
        </tr>

        <?php do_action( 'woocommerce_review_order_after_order_total' ); ?>

    </tfoot>
</table>
