<?php
/**
 * Blog Pro → Analytics — first-party visitor analytics dashboard.
 *
 * Reads the table created by inc/analytics.php and renders:
 *   - KPI cards (pageviews, visitors, sessions, pages/session)
 *   - a daily pageview trend
 *   - which posts were visited (views + unique visitors)
 *   - where visitors came from (country, city)
 *   - devices / browsers / operating systems
 *   - acquisition referrers
 *   - a live tail of the most recent pageviews
 *   - tracking settings and a CSV export
 *
 * Copyright (C) 2026 Nur Hossain Repon, nhrepon (https://nhrepon.com)
 * GPL v2 or later.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------------
 * Menu — Blog Pro → Analytics
 * ------------------------------------------------------------------- */

function blogpro_analytics_menu() {
	add_submenu_page(
		'blogpro-dashboard',
		__( 'Analytics', 'blog-pro' ),
		__( 'Analytics', 'blog-pro' ),
		'manage_options',
		'blogpro-analytics',
		'blogpro_render_analytics_page'
	);
}
add_action( 'admin_menu', 'blogpro_analytics_menu', 1000 );

/* ---------------------------------------------------------------------
 * Range + query helpers
 * ------------------------------------------------------------------- */

/**
 * Resolve the active date range from the query string.
 *
 * @return array array( $start_mysql, $end_mysql, $preset, $from_date, $to_date )
 */
function blogpro_analytics_range() {
	$now    = current_time( 'mysql' );
	$today  = substr( $now, 0, 10 );
	$preset = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '30';

	$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
	$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';

	$is_date = (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) && (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to );

	if ( 'custom' === $preset && $is_date ) {
		return array( $from . ' 00:00:00', $to . ' 23:59:59', 'custom', $from, $to );
	}

	if ( 'all' === $preset ) {
		return array( '1970-01-01 00:00:00', $now, 'all', '', $today );
	}

	$days = array( 'today' => 0, '7' => 6, '30' => 29, '90' => 89, '365' => 364 );
	if ( ! isset( $days[ $preset ] ) ) $preset = '30';

	$from_date = blogpro_analytics_date_offset( $today, -$days[ $preset ] );

	return array( $from_date . ' 00:00:00', $now, $preset, $from_date, $today );
}

/**
 * WHERE clause for a range. Bots are stored but never counted in the UI.
 *
 * @param string $start
 * @param string $end
 * @return string Prepared SQL fragment (no leading AND).
 */
function blogpro_analytics_where( $start, $end ) {
	global $wpdb;
	return $wpdb->prepare( 'created_at BETWEEN %s AND %s', $start, $end ) . ' AND is_bot = 0';
}

/**
 * Run an aggregate against the analytics table. {table} is substituted.
 *
 * @param string $sql    SQL, optionally containing a %s for $params.
 * @param array  $params Prepare params.
 * @return array Row objects.
 */
