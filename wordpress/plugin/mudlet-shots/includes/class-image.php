<?php
/**
 * Deciding whether a file is a screenshot, and turning it into one we want.
 *
 * This is the file to read first. Everything else here is plumbing; this is
 * where the uploaded bytes stop being the uploaded bytes.
 *
 * ---------------------------------------------------------------------------
 *
 * Nothing is stored as it arrived.
 *
 * Whatever comes in is decoded by an image library and written back out - a
 * still as WebP scaled to fit 2560px, an animation as animated WebP scaled to
 * fit 1280px - and the file that was uploaded is unlinked. That is the space
 * saving the whole feature was asked for - a 4MB PNG of a terminal lands
 * somewhere around 300KB, and an animated GIF usually loses nine tenths of
 * itself - but the re-encode is doing three other jobs at the same time, and
 * any one of them would justify it alone:
 *
 * - **It is the file-type check that cannot be fooled.** An extension is a
 *   suggestion and a MIME header is whatever the client typed. A file that
 *   survives being decoded and re-encoded by an image library is an image,
 *   because a decoder that read it produced pixels. The entire class of
 *   PHP-in-a-PNG and polyglot uploads is gone rather than guarded against.
 * - **It drops EXIF.** A screenshot carries none, but a phone photo of
 *   somebody's screen carries where they were standing, and people do send
 *   those. Nothing is stripped selectively; the metadata simply is not carried
 *   across, because the encoder is writing a new file from pixels.
 * - **It makes the pictures one shape.** The community shots on /media/ run
 *   from 0.86 to 1.86 wide, which the gallery already copes with, but they
 *   should at least all be the same format at the same rough scale.
 *
 * ---------------------------------------------------------------------------
 *
 * Animation is a second path, and it has to be.
 *
 * `wp_get_image_editor()` flattens an animation to its first frame. Not as a
 * bug - it is an editor for *an* image, and every size WordPress derives from
 * an upload goes through it - but it means the obvious pipeline silently turns
 * somebody's twelve-second demonstration of a trigger firing into a picture of
 * a terminal. Measured on this stack: four frames in, one frame out.
 *
 * So an animation is decoded frame by frame through Imagick directly,
 * `coalesceImages()` first because an optimised GIF's frames are partial and
 * resizing them apart is how you get a smear. Every frame is resized and
 * stripped, and the lot is written back out as **animated WebP** - which is
 * both far smaller than GIF (measurably: 856 bytes against 1441 on a trivial
 * four-frame test, and the gap widens with real content) and understood by
 * every browser this site targets.
 *
 * Two things about that are worth not undoing:
 *
 * - **The output is verified, not assumed.** A libwebp built without its mux
 *   library writes a single frame and reports success, so the file is read
 *   back and its frames counted, and a flattened result falls back to animated
 *   GIF rather than being published as a still of something that moved.
 * - **A site with no Imagick refuses animations rather than flattening them.**
 *   GD cannot write an animated anything. Somebody who sends a GIF sent it
 *   because it moves, and quietly publishing the first frame answers a
 *   question they did not ask.
 *
 * ---------------------------------------------------------------------------
 *
 * The floor is as deliberate as the ceiling.
 *
 * A 320px-wide picture is not a screenshot of Mudlet, it is a crop of one or a
 * thumbnail somebody saved by mistake, and it will look like a mistake in a
 * gallery of 2560px ones. Refusing it at the form, with a sentence saying why,
 * is kinder than a reviewer rejecting it silently a week later.
 *
 * The pixel caps are not about disk. A decoder allocates the whole canvas
 * while it works - four bytes a pixel on GD, eight on a Q16 ImageMagick - so a
 * 20,000 x 20,000 PNG that compresses to 400KB asks for gigabytes of memory
 * and takes the site down with it. An animation multiplies that by its frame
 * count, which is why there are two budgets rather than one and why both are
 * checked from the file's header - `getimagesize()` and Imagick's `ping`, both
 * of which read metadata rather than pixels - before any decoder is handed the
 * file. That is the only place such a check can possibly go.
 *
 * @package Mudlet_Shots
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validation and normalisation for one uploaded file.
 */
