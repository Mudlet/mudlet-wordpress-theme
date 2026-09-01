<?php
/**
 * Reading Mudlet's credits out of the client's source.
 *
 * ---------------------------------------------------------------------------
 *
 * Why raw.githubusercontent.com and not the API.
 *
 * api.github.com allows 60 requests an hour unauthenticated. A first sync is
 * one file plus eighteen avatars, and requiring a token to draw a credits page
 * is a bad trade. raw.githubusercontent.com is CDN-served, needs no auth, and
 * is not under that cap.
 *
 * ---------------------------------------------------------------------------
 *
 * Why there is a C++ parser in a WordPress plugin.
 *
 * The same answer as the games plugin next door: upstream has no JSON, no API
 * and no generated manifest. The credits are a QVector a human appends to, each
 * entry a brace-initialised struct whose last field is a tr() of a dozen
 * adjacent string literals, with translator comments in between. Asking
 * upstream to publish a machine-readable copy is the better long-term answer;
 * until then, this reads what exists.
 *
 * It is a scanner rather than a regex because `//`, `,` and `{}` all occur
 * inside the data — several descriptions carry a URL or an <a href="…"> — so
 * anything not string-aware corrupts entries rather than failing loudly.
 *
 * Parsed shape, per maker:
 *
 *   name, core (the dialog's own "drawn larger" flag), github, discord,
 *   avatar (filename), avatar_url, description (upstream's HTML)
 *
 * Plus two things that belong to the page rather than to a person:
 * acknowledgements (the prose under the credits) and supporters (the patreon
 * names, by tier).
 *
 * @package Mudlet_Makers
 */

defined( 'ABSPATH' ) || exit;

/**
 * The upstream source of the makers list.
 */
class Mudlet_Makers_Source {

	const REPO   = 'Mudlet/Mudlet';
	const REF    = 'development';
	const DIALOG = 'src/dlgAboutDialog.cpp';

	/**
	 * Base URL for raw files, filterable so a fork or a pinned tag can be used.
	 *
	 * @return string
	 */
	public static function raw_base(): string {
		$repo = (string) apply_filters( 'mudlet_makers_repo', self::REPO );
		$ref  = (string) apply_filters( 'mudlet_makers_ref', self::REF );

		return "https://raw.githubusercontent.com/{$repo}/{$ref}";
	}

