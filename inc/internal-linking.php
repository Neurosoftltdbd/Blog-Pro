<?php
/**
 * Automatic internal linking.
 *
 * Two ways to add links into post content at render time (nothing is
 * written to the database):
 *
 *   1. AUTO — matches other published posts' titles inside the content
 *      and links the first occurrence to that post. Titles shorter than
 *      12 chars or under 2 words are ignored to avoid noisy generic links.
 *
 *   2. MANUAL — per-post meta `_blogpro_il_rules`: one rule per line in
 *      `keyword or phrase => URL` form. Each rule links its first
 *      occurrence to the given URL.
 *
 * Both modes skip text already inside <a>, <pre>, <code>, <script> or
 * <style>, never link to the post itself, and stop after
 * blogpro_internal_links_max (default 3) links per post.
 *
 * NOTE: the_content only runs for classic/Gutenberg content. Elementor
 * and other page builders render their own output and bypass this filter.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Max auto+manual links added per post.
 *
 * @param int $post_id
 * @return int
 */
function blogpro_internal_links_max( $post_id ) {
	return (int) apply_filters( 'blogpro_internal_links_max', 3, $post_id );
}

/**
 * Per-post settings: auto mode + manual keyword=>URL rules.
 *
 * @param int $post_id
 * @return array{auto: bool, rules: array<int, array{phrase: string, url: string}>}
 */
function blogpro_il_settings( $post_id ) {
	$auto = get_post_meta( $post_id, '_blogpro_il_auto', true );
	if ( '' === $auto ) {
		$auto = '1'; // default: auto mode on
	}

	$rules = array();
	$raw   = (string) get_post_meta( $post_id, '_blogpro_il_rules', true );
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || false === strpos( $line, '=>' ) ) {
			continue;
		}
		list( $phrase, $url ) = array_map( 'trim', explode( '=>', $line, 2 ) );
		if ( mb_strlen( $phrase ) < 3 ) {
			continue;
		}
		$url = esc_url( $url );
		if ( ! $url ) {
			continue;
		}
		$rules[] = array(
			'phrase' => $phrase,
			'url'    => $url,
		);
	}

	return array(
		'auto'  => '1' === $auto,
		'rules' => $rules,
	);
}

/**
 * Build the auto rulebook: titles of every published post except $post_id.
 * Sorted longest-first so more specific phrases win over substrings.
 * Cached per request.
 *
 * @param int $post_id
 * @return array<int, array{phrase: string, url: string}>
 */
function blogpro_il_auto_rules( $post_id ) {
	static $titles = null;

	if ( null === $titles ) {
		global $wpdb;
		$rows = (array) $wpdb->get_results(
			"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'",
			OBJECT
		);
		$titles = array();
		foreach ( $rows as $row ) {
			$phrase = blogpro_il_normalize_phrase( $row->post_title );
			if ( ! $phrase ) {
				continue;
			}
			// Ignore very short titles: they match too generically.
			// Whitespace count, not str_word_count(), so non-Latin scripts work.
			if ( count( preg_split( '/\s+/u', $phrase, -1, PREG_SPLIT_NO_EMPTY ) ) < 2 || mb_strlen( $phrase ) < 12 ) {
				continue;
			}
			$titles[] = array(
				'id'     => (int) $row->ID,
				'phrase' => $row->post_title,
				'url'    => get_permalink( $row->ID ),
			);
		}
		usort( $titles, function ( $a, $b ) {
			return mb_strlen( $a['phrase'] ) <=> mb_strlen( $b['phrase'] );
		} );
	}

	$rules = array();
	foreach ( $titles as $rule ) {
		if ( $rule['id'] === $post_id ) {
			continue; // never link a post to itself
		}
		$rules[] = array(
			'phrase' => $rule['phrase'],
			'url'    => $rule['url'],
		);
	}
	return blogpro_il_expand_title_phrases( $rules );
}

/**
 * A full title rarely appears verbatim in body text. Generate 2-3 word
 * phrase variants from each title's content words (stopwords dropped)
 * so "Scan for internal links" actually finds matches. Longest phrases
 * win; duplicates across posts drop (first wins).
 *
 * @param array $rules base rules (phrase = full normalised title)
 * @return array<int, array{phrase: string, url: string}>
 */
