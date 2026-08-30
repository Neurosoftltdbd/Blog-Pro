<?php
/**
 * Checkout terms and conditions area — Tailwind styling.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( apply_filters( 'woocommerce_checkout_show_terms', true ) && function_exists( 'wc_terms_and_conditions_checkbox_enabled' ) ) {
	do_action( 'woocommerce_checkout_before_terms_and_conditions' );

	?>
	<div class="woocommerce-terms-and-conditions-wrapper text-sm text-gray-500 mb-4">
		<?php
		/**
		 * Terms and conditions hook used to inject content.
		 *
		 * @since 3.4.0.
		 * @hooked wc_checkout_privacy_policy_text() Shows custom privacy policy text. Priority 20.
		 * @hooked wc_terms_and_conditions_page_content() Shows t&c page content. Priority 30.
		 */
		do_action( 'woocommerce_checkout_terms_and_conditions' );
		?>

		<?php if ( wc_terms_and_conditions_checkbox_enabled() ) : ?>
			<p class="form-row validate-required">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox flex items-start gap-2 text-sm text-gray-700 cursor-pointer select-none">
				<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" name="terms" <?php checked( apply_filters( 'woocommerce_terms_is_checked_default', isset( $_POST['terms'] ) ), true ); // WPCS: input var ok, csrf ok. ?> id="terms" />
					<span class="woocommerce-terms-and-conditions-checkbox-text"><?php wc_terms_and_conditions_checkbox_text(); ?></span>&nbsp;<abbr class="required text-red-500 no-underline" title="<?php esc_attr_e( 'required', 'woocommerce' ); ?>">*</abbr>
				</label>
				<input type="hidden" name="terms-field" value="1" />
			</p>
		<?php endif; ?>
	</div>
	<?php

	do_action( 'woocommerce_checkout_after_terms_and_conditions' );
}
