<?php
/**
 * The surface a theme is allowed to touch.
 *
 * Everything else in this plugin is private. A theme calls these behind
 * `function_exists()`, the same way inc/games.php and inc/makers.php call the
 * other two plugins, and a site with this plugin gone has a /media/ page with
 * no form on it rather than a fatal error.
 *
 * ---------------------------------------------------------------------------
 *
 * Why the form is a shortcode and not a block.
 *
 * /media/ has no template - it is a page body and nothing else - so wherever
 * the form goes, an editor has to put it there. That leaves a block or a
 * shortcode, and the deciding argument is what each looks like on the day this
 * plugin is not running: WordPress renders an unregistered dynamic block as
 * *nothing at all*, silently, which is the exact reasoning that put the
 * mudlet/games block in the games plugin. A shortcode nobody has registered
 * renders as its own source - visible, ugly, and fixable by the first person
 * who looks at the page.
 *
 * For a form, visible-and-ugly is the better failure. A submission form that
 * has quietly not been on the page for three months looks identical to a
 * submission form nobody has used.
 *
 * ---------------------------------------------------------------------------
 *
 * The look is the theme's.
 *
 * mudlet_shots_form() hands off to template-parts/blocks/screenshot-submit.php
 * when the theme has one, exactly as Mudlet_Games_Block::render() does, and
 * falls back to a plain unstyled form when it does not. What the *script*
 * expects of that markup is a contract, and it is written out in
 * assets/shots-form.js - a theme that renames the classes gets a form that
 * submits nothing.
 *
 * @package Mudlet_Shots
 */

defined( 'ABSPATH' ) || exit;

/**
 * The shortcode tag.
 *
 * @return string
 */
function mudlet_shots_shortcode(): string {
	return 'mudlet_screenshot_submit';
}

/**
 * Whether this site is taking screenshots.
 *
 * Defaults to on. The form is not on any page until somebody puts it there, so
 * the placement is already the opt-in, and a plugin that ships switched off is
 * a plugin whose first bug report is that it does nothing. Turning it off here
 * closes the endpoint too, which is the point: a form removed from a page is
 * not a closed endpoint.
 *
 * @return bool
 */
function mudlet_shots_enabled(): bool {
	/**
	 * Filter whether screenshots may be submitted at all.
	 *
	 * @param bool $enabled Whether the form draws and the endpoint answers.
	 */
	return (bool) apply_filters( 'mudlet_shots_enabled', true );
}

/**
 * Where the browser posts.
 *
 * @return string
 */
function mudlet_shots_endpoint(): string {
	return esc_url_raw( rest_url( 'mudlet/v1/screenshot' ) );
}

/**
 * The largest file this site will actually take.
 *
 * The smaller of what the plugin allows and what PHP will accept, because a
 * form advertising 12MB on a server with `upload_max_filesize = 2M` is a form
 * that fails after the upload rather than before it.
 *
 * @return int Bytes.
 */
function mudlet_shots_max_bytes(): int {
	$limits = Mudlet_Shots_Image::limits();

	return (int) min( (int) $limits['max_bytes'], (int) wp_max_upload_size() );
}

/**
 * What to put in the file input's `accept`.
 *
 * A hint to the file picker, never a check - the check is in class-image.php,
 * where the file is decoded.
 *
 * @return string
 */
function mudlet_shots_accepts(): string {
	// GIF is offered whether or not this site can keep an animation moving: a
	// *still* GIF goes down the same path as a PNG and works everywhere, and
	// an animated one gets a refusal that says exactly what is wrong. Taking
	// them out of the picker to prevent the second would prevent the first.
	return 'image/png,image/jpeg,image/gif,image/webp';
}

/**
 * The numbers the form quotes at the visitor.
 *
 * One call so that the sentence under the field and the check inside it cannot
 * drift apart: the same array feeds the prose, the `data-max` attribute and the
 * script's own guard.
 *
 * @return array<string, mixed>
 */
function mudlet_shots_form_facts(): array {
	$limits = Mudlet_Shots_Image::limits();

	return array(
		'endpoint'  => mudlet_shots_endpoint(),
		'accepts'   => mudlet_shots_accepts(),
		'max_bytes' => mudlet_shots_max_bytes(),
		'max_size'  => size_format( mudlet_shots_max_bytes() ),
		'min_long'  => (int) $limits['min_long'],
		'min_short' => (int) $limits['min_short'],
		'fit'       => (int) $limits['fit'],
		'formats'   => wp_sprintf_l( '%l', array_values( Mudlet_Shots_Image::accepted() ) ),
	);
}

add_action( 'init', 'mudlet_shots_register_shortcode' );
/**
 * Register the shortcode.
 */
