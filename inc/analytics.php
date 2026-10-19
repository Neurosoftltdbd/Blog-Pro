<?php
/**
 * Analytics — first-party, plugin-free visitor analytics.
 *
 * Records one row per pageview in a dedicated table ( {prefix}blogpro_analytics )
 * and renders the numbers under Blog Pro → Analytics (see admin/analytics.php).
 *
 * Why a client-side beacon instead of a server-side hook: the theme ships a
 * full-page file cache (inc/page-cache.php), so a PHP hook would only ever see
 * cache misses and would under-count badly. A tiny beacon posts to a public
 * REST endpoint, which also means no third-party script, no cookie banner and
 * no plugin.
 *
 * Privacy: the visitor's IP is never stored. It is salted and hashed for the
 * unique-visitor counter, and used transiently for the optional country lookup
 * (cached 30 days per IP, then discarded).
 *
 * Copyright (C) 2026 Nur Hossain Repon, nhrepon (https://nhrepon.com)
 * GPL v2 or later.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BLOGPRO_ANALYTICS_DB_VERSION', '1.0.0' );
define( 'BLOGPRO_ANALYTICS_OPTION', 'blogpro_analytics_settings' );
define( 'BLOGPRO_ANALYTICS_SESSION_WINDOW', 1800 ); // 30 minutes

/* ---------------------------------------------------------------------
 * 1. Table + settings
 * ------------------------------------------------------------------- */

/** Fully-qualified analytics table name. */
function blogpro_analytics_table() {
	global $wpdb;
	return $wpdb->prefix . 'blogpro_analytics';
}

/** Default settings. */
function blogpro_analytics_defaults() {
	return array(
		'enabled'        => 1,
		'track_loggedin' => 1,
		'track_admins'   => 0,
		'track_bots'     => 0,
		'geo_lookup'     => 1,
		'retention_days' => 180,
	);
}

/** Saved settings merged over the defaults. */
function blogpro_analytics_settings() {
	$saved = get_option( BLOGPRO_ANALYTICS_OPTION, array() );
	if ( ! is_array( $saved ) ) $saved = array();
	return array_merge( blogpro_analytics_defaults(), $saved );
}

/**
 * True when the storage table has been created.
 *
 * @param bool $refresh Re-query instead of using the cached answer.
 * @return bool
 */
function blogpro_analytics_table_exists( $refresh = false ) {
	global $wpdb;
	static $exists = null;
	if ( $refresh ) $exists = null;
	if ( null !== $exists ) return $exists;
	$table  = blogpro_analytics_table();
	$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	return $exists;
}

/**
 * Create/upgrade the storage table. Safe to call repeatedly (dbDelta).
 */
function blogpro_analytics_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = blogpro_analytics_table();
	$collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		created_at datetime NOT NULL,
		post_id bigint(20) unsigned NOT NULL DEFAULT 0,
		post_type varchar(20) NOT NULL DEFAULT '',
		page_title varchar(255) NOT NULL DEFAULT '',
		url varchar(255) NOT NULL DEFAULT '',
		url_hash char(32) NOT NULL DEFAULT '',
		referrer varchar(255) NOT NULL DEFAULT '',
		ref_host varchar(100) NOT NULL DEFAULT '',
		country char(2) NOT NULL DEFAULT '',
		city varchar(64) NOT NULL DEFAULT '',
		device varchar(10) NOT NULL DEFAULT '',
		browser varchar(24) NOT NULL DEFAULT '',
		os varchar(24) NOT NULL DEFAULT '',
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		visitor_hash char(32) NOT NULL DEFAULT '',
		session_hash char(32) NOT NULL DEFAULT '',
		is_bot tinyint(1) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY created_at (created_at),
		KEY post_id (post_id),
		KEY country (country),
		KEY url_hash (url_hash),
		KEY ref_host (ref_host),
		KEY visitor_hash (visitor_hash),
		KEY session_hash (session_hash)
	) {$collate};";

	dbDelta( $sql );
	update_option( 'blogpro_analytics_db_version', BLOGPRO_ANALYTICS_DB_VERSION );

	// Refresh the cached existence check — the table may have just appeared
	// inside the same request that asked whether it existed.
	blogpro_analytics_table_exists( true );
}

