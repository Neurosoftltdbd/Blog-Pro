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

$wrapper = get_block_wrapper_attributes( array( 'class' => 'bp-faq not-prose' ) );

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

// Allowed HTML for answer content (safe inline HTML only).
$allowed_answer_html = array(
	'a'      => array( 'href' => true, 'title' => true, 'target' => true, 'rel' => true ),
	'b'      => array(),
	'strong' => array(),
	'i'      => array(),
	'em'     => array(),
	'u'      => array(),
	'br'     => array(),
	'code'   => array(),
	'mark'   => array(),
	'span'   => array( 'class' => true ),
);

// Unique block ID for aria-labelledby / anchor links.
$block_id = 'bp-faq-' . absint( wp_rand( 1000, 9999 ) );
?>


<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">

		<?php if ( $title ) : ?>
		<div class="flex items-center gap-3 px-5 py-4 bg-slate-50 border-b border-slate-200">
			<span class="shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-600 text-white" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
			</span>
			<h2 id="<?php echo esc_attr( $block_id ); ?>" class="flex-1 text-lg font-bold text-slate-900 leading-snug">
				<?php echo esc_html( $title ); ?>
			</h2>
			<span class="shrink-0 text-xs font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-full px-2.5 py-0.5">
				<?php echo count( $items ); ?>
			</span>
		</div>
		<?php endif; ?>

		<div class="divide-y divide-slate-100">
			<?php foreach ( $items as $index => $item ) :
				$question    = isset( $item['question'] ) ? $item['question'] : '';
				$answer      = isset( $item['answer'] ) ? $item['answer'] : '';
				if ( '' === $question || '' === $answer ) {
					continue;
				}
				$item_id     = esc_attr( $block_id . '-item-' . $index );
				$safe_answer = wp_kses( $answer, $allowed_answer_html );
			?>
			<details
				id="<?php echo $item_id; ?>"
				class="bp-faq group"
				<?php echo $openFirst && 0 === $index ? 'open' : ''; ?>
			>
				<summary class="flex items-center gap-3 w-full px-5 py-4 cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden hover:bg-slate-50 group-open:bg-indigo-50/50 transition-colors duration-150">
					<span class="shrink-0 w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[11px] font-black group-open:bg-indigo-600 group-open:text-white transition-colors duration-200" aria-hidden="true"><?php echo $index + 1; ?></span>
					<span class="flex-1 text-sm font-semibold text-slate-800 group-open:text-indigo-700 leading-snug text-left"><?php echo esc_html( $question ); ?></span>
					<svg class="shrink-0 w-4.5 h-4.5 text-slate-400 group-open:text-indigo-500 transition-transform duration-200 group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
				</summary>
				<div class="px-5 pt-0 pb-4 pl-14 pr-5 text-sm text-slate-600 leading-relaxed">
					<?php echo $safe_answer ? wpautop( $safe_answer ) : nl2br( esc_html( $answer ) ); // phpcs:ignore ?>
				</div>
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

