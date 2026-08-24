<?php
/**
 * Widget: Categories.
 * Category list with post-count pills, optionally as dropdown.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class BlogPro_Categories_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'blogpro_categories',
			__( 'Blog Pro: Categories', 'blog-pro' ),
			array( 'description' => __( 'Category list with post counts.', 'blog-pro' ) )
		);
	}

	public function widget( $args, $instance ) {
		$title    = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Categories', 'blog-pro' );
		$dropdown = ! empty( $instance['dropdown'] );
		$counts   = ! empty( $instance['counts'] );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title, $instance, $this->id_base ) ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( $dropdown ) {
			wp_dropdown_categories( array(
				'name'            => 'blogpro-cat-dropdown',
				'show_option_none' => __( 'Select a category…', 'blog-pro' ),
				'option_none_value' => '',
				'value_field'     => 'slug',
				'show_count'      => $counts,
				'hierarchical'    => true,
			) );
		} else {
			$cats = get_categories( array(
				'orderby'    => 'count',
				'order'      => 'DESC',
				'hide_empty' => true,
			) );
			if ( ! $cats ) {
				echo '<p class="text-gray-50">' . esc_html__( 'No categories found.', 'blog-pro' ) . '</p>';
			} else {
				?>
				<ul class="space-y-2 list-none p-0 m-0">
					<?php foreach ( $cats as $cat ) : ?>
						<li>
							<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" class="flex items-center justify-between gap-2 text-gray-50 hover:text-indigo-200 transition-colors no-underline py-1">
								<span><?php echo esc_html( $cat->name ); ?></span>
								<?php if ( $counts ) : ?>
									<span class="shrink-0 text-xs font-semibold bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full"><?php echo esc_html( number_format_i18n( $cat->count ) ); ?></span>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php
			}
		}
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function form( $instance ) {
		$title    = isset( $instance['title'] ) ? $instance['title'] : '';
		$dropdown = ! empty( $instance['dropdown'] );
		$counts   = ! empty( $instance['counts'] );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'blog-pro' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'dropdown' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'dropdown' ) ); ?>" value="1" <?php checked( $dropdown ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'dropdown' ) ); ?>"><?php esc_html_e( 'Show as dropdown', 'blog-pro' ); ?></label>
		</p>
		<p>
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'counts' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'counts' ) ); ?>" value="1" <?php checked( $counts ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'counts' ) ); ?>"><?php esc_html_e( 'Show post counts', 'blog-pro' ); ?></label>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title'    => sanitize_text_field( $new_instance['title'] ?? '' ),
			'dropdown' => ! empty( $new_instance['dropdown'] ) ? 1 : 0,
			'counts'   => ! empty( $new_instance['counts'] ) ? 1 : 0,
		);
	}
}
