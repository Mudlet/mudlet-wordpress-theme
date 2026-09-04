<?php
/**
 * Drop Polylang, the way it would be dropped on mudlet.org.
 *
 * Run by seed/setup.sh through `wp eval-file`, as the first step of the
 * migration phase. wordpress/MIGRATION.md decision 4 is the argument for doing
 * it at all; this is the rehearsal.
 *
 * The order here is the whole point, and it is the reverse of the obvious one:
 *
 *   1. write the translation map out, while Polylang can still answer
 *   2. unpublish the translated content
 *   3. remove the language furniture from the menus
 *   4. deactivate the plugin
 *
 * Step 1 cannot be done after step 4. Polylang stores its translation groups in
 * a taxonomy it registers itself; with the plugin gone the terms are still in
 * the database but nothing maps a post to its group, and `pll_get_post()`
 * does not exist to ask. MIGRATION.md calls this "the one step that cannot be
 * done afterwards", and on production there is exactly one attempt at it. The
 * map is what the 301s are built from, so losing it means 162 indexed URLs
 * either 404 or all land on the front page.
 *
 * So the map is written to disk before anything is touched, and this file
 * refuses to go any further if that write fails.
 *
 * What "unpublish" means here: draft, not delete. That is the answer to the
 * last open question in MIGRATION.md - a draft costs nothing, keeps the
 * translator's work, and can be reversed by somebody who changes their mind,
 * whereas a delete cannot. Nothing links to them once the menus lose their
 * switcher, and the 301s cover the URLs.
 *
 * @package Mudlet
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! function_exists( 'PLL' ) ) {
	WP_CLI::log( '    Polylang is not active - nothing to drop.' );
	return;
}

/** Where the map goes. seed/out is the one writable mount; /seed itself is read-only. */
$map_path = '/seed/out/translation-map.json';

// ── 1. the map, before anything else ──────────────────────────────────

$languages = function_exists( 'pll_languages_list' ) ? pll_languages_list() : array();
if ( ! $languages ) {
	WP_CLI::warning( 'Polylang lists no languages - refusing to touch anything.' );
	return;
}

/**
 * One row per translated URL, carrying where it should end up.
 *
 * The English post is the target. Where there is no English counterpart the
 * row still goes in the file with an empty `to` - a blanket redirect to the
 * front page is worse than it looks, and somebody has to decide what those
 * few become. Writing them out is how they get noticed.
 *
 * Written once and never rewritten, which is not tidiness but correctness. The
 * `from` URL of a published post is its permalink; the `from` URL of a draft is
 * ?p=<id>, because that is what WordPress gives you for something unpublished.
 * Step 2 below drafts every row, so a second run would regenerate the same map
 * with 105 of its 119 URLs replaced by query strings - and overwrite the good
 * one with it. Measured, on the second run of this file.
 *
 * So an existing map is read back rather than rebuilt. The file is the record,
 * which is also what it will be on production.
 */
$map = array();

if ( file_exists( $map_path ) ) {
	$map = json_decode( (string) file_get_contents( $map_path ), true );
	if ( ! is_array( $map ) ) {
		WP_CLI::error( "$map_path exists but is not readable JSON - move it aside and re-run." );
		return;
	}
	WP_CLI::log( sprintf( '    translation map: %d URLs, read back from %s', count( $map ), $map_path ) );
}

foreach ( $map ? array() : array( 'post', 'page' ) as $type ) {
	$ids = get_posts(
		array(
			'post_type'        => $type,
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);

	foreach ( $ids as $id ) {
		$lang = pll_get_post_language( $id );
		if ( ! $lang || 'en' === $lang ) {
			continue;
		}

		/*
		 * Polylang answers this with the post itself when a translation group
		 * has no English member, and - for eleven of these - with a member in
		 * another language entirely. Neither is a redirect target: sending a
		 * German reader to the Chinese copy is worse than sending them nowhere,
		 * because nowhere is visible and gets fixed. So the answer is only
		 * accepted if it is a different post that really is in English.
		 */
		$english = pll_get_post( $id, 'en' );
		if ( $english && ( (int) $english === (int) $id || 'en' !== pll_get_post_language( $english ) ) ) {
			$english = 0;
		}

		$map[] = array(
			'id'       => $id,
			'type'     => $type,
			'lang'     => $lang,
			'from'     => wp_make_link_relative( (string) get_permalink( $id ) ),
			'to'       => $english ? wp_make_link_relative( (string) get_permalink( $english ) ) : '',
			'title'    => get_the_title( $id ),
			'status'   => get_post_status( $id ),
		);
	}
}

if ( ! file_exists( $map_path ) ) {
	$written = file_put_contents(
		$map_path,
		wp_json_encode( $map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
	);

	if ( false === $written ) {
		WP_CLI::error( "Could not write $map_path - refusing to deactivate Polylang without the map." );
		return;
	}

	$orphans = count( array_filter( $map, static fn( $row ) => '' === $row['to'] ) );
	WP_CLI::log( sprintf( '    translation map: %d URLs -> %s', count( $map ), $map_path ) );
	if ( $orphans ) {
		WP_CLI::log( sprintf( '    %d have no English counterpart and need a decision before the 301s go up', $orphans ) );
	}
}

// ── 2. unpublish the translations ─────────────────────────────────────

$drafted = 0;
foreach ( $map as $row ) {
	// The live status, not the one recorded in the map: on a re-run the map is
	// read back from disk still saying 'publish', and counting from that would
	// report 119 posts unpublished every time while changing nothing.
	if ( 'publish' !== get_post_status( $row['id'] ) ) {
		continue;
	}
	wp_update_post(
		array(
			'ID'          => $row['id'],
			'post_status' => 'draft',
		)
	);
	// So a later pass can tell these from things somebody drafted by hand.
	update_post_meta( $row['id'], '_mudlet_unpublished_translation', $row['lang'] );
	++$drafted;
}
WP_CLI::log( sprintf( '    unpublished %d translated posts and pages', $drafted ) );

// ── 3. the language furniture in the menus ────────────────────────────

// The three translated menus go whole: main_de, main_it and main_zh exist only
// to point at content that is now drafted.
$menus_removed = 0;
foreach ( wp_get_nav_menus() as $menu ) {
	if ( preg_match( '/_(de|it|ru|zh)$/', $menu->slug ) ) {
		wp_delete_nav_menu( $menu->term_id );
		++$menus_removed;
	}
}

// And the switcher itself, which is a custom item pointing at #pll_switcher.
$switchers = 0;
foreach ( wp_get_nav_menus() as $menu ) {
	foreach ( wp_get_nav_menu_items( $menu->term_id ) ?: array() as $item ) {
		if ( false !== strpos( (string) $item->url, 'pll_switcher' ) ) {
			wp_delete_post( $item->ID, true );
			++$switchers;
		}
	}
}
WP_CLI::log( sprintf( '    removed %d translated menus and %d language switchers', $menus_removed, $switchers ) );

// ── 4. and only now, the plugin ───────────────────────────────────────

WP_CLI::runcommand(
	'plugin deactivate polylang',
	array(
		'return'     => true,
		'exit_error' => false,
	)
);
WP_CLI::log( '    Polylang deactivated' );
WP_CLI::log( '    the site is English-only; build the 301 table from the map above' );
