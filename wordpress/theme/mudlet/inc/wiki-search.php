<?php
/**
 * The wiki, in the site's own search.
 *
 * Most of what anybody searches mudlet.org for is on wiki.mudlet.org: the
 * manual, the Lua API, the build instructions, the known issues. The site's own
 * search reads pages, posts, games and makers - forty pages of marketing and a
 * news log - so a visitor typing `tempTimer` into the palette got "No matches."
 * from a site whose header links to the page that answers them.
 *
 * So the palette asks the wiki as well, and the results page prints what it
 * said. The rows are labelled `Wiki`, never merged into the site's, and they
 * come last: this is the site's search with the manual behind it, not a search
 * engine for two sites.
 *
 * ## Why this is server-side, which is not the obvious answer
 *
 * The wiki is behind Cloudflare, and the rule in front of it is stricter than
 * the wiki is:
 *
 *     /api.php               403, browser and server alike (managed challenge)
 *     /w/Special:Search      403, the same
 *     /index.php?search=     200, but it is a page for a person to read
 *     /rest.php/v1/search/*  200, JSON, and it does not care who is asking
 *
 * MediaWiki's REST search is therefore the only way in - and it answers with no
 * `Access-Control-Allow-Origin` at all, so the browser cannot ask it directly.
 * That settles a question this file would otherwise have to argue about: the
 * request is made here, by the site, and cached here.
 *
 * It also means `/index.php?search=` is the right destination for a human - the
 * one search URL on that host a visitor can actually open.
 *
 * ## The cache, which inc/search.php argues against
 *
 * That file keeps no transient, because a cache keyed by whatever a visitor
 * typed is an unbounded pile of rows nothing reads twice. The reasoning holds
 * for a `WP_Query` and inverts for this: a local query costs a millisecond and
 * this costs an outbound request on somebody else's server, on the pause in
 * somebody's typing. So the answers are kept - hits for hours, because the
 * manual does not change while you are typing, and failures for minutes, so an
 * unreachable wiki costs one request rather than one per keystroke.
 *
 * The pile is bounded on the way in rather than on the way out: the term is
 * trimmed, lower-cased and length-capped before it becomes a key, and a query
 * short enough to match half the wiki is never asked at all.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * How many wiki rows the palette shows under the site's own.
 *
 * Three, not eight. The panel holds ten rows, and both halves plus the two ways
 * out of them have to be visible in it without scrolling - the palette cuts the
 * site's half to five for the same reason. A block nobody scrolls to is a block
 * that is not there, which is what eight and then four looked like.
 */
const MUDLET_WIKI_SEARCH_LIMIT = 3;

/**
 * How many the results page shows, where there is room for them.
 */
const MUDLET_WIKI_SEARCH_PAGE_LIMIT = 6;

/**
 * How long a good answer is kept.
 */
const MUDLET_WIKI_SEARCH_TTL = 6 * HOUR_IN_SECONDS;

/**
 * How long a bad one is. Long enough that an outage is not one request a
 * keystroke, short enough that the wiki coming back is noticed within tea.
 */
const MUDLET_WIKI_SEARCH_FAIL_TTL = 5 * MINUTE_IN_SECONDS;

/**
 * The wiki.
 *
 * @return string Origin, no trailing slash.
 */
function mudlet_wiki_origin(): string {
	/**
	 * Filter the wiki this site searches.
	 *
	 * @param string $origin Origin, no trailing slash.
	 */
	return untrailingslashit( (string) apply_filters( 'mudlet_wiki_origin', 'https://wiki.mudlet.org' ) );
}

/**
 * Whether to ask the wiki at all.
 *
 * A site behind a firewall, a fork with no wiki, or an editor who would rather
 * the search box stayed the site's own: one filter turns off the route, the
 * palette's second request and the block on the results page together.
 *
 * @return bool
 */
function mudlet_wiki_search_enabled(): bool {
	/**
	 * Filter whether the wiki is searched alongside the site.
	 *
	 * @param bool $enabled Whether to search the wiki.
	 */
	return (bool) apply_filters( 'mudlet_wiki_search_enabled', true );
}

/**
 * Where a visitor goes to see the whole answer.
 *
 * `index.php`, not `Special:Search`, which is the path Cloudflare challenges -
 * see the header. MediaWiki serves the same page from both.
 *
 * @param string $query Search term.
 * @return string
 */
