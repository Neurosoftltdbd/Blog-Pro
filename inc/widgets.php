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
			'before_widget' => '<div class="footer-widget text-sm [&_p]:text-sm [&_ul]:text-sm [&_h4]:text-white [&_h4]:font-semibold [&_h4]:mb-4 [&_h4]:text-2xl [&_li:hover]:ps-2 [&_li:hover]:font-bold [&_li:hover]:text-gray-400 [&_li]:transition-all [&_li]:easy-in-out [&_li]:duration-500">',
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
			$recent_posts = wp_get_recent_posts( array(
				'numberposts' => 5,
				'post_status' => 'publish',
			) );
			if ( $recent_posts ) :
				?>
				<h4 class="text-white font-semibold mb-4"><?php esc_html_e( 'Latest Posts', 'blog-pro' ); ?></h4>
				<ul class="space-y-2">
					<?php foreach ( $recent_posts as $post ) : ?>
						<li class="transition-all duration-500 ease-in-out hover:ps-2 hover:font-bold">
							<a class="text-gray-400 hover:text-white" href="<?php echo esc_url( get_permalink( $post['ID'] ) ); ?>"><?php echo esc_html( $post['post_title'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php
			endif;
			break;

		case 3:
			?>
			<h4 class="text-white font-semibold mb-4"><?php esc_html_e( 'Categories', 'blog-pro' ); ?></h4>
			<ul class="space-y-2">
				<?php
				$categories = get_categories( array(
					'orderby' => 'name',
					'order'   => 'ASC',
					'hide_empty' => false,
				) );
				if ($categories) {
					foreach ( $categories as $category ) {
						echo '<li class="transition-all duration-500 ease-in-out hover:ps-2 hover:font-bold"><a class="text-gray-400 hover:text-white" href="' . esc_url( get_category_link( $category->term_id ) ) . '">' . esc_html( $category->name ) . '</a></li>';
					}
				} else {
					echo '<li>' . esc_html__( 'No categories found.', 'blog-pro' ) . '</li>';
				}
				?>
			</ul>
			<?php
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