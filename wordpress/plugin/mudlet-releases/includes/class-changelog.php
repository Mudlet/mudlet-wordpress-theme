<?php
/**
 * The changelog between two releases, built from merged pull requests.
 *
 * The release body is prose somebody wrote. Sometimes it is a tidy
 * Added/Improved/Fixed list; for 5.0 it is marketing sections with their own
 * titles. Either way it is a summary, not the record.
 *
 * The record is what merged between the previous tag and this one. Mudlet
 * squash-merges, so each commit title is its pull request's title with the
 * number on the end - "fix: media start events fired too early (#9611)" - which
 * means a full changelog and honest counts come out of the compare endpoint
 * without asking GitHub about each PR individually.
 *
 * How well this reproduces Mudlet's own published figures, checked against the
 * 4.21 announcement ("47 new features, 77 improvements, 207 bug fixes"):
 *
 *   fixed           207  against 207   exact
 *   added            47  against  47   exact
 *   improved         78  against  77   one over
 *   infrastructure  213  against 203   ten over
 *
 * Close enough to be clearly the same method, not close enough to claim it is
 * the same rules - so PREFIXES is filterable and the counts are described as
 * derived, never as official.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pull-request-derived changelogs.
 */
class Mudlet_Releases_Changelog {

	/** Commits per compare request. */
	const PER_PAGE = 100;

	/**
	 * Safety stop.
	 *
	 * A release of 547 commits takes six requests. Ten pages is a thousand
	 * commits - past that something is wrong with the tags being compared, and
	 * hammering the API is not the way to find out.
	 */
	const MAX_PAGES = 10;

	/**
	 * Leading words that put an entry in a category.
	 *
	 * Matched against the title with any trailing "(#123)" removed, so both
	 * "fix: thing" and "Fix thing" land in the same bucket. Order matters only
	 * in that the first match wins.
	 *
	 * Anything unmatched becomes "other", which is listed rather than hidden.
	 * That bucket is the feedback loop for these patterns: if something worth
	 * reading turns up in it, the pattern is wrong, and the only way to notice
	 * is to see it. Version bumps land there too, which is honest - they did
	 * merge - and cheap, because there are only ever one or two.
	 *
	 * @return array<string, string> category => pattern
	 */
	public static function prefixes(): array {
		/**
		 * Filter the changelog category patterns.
		 *
		 * @param array<string, string> $prefixes category => regex.
		 */
		return apply_filters(
			'mudlet_releases_changelog_prefixes',
			array(
				// "adding" is here because Mudlet's own count includes
				// "Adding selectAll function", which is the one entry that
				// separated 46 from the published 47.
				'added'          => '/^(add|adds|added|adding|feat|feature|new)\b/i',
				'improved'       => '/^(improve[ds]?|improvement|enhance[ds]?|change[ds]?|update[ds]?)\b/i',
				'fixed'          => '/^(fix|fixes|fixed|bugfix|hotfix)\b/i',
				'infrastructure' => '/^(infra|infrastructure|infrastucture|ci|build|chore|docs?|tests?|refactor|revert)\b/i',
			)
		);
	}

	/**
	 * Categories shown as counts in a release panel, in order.
	 *
	 * Infrastructure is deliberately not among them: it is the largest bucket
	 * and the least interesting to a player.
	 *
	 * @return array<string, array{0:string,1:string}> category => [singular, plural]
	 */
	public static function counted(): array {
		return array(
			'added'    => array( __( 'new feature', 'mudlet-releases' ), __( 'new features', 'mudlet-releases' ) ),
			'improved' => array( __( 'improvement', 'mudlet-releases' ), __( 'improvements', 'mudlet-releases' ) ),
			'fixed'    => array( __( 'fix', 'mudlet-releases' ), __( 'fixes', 'mudlet-releases' ) ),
		);
	}

