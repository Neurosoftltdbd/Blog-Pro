<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<form role="search" method="get" class="flex gap-3" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="sr-only" for="s"><?php esc_html_e( 'Search for:', 'blog-pro' ); ?></label>
	<input type="search" id="s" name="s" placeholder="<?php esc_attr_e( 'Search…', 'blog-pro' ); ?>" value="<?php echo get_search_query(); ?>" class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
	<button type="submit" class="px-5 py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition-colors cursor-pointer whitespace-nowrap"><?php esc_html_e( 'Search', 'blog-pro' ); ?></button>
</form>
