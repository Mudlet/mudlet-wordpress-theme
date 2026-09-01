<?php
/**
 * A post's own words, as Markdown.
 *
 * The inverse of class-markdown.php next door. That one renders a GitHub
 * changelog into the page; this one renders the page's announcement back out
 * as Markdown for the GitHub release that changelog came from, so an
 * announcement is written once - in the editor - and pasted above the
 * generated notes, rather than written twice and left to drift.
 *
 * ---------------------------------------------------------------------------
 *
 * What it leaves out is everything nobody typed.
 *
 * The changelog, the contributor list and the download table are not in
 * post_content at all: the theme appends them at render time (single.php) and
 * the [mudlet_release] shortcode injects the changelog through the_content. So
 * reading the *stored* content rather than the rendered page gets the authored
 * half for free, and the two shortcodes that would pull the generated half back
 * in are stripped explicitly - a GitHub release already carries its own
 * changelog, and exporting ours would print it twice on its own page.
 *
 * ---------------------------------------------------------------------------
 *
 * Why this is not a block-by-block translator.
 *
 * Every block is rendered to HTML by WordPress itself and then walked as a DOM
 * tree. A block this file has never heard of therefore comes out as its
 * markup's Markdown instead of vanishing, and the nesting rules - a list inside
 * a list item, a paragraph inside a quote - are written once in the walker
 * rather than once per block.
 *
 * Exactly three shapes are intercepted before that point:
 *
 *   - the release shortcodes, for the reason above;
 *   - core/embed, whose rendered markup is a provider iframe or a wrapper div
 *     and whose attribute is the URL we actually want;
 *   - mudlet/games, which stores slugs and would otherwise arrive as a wall of
 *     card markup - see the games plugin's block.
 *
 * This is a converter for the subset of HTML a post is made of, not a general
 * one. Anything Markdown cannot express - two columns, an image beside prose -
 * flattens, which is the honest answer rather than a bug.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post content to Markdown.
 */
class Mudlet_Releases_Markdown_Export {

	/**
	 * Elements that live inside a line rather than starting one.
	 *
	 * Anything not listed here is treated as block level, which is the safe way
	 * round: an unknown wrapper becomes a container to recurse into and its
	 * contents survive.
	 *
	 * @var string[]
	 */
	private const INLINE = array(
		'a', 'abbr', 'b', 'bdi', 'bdo', 'br', 'cite', 'code', 'data', 'del',
		'dfn', 'em', 'font', 'i', 'img', 'ins', 'kbd', 'label', 'mark', 'q',
		's', 'samp', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'time',
		'u', 'var', 'wbr',
	);

	/**
	 * Elements whose contents are of no interest at all.
	 *
	 * @var string[]
	 */
	private const DROP = array( 'script', 'style', 'noscript', 'template', 'svg', 'button', 'form' );

