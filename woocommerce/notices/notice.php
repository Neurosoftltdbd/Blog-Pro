<?php
/**
 * Info / notice messages — Tailwind styling.
 *
 * The class `woocommerce-info` is kept — WC's frontend JS uses it to
 * add a dismiss button and animate the notice out.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! $notices ) return;
?>

<?php foreach ( $notices as $notice ) : ?>
	<div class="woocommerce-info rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 mb-4 text-sm text-indigo-800"<?php echo wc_get_notice_data_attr( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> role="status">
		<?php echo wc_kses_notice( $notice['notice'] ); ?>
	</div>
<?php endforeach; ?>
