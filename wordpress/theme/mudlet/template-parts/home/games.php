<?php
/**
 * The bundled-games grid.
 *
 * The list is owned by the client, not by an editor: it is read from Mudlet's
 * own src/TGameDetails.h by the Mudlet Games plugin, one post per game. The
 * theme asks inc/games.php for a random fifteen and draws them.
 *
 * No plugin, no games: the section is skipped rather than filled with a list
 * typed into the theme, which is the thing the plugin exists to replace. See
 * inc/games.php.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$mudlet_shown = 15;
$mudlet_games = mudlet_home_games( $mudlet_shown );

if ( ! $mudlet_games ) {
	return;
}

$mudlet_total = mudlet_game_count();
$mudlet_rest  = max( 0, $mudlet_total - count( $mudlet_games ) );
?>
<section class="games" style="padding-top:0">
	<div class="w">
		<div class="head">
			<p class="eyebrow"><?php esc_html_e( 'games', 'mudlet' ); ?><span>
				<?php
				printf(
					/* translators: %s: number of bundled connection profiles */
					esc_html__( '%s bundled', 'mudlet' ),
					esc_html( number_format_i18n( $mudlet_total ) )
				);
				?>
			</span></p>
			<h2><?php esc_html_e( '40+ games, ready to play.', 'mudlet' ); ?></h2>
			<p class="sub">
				<?php
				printf(
					/* translators: %s: number of bundled connection profiles */
					esc_html__( 'Mudlet ships connection profiles for %s MUDs. Pick one from the list and you are in — no host, no port, no fiddling. Adding your own takes about thirty seconds.', 'mudlet' ),
					esc_html( number_format_i18n( $mudlet_total ) )
				);
				?>
			</p>
		</div>

		<div class="pgrid">
			<?php foreach ( $mudlet_games as $mudlet_game ) : ?>
				<a class="pcard" href="<?php echo esc_url( $mudlet_game['url'] ); ?>">
					<span class="plogo">
						<?php if ( $mudlet_game['icon_url'] ) : ?>
							<img src="<?php echo esc_url( $mudlet_game['icon_url'] ); ?>" alt="" loading="lazy" decoding="async">
						<?php endif; ?>
					</span>
					<b><?php echo esc_html( $mudlet_game['name'] ); ?></b>
					<span><?php echo esc_html( $mudlet_game['domain'] ); ?></span>
				</a>
			<?php endforeach; ?>

			<?php if ( $mudlet_rest > 0 ) : ?>
				<a class="pcard pmore" href="<?php echo esc_url( mudlet_games_more_url() ); ?>">
					<b>+<?php echo esc_html( number_format_i18n( $mudlet_rest ) ); ?></b>
					<span><?php esc_html_e( 'more bundled with Mudlet', 'mudlet' ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</div>
</section>
