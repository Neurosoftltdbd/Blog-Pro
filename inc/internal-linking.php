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
	return $rules;
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
 * Post editor metabox (manual keywords + auto toggle)
 * ------------------------------------------------------------------- */
function blogpro_il_add_metabox() {
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
	<textarea name="blogpro_il_rules" rows="5" class="widefat" placeholder="wordpress seo => /wordpress-seo-guide/"><?php echo esc_textarea( (string) get_post_meta( $post->ID, '_blogpro_il_rules', true ) ); ?></textarea>
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
