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
 * What a game's server said about itself when it was last asked.
 *
 * Read off MSSP by Mudlet_Games_Status on a schedule, never on a page view.
 * Empty unless the game answered recently: a game that did not answer, or was
 * last asked longer ago than two of its cadences, returns nothing at all
 * rather than "offline" or an old count - a refused connection is as likely to
 * be this host's firewall as the game, and an hour-old player count is a
 * number that was true once.
 *
 * Every key is optional, because every MSSP field is:
 *
 *   checked   int     unix time of the probe
 *   players   int     how many were connected
 *   since     int     unix time the server last booted
 *   codebase  string  "Rapture 1.4"
 *   created   int     the year the game opened
 *   genre     string  "Fantasy"
 *   gameplay  string  "Roleplaying, Player versus Player"
 *   language  string  "English"
 *
 * @param array<string, mixed>|int $game A game from mudlet_games(), or its post id.
 * @return array<string, mixed>
 */
function mudlet_game_status( $game ): array {
	$id  = is_array( $game ) ? (int) ( $game['id'] ?? 0 ) : (int) $game;
	$one = $id ? Mudlet_Games_Status::get( $id ) : null;
	if ( ! $one || empty( $one['up'] ) || empty( $one['at'] ) ) {
		return array();
	}

	$every = wp_get_schedules()[ Mudlet_Sync::recurrence( Mudlet_Games_Status::HOOK, Mudlet_Games_Status::EVERY ) ]['interval'] ?? HOUR_IN_SECONDS;
	if ( time() - (int) $one['at'] > max( 3 * HOUR_IN_SECONDS, 2 * (int) $every ) ) {
		return array();
	}

	$m   = (array) ( $one['mssp'] ?? array() );
	$out = array( 'checked' => (int) $one['at'] );

	if ( isset( $m['PLAYERS'] ) && ctype_digit( $m['PLAYERS'] ) ) {
		$out['players'] = (int) $m['PLAYERS'];
	}
	// A boot time in the future, or before anybody ran a MUD on the web, is a
	// server with its clock wrong, not a fact.
	if ( isset( $m['UPTIME'] ) && ctype_digit( $m['UPTIME'] ) && (int) $m['UPTIME'] > 946684800 && (int) $m['UPTIME'] <= time() ) {
		$out['since'] = (int) $m['UPTIME'];
	}
	if ( isset( $m['CREATED'] ) && preg_match( '/\b(19[7-9]\d|20\d\d)\b/', $m['CREATED'], $year ) && (int) $year[1] <= (int) gmdate( 'Y' ) ) {
		$out['created'] = (int) $year[1];
	}
	foreach ( array( 'codebase' => 'CODEBASE', 'genre' => 'GENRE', 'gameplay' => 'GAMEPLAY', 'language' => 'LANGUAGE' ) as $key => $field ) {
		if ( isset( $m[ $field ] ) && '' !== $m[ $field ] ) {
			$out[ $key ] = (string) $m[ $field ];
		}
	}

	return $out;
}

/**
 * The archive URL for the games list.
 *
 * @return string
 */
function mudlet_games_url(): string {
	return (string) get_post_type_archive_link( Mudlet_Games_Store::POST_TYPE );
}