function blogpro_il_expand_title_phrases( $rules ) {
	$stopwords = array_flip( explode( ' ', 'the a an and or but in on at to of for with this that it its is are was were be been as by from not so if than too very can just have has had do does did will would should could may might must into over under again further then once here there when where why how all any both each few more most other some such no nor only own same now also about which what who whom whose while during before after up down out off above below between among through you your yours our ours us we they them their theirs he she it him her' ) );

	$out  = array();
	$seen = array();
	foreach ( $rules as $rule ) {
		$tokens = preg_split( '/\s+/u', trim( $rule['phrase'] ), -1, PREG_SPLIT_NO_EMPTY );
		$words  = array();
		foreach ( $tokens as $t ) {
			$t = trim( $t, ".,;:!?\"'()[]" );
			if ( '' !== $t ) {
				$words[] = $t;
			}
		}
		if ( count( $words ) < 2 ) {
			continue;
		}

		// Build the phrase set for this title:
		//   - the first 3 content words ("Elevate Your Business")
		//   - every 2-word window containing no stopword on either side
		//     ("digital landscape", "online presence", "marketing services"…)
		// A single distinctive word in the title ("digital") becomes a
		// window partner when paired, so the user's added keyword matches.
		$clean = array();
		foreach ( array_slice( $words, 0, 6 ) as $w ) {
			$clean[] = trim( $w, ".,;:!?\"'()[]" );
		}

		$candidates = array();
		// Core phrase: first 3 title tokens, stopwords trimmed at the
		// edges only — "Best Time to Buy Appliances of 2026…" keeps its
		// "to": "Best Time to Buy". Natural phrase, not keyword soup.
		$core = array_slice( $clean, 0, 3 );
		while ( $core && isset( $stopwords[ mb_strtolower( $core[0] ) ] ) ) {
			array_shift( $core );
		}
		while ( $core && isset( $stopwords[ mb_strtolower( end( $core ) ) ] ) ) {
			array_pop( $core );
		}
		if ( count( $core ) >= 2 ) {
			$candidates[] = implode( ' ', $core );
		}

		// 2-word windows: "digital landscape", "landscape having",
		// "strong online" — any non-stopword + non-stopword adjacent pair.
		for ( $i = 0; $i + 1 < count( $clean ); $i++ ) {
			$w1 = $clean[ $i ];
			$w2 = $clean[ $i + 1 ];
			if ( ! isset( $stopwords[ mb_strtolower( $w1 ) ] ) && ! isset( $stopwords[ mb_strtolower( $w2 ) ] ) ) {
				$candidates[] = $w1 . ' ' . $w2;
			}
		}

		foreach ( $candidates as $phrase ) {
			$key = mb_strtolower( $phrase );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[] = array(
				'phrase' => $phrase,
				'url'    => $rule['url'],
			);
		}

		// Distinctive single-word phrase: content word, ≥5 chars, not a
		// stopword. Lets a standalone keyword like "digital" match even
		// when no 2-word window of the title appears in the body.
		foreach ( $clean as $w ) {
			if ( mb_strlen( $w ) >= 5 && ! isset( $stopwords[ mb_strtolower( $w ) ] ) ) {
				$key = mb_strtolower( $w );
				if ( ! isset( $seen[ $key ] ) ) {
					$seen[ $key ] = true;
					$out[] = array(
						'phrase' => $w,
						'url'    => $rule['url'],
					);
				}
			}
		}
	}
	usort( $out, function ( $a, $b ) {
		return mb_strlen( $b['phrase'] ) <=> mb_strlen( $a['phrase'] );
	} );
	return $out;
}

/**
 * Strip tags/entities/case for reliable matching.
 *
 * @param string $text
 * @return string
 */
function blogpro_il_normalize_phrase( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
	$text = preg_replace( '/\s+/u', ' ', $text );
	return mb_strtolower( trim( $text ) );
}

/**
 * the_content filter — add internal links.
 *
 * @param string $content
 * @return string
 */
