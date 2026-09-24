<?php
/**
 * Per-post SEO metabox — title & description overrides.
 *
 * seo-meta.php already reads `_blogpro_meta_title` and
 * `_blogpro_meta_description` from post meta; this file adds the
 * admin UI so editors don't need the raw Custom Fields panel.
 *
 * Shows live character counters with colour-coded feedback matching
 * Google's recommended lengths (title ≤ 60 chars, description ≤ 160).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* --------------------------------------------------------------------- */
/*  Register the metabox                                                   */
/* --------------------------------------------------------------------- */

function blogpro_seo_override_metabox_register() {
	$screens = apply_filters( 'blogpro_seo_metabox_screens', array( 'post', 'page' ) );
	add_meta_box(
		'blogpro_seo_metabox',
		__( 'SEO Override', 'blog-pro' ),
		'blogpro_seo_override_metabox_render',
		$screens,
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'blogpro_seo_override_metabox_register' );

/* --------------------------------------------------------------------- */
/*  Render                                                                 */
/* --------------------------------------------------------------------- */

function blogpro_seo_override_metabox_render( $post ) {
	wp_nonce_field( 'blogpro_seo_override_metabox_save', 'blogpro_seo_override_nonce' );

	$meta_title = (string) get_post_meta( $post->ID, '_blogpro_meta_title', true );
	$meta_desc  = (string) get_post_meta( $post->ID, '_blogpro_meta_description', true );
	?>
	<style>
		.blogpro-seo-field { margin-bottom: 14px; }
		.blogpro-seo-field label { display: block; font-weight: 600; margin-bottom: 4px; }
		.blogpro-seo-field input,
		.blogpro-seo-field textarea { width: 100%; box-sizing: border-box; }
		.blogpro-seo-field textarea { height: 80px; resize: vertical; }
		.blogpro-char-counter { font-size: 12px; margin-top: 3px; }
		.blogpro-char-ok   { color: #2e7d32; }
		.blogpro-char-warn { color: #e65100; }
		.blogpro-char-over { color: #b71c1c; }
		.blogpro-seo-hint  { color: #757575; font-size: 12px; margin-top: 3px; }
	</style>

	<p class="blogpro-seo-hint">
		<?php esc_html_e( 'Leave blank to use the auto-generated value. Fill in only when you need a custom override for this specific post.', 'blog-pro' ); ?>
	</p>

	<div class="blogpro-seo-field">
		<label for="blogpro_meta_title"><?php esc_html_e( 'SEO Title', 'blog-pro' ); ?></label>
		<input
			type="text"
			id="blogpro_meta_title"
			name="blogpro_meta_title"
			value="<?php echo esc_attr( $meta_title ); ?>"
			placeholder="<?php esc_attr_e( 'Auto-generated from post title', 'blog-pro' ); ?>"
			maxlength="70"
		>
		<div class="blogpro-char-counter" id="blogpro_title_counter">
			<span id="blogpro_title_count"><?php echo mb_strlen( $meta_title ); ?></span>/60
			<?php if ( mb_strlen( $meta_title ) > 0 && mb_strlen( $meta_title ) <= 60 ): ?>
				&nbsp;✓
			<?php endif; ?>
		</div>
		<p class="blogpro-seo-hint"><?php esc_html_e( 'Recommended: up to 60 characters. Longer titles may be truncated in search results.', 'blog-pro' ); ?></p>
	</div>

	<div class="blogpro-seo-field">
		<label for="blogpro_meta_description"><?php esc_html_e( 'Meta Description', 'blog-pro' ); ?></label>
		<textarea
			id="blogpro_meta_description"
			name="blogpro_meta_description"
			placeholder="<?php esc_attr_e( 'Auto-generated from post excerpt / content', 'blog-pro' ); ?>"
			maxlength="200"
		><?php echo esc_textarea( $meta_desc ); ?></textarea>
		<div class="blogpro-char-counter" id="blogpro_desc_counter">
			<span id="blogpro_desc_count"><?php echo mb_strlen( $meta_desc ); ?></span>/160
		</div>
		<p class="blogpro-seo-hint"><?php esc_html_e( 'Recommended: 120–160 characters. Google may rewrite this but a good description improves click-through rate.', 'blog-pro' ); ?></p>
	</div>

	<script>
	(function() {
		function counter( inputId, countId, max ) {
			var el = document.getElementById( inputId );
			var ct = document.getElementById( countId );
			if ( ! el || ! ct ) return;
			function update() {
				var len = el.value.length;
				ct.textContent = len;
				var parent = ct.parentElement;
				parent.className = parent.className.replace( /blogpro-char-\w+/g, '' ).trim();
				if ( len === 0 ) return;
				if ( len <= max )        parent.classList.add( 'blogpro-char-ok' );
				else if ( len <= max + 10 ) parent.classList.add( 'blogpro-char-warn' );
				else                      parent.classList.add( 'blogpro-char-over' );
			}
			el.addEventListener( 'input', update );
			update();
		}
		counter( 'blogpro_meta_title',       'blogpro_title_count', 60 );
		counter( 'blogpro_meta_description', 'blogpro_desc_count',  160 );
	})();
	</script>
	<?php
}

/* --------------------------------------------------------------------- */
/*  Save                                                                   */
/* --------------------------------------------------------------------- */

function blogpro_seo_override_metabox_save( $post_id ) {
	// Nonce / permission checks.
	if (
		! isset( $_POST['blogpro_seo_override_nonce'] ) ||
		! wp_verify_nonce( sanitize_key( $_POST['blogpro_seo_override_nonce'] ), 'blogpro_seo_override_metabox_save' )
	) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	// Title.
	if ( isset( $_POST['blogpro_meta_title'] ) ) {
		$title = sanitize_text_field( wp_unslash( $_POST['blogpro_meta_title'] ) );
		if ( '' === $title ) {
			delete_post_meta( $post_id, '_blogpro_meta_title' );
		} else {
			update_post_meta( $post_id, '_blogpro_meta_title', $title );
		}
	}

	// Description.
	if ( isset( $_POST['blogpro_meta_description'] ) ) {
		$desc = sanitize_textarea_field( wp_unslash( $_POST['blogpro_meta_description'] ) );
		if ( '' === $desc ) {
			delete_post_meta( $post_id, '_blogpro_meta_description' );
		} else {
			update_post_meta( $post_id, '_blogpro_meta_description', $desc );
		}
	}
}
add_action( 'save_post', 'blogpro_seo_override_metabox_save' );
