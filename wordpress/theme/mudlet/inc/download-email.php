<?php
/**
 * Mailing a download link to somebody.
 *
 * The live site does this with wp-downloadmanager's "Send this link via
 * E-mail?" box: a jQuery post to /mudlet-dl-link.php carrying the address, a
 * reCAPTCHA token, and - the part worth not copying - the download URL read
 * back out of the page. An endpoint that mails whatever URL a browser hands it
 * is a way to send mail from mudlet.org with a stranger's link inside, so this
 * one takes a build key and looks the URL up itself, in inc/downloads.php.
 * Nothing the visitor types reaches the message at all: the address is a
 * recipient, never a line in the body.
 *
 * There is no nonce, and that is deliberate. A nonce is printed into the page,
 * so it is only as fresh as the cache in front of it, and the first symptom of
 * that is a form which works for a logged-in editor and fails for everyone
 * else. Standing in for it: a honeypot field, a cap per address and a cap per
 * IP, and one filter - `mudlet_download_email_verify` - for a site that wants
 * a captcha in front after all.
 *
 * Theme code rather than a plugin, for the same reason as inc/demo-seed.php:
 * unlike a game or a release, it owns nothing. The transients below are a rate
 * limit and not a record, and losing them costs nothing but patience.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether this site offers to mail the link at all.
 *
 * A site with no working mailer should turn this off rather than let the form
 * fail after the visitor has typed an address into it.
 *
 * @return bool
 */
function mudlet_download_email_enabled(): bool {
	/**
	 * Filter whether the download page offers to email the link.
	 *
	 * @param bool $enabled Whether the form is shown and the route answers.
	 */
	return (bool) apply_filters( 'mudlet_download_email_enabled', true );
}

add_action( 'rest_api_init', 'mudlet_download_email_route' );
/**
 * Register the one route.
 */