/** Theme activation: build the table and schedule housekeeping. */
function blogpro_analytics_activate() {
	blogpro_analytics_install();
	blogpro_analytics_schedule_cleanup();
}
add_action( 'after_switch_theme', 'blogpro_analytics_activate' );

/**
 * Upgrade path for sites that already had the theme active: the table is
 * created on the first admin page load after a version bump.
 */
function blogpro_analytics_maybe_upgrade() {
	// Option-only check: no extra query on every admin page load. A missing
	// table is detected and repaired by the Analytics screen itself.
	if ( get_option( 'blogpro_analytics_db_version' ) === BLOGPRO_ANALYTICS_DB_VERSION ) {
		return;
	}
	blogpro_analytics_install();
}
add_action( 'admin_init', 'blogpro_analytics_maybe_upgrade' );

/** Drop the scheduled cleanup when the theme is switched away. */
function blogpro_analytics_deactivate() {
	wp_clear_scheduled_hook( 'blogpro_analytics_cleanup' );
}
add_action( 'switch_theme', 'blogpro_analytics_deactivate' );

/* ---------------------------------------------------------------------
 * 2. Date helpers
 * ------------------------------------------------------------------- */

/**
 * Shift a site-local date by a number of days.
 *
 * Uses the site timezone explicitly instead of strtotime()/gmdate(), which
 * silently disagree whenever PHP's default timezone is not UTC — that mismatch
 * shifts every date range by a whole day.
 *
 * @param string $date Y-m-d (site-local).
 * @param int    $days Negative to go back, positive to go forward.
 * @return string Y-m-d
 */
function blogpro_analytics_date_offset( $date, $days ) {
	$dt = date_create_immutable( $date, wp_timezone() );
	if ( ! $dt ) return $date;
	return $dt->modify( ( $days < 0 ? '-' : '+' ) . abs( (int) $days ) . ' days' )->format( 'Y-m-d' );
}

/**
 * Same as blogpro_analytics_date_offset() but for a full Y-m-d H:i:s value.
 *
 * @param string $datetime Site-local datetime.
 * @param int    $days     Negative to go back, positive to go forward.
 * @return string Y-m-d H:i:s
 */
function blogpro_analytics_datetime_offset( $datetime, $days ) {
	$dt = date_create_immutable( $datetime, wp_timezone() );
	if ( ! $dt ) return $datetime;
	return $dt->modify( ( $days < 0 ? '-' : '+' ) . abs( (int) $days ) . ' days' )->format( 'Y-m-d H:i:s' );
}

/* ---------------------------------------------------------------------
 * 3. Data retention
 * ------------------------------------------------------------------- */

