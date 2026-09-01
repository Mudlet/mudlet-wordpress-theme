<?php
/**
 * The language switcher, over Polylang.
 *
 * The prototype draws the switcher twice - a dropdown in the header and a row
 * of codes in the footer - so both read from one list here. Polylang's own
 * pll_the_languages() returns raw rows when asked, which is exactly what a
 * theme with its own markup wants.
 *
 * With Polylang inactive this returns an empty list and both switchers stop
 * rendering, so the theme still works on a plain single-language install.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether Polylang is providing translations.
 *
 * @return bool
 */
function mudlet_has_polylang(): bool {
	return function_exists( 'pll_the_languages' ) && function_exists( 'pll_current_language' );
}

/**
 * The languages to offer, as a list the two switchers share.
 *
 * @return array<int, array{slug:string,code:string,name:string,url:string,current:bool}>
 */
function mudlet_languages(): array {
	if ( ! mudlet_has_polylang() ) {
		return array();
	}

	$raw = pll_the_languages( array( 'raw' => 1, 'hide_if_empty' => 0 ) );
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$out = array();
	foreach ( $raw as $lang ) {
		$out[] = array(
			'slug'    => (string) ( $lang['slug'] ?? '' ),
			'code'    => strtoupper( (string) ( $lang['slug'] ?? '' ) ),
			'name'    => (string) ( $lang['name'] ?? '' ),
			// no_translation means this exact post has none; Polylang still
			// hands back the language's home URL, which is the right landing
			// place - better than hiding the language altogether.
			'url'     => (string) ( $lang['url'] ?? home_url( '/' ) ),
			'current' => ! empty( $lang['current_lang'] ),
		);
	}
	return $out;
}

/**
 * The current language code, for the header button's label.
 *
 * @return string
 */
function mudlet_current_language_code(): string {
	if ( mudlet_has_polylang() ) {
		$slug = pll_current_language( 'slug' );
		if ( is_string( $slug ) && '' !== $slug ) {
			return strtoupper( $slug );
		}
	}
	$locale = get_locale();
	return strtoupper( substr( $locale, 0, 2 ) );
}

add_action( 'after_setup_theme', 'mudlet_register_polylang_strings' );
/**
 * Register the theme's own free-text strings with Polylang.
 *
 * Everything in the templates goes through __(), so it is covered by the .po
 * files. These are the strings that come from options instead, which gettext
 * cannot see.
 */
function mudlet_register_polylang_strings(): void {
	if ( ! function_exists( 'pll_register_string' ) ) {
		return;
	}
	pll_register_string( 'mudlet-tagline', get_bloginfo( 'description' ), 'Mudlet' );
}
