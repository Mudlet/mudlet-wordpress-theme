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
 * plugins too - Bloom, Shortcoder (`[sc]`), WP-DownloadManager, the reCAPTCHA tags
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
			'shortcoder',  // the Shortcoder plugin's own [shortcoder] form
			'dlm_',        // WP-DownloadManager
			'recaptcha',   // left inside Contact Form 7 bodies
		)
	);
}

/**
 * Whole shortcode names left behind, for the ones too short to be a prefix.
 *
 * Shortcoder's everyday tag is `[sc name="..."]`, and `sc` as a prefix would
 * also take `[screenshot]`, `[scroll]` or anything else a later plugin names
 * that way. So these match the name exactly and nothing that starts with it.
 *
 * @return string[]
 */
function mudlet_dead_shortcode_names(): array {
	/**
	 * Filter the exact shortcode names stripped from post content.
	 *
	 * @param string[] $names Shortcode names.
	 */
	return apply_filters(
		'mudlet_dead_shortcode_names',
		array(
			'sc', // Shortcoder: [sc name="download_link_by_email"] on the old download pages
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
	$names    = mudlet_dead_shortcode_names();
	if ( ! $prefixes && ! $names ) {
		return $content;
	}

	$quote = static function ( string $s ): string {
		return preg_quote( $s, '/' );
	};

	$alternation = array();
	if ( $prefixes ) {
		$alternation[] = '(?:' . implode( '|', array_map( $quote, $prefixes ) ) . ')[a-z0-9_]*';
	}
	if ( $names ) {
		$alternation[] = '(?:' . implode( '|', array_map( $quote, $names ) ) . ')';
	}

	// Opening tags with any attributes, closing tags, and self-closing ones.
	// [^\]]* rather than . so a stray bracket cannot swallow the article. The
	// lookahead is what makes a name exact: after it comes the end of the tag
	// or its attributes, never more name - and a prefix has already eaten the
	// rest of its name by then, so it asks nothing new of those.
	$pattern = '/\[\/?(?:' . implode( '|', $alternation ) . ')(?=[\s\/\]])(?:\s[^\]]*)?\/?\]/i';

	$stripped = preg_replace( $pattern, '', $content );

	// preg_replace returns null on failure (a pathological body hitting the
	// backtrack limit); the original is a better answer than an empty post.
	return is_string( $stripped ) ? $stripped : $content;
}
