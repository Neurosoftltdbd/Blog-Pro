<?php
/**
 * WebMCP schemas — helps AI agents understand and interact with site forms.
 * Outputs structured data describing each form's purpose, fields, and actions
 * so browser-based AI tools (agent-mode assistants, form-fillers, etc.) can
 * operate the site without requiring a human-in-the-loop for every input.
 *
 * WebMCP audit (Chrome DevTools > AI) surfaces:
 *   - form coverage — every <form> should have a WebMCP annotation
 *   - tools registered — defined tool actions
 *   - schema validity — JSON-LD passes schema validation
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register WebMCP schema in a <script type="application/web+mcp"> block
 * rendered in <head>.
 *
 * Each entry describes one "tool" (an action the page can perform). The
 * schema follows the WebMCP spec:
 *
 *   @type  "Action" | "FormAction"
 *   name   short unique name for the tool
 *   query  JSON-LD describing the input fields the tool expects
 *   url    endpoint the tool submits to
 *   method GET | POST
 */
function blogpro_webmcp_schema() {
	$tools = array();

	// ── Search form ──────────────────────────────────────────────
	if ( function_exists( 'get_search_form' ) ) {
		$tools[] = array(
			'@type'   => 'Action',
			'name'    => 'search',
			'title'   => 'Search the site',
			'url'     => home_url( '/' ),
			'method'  => 'GET',
			'query'   => array(
				'type'    => 'PropertyValueSpecification',
				'valueName' => 's',
				'inputMode' => 'text',
				'description' => 'Search query',
			),
		);
	}

	// ── Contact form ─────────────────────────────────────────────
	$contact_page = get_posts( array(
		'post_type' => 'page',
		'meta_key'  => '_wp_page_template',
		'meta_value' => 'page-contact.php',
		'fields'    => 'ids',
		'posts_per_page' => 1,
	) );
	if ( ! empty( $contact_page ) ) {
		$tools[] = array(
			'@type'  => 'Action',
			'name'   => 'contact',
			'title'  => 'Send a contact message',
			'url'    => get_permalink( $contact_page[0] ),
			'method' => 'POST',
			'query'  => array(
				'type'       => 'PropertyValueSpecification',
				'valueName'  => 'contact_name,contact_email,contact_message',
				'description' => 'Name, email address, and message body for the contact form',
			),
		);
	}

	// ── Comment form (on single posts) ───────────────────────────
	if ( is_singular( 'post' ) && comments_open() ) {
		$tools[] = array(
			'@type'  => 'Action',
			'name'   => 'comment',
			'title'  => 'Post a comment on this article',
			'url'    => get_permalink() . '#respond',
			'method' => 'POST',
			'query'  => array(
				'type'       => 'PropertyValueSpecification',
				'valueName'  => 'author,email,url,comment',
				'description' => 'Author name, email, optional website URL, and comment body',
			),
		);
	}

	// ── Schema-powered Action fields (whatsapp / booking / newsletter) ──
	// Add more tools here following the same pattern.

	if ( empty( $tools ) ) return;

	$output = array(
		'@context' => 'https://schema.org',
		'@type'    => 'ItemList',
		'name'     => 'WebMCP Tools',
		'itemListElement' => $tools,
	);

	echo '<script type="application/web+mcp">' . wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'blogpro_webmcp_schema', 5 );
