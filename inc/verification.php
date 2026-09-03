<?php
/**
 * Webmaster / SEO-tool site verification.
 *
 * Stores ownership-verification tokens (Google Search Console, Bing,
 * Ahrefs, Yandex) in one option and prints them as <meta> tags in
 * <head>. Ahrefs also supports file-based verification — we serve the
 * ahrefs_*.txt file virtually (same trick as robots.txt), so no FTP
 * upload is needed.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BLOGPRO_VERIFY_OPTION', 'blogpro_site_verification' );

/**
 * Field schema: option key => array( label, hint, meta-tag name ).
 * meta = '' means the field is not printed as a meta tag (file-based).
 */
function blogpro_verification_fields() {
	return array(
		'google'   => array(
			'label' => __( 'Google Search Console', 'blog-pro' ),
			'hint'  => __( 'HTML tag verification: paste the content value from Settings → Ownership verification → meta tag.', 'blog-pro' ),
			'meta'  => 'google-site-verification',
		),
		'bing'     => array(
			'label' => __( 'Bing Webmaster Tools', 'blog-pro' ),
			'hint'  => __( 'Meta tag content value.', 'blog-pro' ),
			'meta'  => 'msvalidate.01',
		),
		'yandex'   => array(
			'label' => __( 'Yandex Webmaster', 'blog-pro' ),
			'hint'  => __( 'Meta tag content value (ys:webmaster:…)', 'blog-pro' ),
			'meta'  => 'yandex-verification',
		),
		'ahrefs'   => array(
			'label' => __( 'Ahrefs Site Verification', 'blog-pro' ),
			'hint'  => __( 'Paste the token from Ahrefs → Site Audit → Settings → Site verification. Works with either method: printed as a meta tag AND served as the ahrefs_<token>.txt file.', 'blog-pro' ),
			'meta'  => 'ahrefs-site-verification',
		),
		'pinterest'=> array(
			'label' => __( 'Pinterest', 'blog-pro' ),
			'hint'  => __( 'Meta tag content value.', 'blog-pro' ),
			'meta'  => 'p:domain_verify',
		),
		// type=script: rendered as the Ahrefs analytics <script> tag, not a meta.
		'ahrefs_analytics' => array(
			'label' => __( 'Ahrefs Bot Audit / Analytics key', 'blog-pro' ),
			'hint'  => __( 'From the Ahrefs analytics snippet: paste only the data-key value (e.g. CBPuc…cdw). The script tag is built for you.', 'blog-pro' ),
			'meta'  => '',
			'type'  => 'script',
		),
	);
}

/**
 * Read one stored token.
 *
 * @param string $key Field key.
 * @return string
 */
function blogpro_get_verification_token( $key ) {
	$opts = get_option( BLOGPRO_VERIFY_OPTION, array() );
	return is_array( $opts ) && isset( $opts[ $key ] ) ? $opts[ $key ] : '';
}

/**
 * Print configured verification meta tags in <head>.
 * Priority 1 — early in head, before anything can suppress it.
 */
function blogpro_print_verification_tags() {
	foreach ( blogpro_verification_fields() as $key => $field ) {
		$token = blogpro_get_verification_token( $key );
		if ( '' === $token ) {
			continue;
		}

		// Script-type fields render a fixed, theme-authored tag with the
		// key as the only dynamic attribute — never raw stored HTML.
		if ( ! empty( $field['type'] ) && 'script' === $field['type'] ) {
			if ( 'ahrefs_analytics' === $key ) {
				printf(
					'<script src="https://analytics.ahrefs.com/analytics.js" data-key="%s" async></script>' . "\n",
					esc_attr( $token )
				);
			}
			continue;
		}

		if ( '' === $field['meta'] ) {
			continue;
		}
		printf( '<meta name="%s" content="%s">' . "\n", esc_attr( $field['meta'] ), esc_attr( $token ) );
	}
}
add_action( 'wp_head', 'blogpro_print_verification_tags', 1 );

/**
 * Serve the Ahrefs file-based verification token virtually at
 * /ahrefs_<token>.txt — subdirectory installs included (same rewrite
 * approach as inc/robots.php).
 */
function blogpro_verify_rewrite_rules() {
	$token = blogpro_get_verification_token( 'ahrefs' );
	if ( '' !== $token ) {
		add_rewrite_rule( '^ahrefs_' . preg_quote( $token, '#' ) . '\.txt$', 'index.php?blogpro_verify_file=ahrefs', 'top' );
	}
}
add_action( 'init', 'blogpro_verify_rewrite_rules' );

