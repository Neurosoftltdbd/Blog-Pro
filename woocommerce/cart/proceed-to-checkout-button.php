<?php
/**
 * Proceed to checkout button — Tailwind styling.
 *
 * Rendered by woocommerce_button_proceed_to_checkout() from the
 * woocommerce_proceed_to_checkout hook (priority 20).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="checkout-button button alt wc-forward inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-6 py-3.5 text-base font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
	<?php esc_html_e( 'Proceed to checkout', 'woocommerce' ); ?>
</a>
