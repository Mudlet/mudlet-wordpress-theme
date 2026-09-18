<?php
/**
 * The GitHub release webhook, taken over from the older plugin.
 *
 * `Mudlet/mudlet-release-plugin` does two jobs. One is the
 * `[MudletRelease]<id>[/MudletRelease]` shortcode that twenty-one imported
 * posts are written in, and class-content.php already stands in for it. The
 * other is this: GitHub POSTs when a release is published, and an announcement
 * post appears. That is the half that has to be rebuilt before the old plugin
 * can be turned off.
 *
 * ---------------------------------------------------------------------------
 *
 * The same endpoint, deliberately.
 *
 * `admin-ajax.php?action=post_newest_release`, which is where the old plugin
 * listened, so **the webhook configured on Mudlet/Mudlet keeps working with no
 * change at the GitHub end**. A migration that needs somebody to go and edit a
 * webhook in another repository's settings at the right moment is a migration
 * with a step that gets forgotten. There is nothing else to recommend the URL.
 *
 * It registers only when the old plugin is absent - `class_exists()`, the same
 * arbitration the shortcode uses - so the two can be installed side by side
 * during the switch without both answering.
 *
 * ---------------------------------------------------------------------------
 *
 * The payload is not trusted, and that is the security model.
 *
 * The old plugin had no authentication of any kind: it read `$_POST['payload']`
 * and wrote whatever was in it into a post. Anyone who knew the URL could
 * publish to mudlet.org.
 *
 * Set a secret - `MUDLET_RELEASES_WEBHOOK_SECRET` in wp-config.php, matching
 * the one on the webhook - and this verifies `X-Hub-Signature-256` and refuses
 * anything that fails. That is the way to run it.
 *
 * Without a secret it still answers, because otherwise this cannot replace the
 * old plugin without a flag day. What it does instead is take **only the tag**
 * out of the payload and re-fetch the release from api.github.com itself, so a
 * forged POST cannot put a single character on the site: everything published
 * comes from GitHub. Worst case is somebody causing an announcement post for a
 * major Mudlet release that really exists and did not have one - which is
 * noise, not compromise, and it is idempotent besides. Say so out loud rather
 * than leave it looking like the endpoint is guarded.
 *
 * ---------------------------------------------------------------------------
 *
 * What it creates: a post with a tag in it and nothing else.
 *
 * The old plugin wrote `[MudletRelease]<id>[/MudletRelease]` into the body.
 * This writes an **empty body** and the release tag in `_mudlet_release_tag`,
 * because that is what the rest of this plugin is built around - a tagged post
 * with an empty body renders its changelog through
 * `Mudlet_Releases_Content::maybe_append_changelog()`, and the database keeps
 * no copy of prose that lives on GitHub. An editor who then writes an opening
 * paragraph gets their paragraph and the changelog under it, and
 * class-markdown-export.php can hand that paragraph back to GitHub without the
 * changelog coming with it. Storing the rendered body would break both.
 *
 * The same reasoning leaves `post_excerpt` empty: `wp_trim_excerpt()` applies
 * `the_content`, the changelog filter fires inside it, and the news listing
 * gets an excerpt without anything being written down.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * Receive GitHub's release event and keep an announcement post in step with it.
 */
class Mudlet_Releases_Webhook {

	/** The admin-ajax action the old plugin used, and this answers on. */
	const ACTION = 'post_newest_release';

	/** The single event that goes back for the assets. See watch_for_assets(). */
	const FOLLOWUP = 'mudlet_releases_webhook_followup';

	/**
	 * When to look again for a release's binaries, in minutes after delivery.
	 *
	 * Mudlet's `create-github-release.yml` creates the release carrying only
	 * `SHA256SUMS.txt` and uploads the installers in a **separate**
	 * `gh release upload` afterwards - deliberately, so a half-finished upload
	 * never leaves a checksum file covering a binary that is not there. GitHub
	 * fires the `release` webhook on `created`, and uploading an asset fires
	 * nothing at all: the event's actions are published, created, edited,
	 * deleted, prereleased and released, and none of them covers an asset
	 * appearing.
	 *
	 * So the delivery arrives at the one moment the release has no builds on
	 * it. Left there, every release would have an empty download table for
	 * ever. Hence a short chain of single events that goes back and looks -
	 * which stops the moment the builds are there, so a release that was
	 * complete on arrival costs nothing and the ordinary case costs one or two
	 * requests, a few times a year. That is what replaces the polling.
	 */
	const FOLLOWUP_MINUTES = array( 5, 15, 45 );

