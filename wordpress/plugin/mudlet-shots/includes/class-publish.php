<?php
/**
 * Approving one, which is the moment everything else has been arranged around.
 *
 * Three things happen, in this order, and the order is the point: the file
 * becomes an attachment, the attachment gets its caption and its alt text, and
 * only then is it written into the gallery on /media/. Until the first of those
 * there is no public URL for the picture at all.
 *
 * ---------------------------------------------------------------------------
 *
 * Why the gallery and not somewhere of our own.
 *
 * /media/ is blocks in a page body and nothing else - no template, no post
 * type, no plugin - and that is a decision the theme's README defends at
 * length. This plugin does not get to overturn it by inventing a second place
 * screenshots live. So approving one does exactly what an editor would do by
 * hand: it appends a core/image block to the first core/gallery in that page.
 *
 * The reward for that is that everything downstream keeps working without
 * knowing this plugin exists. The front page's thumbnail row and the demo
 * world's Gallery both read the same gallery, through mudlet_front_thumbs() -
 * so one approval puts the picture in three places, and nothing anywhere holds
 * a second copy of the list.
 *
 * ---------------------------------------------------------------------------
 *
 * What happens when there is no gallery.
 *
 * The attachment is still made. A site whose /media/ page somebody has
 * rewritten, or has not got, gets the screenshot in its media library and a
 * notice saying where it did not go - which is a thing an editor can act on,
 * unlike a refusal that throws the picture away because a page was not the
 * shape this code expected.
 *
 * @package Mudlet_Shots
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turning a queued file into a published one.
 */
class Mudlet_Shots_Publish {

	/**
	 * Hook up the one filter this class needs on the front end.
	 */
	public static function init(): void {
		add_filter( 'wp_calculate_image_srcset', array( __CLASS__, 'no_srcset' ), 10, 5 );
	}

	/**
	 * Never offer a browser a choice of sizes for a picture that moves.
	 *
	 * WordPress derives every sub-size through an image editor, and an editor
	 * flattens an animation - so `shot-1024x576.webp` is a *still* of
	 * something that moves. That is fine where a still is wanted, and fatal
	 * where one is not: `srcset` is a set of equally acceptable alternatives,
	 * and core adds one to every image in post content carrying a
	 * `wp-image-<id>` class. The gallery would then animate or not depending
	 * on the width of the window, which is the kind of bug that gets reported
	 * as "sometimes it works".
	 *
	 * So for our animated attachments there is exactly one file, the one this
	 * plugin wrote, and image_block() below points the `src` straight at it.
	 *
	 * @param array<int, array<string, mixed>> $sources       Candidate sources.
	 * @param array<int, int>                  $size_array    Requested size.
	 * @param string                           $image_src     The src.
	 * @param array<string, mixed>             $image_meta    Attachment meta.
	 * @param int                              $attachment_id Attachment.
	 * @return array<int, array<string, mixed>>
	 */
	public static function no_srcset( $sources, $size_array = array(), $image_src = '', $image_meta = array(), $attachment_id = 0 ) {
		if ( $attachment_id && Mudlet_Shots_Store::is_animated( (int) $attachment_id ) ) {
			return array();
		}

		return $sources;
	}

