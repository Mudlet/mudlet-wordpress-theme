<?php
/**
 * The form slot on /contact/, and the address under it.
 *
 * The live site's contact form is a shortcode from a forms plugin, and that is
 * where this one is going too. So the template does not own a form: it owns a
 * slot, and draws a disabled, clearly-labelled placeholder in it until a plugin
 * fills it. Nothing here sends mail, and nothing here is a captcha.
 *
 * Why a slot and not just "paste the shortcode into the page body": the body is
 * prose and renders above the panels, at full measure. The form belongs inside
 * the Email panel, beside Discord, and a shortcode in the body cannot get
 * there. One field on the edit screen puts it in the right box.
 *
 * Plugin-agnostic on purpose. It stores whatever shortcode it is given and runs
 * it - Contact Form 7, WPForms, Fluent Forms, Forminator - because which plugin
 * a site ends up with is not a decision a theme gets to make, and hard-coding
 * `[contact-form-7 …]` would make it one.
 *
 * Theme code and not a plugin for the usual reason: it owns nothing. The one
 * stored value is a pointer at a form somebody else owns, and a theme rewrite
 * that loses it costs one paste.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/** Where the shortcode is stored. */
const MUDLET_CONTACT_FORM_META = '_mudlet_contact_form';

/**
 * Whether a page is drawn by page-contact.php.
 *
 * Two ways to land on that template - an explicit assignment, which is what the
 * seed does, or the slug, which WordPress matches on its own - so two things to
 * check. Same shape as mudlet_is_makers_page().
 *
 * @param WP_Post|int|null $post Page.
 * @return bool
 */
function mudlet_is_contact_page( $post = null ): bool {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return false;
	}

	return 'page-contact.php' === get_page_template_slug( $post ) || 'contact' === $post->post_name;
}

/**
 * The address on the page. Shown whether or not there is a form.
 *
 * An option of its own rather than `admin_email`, which is only the fallback:
 * the admin address is where WordPress sends password resets and fatal-error
 * notices, and "the address the site publishes" and "the address that can reset
 * the site" are not the same fact and should not be one field. Set it with
 *
 *   wp option update mudlet_contact_email you@example.org
 *
 * @return string
 */
function mudlet_contact_email(): string {
	$email = trim( (string) get_option( 'mudlet_contact_email', '' ) );
	if ( '' === $email ) {
		$email = (string) get_option( 'admin_email' );
	}

	/**
	 * Filter the address printed on /contact/.
	 *
	 * @param string $email Email address.
	 */
	return sanitize_email( (string) apply_filters( 'mudlet_contact_email', $email ) );
}

/**
 * That address as a mailto link, kept as text.
 *
 * The live site publishes it as a **PNG of the text**, and the honest reading is
 * that the picture is the stronger obfuscation. antispambot() encodes half the
 * characters as HTML entities, which defeats a regex over the raw bytes and
 * nothing else: every HTML parser decodes entities on its way in, so anything
 * built on one - or on a headless browser, or on a single call to
 * html.unescape() - reads the address with no extra work at all. Lifting it out
 * of a picture takes OCR, and harvesting at scale does not OCR every image on
 * every page on the chance one is an address.
 *
 * The picture is dropped anyway, because what it protects is already public.
 * The same address is in plain text in Mudlet's own src/dlgAboutDialog.cpp, on
 * GitHub, beside twenty-nine others, and it ships inside every Mudlet binary in
 * Help -> About. A harvester reads it there for free. (That file is also why
 * the makers plugin drops the addresses it finds rather than printing them on
 * /the-makers/.) So the PNG is a lock on a door standing next to an open
 * wall - and it charges the full price of one: an image cannot be selected,
 * copied, searched, read aloud by a screen reader, or clicked to open a mail
 * client, and it is the wrong colour in one of the two themes and blurred at
 * any zoom. Putting the address in its alt text to fix that would leave it
 * weaker than the entities below.
 *
 * If this ever needs to be stronger than a speed bump, the answer is not a
 * better picture: it is to publish no address at all once a form plugin fills
 * the slot in mudlet_contact_form(), and let the form be the way through.
 *
 * antispambot() is WordPress's own answer to the same problem and keeps the
 * address as text, which is why neither half goes through esc_html() afterwards
 * - that would escape the ampersands and print the entities at the visitor.
 *
 * @return string Anchor markup, or '' when the site has no usable address.
 */
