<?php
/**
 * Plugin Name:       Mudlet Releases
 * Plugin URI:        https://github.com/Mudlet/mudlet-release-plugin
 * Description:       Turns a release tag into everything a release post needs — changelog, counts, and the download table's sizes, URLs and checksums — read from the GitHub release.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            The Mudlet team
 * License:           GPL-2.0-or-later
 * Text Domain:       mudlet-releases
 *
 * ---------------------------------------------------------------------------
 *
 * Why this is a plugin and not part of the theme.
 *
 * A release post's body is not written by anyone: it is the GitHub release's
 * changelog. If the code that produces it lived in the theme, changing themes
 * would blank every release post on the site. The same goes for the download
 * table's sizes and checksums - those are facts about a release, not a
 * decision about how a page looks.
 *
 * So this owns the *data*: what a Mudlet release is. A theme asks it questions
 * and decides what to draw. Everything a theme needs is in includes/api.php,
 * which is the only surface it should touch.
 *
 * ---------------------------------------------------------------------------
 *
 * The goal: adding a release post means adding a tag.
 *
 * Set the release tag on a post - "Mudlet-4.22.0", or just "4.22.0" - and
 * everything else follows from one API call:
 *
 *   version    from the tag
 *   date       from the release's published_at
 *   changelog  rendered from its Markdown body
 *   counts     the "1 new feature, 2 improvements, 9 fixes" panel, by counting
 *              entries under the body's Added / Improved / Fixed headings
 *   downloads  one row per platform, with the exact size and download URL from
 *              the release assets and the hash from its SHA256SUMS.txt
 *
 * Nobody types a number that can drift from what actually shipped.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

define( 'MUDLET_RELEASES_VERSION', '0.1.0' );
define( 'MUDLET_RELEASES_FILE', __FILE__ );

// The Mudlet menu and the sync schedules, and the seams that let this run
// from the theme's zip as well as from wp-content/plugins. One source file
// each, wordpress/plugin/shared/ - see their headers before editing.
require_once __DIR__ . '/shared/mudlet-bundle.php';
require_once __DIR__ . '/shared/mudlet-sync.php';
require_once __DIR__ . '/includes/class-github-client.php';
require_once __DIR__ . '/includes/class-release.php';
require_once __DIR__ . '/includes/class-changelog.php';
require_once __DIR__ . '/includes/class-store.php';
require_once __DIR__ . '/includes/class-sync.php';
require_once __DIR__ . '/includes/class-markdown.php';
require_once __DIR__ . '/includes/class-markdown-export.php';
require_once __DIR__ . '/includes/class-post-tag.php';
require_once __DIR__ . '/includes/class-content.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-post-export.php';
require_once __DIR__ . '/includes/api.php';

// Not add_action( 'plugins_loaded', … ) directly: that hook has already fired
// when this is loaded from the theme rather than from wp-content/plugins.
Mudlet_Bundle::boot( 'mudlet_releases_boot' );
/**
 * Wire the pieces up.
 */
function mudlet_releases_boot(): void {
	Mudlet_Bundle::textdomain( 'mudlet-releases', __FILE__ );

	Mudlet_Sync::boot();

	Mudlet_Releases_Store::init();
	Mudlet_Releases_Sync::init();
	Mudlet_Releases_Post_Tag::init();
	Mudlet_Releases_Content::init();
	// Not behind is_admin(): the read-only guard has to hold on REST too.
	Mudlet_Releases_Admin::init();
	Mudlet_Releases_Post_Export::init();
}

register_deactivation_hook( __FILE__, 'mudlet_releases_deactivate' );
/**
 * Drop the scheduled refresh. Cached releases are left alone - they are
 * transients and will expire on their own, and keeping them means reactivating
 * does not cost a round of API calls.
 */
function mudlet_releases_deactivate(): void {
	// All three, not just the cache warm: an event whose callback is gone still
	// comes due, and WP-Cron rewrites the array on every pass to find that out.
	wp_clear_scheduled_hook( 'mudlet_releases_refresh' );
	wp_clear_scheduled_hook( Mudlet_Releases_Sync::INDEX );
	wp_clear_scheduled_hook( Mudlet_Releases_Sync::DETAIL );
}
