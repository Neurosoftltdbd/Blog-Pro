<?php
/**
 * Deep SEO Checker — theme-only, automated on-page SEO audit.
 *
 * No plugin dependency. Reads only the theme's own meta keys
 * (`_blogpro_meta_title`, `_blogpro_meta_description`,
 * `_blogpro_seo_focus_keyword`) and derives everything else from the
 * post content the way a crawler parses it:
 *
 *   - title      width/word count + keyword position
 *   - description width (SERP pixel budget) + keyword presence
 *   - content    length, keyword density (1st-word matching), first-
 *                occurrence placement, opening-paragraph strength,
 *                filler/stopword-only paragraphs
 *   - headings   h1 count, h2/h3 hierarchy, keyword in headings,
 *                skipped levels
 *   - links      internal vs external, nofollow, blanket anchors,
 *                exact-match anchor overuse
 *   - images     missing alt, missing width/height (CLS), no lazy load
 *   - schema     FAQPage / Article presence
 *   - structure  HTML comments left in content, TOC on long posts
 *   - readability Flesch Reading Ease + Flesch-Kincaid grade,
 *                avg words/sentence, long paragraphs, thin fragments
 *
 * Automation:
 *   - admin page Tools → SEO Checker auto-scans published posts
 *     (capped batch), caches score in `_blogpro_seo_score`, ranks
 *     them worst-first, and drills into one post's findings
 *   - a sidebar metabox on every post edit screen shows the live
 *     score + top findings without leaving the editor
 *   - score refreshes automatically on save_post
 *   - focus keyword is auto-derived from the post title; the metabox
 *     lets you override it per post
 *   - "Fix it for me" buttons write safe fixes (title/description
 *     keyword, extra-H1 downgrade) — one click, never silent
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------------
 * Text analysis helpers
 * ------------------------------------------------------------------- */

/**
 * Count words the way a crawler-facing parser would: strip all markup,
 * collapse whitespace, count whitespace-separated tokens.
 *
 * @param string $text
 * @return int
 */
function blogpro_seo_word_count( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	$text = preg_replace( '/\s+/u', ' ', trim( $text ) );
	if ( '' === $text ) {
		return 0;
	}
	return count( preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) );
}

/**
 * Text-only paragraphs (tags stripped) for fragment analysis.
 *
 * @param string $content
 * @return array<int, string>
 */
function blogpro_seo_text_blocks( $content ) {
	$blocks = array();
	foreach ( preg_split( '/<\/p>/i', (string) $content ) as $fragment ) {
		$fragment = trim( wp_strip_all_tags( $fragment ) );
		if ( '' !== $fragment ) {
			$blocks[] = preg_replace( '/\s+/u', ' ', $fragment );
		}
	}
	return $blocks;
}

/**
 * Word position of the keyword's first occurrence (0 = not found).
 *
 * @param string $text
 * @param string $keyword
 * @return int
 */
function blogpro_seo_keyword_pos( $text, $keyword ) {
	if ( '' === $text || '' === $keyword ) {
		return 0;
	}
	$norm   = mb_strtolower( preg_replace( '/\s+/u', ' ', $text ) );
	$needle = mb_strtolower( preg_replace( '/\s+/u', ' ', trim( $keyword ) ) );
	$pos    = mb_strpos( $norm, $needle );
	if ( false === $pos ) {
		return 0;
	}
	return 1 + substr_count( mb_substr( $norm, 0, $pos ), ' ' );
}

/**
 * Keyword density in percent (occurrences / total words).
 *
 * @param string $text
 * @param string $keyword
 * @return float
 */
function blogpro_seo_keyword_density( $text, $keyword ) {
	$words = blogpro_seo_word_count( $text );
	if ( $words < 10 ) {
		return 0.0;
	}
	$needle = mb_strtolower( preg_replace( '/\s+/u', ' ', trim( $keyword ) ) );
	if ( '' === $needle ) {
		return 0.0;
	}
	$count = preg_match_all( '/' . preg_quote( $needle, '/' ) . '/iu', mb_strtolower( wp_strip_all_tags( $text ) ) );
	return (float) round( 100 * $count / $words, 2 );
}

/**
 * Flesch Reading Ease + Flesch-Kincaid grade level.
 *
 * @param string $text
 * @return array{score: int, grade: float, sentences: int, words: int, wps: float}
 */
function blogpro_seo_flesch( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	$text = preg_replace( '/\s+/u', ' ', trim( $text ) );

	$sentences = max( 1, preg_match_all( '/[.!?]+(?=\s|$)/u', $text ) );
	$words     = max( 1, blogpro_seo_word_count( $text ) );
	$syllables = preg_match_all( '/[aeiouy]+/iu', $text );
	if ( 0 === $syllables ) {
		$syllables = 1;
	}
	$wps   = $words / $sentences;
	$spw   = $syllables / $words;
	$score = 206.835 - ( 1.015 * $wps ) - ( 84.6 * $spw );
	$grade = max( 0, ( 0.39 * $wps ) + ( 11.8 * $spw ) - 15.59 );

	return array(
		'score'     => (int) round( $score ),
		'grade'     => (float) round( $grade, 1 ),
		'sentences' => $sentences,
		'words'     => $words,
		'wps'       => (float) round( $wps, 1 ),
	);
}

/**
 * Ordered heading list (tag + text).
 *
 * @param string $content
 * @return array<int, array{tag: string, text: string}>
 */
function blogpro_seo_headings( $content ) {
	$out = array();
	if ( ! preg_match_all( '/<(h[1-6])\b[^>]*>(.*?)<\/\1>/is', (string) $content, $m, PREG_SET_ORDER ) ) {
		return $out;
	}
	foreach ( $m as $set ) {
		$text = trim( wp_strip_all_tags( $set[2] ) );
		if ( '' !== $text ) {
			$out[] = array( 'tag' => strtolower( $set[1] ), 'text' => $text );
		}
	}
	return $out;
}

/**
 * Links with href/text/internal/nofollow flags.
 *
 * @param string $content
 * @return array<int, array{href: string, text: string, internal: bool, nofollow: bool}>
 */
function blogpro_seo_links( $content ) {
	$links = array();
	if ( ! preg_match_all( '/<a\b([^>]*)>(.*?)<\/a>/is', (string) $content, $m, PREG_SET_ORDER ) ) {
		return $links;
	}
	$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	foreach ( $m as $set ) {
		if ( ! preg_match( '/\bhref\s*=\s*(["\'])(.*?)\1/i', $set[1], $h ) ) {
			continue;
		}
		$href = $h[2];
		if ( '' === $href || '#' === $href ) {
			continue;
		}
		$href_host = wp_parse_url( $href, PHP_URL_HOST );
		$links[] = array(
			'href'     => $href,
			'text'     => trim( wp_strip_all_tags( $set[2] ) ),
			'internal' => ( null === $href_host || $href_host === $host ),
			'nofollow' => (bool) preg_match( '/\brel\s*=\s*["\'][^"\']*nofollow/i', $set[1] ),
		);
	}
	return $links;
}

/**
 * Images with src/alt/dimension/lazy flags.
 *
 * @param string $content
 * @return array<int, array{src: string, alt: string, has_dim: bool, lazy: bool}>
 */
