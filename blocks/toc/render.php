<?php
/**
 * Server-side render for the blog-pro/toc block.
 *
 * Delegates to blogpro_toc_nav() (inc/toc.php) — same markup the
 * automatic sidebar/mobile TOC uses, so the three surfaces never drift.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$title   = isset( $attributes['title'] ) ? $attributes['title'] : __( 'Table of Contents', 'blog-pro' );
$wrapper = get_block_wrapper_attributes( array( 'class' => '' ) );

echo blogpro_toc_nav( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* internally.
	'title' => $title,
	'attrs' => $wrapper,
) );
