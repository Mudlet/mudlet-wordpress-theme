<?php
/**
 * The Discord server, as data.
 *
 * /contact/ leads with Discord because it is the fastest way to get an answer,
 * and a panel that says so is more convincing when it can also say how many
 * people are in there right now. So the counts are not typed: they are read
 * from Discord, the same way the games come from TGameDetails.h and the credits
 * from dlgAboutDialog.cpp.
 *
 * Deliberately **not** Discord's own iframe widget. That widget is a fixed dark
 * 350x500 box with its own type, its own button and its own idea of the colour
 * scheme, and dropping it into a page that has two themes and a palette means
 * one element on the site that never matches it. Everything the iframe draws is
 * in the JSON behind it, so the theme draws the panel and Discord supplies only
 * the numbers.
 *
 * Two endpoints, because neither one answers the whole question:
 *
 *   /api/guilds/<id>/widget.json      presence_count, up to 100 members with
 *                                     avatars, an instant invite. No total.
 *   /api/v10/invites/<code>?with_counts=true
 *                                     approximate_member_count *and*
 *                                     approximate_presence_count. No members.
 *
 * Both are anonymous - no token, no application, no server of ours in between -
 * and the first works only because the server has the widget switched on, which
 * is the opt-in that makes any of this publishable. Turn the widget off in
 * Discord and the panel loses its faces and keeps its counts; take the whole
 * thing offline and it falls back to a plain invite button, which is what the
 * page said before this file existed.
 *
 * Theme code rather than a plugin, for the same reason as inc/demo-seed.php and
 * inc/download-email.php: unlike a game or a release it owns nothing. The
 * transient is a cache and not a record, and losing it costs one request.
 *
 * Nobody's name is rendered. The member list carries usernames and the faces
 * strip could print them, but the people in it did not choose to appear on
 * mudlet.org - the server admin enabled a widget. Avatars with no names are
 * texture; a wall of usernames is a directory of strangers.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/** How long a good answer is kept. Discord's own widget polls about this often. */
const MUDLET_DISCORD_TTL = 10 * MINUTE_IN_SECONDS;

/** How long a bad one is. Short, but not so short that an outage means a request per view. */
const MUDLET_DISCORD_FAIL_TTL = 3 * MINUTE_IN_SECONDS;

/**
 * The invite the Join button points at.
 *
 * The configured one wins over the `instant_invite` in widget.json, which is
 * generated and rotates; this is the link that has been on the site, in the
 * footer and in the client for years.
 *
 * @return string
 */
function mudlet_discord_invite_url(): string {
	/**
	 * Filter the Discord invite URL.
	 *
	 * @param string $url Invite URL.
	 */
	return (string) apply_filters( 'mudlet_discord_invite_url', 'https://discord.gg/kuYvMQ9' );
}

/**
 * The guild the counts are read from.
 *
 * A snowflake and not a name: the widget route is keyed by id, and the id of
 * the server behind the invite above is stable in a way the invite code is not.
 *
 * @return string
 */
function mudlet_discord_guild_id(): string {
	/**
	 * Filter the Discord guild id.
	 *
	 * @param string $id Guild snowflake.
	 */
	return (string) apply_filters( 'mudlet_discord_guild_id', '283581582550237184' );
}

/**
 * How many faces the strip shows before it gives up and counts.
 *
 * @return int
 */
function mudlet_discord_face_limit(): int {
	/**
	 * Filter the number of avatars drawn in the panel.
	 *
	 * @param int $limit Number of avatars.
	 */
	return max( 0, (int) apply_filters( 'mudlet_discord_face_limit', 12 ) );
}

/**
 * The invite code, off the end of the invite URL.
 *
 * One place to change the server, rather than a code and a URL that can drift
 * apart into a panel counting one server and linking to another.
 *
 * @return string Code, or '' when the URL is not an invite.
 */
