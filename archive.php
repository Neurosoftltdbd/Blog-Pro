<?php
/**
 * Archive template: date archives and any archive type without its own
 * dedicated template. Category, tag, author and custom-taxonomy archives
 * are handled by their own templates when they exist.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<div class="max-w-7xl mx-auto px-4 pt-9">
	<?php blogpro_breadcrumbs(); ?>

	<div class="text-center justify-center items-center py-8">
		<h1 class="text-3xl md:text-5xl font-bold text-gray-900"><?php the_archive_title(); ?></h1>
		<?php if ( get_the_archive_description() ) : ?>
			<div class="max-w-2xl mx-auto mt-4 text-gray-700 leading-relaxed"><?php echo wp_kses_post( get_the_archive_description() ); ?></div>
		<?php endif; ?>
	</div>

	<?php if ( have_posts() ) : ?>
	<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
		<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'flex flex-col bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1' ); ?>>
			<a class="aspect-video bg-gray-100 block overflow-hidden shrink-0" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
				<?php if ( has_post_thumbnail() ) : echo blogpro_responsive_img( get_post_thumbnail_id(), array( 'alt' => esc_attr( get_the_title() ), 'class' => 'w-full h-full object-cover transform hover:scale-105 transition-transform duration-500', 'sizes' => '(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw' ) ); else : ?>
					<div class="w-full h-full bg-linear-to-br from-indigo-100 to-purple-100"></div>
				<?php endif; ?>
			</a>
			<div class="flex flex-col flex-1 p-6">
				<?php
				$categories = get_the_category();
				if ( ! empty( $categories ) ) :
				?>
				<div class="flex flex-wrap gap-2 mb-3">
					<?php foreach ( $categories as $cat ) : ?>
						<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full hover:bg-indigo-100 transition-colors no-underline"><?php echo esc_html( $cat->name ); ?></a>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<div class="text-sm text-gray-700 mb-3"><?php blogpro_posted_on(); ?></div>
				<h2 class="text-lg font-bold text-gray-900 leading-snug mb-2 line-clamp-2"><a href="<?php the_permalink(); ?>" class="hover:text-indigo-600 transition-colors no-underline"><?php the_title(); ?></a></h2>
				<p class="text-gray-700 leading-relaxed text-sm line-clamp-3"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>

				<div class="mt-auto pt-4">
					<a href="<?php the_permalink(); ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition-colors no-underline">
						<?php esc_html_e( 'Read more', 'blog-pro' ); ?>
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
					</a>
				</div>
			</div>
		</article>
		<?php endwhile; ?>
	</div>

	<div class="mt-12">
		<?php blogpro_pagination(); ?>
	</div>

	<?php else : ?>
	<div class="text-center py-20">
		<div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-6">
			<svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
		</div>
		<h2 class="text-2xl font-bold text-gray-900 mb-2"><?php esc_html_e( 'Nothing Found', 'blog-pro' ); ?></h2>
		<p class="text-gray-600 mb-6"><?php esc_html_e( 'No posts were found. Try a search instead?', 'blog-pro' ); ?></p>
		<?php get_search_form(); ?>
	</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>