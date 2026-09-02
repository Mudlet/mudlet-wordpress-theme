<?php
/**
 * The three editable regions of the front page, and their defaults.
 *
 * The front page is a template, not a document: `front-page.php` decides which
 * sections exist and in what order, and that is not something anybody edits.
 * Three regions inside it do change, on a cadence that has nothing to do with
 * deploying a theme:
 *
 *   - the six cards under "What keeps people playing"
 *   - the spec line under them, which grows every time a feature ships
 *   - the two prose columns of "What is Mudlet? / What are MUDs?"
 *
 * So those are data, edited on the front page's own screen (see
 * inc/front-content-admin.php), and everything around them - the headings, the
 * eyebrows, the hero, the section order - stays markup.
 *
 * **A card's picture is not data.** It used to be: each panel carried an
 * uploaded 16:9 screenshot. That fell over because only two of the six claims
 * have a picture that fills a frame that size - "works anywhere" and "free and
 * open source" are facts about where the client runs and who writes it, not
 * things a screenshot of a session can show. So the art is a small, hand-built
 * figure per card, named by an `art` key out of mudlet_front_arts() and drawn
 * by inc/front-art.php; two of them read live numbers. What an editor sets is
 * which figure, and the words. Real screenshots moved to the row of thumbnails
 * under the cards, where they are small enough that cropping stops mattering.
 *
 * **The defaults below are the section as it was written.** An empty option
 * renders exactly what the templates used to hold, which is what makes this
 * safe: the page is identical the day it lands, identical again if somebody
 * clears a field, and there is no seed writing prose into a database to make it
 * look right. The defaults also keep their __() calls, so they stay
 * translatable for as long as nobody has touched them.
 *
 * One option and not post meta on the front page: the screen is not attached to
 * a page, and `page_on_front` can be reassigned - which would otherwise strand
 * the content on a page nobody is looking at any more.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/** Where all three regions are stored, as one array. */
const MUDLET_FRONT_OPTION = 'mudlet_front_page';

/** Where the star count is kept between weekly lookups. */
const MUDLET_FRONT_STARS_KEY = 'mudlet_front_github_stars';

/** Where the contributor list is kept between weekly lookups. */
const MUDLET_FRONT_PEOPLE_KEY = 'mudlet_front_github_people';

/**
 * The stored content, or an empty array when nothing has been saved.
 *
 * @return array<string, mixed>
 */
function mudlet_front_stored(): array {
	$stored = get_option( MUDLET_FRONT_OPTION, array() );

	return is_array( $stored ) ? $stored : array();
}

/**
 * The figures a card can carry, keyed by what the template draws.
 *
 * Six of them, because there are six claims and each one needed its own answer
 * to "what does this look like". Adding a seventh card means picking one of
 * these or drawing a new figure in inc/front-art.php - which is the honest
 * shape of it: the art is code, and a dropdown cannot invent a picture.
 *
 * @return array<string, string>
 */
function mudlet_front_arts(): array {
	return array(
		'platforms'    => __( 'Operating system marks', 'mudlet' ),
		'lua'          => __( 'Lua mark', 'mudlet' ),
		'layers'       => __( 'Stacked windows', 'mudlet' ),
		'map'          => __( 'Map graph', 'mudlet' ),
		'contributors' => __( 'GitHub contributors and stars', 'mudlet' ),
		'discord'      => __( 'Discord members and who is online', 'mudlet' ),
	);
}

/**
 * The cards, as written.
 *
 * @return array<int, array{art:string,title:string,body:string}>
 */
