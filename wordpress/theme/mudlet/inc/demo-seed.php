<?php
/**
 * What the hero's embedded demo asks the site about itself.
 *
 * The demo is a four-room MUD standing in for mudlet.org, and every room used
 * to have the site's facts typed into its prose: a version chalked on the vault
 * wall, four crate weights, three notices on a board, a shelf of "forty-two
 * boxed worlds". Typed facts rot — that vault was still offering 4.22.0 the
 * week 5.0 shipped — and the same argument that moved games, makers and
 * releases out of the theme applies inside the hero: how many games Mudlet
 * bundles is a fact about the client, not a sentence somebody writes.
 *
 * So the world asks. One route, one request, everything it needs:
 *
 *     GET /wp-json/mudlet/v1/demo
 *
 * One rather than four because this is a hero: it is fetched while the console
 * is still animating its fake connect, and the world can only wait so long
 * before it has to print the first room. A single response either lands inside
 * that window or does not; four would give us four chances to be half-seeded.
 *
 * This lives in the theme rather than in a plugin, unlike the data it serves.
 * It owns nothing: every value below is read back out through the same
 * `function_exists()` seams the templates use, so with the plugins deactivated
 * the endpoint answers with whatever the theme's own fallbacks say — which is
 * exactly what the pages would draw. A plugin exists to outlive a theme
 * rewrite; there is nothing here to outlive.
 *
 * The demo treats every field as optional and keeps its own typed copy as the
 * fallback, so an older world against a newer site (or the reverse) degrades to
 * prose rather than to an error. Add fields freely; renaming one is a change to
 * `demo/packages/mudlet-demo/site.lua` as well.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plain text, the way a terminal needs it.
 *
 * Post titles and the About dialog's prose both arrive as HTML with entities
 * in them, and a console has no opinion about `&amp;` other than printing it.
 *
 * @param string $html Source text.
 * @return string
 */
function mudlet_demo_seed_text( string $html ): string {
	return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/**
 * Register the route.
 */
function mudlet_demo_seed_register(): void {
	register_rest_route(
		'mudlet/v1',
		'/demo',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'mudlet_demo_seed_response',
			// Public: it is the front page's own hero asking, over the same
			// origin, for things every one of these pages already prints.
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'mudlet_demo_seed_register' );

/**
 * The response, with a cache header.
 *
 * Five minutes because the underlying data moves on the order of days and the
 * hero is on the busiest page of the site; the release plugin's own GitHub
 * cache is twelve hours, so this is not the slow part.
 *
 * @return WP_REST_Response
 */
function mudlet_demo_seed_response(): WP_REST_Response {
	$response = rest_ensure_response( mudlet_demo_seed() );
	$response->header( 'Cache-Control', 'public, max-age=300' );

	return $response;
}

/**
 * Everything the demo world asks for.
 *
 * @return array<string, mixed>
 */
function mudlet_demo_seed(): array {
	return array(
		'site'      => home_url( '/' ),
		'generated' => gmdate( 'c' ),
		'release'   => mudlet_demo_seed_release(),
		'games'     => mudlet_demo_seed_games(),
		'makers'    => mudlet_demo_seed_makers(),
		'news'      => mudlet_demo_seed_news(),
		'functions' => mudlet_demo_seed_functions(),
	);
}

/**
 * The shelves in the Stacks: everything the client can be told to do.
 *
 * Mudlet keeps the list of its own Lua API in `src/lua-function-list.json` —
 * name to signature, which is what the editor finishes your typing out of — and
 * that file is the only honest answer to "what does this client know how to
 * do". Read from upstream for the same reason the games and the makers are: a
 * copy in this repository would only decide how stale a new site starts out.
 *
 * The demo does not take it as gospel. It counts the client's own globals for
 * the number it quotes and uses this for the signatures and for the handful of
 * names the catalogue has that the build does not — so a request that fails
 * costs a signature, not a room.
 *
 * Cached for a day on success and ten minutes on failure, because an empty
 * answer pinned to the busiest page on the site for a day is worse than asking
 * again. It is 36 KB of JSON on a route the hero fetches at boot; gzip takes it
 * to nine, which is less than any one of the images above it on the page.
 *
 * @return array<string, mixed>
 */
function mudlet_demo_seed_functions(): array {
	$list = get_transient( 'mudlet_demo_lua_functions' );

	if ( ! is_array( $list ) ) {
		$list     = array();
		$response = wp_remote_get(
			'https://raw.githubusercontent.com/Mudlet/Mudlet/development/src/lua-function-list.json',
			array( 'timeout' => 8 )
		);

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $name => $signature ) {
					if ( is_string( $name ) && '' !== $name && is_string( $signature ) ) {
						$list[ $name ] = $signature;
					}
				}
			}
		}

		set_transient(
			'mudlet_demo_lua_functions',
			$list,
			$list ? DAY_IN_SECONDS : 10 * MINUTE_IN_SECONDS
		);
	}

	return array(
		'count' => count( $list ),
		'list'  => $list,
		'url'   => 'https://wiki.mudlet.org/w/Manual:Lua_Functions',
	);
}

