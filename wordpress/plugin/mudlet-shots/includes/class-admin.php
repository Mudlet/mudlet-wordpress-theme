<?php
/**
 * The review screen, and the only way to look at what is in the queue.
 *
 * A wall of pictures with two buttons under each, rather than a list table.
 * The other three plugins here hang their records off the default list table
 * and are right to - a game is a name and a host, and a name in a column is the
 * thing you identify it by. A screenshot is not. The only field that matters is
 * the picture, everything else is a footnote to it, and a table of titles
 * reading "Screenshot sent 2026-03-04 14:22" thirty times over is a screen that
 * makes somebody click into each one to do the job.
 *
 * ---------------------------------------------------------------------------
 *
 * How the pictures get onto this page.
 *
 * Not by URL. A pending file is not an attachment and its directory is not
 * meant to be reachable, so each thumbnail is a request to admin-post.php that
 * checks the capability, reads the file off disk and streams it. That is slower
 * than an <img src> pointing at uploads/, and it is the price of the queue not
 * being on the web - which is the one property this whole plugin exists to
 * have. See the plugin header.
 *
 * @package Mudlet_Shots
 */

defined( 'ABSPATH' ) || exit;

/**
 * The screen under Mudlet -> Screenshots.
 */
class Mudlet_Shots_Admin {

	/** The submenu slug. */
	const PAGE = 'mudlet-shots';

	/** admin-post actions. */
	const DECIDE  = 'mudlet_shots_decide';
	const PREVIEW = 'mudlet_shots_preview';

	/** What it takes to accept one: approving writes to a page. */
	const CAP = 'edit_pages';

	/**
	 * Hook up.
	 */
	public static function init(): void {
		// After Mudlet_Sync::menu(), which registers the parent at 9.
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 11 );

		add_action( 'admin_post_' . self::DECIDE, array( __CLASS__, 'decide' ) );
		add_action( 'admin_post_' . self::PREVIEW, array( __CLASS__, 'preview' ) );