	/**
	 * Accept one submission.
	 *
	 * @param int $post_id The submission.
	 * @return array<string, mixed>|WP_Error attachment id, and whether it reached the gallery.
	 */
	public static function approve( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || Mudlet_Shots_Store::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'mudlet_shot_missing', __( 'That submission is not there any more.', 'mudlet-shots' ) );
		}

		if ( 'pending' !== $post->post_status ) {
			return new WP_Error( 'mudlet_shot_decided', __( 'That submission has been dealt with already.', 'mudlet-shots' ) );
		}

		$path = Mudlet_Shots_Store::path( $post_id );
		if ( '' === $path ) {
			return new WP_Error( 'mudlet_shot_file', __( 'The image behind that submission has gone.', 'mudlet-shots' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$meta   = Mudlet_Shots_Store::META;
		$credit = (string) get_post_meta( $post_id, $meta['credit'], true );
		$about  = trim( (string) $post->post_content );

		// A name of our choosing, not the visitor's: this becomes a public URL
		// under uploads/, and the file it names arrived from a stranger.
		$name = 'mudlet-screenshot-' . $post_id . '.' . pathinfo( $path, PATHINFO_EXTENSION );

		// Sideload *moves* the file, which is what should happen: the queue is
		// where a picture waits, not a second copy of the library.
		$attachment_id = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $path,
			),
			0,
			null,
			array(
				'post_title'   => self::title( $post_id ),
				'post_excerpt' => self::caption( $credit ),
				'post_content' => '',
			)
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$attachment_id = (int) $attachment_id;

		// Alt text is the visitor's sentence about the picture, when they wrote
		// one. An empty alt is correct for a decorative image and wrong for
		// this - but a made-up one is worse than either, so nothing is invented
		// here. The reviewer can write one in the media library.
		if ( '' !== $about ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $about );
		}

		update_post_meta( $attachment_id, Mudlet_Shots_Store::ATTACHED, $post_id );
		update_post_meta( $post_id, $meta['attachment'], $attachment_id );

		// Carried onto the attachment because that is where every reader of it
		// is. The sub-sizes WordPress has just generated are all stills of it.
		if ( get_post_meta( $post_id, $meta['animated'], true ) ) {
			update_post_meta( $attachment_id, Mudlet_Shots_Store::ANIMATED, 1 );
		}

		$placed = self::add_to_gallery( $attachment_id, $credit, $about );

		// The record stays, as the note of what was accepted and who asked to
		// be credited. Its directory does not: the file lives in the media
		// library now.
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);
		Mudlet_Shots_Store::drop_files( $post_id );

		/**
		 * A screenshot has been accepted.
		 *
		 * @param int  $attachment_id The attachment it became.
		 * @param int  $post_id       The submission.
		 * @param bool $placed        Whether it reached the gallery.
		 */
		do_action( 'mudlet_shots_approved', $attachment_id, $post_id, ! is_wp_error( $placed ) && $placed );

		return array(
			'attachment' => $attachment_id,
			'placed'     => ! is_wp_error( $placed ) && $placed,
			'why'        => is_wp_error( $placed ) ? $placed->get_error_message() : '',
		);
	}

	/**
	 * Turn one down.
	 *
	 * The bytes go now; the record goes to the trash, where it sits for the
	 * usual thirty days in case the wrong button was pressed on the wrong card.
	 *
	 * @param int $post_id The submission.
	 * @return bool
	 */
	public static function reject( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || Mudlet_Shots_Store::POST_TYPE !== $post->post_type ) {
			return false;
		}

		Mudlet_Shots_Store::drop_files( $post_id );
		wp_trash_post( $post_id );

		/**
		 * A screenshot has been turned down.
		 *
		 * @param int $post_id The submission.
		 */
		do_action( 'mudlet_shots_rejected', $post_id );

		return true;
	}

	// -- the gallery ---------------------------------------------------

	/**
	 * The page whose gallery accepted screenshots go into.
	 *
	 * @return WP_Post|null
	 */
	public static function gallery_page(): ?WP_Post {
		$page = get_page_by_path( 'media' );

		/**
		 * Filter which page holds the screenshot gallery.
		 *
		 * @param WP_Post|null $page The page, or null if there is not one.
		 */
		$page = apply_filters( 'mudlet_shots_gallery_page', $page );

		return $page instanceof WP_Post ? $page : null;
	}

	/**
	 * Append one image to the first gallery on that page.
	 *
	 * @param int    $attachment_id The picture.
	 * @param string $credit        Who asked to be credited, or ''.
	 * @param string $alt           Alt text, or ''.
	 * @return bool|WP_Error True when it went in.
	 */
	public static function add_to_gallery( int $attachment_id, string $credit = '', string $alt = '' ) {
		$page = self::gallery_page();
		if ( ! $page ) {
			return new WP_Error( 'mudlet_shot_nopage', __( 'There is no /media/ page to put it on, so it is in the media library instead.', 'mudlet-shots' ) );
		}

		$blocks = parse_blocks( $page->post_content );
		$done   = false;
		$blocks = self::walk( $blocks, $attachment_id, $credit, $alt, $done );

		if ( ! $done ) {
			return new WP_Error( 'mudlet_shot_nogallery', __( 'The /media/ page has no gallery on it, so the screenshot is in the media library instead.', 'mudlet-shots' ) );
		}

		// The block comments this rebuilds are HTML comments, and kses runs
		// wp_kses over the inside of one - which is survivable but not worth
		// betting a page body on. The markup being written is this file's, not
		// anybody's input, so the filters come off for the one update and go
		// straight back on.
		$filtered = has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $filtered ) {
			kses_remove_filters();
		}

		$updated = wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => serialize_blocks( $blocks ),
			),
			true
		);

		if ( $filtered ) {
			kses_init_filters();
		}

		return is_wp_error( $updated ) ? $updated : true;
	}

	/**
	 * Find the first gallery and put the image at the end of it.
	 *
	 * Recursive because a gallery can be inside a group, a column or a cover,
	 * and on a page somebody has laid out it usually is.
	 *
	 * @param array<int, array<string, mixed>> $blocks        Parsed blocks.
	 * @param int                              $attachment_id The picture.
	 * @param string                           $credit        Credit, or ''.
	 * @param string                           $alt           Alt text, or ''.
	 * @param bool                             $done          Set once it has gone in.
	 * @return array<int, array<string, mixed>>
	 */
	private static function walk( array $blocks, int $attachment_id, string $credit, string $alt, bool &$done ): array {
		foreach ( $blocks as $i => $block ) {
			if ( $done ) {
				break;
			}

			if ( 'core/gallery' === ( $block['blockName'] ?? '' ) ) {
				$blocks[ $i ] = self::append( $block, $attachment_id, $credit, $alt );
				$done         = true;
				break;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$blocks[ $i ]['innerBlocks'] = self::walk( $block['innerBlocks'], $attachment_id, $credit, $alt, $done );
			}
		}

		return $blocks;
	}

	/**
	 * Put an image block at the end of a gallery block.
	 *
	 * The fiddly half is `innerContent`, which is the gallery's own HTML with a
	 * null standing in for each child block - serialize_blocks() walks it and
	 * swaps the nulls for the serialised children in order. So an extra child
	 * needs an extra null, and it has to go after the last one rather than at
	 * the end, or the new picture is serialised outside the figure that holds
	 * the gallery. An empty gallery has no nulls at all, and the right place is
	 * then just before its closing chunk.
	 *
	 * @param array<string, mixed> $gallery       The gallery block.
	 * @param int                  $attachment_id The picture.
	 * @param string               $credit        Credit, or ''.
	 * @param string               $alt           Alt text, or ''.
	 * @return array<string, mixed>
	 */
	private static function append( array $gallery, int $attachment_id, string $credit, string $alt ): array {
		// Whether the gallery links its pictures at their files. The theme's
		// carousel pattern sets linkTo:"media" and its lightbox reads the
		// href, so an image added without one would be the only picture on the
		// page that does not open.
		$link = 'media' === ( $gallery['attrs']['linkTo'] ?? '' );

		$gallery['innerBlocks']   = array_merge(
			$gallery['innerBlocks'] ?? array(),
			array( self::image_block( $attachment_id, $credit, $alt, $link ) )
		);
		$gallery['innerContent'] = self::with_slot( (array) ( $gallery['innerContent'] ?? array() ) );

		return $gallery;
	}

	/**
	 * One more slot for one more child, in the right place.
	 *
	 * @param array<int, string|null> $content innerContent.
	 * @return array<int, string|null>
	 */
	private static function with_slot( array $content ): array {
		$content = array_values( $content );

		$last = -1;
		foreach ( $content as $i => $chunk ) {
			if ( null === $chunk ) {
				$last = (int) $i;
			}
		}

		// After the last existing child, or - for a gallery with none - before
		// the chunk that closes the figure.
		$at = $last >= 0 ? $last + 1 : max( 0, count( $content ) - 1 );

		array_splice( $content, $at, 0, array( null ) );

		return $content;
	}

	/**
	 * The core/image block a gallery holds.
	 *
	 * Written out rather than built with a helper because there is no core
	 * function that does it: block markup is a string, and the string is the
	 * canonical form of the block.
	 *
	 * @param int    $attachment_id The picture.
	 * @param string $credit        Credit, or ''.
	 * @param string $alt           Alt text, or ''.
	 * @param bool   $link          Whether to link it at the full-size file.
	 * @return array<string, mixed>
	 */
	private static function image_block( int $attachment_id, string $credit, string $alt, bool $link ): array {
		$moves = Mudlet_Shots_Store::is_animated( $attachment_id );

		// large, not full: the gallery shows these at a few hundred pixels and
		// the lightbox links the file. Handing a browser a 2560px picture to
		// draw at 320 is somebody else's data allowance, which is the same
		// argument inc/demo-seed.php makes about the world's Gallery.
		//
		// An animation is the exception, and it is not a preference. `large`
		// is a *still* of it - every size WordPress derives goes through an
		// editor and an editor flattens animation - so a slide pointing there
		// is a frozen picture that only moves once somebody clicks it, which
		// is the bug this whole branch exists to fix. There is one usable file
		// and this is it. It is why fit_animated is half the stills' box: the
		// carousel is going to download the original either way.
		$size = $moves ? 'full' : 'large';
		$src  = (string) wp_get_attachment_image_url( $attachment_id, $size );
		$full = (string) wp_get_attachment_image_url( $attachment_id, 'full' );

		$img = sprintf(
			'<img src="%1$s" alt="%2$s" class="wp-image-%3$d"/>',
			esc_url( $src ),
			esc_attr( $alt ),
			$attachment_id
		);

		if ( $link ) {
			$img = sprintf( '<a href="%s">%s</a>', esc_url( $full ), $img );
		}

		$caption = self::caption( $credit );
		if ( '' !== $caption ) {
			$img .= sprintf( '<figcaption class="wp-element-caption">%s</figcaption>', esc_html( $caption ) );
		}

		$attrs = array(
			'id'       => $attachment_id,
			'sizeSlug' => $size,
		);
		if ( $link ) {
			$attrs['linkDestination'] = 'media';
		}

		// The class has to agree with the attribute or the block is invalid in
		// the editor, and an editor who opens /media/ gets asked to recover a
		// block that was written correctly.
		$html = sprintf( '<figure class="wp-block-image size-%s">%s</figure>', esc_attr( $size ), $img );

		return array(
			'blockName'    => 'core/image',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * The credit line, or nothing at all.
	 *
	 * Nothing at all is the common case: the field is optional and most people
	 * leave it. A picture with no caption is what every other screenshot on
	 * that page already is, so there is nothing to fill in.
	 *
	 * @param string $credit What they typed.
	 * @return string
	 */
	public static function caption( string $credit ): string {
		$credit = trim( $credit );

		return '' === $credit
			? ''
			: sprintf(
				/* translators: %s: the name somebody asked to be credited as */
				__( 'Screenshot by %s', 'mudlet-shots' ),
				$credit
			);
	}

	/**
	 * What the attachment is called in the media library.
	 *
	 * @param int $post_id The submission.
	 * @return string
	 */
	private static function title( int $post_id ): string {
		return sprintf(
			/* translators: %s: date the screenshot was sent in */
			__( 'Screenshot sent in %s', 'mudlet-shots' ),
			get_the_date( 'F Y', $post_id )
		);
	}
}
