<?php
/**
 * Up-sells / "You may also like" — Tailwind heading + the theme's card grid.
 *
 * The grid comes from the woocommerce_product_loop_start/end filters
 * in wcom-support.php (same treatment as related.php).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! $upsells ) return;
?>

<section class="up-sells upsells products mt-12">
	<?php
	$heading = apply_filters( 'woocommerce_product_upsells_products_heading', __( 'You may also like&hellip;', 'woocommerce' ) );

	if ( $heading ) :
		?>
		<h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-5"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php woocommerce_product_loop_start(); ?>

		<?php foreach ( $upsells as $upsell ) : ?>

			<?php
			$post_object = get_post( $upsell->get_id() );

			setup_postdata( $GLOBALS['post'] = $post_object ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, Squiz.PHP.DisallowMultipleAssignments.Found

			wc_get_template_part( 'content', 'product' );
			?>

		<?php endforeach; ?>

	<?php woocommerce_product_loop_end(); ?>

</section>

<?php
wp_reset_postdata();
