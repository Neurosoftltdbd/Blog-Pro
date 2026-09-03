<?php
/**
 * Custom user profile photo.
 *
 * Adds an "upload from your computer" input under the default
 * Profile Picture avatar. Saves the uploaded image to the media
 * library and stores the attachment ID in user meta. The
 * `get_avatar` filter then renders that image in place of the
 * default avatar everywhere on the site.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BLOGPRO_PROFILE_PHOTO_META_KEY', 'blogpro_profile_photo' );

/**
 * Add multipart enctype to the user-edit / profile form so the
 * file input can post its data.
 */
function blogpro_profile_photo_form_tag() {
	echo ' enctype="multipart/form-data"';
}
add_action( 'user_edit_form_tag', 'blogpro_profile_photo_form_tag' );
add_action( 'profile_form_tag',    'blogpro_profile_photo_form_tag' );

/**
 * Render an upload input below the default Profile Picture avatar.
 * Echoes directly — no table wrapper, this is a paragraph inside
 * the existing <td> next to get_avatar().
 *
 * @param WP_User $user
 */
function blogpro_render_profile_photo_upload( $user ) {
	if ( ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}
	wp_nonce_field( 'blogpro_profile_photo_' . $user->ID, 'blogpro_profile_photo_nonce' );
	?>
	<p>
		<input type="file" name="blogpro_photo_file" accept="image/*">
	</p>
	<p class="description">
		<?php esc_html_e( 'Upload a new profile picture. Replaces the default avatar everywhere it appears on the site.', 'blog-pro' ); ?>
	</p>
	<?php
}
add_action( 'show_user_profile', 'blogpro_render_profile_photo_upload' );
add_action( 'edit_user_profile', 'blogpro_render_profile_photo_upload' );

/**
 * Persist the uploaded image on profile save. Runs on the
 * `personal_options_update` / `edit_user_profile_update` hooks so
 * it executes before core's edit_user() and after nonce checks.
 *
 * @param int $user_id
 */
function blogpro_save_profile_photo( $user_id ) {
	if ( empty( $_FILES['blogpro_photo_file']['name'] ) ) {
		return;
	}
	if ( ! isset( $_POST['blogpro_profile_photo_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( $_POST['blogpro_profile_photo_nonce'], 'blogpro_profile_photo_' . $user_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	$file = $_FILES['blogpro_photo_file'];
	if ( ! empty( $file['error'] ) ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$upload = wp_handle_upload( $file, array(
		'test_form' => false, // already nonce-checked
		'mime_type' => 'image',
	) );

	if ( empty( $upload['url'] ) || ! empty( $upload['error'] ) ) {
		return;
	}

	$attachment_id = wp_insert_attachment( array(
		'post_title'     => sanitize_file_name( $file['name'] ),
		'post_mime_type' => $upload['type'],
		'post_content'   => '',
		'post_status'    => 'inherit',
	), $upload['file'] );

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		return;
	}

	$meta = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
	if ( ! is_wp_error( $meta ) ) {
		wp_update_attachment_metadata( $attachment_id, $meta );
	}

	update_user_meta( $user_id, BLOGPRO_PROFILE_PHOTO_META_KEY, (int) $attachment_id );
}
add_action( 'personal_options_update',     'blogpro_save_profile_photo' );
add_action( 'edit_user_profile_update',   'blogpro_save_profile_photo' );

/**
 * Resolve a user's profile photo URL at a given pixel size.
 *
 * @param int $user_id
 * @param int $size
 * @return string URL or empty.
 */
function blogpro_get_user_photo_url( $user_id, $size = 96 ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return '';
	}
	$photo_id = (int) get_user_meta( $user_id, BLOGPRO_PROFILE_PHOTO_META_KEY, true );
	if ( ! $photo_id ) {
		return '';
	}
	$src = wp_get_attachment_image_src( $photo_id, array( (int) $size, (int) $size ) );
	return ( $src && ! empty( $src[0] ) ) ? $src[0] : '';
}

/**
 * `get_avatar` filter — render the user's profile photo instead
 * of the default avatar everywhere on the site.
 */
function blogpro_filter_get_avatar( $avatar, $id_or_email, $args ) {
	$user_id = 0;

	if ( is_numeric( $id_or_email ) ) {
		$user_id = (int) $id_or_email;
	} elseif ( is_string( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
		if ( $user ) { $user_id = (int) $user->ID; }
	} elseif ( $id_or_email instanceof WP_User ) {
		$user_id = (int) $id_or_email->ID;
	} elseif ( $id_or_email instanceof WP_Post ) {
		$user_id = (int) $id_or_email->post_author;
	} elseif ( $id_or_email instanceof WP_Comment ) {
		if ( ! empty( $id_or_email->user_id ) ) {
			$user_id = (int) $id_or_email->user_id;
		} elseif ( ! empty( $id_or_email->comment_author_email ) ) {
			$u = get_user_by( 'email', $id_or_email->comment_author_email );
			if ( $u ) { $user_id = (int) $u->ID; }
		}
	}

	if ( ! $user_id ) {
		return $avatar;
	}

	$size = isset( $args['size'] ) ? (int) $args['size'] : 96;
	$url  = blogpro_get_user_photo_url( $user_id, $size );
	if ( ! $url ) {
		return $avatar;
	}

	$alt   = isset( $args['alt'] ) && '' !== $args['alt']
		? (string) $args['alt']
		: (string) get_the_author_meta( 'display_name', $user_id );
	$class = isset( $args['class'] ) ? array_map( 'sanitize_html_class', (array) $args['class'] ) : array( 'avatar', 'avatar-' . $size, 'photo' );

	return sprintf(
		'<img alt="%s" src="%s" class="%s" width="%d" height="%d" loading="lazy" decoding="async">',
		esc_attr( $alt ),
		esc_url( $url ),
		esc_attr( implode( ' ', $class ) ),
		(int) $size,
		(int) $size
	);
}
add_filter( 'get_avatar', 'blogpro_filter_get_avatar', 10, 3 );
