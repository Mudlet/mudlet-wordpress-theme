<?php
/**
 * Mudlet theme bootstrap.
 *
 * The design lives in assets/css/theme.css. Nothing in PHP should restate a
 * value that stylesheet already owns.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

define( 'MUDLET_VERSION', wp_get_theme()->get( 'Version' ) );

require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/enqueue.php';
require_once __DIR__ . '/inc/blocks.php';
require_once __DIR__ . '/inc/nav-walker.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/search.php';
require_once __DIR__ . '/inc/template-tags.php';
require_once __DIR__ . '/inc/front-content.php';
require_once __DIR__ . '/inc/front-art.php';
require_once __DIR__ . '/inc/github-releases.php';
require_once __DIR__ . '/inc/downloads.php';
require_once __DIR__ . '/inc/download-email.php';
require_once __DIR__ . '/inc/games.php';
require_once __DIR__ . '/inc/makers.php';
require_once __DIR__ . '/inc/discord.php';
require_once __DIR__ . '/inc/contact.php';
require_once __DIR__ . '/inc/demo-seed.php';
require_once __DIR__ . '/inc/release-meta.php';
require_once __DIR__ . '/inc/divi-cleanup.php';
require_once __DIR__ . '/inc/languages.php';

if ( is_admin() ) {
	require_once __DIR__ . '/inc/front-content-admin.php';
}
