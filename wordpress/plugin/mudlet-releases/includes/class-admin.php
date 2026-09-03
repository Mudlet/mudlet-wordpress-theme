<?php
/**
 * The release record's admin screen.
 *
 * A `mudlet_release` is a cache of record, not a document: version, date,
 * assets, sizes, checksums, counts, changelog and contributors are all read
 * from the GitHub release and rewritten by the next sync. The default post
 * editor offers a title field and a body box for exactly those, which is an
 * invitation to type a checksum that will be silently replaced on Thursday.
 *
 * So the editor is replaced with a reader: what was synced, where it came from,
 * and the two actions that make sense on a record — re-read it, or go look at
 * the release on GitHub.
 *
 * Note the boundary. This is the *record*. The release **announcement post** is
 * an ordinary post somebody writes, and nothing here touches it.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read-only admin screens for the release store.
 */
class Mudlet_Releases_Admin {

	const SYNC_ACTION = 'mudlet_releases_sync_now';

	/**
	 * Set while the plugin itself is writing.
	 *
	 * @var bool
	 */
	public static $writing = false;

	/**
	 * Hook up.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'boxes' ) );
		add_action( 'load-post.php', array( __CLASS__, 'strip_editor' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'strip_editor' ) );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'no_block_editor' ), 10, 2 );

		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard' ), 10, 2 );

		add_filter( 'manage_' . Mudlet_Releases_Store::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Mudlet_Releases_Store::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . Mudlet_Releases_Store::POST_TYPE, '__return_empty_array' );

		add_action( 'manage_posts_extra_tablenav', array( __CLASS__, 'tablenav' ) );
		add_filter( 'mudlet_sync_jobs', array( __CLASS__, 'sync_jobs' ) );

		add_action( 'admin_post_' . self::SYNC_ACTION, array( __CLASS__, 'handle_sync' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	/**
	 * Whether we are looking at a release record.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private static function ours( string $post_type ): bool {
		return Mudlet_Releases_Store::POST_TYPE === $post_type;
	}

	/**
	 * Nothing about a record is editable by hand.
	 *
	 * Covers REST and Quick Edit as well as the screen, which has no inputs to
	 * begin with. Status changes pass so a record can still be trashed.
	 *
	 * @param array<string, mixed> $data    Sanitised post data.
	 * @param array<string, mixed> $postarr Raw post array.
	 * @return array<string, mixed>
	 */
	public static function guard( array $data, array $postarr ): array {
		if ( ! self::ours( (string) ( $data['post_type'] ?? '' ) ) || self::$writing ) {
			return $data;
		}

		$existing = empty( $postarr['ID'] ) ? null : get_post( (int) $postarr['ID'] );
		if ( ! $existing ) {
			return $data;
		}

		foreach ( array( 'post_title', 'post_content', 'post_excerpt', 'post_name' ) as $field ) {
			$data[ $field ] = $existing->$field;
		}

		return $data;
	}

	/**
	 * The classic screen.
	 *
	 * @param bool   $use       Whether to use the block editor.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function no_block_editor( $use, $post_type ) {
		return self::ours( (string) $post_type ) ? false : $use;
	}

	/**
	 * Take away the default fields.
	 *
	 * On load-post, not admin_head: core registers the excerpt, custom-fields
	 * and featured-image boxes while building the screen, which is before
	 * admin_head fires. Removing support there left all three on the page.
	 *
	 * Support is dropped for this request only, so the list table keeps its
	 * title column and REST keeps its shape.
	 */
	public static function strip_editor(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! self::ours( (string) $screen->post_type ) ) {
			return;
		}

		foreach ( array( 'title', 'editor', 'custom-fields' ) as $feature ) {
			remove_post_type_support( Mudlet_Releases_Store::POST_TYPE, $feature );
		}