function mudlet_wiki_search_url( string $query ): string {
	return add_query_arg(
		array(
			'search'   => rawurlencode( $query ),
			'fulltext' => '1',
		),
		mudlet_wiki_origin() . '/index.php'
	);
}

/**
 * A page on the wiki, from the key the search answered with.
 *
 * The key is the title with underscores for spaces, and it keeps its namespace
 * colon - `Manual:Trigger_Engine`. Neither survives `rawurlencode()` in a form
 * MediaWiki reads back, so the path is assembled and only the parts that must
 * be escaped are.
 *
 * @param string $key Page key.
 * @return string
 */
function mudlet_wiki_page_url( string $key ): string {
	$path = implode( '/', array_map( 'rawurlencode', explode( '/', $key ) ) );

	// A namespace colon is legal in a path and is what the wiki's own links use.
	return mudlet_wiki_origin() . '/w/' . str_replace( '%3A', ':', $path );
}

/**
 * What the wiki says about one term.
 *
 * Always an array, and always safe to render: an unreachable wiki, a wiki that
 * answered with something other than JSON, and a wiki with nothing to say are
 * one shape - no rows, and a caller that draws nothing.
 *
 * @param string $query Search term.
 * @param int    $limit How many rows to return.
 * @param string $lang  Language slug being browsed, or '' for none.
 * @return array{rows:array<int,array{title:string,url:string,snippet:string}>,more:bool,url:string}
 */
function mudlet_wiki_search( string $query, int $limit = MUDLET_WIKI_SEARCH_LIMIT, string $lang = '' ): array {
	$query = trim( $query );
	$limit = max( 1, min( 20, $limit ) );
	$empty = array(
		'rows' => array(),
		'more' => false,
		'url'  => mudlet_wiki_search_url( $query ),
	);

	// The same floor inc/search.php applies, for the same reason, plus a
	// ceiling: a hundred characters is a search, and anything past it is
	// somebody pasting a log into the box.
	if ( ! mudlet_wiki_search_enabled() || mb_strlen( $query ) < 2 ) {
		return $empty;
	}
	$query = mb_substr( $query, 0, 100 );

	$key    = 'mudlet_wiki_s_' . md5( mb_strtolower( $query ) . '|' . $limit . '|' . $lang );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return array_merge( $empty, $cached );
	}

	// Over-asked, and not by a little. The manual is translated as `/de`, `/fr`,
	// `/pl` subpages of the same page, so the wiki's own first fourteen answers
	// for `trigger` are two pages and twelve translations of one of them. Those
	// are folded together below, and a limit of four that answers two because
	// the other ten were the same page in Finnish is a limit nobody set.
	$ask   = min( 50, max( 20, $limit * 5 ) );
	$found = mudlet_wiki_search_get( $query, $ask );

	if ( null === $found ) {
		set_transient( $key, $empty, MUDLET_WIKI_SEARCH_FAIL_TTL );
		return $empty;
	}

	$rows = array();
	$rank = array(); // how good the copy in $rows is, per page
	$at   = array(); // where that page landed, so a better copy can replace it
	foreach ( $found as $page ) {
		$title = (string) ( $page['title'] ?? '' );
		$slug  = (string) ( $page['key'] ?? '' );
		$text  = (string) ( $page['excerpt'] ?? '' );

		if ( '' === $title || '' === $slug || mudlet_wiki_search_is_redirect( $text ) ) {
			continue;
		}

		$copy = mudlet_wiki_search_copy( $title, $lang );
		if ( null === $copy ) {
			continue;
		}

		$row = array(
			// The page rather than the copy: a reader of English has no use for
			// the `/en` on the end of a title, having asked in English. The link
			// still goes to the copy.
			'title'   => $copy['page'],
			'url'     => mudlet_wiki_page_url( $slug ),
			'snippet' => mudlet_wiki_search_snippet( $text ),
		);
		$page_key = mb_strtolower( $copy['page'] );

		// One row per page, in the order the wiki ranked them: a later, better
		// copy takes the place the first one is holding rather than a new one.
		if ( isset( $at[ $page_key ] ) ) {
			if ( $copy['rank'] < $rank[ $page_key ] ) {
				$rows[ $at[ $page_key ] ] = $row;
				$rank[ $page_key ]        = $copy['rank'];
			}
			continue;
		}

		$at[ $page_key ]   = count( $rows );
		$rank[ $page_key ] = $copy['rank'];
		$rows[]            = $row;
	}

	$out = array(
		// More than fits, either because what survived the fold still does or
		// because the wiki filled the limit it was asked for. Counted rather
		// than assumed, and never a number: MediaWiki's REST search answers
		// rows and no total, and a total this file worked out for itself would
		// be a guess printed as a fact.
		'more' => count( $rows ) > $limit || count( $found ) >= $ask,
		'rows' => array_slice( $rows, 0, $limit ),
		'url'  => $empty['url'],
	);

	set_transient( $key, $out, MUDLET_WIKI_SEARCH_TTL );

	return $out;
}

