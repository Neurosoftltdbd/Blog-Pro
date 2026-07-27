<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<div class="max-w-7xl mx-auto px-4 pt-9">
	<?php blogpro_breadcrumbs(); ?>

	<h1 class="text-2xl md:text-3xl font-bold text-gray-900 mt-8 mb-8">
		<?php
		if ( is_category() || is_tag() || is_tax() ) {
			single_term_title();
		} elseif ( is_search() ) {
			printf( esc_html__( 'Search results for: %s', 'blog-pro' ), '<em>' . esc_html( get_search_query() ) . '</em>' );
		} elseif ( is_author() ) {
			printf( esc_html__( 'Posts by %s', 'blog-pro' ), esc_html( get_the_author() ) );
		} else {
			esc_html_e( 'Blog', 'blog-pro' );
		}
		?>
	</h1>

	<?php if ( have_posts() ) : ?>
	<ul class="space-y-8 list-none p-0 m-0">
		<?php while ( have_posts() ) : the_post(); ?>
			<li class="flex flex-col md:flex-row gap-6 items-start pb-8 border-b border-gray-100 last:border-b-0">
				<a class="shrink-0 w-full aspect-video md:w-48 md:h-32 rounded-xl overflow-hidden bg-gray-100 block" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
					<?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'blogpro-card', array( 'alt' => esc_attr( get_the_title() ), 'class' => 'w-full h-full object-cover' ) ); else : ?>
						<div class="w-full h-full bg-gray-100"></div>
					<?php endif; ?>
				</a>
				<div class="flex-1">
					<div class="text-sm text-gray-500 mb-2"><?php blogpro_posted_on(); ?></div>
					<h2 class="text-xl font-bold text-gray-900 leading-snug"><a href="<?php the_permalink(); ?>" class="hover:text-indigo-600 transition-colors no-underline"><?php the_title(); ?></a></h2>
					<p class="text-gray-600 leading-relaxed mt-2"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26 ) ); ?></p>
				</div>
			</li>
		<?php endwhile; ?>
	</ul>
	<?php blogpro_pagination(); ?>
	<?php else : ?>
		<p class="text-gray-600"><?php esc_html_e( 'No posts found.', 'blog-pro' ); ?></p>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
