<?php
/**
 * "Hop in" — the closing call to action, in four directions.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="hopin">
	<div class="w">
		<div class="head">
			<p class="eyebrow"><?php esc_html_e( 'hop in', 'mudlet' ); ?><span><?php esc_html_e( 'four ways', 'mudlet' ); ?></span></p>
			<h2><?php esc_html_e( 'Hop in', 'mudlet' ); ?></h2>
			<p class="sub"><?php esc_html_e( 'However you want to start — playing, reading, asking, or building.', 'mudlet' ); ?></p>
		</div>

		<div class="hops">
			<div class="hop">
				<?php mudlet_icon( 'download' ); ?>
				<h3><?php esc_html_e( 'Get Mudlet', 'mudlet' ); ?></h3>
				<p><?php esc_html_e( 'Free, open source, and ready on Windows, macOS and Linux.', 'mudlet' ); ?></p>
				<a class="hop__go" href="<?php echo esc_url( mudlet_download_url() ); ?>"><?php esc_html_e( 'download', 'mudlet' ); ?></a>
			</div>

			<div class="hop">
				<?php mudlet_icon( 'book' ); ?>
				<h3><?php esc_html_e( 'Read the docs', 'mudlet' ); ?></h3>
				<p><?php esc_html_e( 'Mudlet’s documentation lives on the Mudlet Wiki. Translations and enhancements are welcome.', 'mudlet' ); ?></p>
				<a class="hop__go" href="https://wiki.mudlet.org/"><?php esc_html_e( 'visit the wiki', 'mudlet' ); ?></a>
			</div>

			<div class="hop">
				<?php mudlet_icon( 'chat' ); ?>
				<h3><?php esc_html_e( 'Find the others', 'mudlet' ); ?></h3>
				<p><?php esc_html_e( 'Join the community forum or the Discord server for sharing, developing, and getting support.', 'mudlet' ); ?></p>
				<a class="hop__go" href="https://forums.mudlet.org/"><?php esc_html_e( 'forum', 'mudlet' ); ?></a>
				<a class="hop__go" href="https://discord.gg/kuYvMQ9"><?php esc_html_e( 'discord', 'mudlet' ); ?></a>
			</div>

			<div class="hop">
				<?php mudlet_icon( 'github' ); ?>
				<h3><?php esc_html_e( 'Build it with us', 'mudlet' ); ?></h3>
				<p><?php esc_html_e( 'Mudlet’s source, issues and feature requests are on GitHub. Translations and enhancements are welcome.', 'mudlet' ); ?></p>
				<a class="hop__go" href="https://github.com/Mudlet/Mudlet"><?php esc_html_e( 'develop with us', 'mudlet' ); ?></a>
			</div>
		</div>
	</div>
</section>
