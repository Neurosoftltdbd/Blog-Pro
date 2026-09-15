<?php
/**
 * Server-side render for the blog-pro/howto block.
 *
 * Numbered steps + inline HowTo JSON-LD (same body-ld+json approach the
 * FAQ block uses — valid for search and parseable by AI engines).
 *
 * Attributes: title (string), totalTime (ISO-8601 duration, optional,
 * e.g. PT15M), steps (array of {name, text}).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'blogpro_howto_human_duration' ) ) {
	/**
	 * ISO-8601 duration → readable ("PT1H30M" → "1 hr 30 min").
	 *
	 * @param string $iso
	 * @return string
	 */
	function blogpro_howto_human_duration( $iso ) {
		if ( ! preg_match( '/^P(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/', (string) $iso, $m ) ) {
			return '';
		}
		$parts = array();
		if ( ! empty( $m[1] ) ) $parts[] = $m[1] . ' hr';
		if ( ! empty( $m[2] ) ) $parts[] = $m[2] . ' min';
		if ( ! empty( $m[3] ) ) $parts[] = $m[3] . ' sec';
		return $parts ? implode( ' ', $parts ) : '';
	}
}

$title     = isset( $attributes['title'] ) ? trim( (string) $attributes['title'] ) : '';
$total     = isset( $attributes['totalTime'] ) ? trim( (string) $attributes['totalTime'] ) : '';
$steps     = isset( $attributes['steps'] ) && is_array( $attributes['steps'] ) ? $attributes['steps'] : array();
$steps     = array_values( array_filter( $steps, function ( $s ) {
	return is_array( $s ) && '' !== trim( (string) ( isset( $s['name'] ) ? $s['name'] : '' ) );
} ) );

if ( ! $steps ) {
	return; // nothing to show — no markup
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'bp-howto' ) );

// Valid totalTime must be a clock duration (PT…); date-based durations
// (P1D) render awkwardly, so they're treated as absent.
$total_valid = ( $total && preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $total, $tm ) && ( ! empty( $tm[1] ) || ! empty( $tm[2] ) || ! empty( $tm[3] ) ) ) ? $total : '';
$human_total = $total_valid ? blogpro_howto_human_duration( $total_valid ) : '';

$schema_steps = array();
foreach ( $steps as $step ) {
	$entry = array(
		'@type' => 'HowToStep',
		'name'  => wp_strip_all_tags( $step['name'] ),
		'text'  => wp_strip_all_tags( isset( $step['text'] ) ? $step['text'] : $step['name'] ),
	);
	$schema_steps[] = $entry;
}

$schema = array(
	'@context' => 'https://schema.org',
	'@type'    => 'HowTo',
	'name'     => $title ? wp_strip_all_tags( $title ) : wp_strip_all_tags( get_the_title() ),
	'step'     => $schema_steps,
);
if ( $total_valid ) {
	$schema['totalTime'] = $total_valid;
}
?>

<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_block_wrapper_attributes. ?>>
	<div class="bg-white border border-gray-100 rounded-3xl py-4 px-6 shadow-sm hover:shadow-md transition-shadow duration-300">
		<?php if ( $title ) : ?>
			<div class="flex items-center gap-4 pb-2 border-b-2 border-gray-300 flex-wrap">
				<span class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4.5 h-4.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
				</span>
				<h2 id="bp-howto" class="text-2xl md:text-3xl font-bold text-gray-900 my-1 py-1 flex-1"><?php echo esc_html( $title ); ?></h2>
				<?php if ( $total_valid ) : ?>
					<span class="text-sm font-semibold text-gray-600 bg-gray-100 px-3 py-1.5 rounded-full whitespace-nowrap">&#9201; <?php echo esc_html( blogpro_howto_human_duration( $total_valid ) ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<ol class="mt-4 mb-2 space-y-6">
			<?php foreach ( $steps as $i => $step ) : ?>
				<li class="flex gap-4 list-none">
					<span class="shrink-0 w-9 h-9 rounded-full bg-indigo-50 text-indigo-700 font-bold flex items-center justify-center ring-2 ring-indigo-100" aria-hidden="true"><?php echo (int) ( $i + 1 ); ?></span>
					<div class="min-w-0 pt-1">
						<h3 class="font-bold text-gray-900 text-base md:text-lg leading-snug"><?php echo esc_html( $step['name'] ); ?></h3>
						<?php if ( '' !== trim( (string) ( isset( $step['text'] ) ? $step['text'] : '' ) ) && trim( (string) $step['text'] ) !== trim( (string) $step['name'] ) ) : ?>
							<div class="mt-1.5 text-gray-600 leading-relaxed text-sm md:text-base"><?php echo wp_kses_post( wpautop( $step['text'] ) ); ?></div>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
	<?php if ( count( $schema_steps ) >= 2 ) : ?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
	<?php endif; ?>
</section>
