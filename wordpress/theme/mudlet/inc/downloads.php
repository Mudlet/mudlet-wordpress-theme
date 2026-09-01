<?php
/**
 * The release the download page offers.
 *
 * Read from GitHub - see inc/github-releases.php, which is where the version,
 * the asset sizes, the download URLs and the SHA-256 hashes all come from. The
 * values below are only a floor: what the page shows if GitHub cannot be
 * reached and nothing has been cached yet, so a network problem degrades to a
 * stale table rather than an empty one. They are the 4.22.0 figures the
 * prototype was drawn against, and they match that release exactly.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Everything about the current release.
 *
 * Precedence is defaults < GitHub < the `mudlet_release` option. The option is
 * an explicit override, for pinning a version or correcting a URL; nothing
 * writes it automatically.
 *
 * @return array<string, mixed>
 */
function mudlet_release(): array {
	$defaults = array(
		'version' => '4.22.0',
		'date'    => '2026-07-06',
		'builds'  => array(
			'win'    => array(
				'label' => __( 'Windows', 'mudlet' ),
				'short' => __( 'Windows', 'mudlet' ),
				'note'  => __( '64-bit installer', 'mudlet' ),
				'size'  => '128.8 MiB',
				'sha'   => 'b9f49c8dd96f5ca736d37767ddde9b4e5cf9d898f77b3de3186ec46b9089bd39',
				'url'   => 'https://www.mudlet.org/download/',
			),
			'macarm' => array(
				'label' => __( 'macOS, Apple Silicon', 'mudlet' ),
				'short' => __( 'macOS', 'mudlet' ),
				'note'  => 'arm64',
				'size'  => '130.1 MiB',
				'sha'   => '54d976936d9ad54cc1ddd65d5e1cc2e3253d84646430636d3237455a10261bdb',
				'url'   => 'https://www.mudlet.org/download/',
			),
			'macx86' => array(
				'label' => __( 'macOS, Intel', 'mudlet' ),
				'short' => __( 'macOS', 'mudlet' ),
				'note'  => 'x86_64',
				'size'  => '131.7 MiB',
				'sha'   => '64371626f0af7a3ab2f276100e57f6d44344b157cd3f405601955677e64ac6d7',
				'url'   => 'https://www.mudlet.org/download/',
			),
			'linux'  => array(
				'label' => __( 'Linux', 'mudlet' ),
				'short' => __( 'Linux', 'mudlet' ),
				'note'  => 'AppImage',
				'size'  => '170.4 MiB',
				'sha'   => '8f10a78ab918d4b46b1f842c1ca7522b9c26aa8200f657bd8fd5ccba8a7c9040',
				'url'   => 'https://www.mudlet.org/download/',
			),
		),
	);

	$release = $defaults;

	// GitHub, when it has answered at some point in the last twelve hours.
	// Merged rather than swapped in so a release missing an asset for one
	// platform still shows the other three with real data.
	$latest = mudlet_github_release( 'latest' );
	if ( $latest && ! empty( $latest['builds'] ) ) {
		$release['version'] = $latest['version'];
		$release['date']    = $latest['date'];
		foreach ( $latest['builds'] as $key => $build ) {
			$release['builds'][ $key ] = array_merge( $release['builds'][ $key ] ?? array(), $build );
		}
	}

	$stored = get_option( 'mudlet_release' );
	if ( is_array( $stored ) ) {
		$release = array_replace_recursive( $release, $stored );
	}

	/**
	 * Filter the release the download page describes.
	 *
	 * @param array<string, mixed> $release Release data.
	 */
	return apply_filters( 'mudlet_release', $release );
}

/**
 * @return string
 */
function mudlet_release_version(): string {
	return (string) mudlet_release()['version'];
}

/**
 * The release date, formatted with the site's own format.
 *
 * @param string|null $format Optional date format.
 * @return string
 */
function mudlet_release_date( ?string $format = null ): string {
	$stamp = strtotime( (string) mudlet_release()['date'] );
	return $stamp ? wp_date( $format ?: (string) get_option( 'date_format' ), $stamp ) : '';
}

/**
 * @return array<string, array<string, string>>
 */
function mudlet_release_builds(): array {
	return (array) mudlet_release()['builds'];
}

/**
 * What theme.js needs to lead with the visitor's own platform.
 *
 * The detection stays in the browser - it is the one thing the server cannot
 * do without either a user-agent database or a cache-busting Vary header - but
 * every string it puts on screen comes from here.
 *
 * @return array<string, mixed>
 */
function mudlet_download_script_data(): array {
	$builds = array();
	foreach ( mudlet_release_builds() as $key => $b ) {
		$builds[ $key ] = array(
			'label' => $b['label'],
			'short' => $b['short'],
			/* translators: 1: build note such as "64-bit installer", 2: file size */
			'meta'  => sprintf( '%1$s &middot; %2$s', $b['note'], $b['size'] ),
			/* translators: %s: short platform name */
			'cta'   => sprintf( __( 'Download for %s', 'mudlet' ), $b['short'] ),
			'url'   => $b['url'],
		);
	}

	// The platform icon is swapped in with the copy, so the markup has to reach
	// the browser. Rendered here rather than duplicated in JS, so there is one
	// icon set in the theme and not two.
	$icons = array();
	foreach ( array( 'win' => 'windows', 'macarm' => 'apple', 'macx86' => 'apple', 'linux' => 'linux', 'cros' => 'chrome' ) as $key => $icon ) {
		$icons[ $key ] = mudlet_get_icon( $icon );
	}

	return array(
		'version' => mudlet_release_version(),
		'builds'  => $builds,
		'icons'   => $icons,
		'strings' => array(
			/* translators: 1: version, 2: platform */
			'heading'  => __( 'Mudlet %1$s for %2$s', 'mudlet' ),
			'crosName' => __( 'Mudlet on ChromeOS', 'mudlet' ),
			'crosMeta' => __( 'Installs through Linux (Crostini)', 'mudlet' ),
			'crosCta'  => __( 'Read the instructions', 'mudlet' ),
			'intelAlt' => __( 'On an Intel Mac? Get the x86_64 build instead.', 'mudlet' ),
			/* translators: %s: platform name, as it reads in the table */
			'handoff'  => __( 'Point a phone at the code, or copy the link — it downloads the %s build.', 'mudlet' ),
			// What the copy button says when there is no clipboard to write
			// to. The link is printed beside it either way, so this points at
			// it rather than reporting a failure.
			'selectIt' => __( 'select it above', 'mudlet' ),
			'sending'  => __( 'Sending…', 'mudlet' ),
			// Only reached when the request never landed at all; every answer
			// the endpoint gives carries its own line. See inc/download-email.php.
			'mailFail' => __( 'The mail did not go out. The download button above still works.', 'mudlet' ),
		),
		'intelUrl' => mudlet_release_builds()['macx86']['url'] ?? '',
		'crosUrl'  => 'https://wiki.mudlet.org/',
		// Absent when the site is not offering to mail anything, which is what
		// leaves the form's toggle hidden.
		'email'    => mudlet_download_email_enabled()
			? array( 'url' => rest_url( 'mudlet/v1/download-link' ) )
			: null,
	);
}
