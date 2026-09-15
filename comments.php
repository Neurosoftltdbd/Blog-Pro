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

		<?php
		// Core emits the <ol class="comment-list"> wrapper itself (style
		// 'ol'); a manual wrapper around it would nest ol>ol — invalid.
		// Spacing/indent come from the callback's inline margin + WP's
		// built-in .comment-level-N depth classes.
		wp_list_comments( array(
			'style'       => 'ol',
			'short_ping'  => true,
			'callback'    => 'blogpro_comment_markup',
			'avatar_size' => 48,
		) );
		?>

		<div class="mt-10"><?php the_comments_pagination(); ?></div>
	<?php endif; ?>


	<?php if ( comments_open() ) : ?>

		<?php
		// Build custom fields for the form
		$commenter = wp_get_current_commenter();
		$req       = get_option( 'require_name_email' );

		$fields = array(
			'author' =>
				'<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">' .
					'<p class="[&_.comment-form-author]:m-0">' .
						'<label for="author" class="block text-sm font-semibold text-gray-700 mb-1.5">' . esc_html__( 'Name', 'blog-pro' ) . ( $req ? ' <span class="text-red-500" aria-hidden="true">*</span><span class="sr-only">' . esc_html__( '(required)', 'blog-pro' ) . '</span>' : '' ) . '</label>' .
						'<input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" placeholder="' . esc_attr__( 'Your name', 'blog-pro' ) . '" autocomplete="name" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all duration-200 placeholder:text-gray-400"' . ( $req ? ' required aria-required="true"' : '' ) . '>' .
					'</p>',

			'email' =>
					'<p class="[&_.comment-form-email]:m-0">' .
						'<label for="email" class="block text-sm font-semibold text-gray-700 mb-1.5">' . esc_html__( 'Email', 'blog-pro' ) . ( $req ? ' <span class="text-red-500" aria-hidden="true">*</span><span class="sr-only">' . esc_html__( '(required)', 'blog-pro' ) . '</span>' : '' ) . '</label>' .
						'<input id="email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" placeholder="' . esc_attr__( 'you@example.com', 'blog-pro' ) . '" autocomplete="email" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all duration-200 placeholder:text-gray-400"' . ( $req ? ' required aria-required="true"' : '' ) . '>' .
					'</p>' .
				'</div>',

			'url' =>
				'<p>' .
					'<label for="url" class="block text-sm font-semibold text-gray-700 mb-1.5">' . esc_html__( 'Website', 'blog-pro' ) . '</label>' .
					'<input id="url" name="url" type="url" value="' . esc_attr( $commenter['comment_author_url'] ) . '" placeholder="' . esc_attr__( 'https://yourwebsite.com', 'blog-pro' ) . '" autocomplete="url" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all duration-200 placeholder:text-gray-400">' .
				'</p>',
		);

		comment_form( array(
			'class_form'         => 'bg-gray-50 border border-gray-100 rounded-2xl p-8 space-y-5',
			'title_reply_before' => '<h3 id="reply-title" class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">',
			'title_reply_after'  => '</h3>',
			'title_reply'        => __( 'Leave a Comment', 'blog-pro' ),
			'comment_notes_before' => '<p class="text-sm text-gray-500 -mt-2">' . esc_html__( 'Your email address will not be published.', 'blog-pro' ) . '</p>',
			'comment_notes_after'  => '',
			'fields'             => $fields,
			'comment_field'      =>
				'<p>' .
					'<label for="comment" class="block text-sm font-semibold text-gray-700 mb-1.5">' . esc_html__( 'Comment', 'blog-pro' ) . ' <span class="text-red-500">*</span></label>' .
					'<textarea id="comment" name="comment" placeholder="' . esc_attr__( 'Share your thoughts…', 'blog-pro' ) . '" rows="5" required autocomplete="off" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all duration-200 placeholder:text-gray-400 resize-y min-h-30"></textarea>' .
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
 * Comment list markup callback — lives in inc/template-tags.php (guarded).
 * Defining it inside this template would fatal-redeclare if comments_template()
 * ever runs twice in one request.
 */

