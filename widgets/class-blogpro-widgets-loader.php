<?php
/**
 * Loads and registers all Blog Pro custom widgets.
 * Widget classes live in this folder, one per file.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function blogpro_load_widget_classes() {
	require_once BLOGPRO_DIR . '/widgets/class-blogpro-recent-posts-widget.php';
	require_once BLOGPRO_DIR . '/widgets/class-blogpro-related-posts-widget.php';
	require_once BLOGPRO_DIR . '/widgets/class-blogpro-popular-posts-widget.php';
	require_once BLOGPRO_DIR . '/widgets/class-blogpro-categories-widget.php';
}
add_action( 'widgets_init', 'blogpro_load_widget_classes' );

function blogpro_register_widgets() {
	register_widget( 'BlogPro_Recent_Posts_Widget' );
	register_widget( 'BlogPro_Related_Posts_Widget' );
	register_widget( 'BlogPro_Popular_Posts_Widget' );
	register_widget( 'BlogPro_Categories_Widget' );
}
add_action( 'widgets_init', 'blogpro_register_widgets', 99 );
