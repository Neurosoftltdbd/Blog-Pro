<?php
/**
 * Single product reviews tab — Tailwind styling.
 *
 * Structure preserved from stock: #reviews, #comments, .commentlist,
 * #review_form_wrapper — WC's review JS + plugins target these. The
 * review list itself renders via single-product/review.php (also
 * overridden in this theme).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

if ( ! $product || ! comments_open() ) return;
?>
<div id="reviews" class="woocommerce-Reviews">
	<div id="comments">
		<h2 class="woocommerce-Reviews-title text-lg font-semibold text-gray-900 mb-4">
			<?php
			$count = $product->get_review_count();
			if ( $count && wc_review_ratings_enabled() ) {
				/* translators: 1: reviews count 2: product name */
				$reviews_title = sprintf( esc_html( _n( '%1$s review for %2$s', '%1$s reviews for %2$s', $count, 'woocommerce' ) ), esc_html( $count ), '<span class="text-indigo-600">' . get_the_title() . '</span>' );
				echo apply_filters( 'woocommerce_reviews_title', $reviews_title, $count, $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				esc_html_e( 'Reviews', 'woocommerce' );
			}
			?>
		</h2>

		<?php if ( have_comments() ) : ?>
			<ol class="commentlist divide-y divide-gray-100 m-0 p-0 list-none">
				<?php wp_list_comments( apply_filters( 'woocommerce_product_review_list_args', array( 'callback' => 'woocommerce_comments' ) ) ); ?>
			</ol>

			<?php
			if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) :
				echo '<nav class="woocommerce-pagination mt-6">';
				paginate_comments_links(
					apply_filters(
						'woocommerce_comment_pagination_args',
						array(
							'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
							'next_text' => is_rtl() ? '&larr;' : '&rarr;',
							'type'      => 'list',
						)
					)
				);
				echo '</nav>';
			endif;
			?>
		<?php else : ?>
			<p class="woocommerce-noreviews text-sm text-gray-500 italic"><?php esc_html_e( 'There are no reviews yet.', 'woocommerce' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( get_option( 'woocommerce_review_rating_verification_required' ) === 'no' || wc_customer_bought_product( '', get_current_user_id(), $product->get_id() ) ) : ?>
		<div id="review_form_wrapper" class="mt-8 pt-6 border-t border-gray-100">
			<div id="review_form">
				<?php
				$commenter    = wp_get_current_commenter();
				$comment_form = array(
					/* translators: %s is product title */
					'title_reply'         => have_comments() ? esc_html__( 'Add a review', 'woocommerce' ) : sprintf( esc_html__( 'Be the first to review &ldquo;%s&rdquo;', 'woocommerce' ), get_the_title() ),
					/* translators: %s is product title */
					'title_reply_to'      => esc_html__( 'Leave a Reply to %s', 'woocommerce' ),
					'title_reply_before'  => '<h3 id="reply-title" class="comment-reply-title text-base font-semibold text-gray-900 mb-4">',
					'title_reply_after'   => '</h3>',
					'comment_notes_after' => '',
					'label_submit'        => esc_html__( 'Submit', 'woocommerce' ),
					'logged_in_as'        => '',
					'comment_field'       => '',
					'class_form'          => 'blogpro-review-form',
					'class_submit'        => 'submit',
				);

				$name_email_required = (bool) get_option( 'require_name_email', 1 );
				$fields              = array(
					'author' => array(
						'label'        => __( 'Name', 'woocommerce' ),
						'type'         => 'text',
						'value'        => $commenter['comment_author'],
						'required'     => $name_email_required,
						'autocomplete' => 'name',
					),
					'email'  => array(
						'label'        => __( 'Email', 'woocommerce' ),
						'type'         => 'email',
						'value'        => $commenter['comment_author_email'],
						'required'     => $name_email_required,
						'autocomplete' => 'email',
					),
				);

				$comment_form['fields'] = array();

				foreach ( $fields as $key => $field ) {
					$field_html  = '<p class="comment-form-' . esc_attr( $key ) . ' mb-4">';
					$field_html .= '<label for="' . esc_attr( $key ) . '" class="block text-sm font-medium text-gray-700 mb-1.5">' . esc_html( $field['label'] );

					if ( $field['required'] ) {
						$field_html .= '&nbsp;<span class="text-red-500" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__( 'Required', 'woocommerce' ) . '</span>';
					}

					$field_html .= '</label>';
					$field_html .= '<input class="block w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . esc_attr( $field['type'] ) . '" value="' . esc_attr( $field['value'] ) . '" size="30" ' . ( $field['required'] ? 'required' : '' ) . ' autocomplete="' . esc_attr( $field['autocomplete'] ) . '" />';
					$field_html .= '</p>';

					$comment_form['fields'][ $key ] = $field_html;
				}

				$account_page_url = wc_get_page_permalink( 'myaccount' );
				if ( $account_page_url ) {
					/* translators: %1$s opening and %2$s closing link tags */
					$comment_form['must_log_in'] = '<p class="must-log-in text-sm text-gray-600">' . sprintf( esc_html__( 'You must be %1$slogged in%2$s to post a review.', 'woocommerce' ), '<a href="' . esc_url( $account_page_url ) . '" class="text-indigo-600 font-medium hover:text-indigo-800 no-underline">', '</a>' ) . '</p>';
				}

				$comment_form['comment_field'] = '';
				if ( wc_review_ratings_enabled() ) {
					$rating_required = wc_review_ratings_required() ? '&nbsp;<span class="text-red-500" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__( 'Required', 'woocommerce' ) . '</span>' : '';
					$comment_form['comment_field'] .= '<div class="comment-form-rating mb-4"><label for="rating" id="comment-form-rating-label" class="block text-sm font-medium text-gray-700 mb-1.5">' . esc_html__( 'Your rating', 'woocommerce' ) . $rating_required . '</label><select name="rating" id="rating"' . ( wc_review_ratings_required() ? ' required' : '' ) . ' class="rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"><option value="">' . esc_html__( 'Rate…', 'woocommerce' ) . '</option><option value="5">' . esc_html__( 'Perfect', 'woocommerce' ) . '</option><option value="4">' . esc_html__( 'Good', 'woocommerce' ) . '</option><option value="3">' . esc_html__( 'Average', 'woocommerce' ) . '</option><option value="2">' . esc_html__( 'Not that bad', 'woocommerce' ) . '</option><option value="1">' . esc_html__( 'Very poor', 'woocommerce' ) . '</option></select></div>';
				}

				$comment_form['comment_field'] .= '<p class="comment-form-comment mb-4"><label for="comment" class="block text-sm font-medium text-gray-700 mb-1.5">' . esc_html__( 'Your review', 'woocommerce' ) . '&nbsp;<span class="text-red-500" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__( 'Required', 'woocommerce' ) . '</span></label><textarea id="comment" class="block w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none" name="comment" cols="45" rows="5" required></textarea></p>';

				comment_form( apply_filters( 'woocommerce_product_review_comment_form_args', $comment_form ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
