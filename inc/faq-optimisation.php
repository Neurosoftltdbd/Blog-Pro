<?php
/**
 * FAQ optimisation — single source of FAQ output per post.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
function blogpro_faq_block_in_content( $post_id ) {
	$content = (string) get_post_field( 'post_content', $post_id );

	// Block formats (case-insensitive)
	if ( preg_match( '/<!--\s*wp:(blog-pro|rank-math|yoast)\/faq/i', $content ) ) {
		return true;
	}

	// Hand-written HTML FAQ wrapper carrying <details> items
	if ( preg_match( blogpro_faq_group_open_regex(), $content ) ) {
		return true;
	}

	// Bare hand-written accordion items (no faq-group wrapper)
	if ( false !== strpos( $content, '<details' ) && false !== strpos( $content, '<summary' ) ) {
		return true;
	}

	return false;
}

// $content = strtolower( (string) get_post_field( 'post_content', $post_id ) );
// 	return false !== strpos( $content, '<!-- wp:blog-pro/faq' ) ||
// 	       false !== strpos( $content, '<!-- wp:rank-math/faq' ) ||
// 	       false !== strpos( $content, '<!-- wp:yoast/faq' ) ||
// 	       false !== strpos( $content, 'faq-group' ) ||
// 	       false !== strpos( $content, 'faq-accordion-group' ) ||
// 	       false !== strpos( $content, 'accordion-group' ) ||
// 	       false !== strpos( $content, 'faq-accordion' ) ||
// 	       false !== strpos( $content, 'faq' );



/**
 * the_content filter — append the metabox FAQ when appropriate.
 *
 * Rules:
 *   - single posts only (not pages, archives, feeds, excerpts)
 *   - only inside the main loop (no Related Posts widgets etc.)
 *   - content has no blog-pro/faq block already
 *   - the metabox has at least one valid item
 *
 * @param string $content
 * @return string
 */
function blogpro_faq_content_filter( $content ) {
	if ( '' === trim( (string) $content ) ) {
		return $content;
	}
	if ( is_admin() || ! is_singular( 'post' ) || is_feed() || ! in_the_loop() ) {
		return $content;
	}

	if ( ! apply_filters( 'blogpro_faq_auto_append', true, get_the_ID() ) ) {
		return $content;
	}

	$post_id   = get_the_ID();
	$items     = blogpro_faq_for_post( $post_id );
	if ( ! $items || blogpro_faq_block_in_content( $post_id ) ) {
		return $content;
	}

	$block = '<!-- wp:blog-pro/faq {"title":"' . esc_attr__( 'Frequently Asked Questions', 'blog-pro' ) . '","items":' . wp_json_encode( $items ) . '} /-->';

	return $content . "\n\n" . do_blocks( $block );
}
add_filter( 'the_content', 'blogpro_faq_content_filter', 20 );

/**
 * Keep the metabox save handler in sync: never auto-append a block when
 * the content already has one. (Used as a shared helper; the save handler
 * in blocks/class-blogpro-block.php applies the same inline check.)
 *
 * @param string $content
 * @return bool
 */
function blogpro_faq_content_has_block( $content ) {
	return false !== strpos( (string) $content, '<!-- wp:blog-pro/faq' );
}

/**
 * Gate the legacy blogpro_faq_block() render helper: false when the post
 * content already has a blog-pro/faq block.
 *
 * @param bool $render
 * @param int  $post_id
 * @return bool
 */
function blogpro_faq_suppress_duplicate( $render, $post_id ) {
	if ( $render && blogpro_faq_block_in_content( $post_id ) ) {
		return false;
	}
	return $render;
}
add_filter( 'blogpro_faq_auto_append', 'blogpro_faq_suppress_duplicate', 10, 2 );

function blogpro_faq_group_open_regex() {
	return '/<div\b[^>]*\bclass\s*=\s*["\'][^"\']*\bfaq-group\b[^"\']*["\'][^>]*>/i';
}

