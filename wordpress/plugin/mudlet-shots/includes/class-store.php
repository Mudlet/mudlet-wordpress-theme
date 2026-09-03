<?php
/**
 * Where a submission lives while nobody has decided about it.
 *
 * Two halves, and they are deliberately not the same thing:
 *
 * - **The record** is a `mudlet_shot` post. Non-public, no archive, no URL, no
 *   admin list of its own — the review screen in class-admin.php is the only
 *   way to see one. It holds what the visitor typed (a name, a sentence), what
 *   the plugin measured (size, dimensions), and a pointer at the file.
 * - **The file** is on disk under uploads/mudlet-shots/<token>/, and is not an
 *   attachment. See the plugin header for why that is the whole design and not
 *   an implementation choice.
 *
 * The post's status is the decision: `pending` is waiting, `publish` is in the
 * gallery, `trash` was turned down. Those are core's own statuses doing exactly
 * what they are named for, which is worth more than three custom ones.
 *
 * @package Mudlet_Shots
 */

defined( 'ABSPATH' ) || exit;

/**
 * The submission store.
 */
class Mudlet_Shots_Store {

	const POST_TYPE = 'mudlet_shot';

	/** The directory under uploads/ that the queue lives in. */
	const DIR = 'mudlet-shots';

	/** The daily tidy-up. */
	const SWEEP = 'mudlet_shots_sweep';

	/** How often it runs by default, and how it is listed on Mudlet -> Sync. */
	const EVERY = 'daily';

	/** When the sweeper last ran. */
	const SWEPT = 'mudlet_shots_swept';

	/** Meta on the submission post. */
	const META = array(
		// The random directory name. Not the path: where uploads/ is can change
		// under a site, and a stored absolute path is a broken one after a move.
		'token'      => '_mudlet_shot_token',
		'file'       => '_mudlet_shot_file',
		'mime'       => '_mudlet_shot_mime',
		'width'      => '_mudlet_shot_width',
		'height'     => '_mudlet_shot_height',
		'bytes'      => '_mudlet_shot_bytes',
		// Whether it moves, and how many frames it moves through. The first is
		// what decides which file the gallery points at - see
		// Mudlet_Shots_Publish - and the second is only ever shown to a
		// reviewer.
		'animated'   => '_mudlet_shot_animated',
		'frames'     => '_mudlet_shot_frames',
		// What the visitor asked to be credited as. Optional, and often empty.
		'credit'     => '_mudlet_shot_credit',
		// Eight characters of wp_hash( ip ) - see origin() below.
		'origin'     => '_mudlet_shot_origin',
		// Set on approval: the attachment this became.
		'attachment' => '_mudlet_shot_attachment',
	);

	/** Meta on the *attachment*, pointing back the other way. */
	const ATTACHED = '_mudlet_shot_submitted';

	/**
	 * Meta on the *attachment* marking one that moves.
	 *
	 * This one is not bookkeeping - it changes what the site renders. Every
	 * size WordPress derives from an upload goes through an image editor, and
	 * an editor flattens an animation, so **an animated attachment's
	 * sub-sizes are all stills**. Anything drawing one has to reach for the
	 * original file, and anything offering the browser a choice through
	 * `srcset` has to stop. See Mudlet_Shots_Publish::init().
	 */
	const ANIMATED = '_mudlet_shot_animated_image';

	/**
	 * Whether an attachment is one of ours that moves.
	 *
	 * @param int $attachment_id Attachment.
	 * @return bool
	 */
	public static function is_animated( int $attachment_id ): bool {
		return (bool) get_post_meta( $attachment_id, self::ANIMATED, true );
	}

