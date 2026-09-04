<?php
/**
 * Releases, stored locally.
 *
 * A release used to be a tag string on a post plus a transient. That is fine
 * until something wants to *ask* a question - the last five releases, every
 * installer ever shipped, what shipped in 2026 - none of which the GitHub API
 * answers without a round trip, and none of which survives GitHub being slow.
 *
 * So each release is a row: a `mudlet_release` post carrying the version, the
 * assets, the counts and the changelog. GitHub remains the source of truth and
 * these are never hand-edited; they are a cache of record, refreshed by sync.
 *
 * Deliberately **not publicly queryable**. A release has no front-end URL of its
 * own: the announcement post is the canonical page, and giving the record a
 * second URL would be two addresses for one thing. It has an admin screen and
 * REST, and a download archive is a page template that queries it. Making it
 * public later is easy; taking published URLs away is not.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * The release store.
 */
class Mudlet_Releases_Store {

	const POST_TYPE = 'mudlet_release';

	/** Set while a record still needs its expensive half fetched. */
	const PENDING = '_mudlet_detail_pending';

	/**
	 * Hook up.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the post type.
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'Releases', 'mudlet-releases' ),
					'singular_name' => __( 'Release', 'mudlet-releases' ),
					'menu_name'     => __( 'Releases', 'mudlet-releases' ),
					'search_items'  => __( 'Search releases', 'mudlet-releases' ),
					'not_found'     => __( 'No releases synced yet.', 'mudlet-releases' ),
					// The screen is a reader, not an editor, and the heading is the
					// first thing that says so.
					'edit_item'     => __( 'Release record', 'mudlet-releases' ),
				),
				// A data store, not a destination. See the file comment.
				'public'             => false,
				'publicly_queryable' => false,
				'exclude_from_search'=> true,
				'has_archive'        => false,
				'rewrite'            => false,
				'show_ui'            => true,
				'show_in_menu'       => Mudlet_Sync::MENU,
				'show_in_rest'       => true,
				'supports'           => array( 'title', 'editor', 'custom-fields' ),
				'capabilities'       => array(
					// Nothing here is authored by hand - it is overwritten on
					// the next sync - so the admin screen is read-only.
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap'       => true,
			)
		);
	}

	/**
	 * The stored record for a tag.
	 *
	 * @param string $tag Release tag.
	 * @return WP_Post|null
	 */
	public static function find( string $tag ): ?WP_Post {
		$tag = trim( $tag );
		if ( '' === $tag ) {
			return null;
		}

		// An editor is invited to type "4.22.0", and the API client already
		// accepts that by also trying "Mudlet-4.22.0". The store has to agree,
		// or a bare version quietly misses every stored release and falls
		// through to a network call that may well be rate limited.
		$candidates = array( $tag );
		if ( ! preg_match( '/^Mudlet[-_]/i', $tag ) ) {
			$candidates[] = 'Mudlet-' . $tag;
		}

		foreach ( $candidates as $candidate ) {
			$found = get_posts(
				array(
					'post_type'        => self::POST_TYPE,
					'post_status'      => 'any',
					'numberposts'      => 1,
					'meta_key'         => '_mudlet_tag',
					'meta_value'       => $candidate,
					'suppress_filters' => false,
				)
			);
			if ( $found ) {
				return $found[0];
			}
		}

		return null;
	}

