<?php
/**
 * No products found (loop) — Tailwind notice.
 *
 * The archive template renders its own empty state; this one covers
 * product shortcodes ([products]) and plugin loops that use
 * loop/no-products-found.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<p class="no-products-found text-center text-gray-500 py-10">
	<?php esc_html_e( 'No products were found matching your selection.', 'woocommerce' ); ?>
</p>
