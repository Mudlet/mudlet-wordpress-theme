<?php
/**
 * What the search palette knows, and where it asks for the rest.
 *
 * The palette used to be a filter over a flat list of the twenty newest page
 * and post *titles*, shipped inline with every page. Enter fell through to
 * WordPress's own search, which reads the documents themselves - so the two
 * halves of one box disagreed: a word in the body of a page matched nothing
 * until you pressed Enter, and then matched.
 *
 * One route closes that gap:
 *
 *     GET /wp-json/mudlet/v1/search?q=<term>
 *
 * It runs the query `search.php` runs - no `post_type`, which is WordPress's
 * own "every type that is searchable", so games and makers are in it and
 * releases (a data store, `exclude_from_search`) are not - and answers rows in
 * the shape the inline index already has: `[title, source, url]`. The palette
 * therefore does not care which of the two a row came from.
 *
 * It answers a `total` beside them because a palette is eight rows tall and a
 * search is not: the count is how the palette knows to offer the results page
 * at the bottom of the list, and what it can say is waiting there.
 *
 * The inline index stays, and is not redundant: it is what the palette draws
 * on the keystroke, before the network has been asked anything, and what it
 * keeps drawing on a site whose REST API is unreachable. Titles first,
 * documents a moment later.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * How many rows either half of the search offers.
 */
const MUDLET_SEARCH_LIMIT = 8;

/**
 * Where the inline index is kept.
 *
 * Versioned, because the rows in it are a shape and not just data: a site that
 * updates the theme mid-hour would otherwise keep serving an hour of rows built
 * by the last version - which is how titles briefly came back with their
 * entities still in them.
 */
const MUDLET_SEARCH_CACHE = 'mudlet_search_index_2';

/**
 * A flat list for the search palette: [title, source label, url].
 *
 * Deliberately small: it is the instant first pass, not an index. Cached for an
 * hour and dropped whenever a post moves.
 *
 * Cached per language, because what it caches is already per language: with
 * Polylang up, `get_pages()` and `get_posts()` answer in the language being
 * browsed, and one shared key would hand the Italian visitor whichever language
 * asked first.
 *
 * @return array<int, array{0:string,1:string,2:string}>
 */
function mudlet_search_index(): array {
	$lang = function_exists( 'mudlet_current_language_slug' ) ? mudlet_current_language_slug() : '';
	$key  = '' === $lang ? MUDLET_SEARCH_CACHE : MUDLET_SEARCH_CACHE . '_' . $lang;

	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$items = array();

	foreach ( get_pages( array( 'number' => 20 ) ) as $page ) {
		$items[] = mudlet_search_row( $page );
	}

	$posts = get_posts(
		array(
			'numberposts' => 20,
			'post_status' => 'publish',
		)
	);
	foreach ( $posts as $post ) {
		$items[] = mudlet_search_row( $post );
	}

	set_transient( $key, $items, HOUR_IN_SECONDS );
	return $items;
}

add_action( 'save_post', 'mudlet_flush_search_index' );
add_action( 'deleted_post', 'mudlet_flush_search_index' );
/**
 * Drop the cached palette index when content changes.
 *
 * Every language's copy, not the current one: a post saved in the admin has no
 * front-end language, and an hour of one stale list is the thing this avoids.
 */
function mudlet_flush_search_index(): void {
	delete_transient( MUDLET_SEARCH_CACHE );

	foreach ( mudlet_languages() as $language ) {
		if ( ! empty( $language['slug'] ) ) {
			delete_transient( MUDLET_SEARCH_CACHE . '_' . $language['slug'] );
		}
	}
}

/**
 * Where a search goes.
 *
 * `home_url( '/' )` is the obvious answer and, on a translated site, the wrong
 * one: with Polylang up it answers with the language's front *page*, and `?s=`
 * on a page URL is a 404 - which is what this form has been submitting to on
 * every language but the default. Asking for a path gets the site's root
 * instead, which is where a search belongs. The query in it is only there to
 * make it a path; a GET form writes its own, and the row the palette draws
 * builds one the same way.
 *
 * @return string
 */
function mudlet_search_action(): string {
	return (string) strtok( home_url( '/?s=' ), '?' );
}

/**
 * One row of either list.
 *
 * The source label is the post type's own singular name, so a synced type
 * labels itself ("Game", "Maker") without this file holding a table of them.
 * The two core types are the exceptions, because "Post" is what WordPress
 * calls them and "News" is what this site does.
 *
 * @param WP_Post $post Post to describe.
 * @return array{0:string,1:string,2:string}
 */
function mudlet_search_row( WP_Post $post ): array {
	return array(
		html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
		mudlet_search_source( $post ),
		(string) get_permalink( $post ),
	);
}

/**
 * The label under which a result files itself.
 *
 * @param WP_Post $post Post to label.
 * @return string
 */
function mudlet_search_source( WP_Post $post ): string {
	if ( 'post' === $post->post_type ) {
		return __( 'News', 'mudlet' );
	}
	if ( 'page' === $post->post_type ) {
		return __( 'Page', 'mudlet' );
	}

	$type = get_post_type_object( $post->post_type );

	return $type && ! empty( $type->labels->singular_name )
		? (string) $type->labels->singular_name
		: __( 'Result', 'mudlet' );
}

/**
 * Register the route.
 */
function mudlet_search_register(): void {
	register_rest_route(
		'mudlet/v1',
		'/search',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'mudlet_search_response',
			// Public, and reading nothing that is not already on a page the
			// same visitor can open: it is the site's own search box asking.
			'permission_callback' => '__return_true',
			'args'                => array(
				'q'    => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				// The language being browsed. A REST request has no place in
				// the site's URL structure, so Polylang cannot work it out the
				// way it does for the results page - the palette says.
				'lang' => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_key',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'mudlet_search_register' );

/**
 * The results for one term.
 *
 * No transient: a cache keyed by whatever a visitor typed is an unbounded pile
 * of rows in `wp_options` that nothing ever reads twice. The palette debounces
 * and asks once per pause, and the cache header is there for the page cache in
 * front of the site, which is the right place to absorb a repeat.
 *
 * @param WP_REST_Request $request The request.
 * @return WP_REST_Response
 */
function mudlet_search_response( WP_REST_Request $request ): WP_REST_Response {
	$query = trim( (string) $request->get_param( 'q' ) );
	$rows  = array();
	$total = 0;

	// Two characters, the same floor the palette applies before it asks: a
	// single letter matches most of the site and orders it by nothing.
	if ( mb_strlen( $query ) > 1 ) {
		$args = array(
			's'                   => mb_substr( $query, 0, 100 ),
			'post_status'         => 'publish',
			'posts_per_page'      => MUDLET_SEARCH_LIMIT,
			'ignore_sticky_posts' => true,
		);

		$lang = (string) $request->get_param( 'lang' );
		if ( '' !== $lang ) {
			$args['lang'] = $lang;
		}

		$found = new WP_Query( $args );

		// Counted rather than assumed: the palette's last row offers the
		// results page only when there is something there the eight rows above
		// it did not already show, and says how much.
		$total = (int) $found->found_posts;

		foreach ( $found->posts as $post ) {
			$rows[] = mudlet_search_row( $post );
		}
	}

	$response = rest_ensure_response(
		array(
			'total' => $total,
			'rows'  => $rows,
		)
	);
	$response->header( 'Cache-Control', 'public, max-age=300' );

	return $response;
}