	/**
	 * The newest stable release on record.
	 *
	 * @return WP_Post|null
	 */
	public static function latest(): ?WP_Post {
		$found = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'meta_query'  => array(
					array(
						'key'     => '_mudlet_prerelease',
						'value'   => '1',
						'compare' => '!=',
					),
				),
			)
		);

		return $found ? $found[0] : null;
	}

	/**
	 * Releases on record, newest first.
	 *
	 * This is the reason the store exists: a plain WP_Query, no API, no cache
	 * to miss. A download archive is a loop over this.
	 *
	 * @param int $limit How many, -1 for all.
	 * @return WP_Post[]
	 */
	public static function all( int $limit = -1 ): array {
		return get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => $limit,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
	}

	/**
	 * Turn a stored record back into the array the API hands out.
	 *
	 * @param WP_Post|null $post Stored release.
	 * @return array<string, mixed>|null
	 */
	public static function to_array( ?WP_Post $post ): ?array {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$get = static function ( string $key, $default = '' ) use ( $post ) {
			$value = get_post_meta( $post->ID, '_mudlet_' . $key, true );
			return '' === $value || null === $value ? $default : $value;
		};

		$body = (string) $post->post_content;

		return array(
			'id'          => (int) $get( 'github_id', 0 ),
			'tag'         => (string) $get( 'tag' ),
			'version'     => (string) $get( 'version' ),
			'name'        => $post->post_title,
			'date'        => get_the_date( 'Y-m-d', $post ),
			'url'         => (string) $get( 'url' ),
			'prerelease'  => '1' === (string) $get( 'prerelease' ),
			'counts'      => (array) $get( 'counts', array() ),
			'counts_from' => (array) $get( 'counts', array() ) ? 'pulls' : 'body',
			'builds'      => Mudlet_Releases_Links::decorate( (array) $get( 'builds', array() ) ),
			'contributors' => (array) $get( 'contributors', array() ),
			'changelog'   => Mudlet_Releases_Markdown::to_html( $body ),
			'body'        => $body,
			'stored'      => true,
			'post_id'     => $post->ID,
		);
	}

	/**
	 * Create or update the record for a release.
	 *
	 * Only the cheap half: everything the releases list endpoint already gives,
	 * which is one request for thirty releases including their assets. The
	 * expensive half - checksums and the pull-request changelog - is left to
	 * store_detail(), and the record is flagged until it has run.
	 *
	 * @param array<string, mixed> $raw Release JSON.
	 * @return int Post ID, or 0 on failure.
	 */
	public static function store( array $raw ): int {
		// Records are read-only to everything else - see Mudlet_Releases_Admin::guard.
		// This is the plugin saying the write about to happen is its own.
		$was                        = Mudlet_Releases_Admin::$writing;
		Mudlet_Releases_Admin::$writing = true;
		try {
			return self::write( $raw );
		} finally {
			Mudlet_Releases_Admin::$writing = $was;
		}
	}

	/**
	 * The write itself.
	 *
	 * @param array<string, mixed> $raw GitHub release.
	 * @return int Post id, 0 on failure.
	 */
	private static function write( array $raw ): int {
		$tag = (string) ( $raw['tag_name'] ?? '' );
		if ( '' === $tag ) {
			return 0;
		}

		$existing = self::find( $tag );
		$date     = (string) ( $raw['published_at'] ?? '' );

		$postarr = array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => (string) ( $raw['name'] ?? $tag ),
			'post_name'    => sanitize_title( $tag ),
			'post_content' => (string) ( $raw['body'] ?? '' ),
		);

		if ( $date ) {
			$postarr['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( $date ) );
			$postarr['post_date']     = get_date_from_gmt( $postarr['post_date_gmt'] );
		}

		if ( $existing ) {
			$postarr['ID'] = $existing->ID;
			$post_id       = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$post_id = (int) $post_id;

		// Assets come with the list, and so does each one’s sha256, so a build
		// row is free here - checksums included. Only a release old enough to
		// have no digest on its assets leaves the column empty for
		// store_detail() or an import to fill in from SHA256SUMS.txt.
		$builds = Mudlet_Releases_Release::builds( (array) ( $raw['assets'] ?? array() ), false );

		// ...which means this must not clobber hashes that are already known.
		// It is otherwise very easy to lose them: any read that misses the store
		// and falls back to the API lands here, recomputes builds without
		// checksums, and silently empties a column that was correct a moment
		// ago. Carry them over whenever the filename still matches.
		$previous = $existing ? (array) get_post_meta( $existing->ID, '_mudlet_builds', true ) : array();
		foreach ( $builds as $key => $build ) {
			if ( '' !== ( $build['sha'] ?? '' ) ) {
				continue;
			}
			$was = $previous[ $key ] ?? null;
			if ( is_array( $was ) && ! empty( $was['sha'] ) && ( $was['file'] ?? '' ) === ( $build['file'] ?? '' ) ) {
				$builds[ $key ]['sha'] = $was['sha'];
			}
		}

		update_post_meta( $post_id, '_mudlet_tag', $tag );
		update_post_meta( $post_id, '_mudlet_version', Mudlet_Releases_Release::version_from_tag( $tag ) );
		update_post_meta( $post_id, '_mudlet_github_id', (int) ( $raw['id'] ?? 0 ) );
		update_post_meta( $post_id, '_mudlet_url', (string) ( $raw['html_url'] ?? '' ) );
		update_post_meta( $post_id, '_mudlet_prerelease', empty( $raw['prerelease'] ) ? '0' : '1' );
		update_post_meta( $post_id, '_mudlet_builds', $builds );
		update_post_meta( $post_id, '_mudlet_synced', time() );

		if ( ! metadata_exists( 'post', $post_id, '_mudlet_counts' ) ) {
			update_post_meta( $post_id, self::PENDING, '1' );
		}

		return $post_id;
	}

	/**
	 * Fill in the half that costs requests: checksums and the changelog.
	 *
	 * @param int  $post_id Stored release.
	 * @param bool $force   Redo it even if already done.
	 * @return bool Whether anything was fetched.
	 */
	public static function store_detail( int $post_id, bool $force = false ): bool {
		$tag = (string) get_post_meta( $post_id, '_mudlet_tag', true );
		if ( '' === $tag ) {
			return false;
		}
		if ( ! $force && ! get_post_meta( $post_id, self::PENDING, true ) ) {
			return false;
		}

		// Checksums. Needs the full release, since the list omits nothing but is
		// already in hand - refetch by tag so this works standalone too.
		$raw = Mudlet_Releases_Github_Client::release( $tag );
		if ( is_array( $raw ) ) {
			$builds = Mudlet_Releases_Release::builds( (array) ( $raw['assets'] ?? array() ), true );
			update_post_meta( $post_id, '_mudlet_builds', $builds );
		}

		// The pull requests merged since the previous release. fetch(), not
		// get(): get() would find the record being written and return early.
		$changes = Mudlet_Releases_Changelog::fetch( $tag );
		if ( $changes ) {
			update_post_meta( $post_id, '_mudlet_counts', $changes['counts'] );
			update_post_meta( $post_id, '_mudlet_changes', $changes );
			// Its own meta rather than a corner of the changelog: the import
			// path works it out from a dump that carries no changelog array,
			// and both should land in the same place.
			if ( ! empty( $changes['contributors'] ) ) {
				update_post_meta( $post_id, '_mudlet_contributors', $changes['contributors'] );
			}
		} else {
			// No changelog available - fall back to the body's own headings so
			// the panel still shows something.
			$counts = Mudlet_Releases_Release::counts( (string) get_post_field( 'post_content', $post_id ) );
			update_post_meta( $post_id, '_mudlet_counts', $counts );
		}

		delete_post_meta( $post_id, self::PENDING );
		update_post_meta( $post_id, '_mudlet_synced', time() );

		return true;
	}

	/**
	 * Records still missing their expensive half.
	 *
	 * @param int $limit How many.
	 * @return int[] Post IDs.
	 */
	public static function pending( int $limit = 3 ): array {
		return get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => $limit,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'fields'      => 'ids',
				'meta_key'    => self::PENDING,
			)
		);
	}

	/**
	 * The stored changelog for a tag, if it has one.
	 *
	 * @param string $tag Release tag.
	 * @return array<string, mixed>|null
	 */
	public static function changes( string $tag ): ?array {
		$post = self::find( $tag );
		if ( ! $post ) {
			return null;
		}
		$changes = get_post_meta( $post->ID, '_mudlet_changes', true );
		return is_array( $changes ) && ! empty( $changes['groups'] ) ? $changes : null;
	}
}
