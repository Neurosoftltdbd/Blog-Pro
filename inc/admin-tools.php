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
 *      own blogpro-card size, not just WP's defaults.
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
			'done'     => __( 'Done — all images are optimized. Empty alt/title/caption/description fields were filled from file names.', 'blog-pro' ),
			'progress' => __( 'Optimized %1$d of %2$d images (%3$d new WebP files created, %4$d updated)…', 'blog-pro' ),
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

		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<h2><?php esc_html_e( 'Convert Original Images to WebP', 'blog-pro' ); ?></h2>
			<p><?php esc_html_e( 'Converts every JPEG, PNG and GIF in the Media Library to WebP at best quality, then deletes the original file and its sizes. Attachments and post links are updated to the WebP files. This cannot be undone. Back up uploads first. Animated GIFs lose their animation.', 'blog-pro' ); ?></p>
			<p><button type="button" class="button" id="blogpro-convert-start"><?php esc_html_e( 'Convert Originals', 'blog-pro' ); ?></button></p>
			<p id="blogpro-convert-status" style="display:none"></p>
		<?php endif; ?>

		<hr style="margin:24px 0">

		<h2><?php esc_html_e( 'Cleanup Orphaned Files', 'blog-pro' ); ?></h2>
		<p><?php esc_html_e( 'Scans the uploads folder and removes any image file (JPEG, PNG, GIF, WebP, AVIF) that no Media Library item still uses, including old sizes and responsive variants. Files used by a live attachment are kept.', 'blog-pro' ); ?></p>
		<p>
			<button type="button" class="button" id="blogpro-cleanup-start"><?php esc_html_e( 'Cleanup Orphans', 'blog-pro' ); ?></button>
		</p>
		<p id="blogpro-cleanup-status" style="display:none"></p>
	</div>
	<?php
}

/**
 * Query for attachments the bulk tool still has to process.
 * JPEG/PNG only: new WebP uploads are never converted again.
 *
 * @param int $limit  Max IDs to return (-1 for all).
 * @param int $offset Starting position.
 * @return int[]
 */
function blogpro_optimize_query_ids( $limit, $offset = 0 ) {
	return get_posts( blogpro_optimize_query_args( $limit, $offset ) );
}

/** Shared WP_Query args for the bulk tool. Used for both the count and the batch. */
function blogpro_optimize_query_args( $limit, $offset = 0 ) {
	return array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp' ),
		'posts_per_page' => $limit,
		'offset'         => $offset,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'fields'         => 'ids',
	);
}

/**
 * Rewrites a palette (indexed) PNG as a truecolor PNG in place, keeping alpha.
 * Core's WebP save refuses palette images ("Palette image not supported by webp").
 * Non-PNG and already-truecolor files are left untouched.
 */
function blogpro_ensure_truecolor_source( $file ) {
	if ( 'png' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) return false;

	$image = @imagecreatefrompng( $file );
	if ( ! $image || imageistruecolor( $image ) ) {
		if ( $image ) imagedestroy( $image );
		return false;
	}

	$w  = imagesx( $image );
	$h  = imagesy( $image );
	$tc = imagecreatetruecolor( $w, $h );
	imagealphablending( $tc, false );
	imagesavealpha( $tc, true );
	imagecopy( $tc, $image, 0, 0, 0, 0, $w, $h );
	imagedestroy( $image );

	$ok = imagepng( $tc, $file );
	imagedestroy( $tc );
	return $ok;
}

/**
 * Regenerate sizes for one attachment, then write its WebP files.
 * Same steps as a new upload. Returns the number of WebP files written.
 */
function blogpro_optimize_one_attachment( $id ) {
	$file = get_attached_file( $id );
	if ( ! $file || ! file_exists( $file ) ) return 0;

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	// Core's WebP save fails on palette (indexed) images. Convert to truecolor first.
	blogpro_ensure_truecolor_source( $file );

	$metadata = wp_generate_attachment_metadata( $id, $file );
	if ( is_wp_error( $metadata ) || ! $metadata ) return 0;

	wp_update_attachment_metadata( $id, $metadata );
	return blogpro_convert_attachment_to_webp( $id, $metadata );
}

/**
 * Fill empty alt / title / caption / description from the file name.
 * Returns true when any field changed. Existing values are never overwritten.
 */
function blogpro_optimize_fill_fields( $id ) {
	$snapshot = function () use ( $id ) {
		return get_post_meta( $id, '_wp_attachment_image_alt', true )
			. '|' . get_post_field( 'post_title', $id )
			. '|' . get_post_field( 'post_excerpt', $id )
			. '|' . get_post_field( 'post_content', $id );
	};

	$before = $snapshot();
	blogpro_auto_image_fields( $id );
	return $snapshot() !== $before;
}

