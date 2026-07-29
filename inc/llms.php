<?php
// inc/llms.php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Rewrite rule for /llms.txt → ?blogpro_llm=1
function blogpro_add_llm_rewrite() {
    add_rewrite_rule( '^llms\.txt$', 'index.php?blogpro_llm=1', 'top' );
}
add_action( 'init', 'blogpro_add_llm_rewrite' );

// Register query var
function blogpro_llm_query_vars( $vars ) {
    $vars[] = 'blogpro_llm';
    return $vars;
}
add_filter( 'query_vars', 'blogpro_llm_query_vars' );

// Serve the dynamic markdown
function blogpro_serve_dynamic_llm() {
    if ( get_query_var( 'blogpro_llm' ) !== '1' ) return;

    $latest_posts = get_posts([
        'post_type'      => 'post',
        'posts_per_page' => 50,
        'post_status'    => 'publish',
    ]);

    // ---- Build markdown per llmstxt.org spec ----
    $site_name = get_bloginfo('name');
    $site_desc = get_bloginfo('description');

    $output  = "# {$site_name}\n";
    $output .= "> {$site_desc}\n\n";

    $output .= "## Recent Posts\n";
    foreach ( $latest_posts as $p ) {
        $title   = get_the_title($p);
        $url     = get_permalink($p);
        $excerpt = wp_trim_words($p->post_content, 40);
        $output .= "- [{$title}]({$url}): {$excerpt}\n";
    }
// ---- Featured Pages (high priority) ----
$featured_pages = get_posts([
    'post_type'   => 'page',
    'meta_key'    => 'featured',
    'meta_value'  => '1',
    'posts_per_page' => -1,
    'post_status' => 'publish',
]);
if ( $featured_pages ) {
    $output .= "\n---\n## Featured Pages\n";
    foreach ( $featured_pages as $fp ) {
        $title = get_the_title( $fp );
        $url   = get_permalink( $fp );
        $output .= "- [{$title}]({$url})\n";
    }
}

// ---- Important Documents (high priority) ----
$important_docs = get_posts([
    'post_type'      => 'attachment',
    'post_mime_type' => 'application/pdf',
    'posts_per_page' => -1,
    'post_status'    => 'inherit',
]);
if ( $important_docs ) {
    $output .= "\n---\n## Important Documents\n";
    foreach ( $important_docs as $doc ) {
        $title = $doc->post_title;
        $url   = wp_get_attachment_url( $doc );
        $output .= "- [{$title}]({$url})\n";
    }
}

// ---- Add separator before next sections ----
$output .= "\n---\n";
    // ---- Categories (high priority) ----
    $categories = get_categories([
        'hide_empty' => true,
    ]);
    if ( $categories ) {
        $output .= "\n## Categories\n";
        foreach ( $categories as $cat ) {
            $cat_name = $cat->name;
            $cat_link = get_category_link( $cat );
            $cat_desc = $cat->description;
            $output .= "- [{$cat_name}]({$cat_link})";
            if ( ! empty( $cat_desc ) ) {
                $output .= ": {$cat_desc}";
            }
            $output .= "\n";
        }
    }

    // ---- Optional (low‑priority) Tags ----
    $tags = get_tags([
        'hide_empty' => true,
    ]);
    if ( $tags ) {
        $output .= "\n## Optional\n";
        foreach ( $tags as $tag ) {
            $tag_name = $tag->name;
            $tag_link = get_tag_link( $tag );
            $output .= "- [{$tag_name}]({$tag_link})\n";
        }
    }

    // ---- Site metadata (logo, author) ----
    $output .= "\n## Site Info\n";
    // Logo
    $logo_id = get_theme_mod( 'custom_logo' );
    if ( $logo_id ) {
        $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
        if ( $logo_url ) {
            $output .= "Logo: {$logo_url}\n";
        }
    }
    // Author bio (first author of latest post)
    if ( ! empty( $latest_posts ) ) {
        $author_id = $latest_posts[0]->post_author;
        $author_name = get_the_author_meta( 'display_name', $author_id );
        $author_bio  = get_the_author_meta( 'description', $author_id );
        $output .= "Author: {$author_name}\n";
            if ( $author_bio ) {
        $output .= "Bio: {$author_bio}\n";
    }
    }
    // ---- Contact info ----
    $contact_email = get_option('admin_email');
    $contact_phone = get_theme_mod('contact_phone');
    $output .= "\n---\n## Contact\n";
    if ( $contact_email ) {
        $output .= "Email: {$contact_email}\n";
    }
    if ( $contact_phone ) {
        $output .= "Phone: {$contact_phone}\n";
    }
    header('Content-Type: text/plain; charset=utf-8');
    // header('Cache-Control: public, max-age=300'); // optional caching
    echo $output;
    exit;
}
add_action( 'template_redirect', 'blogpro_serve_dynamic_llm' );
