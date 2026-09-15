<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
</main><!-- #main -->

<a href="#main" id="blogpro-back-top" class="fixed bottom-6 right-6 z-40 w-12 h-12 rounded-full bg-indigo-600 text-white shadow-lg flex items-center justify-center opacity-0 pointer-events-none translate-y-2 transition-all duration-300 hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-indigo-500 no-underline" aria-label="<?php esc_attr_e( 'Back to top', 'blog-pro' ); ?>" tabindex="-1" hidden>
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5" aria-hidden="true"><path d="M12 19V5m-7 7l7-7 7 7"/></svg>
	<span class="sr-only"><?php esc_html_e( 'Back to top', 'blog-pro' ); ?></span>
</a>
<div id="blogpro-scroll-progress" class="fixed top-0 left-0 right-0 z-60 h-0.5 bg-transparent" aria-hidden="true" hidden>
	<div id="blogpro-scroll-progress-bar" class="h-full w-0 bg-indigo-600"></div>
</div>

<footer class="w-full bg-gray-900 text-gray-200">
	<div class="w-full max-w-7xl mx-auto px-4 py-12">
		<div class="grid grid-cols-1 md:grid-cols-4 gap-12 text-left mb-12">
			<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
				<div class="footer-column-<?php echo $i; ?>">
					<?php
					if ( is_active_sidebar( 'footer-' . $i ) ) {
						dynamic_sidebar( 'footer-' . $i );
					} else {
						if ( function_exists( 'blogpro_footer_default_content' ) ) {
							blogpro_footer_default_content( $i );
						}
					}
					?>
				</div>
			<?php endfor; ?>
		</div>
		<div class="copyright text-center border-t border-gray-800 text-white pt-8">
			<p>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'blog-pro' ); ?></p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
