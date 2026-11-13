<?php
/**
 * Accessibility repairs to imported post bodies, at display time.
 *
 * Eighteen years of posts came over from the old site carrying markup that
 * fails WCAG in ways nobody writing them could have seen. That was a light
 * theme with no dark mode, and it was never audited. Measured against the
 * import, the failures are:
 *
 * - **237 links whose only content is an image with `alt=""`.** A screen
 *   reader announces each one as "link" and nothing else. 230 of them open the
 *   full-size image and the other 7 are logos linking to a game's site.
 * - **Inline `color:` from the old palette, in 7 bodies.** `#333333` headings
 *   and `#910000` emphasis read fine on cream, and at 1.5:1 and 1.9:1 they are
 *   all but invisible on the dark theme.
 * - **Iframes with no `title`**, which leaves a screen reader announcing
 *   "frame" and nothing else.
 * - **Links with no content at all**, left behind by an editor. They still
 *   take a Tab stop and land the reader on nothing.
 *
 * Same contract as divi-cleanup.php: the database keeps the original text, the
 * page gets the repair, and a post fixed by hand passes through untouched. No
 * alt text is invented here. A link with nothing describing its image is named
 * after what it does ("full-size image", or the host it goes to), and that is
 * true whatever the picture shows. Writing real alt text for those 230 images
 * is editorial work, and the media library is where it gets done.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Run every repair over one body.
 *
 * Priority 20 puts it after wpautop (10) and do_shortcode (11), so it sees the
 * same HTML the browser will, embeds and shortcodes included.
 *
 * @param string $html Post content.
 * @return string
 */
function mudlet_content_a11y( string $html ): string {
	if ( '' === $html || is_admin() || is_feed() ) {
		return $html;
	}
	$html = mudlet_a11y_empty_links( $html );
	$html = mudlet_a11y_image_links( $html );
	$html = mudlet_a11y_inline_color( $html );
	$html = mudlet_a11y_iframe_titles( $html );
	return $html;
}
add_filter( 'the_content', 'mudlet_content_a11y', 20 );

/**
 * Drop links that have an href and nothing inside them.
 *
 * Anchors without an href (`<a id>`, `<a name>`) are jump targets rather than
 * links, so they are left alone.
 *
 * @param string $html Post content.
 * @return string
 */
function mudlet_a11y_empty_links( string $html ): string {
	return (string) preg_replace( '#<a\s(?=[^>]*\bhref=)[^>]*>(?:\s|&nbsp;)*</a>#i', '', $html );
}

/**
 * Give a link a name when its only content is an image with an empty alt, or
 * with no alt at all.
 *
 * An image outside a link with no alt is left as it is. Adding `alt=""` would
 * pass a checker by declaring the picture decorative, and these are screenshots
 * of features, so that would hide them from the very readers it is meant for.
 *
 * The alt goes on the image, so the name is the picture's: first its `title`
 * attribute, then the attachment's own alt (set in the media library after the
 * post was written). If neither exists, the link is labelled with what it does.
 *
 * @param string $html Post content.
 * @return string
 */
function mudlet_a11y_image_links( string $html ): string {
	return (string) preg_replace_callback(
		'#(<a\s[^>]*>)(\s*)(<img\s[^>]*>)(\s*</a>)#i',
		static function ( array $m ): string {
			list( , $open, $gap, $img, $close ) = $m;
			$no_alt = ! preg_match( '/\salt=/i', $img );
			if ( ( ! $no_alt && ! preg_match( '/\salt=(["\'])\s*\1/i', $img ) ) || preg_match( '/\saria-label(?:ledby)?=/i', $open ) ) {
				return $m[0];
			}
			if ( $no_alt ) {
				// Read as alt="" from here on, so both cases take one path.
				$img = preg_replace( '/^<img\s/i', '<img alt="" ', $img, 1 );
			}

			$alt = '';
			if ( preg_match( '/\stitle=(["\'])(.+?)\1/i', $img, $t ) ) {
				$alt = html_entity_decode( $t[2], ENT_QUOTES, 'UTF-8' );
			}
			if ( '' === trim( $alt ) && preg_match( '/\bwp-image-(\d+)\b/', $img, $id ) ) {
				$alt = (string) get_post_meta( (int) $id[1], '_wp_attachment_image_alt', true );
			}
			if ( '' !== trim( $alt ) ) {
				$img = preg_replace( '/\salt=(["\'])\s*\1/i', ' alt="' . esc_attr( trim( $alt ) ) . '"', $img, 1 );
				return $open . $gap . $img . $close;
			}

			// Nothing describes the picture, so name the link by where it goes.
			$href  = preg_match( '/\shref=(["\'])(.*?)\1/i', $open, $h ) ? html_entity_decode( $h[2], ENT_QUOTES, 'UTF-8' ) : '';
			$path  = (string) wp_parse_url( $href, PHP_URL_PATH );
			$label = preg_match( '/\.(png|jpe?g|gif|webp|avif|bmp)$/i', $path )
				? __( 'Full-size image', 'mudlet' )
				: preg_replace( '/^www\./', '', (string) wp_parse_url( $href, PHP_URL_HOST ) );
			if ( '' === $label ) {
				return $m[0];
			}
			$open = preg_replace( '/^<a\s/i', '<a aria-label="' . esc_attr( $label ) . '" ', $open, 1 );
			return $open . $gap . $img . $close;
		},
		$html
	);
}

