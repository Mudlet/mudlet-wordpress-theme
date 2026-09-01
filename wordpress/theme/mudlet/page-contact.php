<?php
/**
 * Template Name: Contact
 *
 * /contact/ — two panels and a row of links.
 *
 * The page's own prose stays editable in wp-admin and renders above the panels.
 * What is under it is not typed:
 *
 *   Email     a slot for whichever contact form plugin this site installs, with
 *             a disabled placeholder and the address in it until one does —
 *             see inc/contact.php.
 *   Discord   the counts and the faces come from Discord's widget and invite
 *             endpoints — see inc/discord.php, and read its header before
 *             reaching for Discord's own iframe.
 *
 * The panels sit in the document in the order they are drawn - form left,
 * Discord right - rather than being reordered in CSS. A grid `order` would put
 * the keyboard somewhere the eye is not, and on a narrow screen the two stack
 * in this same order anyway.
 *
 * Both degrade rather than break: no answer from Discord leaves a plain invite
 * button, and no form plugin leaves a form that is visibly, deliberately not
 * accepting anything. Neither state prints a number nobody knows.
 *
 * The old page was three headings of prose — Discord, Forums, Email — with the
 * email one ending in "email the site admins" and no way to do it. The forum
 * kept its paragraph's worth of nothing, so it is a link card at the bottom
 * with GitHub and the wiki, which is what all three of them are.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();

$mudlet_discord = mudlet_discord_server();
$mudlet_mailto  = mudlet_contact_email_link();

while ( have_posts() ) :
	the_post();

	$mudlet_form = mudlet_contact_form( get_the_ID() );
	?>
	<div class="page page--page">
		<section class="sec">
			<div class="w">
				<div class="head">
					<?php
					// No count in the eyebrow. The Discord panel states it a screen's
					// width away, and the same number twice reads as two facts.
					?>
					<p class="eyebrow"><?php esc_html_e( 'contact', 'mudlet' ); ?></p>
					<h2><?php the_title(); ?></h2>
					<?php if ( has_excerpt() ) : ?>
						<p class="sub"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<?php endif; ?>
				</div>

				<?php if ( trim( get_the_content() ) ) : ?>
					<div class="prose">
						<?php the_content(); ?>
					</div>
				<?php endif; ?>

				<div class="ctgrid">

					<section class="ctpanel">
						<div class="ctpanel__top">
							<span class="ctpanel__mark"><?php mudlet_icon( 'mail' ); ?></span>
							<div>
								<h3><?php esc_html_e( 'Send us a message', 'mudlet' ); ?></h3>
								<p class="ctpanel__meta"><?php esc_html_e( 'for anything not for a public channel', 'mudlet' ); ?></p>
							</div>
						</div>

						<?php if ( $mudlet_form ) : ?>
							<div class="ctform"><?php echo $mudlet_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a shortcode's own rendered markup. ?></div>
						<?php else : ?>
							<?php
							// The placeholder. Every control is disabled, so it cannot be
							// filled in and silently swallowed - a form that looks live and
							// goes nowhere is worse than no form. The plugin replaces this
							// whole branch; see inc/contact.php.
							?>
							<form class="ctform ctform--soon">
								<div class="ctform__row">
									<p class="ctfield">
										<label for="ctname"><?php esc_html_e( 'Your name', 'mudlet' ); ?></label>
										<input type="text" id="ctname" autocomplete="name" disabled>
									</p>
									<p class="ctfield">
										<label for="ctmail"><?php esc_html_e( 'Your email', 'mudlet' ); ?></label>
										<input type="email" id="ctmail" autocomplete="email" disabled>
									</p>
								</div>
								<p class="ctfield">
									<label for="ctsubj"><?php esc_html_e( 'Subject', 'mudlet' ); ?></label>
									<input type="text" id="ctsubj" disabled>
								</p>
								<p class="ctfield">
									<label for="ctbody"><?php esc_html_e( 'Message', 'mudlet' ); ?></label>
									<textarea id="ctbody" rows="4" disabled></textarea>
								</p>
								<p><button class="btn" type="button" disabled><?php esc_html_e( 'Send', 'mudlet' ); ?></button></p>
							</form>

							<p class="ctsoon">
								<?php mudlet_icon( 'warning' ); ?>
								<span>
									<?php esc_html_e( 'This form is not connected yet — it is waiting on the site’s contact form plugin.', 'mudlet' ); ?>
									<?php if ( $mudlet_mailto ) : ?>
										<?php esc_html_e( 'Until it is, the address below is the way through.', 'mudlet' ); ?>
									<?php endif; ?>
								</span>
							</p>
						<?php endif; ?>

						<?php
						// Outside the branch above on purpose: the address is not the
						// placeholder's consolation prize. The live site publishes one and
						// some people would always rather use their own mail client than
						// type into a box on a web page - so it stays when the plugin
						// arrives and the placeholder goes. See mudlet_contact_email().
						if ( $mudlet_mailto ) :
							?>
							<p class="ctmail">
								<?php
								printf(
									/* translators: %s: an email address, already linked */
									esc_html__( 'Or write to %s directly.', 'mudlet' ),
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- entity-encoded by antispambot(); escaping again would print the entities.
									$mudlet_mailto
								);
								?>
							</p>
						<?php endif; ?>
					</section>

					<section class="ctpanel">
						<div class="ctpanel__top">
							<span class="ctpanel__mark"><?php mudlet_icon( 'discord' ); ?></span>
							<div>
								<h3><?php esc_html_e( 'Chat on Discord', 'mudlet' ); ?></h3>
								<?php if ( $mudlet_discord['online'] || $mudlet_discord['members'] ) : ?>
									<p class="ctpanel__meta">
										<?php if ( $mudlet_discord['online'] ) : ?>
											<span class="ctdot" aria-hidden="true"></span>
											<?php
											printf(
												/* translators: %s: number of people online */
												esc_html__( '%s online', 'mudlet' ),
												esc_html( number_format_i18n( $mudlet_discord['online'] ) )
											);
											?>
										<?php endif; ?>
										<?php if ( $mudlet_discord['online'] && $mudlet_discord['members'] ) : ?>
											<span class="sep">&middot;</span>
										<?php endif; ?>
										<?php if ( $mudlet_discord['members'] ) : ?>
											<?php
											printf(
												/* translators: %s: total number of server members */
												esc_html__( '%s members', 'mudlet' ),
												esc_html( number_format_i18n( $mudlet_discord['members'] ) )
											);
											?>
										<?php endif; ?>
									</p>
								<?php endif; ?>
							</div>
						</div>

						<?php
						// Faces, no names: the people in the widget did not choose to
						// appear here. See the header of inc/discord.php. Hidden from
						// assistive technology for the same reason the names are gone -
						// the count above already says what this row means.
						if ( $mudlet_discord['faces'] ) :
							$mudlet_rest = max( 0, $mudlet_discord['online'] - count( $mudlet_discord['faces'] ) );
							?>
							<p class="ctfaces" aria-hidden="true">
								<?php foreach ( $mudlet_discord['faces'] as $mudlet_face ) : ?>
									<img src="<?php echo esc_url( $mudlet_face ); ?>" alt="" width="32" height="32"
										loading="lazy" decoding="async" referrerpolicy="no-referrer">
								<?php endforeach; ?>
								<?php if ( $mudlet_rest ) : ?>
									<span class="ctmore">+<?php echo esc_html( number_format_i18n( $mudlet_rest ) ); ?></span>
								<?php endif; ?>
							</p>
						<?php endif; ?>

						<p><?php esc_html_e( 'Live help and discussion about Mudlet, text games and all the rest. Someone is usually around, and it is the fastest way to get an answer.', 'mudlet' ); ?></p>

						<a class="btn" href="<?php echo esc_url( $mudlet_discord['invite'] ); ?>">
							<?php mudlet_icon( 'discord' ); ?><?php esc_html_e( 'Join the server', 'mudlet' ); ?>
						</a>
					</section>

				</div>

				<div class="head head--tight">
					<h3><?php esc_html_e( 'Elsewhere', 'mudlet' ); ?></h3>
				</div>

				<div class="ctlinks">
					<a class="ctlink" href="https://forums.mudlet.org/">
						<?php mudlet_icon( 'chat' ); ?>
						<b><?php esc_html_e( 'The forum', 'mudlet' ); ?></b>
						<span><?php esc_html_e( 'Longer questions, showing off what you have built, and everything worth still being readable in a year.', 'mudlet' ); ?></span>
					</a>
					<a class="ctlink" href="https://github.com/Mudlet/Mudlet/issues">
						<?php mudlet_icon( 'github' ); ?>
						<b><?php esc_html_e( 'Bugs and ideas', 'mudlet' ); ?></b>
						<span><?php esc_html_e( 'File it on GitHub rather than mailing it. An issue gets picked up; an email gets lost.', 'mudlet' ); ?></span>
					</a>
					<a class="ctlink" href="https://wiki.mudlet.org/">
						<?php mudlet_icon( 'wiki' ); ?>
						<b><?php esc_html_e( 'The wiki', 'mudlet' ); ?></b>
						<span><?php esc_html_e( 'Manuals, the scripting API and the answers to most of what people ask us first.', 'mudlet' ); ?></span>
					</a>
				</div>
			</div>
		</section>
	</div>
	<?php
endwhile;

get_footer();
