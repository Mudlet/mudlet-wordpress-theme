<?php
/**
 * The Media page, written once so there is something to look at.
 *
 * Every other page this seed creates is created empty, on the principle that
 * the seed owns that a page exists and never what is on it. This one is the
 * exception, and the exception is narrow: it writes the body **only when the
 * page is still empty**, and never again. Re-running the seed over a page
 * somebody has edited does nothing at all.
 *
 * It is worth the exception because /media/ is the one page whose whole point
 * is the two block styles inc/blocks.php registers - a screenshot carousel and
 * a screencast list - and a page that arrives blank teaches nobody that either
 * exists. What lands here is a worked example: two sections with prose around
 * them, which somebody can then rewrite, reorder or extend in Gutenberg
 * exactly like any other page. Nothing about it is load-bearing.
 *
 * The screencasts are the live site's, links and sentences both. They are real,
 * still accurate, and nobody upstream owns the list - losing it means somebody
 * re-finding eight YouTube URLs by hand, which is the one thing worth carrying
 * across from the old page.
 *
 * The screenshots are downloaded from mudlet.org, not shipped in this repo, for
 * the same reason the games plugin does not carry a copy of the game logos. A
 * checked-in copy of somebody else's screenshot only decides how stale a new
 * site starts out, and these are the community's pictures, not the theme's
 * assets. Set SEED_MEDIA=0 to skip the download and leave an empty gallery
 * block, which is a perfectly good thing to add your own images to.
 *
 * @package Mudlet
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$page = get_page_by_path( 'media' );
if ( ! $page ) {
	WP_CLI::warning( 'no /media/ page - skipping' );
	return;
}

/*
 * Three cases, not two.
 *
 * Empty is the original one: write the page. Somebody's own prose is the
 * protected one: never touch it. The third arrived with the baseline import -
 * mudlet.org's /media/ page, whose body is eleven et_pb_* blocks that render as
 * nothing without Divi. That is not content to protect, it is precisely what
 * the migration replaces, so it is overwritten and said out loud.
 *
 * The test is the shortcode rather than the page's history, because that is the
 * fact on the page: a body made of tags for a plugin this site does not have.
 */
$body   = trim( (string) $page->post_content );
$is_old = ( '' !== $body ) && ( false !== strpos( $body, '[et_pb_' ) );

if ( '' !== $body && ! $is_old ) {
	WP_CLI::log( '  · media page already has content - leaving it alone' );
	return;
}

if ( $is_old ) {
	WP_CLI::log( '  · replacing the imported Divi body with the gallery' );
}

/*
 * The screencasts, as they read on mudlet.org/media/ today. An item is a link
 * and then the sentence, with no wrapper around the sentence: that is the whole
 * shape the "Screencasts" list style is written against, and it stays a legible
 * list of links if the style is ever dropped.
 */
$screencasts = array(
	array(
		'url'   => 'https://www.youtube.com/watch?v=c1Llvwy0Y_Y&list=PLA40A1E6E5AEB8874',
		'title' => 'Introduction to Mudlet',
		'blurb' => 'The Mudlet interface, how to make basic aliases, triggers, keybindings, timers and buttons.',
	),
	array(
		'url'   => 'https://www.youtube.com/watch?v=ZzmcfU_Ri4Q&list=PL15A249142399F3CB',
		'title' => 'Aliases',
		'blurb' => 'How to use wildcards, basic target and attack aliases.',
	),
	array(
		'url'   => 'https://www.youtube.com/watch?v=OaILQThZjEU',
		'title' => 'More aliases',
		'blurb' => 'Creating an alias, by Iron Realms Entertainment.',
	),
	array(
		'url'   => 'https://www.youtube.com/watch?v=URbwW41LBcQ',
		'title' => 'Triggers',
		'blurb' => 'Several pattern types described, and how to highlight words.',
	),
	array(
		'url'   => 'https://www.youtube.com/watch?v=0-G7Wqk_5wk&list=PL31E226EE40A0FA7E',
		'title' => 'Migrating from Nexus to Mudlet',
		'blurb' => 'How to do common Nexus tasks in Mudlet.',
	),
	array(
		'url'   => 'https://www.youtube.com/watch?v=nwfjzRlgG9E',
		'title' => 'Capturing game text',
		'blurb' => 'How to capture data from a variable size in-game list.',
	),
	array(
		'url'   => 'https://youtu.be/mOBKUwGavEs',
		'title' => 'The event system',
		'blurb' => 'How to set up event handlers, raise events, and work with the GMCP ones.',
	),
	array(
		'url'   => 'https://www.youtube.com/watch?v=weWM4xiCMUs',
		'title' => 'Improving script quality',
		'blurb' => 'How to make better, more efficient scripts.',
	),
);

/*
 * The community screenshots the live page carries, in its order.
 *
 * A caption is only written where the picture's own filename says what it is
 * looking at. Three of the fifteen do not - Selection_591, map-stat-big,
 * group-combat-big-1 - and those go in uncaptioned rather than captioned with
 * a guess about somebody else's game. An empty caption is also the normal
 * case for a screenshot somebody sends in, so the carousel had better look
 * right with a few of them, and this is how that gets noticed.
 */
