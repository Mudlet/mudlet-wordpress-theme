<?php
/**
 * Keeping the store in step with GitHub.
 *
 * Two paths, because they have very different budgets.
 *
 * **Backfill** is a one-off for a site that has never synced: forty releases,
 * each needing a compare of up to six pages. That is several hundred requests,
 * which anonymous GitHub (60 an hour) would take most of a day to allow. So it
 * is done outside WordPress with the authenticated `gh` CLI - see
 * tools/fetch-releases.mjs - and imported from a file. Minutes, not a day.
 *
 * **Ongoing** is one new release every few weeks, which the API path handles
 * comfortably: a cheap index pass that costs one request, and a detail pass
 * that fills in checksums and changelogs a couple at a time.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sync scheduling and commands.
 */
class Mudlet_Releases_Sync {

	/** The cheap pass: one request, every release GitHub lists. */
	const INDEX = 'mudlet_releases_sync_index';

	/** The expensive one, rationed: changelogs, contributors, checksums. */
	const DETAIL = 'mudlet_releases_sync_detail';

	/**
	 * What the two ship with, and what Mudlet → Sync starts from.
	 *
	 * Weekly for the index, because a release every few weeks does not need
	 * looking for twice a day — and the tag on an announcement post fetches
	 * its own release anyway, so nobody writing one waits for cron.
	 *
	 * Hourly for the detail pass, which sounds like a lot and is not: it does
	 * nothing at all unless a record is flagged as pending, and a query
	 * returning nothing costs no requests. It is a drain for the backfill, not
	 * a poll. Turn it off and the changelogs stop filling in.
	 */
	const EVERY        = 'weekly';
	const EVERY_DETAIL = 'hourly';

	/**
	 * Hook up.
	 */
	public static function init(): void {
		add_action( self::INDEX, array( __CLASS__, 'sync_index' ) );
		add_action( self::DETAIL, array( __CLASS__, 'sync_detail_batch' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'mudlet-releases', 'Mudlet_Releases_CLI' );
		}
	}

	/**
	 * Keep both jobs on whatever cadence the site has chosen.
	 */
	public static function schedule(): void {
		Mudlet_Sync::reschedule( self::INDEX, self::EVERY );
		Mudlet_Sync::reschedule( self::DETAIL, self::EVERY_DETAIL );
	}

	/**
	 * The cheap pass: one request for the whole releases list.
	 *
	 * The list endpoint includes each release's assets, so this stores names,
	 * dates, versions and download rows for thirty releases at the cost of a
	 * single call. Only checksums and changelogs are left outstanding.
	 *
	 * @param int $per_page How many releases to ask for.
	 * @return int How many records were written, or -1 if GitHub could not be
	 *             read. Nought is the ordinary answer — the list has not moved
	 *             since this morning — and somebody who just pressed a button
	 *             deserves to be told which of the two happened.
	 */
	public static function sync_index( int $per_page = 30 ): int {
		$list = Mudlet_Releases_Github_Client::get_json(
			'https://api.github.com/repos/' . Mudlet_Releases_Github_Client::repo() . '/releases?per_page=' . (int) $per_page
		);

		if ( ! is_array( $list ) ) {
			return -1;
		}

		$written = 0;
		foreach ( $list as $raw ) {
			if ( ! is_array( $raw ) || empty( $raw['tag_name'] ) ) {
				continue;
			}
			// Mudlet publishes a public test build most days; they are not
			// releases in any sense a reader means.
			if ( ! empty( $raw['prerelease'] ) || ! empty( $raw['draft'] ) ) {
				continue;
			}
			if ( Mudlet_Releases_Store::store( $raw ) ) {
				++$written;
			}
		}

		return $written;
	}

	/**
	 * The expensive pass, rationed.
	 *
	 * Two records a run against an hourly schedule clears a backlog of forty in
	 * under a day without ever coming near the rate limit - and the backfill
	 * exists so nobody has to wait that long in the first place.
	 *
	 * @param int $batch How many records to detail.
	 * @return int How many were done.
	 */
	public static function sync_detail_batch( int $batch = 2 ): int {
		$done = 0;
		foreach ( Mudlet_Releases_Store::pending( $batch ) as $post_id ) {
			if ( Mudlet_Releases_Store::store_detail( (int) $post_id ) ) {
				++$done;
			}
		}
		return $done;
	}

