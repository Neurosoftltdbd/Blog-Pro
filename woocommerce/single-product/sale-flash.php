<?php
/**
 * Single product sale flash — Tailwind badge.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $post, $product;

?>
<?php if ( $product->is_on_sale() ) : ?>

	<?php echo apply_filters( 'woocommerce_sale_flash', '<span class="onsale absolute top-3 left-3 z-10 inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-red-500 text-white shadow-sm">' . esc_html__( 'Sale!', 'woocommerce' ) . '</span>', $post, $product ); ?>

	<?php
endif;
