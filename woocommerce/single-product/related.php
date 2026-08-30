<?php
/**
 * Related products — Tailwind heading + the theme's card grid.
 *
 * The grid comes from the woocommerce_product_loop_start/end filters
 * in wcom-support.php, so it reuses the /shop/ responsive grid and the
 * content-product.php cards (which render images via
 * blogpro_responsive_img()).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! $related_products ) return;

/**
 * Ensure all images of related products are lazy loaded by increasing the
 * current media count to WordPress's lazy loading threshold if needed.
 */
if ( function_exists( 'wp_increase_content_media_count' ) ) {
	$content_media_count = wp_increase_content_media_count( 0 );
	if ( $content_media_count < wp_omit_loading_attr_threshold() ) {
		wp_increase_content_media_count( wp_omit_loading_attr_threshold() - $content_media_count );
	}
}
?>

<section class="related products mt-12">

	<?php
	$heading = apply_filters( 'woocommerce_product_related_products_heading', __( 'Related products', 'woocommerce' ) );

	if ( $heading ) :
		?>
		<h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-5"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>
	<?php woocommerce_product_loop_start(); ?>

		<?php foreach ( $related_products as $related_product ) : ?>

				<?php
				$post_object = get_post( $related_product->get_id() );

				setup_postdata( $GLOBALS['post'] = $post_object ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, Squiz.PHP.DisallowMultipleAssignments.Found

				wc_get_template_part( 'content', 'product' );
				?>

		<?php endforeach; ?>

	<?php woocommerce_product_loop_end(); ?>

</section>
<?php
wp_reset_postdata();
