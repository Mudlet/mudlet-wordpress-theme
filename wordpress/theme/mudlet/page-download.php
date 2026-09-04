<?php
/**
 * Template Name: Download
 *
 * The download page.
 *
 * theme.js leads with the build for the visitor's own platform and marks the
 * matching row, so the button and the table agree. That detection stays in the
 * browser - doing it server-side would mean either a user-agent database or a
 * Vary header that defeats every page cache in front of the site - but every
 * string it puts on screen comes from inc/downloads.php. On a platform it
 * cannot name - a phone, or anything with no build of its own - it takes that
 * panel off the page rather than leaving it restating the site name and a
 * version with nothing behind the button; the table below names every build.
 *
 * Every row carries its own hand-off - a code for that build's URL, a copy
 * button, and (only here, never in the prototype) a form that mails the link -
 * in a drawer the two icons in the row slide open. The build is chosen by
 * which row was pressed, so nothing under the table restates the four rows the
 * visitor is already looking at. The mail is sent by inc/download-email.php,
 * which resolves the URL from the build key itself rather than trusting the
 * one the browser sends.
 *
 * The page's own editable content, if any, renders under the table.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();

$builds = mudlet_release_builds();
// Whether this site can post an address anywhere. Read once rather than per
// row: it decides both a button and a form, four times over.
$mail   = mudlet_download_email_enabled();
$icons  = array(
	'win'    => 'windows',
	'macarm' => 'apple',
	'macx86' => 'apple',
	'linux'  => 'linux',
);
?>

<div class="page page--dl">
	<section class="dl">
		<div class="w">
			<div class="head">
				<p class="eyebrow"><?php esc_html_e( 'download', 'mudlet' ); ?><span><?php echo esc_html( mudlet_release_version() . ' · ' . mudlet_release_date() ); ?></span></p>
				<h2><?php the_title(); ?></h2>
				<p class="sub"><?php esc_html_e( 'Free and open source, under the GPL. No account, no telemetry, nothing to buy.', 'mudlet' ); ?></p>
			</div>

			<div class="dlmain">
				<span class="dlmain__icon" id="dlicon"><?php mudlet_icon( 'monitor' ); ?></span>
				<div class="dlmain__os">
					<h3 id="dlos"><?php echo esc_html( get_bloginfo( 'name' ) . ' ' . mudlet_release_version() ); ?></h3>
					<p class="meta" id="dlmeta"><?php esc_html_e( 'Choose your platform below.', 'mudlet' ); ?></p>
					<p class="alt" id="dlalt" hidden></p>
				</div>
				<a class="btn btn--xl" id="dlbtn" href="#downloads"><?php esc_html_e( 'Download Mudlet', 'mudlet' ); ?></a>
			</div>

			<div class="dltable" id="downloads">
				<?php foreach ( $builds as $key => $build ) : ?>
					<?php
					// data-latest is the version-less alias. The row's own link
					// stays version-pinned, because the checksum printed beside
					// it describes that exact file - but the code, the copy
					// button and the emailed link all hand this build to
					// somewhere else, and are read after the next release has
					// shipped. theme.js prefers it for those three.
					?>
					<div class="dlrow" data-os="<?php echo esc_attr( $key ); ?>"
						<?php if ( ! empty( $build['latest'] ) ) : ?>data-latest="<?php echo esc_url( $build['latest'] ); ?>"<?php endif; ?>>
						<div class="dlrow__id">
							<?php mudlet_icon( $icons[ $key ] ?? 'monitor' ); ?>
							<div>
								<b><?php echo esc_html( $build['label'] ); ?></b>
								<span class="note"><?php echo esc_html( $build['note'] ); ?></span>
								<?php if ( ! empty( $build['sha'] ) ) : ?>
									<button class="sha" type="button" data-sha="<?php echo esc_attr( $build['sha'] ); ?>"
										aria-label="
										<?php
										printf(
											/* translators: %s: platform name */
											esc_attr__( 'Copy the SHA-256 checksum for %s', 'mudlet' ),
											esc_attr( $build['label'] )
										);
										?>
										">
										<span class="sha__k">sha256</span>
										<span class="sha__v"><?php echo esc_html( substr( $build['sha'], 0, 24 ) ); ?>…</span>
										<?php mudlet_icon( 'copy' ); ?>
									</button>
								<?php endif; ?>
							</div>
						</div>
						<span class="size"><?php echo esc_html( $build['size'] ); ?></span>

						<?php
						// The two hand-off buttons and the Download link, as one
						// control group. Both buttons ship hidden: neither opens
						// anything without theme.js, and a control that does
						// nothing should not be offered.
						?>
						<div class="dlrow__acts">
							<button class="dlact" type="button" data-face="qr"
								aria-expanded="false" aria-controls="dlmore-<?php echo esc_attr( $key ); ?>" hidden>
								<?php mudlet_icon( 'qr' ); ?>
								<span class="screen-reader-text">
									<?php
									printf(
										/* translators: %s: platform name */
										esc_html__( 'Show a code for the %s download', 'mudlet' ),
										esc_html( $build['label'] )
									);
									?>
								</span>
							</button>
							<?php if ( $mail ) : ?>
								<button class="dlact" type="button" data-face="mail"
									aria-expanded="false" aria-controls="dlmore-<?php echo esc_attr( $key ); ?>" hidden>
									<?php mudlet_icon( 'mail' ); ?>
									<span class="screen-reader-text">
										<?php
										printf(
											/* translators: %s: platform name */
											esc_html__( 'Email a link to the %s download', 'mudlet' ),
											esc_html( $build['label'] )
										);
										?>
									</span>
								</button>
							<?php endif; ?>
							<a class="btn btn--ghost" href="<?php echo esc_url( $build['url'] ); ?>"><?php esc_html_e( 'Download', 'mudlet' ); ?></a>
							<?php
							// Only when the row is not already pointing there:
							// a mirror nobody can route around is a single
							// point of failure, but two links to the same file
							// is just noise.
							if ( ! empty( $build['github'] ) && $build['github'] !== $build['url'] ) :
								/* translators: %s: platform name */
								$mudlet_from_github = sprintf( __( 'Download %s from GitHub', 'mudlet' ), $build['label'] );
								?>
								<a class="dlsrc" href="<?php echo esc_url( $build['github'] ); ?>" target="_blank" rel="noopener"
									title="<?php echo esc_attr( $mudlet_from_github ); ?>">
									<?php mudlet_icon( 'github' ); ?>
									<span class="screen-reader-text"><?php echo esc_html( $mudlet_from_github ); ?></span>
								</a>
							<?php endif; ?>
						</div>

						<?php
						// The drawer, shut. Which face it shows is data-face,
						// set by whichever button was pressed; the code itself
						// is drawn by theme.js on the first open, out of the
						// row's own link.
						?>
						<div class="dlmore" id="dlmore-<?php echo esc_attr( $key ); ?>" data-open="false" data-face="">
							<div class="dlmore__in">
								<div class="dlpane dlpane--qr">
									<div class="dlqr" aria-hidden="true"></div>
									<div>
										<p class="dlpane__say"></p>
										<div class="dlpane__acts">
											<span class="dlurl"></span>
											<button class="btn btn--ghost dlcopy" type="button"><?php esc_html_e( 'Copy link', 'mudlet' ); ?></button>
										</div>
									</div>
								</div>

								<?php if ( $mail ) : ?>
									<div class="dlpane dlpane--mail">
										<p><?php esc_html_e( 'We’ll email this build’s link and nothing else — no list, no follow-up.', 'mudlet' ); ?></p>
										<form class="dlmail">
											<label class="screen-reader-text" for="dlmail-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Email address', 'mudlet' ); ?></label>
											<input id="dlmail-<?php echo esc_attr( $key ); ?>" type="email" name="email" autocomplete="email" required
												placeholder="<?php esc_attr_e( 'you@example.com', 'mudlet' ); ?>">
											<?php // Nobody sees this, so nobody fills it in. See inc/download-email.php. ?>
											<input class="dlmail__hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
											<button class="btn" type="submit"><?php esc_html_e( 'Send', 'mudlet' ); ?></button>
											<p class="dlmail__msg" role="status"></p>
										</form>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="sec" style="padding-top:0">
		<div class="w">
			<div class="head">
				<p class="eyebrow"><?php esc_html_e( 'other ways in', 'mudlet' ); ?></p>
				<h2><?php esc_html_e( 'Other ways to install', 'mudlet' ); ?></h2>
			</div>

			<div class="ways">
				<?php
				// First, because the shortest way in is the one that installs
				// nothing - and it is the answer for the visitor the panel at
				// the top of this page took itself off for: a phone, a
				// Chromebook, anything with no build of its own in the table.
				//
				// Deliberately not a row in that table: every row there is a
				// file, with a size, a checksum and a QR of its URL, and this
				// is a page to open.
				?>
				<div class="way">
					<?php mudlet_icon( 'globe' ); ?>
					<h3><?php esc_html_e( 'In your browser', 'mudlet' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: 1: link reading "Mudlet Web", 2: link reading "the source" */
							esc_html__( '%1$s opens a profile in a browser tab with nothing to install, and Mudlet packages and Lua run in it unchanged. Read %2$s.', 'mudlet' ),
							'<a href="https://mudlet.github.io/mudlet-web/">' . esc_html__( 'Mudlet Web', 'mudlet' ) . '</a>',
							'<a href="https://github.com/Mudlet/mudlet-web">' . esc_html__( 'the source', 'mudlet' ) . '</a>'
						);
						?>
						<?php
						// Only where there is a build of it framed on the front
						// page to point at. Without one the hero stays scripted
						// and this would be sending somebody to look at a
						// terminal that is a picture of one.
						if ( mudlet_demo_src() ) {
							echo ' ' . esc_html__( 'It is what the terminal on the front page is running.', 'mudlet' );
						}
						?>
					</p>
				</div>

				<div class="way">
					<?php mudlet_icon( 'chrome' ); ?>
					<h3><?php esc_html_e( 'ChromeOS', 'mudlet' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: %s: link to the wiki instructions */
							esc_html__( 'Mudlet runs on ChromeOS through Linux (Crostini). The wiki has %s.', 'mudlet' ),
							'<a href="https://wiki.mudlet.org/">' . esc_html__( 'step-by-step instructions', 'mudlet' ) . '</a>'
						);
						?>
					</p>
				</div>

				<div class="way">
					<?php mudlet_icon( 'cube' ); ?>
					<h3><?php esc_html_e( 'Ubuntu &amp; Debian', 'mudlet' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: %s: link reading "your help" */
							esc_html__( 'Use the AppImage above. The Mudlet in Debian’s repositories is out of date — if you maintain it, we’d love %s.', 'mudlet' ),
							'<a href="https://github.com/Mudlet/Mudlet">' . esc_html__( 'your help', 'mudlet' ) . '</a>'
						);
						?>
					</p>
				</div>

				<div class="way">
					<?php mudlet_icon( 'truck' ); ?>
					<h3><?php esc_html_e( 'Portable', 'mudlet' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: 1: the file name portable.txt, 2: link reading "The wiki has the steps" */
							esc_html__( 'Every build can run from a USB stick: put an empty %1$s beside the executable and Mudlet keeps its profiles, packages and settings in a folder next to itself. %2$s.', 'mudlet' ),
							'<code>portable.txt</code>',
							'<a href="https://wiki.mudlet.org/w/Manual:Portable_App">' . esc_html__( 'The wiki has the steps', 'mudlet' ) . '</a>'
						);
						?>
					</p>
				</div>

				<?php
				// One tile wide, like the rest. It used to span two because of
				// the clone line under it: at the narrowest the grid ever draws
				// - three columns at the container's full width - that line is
				// 290px in a 320px box, so it fits, and six tiles make two rows
				// of three rather than a row with a hole in it. In the band of
				// widths where three columns are tighter than that, the line
				// scrolls inside its own box, which is what `.way code` has
				// always done on a narrow screen.
				?>
				<div class="way">
					<?php mudlet_icon( 'code' ); ?>
					<h3><?php esc_html_e( 'Build it yourself', 'mudlet' ); ?></h3>
					<p><?php esc_html_e( 'The wiki carries build instructions for each OS.', 'mudlet' ); ?></p>
					<?php
					// The command stays selectable text and the button sits
					// beside it, not inside it: that <code> is its own
					// horizontal scroll container on a narrow screen, and a
					// control within one scrolls away from the hand reaching
					// for it. Nothing here repeats the command - theme.js reads
					// the <code> next to the button, the way a download row
					// reads its own link - and the button ships hidden,
					// because without the script it copies nothing.
					?>
					<div class="clone">
						<code>git clone https://github.com/Mudlet/Mudlet</code>
						<button class="clone__cp" type="button" hidden>
							<?php
							mudlet_icon( 'copy', 'cp' );
							mudlet_icon( 'check', 'ok' );
							?>
							<span class="screen-reader-text"><?php esc_html_e( 'Copy the clone command', 'mudlet' ); ?></span>
						</button>
					</div>
				</div>

				<div class="way">
					<?php mudlet_icon( 'history' ); ?>
					<h3><?php esc_html_e( 'Older versions', 'mudlet' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: %s: link reading "Browse the archive" */
							esc_html__( 'Every previous installer is still available, back to the early releases. %s.', 'mudlet' ),
							'<a href="' . esc_url( mudlet_download_archive_url() ) . '">' . esc_html__( 'Browse the archive', 'mudlet' ) . '</a>'
						);
						?>
					</p>
				</div>
			</div>

			<div class="calls" style="margin-top:clamp(1.75rem,3.5vw,2.5rem)">
				<div class="call">
					<h3><?php mudlet_icon( 'flask' ); ?><?php esc_html_e( 'Want to test new developments?', 'mudlet' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: 1: version number, 2: link reading "Download a snapshot" */
							esc_html__( 'The Public Test Build carries everything that has landed since %1$s, on all three platforms. %2$s.', 'mudlet' ),
							esc_html( mudlet_release_version() ),
							// theme.js appends the visitor's own platform to this
							// URL, out of the same detection that leads the table
							// above, and leaves it off for anything that is not
							// Windows, macOS or Linux - the snapshots page then
							// offers all three rather than a platform we guessed.
							'<a id="ptb" href="https://make.mudlet.org/snapshots/?source=ptb">' . esc_html__( 'Download a snapshot', 'mudlet' ) . '</a>'
						);
						?>
					</p>
				</div>
				<div class="call">
					<h3><?php mudlet_icon( 'ear' ); ?><?php esc_html_e( 'Playing with a screen reader?', 'mudlet' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: %s: link reading "detailed setup instructions" */
							esc_html__( 'We keep %s for screen-reader software and Mudlet’s other accessibility options.', 'mudlet' ),
							'<a href="https://wiki.mudlet.org/">' . esc_html__( 'detailed setup instructions', 'mudlet' ) . '</a>'
						);
						?>
					</p>
				</div>
			</div>

			<?php
			// Anything the editor has typed into the page itself lands here,
			// under the tables rather than above them - the download is what
			// the visitor came for.
			while ( have_posts() ) :
				the_post();
				$body = trim( get_the_content() );
				if ( '' !== $body ) :
					?>
					<div class="prose" style="margin-top:clamp(1.75rem,3.5vw,2.5rem)"><?php the_content(); ?></div>
					<?php
				endif;
			endwhile;
			?>

			<p class="fineprint"><?php esc_html_e( 'Windows builds are code-signed free of charge by SignPath.io, with a certificate from the SignPath Foundation. Mudlet does not transfer any information to other networked systems unless you ask it to.', 'mudlet' ); ?></p>
		</div>
	</section>
</div>

<?php
get_footer();
