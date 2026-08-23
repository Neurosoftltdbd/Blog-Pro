<?php
/**
 * Server-side render for the blog-pro/faq block.
 *
 * Attributes: title, items (array of {question, answer}), openFirst.
 * Styled with Tailwind utilities — classes are picked up by the theme's
 * PHP source scan in input.css.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$title     = isset( $attributes['title'] ) ? $attributes['title'] : __( 'Frequently Asked Questions', 'blog-pro' );
$items     = isset( $attributes['items'] ) && is_array( $attributes['items'] ) ? $attributes['items'] : array();
$openFirst = ! empty( $attributes['openFirst'] );

if ( ! $items ) {
	return; // nothing to show — no markup
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'bp-faq' ) );

// FAQPage JSON-LD (inline; Google accepts ld+json in the body). Useful for
// GEO/AI answer engines even where FAQ rich results are restricted.
$faq_schema_items = array();
foreach ( $items as $item ) {
	if ( isset( $item['question'], $item['answer'] ) && '' !== $item['question'] && '' !== $item['answer'] ) {
		$faq_schema_items[] = array(
			'@type'          => 'Question',
			'name'           => wp_strip_all_tags( $item['question'] ),
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => wp_strip_all_tags( $item['answer'] ),
			),
		);
	}
}
?>
<?php if ( $faq_schema_items ) : ?>
<script type="application/ld+json"><?php echo wp_json_encode( array(
	'@context'   => 'https://schema.org',
	'@type'      => 'FAQPage',
	'mainEntity' => $faq_schema_items,
), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
<?php endif; ?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_block_wrapper_attributes. ?>>
	<?php if ( $title ) : ?>
		<h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-8"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>
	<div class="space-y-4">
		<?php foreach ( $items as $index => $item ) :
			$question = isset( $item['question'] ) ? $item['question'] : '';
			$answer   = isset( $item['answer'] ) ? $item['answer'] : '';
			if ( '' === $question || '' === $answer ) {
				continue;
			}
		?>
			<details class="group border border-gray-200 rounded-xl open:border-indigo-200 open:bg-indigo-50/30 transition-colors" <?php echo $openFirst && 0 === $index ? 'open' : ''; ?>>
				<summary class="flex items-center justify-between gap-4 p-4 md:p-5 cursor-pointer list-none [&::-webkit-details-marker]:hidden select-none">
					<span class="font-semibold text-gray-900 text-base md:text-lg leading-snug"><?php echo esc_html( $question ); ?></span>
					<span class="shrink-0 w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center transition-transform duration-300 group-open:rotate-45" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="w-4 h-4"><path d="M12 5v14M5 12h14"/></svg>
					</span>
				</summary>
				<div class="px-4 md:px-5 pb-5 text-gray-600 leading-relaxed"><?php echo nl2br( esc_html( $answer ) ); ?></div>
			</details>
		<?php endforeach; ?>
	</div>
</section>