function mudlet_download_email_route(): void {
	register_rest_route(
		'mudlet/v1',
		'/download-link',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'mudlet_download_email_send',
			'permission_callback' => '__return_true',
			'args'                => array(
				// A key from mudlet_release_builds(), not a URL. The whole
				// point of the endpoint is that the caller does not choose
				// what gets mailed.
				'build'   => array(
					'required' => true,
					'type'     => 'string',
				),
				'email'   => array(
					'required' => true,
					'type'     => 'string',
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
 * Send one download link to one address.
 *
 * @param WP_REST_Request $request The request.
 * @return array<string, mixed>|WP_Error
 */
function mudlet_download_email_send( WP_REST_Request $request ) {
	$sent = array(
		'sent'    => true,
		'message' => __( 'Sent. Check your inbox — and the spam folder, if it takes its time.', 'mudlet' ),
	);

	if ( ! mudlet_download_email_enabled() ) {
		return new WP_Error(
			'mudlet_email_off',
			__( 'This site is not sending download links right now.', 'mudlet' ),
			array( 'status' => 404 )
		);
	}

	// A field nobody can see and nobody fills in. Something that filled it gets
	// the same answer as somebody who did not, because telling the two apart is
	// the one thing it came here to learn.
	if ( '' !== trim( (string) $request['website'] ) ) {
		return $sent;
	}

	$builds = mudlet_release_builds();
	$key    = (string) $request['build'];
	if ( ! isset( $builds[ $key ] ) || empty( $builds[ $key ]['url'] ) ) {
		return new WP_Error(
			'mudlet_email_build',
			__( 'That is not one of the downloads on this page.', 'mudlet' ),
			array( 'status' => 400 )
		);
	}

	$email = sanitize_email( (string) $request['email'] );
	if ( ! is_email( $email ) ) {
		return new WP_Error(
			'mudlet_email_address',
			__( 'That does not look like an email address.', 'mudlet' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Filter how many messages one sender and one recipient may ask for.
	 *
	 * `ip` is per hour, `address` per day. Both are counted from the last
	 * attempt rather than the first, so hammering the form extends its own
	 * ban rather than waiting out a window.
	 *
	 * @param array<string, int> $limits Caps, keyed `ip` and `address`.
	 */
	$limits = (array) apply_filters(
		'mudlet_download_email_limits',
		array(
			'ip'      => 5,
			'address' => 3,
		)
	);

	$ip = mudlet_download_email_ip();
	if ( $ip && ! mudlet_download_email_allow( 'ip:' . $ip, (int) $limits['ip'], HOUR_IN_SECONDS ) ) {
		return new WP_Error(
			'mudlet_email_rate',
			__( 'That is a lot of download links. Try again in an hour.', 'mudlet' ),
			array( 'status' => 429 )
		);
	}
	if ( ! mudlet_download_email_allow( 'to:' . strtolower( $email ), (int) $limits['address'], DAY_IN_SECONDS ) ) {
		return new WP_Error(
			'mudlet_email_rate',
			__( 'That address has had this link already today.', 'mudlet' ),
			array( 'status' => 429 )
		);
	}

	/**
	 * Last word before the message goes out - where a captcha would hook in.
	 *
	 * Return true to send, false to refuse, or a WP_Error to refuse with
	 * something to say.
	 *
	 * @param bool            $ok      Whether to send.
	 * @param WP_REST_Request $request The request, tokens and all.
	 */
	$ok = apply_filters( 'mudlet_download_email_verify', true, $request );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	if ( true !== $ok ) {
		return new WP_Error(
			'mudlet_email_verify',
			__( 'We could not verify that request.', 'mudlet' ),
			array( 'status' => 403 )
		);
	}

	$build = $builds[ $key ];
	$lines = array(
		sprintf(
			/* translators: 1: version number, 2: platform name */
			__( 'Mudlet %1$s for %2$s', 'mudlet' ),
			mudlet_release_version(),
			$build['label']
		),
		'',
		$build['url'],
		'',
		trim( ( $build['note'] ?? '' ) . ' · ' . ( $build['size'] ?? '' ), ' ·' ),
	);
	if ( ! empty( $build['sha'] ) ) {
		$lines[] = 'SHA-256: ' . $build['sha'];
	}
	$lines[] = '';
	$lines[] = __( 'Somebody asked mudlet.org to send you this link. We did not keep the address.', 'mudlet' );
	$lines[] = home_url( '/' );

	/**
	 * Filter the message itself.
	 *
	 * @param array<string, mixed> $mail  Subject, body and headers.
	 * @param string               $email Recipient.
	 * @param array<string, mixed> $build The build being linked.
	 */
	$mail = (array) apply_filters(
		'mudlet_download_email_message',
		array(
			'subject' => sprintf(
				/* translators: %s: version number */
				__( 'Your Mudlet %s download', 'mudlet' ),
				mudlet_release_version()
			),
			'body'    => implode( "\n", $lines ),
			'headers' => array( 'Content-Type: text/plain; charset=UTF-8' ),
		),
		$email,
		$build
	);

	if ( ! wp_mail( $email, $mail['subject'], $mail['body'], $mail['headers'] ) ) {
		return new WP_Error(
			'mudlet_email_failed',
			__( 'The mail did not go out. The download button above still works.', 'mudlet' ),
			array( 'status' => 500 )
		);
	}

	return $sent;
}

/**
 * Count one attempt against a bucket, and say whether it was within the cap.
 *
 * Attempts are counted, not sends: a mailer that is down must not become a way
 * to knock on the endpoint for free.
 *
 * @param string $bucket Something to count, already unique to the sender.
 * @param int    $limit  How many are allowed in the window.
 * @param int    $window Seconds.
 * @return bool
 */
function mudlet_download_email_allow( string $bucket, int $limit, int $window ): bool {
	$key  = 'mudlet_dlmail_' . md5( $bucket );
	$seen = (int) get_transient( $key );
	set_transient( $key, $seen + 1, $window );
	return $seen < $limit;
}

/**
 * The caller's address, as far as this server can tell.
 *
 * REMOTE_ADDR and nothing else: behind a proxy every visitor shares one, which
 * makes the per-IP cap useless rather than wrong, and reading a forwarded-for
 * header nobody has verified makes it worse than useless. A site that knows
 * its own proxy can filter this.
 *
 * @return string
 */
function mudlet_download_email_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	/**
	 * Filter the client address the rate limit counts against.
	 *
	 * @param string $ip The address.
	 */
	return (string) apply_filters( 'mudlet_download_email_ip', $ip );
}