	/**
	 * Hook up.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
		add_action( self::SWEEP, array( __CLASS__, 'sweep' ) );

		// A record whose file has been left behind is a file nobody can reach
		// and nobody will delete, so the file goes when the record does.
		add_action( 'before_delete_post', array( __CLASS__, 'forget' ), 10, 2 );
	}

	/**
	 * Register the post type.
	 *
	 * `public => false` and `show_ui => false` together: this has no front-end
	 * URL, and its admin screen is one we draw. Handing a queue of unreviewed
	 * pictures to the default list table would show titles where the only
	 * useful thing to show is the picture.
	 *
	 * `show_in_rest` is off for the same reason it is on everywhere else in
	 * this repo: the other stores are facts anybody may read, and this one is
	 * a pile of unvetted uploads with a hashed origin beside each.
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Screenshots', 'mudlet-shots' ),
					'singular_name' => __( 'Screenshot', 'mudlet-shots' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title', 'editor' ),
				'delete_with_user'    => false,
			)
		);
	}

	/**
	 * Keep the sweeper on the schedule the Mudlet screen says it is on.
	 */
	public static function schedule(): void {
		Mudlet_Sync::reschedule( self::SWEEP, self::EVERY );
	}

	// -- the queue on disk ---------------------------------------------

	/**
	 * The queue directory, created and guarded if it is not there yet.
	 *
	 * The two guard files are written every time this is called and it is
	 * cheap to do: `file_exists` on two paths beats discovering after an
	 * incident that somebody's restore put the directory back without them.
	 *
	 * @return string Absolute path, no trailing slash. '' if uploads is unusable.
	 */
	public static function queue_dir(): string {
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}

