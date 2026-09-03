<?php
/**
 * The three seams a plugin needs when it is not installed as a plugin.
 *
 * The theme ships the games, makers, releases and screenshots plugins in its zip,
 * under `plugins/`, and `require`s them from functions.php. That is the normal
 * way this code runs on mudlet.org: one download, one update, nothing to
 * install. The plugin zips still exist for anyone who would rather have them in
 * wp-content/plugins — and when one is there, it loads first and the theme
 * stands down (see the theme's inc/bundled-plugins.php).
 *
 * So every file under a plugin directory here has to work from either place.
 * Three things break when the directory moves, and this file is all three:
 *
 * - **When to boot.** `plugins_loaded` has already fired by the time a theme's
 *   functions.php runs — WordPress loads plugins, fires it, and only then
 *   reaches the theme. An `add_action( 'plugins_loaded', … )` from a bundled
 *   copy would simply never run. `boot()` picks the next equivalent moment.
 *
 * - **Where the assets are.** `plugins_url()` maps a path against
 *   WP_PLUGIN_DIR and quietly returns nonsense for anything outside it, so a
 *   bundled block script would 404. `url()` measures from wp-content instead,
 *   which both directories are under.
 *
 * - **Where the translations are.** Same problem, same shape, in
 *   `load_plugin_textdomain()`.
 *
 * There is deliberately no fourth seam for `register_activation_hook()`. A
 * theme has no activation hook to borrow, and what those hooks do here is flush
 * the rewrite rules after registering a post type — which the theme does on
 * `after_switch_theme` for the copies it carries, because that is the moment
 * the URLs start existing.
 *
 * Carried in every plugin's zip for the reason mudlet-sync.php next door gives at
 * length. **Edit it in wordpress/plugin/shared/, never in a plugin.**
 *
 * @package Mudlet_Bundle
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Mudlet_Bundle' ) ) {
	return;
}

/**
 * Static helpers for code that may be running from a theme.
 */
class Mudlet_Bundle {

	/**
	 * Run a plugin's boot callback at the first sensible moment.
	 *
	 * Installed as a plugin that is `plugins_loaded`, exactly as before.
	 * Bundled in a theme it is `after_setup_theme`, which fires immediately
	 * after functions.php has been read — the earliest hook still ahead of
	 * `init`, which is where every store, sync and admin screen actually
	 * registers itself.
	 *
	 * @param callable $callback The plugin's boot function.
	 */
	public static function boot( callable $callback ): void {
		add_action( did_action( 'plugins_loaded' ) ? 'after_setup_theme' : 'plugins_loaded', $callback );
	}

	/**
	 * Whether a file is running from wp-content/plugins (or mu-plugins).
	 *
	 * @param string $file A plugin file, normally the main file's __FILE__.
	 */
	public static function is_plugin( string $file ): bool {
		$dir = wp_normalize_path( dirname( $file ) );

		foreach ( array( WP_PLUGIN_DIR, WPMU_PLUGIN_DIR ) as $root ) {
			if ( str_starts_with( $dir . '/', wp_normalize_path( $root ) . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The URL of a file beside a plugin's main file, from wherever it is.
	 *
	 * `plugins_url()` when the plugin is one; otherwise measured off
	 * WP_CONTENT_DIR, which covers a theme, a child theme, and anywhere else
	 * somebody has put wp-content. If it is outside even that — an oddly
	 * configured install — fall back to `plugins_url()` and be wrong the way
	 * we would have been anyway.
	 *
	 * @param string $file The plugin's main file (its MUDLET_*_FILE constant).
	 * @param string $path A path relative to that file's directory.
	 */
	public static function url( string $file, string $path = '' ): string {
		$dir     = wp_normalize_path( dirname( $file ) );
		$content = wp_normalize_path( WP_CONTENT_DIR );

		if ( ! self::is_plugin( $file ) && str_starts_with( $dir . '/', $content . '/' ) ) {
			$rel = substr( $dir, strlen( $content ) );
			return content_url( $rel . ( '' === $path ? '' : '/' . ltrim( $path, '/' ) ) );
		}

		return plugins_url( $path, $file );
	}

	/**
	 * Load a plugin's translations from wherever the plugin is.
	 *
	 * The same two places `load_plugin_textdomain()` looks — a site-wide
	 * override in WP_LANG_DIR/plugins first, then the copy shipped beside the
	 * code — because a bundled plugin is still that plugin as far as anyone
	 * translating it is concerned.
	 *
	 * @param string $domain The text domain.
	 * @param string $file   The plugin's main file.
	 */
	public static function textdomain( string $domain, string $file ): void {
		if ( self::is_plugin( $file ) ) {
			load_plugin_textdomain( $domain, false, dirname( plugin_basename( $file ) ) . '/languages' );
			return;
		}

		/** This filter is documented in wp-includes/l10n.php */
		$locale = apply_filters( 'plugin_locale', determine_locale(), $domain );

		load_textdomain( $domain, WP_LANG_DIR . "/plugins/{$domain}-{$locale}.mo", $locale )
			|| load_textdomain( $domain, dirname( $file ) . "/languages/{$domain}-{$locale}.mo", $locale );
	}
}