function blogpro_analytics_rows( $sql, $params = array() ) {
	global $wpdb;
	$sql = str_replace( '{table}', blogpro_analytics_table(), $sql );
	if ( $params ) {
		$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	return (array) $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB
}

/** Single scalar from the analytics table. */
function blogpro_analytics_scalar( $sql ) {
	global $wpdb;
	return $wpdb->get_var( str_replace( '{table}', blogpro_analytics_table(), $sql ) ); // phpcs:ignore WordPress.DB
}

/** Every stat the dashboard needs for a range, in one pass. */
function blogpro_analytics_summary( $start, $end ) {
	$where = blogpro_analytics_where( $start, $end );

	$views    = (int) blogpro_analytics_scalar( "SELECT COUNT(*) FROM {table} WHERE {$where}" );
	$visitors = (int) blogpro_analytics_scalar( "SELECT COUNT(DISTINCT visitor_hash) FROM {table} WHERE {$where}" );
	$sessions = (int) blogpro_analytics_scalar( "SELECT COUNT(DISTINCT session_hash) FROM {table} WHERE {$where}" );

	$posts = blogpro_analytics_rows(
		"SELECT post_id, MAX(page_title) AS page_title, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors
		 FROM {table} WHERE {$where} AND post_id > 0
		 GROUP BY post_id ORDER BY views DESC LIMIT 20"
	);

	$other_pages = blogpro_analytics_rows(
		"SELECT url, MAX(page_title) AS page_title, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors
		 FROM {table} WHERE {$where} AND post_id = 0
		 GROUP BY url_hash ORDER BY views DESC LIMIT 10"
	);

	$countries = blogpro_analytics_rows(
		"SELECT country, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors
		 FROM {table} WHERE {$where} AND country <> ''
		 GROUP BY country ORDER BY views DESC LIMIT 20"
	);

	$cities = blogpro_analytics_rows(
		"SELECT city, country, COUNT(*) AS views
		 FROM {table} WHERE {$where} AND city <> ''
		 GROUP BY city ORDER BY views DESC LIMIT 10"
	);

	$devices = blogpro_analytics_rows(
		"SELECT device, COUNT(*) AS views FROM {table} WHERE {$where} GROUP BY device ORDER BY views DESC"
	);

	$browsers = blogpro_analytics_rows(
		"SELECT browser, COUNT(*) AS views FROM {table} WHERE {$where} GROUP BY browser ORDER BY views DESC LIMIT 10"
	);

	$systems = blogpro_analytics_rows(
		"SELECT os, COUNT(*) AS views FROM {table} WHERE {$where} GROUP BY os ORDER BY views DESC LIMIT 10"
	);

	$referrers = blogpro_analytics_rows(
		"SELECT ref_host, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors
		 FROM {table} WHERE {$where} AND ref_host <> ''
		 GROUP BY ref_host ORDER BY views DESC LIMIT 15"
	);

	$recent = blogpro_analytics_rows(
		"SELECT * FROM {table} WHERE {$where} ORDER BY id DESC LIMIT 50"
	);

	$trend = blogpro_analytics_rows(
		"SELECT DATE(created_at) AS day, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors
		 FROM {table} WHERE {$where} GROUP BY day ORDER BY day ASC"
	);

	return compact( 'views', 'visitors', 'sessions', 'posts', 'other_pages', 'countries', 'cities', 'devices', 'browsers', 'systems', 'referrers', 'recent', 'trend' );
}

/**
 * Expand a sparse day => views map into a gapless series, so chart bars are
 * evenly spaced and quiet days read as zero rather than as a missing gap.
 *
 * @param array  $trend Rows with ->day and ->views.
 * @param string $start Range start (mysql datetime).
 * @param string $end   Range end (mysql datetime).
 * @return array day => views
 */
function blogpro_analytics_trend_series( $trend, $start, $end ) {
	$by_day = array();
	foreach ( $trend as $row ) {
		$by_day[ $row->day ] = (int) $row->views;
	}

	$first = date_create_immutable( substr( $start, 0, 10 ), wp_timezone() );
	$last  = date_create_immutable( substr( $end, 0, 10 ), wp_timezone() );
	if ( ! $first || ! $last ) return $by_day;

	// Never render more than ~120 bars.
	$floor = $last->modify( '-120 days' );
	if ( $first < $floor ) $first = $floor;

	$series = array();
	for ( $day = $first; $day <= $last; $day = $day->modify( '+1 day' ) ) {
		$key            = $day->format( 'Y-m-d' );
		$series[ $key ] = isset( $by_day[ $key ] ) ? $by_day[ $key ] : 0;
	}
	return $series;
}

/* ---------------------------------------------------------------------
 * Actions — export and clear (must run before any output)
 * ------------------------------------------------------------------- */

function blogpro_analytics_handle_actions() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'blogpro-analytics' !== $page || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Build/repair the table on demand (first visit, or after a version bump).
	if ( isset( $_GET['create_table'] ) && check_admin_referer( 'blogpro_analytics_create_table' ) ) {
		blogpro_analytics_install();
		wp_safe_redirect( admin_url( 'admin.php?page=blogpro-analytics&msg=table_created' ) );
		exit;
	}

	// CSV export of every raw row in the selected range.
	if ( isset( $_GET['export'] ) && check_admin_referer( 'blogpro_analytics_export' ) ) {
		blogpro_analytics_export_csv();
		exit;
	}

	// Wipe recorded data.
	if ( isset( $_GET['clear'] ) && check_admin_referer( 'blogpro_analytics_clear' ) ) {
		blogpro_analytics_clear_all();
		wp_safe_redirect( admin_url( 'admin.php?page=blogpro-analytics&msg=cleared' ) );
		exit;
	}

	// Run retention now.
	if ( isset( $_GET['purge'] ) && check_admin_referer( 'blogpro_analytics_purge' ) ) {
		$deleted = blogpro_analytics_purge_old();
		wp_safe_redirect( admin_url( 'admin.php?page=blogpro-analytics&msg=purged&n=' . (int) $deleted ) );
		exit;
	}
}
add_action( 'admin_init', 'blogpro_analytics_handle_actions' );

