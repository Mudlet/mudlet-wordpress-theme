<?php
/**
 * Release data, from the Mudlet Releases plugin.
 *
 * This used to fetch and parse GitHub itself. It does not any more, and the
 * reason is worth keeping: a release's version, assets, sizes and checksums are
 * facts about a release, not decisions about how a page looks. If they lived
 * here, changing the theme would take the download table's data with it - the
 * same way it would take every release post's body.
 *
 * So the plugin owns that, and this is the seam. Everything goes through
 * function_exists(), because a theme that hard-requires a plugin is a theme
 * that white-screens when somebody deactivates one: with the plugin gone the
 * download page falls back to the figures in inc/downloads.php and the release
 * panel to the "Release details" box in the editor.
 *
 * @see wordpress/plugin/mudlet-releases/includes/api.php
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether release data is available at all.
 *
 * @return bool
 */
function mudlet_has_release_data(): bool {
	return function_exists( 'mudlet_releases_get' );
}

/**
 * A release, by tag, id, or 'latest'.
 *
 * @param string $ref Tag, release id, or 'latest'.
 * @return array<string, mixed>|null
 */
function mudlet_github_release( string $ref = 'latest' ) {
	return mudlet_has_release_data() ? mudlet_releases_get( $ref ) : null;
}

/**
 * The release a post is about.
 *
 * @param WP_Post|int|null $post Post.
 * @return array<string, mixed>|null
 */
function mudlet_release_for_post( $post = null ) {
	return function_exists( 'mudlet_releases_for_post' ) ? mudlet_releases_for_post( $post ) : null;
}

/**
 * The people behind the release a post is about.
 *
 * Free: the plugin worked this out from the compare it already fetched for the
 * changelog and stored it on the record, so this is a meta read, not a request.
 * Empty when the plugin is inactive, when the post is not about a release, or
 * when its detail pass has not run yet — all three mean "draw nothing", which
 * is what the template does.
 *
 * @param WP_Post|int|null $post Post.
 * @return array<int, array<string, mixed>>
 */
function mudlet_post_contributors( $post = null ): array {
	if ( ! function_exists( 'mudlet_releases_contributors' ) ) {
		return array();
	}

	$tag = function_exists( 'mudlet_releases_post_tag' ) ? mudlet_releases_post_tag( $post ) : '';

	return '' === $tag ? array() : mudlet_releases_contributors( $tag );
}

/**
 * Initials for somebody with no avatar.
 *
 * Two letters from a display name, one from a bare login. Enough to fill the
 * circle so the chip keeps its shape.
 *
 * @param string $name Display name.
 * @return string
 */
function mudlet_initials( string $name ): string {
	$words = preg_split( '/[\s._-]+/u', trim( $name ), -1, PREG_SPLIT_NO_EMPTY );
	if ( ! $words ) {
		return '?';
	}

	$first = function_exists( 'mb_substr' ) ? mb_substr( $words[0], 0, 1 ) : substr( $words[0], 0, 1 );
	if ( count( $words ) < 2 ) {
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first ) : strtoupper( $first );
	}

	$last = end( $words );
	$last = function_exists( 'mb_substr' ) ? mb_substr( $last, 0, 1 ) : substr( $last, 0, 1 );

	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first . $last ) : strtoupper( $first . $last );
}

/**
 * Where "older versions" points.
 *
 * Every build mudlet.org has ever shipped is on mudlet.org: CI scps each asset
 * to wp-content/files/ as the platform finishes building, before the GitHub
 * release even exists, and nothing is ever removed. What serves that directory
 * is Apache's own index - not WordPress - so this is a plain URL rather than
 * anything routed, and the C/O parameters are mod_autoindex's own sort: newest
 * first.
 *
 * GitHub's release list is the obvious alternative and carries more per release
 * (changelogs, checksums). It is not the default for the same reason the
 * download rows are not: a download page that sends people off-site is a
 * download page that fails wherever GitHub is throttled. The filter is there
 * for whoever decides that trade differently.
 *
 * @return string
 */
function mudlet_download_archive_url(): string {
	/**
	 * Filter the archive URL behind "Browse the archive".
	 *
	 * @param string $url The archive URL.
	 */
	return (string) apply_filters( 'mudlet_download_archive_url', content_url( 'files/?C=M;O=D' ) );
}

add_action( 'admin_notices', 'mudlet_releases_plugin_notice' );
/**
 * Say so, once, if the plugin is missing.
 *
 * The site still works without it, but silently showing a hardcoded version on
 * the download page is exactly the kind of staleness nobody notices for months.
 */
function mudlet_releases_plugin_notice(): void {
	if ( mudlet_has_release_data() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'themes' ), true ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	echo esc_html__(
		'The Mudlet Releases plugin is not active. Release posts and the download page are falling back to the versions built into the theme.',
		'mudlet'
	);
	echo '</p></div>';
}
