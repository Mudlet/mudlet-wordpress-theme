<?php
/**
 * Search results.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();

$found = (int) $GLOBALS['wp_query']->found_posts;
?>

<div class="page page--news">
	<section class="archive">
		<div class="w">
			<div class="head">
				<p class="eyebrow"><?php esc_html_e( 'search', 'mudlet' ); ?><span>
					<?php
					printf(
						/* translators: %s: number of results */
						esc_html( _n( '%s result', '%s results', $found, 'mudlet' ) ),
						esc_html( number_format_i18n( $found ) )
					);
					?>
				</span></p>
				<h2>
					<?php
					printf(
						/* translators: %s: the search term */
						esc_html__( 'Results for “%s”', 'mudlet' ),
						esc_html( get_search_query() )
					);
					?>
				</h2>
			</div>

			<div class="arch">
				<div class="plist">
					<?php
					if ( have_posts() ) {
						while ( have_posts() ) {
							the_post();
							get_template_part( 'template-parts/news/row' );
						}
					} else {
						?>
						<p class="pempty">
							<?php esc_html_e( 'Nothing matched that.', 'mudlet' ); ?>
							<a class="chip" href="<?php echo esc_url( mudlet_news_url() ); ?>"><?php esc_html_e( 'Browse the news', 'mudlet' ); ?></a>
						</p>
						<?php
					}
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
