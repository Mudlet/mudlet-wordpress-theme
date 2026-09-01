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
 * string it puts on screen comes from inc/downloads.php.
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
					<div class="dlrow" data-os="<?php echo esc_attr( $key ); ?>">
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
					<p><?php esc_html_e( 'The Linux build is portable. Extract the launcher somewhere permanent, then run Mudlet from there.', 'mudlet' ); ?></p>
				</div>

				<div class="way way--wide">
					<?php mudlet_icon( 'code' ); ?>
					<h3><?php esc_html_e( 'Build it yourself', 'mudlet' ); ?></h3>
					<p><?php esc_html_e( 'The wiki carries build instructions for each OS.', 'mudlet' ); ?></p>
					<code>git clone https://github.com/Mudlet/Mudlet</code>
				</div>

				<div class="way">
					<?php mudlet_icon( 'history' ); ?>
					<h3><?php esc_html_e( 'Older versions', 'mudlet' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: %s: link reading "Browse the archive" */
							esc_html__( 'Every previous installer is still available, back to the early releases. %s.', 'mudlet' ),
							'<a href="https://github.com/Mudlet/Mudlet/releases">' . esc_html__( 'Browse the archive', 'mudlet' ) . '</a>'
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
							/* translators: 1: version number, 2: link reading "Tell us how it goes" */
							esc_html__( 'The Public Test Build carries everything that has landed since %1$s, on all three platforms. %2$s.', 'mudlet' ),
							esc_html( mudlet_release_version() ),
							'<a href="https://forums.mudlet.org/">' . esc_html__( 'Tell us how it goes', 'mudlet' ) . '</a>'
						);
						?>
					</p>
				</div>
				<div class="call">
					<h3><?php mudlet_icon( 'access' ); ?><?php esc_html_e( 'Playing with a screen reader?', 'mudlet' ); ?></h3>
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
