<?php
/**
 * The bundled games, from the Mudlet Games plugin.
 *
 * These used to be a PHP array in template-parts/home/games.php, kept in step
 * by hand with whatever Mudlet actually ships. The same reasoning that moved
 * releases out of the theme applies here: which games are bundled is a fact
 * about the client, not a decision about how a page looks, so it lives in a
 * plugin that reads it from Mudlet's own source — and the theme asks.
 *
 * Everything goes through function_exists(), because a theme that hard-requires
 * a plugin is a theme that white-screens when somebody deactivates one. With
 * the plugin gone the section is not drawn at all.
 *
 * Do not give it a fallback list. A typed fifteen is the thing this plugin
 * exists to replace, and it is wrong the day a game is added upstream with
 * nobody able to tell by looking: a missing section is a bug somebody reports,
 * a grid quietly showing fifteen of forty-three is one nobody notices for
 * months. The makers roster has never had one, for the same reason.
 *
 * @see wordpress/plugin/mudlet-games/includes/api.php
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether game data is available at all.
 *
 * @return bool
 */
function mudlet_has_game_data(): bool {
	return function_exists( 'mudlet_games' ) && mudlet_games_count() > 0;
}

/**
 * How many games Mudlet bundles.
 *
 * @return int
 */
function mudlet_game_count(): int {
	return mudlet_has_game_data() ? mudlet_games_count() : 0;
}

/**
 * Games for the front page's grid, in a random order.
 *
 * Random because the grid shows fifteen of forty-odd, and a fixed fifteen means
 * the same fifteen games get all the attention for as long as the page exists.
 *
 * @param int $number How many to return.
 * @return array<int, array<string, mixed>> Rows of name, domain, icon_url, url.
 */
function mudlet_home_games( int $number = 15 ): array {
	if ( ! mudlet_has_game_data() ) {
		return array();
	}

	$download = mudlet_download_url();

	return array_map(
		static function ( array $game ) use ( $download ): array {
			return array(
				'name'     => $game['name'],
				'domain'   => $game['domain'],
				'icon_url' => $game['icon_url'],
				// The game's own page when there is one to send people to.
				'url'      => $game['url'] ? $game['url'] : $download,
			);
		},
		mudlet_games( array(
			'number'  => $number,
			'orderby' => 'rand',
		) )
	);
}

/**
 * Where the "+N more" tile points.
 *
 * The archive is the plugin's, like everything else here - hence the guard,
 * even though nothing draws this tile when the plugin is gone.
 *
 * @return string
 */
function mudlet_games_more_url(): string {
	return function_exists( 'mudlet_games_url' ) ? mudlet_games_url() : mudlet_download_url();
}

add_action( 'pre_get_posts', 'mudlet_games_archive_query' );
/**
 * Put the whole list on /games/.
 *
 * The site's posts_per_page is 18, tuned for the news index. A games archive
 * reached from a tile that says "+28 more" and then paginating at 18 is a
 * small betrayal; there are only forty-odd of them and they are one line each.
 *
 * @param WP_Query $query The query.
 */
function mudlet_games_archive_query( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( $query->is_post_type_archive( 'mudlet_game' ) ) {
		$query->set( 'posts_per_page', 100 );
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	}
}

add_action( 'admin_notices', 'mudlet_games_plugin_notice' );
/**
 * Say so, once, if the plugin is missing.
 *
 * The site still works without it — but the front page is a section short and
 * nothing on it says why, so this is the only thing that does.
 */
function mudlet_games_plugin_notice(): void {
	if ( mudlet_has_game_data() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'themes', 'plugins' ), true ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__(
			'Mudlet: the front page is not showing its games section, because the bundled games come from the Mudlet Games plugin and it is not active. Activate it to read them from Mudlet itself.',
			'mudlet'
		)
	);
}

/* ─────────────────────────────────────────────────────────────────────
   /games/ — the showcase's derived bits.

   Everything below reads what upstream already says about a game and turns it
   into something a card can print. None of it is a place to type a fact: if a
   game wants a different blurb or another link, that is an edit to
   src/TGameDetails.h, and the next sync brings it here.
   ───────────────────────────────────────────────────────────────────── */

/**
 * The opening paragraph of a game's blurb.
 *
 * The showcase card prints this and clamps it; the rest of the blurb goes into
 * the card too, hidden, because the filter searches the card's own text and a
 * search that only sees the first paragraph misses half of what people look a
 * MUD up by - "roleplay", "permadeath", "deutschsprachig".
 *
 * @param array<string, mixed> $game A row from mudlet_games().
 * @return string
 */
