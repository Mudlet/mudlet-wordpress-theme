<?php
/**
 * The public surface.
 *
 * This file is the contract. A theme should call these and nothing else - the
 * classes behind them are free to change shape, these are not.
 *
 * Every one of them is safe to call when GitHub is unreachable: they return
 * null or an empty value rather than raising, so a caller's job is to have a
 * fallback, not to catch anything.
 *
 * Guard calls with function_exists() so a theme keeps working when the plugin
 * is deactivated:
 *
 *     $release = function_exists( 'mudlet_releases_get' )
 *         ? mudlet_releases_get( 'latest' )
 *         : null;
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * A release, by tag, id, or 'latest'.
 *
 * The returned array:
 *
 *   id         int     GitHub's release id
 *   tag        string  "Mudlet-4.22.0"
 *   version    string  "4.22.0"
 *   name       string  the release's title
 *   date       string  "2026-07-06"
 *   url        string  the release page on GitHub
 *   prerelease bool
 *   counts     array   [ ["1","new feature"], ["2","improvements"], … ] - may be
 *                      empty when the changelog has no Added/Improved/Fixed
 *                      headings, which is not an error
 *   builds     array   keyed win|macarm|macx86|linux, each with file, label,
 *                      short, note, size ("128.8 MiB"), bytes, sha, url - a
 *                      platform with no matching asset is simply absent
 *   changelog  string  rendered HTML
 *   body       string  the raw Markdown
 *
 * @param string $ref Tag, release id, or 'latest'.
 * @return array<string, mixed>|null
 */
function mudlet_releases_get( string $ref = 'latest' ) {
	return Mudlet_Releases_Release::get( $ref );
}

/**
 * The release a post is about, or null if it is not a release post.
 *
 * @param WP_Post|int|null $post Post.
 * @return array<string, mixed>|null
 */
function mudlet_releases_for_post( $post = null ) {
	$tag = Mudlet_Releases_Post_Tag::get( $post );
	return '' === $tag ? null : Mudlet_Releases_Release::get( $tag );
}

/**
 * The release tag set on a post, resolving an older release id if that is all
 * the post has.
 *
 * @param WP_Post|int|null $post Post.
 * @return string Empty when the post is not a release.
 */
function mudlet_releases_post_tag( $post = null ): string {
	return Mudlet_Releases_Post_Tag::get( $post );
}

/**
 * A release's own notes, rendered from its Markdown body.
 *
 * This is what the release author wrote. For the record of what actually
 * changed, use mudlet_releases_changes().
 *
 * @param string $ref Tag, release id, or 'latest'.
 * @return string
 */
function mudlet_releases_changelog( string $ref ): string {
	$release = Mudlet_Releases_Release::get( $ref );
	return $release ? (string) $release['changelog'] : '';
}

/**
 * Everything merged between the previous release and this one.
 *
 * Built from the pull requests in the range, which is the record rather than a
 * summary - and works for releases whose notes are prose with no structure.
 *
 * The returned array:
 *
 *   previous     string  the tag compared against
 *   tag          string  the tag compared to
 *   compare_url  string  the GitHub compare page
 *   total        int     entries found
 *   groups       array   category => [ [title, pr, url], … ], categories being
 *                        added, improved, fixed, infrastructure, release, other
 *   counts       array   [ ["47","new features"], … ] for added/improved/fixed
 *
 * **This costs several API requests** - one for the releases list, then one per
 * hundred commits - so call it on a single post, not in a loop over an archive.
 * The result is cached for twelve hours. Use mudlet_releases_changes_cached()
 * where a miss is acceptable.
 *
 * @param string $ref      Tag, release id, or 'latest'.
 * @param string $previous Optional explicit previous tag.
 * @return array<string, mixed>|null
 */
function mudlet_releases_changes( string $ref, string $previous = '' ) {
	$tag = mudlet_releases_tag_for( $ref );
	return '' === $tag ? null : Mudlet_Releases_Changelog::get( $tag, $previous );
}

