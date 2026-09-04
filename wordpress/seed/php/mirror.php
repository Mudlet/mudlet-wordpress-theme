<?php
/**
 * A stand-in for mudlet.org's own download mirror.
 *
 * On mudlet.org every build is served from the site itself: CI scps each asset
 * into wp-content/files/ as the platform finishes building, and the download
 * rows, the /latest/ aliases and "Browse the archive" all point there rather
 * than at GitHub. See wordpress/MIGRATION.md.
 *
 * A development copy has none of those files and should not: they are ~130 MB
 * each and this repo holds no build artifacts, on purpose. But with no mirror
 * at all the local site silently exercises the *other* branch - every link
 * falls back to GitHub - so the thing that changed is the thing you cannot see.
 *
 * So: one placeholder per asset the current release names, a few hundred bytes
 * each. The rows link locally, the aliases redirect locally, the archive index
 * lists something. What you download is a text file explaining itself, which is
 * the correct outcome for a fake.
 *
 * Sizes on the page still come from the release record - GitHub's real figures
 * - so a row will claim 124 MiB and hand over 300 bytes. That mismatch is
 * deliberate and is the cheapest possible reminder that this is not a mirror.
 *
 * SEED_MIRROR=0 skips it, which leaves every link pointing at GitHub.
 *
 * @package Mudlet
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! function_exists( 'mudlet_releases_get' ) ) {
	WP_CLI::warning( 'mirror: the releases plugin is not loaded - nothing to mirror.' );
	return;
}

$release = mudlet_releases_get( 'latest' );
$builds  = is_array( $release ) ? (array) ( $release['builds'] ?? array() ) : array();

if ( ! $builds ) {
	WP_CLI::warning( 'mirror: no release data yet - run the releases sync first.' );
	return;
}

$dir = WP_CONTENT_DIR . '/files';
if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
	WP_CLI::warning( 'mirror: could not create ' . $dir );
	return;
}

$version = (string) ( $release['version'] ?? '' );
$made    = 0;

foreach ( $builds as $build ) {
	$file = (string) ( $build['file'] ?? '' );
	if ( '' === $file || basename( $file ) !== $file ) {
		continue;
	}

	$path = $dir . '/' . $file;
	if ( file_exists( $path ) ) {
		continue;
	}

	$body = implode(
		"\n",
		array(
			'This is not Mudlet.',
			'',
			'It is a placeholder written by wordpress/seed/php/mirror.php so that a',
			'development copy of mudlet.org has something behind its download links.',
			'',
			'  build:   ' . (string) ( $build['label'] ?? '' ),
			'  version: ' . $version,
			'  file:    ' . $file,
			'',
			'The real one is at ' . (string) ( $build['github'] ?? $build['url'] ?? '' ),
			'',
		)
	);

	if ( false !== file_put_contents( $path, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- seeding a dev fixture, not site content.
		++$made;
	}
}

// "Browse the archive" points at this directory, and mudlet.org answers it with
// Apache's own index - mod_autoindex, dressed up server-side. The wordpress
// image ships Options -Indexes, so without this the archive link 403s locally
// and the one page that is pure server config never gets looked at. Dev only:
// on mudlet.org this is the server's business, not a file in the docroot.
$htaccess = $dir . '/.htaccess';
if ( ! file_exists( $htaccess ) ) {
	file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- seeding a dev fixture.
		$htaccess,
		"# Written by wordpress/seed/php/mirror.php. Development only - mudlet.org\n"
		. "# enables indexes in the server config instead.\n"
		. "Options +Indexes\n"
	);
}

// Nothing to configure: the plugin mirrors from wp-content/files whenever the
// file is actually there, and falls back to GitHub per asset when it is not. So
// writing the files *is* switching the mirror on.
WP_CLI::success(
	sprintf(
		'mirror: %d placeholder%s in %s',
		$made,
		1 === $made ? '' : 's',
		$dir
	)
);
