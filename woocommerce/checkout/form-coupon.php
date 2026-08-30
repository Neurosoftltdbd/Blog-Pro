<?php
/**
 * Checkout coupon form — Tailwind styling.
 *
 * The .showcoupon link + #woocommerce-checkout-form-coupon ids are kept —
 * WC's checkout JS toggles the form's visibility through them.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! wc_coupons_enabled() ) return;
?>
<div class="woocommerce-form-coupon-toggle">
	<?php
		wc_print_notice( apply_filters( 'woocommerce_checkout_coupon_message', esc_html__( 'Have a coupon?', 'woocommerce' ) . ' <a href="#" role="button" aria-label="' . esc_attr__( 'Enter your coupon code', 'woocommerce' ) . '" aria-controls="woocommerce-checkout-form-coupon" aria-expanded="false" class="showcoupon underline font-medium">' . esc_html__( 'Click here to enter your code', 'woocommerce' ) . '</a>' ), 'notice' );
	?>
</div>

<form class="checkout_coupon woocommerce-form-coupon bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6 mb-5" method="post" style="display:none" id="woocommerce-checkout-form-coupon">

	<p class="form-row form-row-first">
		<label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Coupon:', 'woocommerce' ); ?></label>
		<input type="text" name="coupon_code" class="input-text block w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none transition-colors" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" id="coupon_code" value="" />
	</p>

	<p class="form-row form-row-last">
		<button type="submit" class="button inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Apply coupon', 'woocommerce' ); ?></button>
	</p>

	<div class="clear"></div>
</form>
