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

// The Optimize Images menu lives under Blog Pro → Optimize Images
// (registered in admin/class-blogpro-admin-menu.php). The page render
// callback is defined here.

function blogpro_optimize_images_enqueue( $hook ) {
	// Hook name for a submenu of 'blogpro-dashboard' (sanitized to
	// 'blog-pro'): 'blog-pro_page_' . menu_slug.
	if ( 'blog-pro_page_blogpro-optimize-images' !== $hook ) return;
	wp_enqueue_script( 'blogpro-optimize-images', BLOGPRO_URI . '/assets/js/admin-optimize-images.js', array(), BLOGPRO_VERSION, true );
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
		<p><?php esc_html_e( 'Scan the uploads directory and remove .webp and .avif files whose source image (.jpg, .png) no longer exists, and sweep the blogpro-cache folder for stale resized images. This cleans up leftovers from previously deleted images.', 'blog-pro' ); ?></p>
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

/* Cleanup orphaned image files.
 *
 * Two passes:
 *   1. uploads/ — remove .webp/.avif files whose source image (.jpg/.jpeg/.png)
 *      no longer exists. Leftovers from deleted attachments.
 *   2. blogpro-cache/ — the on-the-fly resizer's output dir. Cache files are
 *      named {original-name}-{width}.webp (see media-optimize.php). A cache
 *      file is an orphan when no file with that original name exists anywhere
 *      in uploads/ (source deleted or re-uploaded). Caches for current sources
 *      are always re-generated on demand, so removing stale ones is safe.
 */
function blogpro_ajax_cleanup_orphans() {
	check_ajax_referer( 'blogpro_optimize_images', 'nonce' );
	if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error( 'forbidden', 403 );

	$removed   = 0;
	$upload    = wp_upload_dir();
	$basedir   = trailingslashit( wp_normalize_path( $upload['basedir'] ) );
	$cache_dir = $basedir . 'blogpro-cache';

	// Index source images (.jpg/.jpeg/.png) under uploads/ by basename, so we
	// can answer "does a source for name X exist?" without re-scanning per file.
	// The key is the basename without extension (e.g. 'holiday-768x512').
	$sources = array();
	if ( is_dir( $basedir ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $basedir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $entry ) {
			if ( ! $entry->isFile() ) continue;
			$ext = strtolower( $entry->getExtension() );
			if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) continue;
			$sources[ $entry->getBasename( '.' . $entry->getExtension() ) ] = true;
		}
	}

	// Record every file that is a real attachment's original, so Pass 1 never
	// deletes one. A .webp/.avif in the library can be a native upload (no
	// jpg/png source at all), so we must not infer orphan-ness purely from
	// missing file-system sources — check the DB before unlinking anything.
	global $wpdb;
	$attached_files = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'" );
	$attached = array();
	foreach ( $attached_files as $rel ) {
		$attached[ wp_normalize_path( $rel ) ] = true;
	}

	// Pass 1 — orphaned .webp/.avif in uploads/ whose source is gone.
	if ( is_dir( $basedir ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $basedir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $entry ) {
			if ( ! $entry->isFile() ) continue;
			$file = wp_normalize_path( $entry->getPathname() );
			// Skip the resizer cache dir — handled separately below.
			if ( strpos( $file, wp_normalize_path( $cache_dir ) . DIRECTORY_SEPARATOR ) === 0 ) continue;

			$ext = strtolower( $entry->getExtension() );
			if ( ! in_array( $ext, array( 'webp', 'avif' ), true ) ) continue;

			// Never delete a file that's itself a live attachment's original —
			// .webp/.avif are valid library formats, so a native upload may
			// have no jpg/png source at all.
			$rel = ltrim( str_replace( $basedir, '', $file ), '/' );
			if ( isset( $attached[ $rel ] ) ) continue;

			$base = $entry->getBasename( '.' . $entry->getExtension() );
			// The paired source may be the full name (holiday.webp ->
			// holiday.jpg), or — for legacy thumbnails — the base minus a
			// width/scale suffix (photo-768x512.webp -> photo.jpg).
			if ( isset( $sources[ $base ] ) ) continue;
			$plain = preg_replace( '/-\d+x\d+$/', '', $base );
			if ( $plain !== $base && isset( $sources[ $plain ] ) ) continue;

			@unlink( $file );
			$removed++;
		}
	}

	// Pass 2 — stale variants in the resizer cache dir.
	if ( is_dir( $cache_dir ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cache_dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $entry ) {
			if ( ! $entry->isFile() ) continue;
			if ( strtolower( $entry->getExtension() ) !== 'webp' ) continue;

			// Cache format is {name}-{width}.webp. The width is the last
			// numeric segment — strip it to get the source-image name.
			$parts = explode( '-', $entry->getBasename( '.webp' ) );
			array_pop( $parts );
			$name = implode( '-', $parts );
			if ( '' === $name ) continue; // not one of ours

			if ( isset( $sources[ $name ] ) ) continue; // source exists

			@unlink( wp_normalize_path( $entry->getPathname() ) );
			$removed++;
		}
	}

	wp_send_json_success( array( 'message' => sprintf(
		/* translators: %d: number of orphaned files removed */
		__( 'Cleanup complete — removed %d orphaned file(s).', 'blog-pro' ),
		$removed
	) ) );
}
add_action( 'wp_ajax_blogpro_cleanup_orphans', 'blogpro_ajax_cleanup_orphans' );

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