		$dir = trailingslashit( $uploads['basedir'] ) . self::DIR;

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return '';
		}

		// Apache honours this; nginx never reads it. It is here because it
		// costs one file and covers the commonest host, not because the design
		// leans on it - what the design leans on is the token in the path and
		// the fact that nothing anywhere links to it.
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		// Against a server with directory listing on, which would otherwise
		// hand out every token in one request and make the rest of this moot.
		if ( ! file_exists( $dir . '/index.html' ) ) {
			file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		return $dir;
	}

	/**
	 * A fresh directory for one submission, and the token that names it.
	 *
	 * @return string The token, or '' if it could not be made.
	 */
	public static function new_token(): string {
		$root = self::queue_dir();
		if ( '' === $root ) {
			return '';
		}

		// 32 hex characters of real randomness. This is what actually keeps the
		// file off the web on nginx, so it is not sanitize_title( microtime() )
		// and must not become that.
		$token = bin2hex( random_bytes( 16 ) );

		return wp_mkdir_p( $root . '/' . $token ) ? $token : '';
	}

	/**
	 * Where one submission's file is.
	 *
	 * @param int $post_id Submission.
	 * @return string Absolute path, or '' if the record has no file.
	 */
	public static function path( int $post_id ): string {
		$token = (string) get_post_meta( $post_id, self::META['token'], true );
		$file  = (string) get_post_meta( $post_id, self::META['file'], true );
		$root  = self::queue_dir();

		if ( '' === $root || ! self::is_token( $token ) || '' === $file ) {
			return '';
		}

		// The filename is ours, not the visitor's - see Mudlet_Shots_Image -
		// but it is read back out of the database, and a path assembled from
		// the database is a path worth running through basename().
		$path = $root . '/' . $token . '/' . basename( $file );

		return file_exists( $path ) ? $path : '';
	}

	/**
	 * Whether a string is one of our directory names.
	 *
	 * @param string $token Candidate.
	 * @return bool
	 */
	public static function is_token( string $token ): bool {
		return 1 === preg_match( '/^[0-9a-f]{32}$/', $token );
	}

	/**
	 * Throw one submission's directory away.
	 *
	 * @param int $post_id Submission.
	 */
	public static function drop_files( int $post_id ): void {
		$token = (string) get_post_meta( $post_id, self::META['token'], true );
		$root  = self::queue_dir();

		if ( '' === $root || ! self::is_token( $token ) ) {
			return;
		}

		self::rmdir( $root . '/' . $token );
	}

	/**
	 * Remove a queue directory and whatever is in it.
	 *
	 * One level deep on purpose: a submission directory holds one file, and a
	 * recursive delete rooted anywhere under uploads/ is not a thing to write
	 * speculatively.
	 *
	 * @param string $dir Absolute path inside the queue.
	 */
	private static function rmdir( string $dir ): void {
		$root = self::queue_dir();
		if ( '' === $root || ! is_dir( $dir ) || ! str_starts_with( $dir, $root . '/' ) ) {
			return;
		}

		foreach ( (array) glob( $dir . '/*' ) as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}

		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	}

	/**
	 * Delete the file when the record goes.
	 *
	 * @param int          $post_id Post id.
	 * @param WP_Post|null $post    Post.
	 */
	public static function forget( $post_id, $post = null ): void {
		if ( $post instanceof WP_Post && self::POST_TYPE !== $post->post_type ) {
			return;
		}

		self::drop_files( (int) $post_id );
	}

	// -- the sweeper ---------------------------------------------------

	/**
	 * Daily: get rid of what has already been decided about, and of anything on
	 * disk with no record behind it.
	 *
	 * What it deliberately does *not* touch is a pending submission. A queue
	 * that quietly deletes what nobody got round to reviewing is a queue that
	 * loses the picture somebody sent while the person who reviews them was on
	 * holiday. Pending stays until a human says otherwise, and the review
	 * screen says how long each one has been waiting.
	 *
	 * Rejected files go the moment the sweeper sees them - the record stays in
	 * the trash for the usual thirty days so a mistake can be spotted, but the
	 * bytes are the part there is no reason to keep.
	 */
	public static function sweep(): void {
		$root = self::queue_dir();
		if ( '' === $root ) {
			return;
		}

		// Anything decided: approved shots have been copied into the media
		// library already, rejected ones are not wanted.
		$decided = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'trash' ),
				'posts_per_page' => 200,
				'fields'         => 'ids',
			)
		);

		foreach ( $decided as $id ) {
			self::drop_files( (int) $id );
		}

		// And the tokens that are still spoken for, so the orphan pass below
		// leaves them alone.
		$keep = array();
		$live = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 500,
				'fields'         => 'ids',
			)
		);

		foreach ( $live as $id ) {
			$token = (string) get_post_meta( (int) $id, self::META['token'], true );
			if ( '' !== $token ) {
				$keep[ $token ] = true;
			}
		}

		// An orphan is a directory whose record never got written - the request
		// died between the file landing and wp_insert_post, which is rare and
		// leaves a file nothing will ever look at again. An hour of grace, so
		// this cannot race a submission that is still in flight.
		foreach ( (array) glob( $root . '/*', GLOB_ONLYDIR ) as $dir ) {
			$token = basename( $dir );

			if ( isset( $keep[ $token ] ) || ! self::is_token( $token ) ) {
				continue;
			}

			if ( filemtime( $dir ) > time() - HOUR_IN_SECONDS ) {
				continue;
			}

			self::rmdir( $dir );
		}

		update_option( self::SWEPT, time(), false );
	}

	// -- reading records -----------------------------------------------

	/**
	 * The submissions in one state, newest first.
	 *
	 * @param string $status pending, publish or trash.
	 * @param int    $limit  How many.
	 * @return WP_Post[]
	 */
	public static function all( string $status = 'pending', int $limit = 60 ): array {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => $status,
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * How many are waiting.
	 *
	 * @return int
	 */
	public static function pending(): int {
		$counts = wp_count_posts( self::POST_TYPE );

		return (int) ( $counts->pending ?? 0 );
	}

	/**
	 * A short, stable mark for where a submission came from.
	 *
	 * Not the address. A queue that stores IPs is a log of who looked at the
	 * site, kept indefinitely, in a table nobody remembers is there - and the
	 * question a reviewer actually has is never "which address is this" but
	 * "are these six the same person". Eight characters of a salted hash
	 * answers that one and no other, and it cannot be turned back into an
	 * address by anybody who walks off with the database.
	 *
	 * @param string $ip Address.
	 * @return string
	 */
	public static function origin( string $ip ): string {
		return '' === $ip ? '' : substr( wp_hash( 'mudlet-shot|' . $ip ), 0, 8 );
	}
}