function blogpro_verify_query_vars( $vars ) {
	$vars[] = 'blogpro_verify_file';
	return $vars;
}
add_filter( 'query_vars', 'blogpro_verify_query_vars' );

function blogpro_serve_verify_file( $wp ) {
	if ( empty( $wp->query_vars['blogpro_verify_file'] ) || 'ahrefs' !== $wp->query_vars['blogpro_verify_file'] ) {
		return;
	}
	$token = blogpro_get_verification_token( 'ahrefs' );
	if ( '' === $token ) {
		return;
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	echo esc_html( $token );
	exit;
}
add_action( 'parse_request', 'blogpro_serve_verify_file' );

/**
 * Flush the rewrite rules when the token changes (rule text depends on it).
 */
add_action( 'update_option_' . BLOGPRO_VERIFY_OPTION, function () {
	blogpro_verify_flush_rules();
} );

function blogpro_verify_flush_rules() {
	global $wp_rewrite;
	// Re-register with the NEW token (init already ran with the old one),
	// then regenerate the rule set so the file URL resolves immediately.
	blogpro_verify_rewrite_rules();
	$wp_rewrite->flush_rules( false );
}

/* --------------------------------------------------------------------- */
/* Admin settings page (Blog Pro → Verification)                          */
/* --------------------------------------------------------------------- */

function blogpro_verification_register_settings() {
	register_setting(
		'blogpro_verification_group',
		BLOGPRO_VERIFY_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'blogpro_sanitize_verification',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'blogpro_verification_register_settings' );

/**
 * Tokens are opaque alphanumeric strings (may contain - and _).
 * Anything else is stripped — blocks tags/JS injection outright.
 *
 * @param mixed $input Raw settings array.
 * @return array
 */
function blogpro_sanitize_verification( $input ) {
	$clean = array();
	if ( ! is_array( $input ) ) {
		return $clean;
	}
	// Ahrefs analytics keys are base64-ish: allow + and / too.
	// < > & " ' are impossible here, so stored values can never form markup.
	foreach ( blogpro_verification_fields() as $key => $field ) {
		$val = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
		$clean[ $key ] = preg_replace( '/[^A-Za-z0-9\-_=+\/]/', '', $val );
	}
	return $clean;
}

function blogpro_render_verification_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$opts = get_option( BLOGPRO_VERIFY_OPTION, array() );
	$opts = is_array( $opts ) ? $opts : array();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Site Verification', 'blog-pro' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Ownership-verification tokens for search engines and SEO tools. Leave a field empty to skip that service. Values are printed as meta tags in the site <head>.', 'blog-pro' ); ?></p>

		<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'blog-pro' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'blogpro_verification_group' ); ?>
			<table class="form-table">
				<?php foreach ( blogpro_verification_fields() as $key => $field ) : ?>
				<tr>
					<th scope="row"><label for="bp-verify-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
					<td>
						<input
							type="text"
							id="bp-verify-<?php echo esc_attr( $key ); ?>"
							name="<?php echo esc_attr( BLOGPRO_VERIFY_OPTION . '[' . $key . ']' ); ?>"
							value="<?php echo esc_attr( isset( $opts[ $key ] ) ? $opts[ $key ] : '' ); ?>"
							class="regular-text code"
							autocomplete="off"
							spellcheck="false"
						>
						<p class="description"><?php echo esc_html( $field['hint'] ); ?></p>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Where to find each token', 'blog-pro' ); ?></h2>
		<table class="widefat striped" style="max-width:820px;">
			<tbody>
				<tr><th style="width:220px;">Google Search Console</th><td><a href="https://search.google.com/search-console" target="_blank" rel="noopener">search.google.com/search-console</a> → Settings → Ownership verification → HTML tag</td></tr>
				<tr><th>Bing Webmaster Tools</th><td><a href="https://www.bing.com/webmasters" target="_blank" rel="noopener">bing.com/webmasters</a> → Settings → Site Verification</td></tr>
				<tr><th>Ahrefs</th><td><a href="https://ahrefs.com/site-verification/" target="_blank" rel="noopener">ahrefs.com/site-verification</a> — either method works with the token above. Bot Audit / Search Console integration needs the analytics key field instead.</td></tr>
				<tr><th>Yandex Webmaster</th><td><a href="https://webmaster.yandex.com" target="_blank" rel="noopener">webmaster.yandex.com</a> → Verification</td></tr>
				<tr><th>Pinterest</th><td><a href="https://developers.pinterest.com/account-setup/verify" target="_blank" rel="noopener">developers.pinterest.com/account-setup/verify</a></td></tr>
			</tbody>
		</table>
	</div>
	<?php
}
