<?php
/**
 * Rendering a changelog's Markdown.
 *
 * Parsedown is used when it is available - the older Mudlet release plugin
 * bundles and autoloads it, so on mudlet.org it always is. When it is not, the
 * fallback below handles the subset a GitHub release note actually uses rather
 * than pulling in a dependency: headings, bullet lists, links, inline code,
 * bold and italic.
 *
 * The fallback is deliberately small and deliberately strict. It is not a
 * Markdown implementation and should not grow into one; if a changelog ever
 * needs more than this, install Parsedown.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * Markdown to HTML.
 */
class Mudlet_Releases_Markdown {

	/**
	 * Render Markdown as HTML.
	 *
	 * @param string $markdown Source.
	 * @return string HTML, already escaped where it matters.
	 */
	public static function to_html( string $markdown ): string {
		$markdown = trim( str_replace( "\r", '', $markdown ) );
		if ( '' === $markdown ) {
			return '';
		}

		/**
		 * Short-circuit changelog rendering.
		 *
		 * @param string|null $html     Return a string to bypass the renderers.
		 * @param string      $markdown The Markdown source.
		 */
		$pre = apply_filters( 'mudlet_releases_pre_render', null, $markdown );
		if ( is_string( $pre ) ) {
			return $pre;
		}

		if ( class_exists( 'Parsedown' ) ) {
			$parsedown = new Parsedown();
			if ( method_exists( $parsedown, 'setSafeMode' ) ) {
				// Release notes are written by the Mudlet team, but they are
				// still fetched over the network into a page; safe mode keeps
				// raw HTML in a changelog from becoming markup on the site.
				$parsedown->setSafeMode( true );
			}
			return (string) $parsedown->text( $markdown );
		}

		return self::fallback( $markdown );
	}

	/**
	 * The dependency-free renderer.
	 *
	 * @param string $markdown Source.
	 * @return string
	 */
	private static function fallback( string $markdown ): string {
		$out     = array();
		$in_list = false;

		foreach ( explode( "\n", $markdown ) as $line ) {
			$line = rtrim( $line );

			// GitHub escapes list dashes in generated notes: "\- text".
			$line = preg_replace( '/^(\s*)\\\\([-*])\s+/', '$1$2 ', $line );

			if ( '' === trim( $line ) ) {
				if ( $in_list ) {
					$out[]   = '</ul>';
					$in_list = false;
				}
				continue;
			}

			if ( preg_match( '/^(#{1,6})\s*(.+?):?\s*$/', $line, $m ) ) {
				if ( $in_list ) {
					$out[]   = '</ul>';
					$in_list = false;
				}
				// Headings are shifted so a changelog's own "#" cannot compete
				// with the page's <h1>. GitHub's notes start at h5 anyway.
				$level = min( 6, max( 2, strlen( $m[1] ) ) );
				$out[] = sprintf( '<h%1$d>%2$s</h%1$d>', $level, self::inline( $m[2] ) );
				continue;
			}

			if ( preg_match( '/^\s*[-*]\s+(.*)$/', $line, $m ) ) {
				if ( ! $in_list ) {
					$out[]   = '<ul>';
					$in_list = true;
				}
				$out[] = '<li>' . self::inline( $m[1] ) . '</li>';
				continue;
			}

			if ( $in_list ) {
				$out[]   = '</ul>';
				$in_list = false;
			}
			$out[] = '<p>' . self::inline( $line ) . '</p>';
		}

		if ( $in_list ) {
			$out[] = '</ul>';
		}

		return implode( "\n", $out );
	}

	/**
	 * Inline formatting, on already-escaped text.
	 *
	 * Escaping happens first and the tags are added after, so a changelog
	 * cannot inject markup.
	 *
	 * @param string $text Raw inline Markdown.
	 * @return string
	 */
	private static function inline( string $text ): string {
		$text = esc_html( trim( $text ) );

		// `code`
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );
		// [label](https://url) - http(s) only, so no javascript: hrefs
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
			static function ( array $m ): string {
				return '<a href="' . esc_url( html_entity_decode( $m[2], ENT_QUOTES ) ) . '">' . $m[1] . '</a>';
			},
			$text
		);
		// **bold** then *italic*
		$text = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text );

		return $text;
	}
}