function blogpro_internal_links( $content ) {
	if ( '' === trim( (string) $content ) || is_admin() || ! in_the_loop() ) {
		return $content;
	}

	$post_id  = get_the_ID();
	$settings = blogpro_il_settings( $post_id );
	$rules    = array();

	foreach ( $settings['rules'] as $rule ) {
		$rule['manual'] = true;
		$rules[]        = $rule;
	}
	if ( $settings['auto'] ) {
		foreach ( blogpro_il_auto_rules( $post_id ) as $rule ) {
			$rule['manual'] = false;
			$rules[]        = $rule;
		}
	}

	if ( ! $rules ) {
		return $content;
	}

	// Longest phrases first so "SEO Tips for WordPress" wins over "SEO Tips";
	// manual rules beat auto rules of the same length.
	usort( $rules, function ( $a, $b ) {
		$len = mb_strlen( $b['phrase'] ) <=> mb_strlen( $a['phrase'] );
		return 0 !== $len ? $len : ( (int) $b['manual'] <=> (int) $a['manual'] );
	} );

	return blogpro_il_apply_links( $content, $rules, blogpro_internal_links_max( $post_id ) );
}
add_filter( 'the_content', 'blogpro_internal_links', 15 );

/**
 * Replace first occurrence of each phrase from $rules with a marker token,
 * then swap markers for real anchors. Text inside <a>/<pre>/<code>/<script>/
 * <style> and HTML comments is never touched; overlapping phrases cannot
 * double-link because markers break any later matches.
 *
 * @param string $content
 * @param array  $rules      list of array{phrase: string, url: string}
 * @param int    $max_links
 * @return string
 */
