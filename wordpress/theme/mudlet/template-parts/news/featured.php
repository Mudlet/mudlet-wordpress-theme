<?php
/**
 * The newest post, given the room the list cannot.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$mudlet_post = $args['post'] ?? get_post();
if ( ! $mudlet_post instanceof WP_Post ) {
	return;
}

$term    = mudlet_primary_category( $mudlet_post );
$release = mudlet_post_release( $mudlet_post );
?>
<a class="feat" href="<?php echo esc_url( get_permalink( $mudlet_post ) ); ?>"<?php echo $term ? ' data-cat="' . esc_attr( mudlet_category_family( $term->slug ) ) . '"' : ''; ?>>
	<div class="feat__lead">
		<?php mudlet_category_pill( $mudlet_post ); ?>
		<h3><?php echo esc_html( get_the_title( $mudlet_post ) ); ?></h3>
		<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $mudlet_post ), 38, '…' ) ); ?></p>
		<p class="feat__m">
			<span class="who"><?php echo esc_html( get_the_author_meta( 'display_name', (int) $mudlet_post->post_author ) ); ?></span>
			<span class="dot">·</span><?php echo esc_html( get_the_date( '', $mudlet_post ) ); ?>
		</p>
	</div>

	<?php if ( $release ) : ?>
		<div class="relbox">
			<b><?php echo esc_html( $release['version'] ); ?></b>
			<span class="when">
				<?php
				printf(
					/* translators: %s: release date */
					esc_html__( 'released %s', 'mudlet' ),
					esc_html( $release['date'] )
				);
				?>
			</span>
			<dl>
				<?php foreach ( $release['rows'] as $row ) : ?>
					<dt><?php echo esc_html( $row[0] ); ?></dt><dd><?php echo esc_html( $row[1] ); ?></dd>
				<?php endforeach; ?>
			</dl>
		</div>
	<?php endif; ?>
</a>
