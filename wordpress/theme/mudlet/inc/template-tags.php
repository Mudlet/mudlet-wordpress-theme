<?php
/**
 * Small helpers the templates lean on.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Which of the prototype's four views this request is.
 *
 * The stylesheet routes on a data-page attribute because the prototype carried
 * every view in one document. Here each view is its own request, but keeping
 * the attribute means the CSS transfers with no edits at all - and re-running
 * the sync tool stays a no-op rather than a merge.
 *
 * @return string One of home, dl, news, post, page.
 */
function mudlet_page_kind(): string {
	if ( is_front_page() ) {
		return 'home';
	}
	if ( is_singular( 'post' ) ) {
		return 'post';
	}
	// A post-type archive is not the news index. /games/ draws a .page--page,
	// the way /the-makers/ and the legal pages do, and routing it to 'news'
	// left it hidden: the stylesheet shows only the .page that data-page names,
	// so every card was in the DOM at zero height and the page looked empty.
	// No argument, because 'post' has no post-type archive - that is is_home().
	if ( is_post_type_archive() ) {
		return 'page';
	}
	if ( is_home() || is_archive() || is_search() ) {
		return 'news';
	}
	if ( is_page() && is_page_template( 'page-download.php' ) ) {
		return 'dl';
	}
	if ( is_page( 'download' ) ) {
		return 'dl';
	}
	return 'page';
}

/**
 * The permalink of a page by slug, falling back to a plain path.
 *
 * The templates link to /download/ and /news/ in a dozen places. Looking the
 * page up means those links survive an editor renaming or re-parenting it;
 * falling back to the path means a fresh install with no pages yet still
 * renders something clickable rather than a link to nowhere.
 *
 * @param string $slug Page slug.
 * @param string $path Fallback path, relative to the site root.
 * @return string
 */
function mudlet_page_url( string $slug, string $path ): string {
	$page = get_page_by_path( $slug );
	if ( $page instanceof WP_Post ) {
		$url = get_permalink( $page );
		if ( $url ) {
			return $url;
		}
	}
	return home_url( $path );
}

/**
 * Where "Get Mudlet" goes.
 *
 * @return string
 */
function mudlet_download_url(): string {
	return mudlet_page_url( 'download', '/download/' );
}

/**
 * Where the news index lives - the posts page if one is set, else /news/.
 *
 * @return string
 */
function mudlet_news_url(): string {
	$posts_page = (int) get_option( 'page_for_posts' );
	if ( $posts_page ) {
		$url = get_permalink( $posts_page );
		if ( $url ) {
			return $url;
		}
	}
	return home_url( '/news/' );
}

/**
 * The post's primary category, as the term object the pills key off.
 *
 * @param WP_Post|int|null $post Post.
 * @return WP_Term|null
 */
function mudlet_primary_category( $post = null ): ?WP_Term {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return null;
	}
	$terms = get_the_category( $post->ID );
	return $terms ? $terms[0] : null;
}

/**
 * Reduce a category slug to one of the design's colour families.
 *
 * The stylesheet colours pills off data-cat="release|informational|press", but
 * the live site's categories are per-language and their slugs show it: the
 * English release category is `release-en`, the German one `release-de-de`, the
 * Chinese `release-zh-zh`. Matching on the leading word gives every translation
 * of a category the colour its English twin has, without the theme having to
 * know Polylang is involved.
 *
 * Anything unrecognised returns '' and the pill falls back to its neutral dot,
 * which is the right answer for a category the design never anticipated.
 *
 * @param string $slug Term slug.
 * @return string One of release, informational, press, or ''.
 */
function mudlet_category_family( string $slug ): string {
	$families = array( 'release', 'informational', 'press' );

	foreach ( $families as $family ) {
		if ( $slug === $family || str_starts_with( $slug, $family . '-' ) ) {
			return $family;
		}
	}

	/**
	 * Filter the colour family a category slug maps to.
	 *
	 * @param string $family One of release, informational, press, or ''.
	 * @param string $slug   The term slug being mapped.
	 */
	return apply_filters( 'mudlet_category_family', '', $slug );
}

/**
 * Print the coloured category pill.
 *
 * @param WP_Post|int|null $post  Post.
 * @param string           $class Element class - tagpill on lists, tag on cards.
 */
function mudlet_category_pill( $post = null, string $class = 'tagpill' ): void {
	$term = mudlet_primary_category( $post );
	if ( ! $term ) {
		return;
	}
	printf(
		'<span class="%1$s" data-cat="%2$s">%3$s</span>',
		esc_attr( $class ),
		esc_attr( mudlet_category_family( $term->slug ) ),
		esc_html( $term->name )
	);
}

/**
 * Initials for the byline avatar, which is a two-letter box rather than a photo.
 *
 * @param int|null $user_id Author.
 * @return string
 */
