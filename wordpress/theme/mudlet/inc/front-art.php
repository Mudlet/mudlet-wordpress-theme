<?php
/**
 * The little figure at the top of each card on the front page.
 *
 * Six of them, one per claim, because the claims want six different kinds of
 * evidence and only two of them are a screenshot. "Works anywhere" is a fact
 * about operating systems; "free and open source" is a fact about who writes
 * the client; neither is something a picture of a MUD session can show. The old
 * switcher gave every claim the same 16:9 image slot and four of the six had
 * nothing to put in it.
 *
 * So these are markup, not uploads. That buys three things a JPEG would not:
 * they follow the palette into dark without a second asset, they are sharp at
 * any zoom, and two of them can carry a number that is true today - the star
 * count and who is on Discord right now.
 *
 * Which figure a card gets is data (see mudlet_front_arts()); what the figure
 * *is* is code. A dropdown cannot invent a picture, so adding a seventh kind of
 * card means drawing one here.
 *
 * Everything live degrades to nothing rather than to a stale number: no stars,
 * no pill. The faces are local attachments the makers plugin already
 * sideloaded, so the two halves of that card fail independently.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/** How many faces the contributors card shows before it starts counting. */
const MUDLET_FRONT_FACES = 5;

/**
 * Draw one card's figure.
 *
 * @param string $art Key from mudlet_front_arts().
 */
function mudlet_front_card_art( string $art ): void {
	$arts = mudlet_front_arts();
	if ( ! isset( $arts[ $art ] ) ) {
		return;
	}

	echo '<div class="cardart cardart--' . esc_attr( $art ) . '">';

	switch ( $art ) {
		case 'platforms':
			mudlet_front_art_platforms();
			break;
		case 'lua':
			mudlet_front_art_lua();
			break;
		case 'layers':
			mudlet_front_art_layers();
			break;
		case 'map':
			mudlet_front_art_map();
			break;
		case 'contributors':
			mudlet_front_art_contributors();
			break;
		case 'discord':
			mudlet_front_art_discord();
			break;
	}

	echo '</div>';
}

/**
 * The four marks, which are already in the icon set for the download page.
 */
function mudlet_front_art_platforms(): void {
	foreach ( array( 'windows', 'apple', 'linux', 'chrome' ) as $mark ) {
		mudlet_icon( $mark, 'cardart__os' );
	}
}

/**
 * A bolt and the Lua mark: the speed and the thing that provides it.
 */
function mudlet_front_art_lua(): void {
	mudlet_icon( 'bolt', 'cardart__bolt' );
	mudlet_icon( 'lua', 'cardart__lua' );
}

/**
 * Three windows stacked, for an interface that is somebody else's to arrange.
 */
function mudlet_front_art_layers(): void {
	?>
	<span class="cardart__stack" aria-hidden="true">
		<span></span><span></span><span></span>
	</span>
	<?php
}

/**
 * A fragment of a map: rooms on a grid, joined by their exits.
 *
 * Drawn rather than screenshotted, because a real map does not survive being
 * shrunk to 44px. What makes it read as a *map* rather than as scattered
 * squares is the grid: rooms sit on two rows at one spacing and the corridors
 * between them run straight, which is what mudlet's own mapper produces - it
 * walks the exits and puts one square per step. An earlier version staggered
 * them prettily and looked like confetti.
 *
 * Rooms are filled rather than outlined so they hold up in both themes: an
 * outline in --line on a --card background is nearly the same value in dark,
 * which is what made the first one disappear there.
 */
function mudlet_front_art_map(): void {
	?>
	<svg class="cardart__map" viewBox="0 0 68 44" aria-hidden="true" focusable="false">
		<path d="M14 11h8M32 11h8M27 16v12M45 16v12M32 33h8M50 33h8"/>
		<rect x="4" y="6" width="10" height="10" rx="2"/>
		<rect x="22" y="6" width="10" height="10" rx="2"/>
		<rect x="40" y="6" width="10" height="10" rx="2"/>
		<rect x="22" y="28" width="10" height="10" rx="2"/>
		<rect class="is-here" x="40" y="28" width="10" height="10" rx="2"/>
		<rect x="58" y="28" width="10" height="10" rx="2"/>
	</svg>
	<?php
}

