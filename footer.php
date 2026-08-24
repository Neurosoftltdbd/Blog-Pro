<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
</main><!-- #main -->

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
