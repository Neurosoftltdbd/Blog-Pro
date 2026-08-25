<?php
/**
 * Social share — network registry, share URL builders, and admin settings.
 * Replaces the hard-coded icon list that lived in template-tags.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Default settings: all networks on, copy-link button on.
 *
 * @return array
 */
function blogpro_social_share_defaults() {
	return array(
		'networks'  => array( 'facebook', 'x', 'linkedin', 'whatsapp', 'telegram', 'reddit', 'pinterest', 'email' ),
		'copy_link' => 1,
	);
}

/**
 * Current settings merged over defaults, so new networks added by an update
 * or via the filter appear enabled rather than silently dropped.
 *
 * @return array
 */
function blogpro_social_share_settings() {
	return wp_parse_args( get_option( 'blogpro_social_share_settings', array() ), blogpro_social_share_defaults() );
}

/**
 * Network registry: label, share-URL builder, brand SVG path.
 * URL builders receive ( $url, $title, $media, $post_id ).
 *
 * @return array
 */
function blogpro_social_share_networks() {
	$networks = array(
		'facebook' => array(
			'label' => __( 'Facebook', 'blog-pro' ),
			'url'   => function ( $url ) {
				return 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url );
			},
			'svg'   => '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>',
			'class' => 'bg-[#1877F2]',
		),
		'x' => array(
			'label' => __( 'X (Twitter)', 'blog-pro' ),
			'url'   => function ( $url, $title ) {
				return 'https://twitter.com/intent/tweet?text=' . rawurlencode( $title ) . '&url=' . rawurlencode( $url );
			},
			'svg'   => '<path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z"/>',
			'class' => 'bg-black',
		),
		'linkedin' => array(
			'label' => __( 'LinkedIn', 'blog-pro' ),
			'url'   => function ( $url, $title ) {
				return 'https://www.linkedin.com/shareArticle?mini=true&url=' . rawurlencode( $url ) . '&title=' . rawurlencode( $title );
			},
			'svg'   => '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>',
			'class' => 'bg-[#0A66C2]',
		),
		'whatsapp' => array(
			'label' => __( 'WhatsApp', 'blog-pro' ),
			'url'   => function ( $url, $title ) {
				return 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $url );
			},
			'svg'   => '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>',
			'class' => 'bg-[#25D366]',
		),
		'telegram' => array(
			'label' => __( 'Telegram', 'blog-pro' ),
			'url'   => function ( $url, $title ) {
				return 'https://t.me/share/url?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title );
			},
			'svg'   => '<path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0a12 12 0 00-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>',
			'class' => 'bg-[#229ED9]',
		),
		'reddit' => array(
			'label' => __( 'Reddit', 'blog-pro' ),
			'url'   => function ( $url, $title ) {
				return 'https://www.reddit.com/submit?url=' . rawurlencode( $url ) . '&title=' . rawurlencode( $title );
			},
			'svg'   => '<path d="M12 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 01-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 01.042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 014.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 01.14-.197.35.35 0 01.238-.042l2.906.617a1.214 1.214 0 011.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 00-.231.094.33.33 0 000 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 00.029-.463.33.33 0 00-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 00-.232-.095z"/>',
			'class' => 'bg-[#FF4500]',
		),
		'pinterest' => array(
			'label' => __( 'Pinterest', 'blog-pro' ),
			'url'   => function ( $url, $title, $media ) {
				$link = 'https://pinterest.com/pin/create/button/?url=' . rawurlencode( $url ) . '&description=' . rawurlencode( $title );
				if ( $media ) {
					$link .= '&media=' . rawurlencode( $media );
				}
				return $link;
			},
			'svg'   => '<path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/>',
			'class' => 'bg-[#E60023]',
		),
		'email' => array(
			'label' => __( 'Email', 'blog-pro' ),
			'url'   => function ( $url, $title ) {
				return 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( $title . "\n\n" . $url );
			},
			'svg'   => '<path d="M20 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/>',
			'class' => 'bg-indigo-600',
		),
	);
	return apply_filters( 'blogpro_social_share_networks', $networks );
}

