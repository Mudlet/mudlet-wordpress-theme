<?php
/**
 * The Mudlet plugins, carried inside the theme.
 *
 * ---------------------------------------------------------------------------
 *
 * Why the theme carries them.
 *
 * The games, the makers, the releases and the screenshots people send in are
 * plugins for a reason that has not changed: what a game *is*, who wrote the
 * client, what a release contains, what a stranger uploaded on Tuesday — none
 * of that is a decision about how a page looks, and none of it should leave
 * with a theme. See each plugin's own header.
 *
 * But "not the theme's data" and "not in the theme's zip" are two different
 * claims, and only the first one was ever true. Downloading four archives and
 * uploading four archives to stand a site up — and doing it again on every
 * update — is four chances to end up running one new thing and three old ones.
 * So the theme's zip carries the plugins under `plugins/`, and this file
 * requires them. One download, one update, nothing to install.
 *
 * The data still outlives the theme: a `mudlet_game` post is a post, and it is
 * in the database whether or not anything is registering the post type this
 * week. Switching themes stops the site *drawing* the games; it does not lose
 * them, and dropping the games plugin zip into wp-content/plugins brings every
 * one of those URLs back. That is the escape hatch, and it is why the plugin
 * zips are still built and still published on every release.
 *
 * ---------------------------------------------------------------------------
 *
 * An installed copy always wins.
 *
 * WordPress loads plugins long before it reaches a theme's functions.php, so a
 * separately installed Mudlet Games has already defined MUDLET_GAMES_VERSION
 * by the time this runs — and this stands down. That is the whole arbitration:
 * one `defined()` per plugin, the same shape the shared sync file uses for its
 * own `class_exists()` race. It also means an installed copy that is *older*
 * than the one in the theme quietly wins, which is worth an admin notice
 * rather than a surprise, so there is one at the bottom of this file.
 *
 * ---------------------------------------------------------------------------
 *
 * What a bundled plugin does not get.
 *
 * `register_activation_hook()` never fires, because nothing activates. They
 * use it for one thing — register the post type, flush the rewrite rules —
 * and the theme's equivalent moment is `after_switch_theme`, which is when
 * those URLs start existing. (The screenshots plugin flushes nothing, having
 * no public URLs; what its activation hook lays down is the queue directory,
 * and `queue_dir()` makes that on the way past anyway.) `switch_theme` is the other end: the cron events
 * the plugins scheduled would otherwise come due forever with no callback
 * behind them, which WP-Cron pays for on every pass.
 *
 * The rest of the seams — when to boot, where the assets are, where the
 * translations are — are in `shared/mudlet-bundle.php` inside each plugin,
 * because they are the plugin's problem rather than the theme's.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * The plugins the theme ships, in load order.
 *
 * `const` is the guard: already defined means a real plugin is installed and
 * active, and this file leaves it alone. `store` is the class whose
 * `register()` has to run before a rewrite flush means anything, and `hooks`
 * are the cron events to cancel when the theme goes away.
 */
function mudlet_bundled_plugins(): array {
	return array(
		'mudlet-games'    => array(
			'const' => 'MUDLET_GAMES_VERSION',
			'name'  => 'Mudlet Games',
			'store' => 'Mudlet_Games_Store',
			'hooks' => array( 'mudlet_games_sync' ),
		),
		'mudlet-makers'   => array(
			'const' => 'MUDLET_MAKERS_VERSION',
			'name'  => 'Mudlet Makers',
			'store' => 'Mudlet_Makers_Store',
			'hooks' => array( 'mudlet_makers_sync' ),
		),
		'mudlet-releases' => array(
			'const' => 'MUDLET_RELEASES_VERSION',
			'name'  => 'Mudlet Releases',
			'store' => 'Mudlet_Releases_Store',
			'hooks' => array( 'mudlet_releases_refresh', 'mudlet_releases_sync_index', 'mudlet_releases_sync_detail' ),
		),
		'mudlet-shots'    => array(
			'const' => 'MUDLET_SHOTS_VERSION',
			'name'  => 'Mudlet Screenshots',
			'store' => 'Mudlet_Shots_Store',
			'hooks' => array( 'mudlet_shots_sweep' ),
		),
	);
}

