<?php
/**
 * One game.
 *
 * A game post is not a news post — no category pill, no release panel, no
 * author — so it does not go through single.php, which assumes all three.
 *
 * The connection details are printed plainly because they are the useful part:
 * somebody who already has Mudlet wants the host and port, and somebody who
 * does not wants the download button under them.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$mudlet_game = mudlet_game( get_post() );
	?>

	<div class="page page--page">
		<section class="sec">
			<div class="w">
				<p class="crumbs">
					<a href="<?php echo esc_url( mudlet_games_url() ); ?>"><?php esc_html_e( 'Games', 'mudlet' ); ?></a><span class="sep">/</span>
					<span class="here"><?php the_title(); ?></span>
				</p>

				<div class="head">
					<?php if ( has_post_thumbnail() ) : ?>
						<span class="plogo" style="display:inline-grid;margin-bottom:1rem">
							<?php the_post_thumbnail( 'medium', array( 'alt' => '' ) ); ?>
						</span>
					<?php endif; ?>
					<h2><?php the_title(); ?></h2>
					<?php if ( ! empty( $mudlet_game['domain'] ) ) : ?>
						<p class="sub">
							<?php if ( ! empty( $mudlet_game['site'] ) ) : ?>
								<a href="<?php echo esc_url( $mudlet_game['site'] ); ?>" target="_blank" rel="external nofollow noopener"><?php echo esc_html( $mudlet_game['domain'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $mudlet_game['domain'] ); ?>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>

				<div class="prose">
					<?php the_content(); ?>
				</div>

				<?php if ( $mudlet_game ) : ?>
					<?php
					// Mudlet registers itself for telnet:// and telnets://, so the
					// address is not just something to copy into the profile dialog -
					// clicking it opens the client already connected. See
					// mudlet_game_telnet_url(), which returns '' where that would be a
					// lie and leaves the line as the plain text it has always been.
					$mudlet_telnet = mudlet_game_telnet_url( $mudlet_game );
					?>
					<p class="specs">
						<span class="mk">&gt;</span><b><?php esc_html_e( 'connect', 'mudlet' ); ?></b>
						<?php if ( $mudlet_telnet ) : ?>
							<a class="gplay" href="<?php echo esc_url( $mudlet_telnet, mudlet_telnet_protocols() ); ?>"><span class="gplay__addr"><?php
								echo esc_html( $mudlet_game['host'] );
								?><span class="sep">&middot;</span><?php
								printf(
									/* translators: %s: TCP port number */
									esc_html__( 'port %s', 'mudlet' ),
									esc_html( (string) (int) $mudlet_game['port'] )
								);
							?></span></a>
							<span class="gplay__hint"><?php esc_html_e( 'opens Mudlet', 'mudlet' ); ?></span>
						<?php else : ?>
							<?php echo esc_html( $mudlet_game['host'] ); ?><span class="sep">&middot;</span>
							<?php
							printf(
								/* translators: %s: TCP port number */
								esc_html__( 'port %s', 'mudlet' ),
								esc_html( (string) (int) $mudlet_game['port'] )
							);
							?>
						<?php endif; ?>
						<?php // The same two glyphs the listing's chips carry, so the flag a card showed is recognisable on the page it links to. ?>
						<?php if ( $mudlet_game['tls'] ) : ?>
							<span class="sep">&middot;</span><?php mudlet_icon( mudlet_game_tag_icon( 'secure' ), 'specs__i' ); ?><?php esc_html_e( 'secure connection', 'mudlet' ); ?>
						<?php endif; ?>
						<?php if ( $mudlet_game['own_ui'] ) : ?>
							<span class="sep">&middot;</span><?php mudlet_icon( mudlet_game_tag_icon( 'own-ui' ), 'specs__i' ); ?><?php esc_html_e( 'ships its own Mudlet interface', 'mudlet' ); ?>
						<?php endif; ?>
						<?php
						// The other half of that link, on the same rule rather than one of
						// its own: connecting is one subject, and the two rows differ only in
						// which client answers. Mudlet Web opens this same bundled profile in
						// a tab, which is the useful answer for somebody who has not yet
						// decided whether they like MUDs enough to install anything. Returns
						// '' for the same games the telnet:// line does. See mudlet_game_web_url().
						$mudlet_web = mudlet_game_web_url( $mudlet_game );
						?>
						<?php if ( $mudlet_web ) : ?>
							<br>
							<span class="mk">&gt;</span><b><?php esc_html_e( 'browser', 'mudlet' ); ?></b>
							<a class="gplay" href="<?php echo esc_url( $mudlet_web ); ?>" target="_blank" rel="noopener"><span class="gplay__addr"><?php esc_html_e( 'play it in Mudlet Web', 'mudlet' ); ?></span></a>
							<span class="gplay__hint"><?php esc_html_e( 'nothing to install', 'mudlet' ); ?></span>
						<?php endif; ?>
					</p>

					<?php if ( ! empty( $mudlet_game['links'] ) ) : ?>
						<p class="specs">
							<span class="mk">&gt;</span><b><?php esc_html_e( 'links', 'mudlet' ); ?></b>
							<?php
							// Upstream's own label is printed here rather than the one
							// name the listing groups these under - there is room for
							// "Discord Server" on a page - but the glyph is the listing's,
							// off the same classification. See mudlet_game_link_kind().
							foreach ( $mudlet_game['links'] as $mudlet_i => $mudlet_link ) :
								$mudlet_link_icon = mudlet_game_tag_icon(
									mudlet_game_link_kind( (string) $mudlet_link['url'], (string) $mudlet_link['label'] )
								);
								?>
								<?php echo $mudlet_i ? '<span class="sep">&middot;</span>' : ''; ?>
								<?php // Beside the link rather than inside it: the underline would otherwise run under the glyph and read as a strike through it. ?>
								<?php mudlet_icon( $mudlet_link_icon, 'specs__i' ); ?><a href="<?php echo esc_url( $mudlet_link['url'] ); ?>" target="_blank" rel="external nofollow noopener"><?php echo esc_html( $mudlet_link['label'] ); ?></a>
							<?php endforeach; ?>
						</p>
					<?php endif; ?>
				<?php endif; ?>

				<div class="cta" style="margin-top:2rem">
					<a class="btn" href="<?php echo esc_url( mudlet_download_url() ); ?>"><?php esc_html_e( 'Download Mudlet', 'mudlet' ); ?></a>
					<a class="btn btn--ghost" href="<?php echo esc_url( mudlet_games_url() ); ?>"><?php esc_html_e( 'All bundled games', 'mudlet' ); ?></a>
				</div>
			</div>
		</section>
	</div>

	<?php
endwhile;

get_footer();
