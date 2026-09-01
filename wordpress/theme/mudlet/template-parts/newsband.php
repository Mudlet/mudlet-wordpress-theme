<?php
/**
 * Three post cards, used on the front page and under a single post.
 *
 * Accepts $args: eyebrow, note, heading, and optionally exclude (a post ID) and
 * category (a term ID) to steer "keep reading" towards the same category.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$eyebrow = $args['eyebrow'] ?? __( 'news', 'mudlet' );
$note    = $args['note'] ?? '';
$heading = $args['heading'] ?? __( 'Latest from the team', 'mudlet' );

$query_args = array(
	'posts_per_page'      => 3,
	'ignore_sticky_posts' => true,
	'post_status'         => 'publish',
);
if ( ! empty( $args['exclude'] ) ) {
	$query_args['post__not_in'] = array( (int) $args['exclude'] );
}
if ( ! empty( $args['category'] ) ) {
	$query_args['cat'] = (int) $args['category'];
}

$cards = new WP_Query( $query_args );

// A section that would render an empty grid is worse than no section.
if ( ! $cards->have_posts() ) {
	return;
}
?>
<section class="newsband">
	<div class="w">
		<div class="head" style="margin-bottom:1.75rem">
			<p class="eyebrow"><?php echo esc_html( $eyebrow ); ?><?php echo '' !== $note ? '<span>' . esc_html( $note ) . '</span>' : ''; ?></p>
			<h2><?php echo esc_html( $heading ); ?></h2>
		</div>
		<div class="ncards">
			<?php
			while ( $cards->have_posts() ) :
				$cards->the_post();
				?>
				<a class="ncard" href="<?php the_permalink(); ?>">
					<?php mudlet_category_pill( null, 'tag' ); ?>
					<h3><?php the_title(); ?></h3>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22, '…' ) ); ?></p>
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
				</a>
			<?php endwhile; ?>
		</div>
	</div>
</section>
<?php
wp_reset_postdata();