/** Stream the selected range as CSV. */
function blogpro_analytics_export_csv() {
	global $wpdb;

	list( $start, $end ) = blogpro_analytics_range();
	$where = blogpro_analytics_where( $start, $end );
	$table = blogpro_analytics_table();

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB
		"SELECT created_at, url, page_title, post_id, country, city, device, browser, os, ref_host, referrer, user_id, is_bot
		 FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT 100000"
	);

	$filename = 'blogpro-analytics-' . substr( $start, 0, 10 ) . '-to-' . substr( $end, 0, 10 ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );

	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'Date', 'URL', 'Page title', 'Post ID', 'Country', 'City', 'Device', 'Browser', 'OS', 'Referrer host', 'Full referrer', 'User ID', 'Bot' ) );

	foreach ( $rows as $row ) {
		fputcsv( $out, array(
			$row->created_at,
			$row->url,
			$row->page_title,
			$row->post_id,
			blogpro_analytics_country_name( $row->country ),
			$row->city,
			$row->device,
			$row->browser,
			$row->os,
			$row->ref_host,
			$row->referrer,
			$row->user_id,
			$row->is_bot ? 'yes' : 'no',
		) );
	}

	fclose( $out );
}

/* ---------------------------------------------------------------------
 * Render
 * ------------------------------------------------------------------- */

