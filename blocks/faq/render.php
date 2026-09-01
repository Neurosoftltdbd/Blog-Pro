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

// Deduplicate by question (case-insensitive, first occurrence wins) so a
// pasted/merged block can never render the same question twice.
$seen = array();
foreach ( $items as $item_index => $item ) {
	if ( ! isset( $item['question'] ) ) {
		continue;
	}
	$key = mb_strtolower( trim( wp_strip_all_tags( (string) $item['question'] ) ) );
	if ( '' === $key || isset( $seen[ $key ] ) ) {
		unset( $items[ $item_index ] );
	} else {
		$seen[ $key ] = true;
	}
}
$items = array_values( $items );

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

<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_block_wrapper_attributes. ?>>
	<div class="bg-white border border-gray-100 rounded-3xl py-4 px-6 shadow-sm hover:shadow-md transition-shadow duration-300">
		<?php if ( $title ) : ?>
			<div class="flex items-center gap-4 pb-2 border-b-2 border-gray-300">
				<span class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4.5 h-4.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
				</span>
				<h2 id="bp-faq" class="text-2xl md:text-3xl font-bold text-gray-900 my-1 py-1"><?php echo esc_html( $title ); ?></h2>
			</div>
		<?php endif; ?>
		<div class="divide-y divide-gray-100 pt-4">
			<?php foreach ( $items as $index => $item ) :
				$question = isset( $item['question'] ) ? $item['question'] : '';
				$answer   = isset( $item['answer'] ) ? $item['answer'] : '';
				if ( '' === $question || '' === $answer ) {
					continue;
				}
			?>
				<details class="group py-1 transition-all" <?php echo $openFirst && 0 === $index ? 'open' : ''; ?>>
					<summary class="flex items-center justify-between gap-4 py-4 cursor-pointer list-none [&::-webkit-details-marker]:hidden select-none group-open:pb-3 transition-all">
						<h3 class="font-bold text-gray-900 text-base md:text-lg leading-snug flex-1"><?php echo esc_html( $question ); ?></h3>
						<span class="shrink-0 w-7 h-7 rounded-full bg-indigo-50 text-indigo-500 flex items-center justify-center transition-all duration-300 group-open:bg-indigo-600 group-open:text-white" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="w-3.5 h-3.5 transition-transform duration-300 group-open:rotate-45"><path d="M12 5v14M5 12h14"/></svg>
						</span>
					</summary>
					<div class="pl-4 pr-4 md:pr-14 pb-4 text-gray-600 leading-relaxed border-l-2 border-indigo-300 ml-2"><?php echo nl2br( esc_html( $answer ) ); ?></div>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
	<?php if ( $faq_schema_items ) : ?>
<script type="application/ld+json"><?php echo wp_json_encode( array(
	'@context'   => 'https://schema.org',
	'@type'      => 'FAQPage',
	'mainEntity' => $faq_schema_items,
), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
<?php endif; ?>
</section>
