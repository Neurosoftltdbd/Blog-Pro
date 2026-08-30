<?php
/**
 * Error notices — Tailwind styling.
 *
 * The class `woocommerce-error` is kept — WC's frontend JS uses it to
 * attach the dismiss button and animate the notice out.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! $notices ) return;
?>

<ul class="woocommerce-error rounded-xl border border-red-200 bg-red-50 p-4 mb-4" role="alert">
	<?php foreach ( $notices as $notice ) : ?>
		<li class="my-1.5 ml-5 list-disc text-sm text-red-800"<?php echo wc_get_notice_data_attr( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php echo wc_kses_notice( $notice['notice'] ); ?>
		</li>
	<?php endforeach; ?>
</ul>