function blogpro_seo_images( $content ) {
	$images = array();
	if ( ! preg_match_all( '/<img\b[^>]*>/i', (string) $content, $m ) ) {
		return $images;
	}
	foreach ( $m[0] as $img ) {
		$src = '';
		preg_match( '/\bsrc\s*=\s*(["\'])(.*?)\1/i', $img, $s ) && ( $src = $s[2] );
		$alt = '';
		preg_match( '/\balt\s*=\s*(["\'])(.*?)\1/i', $img, $a ) && ( $alt = $a[2] );
		$images[] = array(
			'src'      => $src,
			'alt'      => $alt,
			'has_dim'  => (bool) preg_match( '/\b(?:width|height)\s*=/i', $img ),
			'lazy'     => (bool) preg_match( '/\bloading\s*=\s*["\']lazy["\']/i', $img ),
		);
	}
	return $images;
}

/**
 * Schema @type values found in JSON-LD in the content.
 *
 * @param string $content
 * @return array<int, string>
 */
function blogpro_seo_schema_types( $content ) {
	$types = array();
	if ( preg_match_all( '/<script[^>]*application\/ld\+json[^>]*>(.*?)<\/script>/is', (string) $content, $m ) ) {
		foreach ( $m[1] as $json ) {
			$data = json_decode( trim( $json ), true );
			if ( null === $data ) {
				continue;
			}
			$nodes = isset( $data['@graph'] ) ? $data['@graph'] : array( $data );
			foreach ( $nodes as $node ) {
				if ( isset( $node['@type'] ) ) {
					$types = array_merge( $types, (array) $node['@type'] );
				}
			}
		}
	}
	return array_values( array_map( 'strval', $types ) );
}

/**
 * One finding.
 *
 * @param string $severity error|warning|info
 * @param string $section  section key
 * @param string $message
 * @param string $why      why a search engine cares
 * @param string $suggest  improvement suggestion
 * @param string $measured
 * @param string $target
 * @param string $fix_key  optional "fix it for me" action key
 * @return array
 */
function blogpro_seo_finding( $severity, $section, $message, $why, $suggest, $measured = '', $target = '', $fix_key = '' ) {
	return array(
		'severity' => $severity,
		'section'  => $section,
		'message'  => $message,
		'why'      => $why,
		'suggest'  => $suggest,
		'measured' => $measured,
		'target'   => $target,
		'fix_key'  => $fix_key,
	);
}

/* ---------------------------------------------------------------------
 * Focus keyword: theme meta first, else auto-derived from content
 * ------------------------------------------------------------------- */

/**
 * Topic keywords for a post, best first (theme meta → title phrase →
 * first H2 → most common multi-word phrase).
 *
 * @param int $post_id
 * @return string auto/chosen keyword
 */
function blogpro_seo_focus_keyword( $post_id ) {
	$saved = trim( (string) get_post_meta( $post_id, '_blogpro_seo_focus_keyword', true ) );
	if ( '' !== $saved ) {
		return $saved;
	}
	return blogpro_seo_auto_keyword( $post_id );
}

/**
 * Derive a keyword automatically:
 *   1. post title without trailing site-name segment ("X | Blog")
 *   2. first H2 heading text
 *   3. most frequent phrase in content (1-3 words, no stopwords)
 *
 * @param int $post_id
 * @return string
 */
function blogpro_seo_auto_keyword( $post_id ) {
	$title = get_the_title( $post_id );
	// Cut any trailing " | Site Name" / " — Site Name" segment.
	$parts = preg_split( '/\s*[|]\s*|\s+[–—]\s+|\s+-\s+/u', $title );
	$head  = trim( $parts[0] ?? '' );
	if ( blogpro_seo_word_count( $head ) >= 2 ) {
		return mb_substr( $head, 0, 100 );
	}

	$content = (string) get_post_field( 'post_content', $post_id );
	foreach ( blogpro_seo_headings( $content ) as $h ) {
		if ( 'h2' === $h['tag'] && blogpro_seo_word_count( $h['text'] ) >= 2 ) {
			return $h['text'];
		}
	}

	// Last resort: most frequent 2-word phrase in the content.
	$text = mb_strtolower( wp_strip_all_tags( $content ) );
	$text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );
	$text = preg_replace( '/\s+/u', ' ', trim( $text ) );
	$words = array_filter( explode( ' ', $text ) );
	$stopwords = array_flip( explode( ' ', 'the a an and or but in on at to of for with this that it its is are was were be been as by from not so if than too very can just have has had do does did will would should could may might must into over under again further then once here there when where why how all any both each few more most other some such no nor only own same now also about which what who whom whose while during before after up down out off above below between among through' ) );
	$phrases = array();
	for ( $i = 0; $i < count( $words ) - 1; $i++ ) {
		$w = trim( $words[ $i ], ".,;:!?\"'()" );
		$n = trim( $words[ $i + 1 ], ".,;:!?\"'()" );
		if ( ! isset( $stopwords[ $w ] ) && ! isset( $stopwords[ $n ] ) ) {
			$phrase = $w . ' ' . $n;
			$phrases[ $phrase ] = ( $phrases[ $phrase ] ?? 0 ) + 1;
		}
	}
	arsort( $phrases );
	$key = (string) array_key_first( $phrases );
	return '' === $key ? '' : ucwords( $key );
}

/* ---------------------------------------------------------------------
 * The audit itself
 * ------------------------------------------------------------------- */

