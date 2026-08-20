<?php
/**
 * Template Loader
 *
 * Registers all .php and .html files from /templates/ as selectable
 * WordPress page templates. When a page uses one, it renders the file
 * wrapped in get_header() / get_footer() — regardless of file type.
 *
 * How to use:
 *   require_once get_template_directory() . '/template-loader.php';
 *   (add that line inside functions.php)
 *
 * @package BlogPro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/* =============================================================
 * 1. AUTO-REGISTER ALL TEMPLATES FROM /templates/ FOLDER
 *    Scans for *.php and *.html files and exposes them in the
 *    "Page Attributes > Template" dropdown in wp-admin.
 * ============================================================= */

/**
 * Scan /templates/ and return an array of
 *   [ 'templates/file.ext' => 'Human Readable Name' ]
 */
function blogpro_scan_templates() {
    $dir   = get_template_directory() . '/templates/';
    $found = [];

    if ( ! is_dir( $dir ) ) {
        return $found;
    }

    // Match both .php and .html files
    $files = glob( $dir . '*.{php,html}', GLOB_BRACE );

    if ( empty( $files ) ) {
        return $found;
    }

    foreach ( $files as $file ) {
        $filename  = basename( $file );                      // e.g. services.html
        $rel_path  = 'templates/' . $filename;              // stored in post-meta

        // Generate a human name: "services.html" → "Services"
        $name = ucwords( str_replace( [ '-', '_' ], ' ', pathinfo( $filename, PATHINFO_FILENAME ) ) );

        // For .php files WordPress can read the "Template Name:" header comment.
        // Pull it so user-defined names are respected.
        // if ( pathinfo( $filename, PATHINFO_EXTENSION ) === 'php' ) {
        //     $headers = get_file_data( $file, [ 'TemplateName' => 'Template Name' ] );
        //     if ( ! empty( $headers['TemplateName'] ) ) {
        //         $name = $headers['TemplateName'];
        //     }
        // }

        $found[ $rel_path ] = $name;
    }

    return $found;
}

/**
 * Inject our scanned templates into the WP theme template list.
 * Fires for both 'page' post type and any CPT that supports page-attributes.
 */
add_filter( 'theme_page_templates', 'blogpro_register_templates', 10, 3 );


function blogpro_register_templates( $templates, $theme, $post ) {
    return array_merge( $templates, blogpro_scan_templates() );
}
add_filter( 'template_include', 'blogpro_template_include', 99 );


function blogpro_template_include( $original_template ) {

    // Only act on pages/singular that have a custom template selected
    if ( ! is_singular() ) {
        return $original_template;
    }

    $selected = get_page_template_slug(); // e.g. "templates/services.html"

    if ( empty( $selected ) ) {
        return $original_template;
    }

    $abs_path = get_template_directory() . '/' . $selected;

    if ( ! file_exists( $abs_path ) ) {
        return $original_template; // let WP handle the 404
    }

    $ext = strtolower( pathinfo( $abs_path, PATHINFO_EXTENSION ) );

    // --- .php templates -------------------------------------------
    // WordPress can include these natively; they just need to call
    // get_header()/get_footer() themselves.
    // If the file has those calls, return it as-is.
    // If it doesn't (legacy file), wrap it.
    if ( $ext === 'php' ) {
        $source = file_get_contents( $abs_path );
        if ( strpos( $source, 'get_header' ) !== false ) {
            // File manages its own header/footer — let WP include it normally
            return $abs_path;
        }
        // Legacy .php with no header/footer calls → wrap it
        blogpro_render_wrapped( $abs_path, 'php' );
        exit;
    }

    // --- .html templates ------------------------------------------
    if ( $ext === 'html' ) {
        blogpro_render_wrapped( $abs_path, 'html' );
        exit;
    }

    return $original_template;
}


/* =============================================================
 * 3. RENDER WRAPPER
 *    Outputs get_header(), then the template body content,
 *    then get_footer(). Works for both .php and .html files.
 * ============================================================= */

/**
 * @param string $abs_path  Absolute path to the template file.
 * @param string $type      'php' or 'html'
 */
function blogpro_render_wrapped( $abs_path, $type ) {

    get_header();

    if ( $type === 'php' ) {
        // Execute the PHP file in the current scope
        include $abs_path;

    } else {
        // For HTML: strip everything outside <body> so we don't
        // output a second <html>/<head>/<body> wrapper.
        $raw = file_get_contents( $abs_path );
        echo blogpro_extract_body( $raw );
    }

    get_footer();
}


/* =============================================================
 * 4. HTML BODY EXTRACTOR
 *    Pulls only the content between <body> and </body>.
 *    Falls back to the full file if no <body> tag found
 *    (e.g. the file is already a partial).
 * ============================================================= */

function blogpro_extract_body( $html ) {

    // Try to grab everything between <body ...> and </body>
    if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $html, $matches ) ) {
        return $matches[1];
    }

    // No <body> tag — treat the whole file as a body partial
    return $html;
}


/* =============================================================
 * 5. OPTIONAL: SUPPORT CUSTOM POST TYPES
 *    If you have CPTs with page-attributes support and want
 *    the same template picker, add them here.
 * ============================================================= */

add_filter( 'theme_post_templates', 'blogpro_register_templates', 10, 3 );

// To add page-attributes support to a CPT, use:
// add_post_type_support( 'your_cpt', 'page-attributes' );