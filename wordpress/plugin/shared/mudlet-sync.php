<?php
/**
 * The one menu, and the one place the schedules are set.
 *
 * Three plugins read three things out of Mudlet and GitHub, on three cron
 * jobs. Left alone that is three top-level menus and three places to discover
 * what runs when — so this puts them under a single **Mudlet** menu whose own
 * page lists every job with its cadence, when it last ran, when it runs next,
 * and a button to run it now.
 *
 * ---------------------------------------------------------------------------
 *
 * Why this file is in three zips.
 *
 * It is one source file — `wordpress/plugin/shared/mudlet-sync.php` — copied
 * into each plugin's archive by tools/build-dist.mjs, and bind-mounted into
 * each plugin directory by docker compose. **Edit it there, never in a
 * plugin.** `mudlet-bundle.php` beside it is the second such file, carried the
 * same way for the same reason: it holds the seams a plugin needs when the
 * theme is what loaded it.
 *
 * It is bundled rather than shared because the alternative is worse. A plugin
 * that reaches into a sibling breaks when the sibling is deactivated, and a
 * fourth plugin holding a menu is a fourth thing to install and activate
 * before any of the other three has a screen. So each carries a copy, the
 * first one loaded wins the `class_exists()` race, and the other two use it —
 * the same shape Action Scheduler has for the same reason. Nothing here holds
 * data: it is a menu, an option, and a wrapper over wp_schedule_event().
 *
 * A plugin joins in by filtering `mudlet_sync_jobs` with one entry per cron
 * hook and calling `Mudlet_Sync::reschedule()` where it used to call
 * `wp_schedule_event()`. It does not have to be here for the plugin to work;
 * it has to be here for the plugin to be *configurable*.
 *
 * The strings on this page are in the `default` text domain on purpose. Three
 * plugins with three domains ship the same file, and whichever loads first
 * would decide which .po the others' strings were looked up in — which is a
 * worse answer than leaning on the domain WordPress itself translates.
 *
 * @package Mudlet_Sync
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Mudlet_Sync' ) ) {
	return;
}

/**
 * The Mudlet menu, the sync settings screen, and the scheduling behind them.
 */
class Mudlet_Sync {

	/** The top-level menu every Mudlet screen hangs off. */
	const MENU = 'mudlet';

	/** Where the cadences are stored: hook => recurrence, or 'off'. */
	const OPTION = 'mudlet_sync_schedules';

	/** admin-post action behind the Save button. */
	const SAVE = 'mudlet_sync_save';

	/**
	 * How long after a schedule is set the first run happens.
	 *
	 * Short, deliberately. Weekly is the right cadence for a list that moves a
	 * few times a year, but a site that has just installed the plugin has
	 * nothing at all, and "nothing for a week" is not a defensible first
	 * impression. So the first run is soon and the cadence starts after it.
	 */
	const FIRST_RUN = 10 * MINUTE_IN_SECONDS;

