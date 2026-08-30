<?php
/**
 * Single product short description — Tailwind styling.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $post;

$short_description = apply_filters( 'woocommerce_short_description', $post->post_excerpt );

if ( ! $short_description ) return;

?>
<div class="woocommerce-product-details__short-description prose prose-sm max-w-none text-gray-600 leading-relaxed mt-3">
	<?php echo $short_description; // WPCS: XSS ok. ?>
</div>
