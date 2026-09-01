<?php
/**
 * Not found.
 *
 * Written as a room description, because that is the vocabulary the rest of the
 * site is already speaking.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="page page--page">
	<section class="sec">
		<div class="w">
			<div class="head">
				<p class="eyebrow"><?php esc_html_e( 'error 404', 'mudlet' ); ?></p>
				<h2><?php esc_html_e( 'You cannot go that way.', 'mudlet' ); ?></h2>
				<p class="sub"><?php esc_html_e( 'There is nothing at this address. It may have moved, or it may never have been here.', 'mudlet' ); ?></p>
			</div>
			<div class="cta">
				<a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go home', 'mudlet' ); ?></a>
				<a class="btn btn--ghost" href="<?php echo esc_url( mudlet_news_url() ); ?>"><?php esc_html_e( 'Read the news', 'mudlet' ); ?></a>
				<a class="btn btn--ghost" href="<?php echo esc_url( mudlet_download_url() ); ?>"><?php esc_html_e( 'Download Mudlet', 'mudlet' ); ?></a>
			</div>
		</div>
	</section>
</div>

<?php
get_footer();