		add_filter( 'mudlet_sync_jobs', array( __CLASS__, 'sync_job' ) );
	}

	/**
	 * Put it under the Mudlet menu, with the count on it.
	 *
	 * The bubble is core's own `awaiting-mod`, the one comments use, because
	 * this is the same thing comments are: a queue somebody has to drain, whose
	 * whole failure mode is nobody noticing it has anything in it.
	 */
	public static function menu(): void {
		$waiting = Mudlet_Shots_Store::pending();

		$label = __( 'Screenshots', 'mudlet-shots' );
		if ( $waiting > 0 ) {
			$label .= sprintf(
				' <span class="awaiting-mod"><span class="pending-count">%s</span></span>',
				esc_html( number_format_i18n( $waiting ) )
			);
		}

		add_submenu_page(
			Mudlet_Sync::MENU,
			__( 'Screenshots', 'mudlet-shots' ),
			$label,
			self::CAP,
			self::PAGE,
			array( __CLASS__, 'screen' )
		);
	}

	/**
	 * Where the screen is.
	 *
	 * @param string $status Which tab.
	 * @return string
	 */
	public static function screen_url( string $status = 'pending' ): string {
		return add_query_arg(
			array(
				'page'   => self::PAGE,
				'status' => $status,
			),
			admin_url( 'admin.php' )
		);
	}

	// -- the screen ----------------------------------------------------

	/**
	 * Draw it.
	 */
	public static function screen(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'mudlet-shots' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'pending';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$tabs = array(
			'pending' => __( 'Waiting', 'mudlet-shots' ),
			'publish' => __( 'Accepted', 'mudlet-shots' ),
			'trash'   => __( 'Turned down', 'mudlet-shots' ),
		);

		if ( ! isset( $tabs[ $status ] ) ) {
			$status = 'pending';
		}

		$shots = Mudlet_Shots_Store::all( $status, 'pending' === $status ? 100 : 40 );

		self::styles();
		?>
		<div class="wrap mudlet-shots">
			<h1><?php esc_html_e( 'Screenshots', 'mudlet-shots' ); ?></h1>

			<?php self::result_notice(); ?>

			<p class="mudlet-shots__lead">
				<?php
				esc_html_e(
					'Screenshots people have sent the site. Nothing here is on the web until it is accepted — the files sit outside the media library and are only visible on this screen.',
					'mudlet-shots'
				);
				?>
			</p>

			<?php self::setup_notice(); ?>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $key === $status ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( self::screen_url( $key ) ); ?>">
						<?php echo esc_html( $label ); ?>
						<?php if ( 'pending' === $key && Mudlet_Shots_Store::pending() > 0 ) : ?>
							<span class="mudlet-shots__count"><?php echo esc_html( number_format_i18n( Mudlet_Shots_Store::pending() ) ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<?php if ( ! $shots ) : ?>
				<p class="mudlet-shots__empty">
					<?php
					if ( 'pending' === $status ) {
						esc_html_e( 'Nothing waiting. Everything anybody has sent has been looked at.', 'mudlet-shots' );
					} else {
						esc_html_e( 'Nothing here yet.', 'mudlet-shots' );
					}
					?>
				</p>
			<?php else : ?>
				<div class="mudlet-shots__grid">
					<?php foreach ( $shots as $shot ) : ?>
						<?php self::card( $shot, $status ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * One submission.
	 *
	 * @param WP_Post $shot   The submission.
	 * @param string  $status Which tab we are on.
	 */
	private static function card( WP_Post $shot, string $status ): void {
		$meta   = Mudlet_Shots_Store::META;
		$get    = static fn( string $k ) => (string) get_post_meta( $shot->ID, $meta[ $k ], true );
		$width  = (int) $get( 'width' );
		$height = (int) $get( 'height' );
		$bytes  = (int) $get( 'bytes' );
		$credit = $get( 'credit' );
		$about  = trim( $shot->post_content );
		$origin = $get( 'origin' );
		$image  = (int) $get( 'attachment' );
		?>
		<div class="mudlet-shots__card">
			<div class="mudlet-shots__pic">
				<?php if ( $image && wp_attachment_is_image( $image ) ) : ?>
					<a href="<?php echo esc_url( (string) wp_get_attachment_url( $image ) ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo wp_get_attachment_image( $image, 'medium_large', false, array( 'alt' => '' ) ); ?>
					</a>
				<?php elseif ( '' !== Mudlet_Shots_Store::path( $shot->ID ) ) : ?>
					<a href="<?php echo esc_url( self::preview_url( $shot->ID ) ); ?>" target="_blank" rel="noopener noreferrer">
						<img src="<?php echo esc_url( self::preview_url( $shot->ID ) ); ?>" alt="" loading="lazy">
					</a>
				<?php else : ?>
					<p class="mudlet-shots__gone"><?php esc_html_e( 'The file has been cleared.', 'mudlet-shots' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="mudlet-shots__facts">
				<p class="mudlet-shots__when">
					<?php
					printf(
						/* translators: %s: human-readable time difference, e.g. "2 days" */
						esc_html__( 'Sent %s ago', 'mudlet-shots' ),
						esc_html( human_time_diff( (int) get_post_timestamp( $shot ) ) )
					);
					?>
					<?php if ( $width && $height ) : ?>
						<span class="mudlet-shots__dim">
							<?php echo esc_html( sprintf( '%d × %d', $width, $height ) ); ?>
							<?php echo $bytes ? esc_html( ' · ' . size_format( $bytes ) ) : ''; ?>
							<?php
							// The preview above streams the stored file itself
							// rather than a sub-size, so an animation is
							// already moving on this screen - which is the
							// only way to review one.
							if ( $get( 'animated' ) ) :
								?>
								<span class="mudlet-shots__moves">
									<?php
									printf(
										/* translators: %s: number of frames */
										esc_html( _n( 'animated · %s frame', 'animated · %s frames', (int) $get( 'frames' ), 'mudlet-shots' ) ),
										esc_html( number_format_i18n( (int) $get( 'frames' ) ) )
									);
									?>
								</span>
							<?php endif; ?>
						</span>
					<?php endif; ?>
				</p>

				<?php if ( '' !== $about ) : ?>
					<p class="mudlet-shots__about"><?php echo esc_html( $about ); ?></p>
				<?php endif; ?>

				<p class="mudlet-shots__credit">
					<?php if ( '' !== $credit ) : ?>
						<strong><?php echo esc_html( Mudlet_Shots_Publish::caption( $credit ) ); ?></strong>
					<?php else : ?>
						<span class="mudlet-shots__none"><?php esc_html_e( 'No credit asked for.', 'mudlet-shots' ); ?></span>
					<?php endif; ?>
				</p>

				<?php if ( '' !== $origin ) : ?>
					<p class="mudlet-shots__origin">
						<?php
						printf(
							/* translators: %s: a short hash standing in for the sender */
							esc_html__( 'from %s', 'mudlet-shots' ),
							'<code>' . esc_html( $origin ) . '</code>'
						);
						?>
						<span class="mudlet-shots__hint" title="<?php esc_attr_e( 'A salted hash, not an address — enough to tell whether several came from the same place, and nothing else.', 'mudlet-shots' ); ?>">?</span>
					</p>
				<?php endif; ?>
			</div>

			<div class="mudlet-shots__do">
				<?php if ( 'pending' === $status ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( self::decide_url( $shot->ID, 'approve' ) ); ?>">
						<?php esc_html_e( 'Add to the gallery', 'mudlet-shots' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( self::decide_url( $shot->ID, 'reject' ) ); ?>">
						<?php esc_html_e( 'Turn down', 'mudlet-shots' ); ?>
					</a>
				<?php elseif ( 'publish' === $status && $image ) : ?>
					<a class="button" href="<?php echo esc_url( (string) get_edit_post_link( $image ) ); ?>">
						<?php esc_html_e( 'In the media library', 'mudlet-shots' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Say where the form is, or that there is not one.
	 *
	 * The commonest way for this plugin to look broken is for it to work
	 * perfectly with nowhere for anybody to submit from, which is the state it
	 * ships in: the shortcode has to be put on a page by hand, because where it
	 * goes is a decision about the page and not about the plugin.
	 */
	private static function setup_notice(): void {
		if ( ! mudlet_shots_enabled() ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				esc_html__( 'Submissions are turned off — the form does not draw and the endpoint answers 404. Nothing already in the queue is affected.', 'mudlet-shots' )
			);
			return;
		}

		$page = Mudlet_Shots_Publish::gallery_page();

		if ( ! $page ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				esc_html__( 'There is no /media/ page. Accepted screenshots will go into the media library, and somebody will have to place them by hand.', 'mudlet-shots' )
			);
			return;
		}

		if ( ! has_shortcode( $page->post_content, mudlet_shots_shortcode() ) ) {
			printf(
				'<div class="notice notice-info inline"><p>%s</p></div>',
				sprintf(
					/* translators: 1: the shortcode, 2: link to edit the page, 3: the page title */
					wp_kses_post( __( 'Nobody can send anything yet: add %1$s to <a href="%2$s">%3$s</a>, under the gallery.', 'mudlet-shots' ) ),
					'<code>[' . esc_html( mudlet_shots_shortcode() ) . ']</code>',
					esc_url( (string) get_edit_post_link( $page->ID ) ),
					esc_html( get_the_title( $page ) )
				)
			);
		}
	}

	// -- deciding ------------------------------------------------------

	/**
	 * The nonce-protected URL behind one of the two buttons.
	 *
	 * @param int    $post_id  Submission.
	 * @param string $decision approve or reject.
	 * @return string
	 */
	private static function decide_url( int $post_id, string $decision ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => self::DECIDE,
					'shot'     => $post_id,
					'decision' => $decision,
				),
				admin_url( 'admin-post.php' )
			),
			self::DECIDE . '-' . $post_id
		);
	}

	/**
	 * Approve or reject one, then come back to the screen.
	 */
	public static function decide(): void {
		$post_id = isset( $_GET['shot'] ) ? (int) $_GET['shot'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'mudlet-shots' ) );
		}
		check_admin_referer( self::DECIDE . '-' . $post_id );

		$decision = isset( $_GET['decision'] ) ? sanitize_key( wp_unslash( $_GET['decision'] ) ) : '';
		$result   = array( 'done' => 'nothing' );

		if ( 'approve' === $decision ) {
			$approved = Mudlet_Shots_Publish::approve( $post_id );

			if ( is_wp_error( $approved ) ) {
				$result = array(
					'done' => 'error',
					'why'  => $approved->get_error_message(),
				);
			} else {
				$result = array(
					'done' => $approved['placed'] ? 'placed' : 'library',
					'why'  => $approved['why'],
				);
			}
		} elseif ( 'reject' === $decision ) {
			$result = array( 'done' => Mudlet_Shots_Publish::reject( $post_id ) ? 'rejected' : 'error' );
		}

		wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $result ), self::screen_url() ) );
		exit;
	}

	/**
	 * Say how it went, on the way back.
	 */
	private static function result_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['done'] ) ) {
			return;
		}

		$done = sanitize_key( wp_unslash( $_GET['done'] ) );
		$why  = isset( $_GET['why'] ) ? sanitize_text_field( wp_unslash( $_GET['why'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$page = Mudlet_Shots_Publish::gallery_page();

		switch ( $done ) {
			case 'placed':
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					$page
						? sprintf(
							/* translators: 1: link to the page, 2: page title */
							wp_kses_post( __( 'Added to the gallery on <a href="%1$s">%2$s</a>.', 'mudlet-shots' ) ),
							esc_url( (string) get_permalink( $page ) ),
							esc_html( get_the_title( $page ) )
						)
						: esc_html__( 'Added to the gallery.', 'mudlet-shots' )
				);
				break;

			case 'library':
				printf(
					'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
					esc_html( $why ? $why : __( 'It is in the media library, but it could not be added to the gallery.', 'mudlet-shots' ) )
				);
				break;

			case 'rejected':
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html__( 'Turned down. The image has been deleted.', 'mudlet-shots' )
				);
				break;

			case 'error':
				printf(
					'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
					esc_html( $why ? $why : __( 'That did not work.', 'mudlet-shots' ) )
				);
				break;
		}
	}

	// -- looking at one ------------------------------------------------

	/**
	 * The URL that streams one pending file.
	 *
	 * @param int $post_id Submission.
	 * @return string
	 */
	private static function preview_url( int $post_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::PREVIEW,
					'shot'   => $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::PREVIEW . '-' . $post_id
		);
	}

	/**
	 * Read one queued file off disk and send it.
	 *
	 * Capability first, nonce second, and `no-store` on the way out: this is a
	 * picture nobody has approved, and a copy of it sitting in a shared proxy
	 * is the same failure as the file being on the web in the first place.
	 */
	public static function preview(): void {
		$post_id = isset( $_GET['shot'] ) ? (int) $_GET['shot'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'mudlet-shots' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::PREVIEW . '-' . $post_id );

		$path = Mudlet_Shots_Store::path( $post_id );
		$mime = (string) get_post_meta( $post_id, Mudlet_Shots_Store::META['mime'], true );

		// Not the stored mime on its own: what is served is decided by what the
		// file is, and the file is one this plugin wrote. GIF is on the list
		// because it is the animated path's fallback output, never because
		// somebody uploaded one.
		if ( '' === $path || ! in_array( $mime, array( 'image/webp', 'image/jpeg', 'image/gif' ), true ) ) {
			wp_die( esc_html__( 'There is no image there.', 'mudlet-shots' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Cache-Control: no-store, private' );
		// It is an image and nothing else - see class-image.php - but a browser
		// that decides otherwise about a file from a stranger is exactly the
		// thing worth ruling out.
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: inline' );

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	// -- the sweeper's row on Mudlet -> Sync ---------------------------

	/**
	 * List the tidy-up beside the three syncs.
	 *
	 * It is not a sync - nothing is read from anywhere - but it is a cron job
	 * these plugins own, and the point of that screen is that there is one
	 * place to see every one of them and how often it runs.
	 *
	 * @param array<string, array<string, mixed>> $jobs Registered jobs.
	 * @return array<string, array<string, mixed>>
	 */
	public static function sync_job( array $jobs ): array {
		$waiting = Mudlet_Shots_Store::pending();

		$jobs[ Mudlet_Shots_Store::SWEEP ] = array(
			'label'   => __( 'Screenshot queue', 'mudlet-shots' ),
			'note'    => __( 'Deletes the files behind screenshots that have been accepted or turned down. Never touches one that is still waiting.', 'mudlet-shots' ),
			'default' => Mudlet_Shots_Store::EVERY,
			'synced'  => (int) get_option( Mudlet_Shots_Store::SWEPT ),
			'summary' => sprintf(
				/* translators: %d: number of screenshots waiting for review */
				_n( '%d waiting for review', '%d waiting for review', $waiting, 'mudlet-shots' ),
				$waiting
			),
		);

		return $jobs;
	}

	/**
	 * Screen styles, printed once.
	 *
	 * Inline for the same reason the other plugins' screens do it: forty lines
	 * used on one screen, where a stylesheet to enqueue and version would be
	 * more moving parts than the thing it styles.
	 */
	private static function styles(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		?>
		<style>
			.mudlet-shots__lead{max-width:60em;color:#50575e}
			.mudlet-shots .notice.inline{margin:12px 0}
			.mudlet-shots__count{display:inline-block;min-width:1.4em;margin-left:4px;padding:0 5px;
				border-radius:9px;background:#d63638;color:#fff;font-size:11px;line-height:18px;text-align:center}
			.mudlet-shots__empty{margin:24px 0;color:#646970}
			.mudlet-shots__grid{display:grid;gap:16px;margin-top:16px;
				grid-template-columns:repeat(auto-fill,minmax(320px,1fr))}
			.mudlet-shots__card{display:flex;flex-direction:column;background:#fff;border:1px solid #dcdcde;
				border-radius:4px;overflow:hidden}
			.mudlet-shots__pic{background:#1d2327;display:grid;place-items:center;min-height:180px}
			.mudlet-shots__pic img{display:block;max-width:100%;height:auto}
			.mudlet-shots__gone{color:#a7aaad;margin:0;padding:24px}
			.mudlet-shots__facts{padding:12px 14px;flex:1 1 auto}
			.mudlet-shots__facts p{margin:0 0 8px}
			.mudlet-shots__when{color:#50575e;font-weight:600}
			.mudlet-shots__dim{display:block;font-weight:400;color:#646970;font-size:12px;margin-top:2px}
			.mudlet-shots__moves{display:inline-block;margin-left:4px;padding:0 6px;border-radius:9px;
				background:#f0f0f1;border:1px solid #dcdcde;color:#50575e;font-size:11px}
			.mudlet-shots__about{color:#3c434a}
			.mudlet-shots__none{color:#8c8f94}
			.mudlet-shots__origin{color:#8c8f94;font-size:12px}
			.mudlet-shots__hint{display:inline-grid;place-items:center;width:15px;height:15px;border-radius:50%;
				background:#f0f0f1;color:#646970;font-size:10px;cursor:help;vertical-align:1px}
			.mudlet-shots__do{display:flex;gap:8px;padding:0 14px 14px}
		</style>
		<?php
	}
}
