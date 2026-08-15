<?php
/**
 * Breadcrumb Template
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$breadcrumb = wc_get_breadcrumb();

if ( ! $breadcrumb ) return;
?>

<nav class="woocommerce-breadcrumb mb-6" aria-label="<?php esc_attr_e( 'Breadcrumb', 'blog-pro' ); ?>">
    <ol class="flex items-center flex-wrap gap-2 text-sm text-gray-500">
        <?php foreach ( $breadcrumb as $key => $crumb ) : ?>
            <li class="flex items-center">
                <?php if ( $crumb[1] && $key !== array_key_last( $breadcrumb ) ) : ?>
                    <a href="<?php echo esc_url( $crumb[1] ); ?>" class="text-indigo-600 hover:text-indigo-800 font-medium"><?php echo esc_html( $crumb[0] ); ?></a>
                <?php else : ?>
                    <span class="text-gray-700 font-medium"><?php echo esc_html( $crumb[0] ); ?></span>
                <?php endif; ?>

                <?php if ( $key !== array_key_last( $breadcrumb ) ) : ?>
                    <span class="mx-2 text-gray-300">/</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>