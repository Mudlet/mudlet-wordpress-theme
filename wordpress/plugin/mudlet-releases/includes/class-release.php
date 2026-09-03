<?php
/**
 * A GitHub release, turned into the handful of facts a site actually shows.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalises GitHub's release JSON.
 */
class Mudlet_Releases_Release {

	/**
	 * Build the normalised array for a reference.
	 *
	 * @param string $ref 'latest', a tag, or a release id.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $ref ) {
		$ref = trim( $ref );
		if ( '' === $ref ) {
			return null;
		}

		// The store first, always. It is a database read, it cannot be rate
		// limited, and it works when GitHub does not. The API is the fallback -
		// which matters for a tag an editor has just typed, before any sync has
		// seen it.
		$stored = 'latest' === strtolower( $ref )
			? Mudlet_Releases_Store::latest()
			: Mudlet_Releases_Store::find( $ref );

		if ( $stored ) {
			$release = Mudlet_Releases_Store::to_array( $stored );
			if ( $release ) {
				return $release;
			}
		}

		$raw = Mudlet_Releases_Github_Client::release( $ref );
		if ( ! $raw ) {
			return null;
		}

		// Seen live for the first time: keep it, so the next read is a DB hit
		// and the detail pass can fill in the rest on its own schedule.
		if ( ! empty( $raw['tag_name'] ) ) {
			Mudlet_Releases_Store::store( $raw );
		}

		return self::from_raw( $raw );
	}

	/**
	 * @param array<string, mixed> $raw Release JSON.
	 * @return array<string, mixed>
	 */
	public static function from_raw( array $raw ): array {
		$tag  = (string) ( $raw['tag_name'] ?? '' );
		$body = (string) ( $raw['body'] ?? '' );

		// Counts come from the pull requests merged since the previous release
		// when that has already been worked out, and from the release body's
		// own Added/Improved/Fixed headings otherwise. The PR figures are the
		// better answer - they are the record rather than a summary, and they
		// exist even for a release like 5.0 whose body has no such headings -
		// but working them out costs several requests, so this never triggers
		// that. Mudlet_Releases_Changelog::get() does, on a single post view.
		$changelog = '' !== $tag ? Mudlet_Releases_Changelog::cached( $tag ) : null;
		$counts    = $changelog ? $changelog['counts'] : self::counts( $body );

		$release = array(
			'id'         => (int) ( $raw['id'] ?? 0 ),
			'tag'        => $tag,
			'version'    => self::version_from_tag( $tag ),
			'name'       => (string) ( $raw['name'] ?? $tag ),
			'date'       => substr( (string) ( $raw['published_at'] ?? '' ), 0, 10 ),
			'url'        => (string) ( $raw['html_url'] ?? '' ),
			'prerelease' => ! empty( $raw['prerelease'] ),
			'counts'     => $counts,
			'counts_from'=> $changelog ? 'pulls' : 'body',
			'builds'     => self::builds( (array) ( $raw['assets'] ?? array() ) ),
			'changelog'  => Mudlet_Releases_Markdown::to_html( $body ),
			'body'       => $body,
		);

		/**
		 * Filter a normalised release.
		 *
		 * @param array<string, mixed> $release Normalised release.
		 * @param array<string, mixed> $raw     The GitHub JSON it came from.
		 */
		return apply_filters( 'mudlet_releases_release', $release, $raw );
	}

	/**
	 * "Mudlet-4.22.0" -> "4.22.0".
	 *
	 * @param string $tag Tag name.
	 * @return string
	 */
	public static function version_from_tag( string $tag ): string {
		return (string) preg_replace( '/^Mudlet[-_ ]?/i', '', $tag );
	}

