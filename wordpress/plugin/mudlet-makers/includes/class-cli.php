<?php
/**
 * `wp mudlet-makers …`
 *
 * @package Mudlet_Makers
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage the credits list.
 */
class Mudlet_Makers_CLI {

	/**
	 * Read the credits from Mudlet's source and write what changed.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Rewrite every post even when upstream has not changed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mudlet-makers sync
	 *     wp mudlet-makers sync --force
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function sync( array $args, array $assoc_args ): void {
		$result = Mudlet_Makers_Sync::sync( isset( $assoc_args['force'] ) );

		if ( '' !== $result['error'] ) {
			WP_CLI::error( $result['error'] );
		}

		if ( $result['skipped'] ) {
			WP_CLI::success( 'Upstream unchanged - nothing to do.' );
			return;
		}

		WP_CLI::success( sprintf( '%d makers written.', $result['written'] ) );
	}

	/**
	 * What is on record.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mudlet-makers status
	 */
	public function status(): void {
		$synced  = (int) get_option( Mudlet_Makers_Sync::SYNCED );
		$missing = Mudlet_Makers_Sync::missing_thumbnails();
		$core    = count( mudlet_makers( array( 'group' => 'core' ) ) );

		WP_CLI::line( sprintf( 'makers:      %d', Mudlet_Makers_Sync::count() ) );
		WP_CLI::line( sprintf( 'current:     %d', $core ) );
		// Outstanding, not missing: the twelve with no handle and the two
		// GitHub has refused are not counted - see missing_thumbnails().
		WP_CLI::line( sprintf( 'avatars due: %d', count( $missing ) ) );
		WP_CLI::line( sprintf( 'source:      %s', Mudlet_Makers_Source::raw_base() . '/' . Mudlet_Makers_Source::DIALOG ) );
		WP_CLI::line( sprintf( 'digest:      %s', (string) get_option( Mudlet_Makers_Sync::SHA ) ?: '(none)' ) );
		WP_CLI::line( sprintf( 'synced:      %s', $synced ? gmdate( 'Y-m-d H:i:s', $synced ) . ' UTC' : 'never' ) );
	}
}

WP_CLI::add_command( 'mudlet-makers', 'Mudlet_Makers_CLI' );