function blogpro_analytics_schedule_cleanup() {
	if ( ! wp_next_scheduled( 'blogpro_analytics_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'blogpro_analytics_cleanup' );
	}
}
add_action( 'init', 'blogpro_analytics_schedule_cleanup' );

/** Delete rows older than the retention window. 0 = keep forever. */
function blogpro_analytics_purge_old() {
	$days = (int) blogpro_analytics_settings()['retention_days'];
	if ( $days < 1 || ! blogpro_analytics_table_exists() ) return 0;

	global $wpdb;
	$table  = blogpro_analytics_table();
	// Stored in site-local time, so build the cutoff in site-local time too.
	$cutoff = blogpro_analytics_datetime_offset( current_time( 'mysql' ), -$days );

	return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
}
add_action( 'blogpro_analytics_cleanup', 'blogpro_analytics_purge_old' );

/** Remove every recorded row. */
function blogpro_analytics_clear_all() {
	if ( ! blogpro_analytics_table_exists() ) return 0;
	global $wpdb;
	// DELETE rather than TRUNCATE: TRUNCATE needs the DROP privilege, which
	// plenty of shared hosts withhold from the WordPress database user.
	return (int) $wpdb->query( 'DELETE FROM ' . blogpro_analytics_table() ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/* ---------------------------------------------------------------------
 * 4. Request fingerprinting
 * ------------------------------------------------------------------- */

/** Best-effort real client IP. Never stored raw. */
function blogpro_analytics_client_ip() {
	$candidates = array();
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
		if ( empty( $_SERVER[ $key ] ) ) continue;
		foreach ( explode( ',', (string) wp_unslash( $_SERVER[ $key ] ) ) as $part ) {
			$candidates[] = trim( $part );
		}
	}
	foreach ( $candidates as $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return $ip;
		}
	}
	// Local/staging installs only: fall back to whatever we have.
	foreach ( $candidates as $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
	}
	return '';
}

/** Stable, non-reversible visitor id (site-salted hash of IP + user agent). */
function blogpro_analytics_visitor_hash( $ip, $ua ) {
	return md5( wp_salt( 'auth' ) . '|' . $ip . '|' . $ua );
}

/** Buckets a visitor's hits into 30-minute sessions. */
function blogpro_analytics_session_hash( $visitor_hash, $time = null ) {
	$time = $time ?: time();
	return md5( $visitor_hash . '|' . floor( $time / BLOGPRO_ANALYTICS_SESSION_WINDOW ) );
}

/** Human-readable device class. */
function blogpro_analytics_device( $ua ) {
	if ( preg_match( '/ipad|tablet|playbook|silk|kindle|(android(?!.*mobile))/i', $ua ) ) return 'tablet';
	if ( preg_match( '/mobile|iphone|ipod|android|blackberry|opera mini|iemobile|windows phone/i', $ua ) ) return 'mobile';
	return 'desktop';
}

/** Browser name from the user agent. Order matters: derived engines first. */
function blogpro_analytics_browser( $ua ) {
	$map = array(
		'Edge'      => '/edg(e|a|ios)?\//i',
		'Opera'     => '/opr\/|opera/i',
		'Samsung'   => '/samsungbrowser/i',
		'UC'        => '/ucbrowser/i',
		'Firefox'   => '/firefox|fxios/i',
		'Chrome'    => '/chrome|crios|crmo/i',
		'Safari'    => '/safari/i',
		'IE'        => '/msie|trident/i',
	);
	foreach ( $map as $name => $pattern ) {
		if ( preg_match( $pattern, $ua ) ) return $name;
	}
	return 'Other';
}

/** Operating system from the user agent. */
function blogpro_analytics_os( $ua ) {
	$map = array(
		'Android' => '/android/i',
		'iOS'     => '/iphone|ipad|ipod/i',
		'Windows' => '/windows nt|windows phone/i',
		'macOS'   => '/macintosh|mac os x/i',
		'ChromeOS'=> '/cros/i',
		'Linux'   => '/linux|x11/i',
	);
	foreach ( $map as $name => $pattern ) {
		if ( preg_match( $pattern, $ua ) ) return $name;
	}
	return 'Other';
}

/** True for crawlers, monitors and CLI clients. */
function blogpro_analytics_is_bot( $ua ) {
	if ( '' === $ua ) return true;
	return (bool) preg_match(
		'/bot|crawl|spider|slurp|baidu|yandex|sogou|exabot|ia_archiver|semrush|ahrefs|mj12|dotbot|petal|bytespider|gptbot|chatgpt|claudebot|perplexity|python-requests|python-urllib|curl|wget|headless|phantomjs|lighthouse|pagespeed|pingdom|uptimerobot|statuscake|monitoring|feedfetcher|facebookexternalhit|whatsapp|telegrambot|preview/i',
		$ua
	);
}

/* ---------------------------------------------------------------------
 * 5. Geo lookup
 * ------------------------------------------------------------------- */

/** ISO 3166-1 alpha-2 → country name. Falls back to the raw code. */
function blogpro_analytics_country_names() {
	static $map = null;
	if ( null !== $map ) return $map;

	$map = array(
		'AD' => 'Andorra', 'AE' => 'United Arab Emirates', 'AF' => 'Afghanistan', 'AG' => 'Antigua and Barbuda',
		'AI' => 'Anguilla', 'AL' => 'Albania', 'AM' => 'Armenia', 'AO' => 'Angola',
		'AQ' => 'Antarctica', 'AR' => 'Argentina', 'AS' => 'American Samoa', 'AT' => 'Austria',
		'AU' => 'Australia', 'AW' => 'Aruba', 'AX' => 'Åland Islands', 'AZ' => 'Azerbaijan',
		'BA' => 'Bosnia and Herzegovina', 'BB' => 'Barbados', 'BD' => 'Bangladesh', 'BE' => 'Belgium',
		'BF' => 'Burkina Faso', 'BG' => 'Bulgaria', 'BH' => 'Bahrain', 'BI' => 'Burundi',
		'BJ' => 'Benin', 'BL' => 'Saint Barthélemy', 'BM' => 'Bermuda', 'BN' => 'Brunei',
		'BO' => 'Bolivia', 'BQ' => 'Caribbean Netherlands', 'BR' => 'Brazil', 'BS' => 'Bahamas',
		'BT' => 'Bhutan', 'BV' => 'Bouvet Island', 'BW' => 'Botswana', 'BY' => 'Belarus',
		'BZ' => 'Belize', 'CA' => 'Canada', 'CC' => 'Cocos (Keeling) Islands', 'CD' => 'DR Congo',
		'CF' => 'Central African Republic', 'CG' => 'Congo', 'CH' => 'Switzerland', 'CI' => "Côte d'Ivoire",
		'CK' => 'Cook Islands', 'CL' => 'Chile', 'CM' => 'Cameroon', 'CN' => 'China',
		'CO' => 'Colombia', 'CR' => 'Costa Rica', 'CU' => 'Cuba', 'CV' => 'Cabo Verde',
		'CW' => 'Curaçao', 'CX' => 'Christmas Island', 'CY' => 'Cyprus', 'CZ' => 'Czechia',
		'DE' => 'Germany', 'DJ' => 'Djibouti', 'DK' => 'Denmark', 'DM' => 'Dominica',
		'DO' => 'Dominican Republic', 'DZ' => 'Algeria', 'EC' => 'Ecuador', 'EE' => 'Estonia',
		'EG' => 'Egypt', 'EH' => 'Western Sahara', 'ER' => 'Eritrea', 'ES' => 'Spain',
		'ET' => 'Ethiopia', 'FI' => 'Finland', 'FJ' => 'Fiji', 'FK' => 'Falkland Islands',
		'FM' => 'Micronesia', 'FO' => 'Faroe Islands', 'FR' => 'France', 'GA' => 'Gabon',
		'GB' => 'United Kingdom', 'GD' => 'Grenada', 'GE' => 'Georgia', 'GF' => 'French Guiana',
		'GG' => 'Guernsey', 'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GL' => 'Greenland',
		'GM' => 'Gambia', 'GN' => 'Guinea', 'GP' => 'Guadeloupe', 'GQ' => 'Equatorial Guinea',
		'GR' => 'Greece', 'GS' => 'South Georgia', 'GT' => 'Guatemala', 'GU' => 'Guam',
		'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HK' => 'Hong Kong', 'HM' => 'Heard & McDonald Islands',
		'HN' => 'Honduras', 'HR' => 'Croatia', 'HT' => 'Haiti', 'HU' => 'Hungary',
		'ID' => 'Indonesia', 'IE' => 'Ireland', 'IL' => 'Israel', 'IM' => 'Isle of Man',
		'IN' => 'India', 'IO' => 'British Indian Ocean Territory', 'IQ' => 'Iraq', 'IR' => 'Iran',
		'IS' => 'Iceland', 'IT' => 'Italy', 'JE' => 'Jersey', 'JM' => 'Jamaica',
		'JO' => 'Jordan', 'JP' => 'Japan', 'KE' => 'Kenya', 'KG' => 'Kyrgyzstan',
		'KH' => 'Cambodia', 'KI' => 'Kiribati', 'KM' => 'Comoros', 'KN' => 'Saint Kitts and Nevis',
		'KP' => 'North Korea', 'KR' => 'South Korea', 'KW' => 'Kuwait', 'KY' => 'Cayman Islands',
		'KZ' => 'Kazakhstan', 'LA' => 'Laos', 'LB' => 'Lebanon', 'LC' => 'Saint Lucia',
		'LI' => 'Liechtenstein', 'LK' => 'Sri Lanka', 'LR' => 'Liberia', 'LS' => 'Lesotho',
		'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'LV' => 'Latvia', 'LY' => 'Libya',
		'MA' => 'Morocco', 'MC' => 'Monaco', 'MD' => 'Moldova', 'ME' => 'Montenegro',
		'MF' => 'Saint Martin', 'MG' => 'Madagascar', 'MH' => 'Marshall Islands', 'MK' => 'North Macedonia',
		'ML' => 'Mali', 'MM' => 'Myanmar', 'MN' => 'Mongolia', 'MO' => 'Macao',
		'MP' => 'Northern Mariana Islands', 'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MS' => 'Montserrat',
		'MT' => 'Malta', 'MU' => 'Mauritius', 'MV' => 'Maldives', 'MW' => 'Malawi',
		'MX' => 'Mexico', 'MY' => 'Malaysia', 'MZ' => 'Mozambique', 'NA' => 'Namibia',
		'NC' => 'New Caledonia', 'NE' => 'Niger', 'NF' => 'Norfolk Island', 'NG' => 'Nigeria',
		'NI' => 'Nicaragua', 'NL' => 'Netherlands', 'NO' => 'Norway', 'NP' => 'Nepal',
		'NR' => 'Nauru', 'NU' => 'Niue', 'NZ' => 'New Zealand', 'OM' => 'Oman',
		'PA' => 'Panama', 'PE' => 'Peru', 'PF' => 'French Polynesia', 'PG' => 'Papua New Guinea',
		'PH' => 'Philippines', 'PK' => 'Pakistan', 'PL' => 'Poland', 'PM' => 'Saint Pierre and Miquelon',
		'PN' => 'Pitcairn Islands', 'PR' => 'Puerto Rico', 'PS' => 'Palestine', 'PT' => 'Portugal',
		'PW' => 'Palau', 'PY' => 'Paraguay', 'QA' => 'Qatar', 'RE' => 'Réunion',
		'RO' => 'Romania', 'RS' => 'Serbia', 'RU' => 'Russia', 'RW' => 'Rwanda',
		'SA' => 'Saudi Arabia', 'SB' => 'Solomon Islands', 'SC' => 'Seychelles', 'SD' => 'Sudan',
		'SE' => 'Sweden', 'SG' => 'Singapore', 'SH' => 'Saint Helena', 'SI' => 'Slovenia',
		'SJ' => 'Svalbard and Jan Mayen', 'SK' => 'Slovakia', 'SL' => 'Sierra Leone', 'SM' => 'San Marino',
		'SN' => 'Senegal', 'SO' => 'Somalia', 'SR' => 'Suriname', 'SS' => 'South Sudan',
		'ST' => 'São Tomé and Príncipe', 'SV' => 'El Salvador', 'SX' => 'Sint Maarten', 'SY' => 'Syria',
		'SZ' => 'Eswatini', 'TC' => 'Turks and Caicos Islands', 'TD' => 'Chad', 'TF' => 'French Southern Territories',
		'TG' => 'Togo', 'TH' => 'Thailand', 'TJ' => 'Tajikistan', 'TK' => 'Tokelau',
		'TL' => 'Timor-Leste', 'TM' => 'Turkmenistan', 'TN' => 'Tunisia', 'TO' => 'Tonga',
		'TR' => 'Türkiye', 'TT' => 'Trinidad and Tobago', 'TV' => 'Tuvalu', 'TW' => 'Taiwan',
		'TZ' => 'Tanzania', 'UA' => 'Ukraine', 'UG' => 'Uganda', 'UM' => 'U.S. Outlying Islands',
		'US' => 'United States', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VA' => 'Vatican City',
		'VC' => 'Saint Vincent and the Grenadines', 'VE' => 'Venezuela', 'VG' => 'British Virgin Islands', 'VI' => 'U.S. Virgin Islands',
		'VN' => 'Vietnam', 'VU' => 'Vanuatu', 'WF' => 'Wallis and Futuna', 'WS' => 'Samoa',
		'YE' => 'Yemen', 'YT' => 'Mayotte', 'ZA' => 'South Africa', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
	);
	return $map;
}

/** Country name for an ISO code, or '' when unknown. */
function blogpro_analytics_country_name( $code ) {
	$code = strtoupper( (string) $code );
	if ( '' === $code ) return '';
	$names = blogpro_analytics_country_names();
	return isset( $names[ $code ] ) ? $names[ $code ] : $code;
}

/**
 * Resolve the visitor's country and city.
 *
 * CDN headers first (instant, no network call), then an optional cached
 * lookup. The result is cached per IP for 30 days, so a returning visitor
 * costs nothing.
 *
 * @param string $ip Validated client IP.
 * @return array array( 'country' => 'BD', 'city' => 'Dhaka' )
 */
function blogpro_analytics_geo( $ip ) {
	$out = array( 'country' => '', 'city' => '' );

	// Edge/CDN geolocation — free and instant when present.
	foreach ( array( 'HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_GEOIP_COUNTRY_CODE', 'HTTP_X_GEO_COUNTRY' ) as $header ) {
		if ( empty( $_SERVER[ $header ] ) ) continue;
		$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) wp_unslash( $_SERVER[ $header ] ) ) );
		$code = substr( $code, 0, 2 );
		if ( 2 === strlen( $code ) && 'XX' !== $code && 'T1' !== $code ) {
			$out['country'] = $code;
			if ( ! empty( $_SERVER['HTTP_CF_IPCITY'] ) ) {
				$out['city'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCITY'] ) );
			}
			return $out;
		}
	}

	if ( empty( blogpro_analytics_settings()['geo_lookup'] ) || '' === $ip ) {
		return $out;
	}

	// Private/reserved addresses (local development, intranet) can never be
	// resolved — don't spend a network round-trip finding that out.
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		return $out;
	}

	$cache_key = 'bp_an_geo_' . md5( $ip );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$response = wp_remote_get(
		'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=status,countryCode,city',
		array( 'timeout' => 2, 'redirection' => 1, 'user-agent' => 'BlogPro-Analytics/1.0' )
	);

	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $body ) && ! empty( $body['countryCode'] ) ) {
			$out['country'] = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $body['countryCode'] ), 0, 2 ) );
			$out['city']    = isset( $body['city'] ) ? sanitize_text_field( $body['city'] ) : '';
		}
	}

	// Cache misses (including failures) too — one lookup per IP per month.
	set_transient( $cache_key, $out, 30 * DAY_IN_SECONDS );
	return $out;
}