	/**
	 * Count the entries under the changelog's Added / Improved / Fixed headings.
	 *
	 * Returns an empty array when a release does not use those headings. That is
	 * not a failure: 5.0's changelog is written as prose sections with their own
	 * titles, and the honest thing is to show no counts rather than three zeroes.
	 *
	 * @param string $body Release body, Markdown.
	 * @return array<int, array{0:string,1:string}> [count, label] pairs.
	 */
	public static function counts( string $body ): array {
		$body = str_replace( "\r", '', $body );

		$wanted = array(
			'added'    => array( __( 'new feature', 'mudlet-releases' ), __( 'new features', 'mudlet-releases' ) ),
			'improved' => array( __( 'improvement', 'mudlet-releases' ), __( 'improvements', 'mudlet-releases' ) ),
			'fixed'    => array( __( 'fix', 'mudlet-releases' ), __( 'fixes', 'mudlet-releases' ) ),
		);

		if ( ! preg_match_all( '/^#+[ \t]*([A-Za-z][A-Za-z ]*?):?[ \t]*$/m', $body, $heads, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		$found = array();

		foreach ( $heads[1] as $i => $head ) {
			$name = strtolower( trim( $head[0] ) );
			if ( ! isset( $wanted[ $name ] ) ) {
				continue;
			}

			$start = $heads[0][ $i ][1] + strlen( $heads[0][ $i ][0] );
			$end   = isset( $heads[0][ $i + 1 ] ) ? $heads[0][ $i + 1 ][1] : strlen( $body );
			$chunk = substr( $body, $start, $end - $start );

			// Entries are written "\- text" (the dash escaped, which is how
			// GitHub's own release notes come out) or plainly "- text".
			$n = 0;
			foreach ( explode( "\n", $chunk ) as $line ) {
				if ( preg_match( '/^\s*\\\\?[-*]\s+\S/', $line ) ) {
					++$n;
				}
			}

			if ( $n > 0 ) {
				$found[ $name ] = array( (string) $n, 1 === $n ? $wanted[ $name ][0] : $wanted[ $name ][1] );
			}
		}

		// Added, Improved, Fixed - in that order, whatever order the changelog
		// happens to use.
		$ordered = array();
		foreach ( array_keys( $wanted ) as $name ) {
			if ( isset( $found[ $name ] ) ) {
				$ordered[] = $found[ $name ];
			}
		}
		return $ordered;
	}

	/**
	 * Map release assets onto the platforms a download table shows.
	 *
	 * Matching is on filename, which is what the release workflow controls:
	 * Mudlet-4.22.0-windows-64-installer.exe, -arm64.dmg, -x86_64.dmg,
	 * -linux-x64.AppImage.tar. An unrecognised asset is skipped rather than
	 * shown as a mystery row.
	 *
	 * Checksums come out of the release JSON itself - see digests() - so the
	 * index pass gets them for nothing. Only a release old enough to predate
	 * GitHub stamping its assets falls back to SHA256SUMS.txt, and that is the
	 * one thing here that costs a request.
	 *
	 * @param array<int, array<string, mixed>> $assets    Release assets.
	 * @param bool                             $checksums Allow the fallback
	 *                                                    request for a release
	 *                                                    whose assets carry no
	 *                                                    digest. The index sync
	 *                                                    says no, the detail
	 *                                                    pass says yes.
	 * @return array<string, array<string, string>>
	 */
	public static function builds( array $assets, bool $checksums = true ): array {
		/**
		 * Filter the platform matching rules.
		 *
		 * Each entry is [ pattern, label, short label, note ].
		 *
		 * @param array<string, array{0:string,1:string,2:string,3:string}> $rules Rules.
		 */
		$rules = apply_filters(
			'mudlet_releases_platforms',
			array(
				'win'    => array( '/windows/i', __( 'Windows', 'mudlet-releases' ), __( 'Windows', 'mudlet-releases' ), __( '64-bit installer', 'mudlet-releases' ) ),
				'macarm' => array( '/arm64\.dmg$/i', __( 'macOS, Apple Silicon', 'mudlet-releases' ), __( 'macOS', 'mudlet-releases' ), 'arm64' ),
				'macx86' => array( '/x86_64\.dmg$/i', __( 'macOS, Intel', 'mudlet-releases' ), __( 'macOS', 'mudlet-releases' ), 'x86_64' ),
				'linux'  => array( '/linux/i', __( 'Linux', 'mudlet-releases' ), __( 'Linux', 'mudlet-releases' ), 'AppImage' ),
			)
		);

		$sums   = self::digests( $assets );
		$builds = array();

		foreach ( $rules as $key => $rule ) {
			list( $pattern, $label, $short, $note ) = $rule;

			foreach ( $assets as $asset ) {
				$name = (string) ( $asset['name'] ?? '' );
				if ( 'SHA256SUMS.txt' === $name || ! preg_match( $pattern, $name ) ) {
					continue;
				}

				$builds[ $key ] = array(
					'file'  => $name,
					'label' => $label,
					'short' => $short,
					'note'  => $note,
					'size'  => self::format_bytes( (int) ( $asset['size'] ?? 0 ) ),
					'bytes' => (int) ( $asset['size'] ?? 0 ),
					'sha'   => $sums[ $name ] ?? '',
					'url'   => (string) ( $asset['browser_download_url'] ?? '' ),
				);
				break;
			}
		}

		// Only now, and only if something is actually missing: the releases with
		// no digest on their assets are the ones from before GitHub added the
		// field, and asking for a file that answers a question already answered
		// is the request this avoids.
		if ( $checksums && self::unhashed( $builds ) ) {
			$file = self::checksums( $assets );
			foreach ( $builds as $key => $build ) {
				if ( '' === $build['sha'] ) {
					$builds[ $key ]['sha'] = $file[ $build['file'] ] ?? '';
				}
			}
		}

		return $builds;
	}

	/**
	 * Whether any row still has no checksum.
	 *
	 * @param array<string, array<string, string>> $builds Build rows.
	 */
	private static function unhashed( array $builds ): bool {
		foreach ( $builds as $build ) {
			if ( '' === ( $build['sha'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * filename => sha256, out of the release JSON already in hand.
	 *
	 * GitHub stamps every release asset with a `digest` - "sha256:<hex>" - in
	 * the same response that carries the name, the size and the URL. So for
	 * anything published since it started doing that, a checksum costs no
	 * request at all, and SHA256SUMS.txt below is a second copy of a number we
	 * are already holding.
	 *
	 * The algorithm is read off the string rather than assumed. Anything that
	 * is not sha256 is left out, and the file answers for it instead.
	 *
	 * @param array<int, array<string, mixed>> $assets Release assets.
	 * @return array<string, string>
	 */
	public static function digests( array $assets ): array {
		$sums = array();

		foreach ( $assets as $asset ) {
			$name   = (string) ( $asset['name'] ?? '' );
			$digest = (string) ( $asset['digest'] ?? '' );

			if ( '' !== $name && str_starts_with( $digest, 'sha256:' ) ) {
				$sums[ $name ] = strtolower( substr( $digest, 7 ) );
			}
		}

		return $sums;
	}

	/**
	 * Read the release's SHA256SUMS.txt into filename => hash.
	 *
	 * The fallback, not the source. One extra request per release, a few hundred
	 * bytes, and it says the same thing digests() reads for free out of the JSON
	 * - so this runs only for a release whose assets predate GitHub stamping
	 * them, where it is the only place the hashes exist.
	 *
	 * @param array<int, array<string, mixed>> $assets Release assets.
	 * @return array<string, string>
	 */
	public static function checksums( array $assets ): array {
		$url = '';
		foreach ( $assets as $asset ) {
			if ( 'SHA256SUMS.txt' === ( $asset['name'] ?? '' ) ) {
				$url = (string) ( $asset['browser_download_url'] ?? '' );
				break;
			}
		}
		if ( '' === $url ) {
			return array();
		}

		$body = Mudlet_Releases_Github_Client::get_body( $url );
		if ( null === $body ) {
			return array();
		}

		$sums = array();
		foreach ( preg_split( '/\R/', $body ) as $line ) {
			// "<hash>  <name>", and the binary-mode "<hash> *<name>"
			if ( preg_match( '/^([0-9a-f]{64})\s+\*?(.+)$/i', trim( $line ), $m ) ) {
				$sums[ trim( $m[2] ) ] = strtolower( $m[1] );
			}
		}
		return $sums;
	}

	/**
	 * Bytes as a download table writes them.
	 *
	 * MiB throughout, which is what GitHub's byte counts turn into and what the
	 * site has always shown: 135100240 bytes is "128.8 MiB".
	 *
	 * @param int $bytes Size in bytes.
	 * @return string
	 */
	public static function format_bytes( int $bytes ): string {
		if ( $bytes <= 0 ) {
			return '';
		}
		return number_format_i18n( $bytes / 1048576, 1 ) . ' MiB';
	}
}