/**
 * The same, but only if it has already been worked out.
 *
 * @param string $ref      Tag, release id, or 'latest'.
 * @param string $previous Optional explicit previous tag.
 * @return array<string, mixed>|null
 */
function mudlet_releases_changes_cached( string $ref, string $previous = '' ) {
	$tag = mudlet_releases_tag_for( $ref );
	return '' === $tag ? null : Mudlet_Releases_Changelog::cached( $tag, $previous );
}

/**
 * Resolve any reference to a tag.
 *
 * 'latest' and release ids need a lookup; a tag is already one.
 *
 * @param string $ref Tag, release id, or 'latest'.
 * @return string
 */
function mudlet_releases_tag_for( string $ref ): string {
	$ref = trim( $ref );
	if ( '' === $ref ) {
		return '';
	}
	if ( 'latest' !== strtolower( $ref ) && ! ctype_digit( $ref ) ) {
		return $ref;
	}
	$release = Mudlet_Releases_Release::get( $ref );
	return $release ? (string) $release['tag'] : '';
}

/**
 * The people behind a release.
 *
 * Everyone whose work merged between the previous stable tag and this one, most
 * commits first. Derived from the same compare the changelog comes from, so it
 * costs nothing extra and cannot disagree with it.
 *
 * Each row:
 *
 *   login    string  GitHub login, '' when the commit address matched no account
 *   name     string  display name, falling back to the login
 *   url      string  their GitHub profile, '' without a login
 *   avatar   string  avatar URL, '' when unknown
 *   commits  int     commits in this release, co-authored ones included
 *
 * Bots are excluded - see Mudlet_Releases_Changelog::bots(). Empty for the
 * oldest release on record, which has no previous tag to compare against, and
 * for any release whose detail pass has not run yet.
 *
 * @param string $ref Tag, release id, or 'latest'.
 * @return array<int, array<string, mixed>>
 */
function mudlet_releases_contributors( string $ref = 'latest' ): array {
	$release = mudlet_releases_get( $ref );

	return $release && ! empty( $release['contributors'] ) ? (array) $release['contributors'] : array();
}

/**
 * Every release on record, newest first.
 *
 * A plain database query - no API, nothing to rate limit, nothing to miss. This
 * is what the store exists for: a download archive is a loop over this.
 *
 * Returns the same arrays mudlet_releases_get() does. Empty until a sync or an
 * import has run.
 *
 * @param int $limit How many, -1 for all.
 * @return array<int, array<string, mixed>>
 */
function mudlet_releases_all( int $limit = -1 ): array {
	$out = array();
	foreach ( Mudlet_Releases_Store::all( $limit ) as $post ) {
		$release = Mudlet_Releases_Store::to_array( $post );
		if ( $release ) {
			$out[] = $release;
		}
	}
	return $out;
}

/**
 * Forget what is cached for a reference, or for 'latest' when given none.
 *
 * Note this clears the HTTP cache, not the store - stored releases are removed
 * by deleting them, and refreshed by syncing.
 *
 * @param string $ref Tag, release id, or ''.
 */
function mudlet_releases_flush( string $ref = '' ): void {
	Mudlet_Releases_Github_Client::flush( $ref );
}

/**
 * A post's announcement, as Markdown for a GitHub release.
 *
 * What was written in the editor and nothing that gets generated: the
 * changelog, the contributors and the download table are left out, because the
 * release this is pasted onto already carries them. Images and links come out
 * absolute, since the text is read on github.com.
 *
 * Shapes Markdown cannot express - two columns, an image beside prose - are
 * flattened rather than dropped.
 *
 * @param WP_Post|int|null     $post Post. Defaults to the current one.
 * @param array<string, mixed> $args 'link' (bool, default true) appends a
 *                                   footer linking back to the post; 'title'
 *                                   (bool, default false) leads with the
 *                                   post's title as an H1.
 * @return string Markdown, '' when there is no such post.
 */
function mudlet_release_markdown( $post = null, array $args = array() ): string {
	return Mudlet_Releases_Markdown_Export::post( $post, $args );
}