/**
 * Load whichever of them this site is not already running, and say which.
 *
 * Empty on a theme built without them — `build-dist.mjs --no-plugins`, or a
 * checkout where `plugins/` is the empty bind-mount target — and empty on a
 * site that has all of them installed the old way. Keyed by slug, so a caller
 * can ask about one.
 */
function mudlet_bundled_loaded(): array {
	static $loaded = null;

	if ( null === $loaded ) {
		$loaded = array();

		foreach ( mudlet_bundled_plugins() as $slug => $plugin ) {
			if ( defined( $plugin['const'] ) ) {
				continue;
			}

			$file = get_theme_file_path( "plugins/{$slug}/{$slug}.php" );
			if ( ! is_readable( $file ) ) {
				continue;
			}

			require_once $file;
			$loaded[ $slug ] = $plugin;
		}
	}

	return $loaded;
}

mudlet_bundled_loaded();

add_action( 'after_switch_theme', 'mudlet_bundled_flush' );
/**
 * Make the post types' URLs answer straight away.
 *
 * Standing in for the activation hooks nothing fired: register the post types
 * this theme has just brought with it, then flush, so /games/ and
 * /the-makers/<name>/ work on the first click rather than 404ing until
 * somebody saves the permalinks screen.
 *
 * No sync is kicked off. Switching a theme happens in a request somebody is
 * waiting on, and a first sync is eighty-odd image downloads. Cron picks it up
 * ten minutes later; Mudlet → Sync, or `wp mudlet-games sync`, does it now.
 */
function mudlet_bundled_flush(): void {
	foreach ( mudlet_bundled_loaded() as $plugin ) {
		if ( method_exists( $plugin['store'], 'register' ) ) {
			call_user_func( array( $plugin['store'], 'register' ) );
		}
	}

	flush_rewrite_rules();
}

add_action( 'switch_theme', 'mudlet_bundled_unschedule' );
/**
 * Cancel the bundled plugins' cron events on the way out.
 *
 * What each plugin's deactivation hook does, for the same reason: an event
 * whose callback has gone still comes due, and WP-Cron rewrites the whole
 * array on every pass to work that out. The posts are left alone — they are
 * content with public URLs, and changing a theme is not an instruction to
 * delete seventy pages.
 */
function mudlet_bundled_unschedule(): void {
	foreach ( mudlet_bundled_loaded() as $plugin ) {
		foreach ( $plugin['hooks'] as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
}

add_action( 'admin_notices', 'mudlet_bundled_stale_notice' );
/**
 * Say so when an installed plugin is older than the copy in the theme.
 *
 * The `defined()` arbitration is deliberately unconditional — an installed
 * plugin is something an admin chose, and a theme overruling it silently is
 * worse than either version of the bug. But an admin who installed these
 * separately a year ago and has been updating only the theme since is running
 * old code with no way to see it, so this is the way to see it.
 *
 * Read out of the bundled copy's header rather than from a constant: that file
 * is not loaded, and loading it to find out its version would be the collision
 * this is warning about.
 */
function mudlet_bundled_stale_notice(): void {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$stale = array();

	foreach ( mudlet_bundled_plugins() as $slug => $plugin ) {
		if ( ! defined( $plugin['const'] ) ) {
			continue;
		}

		$file = get_theme_file_path( "plugins/{$slug}/{$slug}.php" );
		if ( ! is_readable( $file ) ) {
			continue;
		}

		$bundled = get_file_data( $file, array( 'version' => 'Version' ) )['version'];
		if ( $bundled && version_compare( $bundled, (string) constant( $plugin['const'] ), '>' ) ) {
			$stale[] = sprintf(
				/* translators: 1: plugin name, 2: the installed version, 3: the version this theme carries */
				__( '%1$s %2$s is installed, but this theme carries %3$s.', 'mudlet' ),
				$plugin['name'],
				constant( $plugin['const'] ),
				$bundled
			);
		}
	}

	if ( ! $stale ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p><p>%s</p></div>',
		esc_html( implode( ' ', $stale ) ),
		esc_html__( 'An installed plugin always wins over the theme\'s copy. Update it, or deactivate and delete it to use the one the theme ships.', 'mudlet' )
	);
}
