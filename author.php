<?php
/**
 * Author archive template: author bio card plus the author's posts grid.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<div class="max-w-7xl mx-auto px-4 pt-9">
	<?php blogpro_breadcrumbs(); ?>

	<?php $author_posts = (int) count_user_posts( get_the_author_meta( 'ID' ) ); ?>
	<div class="flex flex-col sm:flex-row items-center gap-6 p-8 bg-gray-50 border border-gray-100 rounded-3xl mb-10 shadow-sm">
		<div class="shrink-0">
			<?php echo get_avatar( get_the_author_meta( 'ID' ), 80, '', '', array( 'class' => 'rounded-full shadow-md w-20 h-20' ) ); ?>
		</div>
		<div class="text-center sm:text-left">
			<h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-1"><?php the_author(); ?></h1>
			<p class="text-gray-600 leading-relaxed"><?php echo esc_html( get_the_author_meta( 'description' ) ); ?></p>
			<p class="mt-2 text-sm text-gray-500">
				<?php
				printf(
					/* translators: %s: Number of posts by the author. */
					esc_html( _n( '%s post published', '%s posts published', $author_posts, 'blog-pro' ) ),
					esc_html( number_format_i18n( $author_posts ) )
				);
				?>
			</p>
		</div>
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
				<div class="text-sm text-gray-500 mb-3"><?php blogpro_posted_on(); ?></div>
				<h2 class="text-lg font-bold text-gray-900 leading-snug mb-2 line-clamp-2"><a href="<?php the_permalink(); ?>" class="hover:text-indigo-600 transition-colors no-underline"><?php the_title(); ?></a></h2>
				<p class="text-gray-600 leading-relaxed text-sm line-clamp-3"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>

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
		<h2 class="text-2xl font-bold text-gray-900 mb-2"><?php esc_html_e( 'Nothing Found', 'blog-pro' ); ?></h2>
		<p class="text-gray-600"><?php esc_html_e( 'This author has not published any posts yet.', 'blog-pro' ); ?></p>
	</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>