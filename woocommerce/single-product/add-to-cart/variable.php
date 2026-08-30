<?php
/**
 * Variable product add to cart — Tailwind styling.
 *
 * The variation select dropdowns get Tailwind classes via the
 * woocommerce_dropdown_variation_attribute_options_html filter
 * (wcom-support). The variations table is restyled as a definition list
 * so attribute + value stack on phone screens without losing the
 * data-priority/order key columns.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

$attribute_keys  = array_keys( $attributes );
$variations_json = wp_json_encode( $available_variations );
$variations_attr = function_exists( 'wc_esc_json' ) ? wc_esc_json( $variations_json ) : _wp_specialchars( $variations_json, ENT_QUOTES, 'UTF-8', true );

do_action( 'woocommerce_before_add_to_cart_form' ); ?>

<form class="variations_form cart mt-4" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data' data-product_id="<?php echo absint( $product->get_id() ); ?>" data-product_variations="<?php echo $variations_attr; // WPCS: XSS ok. ?>">
	<?php do_action( 'woocommerce_before_variations_form' ); ?>

	<?php if ( empty( $available_variations ) && false !== $available_variations ) : ?>
		<p class="stock out-of-stock inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-200"><?php echo esc_html( apply_filters( 'woocommerce_out_of_stock_message', __( 'This product is currently out of stock and unavailable.', 'woocommerce' ) ) ); ?></p>
	<?php else : ?>
		<table class="variations w-full mb-4" cellspacing="0" role="presentation">
			<tbody>
				<?php foreach ( $attributes as $attribute_name => $options ) : ?>
					<tr class="block md:table-row mb-3 md:mb-0">
						<th class="label block md:table-cell md:w-1/3 md:py-2 md:pr-4 md:text-left text-sm font-medium text-gray-700"><?php echo wc_attribute_label( $attribute_name ); // WPCS: XSS ok. ?></th>
						<td class="value block md:table-cell md:py-2">
							<?php
								wc_dropdown_variation_attribute_options(
									array(
										'options'   => $options,
										'attribute' => $attribute_name,
										'product'   => $product,
									)
								);
								echo end( $attribute_keys ) === $attribute_name ? wp_kses_post( apply_filters( 'woocommerce_reset_variations_link', '<a class="reset_variations ml-2 text-sm text-gray-500 hover:text-indigo-600" href="#" aria-label="' . esc_attr__( 'Clear options', 'woocommerce' ) . '">' . esc_html__( 'Clear', 'woocommerce' ) . '</a>' ) ) : '';
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<div class="reset_variations_alert screen-reader-text" role="alert" aria-live="polite" aria-relevant="all"></div>
		<?php
		if ( \Automattic\WooCommerce\Internal\VariationGallery\Package::is_enabled() ) :
			?>
			<script type="text/template" class="wc-product-gallery-default-template"><?php echo wc_get_product_gallery_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
			<?php
		endif;
		?>
		<?php do_action( 'woocommerce_after_variations_table' ); ?>

		<div class="single_variation_wrap">
			<?php
				do_action( 'woocommerce_before_single_variation' );
				do_action( 'woocommerce_single_variation' );
				do_action( 'woocommerce_after_single_variation' );
			?>
		</div>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_variations_form' ); ?>
</form>

<?php
do_action( 'woocommerce_after_add_to_cart_form' );
