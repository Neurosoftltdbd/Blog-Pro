<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
while ( have_posts() ) : the_post();
?>
<article <?php post_class( 'w-full flex flex-col items-center px-4 md:px-0 py-12' ); ?>>
	<div class="w-full max-w-7xl">
			<?php blogpro_breadcrumbs(); ?>
		</div>
	<div class="w-full max-w-4xl mx-auto py-4 md:py-12">
		<h1 class="text-2xl md:text-5xl text-center font-extrabold tracking-tight text-gray-900 py-4 md:py-8 leading-tight"><?php the_title(); ?></h1>
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="w-full rounded-lg overflow-hidden shadow-lg my-12 bg-gray-100">
				<?php echo blogpro_responsive_img( get_post_thumbnail_id(), array( 'alt' => esc_attr( get_the_title() ), 'class' => 'w-full h-full object-cover aspect-video rounded-lg', 'sizes' => '(max-width: 896px) 100vw, 896px', 'loading' => 'eager' ) ); ?>
			</div>
		<?php endif; ?>
		<div class="text-sm font-semibold text-indigo-600 tracking-widest py-4"><?php blogpro_posted_on(); ?></div>


		<div class="prose prose-lg md:prose-xl prose-indigo max-w-none mx-auto mb-16 text-gray-800 leading-relaxed [&_h1]:text-3xl [&_h1]:font-extrabold [&_h1]:my-4 [&_h1]:tracking-tight [&_h2]:text-2xl [&_h2]:font-extrabold [&_h2]:my-2 [&_h3]:text-xl [&_h3]:font-bold [&_h3]:my-4 [&_h4]:text-lg [&_h4]:font-semibold [&_h4]:my-4 [&_a]:text-indigo-600 [&_a]:underline [&_a]:underline-offset-2 [&_a:hover]:text-indigo-800 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-4 [&_li]:my-2 [&_blockquote]:border-l-4 [&_blockquote]:border-indigo-500 [&_blockquote]:bg-indigo-50 [&_blockquote]:py-3 [&_blockquote]:px-5 [&_blockquote]:my-6 [&_blockquote]:italic [&_blockquote]:text-slate-600 [&_blockquote]:rounded-r-lg [&_table]:w-full [&_table]:overflow-x-auto [&_table]:border-collapse [&_table]:my-6 [&_th]:border [&_th]:border-slate-200 [&_th]:bg-slate-50 [&_th]:px-3 [&_th]:py-2 [&_th]:text-left [&_th]:font-bold [&_td]:border [&_td]:border-slate-200 [&_td]:px-3 [&_td]:py-2 [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-xl [&_pre]:bg-slate-900 [&_pre]:text-slate-100 [&_pre]:p-4 [&_pre]:rounded-xl [&_pre]:overflow-x-auto [&_pre]:my-6 [&_code]:bg-indigo-100 [&_code]:text-indigo-700 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-sm [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_pre_code]:text-inherit [&_hr]:border-slate-200 [&_hr]:my-10 [&_figure]:my-6 [&_figcaption]:text-sm [&_figcaption]:text-slate-500 [&_figcaption]:text-center [&_figcaption]:mt-2 [&_iframe]:max-w-full [&_p]:py-4 overflow-hidden">
			<?php the_content(); ?>
		</div>
		<?php
		$tags = get_the_tags();
		if ( $tags ) :
		?>
		<div class="flex flex-wrap gap-3 mb-16">
			<?php foreach ( $tags as $tag ) : ?>
				<a href="<?php echo esc_url( get_tag_link( $tag ) ); ?>" class="px-4 py-2 bg-indigo-50 text-indigo-700 rounded-full text-sm font-semibold hover:bg-indigo-100 hover:text-indigo-900 transition-colors">
					#<?php echo esc_html( $tag->name ); ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

	<div class="flex flex-wrap items-center gap-4 my-16">
			<?php
			$share = blogpro_social_share();
			if ( $share ) :
				$settings = blogpro_social_share_settings();
			?>
			<p class="text-gray-900 font-bold shrink-0" aria-hidden="true"><?php esc_html_e( 'Share:', 'blog-pro' ); ?></p>
			<div class="flex flex-wrap items-center gap-2">
				<?php foreach ( $share as $platform => $data ) : ?>
					<a href="<?php echo esc_url( $data['url'] ); ?>" target="_blank" rel="noopener noreferrer" data-share-popup class="share-button flex items-center justify-center w-11 h-11 rounded-full text-white shadow-sm hover:scale-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-indigo-500 transition-transform duration-200 <?php echo esc_attr( $data['class'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Share on %s', 'blog-pro' ), $data['label'] ) ); ?>" title="<?php echo esc_attr( $data['label'] ); ?>">
						<svg viewBox="0 0 24 24" class="w-5 h-5 fill-current" aria-hidden="true" focusable="false"><?php echo $data['svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG path data from registered networks. ?></svg>
					</a>
				<?php endforeach; ?>
				<?php if ( ! empty( $settings['copy_link'] ) ) : ?>
					<button type="button" class="share-copy flex items-center justify-center w-11 h-11 rounded-full bg-white text-gray-700 border border-gray-200 shadow-sm hover:scale-110 hover:text-indigo-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-indigo-500 transition-transform duration-200" aria-label="<?php esc_attr_e( 'Copy link', 'blog-pro' ); ?>" title="<?php esc_attr_e( 'Copy link', 'blog-pro' ); ?>" data-share-copy data-copy-msg="<?php esc_attr_e( 'Link copied!', 'blog-pro' ); ?>" data-copy-fail="<?php esc_attr_e( 'Could not copy link', 'blog-pro' ); ?>">
						<svg viewBox="0 0 24 24" class="w-5 h-5 fill-current" aria-hidden="true" focusable="false"><path d="M16 1H4a2 2 0 00-2 2v14h2V3h12V1zm3 4H8a2 2 0 00-2 2v14a2 2 0 002 2h11a2 2 0 002-2V7a2 2 0 00-2-2zm0 16H8V7h11v14z"/></svg>
						<span class="sr-only"><?php esc_html_e( 'Copy link', 'blog-pro' ); ?></span>
					</button>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>

		<div class="flex flex-col sm:flex-row items-center gap-6 p-8 bg-gray-50 border border-gray-100 rounded-3xl mb-20 shadow-sm">
			<div class="shrink-0">
				<?php echo get_avatar( get_the_author_meta( 'ID' ), 80, '', '', array( 'class' => 'rounded-full shadow-md w-20 h-20' ) ); ?>
			</div>
			<div class="text-center sm:text-left">
				<p class="text-xl font-bold text-gray-900 mb-1"><?php the_author(); ?></p>
				<p class="text-gray-700 leading-relaxed"><?php echo esc_html( get_the_author_meta( 'description' ) ); ?></p>
			</div>
		</div>

		<?php
		$related = blogpro_related_posts( get_the_ID(), 6 );
		if ( $related->have_posts() ) :
		?>
		<div class="mb-20">
			<h2 class="text-3xl font-bold text-gray-900 mb-8 border-b-2 border-indigo-500 inline-block pb-2"><?php esc_html_e( 'Related Posts', 'blog-pro' ); ?></h2>
			<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
				<?php while ( $related->have_posts() ) : $related->the_post(); ?>
					<article class="flex flex-col bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-shadow duration-300">
						<a href="<?php the_permalink(); ?>" class="aspect-video bg-gray-100 block overflow-hidden" aria-hidden="true" tabindex="-1">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php echo blogpro_responsive_img( get_post_thumbnail_id(), array( 'alt' => esc_attr( get_the_title() ), 'class' => 'w-full h-full object-cover transform hover:scale-105 transition-transform duration-500', 'sizes' => '(max-width: 768px) 100vw, 33vw' ) ); ?>
							<?php else : ?>
								<div class="w-full h-full bg-linear-to-br from-indigo-100 to-purple-100"></div>
							<?php endif; ?>
						</a>
						<div class="p-2">
							<div class="text-sm text-gray-700 mb-1"><?php echo esc_html( get_the_date() ); ?></div>
							<h3 class="text-base font-semibold text-gray-900 leading-snug line-clamp-2"><a href="<?php the_permalink(); ?>" class="hover:text-indigo-600 transition-colors no-underline"><?php the_title(); ?></a></h3>
						</div>
					</article>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		</div>
		<?php endif; ?>

		<div class="mt-10">
			<?php if ( comments_open() || get_comments_number() ) : comments_template(); endif; ?>
		</div>
	</div>
</article>
<?php endwhile; get_footer(); ?>
