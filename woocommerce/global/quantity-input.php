<?php
/**
 * Product quantity input — Tailwind stepper.
 *
 * Renders a − [qty] + control. The .qty / .input-text classes stay on
 * the <input> (WC AJAX + plugins read them); the +/- buttons are wired
 * by the vanilla JS in wcom-support.php. Falls back to a plain number
 * input with no JS (buttons just do nothing).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* translators: %s: Quantity. */
$label = ! empty( $args['product_name'] ) ? sprintf( esc_html__( '%s quantity', 'woocommerce' ), wp_strip_all_tags( $args['product_name'] ) ) : esc_html__( 'Quantity', 'woocommerce' );

$bp_step_disabled = $readonly ? ' disabled' : '';
?>
<div class="quantity blogpro-qty inline-flex items-stretch border border-gray-200 rounded-lg overflow-hidden bg-white">
	<?php do_action( 'woocommerce_before_quantity_input_field' ); ?>
	<button type="button" class="blogpro-qty-minus w-10 border-0 bg-gray-50 text-gray-700 text-lg leading-none cursor-pointer transition-colors hover:bg-indigo-50 hover:text-indigo-600 active:bg-indigo-100" tabindex="-1" aria-hidden="true"<?php echo $bp_step_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>&minus;</button>
	<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_attr( $label ); ?></label>
	<input
		type="<?php echo esc_attr( $type ); ?>"
		<?php echo $readonly ? 'readonly="readonly"' : ''; ?>
		id="<?php echo esc_attr( $input_id ); ?>"
		class="<?php echo esc_attr( join( ' ', (array) $classes ) ); ?>"
		name="<?php echo esc_attr( $input_name ); ?>"
		value="<?php echo esc_attr( $input_value ); ?>"
		style="border: 0; width: 3.5rem; outline: none; -moz-appearance: textfield; appearance: textfield;"
		aria-label="<?php esc_attr_e( 'Product quantity', 'woocommerce' ); ?>"
		<?php if ( in_array( $type, array( 'text', 'search', 'tel', 'url', 'email', 'password' ), true ) ) : ?>
			size="4"
		<?php endif; ?>
		min="<?php echo esc_attr( $min_value ); ?>"
		<?php if ( 0 < $max_value ) : ?>
			max="<?php echo esc_attr( $max_value ); ?>"
		<?php endif; ?>
		<?php if ( ! $readonly ) : ?>
			step="<?php echo esc_attr( $step ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			inputmode="<?php echo esc_attr( $inputmode ); ?>"
			autocomplete="<?php echo esc_attr( isset( $autocomplete ) ? $autocomplete : 'on' ); ?>"
		<?php endif; ?>
	/>
	<button type="button" class="blogpro-qty-plus w-10 border-0 bg-gray-50 text-gray-700 text-lg leading-none cursor-pointer transition-colors hover:bg-indigo-50 hover:text-indigo-600 active:bg-indigo-100" tabindex="-1" aria-hidden="true"<?php echo $bp_step_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>+</button>
	<?php do_action( 'woocommerce_after_quantity_input_field' ); ?>
</div>
