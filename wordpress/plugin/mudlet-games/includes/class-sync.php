<?php
/**
 * Keeping the store in step with the client.
 *
 * One way in, on purpose: `sync` reads the header from raw.githubusercontent.com
 * and, for any game whose logo is not in the media library yet, downloads it.
 * First run: one header, one .qrc, forty-odd images. Every run after that is one
 * header — the digest of what was parsed is stored, and an unchanged digest
 * costs nothing else. Cheap enough to run daily, which is what cron does.
 *
 * Do not add a second: a checked-in copy of a list read from upstream anyway
 * only decides how stale a new site starts out, and two ways in are two things
 * to keep true.
 *
 * The upsert is keyed on the upstream game name.
 *
 * @package Mudlet_Games
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sync scheduling and the upsert.
 */
class Mudlet_Games_Sync {

	const HOOK   = 'mudlet_games_sync';
	const SHA    = 'mudlet_games_source_sha';
	const COUNT  = 'mudlet_games_count';
	const SYNCED = 'mudlet_games_synced';

	/**
	 * Digest of the logo file attached to a record, so a refresh can tell an
	 * upstream redraw from the same picture arriving again. Bookkeeping, not a
	 * fact about the game: it is deliberately not in Mudlet_Games_Store::META,
	 * which is what the record screen and REST show.
	 */
	const ICON_SHA = '_mudlet_game_icon_sha';

