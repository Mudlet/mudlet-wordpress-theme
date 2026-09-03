<?php
/**
 * The screenshot submission form.
 *
 * The [mudlet_screenshot_submit] shortcode belongs to the Mudlet Screenshots
 * plugin - what people send the site has to survive a theme rewrite - and it
 * calls this when the theme provides it, so the plugin decides what a
 * submission *is* and this decides what the form looks like. Same arrangement
 * as blocks/games.php next door.
 *
 * No heading. The shortcode goes in a page body, where whoever put it there has
 * already written one in Gutenberg, and a panel that supplies its own would
 * either duplicate that or argue with it.
 *
 * The class names are a contract with the plugin's assets/shots-form.js, which
 * lists them in its own header. Renaming one here gives the page a form that
 * looks right and posts nothing.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$mudlet_shot_facts = is_array( $args ?? null ) ? $args : array();
$mudlet_shot_url   = (string) ( $mudlet_shot_facts['endpoint'] ?? '' );

if ( '' === $mudlet_shot_url ) {
	return;
}
?>
<form class="shotform" data-endpoint="<?php echo esc_url( $mudlet_shot_url ); ?>" data-max="<?php echo esc_attr( (string) ( $mudlet_shot_facts['max_bytes'] ?? 0 ) ); ?>">

	<?php
	/*
	 * The drop zone is a <label> wrapped round a real <input type="file">, and
	 * both halves of that matter. The label is what makes the whole box open
	 * the picker on a click, with no JavaScript involved and no click handler
	 * to get wrong. The input is kept — clipped to a pixel rather than
	 * display:none, which would take it out of the tab order — because it is
	 * the only control that opens the picker from the keyboard, announces
	 * itself to a screen reader, and carries `required` and `accept` where the
	 * browser can act on them.
	 *
	 * So dragging is the part that is scripted, and it is the part that can be
	 * missing: with no JavaScript this is a file field in a box that says what
	 * to put in it. The filename below is drawn by the script for the same
	 * reason — the input's own copy of it is inside the pixel we clipped away.
	 */
	?>
	<label class="shotdrop" for="shotform-file">
		<input id="shotform-file" class="shotdrop__input" type="file" name="file"
			accept="<?php echo esc_attr( (string) ( $mudlet_shot_facts['accepts'] ?? 'image/*' ) ); ?>" required>
		<?php mudlet_icon( 'image', 'shotdrop__icon' ); ?>
		<span class="shotdrop__say">
			<?php esc_html_e( 'Drag a screenshot here, or choose one', 'mudlet' ); ?>
		</span>
		<?php
		/*
		 * The rules, inside the box rather than in a line above it. They are
		 * about the thing being dropped and nowhere else on the form has
		 * anything to do with them - and the numbers are not this file's:
		 * `max_size` is the smaller of what the plugin allows and what PHP
		 * will take, so on a server with a 2MB upload limit the box says 2MB.
		 */
		?>
		<span class="shotdrop__rules">
			<?php
			printf(
				/* translators: 1: accepted formats, e.g. "PNG, JPEG, GIF, and WebP", 2: a file size, 3: minimum width, 4: minimum height */
				esc_html__( '%1$s, up to %2$s, at least %3$s by %4$s pixels', 'mudlet' ),
				esc_html( (string) ( $mudlet_shot_facts['formats'] ?? '' ) ),
				esc_html( (string) ( $mudlet_shot_facts['max_size'] ?? '' ) ),
				esc_html( number_format_i18n( (int) ( $mudlet_shot_facts['min_long'] ?? 0 ) ) ),
				esc_html( number_format_i18n( (int) ( $mudlet_shot_facts['min_short'] ?? 0 ) ) )
			);
			?>
		</span>
		<span class="shotdrop__file" aria-live="polite"></span>
	</label>

	<div class="shotform__row">
		<label for="shotform-credit"><?php esc_html_e( 'Credit it to', 'mudlet' ); ?></label>
		<input id="shotform-credit" type="text" name="credit" maxlength="60"
			autocomplete="nickname"
			placeholder="<?php esc_attr_e( 'a name, if you would like one under it', 'mudlet' ); ?>">
	</div>

	<div class="shotform__row">
		<label for="shotform-about"><?php esc_html_e( 'What is in it', 'mudlet' ); ?></label>
		<input id="shotform-about" type="text" name="about" maxlength="240"
			placeholder="<?php esc_attr_e( 'a line about what it shows', 'mudlet' ); ?>">
	</div>

	<?php // The honeypot. Off the page rather than display:none, which is the first thing a bot checks for. ?>
	<input class="shotform__hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

	<div class="shotform__row shotform__row--send">
		<button class="btn" type="submit"><?php esc_html_e( 'Send it in', 'mudlet' ); ?></button>
		<p class="shotform__msg" role="status"></p>
	</div>

	<p class="shotform__fine">
		<?php
		esc_html_e(
			'Nothing appears on this page until somebody has looked at it. Sending a screenshot says it is yours to give and that Mudlet may show it here.',
			'mudlet'
		);
		?>
	</p>
</form>