$shots = array(
	array( 'https://www.mudlet.org/wp-content/uploads/2024/05/The-Land-MUD.png', 'The Land' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2024/05/MUD-in-chinese.png', 'A MUD in Chinese' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2024/05/Cybersphere-UI.png', 'Cybersphere' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2024/05/Gauges-for-a-party-on-Icesus.png', 'Party gauges on Icesus' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2021/11/mume-mudlet.png', 'MUME' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2021/09/Legends-of-the-Jedi-Mudlet-4.12.0-ptb-2021-09-19-68133_001.png', 'Legends of the Jedi' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2021/04/arkadia2-min.png', 'Arkadia' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2021/04/arkadia1.png', 'Arkadia' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2021/05/profile_swap.gif', 'Switching between profiles' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2021/01/mudlet_cleftofdimensions.gif', 'Cleft of Dimensions' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2020/05/cf-adjustable-container.png', 'An adjustable container' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2020/05/Selection_591.png', '' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2019/10/map-stat-big.png', '' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2019/10/group-combat-big-1.png', '' ),
	array( 'https://www.mudlet.org/wp-content/uploads/2024/06/ArkadiaMUD-customized.png', 'Arkadia, customised' ),
);

/**
 * One screenshot into the media library, or an id it is already at.
 *
 * The source URL is kept in meta so a second run finds the attachment instead
 * of downloading a fifteenth copy of it. That matters more than it looks: this
 * script bails on a page with content, but a site whose page was emptied by
 * hand would otherwise re-import the lot.
 *
 * @param string $url     Where to fetch it from.
 * @param string $caption Caption, or '' for none.
 * @return int Attachment id, or 0 if it could not be fetched.
 */
function mudlet_seed_shot( string $url, string $caption ): int {
	$found = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_mudlet_seed_shot',   // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $url,                  // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	if ( $found ) {
		return (int) $found[0];
	}

	$tmp = download_url( $url, 30 );
	if ( is_wp_error( $tmp ) ) {
		WP_CLI::warning( basename( $url ) . ': ' . $tmp->get_error_message() );
		return 0;
	}

	$id = media_handle_sideload(
		array(
			'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		),
		0,
		$caption
	);

	if ( is_wp_error( $id ) ) {
		// media_handle_sideload cleans the temp file up on success only.
		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		WP_CLI::warning( basename( $url ) . ': ' . $id->get_error_message() );
		return 0;
	}

	// The caption is what the carousel prints; the alt is what a screen reader
	// gets, and "Arkadia" is a better answer there than the file's name.
	update_post_meta( $id, '_wp_attachment_image_alt', $caption );
	update_post_meta( $id, '_mudlet_seed_shot', $url );
	wp_update_post(
		array(
			'ID'           => $id,
			'post_excerpt' => $caption,
		)
	);

	return (int) $id;
}

// ── the screencast list ───────────────────────────────────────────────
$items = '';
foreach ( $screencasts as $cast ) {
	$items .= '<!-- wp:list-item --><li><a href="' . esc_url( $cast['url'] ) . '">'
		. esc_html( $cast['title'] ) . '</a> ' . esc_html( $cast['blurb'] )
		. '</li><!-- /wp:list-item -->';
}

// ── the gallery ───────────────────────────────────────────────────────
$slides = '';
$got    = 0;
if ( '0' !== getenv( 'SEED_MEDIA' ) ) {
	WP_CLI::log( '  · fetching ' . count( $shots ) . ' screenshots from mudlet.org' );
	foreach ( $shots as $shot ) {
		list( $url, $caption ) = $shot;

		$id = mudlet_seed_shot( $url, $caption );
		if ( ! $id ) {
			continue;
		}
		++$got;

		$large = wp_get_attachment_image_url( $id, 'large' );
		$file  = wp_get_attachment_url( $id );

		$slides .= '<!-- wp:image {"id":' . $id . ',"sizeSlug":"large","linkDestination":"media"} -->'
			. '<figure class="wp-block-image size-large">'
			. '<a href="' . esc_url( $file ) . '">'
			. '<img src="' . esc_url( $large ? $large : $file ) . '" alt="' . esc_attr( $caption ) . '" class="wp-image-' . $id . '"/>'
			. '</a>'
			. ( '' !== $caption ? '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption>' : '' )
			. '</figure>'
			. '<!-- /wp:image -->';
	}
	WP_CLI::log( '  · ' . $got . ' of ' . count( $shots ) . ' imported' );
} else {
	WP_CLI::log( '  · SEED_MEDIA=0 - leaving the gallery empty' );
}

$content = '<!-- wp:heading --><h2 class="wp-block-heading">Educational screencasts</h2><!-- /wp:heading -->'

	. '<!-- wp:paragraph --><p>Videos covering the basics — aliases, triggers, timers and buttons — '
	. 'and then the things that make a script worth keeping: the event system, capturing text out of '
	. 'the game, and writing Lua you can still read in a year.</p><!-- /wp:paragraph -->'

	. '<!-- wp:list {"className":"is-style-mudlet-screencasts"} -->'
	. '<ul class="wp-block-list is-style-mudlet-screencasts">' . $items . '</ul>'
	. '<!-- /wp:list -->'

	. '<!-- wp:heading --><h2 class="wp-block-heading">Screenshots from the community</h2><!-- /wp:heading -->'

	. '<!-- wp:paragraph --><p>Mudlet draws what you tell it to. These are other people’s: gauges, '
	. 'buttons, floating windows and maps, arranged to suit the game they play and the way they play '
	. 'it. Take an idea, or send us yours.</p><!-- /wp:paragraph -->'

	. '<!-- wp:gallery {"linkTo":"media","imageCrop":false,"className":"is-style-mudlet-carousel"} -->'
	. '<figure class="wp-block-gallery has-nested-images columns-default is-style-mudlet-carousel">'
	. $slides
	. '</figure>'
	. '<!-- /wp:gallery -->';

// Content only. page.php prints an excerpt as the subtitle under the title, and
// a page whose first section is headed "Educational screencasts" does not need a
// line above it saying that it has screencasts on it.
wp_update_post(
	array(
		'ID'           => $page->ID,
		'post_content' => wp_slash( $content ),
	)
);

WP_CLI::log( '  · wrote the media page (#' . $page->ID . ')' );