function blogpro_il_apply_links( $content, $rules, $max_links ) {
	$linked    = array(); // normalized URLs already linked in this content
	$markers   = array(); // token => anchor HTML
	$segments  = preg_split( '/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	$depth     = array(); // tag stack to track never-link tags

	foreach ( $segments as $i => $segment ) {
		if ( 0 === ( $i % 2 ) ) {
			// Text segment (only odd indexes are tags).
			if ( empty( $depth ) ) {
				foreach ( $rules as $idx => $rule ) {
					if ( count( $markers ) >= $max_links ) {
						break 2;
					}
					$key = $rule['url'];
					if ( isset( $linked[ $key ] ) ) {
						continue; // already linked to this URL
					}
					$needle = blogpro_il_normalize_phrase( $rule['phrase'] );
					if ( '' === $needle ) {
						continue;
					}
					$pattern = '/' . preg_quote( $needle, '/' ) . '/iu';
					if ( preg_match( $pattern, $segment ) ) {
						$token = '{{BPIL_' . $i . '_' . $idx . '}}';
						$segment = preg_replace( $pattern, $token, $segment, 1 );
						$segments[ $i ] = $segment;
						$markers[ $token ] = sprintf(
							'<a href="%s" class="bpil-link">%s</a>',
							esc_url( $rule['url'] ),
							esc_html( $rule['phrase'] )
						);
						$linked[ $key ] = true;
					}
				}
			}
			continue;
		}

		// Track open state of skip tags.
		if ( preg_match( '#^<!--#', $segment ) ) {
			if ( false === strpos( $segment, '-->' ) ) {
				$depth['comment'] = true;
			}
			continue;
		}
		if ( 0 === strpos( $segment, '-->' ) && isset( $depth['comment'] ) ) {
			unset( $depth['comment'] );
			continue;
		}
		if ( preg_match( '#^</#', $segment ) ) {
			$tag = strtolower( preg_replace( '/[^a-z]/', '', $segment ) );
			if ( isset( $depth[ $tag ] ) ) {
				unset( $depth[ $tag ] );
			}
			continue;
		}
		if ( preg_match( '#^<([a-z][a-z0-9]*)#i', $segment, $m ) ) {
			$tag = strtolower( $m[1] );
			if ( in_array( $tag, array( 'a', 'pre', 'code', 'script', 'style' ), true ) ) {
				$depth[ $tag ] = true;
			}
		}
	}

	return strtr( implode( '', $segments ), $markers );
}

/* ---------------------------------------------------------------------
 * On-demand link optimizer — scan content, preview candidate links,
 * apply them to the post (persisted).
 * ------------------------------------------------------------------- */

/**
 * Scan content for words/phrases that match another published post's
 * title and are not already inside a link/skip-tag. Returns candidates
 * in content order (first occurrence per rule, per URL).
 *
 * @param string $content
 * @param array  $rules  blogpro_il_auto_rules() output
 * @param int    $max    max links to suggest
 * @return array<int, array{offset: int, len: int, phrase: string, url: string, text: string}>
 */
function blogpro_il_scan_content( $content, $rules, $max ) {
	$linked_urls = array();
	if ( preg_match_all( '/\bhref\s*=\s*(["\'])(.*?)\1/i', $content, $m ) ) {
		foreach ( $m[2] as $href ) {
			$linked_urls[ esc_url( $href ) ] = true;
		}
	}

	$found     = array();
	$used_rules = array();
	$segments  = preg_split( '/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	$depth     = array();
	$offset    = 0;

	foreach ( $segments as $i => $segment ) {
		if ( 0 === ( $i % 2 ) ) {
			if ( empty( $depth ) ) {
				foreach ( $rules as $rule ) {
					$url = esc_url( $rule['url'] );
					if ( isset( $used_rules[ $rule['phrase'] ] ) || isset( $linked_urls[ $url ] ) ) {
						continue;
					}
					if ( $max > 0 && count( $found ) >= $max ) {
						break 2;
					}
					if ( preg_match( '/' . preg_quote( $rule['phrase'], '/' ) . '/iu', $segment, $mm, PREG_OFFSET_CAPTURE ) ) {
						// Case-insensitive: a title "Best Time to Buy" must
						// match body text "best time to buy".
						$after = $offset + (int) $mm[0][1] + strlen( $mm[0][0] );
						// No candidate may straddle a tag boundary (segment is pure text, so safe).
						$found[] = array(
							'offset' => $offset + (int) $mm[0][1],
							'len'    => strlen( $mm[0][0] ),
							'phrase' => $rule['phrase'],
							'url'    => $url,
							'text'   => $mm[0][0],
						);
						$used_rules[ $rule['phrase'] ] = true;
					}
				}
			}
			$offset += strlen( $segment );
			continue;
		}

		// Tag bookkeeping mirrors blogpro_il_apply_links().
		if ( preg_match( '#^<!--#', $segment ) ) {
			if ( false === strpos( $segment, '-->' ) ) {
				$depth['comment'] = true;
			}
			$offset += strlen( $segment );
			continue;
		}
		if ( 0 === strpos( $segment, '-->' ) && isset( $depth['comment'] ) ) {
			unset( $depth['comment'] );
			$offset += strlen( $segment );
			continue;
		}
		if ( preg_match( '#^</#', $segment ) ) {
			$tag = strtolower( preg_replace( '/[^a-z]/', '', $segment ) );
			unset( $depth[ $tag ] );
			$offset += strlen( $segment );
			continue;
		}
		if ( preg_match( '#^<([a-z][a-z0-9]*)#i', $segment, $mt ) ) {
			$tag = strtolower( $mt[1] );
			if ( in_array( $tag, array( 'a', 'pre', 'code', 'script', 'style' ), true ) ) {
				$depth[ $tag ] = true;
			}
		}
		$offset += strlen( $segment );
	}

	return $found;
}

/**
 * AJAX: scan a post's content for internal-link candidates.
 */
function blogpro_il_scan_ajax() {
	check_ajax_referer( 'blogpro_il_scan', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( __( 'Permission denied.', 'blog-pro' ), 403 );
	}

	$content = (string) get_post_field( 'post_content', $post_id );
	$rules   = blogpro_il_auto_rules( $post_id );
	$cands   = blogpro_il_scan_content( $content, $rules, blogpro_internal_links_max( $post_id ) );

	if ( ! $cands ) {
		wp_send_json_success( array( 'candidates' => array(), 'message' => __( 'No link candidates found. Add more posts or longer matching phrases.', 'blog-pro' ) ) );
	}
	foreach ( $cands as $k => $c ) {
		$title = get_the_title( (int) url_to_postid( $c['url'] ) );
		$cands[ $k ]['title'] = $title ? $title : $c['phrase'];
	}
	wp_send_json_success( array( 'candidates' => $cands ) );
}
add_action( 'wp_ajax_blogpro_il_scan', 'blogpro_il_scan_ajax' );

/**
 * AJAX: apply chosen candidates (offsets + url list) to the content.
 */
function blogpro_il_apply_ajax() {
	check_ajax_referer( 'blogpro_il_apply', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( __( 'Permission denied.', 'blog-pro' ), 403 );
	}

	$content    = (string) get_post_field( 'post_content', $post_id );
	$positions  = isset( $_POST['positions'] ) ? json_decode( wp_unslash( $_POST['positions'] ), true ) : array();
	if ( ! is_array( $positions ) || ! $positions ) {
		wp_send_json_error( __( 'Nothing selected.', 'blog-pro' ) );
	}

	// Re-scan and rebuild offsets; insert from the end backwards.
	$rules = blogpro_il_auto_rules( $post_id );
	$all   = blogpro_il_scan_content( $content, $rules, 100 );
	$wanted = array();
	foreach ( $all as $c ) {
		if ( in_array( (int) $c['offset'], array_map( 'intval', array_keys( $positions ) ), true ) ) {
			$wanted[] = $c;
		}
	}
	if ( ! $wanted ) {
		wp_send_json_error( __( 'Selected links are no longer available (content changed).', 'blog-pro' ) );
	}

	// Insert right-to-left: later replacements must not shift the offsets
	// of earlier ones. Sort by offset descending — candidates arrive in
	// rule (phrase length) order, not document order.
	usort( $wanted, function ( $a, $b ) {
		return (int) $b['offset'] <=> (int) $a['offset'];
	} );

	$applied = 0;
	foreach ( $wanted as $c ) {
		$anchor = sprintf(
			'<a href="%s" class="bpil-link">%s</a>',
			esc_url( $c['url'] ),
			esc_html( $c['text'] )
		);
		$content = substr_replace( $content, $anchor, (int) $c['offset'], (int) $c['len'] );
		$applied++;
	}

	wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
	wp_send_json_success( array( 'applied' => $applied ) );
}
add_action( 'wp_ajax_blogpro_il_apply', 'blogpro_il_apply_ajax' );

/* ---------------------------------------------------------------------
 * Post editor metabox (manual keywords + auto toggle)
 * ------------------------------------------------------------------- */
function blogpro_il_add_metabox() {
	// Classic editor (and the bottom "Meta" panel of the block editor).
	add_meta_box(
		'blogpro-internal-linking',
		__( 'Internal Linking', 'blog-pro' ),
		'blogpro_il_render_metabox',
		'post',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'blogpro_il_add_metabox' );

function blogpro_il_render_metabox( $post ) {
	wp_nonce_field( 'blogpro_il_save', 'blogpro_il_nonce' );
	$settings = blogpro_il_settings( $post->ID );
	?>
	<p>
		<label>
			<input type="checkbox" name="blogpro_il_auto" value="1" <?php checked( $settings['auto'] ); ?>>
			<?php esc_html_e( 'Auto-link matching post titles', 'blog-pro' ); ?>
		</label>
	</p>
	<p class="description"><?php esc_html_e( 'One rule per line: keyword or phrase => URL', 'blog-pro' ); ?></p>
	<textarea name="blogpro_il_rules" rows="5" class="widefat" placeholder="WordPress SEO => /wordpress-seo-guide/"><?php echo esc_textarea( (string) get_post_meta( $post->ID, '_blogpro_il_rules', true ) ); ?></textarea>

	<hr style="margin:12px 0;">
	<p>
		<button type="button" id="blogpro-il-scan" class="button"><?php esc_html_e( 'Scan for internal links', 'blog-pro' ); ?></button>
	</p>
	<p id="blogpro-il-status" class="description" style="margin:6px 0;"></p>
	<div id="blogpro-il-results" style="max-height:260px;overflow:auto;display:none;">
		<ul id="blogpro-il-list" style="margin:0;list-style:none;"></ul>
		<p>
			<button type="button" id="blogpro-il-apply" class="button button-primary" disabled><?php esc_html_e( 'Apply selected', 'blog-pro' ); ?></button>
		</p>
	</div>
	<script>
	(function () {
		var scan = document.getElementById('blogpro-il-scan');
		var status = document.getElementById('blogpro-il-status');
		var results = document.getElementById('blogpro-il-results');
		var list = document.getElementById('blogpro-il-list');
		var apply = document.getElementById('blogpro-il-apply');
		var candidates = [];
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'blogpro_il_scan' ) ); ?>;
		var applyNonce = <?php echo wp_json_encode( wp_create_nonce( 'blogpro_il_apply' ) ); ?>;
		var postId = <?php echo (int) $post->ID; ?>;
		var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

		function request(action, n, body, done) {
			var b = new URLSearchParams();
			b.set('action', action);
			b.set('nonce', n);
			b.set('post_id', postId);
			for (var k in body) b.set(k, body[k]);
			fetch(ajax, { method: 'POST', credentials: 'same-origin', body: b })
				.then(function (r) { return r.json(); })
				.then(function (j) { done(j); })
				.catch(function () { status.textContent = 'Request error.'; });
		}

		scan.addEventListener('click', function () {
			scan.disabled = true;
			status.textContent = 'Scanning for linked posts…';
			request('blogpro_il_scan', nonce, {}, function (j) {
				scan.disabled = false;
				if (!j.success) { status.textContent = j.data || 'Scan failed.'; return; }
				candidates = j.data.candidates || [];
				if (!candidates.length) { status.textContent = j.data.message || 'No candidates.'; results.style.display = 'none'; return; }
				status.textContent = candidates.length + ' candidate' + (candidates.length === 1 ? '' : 's') + ' found. Select the ones to add:';
				list.innerHTML = '';
				candidates.forEach(function (c, i) {
					var li = document.createElement('li');
					li.style.cssText = 'margin:6px 0;';
					var cb = document.createElement('input');
					cb.type = 'checkbox';
					cb.checked = true;
					cb.dataset.offset = c.offset;
					cb.style.marginRight = '6px';
					li.appendChild(cb);
					var label = document.createElement('label');
					label.appendChild(document.createTextNode('"'+c.text+'" → '+c.title));
					li.appendChild(label);
					list.appendChild(li);
				});
				apply.disabled = false;
				results.style.display = 'block';
			});
		});

		apply.addEventListener('click', function () {
			var positions = {};
			list.querySelectorAll('input[type=checkbox]:checked').forEach(function (cb) {
				positions[cb.dataset.offset] = true;
			});
			if (!Object.keys(positions).length) { status.textContent = 'Select at least one candidate.'; return; }
			apply.disabled = true;
			status.textContent = 'Applying…';
			request('blogpro_il_apply', applyNonce, { positions: JSON.stringify(positions) }, function (j) {
				apply.disabled = false;
				if (!j.success) { status.textContent = j.data && j.data.message ? j.data.message : 'Apply failed.'; return; }
				status.textContent = j.data.applied + ' internal link' + (j.data.applied === 1 ? '' : 's') + ' added to the post (saved to database). Reload the editor page to see them in the content.';
				results.style.display = 'none';
				list.innerHTML = '';
				candidates = [];
			});
		});
	})();
	</script>
	<?php
}

function blogpro_il_save( $post_id ) {
	if ( ! isset( $_POST['blogpro_il_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['blogpro_il_nonce'] ) ), 'blogpro_il_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['blogpro_il_auto'] ) ) {
		update_post_meta( $post_id, '_blogpro_il_auto', '1' );
	} else {
		// Store explicit '0' so the setting persists; no meta would
		// fall back to the default (on).
		update_post_meta( $post_id, '_blogpro_il_auto', '0' );
	}

	$raw = isset( $_POST['blogpro_il_rules'] ) ? sanitize_textarea_field( wp_unslash( $_POST['blogpro_il_rules'] ) ) : '';
	if ( '' === trim( $raw ) ) {
		delete_post_meta( $post_id, '_blogpro_il_rules' );
	} else {
		update_post_meta( $post_id, '_blogpro_il_rules', $raw );
	}
}
add_action( 'save_post', 'blogpro_il_save' );