/**
 * One anonymous GET at MediaWiki's REST search, decoded.
 *
 * Short timeout for the same reason inc/discord.php has one: this runs while
 * somebody is waiting, and a slow wiki must cost the search its wiki rows
 * rather than cost the visitor the answer.
 *
 * @param string $query Search term.
 * @param int    $limit How many pages to ask for.
 * @return array<int, array<string, mixed>>|null Pages, or null on any failure.
 */
function mudlet_wiki_search_get( string $query, int $limit ): ?array {
	$url = add_query_arg(
		array(
			'q'     => rawurlencode( $query ),
			'limit' => $limit,
		),
		mudlet_wiki_origin() . '/rest.php/v1/search/page'
	);

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 4,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	return isset( $data['pages'] ) && is_array( $data['pages'] ) ? $data['pages'] : null;
}

/**
 * Whether a hit is a redirect rather than a page.
 *
 * `Manaul:Trigger Engine` is a typo somebody kindly pointed at the real page
 * fifteen years ago. It matches every search the page it points at does, and it
 * is the same page under a spelling nobody meant.
 *
 * @param string $text The excerpt the wiki answered with.
 * @return bool
 */
function mudlet_wiki_search_is_redirect( string $text ): bool {
	return 0 === stripos( ltrim( wp_strip_all_tags( $text ) ), '#REDIRECT' );
}

/**
 * Which copy of a page a hit is, and whether to keep it at all.
 *
 * The manual is translated in place: `Manual:Lua API` has `/de`, `/fr`, `/pl`
 * and a dozen more hanging off it, and the wiki ranks them all against the same
 * term. Printed as they come, four rows are one page in four languages.
 *
 * So a hit is reduced to the page it is a copy of, plus how good a copy it is
 * for the language being read - the reader's own language first, the untagged
 * page second, and every other language not at all. The caller keeps one row
 * per page and lets a better copy replace a worse one.
 *
 * @param string $title Page title.
 * @param string $lang  Language slug being browsed, or '' for none.
 * @return array{page:string,rank:int}|null Null when this copy is not for us.
 */
function mudlet_wiki_search_copy( string $title, string $lang ): ?array {
	if ( ! preg_match( '~^(.*)/([a-z]{2}(?:-[a-z]{2,8})?)$~', $title, $parts ) ) {
		return array(
			'page' => $title,
			'rank' => 1,
		);
	}

	if ( '' === $lang || strtolower( $lang ) !== $parts[2] ) {
		return null;
	}

	return array(
		'page' => $parts[1],
		'rank' => 0,
	);
}

/**
 * The line under a wiki result.
 *
 * The wiki marks the match with `<span class="searchmatch">` and puts the
 * page's own **wikitext** around it, unrendered: `{{#description2:...}}`,
 * `[[Manual:Mapper|the mapper]]`, `= Mapper =`. So this is not tag-stripping,
 * it is the small part of a wikitext parser a one-line excerpt needs.
 *
 * The one rule worth stating: a heading is recognised by its *pair* of equals
 * signs, not by the character. `= Mapper =` and `==Simple Trigger Matching==`
 * go; `x = 1` in a page full of Lua stays, because half this wiki is Lua and a
 * snippet that quietly eats assignments is worse than one with a stray `=`.
 *
 * The result is text. A bold word in a two-line excerpt is not worth carrying
 * somebody else's HTML onto this site for.
 *
 * @param string $text Excerpt as the wiki answered it.
 * @return string
 */
