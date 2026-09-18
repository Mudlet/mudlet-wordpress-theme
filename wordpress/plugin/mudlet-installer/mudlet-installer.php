<?php
/**
 * Plugin Name:       Mudlet Theme Installer
 * Plugin URI:        https://github.com/Mudlet/mudlet-wordpress-theme
 * Description:       Installs the Mudlet theme straight from its GitHub release, for a site whose upload limit is smaller than the archive. Delete it once the theme is in.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            The Mudlet team
 * License:           GPL-2.0-or-later
 *
 * ---------------------------------------------------------------------------
 *
 * Why this exists.
 *
 * `mudlet.zip` is the whole site - the theme, the four plugins under
 * `plugins/`, and the hero's client under `assets/demo/` - and that comes to
 * about 14 MB. PHP ships with `upload_max_filesize = 2M`, plenty of hosts leave
 * it at 8M, and on a good many of them it is not something an admin can change.
 * So the one archive that was meant to make installing this easy is the one
 * thing that cannot be uploaded.
 *
 * Nothing is wrong with the archive. What is wrong is *uploading* it: the
 * server can fetch 14 MB from GitHub without noticing, and only the browser
 * -> PHP leg has a limit on it. So this plugin is the browser's part of the
 * job, and it is small enough to upload anywhere.
 *
 * **It is temporary.** Install it, press the button, delete it. The theme
 * carries its own updater (`inc/updates.php`, over the `Update URI` header and
 * `update_themes_github.com`), so from the moment it is installed every later
 * version arrives on the Dashboard -> Updates screen like any other theme, at
 * any size, with nothing to upload and this plugin long gone.
 *
 * ---------------------------------------------------------------------------
 *
 * It is deliberately not a general-purpose "install from URL" tool.
 *
 * A box an admin can paste any zip URL into is a way to run somebody else's PHP
 * on the site, and it would still be there a year later. This asks GitHub for
 * *this* repository's latest release and installs the asset named `mudlet.zip`
 * from it. The repository is filterable for a fork; a URL typed by a visitor is
 * never involved.
 *
 * @package Mudlet_Installer
 */

defined( 'ABSPATH' ) || exit;

/** The theme's directory name, which is also its slug on the release. */
const MUDLET_INSTALLER_STYLESHEET = 'mudlet';

/** The asset to take off the release. Matches the theme's own MUDLET_UPDATE_ASSET. */
const MUDLET_INSTALLER_ASSET = 'mudlet.zip';

/** admin-post action behind the button. */
const MUDLET_INSTALLER_ACTION = 'mudlet_installer_run';

/**
 * The repository releases come from.
 */
function mudlet_installer_repo(): string {
	/**
	 * Filter the GitHub repository the theme is installed from.
	 *
	 * @param string $repo owner/name.
	 */
	return (string) apply_filters( 'mudlet_installer_repo', 'Mudlet/mudlet-wordpress-theme' );
}

add_action( 'admin_menu', 'mudlet_installer_menu' );
/**
 * One screen, under Appearance, beside the themes it installs.
 */
function mudlet_installer_menu(): void {
	add_theme_page(
		__( 'Install Mudlet theme', 'default' ),
		__( 'Install Mudlet theme', 'default' ),
		'install_themes',
		'mudlet-installer',
		'mudlet_installer_screen'
	);
}

/**
 * Ask GitHub for the latest release.
 *
 * Not cached: this screen is looked at about twice in the life of a site, and a
 * transient here would mean pressing the button after a release and being given
 * the one before it.
 *
 * @return array{version: string, package: string, url: string, published: string}|WP_Error
 */
function mudlet_installer_release() {
	$url = 'https://api.github.com/repos/' . mudlet_installer_repo() . '/releases/latest';

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'mudlet-installer',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		// 404 is the ordinary answer for a repository that has tagged nothing
		// yet, and it is worth saying so rather than "unexpected response".
		return new WP_Error(
			'mudlet_installer_http',
			404 === $code
				? sprintf(
					/* translators: %s: owner/name of a GitHub repository */
					__( '%s has published no releases yet, so there is nothing to install.', 'default' ),
					mudlet_installer_repo()
				)
				: sprintf(
					/* translators: %d: an HTTP status code */
					__( 'GitHub answered %d.', 'default' ),
					$code
				)
		);
	}

	$release = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $release ) || empty( $release['assets'] ) ) {
		return new WP_Error( 'mudlet_installer_shape', __( 'GitHub returned a release with no assets on it.', 'default' ) );
	}

	foreach ( (array) $release['assets'] as $asset ) {
		if ( MUDLET_INSTALLER_ASSET !== ( $asset['name'] ?? '' ) ) {
			continue;
		}

		return array(
			'version'   => ltrim( (string) ( $release['tag_name'] ?? '' ), 'v' ),
			'package'   => (string) $asset['browser_download_url'],
			'url'       => (string) ( $release['html_url'] ?? '' ),
			'published' => (string) ( $release['published_at'] ?? '' ),
		);
	}

	return new WP_Error(
		'mudlet_installer_asset',
		sprintf(
			/* translators: %s: an asset file name */
			__( 'The latest release carries no %s asset.', 'default' ),
			MUDLET_INSTALLER_ASSET
		)
	);
}