	/**
	 * Fetch one file from the repository.
	 *
	 * @param string $path Repo-relative path.
	 * @return string|null Body, or null if it could not be fetched.
	 */
	public static function fetch( string $path ): ?string {
		$response = wp_remote_get(
			self::raw_base() . '/' . ltrim( $path, '/' ),
			array(
				'timeout'    => 20,
				'user-agent' => 'mudlet-makers/' . MUDLET_MAKERS_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		return '' === $body ? null : $body;
	}

	/**
	 * Where a maker's avatar comes from.
	 *
	 * Not the Mudlet repository — GitHub's own avatar redirect, which is the
	 * only picture of these people that exists anywhere the project controls.
	 * Makers with no GitHub handle have no avatar and are drawn as initials;
	 * that is a third of the list, so the monogram is the normal case, not an
	 * error path.
	 *
	 * @param string $github Handle.
	 * @return string
	 */
	public static function avatar_url( string $github ): string {
		return '' === $github ? '' : "https://github.com/{$github}.png?size=200";
	}

	/**
	 * The makers, read live from upstream.
	 *
	 * @return array{makers: array<int, array<string, mixed>>, acknowledgements: array<int, string>, supporters: array<string, array<int, string>>, sha256: string}|null
	 */
	public static function pull(): ?array {
		$file = self::fetch( self::DIALOG );
		if ( null === $file ) {
			return null;
		}

		$src = self::strip_comments( $file );

		return array(
			'sha256'           => hash( 'sha256', $file ),
			'makers'           => self::parse( $src ),
			'acknowledgements' => self::acknowledgements( $src ),
			'supporters'       => self::supporters( $file ),
		);
	}

	// ── the parser ────────────────────────────────────────────────────

	/**
	 * Parse the dialog into maker rows.
	 *
	 * `aboutMakers.append({big, name, discord, github, email, description});`
	 *
	 * `big` is the dialog's own distinction between the people running Mudlet
	 * now — it draws their names larger — and everyone else who has left a mark
	 * on it. It is the only grouping upstream states, so it is the only one
	 * recorded here; the website does not get to promote or retire anybody.
	 *
	 * The email field is read and discarded. See the plugin header.
	 *
	 * @param string $src Comment-free source.
	 * @return array<int, array<string, mixed>>
	 */
	public static function parse( string $src ): array {
		$makers = array();
		$offset = 0;

		while ( preg_match( '/aboutMakers\s*\.\s*append\s*\(/', $src, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
			$open = $m[0][1] + strlen( $m[0][0] ) - 1;
			$call = self::balanced( $src, $open );
			if ( null === $call ) {
				break;
			}
			$offset = $open + strlen( $call ) + 2;

			$brace = strpos( $call, '{' );
			if ( false === $brace ) {
				continue;
			}

			$inner = self::balanced( $call, $brace );
			if ( null === $inner ) {
				continue;
			}

			$fields = array_map( 'trim', self::split_top( $inner, ',' ) );
			$at     = static fn( int $i ) => $fields[ $i ] ?? '';
			$first  = static fn( int $i ) => self::literals( $at( $i ) )[0] ?? '';

			$name = $first( 1 );
			if ( '' === $name ) {
				continue;
			}

			$github = $first( 3 );

			$makers[] = array(
				'name'   => $name,
				'core'   => (bool) preg_match( '/\btrue\b/', $at( 0 ) ),
				'github' => $github,
				// The "name#1234" form Discord has since retired. Kept because
				// upstream keeps it, not because it resolves to anything.
				'discord'     => $first( 2 ),
				'avatar'      => '' === $github ? '' : $github . '.png',
				'avatar_url'  => self::avatar_url( $github ),
				// Upstream's own words, and upstream's own HTML: a few carry an
				// <a> or an <i>, and one an &amp;.
				'description' => implode( '', self::literals( $at( 5 ) ) ),
			);
		}

		return $makers;
	}

	/**
	 * The prose under the credits: the "others too have made their mark" note,
	 * the icon attributions, and the thanks to people who never committed a
	 * line but shaped Mudlet anyway.
	 *
	 * Worth carrying because it is the half of the credits that is *about* the
	 * list — it says out loud that the list is incomplete and where the rest of
	 * the names are.
	 *
	 * @param string $src Comment-free source.
	 * @return array<int, string> One entry per paragraph.
	 */
	public static function acknowledgements( string $src ): array {
		$prose  = '';
		$offset = 0;

		// Two calls share the name: the loop appending each maker's generated
		// HTML, which holds no literals at all, and the prose. Take whatever
		// has words in it.
		while ( preg_match( '/aboutMudletBody\s*\.\s*append\s*\(/', $src, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
			$open = $m[0][1] + strlen( $m[0][0] ) - 1;
			$call = self::balanced( $src, $open );
			if ( null === $call ) {
				break;
			}
			$offset = $open + strlen( $call ) + 2;
			$prose .= implode( '', self::literals( $call ) );
		}

		$out = array();

		foreach ( preg_split( '#</p>#i', $prose ) as $para ) {
			$para = preg_replace( '#<p[^>]*>#i', '', $para );
			$para = preg_replace( '#<br\s*/?>#i', '', (string) $para );
			// The dialog colours names by hand to match its own palette. A web
			// page has a stylesheet; unwrap the spans, keep the emphasis.
			$para = preg_replace( '#</?span[^>]*>#i', '', (string) $para );
			// And one address rides along in the prose. Same rule as the
			// makers themselves: out it goes, brackets and all.
			$para = preg_replace( '/\s*\(\s*[^\s()<>@]+@[^\s()<>@]+\s*\)/', '', (string) $para );
			$para = trim( (string) preg_replace( '/\s+/', ' ', (string) $para ) );

			if ( '' !== $para ) {
				$out[] = $para;
			}
		}

		return $out;
	}

	/**
	 * The patreon supporters: the sentence the dialog frames them with, and the
	 * names, in the two tiers it draws them in.
	 *
	 * Given the **raw** file, not the comment-stripped copy. That sentence is a
	 * C++ raw string literal, and strip_comments() does not know about those —
	 * the https:// inside it reads as a line comment and eats the rest of the
	 * line. Nothing here needs comments gone: literals() ignores them anyway.
	 *
	 * @param string $file Contents of dlgAboutDialog.cpp, as fetched.
	 * @return array{intro: string, mightier_than_swords: array<int, string>, on_a_plaque: array<int, string>}
	 */
	public static function supporters( string $file ): array {
		$tier = static function ( string $var ) use ( $file ): array {
			if ( ! preg_match( '/QStringList\s+' . preg_quote( $var, '/' ) . '\s*=/', $file, $m, PREG_OFFSET_CAPTURE ) ) {
				return array();
			}
			$open = strpos( $file, '{', $m[0][1] );
			if ( false === $open ) {
				return array();
			}
			$list = self::balanced( $file, $open );

			return null === $list ? array() : array_map( 'trim', self::literals( $list ) );
		};

		return array(
			'intro'                => self::supporters_intro( $file ),
			'mightier_than_swords' => $tier( 'mightier_than_swords' ),
			'on_a_plaque'          => $tier( 'on_a_plaque' ),
		);
	}

	/**
	 * The sentence above the patreon names, in upstream's own words.
	 *
	 * The page has no sword frames or plaques to paint the names onto, so this
	 * is all the framing there is — and it beats one written here, which is the
	 * whole argument of this plugin.
	 *
	 * It exists twice: Steam builds get a copy with the outbound link stripped,
	 * so the linked one is the one a web page wants.
	 *
	 * @param string $file Contents of dlgAboutDialog.cpp, as fetched.
	 * @return string
	 */
	private static function supporters_intro( string $file ): string {
		// Qualified, because the constructor calls this function forty lines in
		// and an unqualified search lands on that call.
		if ( ! preg_match( '/dlgAboutDialog::setSupportersTab\s*\(/', $file, $m, PREG_OFFSET_CAPTURE ) ) {
			return '';
		}

		$open = strpos( $file, '{', $m[0][1] );
		$body = false === $open ? null : self::balanced( $file, $open );
		if ( null === $body ) {
			return '';
		}

		// R"(…)" and R"tag(…)tag" — the credits are ordinary literals, but this
		// sentence carries an <a href="…"> and is written raw to avoid the
		// backslashes that would otherwise need.
		if ( ! preg_match_all( '/R"([A-Za-z_]*)\((.*?)\)\1"/s', $body, $found, PREG_SET_ORDER ) ) {
			return '';
		}

		$said = array_values(
			array_filter(
				array_column( $found, 2 ),
				static fn( string $t ): bool => (bool) preg_match( '/patreon/i', $t )
			)
		);

		usort(
			$said,
			static fn( string $x, string $y ): int => (int) (bool) preg_match( '/<a\s/i', $y ) - (int) (bool) preg_match( '/<a\s/i', $x )
		);

		$intro = $said[0] ?? '';
		$intro = preg_replace( '#<br\s*/?>#i', ' ', $intro );

		return trim( (string) preg_replace( '/\s+/', ' ', (string) $intro ) );
	}

	// ── scanners ──────────────────────────────────────────────────────

	/**
	 * Strip // and block comments without touching string literals.
	 *
	 * @param string $src Source.
	 * @return string
	 */
	private static function strip_comments( string $src ): string {
		$out = '';
		$len = strlen( $src );

		for ( $i = 0; $i < $len; $i++ ) {
			$c = $src[ $i ];

			if ( '"' === $c ) {
				$out .= $c;
				for ( $i++; $i < $len; $i++ ) {
					$out .= $src[ $i ];
					if ( '\\' === $src[ $i ] ) {
						$out .= $src[ ++$i ] ?? '';
						continue;
					}
					if ( '"' === $src[ $i ] ) {
						break;
					}
				}
				continue;
			}

			if ( '/' === $c && '/' === ( $src[ $i + 1 ] ?? '' ) ) {
				while ( $i < $len && "\n" !== $src[ $i ] ) {
					$i++;
				}
				$out .= "\n";
				continue;
			}

			if ( '/' === $c && '*' === ( $src[ $i + 1 ] ?? '' ) ) {
				$i += 2;
				while ( $i < $len && ! ( '*' === $src[ $i ] && '/' === ( $src[ $i + 1 ] ?? '' ) ) ) {
					$i++;
				}
				$i++;
				continue;
			}

			$out .= $c;
		}

		return $out;
	}

	/**
	 * What sits between the bracket at `$open` and its match, string-aware.
	 *
	 * @param string $src  Source.
	 * @param int    $open Index of an opening bracket.
	 * @return string|null
	 */
	private static function balanced( string $src, int $open ): ?string {
		$pairs = array(
			'{' => '}',
			'(' => ')',
			'[' => ']',
		);

		$opener = $src[ $open ] ?? '';
		$closer = $pairs[ $opener ] ?? '';
		if ( '' === $closer ) {
			return null;
		}

		$depth = 0;
		$len   = strlen( $src );

		for ( $i = $open; $i < $len; $i++ ) {
			if ( '"' === $src[ $i ] ) {
				for ( $i++; $i < $len && '"' !== $src[ $i ]; $i++ ) {
					if ( '\\' === $src[ $i ] ) {
						$i++;
					}
				}
				continue;
			}
			if ( $opener === $src[ $i ] ) {
				$depth++;
			} elseif ( $closer === $src[ $i ] ) {
				if ( 0 === --$depth ) {
					return substr( $src, $open + 1, $i - $open - 1 );
				}
			}
		}

		return null;
	}

	/**
	 * Split on `$sep` at nesting depth zero, ignoring string literals.
	 *
	 * @param string $src Source.
	 * @param string $sep One-character separator.
	 * @return array<int, string>
	 */
	private static function split_top( string $src, string $sep ): array {
		$parts = array();
		$depth = 0;
		$start = 0;
		$len   = strlen( $src );

		for ( $i = 0; $i < $len; $i++ ) {
			$c = $src[ $i ];

			if ( '"' === $c ) {
				for ( $i++; $i < $len && '"' !== $src[ $i ]; $i++ ) {
					if ( '\\' === $src[ $i ] ) {
						$i++;
					}
				}
				continue;
			}

			if ( '{' === $c || '(' === $c || '[' === $c ) {
				$depth++;
			} elseif ( '}' === $c || ')' === $c || ']' === $c ) {
				$depth--;
			} elseif ( $sep === $c && 0 === $depth ) {
				$parts[] = substr( $src, $start, $i - $start );
				$start   = $i + 1;
			}
		}

		$parts[] = substr( $src, $start );

		return $parts;
	}

	/**
	 * Every string literal in an expression, unescaped.
	 *
	 * C++ concatenates adjacent literals, which is how every description here
	 * is written, so callers join what they get back. `QString()` yields
	 * nothing, which is how an absent handle arrives.
	 *
	 * @param string $expr Expression.
	 * @return array<int, string>
	 */
	private static function literals( string $expr ): array {
		$out = array();
		$len = strlen( $expr );

		for ( $i = 0; $i < $len; $i++ ) {
			if ( '"' !== $expr[ $i ] ) {
				continue;
			}

			$s = '';
			for ( $i++; $i < $len && '"' !== $expr[ $i ]; $i++ ) {
				if ( '\\' === $expr[ $i ] ) {
					$esc = $expr[ ++$i ] ?? '';
					$s  .= 'n' === $esc ? "\n" : ( 't' === $esc ? "\t" : $esc );
					continue;
				}
				$s .= $expr[ $i ];
			}
			$out[] = $s;
		}

		return $out;
	}
}