	/**
	 * Hook up, unless the plugin this replaces is still doing it.
	 */
	public static function init(): void {
		if ( class_exists( 'MudletRelease' ) ) {
			return;
		}

		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'receive' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'receive' ) );
		add_action( self::FOLLOWUP, array( __CLASS__, 'followup' ), 10, 2 );
	}

	// ── the request ───────────────────────────────────────────────────

	/**
	 * Handle one delivery.
	 */
	public static function receive(): void {
		$raw = (string) file_get_contents( 'php://input' );

		if ( ! self::authentic( $raw ) ) {
			self::respond( 'Signature missing or invalid.', 401 );
		}

		$payload = self::payload( $raw );
		if ( null === $payload ) {
			self::respond( 'No release payload.', 400 );
		}

		$action = isset( $payload['action'] ) ? (string) $payload['action'] : '';

		// The one field taken from the request. Everything published is fetched
		// from GitHub against it - see the header.
		$tag = isset( $payload['release']['tag_name'] ) ? trim( (string) $payload['release']['tag_name'] ) : '';
		if ( '' === $tag ) {
			self::respond( 'Payload carries no release tag.', 400 );
		}

		// A release that is gone upstream. The *record* goes with it, because a
		// record is an observation of a release and there is no longer one to
		// observe. The announcement post is left alone: it is somebody's
		// writing, it has a public URL people have linked, and deleting a
		// release on GitHub is not an instruction to unpublish an article.
		if ( 'deleted' === $action ) {
			self::respond( self::forget( $tag ), 200 );
		}

		if ( ! in_array( $action, self::actions(), true ) ) {
			self::respond( "Release action '{$action}' is not one this listens for. Skipping.", 200 );
		}

		// A webhook fires because the release moved, so the twelve-hour cache
		// in front of it is exactly wrong here.
		Mudlet_Releases_Github_Client::flush( $tag );

		$raw_release = Mudlet_Releases_Github_Client::release( $tag );
		if ( ! is_array( $raw_release ) ) {
			self::respond( "GitHub has no release '{$tag}', or could not be reached.", 502 );
		}

		if ( ! empty( $raw_release['prerelease'] ) ) {
			self::respond( "{$tag} is a prerelease. Skipping.", 200 );
		}

		if ( self::major_only() && ! self::is_major( (string) $raw_release['tag_name'] ) ) {
			self::respond( "{$tag} is not a major release. Skipping.", 200 );
		}

		// The record first: the announcement post is a post about a release, and
		// the release is what this plugin actually owns.
		$record = Mudlet_Releases_Store::store( $raw_release );
		if ( $record ) {
			Mudlet_Releases_Store::store_detail( $record, true );
		}

		$post_id = self::upsert( $raw_release );
		if ( ! $post_id ) {
			self::respond( "Could not write the announcement post for {$tag}.", 500 );
		}

		$waiting = self::watch_for_assets( $tag, $record );

		self::respond(
			"{$tag}: post {$post_id}, record {$record}."
				. ( $waiting ? ' No builds on the release yet - looking again in ' . self::FOLLOWUP_MINUTES[0] . ' minutes.' : '' ),
			200
		);
	}

	// ── going back for the assets ─────────────────────────────────────

	/**
	 * Whether a stored record has download rows yet.
	 *
	 * The meta `Mudlet_Releases_Store::to_array()` reads as `builds`. Empty
	 * means the release carried no installers when it was last read, which is
	 * the ordinary state of a release seconds after it is created.
	 *
	 * @param int $record Release record post id.
	 */
	private static function has_builds( int $record ): bool {
		return ! empty( get_post_meta( $record, '_mudlet_builds', true ) );
	}

	/**
	 * Book a look at the release again, if it has no builds on it yet.
	 *
	 * @param string $tag    Release tag.
	 * @param int    $record Release record post id, 0 if it could not be stored.
	 * @param int    $step   Which rung of FOLLOWUP_MINUTES to book.
	 * @return bool Whether one was booked.
	 */
	private static function watch_for_assets( string $tag, int $record, int $step = 0 ): bool {
		if ( ! isset( self::FOLLOWUP_MINUTES[ $step ] ) ) {
			return false;
		}

		// Nothing to wait for: the release already had its installers, which is
		// what a re-delivery or an `edited` months later looks like.
		if ( $record && self::has_builds( $record ) ) {
			return false;
		}

		// Args are part of a scheduled event's identity, so a second delivery
		// for the same tag and rung finds the first still booked rather than
		// stacking a duplicate on top of it.
		if ( wp_next_scheduled( self::FOLLOWUP, array( $tag, $step ) ) ) {
			return true;
		}

		wp_schedule_single_event(
			time() + self::FOLLOWUP_MINUTES[ $step ] * MINUTE_IN_SECONDS,
			self::FOLLOWUP,
			array( $tag, $step )
		);

		return true;
	}

	/**
	 * Read the release again, and stop as soon as it has its installers.
	 *
	 * This is the whole of what the scheduled index pass used to be doing for
	 * new releases, minus the waiting: it runs because a release happened,
	 * not because an hour did.
	 *
	 * @param string $tag  Release tag.
	 * @param int    $step Which rung fired.
	 */
	public static function followup( $tag, $step = 0 ): void {
		$tag  = (string) $tag;
		$step = (int) $step;

		Mudlet_Releases_Github_Client::flush( $tag );

		$raw = Mudlet_Releases_Github_Client::release( $tag );
		if ( ! is_array( $raw ) ) {
			// GitHub was unreachable. Not the end of the chain - the next rung
			// is the retry, and that is what the ladder is for.
			self::watch_for_assets( $tag, 0, $step + 1 );
			return;
		}

		$record = Mudlet_Releases_Store::store( $raw );
		if ( $record && get_post_meta( $record, Mudlet_Releases_Store::PENDING, true ) ) {
			Mudlet_Releases_Store::store_detail( $record, true );
		}

		self::watch_for_assets( $tag, $record, $step + 1 );
	}

	/**
	 * Drop the record for a release that no longer exists upstream.
	 *
	 * @param string $tag Release tag.
	 * @return string What happened, for the delivery log.
	 */
	private static function forget( string $tag ): string {
		// A prerelease never had a record - Mudlet deletes old public test
		// builds by the dozen, and this is the answer for every one of them.
		$record = Mudlet_Releases_Store::find( $tag );
		if ( ! $record ) {
			return "{$tag} is deleted upstream; there was no record of it.";
		}

		wp_delete_post( $record->ID, true );
		Mudlet_Releases_Github_Client::flush( $tag );

		return "{$tag} is deleted upstream; record {$record->ID} removed. The announcement post, if any, is untouched.";
	}

	/**
	 * Decode the body, whichever content type the webhook is set to.
	 *
	 * GitHub sends either `application/json` - the raw body is the payload - or
	 * `application/x-www-form-urlencoded`, where it is in a `payload` field.
	 * The old plugin only understood the second, so the webhook is configured
	 * that way today; both are read here so that setting can be corrected
	 * without this breaking.
	 *
	 * @param string $raw The raw request body.
	 * @return array<string, mixed>|null
	 */
	private static function payload( string $raw ) {
		$json = '';

		// wp_unslash() and not also stripslashes(): WordPress has already added
		// one layer of slashes to $_POST and wp_unslash takes exactly that one
		// off. Stripping twice eats the backslashes in the changelog's own
		// Markdown, which is a corrupt payload that still parses.
		if ( isset( $_POST['payload'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- signed webhook, see authentic().
			$json = (string) wp_unslash( $_POST['payload'] ); // phpcs:ignore WordPress.Security.ValidationSanitization.InputNotSanitized -- decoded as JSON below.
		} elseif ( '' !== $raw && str_starts_with( ltrim( $raw ), '{' ) ) {
			$json = $raw;
		}

		if ( '' === $json ) {
			return null;
		}

		$decoded = json_decode( $json, true );

		return isset( $decoded['release'] ) && is_array( $decoded['release'] ) ? $decoded : null;
	}

	/**
	 * Whether this delivery is really from GitHub.
	 *
	 * True when no secret is configured - see the header for why that is a
	 * deliberate door and what is behind it.
	 *
	 * @param string $raw The raw request body, which is what the HMAC covers.
	 */
	private static function authentic( string $raw ): bool {
		$secret = self::secret();
		if ( '' === $secret ) {
			return true;
		}

		$sent = isset( $_SERVER['HTTP_X_HUB_SIGNATURE_256'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ) )
			: '';

		if ( '' === $sent ) {
			return false;
		}

		return hash_equals( 'sha256=' . hash_hmac( 'sha256', $raw, $secret ), $sent );
	}

	/**
	 * The shared secret, if this site has one.
	 */
	private static function secret(): string {
		$secret = defined( 'MUDLET_RELEASES_WEBHOOK_SECRET' ) ? (string) MUDLET_RELEASES_WEBHOOK_SECRET : '';

		/**
		 * Filter the webhook's shared secret.
		 *
		 * Empty means unauthenticated, and the endpoint then publishes only
		 * what it can re-read from GitHub.
		 *
		 * @param string $secret The secret.
		 */
		return (string) apply_filters( 'mudlet_releases_webhook_secret', $secret );
	}

	// ── the rules the old plugin had ──────────────────────────────────

	/**
	 * Which `action` values in the payload are acted on.
	 *
	 * The old plugin's two. GitHub also sends `published`, `released` and
	 * `prereleased` for the same moment, which are not listened for because
	 * `created` already covers it and acting on several would mean three
	 * deliveries doing the same write.
	 *
	 * @return string[]
	 */
	private static function actions(): array {
		/**
		 * Filter the release event actions that create or update a post.
		 *
		 * @param string[] $actions Action names.
		 */
		return (array) apply_filters( 'mudlet_releases_webhook_actions', array( 'created', 'edited' ) );
	}

	/**
	 * Whether only x.y.0 releases get an announcement post.
	 *
	 * **Off by default**, which is the one rule of the old plugin's deliberately
	 * not kept.
	 *
	 * It skipped any tag not ending in `.0`, and that looked like editorial
	 * policy until the site was counted: of 92 posts on mudlet.org whose title
	 * carries a version, **16 are point releases** - and three of those, 4.17.1,
	 * 4.19.1 and 4.20.1, are written in `[MudletRelease]` shortcode style, which
	 * is what a post created by hand from a copy of the last one looks like. So
	 * the rule was never the policy. It was a limitation, and somebody was
	 * working around it by hand for years.
	 *
	 * It is a real decision though, not a bug to be fixed once and hardcoded -
	 * a site that wants four announcements a year rather than twelve is
	 * entitled to say so without editing PHP. So it is a checkbox on
	 * Mudlet -> Releases, and this reads it. The filter still has the last
	 * word, the way a filter should: code beats a setting.
	 */
	private static function major_only(): bool {
		$major_only = ! Mudlet_Releases_Settings::announce_points();

		/**
		 * Filter whether only major releases get an announcement post.
		 *
		 * Overrides the Mudlet -> Releases checkbox.
		 *
		 * @param bool $major_only Whether to skip x.y.z where z is not 0.
		 */
		return (bool) apply_filters( 'mudlet_releases_webhook_major_only', $major_only );
	}

	/**
	 * Whether a tag is a major release.
	 *
	 * Only consulted when the filter above is turned on. Read off the version
	 * rather than off the end of the tag string, so `Mudlet-4.22.0`, `4.22.0`
	 * and `v4.22.0` all answer the same - and so a two-component tag like
	 * `Mudlet-4.0`, which the old plugin's `substr( $tag, -2 ) != '.0'` called
	 * major, is not mistaken for one.
	 *
	 * @param string $tag Release tag.
	 */
	private static function is_major( string $tag ): bool {
		$version = Mudlet_Releases_Release::version_from_tag( $tag );
		$parts   = explode( '.', $version );

		return count( $parts ) >= 3 && '0' === $parts[2];
	}

	// ── the post ──────────────────────────────────────────────────────

	/**
	 * Create the announcement post, or bring the existing one back in step.
	 *
	 * Found by the release tag, so a re-delivery, an `edited` event and a hand
	 * re-run are all the same operation. Only the fields GitHub owns are
	 * rewritten on an update - never the body, which by then may be somebody's
	 * opening paragraph.
	 *
	 * @param array<string, mixed> $release Raw release JSON.
	 * @return int Post id, or 0.
	 */
	private static function upsert( array $release ): int {
		$tag      = (string) $release['tag_name'];
		$title    = '' !== (string) ( $release['name'] ?? '' ) ? (string) $release['name'] : $tag;
		$status   = ! empty( $release['draft'] ) ? 'draft' : 'publish';
		$existing = self::find( $tag );

		if ( $existing ) {
			wp_update_post(
				array(
					'ID'          => $existing,
					'post_title'  => $title,
					'post_status' => $status,
				)
			);

			return $existing;
		}

		$postarr = array(
			'post_type'    => 'post',
			'post_title'   => $title,
			'post_status'  => $status,
			'post_content' => '',
			'post_author'  => self::author(),
		);

		// Dated to the release, not to the delivery, so the permalink lands in
		// the month the release happened even when this is run to backfill one.
		if ( ! empty( $release['published_at'] ) ) {
			$gmt = gmdate( 'Y-m-d H:i:s', (int) strtotime( (string) $release['published_at'] ) );

			$postarr['post_date_gmt'] = $gmt;
			$postarr['post_date']     = get_date_from_gmt( $gmt );
		}

		$category = self::category();
		if ( $category ) {
			$postarr['post_category'] = array( $category );
		}

		/**
		 * Filter the announcement post before it is created.
		 *
		 * @param array<string, mixed> $postarr wp_insert_post() arguments.
		 * @param array<string, mixed> $release Raw release JSON.
		 */
		$postarr = (array) apply_filters( 'mudlet_releases_webhook_post', $postarr, $release );

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		update_post_meta( $post_id, Mudlet_Releases_Post_Tag::META, $tag );

		// Polylang, while it is still here: a post with no language is a post
		// that does not appear in any of them. One language, because the
		// translations are what MIGRATION.md decision 4 is dropping.
		if ( function_exists( 'pll_set_post_language' ) && function_exists( 'pll_default_language' ) ) {
			pll_set_post_language( $post_id, pll_default_language() );
		}

		return (int) $post_id;
	}

	/**
	 * The announcement post for a tag, if there is one.
	 *
	 * Both meta keys, because the posts imported from mudlet.org carry the old
	 * plugin's `release-post` with a release *id* in it - so a webhook for a
	 * release that already has a post finds it rather than publishing a second.
	 *
	 * @param string $tag Release tag.
	 * @return int Post id, or 0.
	 */
	private static function find( string $tag ): int {
		$found = get_posts(
			array(
				'post_type'        => Mudlet_Releases_Post_Tag::post_types(),
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => false,
				'meta_key'         => Mudlet_Releases_Post_Tag::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $tag, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		if ( $found ) {
			return (int) $found[0];
		}

		$release = Mudlet_Releases_Github_Client::release( $tag );
		$id      = is_array( $release ) ? (string) ( $release['id'] ?? '' ) : '';
		if ( '' === $id ) {
			return 0;
		}

		$legacy = get_posts(
			array(
				'post_type'        => Mudlet_Releases_Post_Tag::post_types(),
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => false,
				'meta_key'         => Mudlet_Releases_Post_Tag::LEGACY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return $legacy ? (int) $legacy[0] : 0;
	}

	/**
	 * Who the announcement post is by.
	 *
	 * The old plugin hardcoded user 2. This asks for the site's oldest
	 * administrator instead, so a fresh install and a fork both get a real
	 * author rather than a number that means somebody else there.
	 */
	private static function author(): int {
		/**
		 * Filter the author of webhook-created release posts.
		 *
		 * @param int $author User id, or 0 to work it out.
		 */
		$author = (int) apply_filters( 'mudlet_releases_webhook_author', 0 );
		if ( $author ) {
			return $author;
		}

		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			)
		);

		return $admins ? (int) $admins[0] : 0;
	}

	/**
	 * The category release posts go in.
	 *
	 * By slug rather than by the term id the old plugin carried: 173 is a fact
	 * about one database, and it is the kind of number that is silently wrong
	 * on a staging copy. `release-en` is what mudlet.org calls it - the `-en`
	 * is Polylang's, and once that is gone somebody may well rename it to
	 * `release`, so both are tried and neither is created.
	 */
	private static function category(): int {
		/**
		 * Filter the category webhook-created release posts are filed under.
		 *
		 * @param int $term_id Category term id, or 0 to work it out by slug.
		 */
		$term_id = (int) apply_filters( 'mudlet_releases_webhook_category', 0 );
		if ( $term_id ) {
			return $term_id;
		}

		foreach ( array( 'release-en', 'release' ) as $slug ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( $term instanceof WP_Term ) {
				return (int) $term->term_id;
			}
		}

		return 0;
	}

	// ── replying ──────────────────────────────────────────────────────

	/**
	 * Answer GitHub and stop.
	 *
	 * Plain text, because the reply is read in the webhook's delivery log by
	 * somebody working out why no post appeared. A skip is a 200: it is the
	 * endpoint working, and a red delivery in that log should mean something is
	 * wrong.
	 *
	 * @param string $message What happened.
	 * @param int    $status  HTTP status.
	 */
	private static function respond( string $message, int $status ): void {
		status_header( $status );
		header( 'Content-Type: text/plain; charset=utf-8' );

		wp_die( esc_html( $message ), '', array( 'response' => $status ) );
	}
}
