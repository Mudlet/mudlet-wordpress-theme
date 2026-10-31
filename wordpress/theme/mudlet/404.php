<?php
/**
 * Not found.
 *
 * Written as a room, because that is the vocabulary the rest of the site is
 * already speaking - and since it is a room, it can be typed into. The prompt
 * is the site's search form: without the script, whatever is typed is searched
 * for, which is the most useful thing a 404 can do with a word. With it,
 * theme.js answers the verbs first - look, help, a direction - and only offers
 * the search for what it does not know.
 *
 * Every word the room says is here rather than in the script, as `<template>`s
 * the script clones, so the replies are translated with the rest of the theme
 * and the script knows the verbs and nothing else. The exits are real links:
 * walking is navigating, and a visitor who never types still has five ways out.
 *
 * The room is the demo world's own phrase for a direction that leads nowhere -
 * "Nothing that way but page margin." - given a square of its own.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

// The address that was asked for, for the signpost. Path only - a query string
// is somebody's search or tracking, and the sign has no business repeating it.
$mudlet_lost_path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ), PHP_URL_PATH );
$mudlet_lost_path = mb_strimwidth( '' !== $mudlet_lost_path ? $mudlet_lost_path : '/', 0, 64, '…' );

// Up is home, and the four compass points are the four places the demo world
// puts the same pages, near enough. Games only while the plugin answers for
// them; makers otherwise, so the west wall is never a door into another 404.
$mudlet_lost_exits = array(
	'down'  => array( mudlet_download_url(), __( 'the downloads', 'mudlet' ) ),
	'east'  => array( mudlet_page_url( 'media', '/media/' ), __( 'the screenshots', 'mudlet' ) ),
	'north' => array( mudlet_news_url(), __( 'the news', 'mudlet' ) ),
	'up'    => array( home_url( '/' ), __( 'the front page', 'mudlet' ) ),
	'west'  => function_exists( 'mudlet_games_url' )
		? array( mudlet_games_url(), __( 'the games', 'mudlet' ) )
		: array( mudlet_makers_page_url(), __( 'the makers', 'mudlet' ) ),
);
$mudlet_lost_dirs  = array(
	'down'  => __( 'down', 'mudlet' ),
	'east'  => __( 'east', 'mudlet' ),
	'north' => __( 'north', 'mudlet' ),
	'up'    => __( 'up', 'mudlet' ),
	'west'  => __( 'west', 'mudlet' ),
);

get_header();
?>

<div class="page page--page">
	<section class="sec">
		<div class="w">
			<div class="head">
				<p class="eyebrow"><?php esc_html_e( 'error 404', 'mudlet' ); ?></p>
				<h2><?php esc_html_e( 'You cannot go that way.', 'mudlet' ); ?></h2>
			</div>

			<div class="lostterm" data-lost>
				<div class="term__bar">
					<span class="term__dot" aria-hidden="true"></span><span class="term__dot" aria-hidden="true"></span><span class="term__dot" aria-hidden="true"></span>
					<span class="term__name" aria-hidden="true"><?php esc_html_e( 'mudlet — page margin', 'mudlet' ); ?></span>
				</div>

				<div class="lostterm__out" data-out aria-live="polite">
					<div data-room>
						<p class="ln ln--room"><?php esc_html_e( 'Page Margin', 'mudlet' ); ?></p>
						<p class="ln ln--desc"><?php esc_html_e( 'White space in every direction. Whatever was at this address has moved, or was never here at all, and a signpost stands in the middle of where it used to be anyway.', 'mudlet' ); ?></p>
						<p class="ln"><?php esc_html_e( 'Here: a signpost.', 'mudlet' ); ?></p>
						<p class="ln ln--exits">
							<?php esc_html_e( 'Exits:', 'mudlet' ); ?>
							<?php
							$mudlet_lost_first = true;
							foreach ( $mudlet_lost_exits as $mudlet_lost_dir => $mudlet_lost_exit ) :
								if ( ! $mudlet_lost_first ) {
									echo ', ';
								}
								$mudlet_lost_first = false;
								?>
								<a href="<?php echo esc_url( $mudlet_lost_exit[0] ); ?>" data-dir="<?php echo esc_attr( $mudlet_lost_dir ); ?>"><?php echo esc_html( $mudlet_lost_dirs[ $mudlet_lost_dir ] ); ?></a>
								<span class="lostterm__to">(<?php echo esc_html( $mudlet_lost_exit[1] ); ?>)</span><?php endforeach; ?>
						</p>
					</div>
				</div>

				<form class="lostterm__cmd" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" role="search">
					<label class="vh" for="lost-cmd"><?php esc_html_e( 'Command, or words to search for', 'mudlet' ); ?></label>
					<span class="lostterm__mark" aria-hidden="true">&gt;</span>
					<input id="lost-cmd" name="s" type="text" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="<?php esc_attr_e( 'look, help, north… or search', 'mudlet' ); ?>">
					<button type="submit"><?php esc_html_e( 'Send', 'mudlet' ); ?></button>
				</form>

				<?php
				// What the room answers. `data-word` is filled with what was typed
				// and `data-search` becomes a search for it; see theme.js.
				?>
				<template data-say="sign">
					<p class="ln">
						<?php
						printf(
							/* translators: %s: the address the visitor asked for */
							esc_html__( 'A signpost, freshly painted. It reads %s, and points at nothing.', 'mudlet' ),
							'<b>' . esc_html( $mudlet_lost_path ) . '</b>'
						);
						?>
					</p>
					<p class="ln ln--desc"><?php esc_html_e( 'Somebody followed a link here. Either the link was wrong, or the page has gone.', 'mudlet' ); ?></p>
				</template>
				<template data-say="help">
					<p class="ln ln--desc"><b>look</b> — <?php esc_html_e( 'look around; look sign, for a closer look', 'mudlet' ); ?></p>
					<p class="ln ln--desc"><b>n e w u d</b> — <?php esc_html_e( 'walk; every way out of here leads somewhere real', 'mudlet' ); ?></p>
					<p class="ln ln--desc"><b>search …</b> — <?php esc_html_e( 'look for what you came for, by name', 'mudlet' ); ?></p>
					<p class="ln ln--desc"><b>back</b> — <?php esc_html_e( 'the way you came in', 'mudlet' ); ?></p>
				</template>
				<template data-say="walk">
					<p class="ln ln--desc">
						<?php
						printf(
							/* translators: %s: a direction, like "north" */
							esc_html__( 'You go %s.', 'mudlet' ),
							'<span data-word></span>'
						);
						?>
					</p>
				</template>
				<template data-say="noway">
					<p class="ln"><?php esc_html_e( 'Nothing that way but page margin.', 'mudlet' ); ?></p>
				</template>
				<template data-say="nothing">
					<p class="ln"><?php esc_html_e( 'You do not see that here.', 'mudlet' ); ?></p>
				</template>
				<template data-say="inventory">
					<p class="ln"><?php esc_html_e( 'You are carrying one (1) broken link, which is how you got here.', 'mudlet' ); ?></p>
				</template>
				<template data-say="xyzzy">
					<p class="ln"><?php esc_html_e( 'A hollow voice says “wrong page”.', 'mudlet' ); ?></p>
				</template>
				<template data-say="huh">
					<p class="ln">
						<?php
						printf(
							/* translators: 1: what the visitor typed, 2: a link offering to search the site for it */
							esc_html__( 'You do not know how to %1$s. %2$s', 'mudlet' ),
							'<b data-word></b>',
							'<a data-search href="#">' . esc_html__( 'Search the site for it?', 'mudlet' ) . '</a>'
						);
						?>
					</p>
				</template>
			</div>
		</div>
	</section>
</div>

<?php
get_footer();
