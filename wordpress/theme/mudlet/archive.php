<?php
/**
 * Category, year, month, author — every list of posts that is not the index.
 *
 * The index (home.php) leads with a featured post; an archive does not. Beyond
 * that they are the same page, so this reuses its parts.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="page page--news">
	<section class="archive">
		<div class="w">
			<div class="head">
				<p class="eyebrow"><?php esc_html_e( 'news', 'mudlet' ); ?><span>
					<?php
					printf(
						/* translators: %s: number of posts in this archive */
						esc_html__( '%s posts', 'mudlet' ),
						esc_html( number_format_i18n( (int) $GLOBALS['wp_query']->found_posts ) )
					);
					?>
				</span></p>
				<h2><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h2>
				<?php the_archive_description( '<p class="sub">', '</p>' ); ?>
			</div>

			<div class="arch">
				<?php get_template_part( 'template-parts/news/filters' ); ?>

				<div class="plist">
					<?php
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

					// See home.php: have_posts() is false here on every page.
					if ( 0 === (int) $GLOBALS['wp_query']->post_count ) :
						?>
						<p class="pempty">
							<?php esc_html_e( 'Nothing here.', 'mudlet' ); ?>
							<a class="chip" href="<?php echo esc_url( mudlet_news_url() ); ?>"><?php esc_html_e( 'Show all posts', 'mudlet' ); ?></a>
						</p>
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