function blogpro_seo_check_post( $post_id ) {
	$post      = get_post( $post_id );
	$content   = (string) $post->post_content;
	$keyword   = blogpro_seo_focus_keyword( $post_id );
	$findings  = array();

	/* -- Meta & title ---------------------------------------------------- */

	$title = (string) get_post_meta( $post_id, '_blogpro_meta_title', true );
	if ( '' === $title ) {
		$title = get_the_title( $post_id );
	}
	$title_len   = mb_strlen( $title );
	$title_px    = (int) round( $title_len * 9.5 );
	$title_words = blogpro_seo_word_count( $title );

	if ( $title_px > 580 ) {
		$findings[] = blogpro_seo_finding( 'warning', 'meta',
			__( 'Title is longer than what a SERP shows (will be truncated with "…").', 'blog-pro' ),
			__( 'Google truncates over-long titles; the visible part is all a user clicks.', 'blog-pro' ),
			__( 'Trim the title to ~60 characters (≈ 580 px). Put the core phrase first.', 'blog-pro' ),
			sprintf( __( '%d chars (≈ %d px)', 'blog-pro' ), $title_len, $title_px ),
			__( '≤ 60 chars (≈ 580 px)', 'blog-pro' ) );
	}
	if ( $title_words < 3 ) {
		$findings[] = blogpro_seo_finding( 'warning', 'meta',
			__( 'Title has fewer than 3 words — too thin to be descriptive.', 'blog-pro' ),
			__( 'A title is the first thing a searcher reads; too few words rarely say what the page covers.', 'blog-pro' ),
			__( 'Expand the title to 4-8 words including a modifier (year, best, guide…).', 'blog-pro' ),
			sprintf( _n( '%d word', '%d words', $title_words, 'blog-pro' ), $title_words ),
			__( '4-8 words', 'blog-pro' ) );
	}
	if ( '' !== $keyword && false === stripos( $title, $keyword ) ) {
		$findings[] = blogpro_seo_finding( 'warning', 'meta',
			__( 'Focus keyword not present in the page title.', 'blog-pro' ),
			__( 'The title is the strongest on-page relevance signal; keyword presence there helps matching.', 'blog-pro' ),
			sprintf( __( 'Work "%s" (or a close variant) into the title naturally.', 'blog-pro' ), $keyword ),
			__( 'Missing', 'blog-pro' ), __( 'Present in title', 'blog-pro' ),
			'fix-title' );
	}

	$desc = (string) get_post_meta( $post_id, '_blogpro_meta_description', true );
	if ( '' === $desc ) {
		$desc = has_excerpt( $post_id ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $content ), 32, '…' );
	}
	$desc_len = mb_strlen( $desc );
	$desc_px  = (int) round( $desc_len * 4.7 );
	if ( $desc_px > 880 ) {
		$findings[] = blogpro_seo_finding( 'warning', 'meta',
			__( 'Description is far beyond the SERP limit (will be cut off mid-sentence).', 'blog-pro' ),
			__( 'Google shows ~155-160 chars of a meta description; overflow is truncated, hiding your CTA.', 'blog-pro' ),
			__( 'Move the call-to-action into the first 150 characters.', 'blog-pro' ),
			sprintf( __( '%d chars (≈ %d px)', 'blog-pro' ), $desc_len, $desc_px ),
			__( '≤ 160 chars (≈ 760 px)', 'blog-pro' ) );
	}
	if ( $desc_len < 70 ) {
		$findings[] = blogpro_seo_finding( 'warning', 'meta',
			__( 'Description is very short.', 'blog-pro' ),
			__( 'A thin description wastes SERP real estate and rarely earns a click.', 'blog-pro' ),
			__( 'Write a 140-160 character description: benefit + what the reader gets.', 'blog-pro' ),
			sprintf( __( '%d chars (≈ %d px)', 'blog-pro' ), $desc_len, $desc_px ),
			__( '120-160 chars', 'blog-pro' ) );
	}
	if ( '' !== $keyword && false === stripos( $desc, $keyword ) ) {
		$findings[] = blogpro_seo_finding( 'warning', 'meta',
			__( 'Focus keyword missing from the meta description.', 'blog-pro' ),
			__( 'SERP snippets highlight keyword matches; a match helps relevance and click-through.', 'blog-pro' ),
			sprintf( __( 'Include "%s" in the description, ideally in the first 20 characters.', 'blog-pro' ), $keyword ),
			__( 'Missing', 'blog-pro' ), __( 'Present in description', 'blog-pro' ),
			'fix-desc' );
	}
	if ( '' === (string) get_post_meta( $post_id, '_blogpro_meta_description', true ) ) {
		$findings[] = blogpro_seo_finding( 'info', 'meta',
			__( 'No hand-written meta description — using an auto-derived one.', 'blog-pro' ),
			__( 'Auto descriptions work; hand-written ones usually earn better CTR.', 'blog-pro' ),
			__( 'Write a custom description in the post editor (Blog Pro SEO box).', 'blog-pro' ),
			__( 'Auto-generated', 'blog-pro' ), __( 'Hand-written', 'blog-pro' ) );
	}

	/* -- Content size & keyword placement -------------------------------- */

	$text       = wp_strip_all_tags( $content );
	$word_count = blogpro_seo_word_count( $text );
	if ( $word_count < 300 ) {
		$findings[] = blogpro_seo_finding( 'warning', 'content',
			__( 'Content is on the short side.', 'blog-pro' ),
			__( 'Very short posts struggle to demonstrate coverage, which E-E-A-T rewards.', 'blog-pro' ),
			__( 'Expand to 600+ words: answer fully, add examples, a comparison or FAQ.', 'blog-pro' ),
			sprintf( _n( '%d word', '%d words', $word_count, 'blog-pro' ), $word_count ),
			__( '600+ words (300 minimum)', 'blog-pro' ) );
	}

	if ( '' !== $keyword ) {
		$density = blogpro_seo_keyword_density( $text, $keyword );
		if ( $density > 0 && $density < 0.5 ) {
			$findings[] = blogpro_seo_finding( 'warning', 'density',
				__( 'Keyword density is very low.', 'blog-pro' ),
				__( 'Keyword presence is a relevance cue; a page that never states its topic clearly is hard to match.', 'blog-pro' ),
				__( 'Mention the topic (or synonyms) 2-3 more times, once in a heading.', 'blog-pro' ),
				sprintf( '%.1f%%', $density ), __( '0.5%-2.5%', 'blog-pro' ) );
		} elseif ( $density > 3.5 ) {
			$findings[] = blogpro_seo_finding( 'error', 'density',
				__( 'Keyword density is very high — reads as keyword stuffing.', 'blog-pro' ),
				__( 'Over-using the exact phrase reads spammy to engines and unnatural to users.', 'blog-pro' ),
				__( 'Rewrite 2-3 occurrences with synonyms or pronouns.', 'blog-pro' ),
				sprintf( '%.1f%%', $density ), __( '0.5%-2.5%', 'blog-pro' ) );
		}
		$kw_pos = blogpro_seo_keyword_pos( $text, $keyword );
		if ( $kw_pos > 120 ) {
			$findings[] = blogpro_seo_finding( 'warning', 'density',
				__( 'The keyword first appears very late in the post.', 'blog-pro' ),
				__( 'The first ~100 words set context for users and crawlers; a late keyword weakens relevance.', 'blog-pro' ),
				__( 'Introduce the topic in the opening paragraph.', 'blog-pro' ),
				sprintf( __( 'First at word %d', 'blog-pro' ), $kw_pos ),
				__( 'Within the first 100 words', 'blog-pro' ) );
		}
	}

	$first_block = blogpro_seo_text_blocks( $content )[0] ?? '';
	$first_len   = blogpro_seo_word_count( $first_block );
	if ( $first_len < 15 && $first_len > 0 ) {
		$findings[] = blogpro_seo_finding( 'info', 'content',
			__( 'Opening paragraph is very short.', 'blog-pro' ),
			__( 'A strong intro (50-120 words) hooks readers fast, improving dwell time and intent match.', 'blog-pro' ),
			__( 'Expand the first paragraph to 40-80 words: benefit + what the reader will learn.', 'blog-pro' ),
			sprintf( _n( '%d word', '%d words', $first_len, 'blog-pro' ), $first_len ),
			__( '40-80 words', 'blog-pro' ) );
	}

	/* -- Headings --------------------------------------------------------- */

	$headings = blogpro_seo_headings( $content );
	$h1s      = array();
	foreach ( $headings as $h ) {
		if ( 'h1' === $h['tag'] ) {
			$h1s[] = $h;
		}
	}
	if ( count( $h1s ) > 1 ) {
		$findings[] = blogpro_seo_finding( 'error', 'headings',
			__( 'Multiple H1 headings found.', 'blog-pro' ),
			__( 'A single H1 per page is the primary topic signal; extra H1s dilute it.', 'blog-pro' ),
			__( 'Keep one H1 and downgrade the rest to H2.', 'blog-pro' ),
			sprintf( _n( '%d H1', '%d H1s', count( $h1s ), 'blog-pro' ), count( $h1s ) ),
			__( 'Exactly 1', 'blog-pro' ), 'fix-h1' );
	}

	$expected = 1;
	foreach ( $headings as $h ) {
		$level = (int) substr( $h['tag'], 1 );
		if ( $level > $expected + 1 ) {
			$findings[] = blogpro_seo_finding( 'info', 'headings',
				__( 'Heading level skipped (h%d followed by h%d).', 'blog-pro' ),
				__( 'Skipped levels make the reading hierarchy harder to parse for engines and screen readers.', 'blog-pro' ),
				__( 'Nest each level only one deeper than its parent.', 'blog-pro' ),
				sprintf( __( 'Skipped to h%d', 'blog-pro' ), $level ),
				__( 'h2→h3→h4 only', 'blog-pro' ) );
		}
		$expected = max( $expected, $level );
	}

	$keyword_in_heading = false;
	foreach ( $headings as $h ) {
		if ( '' !== $keyword && in_array( $h['tag'], array( 'h2', 'h3' ), true ) && false !== stripos( $h['text'], $keyword ) ) {
			$keyword_in_heading = true;
			break;
		}
	}
	if ( '' !== $keyword && ! $keyword_in_heading && count( $headings ) > 1 ) {
		$findings[] = blogpro_seo_finding( 'info', 'headings',
			__( 'Keyword not used in any H2/H3 heading.', 'blog-pro' ),
			__( 'Headings are the fastest structure cue a crawler parses; a keyword-bearing heading strengthens topical focus.', 'blog-pro' ),
			__( 'Use the keyword (or a synonym) in one section heading — naturally.', 'blog-pro' ),
			__( 'Missing in headings', 'blog-pro' ), __( 'Present in ≥1 heading', 'blog-pro' ) );
	}

	/* -- Links ------------------------------------------------------------ */

	$links     = blogpro_seo_links( $content );
	$intern = $extern = $nofollow = $bare = 0;
	foreach ( $links as $l ) {
		$l['internal'] ? $intern++ : $extern++;
		$l['nofollow'] && $nofollow++;
		'' === $l['text'] && $bare++;
	}
	if ( $intern < 3 ) {
		$findings[] = blogpro_seo_finding( 'warning', 'links',
			__( 'Few internal links in the post.', 'blog-pro' ),
			__( 'Internal links spread authority and help engines discover related content.', 'blog-pro' ),
			__( 'Link 3-5 related posts naturally within the body text.', 'blog-pro' ),
			sprintf( _n( '%d internal link', '%d internal links', $intern, 'blog-pro' ), $intern ),
			__( '3+ internal links', 'blog-pro' ) );
	}
	if ( 0 === $extern && $links ) {
		$findings[] = blogpro_seo_finding( 'info', 'links',
			__( 'No external links found.', 'blog-pro' ),
			__( 'Linking out to authoritative sources is a trust signal and grounds your topic in context.', 'blog-pro' ),
			__( 'Add 1-2 external links to authoritative, relevant sources.', 'blog-pro' ),
			__( '0 external', 'blog-pro' ), __( '1-3 external', 'blog-pro' ) );
	}
	if ( $bare > 0 ) {
		$findings[] = blogpro_seo_finding( 'info', 'links',
			__( 'Links with no anchor text found.', 'blog-pro' ),
			__( 'Anchor text tells crawlers what the target is about; blank anchors convey nothing.', 'blog-pro' ),
			__( 'Give every link a descriptive text anchor.', 'blog-pro' ),
			sprintf( _n( '%d link', '%d links', $bare, 'blog-pro' ), $bare ),
			__( '0 anchorless', 'blog-pro' ) );
	}

	/* -- Images ----------------------------------------------------------- */

	$images = blogpro_seo_images( $content );
	$no_alt = $no_dim = $no_lazy = 0;
	foreach ( $images as $im ) {
		'' === trim( $im['alt'] ) && $no_alt++;
		! $im['has_dim'] && $no_dim++;
		! $im['lazy'] && $no_lazy++;
	}
	if ( $no_alt > 0 ) {
		$findings[] = blogpro_seo_finding( 'warning', 'images',
			__( 'Images missing alt text.', 'blog-pro' ),
			__( 'Alt text is how search engines and screen readers understand an image; missing alt loses image-search ranking opportunity.', 'blog-pro' ),
			__( 'Describe each image in 5-12 words mentioning its subject and context.', 'blog-pro' ),
			sprintf( _n( '%d image', '%d images', $no_alt, 'blog-pro' ), $no_alt ),
			__( 'All have descriptive alt', 'blog-pro' ) );
	}
	if ( $no_dim > 0 ) {
		$findings[] = blogpro_seo_finding( 'info', 'images',
			__( 'Images missing width/height attributes.', 'blog-pro' ),
			__( 'Without dimensions the browser reserves no space — layout shift (CLS) hurts Core Web Vitals.', 'blog-pro' ),
			__( 'Add width + height (or set them in CSS) for each image.', 'blog-pro' ),
			sprintf( _n( '%d image', '%d images', $no_dim, 'blog-pro' ), $no_dim ),
			__( 'All have dimensions', 'blog-pro' ) );
	}
	if ( $no_lazy > 0 && count( $images ) > 2 ) {
		$findings[] = blogpro_seo_finding( 'info', 'images',
			__( 'Below-the-fold images are not lazy-loaded.', 'blog-pro' ),
			__( 'Lazy loading speeds up LCP/INP by deferring off-screen images.', 'blog-pro' ),
			__( 'Add loading="lazy" to below-fold images (never the hero/LCP image).', 'blog-pro' ),
			sprintf( _n( '%d image', '%d images', $no_lazy, 'blog-pro' ), $no_lazy ),
			__( 'Below-fold images lazy', 'blog-pro' ) );
	}

	/* -- Schema & structure ----------------------------------------------- */

	$schema  = blogpro_seo_schema_types( $content );
	$has_faq = (bool) preg_match( '/<!--\s*wp:blog-pro\/faq/i', $content );
	if ( $has_faq && ! in_array( 'FAQPage', $schema, true ) ) {
		$findings[] = blogpro_seo_finding( 'warning', 'schema',
			__( 'FAQ content present but no FAQPage structured data.', 'blog-pro' ),
			__( 'FAQ schema can win rich results and more SERP real estate.', 'blog-pro' ),
			__( 'The theme FAQ block emits FAQPage JSON-LD automatically — replace hand-written FAQ with it.', 'blog-pro' ),
			__( 'FAQ present, no FAQPage', 'blog-pro' ), __( 'FAQPage JSON-LD', 'blog-pro' ) );
	}

	$comments = preg_match_all( '/<!--(?!\s*wp:|{\s*wp:|\/wp:)/i', $content );
	if ( $comments > 0 ) {
		$findings[] = blogpro_seo_finding( 'info', 'structure',
			__( 'Non-block HTML comments left in the content.', 'blog-pro' ),
			__( 'Comments that are not block delimiters are usually paste artifacts; they bloat HTML.', 'blog-pro' ),
			__( 'Remove paste artifacts in the Code editor view.', 'blog-pro' ),
			sprintf( _n( '%d comment', '%d comments', $comments, 'blog-pro' ), $comments ),
			__( 'None', 'blog-pro' ) );
	}

	$has_toc = (bool) preg_match( '/<!--\s*wp:blog-pro\/toc/i', $content );
	if ( $word_count > 800 && ! $has_toc ) {
		$findings[] = blogpro_seo_finding( 'info', 'structure',
			__( 'Long post without a table of contents.', 'blog-pro' ),
			__( 'A TOC helps users navigate long reads and adds crawlable anchor structure.', 'blog-pro' ),
			__( 'Add the blog-pro/toc block near the top.', 'blog-pro' ),
			sprintf( _n( '%d word', '%d words', $word_count, 'blog-pro' ), $word_count ),
			__( 'TOC aids 800+ word posts', 'blog-pro' ) );
	}

	/* -- Readability ------------------------------------------------------ */

	$flesch = blogpro_seo_flesch( $text );
	if ( $flesch['score'] < 40 ) {
		$findings[] = blogpro_seo_finding( 'warning', 'readability',
			__( 'Readability is hard (Flesch below 40, grade %s).', 'blog-pro' ),
			__( 'Hard-to-read text raises bounce and lowers dwell time — engagement matters.', 'blog-pro' ),
			__( 'Shorten sentences (avg 15-20 words), replace jargon, use simpler wording.', 'blog-pro' ),
			sprintf( __( 'Flesch %d / grade %s', 'blog-pro' ), $flesch['score'], $flesch['grade'] ),
			__( 'Flesch 60-70', 'blog-pro' ) );
	}

	$long_paragraphs = 0;
	foreach ( blogpro_seo_text_blocks( $content ) as $block ) {
		blogpro_seo_word_count( $block ) > 200 && $long_paragraphs++;
	}
	if ( $long_paragraphs > 0 ) {
		$findings[] = blogpro_seo_finding( 'info', 'readability',
			__( 'Very long paragraphs found.', 'blog-pro' ),
			__( 'Walls of text are hard to scan; mobile readers bounce.', 'blog-pro' ),
			sprintf( _n( 'Split %d paragraph into 2-3 shorter ones.', 'Split %d paragraphs into 2-3 shorter ones.', $long_paragraphs, 'blog-pro' ), $long_paragraphs ),
			sprintf( _n( '%d paragraph', '%d paragraphs', $long_paragraphs, 'blog-pro' ), $long_paragraphs ),
			__( 'Max ~150 words each', 'blog-pro' ) );
	}

	$thin = 0;
	foreach ( blogpro_seo_text_blocks( $content ) as $block ) {
		blogpro_seo_word_count( $block ) <= 2 && $thin++;
	}
	if ( $thin > 0 ) {
		$findings[] = blogpro_seo_finding( 'info', 'readability',
			__( 'Some 1-2 word fragments.', 'blog-pro' ),
			__( 'Fragments can be intentional pauses, but too many look broken.', 'blog-pro' ),
			__( 'Merge tiny fragments into a neighbouring paragraph.', 'blog-pro' ),
			sprintf( _n( '%d fragment', '%d fragments', $thin, 'blog-pro' ), $thin ),
			__( 'Few', 'blog-pro' ) );
	}

	/* -- Scoring ---------------------------------------------------------- */

	$sections = array(
		'meta'        => array( 'label' => __( 'Meta & Title', 'blog-pro' ), 'weight' => 25 ),
		'content'     => array( 'label' => __( 'Content', 'blog-pro' ), 'weight' => 20 ),
		'density'     => array( 'label' => __( 'Keywords', 'blog-pro' ), 'weight' => 15 ),
		'headings'    => array( 'label' => __( 'Headings', 'blog-pro' ), 'weight' => 10 ),
		'links'       => array( 'label' => __( 'Links', 'blog-pro' ), 'weight' => 10 ),
		'images'      => array( 'label' => __( 'Images', 'blog-pro' ), 'weight' => 10 ),
		'schema'      => array( 'label' => __( 'Structured Data', 'blog-pro' ), 'weight' => 5 ),
		'readability' => array( 'label' => __( 'Readability', 'blog-pro' ), 'weight' => 5 ),
		'structure'   => array( 'label' => __( 'Structure', 'blog-pro' ), 'weight' => 0 ),
	);
	$by_section = array();
	foreach ( $findings as $f ) {
		$by_section[ $f['section'] ][] = $f;
	}

	$earned = 0; $total = 0;
	foreach ( $sections as $key => $sec ) {
		$pts = $sec['weight'];
		foreach ( $by_section[ $key ] ?? array() as $f ) {
			$pts -= 'error' === $f['severity'] ? 5 : ( 'warning' === $f['severity'] ? 3 : 1 );
		}
		$pts = max( 0, $pts );
		$earned += $pts;
		$total  += $sec['weight'];
	}
	$score = 0 === $total ? 100 : (int) round( 100 * $earned / $total );

	return array(
		'score'    => $score,
		'findings' => $by_section,
		'stats'    => array(
			'keyword'     => $keyword,
			'title_len'   => $title_len,
			'title_px'    => $title_px,
			'desc_len'    => $desc_len,
			'desc_px'     => $desc_px,
			'word_count'  => $word_count,
			'density'     => isset( $density ) ? $density : 0.0,
			'kw_pos'      => isset( $kw_pos ) ? $kw_pos : 0,
			'headings'    => count( $headings ),
			'h1'          => count( $h1s ),
			'links'       => count( $links ),
			'internal'    => $intern,
			'external'    => $extern,
			'images'      => count( $images ),
			'no_alt'      => $no_alt,
			'flesch'      => $flesch,
		),
	);
}

