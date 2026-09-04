<?php
/**
 * A nav walker that emits bare anchors, and a dropdown where a menu item has
 * children.
 *
 * The design's navs are a flex row of <a>, not a list. WordPress's default
 * walker wraps every item in <li class="menu-item menu-item-type-post_type
 * ...">, which would mean either restyling around markup nobody asked for or
 * unpicking it in CSS. Emitting the anchors directly is less code than either.
 *
 * The one exception is a top-level item that has children. That becomes the
 * same shape the language switcher in the same bar already is - a button, and
 * a list under it - because it has to be: a control that opens a panel is a
 * button, not a link, and the panel is a list of links. The parent's own URL
 * is dropped deliberately. mudlet.org's menu repeats it as the first child
 * ("About" over About / Vision / The Makers / Contact Us, "Known Issues" over
 * Known Issues / Contribute / The Manual), so nothing is lost, and a thing
 * that both navigates and opens is the oldest trap in a menu bar: on a touch
 * screen there is no hover, so the first tap has to mean one of the two and
 * whichever it means is wrong half the time.
 *
 * Item CSS classes set in Appearance -> Menus still come through, so a site
 * can style or hide one item from the menu screen. On a parent they land on
 * the wrapper rather than the button, so such a class takes the whole dropdown
 * with it rather than leaving a caret with nothing behind it. The theme itself
 * uses none: the bar either fits the whole row or is a drawer holding all of
 * it (theme.css, 64rem), so there is no class for "drop this one first".
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Anchor-only menu walker, two levels deep.
 */
class Mudlet_Link_Walker extends Walker_Nav_Menu {

	/**
	 * The sub-menu is a real list: it is a panel of links, and the count of
	 * them is worth announcing.
	 *
	 * @param string $output Output buffer.
	 * @param int    $depth  Depth.
	 * @param array  $args   Args.
	 */
	public function start_lvl( &$output, $depth = 0, $args = array() ) {
		if ( 0 === $depth ) {
			$output .= '<ul class="nav__sub" hidden>';
		}
	}

	/**
	 * @param string $output Output buffer.
	 * @param int    $depth  Depth.
	 * @param array  $args   Args.
	 */
	public function end_lvl( &$output, $depth = 0, $args = array() ) {
		if ( 0 === $depth ) {
			$output .= '</ul>';
		}
	}

	/**
	 * @param string $output Output buffer.
	 * @param object $item   Menu item.
	 * @param int    $depth  Depth.
	 * @param array  $args   Args.
	 * @param int    $id     Item id.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
		$classes = array_filter( (array) ( $item->classes ?? array() ) );
		$current = in_array( 'current-menu-item', (array) $item->classes, true ) || ! empty( $item->current );

		// A parent opens a panel instead of going anywhere. WordPress sets
		// has_children on the walker before it calls us.
		if ( 0 === $depth && ! empty( $this->has_children ) ) {
			$output .= mudlet_nav_group_open( $item->title, implode( ' ', $classes ) );
			return;
		}

		$attrs = '';
		if ( $classes && 0 === $depth ) {
			$attrs .= ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}
		if ( $current ) {
			$attrs .= ' aria-current="page"';
		}
		if ( ! empty( $item->target ) ) {
			$attrs .= ' target="' . esc_attr( $item->target ) . '" rel="noopener"';
		}

		$output .= sprintf(
			'%1$s<a href="%2$s"%3$s>%4$s</a>',
			$depth > 0 ? '<li>' : '',
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
	public function end_el( &$output, $item, $depth = 0, $args = array() ) {
		if ( 0 === $depth && ! empty( $this->has_children ) ) {
			$output .= '</div>';
			return;
		}
		if ( $depth > 0 ) {
			$output .= '</li>';
		}
	}
}

/**
 * The opening half of a dropdown: the wrapper and the button that owns it.
 *
 * Shared by the walker and by the fallback below, so the two cannot drift.
 * The list that follows it is the caller's, and so is the closing </div>.
 *
 * @param string $title Label.
 * @param string $class Extra classes for the wrapper, if a menu item set any.
 * @return string
 */
function mudlet_nav_group_open( string $title, string $class = '' ): string {
	return sprintf(
		'<div class="nav__grp%1$s"><button class="nav__top" type="button" aria-expanded="false" aria-haspopup="true">%2$s%3$s</button>',
		'' !== $class ? ' ' . esc_attr( $class ) : '',
		esc_html( $title ),
		mudlet_get_icon( 'caret', 'crt' )
	);
}

/**
 * Print a menu as bare anchors, or the theme's defaults if none is assigned.
 *
 * A fallback entry is [title, url, class?]; give it a fourth element - a list
 * of entries in the same shape - and it becomes a dropdown, in which case the
 * url is never used and may be ''.
 *
 * @param string $location Menu location.
 * @param array<int, array{0:string,1:string,2?:string,3?:array}> $fallback Title, url, optional class, optional children.
 */
function mudlet_nav_links( string $location, array $fallback ): void {
	if ( has_nav_menu( $location ) ) {
		wp_nav_menu(
			array(
				'theme_location' => $location,
				'container'      => false,
				'items_wrap'     => '%3$s',
				'depth'          => 2,
				'walker'         => new Mudlet_Link_Walker(),
			)
		);
		return;
	}

	foreach ( $fallback as $link ) {
		if ( ! empty( $link[3] ) && is_array( $link[3] ) ) {
			echo mudlet_nav_group_open( $link[0], $link[2] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<ul class="nav__sub" hidden>';
			foreach ( $link[3] as $child ) {
				printf(
					'<li><a href="%1$s">%2$s</a></li>',
					esc_url( $child[1] ),
					esc_html( $child[0] )
				);
			}
			echo '</ul></div>';
			continue;
		}

		printf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( $link[1] ),
			isset( $link[2] ) ? ' class="' . esc_attr( $link[2] ) . '"' : '',
			esc_html( $link[0] )
		);
	}
}
