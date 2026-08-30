<?php
/**
 * Single product stock — Tailwind badge.
 *
 * Rendered by wc_get_stock_html() with $class (in-stock /
 * out-of-stock / onbackorder) and $availability (text).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$badge_class = 'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 mb-2';

if ( 'in-stock' === $class ) {
	$badge_class .= ' bg-emerald-50 text-emerald-700 ring-emerald-200';
} elseif ( 'out-of-stock' === $class ) {
	$badge_class .= ' bg-red-50 text-red-700 ring-red-200';
} else {
	$badge_class .= ' bg-amber-50 text-amber-700 ring-amber-200';
}

?>
<p class="stock <?php echo esc_attr( $class ); ?> <?php echo esc_attr( $badge_class ); ?>"><?php echo wp_kses_post( $availability ); ?></p>
