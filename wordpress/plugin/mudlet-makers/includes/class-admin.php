<?php
/**
 * The maker record's admin screen.
 *
 * A maker post is not authored, it is *observed*: every field on it is read
 * from Mudlet's src/dlgAboutDialog.cpp and overwritten on the next sync.
 * Handing that to the default post editor invites exactly the wrong thing — a
 * text box beside a custom-fields table, both of which look editable and
 * neither of which survives a sync. Somebody rewrites a bio, the cron job
 * reverts it, and the lesson learned is that the site is broken.
 *
 * There is a second reason here that the games plugin does not have. These are
 * people. A screen that invites an editor to reword what somebody wrote about
 * their own contribution is a bad idea even when the sync is not about to undo
 * it — that sentence is theirs, and the place to change it is the client, in a
 * pull request they can see.
 *
 * So the editor is replaced with a reader, and the write guard behind it holds
 * on every path, including REST and Quick Edit, or it is only a suggestion.
 *
 * @package Mudlet_Makers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read-only admin screens for the maker store.
 */
class Mudlet_Makers_Admin {

	const SYNC_ACTION = 'mudlet_makers_sync_now';

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

		add_filter( 'manage_' . Mudlet_Makers_Store::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Mudlet_Makers_Store::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . Mudlet_Makers_Store::POST_TYPE, '__return_empty_array' );

		add_action( 'pre_get_posts', array( __CLASS__, 'list_order' ) );

