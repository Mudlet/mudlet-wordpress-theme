<?php
/**
 * Talking to the GitHub releases API.
 *
 * Everything here is cached and nothing here throws. A release page that cannot
 * reach GitHub should show slightly old information, or fall back to whatever
 * the theme hardcodes - never an error and never a blank table.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * A small, cached GitHub client.
 */
class Mudlet_Releases_Github_Client {

	/** How long a good answer is kept. */
	const TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * How long a failure is remembered.
	 *
	 * Without this, GitHub being down turns into one outbound request per page
	 * view, each one waiting out the timeout - which takes a site that is merely
	 * showing stale data and makes it unusably slow.
	 */
	const FAIL_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * The repository releases are read from.
	 *
	 * @return string owner/name
	 */
	public static function repo(): string {
		/**
		 * Filter the GitHub repository releases come from.
		 *
		 * @param string $repo owner/name.
		 */
		return (string) apply_filters( 'mudlet_releases_repo', 'Mudlet/Mudlet' );
	}

	/**
	 * Fetch one release.
	 *
	 * Accepts 'latest', a numeric release id, or a tag. Tags are what this
	 * plugin is built around; ids are supported because the older release
	 * plugin wrote them into post content, and that content still exists.
	 *
	 * A bare version - "4.22.0" - is tried as "Mudlet-4.22.0" too, so an editor
	 * can type either and be right.
	 *
	 * @param string $ref 'latest', a release id, or a tag.
	 * @return array<string, mixed>|null Raw release JSON.
	 */
	public static function release( string $ref ) {
		$ref = trim( $ref );
		if ( '' === $ref ) {
			return null;
		}

		$candidates = self::endpoints( $ref );
		$key        = 'mudlet_rel_' . md5( self::repo() . '|' . $ref );

		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( 'miss' === $cached ) {
			return null;
		}

		foreach ( $candidates as $path ) {
			$data = self::get_json( 'https://api.github.com/repos/' . self::repo() . '/' . $path );
			if ( is_array( $data ) && ! empty( $data['tag_name'] ) ) {
				set_transient( $key, $data, self::TTL );
				return $data;
			}
		}

		set_transient( $key, 'miss', self::FAIL_TTL );
		return null;
	}

	/**
	 * The API paths worth trying for a given reference, in order.
	 *
	 * @param string $ref Reference.
	 * @return string[]
	 */
	private static function endpoints( string $ref ): array {
		if ( 'latest' === strtolower( $ref ) ) {
			return array( 'releases/latest' );
		}

		// A release id. The old plugin stored these, and note that
		// releases/tags/<id> is *not* the same endpoint - asking for an id by
		// tag is a 404, which is the bug that made imported posts render empty.
		if ( ctype_digit( $ref ) ) {
			return array( 'releases/' . rawurlencode( $ref ) );
		}

		$paths = array( 'releases/tags/' . rawurlencode( $ref ) );

		// "4.22.0" -> also try "Mudlet-4.22.0"
		if ( ! preg_match( '/^Mudlet[-_]/i', $ref ) ) {
			$paths[] = 'releases/tags/' . rawurlencode( 'Mudlet-' . $ref );
		}

		return $paths;
	}

	/**
	 * GET a URL and decode it as JSON.
	 *
	 * @param string $url Absolute URL.
	 * @return array<string, mixed>|null
	 */
	public static function get_json( string $url ) {
		$body = self::get_body( $url, 'application/vnd.github+json' );
		if ( null === $body ) {
			return null;
		}
		$data = json_decode( $body, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * GET a URL and return its body.
	 *
	 * @param string $url    Absolute URL.
	 * @param string $accept Accept header.
	 * @return string|null
	 */
	public static function get_body( string $url, string $accept = '*/*' ) {
		$args = array(
			'timeout'    => 5,
			'user-agent' => 'mudlet-releases/' . MUDLET_RELEASES_VERSION,
			'headers'    => array( 'Accept' => $accept ),
		);

		// Unauthenticated GitHub allows 60 requests an hour per IP. A cached
		// read needs very few, but building a changelog for a large release
		// takes six at once, and a site warming several at a time will hit that
		// ceiling. A token raises it to 5000.
		//
		// Define MUDLET_RELEASES_GITHUB_TOKEN in wp-config.php - never in a
		// theme or a repository - or add the header with the filter below.
		if ( defined( 'MUDLET_RELEASES_GITHUB_TOKEN' ) && MUDLET_RELEASES_GITHUB_TOKEN ) {
			$args['headers']['Authorization'] = 'Bearer ' . MUDLET_RELEASES_GITHUB_TOKEN;
		}

		/**
		 * Filter the HTTP arguments used for GitHub requests.
		 *
		 * A site hitting the API rate limit can add an Authorization header
		 * here; unauthenticated GitHub allows 60 requests an hour per IP, which
		 * is plenty for a cached read but not for a busy shared host.
		 *
		 * @param array<string, mixed> $args HTTP arguments.
		 * @param string               $url  The URL being fetched.
		 */
		$args = apply_filters( 'mudlet_releases_http_args', $args, $url );

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return null;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		return '' === $body ? null : $body;
	}

	/**
	 * Forget everything cached for one reference, or all of them.
	 *
	 * @param string $ref Reference, or '' for the scheduled 'latest'.
	 */
	public static function flush( string $ref = '' ): void {
		if ( '' !== $ref ) {
			delete_transient( 'mudlet_rel_' . md5( self::repo() . '|' . $ref ) );
			return;
		}
		delete_transient( 'mudlet_rel_' . md5( self::repo() . '|latest' ) );
	}
}

add_action( 'mudlet_releases_refresh', 'mudlet_releases_refresh_latest' );
/**
 * Keep the latest release warm, so a visitor never waits on GitHub.
 */
function mudlet_releases_refresh_latest(): void {
	Mudlet_Releases_Github_Client::flush();
	Mudlet_Releases_Github_Client::release( 'latest' );
}

add_action( 'init', 'mudlet_releases_schedule_refresh' );
/**
 * Schedule that refresh if it is not already.
 */
function mudlet_releases_schedule_refresh(): void {
	if ( ! wp_next_scheduled( 'mudlet_releases_refresh' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'mudlet_releases_refresh' );
	}
}
