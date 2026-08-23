<?php
/**
 * FAQ block registration + legacy per-post integration.
 *
 * Registers the global block `blog-pro/faq` from metadata in
 * blocks/faq/block.json. The block is dynamic: render.php renders
 * server-side, index.js is the editor-only script.
 *
 * Also keeps the per-post FAQ metabox used by single.php for legacy posts:
 * when a FAQ metabox is saved and the post content does NOT already contain
 * a blog-pro/faq block, one is appended with the metabox items. The block
 * itself is fully usable in the editor/post content independent of this.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the block from metadata.
 */
function blogpro_register_faq_block() {
	register_block_type( BLOGPRO_DIR . '/blocks/faq' );
}
add_action( 'init', 'blogpro_register_faq_block' );

/* ---------------------------------------------------------------------
 * Legacy per-post FAQ metabox (Question => Answer lines)
 * ------------------------------------------------------------------- */
function blogpro_faq_parse( $raw ) {
	$items = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || false === strpos( $line, '=>' ) ) {
			continue;
		}
		list( $question, $answer ) = array_map( 'trim', explode( '=>', $line, 2 ) );
		if ( '' === $question || '' === $answer ) {
			continue;
		}
		$items[] = array(
			'question' => $question,
			'answer'   => $answer,
		);
	}
	return $items;
}

/**
 * Get FAQ items for a post (stored in `_blogpro_faq` meta).
 *
 * @param int $post_id
 * @return array<int, array{question: string, answer: string}>
 */
function blogpro_faq_for_post( $post_id ) {
	return blogpro_faq_parse( (string) get_post_meta( $post_id, '_blogpro_faq', true ) );
}

/**
 * Render the FAQ block for a post (legacy single.php caller).
 *
 * @param int $post_id
 */
function blogpro_faq_block( $post_id = null ) {
	if ( null === $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id ) {
		return;
	}
	$items = blogpro_faq_for_post( $post_id );
	if ( ! $items ) {
		return;
	}
	echo do_blocks( '<!-- wp:blog-pro/faq {"title":"' . esc_attr__( 'Frequently Asked Questions', 'blog-pro' ) . '","items":' . wp_json_encode( $items ) . '} /-->' );
}

function blogpro_faq_add_metabox() {
	add_meta_box(
		'blogpro-faq',
		__( 'FAQ', 'blog-pro' ),
		'blogpro_faq_render_metabox',
		'post',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'blogpro_faq_add_metabox' );

function blogpro_faq_render_metabox( $post ) {
	wp_nonce_field( 'blogpro_faq_save', 'blogpro_faq_nonce' );
	?>
	<p class="description"><?php esc_html_e( 'Legacy field: one item per line, Question => Answer. Rendered as a blog-pro/faq block at the end of this post. Prefer adding the FAQ block directly in the content.', 'blog-pro' ); ?></p>
	<textarea name="blogpro_faq" rows="6" class="widefat" placeholder="<?php esc_attr_e( "How do I get started? => Read the guide below.", 'blog-pro' ); ?>"><?php echo esc_textarea( (string) get_post_meta( $post->ID, '_blogpro_faq', true ) ); ?></textarea>
	<?php
}

function blogpro_faq_save( $post_id ) {
	if ( ! isset( $_POST['blogpro_faq_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['blogpro_faq_nonce'] ) ), 'blogpro_faq_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$raw = isset( $_POST['blogpro_faq'] ) ? sanitize_textarea_field( wp_unslash( $_POST['blogpro_faq'] ) ) : '';
	if ( '' === trim( $raw ) ) {
		delete_post_meta( $post_id, '_blogpro_faq' );
		return;
	}
	update_post_meta( $post_id, '_blogpro_faq', $raw );

	// If the post content has no FAQ block yet, append one with these items.
	$content = get_post_field( 'post_content', $post_id );
	if ( false === strpos( (string) $content, '<!-- wp:blog-pro/faq' ) ) {
		$items  = blogpro_faq_parse( $raw );
		$block  = '<!-- wp:blog-pro/faq {"title":"' . esc_attr__( 'Frequently Asked Questions', 'blog-pro' ) . '","items":' . wp_json_encode( $items ) . '} /-->';
		wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => $content . "\n\n" . $block,
		) );
	}
}
add_action( 'save_post', 'blogpro_faq_save' );
