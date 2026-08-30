<?php
/**
 * Variable product single add-to-cart button — Tailwind styling.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

?>
<div class="woocommerce-variation-add-to-cart variations_button flex flex-wrap items-center gap-3 mt-3">
	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

	<?php do_action( 'woocommerce_before_add_to_cart_quantity' ); ?>

	<?php woocommerce_quantity_input(
		array(
			'min_value'   => $product->get_min_purchase_quantity(),
			'max_value'   => $product->get_max_purchase_quantity(),
			'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(), // WPCS: CSRF ok, input var ok.
		)
	); ?>

	<?php do_action( 'woocommerce_after_add_to_cart_quantity' ); ?>

	<button type="submit" class="single_add_to_cart_button button alt inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>

	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

	<input type="hidden" name="add-to-cart" value="<?php echo absint( $product->get_id() ); ?>" />
	<input type="hidden" name="product_id" value="<?php echo absint( $product->get_id() ); ?>" />
	<input type="hidden" name="variation_id" class="variation_id" value="0" />
</div>
