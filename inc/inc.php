<?php
require BLOGPRO_DIR . '/inc/performance.php';   // speed optimizations
require BLOGPRO_DIR . '/inc/seo-meta.php';       // dynamic meta tags
require BLOGPRO_DIR . '/inc/seo-metabox.php';    // per-post SEO title/description metabox
require BLOGPRO_DIR . '/inc/schema.php';         // JSON-LD structured data
require BLOGPRO_DIR . '/inc/sitemap.php';        // XML sitemap
require BLOGPRO_DIR . '/inc/pwa.php';            // Progressive Web App
require BLOGPRO_DIR . '/inc/robots.php';         // robots.txt
require BLOGPRO_DIR . '/inc/verification.php';   // webmaster site verification tags
require BLOGPRO_DIR . '/inc/users-info.php';     // custom user profile photo
require BLOGPRO_DIR . '/inc/rest-api.php';       // custom REST endpoints
require BLOGPRO_DIR . '/inc/media-optimize.php'; // image/video optimization
require BLOGPRO_DIR . '/inc/force-wp-to-webp.php'; // serve WebP masters through core image APIs
require BLOGPRO_DIR . '/inc/template-tags.php';  // helper functions for templates
require BLOGPRO_DIR . '/inc/social-share.php';   // social share networks + settings
require BLOGPRO_DIR . '/inc/contact-form.php';   // no-plugin contact form handler
require BLOGPRO_DIR . '/inc/htaccess.php';       // writes caching rules into .htaccess on activation
require BLOGPRO_DIR . '/inc/video-optimisation.php'; // video playback robustness + VideoObject schema
require BLOGPRO_DIR . '/inc/admin-tools.php';    // bulk-optimize images uploaded before theme activation
require BLOGPRO_DIR . '/inc/llms.php';    // serve dynamic llms.txt
require BLOGPRO_DIR . '/inc/web-mcp-schema.php'; // WebMCP tool schemas
require BLOGPRO_DIR . '/inc/class-blogpro-nav-walker.php'; // Dropdown menu walker
require BLOGPRO_DIR . '/inc/widgets.php';
require BLOGPRO_DIR . '/widgets/class-blogpro-widgets-loader.php'; // custom widgets
require BLOGPRO_DIR . '/inc/templates-loader.php'; // Custom Template Loader
require BLOGPRO_DIR . '/inc/internal-linking.php'; // auto internal linking
require BLOGPRO_DIR . '/blocks/class-blogpro-block.php'; // FAQ accordion component
require BLOGPRO_DIR . '/inc/toc.php'; // automatic table of contents (sidebar + mobile)
require BLOGPRO_DIR . '/inc/content-blocks.php'; // Key Takeaways + Sources metaboxes, table wrappers
require BLOGPRO_DIR . '/inc/entity-links.php'; // social profile URLs (sameAs) + author identity fields
require BLOGPRO_DIR . '/inc/faq-optimisation.php'; // avoids duplicate FAQ output
require BLOGPRO_DIR . '/admin/seo-checker.php';    // automated on-page SEO auditor (Tools → SEO Checker)
require BLOGPRO_DIR . '/admin/class-blogpro-admin-menu.php'; // branded dashboard sidebar


// WooCommerce integration if active
if ( class_exists( 'WooCommerce' ) ) {
    require BLOGPRO_DIR . '/woocommerce/woocommerce-support.php';
}

// Elementor Optimisation
if( class_exists( 'Elementor\Plugin' ) ) {
    require BLOGPRO_DIR . '/inc/elementor-optimisation.php';
}

