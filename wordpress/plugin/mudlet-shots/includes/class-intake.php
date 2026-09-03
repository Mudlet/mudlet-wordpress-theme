<?php
/**
 * The one route a stranger can reach.
 *
 * Deliberately shaped after the theme's inc/download-email.php, which is the
 * other endpoint on this site that answers to nobody in particular, and for the
 * same reasons written out there at length:
 *
 * - **No nonce.** A nonce is printed into the page, so it is only as fresh as
 *   whatever cache sits in front of it, and the first symptom is a form that
 *   works for a logged-in editor and fails for everybody else.
 * - **A honeypot instead**, named for what a bot expects to find, answering a
 *   filled-in trap with the same cheerful message a real submission gets -
 *   telling the two apart is the one thing it came here to learn.
 * - **A cap per origin**, counted on attempts rather than successes, so a
 *   refusal is not a free way to keep knocking.
 * - **One filter before anything is written**, `mudlet_shots_verify`, which is
 *   where a captcha goes on a site that wants one.
 *
 * What this endpoint has that the other one does not is a cap on the *queue*.
 * Mail is refused per address and per address there is a person; an upload
 * costs disk, and a hundred people sending one picture each is indistinguishable
 * from one person sending a hundred until somebody looks. So the intake closes
 * when the queue is longer than anyone is going to review, and says so - which
 * is a better failure than filling a disk and taking the site down with it.
 *
 * ---------------------------------------------------------------------------
 *
 * What the visitor is asked for, and what they are not.
 *
 * A file, an optional name to be credited as, and an optional sentence about
 * what it shows. That is all, and the omissions are the interesting part:
 *
 * - **No email address.** There is nothing to send anybody: submissions are not
 *   accounts, and "we will let you know" is a promise the queue cannot keep.
 * - **No link with the credit.** A credit line that carries a URL is a
 *   do-follow link on mudlet.org that anybody can ask for by uploading a
 *   picture, and no amount of review makes that not worth somebody's while. A
 *   name is a name.
 *
 * @package Mudlet_Shots
 */

defined( 'ABSPATH' ) || exit;

/**
 * The public submission endpoint.
 */
class Mudlet_Shots_Intake {

	/** How long between the notification emails, however busy it gets. */
	const NOTIFY_EVERY = HOUR_IN_SECONDS;