/**
 * Build the share list for the current post, honoring saved settings.
 * Returns empty array when no networks are enabled or the post has no permalink.
 *
 * @return array
 */
function blogpro_social_share() {
	$settings = blogpro_social_share_settings();
	$networks = blogpro_social_share_networks();
	$enabled  = (array) $settings['networks'];

	if ( empty( $enabled ) ) {
		return array();
	}

	$post_id = get_the_ID();
	$url     = get_permalink( $post_id );
	if ( ! $url ) {
		return array();
	}

	$title = get_the_title( $post_id );
	$media = has_post_thumbnail( $post_id ) ? (string) get_the_post_thumbnail_url( $post_id, 'large' ) : '';

	$share = array();
	foreach ( $enabled as $slug ) {
		if ( ! isset( $networks[ $slug ] ) ) {
			continue;
		}
		$data            = $networks[ $slug ];
		$share[ $slug ]  = array(
			'label' => $data['label'],
			'url'   => $data['url']( $url, $title, $media, $post_id ),
			'svg'   => $data['svg'],
			'class' => isset( $data['class'] ) ? $data['class'] : 'bg-gray-500',
		);
	}

	return apply_filters( 'blogpro_social_share', $share, $post_id );
}

/* ---------------------------------------------------------------------
 * Admin settings — select which networks show up, toggle copy-link.
 * ------------------------------------------------------------------- */

function blogpro_social_share_register_settings() {
	register_setting( 'blogpro_social_share', 'blogpro_social_share_settings', array(
		'type'              => 'array',
		'sanitize_callback' => 'blogpro_social_share_sanitize',
	) );
}
add_action( 'admin_init', 'blogpro_social_share_register_settings' );

/**
 * Whitelist slugs against the registry, force copy_link to 0|1.
 *
 * @param mixed $input
 * @return array
 */
function blogpro_social_share_sanitize( $input ) {
	$input   = is_array( $input ) ? $input : array();
	$allowed = array_keys( blogpro_social_share_networks() );

	$networks = array();
	if ( ! empty( $input['networks'] ) && is_array( $input['networks'] ) ) {
		foreach ( $input['networks'] as $slug ) {
			if ( in_array( $slug, $allowed, true ) ) {
				$networks[] = $slug;
			}
		}
	}

	return array(
		'networks'  => $networks,
		'copy_link' => empty( $input['copy_link'] ) ? 0 : 1,
	);
}

/**
 * Register the Share submenu under the Blog Pro dashboard menu.
 * Priority 1000 keeps it after the parent menu (registered at 999).
 */
function blogpro_social_share_menu() {
	add_submenu_page(
		'blogpro-dashboard',
		__( 'Share Settings', 'blog-pro' ),
		__( 'Share', 'blog-pro' ),
		'manage_options',
		'blogpro-share',
		'blogpro_social_share_render_page'
	);
}
add_action( 'admin_menu', 'blogpro_social_share_menu', 1000 );

/**
 * Settings page render.
 */
function blogpro_social_share_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$settings = blogpro_social_share_settings();
	$networks = blogpro_social_share_networks();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Share Settings', 'blog-pro' ); ?></h1>
		<p><?php esc_html_e( 'Choose which sharing buttons appear on posts and whether the copy-link button is shown.', 'blog-pro' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'blogpro_social_share' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Networks', 'blog-pro' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Networks', 'blog-pro' ); ?></legend>
							<?php foreach ( $networks as $slug => $data ) : ?>
								<label style="display:block;margin-bottom:6px;">
									<input type="checkbox" name="blogpro_social_share_settings[networks][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $settings['networks'], true ) ); ?>>
									<?php echo esc_html( $data['label'] ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Uncheck all to hide the share section entirely.', 'blog-pro' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Copy link button', 'blog-pro' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="blogpro_social_share_settings[copy_link]" value="1" <?php checked( ! empty( $settings['copy_link'] ) ); ?>>
							<?php esc_html_e( 'Show a copy-link button alongside the share buttons.', 'blog-pro' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
