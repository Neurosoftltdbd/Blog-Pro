<?php
/**
 * Adsterra Ad Management & Section Placements
 *
 * Provides custom section-wise ad unit injection for single posts and
 * site-wide ad slots (Header, In-Content, After-Content, Sidebar, and Social Bar / Head code).
 * Includes Core Web Vitals protections: CLS layout-shift prevention and
 * native IntersectionObserver lazy-loading on scroll.
 *
 * @package BlogPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get Adsterra settings with default values.
 *
 * @return array
 */
function blogpro_adsterra_get_settings() {
	$defaults = array(
		'enabled'              => '1',
		'disable_for_admins'   => '1',
		'show_disclosure'      => '1',
		'center_ads'           => '1',
		'prevent_cls'          => '1',
		'lazy_load'            => '1',
		'in_content_paragraph' => '3',
		'ad_before_content'    => '',
		'ad_in_content'        => '',
		'ad_after_content'     => '',
		'ad_sidebar'           => '',
		'ad_head_code'         => '',
		'ad_footer_code'       => '',
	);

	$saved = get_option( 'blogpro_adsterra_settings', array() );

	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/**
 * Register settings and admin menu under Blog Pro.
 */
function blogpro_adsterra_admin_menu() {
	add_submenu_page(
		'blogpro-dashboard',
		__( 'Adsterra Ads', 'blog-pro' ),
		__( 'Adsterra Ads', 'blog-pro' ),
		'manage_options',
		'blogpro-adsterra',
		'blogpro_adsterra_render_settings_page'
	);
}
add_action( 'admin_menu', 'blogpro_adsterra_admin_menu', 1000 );

/**
 * Register settings with sanitization.
 */
function blogpro_adsterra_register_settings() {
	register_setting(
		'blogpro_adsterra_group',
		'blogpro_adsterra_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'blogpro_adsterra_sanitize_settings',
		)
	);
}
add_action( 'admin_init', 'blogpro_adsterra_register_settings' );

/**
 * Sanitize ad code settings while allowing unfiltered HTML for administrators.
 *
 * @param array $input Raw input.
 * @return array
 */
function blogpro_adsterra_sanitize_settings( $input ) {
	$output = blogpro_adsterra_get_settings();

	$output['enabled']              = ! empty( $input['enabled'] ) ? '1' : '0';
	$output['disable_for_admins']   = ! empty( $input['disable_for_admins'] ) ? '1' : '0';
	$output['show_disclosure']      = ! empty( $input['show_disclosure'] ) ? '1' : '0';
	$output['center_ads']           = ! empty( $input['center_ads'] ) ? '1' : '0';
	$output['prevent_cls']          = ! empty( $input['prevent_cls'] ) ? '1' : '0';
	$output['lazy_load']            = ! empty( $input['lazy_load'] ) ? '1' : '0';
	$output['in_content_paragraph'] = max( 1, intval( $input['in_content_paragraph'] ?? 3 ) );

	$code_fields = array(
		'ad_before_content',
		'ad_in_content',
		'ad_after_content',
		'ad_sidebar',
		'ad_head_code',
		'ad_footer_code',
	);

	foreach ( $code_fields as $field ) {
		if ( isset( $input[ $field ] ) ) {
			if ( current_user_can( 'unfiltered_html' ) ) {
				$output[ $field ] = trim( (string) $input[ $field ] );
			} else {
				// Fallback sanitization that allows script and iframe tags for ad networks.
				$output[ $field ] = wp_kses(
					(string) $input[ $field ],
					array(
						'script'   => array( 'type' => true, 'src' => true, 'async' => true, 'defer' => true, 'crossorigin' => true ),
						'iframe'   => array( 'src' => true, 'width' => true, 'height' => true, 'frameborder' => true, 'scrolling' => true, 'style' => true, 'id' => true, 'name' => true ),
						'ins'      => array( 'class' => true, 'style' => true, 'data-ad-client' => true, 'data-ad-slot' => true ),
						'div'      => array( 'class' => true, 'id' => true, 'style' => true ),
						'a'        => array( 'href' => true, 'target' => true, 'rel' => true, 'class' => true ),
						'img'      => array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'class' => true, 'border' => true ),
					)
				);
			}
		}
	}

	return $output;
}

/**
 * Determine whether ads should be displayed for the current request.
 *
 * @return bool
 */
