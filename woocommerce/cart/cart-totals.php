<?php
/**
 * Cart totals — Tailwind styling.
 *
 * Rendered inside <div class="cart-collaterals"> from cart.php. The
 * `woocommerce_cart_total` hooks fire through wc_cart_totals_*_html()
 * helpers, so the calculation markup is unchanged — only the table
 * chrome gets Tailwind.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

?>
<div class="cart_totals <?php echo ( WC()->customer->has_calculated_shipping() ) ? 'calculated_shipping' : ''; ?> bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6">

	<?php do_action( 'woocommerce_before_cart_totals' ); ?>

	<h2 class="text-lg font-semibold text-gray-900 mb-4"><?php esc_html_e( 'Cart totals', 'woocommerce' ); ?></h2>

	<table cellspacing="0" class="shop_table shop_table_responsive w-full text-sm">
		<tr class="cart-subtotal border-b border-gray-100">
			<th class="py-2.5 font-medium text-gray-700 text-left"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
			<td class="py-2.5 text-gray-900 text-right" data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>"><?php wc_cart_totals_subtotal_html(); ?></td>
		</tr>

		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?> border-b border-gray-100">
				<th class="py-2.5 font-medium text-gray-700 text-left"><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
				<td class="py-2.5 text-emerald-600 text-right" data-title="<?php echo esc_attr( wc_cart_totals_coupon_label( $coupon, false ) ); ?>"><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>

			<?php do_action( 'woocommerce_cart_totals_before_shipping' ); ?>

			<?php wc_cart_totals_shipping_html(); ?>

			<?php do_action( 'woocommerce_cart_totals_after_shipping' ); ?>

		<?php elseif ( WC()->cart->needs_shipping() && 'yes' === get_option( 'woocommerce_enable_shipping_calc' ) ) : ?>

			<tr class="shipping border-b border-gray-100">
				<th class="py-2.5 font-medium text-gray-700 text-left"><?php esc_html_e( 'Shipping', 'woocommerce' ); ?></th>
				<td class="py-2.5 text-gray-900 text-right" data-title="<?php esc_attr_e( 'Shipping', 'woocommerce' ); ?>"><?php woocommerce_shipping_calculator(); ?></td>
			</tr>

		<?php endif; ?>

		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<tr class="fee border-b border-gray-100">
				<th class="py-2.5 font-medium text-gray-700 text-left"><?php echo esc_html( $fee->name ); ?></th>
				<td class="py-2.5 text-gray-900 text-right" data-title="<?php echo esc_attr( $fee->name ); ?>"><?php wc_cart_totals_fee_html( $fee ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php
		if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) {
			$taxable_address = WC()->customer->get_taxable_address();
			$estimated_text  = '';

			if ( WC()->customer->is_customer_outside_base() && ! WC()->customer->has_calculated_shipping() ) {
				/* translators: %s location. */
				$estimated_text = sprintf( ' <small>' . esc_html__( '(estimated for %s)', 'woocommerce' ) . '</small>', WC()->countries->estimated_for_prefix( $taxable_address[0] ) . WC()->countries->countries[ $taxable_address[0] ] );
			}

			if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
				foreach ( WC()->cart->get_tax_totals() as $code => $tax ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					?>
					<tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?> border-b border-gray-100">
						<th class="py-2.5 font-medium text-gray-700 text-left"><?php echo esc_html( $tax->label ) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
						<td class="py-2.5 text-gray-900 text-right" data-title="<?php echo esc_attr( $tax->label ); ?>"><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
					</tr>
					<?php
				}
			} else {
				?>
				<tr class="tax-total border-b border-gray-100">
					<th class="py-2.5 font-medium text-gray-700 text-left"><?php echo esc_html( WC()->countries->tax_or_vat() ) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<td class="py-2.5 text-gray-900 text-right" data-title="<?php echo esc_attr( WC()->countries->tax_or_vat() ); ?>"><?php wc_cart_totals_taxes_total_html(); ?></td>
				</tr>
				<?php
			}
		}
		?>

		<?php do_action( 'woocommerce_cart_totals_before_order_total' ); ?>

		<tr class="order-total border-t border-gray-200">
			<th class="py-3 font-bold text-gray-900 text-left"><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
			<td class="py-3 font-bold text-gray-900 text-right" data-title="<?php esc_attr_e( 'Total', 'woocommerce' ); ?>"><?php wc_cart_totals_order_total_html(); ?></td>
		</tr>

		<?php do_action( 'woocommerce_cart_totals_after_order_total' ); ?>

	</table>

	<div class="wc-proceed-to-checkout mt-5">
		<?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
	</div>

	<?php do_action( 'woocommerce_after_cart_totals' ); ?>

</div>
