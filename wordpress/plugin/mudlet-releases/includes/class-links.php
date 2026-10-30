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
		add_action( 'parse_request', array( __CLASS__, 'legacy' ) );
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

		$url = self::current( $want );
		if ( '' === $url ) {
			return;
		}

		// Not wp_safe_redirect(): the mirror and GitHub are both off this
		// host as far as WordPress is concerned, and the target is one this
		// plugin built rather than anything a request supplied.
		wp_redirect( $url, 302 );
		exit;
	}

	/**
	 * Where the current release's build with this alias is downloaded from.
	 *
	 * @param string $alias A version-less asset name.
	 * @return string Empty when the current release has no such build.
	 */
	private static function current( string $alias ): string {
		$release = mudlet_releases_get( 'latest' );
		$builds  = is_array( $release ) ? (array) ( $release['builds'] ?? array() ) : array();

		foreach ( $builds as $build ) {
			$file = (string) ( $build['file'] ?? '' );
			if ( '' !== $file && self::alias( $file ) === $alias && '' !== (string) ( $build['url'] ?? '' ) ) {
				return (string) $build['url'];
			}
		}

		return '';
	}

	/**
	 * Answer a WP-DownloadManager `/download/<id>/` link the plugin no longer
	 * serves, with the current build of the same thing.
	 *
	 * Those links are not stable. Each release adds four new entries and hides
	 * the four before it, and a hidden entry 404s - so every `/download/<id>/`
	 * ever pasted into a forum post stopped working the day the next release
	 * went out, and the one in yesterday's post will stop at the next.
	 *
	 * The entry still says which file it was, and that file's alias is the same
	 * derivation `/latest/` answers on, so `Mudlet-5.0.1-windows-64-installer.exe`
	 * goes to today's Windows installer with no table of IDs typed anywhere. A
	 * build that no longer exists under that name - the single pre-arm64 `.dmg`,
	 * 32-bit Windows, the source tarball, which is mirrored but never on the
	 * release - goes to the download page rather than to a guess.
	 *
	 * An entry the plugin still serves is left to it, so its hit count keeps
	 * counting. With WP-DownloadManager gone every entry lands here, which is
	 * what retiring it needs and nothing more: its table is read, not owned.
	 *
	 * @param WP $wp Current request.
	 */
	public static function legacy( $wp ): void {
		// Both shapes the plugin answers: the pretty one, and ?dl_id= - which is
		// a query var only while the plugin is there to register it.
		$path = isset( $wp->request ) ? (string) $wp->request : '';
		if ( preg_match( '#^download/(\d+)$#', $path, $m ) ) {
			$id = (int) $m[1];
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public link, read as an integer.
			$id = (int) ( $wp->query_vars['dl_id'] ?? ( $_GET['dl_id'] ?? 0 ) );
		}
		if ( $id <= 0 ) {
			return;
		}

		global $wpdb;
		$served = isset( $wpdb->downloads );
		$quiet  = $wpdb->suppress_errors();
		$entry  = $wpdb->get_row(
			$wpdb->prepare( "SELECT file, file_permission FROM {$wpdb->prefix}downloads WHERE file_id = %d", $id )
		);
		$wpdb->suppress_errors( $quiet );

		// A visible entry is the plugin's to serve; a missing one has nothing
		// to say it was ever a build, while the plugin is still around to own
		// its own 404.
		if ( $served && ( ! $entry || -2 !== (int) $entry->file_permission ) ) {
			return;
		}

		$alias = $entry ? self::alias( basename( (string) $entry->file ) ) : '';
		$url   = '' === $alias ? '' : self::current( $alias );

		// 302 either way: both targets move, the first at every release.
		wp_redirect( '' !== $url ? $url : home_url( '/download/' ), 302 );
		exit;
	}
}
