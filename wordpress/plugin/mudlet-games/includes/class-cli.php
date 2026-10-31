<?php
/**
 * `wp mudlet-games …`
 *
 * @package Mudlet_Games
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage the bundled games list.
 */
class Mudlet_Games_CLI {

	/**
	 * Read the games list from Mudlet's source and write what changed.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Rewrite every post even when upstream has not changed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mudlet-games sync
	 *     wp mudlet-games sync --force
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function sync( array $args, array $assoc_args ): void {
		$result = Mudlet_Games_Sync::sync( isset( $assoc_args['force'] ) );

		if ( '' !== $result['error'] ) {
			WP_CLI::error( $result['error'] );
		}

		if ( $result['skipped'] ) {
			WP_CLI::success( 'Upstream unchanged - nothing to do.' );
			return;
		}

		WP_CLI::success( sprintf( '%d games written.', $result['written'] ) );
	}

	/**
	 * What is on record.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mudlet-games status
	 */
	public function status(): void {
		$synced  = (int) get_option( Mudlet_Games_Sync::SYNCED );
		$missing = Mudlet_Games_Sync::missing_thumbnails();

		WP_CLI::line( sprintf( 'games:    %d', Mudlet_Games_Sync::count() ) );
		WP_CLI::line( sprintf( 'no logo:  %d', count( $missing ) ) );
		WP_CLI::line( sprintf( 'source:   %s', Mudlet_Games_Source::raw_base() . '/' . Mudlet_Games_Source::HEADER ) );
		WP_CLI::line( sprintf( 'digest:   %s', (string) get_option( Mudlet_Games_Sync::SHA ) ?: '(none)' ) );
		WP_CLI::line( sprintf( 'synced:   %s', $synced ? gmdate( 'Y-m-d H:i:s', $synced ) . ' UTC' : 'never' ) );
	}
}

WP_CLI::add_command( 'mudlet-games', 'Mudlet_Games_CLI' );

/**
 * Ask every game for MSSP now, and print what each said.
 *
 * Registered apart from the class above because `status` there already means
 * "what is on record"; this is what the servers say.
 *
 * ## EXAMPLES
 *
 *     wp mudlet-games mssp
 *
 * @when after_wp_load
 */
WP_CLI::add_command(
	'mudlet-games mssp',
	static function (): void {
		$names  = wp_list_pluck( mudlet_games(), 'name', 'id' );
		$status = Mudlet_Games_Status::run();
		$rows   = array();

		foreach ( $status as $id => $one ) {
			$m      = (array) $one['mssp'];
			$rows[] = array(
				'game'     => $names[ $id ] ?? $id,
				'up'       => $one['up'] ? 'yes' : 'no',
				'players'  => $m['PLAYERS'] ?? '',
				'codebase' => $m['CODEBASE'] ?? '',
				'created'  => $m['CREATED'] ?? '',
				'error'    => $one['error'] ?? '',
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'game', 'up', 'players', 'codebase', 'created', 'error' ) );
		$c = Mudlet_Games_Status::counts();
		WP_CLI::success( sprintf( '%d up, %d answered MSSP, of %d.', $c['up'], $c['mssp'], $c['checked'] ) );
	}
);
