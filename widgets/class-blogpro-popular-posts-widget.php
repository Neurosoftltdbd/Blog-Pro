<?php
/**
 * Widget: Popular Posts.
 * Sorted by comment count — a proxy for reader interest that stays
 * dependency-free on the database. Number badge + title + date.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class BlogPro_Popular_Posts_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'blogpro_popular_posts',
			__( 'Blog Pro: Popular Posts', 'blog-pro' ),
			array( 'description' => __( 'Most-commented posts with rank badges.', 'blog-pro' ) )
		);
	}

	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Popular Posts', 'blog-pro' );
		$count = min( 10, ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5 );

		$q = new WP_Query( array(
			'posts_per_page'      => $count,
			'orderby'             => 'comment_count',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		) );
		if ( ! $q->have_posts() ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title, $instance, $this->id_base ) ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<ol class="space-y-3 list-none p-0 m-0">
			<?php $i = 1; while ( $q->have_posts() ) : $q->the_post(); ?>
				<li class="flex items-start gap-3">
					<div class="min-w-0 flex-1">
						<div class="text-sm text-gray-100 leading-snug mb-1 line-clamp-2">
							<a href="<?php the_permalink(); ?>" class="text-gray-100 hover:text-indigo-200 transition-colors no-underline"><?php the_title(); ?></a>
						</div>
						<span class="text-xs text-gray-200"><?php echo esc_html( get_the_date() ); ?> &middot; <?php echo esc_html( number_format_i18n( get_comments_number() ) ); ?> <?php esc_html_e( 'comments', 'blog-pro' ); ?></span>
					</div>
				</li>
			<?php $i++; endwhile; wp_reset_postdata(); ?>
		</ol>
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
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title' => sanitize_text_field( $new_instance['title'] ?? '' ),
			'count' => min( 10, absint( $new_instance['count'] ?? 5 ) ),
		);
	}
}
