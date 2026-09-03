<?php
/**
 * Plugin Name:       Mudlet Screenshots
 * Plugin URI:        https://github.com/Mudlet/mudlet-web-page
 * Description:       Lets anybody send the site a screenshot, holds it out of reach until somebody has looked at it, and puts the ones that pass into the gallery on /media/.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            The Mudlet team
 * License:           GPL-2.0-or-later
 * Text Domain:       mudlet-shots
 *
 * ---------------------------------------------------------------------------
 *
 * What this is for.
 *
 * The fifteen screenshots on /media/ are the community's, and every one of them
 * got there by somebody emailing a file to somebody who had a WordPress login.
 * That is a queue with one person in it. This is the same queue with a form on
 * the front of it: a visitor picks a file, the site keeps it somewhere nobody
 * can see, and an editor either puts it in the gallery or throws it away.
 *
 * ---------------------------------------------------------------------------
 *
 * The one thing to understand before editing anything here.
 *
 * **A pending submission is not an attachment.** It is a file in
 * wp-content/uploads/mudlet-shots/<token>/, and the only way to look at it is
 * through a capability-checked route that reads it off disk.
 *
 * This is the whole security position, and it is not a detail. An attachment
 * has a public URL from the instant it exists — before anybody has reviewed it,
 * and whatever its post status says, because the *file* is served by the web
 * server and knows nothing about WordPress. So a submission form that made
 * attachments would not be a moderation queue at all: it would be an open image
 * host on mudlet.org, and the review would only decide whether the picture was
 * *also* on a page. Whatever is uploaded here becomes an attachment at exactly
 * one moment, which is when somebody with edit_pages says so.
 *
 * Three things stand between the queue and the web, in decreasing order of how
 * much they are relied on: it is not in the media library, so nothing on the
 * site queries it and no picker offers it; the directory has an index.html and
 * a deny-all .htaccess, which Apache honours and nginx does not; and the path
 * carries 32 random characters, which is what actually holds when the server is
 * nginx. Belt, braces, and a second pair of braces, because the failure here is
 * somebody else's picture on our domain.
 *
 * ---------------------------------------------------------------------------
 *
 * The second thing: nothing is stored as it arrived.
 *
 * Every accepted file is re-encoded — decoded by GD or Imagick and written back
 * out as WebP, scaled to fit 2560px. The uploaded bytes are unlinked and never
 * reach the media library.
 *
 * That is the "save space" half of the request (a 4MB PNG of a terminal lands
 * around 300KB), but it is also most of the safety. A file that survives being
 * decoded and re-encoded by an image library is an image and nothing else, so
 * the whole class of PHP-in-a-PNG and polyglot uploads is gone rather than
 * guarded against. EXIF goes with it, which matters more than it sounds: a
 * screenshot has none, but a phone photo of somebody's screen carries the place
 * it was taken.
 *
 * ---------------------------------------------------------------------------
 *
 * Why a plugin, and why it is bundled anyway.
 *
 * Same argument as the games, the makers and the releases: these are other
 * people's contributions, and a theme rewrite must not take them with it. It
 * ships inside the theme's zip under plugins/ like the other three, so it is
 * still nothing to install — see the theme's inc/bundled-plugins.php.
 *
 * @see includes/api.php - the only surface a theme should touch.
 *
 * @package Mudlet_Shots
 */

defined( 'ABSPATH' ) || exit;

define( 'MUDLET_SHOTS_VERSION', '0.1.0' );
define( 'MUDLET_SHOTS_FILE', __FILE__ );

// The Mudlet menu and the sync schedules, and the seams that let this run from
// the theme's zip as well as from wp-content/plugins. One source file each,
// wordpress/plugin/shared/ - see their headers before editing.
require_once __DIR__ . '/shared/mudlet-bundle.php';
require_once __DIR__ . '/shared/mudlet-sync.php';
require_once __DIR__ . '/includes/class-store.php';
require_once __DIR__ . '/includes/class-image.php';
require_once __DIR__ . '/includes/class-intake.php';
require_once __DIR__ . '/includes/class-publish.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/api.php';

// Not add_action( 'plugins_loaded', … ) directly: that hook has already fired
// when this is loaded from the theme rather than from wp-content/plugins.
Mudlet_Bundle::boot( 'mudlet_shots_boot' );
/**
 * Wire the pieces up.
 */
function mudlet_shots_boot(): void {
	Mudlet_Bundle::textdomain( 'mudlet-shots', __FILE__ );

	Mudlet_Sync::boot();

	Mudlet_Shots_Store::init();
	Mudlet_Shots_Intake::init();
	// Not behind is_admin() either: its one filter decides what the *front end*
	// renders for a picture that moves.
	Mudlet_Shots_Publish::init();
	// Not behind is_admin(): the decision routes are admin-post, and the
	// preview route has to answer wherever it is asked from.
	Mudlet_Shots_Admin::init();
}

register_activation_hook( __FILE__, 'mudlet_shots_activate' );
/**
 * Register the post type and lay the queue directory down.
 *
 * No rewrite flush: the post type is not public and has no URLs. What this
 * does need is the directory, with its two guard files in place *before* the
 * first submission rather than on the way past it.
 */
function mudlet_shots_activate(): void {
	Mudlet_Shots_Store::register();
	Mudlet_Shots_Store::queue_dir();
}

register_deactivation_hook( __FILE__, 'mudlet_shots_deactivate' );
/**
 * Drop the sweeper. The queue is left exactly where it is: a pending
 * submission is somebody's picture, and deactivating a plugin is not an
 * instruction to throw it away.
 */
function mudlet_shots_deactivate(): void {
	wp_clear_scheduled_hook( Mudlet_Shots_Store::SWEEP );
}
