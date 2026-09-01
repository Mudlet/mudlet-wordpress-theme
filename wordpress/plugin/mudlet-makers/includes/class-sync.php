<?php
/**
 * Keeping the store in step with the client.
 *
 * One way in, on purpose. `sync` reads the dialog from raw.githubusercontent.com
 * and, for any maker whose avatar is not in the media library yet, downloads it.
 * First run: one file and eighteen small images. Every run after that is one
 * file — the digest of what was parsed is stored, and an unchanged digest costs
 * nothing else. Cheap enough to run daily, which is what cron does.
 *
 * Do not add a second: a checked-in copy of a list read from upstream anyway
 * only decides how stale a new site starts out, and two ways in are two things
 * to keep true.
 *
 * The upsert is keyed on the upstream name. Order is kept
 * in menu_order rather than recomputed: the dialog lists the current team
 * first, in no order anybody could derive, and that is a decision upstream has
 * made which the website has no business re-sorting.
 *
 * @package Mudlet_Makers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sync scheduling and the upsert.
 */
class Mudlet_Makers_Sync {

	const HOOK   = 'mudlet_makers_sync';
	const SHA    = 'mudlet_makers_source_sha';
	const COUNT  = 'mudlet_makers_count';
	const SYNCED = 'mudlet_makers_synced';

	/**
	 * Digest of the avatar file attached to a record, so a forced sync can tell
	 * a new picture from the same one arriving again. Bookkeeping, not a fact
	 * about the person: deliberately not in Mudlet_Makers_Store::META.
	 */
	const AVATAR_SHA = '_mudlet_maker_avatar_sha';

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
	 * Daily is generous for a list that gains a name every year or two, but it
	 * is one request when nothing has moved, and the alternative is a credits
	 * page fifteen years stale — which is precisely the state this replaces.
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
		$pulled = Mudlet_Makers_Source::pull();

		if ( null === $pulled ) {
			return array(
				'written' => 0,
				'skipped' => false,
				'error'   => __( 'Could not read the makers list from GitHub.', 'mudlet-makers' ),
			);
		}

		// An unchanged dialog means an unchanged list, and skipping is the
		// whole point of storing the digest.
		//
		// But "unchanged upstream" is not "nothing to do". The store can be
		// short of what upstream has - a post somebody deleted, or an avatar
		// whose download failed last time - and a digest check alone would
		// never heal that, because the thing that would trigger a repair is
		// upstream changing, which is exactly what has not happened. So it also
		// has to look complete.
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

		$written = self::upsert_all( $pulled['makers'], $force );
		self::store_extras( $pulled['acknowledgements'], $pulled['supporters'] );

		update_option( self::SHA, $pulled['sha256'], false );
		update_option( self::SYNCED, time(), false );

