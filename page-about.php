<?php
/**
 * Template Name: About Page
 * Assign this to your "About" page in the WordPress editor.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
while ( have_posts() ) : the_post();
?>
<div class="max-w-4xl mx-auto px-4 py-12">
	<?php blogpro_breadcrumbs(); ?>
	<h1 class="text-3xl md:text-4xl font-bold text-gray-900 mt-8 mb-6"><?php the_title(); ?></h1>
	<div class="prose prose-lg prose-indigo max-w-none text-gray-800 leading-relaxed">
		<?php the_content(); ?>
	</div>
</div>
<?php endwhile; get_footer(); ?>
