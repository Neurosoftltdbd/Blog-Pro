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
	<div class="entry-content prose prose-lg md:prose-xl prose-indigo max-w-none mx-auto mb-16 text-gray-800 leading-relaxed [&_h1]:text-3xl [&_h1]:font-extrabold [&_h1]:my-4 [&_h1]:tracking-tight [&_h2]:text-2xl [&_h2]:font-extrabold [&_h2]:my-2 [&_h3]:text-xl [&_h3]:font-bold [&_h3]:my-4 [&_h4]:text-lg [&_h4]:font-semibold [&_h4]:my-4 [&_a]:text-indigo-600 [&_a:hover]:text-indigo-800 [&_a]:no-underline [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-4 [&_li]:my-2 [&_blockquote]:border-l-4 [&_blockquote]:border-indigo-500 [&_blockquote]:bg-indigo-50 [&_blockquote]:py-3 [&_blockquote]:px-5 [&_blockquote]:my-6 [&_blockquote]:italic [&_blockquote]:text-slate-600 [&_blockquote]:rounded-r-lg [&_table]:w-full [&_table]:border-collapse [&_table]:my-6 [&_table]:text-sm [&_table]:min-w-full [&_th]:border [&_th]:border-slate-200 [&_th]:bg-slate-50 [&_th]:px-3 [&_th]:py-2 [&_th]:text-left [&_th]:font-bold [&_td]:border [&_td]:border-slate-200 [&_td]:px-3 [&_td]:py-2 [&_tbody_tr]:even:bg-slate-50 [&_table]:overflow-x-auto [&_dl]:my-4 [&_dt]:font-bold [&_dt]:mt-2 [&_dd]:ml-4 [&_dd]:mb-1 [&_mark]:bg-yellow-200 [&_mark]:px-1 [&_kbd]:bg-slate-100 [&_kbd]:border [&_kbd]:border-slate-300 [&_kbd]:rounded [&_kbd]:px-1 [&_kbd]:text-sm [&_kbd]:font-mono [&_sup]:text-xs [&_sup]:align-super [&_sub]:text-xs [&_sub]:align-sub [&_q]:italic [&_q]:text-slate-600 [&_cite]:italic [&_cite]:text-slate-600 [&_abbr]:underline [&_abbr]:decoration-dotted [&_time]:text-slate-600 [&_del]:line-through [&_del]:text-slate-400 [&_ins]:underline [&_ins]:text-green-700 [&_details]:my-4 [&_details]:border [&_details]:border-slate-200 [&_details]:rounded-lg [&_details]:p-3 [&_summary]:font-bold [&_summary]:cursor-pointer [&_video]:w-full [&_video]:rounded-xl [&_video]:my-6 [&_audio]:w-full [&_audio]:my-4 [&_caption]:text-sm [&_caption]:text-slate-500 [&_caption]:text-center [&_caption]:mt-2 [&_strong]:font-bold [&_em]:italic [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-xl [&_pre]:bg-slate-900 [&_pre]:text-slate-100 [&_pre]:p-4 [&_pre]:rounded-xl [&_pre]:overflow-x-auto [&_pre]:my-6 [&_code]:bg-indigo-100 [&_code]:text-indigo-700 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-sm [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_pre_code]:text-inherit [&_hr]:border-slate-200 [&_hr]:my-10 [&_figure]:my-6 [&_figcaption]:text-sm [&_figcaption]:text-slate-500 [&_figcaption]:text-center [&_figcaption]:mt-2 [&_iframe]:rounded-xl [&_p]:py-4"><?php the_content(); ?></div>
</div>
<?php endwhile; get_footer(); ?>
