<?php
/**
 * Server-side render for the blog-pro/toc block.
 *
 * Reads the current post's H2/H3 headings via blogpro_toc_headings()
 * (same cache the the_content annotation uses, so anchors always match).
 * Robust design: card with dotted progress tracker, numbered levels.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$title = isset( $attributes['title'] ) ? $attributes['title'] : __( 'Table of Contents', 'blog-pro' );
$heads = blogpro_toc_headings();

if ( ! $heads ) {
	return;
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'bp-toc' ) );
?>
<nav <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_block_wrapper_attributes. ?> aria-label="<?php esc_attr_e( 'Table of contents', 'blog-pro' ); ?>">
	<div class="bg-linear-to-br from-white to-indigo-50/50 border border-indigo-100 rounded-2xl p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-300">
		<div class="flex items-center justify-between mb-4">
			<?php if ( $title ) : ?>
				<h2 id="bp-toc" class="text-lg md:text-xl font-bold text-gray-900 flex items-center gap-2.5">
					<span class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4.5 h-4.5"><path d="M4 6h16M4 12h10M4 18h7"/></svg>
					</span>
					<?php echo esc_html( $title ); ?>
				</h2>
			<?php endif; ?>
			<span class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full"><?php echo count( $heads ); ?> <?php esc_html_e( 'sections', 'blog-pro' ); ?></span>
		</div>

		<ol class="relative space-y-1">
			<?php foreach ( $heads as $i => $head ) : ?>
				<li class="<?php echo 3 === $head['level'] ? 'pl-5 md:pl-7' : ''; ?>">
					<a href="#<?php echo esc_attr( $head['id'] ); ?>" class="bp-toc__link group flex items-start gap-3 py-1.5 px-2 -mx-2 rounded-lg transition-colors duration-200 hover:bg-indigo-50 no-underline">
						<span class="mt-0.5 shrink-0 w-5 h-5 rounded-md bg-indigo-100 text-indigo-700 text-[11px] font-bold flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-200" aria-hidden="true"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
						<span class="text-sm md:text-[15px] font-medium leading-snug group-hover:text-indigo-700 transition-colors duration-200"><?php echo esc_html( $head['text'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</nav>
