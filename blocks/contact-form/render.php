<?php
/**
 * Server-side render for Contact Form block.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$form_id = 'blogpro-contact-form-' . uniqid();
$status = blogpro_contact_form_status();

// Output success/error message
if ( $status === 'success' ) {
    echo '<div class="blogpro-contact-success" style="padding:1rem;background:#d1fae5;border-radius:8px;margin-bottom:1rem;color:#065f46;">' . esc_html__( 'Thank you! Your message has been sent.', 'blog-pro' ) . '</div>';
} elseif ( $status === 'error' ) {
    echo '<div class="blogpro-contact-error" style="padding:1rem;background:#fee2e2;border-radius:8px;margin-bottom:1rem;color:#991b1b;">' . esc_html__( 'Please fill in all fields correctly.', 'blog-pro' ) . '</div>';
}
?>
<form id="<?php echo esc_attr( $form_id ); ?>" method="POST" class="blogpro-contact-form" style="display:flex;flex-direction:column;gap:1rem;max-width:500px;">
    <?php wp_nonce_field( 'blogpro_contact', 'blogpro_contact_nonce' ); ?>
    <input type="hidden" name="blogpro_contact_submit" value="1">

    <!-- Honeypot -->
    <div style="display:none;">
        <label for="website_url">Website</label>
        <input type="text" name="website_url" id="website_url" value="">
    </div>

    <div>
        <label for="contact_name" style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'Your Name', 'blog-pro' ); ?></label>
        <input type="text" name="contact_name" id="contact_name" required style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;">
    </div>

    <div>
        <label for="contact_email" style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'Email Address', 'blog-pro' ); ?></label>
        <input type="email" name="contact_email" id="contact_email" required style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;">
    </div>

    <div>
        <label for="contact_message" style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'Message', 'blog-pro' ); ?></label>
        <textarea name="contact_message" id="contact_message" rows="4" required style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;"></textarea>
    </div>

    <button type="submit" style="padding:12px 24px;background:#1a1a2e;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;">
        <?php esc_html_e( 'Send Message', 'blog-pro' ); ?>
    </button>
</form>
<?php