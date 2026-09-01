<?php
/**
 * The two shapes a release post is built out of, as core blocks.
 *
 * Mudlet's own announcements are Divi, and the parts of one that Gutenberg
 * cannot express out of the box turn out to be four: a feature section with a
 * screenshot beside the prose, a short card of headline items, a carousel of
 * screenshots, and a list of screencasts. Everything else in a 5.0-shaped post
 * is a heading, a paragraph, a list, or something the release plugin already
 * draws.
 *
 * The last two are what /media/ is built out of, and they are the reason that
 * page has no template: it is nothing but its own content. The gallery and the
 * screencast list are blocks in the page body, so somebody can write above
 * them, between them and after them without touching PHP - which is the whole
 * ask that page carries. The same two styles work in a release post, which is
 * most of the rest of the reason they are not a template.
 *
 * Both are registered here as a *block style* over a core block plus a pattern
 * to insert it with, rather than as blocks of the theme's own. That is a
 * deliberate choice and worth the paragraph:
 *
 *   - core/media-text already has the image and video pickers, the left/right
 *     swap and the stack-on-mobile behaviour. A custom block would reimplement
 *     three of those and get the fourth wrong.
 *   - a static custom block bakes its markup into every post that used it, and
 *     the next tweak to that markup invalidates all of them - the block
 *     recovery screen, on a five-year-old announcement.
 *   - nothing here holds data. A block earns its keep when it stores a
 *     reference the way mudlet/games stores slugs; these two store prose, which
 *     is what a paragraph is for.
 *
 * The look lives in assets/css/blocks.css, which is loaded on the page and,
 * unlike the generated stylesheet, in the editor as well - see inc/setup.php.
 * If the freedom this leaves an author turns out to be too much, both convert
 * to locked-down blocks without the front end changing at all.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'mudlet_register_block_styles' );
/**
 * Name the two looks, so they appear in the block's Styles panel.
 */
function mudlet_register_block_styles(): void {
	register_block_style(
		'core/media-text',
		array(
			'name'  => 'mudlet-feature',
			'label' => __( 'Feature section', 'mudlet' ),
		)
	);

	register_block_style(
		'core/group',
		array(
			'name'  => 'mudlet-highlights',
			'label' => __( 'Highlights', 'mudlet' ),
		)
	);

	// A gallery is already the right thing to *hold* screenshots - the picker,
	// the reordering, the captions and the media library are all core's. What
	// mudlet.org's own /media/ does differently is show them one at a time,
	// which is a way of displaying a gallery, not a different kind of content.
	// So: a style, and assets/js/theme.js turns it into the carousel. With the
	// script gone, or in the editor, it stays the grid core drew.
	register_block_style(
		'core/gallery',
		array(
			'name'  => 'mudlet-carousel',
			'label' => __( 'Screenshot carousel', 'mudlet' ),
		)
	);

	// The live page's screencast section is a Divi text module holding eight
	// links, each with a sentence under it. That is a list, and a block that
	// stored it would be storing prose. The style lays the same <ul> out as
	// cards; the item is <a>title</a> followed by the sentence, and both the
	// markup and the meaning survive the style being dropped.
	register_block_style(
		'core/list',
		array(
			'name'  => 'mudlet-screencasts',
			'label' => __( 'Screencasts', 'mudlet' ),
		)
	);
}

add_action( 'init', 'mudlet_register_block_patterns' );
/**
 * Both looks, prefilled, one click from the inserter.
 *
 * A style on its own is only findable by somebody who already knows to reach
 * for core/media-text and then open the Styles panel. The pattern is how an
 * author finds it while writing.
 */
