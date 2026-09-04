<?php
/**
 * The news index.
 *
 * The prototype filtered categories in the browser because it had one page of
 * posts and nowhere to navigate to. Here the chips are links to real category
 * archives, which is both less code and the behaviour a reader expects from a
 * URL they can share. archive.php reuses this file's parts for those.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();

$paged     = max( 1, (int) get_query_var( 'paged' ) );
$total     = (int) wp_count_posts()->publish;
$oldest    = get_posts( array( 'numberposts' => 1, 'order' => 'ASC', 'fields' => 'ids' ) );
$since     = $oldest ? get_the_date( 'Y', $oldest[0] ) : '';
$featured  = null;

// The newest post gets the room the list cannot give it - but only on page one,
// where "newest" still means something.
if ( 1 === $paged && have_posts() ) {
	the_post();
	$featured = get_post();
}
?>

<div class="page page--news">
	<section class="archive">
		<div class="w">
			<div class="head">
				<p class="eyebrow"><?php esc_html_e( 'news', 'mudlet' ); ?><span>
					<?php
					if ( $since ) {
						printf(
							/* translators: 1: number of posts, 2: year of the oldest post */
							esc_html__( '%1$s posts since %2$s', 'mudlet' ),
							esc_html( number_format_i18n( $total ) ),
							esc_html( $since )
						);
					}
					?>
				</span></p>
				<h2><?php single_post_title( '', true ); ?></h2>
			</div>

			<?php
			if ( $featured ) {
				get_template_part( 'template-parts/news/featured', null, array( 'post' => $featured ) );
			}
			?>

			<div class="arch">
				<?php get_template_part( 'template-parts/news/filters' ); ?>

				<div class="plist">
					<?php
					// A heading whenever the year changes, which is what gives
					// the archive its spine. It is driven by the rows in the
					// list, not by the featured post above them - otherwise a
					// featured post from a different year than the one below it
					// leaves a heading standing over nothing.
					$mudlet_last_year = '';

					while ( have_posts() ) :
						the_post();
						$year = get_the_date( 'Y' );
						if ( $year !== $mudlet_last_year ) {
							printf( '<p class="pyear" data-year="%1$s">%1$s</p>', esc_html( $year ) );
							$mudlet_last_year = $year;
						}
						get_template_part( 'template-parts/news/row' );
					endwhile;

					// post_count, not have_posts(): the loop above has exhausted
					// itself by now, so have_posts() is false on every page,
					// including the ones that just rendered eighteen posts.
					if ( ! $featured && 0 === (int) $GLOBALS['wp_query']->post_count ) :
						?>
						<p class="pempty"><?php esc_html_e( 'No posts here yet.', 'mudlet' ); ?></p>
						<?php
					endif;
					?>
				</div>

				<?php mudlet_pager(); ?>
				<?php get_sidebar( 'news' ); ?>
			</div>
		</div>
	</section>
</div>

<?php
get_footer();
