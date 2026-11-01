<?php
/**
 * Empty the page caches when the release the site offers changes.
 *
 * The release data is never stale: mudlet_release() reads the release store
 * on every render. What goes stale is the HTML made from it - WP Super Cache
 * keeps a static copy of every page, and Cloudflare keeps another in front of
 * that - so on release day /download/ went on saying the previous version until
 * somebody cleared both by hand.
 *
 * So this watches the one thing that matters, a fingerprint of what
 * mudlet_release() returns (version, date, every build's URL, size and hash),
 * and when it moves, purges both. Not "a release record was written": the
 * webhook writes one the moment a release is created, with no installers on
 * it, and the page does not change until the follow-up finds them. Purging on
 * every write would empty the cache for nothing several times a day; purging
 * on the fingerprint empties it once or twice a release.
 *
 * Everything is purged, not a list of URLs. The version is not only on
 * /download/: page-layouts.php puts a "Download 5.1.0" card into any page that
 * links there, the demo seed endpoint quotes it, and every Polylang language
 * has its own copy of each. A list would be the next thing to go stale, and
 * this happens a few times a year.
 *
 * WP Super Cache first, then Cloudflare - the other way round, the first
 * visitor to reach Cloudflare refills it from the stale Super Cache copy.
 *
 * Cloudflare needs two constants in wp-config.php, and without them that half
 * simply does not run:
 *
 *     define( 'MUDLET_CLOUDFLARE_ZONE_ID', '…' );   // Overview page, right column
 *     define( 'MUDLET_CLOUDFLARE_API_TOKEN', '…' ); // a token with Zone → Cache Purge only
 *
 * Constants and not an option, because the token can empty the whole zone and
 * an option is readable by anything with a database dump.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/** Where the fingerprint of the last release the caches were emptied for lives. */
const MUDLET_PAGE_CACHE_SEEN = 'mudlet_release_cached_as';

/**
 * A short hash of everything about the release that reaches a page.
 *
 * @return string
 */
function mudlet_release_fingerprint(): string {
	$release = mudlet_release();
	$parts   = array( (string) $release['version'], (string) $release['date'] );

	foreach ( (array) $release['builds'] as $key => $build ) {
		$parts[] = implode( '|', array( $key, $build['url'] ?? '', $build['size'] ?? '', $build['sha'] ?? '' ) );
	}

	return substr( md5( implode( "\n", $parts ) ), 0, 16 );
}

/**
 * Note that the release may have changed; look once, at the end of the request.
 *
 * At shutdown and not on the spot, because a store write is a dozen meta
 * updates in a row and the version is written after the builds: checking on
 * the first one would see half a release.
 */
function mudlet_page_cache_maybe_changed(): void {
	static $booked = false;
	if ( $booked ) {
		return;
	}
	$booked = true;
	add_action( 'shutdown', 'mudlet_page_cache_check', 5 );
}

/**
 * Purge if the release a page would show is not the one the caches were filled with.
 */
function mudlet_page_cache_check(): void {
	$now = mudlet_release_fingerprint();
	if ( get_option( MUDLET_PAGE_CACHE_SEEN ) === $now ) {
		return;
	}

	// Written first, so a purge that throws or times out is not retried on every
	// request after it - the failure is logged, and the caches expire anyway.
	update_option( MUDLET_PAGE_CACHE_SEEN, $now, false );

	mudlet_page_cache_purge( sprintf( 'release %s', mudlet_release_version() ) );
}

/**
 * Empty WP Super Cache, then Cloudflare.
 *
 * @param string $why For the log.
 */
function mudlet_page_cache_purge( string $why = '' ): void {
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();
	}

	mudlet_cloudflare_purge( $why );

	/**
	 * After the page caches were emptied because the release changed.
	 *
	 * The place to empty any other cache in front of the site.
	 *
	 * @param string $why What changed.
	 */
	do_action( 'mudlet_page_cache_purged', $why );
}

/**
 * Ask Cloudflare to drop everything it holds for the zone.
 *
 * @param string $why For the log.
 * @return bool Whether Cloudflare said it did.
 */
function mudlet_cloudflare_purge( string $why = '' ): bool {
	if ( ! defined( 'MUDLET_CLOUDFLARE_ZONE_ID' ) || ! defined( 'MUDLET_CLOUDFLARE_API_TOKEN' ) ) {
		return false;
	}

	$response = wp_remote_post(
		'https://api.cloudflare.com/client/v4/zones/' . rawurlencode( (string) MUDLET_CLOUDFLARE_ZONE_ID ) . '/purge_cache',
		array(
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Bearer ' . MUDLET_CLOUDFLARE_API_TOKEN,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array( 'purge_everything' => true ) ),
		)
	);

	$body = is_wp_error( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );
	$ok   = is_array( $body ) && ! empty( $body['success'] );

	if ( ! $ok ) {
		$error = is_wp_error( $response )
			? $response->get_error_message()
			: (string) ( $body['errors'][0]['message'] ?? 'HTTP ' . wp_remote_retrieve_response_code( $response ) );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( '[mudlet] Cloudflare purge for %s failed: %s', $why, $error ) );
	}

	return $ok;
}

// A release record's installers, or its being a prerelease, are what decide
// whether the download page changes; save_post and deleted_post cover a record
// appearing or going away. Core hooks rather than an action of the releases
// plugin's own, so this holds against whatever copy of the plugin is installed.
add_action(
	'added_post_meta',
	'mudlet_page_cache_on_meta',
	10,
	3
);
add_action(
	'updated_post_meta',
	'mudlet_page_cache_on_meta',
	10,
	3
);
/**
 * @param int    $meta_id   Unused.
 * @param int    $object_id Post id.
 * @param string $meta_key  Meta key.
 */
function mudlet_page_cache_on_meta( $meta_id, $object_id, $meta_key ): void {
	if ( in_array( $meta_key, array( '_mudlet_builds', '_mudlet_prerelease', '_mudlet_version' ), true )
		&& 'mudlet_release' === get_post_type( (int) $object_id ) ) {
		mudlet_page_cache_maybe_changed();
	}
}

add_action( 'save_post_mudlet_release', 'mudlet_page_cache_maybe_changed' );
add_action(
	'deleted_post',
	static function ( $post_id, $post = null ): void {
		if ( $post instanceof WP_Post && 'mudlet_release' === $post->post_type ) {
			mudlet_page_cache_maybe_changed();
		}
	},
	10,
	2
);

// The manual override in inc/downloads.php moves the page just the same.
add_action( 'add_option_mudlet_release', 'mudlet_page_cache_maybe_changed' );
add_action( 'update_option_mudlet_release', 'mudlet_page_cache_maybe_changed' );
add_action( 'delete_option_mudlet_release', 'mudlet_page_cache_maybe_changed' );
