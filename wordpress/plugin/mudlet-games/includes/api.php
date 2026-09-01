<?php
/**
 * The public surface.
 *
 * This file is the contract. A theme should call these and nothing else — the
 * classes behind them are free to change shape, these are not.
 *
 * Every one of them is safe to call when GitHub is unreachable or nothing has
 * synced yet: they return an empty array or zero rather than raising, so a
 * caller's job is to have a fallback, not to catch anything.
 *
 * Guard calls with function_exists() so a theme keeps working when the plugin
 * is deactivated:
 *
 *     $games = function_exists( 'mudlet_games' ) ? mudlet_games() : $hardcoded;
 *
 * @package Mudlet_Games
 */

defined( 'ABSPATH' ) || exit;

/**
 * The bundled games.
 *
 * Each returned array:
 *
 *   id          int     the post id
 *   name        string  "Achaea"
 *   slug        string  "achaea"
 *   url         string  permalink to the game's page
 *   host        string  "achaea.com"
 *   port        int     23
 *   tls         bool    whether the profile connects securely
 *   site        string  the game's website
 *   domain      string  "achaea.com" - what a card prints under the name
 *   links       array   [ ['label' => 'Website', 'url' => '…'], … ]
 *   own_ui      bool    the game's bundled loader installs its own interface
 *   alt_hosts   array   other hostnames the game answers on
 *   icon        string  the logo's filename upstream
 *   icon_id     int     attachment id, 0 if the logo never downloaded
 *   icon_url    string  attachment URL, '' if there is none
 *   description string  the blurb, as stored on the post
 *
 * @param array<string, mixed> $args Optional. 'number' (default all) and
 *                                   'orderby' ('name' default, or 'rand').
 * @return array<int, array<string, mixed>>
 */
function mudlet_games( array $args = array() ): array {
	$args = wp_parse_args(
		$args,
		array(
			'number'  => -1,
			'orderby' => 'name',
		)
	);

	$posts = get_posts(
		array(
			'post_type'   => Mudlet_Games_Store::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => (int) $args['number'],
			'orderby'     => 'rand' === $args['orderby'] ? 'rand' : 'title',
			'order'       => 'ASC',
		)
	);

	return array_map( array( 'Mudlet_Games_Store', 'to_array' ), $posts );
}

/**
 * One game, by slug or post.
 *
 * @param string|int|WP_Post $game Slug, post id, or post.
 * @return array<string, mixed>|null
 */
function mudlet_game( $game ) {
	if ( is_string( $game ) ) {
		$found = get_posts(
			array(
				'post_type'   => Mudlet_Games_Store::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => 1,
				'name'        => $game,
			)
		);
		$game  = $found ? $found[0] : null;
	} else {
		$game = get_post( $game );
	}

	if ( ! $game instanceof WP_Post || Mudlet_Games_Store::POST_TYPE !== $game->post_type ) {
		return null;
	}

	return Mudlet_Games_Store::to_array( $game );
}

/**
 * How many games Mudlet bundles.
 *
 * The number of posts on record, which is the number upstream ships — not a
 * figure anybody types into a headline.
 *
 * @return int
 */
function mudlet_games_count(): int {
	return Mudlet_Games_Sync::count();
}

/**
 * The archive URL for the games list.
 *
 * @return string
 */
function mudlet_games_url(): string {
	return (string) get_post_type_archive_link( Mudlet_Games_Store::POST_TYPE );
}
