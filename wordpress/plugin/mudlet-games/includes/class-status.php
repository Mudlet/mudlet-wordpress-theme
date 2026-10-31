<?php
/**
 * Whether each game is up, and how many people are in it.
 *
 * A game record holds facts about a profile; this holds an observation about a
 * server, which is a different kind of thing and is kept apart from it. MSSP -
 * the Mud Server Status Protocol - is a telnet option most MUD servers answer
 * on connect: the client says IAC DO MSSP and the server replies with one
 * subnegotiation of NAME/VALUE pairs (PLAYERS, UPTIME, CODEBASE, CREATED…) and
 * nothing else is exchanged. Servers that predate the option often answer the
 * plain-text form instead, `MSSP-REQUEST` typed at the login prompt, which is
 * tried second and only after the telnet form has had a few seconds.
 *
 * Asked from the server and on a schedule, never from a visitor's browser: a
 * browser has no raw sockets, and a page that connected to the game on every
 * view would hand a MUD's admin one connection per reader of mudlet.org. This
 * is one connection per game per run, from one address, for the length of the
 * handshake - which is what every MUD listing site already does.
 *
 * Every game is probed at once rather than in turn. Sequentially, forty-three
 * servers that each take three seconds to say nothing is a cron run longer than
 * most hosts' max_execution_time; with stream_select() the whole run is bounded
 * by BUDGET however many there are.
 *
 * What is stored is one option, not post meta: it is rewritten wholesale every
 * run, it is never edited, and it is not a fact a record screen should show as
 * the game's own. It never says a game is *down*. A refused connection is as
 * likely to be this host's outbound firewall as the game, and "offline" printed
 * on forty-three cards because a hosting provider blocks port 23 is a worse
 * page than one that says nothing - so failure is recorded, and the theme is
 * only ever given what answered. See mudlet_game_status() in api.php.
 *
 * @package Mudlet_Games
 */

defined( 'ABSPATH' ) || exit;

/**
 * The MSSP probe, its schedule, and what it last heard.
 */
class Mudlet_Games_Status {

	const HOOK   = 'mudlet_games_status';
	const OPTION = 'mudlet_games_status';
	const SYNCED = 'mudlet_games_status_synced';

	/**
	 * Hourly: a player count is the one number on the site that is wrong within
	 * the hour, and the run costs forty-three short connections whatever it
	 * finds. Mudlet → Sync offers nothing shorter, deliberately.
	 */
	const EVERY = 'hourly';

	/** Seconds the whole run may take, all games together. */
	const BUDGET = 15;

	/** Seconds to wait for a TCP connection. */
	const CONNECT = 6;

	/** Seconds after connecting before trying the plain-text form. */
	const PLAIN_AFTER = 4;

	/** Seconds after connecting before giving up on MSSP and hanging up. */
	const GIVE_UP = 9;

	/** Bytes read from one server before it is assumed to be talking, not answering. */
	const MAX_READ = 65536;

	/**
	 * The fields kept, and nothing else a server volunteers. MSSP carries
	 * around sixty, most of them flags for a listing site's filters; these are
	 * the ones a page about a game can say something with.
	 */
	const FIELDS = array( 'PLAYERS', 'UPTIME', 'CODEBASE', 'CREATED', 'GENRE', 'GAMEPLAY', 'LANGUAGE' );

	// Telnet.
	const IAC  = "\xFF";
	const DO   = "\xFD";
	const WILL = "\xFB";
	const SB   = "\xFA";
	const SE   = "\xF0";
	const MSSP = "\x46";

