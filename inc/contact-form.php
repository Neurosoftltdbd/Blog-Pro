<?php
/**
 * Handle contact form submissions — save to DB as custom post type
 * (view under Dashboard → Blog Pro → Contact) and mail the admin.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function blogpro_handle_contact_form() {
	if ( empty( $_POST['blogpro_contact_submit'] ) ) return;

	if ( ! isset( $_POST['blogpro_contact_nonce'] ) || ! wp_verify_nonce( $_POST['blogpro_contact_nonce'], 'blogpro_contact' ) ) {
		set_transient( 'blogpro_contact_status_' . blogpro_visitor_key(), 'error', 60 );
		return;
	}

	// Honeypot — bots fill every field, humans never see this one.
	if ( ! empty( $_POST['website_url'] ) ) {
		set_transient( 'blogpro_contact_status_' . blogpro_visitor_key(), 'success', 60 ); // pretend success, drop silently
		return;
	}

	$name    = isset( $_POST['contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_name'] ) ) : '';
	$email   = isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';
	$message = isset( $_POST['contact_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['contact_message'] ) ) : '';

	if ( ! $name || ! is_email( $email ) || ! $message ) {
		set_transient( 'blogpro_contact_status_' . blogpro_visitor_key(), 'error', 60 );
		return;
	}

	$post_id = wp_insert_post( array(
		'post_type'    => 'blogpro_contact',
		'post_status'  => 'private',
		'post_title'   => sprintf( '%s — %s', $name, $email ),
		'post_content' => $message,
		'meta_input'   => array(
			'_blogpro_contact_name'    => $name,
			'_blogpro_contact_email'   => $email,
			'_blogpro_contact_message' => $message,
		),
	) );

	if ( ! $post_id || is_wp_error( $post_id ) ) {
		set_transient( 'blogpro_contact_status_' . blogpro_visitor_key(), 'error', 60 );
		return;
	}

	$to      = get_option( 'admin_email' );
	$subject = sprintf( '[%s] New contact form message from %s', get_bloginfo( 'name' ), $name );
	$body    = "Name: $name\nEmail: $email\n\nMessage:\n$message";
	$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );

	// Best-effort email. The DB record is the source of truth (visible under
	// Dashboard → Blog Pro → Contact), so a mail failure (e.g. no sendmail
	// configured on localhost) must not surface as "something went wrong".
	wp_mail( $to, $subject, $body, $headers );

	set_transient( 'blogpro_contact_status_' . blogpro_visitor_key(), 'success', 60 );
}
add_action( 'init', 'blogpro_handle_contact_form' );

function blogpro_visitor_key() {
	return md5( ( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'anon' ) . ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '' ) );
}

function blogpro_contact_form_status() {
	$key    = blogpro_visitor_key();
	$status = get_transient( 'blogpro_contact_status_' . $key );
	if ( $status ) delete_transient( 'blogpro_contact_status_' . $key );
	return $status;
}