		remove_meta_box( 'submitdiv', Mudlet_Releases_Store::POST_TYPE, 'side' );
		remove_meta_box( 'slugdiv', Mudlet_Releases_Store::POST_TYPE, 'normal' );
	}

	/**
	 * Add ours.
	 */
	public static function boxes(): void {
		if ( ! self::ours( (string) get_post_type() ) ) {
			return;
		}

		// Belt to the braces of removing support above: whatever core has
		// already registered by now goes, including the Publish box - there is
		// nothing on this screen to publish.
		foreach ( array( 'submitdiv' => 'side', 'postimagediv' => 'side', 'slugdiv' => 'normal', 'postexcerpt' => 'normal', 'postcustom' => 'normal' ) as $box => $context ) {
			remove_meta_box( $box, Mudlet_Releases_Store::POST_TYPE, $context );
		}

		$type = Mudlet_Releases_Store::POST_TYPE;

		add_meta_box( 'mudlet-release-summary', __( 'Release', 'mudlet-releases' ), array( __CLASS__, 'box_summary' ), $type, 'normal', 'high' );
		add_meta_box( 'mudlet-release-people', __( 'Contributors', 'mudlet-releases' ), array( __CLASS__, 'box_people' ), $type, 'normal', 'default' );
		add_meta_box( 'mudlet-release-builds', __( 'Downloads', 'mudlet-releases' ), array( __CLASS__, 'box_builds' ), $type, 'normal', 'default' );
		add_meta_box( 'mudlet-release-notes', __( 'Release notes', 'mudlet-releases' ), array( __CLASS__, 'box_notes' ), $type, 'normal', 'low' );
		add_meta_box( 'mudlet-release-record', __( 'Record', 'mudlet-releases' ), array( __CLASS__, 'box_record' ), $type, 'side', 'high' );
	}

	/**
	 * Version, date, and the counts panel.
	 *
	 * @param WP_Post $post Release.
	 */
	public static function box_summary( WP_Post $post ): void {
		$release = Mudlet_Releases_Store::to_array( $post );

		self::styles();
		?>
		<div class="mudlet-rec">
			<div class="mudlet-rec__head">
				<div>
					<h2>
						<?php echo esc_html( $release['name'] ? $release['name'] : $release['tag'] ); ?>
						<?php if ( $release['prerelease'] ) : ?>
							<span class="mudlet-rec__pill"><?php esc_html_e( 'prerelease', 'mudlet-releases' ); ?></span>
						<?php endif; ?>
					</h2>
					<p class="mudlet-rec__sub">
						<code><?php echo esc_html( $release['tag'] ); ?></code>
						&middot; <?php echo esc_html( $release['date'] ); ?>
					</p>
				</div>
			</div>

			<?php if ( $release['counts'] ) : ?>
				<p class="mudlet-rec__counts">
					<?php foreach ( $release['counts'] as $count ) : ?>
						<span><b><?php echo esc_html( $count[0] ); ?></b> <?php echo esc_html( $count[1] ); ?></span>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<table class="mudlet-rec__facts">
				<tbody>
					<?php
					self::fact( __( 'Version', 'mudlet-releases' ), $release['version'], true );
					self::fact( __( 'Tag', 'mudlet-releases' ), $release['tag'], true );
					self::fact( __( 'Published', 'mudlet-releases' ), $release['date'] );
					self::fact( __( 'GitHub id', 'mudlet-releases' ), (string) $release['id'], true );
					self::fact(
						__( 'Counts from', 'mudlet-releases' ),
						'pulls' === $release['counts_from']
							? __( 'merged pull requests', 'mudlet-releases' )
							: __( "the release notes' own headings", 'mudlet-releases' )
					);
					?>
					<tr>
						<th><?php esc_html_e( 'On GitHub', 'mudlet-releases' ); ?></th>
						<td>
							<?php if ( $release['url'] ) : ?>
								<a href="<?php echo esc_url( $release['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $release['url'] ); ?></a>
							<?php else : ?>
								<span class="mudlet-rec__none">&mdash;</span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Who wrote it.
	 *
	 * @param WP_Post $post Release.
	 */
	public static function box_people( WP_Post $post ): void {
		$people = (array) get_post_meta( $post->ID, '_mudlet_contributors', true );

		self::styles();

		if ( ! $people ) {
			echo '<p class="mudlet-rec__none">' . esc_html__( 'No contributor list — this record has no compare against a previous release, or its detail pass has not run yet.', 'mudlet-releases' ) . '</p>';
			return;
		}

		printf(
			'<p class="mudlet-rec__sub">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: number of people, 2: number of commits */
					_n( '%1$d person, %2$d commits since the previous release.', '%1$d people, %2$d commits since the previous release.', count( $people ), 'mudlet-releases' ),
					count( $people ),
					array_sum( array_column( $people, 'commits' ) )
				)
			)
		);

		echo '<p class="mudlet-rec__people">';
		foreach ( $people as $person ) {
			$name    = (string) ( $person['name'] ?? '' );
			$commits = (int) ( $person['commits'] ?? 0 );
			$url     = (string) ( $person['url'] ?? '' );
			$avatar  = (string) ( $person['avatar'] ?? '' );

			$tag = $url ? 'a' : 'span';
			printf(
				'<%1$s class="mudlet-rec__person"%2$s>',
				esc_attr( $tag ),
				$url ? ' href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer"' : ''
			);

			if ( $avatar ) {
				printf( '<img src="%s" alt="" loading="lazy" decoding="async">', esc_url( $avatar . '&s=48' ) );
			}

			printf( '<b>%s</b> <span>%d</span>', esc_html( $name ), $commits );
			printf( '</%s>', esc_attr( $tag ) );
		}
		echo '</p>';
	}

	/**
	 * The assets, with their sizes and checksums.
	 *
	 * @param WP_Post $post Release.
	 */
	public static function box_builds( WP_Post $post ): void {
		$builds = (array) get_post_meta( $post->ID, '_mudlet_builds', true );

		self::styles();

		if ( ! $builds ) {
			echo '<p class="mudlet-rec__none">' . esc_html__( 'No download rows on this record.', 'mudlet-releases' ) . '</p>';
			return;
		}
		?>
		<table class="mudlet-rec__builds">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Platform', 'mudlet-releases' ); ?></th>
					<th><?php esc_html_e( 'File', 'mudlet-releases' ); ?></th>
					<th><?php esc_html_e( 'Size', 'mudlet-releases' ); ?></th>
					<th><?php esc_html_e( 'SHA-256', 'mudlet-releases' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $builds as $build ) : ?>
					<tr>
						<td><?php echo esc_html( (string) ( $build['label'] ?? '' ) ); ?></td>
						<td>
							<?php if ( ! empty( $build['url'] ) ) : ?>
								<a href="<?php echo esc_url( $build['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) ( $build['file'] ?? '' ) ); ?></a>
							<?php else : ?>
								<?php echo esc_html( (string) ( $build['file'] ?? '' ) ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) ( $build['size'] ?? '' ) ); ?></td>
						<td>
							<?php if ( ! empty( $build['sha'] ) ) : ?>
								<code><?php echo esc_html( (string) $build['sha'] ); ?></code>
							<?php else : ?>
								<span class="mudlet-rec__none"><?php esc_html_e( 'not fetched', 'mudlet-releases' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * The release's own notes, rendered.
	 *
	 * @param WP_Post $post Release.
	 */
	public static function box_notes( WP_Post $post ): void {
		self::styles();

		if ( '' === trim( $post->post_content ) ) {
			echo '<p class="mudlet-rec__none">' . esc_html__( 'This release has no notes.', 'mudlet-releases' ) . '</p>';
			return;
		}

		echo '<div class="mudlet-rec__prose">' . wp_kses_post( Mudlet_Releases_Markdown::to_html( $post->post_content ) ) . '</div>';
	}

	/**
	 * Where it came from.
	 *
	 * @param WP_Post $post Release.
	 */
	public static function box_record( WP_Post $post ): void {
		$synced  = (int) get_post_meta( $post->ID, '_mudlet_synced', true );
		$pending = (bool) get_post_meta( $post->ID, Mudlet_Releases_Store::PENDING, true );

		self::styles();
		?>
		<div class="mudlet-rec">
			<p class="mudlet-rec__note">
				<?php
				esc_html_e(
					'Read-only. Every field here is read from the GitHub release and rewritten on the next sync. The announcement post is a separate, ordinary post.',
					'mudlet-releases'
				);
				?>
			</p>

			<table class="mudlet-rec__facts">
				<tbody>
					<?php
					self::fact( __( 'Source', 'mudlet-releases' ), 'github.com/' . Mudlet_Releases_Github_Client::repo() );
					self::fact(
						__( 'Last synced', 'mudlet-releases' ),
						$synced
							/* translators: %s: human-readable time difference */
							? sprintf( __( '%s ago', 'mudlet-releases' ), human_time_diff( $synced ) )
							: __( 'never', 'mudlet-releases' )
					);
					self::fact(
						__( 'Detail pass', 'mudlet-releases' ),
						$pending
							? __( 'still outstanding', 'mudlet-releases' )
							: __( 'done', 'mudlet-releases' )
					);
					?>
				</tbody>
			</table>

			<p class="mudlet-rec__actions">
				<a class="button button-primary" href="<?php echo esc_url( self::sync_url( $post->ID ) ); ?>">
					<?php esc_html_e( 'Re-read from GitHub', 'mudlet-releases' ); ?>
				</a>
			</p>
			<p class="mudlet-rec__sub">
				<?php esc_html_e( 'Costs a handful of API requests — anonymous GitHub allows 60 an hour.', 'mudlet-releases' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * One label/value row.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param bool   $mono  Render as code.
	 */
	private static function fact( string $label, string $value, bool $mono = false ): void {
		echo '<tr><th>' . esc_html( $label ) . '</th><td>';
		if ( '' === trim( $value ) ) {
			echo '<span class="mudlet-rec__none">&mdash;</span>';
		} elseif ( $mono ) {
			echo '<code>' . esc_html( $value ) . '</code>';
		} else {
			echo esc_html( $value );
		}
		echo '</td></tr>';
	}

	// ── the list table ────────────────────────────────────────────────

	/**
	 * Version, date and what is on the record.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		return array(
			'cb'                  => $columns['cb'] ?? '',
			'title'               => __( 'Release', 'mudlet-releases' ),
			'mudlet_version'      => __( 'Version', 'mudlet-releases' ),
			'mudlet_builds'       => __( 'Downloads', 'mudlet-releases' ),
			'mudlet_contributors' => __( 'Contributors', 'mudlet-releases' ),
			'date'                => $columns['date'] ?? __( 'Date' ),
		);
	}

	/**
	 * Fill one.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post id.
	 */
	public static function column( $column, $post_id ): void {
		switch ( $column ) {
			case 'mudlet_version':
				$version = (string) get_post_meta( $post_id, '_mudlet_version', true );
				echo $version ? '<code>' . esc_html( $version ) . '</code>' : '&mdash;';
				if ( get_post_meta( $post_id, '_mudlet_prerelease', true ) === '1' ) {
					echo ' <span class="mudlet-rec__pill">' . esc_html__( 'pre', 'mudlet-releases' ) . '</span>';
				}
				break;

			case 'mudlet_builds':
				$builds = (array) get_post_meta( $post_id, '_mudlet_builds', true );
				$hashed = count( array_filter( array_column( $builds, 'sha' ) ) );
				if ( ! $builds ) {
					echo '&mdash;';
					break;
				}
				printf(
					/* translators: 1: number of assets, 2: how many have a checksum */
					esc_html__( '%1$d files, %2$d hashed', 'mudlet-releases' ),
					count( $builds ),
					(int) $hashed
				);
				break;

			case 'mudlet_contributors':
				$people = (array) get_post_meta( $post_id, '_mudlet_contributors', true );
				if ( ! $people ) {
					echo '&mdash;';
					break;
				}
				echo esc_html( (string) count( $people ) );
				echo ' <span class="mudlet-rec__none">(' . esc_html( (string) array_sum( array_column( $people, 'commits' ) ) ) . ' commits)</span>';
				break;
		}
	}

	/**
	 * No Quick Edit on something that cannot be edited.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param WP_Post               $post    Post.
	 * @return array<string, string>
	 */
	public static function row_actions( array $actions, WP_Post $post ): array {
		if ( ! self::ours( $post->post_type ) ) {
			return $actions;
		}

		unset( $actions['inline hide-if-no-js'] );

		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = str_replace(
				'>' . __( 'Edit' ) . '<',
				'>' . esc_html__( 'View record', 'mudlet-releases' ) . '<',
				$actions['edit']
			);
		}

		return $actions;
	}

	// ── sync now ──────────────────────────────────────────────────────

	/**
	 * Put the three release jobs on the Mudlet → Sync screen.
	 *
	 * Three, because they are three different bargains: one request for the
	 * list, a rationed drain for the expensive half, and a cache warm. Rolling
	 * them into one row would hide the only one that has a rate limit behind
	 * it.
	 *
	 * @param array<string, array<string, mixed>> $jobs Registered syncs.
	 * @return array<string, array<string, mixed>>
	 */
	public static function sync_jobs( array $jobs ): array {
		$count   = (int) ( wp_count_posts( Mudlet_Releases_Store::POST_TYPE )->publish ?? 0 );
		$pending = count( Mudlet_Releases_Store::pending( 100 ) );
		$latest  = Mudlet_Releases_Store::latest();

		$jobs[ Mudlet_Releases_Sync::INDEX ] = array(
			'label'    => __( 'Releases', 'mudlet-releases' ),
			'note'     => __( 'One request to GitHub for the whole list: names, dates and download rows. An announcement post fetches its own tag, so this is only how a release nobody has written about yet arrives.', 'mudlet-releases' ),
			'default'  => Mudlet_Releases_Sync::EVERY,
			'synced'   => $latest ? (int) get_post_meta( $latest->ID, '_mudlet_synced', true ) : 0,
			'summary'  => sprintf(
				/* translators: %d: number of releases on record */
				_n( '%d on record', '%d on record', $count, 'mudlet-releases' ),
				$count
			),
			'sync_url' => self::sync_url(),
		);

		$jobs[ Mudlet_Releases_Sync::DETAIL ] = array(
			'label'   => __( 'Release changelogs', 'mudlet-releases' ),
			'note'    => __( 'Changelogs, contributors and SHA-256 sums, two releases per run — anonymous GitHub allows sixty requests an hour. Costs nothing when nothing is outstanding, so hourly is a drain rather than a poll.', 'mudlet-releases' ),
			'default' => Mudlet_Releases_Sync::EVERY_DETAIL,
			'synced'  => 0,
			'summary' => $pending
				? sprintf(
					/* translators: %d: number of releases awaiting the detail pass */
					_n( '%d outstanding', '%d outstanding', $pending, 'mudlet-releases' ),
					$pending
				)
				: __( 'nothing outstanding', 'mudlet-releases' ),
		);

		$jobs['mudlet_releases_refresh'] = array(
			'label'   => __( 'Latest release, cached', 'mudlet-releases' ),
			'note'    => __( 'One request that keeps the newest release warm, so a visitor never waits on GitHub for the download page.', 'mudlet-releases' ),
			'default' => 'daily',
			'synced'  => 0,
		);

		return $jobs;
	}

	/**
	 * The button, over the list.
	 *
	 * Unlike the games and makers plugins, the record screen keeps a button of
	 * its own: a release is read one at a time, and "re-read this one" is a
	 * real thing to want. This one is the other half — the cheap index pass,
	 * which is how a *new* release arrives, and the only way to press anything
	 * on a site that has no records yet.
	 *
	 * Index only, deliberately. The detail pass costs a compare of up to six
	 * pages per release, and anonymous GitHub allows sixty requests an hour;
	 * the hourly job spends that budget two records at a time.
	 *
	 * @param string $which Which tablenav is being drawn.
	 */
	public static function tablenav( string $which ): void {
		$screen = get_current_screen();

		if ( 'top' !== $which || ! $screen || ! self::ours( (string) $screen->post_type ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$count   = (int) ( wp_count_posts( Mudlet_Releases_Store::POST_TYPE )->publish ?? 0 );
		$pending = count( Mudlet_Releases_Store::pending( 100 ) );
		?>
		<div class="alignleft actions">
			<a class="button" href="<?php echo esc_url( self::sync_url() ); ?>">
				<?php esc_html_e( 'Check GitHub for releases', 'mudlet-releases' ); ?>
			</a>
			<span class="mudlet-sync__note">
				<?php
				if ( ! $count ) {
					esc_html_e( 'Nothing on record yet — the index pass runs twice a day.', 'mudlet-releases' );
				} elseif ( $pending ) {
					printf(
						/* translators: 1: number of releases on record, 2: number still awaiting the detail pass */
						esc_html__( '%1$d on record · %2$d still awaiting changelogs and checksums, two an hour.', 'mudlet-releases' ),
						(int) $count,
						(int) $pending
					);
				} else {
					printf(
						/* translators: %d: number of releases on record */
						esc_html( _n( '%d release on record.', '%d releases on record.', $count, 'mudlet-releases' ) ),
						(int) $count
					);
				}
				?>
			</span>
		</div>
		<style>
			.mudlet-sync__note{display:inline-block;margin-left:9px;line-height:30px;color:#646970}
		</style>
		<?php
	}

	/**
	 * The nonce-protected URL behind the button.
	 *
	 * @param int $post_id Release to re-read, or 0 for the index pass.
	 * @return string
	 */
	public static function sync_url( int $post_id = 0 ): string {
		$args = array(
			'action'   => self::SYNC_ACTION,
			'redirect' => rawurlencode( (string) ( $_SERVER['REQUEST_URI'] ?? '' ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		);

		if ( $post_id ) {
			$args['post'] = $post_id;
		}

		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			self::SYNC_ACTION
		);
	}

	/**
	 * Re-read one release — or, with no post, run the index pass — and come back.
	 */
	public static function handle_sync(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'mudlet-releases' ) );
		}
		check_admin_referer( self::SYNC_ACTION );

		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! $post_id ) {
			self::handle_index();
			return;
		}

		$tag = (string) get_post_meta( $post_id, '_mudlet_tag', true );

		$ok = false;
		if ( $tag ) {
			// Drop the cached compare first, or "re-read" would hand back the
			// same answer it already had.
			Mudlet_Releases_Github_Client::flush( $tag );

			self::$writing = true;
			$ok            = Mudlet_Releases_Store::store_detail( $post_id, true );
			self::$writing = false;
		}

		$back = isset( $_GET['redirect'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? rawurldecode( sanitize_text_field( wp_unslash( $_GET['redirect'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification
			: admin_url( 'edit.php?post_type=' . Mudlet_Releases_Store::POST_TYPE );

		wp_safe_redirect( add_query_arg( array( 'mudlet_release_synced' => $ok ? 1 : 0 ), $back ) );
		exit;
	}

	/**
	 * Run the index pass and come back.
	 *
	 * One request, whatever it finds: names, dates, versions and download rows
	 * for every release GitHub lists. The changelogs and checksums behind them
	 * are the hourly job's, not this button's.
	 */
	private static function handle_index(): void {
		self::$writing = true;
		$written       = Mudlet_Releases_Sync::sync_index();
		self::$writing = false;

		$back = isset( $_GET['redirect'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? rawurldecode( sanitize_text_field( wp_unslash( $_GET['redirect'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification
			: admin_url( 'edit.php?post_type=' . Mudlet_Releases_Store::POST_TYPE );

		wp_safe_redirect( add_query_arg( array( 'mudlet_releases_indexed' => $written ), $back ) );
		exit;
	}

	/**
	 * Say how it went.
	 */
	public static function notices(): void {
		if ( isset( $_GET['mudlet_releases_indexed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			self::index_notice( (int) $_GET['mudlet_releases_indexed'] ); // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! isset( $_GET['mudlet_release_synced'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$ok = '1' === (string) $_GET['mudlet_release_synced']; // phpcs:ignore WordPress.Security.NonceVerification

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			$ok ? 'success' : 'error',
			esc_html(
				$ok
					? __( 'Release re-read from GitHub.', 'mudlet-releases' )
					: __( 'Could not re-read that release — GitHub may be rate limiting anonymous requests.', 'mudlet-releases' )
			)
		);
	}

	/**
	 * Say how the index pass went.
	 *
	 * A failed request is told apart from an empty one because sync_index()
	 * answers -1 rather than nought. Nought itself is rare — the pass rewrites
	 * every release GitHub lists rather than only the new ones — and means
	 * GitHub answered with nothing this plugin counts as a release.
	 *
	 * @param int $written How many records were written, or -1 for a failure.
	 */
	private static function index_notice( int $written ): void {
		if ( $written < 0 ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html__(
					'Could not read the releases list — GitHub may be rate limiting anonymous requests.',
					'mudlet-releases'
				)
			);
			return;
		}

		if ( 0 === $written ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'GitHub listed no releases to index — everything it has is a draft or a pre-release.', 'mudlet-releases' )
			);
			return;
		}

		$pending = count( Mudlet_Releases_Store::pending( 100 ) );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of releases indexed */
					_n( '%d release indexed.', '%d releases indexed.', $written, 'mudlet-releases' ),
					$written
				)
				. ( $pending
					? ' ' . sprintf(
						/* translators: %d: number of releases still awaiting the detail pass */
						_n(
							'%d is still awaiting its changelog and checksums, which the hourly pass fills in two at a time.',
							'%d are still awaiting their changelogs and checksums, which the hourly pass fills in two at a time.',
							$pending,
							'mudlet-releases'
						),
						$pending
					)
					: ''
				)
			)
		);
	}

	/**
	 * Screen styles, printed once.
	 */
	private static function styles(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		?>
		<style>
			.mudlet-rec__head{display:flex;gap:16px;align-items:center;margin:0 0 14px}
			.mudlet-rec__head h2{margin:0;font-size:1.3em}
			.mudlet-rec__sub{margin:4px 0 0;color:#646970}
			.mudlet-rec__pill{display:inline-block;margin-left:6px;padding:1px 7px;border-radius:9px;
				background:#f0f0f1;border:1px solid #dcdcde;font-size:11px;color:#50575e;vertical-align:2px}
			.mudlet-rec__counts{display:flex;flex-wrap:wrap;gap:18px;margin:0 0 14px;padding:10px 12px;
				background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px}
			.mudlet-rec__counts b{font-size:16px}
			.mudlet-rec__facts{width:100%;border-collapse:collapse}
			.mudlet-rec__facts th{width:11em;text-align:left;padding:6px 12px 6px 0;vertical-align:top;
				font-weight:600;color:#50575e}
			.mudlet-rec__facts td{padding:6px 0;vertical-align:top}
			.mudlet-rec__none{color:#8c8f94}
			.mudlet-rec__note{margin:0 0 12px;padding:9px 11px;border-left:3px solid #dba617;
				background:#fcf9e8;color:#50575e}
			.mudlet-rec__actions{margin:14px 0 2px}
			.mudlet-rec__prose{max-width:60em;color:#3c434a}
			.mudlet-rec__prose p:first-child{margin-top:0}
			.mudlet-rec__people{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 0}
			.mudlet-rec__person{display:flex;align-items:center;gap:7px;padding:5px 11px 5px 5px;
				border:1px solid #dcdcde;border-radius:20px;background:#fff;text-decoration:none;color:#2c3338}
			.mudlet-rec__person img{width:24px;height:24px;border-radius:50%;display:block;background:#f0f0f1}
			.mudlet-rec__person b{font-weight:600}
			.mudlet-rec__person span{color:#646970;font-size:12px}
			.mudlet-rec__builds{width:100%;border-collapse:collapse}
			.mudlet-rec__builds th{text-align:left;padding:6px 12px 6px 0;color:#50575e}
			.mudlet-rec__builds td{padding:6px 12px 6px 0;border-top:1px solid #f0f0f1;vertical-align:top}
			.mudlet-rec__builds code{font-size:11px;word-break:break-all}
		</style>
		<?php
	}
}