function mudlet_shots_register_shortcode(): void {
	add_shortcode( mudlet_shots_shortcode(), 'mudlet_shots_shortcode_render' );
}

/**
 * Draw the form where the shortcode is.
 *
 * @return string
 */
function mudlet_shots_shortcode_render(): string {
	return mudlet_shots_form();
}

/**
 * The form.
 *
 * Empty when submissions are off, which is what makes the switch a switch: the
 * page keeps its prose and loses the form, rather than showing one that answers
 * 404.
 *
 * @return string
 */
function mudlet_shots_form(): string {
	if ( ! mudlet_shots_enabled() ) {
		return '';
	}

	$facts = mudlet_shots_form_facts();

	// Only where a form is actually drawn. Nothing else on the site needs it,
	// and /media/ is one page.
	wp_enqueue_script( 'mudlet-shots-form' );
	wp_localize_script( 'mudlet-shots-form', 'MUDLET_SHOTS', mudlet_shots_script_data() );

	if ( locate_template( 'template-parts/blocks/screenshot-submit.php' ) ) {
		ob_start();
		get_template_part( 'template-parts/blocks/screenshot-submit', null, $facts );
		return (string) ob_get_clean();
	}

	return mudlet_shots_form_fallback( $facts );
}

add_action( 'wp_enqueue_scripts', 'mudlet_shots_register_script' );
/**
 * Register the script the form needs, without enqueueing it.
 *
 * Registered on every front-end request and enqueued only by the form, so the
 * one page that has a form pays for it and the rest of the site does not.
 */
function mudlet_shots_register_script(): void {
	$file = dirname( MUDLET_SHOTS_FILE ) . '/assets/shots-form.js';

	wp_register_script(
		'mudlet-shots-form',
		// Not plugins_url(): the theme carries a copy of this plugin, and that
		// helper answers for wp-content/plugins only. See shared/mudlet-bundle.php.
		Mudlet_Bundle::url( MUDLET_SHOTS_FILE, 'assets/shots-form.js' ),
		array(),
		// mtime rather than the plugin version, which moves once a release.
		file_exists( $file ) ? (string) filemtime( $file ) : MUDLET_SHOTS_VERSION,
		true
	);
}

/**
 * The strings the script puts on screen.
 *
 * Everything the endpoint refuses comes back with a sentence of its own, so
 * these are only the ones the browser says by itself: what it is doing, and the
 * two things it can tell before sending anything.
 *
 * @return array<string, mixed>
 */
function mudlet_shots_script_data(): array {
	$facts = mudlet_shots_form_facts();

	return array(
		'url'     => $facts['endpoint'],
		'max'     => $facts['max_bytes'],
		'strings' => array(
			'sending' => __( 'Sending…', 'mudlet-shots' ),
			'failed'  => __( 'That did not get through. Try again in a moment.', 'mudlet-shots' ),
			'nofile'  => __( 'Choose a screenshot first.', 'mudlet-shots' ),
			'toobig'  => sprintf(
				/* translators: %s: a file size, e.g. "12 MB" */
				__( 'That file is larger than %s.', 'mudlet-shots' ),
				$facts['max_size']
			),
		),
	);
}

/**
 * The markup for a site whose theme has no opinion about this form.
 *
 * Deliberately plain, and deliberately carrying the same classes the styled one
 * does - it is the same form, unstyled, not a different one.
 *
 * @param array<string, mixed> $facts From mudlet_shots_form_facts().
 * @return string
 */
function mudlet_shots_form_fallback( array $facts ): string {
	ob_start();
	?>
	<form class="shotform" data-endpoint="<?php echo esc_url( (string) $facts['endpoint'] ); ?>" data-max="<?php echo esc_attr( (string) $facts['max_bytes'] ); ?>">
		<p>
			<label for="shotform-file"><?php esc_html_e( 'Your screenshot', 'mudlet-shots' ); ?></label><br>
			<input id="shotform-file" type="file" name="file" accept="<?php echo esc_attr( (string) $facts['accepts'] ); ?>" required>
		</p>
		<p>
			<label for="shotform-credit"><?php esc_html_e( 'Credit it to (optional)', 'mudlet-shots' ); ?></label><br>
			<input id="shotform-credit" type="text" name="credit" maxlength="60" autocomplete="nickname">
		</p>
		<p>
			<label for="shotform-about"><?php esc_html_e( 'What is in it? (optional)', 'mudlet-shots' ); ?></label><br>
			<input id="shotform-about" type="text" name="about" maxlength="240">
		</p>
		<input class="shotform__hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
		<p><button type="submit"><?php esc_html_e( 'Send it in', 'mudlet-shots' ); ?></button></p>
		<p class="shotform__msg" role="status"></p>
	</form>
	<?php
	return (string) ob_get_clean();
}