function mudlet_wiki_search_snippet( string $text ): string {
	$text = wp_strip_all_tags( $text );

	// Twice, because there are two layers and each pass uncovers the next: a
	// page that wrote `<code>` about a function shipped it escaped, so it
	// survives the strip above as text, and parts of this wiki are escaped
	// twice over, where one pass turns `&amp;mdash;` into `&mdash;` and stops.
	// Tags are named rather than stripped wholesale, because `if x < 3 then` is
	// what half of this wiki is made of, `<color>` is how it writes a
	// placeholder, and strip_tags() would eat both.
	for ( $pass = 0; $pass < 2; $pass++ ) {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = (string) preg_replace( '/<!--.*?-->/u', '', $text );
		$text = (string) preg_replace(
			'~</?(?:code|nowiki|pre|br|b|i|u|tt|s|small|sup|sub|span|div|ref|syntaxhighlight|source'
				. '|gallery|translate|tvar|languages|includeonly|noinclude|onlyinclude)(?:\s[^<>]*)?/?>~i',
			'',
			$text
		);
	}

	$text = (string) preg_replace( '/\s+/u', ' ', $text );

	// __NOTOC__ and its relatives, which are instructions to the wiki and not
	// words on the page.
	$text = (string) preg_replace( '/__[A-Z]+__/', '', $text );

	// `{{#description2:...}}` is unwrapped rather than dropped: it is the
	// sentence the page wrote about itself, which is the best thing there could
	// be under a search result, and a great many manual pages open with one.
	// Deleting it with the other templates left two rows in twenty with a title
	// and nothing under it.
	$text = (string) preg_replace( '/\{\{\s*#description2\s*:\s*/iu', '', $text );

	// Templates, innermost first, so a nested one does not leave its outer
	// braces behind. Three passes is deeper than any excerpt goes.
	for ( $pass = 0; $pass < 3; $pass++ ) {
		$text = (string) preg_replace( '/\{\{[^{}]*\}\}/u', ' ', $text );
	}
	// What the window left of a template it cut in half, and the two halves are
	// not the same. After an unclosed `{{` is a template's name and parameters,
	// which is never prose; before an unopened `}}` is a template's *content*,
	// which often is - so that one loses its braces and keeps its words.
	$text = (string) preg_replace( '/\{\{.*$/u', '', $text );
	$text = str_replace( '}}', ' ', $text );
	$text = (string) preg_replace( '/^\s*#[a-z0-9_-]+\s*:\s*/iu', '', $text ); // its name, if that came too

	$text = (string) preg_replace( '/\{\|.*?\|\}/u', ' ', $text ); // a whole table
	// A table's own scaffolding: the attributes on a cell, and the marks that
	// start a row or end the table. Named attributes rather than "anything
	// after a pipe", because a pipe is also punctuation somebody typed.
	$text = (string) preg_replace(
		'/\|?\s*(?:colspan|rowspan|align|valign|style|class|width|height|bgcolor|scope)\s*=\s*"[^"]*"\s*\|?/iu',
		' ',
		$text
	);
	$text = (string) preg_replace( '/(^|\s)\|[-+}]\s*/u', '$1', $text );
	$text = str_replace( '||', ' ', $text ); // one cell from the next, which is how "By || gesslar" reads

	// A file, an image or a category is a thing on the page rather than words
	// in it, and its caption is written for a picture nobody here is showing.
	$text = (string) preg_replace( '/\[\[\s*(?:File|Image|Media|Category)\s*:[^\]]*\]\]/iu', '', $text );
	// A link is the words on the right of its pipe, or all of it when there is
	// no pipe: [[Manual:Mapper|the mapper]] is "the mapper".
	$text = (string) preg_replace( '/\[\[(?:[^\]|]*\|)?([^\]|]*)\]\]/u', '$1', $text );
	// An external link is its label, and nothing when it has none.
	$text = (string) preg_replace( '~\[(?:https?|mailto):\S+(?:\s+([^\]]*))?\]~u', '$1', $text );
	// An excerpt is a window on a page and can open in the middle of a comment,
	// leaving one end of it behind with nothing to close.
	$text = str_replace( array( '<!--', '-->' ), '', $text );
	$text = (string) preg_replace( "/'{2,}/", '', $text ); // bold and italic marks
	// A heading, by its pair of marks; then one whose pair the window cut off.
	$text = (string) preg_replace( '/(^|\s)(=+)\s*([^=]{1,120}?)\s*\2(?=\s|$)/u', '$1$3', $text );
	$text = (string) preg_replace( '/^\s*=+\s*|\s*=+\s*$/u', '', $text );
	// A bullet, an indent or a definition term: a mark at the start of a line,
	// which is a mark after a space once the lines have been folded into one.
	// Two are left out on purpose - `#` starts a numbered list and also
	// `#RRGGBB`, and a mark in front of a digit is arithmetic (`2 * 3`), not a
	// list nobody can see the newline of.
	$text = (string) preg_replace( '/(^|\s)[*;:]+\s*(?=[^\s\d])/u', '$1', $text );
	$text = mudlet_wiki_search_pairs( $text );

	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ), " |-\u{2013}\u{2014}" );

	return mb_strlen( $text ) > 180 ? mb_substr( $text, 0, 179 ) . '…' : $text;
}

