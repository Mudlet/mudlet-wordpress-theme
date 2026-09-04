<?php
/**
 * Comments: the list, one comment, and the form.
 *
 * The site carried 156 approved comments for the whole of this theme's life and
 * drew none of them. There was no comments.php, `the theme draws no comment
 * list` was written in three places as though it were a decision, and the one
 * place it was actually enforced - default_comment_status - only ever governed
 * posts created after it ran. Every post the migration imports arrives with
 * comment_status open, because that is how mudlet.org has it. So the threads
 * were live, reachable by anything that posts straight to wp-comments-post.php,
 * and invisible to the people they were addressed to.
 *
 * **Avatars follow Settings -> Discussion, they are not decided here.**
 * mudlet.org shows Gravatars and `show_avatars` is on, so that is what this
 * draws. An earlier version of this file drew initials unconditionally and
 * argued for it - every other face on the site is initials or a picture the
 * person published themselves, and an <img> per comment is a request to
 * Automattic carrying a hash of the commenter's address and every reader's IP.
 * That argument is still worth making, but it is not a theme's to enforce:
 * WordPress has had a switch for it since 2.5 and hardcoding past it means the
 * checkbox in wp-admin lies. So the switch is honoured, and turning avatars off
 * gets the initials and stops the third-party requests - which makes the
 * privacy position available to whoever runs the site rather than imposed by
 * whoever wrote the theme.
 *
 * **No website field**, which is a theme decision, because core has no setting
 * for it. The URL field is a do-follow link on mudlet.org for the price of a
 * comment - the same argument mudlet-shots makes for taking a credit name and
 * refusing a credit link. Dropping it does not hide the URLs already in the
 * database; those are left as text, not linked.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Initials for a comment's author, the way mudlet_author_initials() does it for
 * a post's.
 *
 * Not that function: it reads a WP_User, and a comment author is usually a
 * name typed into a form by somebody who has no account.
 *
 * @param WP_Comment $comment The comment.
 * @return string One or two letters, or '?'.
 */
function mudlet_comment_initials( WP_Comment $comment ): string {
	$parts = preg_split( '/\s+/', trim( (string) $comment->comment_author ) ) ?: array();
	$out   = '';
	foreach ( array_slice( $parts, 0, 2 ) as $part ) {
		$out .= mb_strtoupper( mb_substr( $part, 0, 1 ) );
	}
	return '' !== $out ? $out : '?';
}

/**
 * One comment, opened but not closed.
 *
 * wp_list_comments() calls this for the opening half and lets its walker write
 * the matching </li>, which is why nothing here closes the element.
 *
 * @param WP_Comment $comment The comment.
 * @param array      $args    Arguments from wp_list_comments().
 * @param int        $depth   How deep this comment sits.
 */
function mudlet_comment( WP_Comment $comment, array $args, int $depth ): void {
	?>
	<li <?php comment_class( 'cmt' ); ?> id="comment-<?php comment_ID(); ?>">
		<article class="cmt__body">
			<header class="cmt__head">
				<?php
				// Settings -> Discussion decides; see the note at the top of
				// this file. get_avatar() returns '' when the option is off,
				// which is the initials path rather than a gap.
				$mudlet_face = get_option( 'show_avatars' )
					? get_avatar( $comment, 64, '', '', array( 'class' => 'avatar avatar--img' ) )
					: '';
				if ( $mudlet_face ) {
					echo $mudlet_face; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
				} else {
					?>
					<span class="avatar" aria-hidden="true"><?php echo esc_html( mudlet_comment_initials( $comment ) ); ?></span>
					<?php
				}
				?>
				<span class="cmt__who"><?php echo esc_html( get_comment_author( $comment ) ); ?></span>
				<?php if ( '0' !== $comment->user_id && user_can( (int) $comment->user_id, 'edit_posts' ) ) : ?>
					<span class="cmt__badge"><?php esc_html_e( 'Mudlet', 'mudlet' ); ?></span>
				<?php endif; ?>
				<span class="dot">·</span>
				<a class="cmt__when" href="<?php echo esc_url( get_comment_link( $comment ) ); ?>">
					<time datetime="<?php echo esc_attr( get_comment_time( 'c' ) ); ?>"><?php echo esc_html( get_comment_date( '', $comment ) ); ?></time>
				</a>
			</header>

			<?php if ( '1' !== $comment->comment_approved ) : ?>
				<p class="cmt__held"><?php esc_html_e( 'Waiting to be approved.', 'mudlet' ); ?></p>
			<?php endif; ?>

			<div class="cmt__text"><?php comment_text(); ?></div>

			<?php
			// Only drawn while the post still takes comments; on a closed thread
			// a Reply link is a button that leads to a form that is not there.
			if ( comments_open() ) {
				comment_reply_link(
					array(
						'depth'      => $depth,
						'max_depth'  => (int) $args['max_depth'],
						'before'     => '<p class="cmt__reply">',
						'after'      => '</p>',
						'reply_text' => __( 'Reply', 'mudlet' ),
					),
					$comment
				);
			}
			?>
		</article>
	<?php
	// No </li>: the walker writes it.
}