function mudlet_front_card_defaults(): array {
	return array(
		array(
			'art'   => 'platforms',
			'title' => __( 'Works anywhere', 'mudlet' ),
			'body'  => __( 'Windows, macOS and Linux — even Chromebooks and Raspberry Pi. Scripts written on one machine run on the next, and profiles sync however you like.', 'mudlet' ),
		),
		array(
			'art'   => 'lua',
			'title' => __( 'Fast & lightweight', 'mudlet' ),
			'body'  => __( 'Performance defined Mudlet from the start. A custom text display and Lua-powered scripting handle the biggest raids without dropping a frame.', 'mudlet' ),
		),
		array(
			'art'   => 'layers',
			'title' => __( '100% modifiable', 'mudlet' ),
			'body'  => __( 'Every part of the interface is designed to be modded — from the space inside the window to the look and feel of the client itself.', 'mudlet' ),
		),
		array(
			'art'   => 'map',
			'title' => __( 'A real mapper', 'mudlet' ),
			'body'  => __( '2D and 3D mapping with built-in pathfinding. Walk once and Mudlet remembers — then draw custom exits or drop a background image over an area.', 'mudlet' ),
		),
		array(
			'art'   => 'contributors',
			'title' => __( 'Free and open source', 'mudlet' ),
			'body'  => __( 'Free to download, modify and extend, under the GPL. Build on a powerful foundation and join us in making MUDing awesome.', 'mudlet' ),
		),
		array(
			'art'   => 'discord',
			'title' => __( 'Approachable', 'mudlet' ),
			'body'  => __( 'A friendly Discord of over 5,000 players, and a scripting API carefully designed to be simple and intuitive before it is powerful.', 'mudlet' ),
		),
	);
}

/**
 * The spec line's items, as written.
 *
 * @return string[]
 */
function mudlet_front_spec_defaults(): array {
	return array(
		__( 'Multiple simultaneous games', 'mudlet' ),
		__( 'Lua scripting API', 'mudlet' ),
		__( 'In-app script editor', 'mudlet' ),
		__( 'Import/export profiles', 'mudlet' ),
		__( 'Broad MUD protocol support', 'mudlet' ),
		__( 'Secure connections', 'mudlet' ),
		__( 'In-app IRC client', 'mudlet' ),
		__( 'Discord Rich Presence', 'mudlet' ),
		__( 'Accessible to visually impaired players', 'mudlet' ),
	);
}

/**
 * The two prose columns, as written.
 *
 * The two <h2>s are not here: "What is Mudlet?" and "What are MUDs?" are the
 * shape of the section rather than its content, and a section whose headings
 * can be edited into something else is a section that can stop being this one.
 *
 * @return array{intro:string[],quote:string,cite:string,note:string}
 */
function mudlet_front_about_defaults(): array {
	return array(
		'intro' => array(
			__( 'A platform for playing and enhancing text games. Mudlet gives players and creators a toolkit and broad protocol support to tailor an immersive experience.', 'mudlet' ),
			__( 'Creators use it to add visual flair or build features into their games. Players use it to script and automate their play, or to visualise game data their own way.', 'mudlet' ),
			__( 'It has even been used outside MUDs entirely — automating 3D games that expose in-game chat over Telnet.', 'mudlet' ),
		),
		'quote' => __( 'A multiplayer real-time virtual world, usually text-based, combining role-playing, hack and slash, player versus player, interactive fiction and online chat. Players interact by typing commands that resemble natural language.', 'mudlet' ),
		'cite'  => __( 'Wikipedia', 'mudlet' ),
		'note'  => __( 'The kind of game you fall in love with for its stories, its raids, its politics — or just for the people.', 'mudlet' ),
	);
}

/**
 * The cards the front page should draw.
 *
 * @return array<int, array{art:string,title:string,body:string}>
 */
function mudlet_front_cards(): array {
	$stored = mudlet_front_stored();
	$cards  = isset( $stored['cards'] ) && is_array( $stored['cards'] ) ? $stored['cards'] : array();

	/**
	 * Filter the six cards under "What keeps people playing".
	 *
	 * @param array $cards Cards, each art/title/body.
	 */
	return (array) apply_filters( 'mudlet_front_cards', $cards ? $cards : mudlet_front_card_defaults() );
}

/**
 * The spec line's items.
 *
 * @return string[]
 */
