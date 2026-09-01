<?php
/**
 * Makers, stored as posts.
 *
 * Public, like the game store next door and for the same reason: a maker has no
 * page anywhere else, and /the-makers/slysven/ is a real destination — a place
 * to link somebody when you want to credit them by name.
 *
 * What it does *not* have is an archive. /the-makers/ is an ordinary editable
 * page: the prose on it ("Mudlet is built by volunteers…", how to join) is
 * nobody's business but the site's, and the roster is drawn into it by the
 * theme. Registering an archive on the same path would have the post type
 * quietly take that page over.
 *
 * Every field below is overwritten on the next sync, the body included, and the
 * admin screen is a reader rather than an editor — see class-admin.php.
 *
 * @package Mudlet_Makers
 */

defined( 'ABSPATH' ) || exit;

/**
 * The maker store.
 */
class Mudlet_Makers_Store {

	const POST_TYPE = 'mudlet_maker';

	/** Upstream's name for the person — the key sync upserts on. */
	const KEY = '_mudlet_maker_key';

	/** Meta holding the generated facts. */
	const META = array(
		'core'    => '_mudlet_maker_core',
		'github'  => '_mudlet_maker_github',
		'discord' => '_mudlet_maker_discord',
		'avatar'  => '_mudlet_maker_avatar',
		// Set when a handle has one but GitHub would not give it to us. Two of
		// the eighteen 404: accounts renamed or closed years after the credit
		// was written. See Mudlet_Makers_Sync::missing_thumbnails().
		'no_avatar' => '_mudlet_maker_no_avatar',
	);

	/** Where the prose under the credits is kept. Not per-person. */
	const ACKNOWLEDGEMENTS = 'mudlet_makers_acknowledgements';

	/** The patreon names, by tier. Also not per-person. */
	const SUPPORTERS = 'mudlet_makers_supporters';

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
					'name'          => __( 'Makers', 'mudlet-makers' ),
					'singular_name' => __( 'Maker', 'mudlet-makers' ),
					'menu_name'     => __( 'Mudlet makers', 'mudlet-makers' ),
					'search_items'  => __( 'Search makers', 'mudlet-makers' ),
					'not_found'     => __( 'No makers synced yet.', 'mudlet-makers' ),
					// The screen is a reader, not an editor, and the heading is
					// the first thing that says so.
					'edit_item'     => __( 'Maker record', 'mudlet-makers' ),
					'view_item'     => __( 'View maker', 'mudlet-makers' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				// The page owns /the-makers/. See the file header.
				'has_archive'        => false,
				'rewrite'            => array(
					'slug'       => 'the-makers',
					'with_front' => false,
				),
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-groups',
				'menu_position'      => 28,
				'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'page-attributes' ),
				'capabilities'       => array(
					// A maker exists because Mudlet credits them. Adding one
					// here would produce a page for somebody the next sync
					// would not know what to do with.
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap'       => true,
			)
		);

		foreach ( self::META as $meta ) {
			register_post_meta(
				self::POST_TYPE,
				$meta,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}

	/**
	 * The post for an upstream name.
	 *
	 * @param string $key Name as it appears in dlgAboutDialog.cpp.
	 * @return WP_Post|null
	 */
	public static function find( string $key ): ?WP_Post {
		$found = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'any',
				'numberposts'      => 1,
				'meta_key'         => self::KEY,   // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $key,        // phpcs:ignore WordPress.DB.SlowDBQuery
				'suppress_filters' => false,
			)
		);

		return $found ? $found[0] : null;
	}

	/**
	 * The initials a maker with no avatar is drawn as.
	 *
	 * A third of the list has no GitHub handle and so no picture. Two letters
	 * from the name is the same answer every chat client reaches for, and it
	 * keeps the roster on one grid instead of two.
	 *
	 * @param string $name Display name.
	 * @return string One or two characters.
	 */
	public static function initials( string $name ): string {
		$words = preg_split( '/\s+/', trim( $name ) ) ?: array();
		$words = array_values( array_filter( $words ) );

		if ( ! $words ) {
			return '?';
		}

		$first = mb_substr( $words[0], 0, 1 );
		$last  = count( $words ) > 1 ? mb_substr( (string) end( $words ), 0, 1 ) : '';

		return mb_strtoupper( $first . $last );
	}

	/**
	 * Flatten a post into the array shape api.php promises.
	 *
	 * @param WP_Post $post Maker post.
	 * @return array<string, mixed>
	 */
	public static function to_array( WP_Post $post ): array {
		$meta   = static fn( string $k ) => (string) get_post_meta( $post->ID, self::META[ $k ], true );
		$github = $meta( 'github' );
		$avatar = get_post_thumbnail_id( $post );

		return array(
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'slug'        => $post->post_name,
			'url'         => get_permalink( $post ),
			'core'        => '1' === $meta( 'core' ),
			'github'      => $github,
			'github_url'  => '' === $github ? '' : 'https://github.com/' . $github,
			'discord'     => $meta( 'discord' ),
			'avatar'      => $meta( 'avatar' ),
			'avatar_id'   => $avatar ?: 0,
			'avatar_url'  => $avatar ? (string) wp_get_attachment_image_url( $avatar, 'medium' ) : '',
			'initials'    => self::initials( $post->post_title ),
			// Upstream's HTML. Print it through wp_kses_post, not esc_html.
			'description' => $post->post_content,
		);
	}
}
