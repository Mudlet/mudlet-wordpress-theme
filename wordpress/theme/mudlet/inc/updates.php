<?php
/**
 * Updating the theme — and, inside it, the three plugins — from a GitHub release.
 *
 * ---------------------------------------------------------------------------
 *
 * Why not just upload the zip.
 *
 * Because somebody has to remember to, and because the theme now carries the
 * games, makers and releases plugins (see inc/bundled-plugins.php), so
 * "somebody has to remember to" used to mean four uploads. The whole site is
 * one archive on a GitHub release; this is what teaches WordPress to see it.
 *
 * ---------------------------------------------------------------------------
 *
 * How WordPress is told.
 *
 * `style.css` carries an `Update URI:` header. Since 6.1 that does two things:
 * it stops WordPress asking wordpress.org about a theme it does not host — the
 * old failure mode where a same-named theme on .org offers to overwrite yours —
 * and it fires `update_themes_<hostname>` so somebody can answer instead. This
 * file is the answer for github.com.
 *
 * Nothing here is registered against a hard-coded repository. The `Update URI`
 * is the single source of truth and the owner/name is read back out of it, so
 * a fork that changes one header line gets its own updates and not ours.
 *
 * ---------------------------------------------------------------------------
 *
 * What it asks for, and how often.
 *
 * One unauthenticated `releases/latest` call, cached twelve hours — GitHub
 * allows sixty an hour per IP unauthenticated, and this shares that budget
 * with the star count on the front page and the clerk in the demo world.
 * `releases/latest` is already "the newest published, non-draft,
 * non-prerelease", so a prerelease is skipped by GitHub rather than by us.
 *
 * The package is the release's own `mudlet.zip` asset, never the `zipball_url`:
 * a zipball is the whole repository under a top-level folder named for the
 * commit, and WordPress installs a theme *by the folder inside the archive*.
 * Ours is built by tools/build-dist.mjs with exactly one folder in it, named
 * `mudlet`, which is why an update lands on top of the theme instead of beside
 * it. No asset, no update offered.
 *
 * A failure is cached too, for an hour: an outage should cost one request an
 * hour, not one per admin page load.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/** Where the answer from GitHub is parked between checks. */
const MUDLET_UPDATE_KEY = 'mudlet_theme_update';

/** The asset on the release that is this theme. */
const MUDLET_UPDATE_ASSET = 'mudlet.zip';

add_filter( 'update_themes_github.com', 'mudlet_theme_update', 10, 3 );
/**
 * Answer WordPress's update check for a theme whose Update URI is on GitHub.
 *
 * Returns the array core wants, or the `$update` it was handed — normally
 * false — when there is nothing to offer. Deliberately does not compare
 * versions: core does that itself, and doing it here as well is two places to
 * get `version_compare()` the wrong way round.
 *
 * The two `requires` are read back off the theme rather than out of
 * `$theme_data`, which carries only the eight headers core needs to run an
 * update check and neither of these. They are not decoration: core refuses to
 * install an update whose `requires_php` the server cannot meet, and an empty
 * one means it never refuses.
 *
 * @param array|false $update     Whatever an earlier filter decided.
 * @param array       $theme_data The theme's headers, as core collected them.
 * @param string      $stylesheet The theme's directory name.
 * @return array|false
 */
function mudlet_theme_update( $update, array $theme_data, string $stylesheet ) {
	$repo = mudlet_update_repo( $theme_data['UpdateURI'] ?? '' );
	if ( ! $repo ) {
		return $update;
	}

	$release = mudlet_update_release( $repo );
	if ( ! $release ) {
		return $update;
	}

	$theme = wp_get_theme( $stylesheet );

	return array(
		'id'           => $theme_data['UpdateURI'] ?? '',
		'theme'        => $stylesheet,
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires'     => (string) $theme->get( 'RequiresWP' ),
		'requires_php' => (string) $theme->get( 'RequiresPHP' ),
	);
}

/**
 * `owner/name` out of an Update URI, or '' if it is not a GitHub repository.
 *
 * Only github.com, and only the two-segment repository form: the filter this
 * feeds is keyed on the hostname, but a header saying
 * `https://github.com/orgs/Mudlet` would reach it too and must not be read as
 * a repository.
 *
 * @param string $uri The Update URI header.
 */