function mudlet_front_specs(): array {
	$stored = mudlet_front_stored();
	$specs  = isset( $stored['specs'] ) && is_array( $stored['specs'] ) ? $stored['specs'] : array();

	/**
	 * Filter the spec line under the cards.
	 *
	 * @param string[] $specs Short phrases.
	 */
	return (array) apply_filters( 'mudlet_front_specs', $specs ? $specs : mudlet_front_spec_defaults() );
}

/**
 * The two prose columns.
 *
 * Merged over the defaults key by key rather than replaced wholesale, so a
 * half-filled option cannot leave the section with an empty column.
 *
 * @return array{intro:string[],quote:string,cite:string,note:string}
 */
function mudlet_front_about(): array {
	$stored   = mudlet_front_stored();
	$about    = isset( $stored['about'] ) && is_array( $stored['about'] ) ? $stored['about'] : array();
	$defaults = mudlet_front_about_defaults();

	foreach ( $defaults as $key => $default ) {
		if ( empty( $about[ $key ] ) ) {
			$about[ $key ] = $default;
		}
	}

	/**
	 * Filter the "What is Mudlet? / What are MUDs?" copy.
	 *
	 * @param array $about intro/quote/cite/note.
	 */
	return (array) apply_filters( 'mudlet_front_about', $about );
}

/**
 * Screenshots for the row of thumbnails under the cards.
 *
 * Read out of /media/ rather than typed or uploaded again: that page's gallery
 * *is* the site's screenshot collection, so adding one there adds it here, and
 * nothing has to be kept in step by hand. Shuffled, so the front page is not
 * always the same three.
 *
 * Falls back to the attachments the seed imported, which carry
 * `_mudlet_seed_shot`, for a site whose /media/ page has been rewritten into
 * some other shape. An empty answer hides the row entirely.
 *
 * @param int $count How many to return.
 * @return int[] Attachment ids.
 */
function mudlet_front_thumbs( int $count = 3 ): array {
	$ids  = array();
	$page = get_page_by_path( 'media' );

	if ( $page instanceof WP_Post && function_exists( 'parse_blocks' ) ) {
		foreach ( parse_blocks( $page->post_content ) as $block ) {
			$ids = array_merge( $ids, mudlet_front_block_images( $block ) );
		}
	}

	if ( ! $ids ) {
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 60,
				'fields'         => 'ids',
				'meta_key'       => '_mudlet_seed_shot', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
	}

	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	shuffle( $ids );

	/**
	 * Filter the screenshots the front page shows.
	 *
	 * @param int[] $ids   Attachment ids, already shuffled.
	 * @param int   $count How many the template asked for.
	 */
	$ids = (array) apply_filters( 'mudlet_front_thumbs', $ids, $count );

	return array_slice( $ids, 0, max( 0, $count ) );
}

/**
 * Every image id inside a block and its children.
 *
 * A gallery keeps its pictures as nested core/image blocks, so this walks
 * rather than reading one attribute.
 *
 * @param array<string, mixed> $block Parsed block.
 * @return int[]
 */
function mudlet_front_block_images( array $block ): array {
	$ids = array();

	if ( ! empty( $block['attrs']['id'] ) && 'core/image' === ( $block['blockName'] ?? '' ) ) {
		$ids[] = (int) $block['attrs']['id'];
	}

	foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $inner ) {
		$ids = array_merge( $ids, mudlet_front_block_images( $inner ) );
	}

	return $ids;
}

/**
 * How many people have starred Mudlet on GitHub.
 *
 * One unauthenticated request a week. Unauthenticated is 60 an hour per IP,
 * which a seven-day transient makes a non-issue, and a star count is not a
 * fact that needs to be fresher than that.
 *
 * `stargazers_count` comes straight off the repository, so unlike a contributor
 * total this needs no paging through a list to arrive at a number.
 *
 * Null means "do not know", and the card draws no pill rather than a stale or
 * invented figure. A failure is cached briefly too, so an outage costs one
 * request an hour instead of one per page view.
 *
 * @return int|null
 */