	/**
	 * The changelog for a tag if it is already cached, without fetching.
	 *
	 * Building one costs a request for the releases list plus a request per
	 * hundred commits - six for a large release. That is fine on a single post,
	 * and far too much on a news index listing twenty of them, so lists ask for
	 * this and take a "no" for an answer. Viewing a post warms the cache, and
	 * the index picks the better counts up afterwards.
	 *
	 * @param string $tag      The release tag.
	 * @param string $previous Optional explicit previous tag.
	 * @return array<string, mixed>|null
	 */
	public static function cached( string $tag, string $previous = '' ) {
		if ( '' === $tag ) {
			return null;
		}
		$cached = get_transient( self::key( $tag, $previous ) );
		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Transient key for a tag pair.
	 *
	 * @param string $tag      Head tag.
	 * @param string $previous Base tag, or ''.
	 * @return string
	 */
	private static function key( string $tag, string $previous ): string {
		return 'mudlet_chlog_' . md5( Mudlet_Releases_Github_Client::repo() . '|' . $tag . '|' . $previous );
	}

	/**
	 * The changelog for a tag: from the store, the cache, or GitHub.
	 *
	 * @param string $tag      The release tag.
	 * @param string $previous Optional explicit previous tag.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $tag, string $previous = '' ) {
		// Stored first. Once a release has been synced this is a database read
		// and costs nothing, which is the whole point of the store.
		$stored = Mudlet_Releases_Store::changes( $tag );
		if ( $stored ) {
			return $stored;
		}
		return self::fetch( $tag, $previous );
	}

	/**
	 * Build a changelog from GitHub, ignoring what is stored.
	 *
	 * The sync uses this: going through get() during a sync would find the
	 * record it is in the middle of writing and never refresh anything.
	 *
	 * @param string $tag      The release tag.
	 * @param string $previous Optional explicit previous tag.
	 * @return array<string, mixed>|null
	 */
	public static function fetch( string $tag, string $previous = '' ) {
		if ( '' === $tag ) {
			return null;
		}

		$key    = self::key( $tag, $previous );
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( 'miss' === $cached ) {
			return null;
		}

		if ( '' === $previous ) {
			$previous = self::previous_tag( $tag );
		}
		if ( '' === $previous ) {
			set_transient( $key, 'miss', Mudlet_Releases_Github_Client::FAIL_TTL );
			return null;
		}

		$commits = self::commits( $previous, $tag );
		if ( null === $commits ) {
			set_transient( $key, 'miss', Mudlet_Releases_Github_Client::FAIL_TTL );
			return null;
		}

		$changelog = self::build( $commits, $previous, $tag );
		set_transient( $key, $changelog, Mudlet_Releases_Github_Client::TTL );

		return $changelog;
	}

	/**
	 * The release published before this tag.
	 *
	 * Prereleases are skipped - Mudlet publishes a public test build most days,
	 * and comparing against one would produce a changelog of a single afternoon.
	 *
	 * @param string $tag The release tag.
	 * @return string Empty when there is no earlier release.
	 */
	public static function previous_tag( string $tag ): string {
		$list = Mudlet_Releases_Github_Client::get_json(
			'https://api.github.com/repos/' . Mudlet_Releases_Github_Client::repo() . '/releases?per_page=30'
		);
		if ( ! is_array( $list ) ) {
			return '';
		}

		$stable = array();
		foreach ( $list as $release ) {
			if ( empty( $release['tag_name'] ) || ! empty( $release['prerelease'] ) || ! empty( $release['draft'] ) ) {
				continue;
			}
			$stable[] = array(
				'tag'  => (string) $release['tag_name'],
				'date' => (string) ( $release['published_at'] ?? '' ),
			);
		}

		usort(
			$stable,
			static function ( array $a, array $b ): int {
				return strcmp( $b['date'], $a['date'] );
			}
		);

		$seen = false;
		foreach ( $stable as $release ) {
			if ( $seen ) {
				return $release['tag'];
			}
			if ( $release['tag'] === $tag ) {
				$seen = true;
			}
		}

		return '';
	}

	/**
	 * Every commit between two tags.
	 *
	 * @param string $base Older tag.
	 * @param string $head Newer tag.
	 * @return array<int, array<string, mixed>>|null
	 */
	private static function commits( string $base, string $head ) {
		$repo = Mudlet_Releases_Github_Client::repo();
		$all  = array();

		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			$url = sprintf(
				'https://api.github.com/repos/%s/compare/%s...%s?per_page=%d&page=%d',
				$repo,
				rawurlencode( $base ),
				rawurlencode( $head ),
				self::PER_PAGE,
				$page
			);

			$data = Mudlet_Releases_Github_Client::get_json( $url );
			if ( ! is_array( $data ) || ! isset( $data['commits'] ) ) {
				// A failure on page one is a failure; on a later page, keep what
				// was collected rather than throwing away five good requests.
				return 1 === $page ? null : $all;
			}

			$commits = (array) $data['commits'];
			$all     = array_merge( $all, $commits );

			if ( count( $commits ) < self::PER_PAGE ) {
				break;
			}
		}

		return $all;
	}

