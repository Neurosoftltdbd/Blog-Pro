<?php
/**
 * "Optimize Existing Images" tool (Media → Optimize Images).
 *
 * The WebP conversion in inc/media-optimize.php only runs automatically
 * on NEW uploads (it's hooked to wp_generate_attachment_metadata, which
 * WordPress fires at upload/edit time). Images that were already in the
 * Media Library before this theme was activated never trigger that hook,
 * so they're skipped by default.
 *
 * This tool walks the existing library in small batches (safe for
 * shared hosting timeouts) and, for each JPEG/PNG:
 *   1. Regenerates thumbnail sizes — so older uploads get the theme's
 *      own blogpro-card / blogpro-hero sizes, not just WP's defaults.
 *   2. Generates a .webp copy of the original and every size.
 * It skips anything already converted, so it's safe to re-run anytime
 * (e.g. after adding more images) without redoing finished work.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function blogpro_optimize_images_menu() {
	add_media_page(
		__( 'Optimize Images', 'blog-pro' ),
		__( 'Optimize Images', 'blog-pro' ),
		'upload_files',
		'blogpro-optimize-images',
		'blogpro_render_optimize_images_page'
	);
}
add_action( 'admin_menu', 'blogpro_optimize_images_menu' );

function blogpro_optimize_images_enqueue( $hook ) {
	if ( 'media_page_blogpro-optimize-images' !== $hook ) return;
	wp_enqueue_script( 'blogpro-optimize-images', BLOGPRO_URI . '/js/admin-optimize-images.js', array(), BLOGPRO_VERSION, true );
	wp_localize_script( 'blogpro-optimize-images', 'blogproOptimize', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'blogpro_optimize_images' ),
		'batch'   => 4,
		'i18n'    => array(
			'start'    => __( 'Optimizing…', 'blog-pro' ),
			'done'     => __( 'Done — all images are optimized.', 'blog-pro' ),
			'progress' => __( 'Optimized %1$d of %2$d images (%3$d new WebP files created)…', 'blog-pro' ),
			'error'    => __( 'Something went wrong. You can click Start again to resume — already-optimized images are skipped automatically.', 'blog-pro' ),
		),
	) );
}
add_action( 'admin_enqueue_scripts', 'blogpro_optimize_images_enqueue' );

function blogpro_render_optimize_images_page() {
	if ( ! current_user_can( 'upload_files' ) ) return;
	$webp_supported = function_exists( 'imagewebp' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Optimize Existing Images', 'blog-pro' ); ?></h1>
		<p><?php esc_html_e( 'New uploads are optimized automatically. Use this once to catch up any images that were added before Blog Pro was activated — it regenerates the theme\'s thumbnail sizes and creates WebP copies. Safe to re-run anytime; finished images are skipped.', 'blog-pro' ); ?></p>

		<?php if ( ! $webp_supported ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Your server\'s GD library does not support WebP output, so thumbnail sizes will still be regenerated, but WebP copies cannot be created here.', 'blog-pro' ); ?></p></div>
		<?php endif; ?>

		<p>
			<button type="button" class="button button-primary" id="blogpro-optimize-start"><?php esc_html_e( 'Start Optimizing', 'blog-pro' ); ?></button>
		</p>
		<div id="blogpro-optimize-progress" style="max-width:520px;display:none">
			<div style="background:#e5e5e5;border-radius:6px;height:14px;overflow:hidden">
				<div id="blogpro-optimize-bar" style="background:#2271b1;height:100%;width:0%;transition:width .2s"></div>
			</div>
			<p id="blogpro-optimize-status"></p>
		</div>

		<hr style="margin:24px 0">

		<h2><?php esc_html_e( 'Cleanup Orphaned Files', 'blog-pro' ); ?></h2>
		<p><?php esc_html_e( 'Scan the uploads directory and remove .webp and .avif files whose source image (.jpg, .png) no longer exists. This cleans up leftovers from previously deleted images.', 'blog-pro' ); ?></p>
		<p>
			<button type="button" class="button" id="blogpro-cleanup-start"><?php esc_html_e( 'Cleanup Orphans', 'blog-pro' ); ?></button>
		</p>
		<p id="blogpro-cleanup-status" style="display:none"></p>
	</div>
	<?php
}

/* Total count of JPEG/PNG attachments — used to size the progress bar. */
function blogpro_ajax_optimize_count() {
	check_ajax_referer( 'blogpro_optimize_images', 'nonce' );
	if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error( 'forbidden', 403 );

	$total = ( new WP_Query( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => array( 'image/jpeg', 'image/png' ),
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) ) )->found_posts;

	wp_send_json_success( array( 'total' => (int) $total ) );
}
add_action( 'wp_ajax_blogpro_optimize_count', 'blogpro_ajax_optimize_count' );

/* Processes one small batch: regenerates sizes + converts to WebP. */
function blogpro_ajax_optimize_batch() {
	check_ajax_referer( 'blogpro_optimize_images', 'nonce' );
	if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error( 'forbidden', 403 );

	$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
	$batch  = isset( $_POST['batch'] ) ? max( 1, min( 10, absint( $_POST['batch'] ) ) ) : 4;

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$ids = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => array( 'image/jpeg', 'image/png' ),
		'posts_per_page' => $batch,
		'offset'         => $offset,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'fields'         => 'ids',
	) );

	$webp_created = 0;
	foreach ( $ids as $id ) {
		$file = get_attached_file( $id );
		if ( $file && file_exists( $file ) ) {
			// Regenerate thumbnails — picks up blogpro-card / blogpro-hero
			// sizes for images uploaded before the theme was active, and
			// re-applies the big_image_size_threshold downscale.
			$metadata = wp_generate_attachment_metadata( $id, $file );
			if ( ! is_wp_error( $metadata ) && $metadata ) {
				wp_update_attachment_metadata( $id, $metadata );
			}
			$webp_created += blogpro_convert_attachment_to_webp( $id, $metadata );
		}
	}

	wp_send_json_success( array(
		'processed' => count( $ids ),
		'webp'      => $webp_created,
		'more'      => count( $ids ) === $batch,
	) );
}
add_action( 'wp_ajax_blogpro_optimize_batch', 'blogpro_ajax_optimize_batch' );
