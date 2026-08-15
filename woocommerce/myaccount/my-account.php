<?php
/**
 * My Account Template
 */
if ( ! defined( 'ABSPATH' ) ) exit;

wc_print_notices();

do_action( 'woocommerce_before_account_navigation' );
?>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    <!-- Sidebar Navigation -->
    <nav class="woocommerce-MyAccount-navigation lg:col-span-1" role="navigation">
        <?php
        $current_endpoint = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
        $current_endpoint = 'dashboard' === $current_endpoint ? 'orders' : $current_endpoint;
        ?>
        <ul class="space-y-1">
            <?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
                <li class="woocommerce-MyAccount-navigation-link">
                    <a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"
                       class="block px-4 py-2.5 rounded-lg transition-colors <?php echo $endpoint === $current_endpoint ? 'bg-indigo-50 text-indigo-600 font-medium' : 'text-gray-600 hover:bg-gray-50 hover:text-indigo-600'; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Content Area -->
    <div class="woocommerce-MyAccount-content lg:col-span-3">
        <?php
        /**
         * woocommerce_account_content hook
         */
        do_action( 'woocommerce_account_content' );
        ?>
    </div>
</div>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>