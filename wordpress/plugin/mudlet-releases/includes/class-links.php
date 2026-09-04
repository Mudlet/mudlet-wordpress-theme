<?php
/**
 * Where a download link points.
 *
 * Three shapes, and each is answering a different question.
 *
 * 1. **The versioned file** is what a row links. The row prints a SHA-256 next
 *    to it, and a checksum has to describe the file at the other end of that
 *    link - so a row can never point at something that changes underneath it.
 *
 * 2. **The stable alias**, `/latest/<name>`, is what goes in the QR code, the
 *    emailed link, a forum post or the wiki. Those are links that travel: to a
 *    phone, to an inbox, into a thread somebody reads in three years. A
 *    version-pinned URL is wrong for every one of them.
 *
 * 3. **GitHub** stays as the second link on each row, because a mirror that
 *    nobody can route around is a single point of failure.
 *
 * The alias name is *derived*, not typed: it is the release's own asset name
 * with the version taken out, so `Mudlet-5.0.1-windows-64-installer.exe`
 * answers at `/latest/Mudlet-windows-64-installer.exe`. A hand-written table of
 * pretty names would read slightly better and would be one more list to keep in
 * step with the release workflow - the same trade this plugin already refuses
 * everywhere else.
 *
 * Both of the first two keep the file extension, and that is not cosmetic:
 * Matomo classifies a link as a download by its extension, which is why
 * `/download/71/` - the WP-DownloadManager link the site used before - was
 * never counted once. See wordpress/ANALYTICS.md.
 *
 * The route is answered on `parse_request` rather than through
 * `add_rewrite_rule()`, so it works the moment the plugin loads instead of
 * 404ing until somebody flushes permalinks.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * Download link resolution.
 */
class Mudlet_Releases_Links {

	/**
	 * Where the site's own copies live, under wp-content.
	 */
	const DIR = 'files';

	/**
	 * First path segment the stable aliases answer on.
	 */
	const ROUTE = 'latest';

	/**
	 * Hook up the alias route.
	 */
	public static function init(): void {
		add_action( 'parse_request', array( __CLASS__, 'route' ) );
	}

	/**
	 * The base URL every build is mirrored under, without a trailing slash.
	 *
	 * `wp-content/files` on this site, because that is where mudlet.org's builds
	 * already are: CI scps each asset there as the platform finishes building,
	 * before the GitHub release even exists.
	 *
	 * @return string
	 */
	public static function mirror(): string {
		/**
		 * Filter the mirror base URL, for a site serving its builds elsewhere.
		 *
		 * @param string $base Base URL.
		 */
		return untrailingslashit( (string) apply_filters( 'mudlet_releases_mirror', content_url( self::DIR ) ) );
	}

	/**
	 * Whether the mirror really has this asset.
	 *
	 * The default mirror is a directory on this very filesystem, so "is it
	 * there" is a `stat` rather than a request - which is what makes the
	 * fallback below honest instead of hopeful. A development copy, a fork, or
	 * a release that CI failed to upload falls through to GitHub, and does so
	 * per asset rather than all-or-nothing.
	 *
	 * A filtered mirror is somebody else's host and is taken at its word;
	 * checking it would mean an HTTP request per row.
	 *
	 * @param string $file Asset filename.
	 * @return bool
	 */
	private static function mirrored( string $file ): bool {
		if ( '' === $file ) {
			return false;
		}

		if ( self::mirror() !== untrailingslashit( content_url( self::DIR ) ) ) {
			return true;
		}

		return file_exists( WP_CONTENT_DIR . '/' . self::DIR . '/' . $file );
	}

	/**
	 * Where a given asset should actually be downloaded from.
	 *
	 * @param string $file   Asset filename.
	 * @param string $github The release's own browser_download_url.
	 * @return string
	 */
	public static function url( string $file, string $github ): string {
		if ( ! self::mirrored( $file ) ) {
			return $github;
		}

		return self::mirror() . '/' . rawurlencode( $file );
	}

	/**
	 * Add the two site-dependent links to a stored build table.
	 *
	 * Called on the way out of the store rather than on the way in, and that is
	 * the whole point: whether a build is mirrored is a question about this
	 * filesystem right now, and the alias is built from `home_url()`. Neither is
	 * a fact about the release, so storing either would mean forty records to
	 * re-sync the day CI backfills a missing asset or the site URL changes.
	 *
	 * `url` is rewritten to the mirror, and what GitHub said moves to `github`.
	 *
	 * @param array<string, array<string, mixed>> $builds Stored builds.
	 * @return array<string, array<string, mixed>>
	 */
	public static function decorate( array $builds ): array {
		foreach ( $builds as $key => $build ) {
			$file   = (string) ( $build['file'] ?? '' );
			$github = (string) ( $build['url'] ?? '' );

			$builds[ $key ]['github'] = $github;
			$builds[ $key ]['url']    = self::url( $file, $github );
			$builds[ $key ]['latest'] = self::latest( $file );
		}

		return $builds;
	}

	/**
	 * An asset's name with the version taken out.
	 *
	 * Mudlet-5.0.1-windows-64-installer.exe -> Mudlet-windows-64-installer.exe
	 * Mudlet-5.0.1.tar.xz                   -> Mudlet.tar.xz
	 *
	 * Only the first version-shaped run is dropped, so an asset that carries a
	 * number for some other reason keeps it.
	 *
	 * @param string $file Asset filename.
	 * @return string Alias name, or '' if the name carries no version.
	 */
	public static function alias( string $file ): string {
		$alias = preg_replace( '/-\d+(?:\.\d+)+/', '', $file, 1 );

		return ( is_string( $alias ) && $alias !== $file ) ? $alias : '';
	}

	/**
	 * The public URL of an asset's stable alias.
	 *
	 * @param string $file Asset filename.
	 * @return string Empty when the name carries no version to strip.
	 */
	public static function latest( string $file ): string {
		$alias = self::alias( $file );

		return '' === $alias ? '' : home_url( '/' . self::ROUTE . '/' . $alias );
	}

	/**
	 * Answer /latest/<name> with a redirect to the current build.
	 *
	 * 302, never 301: the whole point of the URL is that its target moves every
	 * release, and a browser that cached it permanently would pin somebody to
	 * the version that happened to be current the first time they used it.
	 *
	 * @param WP $wp Current request.
	 */
	public static function route( $wp ): void {
		$path = isset( $wp->request ) ? (string) $wp->request : '';
		if ( 0 !== strpos( $path, self::ROUTE . '/' ) ) {
			return;
		}

		$want = rawurldecode( substr( $path, strlen( self::ROUTE ) + 1 ) );
		if ( '' === $want || false !== strpos( $want, '/' ) ) {
			return;
		}

		$release = mudlet_releases_get( 'latest' );
		$builds  = is_array( $release ) ? (array) ( $release['builds'] ?? array() ) : array();

		foreach ( $builds as $build ) {
			$file = (string) ( $build['file'] ?? '' );
			if ( '' === $file || self::alias( $file ) !== $want ) {
				continue;
			}

			$url = (string) ( $build['url'] ?? '' );
			if ( '' === $url ) {
				continue;
			}

			// Not wp_safe_redirect(): the mirror and GitHub are both off this
			// host as far as WordPress is concerned, and the target is one this
			// plugin built rather than anything a request supplied.
			wp_redirect( $url, 302 );
			exit;
		}
	}
}