function mudlet_front_github_stars(): ?int {
	$cached = get_transient( MUDLET_FRONT_STARS_KEY );
	if ( is_numeric( $cached ) ) {
		return (int) $cached > 0 ? (int) $cached : null;
	}

	/**
	 * Filter the repository the star count comes from.
	 *
	 * @param string $repo owner/name.
	 */
	$repo = (string) apply_filters( 'mudlet_front_github_repo', 'Mudlet/Mudlet' );

	$response = wp_remote_get(
		'https://api.github.com/repos/' . $repo,
		array(
			'timeout' => 8,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'mudlet.org',
			),
		)
	);

	$stars = 0;
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$stars = isset( $body['stargazers_count'] ) ? (int) $body['stargazers_count'] : 0;
	}

	set_transient( MUDLET_FRONT_STARS_KEY, $stars, $stars > 0 ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );

	return $stars > 0 ? $stars : null;
}

/**
 * Who has committed to Mudlet, and how many of them there are.
 *
 * **Not the makers.** `mudlet_makers()` is the thirty people the client credits
 * in Help -> About; this is everybody GitHub has recorded a commit for, which is
 * a much larger number and the one that means "open source". The two are
 * different populations and the card shows this one.
 *
 * Two requests, once a week. `per_page=1` is the cheap way to an exact total:
 * GitHub pages the contributor list and puts the last page number in the `Link`
 * header, so one row comes back and the header carries the count. The second
 * request fetches enough people to draw faces from.
 *
 * The avatars are GitHub's own URLs, so a visitor's browser fetches them from
 * avatars.githubusercontent.com. That is the one third-party request this page
 * makes, and it is a deliberate trade against sideloading a hundred pictures
 * into the media library for six circles - see the README note if it needs
 * revisiting.
 *
 * @return array{total:int,people:array<int,array{login:string,avatar:string,url:string}>}
 */
function mudlet_front_github_contributors(): array {
	$empty = array(
		'total'  => 0,
		'people' => array(),
	);

	$cached = get_transient( MUDLET_FRONT_PEOPLE_KEY );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	/** This filter is documented in inc/front-content.php */
	$repo = (string) apply_filters( 'mudlet_front_github_repo', 'Mudlet/Mudlet' );
	$base = 'https://api.github.com/repos/' . $repo . '/contributors';

	// One row, for the Link header the total lives in.
	$head  = mudlet_front_github_get( $base . '?per_page=1' );
	$total = 0;
	if ( $head ) {
		$link = wp_remote_retrieve_header( $head, 'link' );
		if ( $link && preg_match( '/[?&]page=(\d+)[^>]*>;\s*rel="last"/', $link, $m ) ) {
			$total = (int) $m[1];
		} else {
			$total = count( (array) json_decode( wp_remote_retrieve_body( $head ), true ) );
		}
	}

	$people = array();
	$list   = mudlet_front_github_get( $base . '?per_page=30' );
	if ( $list ) {
		foreach ( (array) json_decode( wp_remote_retrieve_body( $list ), true ) as $row ) {
			if ( empty( $row['avatar_url'] ) || empty( $row['login'] ) ) {
				continue;
			}
			$people[] = array(
				'login'  => (string) $row['login'],
				// s=64 rather than the full-size default: these are drawn at
				// about 33px, and GitHub resizes on its own CDN for free.
				'avatar' => add_query_arg( 's', 64, (string) $row['avatar_url'] ),
				'url'    => (string) ( $row['html_url'] ?? '' ),
			);
		}
	}

	$out = $people ? array(
		'total'  => max( $total, count( $people ) ),
		'people' => $people,
	) : $empty;

	set_transient( MUDLET_FRONT_PEOPLE_KEY, $out, $people ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );

	return $out;
}

