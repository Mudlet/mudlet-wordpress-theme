<?php
/**
 * Theme supports, menus, and the handful of WordPress defaults worth changing.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'mudlet_setup' );
/**
 * Register theme support and navigation locations.
 */
function mudlet_setup(): void {
	load_theme_textdomain( 'mudlet', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );

	// The design is a fixed palette in two themes, not a configurable one.
	// A colour picker here would let an editor break the contrast ratios the
	// stylesheet works hard to document.
	add_theme_support( 'disable-custom-colors' );
	add_theme_support( 'disable-custom-font-sizes' );

	// A feature panel wants to be wider than the measure. wp.css has styled
	// .alignwide and .alignfull since it was written, but without this the
	// editor never offers them, so the only way to reach those classes was to
	// type them into the HTML by hand.
	add_theme_support( 'align-wide' );

	// Let the editor canvas load a stylesheet, and give it the two that make a
	// game card and a feature panel look like themselves while being written.
	// Not theme.css: it is generated, and every rule in it is scoped to #site,
	// which the canvas does not have. See assets/css/editor.css.
	add_theme_support( 'editor-styles' );
	add_editor_style( array( mudlet_fonts_url(), 'assets/css/blocks.css', 'assets/css/editor.css' ) );

	// Only two locations are menus. The footer's other three columns point at
	// fixed destinations - the wiki, the forum, GitHub - and each link carries
	// its own icon, which a menu item has nowhere to put. They live in
	// footer.php as markup, which is what they are.
	register_nav_menus(
		array(
			'primary'        => __( 'Header', 'mudlet' ),
			'footer-project' => __( 'Footer - Project', 'mudlet' ),
		)
	);
}

add_filter( 'excerpt_length', 'mudlet_excerpt_length' );
/**
 * @return int
 */
function mudlet_excerpt_length(): int {
	return 32;
}

add_filter( 'excerpt_more', 'mudlet_excerpt_more' );
/**
 * @return string
 */
function mudlet_excerpt_more(): string {
	return '&hellip;';
}

add_action( 'wp_head', 'mudlet_theme_boot', 1 );
/**
 * Set the colour theme before first paint.
 *
 * This has to be inline and it has to be early. Reading localStorage from an
 * external script means a frame of the wrong theme, which reads as a flash of
 * white on every navigation for anyone in dark mode. Lifted verbatim from the
 * prototype's own head script, minus the Artifact-viewer branch that has no
 * meaning here.
 */
function mudlet_theme_boot(): void {
	?>
<script>
(function(){
  var root = document.documentElement, t;
  try { t = localStorage.getItem('mudlet-theme'); } catch (e) {}
  if (t !== 'light' && t !== 'dark')
    t = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  root.setAttribute('data-theme', t);
})();
</script>
	<?php
}

add_filter( 'body_class', 'mudlet_body_class' );
/**
 * @param string[] $classes Body classes.
 * @return string[]
 */
function mudlet_body_class( array $classes ): array {
	$classes[] = 'mudlet';
	return $classes;
}