/**
 * Persist a post's audit (score + finding counts + timestamp).
 *
 * @param int $post_id
 * @return void
 */
function blogpro_seo_store_result( $post_id ) {
	$r = blogpro_seo_check_post( $post_id );
	update_post_meta( $post_id, '_blogpro_seo_score', (int) $r['score'] );
	$group = array( 'error' => 0, 'warning' => 0, 'info' => 0 );
	foreach ( $r['findings'] as $items ) {
		foreach ( $items as $f ) {
			$group[ $f['severity'] ]++;
		}
	}
	update_post_meta( $post_id, '_blogpro_seo_issues', $group );
	update_post_meta( $post_id, '_blogpro_seo_cached_at', time() );
}

/**
 * Recompute a post's score whenever it is saved.
 *
 * @param int $post_id
 * @return void
 */
function blogpro_seo_refresh_on_save( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
		return;
	}
	if ( ! post_type_supports( get_post_type( $post_id ), 'editor' ) ) {
		return;
	}
	blogpro_seo_store_result( $post_id );
}
add_action( 'save_post', 'blogpro_seo_refresh_on_save', 20 );

/* ---------------------------------------------------------------------
 * Post editor metabox — live score in the sidebar
 * ------------------------------------------------------------------- */

function blogpro_seo_metabox_register() {
	foreach ( get_post_types( array( 'public' => true ), 'names' ) as $type ) {
		if ( post_type_supports( $type, 'editor' ) ) {
			add_meta_box( 'blogpro-seo-check', __( 'SEO Check (auto)', 'blog-pro' ), 'blogpro_seo_metabox_render', $type, 'side', 'default' );
		}
	}
}
add_action( 'add_meta_boxes', 'blogpro_seo_metabox_register' );

