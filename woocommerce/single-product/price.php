<?php
/**
 * Single product price — Tailwind styling with savings badge.
 *
 * When the product is on sale, a "Save X%" chip is appended next to
 * the price. The .price class is kept (WC + plugins target it).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

$bp_save_pct = '';
if ( $product->is_on_sale() ) {
	$regular = (float) $product->get_regular_price();
	$sale    = (float) $product->get_sale_price();
	if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
		$bp_save_pct = round( ( ( $regular - $sale ) / $regular ) * 100 );
	}
}
?>
<div class="flex flex-wrap items-center gap-3 my-4">
	<p class="<?php echo esc_attr( apply_filters( 'woocommerce_product_price_class', 'price' ) ); ?> text-3xl font-bold text-gray-900 leading-none"><?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
	<?php if ( $bp_save_pct ) : ?>
		<span class="inline-flex items-center rounded-full bg-red-50 border border-red-200 px-2.5 py-1 text-xs font-bold text-red-600">
			<?php
			/* translators: %d: percentage saved */
			printf( esc_html__( 'Save %d%%', 'blog-pro' ), (int) $bp_save_pct );
			?>
		</span>
	<?php endif; ?>
</div>
