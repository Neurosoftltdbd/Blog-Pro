<?php
/**
 * Single product rating — Tailwind styling.
 *
 * The .star-rating wrapper class is kept (plugins target it).
 * The inner markup is the stock HTML; the SVG-styled variant lives
 * in the woocommerce_product_get_rating_html filter (wcom-support).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

if ( ! wc_review_ratings_enabled() ) return;

$rating_count = $product->get_rating_count();
$review_count = $product->get_review_count();
$average      = $product->get_average_rating();

if ( $rating_count > 0 ) : ?>

	<div class="woocommerce-product-rating flex items-center gap-2 mt-1 mb-3">
		<?php echo wc_get_rating_html( $average, $rating_count ); // WPCS: XSS ok. ?>
		<?php if ( comments_open() ) : ?>
			<?php //phpcs:disable ?>
			<a href="#reviews" class="woocommerce-review-link text-sm text-indigo-600 hover:text-indigo-800 no-underline" rel="nofollow">(<?php printf( _n( '%s customer review', '%s customer reviews', $review_count, 'woocommerce' ), '<span class="count">' . esc_html( $review_count ) . '</span>' ); ?>)</a>
			<?php // phpcs:enable ?>
		<?php endif ?>
	</div>

<?php endif; ?>
