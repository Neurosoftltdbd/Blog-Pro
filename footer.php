<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
</main><!-- #main -->

<footer class="bg-gray-900 text-gray-400 mt-20">
	<div class="max-w-7xl mx-auto px-4 py-12 text-center">
		<p>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'blog-pro' ); ?></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