function mudlet_game_lede( array $game ): string {
	$paras = mudlet_game_paragraphs( $game );

	return $paras ? $paras[0] : '';
}

/**
 * Everything after the opening paragraph, as one run of text.
 *
 * @param array<string, mixed> $game A row from mudlet_games().
 * @return string
 */
function mudlet_game_rest( array $game ): string {
	$paras = mudlet_game_paragraphs( $game );
	array_shift( $paras );

	return implode( ' ', $paras );
}

/**
 * A game's blurb, split into paragraphs with the whitespace normalised.
 *
 * @param array<string, mixed> $game A row from mudlet_games().
 * @return array<int, string>
 */
function mudlet_game_paragraphs( array $game ): array {
	$text = trim( wp_strip_all_tags( (string) ( $game['description'] ?? '' ) ) );
	if ( '' === $text ) {
		return array();
	}

	$paras = preg_split( '/\R\s*\R/', $text );
	if ( ! is_array( $paras ) ) {
		return array();
	}

	$clean = array();
	foreach ( $paras as $para ) {
		$para = trim( (string) preg_replace( '/\s+/', ' ', $para ) );
		if ( '' !== $para ) {
			$clean[] = $para;
		}
	}

	return $clean;
}

/**
 * The line a card shows when upstream gives the game no blurb at all.
 *
 * Four of the bundled profiles carry no description. An empty slot would make
 * their cards short and the grid ragged, and inventing a sentence for them
 * would be the one thing this whole arrangement exists to avoid - so the card
 * prints the thing it does know, which is how to reach the game.
 *
 * @param array<string, mixed> $game A row from mudlet_games().
 * @return string
 */
function mudlet_game_connect( array $game ): string {
	return sprintf(
		/* translators: 1: hostname, 2: TCP port number */
		// A literal separator, not &middot;: this is plain text that the
		// templates run through esc_html(), which would print the entity.
		__( 'connect %1$s · port %2$s', 'mudlet' ),
		(string) $game['host'],
		// Not number_format_i18n(): a port is an identifier, not a quantity, and
		// grouping it gives "port 4,000" for a profile you have to type as 4000.
		(string) (int) $game['port']
	);
}

/**
 * What a game's links and flags amount to, as a short list of tags.
 *
 * Upstream's link labels are whatever whoever added the profile felt like
 * typing - "Discord", "Discord Server", "Discord Guild", "https://discord.gg/…"
 * and a bare "www.blackmud.com" are all in there. A card cannot print those and
 * a filter cannot group by them, so each link is classified by where it points
 * and printed under one name. The two connection flags join the same list,
 * because from a card's point of view "has a Discord" and "connects securely"
 * are the same kind of fact.
 *
 * @param array<string, mixed> $game A row from mudlet_games().
 * @return array<int, array{key: string, label: string, url: string}>
 */
function mudlet_game_tags( array $game ): array {
	$found = array();

	foreach ( (array) ( $game['links'] ?? array() ) as $link ) {
		$key = mudlet_game_link_kind( (string) ( $link['url'] ?? '' ), (string) ( $link['label'] ?? '' ) );
		if ( isset( $found[ $key ] ) ) {
			continue;
		}
		$found[ $key ] = (string) ( $link['url'] ?? '' );
	}

	// Ordered the way the labels are declared rather than the way upstream
	// happened to list the links, so a row of cards lines up.
	$tags = array();
	foreach ( array_keys( mudlet_game_tag_labels() ) as $key ) {
		if ( isset( $found[ $key ] ) ) {
			$tags[] = array(
				'key'   => $key,
				'label' => mudlet_game_tag_label( $key ),
				'url'   => $found[ $key ],
			);
		}
	}

	if ( ! empty( $game['tls'] ) ) {
		$tags[] = array(
			'key'   => 'secure',
			'label' => mudlet_game_tag_label( 'secure' ),
			'url'   => '',
		);
	}
	if ( ! empty( $game['own_ui'] ) ) {
		$tags[] = array(
			'key'   => 'own-ui',
			'label' => mudlet_game_tag_label( 'own-ui' ),
			'url'   => '',
		);
	}

	return $tags;
}

/**
 * Which kind of link this is, by where it points rather than what it is called.
 *
 * The URL and the label are searched together because either one can be the
 * only evidence: "Foros" and "Forums" are both /forum, and a bare
 * "Discord Guild" label sits on a discord.gg URL anyway.
 *
 * @param string $url   The link target.
 * @param string $label Upstream's label for it.
 * @return string One of the keys in mudlet_game_tag_labels().
 */