function blogpro_render_analytics_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$settings = blogpro_analytics_settings();

	if ( ! blogpro_analytics_table_exists() ) {
		blogpro_analytics_install();
	}
	$table_ok = blogpro_analytics_table_exists();

	list( $start, $end, $preset, $from_date, $to_date ) = blogpro_analytics_range();
	$stats = $table_ok ? blogpro_analytics_summary( $start, $end ) : null;

	$msg = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
	$n   = isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0;

	$per_session = ( $stats && $stats['sessions'] > 0 ) ? round( $stats['views'] / $stats['sessions'], 2 ) : 0;
	?>
	<div class="wrap bp-an-wrap">
		<h1><?php esc_html_e( 'Analytics', 'blog-pro' ); ?></h1>
		<p class="bp-an-lead"><?php esc_html_e( 'First-party visitor analytics — stored in your own database, no third-party script and no tracking cookies. Bot traffic is recorded separately and excluded from every number below.', 'blog-pro' ); ?></p>

		<?php if ( 'cleared' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'All analytics data cleared.', 'blog-pro' ); ?></p></div>
		<?php elseif ( 'purged' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php printf( esc_html( _n( 'Retention cleanup removed %d row.', 'Retention cleanup removed %d rows.', $n, 'blog-pro' ) ), (int) $n ); ?></p></div>
		<?php elseif ( 'table_created' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Analytics storage table is ready.', 'blog-pro' ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! empty( $_GET['settings-updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'blog-pro' ); ?></p></div>
		<?php endif; ?>

		<?php if ( empty( $settings['enabled'] ) ) : ?>
			<div class="notice notice-warning inline"><p>
				<strong><?php esc_html_e( 'Tracking is off.', 'blog-pro' ); ?></strong>
				<?php esc_html_e( 'No new pageviews are being recorded. Turn it back on in the settings below.', 'blog-pro' ); ?>
			</p></div>
		<?php elseif ( ! $table_ok ) : ?>
			<div class="notice notice-error inline"><p>
				<?php esc_html_e( 'The analytics table could not be created, so nothing can be recorded yet.', 'blog-pro' ); ?>
			</p></div>
		<?php endif; ?>

		<?php /* ---------- Range selector ---------- */ ?>
		<div class="bp-an-range">
			<?php
			$presets = array(
				'today' => __( 'Today', 'blog-pro' ),
				'7'     => __( 'Last 7 days', 'blog-pro' ),
				'30'    => __( 'Last 30 days', 'blog-pro' ),
				'90'    => __( 'Last 90 days', 'blog-pro' ),
				'365'   => __( 'Last 12 months', 'blog-pro' ),
				'all'   => __( 'All time', 'blog-pro' ),
			);
			foreach ( $presets as $key => $label ) :
				$url = add_query_arg( array( 'page' => 'blogpro-analytics', 'range' => $key ), admin_url( 'admin.php' ) );
				?>
				<a class="bp-an-pill<?php echo $preset === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>

			<form method="get" class="bp-an-custom">
				<input type="hidden" name="page" value="blogpro-analytics">
				<input type="hidden" name="range" value="custom">
				<input type="date" name="from" value="<?php echo esc_attr( $from_date ); ?>">
				<span>&rarr;</span>
				<input type="date" name="to" value="<?php echo esc_attr( $to_date ); ?>">
				<button class="button button-small" type="submit"><?php esc_html_e( 'Apply', 'blog-pro' ); ?></button>
			</form>

			<span class="bp-an-export">
				<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'blogpro-analytics', 'range' => $preset, 'from' => $from_date, 'to' => $to_date, 'export' => 1 ), admin_url( 'admin.php' ) ), 'blogpro_analytics_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'blog-pro' ); ?></a>
			</span>
		</div>

		<p class="bp-an-period">
			<?php
			printf(
				/* translators: 1: start date, 2: end date */
				esc_html__( 'Showing %1$s to %2$s', 'blog-pro' ),
				esc_html( substr( $start, 0, 10 ) ),
				esc_html( substr( $end, 0, 10 ) )
			);
			?>
		</p>

		<?php if ( ! $stats ) : ?>
			<div class="notice notice-error inline"><p><?php esc_html_e( 'Analytics storage is unavailable, so there is nothing to show.', 'blog-pro' ); ?></p></div>
		<?php elseif ( 0 === $stats['views'] ) : ?>
			<div class="bp-an-empty">
				<h2><?php esc_html_e( 'No visits recorded in this period', 'blog-pro' ); ?></h2>
				<p><?php esc_html_e( 'Tracking is active — numbers appear as soon as someone loads a page. Try a wider date range, or check the settings below if you expected data already.', 'blog-pro' ); ?></p>
			</div>
		<?php else : ?>

			<?php /* ---------- KPI cards ---------- */ ?>
			<div class="bp-an-stats">
				<div class="bp-an-stat">
					<div class="v"><?php echo esc_html( number_format_i18n( $stats['views'] ) ); ?></div>
					<div class="k"><?php esc_html_e( 'Pageviews', 'blog-pro' ); ?></div>
				</div>
				<div class="bp-an-stat">
					<div class="v"><?php echo esc_html( number_format_i18n( $stats['visitors'] ) ); ?></div>
					<div class="k"><?php esc_html_e( 'Unique visitors', 'blog-pro' ); ?></div>
				</div>
				<div class="bp-an-stat">
					<div class="v"><?php echo esc_html( number_format_i18n( $stats['sessions'] ) ); ?></div>
					<div class="k"><?php esc_html_e( 'Sessions', 'blog-pro' ); ?></div>
				</div>
				<div class="bp-an-stat">
					<div class="v"><?php echo esc_html( $per_session ); ?></div>
					<div class="k"><?php esc_html_e( 'Pages per session', 'blog-pro' ); ?></div>
				</div>
			</div>

			<?php
			/* ---------- Trend ---------- */
			$series = blogpro_analytics_trend_series( $stats['trend'], $start, $end );
			$peak   = $series ? max( $series ) : 0;
			?>
			<div class="bp-an-card">
				<h2><?php esc_html_e( 'Pageviews over time', 'blog-pro' ); ?></h2>
				<?php if ( $peak < 1 ) : ?>
					<p class="bp-an-muted"><?php esc_html_e( 'No daily data in this range.', 'blog-pro' ); ?></p>
				<?php else : ?>
					<div class="bp-an-chart" role="img" aria-label="<?php esc_attr_e( 'Daily pageviews', 'blog-pro' ); ?>">
						<?php foreach ( $series as $day => $views ) : ?>
							<div class="bp-an-bar" style="height:<?php echo esc_attr( max( 2, (int) round( ( $views / $peak ) * 100 ) ) ); ?>%"
								title="<?php echo esc_attr( sprintf( '%s — %s', $day, number_format_i18n( $views ) ) ); ?>"></div>
						<?php endforeach; ?>
					</div>
					<div class="bp-an-axis">
						<span><?php echo esc_html( (string) array_key_first( $series ) ); ?></span>
						<span class="bp-an-peak"><?php printf( esc_html__( 'peak %s/day', 'blog-pro' ), esc_html( number_format_i18n( $peak ) ) ); ?></span>
						<span><?php echo esc_html( (string) array_key_last( $series ) ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<?php /* ---------- Posts visited ---------- */ ?>
			<div class="bp-an-card">
				<h2><?php esc_html_e( 'Most visited posts', 'blog-pro' ); ?></h2>
				<table class="widefat striped bp-an-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Post', 'blog-pro' ); ?></th>
							<th class="bp-an-num"><?php esc_html_e( 'Views', 'blog-pro' ); ?></th>
							<th class="bp-an-num"><?php esc_html_e( 'Visitors', 'blog-pro' ); ?></th>
							<th class="bp-an-num"><?php esc_html_e( 'Share', 'blog-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $stats['posts'] ) : foreach ( $stats['posts'] as $row ) :
							$pid   = (int) $row->post_id;
							$title = get_the_title( $pid );
							if ( '' === $title ) {
								/* translators: %d: post ID */
								$title = $row->page_title ? $row->page_title : sprintf( __( '(deleted post #%d)', 'blog-pro' ), $pid );
							}
							$share = $stats['views'] ? round( ( (int) $row->views / $stats['views'] ) * 100, 1 ) : 0;
							?>
							<tr>
								<td>
									<strong><a href="<?php echo esc_url( (string) get_permalink( $pid ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $title ); ?></a></strong>
									<?php if ( current_user_can( 'edit_post', $pid ) && get_edit_post_link( $pid ) ) : ?>
										<span class="bp-an-actions">— <a href="<?php echo esc_url( (string) get_edit_post_link( $pid ) ); ?>"><?php esc_html_e( 'Edit', 'blog-pro' ); ?></a></span>
									<?php endif; ?>
								</td>
								<td class="bp-an-num"><?php echo esc_html( number_format_i18n( (int) $row->views ) ); ?></td>
								<td class="bp-an-num"><?php echo esc_html( number_format_i18n( (int) $row->visitors ) ); ?></td>
								<td class="bp-an-num">
									<span class="bp-an-meter"><span style="width:<?php echo esc_attr( $share ); ?>%"></span></span>
									<?php echo esc_html( $share ); ?>%
								</td>
							</tr>
						<?php endforeach; else : ?>
							<tr><td colspan="4"><?php esc_html_e( 'No post views in this period.', 'blog-pro' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $stats['other_pages'] ) : ?>
			<div class="bp-an-card">
				<h2><?php esc_html_e( 'Other pages', 'blog-pro' ); ?></h2>
				<table class="widefat striped bp-an-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'URL', 'blog-pro' ); ?></th>
							<th class="bp-an-num"><?php esc_html_e( 'Views', 'blog-pro' ); ?></th>
							<th class="bp-an-num"><?php esc_html_e( 'Visitors', 'blog-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $stats['other_pages'] as $row ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $row->url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row->url ); ?></a></td>
								<td class="bp-an-num"><?php echo esc_html( number_format_i18n( (int) $row->views ) ); ?></td>
								<td class="bp-an-num"><?php echo esc_html( number_format_i18n( (int) $row->visitors ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<div class="bp-an-grid">
				<?php /* ---------- Countries ---------- */ ?>
				<div class="bp-an-card">
					<h2><?php esc_html_e( 'Countries', 'blog-pro' ); ?></h2>
					<table class="widefat striped bp-an-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Country', 'blog-pro' ); ?></th>
								<th class="bp-an-num"><?php esc_html_e( 'Views', 'blog-pro' ); ?></th>
								<th class="bp-an-num"><?php esc_html_e( 'Visitors', 'blog-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( $stats['countries'] ) : foreach ( $stats['countries'] as $row ) : ?>
								<tr>
									<td><span class="bp-an-flag"><?php echo esc_html( $row->country ); ?></span> <?php echo esc_html( blogpro_analytics_country_name( $row->country ) ); ?></td>
									<td class="bp-an-num"><?php echo esc_html( number_format_i18n( (int) $row->views ) ); ?></td>
									<td class="bp-an-num"><?php echo esc_html( number_format_i18n( (int) $row->visitors ) ); ?></td>
								</tr>
							<?php endforeach; else : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No country data yet.', 'blog-pro' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
					<?php if ( empty( $settings['geo_lookup'] ) ) : ?>
						<p class="bp-an-muted"><?php esc_html_e( 'Geo lookup is disabled — only countries reported by your CDN are recorded.', 'blog-pro' ); ?></p>
					<?php endif; ?>
				</div>

				<?php /* ---------- Cities ---------- */ ?>
				<div class="bp-an-card">
					<h2><?php esc_html_e( 'Cities', 'blog-pro' ); ?></h2>
					<table class="widefat striped bp-an-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'City', 'blog-pro' ); ?></th>
								<th><?php esc_html_e( 'Country', 'blog-pro' ); ?></th>
								<th class="bp-an-num"><?php esc_html_e( 'Views', 'blog-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( $stats['cities'] ) : foreach ( $stats['cities'] as $row ) : ?>
								<tr>
									<td><?php echo esc_html( $row->city ); ?></td>
									<td><?php echo esc_html( blogpro_analytics_country_name( $row->country ) ); ?></td>
									<td class="bp-an-num"><?php echo esc_html( number_format_i18n( (int) $row->views ) ); ?></td>
								</tr>
							<?php endforeach; else : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No city data yet.', 'blog-pro' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="bp-an-grid">
				<?php
				/* ---------- Devices / browsers / systems ---------- */
				$breakdowns = array(
					__( 'Devices', 'blog-pro' )  => $stats['devices'],
					__( 'Browsers', 'blog-pro' ) => $stats['browsers'],
					__( 'Systems', 'blog-pro' )  => $stats['systems'],
				);
				foreach ( $breakdowns as $heading => $rows ) :
					?>
					<div class="bp-an-card">
						<h2><?php echo esc_html( $heading ); ?></h2>
						<table class="widefat striped bp-an-table">
							<tbody>
								<?php if ( $rows ) : foreach ( $rows as $row ) :
									$label = isset( $row->device ) ? $row->device : ( isset( $row->browser ) ? $row->browser : $row->os );
									$pct   = $stats['views'] ? round( ( (int) $row->views / $stats['views'] ) * 100, 1 ) : 0;
									?>
									<tr>
										<td style="width:40%"><?php echo esc_html( ucfirst( (string) $label ) ); ?></td>
										<td class="bp-an-num">
											<span class="bp-an-meter"><span style="width:<?php echo esc_attr( $pct ); ?>%"></span></span>
											<?php echo esc_html( $pct ); ?>%
										</td>
										<td class="bp-an-num"><?php echo esc_html( number_format_i18n( (int) $row->views ) ); ?></td>
									</tr>
								<?php endforeach; else : ?>
									<tr><td><?php esc_html_e( 'No data yet.', 'blog-pro' ); ?></td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			</div>

			<?php /* ---------- Referrers ---------- */ ?>
			<div class="bp-an-card">
				<h2><?php esc_html_e( 'Traffic sources', 'blog-pro' ); ?></h2>
				<table class="widefat striped bp-an-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Referring site', 'blog-pro' ); ?></th>
							<th class="bp-an-num"><?php esc_html_e( 'Views', 'blog-pro' ); ?></th>
							<th class="bp-an-num"><?php esc_html_e( 'Visitors', 'blog-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $stats['referrers'] ) : foreach ( $stats['referrers'] as $row ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( 'https://' . $row->ref_host ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $row->ref_host ); ?></a></td>
								<td class="bp-an-num"><?php echo esc_html( number_format_i18n( (int) $row->views ) ); ?></td>
								<td class="bp-an-num"><?php echo esc_html( number_format_i18n( (int) $row->visitors ) ); ?></td>
							</tr>
						<?php endforeach; else : ?>
							<tr><td colspan="3"><?php esc_html_e( 'All traffic is direct — no referring sites recorded in this period.', 'blog-pro' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php /* ---------- Recent visits ---------- */ ?>
			<div class="bp-an-card">
				<h2><?php esc_html_e( 'Recent visits', 'blog-pro' ); ?></h2>
				<table class="widefat striped bp-an-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'When', 'blog-pro' ); ?></th>
							<th><?php esc_html_e( 'Page', 'blog-pro' ); ?></th>
							<th><?php esc_html_e( 'Country', 'blog-pro' ); ?></th>
							<th><?php esc_html_e( 'Device', 'blog-pro' ); ?></th>
							<th><?php esc_html_e( 'Source', 'blog-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $stats['recent'] as $row ) :
							$label = (int) $row->post_id ? get_the_title( (int) $row->post_id ) : '';
							if ( '' === $label ) $label = $row->page_title ? $row->page_title : $row->url;
							?>
							<tr>
								<td><?php echo esc_html( $row->created_at ); ?></td>
								<td><a href="<?php echo esc_url( $row->url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_trim_words( $label, 8 ) ); ?></a></td>
								<td><?php echo esc_html( $row->country ? blogpro_analytics_country_name( $row->country ) : '—' ); ?></td>
								<td><?php echo esc_html( ucfirst( $row->device ) . ' · ' . $row->browser ); ?></td>
								<td><?php echo esc_html( $row->ref_host ? $row->ref_host : __( 'Direct', 'blog-pro' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

		<?php endif; ?>

		<?php /* ---------- Settings ---------- */ ?>
		<div class="bp-an-card">
			<h2><?php esc_html_e( 'Tracking settings', 'blog-pro' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'blogpro_analytics_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Collect analytics', 'blog-pro' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( BLOGPRO_ANALYTICS_OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
								<?php esc_html_e( 'Record pageviews on the front end.', 'blog-pro' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Logged-in users', 'blog-pro' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( BLOGPRO_ANALYTICS_OPTION ); ?>[track_loggedin]" value="1" <?php checked( ! empty( $settings['track_loggedin'] ) ); ?>>
								<?php esc_html_e( 'Count visits from signed-in users.', 'blog-pro' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Administrators', 'blog-pro' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( BLOGPRO_ANALYTICS_OPTION ); ?>[track_admins]" value="1" <?php checked( ! empty( $settings['track_admins'] ) ); ?>>
								<?php esc_html_e( 'Count my own admin visits (leave off for clean numbers).', 'blog-pro' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Bots &amp; crawlers', 'blog-pro' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( BLOGPRO_ANALYTICS_OPTION ); ?>[track_bots]" value="1" <?php checked( ! empty( $settings['track_bots'] ) ); ?>>
								<?php esc_html_e( 'Store crawler hits (they are always excluded from the reports above).', 'blog-pro' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Country lookup', 'blog-pro' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( BLOGPRO_ANALYTICS_OPTION ); ?>[geo_lookup]" value="1" <?php checked( ! empty( $settings['geo_lookup'] ) ); ?>>
								<?php esc_html_e( 'Resolve visitor country from their IP (cached 30 days per IP). Turn off if your host already sends country headers, or to keep lookups fully local.', 'blog-pro' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bp-an-retention"><?php esc_html_e( 'Keep data for', 'blog-pro' ); ?></label></th>
						<td>
							<input type="number" id="bp-an-retention" min="0" max="3650" step="1" class="small-text"
								name="<?php echo esc_attr( BLOGPRO_ANALYTICS_OPTION ); ?>[retention_days]"
								value="<?php echo esc_attr( (int) $settings['retention_days'] ); ?>">
							<?php esc_html_e( 'days (0 = keep forever). Older rows are deleted automatically once a day.', 'blog-pro' ); ?>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'blog-pro' ) ); ?>
			</form>
		</div>

		<?php /* ---------- Maintenance ---------- */ ?>
		<div class="bp-an-card">
			<h2><?php esc_html_e( 'Maintenance', 'blog-pro' ); ?></h2>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=blogpro-analytics&purge=1' ), 'blogpro_analytics_purge' ) ); ?>"><?php esc_html_e( 'Run retention cleanup now', 'blog-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=blogpro-analytics&create_table=1' ), 'blogpro_analytics_create_table' ) ); ?>"><?php esc_html_e( 'Repair storage table', 'blog-pro' ); ?></a>
				<a class="button bp-an-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=blogpro-analytics&clear=1' ), 'blogpro_analytics_clear' ) ); ?>"
					onclick="return confirm('<?php echo esc_js( __( 'Delete every recorded visit? This cannot be undone.', 'blog-pro' ) ); ?>');"><?php esc_html_e( 'Delete all analytics data', 'blog-pro' ); ?></a>
			</p>
			<p class="bp-an-muted">
				<?php
				printf(
					/* translators: %s: database table name */
					esc_html__( 'Data is stored in %s. Raw IP addresses are never saved — visitors are counted through a salted one-way hash.', 'blog-pro' ),
					'<code>' . esc_html( blogpro_analytics_table() ) . '</code>'
				);
				?>
			</p>
		</div>

		<style>
		.bp-an-wrap{ max-width:1200px; }
		.bp-an-lead{ color:#646970; font-size:13px; max-width:820px; }
		.bp-an-range{ display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin:16px 0 4px; }
		.bp-an-pill{ display:inline-block; padding:4px 11px; border:1px solid #dcdcde; border-radius:999px; background:#fff; color:#50575e; text-decoration:none; font-size:12px; }
		.bp-an-pill:hover{ border-color:#3858e9; color:#3858e9; }
		.bp-an-pill.is-active{ background:#3858e9; border-color:#3858e9; color:#fff; }
		.bp-an-custom{ display:flex; align-items:center; gap:6px; margin-left:8px; }
		.bp-an-custom span{ color:#646970; }
		.bp-an-export{ margin-left:auto; }
		.bp-an-period{ color:#646970; font-size:12px; margin:6px 0 18px; }
		.bp-an-stats{ display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin:0 0 18px; }
		.bp-an-stat{ background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:14px 16px; }
		.bp-an-stat .v{ font-size:28px; font-weight:700; line-height:1.1; }
		.bp-an-stat .k{ font-size:12px; color:#646970; margin-top:2px; }
		.bp-an-card{ background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:16px 18px; margin:0 0 18px; }
		.bp-an-card h2{ margin-top:0; font-size:15px; }
		.bp-an-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(340px,1fr)); gap:18px; }
		.bp-an-table th{ font-size:12px; }
		.bp-an-table td{ font-size:13px; vertical-align:middle; }
		.bp-an-num{ text-align:right; white-space:nowrap; }
		.bp-an-flag{ display:inline-block; min-width:26px; padding:1px 4px; border-radius:4px; background:#f0f0f1; font-size:11px; font-weight:600; text-align:center; }
		.bp-an-meter{ display:inline-block; width:70px; height:6px; margin-right:6px; border-radius:3px; background:#f0f0f1; overflow:hidden; vertical-align:middle; }
		.bp-an-meter > span{ display:block; height:100%; background:#3858e9; }
		.bp-an-chart{ display:flex; align-items:flex-end; gap:2px; height:130px; padding-top:6px; }
		.bp-an-bar{ flex:1 1 auto; min-width:2px; min-height:2px; background:#3858e9; border-radius:2px 2px 0 0; opacity:.85; }
		.bp-an-bar:hover{ opacity:1; }
		.bp-an-axis{ display:flex; justify-content:space-between; font-size:11px; color:#646970; margin-top:6px; }
		.bp-an-peak{ font-weight:600; }
		.bp-an-muted{ color:#646970; font-size:12px; }
		.bp-an-empty{ background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:22px; margin:0 0 18px; }
		.bp-an-empty h2{ margin-top:0; }
		.bp-an-actions{ color:#646970; font-size:12px; }
		.bp-an-danger{ color:#b32d2e; border-color:#b32d2e; }
		.bp-an-danger:hover{ background:#b32d2e; border-color:#b32d2e; color:#fff; }
		</style>
	</div>
	<?php
}
