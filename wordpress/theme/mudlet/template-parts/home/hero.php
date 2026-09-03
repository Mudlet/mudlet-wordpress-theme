<?php
/**
 * The hero, with the embedded Mudlet Web session.
 *
 * The terminal below is a scripted stand-in - hand-written lines mimicking the
 * room the demo world opens in. theme.js drops a real same-origin iframe
 * underneath it and lifts the cover once the client reports that it has
 * printed. Until then, or forever if the frame never answers, this is what a
 * visitor sees.
 *
 * If the demo world's opening room changes, the copy here has to change with
 * it, or the swap becomes a visible edit rather than a client connecting.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="hero">
	<div class="w">
		<div>
			<h1>
				<?php
				printf(
					/* translators: %s: the words "pure-text", emphasised. Keep the markup. */
					esc_html__( 'Play immersive, multiplayer, %s games on Mudlet.', 'mudlet' ),
					'<em>' . esc_html__( 'pure-text', 'mudlet' ) . '</em>'
				);
				?>
			</h1>
			<p class="lead"><?php esc_html_e( 'Mudlet is a free, open-source MUD client. Fast out of the box, and rebuildable down to the last pixel with Lua.', 'mudlet' ); ?></p>
			<div class="cta">
				<a class="btn btn--xl" href="<?php echo esc_url( mudlet_download_url() ); ?>"><?php esc_html_e( 'Download Mudlet', 'mudlet' ); ?></a>
				<a class="btn btn--ghost" href="<?php echo esc_url( mudlet_page_url( 'media', '/media/' ) ); ?>"><?php esc_html_e( 'See it in action', 'mudlet' ); ?></a>
			</div>
			<p class="plat">
				<?php
				printf(
					/* translators: %s: link reading "all downloads" */
					esc_html__( 'Windows · macOS · Linux · %s', 'mudlet' ),
					'<a href="' . esc_url( mudlet_download_url() ) . '">' . esc_html__( 'all downloads', 'mudlet' ) . '</a>'
				);
				?>
			</p>
		</div>

		<div class="heroterm" id="term">
			<div class="term__bar">
				<span class="term__dot" aria-hidden="true"></span><span class="term__dot" aria-hidden="true"></span><span class="term__dot" aria-hidden="true"></span>
				<span class="term__name" aria-hidden="true"><?php esc_html_e( 'mudlet — the front page', 'mudlet' ); ?></span>
				<button class="term__grow" type="button" aria-expanded="false">
					<?php mudlet_icon( 'expand' ); ?>
					<span class="vh"><?php esc_html_e( 'Expand the demo', 'mudlet' ); ?></span>
				</button>
			</div>

			<div class="term__stage">
				<div class="term__body">
					<p class="ln ln--sys step">*** <?php esc_html_e( 'connected', 'mudlet' ); ?> ***</p>
					<p class="ln ln--gap step" aria-hidden="true"></p>
					<p class="ln ln--room step"><?php esc_html_e( 'The Front Page', 'mudlet' ); ?></p>
					<p class="ln ln--desc step"><?php esc_html_e( 'A wide room under a banner in letters the colour of a struck match: play immersive, multiplayer, pure-text games. On a plinth in the centre, a terminal running a small MUD.', 'mudlet' ); ?></p>
					<p class="ln ln--gap step" aria-hidden="true"></p>
					<p class="ln ln--exits step"><?php esc_html_e( 'Exits: down, east, north, west', 'mudlet' ); ?></p>
					<p class="ln ln--gap step" aria-hidden="true"></p>
					<p class="ln ln--in step"><?php esc_html_e( 'look banner', 'mudlet' ); ?></p>
					<p class="ln ln--desc step"><?php esc_html_e( 'It says what the real front page says: the games are text, the text is multiplayer, and forty years in, that is still enough.', 'mudlet' ); ?><span class="caret" aria-hidden="true"></span></p>
				</div>

				<!-- shown instead of the scripted session while the client loads -->
				<div class="term__boot" aria-hidden="true">
					<p class="ln ln--boot">mudlet web</p>
					<p class="ln">&nbsp;</p>
					<p class="ln ln--boot"><?php esc_html_e( 'connecting', 'mudlet' ); ?><span class="dots"><i>.</i><i>.</i><i>.</i></span><span class="caret"></span></p>
				</div>

				<div class="term__cmd" aria-hidden="true">
					<span class="term__cmdbox"><?php esc_html_e( 'Enter command…', 'mudlet' ); ?></span>
					<span class="term__cmdbtn"><?php esc_html_e( 'Send', 'mudlet' ); ?></span>
				</div>

				<!-- theme.js appends the iframe here, after load, in idle time -->
				<div class="herolive"></div>
			</div>
		</div>
	</div>
</section>