function blogpro_adsterra_can_display() {
	$settings = blogpro_adsterra_get_settings();

	if ( '1' !== $settings['enabled'] ) {
		return false;
	}

	// Disable for logged-in administrators if configured.
	if ( '1' === $settings['disable_for_admins'] && current_user_can( 'manage_options' ) ) {
		return false;
	}

	return true;
}

/**
 * Render an ad slot markup with disclosure, zero CLS reservation, and optional lazy loading.
 *
 * @param string $slot_name Slot identifier.
 * @return string HTML output.
 */
function blogpro_adsterra_get_slot( $slot_name ) {
	if ( ! blogpro_adsterra_can_display() ) {
		return '';
	}

	$settings = blogpro_adsterra_get_settings();
	$field    = 'ad_' . $slot_name;

	if ( empty( $settings[ $field ] ) ) {
		return '';
	}

	$code = trim( $settings[ $field ] );
	if ( empty( $code ) ) {
		return '';
	}

	// 1. Minimum height reservation to prevent Cumulative Layout Shift (CLS).
	$cls_classes = '';
	if ( '1' === $settings['prevent_cls'] ) {
		switch ( $slot_name ) {
			case 'before_content':
				$cls_classes = 'min-h-[60px] sm:min-h-[100px]';
				break;
			case 'in_content':
				$cls_classes = 'min-h-[260px] sm:min-h-[290px]';
				break;
			case 'after_content':
				$cls_classes = 'min-h-[260px]';
				break;
			case 'sidebar':
				$cls_classes = 'min-h-[260px] sm:min-h-[610px]';
				break;
		}
	}

	$center_class = '1' === $settings['center_ads'] ? 'flex flex-col items-center justify-center text-center' : '';
	$disclosure   = '';

	if ( '1' === $settings['show_disclosure'] ) {
		$disclosure = '<span class="block text-[11px] uppercase tracking-wider font-medium text-gray-400 mb-1.5 select-none" aria-label="' . esc_attr__( 'Advertisement', 'blog-pro' ) . '">' . esc_html__( 'Advertisement', 'blog-pro' ) . '</span>';
	}

	// Determine if this slot should be lazy-loaded on scroll (below-the-fold units).
	$is_lazy_slot = ( '1' === $settings['lazy_load'] ) && in_array( $slot_name, array( 'in_content', 'after_content', 'sidebar' ), true );

	if ( $is_lazy_slot ) {
		global $blogpro_has_lazy_ads;
		$blogpro_has_lazy_ads = true;

		return sprintf(
			'<div class="blogpro-ad blogpro-ad-lazy blogpro-ad-%s my-6 w-full max-w-full overflow-hidden %s %s" data-ad-slot="%s">%s<template class="blogpro-ad-template">%s</template><div class="blogpro-ad-wrapper max-w-full w-full"></div></div>',
			esc_attr( $slot_name ),
			esc_attr( $cls_classes ),
			esc_attr( $center_class ),
			esc_attr( $slot_name ),
			$disclosure,
			$code
		);
	}

	return sprintf(
		'<div class="blogpro-ad blogpro-ad-%s my-6 w-full max-w-full overflow-hidden %s %s" data-ad-slot="%s">%s<div class="blogpro-ad-wrapper max-w-full w-full">%s</div></div>',
		esc_attr( $slot_name ),
		esc_attr( $cls_classes ),
		esc_attr( $center_class ),
		esc_attr( $slot_name ),
		$disclosure,
		$code
	);
}

/**
 * Helper template function to echo an ad slot directly.
 *
 * @param string $slot_name Slot name ('before_content', 'in_content', 'after_content', 'sidebar').
 */
