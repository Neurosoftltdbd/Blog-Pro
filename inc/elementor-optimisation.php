<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Elementor performance optimisations.
 *
 * - Frontend (logged-out visitors only): drop Font Awesome, eicons and
 *   Elementor Google Fonts, and dequeue all Elementor frontend + Pro
 *   styles/scripts on pages that are not built with Elementor.
 * - Logged-in users, admins, the dashboard, and the Elementor editor /
 *   preview iframes are never optimised — the editor must render fully.
 * - If Elementor Theme Builder locations (header / footer) are assigned,
 *   their assets are kept so site chrome never breaks.
 *
 * Auto-loaded from functions.php only when Elementor is active.
 */

/**
 * Should frontend Elementor optimisations run in this request context?
 */
function blogpro_elementor_optimize_allowed() {
	// Dashboard / logged-in users (editors, previews) — never optimise.
	if ( is_admin() || is_user_logged_in() ) {
		return false;
	}

	// Elementor editor / preview iframe.
	if ( isset( $_GET['elementor-preview'] ) ) {
		return false;
	}
	if ( did_action( 'elementor/loaded' ) && ! empty( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
		return false;
	}

	return true;
}

/**
 * Dequeue Font Awesome and eicons. Do NOT deregister — other Elementor
 * styles (elementor-common, elementor-pro-notes-frontend) still declare
 * them as dependencies, and deregistering triggers WP 6.9.1 notices.
 */
function blogpro_elementor_dequeue_scripts() {
	if ( ! blogpro_elementor_optimize_allowed() ) {
		return;
	}
	wp_dequeue_style( 'font-awesome' );
	wp_dequeue_style( 'elementor-icons' );
}
add_action( 'elementor/frontend/after_enqueue_styles', 'blogpro_elementor_dequeue_scripts', 20 );

/**
 * Dequeue alone can't stop a style that other styles depend on — WP
 * re-enqueues dependencies when printing. Return false from
 * style_loader_src to suppress the output while keeping the dependency
 * graph intact.
 */
function blogpro_drop_icon_styles( $src, $handle ) {
	if ( ! blogpro_elementor_optimize_allowed() ) {
		return $src;
	}
	if ( in_array( $handle, array( 'font-awesome', 'elementor-icons', 'font-awesome-5-all', 'font-awesome-4-shim' ), true ) ) {
		return false;
	}
	return $src;
}
add_filter( 'style_loader_src', 'blogpro_drop_icon_styles', 10, 2 );

/**
 * Disable Elementor Google Fonts on the frontend only.
 */
function blogpro_disable_elementor_gfonts() {
	return blogpro_elementor_optimize_allowed() ? false : true;
}
add_filter( 'elementor/frontend/print_google_fonts', 'blogpro_disable_elementor_gfonts' );

/**
 * Does the current request need Elementor frontend assets?
 * True when: the post is built with Elementor, OR a Theme Builder
 * location (header/footer) is assigned and rendered for this page.
 */
function blogpro_elementor_needs_assets() {
	$post_id = get_the_ID();

	if ( $post_id && \Elementor\Plugin::$instance->db->is_built_with_elementor( $post_id ) ) {
		return true;
	}

	// Elementor Theme Builder header / footer (future-proofing: a user may
	// assign theme locations later even though header.php/footer.php are
	// classic PHP today).
	if ( function_exists( 'elementor_theme_do_location' ) ) {
		foreach ( array( 'header', 'footer' ) as $location ) {
			if ( elementor_theme_do_location( $location ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Dequeue Elementor assets on frontend pages that don't need them.
 * Works for both free and Pro.
 */
function blogpro_conditionally_load_elementor_assets() {
	if ( ! blogpro_elementor_optimize_allowed() ) {
		return;
	}
	if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}
	if ( blogpro_elementor_needs_assets() ) {
		return;
	}

	wp_dequeue_style( 'elementor-frontend' );
	wp_dequeue_style( 'elementor-pro-frontend' );
	wp_dequeue_script( 'elementor-frontend' );
	wp_dequeue_script( 'elementor-pro-frontend' );
}
add_action( 'wp_enqueue_scripts', 'blogpro_conditionally_load_elementor_assets', 9999 );

/**
 * Widget CSS handles we know about (free + Pro). Handles NOT in this list
 * (unknown widgets, future widgets) are never touched — fail-closed.
 */
function blogpro_elementor_css_handles() {
	return array(
		'widget-icon-list',
		'widget-image',
		'widget-heading',
		'widget-social-icons',
		'widget-icon-box',
		'widget-image-box',
		'widget-accordion',
		'widget-tabs',
		'widget-toggle',
		'widget-counter',
		'widget-progress',
		'widget-text-editor',
		'widget-divider',
		'widget-spacer',
		'widget-button',
		'widget-star-rating',
		'widget-alert',
		'widget-call-to-action',

		// Pro.
		'widget-nav-menu',
		'widget-image-carousel',
		'widget-video',
		'widget-testimonial-carousel',
		'widget-carousel-module-base',
		'widget-media-carousel',
		'widget-reviews',
		'widget-posts',
		'widget-price-table',
		'widget-price-list',
		'widget-flip-box',
		'widget-countdown',
		'widget-form',
		'widget-login',
		'widget-animated-headline',
		'widget-gallery',
		'widget-progress-tracker',
		'widget-blockquote',
		'widget-code-highlight',
	);
}

/**
 * Widget-types actually used on the current page: from the queried post's
 * _elementor_data plus Elementor theme-builder header/footer documents.
 * Fallback to an empty set (dequeue nothing) if meta is missing.
 */
function blogpro_elementor_get_used_widget_types() {
	$used = array();

	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		$data = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_string( $data ) && $data !== '' ) {
			preg_match_all( '/"widgetType":"([a-z0-9-]+)"/i', $data, $m );
			if ( ! empty( $m[1] ) ) {
				$used = array_merge( $used, $m[1] );
			}
		}
	}

	// Theme-builder header/footer documents.
	if ( function_exists( 'elementor_theme_do_location' ) && did_action( 'elementor/loaded' ) && class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
		$locations = array( 'header', 'footer' );
		foreach ( $locations as $location ) {
			$docs = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_documents_for_location( $location );
			foreach ( (array) $docs as $doc ) {
				$doc_id = method_exists( $doc, 'get_post' ) ? $doc->get_post()->ID : 0;
				if ( $doc_id ) {
					$data = get_post_meta( $doc_id, '_elementor_data', true );
					if ( is_string( $data ) && $data !== '' ) {
						preg_match_all( '/"widgetType":"([a-z0-9-]+)"/i', $data, $m );
						if ( ! empty( $m[1] ) ) {
							$used = array_merge( $used, $m[1] );
						}
					}
				}
			}
		}
	}

	return array_unique( $used );
}

/**
 * Is the header or footer an Elementor theme-builder document?
 * When yes, the site chrome itself is built with Elementor — its widget
 * styles must never be dropped, so the optimization is skipped entirely.
 */
function blogpro_elementor_has_theme_locations() {
	if ( ! function_exists( 'elementor_theme_do_location' ) || ! did_action( 'elementor/loaded' ) || ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
		return false;
	}
	foreach ( array( 'header', 'footer' ) as $location ) {
		$docs = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_documents_for_location( $location );
		if ( ! empty( $docs ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Dequeue widget-* styles not used by any widget on the page, plus
 * e-animation-*, e-apple-webkit, e-sticky, and swiper/e-swiper when
 * nothing on the page needs them. Runs after Elementor's own widget-style
 * enqueues (priority 10) so the cascade is stable.
 *
 * Skipped entirely when the header/footer are Elementor theme-builder
 * documents — chrome styles are never touched.
 */
function blogpro_elementor_dequeue_unused_widget_styles() {
	if ( ! blogpro_elementor_optimize_allowed() ) {
		return;
	}
	if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}
	if ( ! blogpro_elementor_needs_assets() ) {
		return;
	}

	// Header/footer built with Elementor — keep everything (site chrome).
	if ( blogpro_elementor_has_theme_locations() ) {
		return;
	}

	$used = blogpro_elementor_get_used_widget_types();

	// Widgets never dequeued even if absent from the map — theme chrome,
	// shared bases, or needed by dynamic/loop content we can't see.
	$always_keep = array(
		'widget-nav-menu',
		'widget-image',
		'widget-heading',
		'widget-icon-list',
		'widget-social-icons',
		'widget-image-carousel',
		'widget-testimonial-carousel',
		'widget-carousel-module-base',
		'widget-media-carousel',
		'widget-reviews',
	);

	foreach ( blogpro_elementor_css_handles() as $handle ) {
		if ( in_array( $handle, $always_keep, true ) ) {
			continue;
		}
		$slug = str_replace( 'widget-', '', $handle );
		if ( ! in_array( $slug, $used, true ) ) {
			wp_dequeue_style( $handle );
		}
	}

	// Non-widget files, dropped only when provably unused.
	$data_blobs = '';
	if ( is_singular() ) {
		$data_blobs .= (string) get_post_meta( get_queried_object_id(), '_elementor_data', true );
	}
	if ( function_exists( 'elementor_theme_do_location' ) && did_action( 'elementor/loaded' ) && class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
		foreach ( array( 'header', 'footer' ) as $location ) {
			$docs = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_documents_for_location( $location );
			foreach ( (array) $docs as $doc ) {
				$doc_id = method_exists( $doc, 'get_post' ) ? $doc->get_post()->ID : 0;
				if ( $doc_id ) {
					$data_blobs .= (string) get_post_meta( $doc_id, '_elementor_data', true );
				}
			}
		}
	}
	$content   = (string) get_the_content();
	$has_anim  = ( false !== strpos( $data_blobs, 'elementor-animation-' ) || false !== strpos( $content, 'elementor-animation-' ) );
	$has_stick = ( false !== strpos( $data_blobs, '"sticky":"yes"' ) || preg_match( '/"widgetType":"sticky"/i', $data_blobs ) );

	// e-animation-* + e-apple-webkit load only when an animation is used.
	if ( ! $has_anim ) {
		global $wp_styles;
		foreach ( array_keys( (array) $wp_styles->registered ) as $handle ) {
			if ( 0 === strpos( $handle, 'e-animation-' ) || 'e-apple-webkit' === $handle ) {
				wp_dequeue_style( $handle );
			}
		}
	}

	if ( ! $has_stick ) {
		wp_dequeue_style( 'e-sticky' );
	}

	// Carousel detection: swiper/e-swiper/widget-carousel-module-base.
	$carousel_types = array( 'image-carousel', 'media-carousel', 'testimonial-carousel', 'reviews', 'nested-carousel', 'slider' );
	$has_carousel   = (bool) array_intersect( $carousel_types, $used );
	if ( ! $has_carousel ) {
		wp_dequeue_style( 'swiper' );
		wp_dequeue_style( 'e-swiper' );
		wp_dequeue_style( 'widget-carousel-module-base' );
	}
}
add_action( 'elementor/frontend/after_enqueue_styles', 'blogpro_elementor_dequeue_unused_widget_styles', 20 );

/**
 * Late cleanup on wp_footer: Pro registers some assets after
 * wp_enqueue_scripts. Dequeuing an unregistered handle is a no-op,
 * so this is safe to run unconditionally once the gate passes.
 */
function blogpro_drop_late_elementor_assets() {
	if ( ! blogpro_elementor_optimize_allowed() ) {
		return;
	}
	if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}
	if ( blogpro_elementor_needs_assets() ) {
		return;
	}

	wp_dequeue_style( 'elementor-frontend' );
	wp_dequeue_style( 'elementor-pro-frontend' );
	wp_dequeue_style( 'elementor-common' );
	wp_dequeue_script( 'elementor-frontend' );
	wp_dequeue_script( 'elementor-pro-frontend' );
}
add_action( 'wp_footer', 'blogpro_drop_late_elementor_assets', 1 );

/**
 * Elementor Page builder support for CPTs (admin options — unchanged).
 */
function blogpro_add_cpt_support() {
	$cpt_support = get_option( 'elementor_cpt_support' );

	if ( ! $cpt_support ) {
		$cpt_support = array( 'page', 'post', 'sidebar', 'header', 'footer' );
		update_option( 'elementor_cpt_support', $cpt_support );
	}
}
add_action( 'after_switch_theme', 'blogpro_add_cpt_support' );

/**
 * Disable Elementor default colors and default fonts (admin options —
 * unchanged).
 */
function blogpro_elementor_disable_default_schemes() {
	update_option( 'elementor_disable_color_schemes', 'yes' );
	update_option( 'elementor_disable_typography_schemes', 'yes' );
}
add_action( 'after_switch_theme', 'blogpro_elementor_disable_default_schemes' );