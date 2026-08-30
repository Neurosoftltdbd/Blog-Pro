<?php
/**
 * My Account — modern dashboard layout
 *
 * Layout:
 *   ┌─ greeting card (name + email, edit link) ─┐  ┌─ sidebar nav ─┐
 *   │ quick-link tiles (orders, downloads,       │  │ • Dashboard  │
 *   │ addresses, account details, logout)         │  │ • Orders     │
 *   │ recent orders list (thumb + status pill)    │  │ • Downloads  │
 *   └────────────────────────────────────────────┘  │ • Addresses  │
 *                                                    │ • Account    │
 *                                                    │ • Logout     │
 *                                                    └──────────────┘
 */
if ( ! defined( 'ABSPATH' ) ) exit;

wc_print_notices();

do_action( 'woocommerce_before_account_navigation' );

$current_user = wp_get_current_user();
$is_logged_in  = is_user_logged_in();

/* Active endpoint via WC's own query, falls back to 'dashboard'. */
$current_endpoint = ( function_exists( 'WC' ) && WC()->query ) ? WC()->query->get_current_endpoint() : '';
if ( ! $current_endpoint ) {
    $current_endpoint = 'dashboard';
}
?>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8 my-account-wrap">

    <!-- ============================================== SIDEBAR ============================================== -->
    <aside class="woocommerce-MyAccount-navigation lg:col-span-1" role="navigation">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 lg:sticky lg:top-24">
            <?php if ( $is_logged_in ) : ?>
                <div class="flex items-center gap-3 px-2 py-3 border-b border-gray-100 mb-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center font-semibold text-sm shrink-0">
                        <?php echo esc_html( strtoupper( mb_substr( $current_user->display_name ?: $current_user->user_login, 0, 1 ) ) ); ?>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate">
                            <?php echo esc_html( $current_user->display_name ?: $current_user->user_login ); ?>
                        </p>
                        <p class="text-xs text-gray-500 truncate"><?php echo esc_html( $current_user->user_email ); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <ul class="space-y-1" role="menu">
                <?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) :
                    $active = ( $endpoint === $current_endpoint );
                    ?>
                    <li class="woocommerce-MyAccount-navigation-link <?php echo $active ? 'is-active' : ''; ?>" role="none">
                        <a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"
                           role="menuitem"
                           class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm transition-colors <?php echo $active ? 'bg-indigo-50 text-indigo-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-indigo-600'; ?>">
                            <?php echo blogpro_account_nav_icon( $endpoint ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <span><?php echo esc_html( $label ); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>

                <li class="border-t border-gray-100 mt-2 pt-2" role="none">
                    <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>"
                       role="menuitem"
                       class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 hover:bg-red-50 hover:text-red-600 transition-colors">
                        <?php echo blogpro_account_nav_icon( 'customer-logout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <span><?php esc_html_e( 'Logout', 'blog-pro' ); ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- ============================================== CONTENT ============================================== -->
    <div class="woocommerce-MyAccount-content lg:col-span-3 space-y-6">

        <?php if ( 'dashboard' === $current_endpoint && $is_logged_in ) : ?>
            <!-- ---------- DASHBOARD GREETING + QUICK LINKS ---------- -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-700 text-white p-6 md:p-8 shadow-sm">
                <div class="absolute inset-0 opacity-20 pointer-events-none" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" class="w-full h-full">
                        <defs>
                            <pattern id="ma-dots" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                                <circle cx="2" cy="2" r="1" fill="currentColor"/>
                            </pattern>
                        </defs>
                        <rect width="200" height="200" fill="url(#ma-dots)"/>
                    </svg>
                </div>
                <div class="relative">
                    <p class="text-xs uppercase tracking-widest text-indigo-200 font-semibold mb-2">
                        <?php
                        /* translators: %s: customer display name */
                        printf( esc_html__( 'Hello, %s', 'blog-pro' ), esc_html( $current_user->display_name ?: $current_user->user_login ) );
                        ?>
                    </p>
                    <h1 class="text-2xl md:text-3xl font-bold tracking-tight mb-2">
                        <?php esc_html_e( 'Welcome to your account', 'blog-pro' ); ?>
                    </h1>
                    <p class="text-indigo-100/90 text-sm max-w-xl">
                        <?php esc_html_e( 'Manage orders, downloads, addresses, and account details from here.', 'blog-pro' ); ?>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <?php
                $quick_links = array(
                    'orders'     => array( 'icon' => 'orders',     'label' => __( 'Orders', 'blog-pro' ) ),
                    'downloads'  => array( 'icon' => 'downloads',  'label' => __( 'Downloads', 'blog-pro' ) ),
                    'edit-address' => array( 'icon' => 'address',  'label' => __( 'Addresses', 'blog-pro' ) ),
                    'edit-account' => array( 'icon' => 'account',  'label' => __( 'Account details', 'blog-pro' ) ),
                );
                foreach ( $quick_links as $ep => $info ) :
                    if ( ! array_key_exists( $ep, wc_get_account_menu_items() ) ) continue;
                    ?>
                    <a href="<?php echo esc_url( wc_get_account_endpoint_url( $ep ) ); ?>"
                       class="group flex flex-col items-start gap-3 p-4 bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-indigo-200 transition-all">
                        <span class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                            <?php echo blogpro_account_nav_icon( $ep, 'w-5 h-5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </span>
                        <span class="text-sm font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">
                            <?php echo esc_html( $info['label'] ); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- WC's stock endpoint content (orders table, edit-address, edit-account, etc.) -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6">
            <?php do_action( 'woocommerce_account_content' ); ?>
        </div>
    </div>
</div>

<?php
/**
 * Returns an inline SVG icon for a given my-account endpoint key.
 * Keeps the nav visually distinct without adding an icon library.
 */
function blogpro_account_nav_icon( $endpoint, $size = 'w-5 h-5' ) {
    $icons = array(
        'dashboard'     => '<svg class="' . $size . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
        'orders'        => '<svg class="' . $size . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>',
        'downloads'     => '<svg class="' . $size . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>',
        'edit-address'  => '<svg class="' . $size . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'edit-account'  => '<svg class="' . $size . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'customer-logout' => '<svg class="' . $size . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>',
    );
    return isset( $icons[ $endpoint ] ) ? $icons[ $endpoint ] : $icons['dashboard'];
}

do_action( 'woocommerce_after_account_navigation' );