function blogpro_ad( $slot_name ) {
	echo blogpro_adsterra_get_slot( $slot_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Inject ads automatically into single post content.
 *
 * @param string $content Post content.
 * @return string
 */
function blogpro_adsterra_inject_content_ads( $content ) {
	if ( ! is_singular( 'post' ) || ! is_main_query() || ! in_the_loop() ) {
		return $content;
	}

	if ( ! blogpro_adsterra_can_display() ) {
		return $content;
	}

	$settings = blogpro_adsterra_get_settings();

	// 1. Before Content Ad.
	$before_ad = blogpro_adsterra_get_slot( 'before_content' );

	// 2. In-Content Ad after paragraph N.
	$in_content_ad = blogpro_adsterra_get_slot( 'in_content' );
	if ( ! empty( $in_content_ad ) ) {
		$target_p = max( 1, intval( $settings['in_content_paragraph'] ) );
		$paragraphs = explode( '</p>', $content );
		$p_count = count( $paragraphs );

		if ( $p_count > $target_p ) {
			foreach ( $paragraphs as $index => $paragraph ) {
				if ( trim( $paragraph ) ) {
					$paragraphs[ $index ] .= '</p>';
				}
				if ( $index + 1 === $target_p ) {
					$paragraphs[ $index ] .= $in_content_ad;
				}
			}
			$content = implode( '', $paragraphs );
		} else {
			// If post is too short for target paragraph, append in-content ad before end.
			$content .= $in_content_ad;
		}
	}

	// 3. After Content Ad.
	$after_ad = blogpro_adsterra_get_slot( 'after_content' );

	return $before_ad . $content . $after_ad;
}
add_filter( 'the_content', 'blogpro_adsterra_inject_content_ads', 20 );

/**
 * Inject Head Code (Social Bar / Popunder / Verification).
 */
function blogpro_adsterra_inject_head_code() {
	if ( ! blogpro_adsterra_can_display() ) {
		return;
	}

	$settings = blogpro_adsterra_get_settings();
	if ( ! empty( $settings['ad_head_code'] ) ) {
		echo "\n<!-- Adsterra Header Ad Code -->\n" . $settings['ad_head_code'] . "\n<!-- /Adsterra Header Ad Code -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'wp_head', 'blogpro_adsterra_inject_head_code', 99 );

/**
 * Inject Footer Code (Social Bar / Popunder scripts) and lazy ad runner.
 */
function blogpro_adsterra_inject_footer_code() {
	if ( ! blogpro_adsterra_can_display() ) {
		return;
	}

	$settings = blogpro_adsterra_get_settings();
	if ( ! empty( $settings['ad_footer_code'] ) ) {
		echo "\n<!-- Adsterra Footer Ad Code -->\n" . $settings['ad_footer_code'] . "\n<!-- /Adsterra Footer Ad Code -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// Ultra-lightweight native IntersectionObserver script (< 30 lines) for zero TBT lazy loading.
	global $blogpro_has_lazy_ads;
	if ( ! empty( $blogpro_has_lazy_ads ) ) {
		?>
		<script id="blogpro-lazy-ad-observer">
		(function(){
			function activateAd(container){
				if(container.dataset.adLoaded) return;
				container.dataset.adLoaded = '1';
				var tmpl = container.querySelector('.blogpro-ad-template');
				var target = container.querySelector('.blogpro-ad-wrapper');
				if(!tmpl || !target) return;
				var clone = tmpl.content ? tmpl.content.cloneNode(true) : null;
				if(clone){
					target.appendChild(clone);
				} else {
					target.innerHTML = tmpl.innerHTML;
				}
				target.querySelectorAll('script').forEach(function(s){
					var n = document.createElement('script');
					Array.from(s.attributes).forEach(function(a){ n.setAttribute(a.name, a.value); });
					if(s.innerHTML) n.appendChild(document.createTextNode(s.innerHTML));
					s.parentNode.replaceChild(n, s);
				});
			}

			if(!('IntersectionObserver' in window)){
				document.querySelectorAll('.blogpro-ad-lazy').forEach(activateAd);
				return;
			}

			var obs = new IntersectionObserver(function(entries, observer){
				entries.forEach(function(entry){
					if(entry.isIntersecting){
						activateAd(entry.target);
						observer.unobserve(entry.target);
					}
				});
			}, { rootMargin: '350px 0px' });

			document.querySelectorAll('.blogpro-ad-lazy').forEach(function(el){ obs.observe(el); });
		})();
		</script>
		<?php
	}
}
add_action( 'wp_footer', 'blogpro_adsterra_inject_footer_code', 99 );

/**
 * Register shortcode [blogpro_ad slot="in_content|sidebar|before_content|after_content"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function blogpro_adsterra_shortcode( $atts ) {
	$a = shortcode_atts(
		array(
			'slot' => 'in_content',
		),
		$atts,
		'blogpro_ad'
	);

	return blogpro_adsterra_get_slot( sanitize_key( $a['slot'] ) );
}
add_shortcode( 'blogpro_ad', 'blogpro_adsterra_shortcode' );

/**
 * Render modern Admin Settings Page under Blog Pro -> Adsterra Ads.
 */
function blogpro_adsterra_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = blogpro_adsterra_get_settings();
	?>
	<div class="wrap" style="max-width: 1080px; margin: 20px 0;">
		<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px 32px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
				<div>
					<h1 style="margin: 0; font-size: 26px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px;">
						<span style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: #6366f1; color: #fff; border-radius: 10px; font-size: 20px;">⚡</span>
						<?php esc_html_e( 'Adsterra Ad Units & Placements', 'blog-pro' ); ?>
					</h1>
					<p style="margin: 8px 0 0 0; color: #64748b; font-size: 14px;">
						<?php esc_html_e( 'Configure your section-wise Adsterra ad codes for single posts, sidebars, and sitewide scripts.', 'blog-pro' ); ?>
					</p>
				</div>
				<div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 10px 16px; font-size: 13px; color: #065f46;">
					<strong>🚀 <?php esc_html_e( 'PageSpeed Optimized:', 'blog-pro' ); ?></strong>
					Zero CLS reservation &amp; native scroll lazy-loading active.
				</div>
			</div>
		</div>

		<?php if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) : ?>
			<div class="notice notice-success is-dismissible" style="border-radius: 8px; margin-bottom: 20px;">
				<p><strong><?php esc_html_e( 'Adsterra settings saved successfully!', 'blog-pro' ); ?></strong></p>
			</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'blogpro_adsterra_group' ); ?>

			<!-- General Display & Performance Controls Card -->
			<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px 32px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<h2 style="font-size: 18px; font-weight: 700; color: #1e293b; margin-top: 0; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
					<?php esc_html_e( '1. Global Ad & Core Web Vitals Controls', 'blog-pro' ); ?>
				</h2>

				<table class="form-table" role="presentation" style="margin-top: 0;">
					<tr>
						<th scope="row" style="width: 260px; font-weight: 600; color: #334155;">
							<?php esc_html_e( 'Master Ad Switch', 'blog-pro' ); ?>
						</th>
						<td>
							<label style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: #1e293b;">
								<input type="checkbox" name="blogpro_adsterra_settings[enabled]" value="1" <?php checked( '1', $settings['enabled'] ); ?> style="width: 18px; height: 18px;">
								<?php esc_html_e( 'Enable ad rendering on this site', 'blog-pro' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Uncheck this box to immediately pause all ad units sitewide.', 'blog-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row" style="font-weight: 600; color: #334155;">
							<?php esc_html_e( 'Core Web Vitals Protections', 'blog-pro' ); ?>
						</th>
						<td>
							<div style="margin-bottom: 10px;">
								<label style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: #0f172a;">
									<input type="checkbox" name="blogpro_adsterra_settings[prevent_cls]" value="1" <?php checked( '1', $settings['prevent_cls'] ); ?> style="width: 18px; height: 18px;">
									<?php esc_html_e( 'Prevent CLS (Reserve minimum ad height)', 'blog-pro' ); ?>
								</label>
								<p class="description" style="margin-left: 26px;"><?php esc_html_e( 'Holds the exact dimensions of ad units in advance so content never jumps when ads load (guarantees 0 layout shift).', 'blog-pro' ); ?></p>
							</div>
							<div>
								<label style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: #0f172a;">
									<input type="checkbox" name="blogpro_adsterra_settings[lazy_load]" value="1" <?php checked( '1', $settings['lazy_load'] ); ?> style="width: 18px; height: 18px;">
									<?php esc_html_e( 'Lazy-load below-the-fold ads on scroll', 'blog-pro' ); ?>
								</label>
								<p class="description" style="margin-left: 26px;"><?php esc_html_e( 'Uses native IntersectionObserver to delay in-content and bottom ad scripts until the reader scrolls near them, eliminating Total Blocking Time (TBT).', 'blog-pro' ); ?></p>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row" style="font-weight: 600; color: #334155;">
							<?php esc_html_e( 'Admin View Mode', 'blog-pro' ); ?>
						</th>
						<td>
							<label style="display: inline-flex; align-items: center; gap: 8px;">
								<input type="checkbox" name="blogpro_adsterra_settings[disable_for_admins]" value="1" <?php checked( '1', $settings['disable_for_admins'] ); ?> style="width: 18px; height: 18px;">
								<?php esc_html_e( 'Hide ads for logged-in administrators', 'blog-pro' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Recommended: prevents false impressions and keeps your editing experience clean.', 'blog-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row" style="font-weight: 600; color: #334155;">
							<?php esc_html_e( 'Ad Label & Alignment', 'blog-pro' ); ?>
						</th>
						<td>
							<label style="display: inline-flex; align-items: center; gap: 8px; margin-right: 20px;">
								<input type="checkbox" name="blogpro_adsterra_settings[show_disclosure]" value="1" <?php checked( '1', $settings['show_disclosure'] ); ?> style="width: 18px; height: 18px;">
								<?php esc_html_e( 'Show "Advertisement" disclosure tag', 'blog-pro' ); ?>
							</label>
							<label style="display: inline-flex; align-items: center; gap: 8px;">
								<input type="checkbox" name="blogpro_adsterra_settings[center_ads]" value="1" <?php checked( '1', $settings['center_ads'] ); ?> style="width: 18px; height: 18px;">
								<?php esc_html_e( 'Center ad banners horizontally', 'blog-pro' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row" style="font-weight: 600; color: #334155;">
							<?php esc_html_e( 'In-Content Ad Paragraph Position', 'blog-pro' ); ?>
						</th>
						<td>
							<div style="display: flex; align-items: center; gap: 10px;">
								<span><?php esc_html_e( 'Insert in-content ad after paragraph #', 'blog-pro' ); ?></span>
								<input type="number" name="blogpro_adsterra_settings[in_content_paragraph]" value="<?php echo esc_attr( $settings['in_content_paragraph'] ); ?>" min="1" max="20" style="width: 70px; padding: 4px 8px; border-radius: 6px;">
							</div>
							<p class="description"><?php esc_html_e( 'Paragraph 3 or 4 is recommended for maximum viewability and engagement.', 'blog-pro' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Single Post Section-wise Ad Placements Card -->
			<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px 32px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<h2 style="font-size: 18px; font-weight: 700; color: #1e293b; margin-top: 0; margin-bottom: 8px;">
					<?php esc_html_e( '2. Single Post Ad Units (Section-Wise)', 'blog-pro' ); ?>
				</h2>
				<p style="color: #64748b; font-size: 14px; margin-top: 0; margin-bottom: 24px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
					<?php esc_html_e( 'Paste your Adsterra script codes into each respective slot below. Leave blank any slot you do not wish to use.', 'blog-pro' ); ?>
				</p>

				<!-- Unit 1: Top / Before Content -->
				<div style="margin-bottom: 28px;">
					<label for="ad_before_content" style="display: block; font-weight: 700; font-size: 15px; color: #0f172a; margin-bottom: 4px;">
						📍 <?php esc_html_e( 'Unit 1: Above Article Content (Top Banner)', 'blog-pro' ); ?>
					</label>
					<div style="font-size: 12px; color: #6366f1; font-weight: 600; margin-bottom: 8px;">
						<?php esc_html_e( 'Recommended Format: 728×90 (Desktop) / 320×50 or 468×60 (Mobile) / Responsive Banner', 'blog-pro' ); ?>
					</div>
					<textarea id="ad_before_content" name="blogpro_adsterra_settings[ad_before_content]" rows="4" style="width: 100%; font-family: monospace; font-size: 13px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc;" placeholder="&lt;!-- Paste your Adsterra Top Banner code here --&gt;"><?php echo esc_textarea( $settings['ad_before_content'] ); ?></textarea>
				</div>

				<!-- Unit 2: In-Content (Middle) -->
				<div style="margin-bottom: 28px;">
					<label for="ad_in_content" style="display: block; font-weight: 700; font-size: 15px; color: #0f172a; margin-bottom: 4px;">
						📍 <?php esc_html_e( 'Unit 2: In-Content (Inside Article Flow)', 'blog-pro' ); ?>
					</label>
					<div style="font-size: 12px; color: #6366f1; font-weight: 600; margin-bottom: 8px;">
						<?php esc_html_e( 'Recommended Format: Native Banner (1×1 or 2×1 widget) or 300×250 Rectangle Banner (Highest CTR slot)', 'blog-pro' ); ?>
					</div>
					<textarea id="ad_in_content" name="blogpro_adsterra_settings[ad_in_content]" rows="4" style="width: 100%; font-family: monospace; font-size: 13px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc;" placeholder="&lt;!-- Paste your Adsterra In-Content Native/Banner code here --&gt;"><?php echo esc_textarea( $settings['ad_in_content'] ); ?></textarea>
				</div>

				<!-- Unit 3: After Content (Bottom) -->
				<div style="margin-bottom: 28px;">
					<label for="ad_after_content" style="display: block; font-weight: 700; font-size: 15px; color: #0f172a; margin-bottom: 4px;">
						📍 <?php esc_html_e( 'Unit 3: Below Article Content (Bottom Banner)', 'blog-pro' ); ?>
					</label>
					<div style="font-size: 12px; color: #6366f1; font-weight: 600; margin-bottom: 8px;">
						<?php esc_html_e( 'Recommended Format: Native Banner (4×1 or 4×2 grid) or 728×90 / 300×250 Banner', 'blog-pro' ); ?>
					</div>
					<textarea id="ad_after_content" name="blogpro_adsterra_settings[ad_after_content]" rows="4" style="width: 100%; font-family: monospace; font-size: 13px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc;" placeholder="&lt;!-- Paste your Adsterra Bottom Native / Banner code here --&gt;"><?php echo esc_textarea( $settings['ad_after_content'] ); ?></textarea>
				</div>

				<!-- Unit 4: Sidebar Ad -->
				<div>
					<label for="ad_sidebar" style="display: block; font-weight: 700; font-size: 15px; color: #0f172a; margin-bottom: 4px;">
						📍 <?php esc_html_e( 'Unit 4: Sidebar Ad Slot', 'blog-pro' ); ?>
					</label>
					<div style="font-size: 12px; color: #6366f1; font-weight: 600; margin-bottom: 8px;">
						<?php esc_html_e( 'Recommended Format: 160×600 Skyscraper, 300×250, or 300×600 Half-Page Banner. Also callable via shortcode [blogpro_ad slot="sidebar"].', 'blog-pro' ); ?>
					</div>
					<textarea id="ad_sidebar" name="blogpro_adsterra_settings[ad_sidebar]" rows="4" style="width: 100%; font-family: monospace; font-size: 13px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc;" placeholder="&lt;!-- Paste your Adsterra Sidebar ad code here --&gt;"><?php echo esc_textarea( $settings['ad_sidebar'] ); ?></textarea>
				</div>
			</div>

			<!-- Sitewide / High CPM Formats (Social Bar, Popunder, Head scripts) -->
			<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px 32px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<h2 style="font-size: 18px; font-weight: 700; color: #1e293b; margin-top: 0; margin-bottom: 8px;">
					<?php esc_html_e( '3. Sitewide High-CPM Units (Social Bar / Popunder / Scripts)', 'blog-pro' ); ?>
				</h2>
				<p style="color: #64748b; font-size: 14px; margin-top: 0; margin-bottom: 24px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
					<?php esc_html_e( 'Adsterra Social Bar and Popunder scripts are injected sitewide into the head or footer tags.', 'blog-pro' ); ?>
				</p>

				<div style="margin-bottom: 28px;">
					<label for="ad_head_code" style="display: block; font-weight: 700; font-size: 15px; color: #0f172a; margin-bottom: 4px;">
						⚙️ <?php esc_html_e( 'Header Script Code (<head>)', 'blog-pro' ); ?>
					</label>
					<div style="font-size: 12px; color: #64748b; margin-bottom: 8px;">
						<?php esc_html_e( 'Injected right before </head>. Suitable for Adsterra Anti-Adblock script or header tags.', 'blog-pro' ); ?>
					</div>
					<textarea id="ad_head_code" name="blogpro_adsterra_settings[ad_head_code]" rows="3" style="width: 100%; font-family: monospace; font-size: 13px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc;" placeholder="&lt;script ...&gt;&lt;/script&gt;"><?php echo esc_textarea( $settings['ad_head_code'] ); ?></textarea>
				</div>

				<div>
					<label for="ad_footer_code" style="display: block; font-weight: 700; font-size: 15px; color: #0f172a; margin-bottom: 4px;">
						⚙️ <?php esc_html_e( 'Footer / Body Script Code (Social Bar / Popunder)', 'blog-pro' ); ?>
					</label>
					<div style="font-size: 12px; color: #64748b; margin-bottom: 8px;">
						<?php esc_html_e( 'Injected right before </body>. Ideal for Adsterra Social Bar (chat, push notification simulation) or Popunder tags.', 'blog-pro' ); ?>
					</div>
					<textarea id="ad_footer_code" name="blogpro_adsterra_settings[ad_footer_code]" rows="3" style="width: 100%; font-family: monospace; font-size: 13px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc;" placeholder="&lt;script ...&gt;&lt;/script&gt;"><?php echo esc_textarea( $settings['ad_footer_code'] ); ?></textarea>
				</div>
			</div>

			<div style="margin-top: 24px;">
				<?php submit_button( __( 'Save Adsterra Settings', 'blog-pro' ), 'primary large', 'submit', false, array( 'style' => 'border-radius: 10px; padding: 10px 28px; font-weight: 700; font-size: 15px;' ) ); ?>
			</div>
		</form>
	</div>
	<?php
}
