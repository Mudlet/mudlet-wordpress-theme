<?php
/**
 * "What keeps people playing" - six cards, three screenshots, one spec line.
 *
 * This used to be a tab switcher whose every panel carried a 16:9 screenshot.
 * Only two of the six claims have a picture that fills a frame that size, so
 * four of them were showing a session shot that had nothing to do with what
 * they said. Now each card carries a small figure of its own (inc/front-art.php)
 * and the real screenshots are a row of thumbnails, where being cropped stops
 * mattering.
 *
 * The cards and the spec line are editable - see inc/front-content.php for the
 * shape and the defaults, and the front page's own edit screen for where. The
 * thumbnails are not: they are whatever is in /media/, shuffled.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$mudlet_cards = mudlet_front_cards();
$mudlet_shots = mudlet_front_thumbs( 3 );
?>
<section class="showcase">
	<div class="w">
		<?php
		/*
		 * The eyebrow is the heading. There was a "What keeps people playing"
		 * <h2> under it and it said nothing the eyebrow did not, but the
		 * section still needs a heading of its own or the six card <h3>s hang
		 * off whatever came before it. So the eyebrow carries the level, and
		 * keeps the look: #site .head .eyebrow outranks #site .head h2.
		 */
		?>
		<div class="head">
			<h2 class="eyebrow"><?php esc_html_e( 'why mudlet', 'mudlet' ); ?></h2>
		</div>

		<?php if ( $mudlet_cards ) : ?>
			<div class="cards">
				<?php foreach ( $mudlet_cards as $mudlet_card ) : ?>
					<div class="card">
						<?php mudlet_front_card_art( (string) ( $mudlet_card['art'] ?? '' ) ); ?>
						<div>
							<h3><?php echo esc_html( (string) ( $mudlet_card['title'] ?? '' ) ); ?></h3>
							<p><?php echo esc_html( (string) ( $mudlet_card['body'] ?? '' ) ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $mudlet_shots ) : ?>
			<?php
			/*
			 * The same .head/.eyebrow the section opens with, rather than a
			 * device of its own: the > mark, the rule under it and the
			 * right-hand note are all already that component's, and three
			 * near-identical prompts drawn three different ways is how they
			 * ended up at three different sizes and gaps.
			 */
			?>
			<div class="head">
				<p class="eyebrow">
					<?php esc_html_e( 'from the community', 'mudlet' ); ?>
					<span>
						<a href="<?php echo esc_url( mudlet_page_url( 'media', '/media/' ) ); ?>">
							<?php esc_html_e( 'all screenshots', 'mudlet' ); ?> &rarr;
						</a>
					</span>
				</p>
			</div>

			<div class="shots">
				<?php
				/*
				 * No caption. Which game a screenshot is from is a caption's
				 * job on /media/, where somebody is browsing them; here the row
				 * is showing what Mudlet looks like in other people's hands,
				 * and three game names under three pictures is a line of text
				 * nobody reads under a thing that speaks for itself. The name
				 * still reaches a screen reader through the image's alt.
				 */
				foreach ( $mudlet_shots as $mudlet_shot ) :
					$mudlet_full = wp_get_attachment_image_url( $mudlet_shot, 'full' );
					if ( ! $mudlet_full ) {
						continue;
					}
					// The image is the link's only content, so an empty alt
					// leaves a link with no name. Not every shot in /media/
					// has one; the caption, then a plain description, stands in.
					$mudlet_alt = trim( (string) get_post_meta( $mudlet_shot, '_wp_attachment_image_alt', true ) );
					if ( '' === $mudlet_alt ) {
						$mudlet_alt = trim( wp_strip_all_tags( wp_get_attachment_caption( $mudlet_shot ) ) );
					}
					if ( '' === $mudlet_alt ) {
						$mudlet_alt = __( 'A screenshot of Mudlet in use', 'mudlet' );
					}
					?>
					<a class="shot" href="<?php echo esc_url( $mudlet_full ); ?>">
						<?php
						echo wp_get_attachment_image(
							$mudlet_shot,
							'medium_large',
							false,
							array(
								'alt'      => $mudlet_alt,
								'loading'  => 'lazy',
								'decoding' => 'async',
							)
						);
						?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

	</div>
</section>
