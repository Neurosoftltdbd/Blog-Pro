<?php
// Standalone repro of blogpro_fix_buffer_attributes' <a|button> pass
// (WP functions stubbed).
function __( $t, $d = null ) { return $t; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function blogpro_filename_to_label( $f ) { return $f; }

$nav = '<nav class="bp-pg-nav py-12" aria-label="Posts pagination"><span class="bp-pg-status" aria-hidden="true">Page 1 of 2</span><span class="bp-pg bp-pg--num bp-pg--current" aria-current="page" aria-label="Page 1">1</span><a class="bp-pg bp-pg--num" href="https://localhost/wordpress/category/tally-prime/page/2/" aria-label="Page 2">2</a><a class="bp-pg bp-pg--adjacent bp-pg--next" href="https://localhost/wordpress/category/tally-prime/page/2/" aria-label="Older posts">Older</a></nav>';

$buffer = $nav;
$buffer = preg_replace_callback(
	'/<(a|button)\b[^>]*>(.*?)<\/(a|button)>/is',
	function ( $matches ) {
		$el  = $matches[0];
		$tag = strtolower( $matches[1] );
		if ( preg_match( '/\b(?:aria-label|aria-labelledby)=/i', $el ) ) {
			return $el;
		}
		$text = trim( wp_strip_all_tags( $matches[2] ) );
		if ( '' !== $text ) {
			return $el;
		}
		$url = '';
		if ( preg_match( '/\b(?:href|src)=(["\'])(.*?)\1/i', $el, $m ) ) { $url = $m[2]; }
		$label = $url ? blogpro_filename_to_label( basename( parse_url( $url, PHP_URL_PATH ) ) ) : 'Link';
		$el = preg_replace( '/<' . $tag . '/i', '<' . $tag . ' aria-label="' . esc_attr( $label ) . '"', $el, 1 );
		return $el;
	},
	$buffer
);
echo "SAME: " . ( $nav === $buffer ? "yes" : "NO" ) . "\n";
if ( $nav !== $buffer ) { echo $buffer . "\n"; }