		add_action( 'admin_post_' . self::SYNC_ACTION, array( __CLASS__, 'handle_sync' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	/**
	 * List the records the way the About dialog does.
	 *
	 * The default is publish date descending, which for thirty posts all
	 * written by one sync in one second is an arbitrary order — and it hides
	 * the one thing the list does say, which is who is on the project now.
	 * Left sortable: clicking a column header still overrides this.
	 *
	 * @param WP_Query $query The query.
	 */
	public static function list_order( $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || ! self::ours( (string) $query->get( 'post_type' ) ) ) {
			return;
		}

		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'menu_order' );
			$query->set( 'order', 'ASC' );
		}
	}

	/**
	 * Whether we are looking at a maker.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private static function ours( string $post_type ): bool {
		return Mudlet_Makers_Store::POST_TYPE === $post_type;
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

		foreach ( array( 'post_title', 'post_content', 'post_excerpt', 'post_name', 'menu_order' ) as $field ) {
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
	 * On load-post, not admin_head: core registers the custom-fields,
	 * page-attributes and featured-image boxes while building the screen, which
	 * is before admin_head fires.
	 *
	 * Support is dropped for this request only, so the list table keeps its
	 * title column and REST keeps its shape.
	 */
	public static function strip_editor(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! self::ours( (string) $screen->post_type ) ) {
			return;
		}

		foreach ( array( 'title', 'editor', 'thumbnail', 'custom-fields', 'page-attributes' ) as $feature ) {
			remove_post_type_support( Mudlet_Makers_Store::POST_TYPE, $feature );
		}

		remove_meta_box( 'submitdiv', Mudlet_Makers_Store::POST_TYPE, 'side' );
		remove_meta_box( 'slugdiv', Mudlet_Makers_Store::POST_TYPE, 'normal' );
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
		$boxes = array(
			'submitdiv'    => 'side',
			'postimagediv' => 'side',
			'slugdiv'      => 'normal',
			'postcustom'   => 'normal',
			'pageparentdiv' => 'side',
		);
		foreach ( $boxes as $box => $context ) {
			remove_meta_box( $box, Mudlet_Makers_Store::POST_TYPE, $context );
		}

		add_meta_box(
			'mudlet-maker-person',
			__( 'Maker', 'mudlet-makers' ),
			array( __CLASS__, 'box_person' ),
			Mudlet_Makers_Store::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'mudlet-maker-description',
			__( 'What they did', 'mudlet-makers' ),
			array( __CLASS__, 'box_description' ),
			Mudlet_Makers_Store::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'mudlet-maker-record',
			__( 'Record', 'mudlet-makers' ),
			array( __CLASS__, 'box_record' ),
			Mudlet_Makers_Store::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Who they are and where they can be found.
	 *
	 * @param WP_Post $post Maker.
	 */
	public static function box_person( WP_Post $post ): void {
		$maker = Mudlet_Makers_Store::to_array( $post );

		self::styles();
		?>
		<div class="mudlet-rec">
			<div class="mudlet-rec__head">
				<span class="mudlet-rec__face">
					<?php if ( has_post_thumbnail( $post ) ) : ?>
						<?php echo get_the_post_thumbnail( $post, array( 96, 96 ), array( 'alt' => '' ) ); ?>
					<?php else : ?>
						<span class="mudlet-rec__initials"><?php echo esc_html( $maker['initials'] ); ?></span>
					<?php endif; ?>
				</span>
				<div>
					<h2><?php echo esc_html( $maker['name'] ); ?></h2>
					<p class="mudlet-rec__sub">
						<?php if ( $maker['core'] ) : ?>
							<span class="mudlet-rec__pill"><?php esc_html_e( 'core developer', 'mudlet-makers' ); ?></span>
						<?php else : ?>
							<span class="mudlet-rec__pill"><?php esc_html_e( 'has contributed', 'mudlet-makers' ); ?></span>
						<?php endif; ?>
					</p>
				</div>
			</div>

			<table class="mudlet-rec__facts">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'GitHub', 'mudlet-makers' ); ?></th>
						<td>
							<?php if ( $maker['github'] ) : ?>
								<a href="<?php echo esc_url( $maker['github_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $maker['github'] ); ?></a>
							<?php else : ?>
								<span class="mudlet-rec__none"><?php esc_html_e( 'none published', 'mudlet-makers' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php
					self::fact( __( 'Discord', 'mudlet-makers' ), $maker['discord'], true );
					self::fact( __( 'Avatar file', 'mudlet-makers' ), $maker['avatar'], true );
					self::fact( __( 'Upstream name', 'mudlet-makers' ), (string) get_post_meta( $post->ID, Mudlet_Makers_Store::KEY, true ), true );
					?>
					<tr>
						<th><?php esc_html_e( 'Page', 'mudlet-makers' ); ?></th>
						<td><a href="<?php echo esc_url( (string) get_permalink( $post ) ); ?>"><?php esc_html_e( 'View on the site', 'mudlet-makers' ); ?></a></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Their own sentence, as the page shows it.
	 *
	 * @param WP_Post $post Maker.
	 */
	public static function box_description( WP_Post $post ): void {
		self::styles();

		if ( '' === trim( $post->post_content ) ) {
			echo '<p class="mudlet-rec__none">' . esc_html__( 'Mudlet credits them with no description.', 'mudlet-makers' ) . '</p>';
			return;
		}

		echo '<div class="mudlet-rec__prose">' . wp_kses_post( wpautop( $post->post_content ) ) . '</div>';
	}

	/**
	 * Where it came from, and the one thing you can do about it.
	 *
	 * @param WP_Post $post Maker.
	 */
	public static function box_record( WP_Post $post ): void {
		$synced = (int) get_option( Mudlet_Makers_Sync::SYNCED );
		$source = Mudlet_Makers_Source::raw_base() . '/' . Mudlet_Makers_Source::DIALOG;

		self::styles();
		?>
		<div class="mudlet-rec">
			<p class="mudlet-rec__note">
				<?php
				esc_html_e(
					'Read-only. Every field here is read from Mudlet\'s About dialog and rewritten on the next sync — to change one, change it in Mudlet.',
					'mudlet-makers'
				);
				?>
			</p>

			<table class="mudlet-rec__facts">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Source', 'mudlet-makers' ); ?></th>
						<td><a href="<?php echo esc_url( $source ); ?>" target="_blank" rel="noopener noreferrer">src/dlgAboutDialog.cpp</a></td>
					</tr>
					<?php
					self::fact(
						__( 'Last synced', 'mudlet-makers' ),
						$synced
							/* translators: %s: human-readable time difference */
							? sprintf( __( '%s ago', 'mudlet-makers' ), human_time_diff( $synced ) )
							: __( 'never', 'mudlet-makers' )
					);
					self::fact( __( 'Makers on record', 'mudlet-makers' ), (string) Mudlet_Makers_Sync::count() );
					self::fact( __( 'Listed', 'mudlet-makers' ), (string) ( $post->menu_order + 1 ) . '.' );
					?>
				</tbody>
			</table>

			<p class="mudlet-rec__actions">
				<a class="button button-primary" href="<?php echo esc_url( self::sync_url() ); ?>">
					<?php esc_html_e( 'Sync from Mudlet', 'mudlet-makers' ); ?>
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
	 * Face, standing and handle instead of a date nobody set.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		return array(
			'cb'            => $columns['cb'] ?? '',
			'mudlet_face'   => __( 'Avatar', 'mudlet-makers' ),
			'title'         => __( 'Maker', 'mudlet-makers' ),
			'mudlet_group'  => __( 'Standing', 'mudlet-makers' ),
			'mudlet_github' => __( 'GitHub', 'mudlet-makers' ),
		);
	}

	/**
	 * Fill one.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post id.
	 */
	public static function column( $column, $post_id ): void {
		$meta = Mudlet_Makers_Store::META;

		switch ( $column ) {
			case 'mudlet_face':
				if ( has_post_thumbnail( $post_id ) ) {
					echo get_the_post_thumbnail(
						$post_id,
						array( 40, 40 ),
						array(
							'alt'   => '',
							'style' => 'width:40px;height:40px;border-radius:50%;object-fit:cover',
						)
					);
				} else {
					self::styles();
					echo '<span class="mudlet-rec__initials mudlet-rec__initials--sm">'
						. esc_html( Mudlet_Makers_Store::initials( (string) get_the_title( $post_id ) ) )
						. '</span>';
				}
				break;

			case 'mudlet_group':
				echo get_post_meta( $post_id, $meta['core'], true )
					? esc_html__( 'core developer', 'mudlet-makers' )
					: esc_html__( 'has contributed', 'mudlet-makers' );
				break;

			case 'mudlet_github':
				$handle = (string) get_post_meta( $post_id, $meta['github'], true );
				if ( $handle ) {
					printf(
						'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_url( 'https://github.com/' . $handle ),
						esc_html( $handle )
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
				'>' . esc_html__( 'View record', 'mudlet-makers' ) . '<',
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
			wp_die( esc_html__( 'You are not allowed to do that.', 'mudlet-makers' ) );
		}
		check_admin_referer( self::SYNC_ACTION );

		self::$writing = true;
		$result        = Mudlet_Makers_Sync::sync( true );
		self::$writing = false;

		$back = isset( $_GET['redirect'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? rawurldecode( sanitize_text_field( wp_unslash( $_GET['redirect'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification
			: admin_url( 'edit.php?post_type=' . Mudlet_Makers_Store::POST_TYPE );

		wp_safe_redirect(
			add_query_arg(
				array(
					'mudlet_makers_synced' => '' === $result['error'] ? (int) $result['written'] : -1,
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
		if ( ! isset( $_GET['mudlet_makers_synced'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$written = (int) $_GET['mudlet_makers_synced']; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $written < 0 ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html__( 'Could not read the makers list from GitHub.', 'mudlet-makers' )
			);
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of makers written */
					_n( '%d maker synced from Mudlet.', '%d makers synced from Mudlet.', $written, 'mudlet-makers' ),
					$written
				)
			)
		);
	}

	/**
	 * Screen styles, printed once.
	 *
	 * Inline for the same reason the games plugin does it: thirty lines used on
	 * two screens, where a stylesheet to enqueue and version would be more
	 * moving parts than the thing it styles.
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
			.mudlet-rec__face img{display:block;width:72px;height:72px;border-radius:50%;object-fit:cover;
				border:1px solid #dcdcde;background:#fff}
			.mudlet-rec__initials{display:grid;place-items:center;width:72px;height:72px;border-radius:50%;
				background:#f0f0f1;border:1px solid #dcdcde;color:#50575e;font-size:24px;font-weight:600}
			.mudlet-rec__initials--sm{width:40px;height:40px;font-size:13px}
			.mudlet-rec__sub{margin:6px 0 0;color:#646970}
			.mudlet-rec__pill{display:inline-block;padding:1px 7px;border-radius:9px;
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
		</style>
		<?php
	}
}
