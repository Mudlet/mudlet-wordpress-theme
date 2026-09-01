<?php
/**
 * Reading Mudlet's bundled profiles out of the client's source.
 *
 * ---------------------------------------------------------------------------
 *
 * Why raw.githubusercontent.com and not the API.
 *
 * api.github.com allows 60 requests an hour unauthenticated. A first sync is
 * one header plus forty icons, so the API would rate-limit the very first run
 * on a site with no token — and requiring a token to draw a logo grid is a bad
 * trade. raw.githubusercontent.com is CDN-served, needs no auth, and is not
 * under that cap.
 *
 * ---------------------------------------------------------------------------
 *
 * Why there is a C++ parser in a WordPress plugin.
 *
 * Upstream has no JSON, no API, no generated manifest: the list is a
 * brace-initialised QList<GameDetail> a human maintains, with comments between
 * the fields and descriptions spread over a dozen adjacent string literals.
 * Asking upstream to publish a machine-readable copy is the better long-term
 * answer; until then, this reads what exists.
 *
 * It is a scanner rather than a regex because both `//` and `,` occur inside
 * the data — every website field is an <a href='http://…'> — so anything not
 * string-aware corrupts entries rather than failing loudly.
 *
 * Parsed shape, per game:
 *
 *   name, host, port, tls, site, domain, links[{label,url}], description,
 *   own_ui, alt_hosts[], icon (basename), icon_path (repo-relative),
 *   internal (the tutorial and self-test profiles, which are not games)
 *
 * @package Mudlet_Games
 */

defined( 'ABSPATH' ) || exit;

/**
 * The upstream source of the games list.
 */
class Mudlet_Games_Source {

	const REPO   = 'Mudlet/Mudlet';
	const REF    = 'development';
	const HEADER = 'src/TGameDetails.h';
	const QRC    = 'src/mudlet.qrc';

	/**
	 * Base URL for raw files, filterable so a fork or a pinned tag can be used.
	 *
	 * @return string
	 */
	public static function raw_base(): string {
		$repo = (string) apply_filters( 'mudlet_games_repo', self::REPO );
		$ref  = (string) apply_filters( 'mudlet_games_ref', self::REF );

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
				'user-agent' => 'mudlet-games/' . MUDLET_GAMES_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		return '' === $body ? null : $body;
	}

	/**
	 * The games, read live from upstream.
	 *
	 * @return array{games: array<int, array<string, mixed>>, sha256: string}|null
	 */
	public static function pull(): ?array {
		$header = self::fetch( self::HEADER );
		if ( null === $header ) {
			return null;
		}

		// One icon is a .qrc alias rather than a path under icons/, so the
		// resource file resolves it. Fetched every time because it is small and
		// because guessing which entries need it means finding out the hard way
		// when a second alias appears.
		$aliases = self::aliases( (string) self::fetch( self::QRC ) );

		return array(
			'sha256' => hash( 'sha256', $header ),
			'games'  => self::parse( $header, $aliases ),
		);
	}

	/**
	 * `<file alias="x">icons/y.png</file>` → [ x => icons/y.png ].
	 *
	 * @param string $qrc Contents of mudlet.qrc.
	 * @return array<string, string>
	 */
	private static function aliases( string $qrc ): array {
		$out = array();
		if ( preg_match_all( '#<file\s+alias="([^"]+)"\s*>([^<]+)</file>#', $qrc, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$out[ $match[1] ] = trim( $match[2] );
			}
		}

		return $out;
	}

	// ── the parser ────────────────────────────────────────────────────

	/**
	 * Parse the header into game rows.
	 *
	 * @param string                $header  Contents of TGameDetails.h.
	 * @param array<string, string> $aliases Resource aliases from mudlet.qrc.
	 * @return array<int, array<string, mixed>>
	 */
	public static function parse( string $header, array $aliases = array() ): array {
		$body = self::initialiser( self::strip_comments( $header ) );
		if ( null === $body ) {
			return array();
		}

		$games = array();

		foreach ( self::split_top( $body, ',' ) as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry || '{' !== $entry[0] ) {
				continue;
			}

			$fields = array_map( 'trim', self::split_top( substr( $entry, 1, strrpos( $entry, '}' ) - 1 ), ',' ) );
			$at     = static fn( int $i ) => $fields[ $i ] ?? '';
			$first  = static fn( int $i ) => self::literals( $at( $i ) )[0] ?? '';

			$name = $first( 0 );
			if ( '' === $name ) {
				continue;
			}

			$host     = $first( 1 );
			$icon_res = ltrim( $first( 5 ), ':/' );
			$icon     = $aliases[ $icon_res ] ?? $icon_res;
			$links    = self::links( implode( '', self::literals( $at( 4 ) ) ) );

			$games[] = array(
				'name' => $name,
				// Two bundled profiles are not games: the tutorial connects to
				// localhost, and "Mudlet self-test" says in its own description
				// that it "isn't a game profile". Both are recognised by what
				// they lack rather than by name — a real game has a host and an
				// icon — and both are kept, flagged, and not drawn.
				'internal'    => 'localhost' === $host || '' === $icon_res,
				'host'        => $host,
				'port'        => (int) preg_replace( '/\D/', '', $at( 2 ) ),
				'tls'         => (bool) preg_match( '/\btrue\b/', $at( 3 ) ),
				'site'        => $links[0]['url'] ?? '',
				'domain'      => $links ? self::domain( $links[0]['url'], $host ) : $host,
				'links'       => $links,
				'description' => implode( '', self::literals( $at( 6 ) ) ),
				'own_ui'      => (bool) preg_match( '/\btrue\b/', $at( 7 ) ),
				'alt_hosts'   => self::literals( implode( ',', array_slice( $fields, 8 ) ) ),
				'icon'        => basename( $icon ),
				'icon_path'   => 'src/' . $icon,
			);
		}

		return $games;
	}

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
	 * The body of `scmDefaultGames = { … }`, braces balanced.
	 *
	 * Anchored on the assignment: the inline helpers above it (findGame, keys)
	 * all mention scmDefaultGames first, and a plain search lands in one of them.
	 *
	 * @param string $src Comment-free source.
	 * @return string|null
	 */
	private static function initialiser( string $src ): ?string {
		if ( ! preg_match( '/scmDefaultGames\s*=\s*\{/', $src, $m, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		$open  = strpos( $src, '{', $m[0][1] );
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
			if ( '{' === $src[ $i ] ) {
				$depth++;
			} elseif ( '}' === $src[ $i ] ) {
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
	 * C++ concatenates adjacent literals, which is how the long descriptions
	 * are written, so callers join what they get back. `QString()` and an empty
	 * list both yield nothing.
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

	/**
	 * `<a href='X'>Label</a>` pairs.
	 *
	 * @param string $html The websiteInfo field.
	 * @return array<int, array{label: string, url: string}>
	 */
	private static function links( string $html ): array {
		$out = array();
		if ( preg_match_all( '#<a\s+href=[\'"]([^\'"]+)[\'"]\s*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$out[] = array(
					'url'   => trim( $match[1] ),
					'label' => trim( wp_strip_all_tags( $match[2] ) ),
				);
			}
		}

		return $out;
	}

	/**
	 * The bare domain a card prints under the game's name.
	 *
	 * @param string $url      Website URL.
	 * @param string $fallback Host to use when the URL will not parse.
	 * @return string
	 */
	private static function domain( string $url, string $fallback ): string {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		return $host ? preg_replace( '/^www\./', '', $host ) : $fallback;
	}
}
