<?php
/**
 * The contributors block on a release post.
 *
 * Who the changelog above is by. It comes from the same compare between the two
 * tags, so it is already on the record by the time this runs — a meta read, not
 * a request. That is why this can sit under the changelog without the budget
 * warning that block carries.
 *
 * Everyone is listed. A release with twenty-one contributors names all of them:
 * a credits list that stops at "and 15 others" is worse than no credits list,
 * and the chips wrap.
 *
 * Avatars are hotlinked from GitHub rather than sideloaded. They are small,
 * cached, and change when somebody changes their picture — copying them into
 * the media library would mean forty stale thumbnails and a sync job to keep
 * them fresh, for a decorative circle. Where there is no avatar (a commit
 * address GitHub could not match to an account) the initials fill the circle
 * instead, so the chip keeps its shape.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$mudlet_people = mudlet_post_contributors();
if ( ! $mudlet_people ) {
	return;
}

$mudlet_commits = array_sum( array_column( $mudlet_people, 'commits' ) );

// Only for the tag range in the bar, so the two blocks agree on what they are
// comparing. mudlet_releases_changes() reads the store first and costs nothing
// once a release has synced - and the changelog block directly above has just
// called it anyway, so on this page it is always warm.
$mudlet_changes = function_exists( 'mudlet_releases_changes' )
	? mudlet_releases_changes( mudlet_releases_post_tag() )
	: null;
?>
<div class="credits">
	<div class="credits__bar">
		<span class="mk">&gt;</span>
		<?php
		// Literal em dash and ellipsis rather than entities: this goes through
		// esc_html, which would escape the ampersand and print "&mdash;".
		if ( $mudlet_changes && ! empty( $mudlet_changes['previous'] ) ) {
			printf(
				/* translators: 1: previous release tag, 2: this release tag */
				esc_html__( 'contributors — %1$s…%2$s', 'mudlet' ),
				esc_html( $mudlet_changes['previous'] ),
				esc_html( $mudlet_changes['tag'] )
			);
		} else {
			esc_html_e( 'contributors', 'mudlet' );
		}
		?>
		<span class="n">
			<?php
			printf(
				/* translators: 1: number of people, 2: number of commits */
				esc_html( _n( '%1$s person, %2$s commits', '%1$s people, %2$s commits', count( $mudlet_people ), 'mudlet' ) ),
				esc_html( number_format_i18n( count( $mudlet_people ) ) ),
				esc_html( number_format_i18n( $mudlet_commits ) )
			);
			?>
		</span>
	</div>

	<ul class="credits__body">
		<?php foreach ( $mudlet_people as $mudlet_person ) : ?>
			<?php
			$mudlet_name   = (string) ( $mudlet_person['name'] ?? '' );
			$mudlet_url    = (string) ( $mudlet_person['url'] ?? '' );
			$mudlet_avatar = (string) ( $mudlet_person['avatar'] ?? '' );
			$mudlet_n      = (int) ( $mudlet_person['commits'] ?? 0 );
			$mudlet_tag    = $mudlet_url ? 'a' : 'span';

			// Built here rather than inline: an attribute written across several
			// PHP lines carries their indentation into the tooltip.
			$mudlet_title = sprintf(
				/* translators: 1: contributor name, 2: number of commits */
				_n( '%1$s — %2$s commit in this release', '%1$s — %2$s commits in this release', $mudlet_n, 'mudlet' ),
				$mudlet_name,
				number_format_i18n( $mudlet_n )
			);

			$mudlet_href = $mudlet_url
				? ' href="' . esc_url( $mudlet_url ) . '" target="_blank" rel="external nofollow noopener"'
				: '';
			?>
			<li>
				<<?php echo esc_attr( $mudlet_tag ); ?> class="credits__p"<?php echo $mudlet_href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?> title="<?php echo esc_attr( $mudlet_title ); ?>">
					<span class="credits__av">
						<?php if ( $mudlet_avatar ) : ?>
							<img src="<?php echo esc_url( add_query_arg( 's', 48, $mudlet_avatar ) ); ?>" alt="" width="48" height="48" loading="lazy" decoding="async">
						<?php else : ?>
							<?php echo esc_html( mudlet_initials( $mudlet_name ) ); ?>
						<?php endif; ?>
					</span>
					<b><?php echo esc_html( $mudlet_name ); ?></b>
					<span class="c"><?php echo esc_html( number_format_i18n( $mudlet_n ) ); ?></span>
				</<?php echo esc_attr( $mudlet_tag ); ?>>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
