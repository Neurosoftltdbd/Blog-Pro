<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( post_password_required() ) return;
?>

<div class="max-w-4xl mx-auto my-12 px-0">

	<?php if ( have_comments() ) : ?>
		<div class="flex items-center gap-4 mb-8">
			<h2 class="text-2xl font-bold text-gray-900">
				<?php
				$count = get_comments_number();
				printf( esc_html( _n( '%s Comment', '%s Comments', $count, 'blog-pro' ) ), esc_html( number_format_i18n( $count ) ) );
				?>
			</h2>
			<span class="h-px flex-1 bg-linear-to-r from-gray-200 to-transparent"></span>
		</div>

		<ul class="list-none p-0 m-0 space-y-8">
			<?php
			wp_list_comments( array(
				'style'       => 'ol',
				'short_ping'  => true,
				'callback'    => 'blogpro_comment_markup',
				'avatar_size' => 48,
			) );
			?>
		</ul>

		<div class="mt-10"><?php the_comments_pagination(); ?></div>
	<?php endif; ?>


	<?php if ( comments_open() ) : ?>

		<?php
		// Build custom fields for the form
		$commenter = wp_get_current_commenter();
		$req       = get_option( 'require_name_email' );
		$aria_req  = $req ? " required" : '';
		$html_req  = $req ? " required" : '';

		$fields = array(
			'author' =>
				'<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">' .
					'<p class="[&_.comment-form-author]:m-0">' .
						'<label for="author" class="block text-sm font-semibold text-gray-700 mb-1.5">' . esc_html__( 'Name', 'blog-pro' ) . ( $req ? ' <span class="text-red-500">*</span>' : '' ) . '</label>' .
						'<input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" placeholder="' . esc_attr__( 'Your name', 'blog-pro' ) . '" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all duration-200 placeholder:text-gray-400"' . $aria_req . $html_req . '>' .
					'</p>',

			'email' =>
					'<p class="[&_.comment-form-email]:m-0">' .
						'<label for="email" class="block text-sm font-semibold text-gray-700 mb-1.5">' . esc_html__( 'Email', 'blog-pro' ) . ( $req ? ' <span class="text-red-500">*</span>' : '' ) . '</label>' .
						'<input id="email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" placeholder="' . esc_attr__( 'you@example.com', 'blog-pro' ) . '" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all duration-200 placeholder:text-gray-400"' . $aria_req . $html_req . '>' .
					'</p>' .
				'</div>',

			'url' =>
				'<p>' .
					'<label for="url" class="block text-sm font-semibold text-gray-700 mb-1.5">' . esc_html__( 'Website', 'blog-pro' ) . '</label>' .
					'<input id="url" name="url" type="url" value="' . esc_attr( $commenter['comment_author_url'] ) . '" placeholder="' . esc_attr__( 'https://yourwebsite.com', 'blog-pro' ) . '" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all duration-200 placeholder:text-gray-400">' .
				'</p>',
		);

		comment_form( array(
			'class_form'         => 'bg-gray-50 border border-gray-100 rounded-2xl p-8 space-y-5',
			'title_reply_before' => '<h2 id="reply-title" class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">',
			'title_reply_after'  => '</h2>',
			'title_reply'        => __( 'Leave a Comment', 'blog-pro' ),
			'comment_notes_before' => '<p class="text-sm text-gray-500 -mt-2">' . esc_html__( 'Your email address will not be published.', 'blog-pro' ) . '</p>',
			'comment_notes_after'  => '',
			'fields'             => $fields,
			'comment_field'      =>
				'<p>' .
					'<label for="comment" class="block text-sm font-semibold text-gray-700 mb-1.5">' . esc_html__( 'Comment', 'blog-pro' ) . ' <span class="text-red-500">*</span></label>' .
					'<textarea id="comment" name="comment" placeholder="' . esc_attr__( 'Share your thoughts…', 'blog-pro' ) . '" rows="5" required class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all duration-200 placeholder:text-gray-400 resize-y min-h-30"></textarea>' .
				'</p>',
			'label_submit'       => __( 'Post Comment', 'blog-pro' ),
			'class_submit'       => 'inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 active:bg-indigo-800 transition-all duration-200 cursor-pointer shadow-sm hover:shadow-md',
			'cancel_reply_link'  => esc_html__( 'Cancel reply', 'blog-pro' ),
			'class_cancel'       => 'ml-3 text-sm text-gray-500 hover:text-red-600 transition-colors no-underline',
		) );
		?>

	<?php else : ?>
		<div class="bg-gray-50 border border-gray-100 rounded-2xl p-8 text-center">
			<p class="text-gray-600 font-medium"><?php esc_html_e( 'Comments are closed.', 'blog-pro' ); ?></p>
		</div>
	<?php endif; ?>
</div>


<?php
/**
 * Custom comment markup.
 */
function blogpro_comment_markup( $comment, $args, $depth ) {
	?>
	<li <?php comment_class( 'border-b border-gray-100 pb-8 last:border-b-0 last:pb-0' ); ?> id="comment-<?php comment_ID(); ?>">
		<div class="flex gap-4">
			<div class="shrink-0">
				<?php echo get_avatar( $comment, 48, '', '', array( 'class' => 'rounded-full ring-2 ring-white shadow-sm' ) ); ?>
			</div>
			<div class="flex-1 min-w-0">
				<div class="flex flex-wrap items-center gap-x-3 gap-y-1">
					<span class="font-bold text-gray-900 text-[15px]"><?php comment_author(); ?></span>
					<span class="text-sm text-gray-400"><?php comment_date(); ?></span>
					<?php edit_comment_link( __( 'Edit', 'blog-pro' ), '<span class="text-xs text-indigo-500">', '</span>' ); ?>
				</div>
				<div class="mt-2 text-gray-700 leading-relaxed text-sm [&_p]:mb-3 [&_p:last-child]:mb-0"><?php comment_text(); ?></div>
				<div class="mt-3">
					<?php
					comment_reply_link( array_merge( $args, array(
						'depth'      => $depth,
						'max_depth'  => $args['max_depth'],
						'reply_text' => '<span class="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">' .
										'<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>' .
										__( 'Reply', 'blog-pro' ) .
									   '</span>',
					) ) );
					?>
				</div>
			</div>
		</div>
	<?php
}
