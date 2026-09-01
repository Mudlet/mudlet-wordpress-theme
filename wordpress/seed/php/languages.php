<?php
/**
 * Create the five languages in Polylang.
 *
 * Run by seed/setup.sh through `wp eval-file`.
 *
 * Polylang ships no WP-CLI commands, but it does boot under WP-CLI: PLL()
 * returns a PLL_Admin whose model carries everything needed. An earlier version
 * of this file built PLL_Admin_Model by hand, which broke on Polylang 3.8 -
 * language creation moved to WP_Syntex\Polylang\Model\Languages, and the
 * options object stopped being a plain array. Going through PLL() avoids
 * guessing at either.
 *
 * Two APIs are tried, newest first, so this works either side of that move.
 * Assigning content to a language uses pll_set_post_language() and
 * pll_set_term_language(), which are Polylang's documented public functions and
 * the most stable thing here.
 *
 * @package Mudlet
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

// slug, locale, name in its own language, flag country code
$languages = array(
	array( 'en', 'en_US', 'English', 'us' ),
	array( 'de', 'de_DE', 'Deutsch', 'de' ),
	array( 'it', 'it_IT', 'Italiano', 'it' ),
	array( 'ru', 'ru_RU', 'Русский', 'ru' ),
	array( 'zh', 'zh_CN', '中文', 'cn' ),
);

if ( ! function_exists( 'PLL' ) ) {
	WP_CLI::warning( 'Polylang is not loaded - skipping language setup.' );
	return;
}

$pll   = PLL();
$model = $pll instanceof PLL_Base ? $pll->model : null;

if ( ! $model ) {
	WP_CLI::warning( 'Polylang has no model in this context - skipping language setup.' );
	return;
}

/**
 * Add one language through whichever API this Polylang has.
 *
 * @param object $model Polylang model.
 * @param array  $args  Language arguments.
 * @return true|WP_Error
 */
$add = static function ( $model, array $args ) {
	// Polylang 3.7+
	if ( isset( $model->languages ) && method_exists( $model->languages, 'add' ) ) {
		$result = $model->languages->add( $args );
		return is_wp_error( $result ) ? $result : true;
	}
	// Polylang 3.6 and earlier
	if ( method_exists( $model, 'add_language' ) ) {
		$result = $model->add_language( $args );
		return is_wp_error( $result ) ? $result : true;
	}
	return new WP_Error( 'mudlet_no_api', 'no known way to add a language on ' . get_class( $model ) );
};

/**
 * The slugs Polylang already knows about.
 *
 * @param object $model Polylang model.
 * @return string[]
 */
$existing_slugs = static function ( $model ): array {
	$list = isset( $model->languages ) && method_exists( $model->languages, 'get_list' )
		? $model->languages->get_list()
		: ( method_exists( $model, 'get_languages_list' ) ? $model->get_languages_list() : array() );

	$slugs = array();
	foreach ( (array) $list as $lang ) {
		if ( is_object( $lang ) && isset( $lang->slug ) ) {
			$slugs[] = $lang->slug;
		}
	}
	return $slugs;
};

$existing = $existing_slugs( $model );
$added    = 0;

foreach ( $languages as $i => $lang ) {
	list( $slug, $locale, $name, $flag ) = $lang;

	if ( in_array( $slug, $existing, true ) ) {
		WP_CLI::log( "    $slug already exists" );
		continue;
	}

	$result = $add(
		$model,
		array(
			'name'       => $name,
			'slug'       => $slug,
			'locale'     => $locale,
			'rtl'        => false,
			// Ordering in the switcher: English first, then as listed above.
			'term_group' => $i,
			'flag'       => $flag,
		)
	);

	if ( is_wp_error( $result ) ) {
		WP_CLI::warning( "    $slug: " . $result->get_error_message() );
		continue;
	}

	WP_CLI::log( "    added $name ($locale)" );
	++$added;
}

if ( 0 === $added ) {
	return;
}

// Everything that already exists - the pages and posts the script just created
// - has no language yet, and Polylang nags about that until it is fixed. This
// is the same fix its admin screen performs, through the public API.
$default = function_exists( 'pll_default_language' ) ? pll_default_language() : 'en';

if ( ! $default ) {
	$default = 'en';
}

if ( function_exists( 'pll_set_post_language' ) ) {
	$posts = get_posts(
		array(
			'numberposts' => -1,
			'post_type'   => array( 'post', 'page' ),
			'post_status' => 'any',
			'fields'      => 'ids',
		)
	);
	foreach ( $posts as $id ) {
		pll_set_post_language( $id, $default );
	}
	WP_CLI::log( '    assigned ' . count( $posts ) . " posts and pages to $default" );
}

if ( function_exists( 'pll_set_term_language' ) ) {
	$terms = get_terms(
		array(
			'taxonomy'   => array( 'category', 'post_tag' ),
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $id ) {
			pll_set_term_language( $id, $default );
		}
		WP_CLI::log( '    assigned ' . count( $terms ) . " terms to $default" );
	}
}

flush_rewrite_rules();