function mudlet_contact_email_link(): string {
	$email = mudlet_contact_email();
	if ( ! $email ) {
		return '';
	}

	$safe = antispambot( $email );

	return '<a href="mailto:' . $safe . '">' . $safe . '</a>';
}

/**
 * The form that goes in the slot.
 *
 * Empty means there is no form yet, and the template draws the placeholder.
 *
 * @param int|null $post_id Page id.
 * @return string Rendered markup.
 */
function mudlet_contact_form( ?int $post_id = null ): string {
	$post_id   = $post_id ?: get_the_ID();
	$shortcode = $post_id ? trim( (string) get_post_meta( $post_id, MUDLET_CONTACT_FORM_META, true ) ) : '';

	/**
	 * Filter the contact form's shortcode before it is run.
	 *
	 * A site that would rather configure this in code than in wp-admin returns
	 * its shortcode here and never touches the field.
	 *
	 * @param string   $shortcode Stored shortcode.
	 * @param int|null $post_id   Page id.
	 */
	$shortcode = (string) apply_filters( 'mudlet_contact_form_shortcode', $shortcode, $post_id );

	if ( '' === $shortcode ) {
		return '';
	}

	// An unregistered shortcode renders as its own source. A visitor reading
	// "[contact-form-7 id="42"]" on the contact page is worse than the
	// placeholder, so a deactivated plugin falls back to it instead.
	$html = do_shortcode( $shortcode );

	return trim( $html ) === trim( $shortcode ) ? '' : $html;
}

add_action( 'add_meta_boxes_page', 'mudlet_contact_form_box' );
/**
 * Add the field, on that one page only.
 *
 * @param WP_Post $post Page being edited.
 */
function mudlet_contact_form_box( WP_Post $post ): void {
	if ( ! mudlet_is_contact_page( $post ) ) {
		return;
	}

	add_meta_box(
		'mudlet-contact-form',
		__( 'Contact form', 'mudlet' ),
		'mudlet_contact_form_field',
		'page',
		'side',
		'default'
	);
}

/**
 * Draw it.
 *
 * @param WP_Post $post Page being edited.
 */
function mudlet_contact_form_field( WP_Post $post ): void {
	wp_nonce_field( 'mudlet_contact_form', 'mudlet_contact_form_nonce' );
	$value = (string) get_post_meta( $post->ID, MUDLET_CONTACT_FORM_META, true );
	?>
	<p style="margin-top:0">
		<label for="mudlet_contact_form" style="display:block;margin-bottom:4px;font-weight:600">
			<?php esc_html_e( 'Form shortcode', 'mudlet' ); ?>
		</label>
		<input type="text" class="widefat code" id="mudlet_contact_form" name="mudlet_contact_form"
			value="<?php echo esc_attr( $value ); ?>" placeholder="[contact-form-7 id=&quot;42&quot;]">
	</p>
	<p class="description">
		<?php
		esc_html_e(
			'Renders inside the Email panel, beside Discord. Paste the shortcode from whichever contact form plugin this site uses. While it is empty the panel shows a disabled placeholder and the email address instead.',
			'mudlet'
		);
		?>
	</p>
	<?php
}

add_action( 'save_post_page', 'mudlet_contact_form_save', 10, 2 );
/**
 * Store what was typed.
 *
 * @param int     $post_id Page id.
 * @param WP_Post $post    Page.
 */
function mudlet_contact_form_save( $post_id, $post ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Absence is not emptiness: a save from Quick Edit, REST or a bulk edit
	// carries no field at all and must leave what is stored alone.
	if ( ! isset( $_POST['mudlet_contact_form'], $_POST['mudlet_contact_form_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mudlet_contact_form_nonce'] ) ), 'mudlet_contact_form' )
		|| ! current_user_can( 'edit_post', $post_id )
		|| ! mudlet_is_contact_page( $post ) ) {
		return;
	}

	$shortcode = trim( sanitize_text_field( wp_unslash( $_POST['mudlet_contact_form'] ) ) );

	if ( '' === $shortcode ) {
		delete_post_meta( $post_id, MUDLET_CONTACT_FORM_META );
		return;
	}

	update_post_meta( $post_id, MUDLET_CONTACT_FORM_META, $shortcode );
}