	/**
	 * Hook up.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'route' ) );
	}

	/**
	 * Register it.
	 */
	public static function route(): void {
		register_rest_route(
			'mudlet/v1',
			'/screenshot',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'submit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					// Both optional, both short. The lengths are not arbitrary:
					// a credit is a name and a note is a sentence, and a field
					// that will take a thousand characters is a field somebody
					// will put a thousand characters of link spam into.
					'credit'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'about'   => array(
						'type'    => 'string',
						'default' => '',
					),
					// The honeypot. Named for what a bot expects to find.
					'website' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Take one screenshot, or say why not.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function submit( WP_REST_Request $request ) {
		$thanks = array(
			'ok'      => true,
			'message' => __( 'Thank you - it is in the queue. Somebody will look at it before it appears on the site.', 'mudlet-shots' ),
		);

		if ( ! mudlet_shots_enabled() ) {
			return new WP_Error(
				'mudlet_shot_off',
				__( 'This site is not taking screenshots at the moment.', 'mudlet-shots' ),
				array( 'status' => 404 )
			);
		}

		// Something filled in the field nobody can see. It gets the same answer
		// as somebody who did not.
		if ( '' !== trim( (string) $request['website'] ) ) {
			return $thanks;
		}

		$limits = self::rate_limits();

		if ( Mudlet_Shots_Store::pending() >= (int) $limits['queue'] ) {
			return new WP_Error(
				'mudlet_shot_queue',
				__( 'There are more screenshots waiting than anybody can look at. Try again in a few days.', 'mudlet-shots' ),
				array( 'status' => 503 )
			);
		}

		$ip     = self::ip();
		$origin = Mudlet_Shots_Store::origin( $ip );

		if ( '' !== $origin ) {
			if ( ! self::allow( 'h:' . $origin, (int) $limits['hour'], HOUR_IN_SECONDS ) ) {
				return new WP_Error(
					'mudlet_shot_rate',
					__( 'That is a lot of screenshots at once. Try again in an hour.', 'mudlet-shots' ),
					array( 'status' => 429 )
				);
			}
			if ( ! self::allow( 'd:' . $origin, (int) $limits['day'], DAY_IN_SECONDS ) ) {
				return new WP_Error(
					'mudlet_shot_rate',
					__( 'That is enough screenshots for one day - thank you. Try again tomorrow.', 'mudlet-shots' ),
					array( 'status' => 429 )
				);
			}
		}

		/**
		 * Last word before anything is written - where a captcha hooks in.
		 *
		 * Return true to accept, false to refuse, or a WP_Error to refuse with
		 * something to say.
		 *
		 * @param bool            $ok      Whether to take it.
		 * @param WP_REST_Request $request The request, tokens and all.
		 */
		$ok = apply_filters( 'mudlet_shots_verify', true, $request );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		if ( true !== $ok ) {
			return new WP_Error(
				'mudlet_shot_verify',
				__( 'We could not verify that request.', 'mudlet-shots' ),
				array( 'status' => 403 )
			);
		}

		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;

		if ( ! is_array( $file ) || ! isset( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return new WP_Error(
				'mudlet_shot_none',
				self::upload_error( is_array( $file ) ? (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) : UPLOAD_ERR_NO_FILE ),
				array( 'status' => 400 )
			);
		}

		// Belt to the braces of the checks in Mudlet_Shots_Image: a path that
		// PHP did not put there itself is not an upload, whatever it says.
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error(
				'mudlet_shot_none',
				__( 'The upload did not arrive. Try again.', 'mudlet-shots' ),
				array( 'status' => 400 )
			);
		}

		$token = Mudlet_Shots_Store::new_token();
		if ( '' === $token ) {
			return new WP_Error(
				'mudlet_shot_store',
				__( 'The site could not store that image.', 'mudlet-shots' ),
				array( 'status' => 500 )
			);
		}

		$dir   = Mudlet_Shots_Store::queue_dir() . '/' . $token;
		$image = Mudlet_Shots_Image::accept( (string) $file['tmp_name'], $dir );

		if ( is_wp_error( $image ) ) {
			// The directory was made a moment ago and is empty - accept()
			// unlinks the upload on the way out. The sweeper would find it
			// within the day, but leaving litter behind on every refusal is
			// how a queue directory ends up with ten thousand entries in it.
			self::scrub( $dir );
			return $image;
		}

		$credit = self::clean( (string) $request['credit'], 60 );
		$about  = self::clean( (string) $request['about'], 240 );

		$post_id = wp_insert_post(
			array(
				'post_type'    => Mudlet_Shots_Store::POST_TYPE,
				// Not the uploaded filename. It is the visitor's, it is often
				// "Screenshot 2026-03-04 at 14.22.11.png", and putting a
				// stranger's string in a title is a small pointless risk. The
				// review screen shows the picture, which is the only thing
				// anybody identifies one of these by.
				'post_title'   => sprintf(
					/* translators: %s: date and time the screenshot was submitted */
					__( 'Screenshot sent %s', 'mudlet-shots' ),
					wp_date( 'Y-m-d H:i' )
				),
				'post_content' => $about,
				'post_status'  => 'pending',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			self::scrub( $dir );
			return new WP_Error(
				'mudlet_shot_store',
				__( 'The site could not store that image.', 'mudlet-shots' ),
				array( 'status' => 500 )
			);
		}

		$meta = Mudlet_Shots_Store::META;
		update_post_meta( $post_id, $meta['token'], $token );
		update_post_meta( $post_id, $meta['file'], $image['file'] );
		update_post_meta( $post_id, $meta['mime'], $image['mime'] );
		update_post_meta( $post_id, $meta['width'], $image['width'] );
		update_post_meta( $post_id, $meta['height'], $image['height'] );
		update_post_meta( $post_id, $meta['bytes'], $image['bytes'] );
		update_post_meta( $post_id, $meta['credit'], $credit );
		update_post_meta( $post_id, $meta['origin'], $origin );
		update_post_meta( $post_id, $meta['frames'], (int) ( $image['frames'] ?? 1 ) );
		// Only when true. An absent meta and a '0' read back the same way, and
		// a flag that is only ever set is one fewer thing to get wrong.
		if ( ! empty( $image['animated'] ) ) {
			update_post_meta( $post_id, $meta['animated'], 1 );
		}

		/**
		 * A screenshot has been taken into the queue.
		 *
		 * @param int                  $post_id The submission.
		 * @param array<string, mixed> $image   What was stored: file, mime, size.
		 */
		do_action( 'mudlet_shots_received', $post_id, $image );

		self::notify();

		return $thanks;
	}

	// -- housekeeping ---------------------------------------------------

	/**
	 * Tidy up a directory a failed submission left behind.
	 *
	 * @param string $dir Absolute path inside the queue.
	 */
	private static function scrub( string $dir ): void {
		foreach ( (array) glob( $dir . '/*' ) as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}

		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	}

	/**
	 * Trim one of the two text fields down to something storable.
	 *
	 * @param string $text  What was typed.
	 * @param int    $chars How much of it to keep.
	 * @return string
	 */
	private static function clean( string $text, int $chars ): string {
		$text = sanitize_text_field( wp_unslash( $text ) );

		return mb_substr( trim( $text ), 0, $chars );
	}

	/**
	 * The caps.
	 *
	 * @return array<string, int>
	 */
	public static function rate_limits(): array {
		/**
		 * Filter how much one origin may send, and how long the queue may get.
		 *
		 *   hour  submissions from one origin in an hour
		 *   day   submissions from one origin in a day
		 *   queue how many may be waiting before the form closes
		 *
		 * @param array<string, int> $limits The caps above.
		 */
		return (array) apply_filters(
			'mudlet_shots_rate_limits',
			array(
				'hour'  => 3,
				'day'   => 8,
				'queue' => 100,
			)
		);
	}

	/**
	 * Count one attempt against a bucket, and say whether it was within the cap.
	 *
	 * Attempts, not successes: a refusal must not be a free knock.
	 *
	 * @param string $bucket Something unique to the sender.
	 * @param int    $limit  How many are allowed in the window.
	 * @param int    $window Seconds.
	 * @return bool
	 */
	private static function allow( string $bucket, int $limit, int $window ): bool {
		$key  = 'mudlet_shots_' . md5( $bucket );
		$seen = (int) get_transient( $key );
		set_transient( $key, $seen + 1, $window );

		return $seen < $limit;
	}

	/**
	 * The caller's address, as far as this server can tell.
	 *
	 * REMOTE_ADDR and nothing else, for the reason inc/download-email.php gives:
	 * behind a proxy every visitor shares one, which makes the cap useless
	 * rather than wrong, and trusting a forwarded-for header nobody verified
	 * makes it worse than useless - it becomes a cap anybody can step around by
	 * typing a different number. A site that knows its own proxy can filter it.
	 *
	 * Note that this address is never stored. It is hashed on the way past -
	 * see Mudlet_Shots_Store::origin().
	 *
	 * @return string
	 */
	public static function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filter the client address the rate limit counts against.
		 *
		 * @param string $ip The address.
		 */
		return (string) apply_filters( 'mudlet_shots_ip', $ip );
	}

	/**
	 * A sentence for PHP's own upload failures.
	 *
	 * The two size ones are worth telling apart from everything else, because
	 * they are the ones a visitor can do something about and the ones the form
	 * cannot predict: upload_max_filesize is the server's, and it can be lower
	 * than the limit this plugin advertises.
	 *
	 * @param int $error An UPLOAD_ERR_* code.
	 * @return string
	 */
	private static function upload_error( int $error ): string {
		if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
			return sprintf(
				/* translators: %s: a file size, e.g. "8 MB" */
				__( 'That file is larger than this site accepts (%s).', 'mudlet-shots' ),
				size_format( wp_max_upload_size() )
			);
		}

		return __( 'No screenshot arrived. Choose a file and try again.', 'mudlet-shots' );
	}

	/**
	 * Tell somebody there is something to look at.
	 *
	 * Throttled to one an hour however many arrive, because the failure this is
	 * guarding against is not a full inbox but a filtered one: a rule that sends
	 * these to a folder is written the first time twenty arrive at once, and
	 * after that the queue is unattended and nobody knows it.
	 *
	 * The message carries no picture and no description - only a count and a
	 * link. What is in the queue is unreviewed, and mailing it out is the one
	 * thing this whole plugin exists to avoid.
	 */
	private static function notify(): void {
		/**
		 * Filter who hears about new submissions. Empty turns the mail off.
		 *
		 * @param string $to Recipient.
		 */
		$to = (string) apply_filters( 'mudlet_shots_notify', (string) get_option( 'admin_email' ) );

		if ( '' === $to || ! is_email( $to ) || get_transient( 'mudlet_shots_notified' ) ) {
			return;
		}

		set_transient( 'mudlet_shots_notified', 1, self::NOTIFY_EVERY );

		$waiting = Mudlet_Shots_Store::pending();

		wp_mail(
			$to,
			sprintf(
				/* translators: %s: site name */
				__( '[%s] A screenshot is waiting for review', 'mudlet-shots' ),
				wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES )
			),
			implode(
				"\n",
				array(
					sprintf(
						/* translators: %d: number of screenshots waiting */
						_n( '%d screenshot is waiting to be looked at.', '%d screenshots are waiting to be looked at.', $waiting, 'mudlet-shots' ),
						$waiting
					),
					'',
					Mudlet_Shots_Admin::screen_url(),
					'',
					__( 'Nothing appears on the site until somebody approves it.', 'mudlet-shots' ),
				)
			),
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}
}