	/**
	 * A post, as Markdown.
	 *
	 * @param WP_Post|int|null     $post Post. Defaults to the current one.
	 * @param array<string, mixed> $args 'link' (bool, append the permalink
	 *                                   footer) and 'title' (bool, lead with
	 *                                   the post title as an H1).
	 * @return string Markdown, or '' when there is no post.
	 */
	public static function post( $post = null, array $args = array() ): string {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'link'  => true,
				'title' => false,
			)
		);

		$markdown = self::content( (string) $post->post_content );

		// A post whose body is nothing but the release shortcode has nothing
		// to export, and a footer on its own would be a link to the page the
		// reader is already on.
		if ( '' === trim( $markdown ) ) {
			return '';
		}

		if ( $args['title'] ) {
			// Decoded, because get_the_title() has been through wptexturize and
			// hands back "5.x &#8211; Release Template".
			$title = html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' );
			$title = trim( $title );
			if ( '' !== $title ) {
				$markdown = '# ' . self::escape( $title ) . "\n\n" . $markdown;
			}
		}

		if ( $args['link'] ) {
			$permalink = (string) get_permalink( $post );
			$host      = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			if ( '' !== $permalink ) {
				/* translators: %s: the site's host name, e.g. mudlet.org */
				$label    = sprintf( __( 'Read the full announcement on %s', 'mudlet-releases' ), $host );
				$markdown = rtrim( $markdown ) . "\n\n---\n\n"
					. '[' . self::escape( $label ) . '](' . self::target( $permalink ) . ')';
			}
		}

		$markdown = trim( (string) preg_replace( "/\n{3,}/", "\n\n", $markdown ) ) . "\n";

		/**
		 * Filter a post's exported Markdown.
		 *
		 * @param string               $markdown The Markdown.
		 * @param WP_Post              $post     The post it came from.
		 * @param array<string, mixed> $args     Export arguments.
		 */
		return (string) apply_filters( 'mudlet_releases_post_markdown', $markdown, $post, $args );
	}

	/**
	 * Stored post content, as Markdown.
	 *
	 * @param string $content Raw post_content.
	 * @return string
	 */
	public static function content( string $content ): string {
		$chunks = array();

		foreach ( parse_blocks( $content ) as $block ) {
			$chunk = trim( self::block( $block ) );
			if ( '' !== $chunk ) {
				$chunks[] = $chunk;
			}
		}

		return implode( "\n\n", $chunks );
	}

	// -- blocks --------------------------------------------------------

	/**
	 * One block, as Markdown.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return string
	 */
	private static function block( array $block ): string {
		$name  = (string) ( $block['blockName'] ?? '' );
		$attrs = (array) ( $block['attrs'] ?? array() );

		// Classic content and bare [shortcode] blocks: the stored HTML, with
		// the generated parts taken out and everything else rendered.
		if ( '' === $name || 'core/shortcode' === $name || 'core/freeform' === $name ) {
			$html = self::strip_generated( (string) ( $block['innerHTML'] ?? '' ) );
			if ( '' === trim( $html ) ) {
				return '';
			}
			return self::html( wpautop( do_shortcode( $html ) ) );
		}

		// An embed's markup is a provider iframe or a wrapper div; its URL is
		// the only part Markdown can carry, and it is right there in the attrs.
		if ( 'core/embed' === $name ) {
			$url = (string) ( $attrs['url'] ?? '' );
			return '' === $url ? '' : self::target( $url, false );
		}

		if ( 'mudlet/games' === $name ) {
			return self::games( (array) ( $attrs['games'] ?? array() ) );
		}

		return self::html( (string) render_block( $block ) );
	}

	/**
	 * The games block: slugs, resolved the way the theme's cards resolve them.
	 *
	 * Reached through the games plugin's own API and guarded the way the theme
	 * guards it, so a site without that plugin exports the rest of the post
	 * rather than fataling on one block.
	 *
	 * @param string[] $slugs Game slugs, in the order the block stores them.
	 * @return string
	 */
	private static function games( array $slugs ): string {
		if ( ! function_exists( 'mudlet_game' ) ) {
			return '';
		}

		$lines = array();

		foreach ( $slugs as $slug ) {
			$game = mudlet_game( (string) $slug );
			if ( ! $game ) {
				continue;
			}

			$name = trim( (string) ( $game['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}

			$href = (string) ( $game['site'] ?? '' );
			if ( '' === $href ) {
				$href = (string) ( $game['url'] ?? '' );
			}

			// The first paragraph of the stored description, at the length the
			// theme's card uses. Not prose from the post: a line that repeats a
			// record is a line that disagrees with it a year later.
			$blurb = (string) ( $game['description'] ?? '' );
			$blurb = (string) preg_split( '/\R{2,}/', trim( $blurb ) )[0];
			// Entities decoded and the ellipsis written out: this is going into
			// Markdown, and "the&hellip;" is what happens when it is assumed to
			// be going into HTML.
			$blurb = html_entity_decode( wp_strip_all_tags( $blurb ), ENT_QUOTES, 'UTF-8' );
			$blurb = wp_trim_words( $blurb, 38, '…' );

			$line = '- ' . ( '' !== $href
				? '[' . self::escape( $name ) . '](' . self::target( $href ) . ')'
				: '**' . self::escape( $name ) . '**' );

			if ( '' !== $blurb ) {
				$line .= ' - ' . self::escape( $blurb );
			}

			$lines[] = $line;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Take out what the release plugin puts in.
	 *
	 * Both shortcodes are matched by hand rather than through
	 * strip_shortcodes(), because when the older plugin is active it owns
	 * [MudletRelease] and ours is never registered.
	 *
	 * @param string $html Stored HTML.
	 * @return string
	 */
	private static function strip_generated( string $html ): string {
		$html = (string) preg_replace( '/\[mudlet_release\b[^\]]*\]/i', '', $html );
		$html = (string) preg_replace( '#\[MudletRelease\b[^\]]*\].*?\[/MudletRelease\]#is', '', $html );

		return $html;
	}

	// -- HTML ----------------------------------------------------------

	/**
	 * Rendered HTML, as Markdown.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function html( string $html ): string {
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}

		// ext-dom is present on every WordPress host worth the name, but a
		// missing one should cost the formatting rather than the text.
		if ( ! class_exists( 'DOMDocument' ) ) {
			return self::escape( trim( (string) wp_strip_all_tags( $html ) ) );
		}

		$doc      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );

		// The XML declaration is how libxml is told this is UTF-8; without it
		// it assumes Latin-1 and every em dash in the post becomes mojibake.
		$doc->loadHTML(
			'<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>',
			LIBXML_HTML_NODEFDTD | LIBXML_NONET
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return '';
		}

		return implode( "\n\n", self::chunks( $body ) );
	}

	/**
	 * A node's children, as a list of block-level Markdown chunks.
	 *
	 * Inline children are gathered into a paragraph until a block-level one
	 * interrupts them, which is what makes stray text between two lists come
	 * out as its own paragraph.
	 *
	 * @param DOMNode $parent Node to walk.
	 * @return string[]
	 */
	private static function chunks( DOMNode $parent ): array {
		$chunks = array();
		$line   = '';

		foreach ( iterator_to_array( $parent->childNodes ) as $node ) {
			if ( XML_TEXT_NODE === $node->nodeType || XML_CDATA_SECTION_NODE === $node->nodeType ) {
				$line .= self::inline_node( $node );
				continue;
			}

			if ( XML_ELEMENT_NODE !== $node->nodeType ) {
				continue;
			}

			$tag = strtolower( $node->nodeName );

			if ( in_array( $tag, self::DROP, true ) ) {
				continue;
			}

			if ( in_array( $tag, self::INLINE, true ) ) {
				$line .= self::inline_node( $node );
				continue;
			}

			if ( '' !== trim( $line ) ) {
				$chunks[] = self::guard_line_starts( trim( $line ) );
			}
			$line = '';

			foreach ( self::element( $node, $tag ) as $chunk ) {
				if ( '' !== trim( $chunk ) ) {
					$chunks[] = rtrim( $chunk );
				}
			}
		}

		if ( '' !== trim( $line ) ) {
			$chunks[] = self::guard_line_starts( trim( $line ) );
		}

		return $chunks;
	}

	/**
	 * One block-level element, as zero or more chunks.
	 *
	 * @param DOMNode $el  Element.
	 * @param string  $tag Its lowercased name.
	 * @return string[]
	 */
	private static function element( DOMNode $el, string $tag ): array {
		switch ( $tag ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$text = trim( self::inline( $el ) );
				return '' === $text ? array() : array( str_repeat( '#', (int) substr( $tag, 1 ) ) . ' ' . $text );

			case 'p':
			case 'dd':
			case 'dt':
			case 'address':
			case 'caption':
				$text = trim( self::inline( $el ) );
				return '' === $text ? array() : array( self::guard_line_starts( $text ) );

			case 'figcaption':
				$text = trim( self::inline( $el ) );
				return '' === $text ? array() : array( '*' . $text . '*' );

			case 'ul':
			case 'ol':
				$list = self::list_items( $el, 'ol' === $tag );
				return '' === $list ? array() : array( $list );

			case 'li':
				// Only reachable from malformed markup; treat it as a line.
				$text = trim( self::inline( $el ) );
				return '' === $text ? array() : array( '- ' . $text );

			case 'blockquote':
				return array( self::quote( $el ) );

			case 'pre':
				return array( self::fence( $el ) );

			case 'hr':
				return array( '---' );

			case 'table':
				$table = self::table( $el );
				return '' === $table ? array() : array( $table );

			case 'video':
			case 'audio':
				// Markdown has no player, and a release post that shows a feature
				// off in a clip should not export as the silence around it.
				$media = self::media_src( $el );
				return '' === $media ? array() : array( self::target( $media, false ) );

			case 'iframe':
				$src = $el instanceof DOMElement ? $el->getAttribute( 'src' ) : '';
				return '' === $src ? array() : array( self::target( $src, false ) );

			case 'br':
				return array();

			default:
				// div, section, figure, article, header, footer, dl, and every
				// wrapper the block editor writes: containers, nothing more.
				return self::chunks( $el );
		}
	}

	/**
	 * Where a <video> or an <audio> keeps its file.
	 *
	 * @param DOMNode $el The element.
	 * @return string
	 */
	private static function media_src( DOMNode $el ): string {
		if ( ! $el instanceof DOMElement ) {
			return '';
		}

		$src = $el->getAttribute( 'src' );
		if ( '' === $src ) {
			$source = $el->getElementsByTagName( 'source' )->item( 0 );
			$src    = $source instanceof DOMElement ? $source->getAttribute( 'src' ) : '';
		}

		return $src;
	}

	/**
	 * A ul/ol, as Markdown.
	 *
	 * @param DOMNode $el      The list.
	 * @param bool    $ordered Numbered rather than bulleted.
	 * @return string
	 */
	private static function list_items( DOMNode $el, bool $ordered ): string {
		$lines = array();
		$n     = 1;

		if ( $ordered && $el instanceof DOMElement && $el->hasAttribute( 'start' ) ) {
			$n = max( 1, (int) $el->getAttribute( 'start' ) );
		}

		foreach ( iterator_to_array( $el->childNodes ) as $item ) {
			if ( XML_ELEMENT_NODE !== $item->nodeType || 'li' !== strtolower( $item->nodeName ) ) {
				continue;
			}

			$marker = $ordered ? $n++ . '. ' : '- ';
			$body   = implode( "\n\n", self::chunks( $item ) );

			if ( '' === trim( $body ) ) {
				continue;
			}

			// A nested list arrives as its own chunk with no indent of its own;
			// hanging every line but the first under the marker is what nests
			// it, and what keeps a wrapped paragraph inside the item.
			$lines[] = $marker . self::hang( $body, strlen( $marker ) );
		}

		return implode( "\n", $lines );
	}

	/**
	 * A blockquote, as Markdown.
	 *
	 * @param DOMNode $el The quote.
	 * @return string
	 */
	private static function quote( DOMNode $el ): string {
		$inner = implode( "\n\n", self::chunks( $el ) );
		$out   = array();

		foreach ( explode( "\n", $inner ) as $line ) {
			$out[] = '' === trim( $line ) ? '>' : '> ' . $line;
		}

		return implode( "\n", $out );
	}

	/**
	 * A <pre>, as a fenced block.
	 *
	 * @param DOMNode $el The block.
	 * @return string
	 */
	private static function fence( DOMNode $el ): string {
		$code = rtrim( (string) $el->textContent );
		$lang = '';

		if ( $el instanceof DOMElement ) {
			$class = $el->getAttribute( 'class' );
			$inner = $el->getElementsByTagName( 'code' )->item( 0 );
			if ( $inner instanceof DOMElement ) {
				$class .= ' ' . $inner->getAttribute( 'class' );
			}
			if ( preg_match( '/language-([A-Za-z0-9_+-]+)/', $class, $m ) ) {
				$lang = $m[1];
			}
		}

		// Longer than any run of backticks inside, so a post about Markdown
		// survives being written in Markdown.
		$ticks = '```';
		if ( preg_match_all( '/`+/', $code, $runs ) ) {
			$ticks = str_repeat( '`', max( 3, max( array_map( 'strlen', $runs[0] ) ) + 1 ) );
		}

		return $ticks . $lang . "\n" . $code . "\n" . $ticks;
	}

	/**
	 * A table, as a pipe table.
	 *
	 * GitHub needs a header row, so the first row becomes one whether it was
	 * marked up as one or not.
	 *
	 * @param DOMNode $el The table.
	 * @return string
	 */
	private static function table( DOMNode $el ): string {
		if ( ! $el instanceof DOMElement ) {
			return '';
		}

		$rows = array();

		foreach ( iterator_to_array( $el->getElementsByTagName( 'tr' ) ) as $tr ) {
			$cells = array();

			foreach ( iterator_to_array( $tr->childNodes ) as $cell ) {
				if ( XML_ELEMENT_NODE !== $cell->nodeType ) {
					continue;
				}
				if ( ! in_array( strtolower( $cell->nodeName ), array( 'th', 'td' ), true ) ) {
					continue;
				}
				$text    = trim( (string) preg_replace( '/\s*\n\s*/', ' ', self::inline( $cell ) ) );
				$cells[] = str_replace( '|', '\|', $text );
			}

			if ( $cells ) {
				$rows[] = $cells;
			}
		}

		if ( ! $rows ) {
			return '';
		}

		$width = max( array_map( 'count', $rows ) );
		$out   = array();

		foreach ( $rows as $i => $cells ) {
			$cells = array_pad( $cells, $width, '' );
			$out[] = '| ' . implode( ' | ', $cells ) . ' |';
			if ( 0 === $i ) {
				$out[] = '| ' . implode( ' | ', array_fill( 0, $width, '---' ) ) . ' |';
			}
		}

		return implode( "\n", $out );
	}

	// -- inline --------------------------------------------------------

	/**
	 * A node's children, as one line of Markdown.
	 *
	 * @param DOMNode $parent Node.
	 * @return string
	 */
	private static function inline( DOMNode $parent ): string {
		$out = '';

		foreach ( iterator_to_array( $parent->childNodes ) as $node ) {
			$out .= self::inline_node( $node );
		}

		return $out;
	}

	/**
	 * One node, as inline Markdown.
	 *
	 * @param DOMNode $node Node.
	 * @return string
	 */
	private static function inline_node( DOMNode $node ): string {
		if ( XML_TEXT_NODE === $node->nodeType || XML_CDATA_SECTION_NODE === $node->nodeType ) {
			// HTML collapses runs of whitespace and so does this, which is why
			// the indentation the editor writes into its markup does not end up
			// in the export.
			return self::escape( (string) preg_replace( '/\s+/u', ' ', (string) $node->nodeValue ) );
		}

		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return '';
		}

		$tag = strtolower( $node->nodeName );

		if ( in_array( $tag, self::DROP, true ) ) {
			return '';
		}

		switch ( $tag ) {
			case 'br':
				// Two spaces: a line break inside a paragraph, rather than the
				// start of a new one.
				return "  \n";

			case 'img':
				return self::image( $node );

			case 'a':
				$href  = $node instanceof DOMElement ? $node->getAttribute( 'href' ) : '';
				$label = trim( self::inline( $node ) );
				if ( '' === $href ) {
					return $label;
				}
				if ( '' === $label ) {
					$label = self::escape( $href );
				}
				return '[' . $label . '](' . self::target( $href ) . ')';

			case 'strong':
			case 'b':
				$text = self::inline( $node );
				return '' === trim( $text ) ? $text : self::wrap( $text, '**' );

			case 'em':
			case 'i':
				$text = self::inline( $node );
				return '' === trim( $text ) ? $text : self::wrap( $text, '*' );

			case 'del':
			case 's':
			case 'strike':
				$text = self::inline( $node );
				return '' === trim( $text ) ? $text : self::wrap( $text, '~~' );

			case 'code':
			case 'samp':
			case 'kbd':
				$code = trim( (string) preg_replace( '/\s+/u', ' ', (string) $node->textContent ) );
				if ( '' === $code ) {
					return '';
				}
				$ticks = '`';
				if ( preg_match_all( '/`+/', $code, $runs ) ) {
					$ticks = str_repeat( '`', max( array_map( 'strlen', $runs[0] ) ) + 1 );
				}
				return $ticks . $code . $ticks;

			default:
				// span, sup, cite, the editor's own wrappers, and anything
				// block-level that turns up mid-sentence: keep the words.
				return in_array( $tag, self::INLINE, true )
					? self::inline( $node )
					: implode( ' ', self::chunks( $node ) );
		}
	}

	/**
	 * An image.
	 *
	 * @param DOMNode $node The img.
	 * @return string
	 */
	private static function image( DOMNode $node ): string {
		if ( ! $node instanceof DOMElement ) {
			return '';
		}

		$src = $node->getAttribute( 'src' );
		if ( '' === $src ) {
			return '';
		}

		$alt   = self::escape( trim( $node->getAttribute( 'alt' ) ) );
		$title = trim( $node->getAttribute( 'title' ) );
		$tail  = '' === $title ? '' : ' "' . str_replace( '"', '\"', $title ) . '"';

		return '![' . $alt . '](' . self::target( $src ) . $tail . ')';
	}

	/**
	 * Wrap inline text in emphasis without swallowing the spaces around it.
	 *
	 * "<strong> word </strong>" with the markers hard against the spaces is not
	 * emphasis at all in CommonMark, so the padding moves outside.
	 *
	 * @param string $text   Inner text.
	 * @param string $marker Marker.
	 * @return string
	 */
	private static function wrap( string $text, string $marker ): string {
		preg_match( '/^(\s*)(.*?)(\s*)$/su', $text, $m );

		return $m[1] . $marker . $m[2] . $marker . $m[3];
	}

	// -- text ----------------------------------------------------------

	/**
	 * Escape the characters Markdown would otherwise read as formatting.
	 *
	 * Deliberately not the whole CommonMark punctuation set: escaping every dot
	 * and bracket produces correct Markdown that nobody can read in a GitHub
	 * release's edit box, which is where this text is going.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	private static function escape( string $text ): string {
		$text = str_replace( '\\', '\\\\', $text );
		$text = (string) preg_replace( '/([`*\[\]<])/u', '\\\\$1', $text );

		// Underscores only where they could actually open emphasis: one inside
		// a word never does, and snake_case is common in anything Mudlet
		// writes about.
		$text = (string) preg_replace( '/(^|[^\p{L}\p{N}])_/u', '$1\\\\_', $text );
		$text = (string) preg_replace( '/_($|[^\p{L}\p{N}])/u', '\\\\_$1', $text );

		return $text;
	}

	/**
	 * Stop a line of prose from reading as a heading, a quote or a list item.
	 *
	 * Only ever applied to text that is already at the start of its own line,
	 * so a "-" mid-sentence is left alone.
	 *
	 * @param string $text Paragraph.
	 * @return string
	 */
	private static function guard_line_starts( string $text ): string {
		$lines = explode( "\n", $text );

		foreach ( $lines as $i => $line ) {
			$lines[ $i ] = (string) preg_replace( '/^(\s*)(#{1,6}|>|[-+]|=+|\d+[.)])(\s)/', '$1\\\\$2$3', $line );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Every line but the first, indented under a list marker.
	 *
	 * @param string $text  Item body.
	 * @param int    $width Marker width.
	 * @return string
	 */
	private static function hang( string $text, int $width ): string {
		$pad   = str_repeat( ' ', $width );
		$lines = explode( "\n", $text );

		foreach ( $lines as $i => $line ) {
			if ( 0 === $i || '' === trim( $line ) ) {
				continue;
			}
			$lines[ $i ] = $pad . $line;
		}

		return implode( "\n", $lines );
	}

	/**
	 * A URL, absolute and safe to put inside (...).
	 *
	 * Absolute because the export is read on github.com, where "/wp-content/..."
	 * and "/download/" mean something else entirely.
	 *
	 * @param string $url     Possibly relative URL.
	 * @param bool   $bracket Wrap in <> when it holds characters that would end
	 *                        the link early. Off for a bare URL on its own
	 *                        line, which GitHub autolinks.
	 * @return string
	 */
	private static function target( string $url, bool $bracket = true ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		if ( ! str_starts_with( $url, '#' ) && class_exists( 'WP_Http' ) ) {
			$absolute = WP_Http::make_absolute_url( $url, home_url( '/' ) );
			if ( is_string( $absolute ) && '' !== $absolute ) {
				$url = $absolute;
			}
		}

		if ( $bracket && preg_match( '/[\s()<>]/', $url ) ) {
			return '<' . str_replace( array( '<', '>' ), array( '%3C', '%3E' ), $url ) . '>';
		}

		return $url;
	}
}
