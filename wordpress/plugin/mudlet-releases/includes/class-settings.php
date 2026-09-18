<?php
/**
 * What happens when a release arrives: the webhook's one screen.
 *
 * The sync screen next door answers "what runs on a timer". This answers the
 * other half, which has no cadence at all because it runs when GitHub says a
 * release happened - where to point the webhook, whether it is signed, and
 * which releases are worth an announcement post.
 *
 * A whole page for one checkbox would be hard to justify. It is not one
 * checkbox: two thirds of this screen is the endpoint and the secret, which is
 * the information somebody needs *in another tab* while filling in a webhook
 * form on github.com, and which otherwise lives only in a README.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * Mudlet -> Releases.
 */
class Mudlet_Releases_Settings {

	/** The submenu slug. */
	const PAGE = 'mudlet-releases';

	/** admin-post action behind Save. */
	const SAVE = 'mudlet_releases_save_settings';

	/** Whether point releases get an announcement post. */
	const OPTION_POINTS = 'mudlet_releases_announce_points';

	/** Who may change this. */
	const CAP = 'manage_options';

	/**
	 * Hook up.
	 */
	public static function init(): void {
		// After Mudlet_Sync::menu(), which registers the parent at 9.
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 11 );
		add_action( 'admin_post_' . self::SAVE, array( __CLASS__, 'save' ) );
	}

	/**
	 * Whether an announcement post is made for x.y.z where z is not 0.
	 *
	 * Default **on**, which is what the site actually does: of 92 posts on
	 * mudlet.org whose title carries a version, 16 are point releases, three of
	 * them written by hand in the old plugin's shortcode style because its
	 * webhook refused to make them. See class-webhook.php.
	 */
	public static function announce_points(): bool {
		return (bool) get_option( self::OPTION_POINTS, true );
	}

	/**
	 * The URL GitHub is pointed at.
	 *
	 * The old plugin's, deliberately - see class-webhook.php - so an existing
	 * webhook needs no change. Printed here because the one moment anybody
	 * wants it is while looking at a webhook form on another site.
	 */
	public static function endpoint(): string {
		return admin_url( 'admin-ajax.php?action=' . Mudlet_Releases_Webhook::ACTION );
	}

	/**
	 * Put it under the Mudlet menu.
	 */
	public static function menu(): void {
		add_submenu_page(
			Mudlet_Sync::MENU,
			__( 'Releases', 'mudlet-releases' ),
			__( 'Releases', 'mudlet-releases' ),
			self::CAP,
			self::PAGE,
			array( __CLASS__, 'screen' )
		);
	}

	/**
	 * The screen.
	 */
	public static function screen(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'mudlet-releases' ) );
		}

		$signed   = defined( 'MUDLET_RELEASES_WEBHOOK_SECRET' ) && '' !== (string) MUDLET_RELEASES_WEBHOOK_SECRET;
		$occupied = class_exists( 'MudletRelease' );

		echo '<div class="wrap"><h1>' . esc_html__( 'Releases', 'mudlet-releases' ) . '</h1>';

		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a redirect flag, not an action.
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'Saved.', 'mudlet-releases' )
			);
		}

		// ── the webhook ───────────────────────────────────────────────

		echo '<h2>' . esc_html__( 'The webhook', 'mudlet-releases' ) . '</h2>';

		if ( $occupied ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				esc_html__( 'The older Mudlet release plugin is active and is answering this endpoint instead. Deactivate it to hand the job over; nothing needs changing on GitHub when you do.', 'mudlet-releases' )
			);
		}

		echo '<table class="form-table" role="presentation"><tbody>';

		printf(
			'<tr><th scope="row">%s</th><td><code style="user-select:all">%s</code><p class="description">%s</p></td></tr>',
			esc_html__( 'Payload URL', 'mudlet-releases' ),
			esc_html( self::endpoint() ),
			esc_html__( 'Paste this into the release webhook on the Mudlet repository. Content type may be either form-urlencoded or JSON.', 'mudlet-releases' )
		);

		printf(
			'<tr><th scope="row">%s</th><td>%s<p class="description">%s</p></td></tr>',
			esc_html__( 'Secret', 'mudlet-releases' ),
			$signed
				? '<span style="color:#00713c;font-weight:600">' . esc_html__( 'Set — deliveries are verified.', 'mudlet-releases' ) . '</span>'
				: '<span style="color:#b32d2e;font-weight:600">' . esc_html__( 'Not set — deliveries are not verified.', 'mudlet-releases' ) . '</span>',
			$signed
				? esc_html__( 'Every delivery must carry a matching X-Hub-Signature-256 or it is refused.', 'mudlet-releases' )
				: esc_html__( 'Define MUDLET_RELEASES_WEBHOOK_SECRET in wp-config.php, matching the secret on the webhook. Until then anyone who knows the URL can ask for a post — though only ever for a release that really exists on GitHub, because nothing in the request is published.', 'mudlet-releases' )
		);

		echo '</tbody></table>';

		// ── what gets a post ──────────────────────────────────────────

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::SAVE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SAVE ) . '">';

		echo '<h2>' . esc_html__( 'What gets an announcement post', 'mudlet-releases' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody><tr>';
		echo '<th scope="row">' . esc_html__( 'Point releases', 'mudlet-releases' ) . '</th><td>';

		printf(
			'<label><input type="checkbox" name="%s" value="1"%s> %s</label>',
			esc_attr( self::OPTION_POINTS ),
			checked( self::announce_points(), true, false ),
			esc_html__( 'Announce bugfix releases too — 5.0.1, 4.19.1', 'mudlet-releases' )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Off, only feature releases get a post — 5.0.0, 4.19.0. Prereleases and public test builds never get one either way, and a draft release makes a draft post.', 'mudlet-releases' )
		);

		echo '</td></tr></tbody></table>';

		submit_button();
		echo '</form></div>';
	}

	/**
	 * Save.
	 *
	 * An unchecked box posts nothing at all, which is why this reads presence
	 * rather than a value - and why the option is written on every save rather
	 * than only when something is there to write.
	 */
	public static function save(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'mudlet-releases' ) );
		}

		check_admin_referer( self::SAVE );

		update_option( self::OPTION_POINTS, isset( $_POST[ self::OPTION_POINTS ] ) ? 1 : 0 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE,
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