/* ---------------------------------------------------------------------
 * Cleanup submenu — under Blog Pro → Cleanup.
 * Handles orphaned files and unused intermediate sizes.
 * ------------------------------------------------------------------- */

function blogpro_cleanup_menu() {
	add_submenu_page(
		'blogpro-dashboard',
		__( 'Cleanup', 'blog-pro' ),
		__( 'Cleanup', 'blog-pro' ),
		'manage_options',
		'blogpro-cleanup',
		'blogpro_render_cleanup_page',
		999
	);
}
add_action( 'admin_menu', 'blogpro_cleanup_menu', 1000 );

function blogpro_render_cleanup_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$stats = null;
	if ( isset( $_GET['blogpro_cleanup'] ) && check_admin_referer( 'blogpro_cleanup' ) ) {
		$rev_stats = blogpro_cleanup_revisions();
		$img_stats = blogpro_cleanup_unused_image_sizes();
		$stats     = array_merge( $rev_stats, $img_stats );
		update_option( 'blogpro_last_cleanup', current_time( 'mysql' ) );
	}
	$last = get_option( 'blogpro_last_cleanup' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Cleanup', 'blog-pro' ); ?></h1>
		<p><?php esc_html_e( 'Deletes stale post revisions and orphaned intermediate image sizes (and their WebP twins) that this theme does not use. Also removes orphaned .webp / .avif files whose source no longer exists.', 'blog-pro' ); ?></p>
		<?php if ( $stats ) : ?>
			<div class="notice notice-success"><p>
				<?php
				printf(
					esc_html__( 'Done — %1$d revisions, %2$d files removed (%3$s).', 'blog-pro' ),
					(int) $stats['revisions'],
					(int) $stats['images'],
					size_format( (int) $stats['bytes'] )
				);
				?>
			</p></div>
		<?php endif; ?>
		<?php if ( $last ) : ?>
			<p><?php printf( esc_html__( 'Last run: %s', 'blog-pro' ), esc_html( $last ) ); ?></p>
		<?php endif; ?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=blogpro-cleanup&blogpro_cleanup=1' ), 'blogpro_cleanup' ) ); ?>">
				<?php esc_html_e( 'Run cleanup now', 'blog-pro' ); ?>
			</a>
		</p>
		<hr style="margin:24px 0">
		<h2><?php esc_html_e( 'Orphaned .webp / .avif Files', 'blog-pro' ); ?></h2>
		<p><?php esc_html_e( 'Scan the uploads directory and remove .webp and .avif files whose source image (.jpg, .png) no longer exists, and sweep the blogpro-cache folder for stale resized images.', 'blog-pro' ); ?></p>
		<p>
			<button type="button" class="button" id="blogpro-cleanup-start"><?php esc_html_e( 'Cleanup Orphans', 'blog-pro' ); ?></button>
		</p>
		<p id="blogpro-cleanup-status" style="display:none"></p>
		<script>
		(function(){
			var btn = document.getElementById('blogpro-cleanup-start');
			var sts = document.getElementById('blogpro-cleanup-status');
			if (!btn) return;
			btn.addEventListener('click', function(){
				btn.disabled = true;
				btn.textContent = 'Scanning…';
				sts.style.display = 'block';
				sts.textContent = 'Scanning…';
				var body = new URLSearchParams({action:'blogpro_cleanup_orphans', nonce:'<?php echo wp_create_nonce( 'blogpro_optimize_images' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>'});
				fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {method:'POST', credentials:'same-origin', body:body})
					.then(function(r){return r.json()})
					.then(function(res){
						btn.disabled = false;
						btn.textContent = 'Cleanup Orphans';
						sts.textContent = res.success ? res.data.message : 'Cleanup failed. Please try again.';
					})
					.catch(function(){
						btn.disabled = false;
						btn.textContent = 'Cleanup Orphans';
						sts.textContent = 'Something went wrong. Please try again.';
					});
			});
		})();
		</script>
	</div>
	<?php
}