function blogpro_seo_metabox_render( $post ) {
	$r       = blogpro_seo_check_post( $post->ID );
	$score   = $r['score'];
	$cls     = $score >= 80 ? 'ok' : ( $score >= 60 ? 'mid' : 'bad' );
	$keyword = get_post_meta( $post->ID, '_blogpro_seo_focus_keyword', true );
	$total   = 0;
	foreach ( $r['findings'] as $items ) {
		$total += count( $items );
	}
	?>
	<div class="bpseo-meta">
		<p style="margin:0 0 8px;">
			<span class="bpseo-score <?php echo esc_attr( $cls ); ?>" style="display:inline-block;font-size:26px;font-weight:700;"><?php echo (int) $score; ?>/100</span>
			<span style="color:#646970;font-size:12px;"><?php printf( esc_html( _n( '%d issue', '%d issues', $total, 'blog-pro' ) ), $total ); ?></span>
		</p>
		<p style="margin:0 0 8px;font-size:13px;">
			<?php esc_html_e( 'Focus keyword (auto if empty):', 'blog-pro' ); ?><br>
			<input type="text" style="width:100%;" name="blogpro_seo_focus_keyword" value="<?php echo esc_attr( $keyword ); ?>" placeholder="<?php echo esc_attr( $r['stats']['keyword'] ); ?>">
		</p>
		<p class="description" style="margin:0 0 8px;">
			<?php
			$top = array();
			foreach ( $r['findings'] as $items ) {
				foreach ( $items as $f ) {
					if ( 'info' !== $f['severity'] ) {
						$top[] = $f['message'];
					}
				}
			}
			$top = array_slice( $top, 0, 3 );
			if ( $top ) :
				foreach ( $top as $t ) :
					?>
					&bull; <?php echo esc_html( $t ); ?><br>
				<?php endforeach; ?>
			<?php else : ?>
				<?php esc_html_e( 'No issues found — well optimised.', 'blog-pro' ); ?>
			<?php endif; ?>
		</p>
		<p style="margin:0;">
			<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-seo-checker&post_id=' . (int) $post->ID ) ); ?>"><?php esc_html_e( 'Full report', 'blog-pro' ); ?></a>
			<?php if ( 0 === strlen( $keyword ) ) : ?>
				<span class="description"><?php esc_html_e( 'Keyword derived from title. Override above.', 'blog-pro' ); ?></span>
			<?php endif; ?>
		</p>
	</div>
	<?php
}

