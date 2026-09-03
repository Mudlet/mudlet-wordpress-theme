<?php
/**
 * Template Name: The makers
 *
 * /the-makers/ — the people who make Mudlet.
 *
 * The page's own prose stays editable in wp-admin: what the project is, how to
 * join, whatever the site wants to say. The roster under it is not editable and
 * is not typed — it is Mudlet's own About dialog, read by the Mudlet Makers
 * plugin. See inc/makers.php.
 *
 * Why a page template and not a post-type archive: /the-makers/ is a page in
 * the menu with paragraphs on it, and registering an archive on the same path
 * would have the post type quietly take it over. The makers still have their
 * own URLs — /the-makers/<name>/ — they just do not own the index.
 *
 * The page has two editable regions, and the edit screen says so: the body,
 * which renders above the roster, and an **Also credited** editor below it,
 * which renders under the roster. See mudlet_makers_editor() in inc/makers.php.
 *
 * That second field is not decoration — it is where people the client's credits
 * do not carry go. Mudlet's About dialog has never listed Nickpick, xtian or
 * Larkin, three names the live page credits; the durable fix is to add them to
 * dlgAboutDialog.cpp, but a page that cannot name anybody the client forgot is a
 * page that quietly drops them.
 *
 * Nothing in it ships from this repo. It is written by hand in wp-admin and it
 * survives every sync — sync writes maker posts and never touches pages.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();

$mudlet_core       = mudlet_core_makers();
$mudlet_past       = mudlet_past_makers();
$mudlet_notes      = function_exists( 'mudlet_makers_acknowledgements' ) ? mudlet_makers_acknowledgements() : array();
$mudlet_supporters = function_exists( 'mudlet_makers_supporters' ) ? mudlet_makers_supporters() : array();
$mudlet_said       = (string) ( $mudlet_supporters['intro'] ?? '' );
$mudlet_patrons    = array_merge(
	(array) ( $mudlet_supporters['mightier_than_swords'] ?? array() ),
	(array) ( $mudlet_supporters['on_a_plaque'] ?? array() )
);

while ( have_posts() ) :
	the_post();

	$mudlet_extra = mudlet_makers_extra( get_the_ID() );
	?>
	<div class="page page--page">
		<section class="sec">
			<div class="w">
				<div class="head">
					<p class="eyebrow"><?php esc_html_e( 'the makers', 'mudlet' ); ?><?php if ( mudlet_maker_count() ) : ?><span>
						<?php
						printf(
							/* translators: %s: number of people credited in Mudlet */
							esc_html__( '%s credited', 'mudlet' ),
							esc_html( number_format_i18n( mudlet_maker_count() ) )
						);
						?>
					</span><?php endif; ?></p>
					<h2><?php the_title(); ?></h2>
					<?php if ( has_excerpt() ) : ?>
						<p class="sub"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<?php endif; ?>
				</div>

				<div class="prose">
					<?php the_content(); ?>
				</div>

				<?php if ( $mudlet_core ) : ?>
					<div class="head head--tight">
						<h3><?php esc_html_e( 'Core developers', 'mudlet' ); ?></h3>
					</div>

					<div class="mkgrid">
						<?php foreach ( $mudlet_core as $mudlet_maker ) : ?>
							<?php mudlet_maker_card( $mudlet_maker ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $mudlet_past ) : ?>
					<div class="head head--tight">
						<h3><?php esc_html_e( 'And everyone who built it with them', 'mudlet' ); ?></h3>
						<p class="sub">
							<?php
							esc_html_e(
								'Mappers, installers, translators, the Lua API, the mac build, the logo. Some of this work is fifteen years old and all of it is still in the client.',
								'mudlet'
							);
							?>
						</p>
					</div>

					<div class="mkgrid mkgrid--past">
						<?php foreach ( $mudlet_past as $mudlet_maker ) : ?>
							<?php mudlet_maker_card( $mudlet_maker ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $mudlet_extra ) : ?>
					<div class="prose mktail">
						<?php echo wp_kses_post( wpautop( $mudlet_extra ) ); ?>
					</div>
				<?php endif; ?>

				<?php if ( ! mudlet_has_maker_data() ) : ?>
					<p class="sub">
						<?php
						printf(
							/* translators: %s: link to the GitHub contributors graph */
							esc_html__( 'Everyone who has contributed to Mudlet is listed on %s.', 'mudlet' ),
							'<a href="' . esc_url( MUDLET_CONTRIBUTORS_URL ) . '" target="_blank" rel="external noopener">' . esc_html__( 'the contributors graph', 'mudlet' ) . '</a>'
						);
						?>
					</p>
				<?php endif; ?>

				<?php if ( $mudlet_notes ) : ?>
					<div class="prose mknotes">
						<?php foreach ( $mudlet_notes as $mudlet_note ) : ?>
							<p><?php echo wp_kses_post( $mudlet_note ); ?></p>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $mudlet_patrons ) : ?>
					<?php if ( '' !== $mudlet_said ) : ?>
						<div class="head head--tight">
							<?php // Upstream's sentence, link and all. The client paints these names onto sword frames and plaques; a web page has neither, so this is the framing. ?>
							<p class="sub"><?php echo wp_kses_post( $mudlet_said ); ?></p>
						</div>
					<?php endif; ?>

					<p class="mkpatrons">
						<?php foreach ( $mudlet_patrons as $mudlet_i => $mudlet_patron ) : ?>
							<?php echo $mudlet_i ? '<span class="sep">&middot;</span>' : ''; ?>
							<span><?php echo esc_html( $mudlet_patron ); ?></span>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>

				<div class="cta" style="margin-top:2rem">
					<a class="btn" href="<?php echo esc_url( mudlet_page_url( 'contribute', '/contribute/' ) ); ?>"><?php esc_html_e( 'Help build it', 'mudlet' ); ?></a>
					<a class="btn btn--ghost" href="<?php echo esc_url( MUDLET_CONTRIBUTORS_URL ); ?>" target="_blank" rel="external noopener"><?php esc_html_e( 'Everyone on GitHub', 'mudlet' ); ?></a>
				</div>
			</div>
		</section>
	</div>
	<?php
endwhile;

get_footer();
