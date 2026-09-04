<?php
/**
 * The comment list and the form under a post.
 *
 * Reached through comments_template() in single.php. The pieces it draws each
 * comment with live in inc/comments.php, together with why there are no
 * avatars and no website field.
 *
 * Three states, and the middle one is the reason this file exists: a thread
 * with comments that are closed. Fifteen years of replies sit on posts nobody
 * is going to reply to again, and they are still worth reading - so the list is
 * drawn whenever there is one, quite apart from whether the form is.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/*
 * A password-protected post's comments are behind the same password. Bailing
 * here rather than rendering an empty list is what core expects, and it happens
 * before anything is printed.
 */
if ( post_password_required() ) {
	return;
}

$mudlet_count = (int) get_comments_number();

// Nothing to show and nothing to offer: draw no heading, no empty box.
if ( 0 === $mudlet_count && ! comments_open() ) {
	return;
}
?>

<section class="cmts" id="comments" aria-label="<?php esc_attr_e( 'Comments', 'mudlet' ); ?>">
	<?php if ( $mudlet_count > 0 ) : ?>
		<h2 class="cmts__head">
			<?php
			printf(
				/* translators: %s: number of comments */
				esc_html( _n( '%s comment', '%s comments', $mudlet_count, 'mudlet' ) ),
				esc_html( number_format_i18n( $mudlet_count ) )
			);
			?>
		</h2>

		<ol class="cmts__list">
			<?php
			wp_list_comments(
				array(
					'callback'   => 'mudlet_comment',
					'style'      => 'ol',
					// Pingbacks and trackbacks are not conversation, and most of
					// what is in the database under those types is link spam that
					// was never moderated. The list is people.
					'type'       => 'comment',
					'short_ping' => true,
				)
			);
			?>
		</ol>

		<?php
		// Only draws anything once a thread is longer than the discussion
		// setting's page length, which on this site is most of them are not.
		the_comments_pagination(
			array(
				'prev_text' => __( '← Older comments', 'mudlet' ),
				'next_text' => __( 'Newer comments →', 'mudlet' ),
			)
		);
		?>
	<?php endif; ?>

	<?php
	if ( comments_open() ) {
		comment_form( mudlet_comment_form_args() );
	} elseif ( $mudlet_count > 0 ) {
		?>
		<p class="cmts__closed">
			<?php esc_html_e( 'Comments are closed on this post.', 'mudlet' ); ?>
			<a href="https://forums.mudlet.org/"><?php esc_html_e( 'The forum is where discussion happens now.', 'mudlet' ); ?></a>
		</p>
		<?php
	}
	?>
</section>