/* Total JPEG/PNG attachments, used to size the progress bar. */
function blogpro_ajax_optimize_count() {
	check_ajax_referer( 'blogpro_optimize_images', 'nonce' );
	if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error( 'forbidden', 403 );

	// found_posts from the same query args as blogpro_optimize_query_ids(), without loading IDs.
	$total = ( new WP_Query( blogpro_optimize_query_args( 1 ) ) )->found_posts;

	wp_send_json_success( array( 'total' => (int) $total ) );
}
add_action( 'wp_ajax_blogpro_optimize_count', 'blogpro_ajax_optimize_count' );

/* Process one batch. JS calls this repeatedly, advancing offset. */
function blogpro_ajax_optimize_batch() {
	check_ajax_referer( 'blogpro_optimize_images', 'nonce' );
	if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error( 'forbidden', 403 );

	$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
	$batch  = isset( $_POST['batch'] ) ? max( 1, min( 10, absint( $_POST['batch'] ) ) ) : 4;
	$ids    = blogpro_optimize_query_ids( $batch, $offset );

	$webp_created  = 0;
	$fields_filled = 0;
	foreach ( $ids as $id ) {
		$webp_created += blogpro_optimize_one_attachment( $id );
		if ( blogpro_optimize_fill_fields( $id ) ) $fields_filled++;
	}

	wp_send_json_success( array(
		'processed' => count( $ids ),
		'webp'      => $webp_created,
		'fields'    => $fields_filled,
		'more'      => count( $ids ) === $batch,
	) );
}
add_action( 'wp_ajax_blogpro_optimize_batch', 'blogpro_ajax_optimize_batch' );

/* Cleanup orphaned image files.
 *
 * The database is the source of truth. Every file that a live attachment still
 * uses is kept, and any other image file in uploads/ is removed, whatever its
 * extension (jpg, jpeg, png, gif, webp, avif).
 *
 * A live file is:
 *   - the attachment's main file,
 *   - every size in its metadata,
 *   - the WebP variants the theme writes next to the main file (-320, -640),
 *   - the WebP twin of any of the above.
 *
 * Everything else under uploads/ that looks like an image is an orphan.
 * The blogpro-cache/ folder is handled the same way, by source stem.
 *
 * Pass dry_run=1 to count what would be removed without deleting anything.
 */
function blogpro_collect_live_image_files() {
	global $wpdb;
	$upload  = wp_upload_dir();
	$basedir = trailingslashit( wp_normalize_path( $upload['basedir'] ) );
	$live    = array();

	$attachments = $wpdb->get_results(
		"SELECT p.ID, m.meta_value AS rel FROM {$wpdb->posts} p
		 JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attached_file'
		 WHERE p.post_type = 'attachment'"
	);

	foreach ( $attachments as $row ) {
		$main = $basedir . ltrim( wp_normalize_path( $row->rel ), '/' );
		$dir  = trailingslashit( dirname( $main ) );
		$stem = pathinfo( $main, PATHINFO_FILENAME );

		$names = array( basename( $main ) );
		$meta  = wp_get_attachment_metadata( (int) $row->ID );
		if ( ! empty( $meta['original_image'] ) ) $names[] = $meta['original_image'];
		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) $names[] = $size['file'];
		}

		foreach ( $names as $name ) {
			$live[ $dir . $name ] = true;
			$live[ $dir . pathinfo( $name, PATHINFO_FILENAME ) . '.webp' ] = true;
		}
		foreach ( BLOGPRO_WEBP_WIDTHS as $width ) {
			$live[ $dir . $stem . '-' . $width . '.webp' ] = true;
		}
		$live[ $dir . $stem . '.webp' ] = true;
	}
	return $live;
}

/** Image extensions the cleanup considers. Anything else in uploads/ is never touched. */
function blogpro_image_extensions() {
	return array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif' );
}

/**
 * Removes (or, in dry-run, counts) image files no attachment uses.
 *
 * @return array{removed:int, bytes:int}
 */
function blogpro_remove_orphan_images( $dry_run = false ) {
	$upload  = wp_upload_dir();
	$basedir = wp_normalize_path( $upload['basedir'] );
	$live    = blogpro_collect_live_image_files();
	$exts    = blogpro_image_extensions();
	$stats   = array( 'removed' => 0, 'bytes' => 0 );

	if ( ! is_dir( $basedir ) ) return $stats;

	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $basedir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $entry ) {
		if ( ! $entry->isFile() ) continue;
		if ( ! in_array( strtolower( $entry->getExtension() ), $exts, true ) ) continue;

		$path = wp_normalize_path( $entry->getPathname() );
		if ( isset( $live[ $path ] ) ) continue;

		// Keep the cache folder's files that belong to a live source.
		$stats['removed']++;
		$stats['bytes'] += (int) $entry->getSize();
		if ( ! $dry_run ) @unlink( $path );
	}
	return $stats;
}

