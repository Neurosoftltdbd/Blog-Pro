<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Custom nav menu walker for Blogpro.
 *
 * Renders a responsive menu: desktop hover dropdowns (Tailwind's
 * group/group-hover utilities) plus a separate mobile toggle button
 * for touch devices, since hover states don't translate to touch.
 */
class Blogpro_Nav_Walker extends Walker_Nav_Menu {

	/** Base classes applied to every <ul> submenu, regardless of depth. */
	const SUBMENU_BASE_CLASSES = 'sub-menu';

	/** Top-level (depth 0) submenus render as absolutely-positioned desktop dropdowns. */
	const SUBMENU_DESKTOP_CLASSES = 'absolute top-full left-0 mt-1 w-fit origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black/5 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50';

	/** Nested (depth > 0) submenus render inline, toggled via JS on mobile. */
	const SUBMENU_NESTED_CLASSES = 'hidden w-full';

	/** Shared chevron path data, reused for both the desktop and mobile toggle icons. */
	const CHEVRON_PATH = 'M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z';

	/**
	 * Opens a new submenu <ul>, with different styling for the top-level
	 * (desktop hover dropdown) versus nested levels (mobile accordion).
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$indent = str_repeat( "\t", $depth );

        $is_mobile = ! empty( $args->is_mobile );

		$classes  = self::SUBMENU_BASE_CLASSES . ' ';
		$classes .= ( 0 === $depth ) ? self::SUBMENU_DESKTOP_CLASSES : self::SUBMENU_NESTED_CLASSES;

		$output .= sprintf( "\n%s<ul class=\"%s\">\n", $indent, esc_attr( $classes ) );
	}

	/**
	 * Closes a submenu <ul>.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "$indent</ul>\n";
	}

	/**
	 * Renders a single menu item <li>.
	 */
	public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
		$menu_item = $data_object;
		$indent    = $depth ? str_repeat( "\t", $depth ) : '';

		$li_classes   = empty( $menu_item->classes ) ? array() : (array) $menu_item->classes;
		$li_classes[] = 'menu-item-' . $menu_item->ID;

		$has_children = in_array( 'menu-item-has-children', $li_classes, true );

		if ( $has_children ) {
			$li_classes[] = 'group relative';
		}

		$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $li_classes ), $menu_item, $args, $depth ) );
		$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

		$output .= $indent . '<li' . $class_names . '>';
		$output .= apply_filters(
			'walker_nav_menu_start_el',
			$this->build_item_markup( $menu_item, $args, $depth, $has_children ),
			$menu_item,
			$depth,
			$args
		);
	}

	/**
	 * Closes a menu item <li>.
	 */
	public function end_el( &$output, $data_object, $depth = 0, $args = null ) {
		$output .= "</li>\n";
	}

	/**
	 * Builds the inner markup for a menu item: the link, and, for items
	 * with children, a desktop chevron plus a separate mobile toggle button.
	 */
	private function build_item_markup( $menu_item, $args, $depth, $has_children ) {
		$title = apply_filters( 'the_title', $menu_item->title, $menu_item->ID );

		$markup  = $args->before;
		$markup .= '<div class="mobile-menu-item-wrapper flex items-center w-full relative">';
		$markup .= $this->build_link( $menu_item, $args, $depth, $has_children, $title );

		if ( $has_children ) {
			$markup .= $this->build_mobile_toggle();
		}

		$markup .= '</div>';
		$markup .= $args->after;

		return $markup;
	}

	/**
	 * Builds the <a> tag, including the desktop-only chevron for parent items.
	 */
	private function build_link( $menu_item, $args, $depth, $has_children, $title ) {
		$atts = array(
			'title'  => $menu_item->attr_title ?: '',
			'target' => $menu_item->target ?: '',
			'rel'    => $menu_item->xfn ?: '',
			'href'   => $menu_item->url ?: '',
			'class'  => 'flex items-center',
		);

		// A menu item whose title resolves to empty text (icon-only item,
		// image link, etc.) has no discernible label — give the link an
		// accessible name from the menu item title or the URL.
		if ( '' === trim( wp_strip_all_tags( $title ) ) && empty( $atts['aria-label'] ) ) {
			$atts['aria-label'] = $menu_item->title ?: ( $menu_item->url ? $menu_item->url : __( 'Menu item', 'blog-pro' ) );
		}

		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $menu_item, $args, $depth );

		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( ! empty( $value ) ) {
				$value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
				$attributes .= ' ' . $attr . '="' . $value . '"';
			}
		}

		$link  = '<a' . $attributes . '>';
		$link .= $args->link_before . $title . $args->link_after;

		if ( $has_children ) {
			$link .= $this->chevron_icon( 'h-5 w-5 ml-1 text-gray-400 hidden md:inline-block transition-transform duration-200 group-hover:rotate-180' );
		}

		$link .= '</a>';

		return $link;
	}

	/**
	 * Builds the mobile-only submenu toggle button (hidden on desktop).
	 */
	private function build_mobile_toggle() {
		$button  = '<button class="submenu-toggle md:hidden p-2 ml-2 -mr-2" aria-expanded="false" aria-label="' . esc_attr__( 'Toggle submenu', 'blogpro' ) . '">';
		$button .= $this->chevron_icon( 'h-5 w-5 text-gray-600' );
		$button .= '</button>';

		return $button;
	}

	/**
	 * Renders the shared chevron SVG with the given classes.
	 */
	private function chevron_icon( $classes ) {
		return sprintf(
			'<svg class="%s" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="%s" clip-rule="evenodd" /></svg>',
			esc_attr( $classes ),
			self::CHEVRON_PATH
		);
	}
}