/* ---------------------------------------------------------------------
 * 6. Recording
 * ------------------------------------------------------------------- */

/** Parse a referrer URL into a host label. */
function blogpro_analytics_ref_host( $referrer ) {
	if ( '' === $referrer ) return '';
	$host = wp_parse_url( $referrer, PHP_URL_HOST );
	if ( ! $host ) return '';
	$host = strtolower( preg_replace( '/^www\./', '', $host ) );

	// Same-site navigation is not an acquisition source.
	$self = strtolower( preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
	if ( $host === $self ) return '';

	return substr( $host, 0, 100 );
}

/**
 * Insert one pageview row. All values are pre-sanitised by the caller.
 *
 * @param array $data Column => value.
 * @return int|false Insert id, or false when nothing was written.
 */
function blogpro_analytics_insert( $data ) {
	if ( ! blogpro_analytics_table_exists() ) return false;

	global $wpdb;
	$row = array(
		'created_at'   => current_time( 'mysql' ),
		'post_id'      => 0,
		'post_type'    => '',
		'page_title'   => '',
		'url'          => '',
		'url_hash'     => '',
		'referrer'     => '',
		'ref_host'     => '',
		'country'      => '',
		'city'         => '',
		'device'       => '',
		'browser'      => '',
		'os'           => '',
		'user_id'      => 0,
		'visitor_hash' => '',
		'session_hash' => '',
		'is_bot'       => 0,
	);
	$row = array_merge( $row, array_intersect_key( $data, $row ) );

	return $wpdb->insert( blogpro_analytics_table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/** Should this request be recorded at all? */
function blogpro_analytics_should_track() {
	$settings = blogpro_analytics_settings();
	if ( empty( $settings['enabled'] ) ) return false;

	if ( is_user_logged_in() ) {
		if ( empty( $settings['track_loggedin'] ) ) return false;
		if ( empty( $settings['track_admins'] ) && current_user_can( 'manage_options' ) ) return false;
	}
	return true;
}

/* ---------------------------------------------------------------------
 * 7. REST endpoint — POST /wp-json/blogpro/v1/hit
 * ------------------------------------------------------------------- */

function blogpro_analytics_register_routes() {
	register_rest_route( 'blogpro/v1', '/hit', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'args'                => array(
			'url'      => array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ),
			'referrer' => array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ),
			'postId'   => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
		),
		'callback'            => 'blogpro_analytics_handle_hit',
	) );
}
add_action( 'rest_api_init', 'blogpro_analytics_register_routes' );