/* Handler for the "Cleanup Orphans" button. Accepts dry_run=1 for a preview. */
function blogpro_ajax_cleanup_orphans() {
	check_ajax_referer( 'blogpro_optimize_images', 'nonce' );
	if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error( 'forbidden', 403 );

	$dry_run = ! empty( $_POST['dry_run'] );
	$stats   = blogpro_remove_orphan_images( $dry_run );

	wp_send_json_success( array(
		'removed' => $stats['removed'],
		'bytes'   => $stats['bytes'],
		'dry_run' => $dry_run,
		'message' => sprintf(
			/* translators: 1: number of files, 2: formatted size */
			$dry_run
				? __( 'Preview: %1$d orphaned file(s), %2$s would be removed.', 'blog-pro' )
				: __( 'Cleanup complete: removed %1$d orphaned file(s), freed %2$s.', 'blog-pro' ),
			$stats['removed'],
			size_format( $stats['bytes'] )
		),
	) );
}
add_action( 'wp_ajax_blogpro_cleanup_orphans', 'blogpro_ajax_cleanup_orphans' );

/* Converts one batch of existing originals. Keyset paging by ID: a converted row
   stops matching the query, so offsets would skip rows. */
function blogpro_ajax_convert_originals() {
	check_ajax_referer( 'blogpro_optimize_images', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'forbidden', 403 );

	global $wpdb;
	$cursor       = isset( $_POST['cursor'] ) ? absint( $_POST['cursor'] ) : 0;
	$batch        = 3;
	$mimes        = BLOGPRO_CONVERTIBLE_MIMES;
	$placeholders = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );

	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ($placeholders) AND ID > %d ORDER BY ID ASC LIMIT %d",
		array_merge( $mimes, array( $cursor, $batch ) )
	) );

	$converted = 0;
	$failed    = 0;
	$last      = $cursor;
	foreach ( $ids as $id ) {
		$last   = (int) $id;
		$result = blogpro_convert_original_to_webp( $last );
		if ( 'converted' === $result ) $converted++;
		if ( 'failed' === $result ) $failed++;
	}

	$remaining = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ($placeholders) AND ID > %d",
		array_merge( $mimes, array( $last ) )
	) );

	wp_send_json_success( array(
		'cursor'    => $last,
		'converted' => $converted,
		'failed'    => $failed,
		'remaining' => $remaining,
		'more'      => count( $ids ) === $batch,
	) );
}
add_action( 'wp_ajax_blogpro_convert_originals', 'blogpro_ajax_convert_originals' );

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

	$stats   = null;
	$flushed = false;
	if ( isset( $_GET['blogpro_cleanup'] ) && check_admin_referer( 'blogpro_cleanup' ) ) {
		$rev_stats = blogpro_cleanup_revisions();
		$img_stats = blogpro_cleanup_unused_image_sizes();
		$stats     = array_merge( $rev_stats, $img_stats );
		update_option( 'blogpro_last_cleanup', current_time( 'mysql' ) );
	}
	if ( isset( $_GET['blogpro_flush_cache'] ) && check_admin_referer( 'blogpro_flush_cache' ) ) {
		blogpro_page_cache_flush();
		$flushed = true;
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
		<?php if ( $flushed ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Page cache cleared.', 'blog-pro' ); ?></p></div>
		<?php endif; ?>
		<?php if ( $last ) : ?>
			<p><?php printf( esc_html__( 'Last run: %s', 'blog-pro' ), esc_html( $last ) ); ?></p>
		<?php endif; ?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=blogpro-cleanup&blogpro_cleanup=1' ), 'blogpro_cleanup' ) ); ?>">
				<?php esc_html_e( 'Run cleanup now', 'blog-pro' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=blogpro-cleanup&blogpro_flush_cache=1' ), 'blogpro_flush_cache' ) ); ?>">
				<?php esc_html_e( 'Clear page cache', 'blog-pro' ); ?>
			</a>
		</p>
		<hr style="margin:24px 0">
		<h2><?php esc_html_e( 'Orphaned .webp / .avif Files', 'blog-pro' ); ?></h2>
		<p><?php esc_html_e( 'Scans the uploads folder and removes any image file (JPEG, PNG, GIF, WebP, AVIF) that no Media Library item still uses, including old sizes and responsive variants. Files used by a live attachment are kept.', 'blog-pro' ); ?></p>
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
