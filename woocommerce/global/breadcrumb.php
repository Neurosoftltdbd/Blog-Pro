<?php
/**
 * WooCommerce Breadcrumb Template — Tailwind styling.
 *
 * WC core (woocommerce_breadcrumb()) builds the trail and passes it to
 * this template as $breadcrumb, along with wrap/delimiter args from the
 * woocommerce_breadcrumb_defaults filter. We honour those args so
 * plugins that filter the trail or delimiters keep working, and render
 * the wrapper with Tailwind classes ourselves.
 *
 * @see woocommerce_breadcrumb()
 * @var array $breadcrumb
 * @var string $delimiter
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( empty( $breadcrumb ) ) return;

$delimiter = isset( $delimiter ) ? $delimiter : '&nbsp;/&nbsp;';
?>

<nav class="woocommerce-breadcrumb mb-6 text-sm text-gray-500" aria-label="<?php esc_attr_e( 'Breadcrumb', 'blog-pro' ); ?>">
	<ol class="flex items-center flex-wrap gap-1.5 m-0 p-0 list-none">
		<?php
		$count = count( $breadcrumb );
		foreach ( $breadcrumb as $key => $crumb ) :
			$is_last = ( $count === $key + 1 );
			?>
			<li class="flex items-center gap-1.5">
				<?php if ( ! empty( $crumb[1] ) && ! $is_last ) : ?>
					<a href="<?php echo esc_url( $crumb[1] ); ?>" class="text-indigo-600 hover:text-indigo-800 font-medium no-underline transition-colors"><?php echo esc_html( $crumb[0] ); ?></a>
					<span class="text-gray-300 select-none" aria-hidden="true">/</span>
				<?php else : ?>
					<span class="text-gray-700 font-medium" <?php echo $is_last ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $crumb[0] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</nav>