/**
 * Record a beacon hit.
 *
 * The endpoint is intentionally public (a beacon cannot carry a nonce without
 * breaking on cached pages), so it is hardened instead: the beacon must claim
 * a URL on this site, a per-visitor hourly rate limit applies, and every
 * stored value is sanitised and length-capped.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function blogpro_analytics_handle_hit( $request ) {
	if ( ! blogpro_analytics_should_track() || ! blogpro_analytics_table_exists() ) {
		return new WP_REST_Response( array( 'tracked' => false ), 200 );
	}

	$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
		? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 )
		: '';

	$is_bot = blogpro_analytics_is_bot( $ua );
	if ( $is_bot && empty( blogpro_analytics_settings()['track_bots'] ) ) {
		return new WP_REST_Response( array( 'tracked' => false, 'reason' => 'bot' ), 200 );
	}

	$url = (string) $request->get_param( 'url' );
	$url = substr( $url, 0, 255 );

	// Only accept hits for this site — blocks the endpoint being used as a
	// free logging service by third parties.
	$url_host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$self_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( '' === $url_host || ( $url_host !== $self_host && 'www.' . $self_host !== $url_host && $self_host !== 'www.' . $url_host ) ) {
		return new WP_REST_Response( array( 'tracked' => false, 'reason' => 'foreign_url' ), 200 );
	}

	$ip      = blogpro_analytics_client_ip();
	$visitor = blogpro_analytics_visitor_hash( $ip, $ua );

	// Flood guard: 240 hits/hour per visitor is far above human browsing.
	$rate_key = 'bp_an_rl_' . substr( $visitor, 0, 24 );
	$hits     = (int) get_transient( $rate_key );
	if ( $hits > 240 ) {
		return new WP_REST_Response( array( 'tracked' => false, 'reason' => 'rate_limited' ), 429 );
	}
	set_transient( $rate_key, $hits + 1, HOUR_IN_SECONDS );

	$post_id   = (int) $request->get_param( 'postId' );
	$post_type = '';
	$title     = '';
	if ( $post_id > 0 && 'publish' === get_post_status( $post_id ) ) {
		$post_type = (string) get_post_type( $post_id );
		$title     = (string) get_the_title( $post_id );
	} else {
		$post_id = 0;
	}

	$geo = blogpro_analytics_geo( $ip );
	$ref = (string) $request->get_param( 'referrer' );

	$inserted = blogpro_analytics_insert( array(
		'post_id'      => $post_id,
		'post_type'    => substr( $post_type, 0, 20 ),
		'page_title'   => substr( sanitize_text_field( $title ), 0, 255 ),
		'url'          => $url,
		'url_hash'     => md5( $url ),
		'referrer'     => substr( $ref, 0, 255 ),
		'ref_host'     => blogpro_analytics_ref_host( $ref ),
		'country'      => $geo['country'],
		'city'         => substr( $geo['city'], 0, 64 ),
		'device'       => blogpro_analytics_device( $ua ),
		'browser'      => blogpro_analytics_browser( $ua ),
		'os'           => blogpro_analytics_os( $ua ),
		'user_id'      => is_user_logged_in() ? get_current_user_id() : 0,
		'visitor_hash' => $visitor,
		'session_hash' => blogpro_analytics_session_hash( $visitor ),
		'is_bot'       => $is_bot ? 1 : 0,
	) );

	return new WP_REST_Response( array( 'tracked' => (bool) $inserted ), 200 );
}

/* ---------------------------------------------------------------------
 * 8. Front-end beacon
 * ------------------------------------------------------------------- */

