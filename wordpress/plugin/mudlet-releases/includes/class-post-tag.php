<?php
/**
 * The one thing a release post needs: a tag.
 *
 * Everything else is looked up from it. The editor box is a single text field,
 * and it accepts either "Mudlet-4.22.0" or "4.22.0".
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * Release tag post meta.
 */
class Mudlet_Releases_Post_Tag {

	/** Where the tag is stored. */
	const META = '_mudlet_release_tag';

	/**
	 * The key the older release plugin used, holding a GitHub release *id*
	 * rather than a tag. Imported posts carry it, so it is read as a fallback
	 * and upgraded to a tag the first time it is resolved.
	 */
	const LEGACY_META = 'release-post';

	/**
	 * Hook up.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ) );
	}

	/**
	 * Post types that can be releases.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		/**
		 * Filter which post types get a release tag.
		 *
		 * @param string[] $types Post types.
		 */
		return (array) apply_filters( 'mudlet_releases_post_types', array( 'post' ) );
	}

	/**
	 * Register the meta so it is available over REST.
	 */
	public static function register_meta(): void {
		foreach ( self::post_types() as $type ) {
			register_post_meta(
				$type,
				self::META,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static function (): bool {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * The tag for a post, resolving and upgrading legacy ids on the way.
	 *
	 * @param WP_Post|int|null $post Post.
	 * @return string Empty when the post is not a release.
	 */
	public static function get( $post = null ): string {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$tag = trim( (string) get_post_meta( $post->ID, self::META, true ) );
		if ( '' !== $tag ) {
			return $tag;
		}

		// Older posts carry a release id instead. Resolve it once and write the
		// tag back, so this costs one lookup per post ever rather than one per
		// page view - and so the post ends up in the shape everything else
		// expects.
		$legacy = trim( (string) get_post_meta( $post->ID, self::LEGACY_META, true ) );
		if ( '' === $legacy || ! ctype_digit( $legacy ) ) {
			return '';
		}

		$raw = Mudlet_Releases_Github_Client::release( $legacy );
		if ( ! $raw || empty( $raw['tag_name'] ) ) {
			return '';
		}

		$tag = (string) $raw['tag_name'];
		update_post_meta( $post->ID, self::META, $tag );

		return $tag;
	}

	/**
	 * Add the editor panel.
	 */
	public static function add_meta_box(): void {
		foreach ( self::post_types() as $type ) {
			add_meta_box(
				'mudlet-release-tag',
				__( 'Mudlet release', 'mudlet-releases' ),
				array( __CLASS__, 'render_meta_box' ),
				$type,
				'side'
			);
		}
	}

	/**
	 * Render the editor panel.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'mudlet_release_tag', 'mudlet_release_tag_nonce' );

		$tag    = (string) get_post_meta( $post->ID, self::META, true );
		$legacy = (string) get_post_meta( $post->ID, self::LEGACY_META, true );

		echo '<p style="margin-top:0">' . esc_html__(
			'The GitHub release tag. Everything else — the changelog, the counts, the download details — is read from it.',
			'mudlet-releases'
		) . '</p>';

		printf(
			'<input type="text" name="%1$s" id="%1$s" value="%2$s" class="widefat" placeholder="Mudlet-4.22.0">',
			esc_attr( self::META ),
			esc_attr( $tag )
		);

		echo '<p class="description">' . esc_html__( '"4.22.0" works too. Leave it blank on posts that are not releases.', 'mudlet-releases' ) . '</p>';

		if ( '' === $tag && '' !== $legacy ) {
			echo '<p class="description">' . sprintf(
				/* translators: %s: a GitHub release id */
				esc_html__( 'This post still uses the older release id %s. It will be turned into a tag automatically the first time the post is viewed.', 'mudlet-releases' ),
				'<code>' . esc_html( $legacy ) . '</code>'
			) . '</p>';
			return;
		}

		if ( '' === $tag ) {
			return;
		}

		// Confirm the tag actually resolves, so a typo is caught in the editor
		// rather than discovered on the published page.
		$release = Mudlet_Releases_Release::get( $tag );

		if ( ! $release ) {
			echo '<p style="color:#b32d2e"><strong>' . esc_html__( 'No release found for that tag.', 'mudlet-releases' ) . '</strong></p>';
			return;
		}

		echo '<p style="color:#1e7b34"><strong>' . esc_html(
			sprintf(
				/* translators: 1: version, 2: date */
				__( 'Found %1$s, released %2$s.', 'mudlet-releases' ),
				$release['version'],
				$release['date']
			)
		) . '</strong></p>';

		if ( $release['counts'] ) {
			$parts = array();
			foreach ( $release['counts'] as $row ) {
				$parts[] = $row[0] . ' ' . $row[1];
			}
			echo '<p class="description">' . esc_html( implode( ', ', $parts ) ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__(
				'This changelog has no Added/Improved/Fixed headings, so the release panel will show no counts.',
				'mudlet-releases'
			) . '</p>';
		}
	}

	/**
	 * Persist the field.
	 *
	 * @param int $post_id Post being saved.
	 */
	public static function save( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['mudlet_release_tag_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mudlet_release_tag_nonce'] ) ), 'mudlet_release_tag' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::META ] ) ) {
			return;
		}

		$tag = sanitize_text_field( wp_unslash( $_POST[ self::META ] ) );

		if ( '' === $tag ) {
			delete_post_meta( $post_id, self::META );
			return;
		}

		update_post_meta( $post_id, self::META, $tag );
		// A retyped tag should be re-checked, not read from a stale cache.
		Mudlet_Releases_Github_Client::flush( $tag );
	}
}
