<?php
/**
 * Search results.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();

$found = (int) $GLOBALS['wp_query']->found_posts;

// The wiki, under the site's own rows and never mixed into them - the same
// order the palette draws, from the same call. Only on the first page: page two
// of the news log is not a second chance to read the manual.
$mudlet_wiki = is_paged()
	? array( 'rows' => array() )
	: mudlet_wiki_search(
		get_search_query( false ),
		MUDLET_WIKI_SEARCH_PAGE_LIMIT,
		function_exists( 'mudlet_current_language_slug' ) ? mudlet_current_language_slug() : ''
	);
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
							<?php
							// With wiki rows below it, "Nothing matched that."
							// is standing over six things that did.
							if ( $mudlet_wiki['rows'] ) {
								esc_html_e( 'Nothing on the site matched that.', 'mudlet' );
							} else {
								esc_html_e( 'Nothing matched that.', 'mudlet' );
							}
							?>
							<a class="chip" href="<?php echo esc_url( mudlet_news_url() ); ?>"><?php esc_html_e( 'Browse the news', 'mudlet' ); ?></a>
						</p>
						<?php
					}

					if ( $mudlet_wiki['rows'] ) :
						?>
						<section class="wres">
							<h2 class="wres__h"><?php esc_html_e( 'From the wiki', 'mudlet' ); ?></h2>
							<?php foreach ( $mudlet_wiki['rows'] as $mudlet_hit ) : ?>
								<a class="wres__row" href="<?php echo esc_url( $mudlet_hit['url'] ); ?>" target="_blank" rel="noopener">
									<h3><?php echo esc_html( $mudlet_hit['title'] ); ?></h3>
									<?php if ( $mudlet_hit['snippet'] ) : ?>
										<p><?php echo esc_html( $mudlet_hit['snippet'] ); ?></p>
									<?php endif; ?>
								</a>
							<?php endforeach; ?>
							<p class="wres__all">
								<a class="chip" href="<?php echo esc_url( $mudlet_wiki['url'] ); ?>" target="_blank" rel="noopener">
									<?php esc_html_e( 'Search the wiki', 'mudlet' ); ?>
								</a>
							</p>
						</section>
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
