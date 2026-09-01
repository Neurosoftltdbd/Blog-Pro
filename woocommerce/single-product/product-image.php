<?php
/**
 * Single Product Image Gallery — fully custom, ID-based.
 *
 * Every <img> uses blogpro_responsive_img() so images are served via
 * the /blogpro-img/ WebP resizer with a full srcset. No dependency on
 * wc_get_gallery_image_html() or the media-optimize buffer pass.
 *
 * Classes kept for WC JS compatibility:
 *   .woocommerce-product-gallery         — flexslider init target
 *   .woocommerce-product-gallery__wrapper — flexslider container
 *   .woocommerce-product-gallery__image   — slide element
 *   .wp-post-image                        — main image identifier
 *   data-columns                          — thumbnail column count
 *   data-thumb / data-large_image         — photoswipe/zoom data
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

$columns           = apply_filters( 'woocommerce_product_thumbnails_columns', 4 );
$post_thumbnail_id = $product->get_image_id();
$has_images        = (bool) $post_thumbnail_id;

$wrapper_classes = apply_filters(
	'woocommerce_single_product_image_gallery_classes',
	array(
		'woocommerce-product-gallery',
		'woocommerce-product-gallery--' . ( $has_images ? 'with-images' : 'without-images' ),
		'woocommerce-product-gallery--columns-' . absint( $columns ),
		'images',
		'relative',
		'rounded-2xl',
		'overflow-hidden',
		'bg-white',
	)
);
?>
<div class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) ) ); ?>" data-columns="<?php echo esc_attr( $columns ); ?>">
	<div class="woocommerce-product-gallery__wrapper grid grid-cols-4 gap-2.5">
		<?php if ( $post_thumbnail_id ) : ?>
			<?php
			/* ---- Main image ---- */
			$full_src    = wp_get_attachment_image_src( $post_thumbnail_id, 'full' );
			$main_alt    = trim( wp_strip_all_tags( get_post_meta( $post_thumbnail_id, '_wp_attachment_image_alt', true ) ) );
			if ( ! $main_alt ) $main_alt = get_the_title( $post_thumbnail_id );
			$full_url    = isset( $full_src[0] ) ? esc_url( $full_src[0] ) : '';
			$full_w      = isset( $full_src[1] ) ? (int) $full_src[1] : 0;
			$full_h      = isset( $full_src[2] ) ? (int) $full_src[2] : 0;
			$thumb_url   = esc_url( wp_get_attachment_thumb_url( $post_thumbnail_id ) );
			?>
			<div class="woocommerce-product-gallery__image col-span-4 cursor-pointer"
			     data-thumb="<?php echo $thumb_url; ?>"
			     data-thumb-alt="<?php echo esc_attr( $main_alt ); ?>"
			     data-large_image="<?php echo $full_url; ?>"
			     data-large_image_width="<?php echo $full_w; ?>"
			     data-large_image_height="<?php echo $full_h; ?>">
				<a href="<?php echo $full_url; ?>">
					<?php
					echo blogpro_responsive_img( $post_thumbnail_id, array(
						'class'   => 'wp-post-image w-full h-auto aspect-square object-cover',
						'alt'     => esc_attr( $main_alt ),
						'sizes'   => '(max-width: 768px) 100vw, 600px',
						'loading' => 'eager',
					) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</a>
			</div>
		<?php else : ?>
			<div class="woocommerce-product-gallery__image woocommerce-product-gallery__image--placeholder block overflow-hidden rounded-2xl bg-white">
				<img src="<?php echo esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ); ?>" alt="<?php esc_attr_e( 'Awaiting product image', 'woocommerce' ); ?>" class="wp-post-image w-full h-auto object-cover" />
			</div>
		<?php endif; ?>

		<?php
		/**
		 * Gallery thumbnails — fires woocommerce_show_product_thumbnails()
		 * which loads single-product/product-thumbnails.php (also
		 * overridden in this theme to use blogpro_responsive_img()).
		 */
		do_action( 'woocommerce_product_thumbnails' );
		?>
	</div>
</div>
