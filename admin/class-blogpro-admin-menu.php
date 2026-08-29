<?php
/**
 * Dashboard sidebar menu — registers the theme brand under the site title
 * (e.g. "Blog Pro") as a root menu, with submenus: Contact submissions,
 * Optimize Images, and a Help/About screen.
 *
 * Root menu mimics wp-admin's home screen brand so the theme name shows
 * in the top-left sidebar position.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add the theme-branded root menu ("Blog Pro") before Appearance.
 * The default Dashboard menu is left untouched — it keeps its Home /
 * Updates submenus.
 */
function blogpro_admin_menu_brand() {
	$brand = 'Blog Pro';
	$icon  = 'dashicons-admin-site-alt3';

	add_menu_page(
		$brand,
		$brand,
		'read',
		'blogpro-dashboard',
		'blogpro_render_dashboard_page',
		$icon,
		59 // right before Appearance (60)
	);

	add_submenu_page(
		'blogpro-dashboard',
		__( 'Contact Messages', 'blog-pro' ),
		__( 'Contact', 'blog-pro' ),
		'edit_posts',
		'blogpro-contact',
		'blogpro_render_contact_page'
	);

	add_submenu_page(
		'blogpro-dashboard',
		__( 'Optimize Images', 'blog-pro' ),
		__( 'Optimize Images', 'blog-pro' ),
		'upload_files',
		'blogpro-optimize-images',
		'blogpro_render_optimize_images_page'
	);

	add_submenu_page(
		'blogpro-dashboard',
		__( 'About Blog Pro', 'blog-pro' ),
		__( 'About', 'blog-pro' ),
		'read',
		'blogpro-about',
		'blogpro_render_about_page'
	);

	// Remove the standalone Media → Optimize Images page; it lives here now.
	remove_submenu_page( 'upload.php', 'blogpro-optimize-images' );
}
add_action( 'admin_menu', 'blogpro_admin_menu_brand', 999 );

/**
 * Dashboard landing page.
 */
function blogpro_render_dashboard_page() {
	if ( ! current_user_can( 'read' ) ) return;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Blog Pro Dashboard', 'blog-pro' ); ?></h1>
		<p><?php esc_html_e( 'Manage your site from the theme\'s dashboard.', 'blog-pro' ); ?></p>
		<ul>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-contact' ) ); ?>"><?php esc_html_e( 'View contact messages', 'blog-pro' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-optimize-images' ) ); ?>"><?php esc_html_e( 'Optimize images', 'blog-pro' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-about' ) ); ?>"><?php esc_html_e( 'About this theme', 'blog-pro' ); ?></a></li>
		</ul>
	</div>
	<?php
}

/**
 * Contact submissions list page.
 */
function blogpro_render_contact_page() {
	if ( ! current_user_can( 'edit_posts' ) ) return;

	// Create the CPT on demand if the theme wasn't activated to trigger the hook.
	blogpro_contact_register_cpt();

	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
	$id     = isset( $_GET['contact_id'] ) ? absint( $_GET['contact_id'] ) : 0;

	if ( 'view' === $action && $id ) {
		blogpro_render_contact_view( $id );
		return;
	}

	if ( 'edit' === $action && $id ) {
		$save_error = isset( $_GET['msg'] ) && 'save_error' === sanitize_key( $_GET['msg'] );
		blogpro_render_contact_edit( $id, $save_error );
		return;
	}

	blogpro_render_contact_list();
}

/**
 * Handle save/delete with redirects on admin_init — wp-admin has already
 * sent headers (admin-header.php) by the time the page callback runs, so
 * wp_redirect() there triggers "headers already sent". admin_init runs
 * before any output, so redirects exit cleanly.
 */
function blogpro_contact_handle_admin_actions() {
	$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
	if ( 'blogpro-contact' !== $page ) {
		return;
	}

	$id = isset( $_GET['contact_id'] ) ? absint( $_GET['contact_id'] ) : 0;

	if ( isset( $_POST['blogpro_contact_save'] ) ) {
		blogpro_contact_handle_save( $id );
	}

	if ( isset( $_GET['action'] ) && 'delete' === sanitize_key( $_GET['action'] ) && $id ) {
		blogpro_contact_handle_delete( $id );
	}
}
add_action( 'admin_init', 'blogpro_contact_handle_admin_actions' );

/**
 * Save edited message fields. Redirects on success; on validation failure
 * redirects back to the edit form with an error flag.
 *
 * @param int $id
 */
