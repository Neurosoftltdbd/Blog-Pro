<?php
/**
 * Default page template.
 *
 * If the page has a block or PHP template selected in the editor,
 * that template is rendered instead. With no template selected,
 * the default layout below is used.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$page_id  = get_queried_object_id();
$selected = $page_id ? get_page_template_slug( $page_id ) : '';

if ( $selected ) {
	global $_wp_current_template_id, $_wp_current_template_content;

	// Load a block template file directly so any Site Editor DB
	// customization does not replace the template shipped with the theme.
	$template_file = _get_block_template_file( 'wp_template', $selected );

	if ( $template_file && ! empty( $template_file['path'] ) ) {
		$block_template = _build_block_template_result_from_file( $template_file, 'wp_template' );

		$_wp_current_template_id      = $block_template->id;
		$_wp_current_template_content = $block_template->content;

		get_header();
		echo get_the_block_template_html();
		get_footer();
		return;
	}

	// Otherwise include a PHP page template if one is selected.
	if ( 0 === validate_file( $selected ) ) {
		$php_template = locate_template( array( $selected ) );
		if ( $php_template ) {
			include $php_template;
			return;
		}
	}
}

get_header();
while ( have_posts() ) : the_post();
?>
<div class="max-w-7xl mx-auto px-4 py-12">
	<?php blogpro_breadcrumbs(); ?>
	<h1 class="text-3xl md:text-4xl font-bold text-gray-900 mt-8 mb-6 text-center"><?php the_title(); ?></h1>
	<div class="prose prose-lg prose-indigo max-w-none text-gray-800 leading-relaxed"><?php the_content(); ?></div>
</div>
<?php endwhile; get_footer(); ?>