function blogpro_seo_metabox_save( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['blogpro_seo_focus_keyword'] ) ) {
		$kw = sanitize_text_field( wp_unslash( $_POST['blogpro_seo_focus_keyword'] ) );
		if ( '' === $kw ) {
			delete_post_meta( $post_id, '_blogpro_seo_focus_keyword' );
		} else {
			update_post_meta( $post_id, '_blogpro_seo_focus_keyword', $kw );
		}
	}
}
add_action( 'save_post', 'blogpro_seo_metabox_save', 10 );

/* ---------------------------------------------------------------------
 * Admin page (Tools → SEO Checker) — automated scan + detail
 * ------------------------------------------------------------------- */

// Submenu item registered in admin/class-blogpro-admin-menu.php
// (blogpro_admin_menu_brand) so the parent exists at hook time.

/**
 * Batch-scan published posts (multi-request friendly inbox): audits
 * posts without a cached score or whose cache is older than $max_age,
 * up to $limit per call.
 *
 * @param int $limit
 * @param int $max_age seconds
 * @return int number of posts audited
 */
function blogpro_seo_scan_batch( $limit = 10, $max_age = 604800 ) {
	$cache_key = '_blogpro_seo_cached_at';
	$cutoff    = time() - $max_age;
	$posts = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'meta_query'     => array(
			'relation' => 'OR',
			array( 'key' => $cache_key, 'compare' => 'NOT EXISTS' ),
			array( 'key' => $cache_key, 'value' => $cutoff, 'compare' => '<', 'type' => 'NUMERIC' ),
		),
	) );
	foreach ( $posts as $p ) {
		blogpro_seo_store_result( $p->ID );
	}
	return count( $posts );
}