/**
 * Strip text colours the old theme's palette put inline.
 *
 * They were picked for one cream page and cannot follow the theme into dark,
 * where `#333333` on `#17130e` is 1.5:1. Text then takes the colour of the
 * element it is in, which is what the theme drew it for. A style that sets a
 * background as well keeps both declarations: that pair was chosen together,
 * and taking half of it away is how white-on-white happens.
 *
 * One post hid a spacer with white text, `<span style="color:#fff">.</span>`.
 * Without the colour that is a stray full stop, so a white span holding only
 * punctuation is removed rather than recoloured.
 *
 * @param string $html Post content.
 * @return string
 */
function mudlet_a11y_inline_color( string $html ): string {
	if ( false === stripos( $html, 'color' ) ) {
		return $html;
	}
	$html = (string) preg_replace(
		'#<span\s[^>]*style=(["\'])\s*color\s*:\s*(?:\#fff(?:fff)?|white)\s*;?\s*\1[^>]*>(?:\s|&nbsp;|[.,;:·])*</span>#iu',
		'',
		$html
	);
	return (string) preg_replace_callback(
		'#\sstyle=(["\'])(.*?)\1#is',
		static function ( array $m ): string {
			$style = $m[2];
			if ( ! preg_match( '/(?<![\w-])color\s*:/i', $style ) || preg_match( '/background/i', $style ) ) {
				return $m[0];
			}
			$style = trim( (string) preg_replace( '/(?<![\w-])color\s*:[^;]*;?/i', '', $style ) );
			return '' === $style ? '' : ' style=' . $m[1] . $style . $m[1];
		},
		$html
	);
}

/**
 * Title the iframes a body embeds.
 *
 * Embeds WordPress itself makes (oEmbed) already carry one, so this only
 * reaches iframes pasted in as raw HTML. Known hosts get a real name, and
 * anything else is named after its host, which beats "frame".
 *
 * @param string $html Post content.
 * @return string
 */
function mudlet_a11y_iframe_titles( string $html ): string {
	if ( false === stripos( $html, '<iframe' ) ) {
		return $html;
	}
	return (string) preg_replace_callback(
		'#<iframe\s[^>]*>#i',
		static function ( array $m ): string {
			$tag = $m[0];
			if ( preg_match( '/\s(?:title|aria-label)=(["\'])\s*\S/i', $tag ) ) {
				return $tag;
			}
			$src  = preg_match( '/\ssrc=(["\'])(.*?)\1/i', $tag, $s ) ? html_entity_decode( $s[2], ENT_QUOTES, 'UTF-8' ) : '';
			$host = preg_replace( '/^www\./', '', (string) wp_parse_url( $src, PHP_URL_HOST ) );
			$known = array(
				'ghbtns.com'                => __( 'Mudlet on GitHub: star count', 'mudlet' ),
				'discord.com'               => __( 'Mudlet Discord server', 'mudlet' ),
				'discordapp.com'            => __( 'Mudlet Discord server', 'mudlet' ),
				'youtube.com'               => __( 'YouTube video', 'mudlet' ),
				'youtube-nocookie.com'      => __( 'YouTube video', 'mudlet' ),
				'player.vimeo.com'          => __( 'Vimeo video', 'mudlet' ),
			);
			$title = $known[ $host ] ?? $host;
			if ( '' === $title ) {
				return $tag;
			}
			return preg_replace( '/^<iframe\s/i', '<iframe title="' . esc_attr( $title ) . '" ', $tag, 1 );
		},
		$html
	);
}
