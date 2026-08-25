<?php
/**
 * Blog Pro theme bootstrap.
 * Loads all functionality modules. Kept plugin-free by design.
 *
 * Copyright (C) 2026 Nur Hossain Repon, nhrepon (https://nhrepon.com)
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BLOGPRO_VERSION', '1.0.0' );
define( 'BLOGPRO_DIR', get_template_directory() );
define( 'BLOGPRO_URI', get_template_directory_uri() );

/* ---------------------------------------------------------------------
 * 1. Theme setup
 * ------------------------------------------------------------------- */
function blogpro_setup() {
	load_theme_textdomain( 'blog-pro', BLOGPRO_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'custom-logo', array( 'height' => 60, 'width' => 200, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );

	// Custom, compact thumbnail sizes — avoids generating/serving oversized images.
	// set_post_thumbnail_size( 640, 400, true );
	// add_image_size( 'blogpro-card', 480, 300, true );
	// add_image_size( 'blogpro-hero', 1200, 675, true );

	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'blog-pro' ),
		'footer'  => __( 'Footer Menu', 'blog-pro' ),
	) );
}
add_action( 'after_setup_theme', 'blogpro_setup' );

function blogpro_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Footer', 'blog-pro' ),
		'id'            => 'footer-1',
		'before_widget' => '<div class="footer-widget">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4>',
		'after_title'   => '</h4>',
	) );
}
add_action( 'widgets_init', 'blogpro_widgets_init' );

/* ---------------------------------------------------------------------
 * 2. Load feature modules
 * ------------------------------------------------------------------- */
require BLOGPRO_DIR . '/inc/performance.php';   // speed optimizations
require BLOGPRO_DIR . '/inc/seo-meta.php';       // dynamic meta tags
require BLOGPRO_DIR . '/inc/schema.php';         // JSON-LD structured data
require BLOGPRO_DIR . '/inc/sitemap.php';        // XML sitemap
require BLOGPRO_DIR . '/inc/robots.php';         // robots.txt
require BLOGPRO_DIR . '/inc/rest-api.php';       // custom REST endpoints
require BLOGPRO_DIR . '/inc/media-optimize.php'; // image/video optimization
require BLOGPRO_DIR . '/inc/force-wp-to-webp.php'; // serve WebP masters through core image APIs
require BLOGPRO_DIR . '/inc/template-tags.php';  // helper functions for templates
require BLOGPRO_DIR . '/inc/social-share.php';   // social share networks + settings
require BLOGPRO_DIR . '/inc/contact-form.php';   // no-plugin contact form handler
require BLOGPRO_DIR . '/inc/htaccess.php';       // writes caching rules into .htaccess on activation
require BLOGPRO_DIR . '/inc/admin-tools.php';    // bulk-optimize images uploaded before theme activation
require BLOGPRO_DIR . '/inc/llms.php';    // serve dynamic llms.txt
require BLOGPRO_DIR . '/inc/web-mcp-schema.php'; // WebMCP tool schemas
require BLOGPRO_DIR . '/inc/class-blogpro-nav-walker.php'; // Dropdown menu walker
require BLOGPRO_DIR . '/inc/widgets.php';
require BLOGPRO_DIR . '/widgets/class-blogpro-widgets-loader.php'; // custom widgets
require BLOGPRO_DIR . '/inc/templates-loader.php'; // Custom Template Loader
require BLOGPRO_DIR . '/inc/internal-linking.php'; // auto internal linking
require BLOGPRO_DIR . '/blocks/class-blogpro-block.php'; // FAQ accordion component
require BLOGPRO_DIR . '/inc/faq-optimisation.php'; // avoids duplicate FAQ output
require BLOGPRO_DIR . '/admin/class-blogpro-admin-menu.php'; // branded dashboard sidebar


// WooCommerce integration if active
if ( class_exists( 'WooCommerce' ) ) {
    require BLOGPRO_DIR . '/woocommerce/wcom-support.php';
}

