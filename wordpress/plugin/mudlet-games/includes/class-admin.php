<?php
/**
 * The game record's admin screen.
 *
 * A game post is not authored, it is *observed*: every field on it is read from
 * Mudlet's src/TGameDetails.h and overwritten on the next sync. Handing that to
 * the default post editor invites exactly the wrong thing — a text box beside a
 * custom-fields table, both of which look editable and neither of which
 * survives a sync. Somebody fixes a typo, the cron job reverts it, and the
 * lesson learned is that the site is broken.
 *
 * So the editor is replaced with a reader. The screen shows what was synced,
 * where it came from, and when — and offers the only two actions that make
 * sense on a record: re-read it, or go and look at the source.
 *
 * The write guard behind it is not decoration. Read-only has to mean read-only
 * on every path, including REST and Quick Edit, or it is only a suggestion.
 *
 * @package Mudlet_Games
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read-only admin screens for the game store.
 */
class Mudlet_Games_Admin {

	const SYNC_ACTION = 'mudlet_games_sync_now';

	/**
	 * Set while the plugin itself is writing, so the guard below can tell a
	 * sync apart from a person with a form.
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

		add_filter( 'manage_' . Mudlet_Games_Store::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Mudlet_Games_Store::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . Mudlet_Games_Store::POST_TYPE, '__return_empty_array' );

		add_action( 'admin_post_' . self::SYNC_ACTION, array( __CLASS__, 'handle_sync' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	/**
	 * Whether we are looking at a game.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private static function ours( string $post_type ): bool {
		return Mudlet_Games_Store::POST_TYPE === $post_type;
	}

	/**
	 * Nothing about a record is editable by hand.
	 *
	 * Belt and braces to the screen having no inputs: this also covers REST,
	 * Quick Edit, and anybody who posts the form themselves. Status changes are
	 * let through so a record can still be trashed and restored — that is
	 * housekeeping, not authoring.
	 *
	 * @param array<string, mixed> $data    Sanitised post data, about to be written.
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
	 * The classic screen, so the boxes below are the whole page.
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

		foreach ( array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ) as $feature ) {
			remove_post_type_support( Mudlet_Games_Store::POST_TYPE, $feature );
		}

		remove_meta_box( 'submitdiv', Mudlet_Games_Store::POST_TYPE, 'side' );
		remove_meta_box( 'slugdiv', Mudlet_Games_Store::POST_TYPE, 'normal' );
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
			remove_meta_box( $box, Mudlet_Games_Store::POST_TYPE, $context );
		}

		add_meta_box(
			'mudlet-game-profile',
			__( 'Connection profile', 'mudlet-games' ),
			array( __CLASS__, 'box_profile' ),
			Mudlet_Games_Store::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'mudlet-game-description',
			__( 'Description', 'mudlet-games' ),
			array( __CLASS__, 'box_description' ),
			Mudlet_Games_Store::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'mudlet-game-record',
			__( 'Record', 'mudlet-games' ),
			array( __CLASS__, 'box_record' ),
			Mudlet_Games_Store::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * The identity and the connection facts.
	 *
	 * @param WP_Post $post Game.
	 */
	public static function box_profile( WP_Post $post ): void {
		$game = Mudlet_Games_Store::to_array( $post );

		self::styles();
		?>
		<div class="mudlet-rec">
			<div class="mudlet-rec__head">
				<?php if ( has_post_thumbnail( $post ) ) : ?>
					<span class="mudlet-rec__logo"><?php echo get_the_post_thumbnail( $post, 'medium', array( 'alt' => '' ) ); ?></span>
				<?php endif; ?>
				<div>
					<h2><?php echo esc_html( $game['name'] ); ?></h2>
					<p class="mudlet-rec__sub">
						<code><?php echo esc_html( $game['host'] . ':' . $game['port'] ); ?></code>
						<?php if ( $game['tls'] ) : ?>
							<span class="mudlet-rec__pill"><?php esc_html_e( 'secure', 'mudlet-games' ); ?></span>
						<?php endif; ?>
						<?php if ( $game['own_ui'] ) : ?>
							<span class="mudlet-rec__pill"><?php esc_html_e( 'ships its own UI', 'mudlet-games' ); ?></span>
						<?php endif; ?>
					</p>
				</div>
			</div>

			<table class="mudlet-rec__facts">
				<tbody>
					<?php
					self::fact( __( 'Host', 'mudlet-games' ), $game['host'], true );
					self::fact( __( 'Port', 'mudlet-games' ), (string) $game['port'], true );
					self::fact(
						__( 'Secure connection', 'mudlet-games' ),
						$game['tls'] ? __( 'yes', 'mudlet-games' ) : __( 'no', 'mudlet-games' )
					);

					if ( $game['alt_hosts'] ) {
						self::fact( __( 'Also reachable at', 'mudlet-games' ), implode( ', ', $game['alt_hosts'] ), true );
					}
					?>

					<tr>
						<th><?php esc_html_e( 'Website', 'mudlet-games' ); ?></th>
						<td>
							<?php if ( $game['site'] ) : ?>
								<a href="<?php echo esc_url( $game['site'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $game['domain'] ); ?></a>
							<?php else : ?>
								<span class="mudlet-rec__none"><?php esc_html_e( 'none given', 'mudlet-games' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>

					<?php if ( count( $game['links'] ) > 1 ) : ?>
						<tr>
							<th><?php esc_html_e( 'Links', 'mudlet-games' ); ?></th>
							<td>
								<?php foreach ( $game['links'] as $i => $link ) : ?>
									<?php echo $i ? ' &middot; ' : ''; ?>
									<a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link['label'] ); ?></a>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endif; ?>

					<?php
					self::fact( __( 'Logo file', 'mudlet-games' ), $game['icon'], true );
					self::fact( __( 'Upstream name', 'mudlet-games' ), (string) get_post_meta( $post->ID, Mudlet_Games_Store::KEY, true ), true );
					?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * The blurb, as the game page shows it.
	 *
	 * @param WP_Post $post Game.
	 */
	public static function box_description( WP_Post $post ): void {
		self::styles();

		if ( '' === trim( $post->post_content ) ) {
			echo '<p class="mudlet-rec__none">' . esc_html__( 'Mudlet ships no description for this game.', 'mudlet-games' ) . '</p>';
			return;
		}

		echo '<div class="mudlet-rec__prose">' . wp_kses_post( wpautop( $post->post_content ) ) . '</div>';
	}

	/**
	 * Where it came from, and the two things you can do about it.
	 *
	 * @param WP_Post $post Game.
	 */
	public static function box_record( WP_Post $post ): void {
		$synced = (int) get_option( Mudlet_Games_Sync::SYNCED );
		$source = Mudlet_Games_Source::raw_base() . '/' . Mudlet_Games_Source::HEADER;

		self::styles();
		?>
		<div class="mudlet-rec">
			<p class="mudlet-rec__note">
				<?php
				esc_html_e(
					'Read-only. Every field here is read from Mudlet and rewritten on the next sync — to change one, change it in Mudlet.',
					'mudlet-games'
				);
				?>
			</p>

			<table class="mudlet-rec__facts">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Source', 'mudlet-games' ); ?></th>
						<td><a href="<?php echo esc_url( $source ); ?>" target="_blank" rel="noopener noreferrer">src/TGameDetails.h</a></td>
					</tr>
					<?php
					self::fact(
						__( 'Last synced', 'mudlet-games' ),
						$synced
							/* translators: %s: human-readable time difference */
							? sprintf( __( '%s ago', 'mudlet-games' ), human_time_diff( $synced ) )
							: __( 'never', 'mudlet-games' )
					);
					self::fact( __( 'Games on record', 'mudlet-games' ), (string) Mudlet_Games_Sync::count() );
					?>
					<tr>
						<th><?php esc_html_e( 'Page', 'mudlet-games' ); ?></th>
						<td><a href="<?php echo esc_url( (string) get_permalink( $post ) ); ?>"><?php esc_html_e( 'View on the site', 'mudlet-games' ); ?></a></td>
					</tr>
				</tbody>
			</table>

			<p class="mudlet-rec__actions">
				<a class="button button-primary" href="<?php echo esc_url( self::sync_url() ); ?>">
					<?php esc_html_e( 'Sync from Mudlet', 'mudlet-games' ); ?>
				</a>
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
	 * Logo, host and website instead of a date nobody set.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		return array(
			'cb'            => $columns['cb'] ?? '',
			'mudlet_logo'   => __( 'Logo', 'mudlet-games' ),
			'title'         => __( 'Game', 'mudlet-games' ),
			'mudlet_host'   => __( 'Connects to', 'mudlet-games' ),
			'mudlet_site'   => __( 'Website', 'mudlet-games' ),
		);
	}

	/**
	 * Fill one.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post id.
	 */
	public static function column( $column, $post_id ): void {
		$meta = Mudlet_Games_Store::META;

		switch ( $column ) {
			case 'mudlet_logo':
				echo has_post_thumbnail( $post_id )
					? get_the_post_thumbnail( $post_id, array( 96, 32 ), array( 'alt' => '', 'style' => 'max-width:96px;height:auto' ) )
					: '&mdash;';
				break;

			case 'mudlet_host':
				$host = (string) get_post_meta( $post_id, $meta['host'], true );
				$port = (string) get_post_meta( $post_id, $meta['port'], true );
				echo '<code>' . esc_html( $host . ':' . $port ) . '</code>';
				if ( get_post_meta( $post_id, $meta['tls'], true ) ) {
					echo ' <span class="mudlet-rec__pill">' . esc_html__( 'secure', 'mudlet-games' ) . '</span>';
				}
				break;

			case 'mudlet_site':
				$site   = (string) get_post_meta( $post_id, $meta['site'], true );
				$domain = (string) get_post_meta( $post_id, $meta['domain'], true );
				if ( $site ) {
					printf(
						'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_url( $site ),
						esc_html( $domain )
					);
				} else {
					echo '&mdash;';
				}
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
				'>' . esc_html__( 'View record', 'mudlet-games' ) . '<',
				$actions['edit']
			);
		}

		return $actions;
	}

