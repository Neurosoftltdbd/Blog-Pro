<?php
/**
 * Checkout billing information form — Tailwind styling.
 *
 * Keeps WC's stock class names on the wrappers (.woocommerce-billing-fields
 * etc.); the fields inside are rendered by woocommerce_form_field(), which
 * already has Tailwind classes injected by blogpro_wcom_form_field_args().
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="woocommerce-billing-fields bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6">
	<?php if ( wc_ship_to_billing_address_only() && WC()->cart->needs_shipping() ) : ?>

		<h3 class="text-lg font-semibold text-gray-900 mb-4"><?php esc_html_e( 'Billing &amp; Shipping', 'woocommerce' ); ?></h3>

	<?php else : ?>

		<h3 class="text-lg font-semibold text-gray-900 mb-4"><?php esc_html_e( 'Billing details', 'woocommerce' ); ?></h3>

	<?php endif; ?>

	<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

	<div class="woocommerce-billing-fields__field-wrapper">
		<?php
		$fields = $checkout->get_checkout_fields( 'billing' );

		foreach ( $fields as $key => $field ) {
			woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
		}
		?>
	</div>

	<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
</div>

<?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
	<div class="woocommerce-account-fields bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6 mt-5">
		<?php if ( ! $checkout->is_registration_required() ) : ?>

			<p class="form-row form-row-wide create-account">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox flex items-start gap-2 text-sm text-gray-700 cursor-pointer select-none">
					<input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ); ?> type="checkbox" name="createaccount" value="1" /> <span><?php esc_html_e( 'Create an account?', 'woocommerce' ); ?></span>
				</label>
			</p>

		<?php endif; ?>

		<?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); ?>

		<?php if ( $checkout->get_checkout_fields( 'account' ) ) : ?>

			<div class="create-account">
				<?php foreach ( $checkout->get_checkout_fields( 'account' ) as $key => $field ) : ?>
					<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
				<?php endforeach; ?>
				<div class="clear"></div>
			</div>

		<?php endif; ?>

		<?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>
	</div>
<?php endif; ?>
