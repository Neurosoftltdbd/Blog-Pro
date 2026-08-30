<?php
/**
 * Single product tabs — Tailwind pill styling.
 *
 * The <a role="tab"> elements get Tailwind classes; the <ul> wrapper
 * keeps the .wc-tabs / .tabs classes that WC core JS targets.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Filter tabs and allow third parties to add their own.
 */
$product_tabs = apply_filters( 'woocommerce_product_tabs', array() );

if ( ! empty( $product_tabs ) ) : ?>

	<div class="woocommerce-tabs wc-tabs-wrapper bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6">
		<ul class="tabs wc-tabs flex flex-wrap gap-2 mb-5" role="tablist">
			<?php foreach ( $product_tabs as $key => $product_tab ) : ?>
				<li role="presentation" class="<?php echo esc_attr( $key ); ?>_tab" id="tab-title-<?php echo esc_attr( $key ); ?>">
					<a href="#tab-<?php echo esc_attr( $key ); ?>" role="tab" aria-controls="tab-<?php echo esc_attr( $key ); ?>" class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium text-gray-600 bg-gray-100 hover:bg-indigo-50 hover:text-indigo-600 transition-colors no-underline">
						<?php echo wp_kses_post( apply_filters( 'woocommerce_product_' . $key . '_tab_title', $product_tab['title'], $key ) ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php foreach ( $product_tabs as $key => $product_tab ) : ?>
			<div class="woocommerce-Tabs-panel woocommerce-Tabs-panel--<?php echo esc_attr( $key ); ?> panel entry-content wc-tab prose prose-sm max-w-none text-gray-700" id="tab-<?php echo esc_attr( $key ); ?>" role="tabpanel" aria-labelledby="tab-title-<?php echo esc_attr( $key ); ?>">
				<?php
				if ( isset( $product_tab['callback'] ) ) {
					call_user_func( $product_tab['callback'], $key, $product_tab );
				}
				?>
			</div>
		<?php endforeach; ?>

		<?php do_action( 'woocommerce_product_after_tabs' ); ?>
	</div>

<?php endif; ?>
