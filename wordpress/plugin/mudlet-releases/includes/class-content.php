<?php
/**
 * Putting the changelog into the post.
 *
 * The point of the plugin is that a release post needs a tag and nothing else,
 * so a post with a tag and an empty body renders its changelog. There is also
 * an explicit [mudlet_release] shortcode for posts that want the changelog
 * somewhere particular, or want to say something before it.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post content rendering.
 */
class Mudlet_Releases_Content {

	/**
	 * Hook up.
	 */
	public static function init(): void {
		add_shortcode( 'mudlet_release', array( __CLASS__, 'shortcode' ) );

		// The older plugin owns [MudletRelease]. Only stand in for it when it is
		// not active, so the two never fight over the same content - and so a
		// site that drops that plugin does not lose the bodies of every release
		// post it ever published.
		if ( ! shortcode_exists( 'MudletRelease' ) ) {
			add_shortcode( 'MudletRelease', array( __CLASS__, 'legacy_shortcode' ) );
		}

		add_filter( 'the_content', array( __CLASS__, 'maybe_append_changelog' ), 9 );
	}

	/**
	 * [mudlet_release] - the changelog, for the post's tag or an explicit one.
	 *
	 * @param array<string, string>|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'tag' => '' ), (array) $atts, 'mudlet_release' );

		$tag = '' !== $atts['tag'] ? $atts['tag'] : Mudlet_Releases_Post_Tag::get();
		if ( '' === $tag ) {
			return '';
		}

		return self::changelog( $tag );
	}

	/**
	 * [MudletRelease]<id>[/MudletRelease] - the older plugin's shape.
	 *
	 * Its content is a release id rather than a tag, which this handles because
	 * every release post imported from mudlet.org is written that way.
	 *
	 * @param array<string, string>|string $atts    Attributes.
	 * @param string|null                  $content Enclosed content: a release id.
	 * @return string
	 */
	public static function legacy_shortcode( $atts, $content = null ): string {
		$ref = trim( (string) $content );
		return '' === $ref ? '' : self::changelog( $ref );
	}

	/**
	 * Render a changelog, or an honest note if it cannot be fetched.
	 *
	 * @param string $ref Tag or release id.
	 * @return string
	 */
	public static function changelog( string $ref ): string {
		$release = Mudlet_Releases_Release::get( $ref );

		if ( ! $release || '' === $release['changelog'] ) {
			// Never fail silently into an empty post: say what is missing and
			// give the reader somewhere to go.
			$url = 'https://github.com/' . Mudlet_Releases_Github_Client::repo() . '/releases';
			return '<p>' . sprintf(
				/* translators: 1: release tag, 2: link to the releases page */
				esc_html__( 'The changelog for %1$s could not be loaded. It is on %2$s.', 'mudlet-releases' ),
				'<code>' . esc_html( $ref ) . '</code>',
				'<a href="' . esc_url( $url ) . '">GitHub</a>'
			) . '</p>';
		}

		return $release['changelog'];
	}

	/**
	 * Render the changelog for a tagged post whose body is empty.
	 *
	 * This is what makes "add a tag" enough. It only ever fires on a post that
	 * has a release tag and nothing written in it, so it cannot displace
	 * anything an author wrote - and because it is a display filter, the empty
	 * post_content in the database stays empty.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function maybe_append_changelog( $content ): string {
		$content = (string) $content;

		if ( '' !== trim( $content ) ) {
			return $content;
		}
		if ( ! is_singular() && ! doing_filter( 'the_content' ) ) {
			return $content;
		}

		$tag = Mudlet_Releases_Post_Tag::get();
		if ( '' === $tag ) {
			return $content;
		}

		/**
		 * Filter whether an empty tagged post renders its changelog.
		 *
		 * @param bool   $enabled Whether to render.
		 * @param string $tag     The post's release tag.
		 */
		if ( ! apply_filters( 'mudlet_releases_autofill_content', true, $tag ) ) {
			return $content;
		}

		return self::changelog( $tag );
	}
}
