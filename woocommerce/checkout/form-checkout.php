<?php
/**
 * Checkout Form Template
 *
 * NOTE: The page wrapper (<div class="w-full max-w-7xl ...">) is
 * emitted by wcom-support.php via blogpro_wcom_wrapper_before/after
 * on the woocommerce_before_main_content / _after_main_content
 * hooks. Do not duplicate it here.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

wc_print_notices();

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout requires the user to be logged in, show the message and stop.
// Stock WC uses: ! enabled AND required AND not logged in.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
    echo esc_html( apply_filters(
        'woocommerce_checkout_must_be_logged_in_message',
        __( 'You must be logged in to checkout.', 'woocommerce' )
    ) );
    return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout grid grid-cols-1 lg:grid-cols-5 gap-8 items-start" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">

    <!-- Left column: customer details -->
    <div class="lg:col-span-3 space-y-5">
        <?php if ( $checkout->get_checkout_fields() ) : ?>
            <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

            <div id="customer_details" class="space-y-5">
                <div>
                    <?php do_action( 'woocommerce_checkout_billing' ); ?>
                </div>

                <div>
                    <?php do_action( 'woocommerce_checkout_shipping' ); ?>
                </div>
            </div>

            <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
        <?php endif; ?>
    </div>

    <!-- Right column: sticky order review -->
    <div class="lg:col-span-2 lg:sticky lg:top-24">
        <h3 id="order_review_heading" class="text-lg font-semibold text-gray-900 mb-3"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>
        <?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

        <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

        <div id="order_review" class="woocommerce-checkout-review-order bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6">
            <?php do_action( 'woocommerce_checkout_order_review' ); ?>
        </div>

        <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
    </div>

</form>

<?php do_action( 'woocommerce_after_checkout_form' ); ?>
