<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<div class="max-w-2xl mx-auto px-4 py-24 text-center">
	<h1 class="text-6xl font-extrabold text-gray-900 mb-4">404</h1>
	<p class="text-xl text-gray-600 mb-8"><?php esc_html_e( "Sorry, the page you're looking for doesn't exist. Try searching below.", 'blog-pro' ); ?></p>
	<div class="max-w-md mx-auto"><?php get_search_form(); ?></div>
	<p class="mt-8"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="text-indigo-600 hover:text-indigo-800 font-semibold transition-colors">&larr; <?php esc_html_e( 'Back to homepage', 'blog-pro' ); ?></a></p>
</div>
<?php get_footer(); ?>
