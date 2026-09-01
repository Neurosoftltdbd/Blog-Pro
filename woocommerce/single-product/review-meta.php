<?php
/**
 * Review meta line — Tailwind styling.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $comment;
$verified = wc_review_is_from_verified_owner( $comment->comment_ID );

if ( '0' === $comment->comment_approved ) : ?>

	<p class="meta m-0">
		<em class="woocommerce-review__awaiting-approval text-sm text-amber-600">
			<?php esc_html_e( 'Your review is awaiting approval', 'woocommerce' ); ?>
		</em>
	</p>

<?php else : ?>

	<p class="meta m-0 flex flex-wrap items-center gap-x-2 text-sm text-gray-500">
		<strong class="woocommerce-review__author text-gray-900 font-semibold"><?php comment_author(); ?></strong>
		<?php
		if ( 'yes' === get_option( 'woocommerce_review_rating_verification_label' ) && $verified ) {
			echo '<em class="woocommerce-review__verified verified not-italic inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-xs font-semibold text-emerald-700">(' . esc_attr__( 'verified owner', 'woocommerce' ) . ')</em>';
		}
		?>
		<span class="woocommerce-review__dash text-gray-300" aria-hidden="true">&ndash;</span>
		<time class="woocommerce-review__published-date" datetime="<?php echo esc_attr( get_comment_date( 'c' ) ); ?>"><?php echo esc_html( get_comment_date( wc_date_format() ) ); ?></time>
	</p>

<?php endif; ?>
