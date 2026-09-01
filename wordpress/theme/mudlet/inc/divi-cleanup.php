<?php
/**
 * Make orphaned shortcodes degrade to their contents.
 *
 * A site that ran Divi for years leaves shortcodes behind in post bodies. With
 * the plugin gone nothing registers them, so WordPress prints them verbatim and
 * the reader gets "[et_pb_text]" in the middle of a release announcement.
 *
 * Eight imported bodies still carry Divi shortcodes, and this is what keeps
 * them off the page until they are dealt with properly. It covers the other
 * plugins too - Bloom, Shortcoder, WP-DownloadManager, the reCAPTCHA tags
 * inside Contact Form 7 bodies.
 *
 * Unregistered shortcodes are stripped tag-by-tag rather than with
 * strip_shortcodes(), which would delete the wrapped content along with the
 * wrapper - and in a Divi post the wrapped content is the entire article.
 *
 * This is a display-time repair, not a migration: the database still holds the
 * original text either way. It is cheap enough to run on every post (a single
 * regex over content that already went through the rest of the_content) and it
 * means one badly-migrated post never shows brackets to a visitor.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode name prefixes left behind by plugins this site no longer runs.
 *
 * @return string[]
 */
function mudlet_dead_shortcodes(): array {
	/**
	 * Filter the shortcode prefixes stripped from post content.
	 *
	 * @param string[] $prefixes Shortcode name prefixes.
	 */
	return apply_filters(
		'mudlet_dead_shortcodes',
		array(
			'et_pb_',      // Divi's page builder
			'et_bloom',    // Bloom, Elegant Themes' opt-in plugin
			'shortcoder',  // the Shortcoder plugin
			'dlm_',        // WP-DownloadManager
			'recaptcha',   // left inside Contact Form 7 bodies
		)
	);
}

add_filter( 'the_content', 'mudlet_strip_dead_shortcodes', 9 );
add_filter( 'the_excerpt', 'mudlet_strip_dead_shortcodes', 9 );
add_filter( 'get_the_excerpt', 'mudlet_strip_dead_shortcodes', 9 );
/**
 * Remove dead shortcode tags, keeping whatever they wrapped.
 *
 * Runs at priority 9 - before wpautop and before do_shortcode - so the tags are
 * gone before anything tries to make paragraphs out of them, and a shortcode
 * this site *does* still register is never touched.
 *
 * @param string $content Post content.
 * @return string
 */
function mudlet_strip_dead_shortcodes( $content ): string {
	$content = (string) $content;

	if ( '' === $content || ! str_contains( $content, '[' ) ) {
		return $content;
	}

	$prefixes = mudlet_dead_shortcodes();
	if ( ! $prefixes ) {
		return $content;
	}

	$alternation = implode( '|', array_map( 'preg_quote', $prefixes ) );

	// Opening tags with any attributes, closing tags, and self-closing ones.
	// [^\]]* rather than . so a stray bracket cannot swallow the article.
	$pattern = '/\[\/?(?:' . $alternation . ')[a-z0-9_]*(?:\s[^\]]*)?\/?\]/i';

	$stripped = preg_replace( $pattern, '', $content );

	// preg_replace returns null on failure (a pathological body hitting the
	// backtrack limit); the original is a better answer than an empty post.
	return is_string( $stripped ) ? $stripped : $content;
}