function blogpro_contact_handle_save( $id ) {
	check_admin_referer( 'blogpro_contact_edit_' . $id, 'blogpro_contact_edit_nonce' );

	$post = get_post( $id );
	if ( ! $post || 'blogpro_contact' !== $post->post_type || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( esc_html__( 'You are not allowed to edit this message.', 'blog-pro' ) );
	}

	$name    = isset( $_POST['contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_name'] ) ) : '';
	$email   = isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';
	$message = isset( $_POST['contact_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['contact_message'] ) ) : '';

	if ( ! $name || ! is_email( $email ) || ! $message ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'blogpro-contact', 'action' => 'edit', 'contact_id' => $id, 'msg' => 'save_error' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	wp_update_post( array(
		'ID'           => $id,
		'post_title'   => sprintf( '%s — %s', $name, $email ),
		'post_content' => $message,
	) );
	update_post_meta( $id, '_blogpro_contact_name', $name );
	update_post_meta( $id, '_blogpro_contact_email', $email );
	update_post_meta( $id, '_blogpro_contact_message', $message );

	wp_safe_redirect( add_query_arg( array( 'page' => 'blogpro-contact', 'msg' => 'updated' ), admin_url( 'admin.php' ) ) );
	exit;
}

/**
 * Delete a message permanently. Nonce-protected GET (standard wp-admin
 * pattern), then redirects back to the list.
 *
 * @param int $id
 */
function blogpro_contact_handle_delete( $id ) {
	check_admin_referer( 'blogpro_contact_delete_' . $id );

	if ( ! current_user_can( 'delete_post', $id ) ) {
		wp_die( esc_html__( 'You are not allowed to delete this message.', 'blog-pro' ) );
	}

	wp_delete_post( $id, true );

	wp_safe_redirect( add_query_arg( array( 'page' => 'blogpro-contact', 'msg' => 'deleted' ), admin_url( 'admin.php' ) ) );
	exit;
}

/**
 * Print success notices from ?msg= (list page only).
 */
function blogpro_contact_notices() {
	$msg = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : '';
	if ( 'updated' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Message updated.', 'blog-pro' ); ?></p></div>
	<?php elseif ( 'deleted' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Message deleted.', 'blog-pro' ); ?></p></div>
	<?php endif;
}

/**
 * Read-only view of one message.
 *
 * @param int $id
 */
function blogpro_render_contact_view( $id ) {
	$post = get_post( $id );
	if ( ! $post || 'blogpro_contact' !== $post->post_type ) {
		wp_die( esc_html__( 'Message not found.', 'blog-pro' ) );
	}

	$name    = get_post_meta( $id, '_blogpro_contact_name', true );
	$email   = get_post_meta( $id, '_blogpro_contact_email', true );
	$message = get_post_meta( $id, '_blogpro_contact_message', true );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Contact Message', 'blog-pro' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-contact' ) ); ?>" class="button">&larr; <?php esc_html_e( 'Back to list', 'blog-pro' ); ?></a>
		<table class="widefat striped mt-4" style="max-width: 720px;">
			<tbody>
				<tr><th style="width: 120px;"><?php esc_html_e( 'Name', 'blog-pro' ); ?></th><td><?php echo esc_html( $name ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Email', 'blog-pro' ); ?></th><td><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></td></tr>
				<tr><th><?php esc_html_e( 'Date', 'blog-pro' ); ?></th><td><?php echo esc_html( get_the_date( '', $post ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Message', 'blog-pro' ); ?></th><td><?php echo nl2br( esc_html( $message ) ); ?></td></tr>
			</tbody>
		</table>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-contact&action=edit&contact_id=' . $id ) ); ?>" class="button button-primary"><?php esc_html_e( 'Edit', 'blog-pro' ); ?></a>
			<a href="mailto:<?php echo esc_attr( $email ) . '?subject=' . rawurlencode( sprintf( 'Re: %s', $name ) ); ?>" class="button"><?php esc_html_e( 'Reply by Email', 'blog-pro' ); ?></a>
		</p>
	</div>
	<?php
}

/**
 * Edit form for one message.
 *
 * @param int  $id
 * @param bool $save_error
 */
function blogpro_render_contact_edit( $id, $save_error = false ) {
	$post = get_post( $id );
	if ( ! $post || 'blogpro_contact' !== $post->post_type ) {
		wp_die( esc_html__( 'Message not found.', 'blog-pro' ) );
	}

	$name    = get_post_meta( $id, '_blogpro_contact_name', true );
	$email   = get_post_meta( $id, '_blogpro_contact_email', true );
	$message = get_post_meta( $id, '_blogpro_contact_message', true );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Edit Contact Message', 'blog-pro' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-contact' ) ); ?>" class="button">&larr; <?php esc_html_e( 'Back to list', 'blog-pro' ); ?></a>
		<?php if ( $save_error ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Please fill in a valid name, email and message.', 'blog-pro' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-contact&action=edit&contact_id=' . $id ) ); ?>" style="max-width: 720px;">
			<?php wp_nonce_field( 'blogpro_contact_edit_' . $id, 'blogpro_contact_edit_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="contact_name"><?php esc_html_e( 'Name', 'blog-pro' ); ?></label></th>
					<td><input type="text" id="contact_name" name="contact_name" value="<?php echo esc_attr( $name ); ?>" class="regular-text" required></td>
				</tr>
				<tr>
					<th><label for="contact_email"><?php esc_html_e( 'Email', 'blog-pro' ); ?></label></th>
					<td><input type="email" id="contact_email" name="contact_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" required></td>
				</tr>
				<tr>
					<th><label for="contact_message"><?php esc_html_e( 'Message', 'blog-pro' ); ?></label></th>
					<td><textarea id="contact_message" name="contact_message" rows="10" class="large-text" required><?php echo esc_textarea( $message ); ?></textarea></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Message', 'blog-pro' ) ); ?>
			<input type="hidden" name="blogpro_contact_save" value="1">
		</form>
	</div>
	<?php
}

/**
 * List table with View / Edit / Delete actions.
 */
function blogpro_render_contact_list() {
	$per_page = 20;
	$paged    = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );

	$q = new WP_Query( array(
		'post_type'      => 'blogpro_contact',
		'post_status'    => 'private',
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	$total = (int) $q->found_posts;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Contact Messages', 'blog-pro' ); ?></h1>
		<p><?php esc_html_e( 'Messages submitted through the theme\'s contact form.', 'blog-pro' ); ?></p>
		<?php blogpro_contact_notices(); ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'blog-pro' ); ?></th>
					<th><?php esc_html_e( 'Email', 'blog-pro' ); ?></th>
					<th><?php esc_html_e( 'Message', 'blog-pro' ); ?></th>
					<th><?php esc_html_e( 'Date', 'blog-pro' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'blog-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $q->have_posts() ) : while ( $q->have_posts() ) : $q->the_post(); ?>
				<tr>
					<td><?php echo esc_html( get_post_meta( get_the_ID(), '_blogpro_contact_name', true ) ); ?></td>
					<td>
						<a href="mailto:<?php echo esc_attr( get_post_meta( get_the_ID(), '_blogpro_contact_email', true ) ); ?>">
							<?php echo esc_html( get_post_meta( get_the_ID(), '_blogpro_contact_email', true ) ); ?>
						</a>
					</td>
					<td><?php echo esc_html( wp_trim_words( get_post_meta( get_the_ID(), '_blogpro_contact_message', true ), 30 ) ); ?></td>
					<td><?php echo esc_html( get_the_date() ); ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-contact&action=view&contact_id=' . get_the_ID() ) ); ?>"><?php esc_html_e( 'View', 'blog-pro' ); ?></a> |
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-contact&action=edit&contact_id=' . get_the_ID() ) ); ?>"><?php esc_html_e( 'Edit', 'blog-pro' ); ?></a> |
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=blogpro-contact&action=delete&contact_id=' . get_the_ID() ), 'blogpro_contact_delete_' . get_the_ID() ) ); ?>" onclick="<?php echo esc_attr( sprintf( "return confirm( '%s' );", esc_js( __( 'Delete this message permanently?', 'blog-pro' ) ) ) ); ?>"><?php esc_html_e( 'Delete', 'blog-pro' ); ?></a>
					</td>
				</tr>
				<?php endwhile; wp_reset_postdata(); else : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No messages yet.', 'blog-pro' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php if ( $total > $per_page ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php echo wp_kses_post( paginate_links( array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'current'   => $paged,
					'total'     => ceil( $total / $per_page ),
				) ) ); ?>
			</div>
		</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * About page.
 */
function blogpro_render_about_page() {
	if ( ! current_user_can( 'read' ) ) return;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'About Blog Pro', 'blog-pro' ); ?></h1>
		<p><?php echo esc_html( wp_get_theme()->get( 'Name' ) ) . ' ' . esc_html( wp_get_theme()->get( 'Version' ) ); ?></p>
		<p>Blog Pro is a modern, lightweight, and feature-rich WordPress theme designed for speed and simplicity. It comes packed with powerful features to help you build a stunning blog or website with ease.</p>
		
	</div>
	<?php
}

/**
 * Register the contact submission CPT.
 * Called on init (theme load) and on-demand in the contact page.
 */
function blogpro_contact_register_cpt() {
	register_post_type( 'blogpro_contact', array(
		'labels' => array(
			'name'          => __( 'Contact Messages', 'blog-pro' ),
			'singular_name' => __( 'Contact Message', 'blog-pro' ),
		),
		'public'       => false,
		'show_ui'      => false,
		'show_in_menu' => false,
		'supports'     => array( 'title' ),
		'capability_type' => 'post',
		'map_meta_cap' => true,
	) );
}
// Priority 5: must exist before blogpro_handle_contact_form() (priority 10)
// inserts a submission via wp_insert_post().
add_action( 'init', 'blogpro_contact_register_cpt', 5 );
