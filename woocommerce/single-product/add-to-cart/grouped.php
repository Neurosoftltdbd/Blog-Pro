<?php
/**
 * Grouped product add to cart — Tailwind styling.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product, $post;

do_action( 'woocommerce_before_add_to_cart_form' ); ?>

<form class="cart grouped_form mt-4" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>
	<table cellspacing="0" class="woocommerce-grouped-product-list group_table w-full text-sm" role="presentation">
		<tbody>
			<?php
			$quantites_required      = false;
			$previous_post           = $post;
			$grouped_product_columns = apply_filters(
				'woocommerce_grouped_product_columns',
				array(
					'quantity',
					'label',
					'price',
				),
				$product
			);
			$show_add_to_cart_button = false;

			do_action( 'woocommerce_grouped_product_list_before', $grouped_product_columns, $quantites_required, $product );

			foreach ( $grouped_products as $grouped_product_child ) {
				$post_object        = get_post( $grouped_product_child->get_id() );
				$quantites_required = $quantites_required || ( $grouped_product_child->is_purchasable() && ! $grouped_product_child->has_options() );
				$post               = $post_object; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				setup_postdata( $post );

				if ( $grouped_product_child->is_in_stock() ) {
					$show_add_to_cart_button = true;
				}

				echo '<tr id="product-' . esc_attr( $grouped_product_child->get_id() ) . '" class="woocommerce-grouped-product-list-item ' . esc_attr( implode( ' ', wc_get_product_class( '', $grouped_product_child ) ) ) . ' border-b border-gray-100">';

				// Output columns for each product.
				foreach ( $grouped_product_columns as $column_id ) {
					do_action( 'woocommerce_grouped_product_list_before_' . $column_id, $grouped_product_child );

					switch ( $column_id ) {
						case 'quantity':
							ob_start();
							?>
							<td class="quantity py-3 w-24">
							<?php
							if ( ! $grouped_product_child->is_purchasable() || $grouped_product_child->has_options() ) {
								woocommerce_quantity_input( array(
									'input_name'  => 'quantity[' . $grouped_product_child->get_id() . ']',
									'input_value' => isset( $_POST['quantity'][ $grouped_product_child->get_id() ] ) ? wc_stock_amount( wc_clean( wp_unslash( $_POST['quantity'][ $grouped_product_child->get_id() ] ) ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
									'min_value'   => apply_filters( 'woocommerce_quantity_input_min', 0, $grouped_product_child ),
									'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $grouped_product_child->get_max_purchase_quantity(), $grouped_product_child ),
									'placeholder' => '0',
								) );

								$grouped_product_child->set_props( array(
									'min_purchase_quantity' => 0,
								) );
							} else {
								do_action( 'woocommerce_grouped_product_list_before_quantity', $grouped_product_child );
								?>
								<input type="hidden" name="quantity[<?php echo esc_attr( $grouped_product_child->get_id() ); ?>]" value="1" />
								<?php
							}
							?>
							</td>
							<?php
							$value = ob_get_clean();
							echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							break;
						case 'label':
							?>
							<td class="label py-3">
								<label class="text-sm font-medium text-gray-900" for="product-<?php echo esc_attr( $grouped_product_child->get_id() ); ?>">
									<?php echo $grouped_product_child->is_visible() ? '<a class="text-indigo-600 hover:text-indigo-800" href="' . esc_url( apply_filters( 'woocommerce_grouped_product_list_link', $grouped_product_child->get_permalink(), $grouped_product_child->get_id() ) ) . '">' . wp_kses_post( $grouped_product_child->get_name() ) . '</a>' : wp_kses_post( $grouped_product_child->get_name() ); ?>
								</label>
							</td>
							<?php
							break;
						case 'price':
							?>
							<td class="price py-3 text-right text-gray-900">
								<?php echo $grouped_product_child->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</td>
							<?php
							break;
						default:
							break;
					}

					do_action( 'woocommerce_grouped_product_list_after_' . $column_id, $grouped_product_child );
				}

				echo '</tr>';
			}
			$post = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $post );

			do_action( 'woocommerce_grouped_product_list_after', $grouped_product_columns, $quantites_required, $product );
			?>
		</tbody>
	</table>

	<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" />

	<?php if ( $quantites_required && $show_add_to_cart_button ) : ?>

		<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

		<button type="submit" class="single_add_to_cart_button button alt mt-4 inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>

		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

	<?php endif; ?>
</form>

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
