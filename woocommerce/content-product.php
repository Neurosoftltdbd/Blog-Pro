<?php
/**
 * Product Card (Loop) — redesigned.
 *
 * Renders inside <ul id="blogpro-products"> from archive-product.php.
 * The same template serves the strip/list view — the <li> carries
 * .blogpro-card-grid / .blogpro-card-list modifiers, and layout is
 * driven purely by CSS grid template classes on the <ul>.
 *
 * Layout (grid):
 *   ┌──────────────────────────────┐
 *   │ image (4:3, hover scale)     │ ← blogpro_responsive_img (WebP srcset)
 *   │  [Sale] [Featured] [OOS]     │
 *   ├──────────────────────────────┤
 *   │ category · rating badge      │
 *   │ Product Title                │
 *   │ price / old price            │
 *   │ [Add to cart]  (full width)  │
 *   └──────────────────────────────┘
 *
 * Robustness:
 *  - Image is wrapped in <a> with predictable hover area; the
 *    add-to-cart is always reachable (not only on hover) so touch
 *    users and keyboard users can act — hover-only CTAs are a known
 *    mobile UX failure mode.
 *  - The whole card is not a single link (nested anchors are invalid
 *    HTML) — title link + image link only.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

if ( empty( $product ) || ! $product->is_visible() ) return;

/** Sizes for the responsive srcset. 4-col card ≈ 280px, 2-col ≈ 600px. */
$card_sizes = '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw';

/** Build the badge stack (sale + out-of-stock + featured). */
$badges = array();
if ( $product->is_on_sale() ) {
	$badges[] = '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-red-500 text-white shadow-sm">' . esc_html__( 'Sale', 'blog-pro' ) . '</span>';
}
if ( ! $product->is_in_stock() ) {
	$badges[] = '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-gray-900 text-white shadow-sm">' . esc_html__( 'Out of stock', 'blog-pro' ) . '</span>';
}
if ( $product->is_featured() ) {
	$badges[] = '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-amber-400 text-amber-950 shadow-sm">' . esc_html__( 'Featured', 'blog-pro' ) . '</span>';
}

/** Primary category for the small label under the image. */
$primary_cat = '';
$terms = get_the_terms( $product->get_id(), 'product_cat' );
if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
	$primary_cat = $terms[0]->name;
}

/** Rating chips — SVG stars via the wcom-support filter. */
$rating_count = (int) $product->get_rating_count();
?>
<li <?php wc_product_class( 'product-card blogpro-card group relative bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden flex flex-col', $product ); ?>>

	<!-- ==================== IMAGE ==================== -->
	<div class="blogpro-card-media relative aspect-4/3 bg-gray-50 overflow-hidden shrink-0">
		<?php if ( ! empty( $badges ) ) : ?>
			<div class="absolute top-3 left-3 z-10 flex flex-col gap-1.5">
				<?php echo implode( '', $badges ); // badges are pre-escaped ?>
			</div>
		<?php endif; ?>

		<?php if ( ! $product->is_in_stock() ) : ?>
			<div class="absolute top-3 right-3 z-10">
				<span class="inline-flex items-center rounded-full bg-white/90 backdrop-blur-sm px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm">
					<?php esc_html_e( 'Sold out', 'blog-pro' ); ?>
				</span>
			</div>
		<?php endif; ?>

		<a href="<?php the_permalink(); ?>"
		   class="block w-full h-full"
		   aria-label="<?php echo esc_attr( get_the_title() ); ?>"
		   tabindex="-1">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php
				/**
				 * WebP-first responsive image via the media-optimization
				 * helper. Outputs a full srcset (320/480/768/1024/1280/1600),
				 * intrinsic width/height (kills CLS), async decode, lazy load.
				 */
				echo blogpro_responsive_img( get_post_thumbnail_id(), array(
					'class' => 'w-full h-full object-cover transition-transform duration-500 ease-out group-hover:scale-105',
					'alt'   => esc_attr( get_the_title() ),
					'sizes' => $card_sizes,
				) );
				?>
			<?php else : ?>
				<div class="w-full h-full flex items-center justify-center text-gray-300">
					<svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
						      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
					</svg>
				</div>
			<?php endif; ?>
		</a>
	</div>

	<!-- ==================== BODY ==================== -->
	<div class="blogpro-card-body flex flex-col flex-1 p-4 min-w-0">

		<?php if ( $primary_cat ) : ?>
			<p class="text-[11px] uppercase tracking-wider text-indigo-600 font-semibold mb-1 truncate">
				<?php echo esc_html( $primary_cat ); ?>
			</p>
		<?php endif; ?>

		<?php
		/**
		 * woocommerce_shop_loop_item_title hook
		 * (renders the product title linked to the product page)
		 */
		?>
		<h2 class="text-base font-semibold text-gray-900 leading-snug mb-1.5 line-clamp-2 text-pretty">
			<a href="<?php the_permalink(); ?>" class="no-underline text-inherit hover:text-indigo-600 transition-colors"><?php the_title(); ?></a>
		</h2>

		<?php if ( $rating_count > 0 ) : ?>
			<div class="flex items-center gap-1.5 mb-2" aria-label="<?php
				/* translators: %s: average rating */
				echo esc_attr( sprintf( __( 'Rated %s out of 5 from %d reviews', 'blog-pro' ), $product->get_average_rating(), $rating_count ) );
			?>">
				<?php echo wc_get_rating_html( $product->get_average_rating(), $rating_count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="text-xs text-gray-400">(<?php echo esc_html( $rating_count ); ?>)</span>
			</div>
		<?php endif; ?>

		<div class="mt-auto pt-2">
			<?php
			/**
			 * woocommerce_after_shop_loop_item_title hook
			 * (renders the price)
			 */
			?>
			<div class="text-lg font-bold text-gray-900 pricing">
				<?php echo wp_kses_post( $product->get_price_html() ); ?>
			</div>

			<?php
			/**
			 * woocommerce_after_shop_loop_item hook
			 * (add-to-cart button + extras from wishlist/compare plugins).
			 * The button HTML comes from loop/add-to-cart.php — styled there.
			 */
			?>
			<div class="mt-2.5">
				<?php do_action( 'woocommerce_after_shop_loop_item' ); ?>
			</div>
		</div>
	</div>
</li>
