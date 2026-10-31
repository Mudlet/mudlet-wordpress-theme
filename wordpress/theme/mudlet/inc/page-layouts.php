<?php
/**
 * /about/, /vision/ and /contribute/ - the same words, laid out as what they are.
 *
 * The three bodies are the live site's, written in the classic editor between
 * 2008 and 2021. As prose at a measure each read as one long column: About is
 * a claim, a feature list, five headed paragraphs, steps and credits; Vision is
 * a letter with a mission statement in the middle of it; Contribute is six ways
 * to help. Nothing here changes a word. It names the shapes the markup already
 * has, so the stylesheet can draw a feature list as a grid, a run of headed
 * paragraphs as cards and a mission statement as a pull quote.
 *
 * Every rule is about structure and none is about wording, so an editor can
 * rewrite a page and it keeps its look - and a shape the pass does not
 * recognise is left as prose rather than guessed at. The page body stays the
 * one place the copy is edited.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Which pages get a layout, by slug, and what is different about each.
 *
 * By slug, the way WordPress's own page-<slug>.php hierarchy would pick one -
 * a template file each would be page.php again with one line changed.
 *
 *  - cards:  the heading level whose runs become a grid of cards ('' for none).
 *  - pull:   the heading level drawn as a pull quote ('' for none).
 *  - brands: mark links to GitHub, the wiki, Patreon and the rest with their
 *            logo, and put the logos a card points at in its corner. Contribute
 *            is a page of places to go, so there the logo is the content; on
 *            About it would be a logo beside every second word.
 *
 * @return array<string, array{cards: string, pull: string, brands: bool}>
 */
function mudlet_page_layouts(): array {
	return apply_filters(
		'mudlet_page_layouts',
		array(
			'about'      => array( 'cards' => 'h3', 'pull' => '', 'brands' => false ),
			'contribute' => array( 'cards' => 'h2', 'pull' => '', 'brands' => true ),
			'vision'     => array( 'cards' => '', 'pull' => 'h4', 'brands' => false ),
		)
	);
}

/**
 * Which logo a link earns, if any.
 *
 * The language a link is about wins over the host it is on: "Lua tests" and
 * "C++ tests" are both GitHub links, and the reader is choosing between the
 * languages, not between the two folders.
 *
 * @param string $href Link target.
 * @param string $text Link text.
 * @return string An icon key, or ''.
 */
function mudlet_page_layout_brand( string $href, string $text ): string {
	$lang = mudlet_page_layout_langs( $text );
	if ( $lang ) {
		return $lang[0];
	}
	$host  = strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) );
	$hosts = array(
		'github.com'         => 'github',
		'wiki.mudlet.org'    => 'wiki',
		'patreon.com'        => 'patreon',
		'opencollective.com' => 'opencollective',
		'paypal.me'          => 'paypal',
		'paypal.com'         => 'paypal',
		'discord.gg'         => 'discord',
		'discord.com'        => 'discord',
	);
	foreach ( $hosts as $suffix => $icon ) {
		if ( $host === $suffix || str_ends_with( $host, '.' . $suffix ) ) {
			return $icon;
		}
	}
	return '';
}

/**
 * The languages a piece of text names, as icon keys.
 *
 * Language names are the one bit of wording read here, because they are not
 * translated: Lua is Lua in every locale this site has.
 *
 * @param string $text Text.
 * @return string[]
 */
function mudlet_page_layout_langs( string $text ): array {
	$found = array();
	if ( preg_match( '/C\+\+/', $text, $m, PREG_OFFSET_CAPTURE ) ) {
		$found[ $m[0][1] ] = 'cplusplus';
	}
	if ( preg_match( '/\bLua\b/', $text, $m, PREG_OFFSET_CAPTURE ) ) {
		$found[ $m[0][1] ] = 'lua';
	}
	ksort( $found );
	return array_values( $found );
}

/**
 * The layout this request's page has, or '' for plain prose.
 *
 * @return string A key of mudlet_page_layouts().
 */