	// ── sync now ──────────────────────────────────────────────────────

	/**
	 * The nonce-protected URL behind the button.
	 *
	 * @return string
	 */
	public static function sync_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => self::SYNC_ACTION,
					'redirect' => rawurlencode( (string) ( $_SERVER['REQUEST_URI'] ?? '' ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				),
				admin_url( 'admin-post.php' )
			),
			self::SYNC_ACTION
		);
	}

	/**
	 * Run a sync and come back.
	 */
	public static function handle_sync(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'mudlet-games' ) );
		}
		check_admin_referer( self::SYNC_ACTION );

		self::$writing = true;
		$result        = Mudlet_Games_Sync::sync( true );
		self::$writing = false;

		$back = isset( $_GET['redirect'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? rawurldecode( sanitize_text_field( wp_unslash( $_GET['redirect'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification
			: admin_url( 'edit.php?post_type=' . Mudlet_Games_Store::POST_TYPE );

		wp_safe_redirect(
			add_query_arg(
				array(
					'mudlet_games_synced' => '' === $result['error'] ? (int) $result['written'] : -1,
				),
				$back
			)
		);
		exit;
	}

	/**
	 * Say how it went.
	 */
	public static function notices(): void {
		if ( ! isset( $_GET['mudlet_games_synced'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$written = (int) $_GET['mudlet_games_synced']; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $written < 0 ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html__( 'Could not read the games list from GitHub.', 'mudlet-games' )
			);
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of games written */
					_n( '%d game synced from Mudlet.', '%d games synced from Mudlet.', $written, 'mudlet-games' ),
					$written
				)
			)
		);
	}

	/**
	 * Screen styles, printed once.
	 *
	 * Inline because it is thirty lines used on two screens; a stylesheet to
	 * enqueue and version would be more moving parts than the thing it styles.
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
			.mudlet-rec__logo img{display:block;max-height:44px;width:auto;background:#fff;
				border:1px solid #dcdcde;border-radius:4px;padding:3px}
			.mudlet-rec__sub{margin:4px 0 0;color:#646970}
			.mudlet-rec__pill{display:inline-block;margin-left:6px;padding:1px 7px;border-radius:9px;
				background:#f0f0f1;border:1px solid #dcdcde;font-size:11px;color:#50575e;vertical-align:1px}
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
			.mudlet-rec__people{display:flex;flex-wrap:wrap;gap:8px;margin:0}
			.mudlet-rec__person{display:flex;align-items:center;gap:7px;padding:5px 10px 5px 5px;
				border:1px solid #dcdcde;border-radius:20px;background:#fff;text-decoration:none}
			.mudlet-rec__person img{width:24px;height:24px;border-radius:50%;display:block}
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
