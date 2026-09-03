<?php
/**
 * One maker.
 *
 * A maker post is not a news post — no category pill, no release panel, no
 * author byline — so it does not go through single.php, which assumes all
 * three.
 *
 * It exists so a person can be linked to by name: from a release post thanking
 * them, from the wiki, from a forum reply. Short on purpose. Everything on it
 * comes from Mudlet's About dialog, and there is nothing here the project would
 * not already say about them in the client.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$mudlet_maker = mudlet_maker( get_post() );
	?>

	<div class="page page--page">
		<section class="sec">
			<div class="w">
				<p class="crumbs">
					<a href="<?php echo esc_url( mudlet_makers_page_url() ); ?>"><?php esc_html_e( 'The makers', 'mudlet' ); ?></a><span class="sep">/</span>
					<span class="here"><?php the_title(); ?></span>
				</p>

				<div class="head">
					<span class="mkface mkface--big">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'medium', array( 'alt' => '' ) ); ?>
						<?php else : ?>
							<span class="mkinit" aria-hidden="true"><?php echo esc_html( (string) ( $mudlet_maker['initials'] ?? '' ) ); ?></span>
						<?php endif; ?>
					</span>
					<h2><?php the_title(); ?></h2>
					<p class="sub">
						<?php
						echo esc_html(
							! empty( $mudlet_maker['core'] )
								? __( 'Core developer', 'mudlet' )
								: __( 'Has contributed to Mudlet.', 'mudlet' )
						);
						?>
					</p>
				</div>

				<div class="prose">
					<?php the_content(); ?>
				</div>

				<?php if ( $mudlet_maker && ( $mudlet_maker['github'] || $mudlet_maker['discord'] ) ) : ?>
					<p class="specs">
						<span class="mk">&gt;</span><b><?php esc_html_e( 'find them', 'mudlet' ); ?></b>
						<?php if ( $mudlet_maker['github'] ) : ?>
							<a href="<?php echo esc_url( $mudlet_maker['github_url'] ); ?>" target="_blank" rel="external nofollow noopener"><?php echo esc_html( $mudlet_maker['github'] ); ?></a>
						<?php endif; ?>
						<?php if ( $mudlet_maker['github'] && $mudlet_maker['discord'] ) : ?>
							<span class="sep">&middot;</span>
						<?php endif; ?>
						<?php if ( $mudlet_maker['discord'] ) : ?>
							<?php
							printf(
								/* translators: %s: a Discord handle */
								esc_html__( 'discord %s', 'mudlet' ),
								esc_html( $mudlet_maker['discord'] )
							);
							?>
						<?php endif; ?>
					</p>
				<?php endif; ?>

				<div class="cta" style="margin-top:2rem">
					<a class="btn" href="<?php echo esc_url( mudlet_page_url( 'contribute', '/contribute/' ) ); ?>"><?php esc_html_e( 'Help build it', 'mudlet' ); ?></a>
					<a class="btn btn--ghost" href="<?php echo esc_url( mudlet_makers_page_url() ); ?>"><?php esc_html_e( 'All the makers', 'mudlet' ); ?></a>
				</div>
			</div>
		</section>
	</div>

	<?php
endwhile;

get_footer();
