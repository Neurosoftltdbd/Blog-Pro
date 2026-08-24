<?php
/**
 * Footer Widget Areas
 * Registers 4 widget areas displayed in the footer above the copyright.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register footer widget sidebars.
 */
function blogpro_register_footer_widgets() {
	for ( $i = 1; $i <= 4; $i++ ) {
		register_sidebar( array(
			/* translators: %d: column number */
			'name'          => sprintf( __( 'Footer Column %d', 'blog-pro' ), $i ),
			'id'            => 'footer-' . $i,
			'description'   => __( 'Widgets in this area will appear in the footer.', 'blog-pro' ),
			'before_widget' => '<div class="footer-widget text-sm [&_p]:text-sm [&_ul]:text-sm [&_h4]:text-white [&_h4]:font-semibold [&_h4]:mb-4 [&_h4]:text-2xl [&_li:hover]:ps-1 [&_li:hover]:text-gray-400 [&_li]:transition-all [&_li]:easy-in-out [&_li]:duration-500">',
			'after_widget'  => '</div>',
			'before_title'  => '<h4 class="text-white font-semibold mb-4">',
			'after_title'   => '</h4>',
		) );
	}
}
add_action( 'widgets_init', 'blogpro_register_footer_widgets' );

/**
 * Default fallback content for empty footer widget areas.
 *
 * @param int $column Column number (1-4).
 */
function blogpro_footer_default_content( $column ) {
	switch ( $column ) {
		case 1:
			?>
			<h4 class="text-white font-semibold mb-4"><?php esc_html_e( 'About Us', 'blog-pro' ); ?></h4>
			<p class="text-gray-400"><?php esc_html_e( 'We share insightful articles, tutorials and news on our blog. Stay connected for the latest updates from our team.', 'blog-pro' ); ?></p>
			<?php
			break;

		case 2:
			if ( class_exists( 'BlogPro_Categories_Widget' ) ) {
				( new BlogPro_Categories_Widget() )->widget( array(
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '<h4 class="text-white font-semibold mb-4">',
					'after_title'   => '</h4>',
				), array(
					'title'  => __( 'Categories', 'blog-pro' ),
					'counts' => 1,
				) );
			}
			break;

		case 3:
			if ( class_exists( 'BlogPro_Popular_Posts_Widget' ) ) {
				( new BlogPro_Popular_Posts_Widget() )->widget( array(
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '<h4 class="text-white font-semibold mb-4">',
					'after_title'   => '</h4>',
				), array(
					'title' => __( 'Popular Posts', 'blog-pro' ),
					'count' => 5,
				) );
			}
			break;

		case 4:
			?>
			<h4 class="text-white font-semibold mb-4"><?php esc_html_e( 'Address', 'blog-pro' ); ?></h4>
			<address class="not-italic text-gray-400">
				<?php echo esc_html( get_bloginfo( 'name' ) ); ?><br>
				<?php esc_html_e( '123 Main Street, City, Country', 'blog-pro' ); ?><br>
				<?php esc_html_e( 'Email: hello@example.com', 'blog-pro' ); ?>
			</address>
			<?php
			break;
	}
}