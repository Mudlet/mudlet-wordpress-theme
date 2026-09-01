<?php
/**
 * The manual fallback for the "this release" panel.
 *
 * Normally nobody touches this. A release announcement carries a `release-post`
 * meta key - written by the release plugin - holding a GitHub release id, and
 * inc/github-releases.php reads the version, the date and the changelog counts
 * straight from that release. Numbers nobody types cannot drift from what
 * actually shipped.
 *
 * This box exists for the cases that path does not cover: a release post with
 * no GitHub release behind it, or a correction. Leave the version blank and the
 * panel is simply not drawn.
 *
 * No ACF - the theme should install on a stock WordPress.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * The meta keys, and their labels in the editor.
 *
 * @return array<string, string>
 */
function mudlet_release_fields(): array {
	return array(
		'_mudlet_version'  => __( 'Version (e.g. 4.22.0)', 'mudlet' ),
		'_mudlet_added'    => __( 'New features', 'mudlet' ),
		'_mudlet_improved' => __( 'Improvements', 'mudlet' ),
		'_mudlet_fixed'    => __( 'Fixes', 'mudlet' ),
	);
}

add_action( 'init', 'mudlet_register_release_meta' );
/**
 * Register the meta so it is available over REST and to the block editor.
 */
function mudlet_register_release_meta(): void {
	foreach ( array_keys( mudlet_release_fields() ) as $key ) {
		register_post_meta(
			'post',
			$key,
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}

add_action( 'add_meta_boxes', 'mudlet_release_meta_box' );
/**
 * Add the editor panel - only when the plugin is not there to do it better.
 *
 * With the plugin active an editor gets its "Mudlet release" box, which needs
 * one tag and derives everything. Showing this one beside it would be two
 * panels asking for the same facts, and an invitation to type numbers that
 * disagree with the release.
 */
function mudlet_release_meta_box(): void {
	if ( mudlet_has_release_data() ) {
		return;
	}

	add_meta_box(
		'mudlet-release',
		__( 'Release details', 'mudlet' ),
		'mudlet_release_meta_box_render',
		'post',
		'side',
		'default'
	);
}

/**
 * Render the editor panel.
 *
 * @param WP_Post $post Post being edited.
 */
function mudlet_release_meta_box_render( WP_Post $post ): void {
	wp_nonce_field( 'mudlet_release_meta', 'mudlet_release_nonce' );
	echo '<p style="margin-top:0">' . esc_html__( 'Leave the version blank on posts that are not releases - the panel is hidden when it is empty.', 'mudlet' ) . '</p>';
	foreach ( mudlet_release_fields() as $key => $label ) {
		printf(
			'<p><label for="%1$s"><strong>%2$s</strong></label><br><input type="text" id="%1$s" name="%1$s" value="%3$s" class="widefat"></p>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( (string) get_post_meta( $post->ID, $key, true ) )
		);
	}
}

add_action( 'save_post_post', 'mudlet_release_meta_save' );
/**
 * Persist the editor panel.
 *
 * @param int $post_id Post being saved.
 */
function mudlet_release_meta_save( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['mudlet_release_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mudlet_release_nonce'] ) ), 'mudlet_release_meta' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( array_keys( mudlet_release_fields() ) as $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}

/**
 * The release panel's data for a post, or null when it is not a release.
 *
 * @param WP_Post|int|null $post Post.
 * @return array{version:string,date:string,rows:array<int, array{0:string,1:string}>}|null
 */
function mudlet_post_release( $post = null ): ?array {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	// The good path: the post carries a release tag, and the plugin turns that
	// into the version, the date and the changelog counts. Nobody types a
	// number that can drift from what actually shipped.
	$release = mudlet_release_for_post( $post );
	if ( $release && '' !== $release['version'] ) {
		return array(
			'version' => $release['version'],
			'date'    => $release['date'] ? wp_date( 'j M Y', strtotime( $release['date'] ) ) : get_the_date( 'j M Y', $post ),
			// Empty when the changelog does not use Added/Improved/Fixed
			// headings, which is fine: the panel then shows the version and the
			// date and no invented figures.
			'rows'    => $release['counts'],
			'url'     => $release['url'],
		);
	}

	// The manual fallback, for a post with no GitHub release behind it - or for
	// when GitHub is unreachable and nothing is cached.
	$version = (string) get_post_meta( $post->ID, '_mudlet_version', true );
	if ( '' === $version ) {
		return null;
	}

	$counts = array(
		'_mudlet_added'    => array( __( 'new feature', 'mudlet' ), __( 'new features', 'mudlet' ) ),
		'_mudlet_improved' => array( __( 'improvement', 'mudlet' ), __( 'improvements', 'mudlet' ) ),
		'_mudlet_fixed'    => array( __( 'fix', 'mudlet' ), __( 'fixes', 'mudlet' ) ),
	);

	$rows = array();
	foreach ( $counts as $key => $labels ) {
		$n = (string) get_post_meta( $post->ID, $key, true );
		if ( '' === $n || '0' === $n ) {
			continue;
		}
		$rows[] = array( $n, '1' === $n ? $labels[0] : $labels[1] );
	}

	return array(
		'version' => $version,
		'date'    => get_the_date( 'j M Y', $post ),
		'rows'    => $rows,
		'url'     => '',
	);
}