/**
 * Brackets that lost their partner to the window.
 *
 * An excerpt is a few dozen characters cut out of the middle of a page, so a
 * `[` can arrive with its `]` a paragraph away and unquoted. Everything above
 * matches pairs, and what a pair-matcher leaves behind is exactly the halves.
 *
 * Only brackets and braces, and deliberately not parentheses: `expandAlias(`
 * survives a cut the same way, but a stray parenthesis reads as punctuation
 * somebody typed, while a stray `]` never does.
 *
 * @param string $text Snippet, part-way cleaned.
 * @return string
 */
function mudlet_wiki_search_pairs( string $text ): string {
	foreach ( array( array( '[', ']' ), array( '{', '}' ) ) as $pair ) {
		list( $open, $close ) = $pair;

		$length = strlen( $text );
		$stack  = array();
		$lone   = array();

		// Byte by byte, which is safe for these four: no continuation byte of a
		// multi-byte character ever equals an ASCII one.
		for ( $at = 0; $at < $length; $at++ ) {
			if ( $open === $text[ $at ] ) {
				$stack[] = $at;
			} elseif ( $close === $text[ $at ] ) {
				if ( $stack ) {
					array_pop( $stack );
				} else {
					$lone[ $at ] = true;
				}
			}
		}

		foreach ( $stack as $at ) {
			$lone[ $at ] = true;
		}
		if ( ! $lone ) {
			continue;
		}

		$kept = '';
		for ( $at = 0; $at < $length; $at++ ) {
			if ( ! isset( $lone[ $at ] ) ) {
				$kept .= $text[ $at ];
			}
		}
		$text = $kept;
	}

	return $text;
}

/**
 * The wiki's rows in the shape the palette's are.
 *
 * `[title, source, url]`, so the palette needs to know nothing about where a
 * row came from beyond the word in its right-hand column.
 *
 * @param array<int, array{title:string,url:string,snippet:string}> $rows Rows.
 * @return array<int, array{0:string,1:string,2:string}>
 */
function mudlet_wiki_search_rows( array $rows ): array {
	$out = array();
	foreach ( $rows as $row ) {
		$out[] = array( $row['title'], __( 'Wiki', 'mudlet' ), $row['url'] );
	}

	return $out;
}

/**
 * Register the route.
 *
 * A second route rather than a second half of `mudlet/v1/search`, because the
 * two are not the same speed: the site answers out of the database and the wiki
 * over somebody else's network. One route would make every palette keystroke
 * wait for the slower of the two. Two lets the palette draw the site's rows the
 * moment they land and the wiki's whenever they do.
 */
function mudlet_wiki_search_register(): void {
	register_rest_route(
		'mudlet/v1',
		'/search/wiki',
		array(
			'methods'             => WP_REST_Server::READABLE,
			// Public, the same as the search it belongs to: it reads a public
			// wiki on behalf of the site's own search box.
			'permission_callback' => '__return_true',
			'callback'            => 'mudlet_wiki_search_response',
			'args'                => array(
				'q'    => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'lang' => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_key',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'mudlet_wiki_search_register' );

/**
 * The wiki's answer for one term.
 *
 * @param WP_REST_Request $request The request.
 * @return WP_REST_Response
 */
function mudlet_wiki_search_response( WP_REST_Request $request ): WP_REST_Response {
	$query = trim( (string) $request->get_param( 'q' ) );
	$found = mudlet_wiki_search( $query, MUDLET_WIKI_SEARCH_LIMIT, (string) $request->get_param( 'lang' ) );

	$response = rest_ensure_response(
		array(
			'rows' => mudlet_wiki_search_rows( $found['rows'] ),
			'more' => $found['more'],
			'url'  => $found['url'],
		)
	);
	$response->header( 'Cache-Control', 'public, max-age=300' );

	return $response;
}