/**
 * One GET at GitHub, or null.
 *
 * @param string $url Endpoint.
 * @return array|null Response array, or null on anything but a 200.
 */
function mudlet_front_github_get( string $url ): ?array {
	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 8,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'mudlet.org',
			),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	return $response;
}

/**
 * A count as the card prints it: 4310 becomes "4.3k".
 *
 * @param int $n Count.
 * @return string
 */
function mudlet_front_short_count( int $n ): string {
	if ( $n < 1000 ) {
		return number_format_i18n( $n );
	}

	return sprintf(
		/* translators: %s: a number of thousands, e.g. "4.3" */
		__( '%sk', 'mudlet' ),
		number_format_i18n( $n / 1000, $n < 10000 ? 1 : 0 )
	);
}

/**
 * Clean one submitted copy of everything above.
 *
 * The only way in: the screen posts through save_post, and this runs on every
 * save whoever made it.
 *
 * Empty means empty here, not "use the defaults" - the accessors decide that.
 * Storing a copy of the defaults the first time somebody presses Save would
 * freeze today's copy into the database and quietly detach it from the
 * templates, which is the failure this whole file is arranged to avoid.
 *
 * @param mixed $input Raw $_POST value.
 * @return array<string, mixed>
 */
function mudlet_front_sanitize( $input ): array {
	$input = is_array( $input ) ? $input : array();
	$out   = array();

	$arts = array_keys( mudlet_front_arts() );

	$cards = array();
	foreach ( (array) ( $input['cards'] ?? array() ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
		$body  = sanitize_textarea_field( (string) ( $row['body'] ?? '' ) );

		// A card with neither a title nor a sentence is a row somebody added
		// and did not fill in. Dropping it here is what makes "clear the
		// fields" a way to delete one.
		if ( '' === $title && '' === $body ) {
			continue;
		}

		$art = (string) ( $row['art'] ?? '' );

		$cards[] = array(
			'art'   => in_array( $art, $arts, true ) ? $art : 'lua',
			'title' => $title,
			'body'  => $body,
		);
	}
	$out['cards'] = $cards;

	// One per line, blanks dropped. A textarea rather than a repeater because
	// each item is three words and has no second field to carry.
	$specs = array();
	foreach ( preg_split( '/\R/', (string) ( $input['specs'] ?? '' ) ) as $line ) {
		$line = sanitize_text_field( trim( $line ) );
		if ( '' !== $line ) {
			$specs[] = $line;
		}
	}
	$out['specs'] = $specs;

	$about = is_array( $input['about'] ?? null ) ? $input['about'] : array();

	// Blank line between paragraphs, which is how the field says it wants them.
	$intro = array();
	foreach ( preg_split( '/\R{2,}/', (string) ( $about['intro'] ?? '' ) ) as $para ) {
		$para = trim( wp_kses( $para, mudlet_front_prose_tags() ) );
		if ( '' !== $para ) {
			$intro[] = $para;
		}
	}

	$out['about'] = array(
		'intro' => $intro,
		'quote' => trim( wp_kses( (string) ( $about['quote'] ?? '' ), mudlet_front_prose_tags() ) ),
		'cite'  => sanitize_text_field( (string) ( $about['cite'] ?? '' ) ),
		'note'  => trim( wp_kses( (string) ( $about['note'] ?? '' ), mudlet_front_prose_tags() ) ),
	);

	return $out;
}

/**
 * What prose on the front page is allowed to contain.
 *
 * A link and emphasis, and nothing structural: these fields sit inside markup
 * that decides its own layout, so a heading or a list pasted into one has
 * nowhere sensible to land.
 *
 * @return array<string, array<string, bool>>
 */
function mudlet_front_prose_tags(): array {
	return array(
		'a'      => array(
			'href'   => true,
			'title'  => true,
			'rel'    => true,
			'target' => true,
		),
		'em'     => array(),
		'strong' => array(),
		'code'   => array(),
		'br'     => array(),
	);
}
