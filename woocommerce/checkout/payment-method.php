<?php
/**
 * Output a single payment method — Tailwind card styling.
 *
 * The radio keeps its stock name/value/data attributes — WC checkout JS
 * reads payment_method to toggle the .payment_box below.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<li class="wc_payment_method payment_method_<?php echo esc_attr( $gateway->id ); ?> block rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:border-indigo-300 has-checked:border-indigo-500 has-checked:bg-indigo-50/40">
	<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio mt-0.5 h-4 w-4 shrink-0 rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?> data-order_button_text="<?php echo esc_attr( $gateway->order_button_text ); ?>" />

	<label for="payment_method_<?php echo esc_attr( $gateway->id ); ?>" class="flex items-center gap-2.5 text-sm font-medium text-gray-900 cursor-pointer">
		<?php echo $gateway->get_title(); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?> <?php echo $gateway->get_icon(); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?>
	</label>
	<?php if ( $gateway->has_fields() || $gateway->get_description() ) : ?>
		<div class="payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?> mt-3 text-sm text-gray-600" <?php if ( ! $gateway->chosen ) : /* phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace */ ?>style="display:none;"<?php endif; /* phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace */ ?>>
			<?php $gateway->payment_fields(); ?>
		</div>
	<?php endif; ?>
</li>