class Mudlet_Shots_Image {

	/** What the accepted file is called inside its own directory. */
	const FILENAME = 'shot';

	/**
	 * The numbers, in one place, filterable as one array.
	 *
	 * A single filter rather than nine, because these are not nine independent
	 * decisions: raising `max_edge` without raising `max_pixels` gets a
	 * refusal that mentions neither.
	 *
	 * @return array<string, int>
	 */
	public static function limits(): array {
		/**
		 * Filter what the intake will accept and what it produces.
		 *
		 *   max_bytes    the upload itself, before anything is decoded
		 *   max_pixels   width x height, checked before a decoder sees it
		 *   max_edge     longest side of a submitted file
		 *   min_long     shortest acceptable long edge
		 *   min_short    shortest acceptable short edge
		 *   fit          what a stored still is scaled to fit
		 *   quality      the encoder's quality setting
		 *   fit_animated what a stored animation is scaled to fit
		 *   max_frames   how many frames an animation may have
		 *   frame_pixels frames x width x height, the decoder's memory budget
		 *
		 * @param array<string, int> $limits The numbers above.
		 */
		return (array) apply_filters(
			'mudlet_shots_limits',
			array(
				'max_bytes'    => 12 * MB_IN_BYTES,
				'max_pixels'   => 25000000,
				'max_edge'     => 8000,
				'min_long'     => 800,
				'min_short'    => 400,
				'fit'          => 2560,
				// 82 is where WebP stops being distinguishable from the PNG on
				// terminal text at 1:1, measured on the shots already on
				// /media/. Sharp glyphs on flat backgrounds are the hard case
				// for this encoder, and they are all this site gets.
				'quality'      => 82,
				// Half the stills' box. An animation is the same picture forty
				// times over, and the gallery draws it a few hundred pixels
				// wide - 2560 would be a download nobody watching a carousel
				// asked for. It is also the frame budget's other half: the same
				// ceiling costs four times less memory per frame.
				'fit_animated' => 1280,
				'max_frames'   => 120,
				'frame_pixels' => 60000000,
			)
		);
	}

	/**
	 * The image types a submission may arrive as.
	 *
	 * Not the types the site can store - that is decided below, and is one
	 * type in two flavours. These are what a person plausibly has to hand
	 * after pressing the screenshot key, or after recording a few seconds of
	 * one, on any of the three platforms.
	 *
	 * @return array<int, string> IMAGETYPE_* constant => label.
	 */
	public static function accepted(): array {
		return array(
			IMAGETYPE_PNG  => 'PNG',
			IMAGETYPE_JPEG => 'JPEG',
			IMAGETYPE_GIF  => 'GIF',
			IMAGETYPE_WEBP => 'WebP',
		);
	}

	/**
	 * Whether this site can keep an animation moving.
	 *
	 * GD cannot write an animated anything at all, and WordPress's own editor
	 * flattens one whichever library is behind it, so this is a question about
	 * Imagick specifically and about the two formats the animated path reads
	 * and writes.
	 *
	 * @return bool
	 */
	public static function animation_supported(): bool {
		static $can = null;

		if ( null === $can ) {
			$can = false;

			if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
				try {
					$formats = Imagick::queryFormats();
					$can     = in_array( 'GIF', $formats, true ) && in_array( 'WEBP', $formats, true );
				} catch ( Throwable $e ) {
					$can = false;
				}
			}
		}