function mudlet_discord_invite_code(): string {
	$path = (string) wp_parse_url( mudlet_discord_invite_url(), PHP_URL_PATH );
	$code = trim( (string) strrchr( '/' . ltrim( $path, '/' ), '/' ), '/' );

	return preg_match( '/^[A-Za-z0-9-]{2,64}$/', $code ) ? $code : '';
}

/**
 * Everything the panel draws, cached.
 *
 * Always an array, and always safe to render: `invite` is filled in even when
 * nothing came back, and every count is 0 rather than a guess. A caller shows
 * the numbers it was given and nothing else - printing "0 online" would be
 * inventing a fact, which is the thing this file exists to avoid.
 *
 * @return array{live:bool,name:string,invite:string,online:int,members:int,faces:string[]}
 */
function mudlet_discord_server(): array {
	$empty = array(
		'live'    => false,
		'name'    => '',
		'invite'  => mudlet_discord_invite_url(),
		'online'  => 0,
		'members' => 0,
		'faces'   => array(),
	);

	/**
	 * Short-circuit the lookup entirely.
	 *
	 * A site behind a firewall, or one that would rather not call Discord on a
	 * page render, returns the shape above and gets the plain invite button.
	 *
	 * @param array|null $server Pre-empted answer, or null to look it up.
	 */
	$pre = apply_filters( 'pre_mudlet_discord_server', null );
	if ( is_array( $pre ) ) {
		return array_merge( $empty, $pre );
	}

	$cached = get_transient( 'mudlet_discord_server' );
	if ( is_array( $cached ) ) {
		return array_merge( $empty, $cached );
	}

	$server = $empty;

	// The widget: who is on, and the faces.
	$widget = mudlet_discord_get( 'https://discord.com/api/guilds/' . rawurlencode( mudlet_discord_guild_id() ) . '/widget.json' );
	if ( $widget ) {
		$server['live']   = true;
		$server['name']   = (string) ( $widget['name'] ?? '' );
		$server['online'] = max( 0, (int) ( $widget['presence_count'] ?? 0 ) );

		foreach ( (array) ( $widget['members'] ?? array() ) as $member ) {
			if ( count( $server['faces'] ) >= mudlet_discord_face_limit() ) {
				break;
			}
			$url = (string) ( $member['avatar_url'] ?? '' );
			// Only Discord's own CDN. The field is a URL from a third party and
			// it is about to become an <img src> on mudlet.org.
			if ( $url && 'cdn.discordapp.com' === wp_parse_url( $url, PHP_URL_HOST ) ) {
				$server['faces'][] = $url;
			}
		}
	}

	// The invite: the total, which the widget does not carry.
	$code = mudlet_discord_invite_code();
	if ( $code ) {
		$invite = mudlet_discord_get( 'https://discord.com/api/v10/invites/' . rawurlencode( $code ) . '?with_counts=true' );
		if ( $invite ) {
			$server['live']    = true;
			$server['members'] = max( 0, (int) ( $invite['approximate_member_count'] ?? 0 ) );
			$server['name']    = $server['name'] ?: (string) ( $invite['guild']['name'] ?? '' );
			// Both endpoints count the same people a moment apart. Prefer the
			// widget's, and take this one only when the widget is off.
			if ( ! $server['online'] ) {
				$server['online'] = max( 0, (int) ( $invite['approximate_presence_count'] ?? 0 ) );
			}
		}
	}

	set_transient( 'mudlet_discord_server', $server, $server['live'] ? MUDLET_DISCORD_TTL : MUDLET_DISCORD_FAIL_TTL );

	return $server;
}

/**
 * One anonymous GET, decoded, or null.
 *
 * Short timeout on purpose: this runs while somebody is waiting for a page, and
 * a slow Discord must cost the panel its numbers rather than cost the visitor
 * the page.
 *
 * @param string $url Endpoint.
 * @return array|null Decoded body, or null on any failure at all.
 */
function mudlet_discord_get( string $url ): ?array {
	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 3,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	return is_array( $data ) ? $data : null;
}