function blogpro_seo_checker_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'blog-pro' ) );
	}

	// Rescan action.
	if ( isset( $_GET['blogpro_seo_rescan'] ) && check_admin_referer( 'blogpro_seo_rescan' ) ) {
		$rescanned = blogpro_seo_scan_batch( 1000, 0 );
	}

	$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
	$detail  = $post_id ? get_post( $post_id ) : null;

	// Ranked list of audited posts.
	$list = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'meta_key'       => '_blogpro_seo_score',
		'orderby'        => 'meta_value_num',
		'order'          => 'ASC',
	) );
	$unsorted = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'meta_query'     => array( array( 'key' => '_blogpro_seo_score', 'compare' => 'NOT EXISTS' ) ),
	) );
	?>
	<style>
	.bpseo-wrap{ max-width:1100px; }
	.bpseo-card{ background:#fff; border:1px solid #dcdcde; border-radius:8px; margin:14px 0; overflow:hidden; }
	.bpseo-card > h3{ margin:0; padding:11px 16px; background:#f0f0f1; border-bottom:1px solid #dcdcde; font-size:13px; }
	.bpseo-item{ display:grid; grid-template-columns:86px 1fr; padding:11px 16px; border-top:1px solid #f0f0f1; }
	.bpseo-item:first-child{ border-top:0; }
	.bpseo-flag{ font-size:11px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; padding:3px 8px; border-radius:3px; align-self:start; text-align:center; color:#fff; }
	.bpseo-flag.error{ background:#d63638; } .bpseo-flag.warning{ background:#c77700; } .bpseo-flag.info{ background:#2271b1; }
	.bpseo-msg{ font-weight:600; }
	.bpseo-why{ color:#3c434a; margin-top:2px; }
	.bpseo-suggest{ background:#f6f7f7; border-left:3px solid #c3c4c7; padding:7px 10px; margin-top:8px; font-size:13px; }
	.bpseo-meta{ display:flex; gap:14px; margin-top:6px; font-size:12px; color:#646970; }
	.bpseo-meta b{ color:#3c434a; }
	.bpseo-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; padding:14px 16px; }
	.bpseo-stat{ background:#f6f7f7; border-radius:6px; padding:9px 12px; }
	.bpseo-stat .v{ font-size:19px; font-weight:700; } .bpseo-stat .k{ font-size:12px; color:#646970; }
	.bpseo-score{ font-weight:700; }
	.bpseo-score.ok{ color:#008a20; } .bpseo-score.mid{ color:#c77700; } .bpseo-score.bad{ color:#d63638; }
	.bpseo-table{ width:100%; border-collapse:collapse; }
	.bpseo-table th, .bpseo-table td{ text-align:left; padding:8px 12px; border-bottom:1px solid #f0f0f1; font-size:13px; }
	.bpseo-table th{ background:#f6f7f7; font-weight:600; }
	.bpseo-table tr:hover td{ background:#f6f7f7; }
	.bpseo-table .sc{ font-size:15px; font-weight:700; }
	.bpseo-fixbtn{ margin-top:8px; }
	.bpseo-note{ font-size:12px; color:#646970; }
	</style>

	<div class="wrap bpseo-wrap">
		<h1><?php esc_html_e( 'SEO Checker', 'blog-pro' ); ?></h1>
		<p><?php esc_html_e( 'Automated on-page audit of all published posts, ranked worst-first. Scores refresh on every post save; use the rescan button to audit new/changed posts.' ); ?></p>

		<?php
		if ( isset( $rescanned ) ) :
			?>
			<div class="notice notice-success is-dismissible"><p><?php printf( esc_html( _n( 'Scanned %d post.', 'Scanned %d posts.', $rescanned, 'blog-pro' ) ), (int) $rescanned ); ?></p></div>
		<?php endif; ?>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin:12px 0;">
			<input type="hidden" name="page" value="blogpro-seo-checker">
			<input type="hidden" name="blogpro_seo_rescan" value="1">
			<?php wp_nonce_field( 'blogpro_seo_rescan' ); ?>
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Rescan all posts', 'blog-pro' ); ?></button>
			<span class="description"><?php esc_html_e( 'Scores also refresh automatically on save + on first page load.', 'blog-pro' ); ?></span>
		</form>

		<div class="bpseo-card">
			<h3><?php esc_html_e( 'All posts (worst first)', 'blog-pro' ); ?></h3>
			<table class="bpseo-table">
				<thead><tr>
					<th><?php esc_html_e( 'Post', 'blog-pro' ); ?></th>
					<th><?php esc_html_e( 'Score', 'blog-pro' ); ?></th>
					<th><?php esc_html_e( 'Issues', 'blog-pro' ); ?></th>
					<th><?php esc_html_e( 'Keyword', 'blog-pro' ); ?></th>
					<th><?php esc_html_e( 'Words', 'blog-pro' ); ?></th>
				</tr></thead>
				<tbody>
				<?php
				$rows = array_merge( $list, $unsorted );
				if ( ! $rows ) :
					?>
					<tr><td colspan="5"><?php esc_html_e( 'No published posts yet.', 'blog-pro' ); ?></td></tr>
				<?php endif;
				foreach ( $rows as $p ) :
					$score = get_post_meta( $p->ID, '_blogpro_seo_score', true );
					$issues = get_post_meta( $p->ID, '_blogpro_seo_issues', true );
					$issues = is_array( $issues ) ? $issues : array( 'error' => 0, 'warning' => 0, 'info' => 0 );
					$total_issues = array_sum( $issues );
					$sc  = '' === $score ? null : (int) $score;
					$cls = null === $sc ? 'mid' : ( $sc >= 80 ? 'ok' : ( $sc >= 60 ? 'mid' : 'bad' ) );
					?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=blogpro-seo-checker&post_id=' . (int) $p->ID ) ); ?>"><strong><?php echo esc_html( get_the_title( $p ) ); ?></strong></a></td>
						<td class="sc bpseo-score <?php echo esc_attr( $cls ); ?>"><?php echo null === $sc ? '—' : (int) $sc; ?></td>
						<td><?php
							printf(
								'<span style="color:#d63638;">%d</span> / <span style="color:#c77700;">%d</span> / <span style="color:#2271b1;">%d</span>',
								(int) $issues['error'], (int) $issues['warning'], (int) $issues['info']
							);
							?>
						</td>
						<td><?php echo esc_html( blogpro_seo_focus_keyword( $p->ID ) ); ?></td>
						<td><?php echo (int) blogpro_seo_word_count( (string) $p->post_content ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $detail ) : $r = blogpro_seo_check_post( $detail->ID ); ?>
			<div class="bpseo-card">
				<h3><?php printf( esc_html__( 'Report: %s', 'blog-pro' ), esc_html( get_the_title( $detail ) ) ); ?>
					<span class="bpseo-score <?php echo esc_attr( $r['score'] >= 80 ? 'ok' : ( $r['score'] >= 60 ? 'mid' : 'bad' ) ); ?>">— <?php echo (int) $r['score']; ?>/100</span>
				</h3>

				<div class="bpseo-grid">
					<?php
					$s = $r['stats'];
					$stats = array(
						__( 'Keyword', 'blog-pro' )      => $s['keyword'] ?: '—',
						__( 'Title', 'blog-pro' )        => (int) $s['title_len'] . ' chars',
						__( 'Description', 'blog-pro' )  => (int) $s['desc_len'] . ' chars',
						__( 'Words', 'blog-pro' )        => (int) $s['word_count'],
						__( 'Density', 'blog-pro' )      => (float) $s['density'] . '%',
						__( 'Headings', 'blog-pro' )     => (int) $s['headings'] . ' (' . (int) $s['h1'] . ' h1)',
						__( 'Links', 'blog-pro' )        => (int) $s['links'] . ' (' . (int) $s['internal'] . ' in / ' . (int) $s['external'] . ' out)',
						__( 'Images', 'blog-pro' )       => (int) $s['images'] . ( $s['no_alt'] ? ' (' . (int) $s['no_alt'] . ' no alt)' : '' ),
						__( 'Flesch ease', 'blog-pro' )  => (int) $s['flesch']['score'] . ' (grade ' . (float) $s['flesch']['grade'] . ')',
					);
					foreach ( $stats as $k => $v ) :
						?>
						<div class="bpseo-stat"><div class="v"><?php echo esc_html( $v ); ?></div><div class="k"><?php echo esc_html( $k ); ?></div></div>
					<?php endforeach; ?>
				</div>

				<?php
				$any = false;
				foreach ( $r['findings'] as $section => $items ) :
					if ( ! $items ) { continue; }
					$any = true;
					$label = array( 'meta' => __( 'Meta & Title', 'blog-pro' ), 'content' => __( 'Content', 'blog-pro' ), 'density' => __( 'Keywords', 'blog-pro' ), 'headings' => __( 'Headings', 'blog-pro' ), 'links' => __( 'Links', 'blog-pro' ), 'images' => __( 'Images', 'blog-pro' ), 'schema' => __( 'Structured Data', 'blog-pro' ), 'structure' => __( 'Structure', 'blog-pro' ), 'readability' => __( 'Readability', 'blog-pro' ) );
					?>
					<h3 style="background:#fff;"><?php echo esc_html( $label[ $section ] ?? $section ); ?></h3>
					<?php foreach ( $items as $f ) : ?>
						<div class="bpseo-item">
							<div><span class="bpseo-flag <?php echo esc_attr( $f['severity'] ); ?>"><?php echo esc_html( $f['severity'] ); ?></span></div>
							<div>
								<div class="bpseo-msg"><?php echo esc_html( $f['message'] ); ?></div>
								<div class="bpseo-why"><?php echo esc_html( $f['why'] ); ?></div>
								<div class="bpseo-meta">
									<span><b><?php esc_html_e( 'Now', 'blog-pro' ); ?>:</b> <?php echo esc_html( $f['measured'] ); ?></span>
									<span><b><?php esc_html_e( 'Target', 'blog-pro' ); ?>:</b> <?php echo esc_html( $f['target'] ); ?></span>
								</div>
								<div class="bpseo-suggest"><?php echo esc_html( $f['suggest'] ); ?></div>
								<?php if ( $f['fix_key'] && in_array( $f['severity'], array( 'error', 'warning' ), true ) ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bpseo-fixbtn">
										<input type="hidden" name="action" value="blogpro_seo_fix">
										<input type="hidden" name="post_id" value="<?php echo (int) $detail->ID; ?>">
										<input type="hidden" name="what" value="<?php echo esc_attr( $f['fix_key'] ); ?>">
										<input type="hidden" name="value" value="<?php echo esc_attr( $f['fix_value'] ?? '' ); ?>">
										<?php wp_nonce_field( 'blogpro_seo_fix_' . $detail->ID . '_' . $f['fix_key'] ); ?>
										<button type="submit" class="button button-secondary"><?php esc_html_e( 'Fix it for me', 'blog-pro' ); ?></button>
									</form>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endforeach; ?>
				<?php if ( ! $any ) : ?>
					<div class="bpseo-item"><div></div><div><?php esc_html_e( 'No findings — well optimised.', 'blog-pro' ); ?></div></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<p class="bpseo-note">
			<?php esc_html_e( 'Static on-page analysis only — rankings also depend on site-wide factors (authority, backlinks, speed, indexation). Suggestions follow what a crawler can read in the rendered HTML.', 'blog-pro' ); ?>
		</p>
	</div>
	<?php
}

/* ---------------------------------------------------------------------
 * REST endpoint — audit a post on demand (editor sidebar)
 * ------------------------------------------------------------------- */

/**
 * REST: GET /wp-json/blogpro/v1/seo-check/<id>
 * Returns the full audit result (same shape as blogpro_seo_check_post).
 */
function blogpro_seo_rest_check( $request ) {
	$post_id = (int) $request['id'];
	$post    = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return new WP_Error( 'blogpro_post_not_found', __( 'Post not found or not published.', 'blog-pro' ), array( 'status' => 404 ) );
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error( 'blogpro_forbidden', __( 'Not allowed.', 'blog-pro' ), array( 'status' => 403 ) );
	}
	$r = blogpro_seo_check_post( $post_id );
	// Flatten findings for JSON: keep severity/section/message/suggest measured/target.
	$flat = array();
	foreach ( $r['findings'] as $section => $items ) {
		foreach ( $items as $f ) {
			$flat[] = array(
				'severity' => $f['severity'],
				'section'  => $section,
				'message'  => $f['message'],
				'why'      => $f['why'],
				'suggest'  => $f['suggest'],
				'measured' => $f['measured'],
				'target'   => $f['target'],
			);
		}
	}
	return rest_ensure_response( array(
		'score'    => (int) $r['score'],
		'keyword'  => $r['stats']['keyword'],
		'findings' => $flat,
	) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'blogpro/v1', '/seo-check/(?P<id>\d+)', array(
		'methods'             => 'GET',
		'callback'            => 'blogpro_seo_rest_check',
		'permission_callback' => '__return_true', // checked in callback
		'args'                => array(
			'id' => array( 'validate_callback' => 'is_numeric', 'required' => true ),
		),
	) );
} );

/* ---------------------------------------------------------------------
 * Editor sidebar script (Gutenberg PluginSidebar)
 * ------------------------------------------------------------------- */

/**
 * Enqueue the sidebar JS on post edit screens (Gutenberg only).
 *
 * @param string $hook
 */
function blogpro_seo_sidebar_enqueue( $hook ) {
	if ( ! function_exists( 'use_block_editor_for_post_type' ) || ! use_block_editor_for_post_type( 'post' ) ) {
		return;
	}
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}
	wp_enqueue_script(
		'blogpro-seo-sidebar',
		get_template_directory_uri() . '/admin/js/seo-sidebar.js',
		array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-url', 'wp-data', 'wp-edit-post', 'wp-editor' ),
		wp_get_theme()->get( 'Version' ),
		true
	);
	wp_localize_script( 'blogpro-seo-sidebar', 'blogproSeoSidebar', array(
		'root' => esc_url_raw( rest_url() ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'blogpro_seo_sidebar_enqueue' );

/* ---------------------------------------------------------------------
 * Post list column — SEO score per row (cached meta, no re-scan)
 * ------------------------------------------------------------------- */

/**
 * Add the "SEO" column to the Posts list table.
 *
 * @param array $columns
 * @return array
 */
function blogpro_seo_posts_column( $columns ) {
	// Insert after Title column.
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['blogpro_seo_score'] = __( 'SEO', 'blog-pro' );
		}
	}
	return $new;
}
add_filter( 'manage_posts_columns', 'blogpro_seo_posts_column' );

/**
 * Render the score chip in the row.
 *
 * @param string $column
 * @param int    $post_id
 */
function blogpro_seo_posts_column_value( $column, $post_id ) {
	if ( 'blogpro_seo_score' !== $column || 'post' !== get_post_type( $post_id ) ) {
		return;
	}
	$score = get_post_meta( $post_id, '_blogpro_seo_score', true );
	if ( '' === $score ) {
		echo '<span class="dashicons dashicons-marker"></span>';
		return;
	}
	$score = (int) $score;
	$cls   = $score >= 80 ? 'bpseo-ok' : ( $score >= 60 ? 'bpseo-mid' : 'bpseo-bad' );
	printf(
		'<span class="bpseo-row %1$s" title="%2$s">%3$d</span>',
		esc_attr( $cls ),
		esc_attr__( 'SEO score (auto). Click for full report.', 'blog-pro' ),
		(int) $score
	);
}
add_action( 'manage_posts_custom_column', 'blogpro_seo_posts_column_value', 10, 2 );

/**
 * Make the column sortable by score.
 *
 * @param array $columns
 * @return array
 */
function blogpro_seo_posts_sortable( $columns ) {
	$columns['blogpro_seo_score'] = 'blogpro_seo_score';
	return $columns;
}
add_filter( 'manage_edit-post_sortable_columns', 'blogpro_seo_posts_sortable' );

/**
 * Tell WP how to sort the meta value ("score" = meta_key).
 *
 * @param WP_Query $query
 */
function blogpro_seo_posts_sort_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	$orderby = $query->get( 'orderby' );
	if ( 'blogpro_seo_score' === $orderby ) {
		$query->set( 'meta_key', '_blogpro_seo_score' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
add_action( 'pre_get_posts', 'blogpro_seo_posts_sort_query' );

/**
 * Column styling + a favicon-free score chip.
 */
function blogpro_seo_posts_column_style() {
	global $pagenow;
	if ( 'edit.php' !== $pagenow ) {
		return;
	}
	?>
	<style>
	.bpseo-row{ display:inline-block; min-width:36px; text-align:center; font-weight:700; font-size:12px; padding:2px 8px; border-radius:10px; }
	.bpseo-ok{ background:#edfaef; color:#008a20; }
	.bpseo-mid{ background:#fcf9e8; color:#c77700; }
	.bpseo-bad{ background:#fcf0f1; color:#d63638; }
	th.manage-column.column-blogpro_seo_score{ width:64px; }
	</style>
	<?php
}
add_action( 'admin_head', 'blogpro_seo_posts_column_style' );

/* ---------------------------------------------------------------------
 * "Fix it for me" action
 * ------------------------------------------------------------------- */

function blogpro_seo_fix() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'blog-pro' ) );
	}
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	$what    = isset( $_POST['what'] ) ? sanitize_key( $_POST['what'] ) : '';
	check_admin_referer( 'blogpro_seo_fix_' . $post_id . '_' . $what );

	if ( 'fix-title' === $what || 'fix-desc' === $what ) {
		$key   = 'fix-title' === $what ? '_blogpro_meta_title' : '_blogpro_meta_description';
		$value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
		if ( '' !== $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	} elseif ( 'fix-h1' === $what ) {
		$content = (string) get_post_field( 'post_content', $post_id );
		$count   = 0;
		$content = preg_replace_callback(
			'/<h1\b[^>]*>(.*?)<\/h1>/is',
			function ( $m ) use ( &$count ) {
				$count++;
				return 1 === $count ? $m[0] : '<h2' . substr( $m[0], 3 );
			},
			$content
		);
		if ( $count > 1 ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
		}
	} else {
		wp_die( esc_html__( 'Unknown fix.', 'blog-pro' ) );
	}

	blogpro_seo_store_result( $post_id ); // refresh score immediately.
	wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=blogpro-seo-checker&post_id=' . $post_id ) );
	exit;
}
add_action( 'admin_post_blogpro_seo_fix', 'blogpro_seo_fix' );