/**
 * The screen.
 */
function mudlet_installer_screen(): void {
	if ( ! current_user_can( 'install_themes' ) ) {
		wp_die( esc_html__( 'You are not allowed to install themes on this site.', 'default' ) );
	}

	$installed = wp_get_theme( MUDLET_INSTALLER_STYLESHEET );
	$have      = $installed->exists() ? (string) $installed->get( 'Version' ) : '';
	$release   = mudlet_installer_release();

	echo '<div class="wrap"><h1>' . esc_html__( 'Install Mudlet theme', 'default' ) . '</h1>';

	if ( isset( $_GET['done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading a redirect flag, not acting on it.
		$ok = '1' === $_GET['done']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		printf(
			'<div class="notice notice-%s"><p>%s</p></div>',
			$ok ? 'success' : 'error',
			esc_html( (string) get_transient( 'mudlet_installer_notice' ) )
		);
		delete_transient( 'mudlet_installer_notice' );
	}

	if ( is_wp_error( $release ) ) {
		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $release->get_error_message() ) );
		echo '</div>';
		return;
	}

	echo '<table class="widefat striped" style="max-width:40em;margin:1em 0"><tbody>';
	printf(
		'<tr><td>%s</td><td><strong>%s</strong></td></tr>',
		esc_html__( 'Latest release', 'default' ),
		esc_html( $release['version'] )
	);
	printf(
		'<tr><td>%s</td><td>%s</td></tr>',
		esc_html__( 'Installed', 'default' ),
		'' === $have ? esc_html__( 'not installed', 'default' ) : esc_html( $have )
	);
	echo '</tbody></table>';

	$same = '' !== $have && version_compare( $have, $release['version'], '>=' );

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( MUDLET_INSTALLER_ACTION );
	echo '<input type="hidden" name="action" value="' . esc_attr( MUDLET_INSTALLER_ACTION ) . '">';
	submit_button(
		'' === $have
			? __( 'Install it', 'default' )
			: ( $same ? __( 'Reinstall it', 'default' ) : __( 'Update it', 'default' ) ),
		'primary',
		'submit',
		false
	);
	echo '</form>';

	echo '<p class="description" style="max-width:40em;margin-top:1.5em">';
	printf(
		/* translators: %s: a link to the release on GitHub */
		esc_html__( 'The archive is downloaded by this server from %s, so the upload limit does not apply to it. Once the theme is installed it updates itself from the same releases, and this plugin can be deleted.', 'default' ),
		'<a href="' . esc_url( $release['url'] ) . '">GitHub</a>'
	);
	echo '</p></div>';
}

add_action( 'admin_post_' . MUDLET_INSTALLER_ACTION, 'mudlet_installer_run' );
/**
 * Fetch the release's archive and install it over whatever is there.
 *
 * `overwrite_package` is what makes this work as an update as well as a first
 * install: without it `Theme_Upgrader` refuses as soon as the destination
 * directory exists, which on the second press is always.
 *
 * The theme is never activated here. Installing and activating are different
 * decisions - the whole point of previewing before a switch - and a button that
 * quietly changed how the site looks would be the wrong one to have pressed.
 */
function mudlet_installer_run(): void {
	if ( ! current_user_can( 'install_themes' ) ) {
		wp_die( esc_html__( 'You are not allowed to install themes on this site.', 'default' ) );
	}

	check_admin_referer( MUDLET_INSTALLER_ACTION );

	$release = mudlet_installer_release();
	if ( is_wp_error( $release ) ) {
		mudlet_installer_finish( false, $release->get_error_message() );
	}

	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';

	$upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
	$result   = $upgrader->install( $release['package'], array( 'overwrite_package' => true ) );

	if ( is_wp_error( $result ) ) {
		mudlet_installer_finish( false, $result->get_error_message() );
	}

	if ( ! $result ) {
		// install() answers false or null when the filesystem could not be
		// reached at all - the usual cause is FTP credentials being asked for
		// on a host where WP_Filesystem cannot write directly.
		$messages = $upgrader->skin->get_upgrade_messages();
		mudlet_installer_finish(
			false,
			$messages
				? (string) end( $messages )
				: __( 'The install failed and WordPress gave no reason, which usually means it cannot write to wp-content/themes.', 'default' )
		);
	}

	mudlet_installer_finish(
		true,
		sprintf(
			/* translators: %s: a version number */
			__( 'Mudlet %s is installed. Activate it from Appearance -> Themes when you are ready, then delete this plugin.', 'default' ),
			$release['version']
		)
	);
}

/**
 * Say what happened and go back to the screen.
 *
 * Through a transient rather than a query argument: an install failure is
 * whatever WP_Filesystem said, which can be a paragraph, and a paragraph does
 * not belong in a URL.
 *
 * @param bool   $ok      Whether it worked.
 * @param string $message What to say.
 */
function mudlet_installer_finish( bool $ok, string $message ): void {
	set_transient( 'mudlet_installer_notice', $message, 5 * MINUTE_IN_SECONDS );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page' => 'mudlet-installer',
				'done' => $ok ? '1' : '0',
			),
			admin_url( 'themes.php' )
		)
	);
	exit;
}
