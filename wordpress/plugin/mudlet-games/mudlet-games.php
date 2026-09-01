<?php
/**
 * Plugin Name:       Mudlet Games
 * Plugin URI:        https://github.com/Mudlet/Mudlet
 * Description:       The games Mudlet bundles, read from the client's own source — one post per game, with its logo, host, port and description.
 * Version:           0.1.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            The Mudlet team
 * License:           GPL-2.0-or-later
 * Text Domain:       mudlet-games
 *
 * ---------------------------------------------------------------------------
 *
 * Where the list comes from.
 *
 * Mudlet ships connection profiles for forty-odd MUDs, and that list is not an
 * editorial decision anybody makes on the website: it is whatever is in
 * src/TGameDetails.h in the client, with the logos in src/icons/ beside it. A
 * game is added to Mudlet, not to mudlet.org.
 *
 * So the front page's grid used to be a PHP array somebody kept in step by
 * hand, and it was two games and one rename out of date. This reads the header
 * instead. Nobody types a game.
 *
 * ---------------------------------------------------------------------------
 *
 * Why a plugin and not the theme, and why posts and not an option.
 *
 * Same reasoning as Mudlet Releases next door: what a game *is* — its host, its
 * port, whether it speaks TLS — is a fact about the game, not a decision about
 * how a page looks, so changing themes must not take it with it.
 *
 * They are posts because the front page is not the only thing that wants them.
 * A post gives every game a URL, a featured image, an excerpt, REST, search,
 * and a place for an editor to add a sentence the upstream header does not
 * carry — all for free, and all reusable by any template that cares to run a
 * WP_Query. An option holding a serialised array gives none of that.
 *
 * The generated fields are overwritten on every sync and should not be edited;
 * the post's own body is not touched once it exists, so anything written there
 * survives.
 *
 * @see includes/api.php - the only surface a theme should touch.
 *
 * @package Mudlet_Games
 */

defined( 'ABSPATH' ) || exit;

define( 'MUDLET_GAMES_VERSION', '0.1.1' );
define( 'MUDLET_GAMES_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-store.php';
require_once __DIR__ . '/includes/class-source.php';
require_once __DIR__ . '/includes/class-sync.php';
require_once __DIR__ . '/includes/class-cli.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-block.php';
require_once __DIR__ . '/includes/api.php';

add_action( 'plugins_loaded', 'mudlet_games_boot' );
/**
 * Wire the pieces up.
 */
function mudlet_games_boot(): void {
	load_plugin_textdomain( 'mudlet-games', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	Mudlet_Games_Store::init();
	Mudlet_Games_Sync::init();
	// Not behind is_admin(): the read-only guard has to hold on REST too.
	Mudlet_Games_Admin::init();
	Mudlet_Games_Block::init();
}

register_activation_hook( __FILE__, 'mudlet_games_activate' );
/**
 * Register the post type and flush, so /games/ answers immediately rather than
 * 404ing until somebody saves the permalinks screen.
 *
 * No sync is kicked off here: activation happens during a request somebody is
 * waiting on, and this one costs forty icon downloads. Cron picks it up within
 * the hour, or `wp mudlet-games sync` does it now.
 */
function mudlet_games_activate(): void {
	Mudlet_Games_Store::register();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'mudlet_games_deactivate' );
/**
 * Drop the scheduled refresh and the rewrite rules. The game posts are left
 * alone: they are content with public URLs, and deactivating a plugin is not
 * an instruction to delete forty pages.
 */
function mudlet_games_deactivate(): void {
	wp_clear_scheduled_hook( Mudlet_Games_Sync::HOOK );
	flush_rewrite_rules();
}
