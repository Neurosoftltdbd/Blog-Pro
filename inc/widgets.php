<?php
/**
 * Footer Widget Areas
 * Registers 3 widget areas displayed in the footer above the copyright.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register footer widget sidebars.
 */
function blogpro_register_footer_widgets() {
	for ( $i = 1; $i <= 3; $i++ ) {
		register_sidebar( array(
			/* translators: %d: column number */
			'name'          => sprintf( __( 'Footer Column %d', 'blog-pro' ), $i ),
			'id'            => 'footer-' . $i,
			'description'   => __( 'Widgets in this area will appear in the footer.', 'blog-pro' ),
			'before_widget' => '<div class="footer-widget">',
			'after_widget'  => '</div>',
			'before_title'  => '<h4 class="text-white font-semibold mb-4">',
			'after_title'   => '</h4>',
		) );
	}
}
add_action( 'widgets_init', 'blogpro_register_footer_widgets' );
