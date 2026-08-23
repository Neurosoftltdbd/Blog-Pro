<?php
/**
 * Home page: hero, featured posts, recent posts grid.
 *
 * If the static front page has any page template selected (block or PHP)
 * it is rendered instead of this classic one. With no template selected
 * the default front-page content below is used.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$front_page_id = (int) get_option( 'page_on_front' );
$selected      = $front_page_id ? get_page_template_slug( $front_page_id ) : '';

if ( $selected ) {
	global $_wp_current_template_id, $_wp_current_template_content;

	// Load a block template file directly so any Site Editor DB
	// customization does not replace the template shipped with the theme.
	$template_file = _get_block_template_file( 'wp_template', $selected );

	if ( $template_file && ! empty( $template_file['path'] ) ) {
		$block_template = _build_block_template_result_from_file( $template_file, 'wp_template' );

		$_wp_current_template_id      = $block_template->id;
		$_wp_current_template_content = $block_template->content;

		get_header();
		echo get_the_block_template_html();
		get_footer();
		return;
	}

	// Otherwise include a PHP page template if one is selected.
	if ( 0 === validate_file( $selected ) ) {
		$php_template = locate_template( array( $selected ) );
		if ( $php_template ) {
			include $php_template;
			return;
		}
	}
}

get_header();
?>
<!--hero-->
<section id="hero" class="w-full bg-white">
	<div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8 py-12 px-2 md:px-0">
		<!-- Left Side: Slider (5 random posts) -->
		<div class="lg:col-span-2 relative">
			<?php
			$slider_query = new WP_Query( array(
				'post_type'      => 'post',
				'posts_per_page' => 5,
				'orderby'        => 'rand',
				'no_found_rows'  => true
			) );
			if ( $slider_query->have_posts() ) :
			?>
			<div class="relative w-full h-60 md:h-125 overflow-hidden rounded-lg bg-gray-100" id="heroSlider">
				<?php $slide_idx = 0; while ( $slider_query->have_posts() ) : $slider_query->the_post(); $is_active = $slide_idx === 0; ?>
				<div class="slide absolute inset-0 w-full h-full opacity-0 z-1 transition-opacity duration-500 " style="opacity: <?php echo $is_active ? 1 : 0; ?>; z-index: <?php echo $is_active ? 2 : 1; ?>;">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php echo blogpro_responsive_img( get_post_thumbnail_id(), array( 'class' => 'w-full h-full object-cover', 'sizes' => '(max-width: 1024px) 100vw, 66vw' ) ); ?>
					<?php else : ?>
						<div class="w-full h-full bg-linear-to-br from-indigo-100 to-purple-100"></div>
					<?php endif; ?>
					<div class="absolute inset-0 flex flex-col justify-end p-8 bg-linear-to-t from-black via-black/50 to-transparent text-white">
						<span class="inline-block px-3 py-1 bg-indigo-600 text-white text-sm font-semibold rounded-full mb-3 w-fit"><?php $cats = get_the_category(); if($cats) echo esc_html($cats[0]->name); ?></span>
						<h2 class="text-lg md:text-3xl font-bold leading-tight line-clamp-2 md:line-clamp-none"><a href="<?php the_permalink(); ?>" class="text-white hover:text-indigo-200 transition-colors no-underline"><?php the_title(); ?></a></h2>
						<div class="text-sm text-white/70 mt-2"><?php echo esc_html( get_the_date() ); ?></div>
					</div>
				</div>
				<?php $slide_idx++; endwhile; wp_reset_postdata(); ?>
			</div>
			<div class="flex gap-3 mt-5 justify-center">
				<button id="prevSlide" aria-label="Previous Slide" class="w-10 h-10 rounded-full bg-white border border-gray-200 text-gray-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all flex items-center justify-center shadow-sm cursor-pointer">&larr;</button>
				<button id="nextSlide" aria-label="Next Slide" class="w-10 h-10 rounded-full bg-white border border-gray-200 text-gray-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all flex items-center justify-center shadow-sm cursor-pointer">&rarr;</button>
			</div>
			<?php endif; ?>
		</div>

		<!-- Right Side: List (5 random posts) -->
		<div class="lg:col-span-1">
			<h3 class="text-xl font-bold text-gray-900 mb-6"><?php esc_html_e('Must Read', 'blog-pro'); ?></h3>
			<?php
			$list_query = new WP_Query( array(
				'post_type'      => 'post',
				'posts_per_page' => 5,
				'orderby'        => 'rand',
				'no_found_rows'  => true
			) );
			if ( $list_query->have_posts() ) :
			?>
			<ul class="space-y-5 list-none p-0 m-0">
				<?php while ( $list_query->have_posts() ) : $list_query->the_post(); ?>
				<li class="flex gap-4 items-start">
					<a href="<?php the_permalink(); ?>" class="shrink-0 w-20 h-20 rounded-xl overflow-hidden bg-gray-100 block">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php echo blogpro_responsive_img( get_post_thumbnail_id(), array( 'class' => 'w-full h-full object-cover hover:scale-105 transition-all easy-in-out duration-300', 'sizes' => '80px' ) ); ?>
						<?php else : ?>
							<div class="w-full h-full bg-gray-200"></div>
						<?php endif; ?>
					</a>
					<div class="flex-1 min-w-0">
						<h4 class="text-sm font-semibold text-gray-900 leading-snug mb-1 line-clamp-2"><a href="<?php the_permalink(); ?>" class="text-gray-900 hover:text-indigo-600 transition-colors no-underline"><?php the_title(); ?></a></h4>
						<span class="text-xs text-gray-500"><?php echo esc_html( get_the_date() ); ?></span>
					</div>
				</li>
				<?php endwhile; wp_reset_postdata(); ?>
			</ul>
			<?php endif; ?>
		</div>
	</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
	const slider = document.getElementById('heroSlider');
	if (!slider) return;
	const slides = slider.querySelectorAll('.slide');
	const prevBtn = document.getElementById('prevSlide');
	const nextBtn = document.getElementById('nextSlide');
	let currentSlide = 0;

	if(slides.length === 0) return;

	function showSlide(index) {
		slides.forEach((slide, i) => {
			if (i === index) {
				slide.classList.add('active');
				slide.style.opacity = '1';
				slide.style.zIndex = '2';
			} else {
				slide.classList.remove('active');
				slide.style.opacity = '0';
				slide.style.zIndex = '1';
			}
		});
	}

	function nextSlideFn() {
		currentSlide = (currentSlide + 1) % slides.length;
		showSlide(currentSlide);
	}

	function prevSlideFn() {
		currentSlide = (currentSlide - 1 + slides.length) % slides.length;
		showSlide(currentSlide);
	}

	if(nextBtn) nextBtn.addEventListener('click', nextSlideFn);
	if(prevBtn) prevBtn.addEventListener('click', prevSlideFn);

	// Initialize
	showSlide(0);

	// Auto slide
	setInterval(nextSlideFn, 5000);
});
</script>
</section>


<!--featured-->
<section id="featured" class="max-w-7xl mx-auto px-4 md:px-0">
	<?php
	$featured = blogpro_featured_query( 4 );
	if ( $featured->have_posts() ) :
	?>
	<h2 class="text-2xl md:text-3xl font-bold text-gray-900 mt-16 mb-8"><?php esc_html_e( 'Featured Posts', 'blog-pro' ); ?></h2>
	<div class="grid grid-cols-1 md:grid-cols-4 gap-8">
		<?php while ( $featured->have_posts() ) : $featured->the_post(); ?>
			<article <?php post_class( 'flex flex-col bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-shadow duration-300' ); ?>>
				<a class="aspect-video bg-gray-100 block overflow-hidden" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
					<?php if ( has_post_thumbnail() ) : echo blogpro_responsive_img( get_post_thumbnail_id(), array( 'alt' => esc_attr( get_the_title() ), 'class' => 'w-full h-full object-cover hover:scale-105 transition-all easy-in-out duration-300', 'sizes' => '(max-width: 768px) 100vw, 50vw' ) ); else : ?>
						<div class="w-full h-full bg-gray-100"></div>
					<?php endif; ?>
				</a>
				<div class="p-4">
					<div class="text-sm text-gray-500 mb-2"><?php echo esc_html( get_the_date() ); ?> &middot; <?php echo esc_html( blogpro_reading_time() ); ?></div>
					<h3 class="text-sm font-bold text-gray-900 leading-snug line-clamp-2"><a href="<?php the_permalink(); ?>" class="hover:text-indigo-600 transition-colors no-underline"><?php the_title(); ?></a></h3>
					
				</div>
			</article>
		<?php endwhile; wp_reset_postdata(); ?>
	</div>
	<?php endif; ?>
</section>


<!-- Category wise post -->
<section id="category-posts" class="max-w-7xl mx-auto px-4 md:px-0">
<?php
$categories = get_categories( array(
	'orderby'    => 'count',
	'order'      => 'DESC',
	'number'     => 6,
	'hide_empty' => true,
	'exclude'    => array( get_option( 'default_category' ) ),
) );

if ( $categories ) :
	foreach ( $categories as $cat ) :
		$cat_query = new WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => 4,
			'cat'            => $cat->term_id,
			'no_found_rows'  => true,
		) );

		if ( ! $cat_query->have_posts() ) :
			wp_reset_postdata();
			continue;
		endif;
?>
	<div class="mt-16">
		<div class="flex items-center justify-between mb-6">
			<h2 class="text-2xl md:text-3xl font-bold text-gray-900">
				<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" class="no-underline text-gray-900 hover:text-indigo-600 transition-colors">
					<?php echo esc_html( $cat->name ); ?>
				</a>
			</h2>
			<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition-colors no-underline">
				<?php esc_html_e( 'View All →', 'blog-pro' ); ?>
			</a>
		</div>
		<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
			<?php while ( $cat_query->have_posts() ) : $cat_query->the_post(); ?>
				<article <?php post_class( 'flex flex-col bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-shadow duration-300' ); ?>>
					<a class="aspect-video bg-gray-100 block overflow-hidden" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php echo blogpro_responsive_img( get_post_thumbnail_id(), array( 'alt' => esc_attr( get_the_title() ), 'class' => 'w-full h-full object-cover hover:scale-105 transition-all easy-in-out duration-300', 'sizes' => '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw' ) ); ?>
						<?php else : ?>
							<div class="w-full h-full bg-gray-100"></div>
						<?php endif; ?>
					</a>
					<div class="p-4 flex flex-col flex-1">
						<div class="text-sm text-gray-500 mb-2"><?php echo esc_html( get_the_date() ); ?> &middot; <?php echo esc_html( blogpro_reading_time() ); ?></div>
						<h3 class="text-sm font-bold text-gray-900 leading-snug line-clamp-2"><a href="<?php the_permalink(); ?>" class="hover:text-indigo-600 transition-colors no-underline"><?php the_title(); ?></a></h3>
						<p class="text-sm text-gray-600 mt-2 line-clamp-2 flex-1"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
						<div class="mt-3">
							<a href="<?php the_permalink(); ?>" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors no-underline"><?php esc_html_e( 'Read More', 'blog-pro' ); ?> →</a>
						</div>
					</div>
				</article>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
	</div>
<?php
	endforeach;
endif;
?>
</section>
<div class="w-full text-center py-12">
    <a href="<?php echo esc_url( home_url("/blog/") ); ?>" class="button-primary px-6 py-3 rounded-full font-semibold">Read All Posts</a>
</div>
<?php get_footer(); ?>
