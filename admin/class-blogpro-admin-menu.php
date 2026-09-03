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

	// Root click lands on the Dashboard page, not the first submenu.
	add_submenu_page(
		'blogpro-dashboard',
		__( 'Dashboard', 'blog-pro' ),
		__( 'Dashboard', 'blog-pro' ),
		'read',
		'blogpro-dashboard',
		'blogpro_render_dashboard_page'
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
		__( 'SEO Checker', 'blog-pro' ),
		__( 'SEO Checker', 'blog-pro' ),
		'manage_options',
		'blogpro-seo-checker',
		'blogpro_seo_checker_page'
	);

	add_submenu_page(
		'blogpro-dashboard',
		__( 'FAQ Migration', 'blog-pro' ),
		__( 'FAQ Migration', 'blog-pro' ),
		'manage_options',
		'blogpro-faq-migrate',
		'blogpro_faq_migrate_page'
	);

	add_submenu_page(
		'blogpro-dashboard',
		__( 'Site Verification', 'blog-pro' ),
		__( 'Verification', 'blog-pro' ),
		'manage_options',
		'blogpro-verification',
		'blogpro_render_verification_page'
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
 * Dashboard landing page — at-a-glance health of the site + theme.
 */
function blogpro_render_dashboard_page() {
	if ( ! current_user_can( 'read' ) ) return;
	?>
	<div class="wrap bp-dash-wrap">
		<h1><?php esc_html_e( 'Blog Pro Dashboard', 'blog-pro' ); ?></h1>
		<p class="bp-dash-lead"><?php esc_html_e( 'Your site health at a glance. Everything the theme manages, in one place.', 'blog-pro' ); ?></p>

		<?php
		$posts_total   = wp_count_posts( 'post' );
		$posts_pub     = (int) ( $posts_total->publish ?? 0 );
		$posts_draft   = (int) ( $posts_total->draft ?? 0 );
		$posts_pending = (int) ( $posts_total->pending ?? 0 );
		$cat_count     = count( get_categories( array( 'hide_empty' => false ) ) );

		$contacts = wp_count_posts( 'blogpro_contact' );
		$contact_n = (int) ( $contacts->publish ?? 0 );

		$img_total = (int) ( wp_count_posts( 'attachment' )->inherit ?? 0 );
		$img_unopt = 0;
		if ( function_exists( 'blogpro_media_optimizer_stats' ) ) {
			$stats = blogpro_media_optimizer_stats();
			$img_unopt = (int) ( $stats['unoptimized'] ?? 0 );
		} else {
			$img_unopt = (int) ( $GLOBALS['wpdb']->get_var(
				"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts}
				 WHERE post_type='attachment' AND post_mime_type IN ('image/jpeg','image/png')"
			) ?? 0 );
		}

		$perf_score = null;
		if ( function_exists( 'blogpro_get_perf_status' ) ) {
			$perf = blogpro_get_perf_status();
			$perf_score = isset( $perf['score'] ) ? (int) $perf['score'] : null;
		}

		$seo_issues = null;
		if ( function_exists( 'blogpro_seo_check_post' ) ) {
			$samples = get_posts( array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 3,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			) );
			$seo_issues = array_sum( array_map( function ( $p ) {
				$r = blogpro_seo_check_post( $p->ID );
				$n = 0;
				foreach ( $r['findings'] as $items ) $n += count( $items );
				return $n;
			}, $samples ) );
		}

		$page_speed = null;
		if ( function_exists( 'blogpro_get_lcp_status' ) ) {
			$page_speed = (int) blogpro_get_lcp_status();
		}
		?>
		<div class="bp-dash-stats">
			<div class="bp-dash-stat">
				<div class="v"><?php echo (int) $posts_pub; ?></div>
				<div class="k"><?php esc_html_e( 'Published posts', 'blog-pro' ); ?></div>
			</div>
			<div class="bp-dash-stat">
				<div class="v"><?php echo (int) $posts_draft + (int) $posts_pending; ?></div>
				<div class="k"><?php esc_html_e( 'Draft / pending', 'blog-pro' ); ?></div>
			</div>
			<div class="bp-dash-stat">
				<div class="v"><?php echo (int) $cat_count; ?></div>
				<div class="k"><?php esc_html_e( 'Categories', 'blog-pro' ); ?></div>
			</div>
			<div class="bp-dash-stat">
				<div class="v"><?php echo (int) $img_unopt; ?> <?php esc_html_e( '/', 'blog-pro' ); ?> <?php echo (int) $img_total; ?></div>
				<div class="k"><?php esc_html_e( 'Images unoptimized / total', 'blog-pro' ); ?></div>
			</div>
			<div class="bp-dash-stat">
				<div class="v"><?php echo (int) $contact_n; ?></div>
				<div class="k"><?php esc_html_e( 'Contact messages', 'blog-pro' ); ?></div>
			</div>
			<?php if ( null !== $perf_score ) : ?>
			<div class="bp-dash-stat">
				<div class="v"><?php echo (int) $perf_score; ?></div>
				<div class="k"><?php esc_html_e( 'Performance status', 'blog-pro' ); ?></div>
			</div>
			<?php endif; ?>
			<?php if ( null !== $page_speed ) : ?>
			<div class="bp-dash-stat">
				<div class="v"><?php echo (int) $page_speed; ?> <span class="u">ms</span></div>
				<div class="k"><?php esc_html_e( 'LCP (approx)', 'blog-pro' ); ?></div>
			</div>
			<?php endif; ?>
		</div>

		<?php if ( $img_unopt > 0 ) : ?>
			<div class="notice notice-warning is-dismissible inline"><p>
				<strong><?php esc_html_e( 'Images awaiting optimization', 'blog-pro' ); ?></strong>
				<?php printf( esc_html( _n( '%d image is', '%d images are', $img_unopt, 'blog-pro' ) ), (int) $img_unopt ); ?>
				<?php esc_html_e( 'still using the original format.', 'blog-pro' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-optimize-images' ) ); ?>"><?php esc_html_e( 'Optimize now', 'blog-pro' ); ?></a>
			</p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Quick actions', 'blog-pro' ); ?></h2>
		<div class="bp-dash-actions">
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'New post', 'blog-pro' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-optimize-images' ) ); ?>"><?php esc_html_e( 'Optimize images', 'blog-pro' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-seo-checker' ) ); ?>"><?php esc_html_e( 'SEO check', 'blog-pro' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-contact' ) ); ?>"><?php esc_html_e( 'Contact inbox', 'blog-pro' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-about' ) ); ?>"><?php esc_html_e( 'About theme', 'blog-pro' ); ?></a>
		</div>

		<style>
		.bp-dash-wrap{ max-width:1100px; }
		.bp-dash-lead{ color:#646970; font-size:13px; }
		.bp-dash-stats{ display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin:16px 0; }
		.bp-dash-stat{ background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:14px 16px; }
		.bp-dash-stat .v{ font-size:28px; font-weight:700; }
		.bp-dash-stat .v .u{ font-size:14px; font-weight:400; color:#646970; }
		.bp-dash-stat .k{ font-size:12px; color:#646970; margin-top:2px; }
		.bp-dash-actions{ display:flex; gap:10px; flex-wrap:wrap; margin:10px 0 24px; }
		</style>
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

	$theme  = wp_get_theme();
	$name   = $theme->get( 'Name' );
	$ver    = $theme->get( 'Version' );
	$server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
	?>
	<div class="wrap about-wrap">
		<h1><?php
			/* translators: %s: theme name */
			printf( esc_html__( 'About %s', 'blog-pro' ), esc_html( $name ) );
		?></h1>
		<p class="about-text"><?php esc_html_e( 'An ultra-lightweight, dependency-free blogging theme built for sub-second load times — SEO, schema, sitemap, REST API and speed tuning baked in. No plugins required.', 'blog-pro' ); ?></p>

		<div class="bp-about-cards">

			<div class="bp-about-card">
				<h2><?php esc_html_e( 'Version', 'blog-pro' ); ?></h2>
				<p class="bp-big"><?php echo esc_html( $ver ); ?></p>
				<p class="bp-sub"><?php printf(
					/* translators: 1: WordPress requirement, 2: PHP requirement */
					esc_html__( 'Requires WordPress %1$s+ and PHP %2$s+', 'blog-pro' ),
					esc_html( $theme->get( 'RequiresWP' ) ?: '6.0' ),
					esc_html( $theme->get( 'RequiresPHP' ) ?: '7.4' )
				); ?></p>
			</div>

			<div class="bp-about-card">
				<h2><?php esc_html_e( 'Author', 'blog-pro' ); ?></h2>
				<p class="bp-big"><?php echo esc_html( $theme->get( 'Author' ) ); ?></p>
				<p class="bp-sub"><a href="<?php echo esc_url( $theme->get( 'AuthorURI' ) ?? '#' ); ?>"><?php echo esc_html( $theme->get( 'AuthorURI' ) ?? '' ); ?></a></p>
			</div>

			<div class="bp-about-card">
				<h2><?php esc_html_e( 'License', 'blog-pro' ); ?></h2>
				<p class="bp-big"><?php esc_html_e( 'GPL v2+', 'blog-pro' ); ?></p>
				<p class="bp-sub"><?php
					if ( $theme->get( 'LicenseURI' ) ) :
						?><a href="<?php echo esc_url( $theme->get( 'LicenseURI' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'GNU General Public License', 'blog-pro' ); ?></a><?php
					else :
						esc_html_e( 'GNU General Public License v2 or later', 'blog-pro' );
					endif;
				?></p>
			</div>

			<div class="bp-about-card">
				<h2><?php esc_html_e( 'Server', 'blog-pro' ); ?></h2>
				<p class="bp-big"><?php echo esc_html( $server ?: __( 'Unknown', 'blog-pro' ) ); ?></p>
				<p class="bp-sub"><?php printf(
					/* translators: %s: WordPress version */
					esc_html__( 'WordPress %s', 'blog-pro' ),
					esc_html( get_bloginfo( 'version' ) )
				); ?></p>
			</div>
		</div>

		<h2><?php esc_html_e( 'What is built in', 'blog-pro' ); ?></h2>
		<table class="widefat striped bp-about-table">
			<tbody>
				<tr><th><?php esc_html_e( 'Speed', 'blog-pro' ); ?></th>
					<td><?php esc_html_e( 'Zero front-end dependencies, deferred scripts, WebP-ready image pipeline, lazy loading, caching rules in .htaccess — built for sub-second loads and green Core Web Vitals.', 'blog-pro' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'SEO (no plugin)', 'blog-pro' ); ?></th>
					<td><?php esc_html_e( 'Dynamic meta title/description, canonical URL, Open Graph + Twitter Cards, robots directives, XML sitemap, robots.txt, and JSON-LD schema (Article, FAQPage, Breadcrumbs).', 'blog-pro' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'SEO Checker', 'blog-pro' ); ?></th>
					<td><?php esc_html_e( 'Automated on-page audit of every post — title, description, keyword density, headings, links, images, schema, readability — with a live score in the post editor and one-click fixes. Under Blog Pro → SEO Checker.', 'blog-pro' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'FAQ system', 'blog-pro' ); ?></th>
					<td><?php esc_html_e( 'One FAQ block per post, styled accordion, FAQPage structured data, and one-click migration of old hand-written / Rank Math FAQ markup. Under Blog Pro → FAQ Migration.', 'blog-pro' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Internal linking', 'blog-pro' ); ?></th>
					<td><?php esc_html_e( 'Automatic keyword → related-post links at render time (no content rewrite), plus per-post manual rules: keyword => URL. Maximum 3 links per post by default.', 'blog-pro' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Table of contents', 'blog-pro' ); ?></th>
					<td><?php esc_html_e( 'Auto-generated TOC block from H2/H3 headings with anchor links and sticky-header offset.', 'blog-pro' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Media optimization', 'blog-pro' ); ?></th>
					<td><?php esc_html_e( 'Bulk image optimizer (WebP/AVIF/E-WebP, quality + max-width control), force-WebP serving, and alt/title auto-fill for accessibility.', 'blog-pro' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'REST API', 'blog-pro' ); ?></th>
					<td><?php esc_html_e( 'Custom endpoint serving posts as clean JSON with featured images and categories — headless-ready.', 'blog-pro' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Content tools', 'blog-pro' ); ?></th>
					<td><?php esc_html_e( 'Contact form + submissions inbox (no plugin), social share buttons, reading-progress bar, related posts, breadcrumbs, widgets (categories, recent posts, etc.), and WebMCP/llms.txt integration.', 'blog-pro' ); ?></td></tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Support & attribution', 'blog-pro' ); ?></h2>
		<p><?php esc_html_e( 'This theme is distributed under the terms of the GNU General Public License v2 or later. You are free to use, modify, and redistribute it. Developed and maintained by', 'blog-pro' ); ?>
			<a href="<?php echo esc_url( 'https://nhrepon.com' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Blog Pro', 'blog-pro' ); ?></a>.
		</p>

		<style>
		.bp-about-cards{ display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin:18px 0 28px; }
		.bp-about-card{ background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:16px 18px; }
		.bp-about-card h2{ margin-top:0; font-size:13px; text-transform:uppercase; letter-spacing:.4px; color:#646970; }
		.bp-big{ font-size:22px; font-weight:700; margin:4px 0 2px; }
		.bp-sub{ margin:0; font-size:12px; color:#646970; }
		.bp-about-table th{ width:220px; }
		.bp-about-table td{ line-height:1.6; }
		</style>
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
