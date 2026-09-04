<?php
/**
 * Styles, scripts, and the data the scripts need from PHP.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'mudlet_assets' );
/**
 * Enqueue the front-end bundle.
 */
function mudlet_assets(): void {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();

	wp_enqueue_style( 'mudlet-fonts', mudlet_fonts_url(), array(), null );

	wp_enqueue_style( 'mudlet-theme', $uri . '/assets/css/theme.css', array( 'mudlet-fonts' ), mudlet_asset_version( $dir . '/assets/css/theme.css' ) );
	wp_enqueue_style( 'mudlet-wp', $uri . '/assets/css/wp.css', array( 'mudlet-theme' ), mudlet_asset_version( $dir . '/assets/css/wp.css' ) );

	// The block components. Last, and also handed to the editor by inc/setup.php
	// - it is the one stylesheet both sides load, which is why it is written
	// without #site. See its header.
	wp_enqueue_style( 'mudlet-blocks', $uri . '/assets/css/blocks.css', array( 'mudlet-wp' ), mudlet_asset_version( $dir . '/assets/css/blocks.css' ) );

	wp_enqueue_script( 'mudlet-theme', $uri . '/assets/js/theme.js', array(), mudlet_asset_version( $dir . '/assets/js/theme.js' ), true );
	wp_localize_script( 'mudlet-theme', 'MUDLET', mudlet_script_data() );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}

/**
 * The webfonts.
 *
 * Two families, one weight range each. A site that would rather not call out to
 * Google can drop the files into assets/fonts/ and filter this.
 *
 * A function rather than a string in two places: the block editor asks for the
 * same sheet through add_editor_style() in inc/setup.php, and a canvas set in
 * the wrong face is a preview that lies about the page.
 *
 * @return string
 */
function mudlet_fonts_url(): string {
	/**
	 * Filter the webfont stylesheet URL.
	 *
	 * @param string $url Stylesheet URL.
	 */
	return (string) apply_filters(
		'mudlet_fonts_url',
		'https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap'
	);
}

/**
 * Cache-bust on file mtime rather than the theme version.
 *
 * The theme version moves once a release; these files move every time somebody
 * re-runs the sync tool, and a stale stylesheet is the kind of bug that eats an
 * afternoon before anyone thinks to hard-refresh.
 *
 * @param string $path Absolute path to the asset.
 * @return string
 */
function mudlet_asset_version( string $path ): string {
	return file_exists( $path ) ? (string) filemtime( $path ) : MUDLET_VERSION;
}

/**
 * Everything theme.js would otherwise have to hard-code.
 *
 * @return array<string, mixed>
 */
function mudlet_script_data(): array {
	return array(
		'demoSrc'   => mudlet_demo_src(),
		'downloads' => mudlet_download_script_data(),
		// Two halves of one box: the inline index is what the palette draws on
		// the keystroke, the route is what it asks a moment later. See
		// inc/search.php.
		'search'    => mudlet_search_index(),
		'searchUrl' => esc_url_raw( rest_url( 'mudlet/v1/search' ) ),
		// The manual, asked in parallel with the site and drawn under it. Empty
		// when the wiki has been switched off, which is how the palette knows
		// not to ask - see inc/wiki-search.php.
		'searchWikiUrl' => mudlet_wiki_search_enabled() ? esc_url_raw( rest_url( 'mudlet/v1/search/wiki' ) ) : '',
		// A REST request is not inside the language's URLs, so the language
		// travels with the question. Empty without Polylang.
		'searchLang' => mudlet_current_language_slug(),
		'strings'   => array(
			'lightTheme' => __( 'Switch to light theme', 'mudlet' ),
			'darkTheme'  => __( 'Switch to dark theme', 'mudlet' ),
			'copied'     => __( 'copied', 'mudlet' ),
			// Held in the palette's empty slot while the route is being asked,
			// so a query the inline titles miss does not read "No matches."
			// for as long as the network takes.
			'searching'  => __( 'Searching…', 'mudlet' ),
			// The last row, when the count says the eight above it are not all
			// of them. Only ever plural: it is drawn when there are more
			// results than rows.
			/* translators: %s: total number of results */
			'searchAll'  => __( 'See all %s results', 'mudlet' ),
			'searchSrc'  => __( 'Search', 'mudlet' ),
			// The end of the wiki's own block, and the only way from the
			// palette to the wiki's search. Never a count: MediaWiki's REST
			// search answers rows and no total, and a number worked out here
			// would be a guess printed as a fact. Same words as the chip under
			// the block on the results page, because it is the same offer.
			'searchWikiAll' => __( 'Search the wiki', 'mudlet' ),
			'searchWikiSrc' => __( 'Wiki', 'mudlet' ),
			/* translators: %s: number of games currently matching the filter */
			'gamesShown' => __( '%s shown', 'mudlet' ),
			// The screenshot carousel and its lightbox. Every control on both is
			// built in the browser, so this is the only place their labels can
			// be translated from.
			'galLabel'   => __( 'Screenshots', 'mudlet' ),
			'galPrev'    => __( 'Previous screenshot', 'mudlet' ),
			'galNext'    => __( 'Next screenshot', 'mudlet' ),
			/* translators: %s: position of a screenshot in the gallery */
			'galGo'      => __( 'Screenshot %s', 'mudlet' ),
			'galClose'   => __( 'Close', 'mudlet' ),
			'galCasts'   => __( 'Screencasts', 'mudlet' ),
			'galWatch'   => __( 'Watch on YouTube', 'mudlet' ),
			/* translators: 1: position of the screenshot shown, 2: how many there are */
			'galCount'   => __( '%1$s / %2$s', 'mudlet' ),
		),
	);
}

/**
 * Where the hero's embedded Mudlet Web build lives.
 *
 * Same-origin is a hard requirement, not a preference: Mudlet Web keeps each
 * profile in IndexedDB, which Safari and Firefox deny to cross-origin frames,
 * and its VFS service worker needs a secure context. Serving the client out of
 * the theme keeps it on the site's own origin for free.
 *
 * Returns '' when the build has not been copied in, which leaves the hero on
 * its scripted session rather than pointing an iframe at a 404.
 *
 * @return string
 */
function mudlet_demo_src(): string {
	$path = get_template_directory() . '/assets/demo/index.html';
	return file_exists( $path ) ? get_template_directory_uri() . '/assets/demo/index.html' : '';
}
