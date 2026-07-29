<?php if ( ! defined( 'ABSPATH' ) ) exit; ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'bg-white' ); ?>>
<?php wp_body_open(); ?>
<a class="fixed left-[-9999px] top-0 bg-gray-900 text-white px-4 py-2.5 z-999 focus:left-2.5 focus:top-2.5 no-underline" href="#main"><?php esc_html_e( 'Skip to content', 'blog-pro' ); ?></a>

<header class="w-full h-15 bg-white/80 backdrop-blur-md border-b border-gray-100 sticky top-0 z-50">
	<div class="w-full max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center py-4">
		<div class="flex justify-between w-full md:w-fit px-4 md:px-0">
			<div class="site-branding text-xl font-bold tracking-tighter text-indigo-600 hover:text-indigo-800 transition-colors">
			<?php if ( has_custom_logo() ) : the_custom_logo(); else : ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" class="text-inherit no-underline"><?php bloginfo( 'name' ); ?></a>
			<?php endif; ?>
			</div>

			<button class="nav-toggle md:hidden p-2 text-gray-600 hover:text-indigo-600 focus:outline-none cursor-pointer" aria-label="<?php esc_attr_e( 'Toggle menu', 'blog-pro' ); ?>" aria-expanded="false" aria-controls="mobile-menu">
				<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
			</button>
		</div>
<!-- Mobile menu -->
		<nav class="mobile-nav w-full bg-gray-50 border-b border-gray-100 hidden shadow-md p-0 mt-2" id="mobile-menu" aria-label="<?php esc_attr_e( 'Primary', 'blog-pro' ); ?>">
			<?php
			$menu_classes = 'flex flex-col bg-gray-50 p-0 pl-0 [&_li:hover]:bg-gray-600 [&_li:hover]:text-white [&_li]:py-2 [&_li]:px-4 [&_li]:transition-all [&_li]:easy-in-out [&_li]:duration-500 [&_li]:w-full  [&_a]:w-full';
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => $menu_classes, 'depth' => 1 ) );
			} else {
				echo '<ul class="' . esc_attr($menu_classes) . '">';
				echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'blog-pro' ) . '</a></li>';
				echo '<li><a href="' . esc_url( home_url( '/blog/' ) ) . '">' . esc_html__( 'Blog', 'blog-pro' ) . '</a></li>';
				echo '<li><a href="' . esc_url( home_url( '/about/' ) ) . '">' . esc_html__( 'About', 'blog-pro' ) . '</a></li>';
				echo '<li><a href="' . esc_url( home_url( '/contact/' ) ) . '">' . esc_html__( 'Contact', 'blog-pro' ) . '</a></li></ul>';
			}
			?>
		</nav>
<!-- Desktop menu -->
		<nav class="primary-nav hidden md:flex" id="primary-menu" aria-label="<?php esc_attr_e( 'Primary', 'blog-pro' ); ?>">
			<?php
			$menu_classes = 'flex flex-col md:flex-row md:space-x-8 space-y-4 md:space-y-0 w-full text-gray-700 font-medium [&_a]:transition-colors [&_a:hover]:text-indigo-600';
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => $menu_classes, 'depth' => 1 ) );
			} else {
				echo '<ul class="' . esc_attr($menu_classes) . '">';
				echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'blog-pro' ) . '</a></li>';
				echo '<li><a href="' . esc_url( home_url( '/blog/' ) ) . '">' . esc_html__( 'Blog', 'blog-pro' ) . '</a></li>';
				echo '<li><a href="' . esc_url( home_url( '/about/' ) ) . '">' . esc_html__( 'About', 'blog-pro' ) . '</a></li>';
				echo '<li><a href="' . esc_url( home_url( '/contact/' ) ) . '">' . esc_html__( 'Contact', 'blog-pro' ) . '</a></li></ul>';
			}
			?>
		</nav>
	</div>
</header>

<main class="w-full min-h-screen" id="main">
