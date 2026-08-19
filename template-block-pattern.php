<?php
/**
 * Template Name: Block Pattern Loader
 * Description: A generic template to load an HTML block pattern. It checks for a 'template_file' custom field first, then falls back to the page slug.
 */

// Include the theme header
get_header();

// Get the custom field value for the template file
$template_filename = get_post_meta( get_the_ID(), 'template_file', true );

// If the custom field is empty, fall back to the page slug
if ( empty( $template_filename ) ) {
    $template_filename = get_post_field( 'post_name', get_the_ID() ) . '.html';
}

// Construct the path to the HTML template file
$template_path = get_template_directory() . '/templates/' . $template_filename;

if ( file_exists( $template_path ) ) {
    // Read the file content
    $pattern_content = file_get_contents( $template_path );

    // Render the block pattern content
    echo do_blocks( $pattern_content );

} else {
    // Fallback content if the template file doesn't exist
    echo '<div class="container mx-auto my-8 px-4">';
    echo '<h2>Template Not Found</h2>';
    echo '<p>The template file <code>' . esc_html( $template_filename ) . '</code> was not found in the <code>/templates/</code> directory.</p>';
    echo '</div>';
}

// Include the theme footer
get_footer();
