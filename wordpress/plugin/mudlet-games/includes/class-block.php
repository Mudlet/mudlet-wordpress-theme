<?php
/**
 * Putting games into a post.
 *
 * A release announcement wants to introduce the games that release added.
 * Mudlet's own 5.0 post does it with four page-builder cards, each carrying a
 * logo, a name and a blurb typed into the post body - which is a copy of four
 * records this plugin already keeps, and goes stale the moment one of them
 * changes its address.
 *
 * So this is a block that stores *slugs*. What it draws is looked up at render
 * time, exactly the way the front page's grid is, and an editor picks which
 * games rather than retyping them.
 *
 * ---------------------------------------------------------------------------
 *
 * Why the block lives in the plugin and not the theme.
 *
 * WordPress renders an unregistered dynamic block as nothing at all - no
 * markup, no comment, no trace. A games block owned by the theme would
 * therefore delete that section from every past announcement the day somebody
 * changes themes, silently. Same argument as the post type next to it: a
 * release post's body must survive a theme rewrite.
 *
 * The *look* is still the theme's. render() hands off to
 * template-parts/blocks/games.php when the theme provides one and only falls
 * back to a plain list of links when it does not, so this file describes what
 * a game is and the theme describes what a card looks like.
 *
 * ---------------------------------------------------------------------------
 *
 * No build step.
 *
 * There is no npm anywhere under wordpress/, and adding one so that two hundred
 * lines of editor UI can be written in JSX would be a poor trade. assets/
 * block-games.js is plain ES5 against the wp.* globals, the same way the
 * theme's own script is plain ES5 against the DOM.
 *
 * @package Mudlet_Games
 */

defined( 'ABSPATH' ) || exit;

/**
 * The mudlet/games block.
 */
class Mudlet_Games_Block {

	/** The block's name. */
	const NAME = 'mudlet/games';

	/** The editor script's handle. */
	const HANDLE = 'mudlet-games-block';

	/**
	 * Hook up.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'category' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_data' ) );
	}

	/**
	 * Register the editor script and the block.
	 */
	public static function register(): void {
		$file = dirname( MUDLET_GAMES_FILE ) . '/assets/block-games.js';

		wp_register_script(
			self::HANDLE,
			// Not plugins_url(): the theme carries a copy of this plugin, and
			// that helper answers for wp-content/plugins only. See shared/mudlet-bundle.php.
			Mudlet_Bundle::url( MUDLET_GAMES_FILE, 'assets/block-games.js' ),
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			// mtime rather than the plugin version: the version moves once a
			// release, this file moves whenever somebody edits it.
			file_exists( $file ) ? (string) filemtime( $file ) : MUDLET_GAMES_VERSION,
			true
		);
		wp_set_script_translations( self::HANDLE, 'mudlet-games' );

		register_block_type(
			self::NAME,
			array(
				'api_version'           => 3,
				'title'                 => __( 'Games in this release', 'mudlet-games' ),
				'description'           => __( 'A grid of bundled games, drawn from their records. Stores which games, not what they say.', 'mudlet-games' ),
				'category'              => 'mudlet',
				'icon'                  => 'games',
				'keywords'              => array(
					__( 'games', 'mudlet-games' ),
					__( 'muds', 'mudlet-games' ),
					__( 'release', 'mudlet-games' ),
				),
				'attributes'            => array(
					'games' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'string' ),
						'default' => array(),
					),
				),
				'supports'              => array(
					'html'   => false,
					'align'  => array( 'wide' ),
					'anchor' => true,
				),
				'editor_script_handles' => array( self::HANDLE ),
				'render_callback'       => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * A category of our own, so the block is findable by somebody who does not
	 * already know its name.
	 *
	 * @param array<int, array<string, mixed>> $categories Block categories.
	 * @return array<int, array<string, mixed>>
	 */
	public static function category( $categories ) {
		foreach ( (array) $categories as $existing ) {
			if ( isset( $existing['slug'] ) && 'mudlet' === $existing['slug'] ) {
				return $categories;
			}
		}

		return array_merge(
			(array) $categories,
			array(
				array(
					'slug'  => 'mudlet',
					'title' => __( 'Mudlet', 'mudlet-games' ),
				),
			)
		);
	}

	/**
	 * Draw the block.
	 *
	 * A slug that no longer names a game is dropped rather than drawn as a
	 * hole: games do get renamed upstream, and a post that quietly shows three
	 * cards instead of four is better than one showing an empty box. With
	 * nothing left the block renders nothing, which is also what happens on a
	 * site where the games have never synced.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ): string {
		$slugs = isset( $attributes['games'] ) && is_array( $attributes['games'] ) ? $attributes['games'] : array();

		$games = array();
		foreach ( $slugs as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$game = mudlet_game( $slug );
			if ( $game ) {
				$games[] = $game;
			}
		}

		if ( ! $games ) {
			return '';
		}

		$wrapper = get_block_wrapper_attributes( array( 'class' => 'rgames' ) );

		if ( locate_template( 'template-parts/blocks/games.php' ) ) {
			ob_start();
			get_template_part(
				'template-parts/blocks/games',
				null,
				array(
					'games'   => $games,
					'wrapper' => $wrapper,
				)
			);
			return (string) ob_get_clean();
		}

		return self::fallback( $games, $wrapper );
	}

	/**
	 * The markup for a site whose theme has no opinion about game cards.
	 *
	 * Deliberately a list of links and nothing else. This is the shape that
	 * cannot look broken in a theme it was not designed for.
	 *
	 * @param array<int, array<string, mixed>> $games   Games.
	 * @param string                           $wrapper Block wrapper attributes.
	 * @return string
	 */
	private static function fallback( array $games, string $wrapper ): string {
		$out = '<ul ' . $wrapper . '>';
		foreach ( $games as $game ) {
			$out .= '<li><a href="' . esc_url( (string) $game['url'] ) . '">' . esc_html( (string) $game['name'] ) . '</a></li>';
		}
		return $out . '</ul>';
	}

	/**
	 * Hand the editor the game list the picker offers.
	 *
	 * @return void
	 */
	public static function editor_data(): void {
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			return;
		}

		wp_localize_script(
			self::HANDLE,
			'MudletGamesBlock',
			array( 'games' => self::choices() )
		);
	}

	/**
	 * Every game, as the picker needs it.
	 *
	 * Forty-odd rows of two short strings. Small enough to hand over with the
	 * script rather than standing up a REST route and a spinner for it.
	 *
	 * @return array<int, array{slug:string, name:string}>
	 */
	private static function choices(): array {
		return array_map(
			static function ( array $game ): array {
				return array(
					'slug' => (string) $game['slug'],
					'name' => (string) $game['name'],
				);
			},
			mudlet_games()
		);
	}
}