	/**
	 * Import a dump produced by tools/fetch-releases.mjs.
	 *
	 * @param string $path Absolute path to the JSON file.
	 * @return array{releases:int,changelogs:int,contributors:int,errors:string[]}
	 */
	public static function import( string $path ): array {
		$result = array(
			'releases'     => 0,
			'changelogs'   => 0,
			'contributors' => 0,
			'errors'       => array(),
		);

		if ( ! is_readable( $path ) ) {
			$result['errors'][] = "cannot read $path";
			return $result;
		}

		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) || empty( $data['releases'] ) ) {
			$result['errors'][] = 'no releases in the file';
			return $result;
		}

		foreach ( (array) $data['releases'] as $raw ) {
			if ( empty( $raw['tag_name'] ) ) {
				continue;
			}

			$post_id = Mudlet_Releases_Store::store( $raw );
			if ( ! $post_id ) {
				$result['errors'][] = 'could not store ' . $raw['tag_name'];
				continue;
			}
			++$result['releases'];

			// Checksums came with the dump, so the build rows can be completed
			// here without the request store_detail() would have made.
			$sums = isset( $raw['checksums'] ) && is_array( $raw['checksums'] ) ? $raw['checksums'] : array();
			if ( $sums ) {
				$builds = (array) get_post_meta( $post_id, '_mudlet_builds', true );
				foreach ( $builds as $key => $build ) {
					$file = $build['file'] ?? '';
					if ( '' !== $file && isset( $sums[ $file ] ) ) {
						$builds[ $key ]['sha'] = $sums[ $file ];
					}
				}
				update_post_meta( $post_id, '_mudlet_builds', $builds );
			}

			// Commit titles are raw in the dump; categorising them here keeps
			// one implementation of the rules.
			$changes = $raw['changes'] ?? null;
			if ( is_array( $changes ) && ! empty( $changes['commit_titles'] ) ) {
				$built = Mudlet_Releases_Changelog::build_from_messages(
					(array) $changes['commit_titles'],
					(string) ( $changes['previous'] ?? '' ),
					(string) $raw['tag_name']
				);
				update_post_meta( $post_id, '_mudlet_changes', $built );
				update_post_meta( $post_id, '_mudlet_counts', $built['counts'] );
				++$result['changelogs'];

				// Identities are raw in the dump too, for the same reason: who
				// counts as a person is decided in one place.
				if ( ! empty( $changes['commit_authors'] ) ) {
					$contributors = Mudlet_Releases_Changelog::contributors_from_rows(
						(array) $changes['commit_authors']
					);
					if ( $contributors ) {
						update_post_meta( $post_id, '_mudlet_contributors', $contributors );
						++$result['contributors'];
					}
				}
			} else {
				// No compare in the dump - the oldest release has no previous.
				update_post_meta(
					$post_id,
					'_mudlet_counts',
					Mudlet_Releases_Release::counts( (string) ( $raw['body'] ?? '' ) )
				);
			}

			delete_post_meta( $post_id, Mudlet_Releases_Store::PENDING );
		}

		return $result;
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Manage the Mudlet release store.
	 */
	class Mudlet_Releases_CLI {

		/**
		 * Import a dump from tools/fetch-releases.mjs.
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : Path to the JSON dump.
		 *
		 * ## EXAMPLES
		 *
		 *     wp mudlet-releases import /seed/releases.json
		 *
		 * @param string[] $args Positional arguments.
		 */
		public function import( array $args ): void {
			$result = Mudlet_Releases_Sync::import( $args[0] );

			foreach ( $result['errors'] as $error ) {
				WP_CLI::warning( $error );
			}

			WP_CLI::success(
				sprintf(
					'%d releases stored, %d with a changelog, %d with contributors.',
					$result['releases'],
					$result['changelogs'],
					$result['contributors']
				)
			);
		}

		/**
		 * Refresh the release index from the GitHub API.
		 *
		 * ## OPTIONS
		 *
		 * [--detail=<n>]
		 * : Also fill in checksums and changelogs for this many records.
		 *
		 * @param string[]              $args       Positional arguments.
		 * @param array<string, string> $assoc_args Flags.
		 */
		public function sync( array $args, array $assoc_args ): void {
			$written = Mudlet_Releases_Sync::sync_index();
			if ( $written < 0 ) {
				WP_CLI::error( 'Could not read the releases list from GitHub.' );
			}
			WP_CLI::log( sprintf( '%d releases indexed.', $written ) );

			$detail = (int) ( $assoc_args['detail'] ?? 0 );
			if ( $detail > 0 ) {
				$done = Mudlet_Releases_Sync::sync_detail_batch( $detail );
				WP_CLI::log( sprintf( '%d detailed.', $done ) );
			}

			$pending = count( Mudlet_Releases_Store::pending( 100 ) );
			if ( $pending ) {
				WP_CLI::log( sprintf( '%d still awaiting detail.', $pending ) );
			}

			WP_CLI::success( 'done' );
		}

		/**
		 * Print a post's announcement as Markdown, for a GitHub release.
		 *
		 * What was written in the editor, and nothing that gets generated: the
		 * changelog, the contributors and the download table are left out,
		 * because the release this is pasted onto already carries them.
		 *
		 * ## OPTIONS
		 *
		 * <post>
		 * : Post id or slug.
		 *
		 * [--title]
		 * : Lead with the post's title as an H1. Off by default - a GitHub
		 * release has a title field of its own.
		 *
		 * [--link]
		 * : Append the footer linking back to the announcement. On by default;
		 * pass --no-link to leave it off.
		 *
		 * ## EXAMPLES
		 *
		 *     wp mudlet-releases markdown 4-22-mapping-made-friendlier
		 *     wp mudlet-releases markdown 137 --no-link > notes.md
		 *
		 * @param string[]              $args       Positional arguments.
		 * @param array<string, string> $assoc_args Flags.
		 */
		public function markdown( array $args, array $assoc_args ): void {
			$ref  = (string) ( $args[0] ?? '' );
			$post = ctype_digit( $ref ) ? get_post( (int) $ref ) : get_page_by_path( $ref, OBJECT, 'post' );

			if ( ! $post instanceof WP_Post ) {
				WP_CLI::error( sprintf( 'No post found for "%s".', $ref ) );
			}

			$markdown = Mudlet_Releases_Markdown_Export::post(
				$post,
				array(
					'title' => (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'title', false ),
					'link'  => (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'link', true ),
				)
			);

			if ( '' === trim( $markdown ) ) {
				WP_CLI::error( 'That post has no content of its own to export.' );
			}

			// Straight to STDOUT rather than through WP_CLI::log, so that
			// redirecting it into a file gets the Markdown and nothing else.
			echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown, on purpose.
		}

		/**
		 * List what is stored.
		 *
		 * @param string[] $args Positional arguments.
		 */
		public function list( array $args ): void {
			$rows = array();
			foreach ( Mudlet_Releases_Store::all() as $post ) {
				$release = Mudlet_Releases_Store::to_array( $post );
				$counts  = array();
				foreach ( (array) $release['counts'] as $row ) {
					$counts[] = $row[0] . ' ' . $row[1];
				}
				$rows[] = array(
					'tag'      => $release['tag'],
					'date'     => $release['date'],
					'builds'   => count( (array) $release['builds'] ),
					'counts'   => implode( ', ', $counts ),
					'pending'  => get_post_meta( $post->ID, Mudlet_Releases_Store::PENDING, true ) ? 'yes' : '',
				);
			}

			if ( ! $rows ) {
				WP_CLI::warning( 'nothing stored yet' );
				return;
			}

			WP_CLI\Utils\format_items( 'table', $rows, array( 'tag', 'date', 'builds', 'counts', 'pending' ) );
		}
	}
}