function mudlet_register_block_patterns(): void {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category(
			'mudlet',
			array( 'label' => __( 'Mudlet', 'mudlet' ) )
		);
	}

	register_block_pattern(
		'mudlet/feature-section',
		array(
			'title'       => __( 'Feature section', 'mudlet' ),
			'description' => __( 'A heading and a paragraph with a screenshot or video beside them.', 'mudlet' ),
			'categories'  => array( 'mudlet' ),
			'keywords'    => array( __( 'release', 'mudlet' ), __( 'screenshot', 'mudlet' ) ),
			'content'     => '<!-- wp:media-text {"className":"is-style-mudlet-feature"} -->'
				. '<div class="wp-block-media-text alignwide is-stacked-on-mobile is-style-mudlet-feature">'
				. '<figure class="wp-block-media-text__media"></figure>'
				. '<div class="wp-block-media-text__content">'
				. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">'
				. esc_html__( 'A better first experience', 'mudlet' )
				. '</h3><!-- /wp:heading -->'
				. '<!-- wp:paragraph --><p>'
				. esc_html__( 'Say what changed and who it is for. One paragraph, and a screenshot that shows it rather than describing it.', 'mudlet' )
				. '</p><!-- /wp:paragraph -->'
				. '</div></div>'
				. '<!-- /wp:media-text -->',
		)
	);

	register_block_pattern(
		'mudlet/highlights',
		array(
			'title'       => __( 'Highlights', 'mudlet' ),
			'description' => __( 'A short card of headline items, for the top of a release post.', 'mudlet' ),
			'categories'  => array( 'mudlet' ),
			'keywords'    => array( __( 'release', 'mudlet' ), __( 'what is new', 'mudlet' ) ),
			'content'     => '<!-- wp:group {"className":"is-style-mudlet-highlights"} -->'
				. '<div class="wp-block-group is-style-mudlet-highlights">'
				. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">'
				. esc_html__( 'What’s new', 'mudlet' )
				. '</h3><!-- /wp:heading -->'
				. '<!-- wp:list --><ul class="wp-block-list">'
				. '<!-- wp:list-item --><li>' . esc_html__( 'the headline feature, in a few words', 'mudlet' ) . '</li><!-- /wp:list-item -->'
				. '<!-- wp:list-item --><li>' . esc_html__( 'the second one', 'mudlet' ) . '</li><!-- /wp:list-item -->'
				. '<!-- wp:list-item --><li>' . esc_html__( 'the third', 'mudlet' ) . '</li><!-- /wp:list-item -->'
				. '</ul><!-- /wp:list -->'
				. '</div>'
				. '<!-- /wp:group -->',
		)
	);

	// Deliberately an *empty* gallery. A pattern that arrived with pictures in
	// it would either ship images in this repo or hotlink somebody else's, and
	// the first thing an author does is delete them. Empty, core draws its own
	// media placeholder - "drag images, upload new ones or select files from
	// your library" - which is the instruction this pattern is for.
	register_block_pattern(
		'mudlet/screenshot-carousel',
		array(
			'title'       => __( 'Screenshot carousel', 'mudlet' ),
			'description' => __( 'A gallery shown one screenshot at a time, with arrows, dots and a lightbox.', 'mudlet' ),
			'categories'  => array( 'mudlet' ),
			'keywords'    => array( __( 'gallery', 'mudlet' ), __( 'screenshot', 'mudlet' ), __( 'slider', 'mudlet' ), __( 'carousel', 'mudlet' ) ),
			// linkTo media, so the no-script click still reaches the full image.
			// imageCrop off, so the gallery is uncropped before the script ever
			// runs: the editor preview, and the page with no script, both show
			// whole screenshots rather than tiles cut to a common shape.
			'content'     => '<!-- wp:gallery {"linkTo":"media","imageCrop":false,"className":"is-style-mudlet-carousel"} -->'
				. '<figure class="wp-block-gallery has-nested-images columns-default is-style-mudlet-carousel">'
				. '</figure>'
				. '<!-- /wp:gallery -->',
		)
	);

	register_block_pattern(
		'mudlet/screencasts',
		array(
			'title'       => __( 'Screencast list', 'mudlet' ),
			'description' => __( 'A list of videos, each a link and the sentence that says what it covers.', 'mudlet' ),
			'categories'  => array( 'mudlet' ),
			'keywords'    => array( __( 'video', 'mudlet' ), __( 'screencast', 'mudlet' ), __( 'tutorial', 'mudlet' ) ),
			'content'     => '<!-- wp:list {"className":"is-style-mudlet-screencasts"} -->'
				. '<ul class="wp-block-list is-style-mudlet-screencasts">'
				. '<!-- wp:list-item --><li><a href="https://www.youtube.com/">'
				. esc_html__( 'What the video is called', 'mudlet' )
				. '</a> ' . esc_html__( 'One sentence on what it covers, so somebody can tell whether it is the one they want.', 'mudlet' )
				. '</li><!-- /wp:list-item -->'
				. '<!-- wp:list-item --><li><a href="https://www.youtube.com/">'
				. esc_html__( 'The next one', 'mudlet' )
				. '</a> ' . esc_html__( 'And what that one covers.', 'mudlet' )
				. '</li><!-- /wp:list-item -->'
				. '</ul><!-- /wp:list -->',
		)
	);
}
