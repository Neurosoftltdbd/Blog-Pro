<?php
/**
 * Single product price — Tailwind styling.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

?>
<p class="<?php echo esc_attr( apply_filters( 'woocommerce_product_price_class', 'price' ) ); ?> text-2xl font-bold text-indigo-600"><?php echo $product->get_price_html(); ?></p>