	/**
	 * Hook up.
	 */
	public static function init(): void {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Keep the probe on the cadence Mudlet → Sync has for it.
	 */
	public static function schedule(): void {
		Mudlet_Sync::reschedule( self::HOOK, self::EVERY );
	}

	/**
	 * Probe every game and store what answered.
	 *
	 * @return array<int, array<string, mixed>> Post id => status.
	 */
	public static function run(): array {
		$targets = array();
		foreach ( mudlet_games() as $game ) {
			if ( '' !== $game['host'] && $game['port'] > 0 ) {
				$targets[ (int) $game['id'] ] = array(
					'host' => (string) $game['host'],
					'port' => (int) $game['port'],
					'tls'  => (bool) $game['tls'],
				);
			}
		}

		$status = self::probe( $targets );

		update_option( self::OPTION, $status, false );
		update_option( self::SYNCED, time(), false );

		return $status;
	}

	/**
	 * What was last heard from one game.
	 *
	 * @param int $id Game post id.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) && isset( $all[ $id ] ) && is_array( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * How the last run went, for the Sync screen and the CLI.
	 *
	 * @return array{checked: int, up: int, mssp: int}
	 */
	public static function counts(): array {
		$all = get_option( self::OPTION, array() );
		$out = array( 'checked' => 0, 'up' => 0, 'mssp' => 0 );
		foreach ( is_array( $all ) ? $all : array() as $one ) {
			++$out['checked'];
			$out['up']   += ! empty( $one['up'] ) ? 1 : 0;
			$out['mssp'] += ! empty( $one['mssp'] ) ? 1 : 0;
		}
		return $out;
	}

	/**
	 * Connect to every target at once and ask each for MSSP.
	 *
	 * One loop over stream_select() with a state per connection:
	 * connecting -> asking (telnet form sent) -> asking (plain form sent too)
	 * -> done. A connection is finished by an answer, by the server closing,
	 * by GIVE_UP, or by the run's BUDGET, whichever is first.
	 *
	 * @param array<int, array{host: string, port: int, tls: bool}> $targets Post id => address.
	 * @return array<int, array<string, mixed>> Post id => status.
	 */
	public static function probe( array $targets ): array {
		$start = microtime( true );
		$out   = array();
		$live  = array();

		foreach ( $targets as $id => $t ) {
			$out[ $id ] = array( 'at' => time(), 'up' => false, 'mssp' => array() );

			$errno  = 0;
			$errstr = '';
			$sock   = @stream_socket_client( // phpcs:ignore WordPress.PHP.NoSilencedErrors
				'tcp://' . $t['host'] . ':' . $t['port'],
				$errno,
				$errstr,
				self::CONNECT,
				STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
			);
			if ( ! $sock ) {
				$out[ $id ]['error'] = $errstr ? $errstr : 'connect failed';
				continue;
			}
			stream_set_blocking( $sock, false );
			$live[ $id ] = array(
				'sock'  => $sock,
				'tls'   => $t['tls'],
				'host'  => $t['host'],
				'state' => 'connecting',
				'since' => microtime( true ),
				'buf'   => '',
				'plain' => false,
			);
		}

		while ( $live && microtime( true ) - $start < self::BUDGET ) {
			$read  = array();
			$write = array();
			foreach ( $live as $id => $c ) {
				if ( 'connecting' === $c['state'] ) {
					$write[ $id ] = $c['sock'];
				} else {
					$read[ $id ] = $c['sock'];
				}
			}
			$except = null;
			if ( false === @stream_select( $read, $write, $except, 0, 200000 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				break;
			}

			foreach ( array_keys( $write ) as $id ) {
				$c = &$live[ $id ];
				// Writable is "connected, or failed": only a connected socket has
				// a peer to name.
				if ( false === stream_socket_get_name( $c['sock'], true ) ) {
					$out[ $id ]['error'] = 'connect refused';
					self::close( $live, $id );
					unset( $c );
					continue;
				}
				if ( $c['tls'] && ! self::tls( $c['sock'], $c['host'] ) ) {
					$out[ $id ]['error'] = 'tls handshake failed';
					self::close( $live, $id );
					unset( $c );
					continue;
				}
				$out[ $id ]['up'] = true;
				@fwrite( $c['sock'], self::IAC . self::DO . self::MSSP ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$c['state'] = 'asking';
				$c['since'] = microtime( true );
				unset( $c );
			}

			foreach ( array_keys( $read ) as $id ) {
				$chunk = @fread( $live[ $id ]['sock'], 8192 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( false === $chunk || ( '' === $chunk && feof( $live[ $id ]['sock'] ) ) ) {
					self::close( $live, $id );
					continue;
				}
				$live[ $id ]['buf'] .= $chunk;

				$mssp = self::parse( $live[ $id ]['buf'] );
				if ( $mssp ) {
					$out[ $id ]['mssp'] = $mssp;
					self::close( $live, $id );
				} elseif ( strlen( $live[ $id ]['buf'] ) > self::MAX_READ ) {
					self::close( $live, $id );
				}
			}

			$now = microtime( true );
			foreach ( $live as $id => $c ) {
				if ( 'connecting' === $c['state'] ) {
					if ( $now - $c['since'] > self::CONNECT ) {
						$out[ $id ]['error'] = 'connect timed out';
						self::close( $live, $id );
					}
					continue;
				}
				if ( $now - $c['since'] > self::GIVE_UP ) {
					self::close( $live, $id );
				} elseif ( ! $c['plain'] && $now - $c['since'] > self::PLAIN_AFTER ) {
					@fwrite( $c['sock'], "MSSP-REQUEST\r\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					$live[ $id ]['plain'] = true;
				}
			}
		}

		foreach ( array_keys( $live ) as $id ) {
			if ( 'connecting' === $live[ $id ]['state'] ) {
				$out[ $id ]['error'] = 'connect timed out';
			}
			self::close( $live, $id );
		}

		return $out;
	}

	/**
	 * Turn a connected socket into a TLS one.
	 *
	 * Blocking for the handshake alone, with a short timeout, then back: a
	 * non-blocking enable_crypto has to be polled, and one handshake is quick
	 * enough not to be worth the second state machine. The certificate is not
	 * verified. Nothing secret crosses this connection - it asks a public
	 * server a public question - and a MUD on a self-signed certificate is
	 * still up.
	 *
	 * @param resource $sock Connected socket.
	 * @param string   $host Name for SNI.
	 * @return bool
	 */
	private static function tls( $sock, string $host ): bool {
		stream_context_set_option( $sock, 'ssl', 'verify_peer', false );
		stream_context_set_option( $sock, 'ssl', 'verify_peer_name', false );
		stream_context_set_option( $sock, 'ssl', 'SNI_enabled', true );
		stream_context_set_option( $sock, 'ssl', 'peer_name', $host );

		stream_set_blocking( $sock, true );
		stream_set_timeout( $sock, 4 );
		$ok = @stream_socket_enable_crypto( $sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		stream_set_blocking( $sock, false );

		return true === $ok;
	}

	/**
	 * Hang up and forget a connection.
	 *
	 * @param array<int, array<string, mixed>> $live Open connections.
	 * @param int                              $id   Which.
	 */
	private static function close( array &$live, int $id ): void {
		if ( isset( $live[ $id ] ) ) {
			@fclose( $live[ $id ]['sock'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			unset( $live[ $id ] );
		}
	}

	/**
	 * Find an MSSP answer in what a server has sent so far, in either form.
	 *
	 * @param string $buf Everything read.
	 * @return array<string, string> Kept fields, or empty while there is no whole answer.
	 */
	public static function parse( string $buf ): array {
		$pairs = array();

		$open = strpos( $buf, self::IAC . self::SB . self::MSSP );
		if ( false !== $open ) {
			$close = strpos( $buf, self::IAC . self::SE, $open + 3 );
			if ( false === $close ) {
				return array();
			}
			// A literal 255 in a value is sent doubled.
			$body = str_replace( self::IAC . self::IAC, self::IAC, substr( $buf, $open + 3, $close - $open - 3 ) );

			// MSSP_VAR (1) opens a name, MSSP_VAL (2) opens a value, and a name
			// may carry several values.
			$name = null;
			$mark = null;
			foreach ( preg_split( '/([\x01\x02])/', $body, -1, PREG_SPLIT_DELIM_CAPTURE ) as $part ) {
				if ( "\x01" === $part || "\x02" === $part ) {
					$mark = $part;
					continue;
				}
				if ( null === $mark ) {
					continue;
				}
				if ( "\x01" === $mark ) {
					$name = strtoupper( trim( $part ) );
				} elseif ( null !== $name ) {
					$pairs[ $name ][] = $part;
				}
			}
		} else {
			$from = strpos( $buf, 'MSSP-REPLY-START' );
			if ( false === $from ) {
				return array();
			}
			$to = strpos( $buf, 'MSSP-REPLY-END', $from );
			if ( false === $to ) {
				return array();
			}
			foreach ( preg_split( '/\r?\n/', substr( $buf, $from + 16, $to - $from - 16 ) ) as $line ) {
				$cells = explode( "\t", trim( $line, "\r\n" ) );
				if ( count( $cells ) >= 2 ) {
					$pairs[ strtoupper( trim( array_shift( $cells ) ) ) ][] = implode( ', ', $cells );
				}
			}
		}

		$kept = array();
		foreach ( self::FIELDS as $field ) {
			if ( empty( $pairs[ $field ] ) ) {
				continue;
			}
			// Whatever arrives is somebody else's bytes: one line of printable
			// text, short, and never markup.
			$value = sanitize_text_field( wp_check_invalid_utf8( implode( ', ', array_filter( array_map( 'trim', $pairs[ $field ] ), 'strlen' ) ) ) );
			$value = mb_substr( $value, 0, 80 );
			if ( '' !== $value ) {
				$kept[ $field ] = $value;
			}
		}

		// A server that answered with nothing worth keeping still answered.
		return $kept ? $kept : ( $pairs ? array( 'ANSWERED' => '1' ) : array() );
	}
}
