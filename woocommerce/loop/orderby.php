<?php
/**
 * Shop order dropdown — Tailwind select with a chevron.
 *
 * Submitting this form reloads the page; the selected value is
 * preserved because the form includes all current query args
 * (via wc_query_string_form_fields). Keeping stock behavior means
 * no JS, and no AJAX-orderby breakage.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$id_suffix = wp_unique_id();

?>
<form class="woocommerce-ordering m-0" method="get">
	<?php if ( $use_label ) : ?>
		<label for="woocommerce-orderby-<?php echo esc_attr( $id_suffix ); ?>" class="sr-only"><?php echo esc_html__( 'Sort by', 'woocommerce' ); ?></label>
	<?php endif; ?>
	<div class="relative inline-flex items-center">
		<select
			name="orderby"
			class="orderby appearance-none rounded-lg border border-gray-200 bg-white py-2 pl-3.5 pr-9 text-sm text-gray-700 cursor-pointer focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none transition-colors"
			<?php if ( $use_label ) : ?>
				id="woocommerce-orderby-<?php echo esc_attr( $id_suffix ); ?>"
			<?php else : ?>
				aria-label="<?php esc_attr_e( 'Shop order', 'woocommerce' ); ?>"
			<?php endif; ?>
		>
			<?php foreach ( $catalog_orderby_options as $id => $name ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $orderby, $id ); ?>><?php echo esc_html( $name ); ?></option>
			<?php endforeach; ?>
		</select>
		<svg class="pointer-events-none absolute right-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
	</div>
	<input type="hidden" name="paged" value="1" />
	<?php wc_query_string_form_fields( null, array( 'orderby', 'submit', 'paged', 'product-page' ) ); ?>
</form>