function blogpro_faq_group_spans( $content ) {
	$spans = array();
	if ( ! preg_match_all( blogpro_faq_group_open_regex(), $content, $opens, PREG_OFFSET_CAPTURE ) ) {
		return $spans;
	}

	foreach ( $opens[0] as $open ) {
		$start = (int) $open[1];
		$inner = $start + strlen( $open[0] );
		$rest  = substr( $content, $inner );

		if ( ! preg_match_all( '/<(\/?)div\b[^>]*>/i', $rest, $tags, PREG_OFFSET_CAPTURE ) ) {
			continue;
		}

		$depth  = 1;
		$finish = false;
		foreach ( $tags[0] as $i => $tag ) {
			$is_close = '' !== $tags[1][ $i ][0];
			if ( $is_close ) {
				$depth--;
				if ( 0 === $depth ) {
					$finish = $inner + (int) $tag[1] + strlen( $tag[0] );
					break;
				}
			} elseif ( ! preg_match( '/\/\s*>$/', $tag[0] ) ) {
				$depth++;
			}
		}
		if ( false === $finish ) {
			continue;
		}

		if ( preg_match( '/<!--\s*wp:html\s*-->(\s*)$/i', substr( $content, 0, $start ), $m, PREG_OFFSET_CAPTURE ) ) {
			$start = (int) $m[0][1]; // swallow the wp:html opener too
		}
		if ( preg_match( '/^\s*<!--\s*\/wp:html\s*-->/i', substr( $content, $finish ), $m ) ) {
			$finish += strlen( $m[0] );
		}

		$spans[] = array( $start, $inner, $finish );
	}

	return $spans;
}