function mudlet_author_initials( ?int $user_id = null ): string {
	$name  = get_the_author_meta( 'display_name', $user_id ?? (int) get_post_field( 'post_author' ) );
	$parts = preg_split( '/\s+/', trim( (string) $name ) ) ?: array();
	$out   = '';
	foreach ( array_slice( $parts, 0, 2 ) as $part ) {
		$out .= mb_strtoupper( mb_substr( $part, 0, 1 ) );
	}
	return $out !== '' ? $out : '?';
}

/**
 * Category counts for the news rail, newest-first and excluding empties.
 *
 * @return WP_Term[]
 */
function mudlet_news_categories(): array {
	$terms = get_categories( array( 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
	return is_array( $terms ) ? $terms : array();
}

/**
 * Years that actually have posts, for the archive jump.
 *
 * @return string[]
 */
function mudlet_archive_years(): array {
	global $wpdb;
	$cached = get_transient( 'mudlet_archive_years' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$years = $wpdb->get_col(
		"SELECT DISTINCT YEAR(post_date) FROM {$wpdb->posts}
		 WHERE post_type = 'post' AND post_status = 'publish'
		 ORDER BY post_date DESC"
	);
	$years = array_map( 'strval', (array) $years );
	set_transient( 'mudlet_archive_years', $years, DAY_IN_SECONDS );
	return $years;
}

/**
 * Give a post's h2s ids and hand back the list, for the "In this post" nav.
 *
 * The prototype hand-wrote both the headings and the outline over them, which
 * only works when there is one post. Deriving the outline from the content
 * means it cannot go stale, and stamping the ids in the same pass means the
 * anchors always match - an outline whose links 404 into the middle of the page
 * is worse than no outline.
 *
 * Headings that already carry an id keep it, so an editor can pin one.
 *
 * @param string $html Rendered post content.
 * @return array{0:string,1:array<int, array{id:string,text:string}>}
 */
function mudlet_outline( string $html ): array {
	$headings = array();
	$seen     = array();

	$out = preg_replace_callback(
		'/<h2(?P<attrs>[^>]*)>(?P<text>.*?)<\/h2>/is',
		static function ( array $m ) use ( &$headings, &$seen ): string {
			$text = trim( wp_strip_all_tags( $m['text'] ) );
			if ( '' === $text ) {
				return $m[0];
			}

			if ( preg_match( '/\bid=["\']([^"\']+)["\']/', $m['attrs'], $has ) ) {
				$id = $has[1];
			} else {
				$id = sanitize_title( $text );
				// sanitize_title returns '' for headings that are all
				// punctuation or non-Latin script with no transliteration.
				if ( '' === $id ) {
					$id = 'section';
				}
				if ( isset( $seen[ $id ] ) ) {
					$id .= '-' . ( ++$seen[ $id ] );
				} else {
					$seen[ $id ] = 1;
				}
			}

			$headings[] = array( 'id' => $id, 'text' => $text );

			return preg_match( '/\bid=/', $m['attrs'] )
				? $m[0]
				: '<h2' . $m['attrs'] . ' id="' . esc_attr( $id ) . '">' . $m['text'] . '</h2>';
		},
		$html
	);

	return array( is_string( $out ) ? $out : $html, $headings );
}

/**
 * Print the pagination the prototype draws as .pager.
 *
 * paginate_links does the arithmetic; the markup it returns is already the
 * shape the stylesheet wants (a run of <a> with aria-current on the current
 * one), so this only has to supply the arrows and the count.
 */
function mudlet_pager(): void {
	global $wp_query;
	if ( $wp_query->max_num_pages < 2 ) {
		return;
	}

	$per   = (int) get_query_var( 'posts_per_page' );
	$page  = max( 1, (int) get_query_var( 'paged' ) );
	$total = (int) $wp_query->found_posts;
	$from  = ( ( $page - 1 ) * $per ) + 1;
	$to    = min( $total, $page * $per );

	$links = paginate_links(
		array(
			'type'      => 'array',
			'mid_size'  => 2,
			'end_size'  => 1,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
		)
	);
	if ( ! $links ) {
		return;
	}
	?>
	<nav class="pager" aria-label="<?php esc_attr_e( 'Pagination', 'mudlet' ); ?>">
		<span class="count">
			<?php
			printf(
				/* translators: 1: first post on this page, 2: last post on this page, 3: total posts */
				esc_html__( 'Showing %1$s–%2$s of %3$s', 'mudlet' ),
				esc_html( number_format_i18n( $from ) ),
				esc_html( number_format_i18n( $to ) ),
				esc_html( number_format_i18n( $total ) )
			);
			?>
		</span>
		<?php
		foreach ( $links as $link ) {
			// paginate_links returns its own markup and its own class
			// vocabulary - page-numbers, current, dots, prev, next. wp.css maps
			// those onto the design rather than rewriting them here, so this
			// keeps working if WordPress adds to the set.
			echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
	</nav>
	<?php
}
