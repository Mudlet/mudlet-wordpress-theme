<?php
/**
 * Plugin Name:       Mudlet Makers
 * Plugin URI:        https://github.com/Mudlet/Mudlet
 * Description:       The people who make Mudlet, read from the client's own About dialog — one post per maker, with their avatar, handles and what they did.
 * Version:           0.1.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            The Mudlet team
 * License:           GPL-2.0-or-later
 * Text Domain:       mudlet-makers
 *
 * ---------------------------------------------------------------------------
 *
 * Where the list comes from.
 *
 * Mudlet credits thirty people in Help -> About -> About Mudlet, and that list
 * is not an editorial decision anybody makes on the website: it is the
 * `aboutMakers` vector in src/dlgAboutDialog.cpp, maintained by the people it
 * names. Somebody joins the project and is added to Mudlet, not to mudlet.org.
 *
 * The live /the-makers/ page is a hand-typed copy of that list from around
 * 2010. It credits twelve people, several of whom have long since moved on,
 * and omits most of the current team — including two of the three people who
 * have written the most of the client. That is what happens to a list somebody
 * has to remember to update. This reads the dialog instead. Nobody types a
 * person.
 *
 * ---------------------------------------------------------------------------
 *
 * Why a plugin and not the theme, and why posts and not an option.
 *
 * Same reasoning as Mudlet Games next door: who made Mudlet is a fact about the
 * project, not a decision about how a page looks, so changing themes must not
 * take it with it.
 *
 * They are posts because a post gives every maker a URL, a featured image for
 * the avatar, REST, search and a place any template can query — all for free.
 * An option holding a serialised array gives none of that.
 *
 * ---------------------------------------------------------------------------
 *
 * What is deliberately not copied.
 *
 * The dialog lists an email address for two thirds of these people. A dialog is
 * not a crawled web page: publishing those addresses would turn a credits page
 * into a spam list, and nobody agreed to that by contributing to a MUD client.
 * The parser drops them on the floor and never stores them.
 *
 * @see includes/api.php - the only surface a theme should touch.
 *
 * @package Mudlet_Makers
 */

defined( 'ABSPATH' ) || exit;

define( 'MUDLET_MAKERS_VERSION', '0.1.1' );
define( 'MUDLET_MAKERS_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-store.php';
require_once __DIR__ . '/includes/class-source.php';
require_once __DIR__ . '/includes/class-sync.php';
require_once __DIR__ . '/includes/class-cli.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/api.php';

add_action( 'plugins_loaded', 'mudlet_makers_boot' );
/**
 * Wire the pieces up.
 */
function mudlet_makers_boot(): void {
	load_plugin_textdomain( 'mudlet-makers', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	Mudlet_Makers_Store::init();
	Mudlet_Makers_Sync::init();
	// Not behind is_admin(): the read-only guard has to hold on REST too.
	Mudlet_Makers_Admin::init();
}

register_activation_hook( __FILE__, 'mudlet_makers_activate' );
/**
 * Register the post type and flush, so /the-makers/<name>/ answers immediately
 * rather than 404ing until somebody saves the permalinks screen.
 *
 * No sync is kicked off here: activation happens during a request somebody is
 * waiting on, and this one costs eighteen avatar downloads. Cron picks it up
 * within the hour, or `wp mudlet-makers sync` does it now.
 */
function mudlet_makers_activate(): void {
	Mudlet_Makers_Store::register();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'mudlet_makers_deactivate' );
/**
 * Drop the scheduled refresh and the rewrite rules. The maker posts are left
 * alone: they are content with public URLs, and deactivating a plugin is not an
 * instruction to delete thirty pages.
 */
function mudlet_makers_deactivate(): void {
	wp_clear_scheduled_hook( Mudlet_Makers_Sync::HOOK );
	flush_rewrite_rules();
}