function blogpro_faq_extract_group( $content ) {
	$items = array();
	foreach ( blogpro_faq_group_spans( $content ) as $span ) {
		$inner = substr( $content, $span[1], $span[2] - $span[1] );
		if ( ! preg_match_all( '/<details\b[^>]*>(.*?)<\/details>/is', $inner, $details ) ) {
			continue;
		}
		foreach ( $details[1] as $d ) {
			if ( ! preg_match( '/<summary\b[^>]*>(.*?)<\/summary>/is', $d, $qs ) ) {
				continue;
			}
			$q = trim( wp_strip_all_tags( html_entity_decode( $qs[1], ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
			if ( '' === $q ) {
				continue;
			}
			$body = preg_replace( '/<summary\b[^>]*>.*?<\/summary>/is', '', $d );
			$a = trim( wp_strip_all_tags( html_entity_decode( $body, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
			if ( '' === $a ) {
				continue;
			}
			$items[] = array( 'question' => $q, 'answer' => $a );
		}
	}
	return $items;
}

/**
 * Drop duplicate FAQ items (same question, case-insensitive).
 * Order preserved. Handles pasted rendered HTML whose details got
 * duplicated, and rank-math blocks whose questions repeat.
 *
 * @param array $items
 * @return array<int, array{question: string, answer: string}>
 */
function blogpro_faq_dedupe_items( $items ) {
	$seen = array();
	$out  = array();
	foreach ( $items as $it ) {
		$key = isset( $it['question'] ) ? mb_strtolower( trim( $it['question'] ) ) : '';
		if ( '' === $key || isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$out[] = $it;
	}
	return $out;
}

/**
 * Extract FAQ items from bare <details><summary>Q</summary>…</details>
 * pairs anywhere in the content (no faq-group wrapper).
 *
 * @param string $content
 * @return array<int, array{question: string, answer: string}>
 */
function blogpro_faq_extract_details( $content ) {
	$items = array();
	if ( ! preg_match_all( '/<details\b[^>]*>.*?<\/details>/is', $content, $matches ) ) {
		return $items;
	}
	foreach ( $matches[0] as $d ) {
		if ( ! preg_match( '/<summary\b[^>]*>(.*?)<\/summary>/is', $d, $qs ) ) {
			continue;
		}
		$q = trim( wp_strip_all_tags( html_entity_decode( $qs[1], ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
		if ( '' === $q ) {
			continue;
		}
		$body = preg_replace( '/<summary\b[^>]*>.*?<\/summary>/is', '', $d );
		$a = trim( wp_strip_all_tags( html_entity_decode( $body, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
		if ( '' === $a ) {
			continue;
		}
		$items[] = array( 'question' => $q, 'answer' => $a );
	}
	return $items;
}

/**
 * Replace bare <details> items (outside any faq-group wrapper) with the block.
 *
 * The first item's position becomes the block position; the rest are removed.
 * A surrounding wp:html wrapper is swallowed when the details were the only
 * content inside it.
 *
 * @param string $content
 * @param array  $items
 * @return array{0: string, 1: int}
 */
function blogpro_faq_replace_details_with_block( $content, $items ) {
	$block = '<!-- wp:blog-pro/faq {"title":"' . esc_attr__( 'Frequently Asked Questions', 'blog-pro' ) . '","items":' . wp_json_encode( $items ) . '} /-->';

	if ( ! preg_match_all( '/<details\b[^>]*>.*?<\/details>/is', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
		return array( $content, 0 );
	}

	// Skip items already inside a faq-group wrapper — those are handled by
	// blogpro_faq_replace_with_block().
	$group_spans = blogpro_faq_group_spans( $content );
	$targets     = array();
	foreach ( $matches[0] as $m ) {
		$at = (int) $m[1];
		$in_group = false;
		foreach ( $group_spans as $g ) {
			if ( $at >= $g[0] && $at < $g[2] ) {
				$in_group = true;
				break;
			}
		}
		if ( ! $in_group ) {
			$targets[] = array( $at, $at + strlen( $m[0] ) );
		}
	}
	if ( ! $targets ) {
		return array( $content, 0 );
	}

	// Swallow an enclosing wp:html wrapper when the details items are its
	// only content (whitespace between them).
	$first = $targets[0][0];
	$last  = $targets[ count( $targets ) - 1 ][1];
	if ( preg_match( '/<!--\s*wp:html\s*-->\s*$/i', substr( $content, 0, $first ), $m, PREG_OFFSET_CAPTURE )
		&& preg_match( '/^\s*<!--\s*\/wp:html\s*-->/i', substr( $content, $last ), $m2 ) ) {
		$targets[0][0] = (int) $m[0][1];
		$targets[ count( $targets ) - 1 ][1] = $last + strlen( $m2[0] );
	}

	// Replace each item from the end backwards; the block lands at the
	// first item's position. Content between items is preserved.
	foreach ( array_reverse( $targets ) as $i => $t ) {
		$replacement = ( $i === 0 ) ? "\n\n" . $block . "\n\n" : '';
		$content = substr_replace( $content, $replacement, $t[0], $t[1] - $t[0] );
	}

	return array( $content, count( $targets ) );
}

/**
 * Locate every block of a given name in the content.
 *
 * Block-attribute JSON contains nested braces, so a lazy `\{.*?\}` regex
 * truncates it. This scans from each `<!-- wp:name` marker and reads a
 * balanced, string-aware JSON object, then the marker terminator.
 *
 * @param string $content
 * @param string $name      e.g. "rank-math/faq-block"
 * @return array<int, array{0: int, 1: int, attrs: ?array}> start, end, attrs
 */
function blogpro_faq_find_blocks( $content, $name ) {
	$found = array();
	$offset = 0;
	$needle = '<!-- wp:' . $name;
	while ( false !== ( $at = stripos( $content, $needle, $offset ) ) ) {
		$offset = $at + strlen( $needle );
		$rest = substr( $content, $at );
		if ( ! preg_match( '/^<!--\s*wp:' . preg_quote( $name, '/' ) . '\s*/i', $rest, $m ) ) {
			continue; // name is a prefix of a longer block name
		}
		$cursor = strlen( $m[0] );
		$attrs  = null;

		if ( isset( $rest[ $cursor ] ) && '{' === $rest[ $cursor ] ) {
			$depth   = 0;
			$in_str  = false;
			$escaped = false;
			$json    = '';
			$len     = strlen( $rest );
			for ( $i = $cursor; $i < $len; $i++ ) {
				$ch = $rest[ $i ];
				$json .= $ch;
				if ( $in_str ) {
					if ( $escaped ) {
						$escaped = false;
					} elseif ( '\\' === $ch ) {
						$escaped = true;
					} elseif ( '"' === $ch ) {
						$in_str = false;
					}
					continue;
				}
				if ( '"' === $ch ) {
					$in_str = true;
				} elseif ( '{' === $ch ) {
					$depth++;
				} elseif ( '}' === $ch ) {
					$depth--;
					if ( 0 === $depth ) {
						break;
					}
				}
			}
			if ( 0 !== $depth ) {
				continue; // unbalanced — skip
			}
			$attrs  = json_decode( $json, true );
			$cursor += strlen( $json );
		}

		if ( ! preg_match( '/^\s*(\/?-->)/', substr( $rest, $cursor ), $m2 ) ) {
			continue;
		}
		$is_self_closing = ( '/' === substr( $m2[1], 0, 1 ) );
		$end = $at + $cursor + strlen( $m2[0] );

		if ( ! $is_self_closing ) {
			$closer = '<!-- /wp:' . $name;
			$cl = stripos( $content, $closer, $end );
			if ( false === $cl ) {
				continue;
			}
			if ( ! preg_match( '/^<!--\s*\/wp:' . preg_quote( $name, '/' ) . '\s*-->/i', substr( $content, $cl ), $m3 ) ) {
				continue;
			}
			$end = $cl + strlen( $m3[0] );
		}

		$found[] = array( $at, $end, $attrs );
	}
	return $found;
}

/**
 * Repair Rank Math's corrupted unicode escapes in block attrs.
 *
 * Rank Math sometimes stores "<" with the backslash stripped,
 * leaving literal `u003c` (i.e. `<`) inside JSON strings.
 *
 * @param string $s
 * @return string
 */
function blogpro_faq_fix_unicode( $s ) {
	return preg_replace_callback( '/u00([0-9a-fA-F]{2})/', function ( $m ) {
		return html_entity_decode( '&#x' . $m[1] . ';', ENT_QUOTES, 'UTF-8' );
	}, (string) $s );
}

/**
 * Extract FAQ items from `<!-- wp:rank-math/faq-block {"questions":[…]} -->`
 * blocks. Each question has title/content (HTML, possibly corrupted).
 *
 * @param string $content
 * @return array<int, array{question: string, answer: string}>
 */
function blogpro_faq_extract_rankmath( $content ) {
	$items = array();
	foreach ( blogpro_faq_find_blocks( $content, 'rank-math/faq-block' ) as $blk ) {
		$attrs = $blk[2];
		if ( empty( $attrs['questions'] ) || ! is_array( $attrs['questions'] ) ) {
			continue;
		}
		foreach ( $attrs['questions'] as $q ) {
			if ( isset( $q['visible'] ) && false === $q['visible'] ) {
				continue;
			}
			$question = trim( wp_strip_all_tags( blogpro_faq_fix_unicode( isset( $q['title'] ) ? $q['title'] : '' ) ) );
			$answer   = trim( wp_strip_all_tags( blogpro_faq_fix_unicode( isset( $q['content'] ) ? $q['content'] : '' ) ) );
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$items[] = array( 'question' => $question, 'answer' => $answer );
		}
	}
	return $items;
}

/**
 * Merge multiple blog-pro/faq blocks into one (deduped by question).
 *
 * @param string $content
 * @return array{0: string, 1: int} new content, number of blocks removed
 */
function blogpro_faq_consolidate_blocks( $content ) {
	$blocks = blogpro_faq_find_blocks( $content, 'blog-pro/faq' );
	if ( count( $blocks ) < 2 ) {
		return array( $content, 0 );
	}

	$seen   = array();
	$merged = array();
	$attrs  = is_array( $blocks[0][2] ) ? $blocks[0][2] : array();
	foreach ( $blocks as $blk ) {
		$items = ( is_array( $blk[2] ) && ! empty( $blk[2]['items'] ) && is_array( $blk[2]['items'] ) ) ? $blk[2]['items'] : array();
		foreach ( $items as $it ) {
			$key = isset( $it['question'] ) ? mb_strtolower( trim( $it['question'] ) ) : '';
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$merged[] = $it;
		}
	}
	$attrs['items'] = $merged;
	$new_block = '<!-- wp:blog-pro/faq ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' /-->';

	foreach ( array_reverse( $blocks, true ) as $bi => $blk ) {
		$content = substr_replace( $content, 0 === $bi ? "\n\n" . $new_block . "\n\n" : '', $blk[0], $blk[1] - $blk[0] );
	}
	return array( $content, count( $blocks ) - 1 );
}

/**
 * Replace rank-math/faq-block markup with the blog-pro/faq block.
 *
 * When the content already carries a blog-pro/faq block, the new items
 * merge into it (deduped by question) and the rank-math block is removed.
 *
 * @param string $content
 * @param array  $items
 * @return array{0: string, 1: int}
 */
function blogpro_faq_replace_rankmath_with_block( $content, $items ) {
	$rm_blocks = blogpro_faq_find_blocks( $content, 'rank-math/faq-block' );
	if ( ! $rm_blocks ) {
		return array( $content, 0 );
	}
	$first_pos = $rm_blocks[0][0];

	// Remove rank-math blocks from the end backwards.
	foreach ( array_reverse( $rm_blocks ) as $blk ) {
		$content = substr_replace( $content, '', $blk[0], $blk[1] - $blk[0] );
	}

	$block = '<!-- wp:blog-pro/faq {"title":"' . esc_attr__( 'Frequently Asked Questions', 'blog-pro' ) . '","items":' . wp_json_encode( $items ) . '} /-->';

	// Merge into an existing blog-pro/faq block when present.
	$bp_blocks = blogpro_faq_find_blocks( $content, 'blog-pro/faq' );
	if ( $bp_blocks ) {
		$seen   = array();
		$merged = array();
		foreach ( $bp_blocks as $bi => $blk ) {
			$existing = ( is_array( $blk[2] ) && ! empty( $blk[2]['items'] ) && is_array( $blk[2]['items'] ) ) ? $blk[2]['items'] : array();
			foreach ( array_merge( $existing, $items ) as $it ) {
				$key = isset( $it['question'] ) ? mb_strtolower( $it['question'] ) : '';
				if ( '' === $key || isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$merged[] = $it;
			}
		}
		$attrs = is_array( $bp_blocks[0][2] ) ? $bp_blocks[0][2] : array();
		$attrs['items'] = $merged;
		$new_block = '<!-- wp:blog-pro/faq ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' /-->';
		// Replace first block, drop the rest (backwards).
		foreach ( array_reverse( $bp_blocks, true ) as $bi => $blk ) {
			$content = substr_replace( $content, 0 === $bi ? "\n\n" . $new_block . "\n\n" : '', $blk[0], $blk[1] - $blk[0] );
		}
		return array( $content, count( $rm_blocks ) );
	}

	// No existing block — insert at the first rank-math position.
	$insert = min( $first_pos, strlen( $content ) );
	$content = substr_replace( $content, "\n\n" . $block . "\n\n", $insert, 0 );
	return array( $content, count( $rm_blocks ) );
}

function blogpro_faq_replace_with_block( $content, $items ) {
	$block = '<!-- wp:blog-pro/faq {"title":"' . esc_attr__( 'Frequently Asked Questions', 'blog-pro' ) . '","items":' . wp_json_encode( $items ) . '} /-->';

	// Strip a FAQ heading block sitting directly before the faq-group
	// wrapper. Matches h2/h3/h4 with text "Frequently asked questions"
	// / "FAQ" / "faqs".
	$before = '(?=(?:<!--\s*wp:html\s*-->)?\s*<div\b[^>]*\bclass\s*=\s*["\'][^"\']*\bfaq-group\b)';
	$content = preg_replace(
		'/(<!--\s*wp:heading[^>]*-->\s*<h[234][^>]*>\s*(?:Frequently\s*asked\s*questions?|faqs?|FAQ?)\s*<\/h[234]>\s*<!--\s*\/wp:heading\s*-->)\s*' . $before . '/is',
		'',
		$content
	);
	$content = preg_replace(
		'/(<h[234][^>]*>\s*(?:Frequently\s*asked\s*questions?|faqs?|FAQ?)\s*<\/h[234]>\s*<!--\s*\/wp:heading\s*-->)\s*' . $before . '/is',
		'',
		$content
	);

	$spans = blogpro_faq_group_spans( $content );
	$n     = count( $spans );
	if ( ! $n ) {
		return array( $content, 0 );
	}

	// Replace from the end backwards so earlier offsets stay valid.
	// The block lands at the first wrapper; any later duplicates are removed.
	foreach ( array_reverse( $spans ) as $i => $span ) {
		$replacement = ( $i === $n - 1 ) ? $block : '';
		$content = substr_replace( $content, "\n\n" . $replacement . "\n\n", $span[0], $span[2] - $span[0] );
	}

	return array( $content, $n );
}

/**
 * Migrate one post (or report only). Sets `_blogpro_faq_migrated` so the
 * migration can be resumed across batches.
 *
 * @param int  $post_id
 * @param bool $dry_run
 * @return array{ok: bool, action: string, reason: string}
 */
function blogpro_faq_migrate_post( $post_id, $dry_run = true ) {
	$content = (string) get_post_field( 'post_content', $post_id );

	$has_group   = (bool) preg_match( blogpro_faq_group_open_regex(), $content );
	$has_details = ! $has_group && false !== strpos( $content, '<details' );
	$has_rank    = ! $has_group && ! $has_details && (bool) preg_match( '/<!--\s*wp:rank-math\/faq-block/i', $content );

	if ( blogpro_faq_block_in_content( $post_id ) && ! $has_group && ! $has_details && ! $has_rank ) {
		// No legacy source left — still heal duplicate blocks.
		list( $consolidated, $removed ) = blogpro_faq_consolidate_blocks( $content );
		if ( $removed ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => $consolidated ) );
			return array( 'ok' => true, 'action' => 'converted', 'reason' => 'consolidated' );
		}
		return array( 'ok' => false, 'action' => 'skipped', 'reason' => 'already-block' );
	}
	if ( ! $has_group && ! $has_details && ! $has_rank ) {
		return array( 'ok' => false, 'action' => 'skipped', 'reason' => 'no-faq' );
	}

	// Content carries legacy markup — migrate regardless of the marker.
	// The meta is only a resume hint, not a guard: a saved pasted-HTML or
	// re-inserted old block makes it stale.
	delete_post_meta( $post_id, '_blogpro_faq_migrated' );

	if ( $has_group ) {
		$items = blogpro_faq_dedupe_items( blogpro_faq_extract_group( $content ) );
	} elseif ( $has_details ) {
		$items = blogpro_faq_dedupe_items( blogpro_faq_extract_details( $content ) );
	} else {
		$items = blogpro_faq_dedupe_items( blogpro_faq_extract_rankmath( $content ) );
	}
	if ( ! $items ) {
		return array( 'ok' => false, 'action' => 'skipped', 'reason' => 'no-items' );
	}

	if ( $dry_run ) {
		return array( 'ok' => true, 'action' => 'convert', 'reason' => '' );
	}

	if ( $has_group ) {
		list( $new_content, $n ) = blogpro_faq_replace_with_block( $content, $items );
	} elseif ( $has_details ) {
		list( $new_content, $n ) = blogpro_faq_replace_details_with_block( $content, $items );
	} else {
		list( $new_content, $n ) = blogpro_faq_replace_rankmath_with_block( $content, $items );
	}
	if ( ! $n ) {
		return array( 'ok' => false, 'action' => 'skipped', 'reason' => 'replace-failed' );
	}

	wp_update_post( array(
		'ID'           => $post_id,
		'post_content' => $new_content,
	) );
	update_post_meta( $post_id, '_blogpro_faq_migrated', time() );

	return array( 'ok' => true, 'action' => 'converted', 'reason' => '' );
}

/**
 * Candidate ids: posts containing a faq-group wrapper (deterministic,
 * offset pagination, no found_rows).
 *
 * @param int $offset
 * @param int $limit
 * @return array<int>
 */
function blogpro_faq_migrate_candidates( $offset, $limit ) {
	global $wpdb;
	$ids = array();
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_type = 'post' AND post_status = 'publish'
		   AND ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s )
		 ORDER BY ID ASC
		 LIMIT %d OFFSET %d",
		'%faq-group%', '%<details%', '%wp:rank-math/faq-block%', $limit, $offset
	) );
	foreach ( (array) $rows as $r ) {
		$ids[] = (int) $r->ID;
	}
	return $ids;
}

function blogpro_faq_migrate_count() {
	check_ajax_referer( 'blogpro_faq_migrate', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	global $wpdb;
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND ( post_content LIKE '%faq-group%' OR post_content LIKE '%<details%' OR post_content LIKE '%wp:rank-math/faq-block%' )" );
	wp_send_json_success( array( 'total' => $total ) );
}
add_action( 'wp_ajax_blogpro_faq_migrate_count', 'blogpro_faq_migrate_count' );

function blogpro_faq_migrate_batch() {
	check_ajax_referer( 'blogpro_faq_migrate', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	$offset  = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
	$batch   = isset( $_POST['batch'] ) ? max( 1, min( 20, absint( $_POST['batch'] ) ) ) : 5;
	$dry_run = ! empty( $_POST['dry_run'] );

	$ids = blogpro_faq_migrate_candidates( $offset, $batch );

	$results = array(
		'converted' => 0,
		'skipped'   => 0,
		'processed' => count( $ids ),
		'more'      => count( $ids ) === $batch,
	);
	foreach ( $ids as $id ) {
		$r = blogpro_faq_migrate_post( $id, $dry_run );
		if ( 'converted' === $r['action'] ) {
			$results['converted']++;
		} else {
			$results['skipped']++;
		}
	}

	wp_send_json_success( $results );
}
add_action( 'wp_ajax_blogpro_faq_migrate_batch', 'blogpro_faq_migrate_batch' );

function blogpro_faq_migrate_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Blog Pro FAQ Migration', 'blog-pro' ); ?></h1>
		<p><?php esc_html_e( 'Replaces hand-written .faq-group <details> markup in existing posts with the blog-pro/faq block. Runs in small batches (safe for shared hosting), skips already-converted posts, and is safe to re-run.', 'blog-pro' ); ?></p>
		<div id="blogpro-faq-migrate-status" class="notice notice-info"><p><?php esc_html_e( 'Press Start to scan for posts to convert.', 'blog-pro' ); ?></p></div>
		<button id="blogpro-faq-migrate-start" class="button button-primary"><?php esc_html_e( 'Start Migration', 'blog-pro' ); ?></button>
		<button id="blogpro-faq-migrate-dry" class="button"><?php esc_html_e( 'Dry Run', 'blog-pro' ); ?></button>
		<p id="blogpro-faq-migrate-progress" class="description"></p>
	</div>
	<script>
	(function () {
		var status = document.getElementById('blogpro-faq-migrate-status');
		var progress = document.getElementById('blogpro-faq-migrate-progress');
		var start = document.getElementById('blogpro-faq-migrate-start');
		var dry = document.getElementById('blogpro-faq-migrate-dry');
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'blogpro_faq_migrate' ) ); ?>;
		var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var batch = 5, running = false, total = 0, converted = 0, skipped = 0;

		function request( action, data, cb ) {
			var body = new URLSearchParams();
			body.set('action', action);
			body.set('nonce', nonce);
			body.set('batch', batch);
			for ( var k in data ) body.set( k, data[k] );
			fetch( ajax, { method: 'POST', credentials: 'same-origin', body: body } )
				.then(function ( r ) { return r.json(); })
				.then(function ( j ) {
					if ( j && j.success ) cb( j.data );
					else throw new Error( j && j.data ? j.data : 'Request failed' );
				})
				.catch(function ( e ) {
					status.className = 'notice notice-error';
					status.innerHTML = '<p>' + e.message + '</p>';
					running = false;
				});
		}

		function run( dry ) {
			running = true;
			start.disabled = dry.disabled = true;
			request( 'blogpro_faq_migrate_count', {}, function ( data ) {
				total = data.total;
				if ( ! total ) {
					status.className = 'notice notice-success';
					status.innerHTML = '<p><?php esc_html_e( 'No posts with faq-group markup found.', 'blog-pro' ); ?></p>';
					running = false;
					start.disabled = dry.disabled = false;
					return;
				}
				progress.textContent = '0 / ' + total;
				convert( 0, dry );
			} );
		}

		function convert( offset, dry ) {
			request( 'blogpro_faq_migrate_batch', { offset: offset, dry_run: dry ? '1' : '0' }, function ( data ) {
				converted += data.converted;
				skipped += data.skipped;
				progress.textContent = 'Processed ' + ( offset + data.processed ) + ' / ' + total + ' — converted: ' + converted + ', skipped: ' + skipped;
				if ( data.more ) {
					convert( offset + batch, dry );
				} else {
					status.className = 'notice notice-success';
					status.innerHTML = '<p><?php esc_html_e( 'Migration complete.', 'blog-pro' ); ?></p>';
					running = false;
					start.disabled = dry.disabled = false;
				}
			} );
		}

		start.addEventListener( 'click', function () { if ( !running ) run( false ); } );
		dry.addEventListener( 'click', function () { if ( !running ) run( true ); } );
	})();
	</script>
	<?php
}

// Submenu item registered in admin/class-blogpro-admin-menu.php
// (blogpro_admin_menu_brand) so the parent exists at hook time.

/* ---------------------------------------------------------------------
 * Per-post FAQ migration metabox (sidebar, above Internal Linking).
 * One-click migrate for the current post being edited.
 * ------------------------------------------------------------------- */

/**
 * Register the per-post FAQ migration metabox.
 */
function blogpro_faq_single_migrate_metabox() {
	add_meta_box(
		'blogpro-faq-single-migrate',
		__( 'FAQ Migration', 'blog-pro' ),
		'blogpro_faq_single_migrate_render',
		'post',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'blogpro_faq_single_migrate_metabox' );

/**
 * Render the per-post FAQ migration button.
 *
 * @param WP_Post $post
 */
function blogpro_faq_single_migrate_render( $post ) {
	if ( 'auto-draft' === $post->post_status ) {
		echo '<p class="description">' . esc_html__( 'Save the post first to enable FAQ migration.', 'blog-pro' ) . '</p>';
		return;
	}
	wp_nonce_field( 'blogpro_faq_single_migrate', 'blogpro_faq_single_nonce' );
	$migrated = get_post_meta( $post->ID, '_blogpro_faq_migrated', true );
	?>
	<p id="blogpro-faq-single-status" class="description">
		<?php if ( $migrated ) : ?>
			<?php printf(
				/* translators: %s: date/time string */
				esc_html__( 'Last migrated: %s', 'blog-pro' ),
				esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $migrated ) )
			); ?>
		<?php else : ?>
			<?php esc_html_e( 'Convert legacy FAQ markup to the theme FAQ block.', 'blog-pro' ); ?>
		<?php endif; ?>
	</p>
	<p>
		<button type="button" id="blogpro-faq-single-run" class="button button-primary"><?php esc_html_e( 'Migrate FAQ', 'blog-pro' ); ?></button>
	</p>
	<script>
	(function () {
		var btn = document.getElementById('blogpro-faq-single-run');
		var status = document.getElementById('blogpro-faq-single-status');
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'blogpro_faq_single_migrate' ) ); ?>;
		var postId = <?php echo (int) $post->ID; ?>;
		var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

		btn.addEventListener('click', function () {
			btn.disabled = true;
			status.textContent = <?php echo wp_json_encode( __( 'Migrating…', 'blog-pro' ) ); ?>;
			var body = new URLSearchParams();
			body.set('action', 'blogpro_faq_single_migrate');
			body.set('nonce', nonce);
			body.set('post_id', postId);
			fetch(ajax, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					if (j && j.success) {
						status.textContent = j.data.message || <?php echo wp_json_encode( __( 'Migration complete.', 'blog-pro' ) ); ?>;
					} else {
						status.textContent = (j && j.data) ? j.data : <?php echo wp_json_encode( __( 'Migration failed.', 'blog-pro' ) ); ?>;
					}
				})
				.catch(function () {
					status.textContent = <?php echo wp_json_encode( __( 'Request error.', 'blog-pro' ) ); ?>;
				})
				.finally(function () {
					btn.disabled = false;
				});
		});
	})();
	</script>
	<?php
}

/**
 * AJAX handler: migrate a single post's FAQ markup.
 */
function blogpro_faq_single_migrate_ajax() {
	check_ajax_referer( 'blogpro_faq_single_migrate', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( __( 'Permission denied.', 'blog-pro' ), 403 );
	}

	$result = blogpro_faq_migrate_post( $post_id, false );

	if ( $result['ok'] ) {
		$msg = 'converted' === $result['action']
			? __( 'FAQ migrated successfully.', 'blog-pro' )
			: __( 'FAQ consolidated.', 'blog-pro' );
		wp_send_json_success( array( 'message' => $msg, 'action' => $result['action'] ) );
	}

	$reasons = array(
		'already-block'   => __( 'Already using the FAQ block.', 'blog-pro' ),
		'migrated-before' => __( 'Already migrated previously.', 'blog-pro' ),
		'no-faq'          => __( 'No FAQ markup found in this post.', 'blog-pro' ),
		'no-items'        => __( 'FAQ markup found but no valid items extracted.', 'blog-pro' ),
		'replace-failed'  => __( 'Could not replace FAQ markup.', 'blog-pro' ),
	);
	$msg = isset( $reasons[ $result['reason'] ] ) ? $reasons[ $result['reason'] ] : __( 'Migration skipped.', 'blog-pro' );
	wp_send_json_error( $msg );
}
add_action( 'wp_ajax_blogpro_faq_single_migrate', 'blogpro_faq_single_migrate_ajax' );
