<?php
/**
 * The document head and the site header.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$mudlet_languages = mudlet_languages();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip" href="#content"><?php esc_html_e( 'Skip to content', 'mudlet' ); ?></a>

<?php
// data-page is what the stylesheet routes on. See mudlet_page_kind().
?>
<div id="site" data-page="<?php echo esc_attr( mudlet_page_kind() ); ?>">
	<header class="top">
		<div class="w">
			<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/mudlet_main_512x512.png' ); ?>" alt="" width="27" height="27">
				<span class="mk">&gt;</span><?php bloginfo( 'name' ); ?>
			</a>

			<nav class="topnav">
				<?php
				// Places to read. Download is deliberately not among them: the
				// button on the right already is the download, and two controls
				// pointing at the same page half a centimetre apart read as one
				// control drawn twice.
				mudlet_nav_links(
					'primary',
					array(
						array( __( 'News', 'mudlet' ), mudlet_news_url() ),
						array( __( 'Gallery', 'mudlet' ), mudlet_page_url( 'media', '/media/' ), 'lo' ),
						array( __( 'Docs', 'mudlet' ), 'https://wiki.mudlet.org', 'lo' ),
						array( __( 'Forum', 'mudlet' ), 'https://forums.mudlet.org', 'lo' ),
					)
				);
				?>
			</nav>

			<div class="util">
				<button class="searchbtn" type="button" aria-label="<?php esc_attr_e( 'Search', 'mudlet' ); ?>" aria-keyshortcuts="/">
					<?php mudlet_icon( 'search' ); ?>
				</button>

				<button class="theme" type="button" aria-label="<?php esc_attr_e( 'Switch to dark theme', 'mudlet' ); ?>" aria-pressed="false">
					<?php
					mudlet_icon( 'moon', 'moon' );
					mudlet_icon( 'sun', 'sun' );
					?>
				</button>

				<?php if ( $mudlet_languages ) : ?>
					<div class="lang">
						<button class="lang__btn" aria-expanded="false" aria-haspopup="true" aria-label="<?php esc_attr_e( 'Change language', 'mudlet' ); ?>">
							<?php mudlet_icon( 'globe', 'glb' ); ?>
							<?php echo esc_html( mudlet_current_language_code() ); ?>
							<?php mudlet_icon( 'caret', 'crt' ); ?>
						</button>
						<ul class="lang__menu" hidden>
							<?php foreach ( $mudlet_languages as $lang ) : ?>
								<li>
									<a href="<?php echo esc_url( $lang['url'] ); ?>"<?php echo $lang['current'] ? ' aria-current="true"' : ''; ?>>
										<?php echo esc_html( $lang['name'] ); ?><code><?php echo esc_html( $lang['code'] ); ?></code>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<a class="btn" href="<?php echo esc_url( mudlet_download_url() ); ?>"><?php esc_html_e( 'Get Mudlet', 'mudlet' ); ?></a>
			</div>
		</div>
	</header>

	<div id="content">
