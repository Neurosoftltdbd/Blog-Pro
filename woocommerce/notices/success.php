<?php
/**
 * Success notices — Tailwind styling.
 *
 * The class `woocommerce-message` is kept — WC's frontend JS uses it to
 * attach the dismiss button and animate the notice out.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! $notices ) return;
?>

<?php foreach ( $notices as $notice ) : ?>
	<div class="woocommerce-message rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 mb-4 text-sm text-emerald-800"<?php echo wc_get_notice_data_attr( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> role="alert">
		<?php echo wc_kses_notice( $notice['notice'] ); ?>
	</div>
<?php endforeach; ?>
