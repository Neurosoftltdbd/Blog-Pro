<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
while ( have_posts() ) : the_post();
?>
<article <?php post_class( 'w-full flex flex-col items-center px-4 md:px-0 py-12' ); ?>>
	<div class="w-full max-w-7xl">
			<?php blogpro_breadcrumbs(); ?>
		</div>
	<div class="w-full max-w-4xl mx-auto py-12 md:py-20">
		<h1 class="text-3xl md:text-5xl text-center font-extrabold tracking-tight text-gray-900 py-8 leading-tight"><?php the_title(); ?></h1>
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="w-full rounded-lg overflow-hidden shadow-lg my-12 bg-gray-100">
				<?php the_post_thumbnail( 'blogpro-hero', array( 'alt' => esc_attr( get_the_title() ), 'class' => 'w-full h-full object-cover aspect-video rounded-lg' ) ); ?>
			</div>
		<?php endif; ?>
		<div class="text-sm font-semibold text-indigo-600 uppercase tracking-widest py-4"><?php blogpro_posted_on(); ?></div>


		<div class="prose prose-lg md:prose-xl prose-indigo max-w-none mx-auto mb-16 text-gray-800 leading-relaxed">
			<?php the_content(); ?>
		</div>

		<?php
		$tags = get_the_tags();
		if ( $tags ) :
		?>
		<div class="flex flex-wrap gap-3 mb-16">
			<?php foreach ( $tags as $tag ) : ?>
				<a href="<?php echo esc_url( get_tag_link( $tag ) ); ?>" class="px-4 py-2 bg-indigo-50 text-indigo-700 rounded-full text-sm font-semibold hover:bg-indigo-100 hover:text-indigo-900 transition-colors">
					#<?php echo esc_html( $tag->name ); ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<div class="flex flex-col sm:flex-row items-center gap-6 p-8 bg-gray-50 border border-gray-100 rounded-3xl mb-20 shadow-sm">
			<div class="shrink-0">
				<?php echo get_avatar( get_the_author_meta( 'ID' ), 80, '', '', array( 'class' => 'rounded-full shadow-md w-20 h-20' ) ); ?>
			</div>
			<div class="text-center sm:text-left">
				<h4 class="text-xl font-bold text-gray-900 mb-1"><?php the_author(); ?></h4>
				<p class="text-gray-600 leading-relaxed"><?php echo esc_html( get_the_author_meta( 'description' ) ); ?></p>
			</div>
		</div>

		<?php
		$related = blogpro_related_posts( get_the_ID(), 3 );
		if ( $related->have_posts() ) :
		?>
		<div class="mb-20">
			<h2 class="text-3xl font-bold text-gray-900 mb-8 border-b-2 border-indigo-500 inline-block pb-2"><?php esc_html_e( 'Related Posts', 'blog-pro' ); ?></h2>
			<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
				<?php while ( $related->have_posts() ) : $related->the_post(); ?>
					<article class="flex flex-col bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-shadow duration-300">
						<a href="<?php the_permalink(); ?>" class="aspect-video bg-gray-100 block overflow-hidden" aria-hidden="true" tabindex="-1">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'blogpro-card', array( 'alt' => esc_attr( get_the_title() ), 'class' => 'w-full h-full object-cover transform hover:scale-105 transition-transform duration-500' ) ); ?>
							<?php else : ?>
								<div class="w-full h-full bg-linear-to-br from-indigo-100 to-purple-100"></div>
							<?php endif; ?>
						</a>
						<div class="p-5">
							<div class="text-sm text-gray-500 mb-1"><?php echo esc_html( get_the_date() ); ?></div>
							<h3 class="text-base font-semibold text-gray-900 leading-snug line-clamp-2"><a href="<?php the_permalink(); ?>" class="hover:text-indigo-600 transition-colors no-underline"><?php the_title(); ?></a></h3>
						</div>
					</article>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		</div>
		<?php endif; ?>

		<div class="mt-10">
			<?php if ( comments_open() || get_comments_number() ) : comments_template(); endif; ?>
		</div>
	</div>
</article>
<?php endwhile; get_footer(); ?>
