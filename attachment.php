<?php
/**
 * Attachment template: displays a single attachment (image, document, etc.)
 * with next/previous image navigation within its gallery context.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
while ( have_posts() ) : the_post();
?>
<article <?php post_class( 'max-w-4xl mx-auto px-4 py-12' ); ?>>
	<?php blogpro_breadcrumbs(); ?>

	<h1 class="text-2xl md:text-4xl font-bold text-gray-900 text-center py-4"><?php the_title(); ?></h1>

	<?php if ( wp_attachment_is_image( get_the_ID() ) ) : ?>
		<div class="w-full rounded-2xl overflow-hidden shadow-lg my-8 bg-gray-100">
			<a href="<?php echo esc_url( wp_get_attachment_url() ); ?>">
				<?php echo wp_get_attachment_image( get_the_ID(), 'large', false, array( 'class' => 'w-full h-auto object-contain' ) ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="my-8 p-8 bg-gray-50 border border-gray-100 rounded-2xl text-center">
			<p class="text-gray-600 mb-4"><?php esc_html_e( 'Download this file:', 'blog-pro' ); ?></p>
			<a href="<?php echo esc_url( wp_get_attachment_url() ); ?>" class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800 font-semibold transition-colors no-underline">
				<?php echo esc_html( wp_basename( get_attached_file() ) ); ?>
			</a>
		</div>
	<?php endif; ?>

	<?php if ( has_excerpt() ) : ?>
		<p class="text-gray-600 leading-relaxed text-center mb-8"><?php the_excerpt(); ?></p>
	<?php endif; ?>

	<div class="prose prose-lg prose-indigo max-w-none text-gray-800 leading-relaxed mb-8">
		<?php the_content(); ?>
	</div>

	<nav class="flex items-center justify-between gap-4 text-sm font-semibold" aria-label="<?php esc_attr_e( 'Attachment navigation', 'blog-pro' ); ?>">
		<div class="flex-1">
			<?php previous_image_link( false, '&larr; ' . esc_html__( 'Previous image', 'blog-pro' ) ); ?>
		</div>
		<div class="flex-1 text-right">
			<?php next_image_link( false, esc_html__( 'Next image', 'blog-pro' ) . ' &rarr;' ); ?>
		</div>
	</nav>

	<?php
	$parent_id = get_post()->post_parent;
	if ( $parent_id ) :
	?>
	<p class="mt-8">
		<a href="<?php echo esc_url( get_permalink( $parent_id ) ); ?>" class="text-indigo-600 hover:text-indigo-800 font-semibold transition-colors no-underline">&larr; <?php printf( esc_html__( 'Back to %s', 'blog-pro' ), '<strong>' . esc_html( get_the_title( $parent_id ) ) . '</strong>' ); ?></a>
	</p>
	<?php endif; ?>
</article>
<?php endwhile; get_footer(); ?>