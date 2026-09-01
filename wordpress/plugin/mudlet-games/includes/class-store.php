<?php
/**
 * Games, stored as posts.
 *
 * Public, unlike the release store next door, and for the opposite reason: a
 * release already has a canonical page (its announcement post), so giving the
 * record a second URL would be two addresses for one thing. A game has no page
 * at all, and wants one — /games/achaea/ is a real destination, and /games/ is
 * a list worth having.
 *
 * Every field below is overwritten on the next sync, the body included, and the
 * admin screen is a reader rather than an editor — see class-admin.php. The two
 * go together: the body was once written only on creation, so that an editor
 * could improve on upstream's blurb, and that only made sense while the record
 * was editable at all. Read-only plus write-once would have meant frozen.
 *
 * @package Mudlet_Games
 */

defined( 'ABSPATH' ) || exit;

/**
 * The game store.
 */
class Mudlet_Games_Store {

	const POST_TYPE = 'mudlet_game';

	/** Upstream's name for the game — the key sync upserts on. */
	const KEY = '_mudlet_game_key';

	/** Meta holding the generated facts. */
	const META = array(
		'host'      => '_mudlet_game_host',
		'port'      => '_mudlet_game_port',
		'tls'       => '_mudlet_game_tls',
		'site'      => '_mudlet_game_site',
		'domain'    => '_mudlet_game_domain',
		'links'     => '_mudlet_game_links',
		'icon'      => '_mudlet_game_icon',
		'own_ui'    => '_mudlet_game_own_ui',
		'alt_hosts' => '_mudlet_game_alt_hosts',
	);

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
					'name'          => __( 'Games', 'mudlet-games' ),
					'singular_name' => __( 'Game', 'mudlet-games' ),
					'menu_name'     => __( 'Mudlet games', 'mudlet-games' ),
					'search_items'  => __( 'Search games', 'mudlet-games' ),
					'not_found'     => __( 'No games synced yet.', 'mudlet-games' ),
					// The screen is a reader, not an editor, and the heading is the
					// first thing that says so.
					'edit_item'     => __( 'Game record', 'mudlet-games' ),
					'view_item'     => __( 'View game', 'mudlet-games' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				'has_archive'        => 'games',
				'rewrite'            => array(
					'slug'       => 'games',
					'with_front' => false,
				),
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-games',
				'menu_position'      => 27,
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
				'capabilities'       => array(
					// A game exists because Mudlet ships it. Adding one here
					// would produce a page for a profile nobody can connect
					// to, and the next sync would not know what to do with it.
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
	 * The post for an upstream game name.
	 *
	 * @param string $key Game name as it appears in TGameDetails.h.
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
	 * Flatten a post into the array shape api.php promises.
	 *
	 * @param WP_Post $post Game post.
	 * @return array<string, mixed>
	 */
	public static function to_array( WP_Post $post ): array {
		$meta = static fn( string $k ) => (string) get_post_meta( $post->ID, self::META[ $k ], true );

		$links = json_decode( $meta( 'links' ), true );
		$alt   = json_decode( $meta( 'alt_hosts' ), true );
		$icon  = get_post_thumbnail_id( $post );

		return array(
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'slug'        => $post->post_name,
			'url'         => get_permalink( $post ),
			'host'        => $meta( 'host' ),
			'port'        => (int) $meta( 'port' ),
			'tls'         => '1' === $meta( 'tls' ),
			'site'        => $meta( 'site' ),
			'domain'      => $meta( 'domain' ),
			'links'       => is_array( $links ) ? $links : array(),
			'own_ui'      => '1' === $meta( 'own_ui' ),
			'alt_hosts'   => is_array( $alt ) ? $alt : array(),
			'icon'        => $meta( 'icon' ),
			'icon_id'     => $icon ?: 0,
			'icon_url'    => $icon ? (string) wp_get_attachment_image_url( $icon, 'medium' ) : '',
			'description' => $post->post_content,
		);
	}
}