// Elementor Optimisation
if( class_exists( 'Elementor\Plugin' ) ) {
    require BLOGPRO_DIR . '/inc/elementor-optimisation.php';
}



/* ---------------------------------------------------------------------
 * 3. Assets — minimal, deferred, no external dependencies
 * ------------------------------------------------------------------- */

// ------------------------------------------------------------
// Limit srcset to images no larger than the rendered width
function blogpro_limit_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
    $max_width = $size_array[0]; // width the image will be displayed at
    foreach ( $sources as $key => $src ) {
        if ( $src['value'] > $max_width ) {
            unset( $sources[ $key ] );
        }
    }
    return $sources;
}
add_filter( 'wp_calculate_image_srcset', 'blogpro_limit_srcset', 10, 5 );

// ------------------------------------------------------------
// Defer Tailwind CSS – load it non‑blocking
function blogpro_assets() {
    wp_enqueue_style( 'blogpro-style', get_stylesheet_uri(), array(), BLOGPRO_VERSION );

// if ( file_exists( BLOGPRO_DIR . '/assets/css/tailwind.css' ) ) {
//         // Load as print media first, then switch to all onload (non‑blocking)
//         wp_enqueue_style( 'blogpro-tailwind', BLOGPRO_URI . '/assets/css/tailwind.css', array(), filemtime( BLOGPRO_DIR . '/assets/css/tailwind.css' ), 'print' );
//         wp_style_add_data( 'blogpro-tailwind', 'preload', true );
//         wp_style_add_data( 'blogpro-tailwind', 'onload', "this.rel='stylesheet'" );
//     }


    if ( file_exists( BLOGPRO_DIR . '/assets/css/tailwind.css' ) ) {
		wp_enqueue_style( 'blogpro-tailwind', BLOGPRO_URI . '/assets/css/tailwind.css', array(), filemtime( BLOGPRO_DIR . '/assets/css/tailwind.css' ) );
    }
	wp_style_add_data( 'blogpro-tailwind', 'preload', true );
	wp_style_add_data( 'blogpro-tailwind', 'onload', "this.rel='stylesheet'" );

    wp_enqueue_script( 'blogpro-main', BLOGPRO_URI . '/assets/js/main.js', array(), BLOGPRO_VERSION, true );
    wp_script_add_data( 'blogpro-main', 'defer', true );

    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
}
add_action( 'wp_enqueue_scripts', 'blogpro_assets' );

/* Add defer/async attributes generically for any future scripts flagged this way. */
function blogpro_script_attributes( $tag, $handle, $src ) {
	if ( wp_scripts()->get_data( $handle, 'defer' ) ) {
		$tag = str_replace( ' src', ' defer src', $tag );
	}
	if ( wp_scripts()->get_data( $handle, 'async' ) ) {
		$tag = str_replace( ' src', ' async src', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'blogpro_script_attributes', 10, 3 );

/* ---------------------------------------------------------------------
 * 4. Excerpt tuning
 * ------------------------------------------------------------------- */
add_filter( 'excerpt_length', function () { return 30; } );
add_filter( 'excerpt_more', function () { return '&hellip;'; } );

/* ---------------------------------------------------------------------
 * 5. Flush rewrite rules on activation/deactivation (needed for /sitemap.xml)
 * ------------------------------------------------------------------- */
function blogpro_activate() {
	blogpro_register_sitemap_rewrite();
	flush_rewrite_rules();
	blogpro_write_htaccess_rules();
}
add_action( 'after_switch_theme', 'blogpro_activate' );

function blogpro_deactivate() {
	flush_rewrite_rules();
	blogpro_remove_htaccess_rules();
}
add_action( 'switch_theme', 'blogpro_deactivate' );

// Disable block-based widget editor
add_filter( 'use_widgets_block_editor', '__return_false' );