/**
 * The comment form, shaped like the rest of the site's forms.
 *
 * @return array Arguments for comment_form().
 */
function mudlet_comment_form_args(): array {
	$req       = (bool) get_option( 'require_name_email' );
	$mark      = $req ? ' <span class="cmtform__req" aria-hidden="true">*</span>' : '';
	$aria      = $req ? " aria-required='true' required" : '';
	$commenter = wp_get_current_commenter();
	$fields    = array(
		'author' => '<p class="cmtform__row"><label for="author">' . esc_html__( 'Name', 'mudlet' ) . $mark . '</label>'
			. '<input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" maxlength="245"' . $aria . ' /></p>',
		'email'  => '<p class="cmtform__row"><label for="email">' . esc_html__( 'Email', 'mudlet' ) . $mark . '</label>'
			. '<input id="email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" maxlength="100"' . $aria . ' /></p>',
	);

	/*
	 * The cookie opt-in has to be supplied rather than left out. Dropping the
	 * website field from `fields` is enough to stop it rendering, but core then
	 * puts the consent checkbox back on its own - comment-template.php:2595,
	 * "Ensure that the passed fields include cookies consent", which adds it to
	 * any custom fields array that has not got one. Its wording is core's, and
	 * core's wording promises to remember a website this form never asked for.
	 *
	 * So it is written here instead, saying the two things that are actually
	 * stored. Only when the site has the opt-in switched on at all; with the
	 * option off, core adds nothing and neither does this.
	 */
	if ( has_action( 'set_comment_cookies', 'wp_set_comment_cookies' ) && get_option( 'show_comments_cookies_opt_in' ) ) {
		$fields['cookies'] = '<p class="cmtform__row cmtform__row--check comment-form-cookies-consent">'
			. '<input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"'
			. ( empty( $commenter['comment_author_email'] ) ? '' : ' checked="checked"' ) . ' /> '
			. '<label for="wp-comment-cookies-consent">'
			. esc_html__( 'Save my name and email in this browser for next time.', 'mudlet' )
			. '</label></p>';
	}

	/*
	 * Core builds a "Required fields are marked *" sentence once and appends it
	 * to two different things: comment_notes_before, and logged_in_as. Only the
	 * first is ever replaced by a theme, so overriding the notes leaves the
	 * sentence stranded on the signed-in line - where it is not merely
	 * redundant but false, because signing in is exactly what removes the two
	 * fields the asterisk was pointing at.
	 *
	 * So logged_in_as is written out too. The profile and sign-out links are
	 * worth keeping; the sentence about fields that are not on screen is not.
	 */
	$logged_in_as = '';
	if ( is_user_logged_in() ) {
		$logged_in_as = sprintf(
			'<p class="cmtform__note">%s</p>',
			sprintf(
				/* translators: 1: the signed-in user's name, 2: edit-profile URL, 3: sign-out URL */
				wp_kses(
					__( 'Signed in as %1$s. <a href="%2$s">Edit your profile</a>, or <a href="%3$s">sign out</a>.', 'mudlet' ),
					array( 'a' => array( 'href' => array() ) )
				),
				esc_html( wp_get_current_user()->display_name ),
				esc_url( get_edit_user_link() ),
				esc_url( wp_logout_url( get_permalink() ) )
			)
		);
	}

	// The asterisk needs saying once, beside the fields that carry it, and only
	// when there are any - core's copy of this sentence went out with the line
	// above.
	$notes = esc_html__( 'Your email address is not published.', 'mudlet' );
	if ( $req ) {
		$notes .= ' ' . esc_html__( 'Required fields are marked *', 'mudlet' );
	}

	return array(
		'fields'               => $fields,
		'logged_in_as'         => $logged_in_as,
		'class_form'           => 'cmtform',
		'title_reply'          => __( 'Leave a comment', 'mudlet' ),
		'title_reply_to'       => __( 'Reply to %s', 'mudlet' ),
		'label_submit'         => __( 'Post comment', 'mudlet' ),
		'class_submit'         => 'btn',
		/*
		 * The label is kept and hidden rather than dropped. A placeholder is
		 * not a label: it goes away the moment somebody types, it is not
		 * reliably announced, and a field whose only name is inside it has no
		 * name at all once it has content. So the <label> stays for anything
		 * reading the form and the placeholder does the visible work - which
		 * is why the two do not say the same thing.
		 */
		'comment_field'        => '<p class="cmtform__row cmtform__row--full"><label class="screen-reader-text" for="comment">' . esc_html__( 'Comment', 'mudlet' ) . '</label>'
			. '<textarea id="comment" name="comment" rows="5" maxlength="65525" placeholder="' . esc_attr__( 'Write your comment…', 'mudlet' ) . '" required></textarea></p>',
		// The address is never published, and saying so is cheaper than being asked.
		'comment_notes_before' => '<p class="cmtform__note">' . $notes . '</p>',
		'comment_notes_after'  => '',
	);
}