/**
 * Attach the tracking beacon to the already-deferred main.js bundle.
 * ~300 bytes, no network request of its own, never blocks rendering.
 */
function blogpro_analytics_beacon() {
	if ( is_admin() || is_feed() || is_robots() || is_trackback() || is_preview() || is_customize_preview() ) return;
	if ( ! blogpro_analytics_should_track() ) return;

	$post_id = is_singular() ? (int) get_queried_object_id() : 0;

	$script = sprintf(
		'(function(){if(!window.navigator||!navigator.sendBeacon||!window.Blob)return;'
		. 'try{navigator.sendBeacon(%1$s,new Blob([JSON.stringify({url:location.href,referrer:document.referrer,postId:%2$d})],{type:"application/json"}));}catch(e){}})();',
		wp_json_encode( esc_url_raw( rest_url( 'blogpro/v1/hit' ) ) ),
		$post_id
	);

	wp_add_inline_script( 'blogpro-main', $script, 'after' );
}
add_action( 'wp_enqueue_scripts', 'blogpro_analytics_beacon', 20 );

/* ---------------------------------------------------------------------
 * 9. Settings
 * ------------------------------------------------------------------- */

function blogpro_analytics_register_settings() {
	register_setting(
		'blogpro_analytics_group',
		BLOGPRO_ANALYTICS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'blogpro_analytics_sanitize_settings',
			'default'           => blogpro_analytics_defaults(),
		)
	);
}
add_action( 'admin_init', 'blogpro_analytics_register_settings' );

/**
 * Booleans come back as checkboxes; retention is clamped to 0–3650 days.
 *
 * @param mixed $input Raw settings array.
 * @return array
 */
function blogpro_analytics_sanitize_settings( $input ) {
	if ( ! is_array( $input ) ) $input = array();

	$out = array();
	foreach ( array( 'enabled', 'track_loggedin', 'track_admins', 'track_bots', 'geo_lookup' ) as $flag ) {
		$out[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
	}

	$days = isset( $input['retention_days'] ) ? absint( $input['retention_days'] ) : 180;
	$out['retention_days'] = min( 3650, $days );

	return $out;
}
