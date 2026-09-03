<?php
/**
 * The changelog block on a release post.
 *
 * Everything merged since the previous release, grouped. The release's own
 * notes are the prose above this; these are the receipts.
 *
 * Every entry is rendered. Not truncated with a link to GitHub - that is an
 * admission the page could not be bothered - and not folded behind a button
 * either: the block has a max height and scrolls inside itself, which already
 * stops 420 entries from burying the rest of the page. A toggle on top of that
 * would be two controls for one problem.
 *
 * That also means no JavaScript is involved. The list is complete and usable
 * with scripting off.
 *
 * Only rendered on a single post: building this costs several API requests the
 * first time, so an archive listing twenty releases must never reach it. See
 * mudlet_releases_changes().
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mudlet_releases_changes' ) ) {
	return;
}

$tag = mudlet_releases_post_tag();
if ( '' === $tag ) {
	return;
}

$changes = mudlet_releases_changes( $tag );
if ( ! $changes || empty( $changes['groups'] ) ) {
	return;
}

// Order matters: what a player cares about first, so the collapsed preview is
// the interesting end of the list. "other" is where an entry lands when its
// title matched no pattern - showing it is what makes a bad rule noticeable -
// and infrastructure is last because it is the largest and least interesting.
$labels = array(
	'added'          => __( 'added', 'mudlet' ),
	'improved'       => __( 'improved', 'mudlet' ),
	'fixed'          => __( 'fixed', 'mudlet' ),
	'other'          => __( 'other', 'mudlet' ),
	'infrastructure' => __( 'infrastructure', 'mudlet' ),
);

$sections = array();
foreach ( $labels as $category => $label ) {
	if ( ! empty( $changes['groups'][ $category ] ) ) {
		$sections[ $category ] = $changes['groups'][ $category ];
	}
}
if ( ! $sections ) {
	return;
}

$total = (int) $changes['total'];
?>
<div class="chlog">
	<div class="chlog__bar">
		<span class="mk">&gt;</span><?php
		printf(
			/* translators: 1: previous tag, 2: this tag */
			esc_html__( 'changelog — %1$s…%2$s', 'mudlet' ),
			esc_html( $changes['previous'] ),
			esc_html( $changes['tag'] )
		);
		?><span class="n">
			<a href="<?php echo esc_url( $changes['compare_url'] ); ?>" target="_blank" rel="external nofollow noopener">
				<?php
				printf(
					/* translators: %s: number of merged pull requests */
					esc_html( _n( '%s change', '%s changes', $total, 'mudlet' ) ),
					esc_html( number_format_i18n( $total ) )
				);
				?>
			</a>
		</span>
	</div>

	<dl class="chlog__body">
		<?php foreach ( $sections as $category => $entries ) : ?>
			<dt><?php echo esc_html( $labels[ $category ] ); ?></dt>
			<?php foreach ( $entries as $entry ) : ?>
				<dd>
					<?php echo esc_html( $entry['title'] ); ?>
					<?php if ( ! empty( $entry['url'] ) ) : ?>
						<a href="<?php echo esc_url( $entry['url'] ); ?>" target="_blank" rel="external nofollow noopener">#<?php echo esc_html( $entry['pr'] ); ?></a>
					<?php endif; ?>
				</dd>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</dl>

</div>
