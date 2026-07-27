<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( post_password_required() ) return;
?>
<div class="max-w-4xl mx-auto my-12 px-0">
	<?php if ( have_comments() ) : ?>
		<h2 class="text-2xl font-bold text-gray-900 mb-6">
			<?php
			$count = get_comments_number();
			printf( esc_html( _n( '%s Comment', '%s Comments', $count, 'blog-pro' ) ), esc_html( number_format_i18n( $count ) ) );
			?>
		</h2>
		<ul class="list-none p-0 m-0 space-y-6">
			<?php
			wp_list_comments( array(
				'style'      => 'ol',
				'short_ping' => true,
				'callback'   => 'blogpro_comment_markup',
			) );
			?>
		</ul>
		<div class="mt-6"><?php the_comments_pagination(); ?></div>
	<?php endif; ?>

	<?php if ( comments_open() ) : ?>
		<h2 class="text-2xl font-bold text-gray-900 mt-12 mb-6"><?php esc_html_e( 'Leave a Comment', 'blog-pro' ); ?></h2>
		<?php
		comment_form( array(
			'class_form'   => 'space-y-5',
			'comment_field'=> '<p><label for="comment" class="block text-sm font-semibold text-gray-700 mb-1">' . esc_html__( 'Comment', 'blog-pro' ) . ' *</label><textarea id="comment" name="comment" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all min-h-30"></textarea></p>',
			'label_submit' => __( 'Post Comment', 'blog-pro' ),
			'class_submit' => 'px-6 py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition-colors cursor-pointer',
			'title_reply'  => '',
		) );
		?>
	<?php else : ?>
		<p class="text-gray-600"><?php esc_html_e( 'Comments are closed.', 'blog-pro' ); ?></p>
	<?php endif; ?>
</div>

<?php
function blogpro_comment_markup( $comment, $args, $depth ) {
	?>
	<li <?php comment_class( 'border-b border-gray-100 pb-6 last:border-b-0' ); ?> id="comment-<?php comment_ID(); ?>">
		<div class="comment-body flex gap-4 items-start">
			<div class="shrink-0"><?php echo get_avatar( $comment, 44, '', '', array( 'class' => 'rounded-full' ) ); ?></div>
			<div class="flex-1">
				<div class="flex items-center gap-3">
					<span class="font-bold text-gray-900"><?php comment_author(); ?></span>
					<span class="text-sm text-gray-500"><?php comment_date(); ?></span>
					<?php edit_comment_link( __( 'Edit', 'blog-pro' ), '<span class="text-sm text-indigo-600">', '</span>' ); ?>
				</div>
				<div class="mt-2 text-gray-700 leading-relaxed"><?php comment_text(); ?></div>
				<div class="mt-2 text-sm font-semibold text-indigo-600"><?php comment_reply_link( array_merge( $args, array( 'depth' => $depth, 'max_depth' => $args['max_depth'] ) ) ); ?></div>
			</div>
		</div>
	<?php
}
