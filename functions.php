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

define( 'BLOGPRO_VERSION', '1.1.0' );
define( 'BLOGPRO_DIR', get_template_directory() );
define( 'BLOGPRO_URI', get_template_directory_uri() );

/* Remove default canonical tag */
add_action('init', function() {
    remove_action('wp_head', 'rel_canonical');
});

add_filter('wp_revisions_to_keep', '__return_false', 10, 2);

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
require BLOGPRO_DIR . '/inc/inc.php';

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
    // Both style.css and tailwind.css are inlined directly into <head>
    // (see blogpro_inline_styles below) to eliminate render-blocking CSS network requests.

    wp_enqueue_script( 'blogpro-main', BLOGPRO_URI . '/assets/js/main.js', array(), BLOGPRO_VERSION, true );
    wp_script_add_data( 'blogpro-main', 'defer', true );

    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
}
add_action( 'wp_enqueue_scripts', 'blogpro_assets' );

/* Inline style.css (~1 KiB) & tailwind.css (~17 KiB compressed) directly into <head>.
 * This completely eliminates render-blocking CSS network requests with ZERO layout shift. */
function blogpro_inline_styles() {
    $style_file = BLOGPRO_DIR . '/style.css';
    if ( file_exists( $style_file ) ) {
        $css = file_get_contents( $style_file );
        // Strip the file-header comment (not needed in inline output).
        $css = preg_replace( '/\/\*[\s\S]*?\*\//', '', $css, 1 );
        $css = trim( $css );
        if ( $css ) {
            echo '<style id="blogpro-style-inline">' . $css . "</style>\n";
        }
    }

    $tw_file = BLOGPRO_DIR . '/assets/css/tailwind.css';
    if ( file_exists( $tw_file ) ) {
        $tw_css = file_get_contents( $tw_file );
        if ( $tw_css ) {
            echo '<style id="blogpro-tailwind-inline">' . $tw_css . "</style>\n";
        }
    }
}
add_action( 'wp_head', 'blogpro_inline_styles', 7 );


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

/* ---------------------------------------------------------------------
 * 6. Combined output buffer to replace multiple redundant buffers
 * ------------------------------------------------------------------- */
function blogpro_combined_output_buffer( $buffer ) {
    // 1. Responsive image rewrites (media‑optimize)
    if ( function_exists( 'blogpro_responsive_buffer_images' ) ) {
        $buffer = blogpro_responsive_buffer_images( $buffer );
    }
    // 2. Minify HTML (templates‑optimisation)
    if ( function_exists( 'blogpro_minify_html_output_buffer_callback' ) ) {
        $buffer = blogpro_minify_html_output_buffer_callback( $buffer );
    }
    // 3. Fix attributes (seo‑meta)
    if ( function_exists( 'blogpro_fix_buffer_attributes' ) ) {
        $buffer = blogpro_fix_buffer_attributes( $buffer );
    }
    return $buffer;
}

function blogpro_start_combined_buffer() {
    // Start the single combined output buffer on template_redirect (frontend only)
    if ( ! is_admin() && ! is_user_logged_in() ) {
        ob_start( 'blogpro_combined_output_buffer' );
    }
}
add_action( 'template_redirect', 'blogpro_start_combined_buffer' );

/* ---------------------------------------------------------------------
 * 7. Unified the_content pipeline (replaces many separate filters)
 * ------------------------------------------------------------------- */
function blogpro_unified_content_pipeline( $content ) {
    // 1. Responsive images (media‑optimize)
    if ( function_exists( 'blogpro_responsive_content_images' ) ) {
        $content = blogpro_responsive_content_images( $content );
    }
    // 2. Picture wrapper (media‑optimize)
    if ( function_exists( 'blogpro_maybe_use_picture' ) ) {
        $content = blogpro_maybe_use_picture( $content );
    }
    // 3. Lazy load for images/iframes (media‑optimize)
    if ( function_exists( 'blogpro_lazy_load_media' ) ) {
        $content = blogpro_lazy_load_media( $content );
    }
    // 4. Internal linking (internal‑linking)
    if ( function_exists( 'blogpro_internal_links' ) ) {
        $content = blogpro_internal_links( $content );
    }
    // 5. Video tidy (video‑optimisation)
    if ( function_exists( 'blogpro_tidy_videos' ) ) {
        $content = blogpro_tidy_videos( $content );
    }
    // 6. Lazy iframes (video‑optimisation)
    if ( function_exists( 'blogpro_lazy_iframes' ) ) {
        $content = blogpro_lazy_iframes( $content );
    }
    return $content;
}
// Priority 10 is early; we keep a medium priority to ensure other core plugins can run before/after as needed.
add_filter( 'the_content', 'blogpro_unified_content_pipeline', 15 );

?>