function mudlet_game_link_kind( string $url, string $label ): string {
	$hay = strtolower( $url . ' ' . $label );

	if ( str_contains( $hay, 'discord' ) ) {
		return 'discord';
	}
	if ( str_contains( $hay, 'youtube' ) || str_contains( $hay, 'youtu.be' ) ) {
		return 'youtube';
	}
	if ( str_contains( $hay, 'wiki' ) ) {
		return 'wiki';
	}
	if ( str_contains( $hay, 'forum' ) || str_contains( $hay, 'foro' ) ) {
		return 'forum';
	}

	return 'site';
}

/**
 * The one name each kind of tag is printed under, in the order cards show them.
 *
 * @return array<string, string>
 */
function mudlet_game_tag_labels(): array {
	return array(
		'site'    => __( 'Website', 'mudlet' ),
		'wiki'    => __( 'Wiki', 'mudlet' ),
		'forum'   => __( 'Forum', 'mudlet' ),
		'discord' => __( 'Discord', 'mudlet' ),
		'youtube' => __( 'YouTube', 'mudlet' ),
		// "TLS", not "Secure": a pill beside a game's name saying Secure reads
		// as a claim about the game - that it is safe, or vetted, or moderated -
		// when all it says is that the profile connects over telnets:// rather
		// than telnet://. The acronym is narrow enough to only mean the one
		// thing, and the game's own page spells it "secure connection" where
		// there is room for the sentence.
		'secure'  => __( 'TLS', 'mudlet' ),
		'own-ui'  => __( 'Own interface', 'mudlet' ),
	);
}

/**
 * The printed name for one tag key.
 *
 * @param string $key Tag key.
 * @return string
 */
function mudlet_game_tag_label( string $key ): string {
	$labels = mudlet_game_tag_labels();

	return $labels[ $key ] ?? $key;
}

/**
 * The icon each kind of tag is drawn with, keyed the same way as the labels.
 *
 * A chip is a word first: the icon is there so a row of six is scannable
 * without reading it, which is what tells "Discord" from "Wiki" at a glance in
 * a grid of forty-three cards. Anything with no obvious glyph gets none rather
 * than a vague one - a tag with an icon nobody recognises reads slower than a
 * tag with none - so this map is allowed to be shorter than the label list.
 *
 * @return array<string, string>
 */
function mudlet_game_tag_icons(): array {
	return array(
		'site'    => 'globe',
		'wiki'    => 'wiki',
		'forum'   => 'chat',
		'discord' => 'discord',
		'youtube' => 'youtube',
		'secure'  => 'lock',
		'own-ui'  => 'layout',
	);
}

/**
 * The icon name for one tag key, or '' where that tag is drawn as a word alone.
 *
 * @param string $key Tag key.
 * @return string An icon key for mudlet_icon(), or ''.
 */
function mudlet_game_tag_icon( string $key ): string {
	$icons = mudlet_game_tag_icons();

	return $icons[ $key ] ?? '';
}

/**
 * The tags worth offering as filters, with how many games each would match.
 *
 * Only the two connection flags. Where a game keeps its forum or whether it
 * runs a Discord is worth printing on the card, but nobody narrows forty-three
 * worlds down to the one they want to play by asking which have a chat server -
 * that is a fact about the project behind the game, not about the game. How it
 * connects is: telnets:// versus telnet:// is a real difference to somebody on
 * a network they do not trust, and a profile that installs its own Mudlet
 * interface is a visibly different client the moment it opens.
 *
 * Everything else the blurbs say - roleplay-enforced, permadeath, newbie
 * schools, which language the world is in - is prose, and prose is what the
 * search box is for. It reads the whole blurb, not just the three lines the
 * card shows.
 *
 * A tag matching fewer than two games is dropped: that is a link to one game,
 * not a filter. It falls out of the counts rather than being written down, so
 * a flag that upstream stops using stops appearing here on its own.
 *
 * @param array<int, array<string, mixed>> $games Rows from mudlet_games().
 * @return array<int, array{key: string, label: string, count: int}>
 */
function mudlet_game_facets( array $games ): array {
	$filterable = array( 'secure', 'own-ui' );

	$counts = array();
	foreach ( $games as $game ) {
		foreach ( mudlet_game_tags( $game ) as $tag ) {
			$counts[ $tag['key'] ] = ( $counts[ $tag['key'] ] ?? 0 ) + 1;
		}
	}

	$facets = array();
	foreach ( $filterable as $key ) {
		$n = $counts[ $key ] ?? 0;
		if ( $n < 2 ) {
			continue;
		}
		$facets[] = array(
			'key'   => $key,
			'label' => mudlet_game_tag_label( $key ),
			'count' => $n,
		);
	}

	return $facets;
}

