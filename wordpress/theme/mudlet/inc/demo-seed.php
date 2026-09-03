<?php
/**
 * What the hero's embedded demo asks the site about itself.
 *
 * The demo is an eight-room MUD standing in for mudlet.org, and every room used
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
		'media'     => mudlet_demo_seed_media(),
		'functions' => mudlet_demo_seed_functions(),
		'packages'  => mudlet_demo_seed_packages(),
	);
}


/**
 * The Gallery: the screenshots and screencasts on /media/.
 *
 * The one page on the site whose whole content is content — no template, no
 * post type, just blocks — and so the one page the world could not parody
 * without asking. The room east of the front page hangs a real screenshot in a
 * real Geyser label, which means it needs a URL to fetch rather than a number
 * to print: this is the only part of the seed the demo *downloads* rather than
 * reads.
 *
 * Sized to `large` rather than full. The world is fetching these into a
 * profile inside a hero, and a 4MB original to draw at 400px wide is somebody
 * else's data allowance.
 *
 * The shots come through `mudlet_front_thumbs()`, which is already how the
 * front page's thumbnail row reads that page — adding a screenshot to /media/
 * adds it to both, and neither holds a copy. It shuffles, so which frames hang
 * on the wall is a different eight per request, the same way the games grid
 * picks a random fifteen.
 *
 * @return array<string, mixed>
 */
function mudlet_demo_seed_media(): array {
	$ids   = function_exists( 'mudlet_front_thumbs' ) ? mudlet_front_thumbs( 99 ) : array();
	$shots = array();

	foreach ( array_slice( $ids, 0, 8 ) as $id ) {
		$src = wp_get_attachment_image_src( (int) $id, 'large' );
		if ( ! is_array( $src ) || empty( $src[0] ) ) {
			continue;
		}

		$shots[] = array(
			'url'   => $src[0],
			'w'     => (int) $src[1],
			'h'     => (int) $src[2],
			// The alt text before the title: /media/'s pictures are described
			// for a screen reader already, and that description is a caption.
			'title' => mudlet_demo_seed_text(
				(string) ( get_post_meta( (int) $id, '_wp_attachment_image_alt', true ) ?: get_the_title( (int) $id ) )
			),
		);
	}

	$page = get_page_by_path( 'media' );

	return array(
		'count' => count( $ids ),
		'shots' => $shots,
		'films' => $page instanceof WP_Post ? mudlet_demo_seed_films( $page ) : array(),
		'url'   => $page instanceof WP_Post ? get_permalink( $page ) : home_url( '/media/' ),
	);
}

/**
 * The screencasts, out of the list block that holds them.
 *
 * Stored as prose — an anchor and then a sentence — for the reason the release
 * post's two shapes are core blocks: a block that stores a title and a URL and
 * a sentence is a paragraph with extra steps. So this reads them back the way
 * they were written rather than out of some parallel record.
 *
 * @param WP_Post $page The /media/ page.
 * @return array<int, array<string, string>>
 */
function mudlet_demo_seed_films( WP_Post $page ): array {
	$films = array();

	if ( ! function_exists( 'parse_blocks' ) ) {
		return $films;
	}

	foreach ( parse_blocks( $page->post_content ) as $block ) {
		if ( 'core/list' !== ( $block['blockName'] ?? '' ) ) {
			continue;
		}

		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $item ) {
			$html = (string) ( $item['innerHTML'] ?? '' );
			if ( ! preg_match( '~<a[^>]+href="([^"]+)"[^>]*>(.*?)</a>~is', $html, $found ) ) {
				continue;
			}

			$films[] = array(
				'title' => mudlet_demo_seed_text( $found[2] ),
				'url'   => esc_url_raw( html_entity_decode( $found[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
			);
		}
	}

	return $films;
}
/**
 * The cabinet in the commons: how many packages there are, and how many people
 * wrote them.
 *
 * Both counted off the package repository's own index rather than off anybody's
 * memory of it. The cabinet used to say "229 drawers from 123 authors" and both
 * halves were wrong inside a month.
 *
 * The two numbers are not equally good and the world does not treat them as
 * though they were. The packages are countable: one entry, one drawer. The
 * authors are a free-text field — `"tjurczyk, Delwing"` where two people wrote
 * one package, and the same person under more than one spelling of themselves —
 * so this splits it on commas and folds case, which is as close as anybody can
 * get, and the world rounds what it gets down to the hundred before saying it
 * out loud.
 *
 * Only the two numbers are kept. The index itself is 340 KB and there is
 * nothing in it the hero wants.
 *
 * @return array<string, mixed>
 */
function mudlet_demo_seed_packages(): array {
	$counted = get_transient( 'mudlet_demo_package_counts' );

	if ( ! is_array( $counted ) ) {
		$counted  = array( 'count' => 0, 'authors' => 0 );
		$response = wp_remote_get(
			'https://raw.githubusercontent.com/Mudlet/mudlet-package-repository/main/packages/mpkg.packages.json',
			array( 'timeout' => 8 )
		);

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$decoded  = json_decode( wp_remote_retrieve_body( $response ), true );
			$packages = is_array( $decoded ) && isset( $decoded['packages'] ) && is_array( $decoded['packages'] )
				? $decoded['packages']
				: array();

			$hands = array();
			foreach ( $packages as $package ) {
				$field = is_array( $package ) && isset( $package['author'] ) ? (string) $package['author'] : '';
				foreach ( explode( ',', $field ) as $name ) {
					$name = trim( $name );
					if ( '' !== $name ) {
						$hands[ strtolower( $name ) ] = true;
					}
				}
			}

			$counted = array(
				'count'   => count( $packages ),
				'authors' => count( $hands ),
			);
		}

		set_transient(
			'mudlet_demo_package_counts',
			$counted,
			$counted['count'] ? DAY_IN_SECONDS : 10 * MINUTE_IN_SECONDS
		);
	}

	return array(
		'count'   => (int) $counted['count'],
		'authors' => (int) $counted['authors'],
		'url'     => 'https://packages.mudlet.org/',
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