/**
 * Faces, and how many people have starred the repository.
 *
 * The faces are the makers plugin's own rows, whose avatars it has already
 * sideloaded from GitHub - so nothing here reaches a third party while the page
 * renders, and a maker with no handle keeps the initials the roster gives them.
 * Shuffled, so the front page is not always the same five.
 *
 * The count in the last circle is everybody the faces did not fit, which is a
 * different number from the stars beside it and deliberately so: one is the
 * people the client credits, the other is the people who pressed a button.
 */
function mudlet_front_art_contributors(): void {
	$people = mudlet_front_github_contributors();

	if ( $people['people'] ) {
		// Shuffled out of the top thirty rather than always the same five - the
		// list comes back ordered by commit count, and a card that only ever
		// showed the busiest five would be a leaderboard rather than a crowd.
		$pool = $people['people'];
		shuffle( $pool );
		$shown = array_slice( $pool, 0, MUDLET_FRONT_FACES );
		$rest  = $people['total'] - count( $shown );
		?>
		<span class="cardart__faces">
			<?php foreach ( $shown as $person ) : ?>
				<span class="cardart__face">
					<img src="<?php echo esc_url( $person['avatar'] ); ?>" alt=""
						width="33" height="33" loading="lazy" decoding="async">
				</span>
			<?php endforeach; ?>

			<?php if ( $rest > 0 ) : ?>
				<span class="cardart__face cardart__face--more">
					<?php
					printf(
						/* translators: %s: how many more people have contributed */
						esc_html__( '+%s', 'mudlet' ),
						esc_html( mudlet_front_short_count( $rest ) )
					);
					?>
				</span>
			<?php endif; ?>
		</span>
		<?php
	}

	$stars = mudlet_front_github_stars();
	if ( null === $stars ) {
		return;
	}
	?>
	<a class="cardart__pill" href="https://github.com/Mudlet/Mudlet" target="_blank" rel="external noopener">
		<?php
		mudlet_icon( 'github' );
		mudlet_icon( 'star', 'cardart__star' );
		?>
		<b><?php echo esc_html( mudlet_front_short_count( $stars ) ); ?></b>
		<span class="vh"><?php esc_html_e( 'stars on GitHub', 'mudlet' ); ?></span>
	</a>
	<?php
}

/**
 * The Discord mark, and how many people are in there now.
 *
 * The same lookup /contact/ makes, cached the same ten minutes - so this costs
 * nothing extra. **No faces and no names**, unlike that page: the people in
 * that widget joined a chat server, not a shop window on the busiest page of
 * mudlet.org, and the number carries the point without them.
 */
function mudlet_front_art_discord(): void {
	$server = mudlet_discord_server();
	?>
	<a class="cardart__pill cardart__pill--discord" href="<?php echo esc_url( (string) $server['invite'] ); ?>" target="_blank" rel="external noopener">
		<?php mudlet_icon( 'discord' ); ?>

		<?php if ( ! empty( $server['live'] ) && $server['members'] > 0 ) : ?>
			<b>
				<?php
				printf(
					/* translators: %s: how many people are in the Discord server */
					esc_html__( '%s members', 'mudlet' ),
					esc_html( number_format_i18n( (int) $server['members'] ) )
				);
				?>
			</b>

			<?php // The size of the room, then how many are in it right now. ?>
			<?php if ( $server['online'] > 0 ) : ?>
				<span class="cardart__now">
					<span class="cardart__dot" aria-hidden="true"></span>
					<?php
					printf(
						/* translators: %s: how many of them are online now */
						esc_html__( '%s online', 'mudlet' ),
						esc_html( number_format_i18n( (int) $server['online'] ) )
					);
					?>
				</span>
			<?php endif; ?>
		<?php else : ?>
			<b><?php esc_html_e( 'Join the Discord', 'mudlet' ); ?></b>
		<?php endif; ?>
	</a>
	<?php
}
