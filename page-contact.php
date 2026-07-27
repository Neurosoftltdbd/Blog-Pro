<?php
/**
 * Template Name: Contact Page
 * Assign this to your "Contact" page in the WordPress editor.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
$status = blogpro_contact_form_status();
while ( have_posts() ) : the_post();
?>
<div class="max-w-2xl mx-auto px-4 py-12">
	<?php blogpro_breadcrumbs(); ?>
	<h1 class="text-3xl md:text-4xl font-bold text-gray-900 mt-8 mb-6"><?php the_title(); ?></h1>
	<div class="prose prose-lg prose-indigo max-w-none text-gray-800 leading-relaxed mb-8"><?php the_content(); ?></div>

	<?php if ( 'success' === $status ) : ?>
		<div class="p-4 mb-6 bg-green-50 border border-green-200 text-green-700 rounded-xl"><?php esc_html_e( 'Thanks — your message has been sent.', 'blog-pro' ); ?></div>
	<?php elseif ( 'error' === $status ) : ?>
		<div class="p-4 mb-6 bg-red-50 border border-red-200 text-red-700 rounded-xl"><?php esc_html_e( 'Something went wrong. Please check the fields and try again.', 'blog-pro' ); ?></div>
	<?php endif; ?>

	<form class="space-y-6" method="post" action="">
		<?php wp_nonce_field( 'blogpro_contact', 'blogpro_contact_nonce' ); ?>
		<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
			<p>
				<label for="contact_name" class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Name', 'blog-pro' ); ?></label>
				<input type="text" id="contact_name" name="contact_name" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
			</p>
			<p>
				<label for="contact_email" class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Email', 'blog-pro' ); ?></label>
				<input type="email" id="contact_email" name="contact_email" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
			</p>
		</div>
		<p>
			<label for="contact_message" class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Message', 'blog-pro' ); ?></label>
			<textarea id="contact_message" name="contact_message" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all min-h-35"></textarea>
		</p>
		<p class="hidden" aria-hidden="true">
			<label for="website_url">Website</label>
			<input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
		</p>
		<button type="submit" name="blogpro_contact_submit" value="1" class="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition-colors cursor-pointer"><?php esc_html_e( 'Send Message', 'blog-pro' ); ?></button>
	</form>
</div>
<?php endwhile; get_footer(); ?>
