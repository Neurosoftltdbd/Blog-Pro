<?php
/**
 * Checkout Form Template
 */
if ( ! defined( 'ABSPATH' ) ) exit;

wc_print_notices();

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_required() && $checkout->is_registration_optional() && ! is_user_logged_in() ) {
    echo apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be <a href="%s">logged in</a> to checkout.', 'blog-pro' ) );
}

?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

    <?php if ( sizeof( $checkout->checkout_fields ) > 0 ) : ?>
        <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8" id="customer_details">
            <div class="lg:col-span-2">
                <?php do_action( 'woocommerce_checkout_billing' ); ?>
            </div>

            <div class="lg:col-span-1">
                <?php do_action( 'woocommerce_checkout_shipping' ); ?>
            </div>
        </div>

        <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
    <?php endif; ?>

    <h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'blog-pro' ); ?></h3>

    <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

    <div id="order_review" class="woocommerce-checkout-review-order">
        <?php do_action( 'woocommerce_checkout_order_review' ); ?>
    </div>

    <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

</form>

<?php do_action( 'woocommerce_after_checkout_form' ); ?>