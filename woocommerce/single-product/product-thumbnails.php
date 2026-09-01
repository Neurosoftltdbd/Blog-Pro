<?php
/**
 * Single Product Thumbnails — fully custom, ID-based.
 *
 * Each thumbnail uses blogpro_responsive_img() for WebP srcset.
 * Classes + data-* attrs kept for flexslider/zoom/photoswipe JS.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

if ( ! $product || ! $product instanceof WC_Product ) return;

$attachment_ids = $product->get_gallery_image_ids();

if ( empty( $attachment_ids ) ) return;

foreach ( $attachment_ids as $key => $attachment_id ) :
	$full_src = wp_get_attachment_image_src( $attachment_id, 'full' );
	$alt      = trim( wp_strip_all_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) );
	if ( ! $alt ) $alt = get_the_title( $attachment_id );

	$full_url  = isset( $full_src[0] ) ? esc_url( $full_src[0] ) : '';
	$full_w    = isset( $full_src[1] ) ? (int) $full_src[1] : 0;
	$full_h    = isset( $full_src[2] ) ? (int) $full_src[2] : 0;
	$thumb_url = esc_url( wp_get_attachment_thumb_url( $attachment_id ) );
	?>
	<div class="woocommerce-product-gallery__image col-span-1 cursor-pointer rounded-lg overflow-hidden border border-gray-100 transition-shadow hover:shadow-sm"
	     data-thumb="<?php echo $thumb_url; ?>"
	     data-thumb-alt="<?php echo esc_attr( $alt ); ?>"
	     data-large_image="<?php echo $full_url; ?>"
	     data-large_image_width="<?php echo $full_w; ?>"
	     data-large_image_height="<?php echo $full_h; ?>">
		<a href="<?php echo $full_url; ?>">
			<?php
			echo blogpro_responsive_img( $attachment_id, array(
				'class'   => 'w-full h-auto object-cover',
				'alt'     => esc_attr( $alt ),
				'sizes'   => '(max-width: 768px) 25vw, 150px',
				'loading' => 'lazy',
			) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</a>
	</div>
<?php endforeach; ?>