		/**
		 * Filter whether animated submissions are accepted.
		 *
		 * False refuses them with a sentence saying so, which is the honest
		 * answer - the alternative is publishing the first frame of something
		 * that was sent because it moves.
		 *
		 * @param bool $can Whether this site can re-encode an animation.
		 */
		return (bool) apply_filters( 'mudlet_shots_animation', $can );
	}

	/**
	 * Take one uploaded file and leave a stored one in its place.
	 *
	 * The uploaded file is unlinked either way: on success because the stored
	 * file replaces it, and on refusal because there is nothing left to want
	 * it. A caller never has to remember to clean up after a WP_Error.
	 *
	 * @param string $tmp The uploaded file, wherever PHP put it.
	 * @param string $dir The submission's own directory, already made.
	 * @return array<string, mixed>|WP_Error file, mime, width, height, bytes, frames.
	 */
	public static function accept( string $tmp, string $dir ) {
		$limits = self::limits();

		if ( ! is_readable( $tmp ) ) {
			return self::fail( $tmp, 'mudlet_shot_gone', __( 'The upload did not arrive. Try again.', 'mudlet-shots' ) );
		}

		$bytes = (int) filesize( $tmp );
		if ( $bytes <= 0 || $bytes > $limits['max_bytes'] ) {
			return self::fail(
				$tmp,
				'mudlet_shot_big',
				sprintf(
					/* translators: %s: a file size, e.g. "12 MB" */
					__( 'That file is larger than %s. Screenshots are usually well under that.', 'mudlet-shots' ),
					size_format( $limits['max_bytes'] )
				)
			);
		}

		// The authority on what this file is. Not the extension, which the
		// visitor chose, and not the Content-Type, which the visitor's browser
		// was told by the visitor's operating system.
		$size = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
			return self::fail( $tmp, 'mudlet_shot_type', __( 'That does not look like an image.', 'mudlet-shots' ) );
		}

		$width  = (int) $size[0];
		$height = (int) $size[1];
		$type   = (int) ( $size[2] ?? 0 );

		if ( ! isset( self::accepted()[ $type ] ) ) {
			return self::fail(
				$tmp,
				'mudlet_shot_type',
				sprintf(
					/* translators: %s: a list of file formats, e.g. "PNG, JPEG, GIF, and WebP" */
					__( 'Screenshots have to be %s.', 'mudlet-shots' ),
					wp_sprintf_l( '%l', array_values( self::accepted() ) )
				)
			);
		}

		// Before any decoder is handed the file. See the file header: this is a
		// memory limit, not a disk one, and it is the only place it can be
		// enforced.
		if ( $width > $limits['max_edge'] || $height > $limits['max_edge'] || $width * $height > $limits['max_pixels'] ) {
			return self::fail( $tmp, 'mudlet_shot_huge', __( 'That image is far larger than a screen. Send the screenshot rather than a scan or a poster.', 'mudlet-shots' ) );
		}

		$long  = max( $width, $height );
		$short = min( $width, $height );

		if ( $long < $limits['min_long'] || $short < $limits['min_short'] ) {
			return self::fail(
				$tmp,
				'mudlet_shot_small',
				sprintf(
					/* translators: 1: minimum width in pixels, 2: minimum height in pixels */
					__( 'That is too small to show. Screenshots need to be at least %1$s by %2$s pixels.', 'mudlet-shots' ),
					number_format_i18n( $limits['min_long'] ),
					number_format_i18n( $limits['min_short'] )
				)
			);
		}

		// Which of the two paths. The byte scan only routes - the frame count
		// it implies is not trusted, and keep_moving() hands anything that
		// turns out to be a single frame back to the still path.
		if ( self::looks_animated( $tmp, $type ) ) {
			return self::keep_moving( $tmp, $dir, $width, $height, $bytes );
		}

		return self::flatten( $tmp, $dir, $width, $height, $bytes );
	}

	// -- the still path ------------------------------------------------

	/**
	 * One frame in, one frame out.
	 *
	 * @param string $tmp    The upload.
	 * @param string $dir    Where to write.
	 * @param int    $width  Measured width.
	 * @param int    $height Measured height.
	 * @param int    $bytes  Size of the upload.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function flatten( string $tmp, string $dir, int $width, int $height, int $bytes ) {
		$limits = self::limits();

		$editor = wp_get_image_editor( $tmp );
		if ( is_wp_error( $editor ) ) {
			return self::fail( $tmp, 'mudlet_shot_decode', __( 'That image could not be opened. Try saving it again as a PNG.', 'mudlet-shots' ) );
		}

		// Only when it is actually bigger: resize() answers with an error
		// rather than a no-op when asked to scale something up.
		if ( max( $width, $height ) > $limits['fit'] ) {
			$resized = $editor->resize( $limits['fit'], $limits['fit'], false );
			if ( is_wp_error( $resized ) ) {
				return self::fail( $tmp, 'mudlet_shot_resize', __( 'That image could not be resized.', 'mudlet-shots' ) );
			}
		}

		$mime = self::output_mime();
		$editor->set_quality( (int) $limits['quality'] );

		$saved = $editor->save( trailingslashit( $dir ) . self::FILENAME . self::extension( $mime ), $mime );
		if ( is_wp_error( $saved ) ) {
			return self::fail( $tmp, 'mudlet_shot_write', __( 'The site could not store that image.', 'mudlet-shots' ) );
		}

		// Its job is done, and it is the only copy of the original anybody
		// could have got at.
		wp_delete_file( $tmp );

		return array(
			'file'     => basename( (string) $saved['path'] ),
			'mime'     => (string) ( $saved['mime-type'] ?? $mime ),
			'width'    => (int) ( $saved['width'] ?? $width ),
			'height'   => (int) ( $saved['height'] ?? $height ),
			'bytes'    => (int) filesize( $saved['path'] ),
			'frames'   => 1,
			'animated' => false,
			// What came in, kept only so the review screen can say how much
			// was saved. The file it describes is already gone.
			'was'      => $bytes,
		);
	}

	// -- the animated path ---------------------------------------------

	/**
	 * Every frame in, every frame out.
	 *
	 * @param string $tmp    The upload.
	 * @param string $dir    Where to write.
	 * @param int    $width  Measured width.
	 * @param int    $height Measured height.
	 * @param int    $bytes  Size of the upload.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function keep_moving( string $tmp, string $dir, int $width, int $height, int $bytes ) {
		$limits = self::limits();

		if ( ! self::animation_supported() ) {
			return self::fail(
				$tmp,
				'mudlet_shot_animated',
				__( 'This site cannot take animated images at the moment. A still screenshot is welcome.', 'mudlet-shots' )
			);
		}

		// Imagick's ping reads the header and no pixels, which is what makes
		// it safe to ask an unvetted file how many frames it claims to have
		// before deciding whether to decode it.
		try {
			$probe = new Imagick();
			$probe->pingImage( $tmp );
			$frames = (int) $probe->getNumberImages();
			$probe->clear();
		} catch ( Throwable $e ) {
			return self::fail( $tmp, 'mudlet_shot_decode', __( 'That image could not be opened. Try saving it again.', 'mudlet-shots' ) );
		}

		// The scan said animated and the header says otherwise. One frame is a
		// still whatever its container, and the still path makes a smaller
		// file of it.
		if ( $frames < 2 ) {
			return self::flatten( $tmp, $dir, $width, $height, $bytes );
		}

		if ( $frames > (int) $limits['max_frames'] ) {
			return self::fail(
				$tmp,
				'mudlet_shot_frames',
				sprintf(
					/* translators: %s: a number of frames */
					__( 'That animation has more than %s frames. A few seconds of it would be plenty.', 'mudlet-shots' ),
					number_format_i18n( $limits['max_frames'] )
				)
			);
		}

		// The decoder's budget, from the header, before the decoder runs. See
		// the file header: coalescing holds every frame at full canvas size.
		if ( $frames * $width * $height > (int) $limits['frame_pixels'] ) {
			return self::fail(
				$tmp,
				'mudlet_shot_frames',
				__( 'That animation is too large to process — fewer frames, or a smaller picture.', 'mudlet-shots' )
			);
		}

		$target = (int) $limits['fit_animated'];
		$scale  = max( $width, $height ) > $target;
		$out    = trailingslashit( $dir ) . self::FILENAME;

		// Belt to the braces of the budget above: if this is wrong about how
		// much the decode costs, ImageMagick spills to disk rather than
		// exhausting PHP's memory and taking the request with it.
		try {
			Imagick::setResourceLimit( Imagick::RESOURCETYPE_MEMORY, 256 * MB_IN_BYTES );
			Imagick::setResourceLimit( Imagick::RESOURCETYPE_MAP, 512 * MB_IN_BYTES );
		} catch ( Throwable $e ) {
			// An Imagick without the constants. Not worth refusing over.
			unset( $e );
		}

		$written = self::write_frames( $tmp, $out . '.webp', 'webp', $scale, $target, $frames );

		// A libwebp with no mux library writes one frame and says it worked,
		// so the answer is read back rather than believed. GIF is the fallback
		// because it is the one animated format that cannot fail this way.
		if ( ! $written ) {
			$written = self::write_frames( $tmp, $out . '.gif', 'gif', $scale, $target, $frames );
		}

		if ( ! $written ) {
			return self::fail( $tmp, 'mudlet_shot_write', __( 'The site could not store that animation.', 'mudlet-shots' ) );
		}

		wp_delete_file( $tmp );

		$size = @getimagesize( $written['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		return array(
			'file'     => basename( $written['path'] ),
			'mime'     => $written['mime'],
			'width'    => (int) ( $size[0] ?? $width ),
			'height'   => (int) ( $size[1] ?? $height ),
			'bytes'    => (int) filesize( $written['path'] ),
			'frames'   => $written['frames'],
			'animated' => true,
			'was'      => $bytes,
		);
	}

	/**
	 * Decode every frame, resize it, and write the lot back out as one file.
	 *
	 * Returns false rather than throwing, and false includes "it wrote a file
	 * but the file has one frame in it" - which is the whole reason this is a
	 * function that can be called twice with two formats.
	 *
	 * @param string $src    The upload.
	 * @param string $dest   Where to write.
	 * @param string $format webp or gif.
	 * @param bool   $scale  Whether to resize.
	 * @param int    $target Box to fit inside.
	 * @param int    $frames How many frames the header promised.
	 * @return array<string, mixed>|false path, mime, frames.
	 */
	private static function write_frames( string $src, string $dest, string $format, bool $scale, int $target, int $frames ) {
		$limits = self::limits();

		try {
			$read = new Imagick();
			$read->readImage( $src );

			// An optimised GIF's frames are partial - each one paints only what
			// changed, over whatever the last one left behind. Resizing those
			// as they are smears the difference across the picture, so they are
			// flattened into whole frames first. This is the expensive step and
			// the reason for the budget in keep_moving().
			$loops = (int) $read->getImageIterations();
			$anim  = $read->coalesceImages();
			$read->clear();

			foreach ( $anim as $frame ) {
				if ( $scale ) {
					// One dimension given and the other zero: Imagick keeps
					// the aspect ratio, which is what the still path gets from
					// resize()'s $crop = false.
					$frame->resizeImage( $target, 0, Imagick::FILTER_LANCZOS, 1 );
				}
				// Profiles, comments, and anything else that came along.
				$frame->stripImage();
				$frame->setImageFormat( $format );
			}

			// GIF wants the frames put back into differences - that is most of
			// where its size goes. WebP does its own inter-frame work and is
			// measurably worse if handed pre-deconstructed input.
			if ( 'gif' === $format ) {
				$anim = $anim->deconstructImages();
			}

			$anim->setImageFormat( $format );
			$anim->setImageIterations( $loops );

			if ( 'webp' === $format ) {
				$anim->setImageCompressionQuality( (int) $limits['quality'] );
				$anim->setOption( 'webp:method', '4' );
			}

			// adjoin = true: one file holding every frame, rather than
			// shot-0.webp, shot-1.webp and so on.
			$anim->writeImages( $dest, true );
			$anim->clear();
		} catch ( Throwable $e ) {
			if ( file_exists( $dest ) ) {
				wp_delete_file( $dest );
			}
			return false;
		}

		if ( ! file_exists( $dest ) ) {
			return false;
		}

		// The verification the file header argues for.
		try {
			$back = new Imagick();
			$back->pingImage( $dest );
			$kept = (int) $back->getNumberImages();
			$back->clear();
		} catch ( Throwable $e ) {
			$kept = 0;
		}

		if ( $kept < 2 ) {
			wp_delete_file( $dest );
			return false;
		}

		return array(
			'path'   => $dest,
			'mime'   => 'webp' === $format ? 'image/webp' : 'image/gif',
			'frames' => min( $kept, $frames ),
		);
	}

	// -- reading the file's own claims ---------------------------------

	/**
	 * Whether a file looks like it moves.
	 *
	 * Bytes rather than a library, because this runs before the decision to
	 * decode and on a site that may have no Imagick to ask. Both answers are
	 * cheap and neither is trusted further than routing: an animation goes to
	 * a path that pings the header for the real count, and a still goes to the
	 * editor, which would flatten an animation this missed - so a false
	 * negative costs the animation and a false positive costs nothing.
	 *
	 * @param string $path The file.
	 * @param int    $type An IMAGETYPE_* constant.
	 * @return bool
	 */
	private static function looks_animated( string $path, int $type ): bool {
		if ( IMAGETYPE_WEBP === $type ) {
			// An animated WebP is an extended-format RIFF carrying an ANIM
			// chunk, which sits within the first few dozen bytes.
			$head = (string) file_get_contents( $path, false, null, 0, 64 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return false !== strpos( $head, 'ANIM' );
		}

		if ( IMAGETYPE_GIF !== $type ) {
			return false;
		}

		// A GIF frame is introduced by a Graphic Control Extension - 21 F9 04.
		// One of those is a still with a transparency or delay set on it; two
		// is an animation. Read in chunks with an overlap, because the marker
		// can straddle a boundary, and stop at the second one.
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $handle ) {
			return false;
		}

		$found = 0;
		$tail  = '';

		while ( ! feof( $handle ) ) {
			$chunk = (string) fread( $handle, 65536 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( '' === $chunk ) {
				break;
			}

			$found += (int) substr_count( $tail . $chunk, "\x21\xF9\x04" );
			if ( $found > 1 ) {
				break;
			}

			$tail = substr( $chunk, -2 );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return $found > 1;
	}

	// -- odds and ends -------------------------------------------------

	/**
	 * Refuse, and take the upload with us.
	 *
	 * @param string $tmp     The upload to unlink.
	 * @param string $code    Error code.
	 * @param string $message What the visitor is told.
	 * @return WP_Error
	 */
	private static function fail( string $tmp, string $code, string $message ): WP_Error {
		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}

		return new WP_Error( $code, $message, array( 'status' => 400 ) );
	}

	/**
	 * What to store a still as.
	 *
	 * WebP wherever the site can write it, which since WordPress 5.8 is most
	 * places; JPEG where it cannot, because a site running an image library
	 * that old should still be able to take a screenshot rather than refuse
	 * every one of them. PNG is deliberately not the fallback: it is what most
	 * of these arrive as, and re-encoding a screenshot to PNG saves nothing at
	 * all, which is the one thing this was asked to do.
	 *
	 * @return string A mime type.
	 */
	public static function output_mime(): string {
		return wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ? 'image/webp' : 'image/jpeg';
	}

	/**
	 * The extension for a mime type, dot included.
	 *
	 * @param string $mime Mime type.
	 * @return string
	 */
	private static function extension( string $mime ): string {
		return 'image/webp' === $mime ? '.webp' : '.jpg';
	}
}
