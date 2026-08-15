<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
</main><!-- #main -->

<footer class="bg-gray-900 text-gray-400 mt-20">
	<div class="max-w-7xl mx-auto px-4 py-12">
		<div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-left mb-12">
			<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
				<div class="footer-column-<?php echo $i; ?>">
					<?php if ( is_active_sidebar( 'footer-' . $i ) ) : ?>
						<?php dynamic_sidebar( 'footer-' . $i ); ?>
					<?php endif; ?>
				</div>
			<?php endfor; ?>
		</div>
		<div class="copyright text-center border-t border-gray-800 pt-8">
			<p>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'blog-pro' ); ?></p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
