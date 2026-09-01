<?php
/**
 * Single product review item — Tailwind card.
 *
 * comment_class() + #comment-N ids are kept (WP comment threading +
 * the "awaiting approval" state target them).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<li <?php comment_class( 'blogpro-review py-5 first:pt-0 last:pb-0' ); ?> id="li-comment-<?php comment_ID(); ?>">

	<div id="comment-<?php comment_ID(); ?>" class="comment_container flex items-start gap-4">

		<?php
		/**
		 * The woocommerce_review_before hook
		 * @hooked woocommerce_review_display_gravatar - 10
		 */
		do_action( 'woocommerce_review_before', $comment );
		?>

		<div class="comment-text flex-1 min-w-0">

			<?php
			/**
			 * The woocommerce_review_before_comment_meta hook.
			 * @hooked woocommerce_review_display_rating - 10
			 */
			do_action( 'woocommerce_review_before_comment_meta', $comment );

			/**
			 * The woocommerce_review_meta hook.
			 * @hooked woocommerce_review_display_meta - 10
			 */
			do_action( 'woocommerce_review_meta', $comment );

			do_action( 'woocommerce_review_before_comment_text', $comment );

			/**
			 * The woocommerce_review_comment_text hook
			 * @hooked woocommerce_review_display_comment_text - 10
			 */
			do_action( 'woocommerce_review_comment_text', $comment );

			do_action( 'woocommerce_review_after_comment_text', $comment );
			?>

		</div>
	</div>
