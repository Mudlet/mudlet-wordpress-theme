<?php
/**
 * The search palette and the site footer.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$mudlet_languages = mudlet_languages();

// The root takes ?s=; the language travels as a field, because that root is
// the same one for every language. See mudlet_search_action().
$mudlet_search_lang = mudlet_current_language_slug();
?>
	</div><!-- #content -->

	<dialog class="palette" aria-label="<?php esc_attr_e( 'Search Mudlet', 'mudlet' ); ?>">
		<form class="palette__in" role="search" method="get" action="<?php echo esc_url( mudlet_search_action() ); ?>">
			<?php mudlet_icon( 'search' ); ?>
			<input type="search" name="s" placeholder="<?php esc_attr_e( 'Search docs, forum and news', 'mudlet' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'mudlet' ); ?>" autocomplete="off" spellcheck="false">
			<?php if ( '' !== $mudlet_search_lang ) : ?>
				<input type="hidden" name="lang" value="<?php echo esc_attr( $mudlet_search_lang ); ?>">
			<?php endif; ?>
			<kbd>esc</kbd>
		</form>
		<ul class="palette__list"></ul>
		<p class="palette__empty" hidden><?php esc_html_e( 'No matches.', 'mudlet' ); ?></p>
		<div class="palette__foot">
			<span><kbd>&#8593;</kbd><kbd>&#8595;</kbd> <?php esc_html_e( 'navigate', 'mudlet' ); ?></span>
			<span><kbd>&#8629;</kbd> <?php esc_html_e( 'open', 'mudlet' ); ?></span>
			<span><kbd>ctrl</kbd><kbd>K</kbd> <?php esc_html_e( 'anywhere', 'mudlet' ); ?></span>
		</div>
	</dialog>

	<footer class="foot">
		<div class="w">
			<div class="foot__grid">
				<div class="foot__col" style="max-width:16rem">
					<span class="foot__brand">
						<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/mudlet_main_512x512.png' ); ?>" alt="" width="27" height="27">
						<span class="mk">&gt;</span><?php bloginfo( 'name' ); ?>
					</span>
					<p class="foot__blurb" style="margin:0"><?php bloginfo( 'description' ); ?></p>
				</div>

				<?php
				// These three columns are theme markup rather than menus on
				// purpose: every link carries its own icon, and a menu item has
				// nowhere to keep one. The Project column below is a real menu,
				// because those destinations are pages an editor owns.
				?>
				<div class="foot__col"><b><?php esc_html_e( 'Get Mudlet', 'mudlet' ); ?></b>
					<a href="<?php echo esc_url( mudlet_download_url() ); ?>"><?php mudlet_icon( 'download' ); ?><?php esc_html_e( 'Download', 'mudlet' ); ?></a>
					<a href="https://packages.mudlet.org/"><?php mudlet_icon( 'package' ); ?><?php esc_html_e( 'Packages', 'mudlet' ); ?></a>
					<a href="<?php echo esc_url( mudlet_page_url( 'media', '/media/' ) ); ?>"><?php mudlet_icon( 'image' ); ?><?php esc_html_e( 'Media', 'mudlet' ); ?></a>
				</div>

				<div class="foot__col"><b><?php esc_html_e( 'Learn', 'mudlet' ); ?></b>
					<a href="https://wiki.mudlet.org/w/Manual:Contents"><?php mudlet_icon( 'book' ); ?><?php esc_html_e( 'The Manual', 'mudlet' ); ?></a>
					<a href="https://wiki.mudlet.org"><?php mudlet_icon( 'wiki' ); ?><?php esc_html_e( 'Wiki', 'mudlet' ); ?></a>
					<a href="https://wiki.mudlet.org/w/Known_Issues"><?php mudlet_icon( 'warning' ); ?><?php esc_html_e( 'Known issues', 'mudlet' ); ?></a>
				</div>

				<div class="foot__col"><b><?php esc_html_e( 'Community', 'mudlet' ); ?></b>
					<a href="https://forums.mudlet.org"><?php mudlet_icon( 'chat' ); ?><?php esc_html_e( 'Forum', 'mudlet' ); ?></a>
					<a href="https://discord.gg/kuYvMQ9"><?php mudlet_icon( 'discord' ); ?><?php esc_html_e( 'Discord', 'mudlet' ); ?></a>
					<a href="https://github.com/Mudlet/Mudlet"><?php mudlet_icon( 'github' ); ?><?php esc_html_e( 'GitHub', 'mudlet' ); ?></a>
					<a href="<?php echo esc_url( mudlet_news_url() ); ?>"><?php mudlet_icon( 'rss' ); ?><?php esc_html_e( 'News', 'mudlet' ); ?></a>
				</div>

				<div class="foot__col"><b><?php esc_html_e( 'Project', 'mudlet' ); ?></b>
					<?php
					mudlet_nav_links(
						'footer-project',
						array(
							array( __( 'About Mudlet', 'mudlet' ), mudlet_page_url( 'about', '/about/' ) ),
							array( __( 'Vision', 'mudlet' ), mudlet_page_url( 'vision', '/about/vision/' ) ),
							array( __( 'The makers', 'mudlet' ), mudlet_page_url( 'the-makers', '/the-makers/' ) ),
							array( __( 'Contribute', 'mudlet' ), mudlet_page_url( 'contribute', '/contribute/' ) ),
							array( __( 'Contact us', 'mudlet' ), mudlet_page_url( 'contact', '/contact/' ) ),
						)
					);
					?>
				</div>
			</div>

			<div class="foot__end">
				<span><?php esc_html_e( 'Mudlet is free software, released under the GPL.', 'mudlet' ); ?></span>
				<a href="<?php echo esc_url( mudlet_page_url( 'privacy-policy', '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy', 'mudlet' ); ?></a>
				<a href="<?php echo esc_url( mudlet_page_url( 'terms-of-service', '/terms-of-service/' ) ); ?>"><?php esc_html_e( 'Terms', 'mudlet' ); ?></a>
				<?php if ( $mudlet_languages ) : ?>
					<nav class="langs" aria-label="<?php esc_attr_e( 'Language', 'mudlet' ); ?>">
						<?php foreach ( $mudlet_languages as $lang ) : ?>
							<a href="<?php echo esc_url( $lang['url'] ); ?>"<?php echo $lang['current'] ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $lang['code'] ); ?></a>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
			</div>
		</div>
	</footer>
</div><!-- #site -->

<?php wp_footer(); ?>
</body>
</html>
