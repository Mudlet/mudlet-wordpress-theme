<?php
/**
 * /games/ — every game Mudlet bundles, as a showcase.
 *
 * The front page shows a random fifteen logos. This is the page the "+N more"
 * tile goes to, and a second wall of the same logos would be a worse version of
 * what the visitor just clicked away from. So it does the thing the front page
 * has no room for: it shows what each of these worlds is, in its own words.
 *
 * Three parts, and none of them holds a fact anybody typed here:
 *
 *   - a panel for one game, picked at random per request, so the page opens on
 *     something specific rather than on forty-three logos;
 *   - the whole list as cards carrying the blurb, the domain and the tags that
 *     inc/games.php derives from upstream's links and connection flags;
 *   - a toolbar that filters those cards in place.
 *
 * The toolbar is drawn hidden and revealed by theme.js, because a search box
 * that cannot search is worse than no search box. Everything else works with
 * no JavaScript at all: the panel is server-rendered, and the grid is the
 * complete list in alphabetical order (see mudlet_games_archive_query()).
 *
 * The panel's "another" button does not re-fetch anything — it rebuilds itself
 * out of a card that is already on the page. Every field it shows is a field a
 * card carries, which is why the cards hold host, port and tag URLs in data
 * attributes: one copy of the data, drawn twice.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();

$mudlet_total = (int) $GLOBALS['wp_query']->found_posts;

// The panel needs a game before the loop runs, and it must be one of the games
// the loop is about to draw. found_posts is the whole list — there is no
// pagination here — so any offset into the query's own posts is safe.
$mudlet_pick = $GLOBALS['wp_query']->posts ? $GLOBALS['wp_query']->posts[ wp_rand( 0, count( $GLOBALS['wp_query']->posts ) - 1 ) ] : null;
$mudlet_hero = $mudlet_pick ? mudlet_game( $mudlet_pick ) : null;

// Facets are counted over every game, not over the page, so the numbers on the
// chips are the numbers the filter will actually produce.
$mudlet_all    = function_exists( 'mudlet_games' ) ? mudlet_games() : array();
$mudlet_facets = mudlet_game_facets( $mudlet_all );
?>

<div class="page page--page">
	<section class="games sec">
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
				<h2><?php esc_html_e( 'Games you can play today.', 'mudlet' ); ?></h2>
				<p class="sub">
					<?php
					esc_html_e(
						'Every one of these ships with Mudlet as a connection profile: pick it from the list on startup and you are in.',
						'mudlet'
					);
					?>
				</p>
			</div>

			<?php if ( $mudlet_hero ) : ?>
				<?php
				$mudlet_hero_lede   = mudlet_game_lede( $mudlet_hero );
				$mudlet_hero_tags   = mudlet_game_tags( $mudlet_hero );
				$mudlet_hero_telnet = mudlet_game_telnet_url( $mudlet_hero );
				?>
				<div class="gfeat" id="gfeat">
					<span class="plogo gfeat__logo">
						<?php if ( $mudlet_hero['icon_url'] ) : ?>
							<img src="<?php echo esc_url( $mudlet_hero['icon_url'] ); ?>" alt="" decoding="async">
						<?php endif; ?>
					</span>

					<div class="gfeat__body">
						<p class="gfeat__eyebrow"><span class="mk">&gt;</span> <?php esc_html_e( 'one to try', 'mudlet' ); ?></p>
						<h3 class="gfeat__name"><a href="<?php echo esc_url( $mudlet_hero['url'] ); ?>"><?php echo esc_html( $mudlet_hero['name'] ); ?></a></h3>
						<p class="gfeat__lede">
							<?php echo esc_html( '' !== $mudlet_hero_lede ? $mudlet_hero_lede : mudlet_game_connect( $mudlet_hero ) ); ?>
						</p>
						<?php
						// A telnet:// link is the whole ceremony for anybody who already
						// has Mudlet: the client comes up connected. It is rendered as an
						// <a> with no href when there is no address worth linking - an
						// anchor without one is text, so the line reads the same either
						// way and theme.js has one shape to rewrite on shuffle.
						?>
						<p class="gfeat__connect">
							<a class="gplay"<?php echo $mudlet_hero_telnet ? ' href="' . esc_url( $mudlet_hero_telnet, mudlet_telnet_protocols() ) . '"' : ''; ?>>
								<span class="mk">&gt;</span><b><?php esc_html_e( 'connect', 'mudlet' ); ?></b><span class="gplay__addr"><?php
									echo esc_html( $mudlet_hero['host'] );
									?><span class="sep">&middot;</span><?php
									printf(
										/* translators: %s: TCP port number */
										esc_html__( 'port %s', 'mudlet' ),
										esc_html( (string) (int) $mudlet_hero['port'] )
									);
								?></span>
							</a>
							<span class="gplay__hint"><?php esc_html_e( 'opens Mudlet', 'mudlet' ); ?></span>
						</p>
						<p class="gfeat__tags">
							<?php foreach ( $mudlet_hero_tags as $mudlet_tag ) : ?>
								<?php $mudlet_tag_icon = mudlet_get_icon( mudlet_game_tag_icon( $mudlet_tag['key'] ), 'gtag__i' ); ?>
								<?php if ( $mudlet_tag['url'] ) : ?>
									<a class="gtag gtag--link" href="<?php echo esc_url( $mudlet_tag['url'] ); ?>" target="_blank" rel="external nofollow noopener" data-tag="<?php echo esc_attr( $mudlet_tag['key'] ); ?>"><?php echo $mudlet_tag_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG from the theme's own set. ?><?php echo esc_html( $mudlet_tag['label'] ); ?></a>
								<?php else : ?>
									<span class="gtag" data-tag="<?php echo esc_attr( $mudlet_tag['key'] ); ?>"><?php echo $mudlet_tag_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG from the theme's own set. ?><?php echo esc_html( $mudlet_tag['label'] ); ?></span>
								<?php endif; ?>
							<?php endforeach; ?>
						</p>
					</div>

					<button class="gfeat__again" type="button" hidden>
						<span aria-hidden="true">&#8635;</span> <?php esc_html_e( 'another', 'mudlet' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<?php if ( have_posts() ) : ?>
				<div class="gbar" id="gbar" hidden>
					<label class="gfind">
						<span class="screen-reader-text"><?php esc_html_e( 'Search the bundled games', 'mudlet' ); ?></span>
						<input type="search" id="gfind" autocomplete="off" placeholder="<?php esc_attr_e( 'Search names, worlds, blurbs…', 'mudlet' ); ?>">
					</label>

					<?php if ( $mudlet_facets ) : ?>
						<div class="gfacets" role="group" aria-label="<?php esc_attr_e( 'Filter games', 'mudlet' ); ?>">
							<button class="chip" type="button" data-facet="" aria-pressed="true">
								<?php esc_html_e( 'All', 'mudlet' ); ?> <b><?php echo esc_html( number_format_i18n( $mudlet_total ) ); ?></b>
							</button>
							<?php foreach ( $mudlet_facets as $mudlet_facet ) : ?>
								<button class="chip" type="button" data-facet="<?php echo esc_attr( $mudlet_facet['key'] ); ?>" aria-pressed="false">
									<?php echo esc_html( $mudlet_facet['label'] ); ?> <b><?php echo esc_html( number_format_i18n( $mudlet_facet['count'] ) ); ?></b>
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<p class="gcount" id="gcount" role="status"></p>
				</div>

				<div class="gshelf" id="gshelf">
					<?php
					while ( have_posts() ) :
						the_post();
						$mudlet_game = mudlet_game( get_post() );
						if ( ! $mudlet_game ) {
							continue;
						}
						$mudlet_lede = mudlet_game_lede( $mudlet_game );
						$mudlet_rest = mudlet_game_rest( $mudlet_game );
						$mudlet_tags = mudlet_game_tags( $mudlet_game );
						?>
						<article class="gcard"
							data-name="<?php the_title_attribute(); ?>"
							data-host="<?php echo esc_attr( $mudlet_game['host'] ); ?>"
							<?php // The whole localised phrase, not the number: the panel's shuffle prints it as-is rather than reassembling "port %s" in JavaScript. ?>
							data-portline="<?php
								printf(
									/* translators: %s: TCP port number */
									esc_attr__( 'port %s', 'mudlet' ),
									esc_attr( (string) (int) $mudlet_game['port'] )
								);
							?>"
							data-telnet="<?php echo esc_url( mudlet_game_telnet_url( $mudlet_game ), mudlet_telnet_protocols() ); ?>"
							data-tags="<?php echo esc_attr( implode( ' ', wp_list_pluck( $mudlet_tags, 'key' ) ) ); ?>">
							<span class="plogo gcard__logo">
								<?php if ( has_post_thumbnail() ) : ?>
									<?php the_post_thumbnail( 'medium', array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
								<?php endif; ?>
							</span>

							<h3 class="gcard__name">
								<?php // The whole card is clickable through .gcard__name a::after — see wp.css. ?>
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h3>
							<p class="gcard__domain"><?php echo esc_html( $mudlet_game['domain'] ); ?></p>

							<p class="gcard__lede<?php echo '' === $mudlet_lede ? ' gcard__lede--connect' : ''; ?>">
								<?php echo esc_html( '' !== $mudlet_lede ? $mudlet_lede : mudlet_game_connect( $mudlet_game ) ); ?>
							</p>

							<?php if ( '' !== $mudlet_rest ) : ?>
								<?php // Not shown, but in the card's textContent, which is what the filter reads. ?>
								<span class="gcard__index" hidden><?php echo esc_html( $mudlet_rest ); ?></span>
							<?php endif; ?>

							<?php if ( $mudlet_tags ) : ?>
								<p class="gcard__tags">
									<?php foreach ( $mudlet_tags as $mudlet_tag ) : ?>
										<?php // No nested anchors inside a stretched link: the URL rides along as data for the panel to build a real link from. ?>
										<span class="gtag" data-tag="<?php echo esc_attr( $mudlet_tag['key'] ); ?>" data-url="<?php echo esc_url( $mudlet_tag['url'] ); ?>"><?php echo mudlet_get_icon( mudlet_game_tag_icon( $mudlet_tag['key'] ), 'gtag__i' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG from the theme's own set. ?><?php echo esc_html( $mudlet_tag['label'] ); ?></span>
									<?php endforeach; ?>
								</p>
							<?php endif; ?>
						</article>
						<?php
					endwhile;
					?>
				</div>

				<p class="gnone" id="gnone" hidden>
					<?php esc_html_e( 'No bundled game matches that. Mudlet connects to any MUD you can name a host for — the profile list is a shortcut, not a limit.', 'mudlet' ); ?>
				</p>
			<?php else : ?>
				<p class="sub">
					<?php esc_html_e( 'No games have been synced yet.', 'mudlet' ); ?>
				</p>
			<?php endif; ?>

			<div class="cta gshelf__cta">
				<a class="btn" href="<?php echo esc_url( mudlet_download_url() ); ?>"><?php esc_html_e( 'Download Mudlet', 'mudlet' ); ?></a>
			</div>
		</div>
	</section>
</div>

<?php
get_footer();