	/**
	 * Hook up.
	 */
	public static function init(): void {
		add_action( self::HOOK, array( __CLASS__, 'sync' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Keep the refresh scheduled.
	 *
	 * Daily is generous for a list that changes a few times a year, but it is
	 * one request when nothing has moved, and the alternative is a site that is
	 * a year stale because nobody remembered there was a button.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Pull upstream and write what changed.
	 *
	 * @param bool $force Sync even when the source digest is unchanged.
	 * @return array{written: int, skipped: bool, error: string} Summary.
	 */
	public static function sync( bool $force = false ): array {
		$pulled = Mudlet_Games_Source::pull();

		if ( null === $pulled ) {
			return array(
				'written' => 0,
				'skipped' => false,
				'error'   => __( 'Could not read the games list from GitHub.', 'mudlet-games' ),
			);
		}

		// An unchanged header means an unchanged list, and skipping is the whole
		// point of storing the digest: a daily sync should cost one request.
		//
		// But "unchanged upstream" is not "nothing to do". The store can be
		// short of what upstream has - a post somebody deleted, or a logo whose
		// download failed last time - and a digest check alone would never
		// heal that, because the thing that would trigger a repair is upstream
		// changing, which is exactly what has not happened. So it also has to
		// look complete: as many posts as the last pass counted, every one of
		// them with a logo.
		$expected  = (int) get_option( self::COUNT );
		$unchanged = get_option( self::SHA ) === $pulled['sha256'];
		$complete  = $expected > 0
			&& self::count() >= $expected
			&& 0 === count( self::missing_thumbnails() );

		if ( $unchanged && $complete && ! $force ) {
			update_option( self::SYNCED, time(), false );
			return array(
				'written' => 0,
				'skipped' => true,
				'error'   => '',
			);
		}

		// A forced sync is somebody asking for a repair, so it also re-reads
		// the logos - see attach_icon(). A scheduled one does not: the whole
		// budget of a nightly run is the one header above.
		$written = self::upsert_all( $pulled['games'], $force );

		update_option( self::SHA, $pulled['sha256'], false );
		update_option( self::SYNCED, time(), false );

		return array(
			'written' => $written,
			'skipped' => false,
			'error'   => '',
		);
	}

	/**
	 * Upsert every playable game.
	 *
	 * @param array<int, array<string, mixed>> $games   Parsed games.
	 * @param bool                             $refresh Re-read logos already attached.
	 * @return int How many posts were written.
	 */
	private static function upsert_all( array $games, bool $refresh = false ): int {
		$written = 0;
		$count   = 0;

		// Records are read-only to everything else - see Mudlet_Games_Admin::guard.
		// This is the plugin saying the writes about to happen are its own.
		$was                     = Mudlet_Games_Admin::$writing;
		Mudlet_Games_Admin::$writing = true;

		foreach ( $games as $game ) {
			if ( ! empty( $game['internal'] ) ) {
				continue;
			}
			$count++;
			if ( self::upsert( $game, $refresh ) ) {
				$written++;
			}
		}

		update_option( self::COUNT, $count, false );

		Mudlet_Games_Admin::$writing = $was;

		return $written;
	}

	/**
	 * Create or update one game.
	 *
	 * @param array<string, mixed> $game    One parsed game.
	 * @param bool                 $refresh Re-read a logo already attached.
	 * @return bool Whether a post was written.
	 */
	private static function upsert( array $game, bool $refresh = false ): bool {
		$name = (string) $game['name'];
		$post = Mudlet_Games_Store::find( $name );

		// Read before the meta below overwrites it: upstream renaming a game's
		// icon file is reason enough to look at the logo again on a run that
		// was not asked to.
		$was     = $post ? (string) get_post_meta( $post->ID, Mudlet_Games_Store::META['icon'], true ) : '';
		$renamed = '' !== $was && $was !== (string) ( $game['icon'] ?? '' );

		// Sync owns every field, the body included.
		//
		// It did not always: the description used to be written once and then
		// left alone, so an editor could improve on the blurb upstream ships
		// without a nightly job reverting it. That only made sense while the
		// record was editable. Now that it is read-only there is nothing left
		// that could ever correct a description - "written once, never again"
		// would mean frozen at whatever it was on the day the post appeared. A
		// field nothing can change is worse than one upstream owns.
		$fields = array(
			'post_type'    => Mudlet_Games_Store::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $name,
			'post_content' => self::paragraphs( (string) ( $game['description'] ?? '' ) ),
		);

		if ( $post ) {
			$fields['ID'] = $post->ID;
			$id           = wp_update_post( $fields, true );
		} else {
			$fields['post_name'] = sanitize_title( $name );
			$id                  = wp_insert_post( $fields, true );
		}

		if ( is_wp_error( $id ) || ! $id ) {
			return false;
		}

		update_post_meta( $id, Mudlet_Games_Store::KEY, $name );

		$meta = Mudlet_Games_Store::META;
		update_post_meta( $id, $meta['host'], (string) ( $game['host'] ?? '' ) );
		update_post_meta( $id, $meta['port'], (int) ( $game['port'] ?? 0 ) );
		update_post_meta( $id, $meta['tls'], empty( $game['tls'] ) ? '' : '1' );
		update_post_meta( $id, $meta['site'], (string) ( $game['site'] ?? '' ) );
		update_post_meta( $id, $meta['domain'], (string) ( $game['domain'] ?? '' ) );
		update_post_meta( $id, $meta['links'], wp_json_encode( $game['links'] ?? array() ) );
		update_post_meta( $id, $meta['icon'], (string) ( $game['icon'] ?? '' ) );
		update_post_meta( $id, $meta['own_ui'], empty( $game['own_ui'] ) ? '' : '1' );
		update_post_meta( $id, $meta['alt_hosts'], wp_json_encode( $game['alt_hosts'] ?? array() ) );

		self::attach_icon( (int) $id, $game, $refresh || $renamed );

		return true;
	}

	/**
	 * Give the post the logo upstream ships, and keep it that one.
	 *
	 * Three things can be true of a record's thumbnail, and only the first was
	 * ever handled here:
	 *
	 *   * There is none. The usual case - a new post, or a download that failed
	 *     last time. Fetch it.
	 *   * There is one, and it names an attachment that is not there. A WXR
	 *     import leaves every post like this: the importer copies
	 *     `_thumbnail_id` across verbatim and only remaps it if the attachment
	 *     travelled in the same file and its media downloaded, which it cannot
	 *     when the export came from a site the new one cannot reach.
	 *     `get_post_thumbnail_id()` still returns that number, so a store that
	 *     trusted it stayed logo-less through every sync, forced or not - and
	 *     missing_thumbnails() called the store complete while the grid drew
	 *     nothing. Clear it and fetch.
	 *   * There is one and it is real, but upstream has changed the picture.
	 *     Mudlet redraws a logo from time to time and keeps the filename, so
	 *     nothing in the header says it happened. On a refresh the bytes are
	 *     read again and compared with what is attached: same picture, nothing
	 *     happens; different, the new one is attached and the old deleted.
	 *
	 * The comparison is a digest of the file rather than a date or an ETag
	 * because the media library is the only record of what was attached, and
	 * its own copy is the honest thing to compare against.
	 *
	 * A nightly sync never reaches the network here: with a real thumbnail and
	 * no rename it returns on the second line. A refresh reads all forty-odd -
	 * 700KB from the CDN, which is what a first run costs anyway.
	 *
	 * @param int                  $id      Post id.
	 * @param array<string, mixed> $game    Parsed game.
	 * @param bool                 $refresh Look again at a logo already attached.
	 */
	private static function attach_icon( int $id, array $game, bool $refresh = false ): void {
		$icon = (string) ( $game['icon'] ?? '' );
		if ( '' === $icon ) {
			return;
		}

		$current = self::attached_icon( $id );
		if ( $current && ! $refresh ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$url = Mudlet_Games_Source::raw_base() . '/' . ltrim( (string) ( $game['icon_path'] ?? '' ), '/' );
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return;
		}

		// Digest the file we were handed, not the one that ends up in the
		// library: an upload can be rewritten on its way in - scaled, rotated -
		// and then no two runs would ever agree.
		$digest = self::digest( $tmp );

		// Already attached, and the same picture. Put the temp file back and
		// leave the library alone: re-attaching an identical logo would mint a
		// new attachment, and a new URL, on every forced sync.
		if ( $current && '' !== $digest && $digest === self::attached_digest( $id, $current ) ) {
			wp_delete_file( $tmp );
			return;
		}

		$attachment = media_handle_sideload(
			array(
				'name'     => $icon,
				'tmp_name' => $tmp,
			),
			$id,
			/* translators: %s: game name */
			sprintf( __( '%s logo', 'mudlet-games' ), (string) $game['name'] )
		);

		if ( is_wp_error( $attachment ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return;
		}

		// The logos are wordmarks on a background, not pictures of anything, so
		// an empty alt is the honest answer - the game's name is right beside
		// them in the card.
		update_post_meta( $attachment, '_wp_attachment_image_alt', '' );
		set_post_thumbnail( $id, $attachment );
		update_post_meta( $id, self::ICON_SHA, $digest );

		// The logo this one replaces was the record's own and nothing else's.
		// Leaving it behind would put a second copy of every redrawn logo in
		// the media library, and a third the time after that.
		if ( $current && $current !== (int) $attachment ) {
			$old = get_post( $current );
			if ( $old && (int) $old->post_parent === $id ) {
				wp_delete_attachment( $current, true );
			}
		}
	}

	/**
	 * The record's logo, if the attachment it names is really there.
	 *
	 * A `_thumbnail_id` pointing at nothing is cleared on the way past, so the
	 * fetch below it can happen and so missing_thumbnails() stops counting the
	 * record as complete.
	 *
	 * @param int $id Post id.
	 * @return int Attachment id, or 0.
	 */
	private static function attached_icon( int $id ): int {
		$thumb = (int) get_post_thumbnail_id( $id );

		if ( $thumb && self::is_attachment( $thumb ) ) {
			return $thumb;
		}

		if ( $thumb ) {
			delete_post_meta( $id, '_thumbnail_id' );
			delete_post_meta( $id, self::ICON_SHA );
		}

		return 0;
	}

	/**
	 * Whether an id names an attachment that exists.
	 *
	 * @param int $id Attachment id.
	 * @return bool
	 */
	private static function is_attachment( int $id ): bool {
		return $id > 0 && 'attachment' === get_post_type( $id );
	}

	/**
	 * The digest of the logo currently attached.
	 *
	 * Records synced before this was written carry no note of it, so the file
	 * in the library is hashed once and the answer kept. A file that has gone
	 * missing under the library hashes to nothing, which reads as "not the
	 * picture upstream has" - and the next refresh puts it back.
	 *
	 * @param int $id         Post id.
	 * @param int $attachment Attachment id.
	 * @return string sha256, or '' if it cannot be read.
	 */
	private static function attached_digest( int $id, int $attachment ): string {
		$sha = (string) get_post_meta( $id, self::ICON_SHA, true );
		if ( '' !== $sha ) {
			return $sha;
		}

		$sha = self::digest( (string) get_attached_file( $attachment ) );
		if ( '' !== $sha ) {
			update_post_meta( $id, self::ICON_SHA, $sha );
		}

		return $sha;
	}

	/**
	 * sha256 of a file.
	 *
	 * @param string $path File path.
	 * @return string Digest, or '' if it cannot be read.
	 */
	private static function digest( string $path ): string {
		if ( '' === $path || ! is_readable( $path ) ) {
			return '';
		}

		$sha = hash_file( 'sha256', $path );

		return is_string( $sha ) ? $sha : '';
	}

	/**
	 * Upstream's blank-line-separated description as paragraphs.
	 *
	 * @param string $text Description.
	 * @return string
	 */
	private static function paragraphs( string $text ): string {
		$text = trim( str_replace( "\r\n", "\n", $text ) );
		if ( '' === $text ) {
			return '';
		}

		$out = array();
		foreach ( preg_split( '/\n{2,}/', $text ) as $para ) {
			$para = trim( preg_replace( '/\s+/', ' ', $para ) );
			if ( '' !== $para ) {
				$out[] = $para;
			}
		}

		return implode( "\n\n", $out );
	}

	/**
	 * Game posts with no logo attached.
	 *
	 * "No logo" is not the same question as "no `_thumbnail_id`", which is what
	 * this used to ask. An imported record has the meta and no picture - see
	 * attach_icon() - and answering the easy question meant the completeness
	 * check in sync() passed on a store that could not draw a single logo.
	 *
	 * Read-only, deliberately: the stale meta is cleared where the repair
	 * happens, not where the count is taken.
	 *
	 * @return array<int, int> Post ids.
	 */
	public static function missing_thumbnails(): array {
		$ids = get_posts(
			array(
				'post_type'   => Mudlet_Games_Store::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		if ( ! $ids ) {
			return array();
		}

		// One query for the thumbnail ids rather than one per record.
		update_meta_cache( 'post', $ids );

		$thumbs = array();
		foreach ( $ids as $id ) {
			$thumbs[ (int) $id ] = (int) get_post_thumbnail_id( (int) $id );
		}

		// And one for the attachments they name, so this stays two queries
		// whether there are forty records or four hundred.
		$named    = array_values( array_unique( array_filter( $thumbs ) ) );
		$existing = $named ? get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'post__in'    => $named,
			)
		) : array();
		$existing = array_map( 'intval', $existing );

		$missing = array();
		foreach ( $thumbs as $id => $thumb ) {
			if ( ! $thumb || ! in_array( $thumb, $existing, true ) ) {
				$missing[] = $id;
			}
		}

		return $missing;
	}

	/**
	 * How many games are on record.
	 *
	 * @return int
	 */
	public static function count(): int {
		$counts = wp_count_posts( Mudlet_Games_Store::POST_TYPE );

		return (int) ( $counts->publish ?? 0 );
	}
}