	/**
	 * Hook up. Idempotent — three plugins call it, one of them is first.
	 */
	public static function boot(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'admin_post_' . self::SAVE, array( __CLASS__, 'save' ) );
	}

	// ── scheduling ────────────────────────────────────────────────────

	/**
	 * The cadences a job can be set to.
	 *
	 * Core's four, plus off. Anything else a site has registered through
	 * `cron_schedules` is honoured if it is already stored, but not offered:
	 * a dropdown of every interval some other plugin invented is not a choice,
	 * it is a quiz.
	 *
	 * @return array<string, string> Recurrence => label.
	 */
	public static function choices(): array {
		$all = wp_get_schedules();

		$out = array( 'off' => __( 'Never', 'default' ) );
		foreach ( array( 'hourly', 'twicedaily', 'daily', 'weekly' ) as $every ) {
			if ( isset( $all[ $every ] ) ) {
				$out[ $every ] = (string) $all[ $every ]['display'];
			}
		}

		return $out;
	}

	/**
	 * What a job is set to.
	 *
	 * @param string $hook    Cron hook.
	 * @param string $default What the plugin ships with.
	 * @return string A recurrence, or 'off'.
	 */
	public static function recurrence( string $hook, string $default ): string {
		$stored = (array) get_option( self::OPTION, array() );
		$want   = (string) ( $stored[ $hook ] ?? $default );

		// A recurrence that no longer exists — a plugin gone, or a typo in the
		// option — must not silently mean "never".
		if ( 'off' !== $want && ! isset( wp_get_schedules()[ $want ] ) ) {
			return $default;
		}

		return $want;
	}

	/**
	 * Keep a job scheduled the way the option says.
	 *
	 * Replaces the `if ( ! wp_next_scheduled() ) wp_schedule_event()` each
	 * plugin used to do: that keeps a schedule alive but can never change one,
	 * so a cadence edited on the screen would not take until somebody
	 * deactivated the plugin.
	 *
	 * @param string $hook    Cron hook.
	 * @param string $default What the plugin ships with.
	 */
	public static function reschedule( string $hook, string $default ): void {
		$want = self::recurrence( $hook, $default );
		$next = wp_next_scheduled( $hook );

		if ( 'off' === $want ) {
			if ( $next ) {
				wp_clear_scheduled_hook( $hook );
			}
			return;
		}

		if ( $next && wp_get_schedule( $hook ) === $want ) {
			return;
		}

		if ( $next ) {
			wp_clear_scheduled_hook( $hook );
		}

		wp_schedule_event( time() + self::FIRST_RUN, $want, $hook );
	}

	/**
	 * Every job that has registered itself.
	 *
	 * @return array<string, array<string, mixed>> Hook => job.
	 */
	public static function jobs(): array {
		/**
		 * Filter the syncs listed on the Mudlet screen.
		 *
		 * One entry per cron hook:
		 *
		 *   label    string  what it syncs, e.g. "Games"
		 *   note     string  a sentence under it: where from, what it costs
		 *   default  string  the recurrence the plugin ships with
		 *   synced   int     unix time of the last successful run, 0 for never
		 *   summary  string  what is on record now, e.g. "43 games"
		 *   sync_url string  a nonce-protected URL that runs it now
		 *
		 * @param array<string, array<string, mixed>> $jobs Hook => job.
		 */
		return (array) apply_filters( 'mudlet_sync_jobs', array() );
	}

	// ── the menu ──────────────────────────────────────────────────────

	/**
	 * Register the parent menu, once.
	 *
	 * At priority 9 because `_add_post_type_submenus()` runs at 10, and a post
	 * type whose `show_in_menu` names a parent that does not exist yet is a
	 * post type with no screen in the menu at all.
	 */
	public static function menu(): void {
		if ( isset( $GLOBALS['admin_page_hooks'][ self::MENU ] ) ) {
			return;
		}

		add_menu_page(
			__( 'Mudlet', 'default' ),
			__( 'Mudlet', 'default' ),
			'edit_posts',
			self::MENU,
			array( __CLASS__, 'screen' ),
			'dashicons-cloud',
			26
		);

		// Same slug as the parent, so this renames the duplicate entry
		// WordPress adds under it rather than adding a second one.
		add_submenu_page(
			self::MENU,
			__( 'Mudlet', 'default' ),
			__( 'Sync', 'default' ),
			'edit_posts',
			self::MENU,
			array( __CLASS__, 'screen' )
		);
	}

	/**
	 * The screen.
	 */
	public static function screen(): void {
		$jobs = self::jobs();
		?>
		<div class="wrap mudlet-sync">
			<h1><?php esc_html_e( 'Mudlet', 'default' ); ?></h1>

			<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'default' ); ?></p>
				</div>
			<?php endif; ?>

			<p class="mudlet-sync__lead">
				<?php
				esc_html_e(
					'Games, makers and releases are read from Mudlet and GitHub rather than typed here. Each one refreshes on its own schedule; this is where those are set.',
					'default'
				);
				?>
			</p>

			<?php if ( ! $jobs ) : ?>
				<p><?php esc_html_e( 'No syncs are registered — the plugins that read from Mudlet are not active.', 'default' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE ); ?>" />
					<?php wp_nonce_field( self::SAVE ); ?>

					<table class="widefat striped mudlet-sync__table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'What', 'default' ); ?></th>
								<th><?php esc_html_e( 'How often', 'default' ); ?></th>
								<th><?php esc_html_e( 'Last run', 'default' ); ?></th>
								<th><?php esc_html_e( 'Next run', 'default' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $jobs as $hook => $job ) : ?>
								<?php self::row( (string) $hook, (array) $job ); ?>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php submit_button( __( 'Save Changes', 'default' ) ); ?>
				</form>

				<?php self::cron_note(); ?>
			<?php endif; ?>
		</div>

		<style>
			.mudlet-sync__lead{max-width:46em}
			.mudlet-sync__table{margin-top:14px;max-width:60em}
			.mudlet-sync__table td,.mudlet-sync__table th{vertical-align:top;padding:10px 12px}
			.mudlet-sync__what b{font-size:14px}
			.mudlet-sync__note{display:block;margin-top:3px;color:#646970;max-width:34em}
			.mudlet-sync__when{color:#646970}
			.mudlet-sync__off{color:#8c8f94}
			.mudlet-sync__cron{max-width:46em;margin-top:18px;padding:10px 12px;
				border-left:3px solid #dba617;background:#fcf9e8;color:#50575e}
		</style>
		<?php
	}

	/**
	 * One job.
	 *
	 * @param string               $hook Cron hook.
	 * @param array<string, mixed> $job  Registered job.
	 */
	private static function row( string $hook, array $job ): void {
		$default = (string) ( $job['default'] ?? 'weekly' );
		$current = self::recurrence( $hook, $default );
		$next    = wp_next_scheduled( $hook );
		$synced  = (int) ( $job['synced'] ?? 0 );
		?>
		<tr>
			<td class="mudlet-sync__what">
				<b><?php echo esc_html( (string) ( $job['label'] ?? $hook ) ); ?></b>
				<?php if ( ! empty( $job['summary'] ) ) : ?>
					— <?php echo esc_html( (string) $job['summary'] ); ?>
				<?php endif; ?>
				<?php if ( ! empty( $job['note'] ) ) : ?>
					<span class="mudlet-sync__note"><?php echo esc_html( (string) $job['note'] ); ?></span>
				<?php endif; ?>
			</td>

			<td>
				<label class="screen-reader-text" for="mudlet-sync-<?php echo esc_attr( $hook ); ?>">
					<?php echo esc_html( (string) ( $job['label'] ?? $hook ) ); ?>
				</label>
				<select name="every[<?php echo esc_attr( $hook ); ?>]" id="mudlet-sync-<?php echo esc_attr( $hook ); ?>">
					<?php foreach ( self::choices() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $current ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>

			<td class="mudlet-sync__when">
				<?php
				echo $synced
					/* translators: %s: human-readable time difference */
					? esc_html( sprintf( __( '%s ago', 'default' ), human_time_diff( $synced ) ) )
					: '<span class="mudlet-sync__off">' . esc_html__( 'Never', 'default' ) . '</span>';
				?>
			</td>

			<td class="mudlet-sync__when">
				<?php if ( 'off' === $current ) : ?>
					<span class="mudlet-sync__off"><?php esc_html_e( 'Never', 'default' ); ?></span>
				<?php elseif ( $next ) : ?>
					<?php echo esc_html( wp_date( 'j M, H:i', $next ) ); ?>
				<?php else : ?>
					<span class="mudlet-sync__off"><?php esc_html_e( 'Not scheduled', 'default' ); ?></span>
				<?php endif; ?>
			</td>

			<td>
				<?php if ( ! empty( $job['sync_url'] ) ) : ?>
					<a class="button" href="<?php echo esc_url( (string) $job['sync_url'] ); ?>">
						<?php esc_html_e( 'Sync now', 'default' ); ?>
					</a>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * The thing that makes every schedule on this page a wish rather than a
	 * promise, said out loud where it is decided.
	 */
	private static function cron_note(): void {
		if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
			?>
			<p class="mudlet-sync__note">
				<?php
				esc_html_e(
					'WordPress runs these on the next page view after they come due, so a site nobody visits syncs nothing. Sync now does not wait.',
					'default'
				);
				?>
			</p>
			<?php
			return;
		}
		?>
		<p class="mudlet-sync__cron">
			<?php
			esc_html_e(
				'DISABLE_WP_CRON is set on this site, so nothing here runs on its own: a system cron has to call wp-cron.php. Sync now still works.',
				'default'
			);
			?>
		</p>
		<?php
	}

	// ── saving ────────────────────────────────────────────────────────

	/**
	 * Store the cadences and put the jobs on them.
	 */
	public static function save(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'default' ) );
		}
		check_admin_referer( self::SAVE );

		$jobs    = self::jobs();
		$allowed = array_keys( self::choices() );
		$posted  = isset( $_POST['every'] ) && is_array( $_POST['every'] )
			? wp_unslash( $_POST['every'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			: array();

		$stored = (array) get_option( self::OPTION, array() );

		foreach ( $jobs as $hook => $job ) {
			$want = sanitize_text_field( (string) ( $posted[ $hook ] ?? '' ) );
			if ( ! in_array( $want, $allowed, true ) ) {
				continue;
			}
			$stored[ (string) $hook ] = $want;
		}

		update_option( self::OPTION, $stored, false );

		// Now rather than on the next request's init: somebody who just pressed
		// Save is about to read the "next run" column they changed.
		foreach ( $jobs as $hook => $job ) {
			self::reschedule( (string) $hook, (string) ( $job['default'] ?? 'weekly' ) );
		}

		// Built by hand rather than with menu_page_url(): admin-post.php never
		// fires admin_menu, so the map of pages that function reads is empty
		// there and it hands back nothing at all.
		wp_safe_redirect(
			admin_url( 'admin.php?page=' . self::MENU . '&settings-updated=true' )
		);
		exit;
	}
}