		return array(
			'written' => $written,
			'skipped' => false,
			'error'   => '',
		);
	}

	/**
	 * Keep the two page-level lists.
	 *
	 * Options rather than posts: neither is about a person, neither has a URL
	 * worth having, and a supporter is a name on a plaque, not a record.
	 *
	 * @param array<int, string>               $acknowledgements Prose paragraphs.
	 * @param array<string, array<int,string>> $supporters       Patreon names by tier.
	 */
	private static function store_extras( array $acknowledgements, array $supporters ): void {
		update_option( Mudlet_Makers_Store::ACKNOWLEDGEMENTS, $acknowledgements, false );
		update_option( Mudlet_Makers_Store::SUPPORTERS, $supporters, false );
	}

	/**
	 * Upsert every maker.
	 *
	 * @param array<int, array<string, mixed>> $makers Parsed makers.
	 * @param bool                             $force  Retry avatars GitHub has already refused.
	 * @return int How many posts were written.
	 */
	private static function upsert_all( array $makers, bool $force = false ): int {
		$written = 0;
		$count   = 0;

		// Records are read-only to everything else - see Mudlet_Makers_Admin::guard.
		// This is the plugin saying the writes about to happen are its own.
		$was                          = Mudlet_Makers_Admin::$writing;
		Mudlet_Makers_Admin::$writing = true;

		foreach ( $makers as $i => $maker ) {
			if ( empty( $maker['name'] ) ) {
				continue;
			}
			$count++;
			if ( self::upsert( $maker, $i, $force ) ) {
				$written++;
			}
		}

		update_option( self::COUNT, $count, false );

		Mudlet_Makers_Admin::$writing = $was;

		return $written;
	}

	/**
	 * Create or update one maker.
	 *
	 * @param array<string, mixed> $maker    One parsed maker.
	 * @param int                  $position Index in upstream's order.
	 * @param bool                 $force    Retry an avatar GitHub has already refused.
	 * @return bool Whether a post was written.
	 */
	private static function upsert( array $maker, int $position, bool $force = false ): bool {
		$name = (string) $maker['name'];
		$post = Mudlet_Makers_Store::find( $name );

		// Read before the meta below overwrites it: a maker who has changed
		// their handle has a different picture at the end of a different URL,
		// which is worth a look on a run nobody forced.
		$was     = $post ? (string) get_post_meta( $post->ID, Mudlet_Makers_Store::META['avatar'], true ) : '';
		$renamed = '' !== $was && $was !== (string) ( $maker['avatar'] ?? '' );

		// Sync owns every field, the body included: what somebody did for
		// Mudlet is a sentence they wrote about themselves in the client, and
		// an editor "improving" it here would be overwritten on the next run
		// anyway. Better that it is honestly read-only than quietly reverted.
		$fields = array(
			'post_type'    => Mudlet_Makers_Store::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $name,
			'post_content' => trim( (string) preg_replace( '/\s+/', ' ', (string) ( $maker['description'] ?? '' ) ) ),
			'menu_order'   => $position,
		);

		if ( $post ) {
			$fields['ID'] = $post->ID;
			$id           = wp_update_post( $fields, true );
		} else {
			// A handle is a better URL than a transliterated name when there is
			// one - /the-makers/slysven/ is what people would type - but the
			// name is what the page is about, so it wins where both exist and
			// only stands in when there is no handle at all.
			$fields['post_name'] = sanitize_title( $name ) ?: sanitize_title( (string) ( $maker['github'] ?? '' ) );
			$id                  = wp_insert_post( $fields, true );
		}

		if ( is_wp_error( $id ) || ! $id ) {
			return false;
		}

		update_post_meta( $id, Mudlet_Makers_Store::KEY, $name );

		$meta = Mudlet_Makers_Store::META;
		update_post_meta( $id, $meta['core'], empty( $maker['core'] ) ? '' : '1' );
		update_post_meta( $id, $meta['github'], (string) ( $maker['github'] ?? '' ) );
		update_post_meta( $id, $meta['discord'], (string) ( $maker['discord'] ?? '' ) );
		update_post_meta( $id, $meta['avatar'], (string) ( $maker['avatar'] ?? '' ) );

		self::attach_avatar( (int) $id, $maker, $force || $renamed );

		return true;
	}

	/**
	 * Give the post its avatar, if it has one to have.
	 *
	 * A third of the makers have no GitHub handle and so no picture anywhere.
	 * That is not a failure and nothing retries it — the roster draws them as
	 * initials.
	 *
	 * Two more things can be true of a thumbnail that is set, and neither used
	 * to be looked at:
	 *
	 *   * it names an attachment that is not there. A WXR import leaves every
	 *     post like this - the importer copies `_thumbnail_id` verbatim and
	 *     remaps it only if the attachment travelled with it and its media
	 *     downloaded. The number survives, the picture does not, and a store
	 *     that trusted the number never fetched one again.
	 *   * the person has changed their GitHub picture. `github.com/<handle>.png`
	 *     is the same URL forever, so nothing upstream says it happened; a
	 *     forced sync reads the bytes and compares them with what is attached.
	 *
	 * A nightly sync still returns before the network on anybody who has a
	 * picture and has not changed their handle.
	 *
	 * @param int                  $id          Post id.
	 * @param array<string, mixed> $maker       Parsed maker.
	 * @param bool                 $force       Look again: retry one GitHub has
	 *                                          refused, and re-read one already
	 *                                          attached.
	 */
	private static function attach_avatar( int $id, array $maker, bool $force = false ): void {
		$avatar = (string) ( $maker['avatar'] ?? '' );
		if ( '' === $avatar ) {
			return;
		}

		$current = self::attached_avatar( $id );
		if ( $current && ! $force ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// A handle whose avatar GitHub has already refused. Retried only on a
		// forced sync: without this the store never looks complete, so every
		// nightly run rewrites all thirty posts and asks GitHub twice for
		// pictures that have not existed for years.
		if ( ! $force && get_post_meta( $id, Mudlet_Makers_Store::META['no_avatar'], true ) ) {
			return;
		}

		$url = (string) ( $maker['avatar_url'] ?? Mudlet_Makers_Source::avatar_url( (string) ( $maker['github'] ?? '' ) ) );
		if ( '' === $url ) {
			return;
		}

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			// Only a mark against a maker who has no picture to lose. One already
			// attached keeps it: GitHub being unreachable this minute is not the
			// account going away.
			if ( ! $current ) {
				self::no_avatar( $id );
			}
			return;
		}

		// Digest the file we were handed, not the one that ends up in the
		// library: an upload can be rewritten on its way in - scaled, rotated -
		// and then no two runs would ever agree.
		$digest = self::digest( $tmp );

		// Already attached, and the same picture. Put the temp file back and
		// leave the library alone: re-attaching an identical avatar would mint
		// a new attachment, and a new URL, on every forced sync.
		if ( $current && '' !== $digest && $digest === self::attached_digest( $id, $current ) ) {
			wp_delete_file( $tmp );
			return;
		}

		$attachment = media_handle_sideload(
			array(
				'name'     => $avatar,
				'tmp_name' => $tmp,
			),
			$id,
			// The attachment's own title. Their name, not a caption about it.
			(string) $maker['name']
		);

		if ( is_wp_error( $attachment ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			if ( ! $current ) {
				self::no_avatar( $id );
			}
			return;
		}

		// The name is printed beside the picture in every place it is drawn, so
		// an empty alt is the honest answer - a screen reader announcing
		// "Vadim Peretokin, Vadim Peretokin" helps nobody.
		update_post_meta( $attachment, '_wp_attachment_image_alt', '' );
		set_post_thumbnail( $id, $attachment );
		update_post_meta( $id, self::AVATAR_SHA, $digest );
		delete_post_meta( $id, Mudlet_Makers_Store::META['no_avatar'] );

		// The picture this one replaces was the record's own and nothing
		// else's. Leaving it behind would put a second copy of every changed
		// avatar in the media library, and a third the time after that.
		if ( $current && $current !== (int) $attachment ) {
			$old = get_post( $current );
			if ( $old && (int) $old->post_parent === $id ) {
				wp_delete_attachment( $current, true );
			}
		}
	}

	/**
	 * The record's avatar, if the attachment it names is really there.
	 *
	 * A `_thumbnail_id` pointing at nothing is cleared on the way past, so the
	 * fetch below it can happen and so missing_thumbnails() stops counting the
	 * record as complete.
	 *
	 * @param int $id Post id.
	 * @return int Attachment id, or 0.
	 */
	private static function attached_avatar( int $id ): int {
		$thumb = (int) get_post_thumbnail_id( $id );

		if ( $thumb && self::is_attachment( $thumb ) ) {
			return $thumb;
		}

		if ( $thumb ) {
			delete_post_meta( $id, '_thumbnail_id' );
			delete_post_meta( $id, self::AVATAR_SHA );
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
	 * The digest of the avatar currently attached.
	 *
	 * Records synced before this was written carry no note of it, so the file
	 * in the library is hashed once and the answer kept. A file that has gone
	 * missing under the library hashes to nothing, which reads as "not the
	 * picture GitHub has" - and the next forced sync puts it back.
	 *
	 * @param int $id         Post id.
	 * @param int $attachment Attachment id.
	 * @return string sha256, or '' if it cannot be read.
	 */
	private static function attached_digest( int $id, int $attachment ): string {
		$sha = (string) get_post_meta( $id, self::AVATAR_SHA, true );
		if ( '' !== $sha ) {
			return $sha;
		}

		$sha = self::digest( (string) get_attached_file( $attachment ) );
		if ( '' !== $sha ) {
			update_post_meta( $id, self::AVATAR_SHA, $sha );
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
	 * Maker posts that should have an avatar and have not got one.
	 *
	 * Two qualifiers, both there to stop the store looking permanently
	 * "incomplete" - which would mean a full rewrite of all thirty posts on
	 * every single nightly sync:
	 *
	 *   * no GitHub handle, so there is no picture to fetch. Twelve of thirty.
	 *   * a handle GitHub has already 404ed - renamed or closed accounts. Two
	 *     more, and they stay marked until somebody forces a sync.
	 *
	 * The third qualifier used to be `_thumbnail_id NOT EXISTS`, which is a
	 * different question from "has no avatar": an imported record has the meta
	 * and no picture - see attach_avatar() - so the completeness check in
	 * sync() passed on a roster of blank circles.
	 *
	 * Read-only, deliberately: the stale meta is cleared where the repair
	 * happens, not where the count is taken.
	 *
	 * @return array<int, int> Post ids.
	 */
	public static function missing_thumbnails(): array {
		$ids = get_posts(
			array(
				'post_type'   => Mudlet_Makers_Store::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					array(
						'key'     => Mudlet_Makers_Store::META['github'],
						'value'   => '',
						'compare' => '!=',
					),
					array(
						'key'     => Mudlet_Makers_Store::META['no_avatar'],
						'compare' => 'NOT EXISTS',
					),
				),
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

		// And one for the attachments they name.
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
	 * Remember that GitHub will not give us this one's picture.
	 *
	 * @param int $id Post id.
	 */
	private static function no_avatar( int $id ): void {
		update_post_meta( $id, Mudlet_Makers_Store::META['no_avatar'], '1' );
	}

	/**
	 * How many makers are on record.
	 *
	 * @return int
	 */
	public static function count(): int {
		$counts = wp_count_posts( Mudlet_Makers_Store::POST_TYPE );

		return (int) ( $counts->publish ?? 0 );
	}
}
