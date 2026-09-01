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
 *     $makers = function_exists( 'mudlet_makers' ) ? mudlet_makers() : $prose_only;
 *
 * @package Mudlet_Makers
 */

defined( 'ABSPATH' ) || exit;

/**
 * The people credited in Mudlet.
 *
 * Returned in the order the About dialog lists them — current team first — and
 * not re-sorted, because that order is upstream's decision.
 *
 * Each returned array:
 *
 *   id          int     the post id
 *   name        string  "Stephen Lyons"
 *   slug        string  "stephen-lyons"
 *   url         string  permalink to their page
 *   core        bool    core developer
 *   github      string  handle, '' if they publish none
 *   github_url  string  profile URL, '' likewise
 *   discord     string  handle in the retired "name#1234" form, often ''
 *   avatar      string  the avatar's filename
 *   avatar_id   int     attachment id, 0 when there is no picture
 *   avatar_url  string  attachment URL, '' likewise
 *   initials    string  what to draw when there is no picture
 *   description string  what they did, in upstream's own HTML
 *
 * @param array<string, mixed> $args Optional. 'group' ('all' default, 'core',
 *                                   or 'past') and 'number' (default all).
 * @return array<int, array<string, mixed>>
 */
function mudlet_makers( array $args = array() ): array {
	$args = wp_parse_args(
		$args,
		array(
			'group'  => 'all',
			'number' => -1,
		)
	);

	$query = array(
		'post_type'   => Mudlet_Makers_Store::POST_TYPE,
		'post_status' => 'publish',
		'numberposts' => (int) $args['number'],
		'orderby'     => 'menu_order',
		'order'       => 'ASC',
	);

	if ( 'all' !== $args['group'] ) {
		$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'key'     => Mudlet_Makers_Store::META['core'],
				'value'   => '1',
				'compare' => 'core' === $args['group'] ? '=' : '!=',
			),
		);
	}

	return array_map( array( 'Mudlet_Makers_Store', 'to_array' ), get_posts( $query ) );
}

/**
 * One maker, by slug or post.
 *
 * @param string|int|WP_Post $maker Slug, post id, or post.
 * @return array<string, mixed>|null
 */
function mudlet_maker( $maker ) {
	if ( is_string( $maker ) ) {
		$found = get_posts(
			array(
				'post_type'   => Mudlet_Makers_Store::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => 1,
				'name'        => $maker,
			)
		);
		$maker = $found ? $found[0] : null;
	} else {
		$maker = get_post( $maker );
	}

	if ( ! $maker instanceof WP_Post || Mudlet_Makers_Store::POST_TYPE !== $maker->post_type ) {
		return null;
	}

	return Mudlet_Makers_Store::to_array( $maker );
}

/**
 * How many people Mudlet credits.
 *
 * @return int
 */
function mudlet_makers_count(): int {
	return Mudlet_Makers_Sync::count();
}

/**
 * The prose the About dialog prints under the credits.
 *
 * Paragraphs of upstream's HTML: that the list is incomplete and where the rest
 * of the names are, who drew the icons, and thanks to a few people who never
 * committed a line but shaped Mudlet anyway. Print through wp_kses_post.
 *
 * @return array<int, string>
 */
function mudlet_makers_acknowledgements(): array {
	$prose = get_option( Mudlet_Makers_Store::ACKNOWLEDGEMENTS, array() );

	return is_array( $prose ) ? $prose : array();
}

/**
 * The patreon supporters, by tier.
 *
 * Keyed as upstream names the tiers: 'mightier_than_swords' and 'on_a_plaque',
 * each a list of plain names — plus 'intro', the sentence the About dialog puts
 * above them, in upstream's own words and carrying its patreon link.
 *
 * @return array<string, array<int, string>>
 */
function mudlet_makers_supporters(): array {
	$supporters = get_option( Mudlet_Makers_Store::SUPPORTERS, array() );

	return is_array( $supporters ) ? $supporters : array();
}