function mudlet_update_repo( string $uri ): string {
	$host = wp_parse_url( $uri, PHP_URL_HOST );
	if ( 'github.com' !== $host ) {
		return '';
	}

	$path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
	$bits = explode( '/', $path );

	if ( 2 !== count( $bits ) || '' === $bits[0] || '' === $bits[1] ) {
		return '';
	}

	return $bits[0] . '/' . preg_replace( '/\.git$/', '', $bits[1] );
}

/**
 * The latest release of a repository, as version / url / package.
 *
 * Null when there is no usable one — no release, no `mudlet.zip` on it, or
 * GitHub unreachable. Every one of those is cached, so the difference between
 * "nothing to update to" and "could not ask" costs the same one request an
 * hour rather than one per page.
 *
 * @param string $repo owner/name.
 * @return array{version:string,url:string,package:string}|null
 */
function mudlet_update_release( string $repo ): ?array {
	$key    = MUDLET_UPDATE_KEY . '_' . md5( $repo );
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		return $cached['version'] ? $cached : null;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . $repo . '/releases/latest',
		array(
			'timeout' => 8,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'mudlet.org',
			),
		)
	);

	$release = array(
		'version' => '',
		'url'     => '',
		'package' => '',
	);

	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// The tag is `v1.2.3`; a theme header is `1.2.3`. version_compare()
		// treats a leading letter as an older release than any number, so a
		// tag left unstripped means the site is never offered an update.
		$version = ltrim( (string) ( $body['tag_name'] ?? '' ), 'vV' );

		foreach ( (array) ( $body['assets'] ?? array() ) as $asset ) {
			if ( MUDLET_UPDATE_ASSET === ( $asset['name'] ?? '' ) && $version ) {
				$release = array(
					'version' => $version,
					'url'     => (string) ( $body['html_url'] ?? '' ),
					'package' => (string) ( $asset['browser_download_url'] ?? '' ),
				);
				break;
			}
		}
	}

	set_transient( $key, $release, $release['version'] ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );

	return $release['version'] ? $release : null;
}

add_filter( 'auto_update_theme', 'mudlet_theme_auto_update', 10, 2 );
/**
 * Keep this theme up to date on its own.
 *
 * WordPress leaves theme auto-updates off until somebody turns them on per
 * theme, which for a site that is one theme and three plugins in one archive
 * is a switch nobody would ever want the other way — a site running a release
 * behind is running a release behind on all four.
 *
 * Only ever an opinion about *this* theme: another theme's answer is passed
 * through untouched. A site that disagrees can say so with the
 * `MUDLET_AUTO_UPDATE` constant in wp-config.php, which is the one place a
 * host can reach without editing files that the next update overwrites.
 *
 * @param bool|null $enabled Whatever core or an earlier filter decided.
 * @param object    $theme   The update object; `->theme` is the stylesheet.
 * @return bool|null
 */
function mudlet_theme_auto_update( $enabled, $theme ) {
	if ( ! isset( $theme->theme ) || get_stylesheet() !== $theme->theme ) {
		return $enabled;
	}

	return defined( 'MUDLET_AUTO_UPDATE' ) ? (bool) MUDLET_AUTO_UPDATE : true;
}

add_action( 'upgrader_process_complete', 'mudlet_update_forget', 10, 2 );
/**
 * Drop the cached answer once an update has been installed.
 *
 * Otherwise the twelve-hour transient keeps offering the release that was just
 * installed, and the admin's Updates screen disagrees with itself until it
 * expires.
 *
 * @param WP_Upgrader $upgrader The upgrader that ran.
 * @param array       $hook_extra What it was asked to do.
 */
function mudlet_update_forget( $upgrader, array $hook_extra ): void {
	unset( $upgrader );

	if ( 'theme' !== ( $hook_extra['type'] ?? '' ) ) {
		return;
	}

	$uri = wp_get_theme()->get( 'UpdateURI' );
	if ( $uri ) {
		delete_transient( MUDLET_UPDATE_KEY . '_' . md5( mudlet_update_repo( $uri ) ) );
	}
}
