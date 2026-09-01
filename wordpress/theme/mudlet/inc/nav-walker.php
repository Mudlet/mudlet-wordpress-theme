<?php
/**
 * A nav walker that emits bare anchors.
 *
 * The design's navs are a flex row of <a>, not a list. WordPress's default
 * walker wraps every item in <li class="menu-item menu-item-type-post_type
 * ...">, which would mean either restyling around markup nobody asked for or
 * unpicking it in CSS. Emitting the anchors directly is less code than either.
 *
 * Item CSS classes set in Appearance -> Menus still come through, which is how
 * a menu item gets the "lo" class that hides it on narrow screens.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flat anchor-only menu walker.
 */
class Mudlet_Link_Walker extends Walker_Nav_Menu {

	/**
	 * No list wrapper, so no sub-menu either.
	 *
	 * @param string $output Output buffer.
	 * @param int    $depth  Depth.
	 * @param array  $args   Args.
	 */
	public function start_lvl( &$output, $depth = 0, $args = array() ) {}

	/**
	 * @param string $output Output buffer.
	 * @param int    $depth  Depth.
	 * @param array  $args   Args.
	 */
	public function end_lvl( &$output, $depth = 0, $args = array() ) {}

	/**
	 * @param string $output Output buffer.
	 * @param object $item   Menu item.
	 * @param int    $depth  Depth.
	 * @param array  $args   Args.
	 * @param int    $id     Item id.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
		$classes = array_filter( (array) ( $item->classes ?? array() ) );
		$attrs   = '';

		if ( $classes ) {
			$attrs .= ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}
		if ( in_array( 'current-menu-item', (array) $item->classes, true ) || ! empty( $item->current ) ) {
			$attrs .= ' aria-current="page"';
		}
		if ( ! empty( $item->target ) ) {
			$attrs .= ' target="' . esc_attr( $item->target ) . '" rel="noopener"';
		}

		$output .= sprintf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( $item->url ),
			$attrs,
			esc_html( $item->title )
		);
	}

	/**
	 * @param string $output Output buffer.
	 * @param object $item   Menu item.
	 * @param int    $depth  Depth.
	 * @param array  $args   Args.
	 */
	public function end_el( &$output, $item, $depth = 0, $args = array() ) {}
}

/**
 * Print a menu as bare anchors, or the theme's defaults if none is assigned.
 *
 * @param string                        $location Menu location.
 * @param array<int, array{0:string,1:string,2?:string}> $fallback Title, url, optional class.
 */
function mudlet_nav_links( string $location, array $fallback ): void {
	if ( has_nav_menu( $location ) ) {
		wp_nav_menu(
			array(
				'theme_location' => $location,
				'container'      => false,
				'items_wrap'     => '%3$s',
				'depth'          => 1,
				'walker'         => new Mudlet_Link_Walker(),
			)
		);
		return;
	}

	foreach ( $fallback as $link ) {
		printf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( $link[1] ),
			isset( $link[2] ) ? ' class="' . esc_attr( $link[2] ) . '"' : '',
			esc_html( $link[0] )
		);
	}
}
