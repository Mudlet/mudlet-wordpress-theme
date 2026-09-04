<?php
/**
 * The card grid behind the mudlet/games block.
 *
 * The block itself belongs to the Mudlet Games plugin - a post's body must keep
 * working through a theme rewrite - and it calls this when the theme provides
 * it, so the plugin decides *which* games and this decides what one looks like.
 *
 * Not the .pcard from the front page's grid. That card is a logo, a name and a
 * hostname packed fifteen to a screen, and it has nowhere to put the sentence
 * that makes a reader want to try the game. This one is wider, carries the
 * blurb, and comes two to a row - which is the shape Mudlet's own 5.0
 * announcement reached for when it introduced four new worlds.
 *
 * The blurb is the game's stored description, trimmed. Not something written
 * into the post: a card that repeats a record is a card that disagrees with it
 * a year later.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$mudlet_games   = isset( $args['games'] ) && is_array( $args['games'] ) ? $args['games'] : array();
$mudlet_wrapper = isset( $args['wrapper'] ) ? (string) $args['wrapper'] : 'class="rgames"';

if ( ! $mudlet_games ) {
	return;
}
?>
<div <?php echo $mudlet_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?>>
	<?php
	foreach ( $mudlet_games as $mudlet_game ) :
		// A description is post_content and can run to several paragraphs; the
		// first is the one written as an introduction, and it is printed whole.
		//
		// It used to be cut at 38 words with an ellipsis, which was backwards:
		// the blurb is the reason this card exists rather than a logo tile, and
		// four of them ended mid-sentence on "Every expedition into…". A row of
		// cards is as tall as its tallest card either way; the difference is
		// only whether the sentence finishes. Through mudlet_game_lede() rather
		// than a second paragraph-splitter, so the block and /games/ never
		// disagree about where a blurb starts.
		$mudlet_blurb = function_exists( 'mudlet_game_lede' )
			? mudlet_game_lede( $mudlet_game )
			: trim( wp_strip_all_tags( (string) ( $mudlet_game['description'] ?? '' ) ) );
		?>
		<article class="rgame">
			<?php if ( ! empty( $mudlet_game['icon_url'] ) ) : ?>
				<span class="rgame__logo">
					<img src="<?php echo esc_url( (string) $mudlet_game['icon_url'] ); ?>" alt="" loading="lazy" decoding="async">
				</span>
			<?php endif; ?>

			<h3 class="rgame__name">
				<a href="<?php echo esc_url( (string) $mudlet_game['url'] ); ?>"><?php echo esc_html( (string) $mudlet_game['name'] ); ?></a>
			</h3>

			<?php if ( '' !== $mudlet_blurb ) : ?>
				<p class="rgame__blurb"><?php echo esc_html( $mudlet_blurb ); ?></p>
			<?php endif; ?>

			<p class="rgame__links">
				<?php
				// "Play in Mudlet" now means it. Mudlet registers itself for telnet://
				// and telnets://, so this hands the client a host and a port and it
				// comes up connected - and where there is no address worth linking
				// (mudlet_game_telnet_url() returns '' for those) the link falls back
				// to the game's own page, which is what it always was.
				$mudlet_telnet = function_exists( 'mudlet_game_telnet_url' ) ? mudlet_game_telnet_url( $mudlet_game ) : '';
				// And its other half: the same profile in a browser tab, for a reader
				// who does not have Mudlet yet. Announcement posts are where people
				// meet a new world first, so "try it" wanting no download is the point.
				$mudlet_web = function_exists( 'mudlet_game_web_url' ) ? mudlet_game_web_url( $mudlet_game ) : '';
				?>
				<?php if ( $mudlet_telnet ) : ?>
					<a class="rgame__play" href="<?php echo esc_url( $mudlet_telnet, mudlet_telnet_protocols() ); ?>"><?php esc_html_e( 'Play in Mudlet', 'mudlet' ); ?></a>
					<span class="dot" aria-hidden="true">·</span>
					<a href="<?php echo esc_url( (string) $mudlet_game['url'] ); ?>"><?php esc_html_e( 'About', 'mudlet' ); ?></a>
				<?php else : ?>
					<a href="<?php echo esc_url( (string) $mudlet_game['url'] ); ?>"><?php esc_html_e( 'Play in Mudlet', 'mudlet' ); ?></a>
				<?php endif; ?>
				<?php if ( $mudlet_web ) : ?>
					<span class="dot" aria-hidden="true">·</span>
					<a href="<?php echo esc_url( $mudlet_web ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Play in a browser', 'mudlet' ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $mudlet_game['site'] ) ) : ?>
					<span class="dot" aria-hidden="true">·</span>
					<a href="<?php echo esc_url( (string) $mudlet_game['site'] ); ?>" target="_blank" rel="external nofollow noopener"><?php echo esc_html( (string) ( $mudlet_game['domain'] ?: $mudlet_game['site'] ) ); ?></a>
				<?php endif; ?>
			</p>
		</article>
	<?php endforeach; ?>
</div>
