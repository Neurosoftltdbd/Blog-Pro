<?php
/**
 * Widget: Related Posts.
 * Shows other posts from the same categories as the current post.
 * Renders nothing on non-singular views.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class BlogPro_Related_Posts_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'blogpro_related_posts',
			__( 'Blog Pro: Related Posts', 'blog-pro' ),
			array( 'description' => __( 'Posts from the same categories as the current post.', 'blog-pro' ) )
		);
	}

	public function widget( $args, $instance ) {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Related Posts', 'blog-pro' );
		$count = min( 10, ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5 );

		$q = blogpro_related_posts( get_queried_object_id(), $count );
		if ( ! $q->have_posts() ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title, $instance, $this->id_base ) ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<ul class="space-y-4 list-none p-0 m-0">
			<?php while ( $q->have_posts() ) : $q->the_post(); ?>
				<li class="flex gap-3 items-start">
					<a href="<?php the_permalink(); ?>" class="shrink-0 w-16 h-16 rounded-lg overflow-hidden block bg-gray-100">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php echo blogpro_responsive_img( get_post_thumbnail_id(), array( 'class' => 'w-full h-full object-cover', 'sizes' => '64px' ) ); ?>
						<?php endif; ?>
					</a>
					<div class="min-w-0 flex-1">
						<h4 class="text-sm font-semibold text-gray-900 leading-snug mb-1 line-clamp-2">
							<a href="<?php the_permalink(); ?>" class="text-gray-900 hover:text-indigo-600 transition-colors no-underline"><?php the_title(); ?></a>
						</h4>
						<span class="text-xs text-gray-500"><?php echo esc_html( get_the_date() ); ?></span>
					</div>
				</li>
			<?php endwhile; wp_reset_postdata(); ?>
		</ul>
		<?php
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$count = isset( $instance['count'] ) ? absint( $instance['count'] ) : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'blog-pro' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Number of posts (max 10):', 'blog-pro' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" min="1" max="10" value="<?php echo esc_attr( $count ); ?>">
		</p>
		<p class="description"><?php esc_html_e( 'Only shows on single posts.', 'blog-pro' ); ?></p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title' => sanitize_text_field( $new_instance['title'] ?? '' ),
			'count' => min( 10, absint( $new_instance['count'] ?? 5 ) ),
		);
	}
}
