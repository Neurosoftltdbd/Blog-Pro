<?php
/**
 * Embed template: self-contained output used when this post is embedded
 * on another site via oEmbed. Fires the core `embed_head` / `embed_footer`
 * hooks so the embed script, styles and share dialog are printed.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! headers_sent() ) {
	header( 'X-WP-embed: true' );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<title><?php echo wp_get_document_title(); ?></title>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
	/**
	 * Prints scripts or data in the embed template head tag.
	 *
	 * @since 4.4.0
	 */
	do_action( 'embed_head' );
	?>
</head>
<body <?php body_class( 'bg-white' ); ?>>
<?php
if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		$thumbnail_id = has_post_thumbnail() ? get_post_thumbnail_id() : 0;

		if ( 'attachment' === get_post_type() && wp_attachment_is_image() ) {
			$thumbnail_id = get_the_ID();
		}

		/** This filter is documented in wp-includes/theme-compat/embed-content.php */
		$thumbnail_id = apply_filters( 'embed_thumbnail_id', $thumbnail_id );

		$shape = 'square';

		if ( $thumbnail_id ) {
			$measurements = array( 1, 1 );
			$image_size   = 'full';
			$meta         = wp_get_attachment_metadata( $thumbnail_id );

			if ( ! empty( $meta['sizes'] ) ) {
				$aspect_ratio = 1;
				foreach ( $meta['sizes'] as $size => $data ) {
					if ( $data['height'] > 0 && $data['width'] / $data['height'] > $aspect_ratio ) {
						$aspect_ratio = $data['width'] / $data['height'];
						$measurements = array( $data['width'], $data['height'] );
						$image_size   = $size;
					}
				}
			}

			/** This filter is documented in wp-includes/theme-compat/embed-content.php */
			$image_size = apply_filters( 'embed_thumbnail_image_size', $image_size, $thumbnail_id );

			$shape = $measurements[0] / $measurements[1] >= 1.75 ? 'rectangular' : 'square';

			/** This filter is documented in wp-includes/theme-compat/embed-content.php */
			$shape = apply_filters( 'embed_thumbnail_image_shape', $shape, $thumbnail_id );
		}
		?>
		<div <?php post_class( 'wp-embed' ); ?>>
			<?php if ( $thumbnail_id && 'rectangular' === $shape ) : ?>
				<div class="wp-embed-featured-image rectangular">
					<a href="<?php the_permalink(); ?>" target="_top">
						<?php echo wp_get_attachment_image( $thumbnail_id, $image_size ); ?>
					</a>
				</div>
			<?php endif; ?>

			<p class="wp-embed-heading">
				<a href="<?php the_permalink(); ?>" target="_top">
					<?php the_title(); ?>
				</a>
			</p>

			<?php if ( $thumbnail_id && 'square' === $shape ) : ?>
				<div class="wp-embed-featured-image square">
					<a href="<?php the_permalink(); ?>" target="_top">
						<?php echo wp_get_attachment_image( $thumbnail_id, $image_size ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="wp-embed-excerpt"><?php the_excerpt_embed(); ?></div>

			<?php
			/**
			 * Prints additional content after the embed excerpt.
			 *
			 * @since 4.4.0
			 */
			do_action( 'embed_content' );
			?>

			<div class="wp-embed-footer">
				<?php the_embed_site_title(); ?>

				<div class="wp-embed-meta">
					<?php
					/**
					 * Prints additional meta content in the embed template.
					 *
					 * @since 4.4.0
					 */
					do_action( 'embed_content_meta' );
					?>
				</div>
			</div>
		</div>
		<?php
	endwhile;
else :
	?>
	<div class="wp-embed">
		<p class="wp-embed-heading"><?php esc_html_e( 'Oops! That embed cannot be found.', 'blog-pro' ); ?></p>

		<div class="wp-embed-excerpt">
			<p>
				<?php
				printf(
					wp_kses(
						/* translators: %s: A link to the embedded site. */
						__( 'It looks like nothing was found at this location. Maybe try visiting %s directly?', 'blog-pro' ),
						array( 'strong' => array(), 'a' => array( 'href' => array() ) )
					),
					'<strong><a href="' . esc_url( home_url() ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a></strong>'
				);
				?>
			</p>
		</div>

		<?php
		/** This filter is documented in wp-includes/theme-compat/embed-content.php */
		do_action( 'embed_content' );
		?>

		<div class="wp-embed-footer">
			<?php the_embed_site_title(); ?>
		</div>
	</div>
	<?php
endif;

/**
 * Prints scripts or data before the closing body tag in the embed template.
 *
 * @since 4.4.0
 */
do_action( 'embed_footer' );
?>
</body>
</html>