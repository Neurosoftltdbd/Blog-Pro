<?php
/**
 * Single Product Content — redesigned.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

/**
 * Hook: woocommerce_before_single_product
 * @hooked woocommerce_output_all_notices - 10
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

if ( ! $product ) return;

/* Category eyebrow — the product's primary term, linked. */
$bp_eyebrow = '';
$bp_cats    = get_the_terms( $product->get_id(), 'product_cat' );
if ( ! empty( $bp_cats ) && ! is_wp_error( $bp_cats ) ) {
	$bp_first = array_pop( $bp_cats );
	$bp_eyebrow = sprintf(
		'<a href="%s" class="text-xs font-semibold uppercase tracking-widest text-indigo-600 hover:text-indigo-800 no-underline transition-colors">%s</a>',
		esc_url( get_term_link( $bp_first ) ),
		esc_html( $bp_first->name )
	);
}
?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'product-single', $product ); ?>>

	<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-start">

		<!-- ==================== GALLERY ==================== -->
		<div class="product-gallery lg:sticky lg:top-24">
			<?php
			/**
			 * Hook: woocommerce_before_single_product_summary
			 * @hooked woocommerce_show_product_sale_flash - 10
			 * @hooked woocommerce_show_product_images     - 20
			 */
			do_action( 'woocommerce_before_single_product_summary' );
			?>
		</div>

		<!-- ==================== SUMMARY / BUY BOX ==================== -->
		<div class="summary entry-summary product-summary">
			<?php if ( $bp_eyebrow ) : ?>
				<div class="mb-2"><?php echo $bp_eyebrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>

			<?php
			/**
			 * Hook: woocommerce_single_product_summary
			 * @hooked title 5 · rating 10 · price 10 · excerpt 20 ·
			 *         add-to-cart 30 · meta 40 · sharing 50 · schema 60
			 */
			do_action( 'woocommerce_single_product_summary' );
			?>

			<?php
			/* Trust row — honest, data-driven defaults; filterable. */
			$bp_trust = array();
			if ( $product->is_in_stock() && $product->is_purchasable() ) {
				$bp_trust[] = array(
					'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>',
					'text'  => __( 'In stock — ready to ship', 'blog-pro' ),
				);
			}
			$bp_trust[] = array(
				'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>',
				'text' => __( 'Secure checkout', 'blog-pro' ),
			);
			/**
			 * Filter the single-product trust row items.
			 * Each item: array( 'icon' => '<svg path…>', 'text' => '…' ).
			 */
			$bp_trust = apply_filters( 'blogpro_single_product_trust', $bp_trust, $product );
			?>
			<?php if ( $bp_trust ) : ?>
				<ul class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 pt-6 border-t border-gray-100 text-sm text-gray-600" role="list">
					<?php foreach ( $bp_trust as $item ) : ?>
						<li class="flex items-center gap-2">
							<span class="w-5 h-5 shrink-0 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center" aria-hidden="true">
								<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><?php echo $item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></svg>
							</span>
							<?php echo esc_html( $item['text'] ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<?php
	/**
	 * Hook: woocommerce_after_single_product_summary
	 * @hooked woocommerce_output_product_data_tabs 10
	 * @hooked woocommerce_upsell_display           15
	 * @hooked woocommerce_output_related_products  20
	 */
	do_action( 'woocommerce_after_single_product_summary' );
	?>
</div>

<?php
/* Mobile sticky add-to-cart bar — appears once the real form scrolls
 * out of view (JS in wcom-support.php). Hidden by default so no-JS and
 * desktop are unaffected. */
if ( $product->is_purchasable() && $product->is_in_stock() ) :
	$bp_bar_thumb = $product->get_image_id()
		? blogpro_responsive_img( $product->get_image_id(), array( 'class' => 'w-11 h-11 rounded-lg object-cover', 'alt' => '', 'sizes' => '44px' ) )
		: '';
	// Variable / grouped products must pick options first — the bar
	// scrolls to the form instead of claiming to add directly.
	$bp_bar_label = $product->is_type( 'simple' ) ? __( 'Add to cart', 'blog-pro' ) : __( 'Choose options', 'blog-pro' );
	?>
	<div id="blogpro-sticky-atc" class="lg:hidden fixed inset-x-0 bottom-0 z-40 translate-y-full transition-transform duration-300 ease-out" aria-hidden="true">
		<div class="flex items-center gap-3 bg-white border-t border-gray-200 shadow-[0_-4px_20px_rgba(0,0,0,0.06)] px-4 py-3">
			<?php if ( $bp_bar_thumb ) : ?>
				<div class="shrink-0"><?php echo $bp_bar_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>
			<div class="min-w-0 flex-1">
				<p class="text-xs text-gray-500 truncate"><?php echo esc_html( $product->get_name() ); ?></p>
				<p class="text-sm font-bold text-gray-900 leading-tight"><?php echo wp_kses_post( wp_strip_all_tags( $product->get_price_html() ) ); ?></p>
			</div>
			<a href="#blogpro-atc-form" id="blogpro-sticky-atc-btn" class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm no-underline active:bg-indigo-700">
				<?php echo esc_html( $bp_bar_label ); ?>
			</a>
		</div>
	</div>
<?php endif; ?>