/**
 * The one-click way into a game, for people who already have Mudlet.
 *
 * Mudlet registers itself as the handler for telnet:// and telnets:// —
 * mudlet.desktop declares both as x-scheme-handler MIME types, and main.cpp
 * hands the URI to a running instance or opens one — so a link is the whole
 * ceremony: the client comes up connected to that host and port.
 *
 * telnets:// for the profiles upstream marks as TLS, which is why the scheme is
 * derived rather than fixed: sending a secure profile to plain telnet:// would
 * connect it in the clear.
 *
 * Returns '' where a link would be a lie. The tutorial profiles point at
 * localhost:0 because Mudlet answers them itself, and a port of zero is not
 * something to hand an OS.
 *
 * @param array<string, mixed> $game A row from mudlet_games().
 * @return string A telnet:// or telnets:// URL, or '' if the game has no
 *                address worth linking.
 */
function mudlet_game_telnet_url( array $game ): string {
	$host = strtolower( trim( (string) ( $game['host'] ?? '' ) ) );
	$port = (int) ( $game['port'] ?? 0 );

	if ( '' === $host || $port < 1 || $port > 65535 ) {
		return '';
	}
	if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
		return '';
	}

	return sprintf( '%s://%s:%d', empty( $game['tls'] ) ? 'telnet' : 'telnets', rawurlencode( $host ), $port );
}

/**
 * The protocols esc_url() has to be told about before it will print one.
 *
 * wp_allowed_protocols() carries telnet but not telnets, so every esc_url()
 * around a mudlet_game_telnet_url() needs this list — without it a TLS game's
 * link is silently emptied, which is the kind of thing that only shows up on
 * the five profiles that have the flag set.
 *
 * @return array<int, string>
 */
function mudlet_telnet_protocols(): array {
	return array( 'telnet', 'telnets' );
}

/**
 * Where Mudlet Web lives.
 *
 * Its own host, not this one: the hero's client is a build of the same package
 * served from here for the same-origin reasons demo/README.md gives, but it is
 * a one-room offline world with no proxy behind it and cannot connect anybody
 * to Achaea. The public deployment can. A filter, because a fork - or a staging
 * site pointed at a branch build - should not have to patch a template.
 *
 * @return string Base URL with a trailing slash, or '' to draw no link at all.
 */
function mudlet_web_url(): string {
	return (string) apply_filters( 'mudlet_web_url', 'https://mudlet-web.mudlet.org/' );
}

/**
 * The same game, in a browser tab.
 *
 * Mudlet Web takes ?play=<slug> and opens that bundled profile connected, which
 * is the telnet:// link's other half: one for the reader who already has Mudlet
 * installed, one for the reader who has not and is not going to install
 * anything to find out whether they like MUDs.
 *
 * The slug is derived from the game's *name* with Mudlet Web's own rule -
 * `.toLowerCase().replace(/[^a-z0-9]+/g, '-')`, trimmed - rather than read off
 * post_name, which is a different rule that happens to agree on today's
 * forty-three. sanitize_title() folds accents before slugifying, so a profile
 * named "Café Noir" would be stored as `cafe-noir` and asked for as
 * `caf-noir`; and a post slug is subject to WordPress's own collision
 * suffixing, so the day a page called /games/infinity/ exists the record
 * quietly becomes `infinity-2`. Neither would show up here - the link would
 * simply open Mudlet Web on its profile list, looking like the visitor
 * mis-clicked. So this mirrors upstream's rule literally: if it changes there,
 * change it here.
 *
 * Guarded on mudlet_game_telnet_url() rather than on its own copy of the same
 * checks: Mudlet Web only offers the profiles with a real host and a port, so
 * the set of games worth linking is the set that already has an address worth
 * linking. (The tutorial and self-test profiles never reach the database at
 * all - the sync flags them internal - so this is belt and braces.)
 *
 * @param array<string, mixed> $game A row from mudlet_games().
 * @return string An https:// URL, or '' if this game has no address.
 */
function mudlet_game_web_url( array $game ): string {
	$base = mudlet_web_url();
	if ( '' === $base || '' === mudlet_game_telnet_url( $game ) ) {
		return '';
	}

	$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) ( $game['name'] ?? '' ) ) ), '-' );
	if ( '' === $slug ) {
		return '';
	}

	return add_query_arg( 'play', $slug, $base );
}
