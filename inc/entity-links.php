<?php
/**
 * Entity identity links — feeds schema.org `sameAs` for Organization
 * (site social profiles) and Person (per-author profiles), the strongest
 * cheap E-E-A-T / AI-grounding signal available without a plugin.
 *
 * Site profiles: one option per network, `blogpro_{network}_url`.
 * These exact keys are also read by woocommerce/wc-schema-markup.php,
 * so this page powers both. Settings-API form, same shape as
 * inc/verification.php (field descriptors → inputs → one sanitizer).
 *
 * Author profiles: WP's `user_contactmethods` gives per-user URL inputs
 * on the profile screen for free; `job_title` is a hand-rolled field.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Networks the site can declare a profile URL for.
 *
 * @return array[] slug => [label, icon-ish hint for schema]
 */
function blogpro_entity_networks() {
	$networks = array(
		'facebook'  => __( 'Facebook page', 'blog-pro' ),
		'twitter'   => __( 'X (Twitter) profile', 'blog-pro' ),
		'instagram' => __( 'Instagram profile', 'blog-pro' ),
		'linkedin'  => __( 'LinkedIn company page', 'blog-pro' ),
		'youtube'   => __( 'YouTube channel', 'blog-pro' ),
		'pinterest' => __( 'Pinterest profile', 'blog-pro' ),
	);
	return apply_filters( 'blogpro_entity_networks', $networks );
}

/**
 * Non-empty profile URLs, for Organization.sameAs.
 *
 * @return string[]
 */
function blogpro_entity_same_as() {
	$urls = array();
	foreach ( array_keys( blogpro_entity_networks() ) as $slug ) {
		$url = trim( (string) get_option( 'blogpro_' . $slug . '_url', '' ) );
		if ( '' !== $url && preg_match( '#^https?://#i', $url ) ) {
			$urls[] = $url;
		}
	}
	return $urls;
}

/* ---------------------------------------------------------------------
 * Settings (one option per network — keys shared with WC schema)
 * ------------------------------------------------------------------- */
function blogpro_entity_register_settings() {
	foreach ( blogpro_entity_networks() as $slug => $label ) {
		register_setting( 'blogpro_entity_links', 'blogpro_' . $slug . '_url', array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		) );
	}
}
add_action( 'admin_init', 'blogpro_entity_register_settings' );

function blogpro_entity_menu() {
	add_submenu_page(
		'blogpro-dashboard',
		__( 'Profile Links', 'blog-pro' ),
		__( 'Profile Links', 'blog-pro' ),
		'manage_options',
		'blogpro-profile-links',
		'blogpro_entity_render_page'
	);
}
add_action( 'admin_menu', 'blogpro_entity_menu', 1000 );

function blogpro_entity_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Profile Links', 'blog-pro' ); ?></h1>
		<p><?php esc_html_e( 'Official social profiles for this site. Used in structured data (Organization sameAs) so search engines and AI models can verify entity identity. Include the full https:// URL.', 'blog-pro' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'blogpro_entity_links' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( blogpro_entity_networks() as $slug => $label ) : ?>
					<tr>
						<th scope="row"><label for="blogpro_<?php echo esc_attr( $slug ); ?>_url"><?php echo esc_html( $label ); ?></label></th>
						<td><input type="url" class="regular-text" id="blogpro_<?php echo esc_attr( $slug ); ?>_url"
							name="blogpro_<?php echo esc_attr( $slug ); ?>_url"
							value="<?php echo esc_attr( get_option( 'blogpro_' . $slug . '_url', '' ) ); ?>"
							placeholder="https://"></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* ---------------------------------------------------------------------
 * Author identity fields (Person sameAs + jobTitle)
 * ------------------------------------------------------------------- */

/**
 * Core-provided contactmethod UI: per-author social URLs.
 */
function blogpro_author_contactmethods( $methods ) {
	$methods['blogpro_twitter_url']  = __( 'X (Twitter) profile URL', 'blog-pro' );
	$methods['blogpro_linkedin_url'] = __( 'LinkedIn profile URL', 'blog-pro' );
	$methods['blogpro_facebook_url'] = __( 'Facebook profile URL', 'blog-pro' );
	return $methods;
}
add_filter( 'user_contactmethods', 'blogpro_author_contactmethods' );

/**
 * Job title field — rendered right above the contactmethods block.
 */
function blogpro_author_job_title_field( $user ) {
	?>
	<h2><?php esc_html_e( 'Author Schema', 'blog-pro' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="blogpro_job_title"><?php esc_html_e( 'Job title', 'blog-pro' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="blogpro_job_title" name="blogpro_job_title"
					value="<?php echo esc_attr( get_user_meta( $user->ID, 'blogpro_job_title', true ) ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. Senior Transit Analyst', 'blog-pro' ); ?>">
				<p class="description"><?php esc_html_e( 'Shown as the author\'s jobTitle in structured data.', 'blog-pro' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'blogpro_author_job_title_field' );
add_action( 'edit_user_profile', 'blogpro_author_job_title_field' );

function blogpro_author_save_fields( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	// Contactmethod URL fields (twitter/linkedin/facebook) are saved by core
	// after this hook — consumers validate them (blogpro_author_same_as
	// requires an http(s) URL), so no post-processing needed here.
	if ( isset( $_POST['blogpro_job_title'] ) ) {
		$title = sanitize_text_field( wp_unslash( $_POST['blogpro_job_title'] ) );
		if ( '' === $title ) {
			delete_user_meta( $user_id, 'blogpro_job_title' );
		} else {
			update_user_meta( $user_id, 'blogpro_job_title', $title );
		}
	}
}
add_action( 'personal_options_update', 'blogpro_author_save_fields' );
add_action( 'edit_user_profile_update', 'blogpro_author_save_fields' );

/**
 * Person.sameAs URLs for an author, non-empty only.
 *
 * @param int $user_id
 * @return string[]
 */
function blogpro_author_same_as( $user_id ) {
	$urls = array();
	foreach ( array( 'blogpro_twitter_url', 'blogpro_linkedin_url', 'blogpro_facebook_url' ) as $key ) {
		$url = trim( (string) get_user_meta( $user_id, $key, true ) );
		if ( '' !== $url && preg_match( '#^https?://#i', $url ) ) {
			$urls[] = $url;
		}
	}
	$archive = get_author_posts_url( $user_id );
	if ( $archive ) {
		$urls[] = $archive; // own author page is a sameAs too
	}
	return $urls;
}