/**
 * The release the vault is stacked with.
 *
 * Keyed the way the world names its crates rather than the way the download
 * table does — `macos` and `silicon`, not `macx86` and `macarm` — because the
 * crates are what a visitor types at, and renaming them in Lua to match a PHP
 * array key would be the tail wagging the dog.
 *
 * @return array<string, mixed>
 */
function mudlet_demo_seed_release(): array {
	$builds = mudlet_release_builds();
	$crates = array(
		'windows' => 'win',
		'macos'   => 'macx86',
		'silicon' => 'macarm',
		'linux'   => 'linux',
	);

	$out = array();
	foreach ( $crates as $crate => $key ) {
		$build = $builds[ $key ] ?? array();
		if ( ! $build ) {
			continue;
		}

		$sha = (string) ( $build['sha'] ?? '' );

		$out[ $crate ] = array(
			'label' => (string) ( $build['label'] ?? '' ),
			'size'  => (string) ( $build['size'] ?? '' ),
			'url'   => (string) ( $build['url'] ?? mudlet_download_url() ),
			// Both forms: the crate lids print the elided one, and a visitor
			// who wants to check a download needs the whole thing.
			'sha'   => $sha,
			// The world prints hashes the way the download page elides them,
			// eight and eight around an ellipsis. Done here so the two can
			// never disagree about how many characters that is.
			'short' => 64 === strlen( $sha ) ? substr( $sha, 0, 8 ) . '…' . substr( $sha, -8 ) : '',
		);
	}

	return array(
		'version'  => mudlet_release_version(),
		// Three renderings of one date, because the world writes it three ways:
		// on the wall in chalk, in the crate prose, and on the notice board.
		'date'     => mudlet_release_date( 'j F Y' ),
		'date_short' => mudlet_release_date( 'j M Y' ),
		'date_loud'  => strtoupper( mudlet_release_date( 'j F Y' ) ),
		'url'      => mudlet_download_url(),
		'builds'   => $out,
	);
}

/**
 * The shelf of boxed worlds.
 *
 * Every name, not a selection. The shelves are read more than once in a
 * session and name a dozen games each time; which dozen is the world's to
 * decide, per look, the way the page's own grid shuffles per load. Sampling
 * here would fix that choice for as long as the response is cached.
 *
 * @return array<string, mixed>
 */
function mudlet_demo_seed_games(): array {
	$named = array();
	foreach ( mudlet_home_games( -1 ) as $game ) {
		$named[] = (string) $game['name'];
	}

	return array(
		'count' => mudlet_game_count(),
		'names' => $named,
		'url'   => mudlet_download_url(),
	);
}

/**
 * The ledger in Makers Hall.
 *
 * Everyone, in the About dialog's own order — the team first, then the people
 * who carried it earlier — because the sage can be asked about any of them by
 * name and the answer is this sentence. `core` is sent per person rather than
 * as two lists so the world can tell who is on the project now without having
 * to be told twice.
 *
 * @return array<string, mixed>
 */
function mudlet_demo_seed_makers(): array {
	$people = array();
	foreach ( array_merge( mudlet_core_makers(), mudlet_past_makers() ) as $maker ) {
		$people[] = array(
			'name'   => (string) $maker['name'],
			'github' => (string) ( $maker['github'] ?? '' ),
			'core'   => (bool) ( $maker['core'] ?? false ),
			'line'   => mudlet_demo_seed_text( (string) ( $maker['description'] ?? '' ) ),
		);
	}

	return array(
		'count'  => mudlet_maker_count(),
		'people' => $people,
		'url'    => mudlet_makers_page_url(),
	);
}

/**
 * The notice board and the drawer under it.
 *
 * @return array<string, mixed>
 */
function mudlet_demo_seed_news(): array {
	$posts = array();
	foreach ( get_posts( array( 'numberposts' => 3, 'post_status' => 'publish' ) ) as $post ) {
		$posts[] = array(
			'title'  => mudlet_demo_seed_text( get_the_title( $post ) ),
			'date'   => get_the_date( 'j M Y', $post ),
			'author' => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'url'    => get_permalink( $post ),
			// The board prints a clause under each headline. The excerpt is
			// the closest thing the site has to one, trimmed hard: this is a
			// terminal, and the line has to fit beside a date.
			'blurb'  => mudlet_demo_seed_text( wp_trim_words( get_the_excerpt( $post ), 14, '' ) ),
		);
	}

	$counts = wp_count_posts();

	return array(
		'count' => (int) ( $counts->publish ?? 0 ),
		'posts' => $posts,
		'url'   => mudlet_news_url(),
	);
}