	/**
	 * Turn commits into a grouped changelog.
	 *
	 * @param array<int, array<string, mixed>> $commits  Commits.
	 * @param string                           $previous Base tag.
	 * @param string                           $tag      Head tag.
	 * @return array<string, mixed>
	 */
	private static function build( array $commits, string $previous, string $tag ): array {
		$messages = array();
		foreach ( $commits as $commit ) {
			$messages[] = (string) ( $commit['commit']['message'] ?? '' );
		}

		$changelog = self::build_from_messages( $messages, $previous, $tag );

		// The same commits already in hand also say who wrote them, so the
		// contributor list rides along at no extra request. It is cached with
		// the changelog and lifted into its own meta by the store.
		$changelog['contributors'] = self::contributors_from_commits( $commits );

		return $changelog;
	}

	/**
	 * Categorise a list of raw commit messages.
	 *
	 * Split out so the backfill can feed in titles dumped by `gh` and get
	 * exactly what the live path would have produced. The rules live here and
	 * only here - a second implementation in the dump script would drift.
	 *
	 * @param string[] $messages Commit messages, first line significant.
	 * @param string   $previous Base tag.
	 * @param string   $tag      Head tag.
	 * @return array<string, mixed>
	 */
	public static function build_from_messages( array $messages, string $previous, string $tag ): array {
		$repo     = Mudlet_Releases_Github_Client::repo();
		$prefixes = self::prefixes();
		$groups   = array();
		$total    = 0;
		$seen     = array();

		foreach ( $messages as $message ) {
			$message = (string) $message;
			$title   = trim( explode( "\n", $message )[0] );
			if ( '' === $title ) {
				continue;
			}

			// Squash merges end in "(#1234)". Merge commits start with "Merge
			// pull request #1234" and keep the title on a later line.
			$pr = '';
			if ( preg_match( '/^(.*?)\s*\(#(\d+)\)\s*$/', $title, $m ) ) {
				$title = trim( $m[1] );
				$pr    = $m[2];
			} elseif ( preg_match( '/^Merge pull request #(\d+)/', $title, $m ) ) {
				$pr    = $m[1];
				$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $message ) ) ) );
				$title = $lines[1] ?? $title;
			}

			// A PR can appear twice when a branch was merged more than once.
			if ( '' !== $pr ) {
				if ( isset( $seen[ $pr ] ) ) {
					continue;
				}
				$seen[ $pr ] = true;
			}

			$category = 'other';
			$lead     = (string) preg_replace( '/^([A-Za-z]+)\s*:\s*/', '$1 ', $title );
			foreach ( $prefixes as $name => $pattern ) {
				if ( preg_match( $pattern, $lead ) ) {
					$category = $name;
					break;
				}
			}

			// "fix: thing" reads better in a categorised list as just "thing".
			$clean = (string) preg_replace( '/^[A-Za-z]+\s*:\s*/', '', $title );

			$groups[ $category ][] = array(
				'title' => '' !== $clean ? $clean : $title,
				'pr'    => $pr,
				'url'   => '' !== $pr ? 'https://github.com/' . $repo . '/pull/' . $pr : '',
			);
			++$total;
		}

		return array(
			'previous'    => $previous,
			'tag'         => $tag,
			'compare_url' => 'https://github.com/' . $repo . '/compare/' . $previous . '...' . $tag,
			'total'       => $total,
			'groups'      => $groups,
			'counts'      => self::counts_from_groups( $groups ),
		);
	}

	/**
	 * The [count, label] pairs a release panel shows.
	 *
	 * @param array<string, array<int, array<string, string>>> $groups Grouped entries.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function counts_from_groups( array $groups ): array {
		$counts = array();
		foreach ( self::counted() as $category => $labels ) {
			$n = isset( $groups[ $category ] ) ? count( $groups[ $category ] ) : 0;
			if ( $n > 0 ) {
				$counts[] = array( (string) $n, 1 === $n ? $labels[0] : $labels[1] );
			}
		}
		return $counts;
	}

	// ── who wrote it ──────────────────────────────────────────────────
	//
	// Same source as the changelog, and therefore free: the compare endpoint
	// returns each commit's GitHub author alongside its message, so a release's
	// contributors cost no extra requests.
	//
	// Both entry points hand rows to contributors_from_rows() and nothing else
	// decides anything, for the same reason the categorising rules live in one
	// place: a second implementation in the dump script would drift.

	/**
	 * Accounts that are not people.
	 *
	 * Mudlet's release history contains three kinds of non-human commit author,
	 * and only one of them is spelled the obvious way: `dependabot[bot]` wears
	 * the suffix, `mudlet-machine-account` does not, and translation syncs land
	 * under Weblate. A credits list with a robot at the top of it is worse than
	 * no credits list, so all three are named here.
	 *
	 * @return string[] Logins and names, lowercase.
	 */
	public static function bots(): array {
		/**
		 * Filter the accounts excluded from contributor lists.
		 *
		 * Anything ending in "[bot]" is excluded regardless.
		 *
		 * @param string[] $bots Logins or names, lowercase.
		 */
		return array_map(
			'strtolower',
			(array) apply_filters(
				'mudlet_releases_bot_accounts',
				array( 'mudlet-machine-account', 'github-actions', 'dependabot', 'weblate', 'imgbot' )
			)
		);
	}

	/**
	 * Whether an identity is a machine rather than a person.
	 *
	 * @param string $login GitHub login, if known.
	 * @param string $name  Display name.
	 * @param string $email Email, if known.
	 * @return bool
	 */
	public static function is_bot( string $login, string $name = '', string $email = '' ): bool {
		$bots = self::bots();

		foreach ( array( $login, $name ) as $handle ) {
			$handle = strtolower( trim( $handle ) );
			if ( '' === $handle ) {
				continue;
			}
			if ( str_ends_with( $handle, '[bot]' ) || in_array( $handle, $bots, true ) ) {
				return true;
			}
		}

		// A "Co-authored-by" trailer is also where tooling signs its work -
		// noreply@anthropic.com and friends. GitHub's own noreply addresses are
		// people (that is how a user hides their real address), so those stay.
		$email = strtolower( trim( $email ) );
		if ( '' !== $email && str_starts_with( $email, 'noreply@' ) && ! str_ends_with( $email, 'github.com' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * The "Co-authored-by: Name <email>" trailers in a commit message.
	 *
	 * @param string $message Full commit message.
	 * @return array<int, array{name: string, email: string}>
	 */
	public static function coauthors_from_message( string $message ): array {
		$out = array();

		if ( ! preg_match_all( '/^\s*Co-authored-by:\s*(.+?)\s*<([^>]+)>\s*$/mi', $message, $matches, PREG_SET_ORDER ) ) {
			return $out;
		}

		foreach ( $matches as $match ) {
			$out[] = array(
				'name'  => trim( $match[1] ),
				'email' => trim( $match[2] ),
			);
		}

		return $out;
	}

	/**
	 * A GitHub login hidden in a noreply address.
	 *
	 * Co-author trailers carry an email, not a login, so most of them would be
	 * a second unlinked row for somebody already in the list. GitHub's own
	 * addresses - "12345+octocat@users.noreply.github.com", or plain
	 * "octocat@users.noreply.github.com" - give the login back, which is what
	 * makes deduplicating them exact rather than a name-spelling guess.
	 *
	 * @param string $email Email address.
	 * @return string Login, or '' if this is not a GitHub noreply address.
	 */
	public static function login_from_email( string $email ): string {
		if ( preg_match( '/^(?:\d+\+)?([^@]+)@users\.noreply\.github\.com$/i', trim( $email ), $m ) ) {
			return $m[1];
		}

		return '';
	}

	/**
	 * Tally the people behind a set of commits.
	 *
	 * A row is one commit:
	 *
	 *   login      GitHub login, '' when GitHub could not match the address
	 *   name       display name
	 *   email      author email, if known
	 *   avatar     avatar URL, if known
	 *   coauthors  [ ['name' => …, 'email' => …], … ] from the message trailers
	 *
	 * Co-authors count towards the commit they are credited on - they wrote
	 * part of it - but are folded into the same person when they resolve to a
	 * login already present, which they usually do: GitHub adds a trailer for
	 * the author themselves on a web merge, so a naive list doubles half of it.
	 *
	 * @param array<int, array<string, mixed>> $rows One entry per commit.
	 * @return array<int, array<string, mixed>> Contributors, most commits first.
	 */
	public static function contributors_from_rows( array $rows ): array {
		$people = array();
		$logins = self::logins_by_name( $rows );

		foreach ( $rows as $row ) {
			$login  = (string) ( $row['login'] ?? '' );
			$name   = (string) ( $row['name'] ?? '' );
			$email  = (string) ( $row['email'] ?? '' );
			$avatar = (string) ( $row['avatar'] ?? '' );

			// Everyone credited on this one commit, the author first.
			$credited = array(
				array(
					'login'  => $login,
					'name'   => $name,
					'email'  => $email,
					'avatar' => $avatar,
				),
			);

			foreach ( (array) ( $row['coauthors'] ?? array() ) as $coauthor ) {
				$co_name  = (string) ( $coauthor['name'] ?? '' );
				$co_email = (string) ( $coauthor['email'] ?? '' );

				// The address gives the login back when it is one of GitHub's;
				// when it is somebody's own - "Vadim Peretokin <vadi@…>" - the
				// name is all there is, so fall back to the login that name
				// commits under elsewhere in this release.
				$co_login = self::login_from_email( $co_email );
				if ( '' === $co_login ) {
					$co_login = $logins[ strtolower( trim( $co_name ) ) ] ?? '';
				}

				$credited[] = array(
					'login'  => $co_login,
					'name'   => $co_name,
					'email'  => $co_email,
					'avatar' => '',
				);
			}

			$counted = array();

			foreach ( $credited as $person ) {
				if ( '' === $person['login'] && '' === $person['name'] ) {
					continue;
				}
				if ( self::is_bot( $person['login'], $person['name'], $person['email'] ) ) {
					continue;
				}

				// Identity is the login when there is one, because names are
				// spelled several ways and logins are not.
				$key = '' !== $person['login']
					? 'l:' . strtolower( $person['login'] )
					: 'n:' . strtolower( $person['name'] );

				// One commit, one increment per person, however many trailers
				// name them.
				if ( isset( $counted[ $key ] ) ) {
					continue;
				}
				$counted[ $key ] = true;

				if ( ! isset( $people[ $key ] ) ) {
					$people[ $key ] = array(
						'login'   => $person['login'],
						'name'    => '' !== $person['name'] ? $person['name'] : $person['login'],
						'url'     => '' !== $person['login'] ? 'https://github.com/' . $person['login'] : '',
						'avatar'  => $person['avatar'],
						'commits' => 0,
					);
				}

				// A later commit may be the one that carries the avatar, or a
				// better-spelled name than a bare login.
				if ( '' === $people[ $key ]['avatar'] && '' !== $person['avatar'] ) {
					$people[ $key ]['avatar'] = $person['avatar'];
				}
				if ( '' !== $person['name'] && $people[ $key ]['name'] === $people[ $key ]['login'] ) {
					$people[ $key ]['name'] = $person['name'];
				}

				++$people[ $key ]['commits'];
			}
		}

		$people = array_values( $people );

		usort(
			$people,
			static function ( array $a, array $b ): int {
				return $b['commits'] <=> $a['commits'] ?: strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $people;
	}

	/**
	 * Which login each display name commits under.
	 *
	 * Built from the commits GitHub *did* match to an account, and used to
	 * rescue the ones it could not. Without it a single person shows up twice —
	 * once as the author of their own commits and again, unlinked, wherever a
	 * "Co-authored-by" trailer used their personal address instead of GitHub's.
	 * That is not a rare edge: it is how every co-authored commit by someone who
	 * has not hidden their email looks.
	 *
	 * @param array<int, array<string, mixed>> $rows Commit rows.
	 * @return array<string, string> lowercase name => login
	 */
	private static function logins_by_name( array $rows ): array {
		$map = array();

		foreach ( $rows as $row ) {
			$login = trim( (string) ( $row['login'] ?? '' ) );
			$name  = strtolower( trim( (string) ( $row['name'] ?? '' ) ) );

			if ( '' !== $login && '' !== $name && ! isset( $map[ $name ] ) ) {
				$map[ $name ] = $login;
			}
		}

		return $map;
	}

	/**
	 * Contributor rows from raw compare commits.
	 *
	 * @param array<int, array<string, mixed>> $commits Commits as GitHub returns them.
	 * @return array<int, array<string, mixed>>
	 */
	public static function contributors_from_commits( array $commits ): array {
		$rows = array();

		foreach ( $commits as $commit ) {
			$message = (string) ( $commit['commit']['message'] ?? '' );

			$rows[] = array(
				// `author` is GitHub's match for the address; it is null when
				// the commit email belongs to no account, and then only the git
				// name is known.
				'login'     => (string) ( $commit['author']['login'] ?? '' ),
				'name'      => (string) ( $commit['commit']['author']['name'] ?? '' ),
				'email'     => (string) ( $commit['commit']['author']['email'] ?? '' ),
				'avatar'    => (string) ( $commit['author']['avatar_url'] ?? '' ),
				'coauthors' => self::coauthors_from_message( $message ),
			);
		}

		return self::contributors_from_rows( $rows );
	}
}