function mudlet_page_layout_kind(): string {
	if ( ! is_page() ) {
		return '';
	}
	$slug = (string) get_post_field( 'post_name', get_queried_object_id() );
	return isset( mudlet_page_layouts()[ $slug ] ) ? $slug : '';
}

/**
 * Label the shapes in a page's rendered body.
 *
 * Runs before mudlet_outline(), so the rail is built from what is left: About's
 * opening claim stops being a heading (it is a sentence, and it was the rail's
 * longest entry by a paragraph), and the row of anchor links above it goes,
 * because the outline is that row.
 *
 * @param string $html Rendered content.
 * @param string $kind A key of mudlet_page_layouts().
 * @return string
 */
function mudlet_page_layout( string $html, string $kind ): string {
	$opts = mudlet_page_layouts()[ $kind ] ?? null;
	if ( ! $opts || '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
		return $html;
	}

	$doc      = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	// The XML declaration is how libxml is told this is UTF-8.
	$doc->loadHTML(
		'<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>',
		LIBXML_HTML_NODEFDTD | LIBXML_NONET
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	$body = $doc->getElementsByTagName( 'body' )->item( 0 );
	if ( ! $body ) {
		return $html;
	}

	$blocks = static function () use ( $body ): array {
		return array_values(
			array_filter(
				iterator_to_array( $body->childNodes ),
				static fn ( DOMNode $n ): bool => XML_ELEMENT_NODE === $n->nodeType
			)
		);
	};

	// A paragraph of nothing but in-page links is a table of contents, and
	// the outline is one already. A paragraph of nothing at all is <!--more-->.
	foreach ( $blocks() as $el ) {
		if ( 'p' !== $el->nodeName ) {
			continue;
		}
		if ( '' === mudlet_page_layout_bare( $el->textContent ) && ! $el->getElementsByTagName( 'img' )->length ) {
			$body->removeChild( $el );
			continue;
		}
		if ( $el->getElementsByTagName( 'a' )->length > 1 && mudlet_page_layout_only_anchors( $el ) ) {
			$body->removeChild( $el );
		}
	}

	mudlet_page_layout_intro( $doc, $body, $blocks()[0] ?? null );

	// A run of headings at the card level is a run of cards: each takes
	// everything up to the next heading, and adjacent ones share a grid.
	if ( $opts['cards'] ) {
		$rank = (int) substr( $opts['cards'], 1 );
		$grid = null;
		foreach ( $blocks() as $el ) {
			if ( $opts['cards'] !== $el->nodeName ) {
				if ( preg_match( '/^h([1-6])$/', $el->nodeName, $h ) && (int) $h[1] < $rank ) {
					$grid = null;
				}
				continue;
			}
			if ( ! $grid ) {
				$grid = mudlet_page_layout_el( $doc, 'div', 'pl-cards' );
				$body->insertBefore( $grid, $el );
			}
			$card = mudlet_page_layout_el( $doc, 'section', 'pl-card' );
			$grid->appendChild( $card );

			$next = mudlet_page_layout_next( $el );
			$card->appendChild( $el );
			while ( $next && ! preg_match( '/^h[1-6]$/', $next->nodeName ) ) {
				$after = mudlet_page_layout_next( $next );
				$card->appendChild( $next );
				$next = $after;
			}
		}
	}

	$last = null;
	foreach ( $blocks() as $el ) {
		// Already moved inside the list before it, below.
		if ( $el->parentNode !== $body ) {
			continue;
		}
		$last = $el;

		if ( $opts['pull'] && $opts['pull'] === $el->nodeName ) {
			// A heading that is a sentence is the page's thesis, not a section
			// of it: the outline only reads h2s, so nothing is lost by it.
			$quote = mudlet_page_layout_el( $doc, 'p', 'pl-pull' );
			while ( $el->firstChild ) {
				$quote->appendChild( $el->firstChild );
			}
			$body->replaceChild( $quote, $el );
			$last = $quote;
			continue;
		}

		switch ( $el->nodeName ) {
			case 'ul':
				mudlet_page_layout_class( $el, mudlet_page_layout_list_kind( $el ) );
				break;

			case 'ol':
				// "... or play a game of your choosing:" and then a second list:
				// the second one is the third step's, and is drawn inside it.
				mudlet_page_layout_class( $el, 'pl-steps' );
				$next = mudlet_page_layout_next( $el );
				$item = null;
				foreach ( $el->childNodes as $li ) {
					if ( 'li' === $li->nodeName ) {
						$item = $li;
					}
				}
				if ( $item && $next && 'ol' === $next->nodeName ) {
					mudlet_page_layout_class( $next, 'pl-substeps' );
					$item->appendChild( $next );
				}
				break;

			case 'p':
				$shot = mudlet_page_layout_shot( $doc, $el );
				if ( $shot ) {
					$last = $shot;
					break;
				}
				// One link, and it is the download page: that is the download card.
				$links = $el->getElementsByTagName( 'a' );
				if ( 1 === $links->length && preg_match( '#/download/?(?:[?\#].*)?$#', $links->item( 0 )->getAttribute( 'href' ) ) ) {
					$last = mudlet_page_layout_download( $doc, $el, $links->item( 0 )->getAttribute( 'href' ) );
				}
				break;

			case 'h2':
				// A section that is one short line - "GPL2+" - is a fact, not a
				// paragraph, and is drawn as a badge rather than left as a word
				// on its own at the bottom of the page. The mark is copyleft
				// because the only such line this site has is its licence.
				$only  = mudlet_page_layout_next( $el );
				$after = $only ? mudlet_page_layout_next( $only ) : null;
				if (
					$only && 'p' === $only->nodeName
					&& ( ! $after || preg_match( '/^h[1-6]$/', $after->nodeName ) )
					&& 0 === $only->getElementsByTagName( 'a' )->length
					&& mb_strlen( trim( $only->textContent ) ) <= 24
				) {
					mudlet_page_layout_class( $only, 'pl-stamp' );
					$only->insertBefore( $doc->createComment( 'pl-icon:copyleft' ), $only->firstChild );
				}
				break;
		}
	}

	if ( $opts['brands'] ) {
		mudlet_page_layout_brands( $doc, $body );
	}

	// "- Vadi." on a line of its own at the end is a signature.
	if ( $last && 'p' === $last->nodeName && preg_match( '/^\s*[-–—]\s*\S.{0,40}$/u', $last->textContent ) ) {
		mudlet_page_layout_class( $last, 'pl-sign' );
	}

	$out = '';
	foreach ( iterator_to_array( $body->childNodes ) as $node ) {
		$out .= $doc->saveHTML( $node );
	}

	// Icons go in as comments and come out as markup: an SVG built through
	// the HTML DOM is serialised by rules that are not SVG's.
	return (string) preg_replace_callback(
		'/<!--pl-icon:([a-z0-9]+)-->/',
		static fn ( array $m ): string => mudlet_get_icon( $m[1], 'pl-ico pl-ico--' . $m[1] ),
		$out
	);
}

/**
 * Turn "Download for free from mudlet.org" into a download card.
 *
 * The sentence stays as it was written. Around it go the three platforms and a
 * button carrying the current version, which is read from the same release the
 * download page draws, so it is never a number somebody forgot to bump.
 *
 * @param DOMDocument $doc  Document.
 * @param DOMElement  $p    The paragraph.
 * @param string      $href Where its link goes.
 * @return DOMElement The card.
 */
function mudlet_page_layout_download( DOMDocument $doc, DOMElement $p, string $href ): DOMElement {
	$card = mudlet_page_layout_el( $doc, 'div', 'pl-dl' );
	$p->parentNode->replaceChild( $card, $p );

	$os = mudlet_page_layout_el( $doc, 'span', 'pl-dl__os' );
	$os->setAttribute( 'aria-hidden', 'true' );
	foreach ( array( 'windows', 'apple', 'linux' ) as $icon ) {
		$os->appendChild( $doc->createComment( 'pl-icon:' . $icon ) );
	}

	$text = mudlet_page_layout_el( $doc, 'div', 'pl-dl__text' );
	mudlet_page_layout_class( $p, 'pl-dl__say' );
	$text->appendChild( $os );
	$text->appendChild( $p );

	$version = function_exists( 'mudlet_release_version' ) ? mudlet_release_version() : '';
	$date    = function_exists( 'mudlet_release_date' ) ? mudlet_release_date() : '';
	if ( '' !== $date ) {
		/* translators: %s: release date */
		$text->appendChild( mudlet_page_layout_text( $doc, 'span', 'pl-dl__when', sprintf( __( 'released %s', 'mudlet' ), $date ) ) );
	}
	$card->appendChild( $text );

	$button = mudlet_page_layout_el( $doc, 'a', 'pl-dl__btn' );
	$button->setAttribute( 'href', $href );
	$button->appendChild( $doc->createComment( 'pl-icon:download' ) );
	$button->appendChild(
		$doc->createTextNode(
			'' !== $version
				/* translators: %s: version number */
				? sprintf( __( 'Mudlet %s', 'mudlet' ), $version )
				: __( 'Download', 'mudlet' )
		)
	);
	$card->appendChild( $button );

	return $card;
}

/**
 * Logos on the links, and in the corner of each card the logos it points at.
 *
 * A card's corner also carries the languages its text names, which is how
 * "Either C++ or Lua can be used here" gets both marks with no link to either.
 *
 * @param DOMDocument $doc  Document.
 * @param DOMElement  $body Its body.
 */
function mudlet_page_layout_brands( DOMDocument $doc, DOMElement $body ): void {
	foreach ( iterator_to_array( $body->getElementsByTagName( 'a' ) ) as $a ) {
		$brand = mudlet_page_layout_brand( $a->getAttribute( 'href' ), $a->textContent );
		if ( $brand ) {
			mudlet_page_layout_class( $a, 'pl-brand' );
			$a->setAttribute( 'data-brand', $brand );
			$a->insertBefore( $doc->createComment( 'pl-icon:' . $brand ), $a->firstChild );
		}
	}

	$xpath = new DOMXPath( $doc );
	foreach ( iterator_to_array( $xpath->query( '//section[contains(concat(" ", @class, " "), " pl-card ")]' ) ) as $card ) {
		$marks = array();
		foreach ( $card->getElementsByTagName( 'a' ) as $a ) {
			$marks[] = $a->getAttribute( 'data-brand' );
		}
		$marks = array_slice( array_values( array_unique( array_filter( array_merge( $marks, mudlet_page_layout_langs( $card->textContent ) ) ) ) ), 0, 3 );
		if ( ! $marks ) {
			continue;
		}
		$logos = mudlet_page_layout_el( $doc, 'span', 'pl-logos' );
		$logos->setAttribute( 'aria-hidden', 'true' );
		foreach ( $marks as $mark ) {
			$logos->appendChild( $doc->createComment( 'pl-icon:' . $mark ) );
		}
		mudlet_page_layout_class( $card, 'pl-card--logos' );
		$card->insertBefore( $logos, $card->firstChild );
	}

	foreach ( iterator_to_array( $xpath->query( '//a[@data-brand]' ) ) as $a ) {
		$a->removeAttribute( 'data-brand' );
	}
}

/**
 * An element with a class and some text in it.
 *
 * @param DOMDocument $doc   Document.
 * @param string      $tag   Tag name.
 * @param string      $class Class.
 * @param string      $text  Text.
 * @return DOMElement
 */
function mudlet_page_layout_text( DOMDocument $doc, string $tag, string $class, string $text ): DOMElement {
	$el = mudlet_page_layout_el( $doc, $tag, $class );
	$el->appendChild( $doc->createTextNode( $text ) );
	return $el;
}

/**
 * Gather the page's opening into a banner.
 *
 * Three openings are recognised: an h2 that is a sentence (About's claim) and
 * what follows it up to the next heading; a blockquote (Vision's); and the
 * paragraphs before the first heading (Contribute's).
 *
 * @param DOMDocument     $doc   Document.
 * @param DOMElement      $body  Its body.
 * @param DOMElement|null $first The first block.
 */
function mudlet_page_layout_intro( DOMDocument $doc, DOMElement $body, ?DOMElement $first ): void {
	if ( ! $first ) {
		return;
	}
	$intro = mudlet_page_layout_el( $doc, 'div', 'pl-intro' );

	if ( 'blockquote' === $first->nodeName ) {
		$body->replaceChild( $intro, $first );
		$lede = null;
		while ( $first->firstChild ) {
			$child = $first->firstChild;
			if ( 'p' === $child->nodeName ) {
				mudlet_page_layout_class( $child, 'pl-lede' );
				$intro->appendChild( $child );
				$lede = null;
				continue;
			}
			// A classic-editor quote holds its text bare.
			if ( ! $lede ) {
				if ( XML_TEXT_NODE === $child->nodeType && '' === trim( $child->textContent ) ) {
					$first->removeChild( $child );
					continue;
				}
				$lede = mudlet_page_layout_el( $doc, 'p', 'pl-lede' );
				$intro->appendChild( $lede );
			}
			$lede->appendChild( $child );
		}
		return;
	}

	if ( 'h2' === $first->nodeName ) {
		$claim = mudlet_page_layout_el( $doc, 'p', 'pl-claim' );
		if ( $first->hasAttribute( 'id' ) ) {
			$claim->setAttribute( 'id', $first->getAttribute( 'id' ) );
		}
		while ( $first->firstChild ) {
			$claim->appendChild( $first->firstChild );
		}
		$body->replaceChild( $intro, $first );
		$intro->appendChild( $claim );
	} elseif ( 'p' === $first->nodeName ) {
		$body->insertBefore( $intro, $first );
	} else {
		return;
	}

	$next = 'p' === $first->nodeName ? $first : mudlet_page_layout_next( $intro );
	while ( $next && 'p' === $next->nodeName && ! mudlet_page_layout_is_shot( $next ) ) {
		$after = mudlet_page_layout_next( $next );
		mudlet_page_layout_class( $next, 'pl-lede' );
		$intro->appendChild( $next );
		$next = $after;
	}

	// An opening paragraph with no heading after it is the whole page.
	if ( ! $next && 'p' === $first->nodeName ) {
		while ( $intro->firstChild ) {
			$body->insertBefore( $intro->firstChild, $intro );
		}
		$body->removeChild( $intro );
	}
}

/**
 * Whether a paragraph is nothing but a picture (or a picture in a link).
 *
 * @param DOMElement $p Paragraph.
 * @return bool
 */
function mudlet_page_layout_is_shot( DOMElement $p ): bool {
	return 1 === $p->getElementsByTagName( 'img' )->length
		&& '' === mudlet_page_layout_bare( $p->textContent );
}

/**
 * Turn a paragraph holding one picture into a figure, at a size worth seeing.
 *
 * The classic editor inserted 300px "medium" copies; at the measure they are
 * stamps. A known attachment gets its own srcset, and a picture that is only a
 * URL takes the full-size file its link already points at.
 *
 * @param DOMDocument $doc Document.
 * @param DOMElement  $p   Paragraph.
 * @return DOMElement|null The figure, or null if this was not a picture.
 */
function mudlet_page_layout_shot( DOMDocument $doc, DOMElement $p ): ?DOMElement {
	if ( ! mudlet_page_layout_is_shot( $p ) ) {
		return null;
	}
	$img   = $p->getElementsByTagName( 'img' )->item( 0 );
	$class = $img->getAttribute( 'class' );
	$align = preg_match( '/\balign(left|right)\b/', $class, $a ) ? ' pl-shot--' . $a[1] : '';

	$id  = preg_match( '/\bwp-image-(\d+)\b/', $class, $m ) ? (int) $m[1] : 0;
	$src = $id ? wp_get_attachment_image_src( $id, 'large' ) : false;
	if ( $src ) {
		$img->setAttribute( 'src', $src[0] );
		$img->setAttribute( 'width', (string) $src[1] );
		$img->setAttribute( 'height', (string) $src[2] );
		$set = wp_get_attachment_image_srcset( $id, 'large' );
		if ( $set ) {
			$img->setAttribute( 'srcset', $set );
			$img->setAttribute( 'sizes', '(max-width: 46rem) 100vw, 46rem' );
		}
	} else {
		$link = 'a' === $img->parentNode->nodeName ? $img->parentNode->getAttribute( 'href' ) : '';
		if ( preg_match( '/\.(?:png|jpe?g|gif|webp|avif)$/i', (string) wp_parse_url( $link, PHP_URL_PATH ) ) ) {
			$img->setAttribute( 'src', $link );
		}
	}
	$img->setAttribute( 'loading', 'lazy' );

	$figure = mudlet_page_layout_el( $doc, 'figure', 'pl-shot' . $align );
	while ( $p->firstChild ) {
		$figure->appendChild( $p->firstChild );
	}
	$p->parentNode->replaceChild( $figure, $p );

	return $figure;
}

/**
 * An element with a class on it.
 *
 * @param DOMDocument $doc   Document.
 * @param string      $tag   Tag name.
 * @param string      $class Class.
 * @return DOMElement
 */
function mudlet_page_layout_el( DOMDocument $doc, string $tag, string $class ): DOMElement {
	$el = $doc->createElement( $tag );
	$el->setAttribute( 'class', $class );
	return $el;
}

/**
 * Add a class to an element, keeping any it has.
 *
 * @param DOMElement $el    Element.
 * @param string     $class Class, or '' to do nothing.
 */
function mudlet_page_layout_class( DOMElement $el, string $class ): void {
	if ( '' !== $class ) {
		$el->setAttribute( 'class', trim( $el->getAttribute( 'class' ) . ' ' . $class ) );
	}
}

/**
 * The next element sibling, skipping the whitespace between blocks.
 *
 * @param DOMNode $node Node to start from.
 * @return DOMElement|null
 */
function mudlet_page_layout_next( DOMNode $node ): ?DOMElement {
	for ( $n = $node->nextSibling; $n; $n = $n->nextSibling ) {
		if ( $n instanceof DOMElement ) {
			return $n;
		}
		if ( '' !== trim( $n->textContent ) ) {
			return null;
		}
	}
	return null;
}

/**
 * Whether every link in an element is a #fragment and nothing else is said.
 *
 * @param DOMElement $el Element to check.
 * @return bool
 */
function mudlet_page_layout_only_anchors( DOMElement $el ): bool {
	$said = $el->textContent;
	foreach ( $el->getElementsByTagName( 'a' ) as $a ) {
		if ( ! str_starts_with( $a->getAttribute( 'href' ), '#' ) ) {
			return false;
		}
		$said = str_replace( $a->textContent, '', $said );
	}
	return '' === mudlet_page_layout_bare( $said );
}

/**
 * Text with the whitespace and the separators a row of links uses taken out.
 *
 * @param string $text Text.
 * @return string
 */
function mudlet_page_layout_bare( string $text ): string {
	return (string) preg_replace( '/[\s\x{a0}•·|]+/u', '', $text );
}

/**
 * What a bulleted list is, by its shape.
 *
 * Every item a single link is a row of credits; every item short and
 * unlinked is a list of features. Anything else is prose and is left alone.
 *
 * @param DOMElement $ul The list.
 * @return string Class to add, or ''.
 */
function mudlet_page_layout_list_kind( DOMElement $ul ): string {
	$items = array();
	foreach ( $ul->childNodes as $li ) {
		if ( 'li' === $li->nodeName ) {
			$items[] = $li;
		}
	}
	if ( ! $items ) {
		return '';
	}

	$chips = true;
	$feats = true;
	foreach ( $items as $li ) {
		$links = $li->getElementsByTagName( 'a' );
		$text  = trim( $li->textContent );
		$chips = $chips && 1 === $links->length && trim( $links->item( 0 )->textContent ) === $text;
		$feats = $feats && 0 === $links->length && mb_strlen( $text ) <= 64;
	}

	if ( $chips ) {
		return 'pl-chips';
	}
	return $feats ? 'pl-feats' : '';
}
