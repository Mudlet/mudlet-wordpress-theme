<?php
/**
 * Placeholder news, for when there is no WXR export yet.
 *
 * These exist so the archive, the year headings, the category pills, the
 * release panel and the single-post outline all have something real to render.
 * They are thrown away the moment a real export is imported - the importer
 * matches on slug, and these use the same slugs as the live posts, so the real
 * versions overwrite them rather than sitting alongside.
 *
 * Headlines and dates are the live site's; the bodies are summaries, not the
 * real articles.
 *
 * @package Mudlet
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$author = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$author = $author ? (int) $author[0] : 1;

$posts = array(
	array(
		'slug'    => '4-22-mapping-made-friendlier',
		'title'   => '4.22 — mapping, made friendlier',
		'date'    => '2026-07-06 10:00:00',
		'cat'     => 'release',
		'release' => array( '4.22.0', '1', '2', '8' ),
		'excerpt' => 'Create, rename and delete map areas right from the mapper with the new Configure Areas dialog, and lock stub exits from the Set Exits GUI. Both were scripting-only until now.',
		'body'    => '<p>Mudlet 4.22 is out — a mapper-focused release. Two things that until now needed a script can be done from the interface, and Windows builds arrive signed.</p>

<h2>Manage your map areas visually</h2>
<p>You can now create, rename and delete map areas right from the mapper, with the new <strong>Configure Areas</strong> dialog. No scripting needed — a long-requested feature, finally here.</p>

<h2>Lock stub exits without scripting</h2>
<p>The Set Exits dialog’s “No route” checkbox now works for stub exits, bringing the interface in line with what the Lua API could already do. Two related fixes come with it:</p>
<ul>
<li>saving a room no longer clears locks that were set from a script;</li>
<li>map audits keep those locks instead of dropping them.</li>
</ul>

<h2>Signed Windows installers</h2>
<p>Windows builds are now code-signed through <a href="https://signpath.io/">SignPath.io</a>, with a certificate from the SignPath Foundation — so the installer no longer arrives from an “unknown publisher”.</p>
<blockquote><p>Upgrading is free and leaves your profiles, packages and maps exactly where they are.</p></blockquote>

<h2>Everything in 4.22.0</h2>
<p>Mudlet 4.22.0 is available for Windows, macOS and Linux. Existing installations will offer the update on next launch.</p>',
	),
	array(
		'slug'    => 'mudlet-4-22-0',
		'title'   => 'Mudlet 4.22.0',
		'date'    => '2026-07-06 09:00:00',
		'cat'     => 'release',
		'release' => array( '4.22.0', '1', '2', '8' ),
		'excerpt' => 'Added: a Configure Areas UI. Improved: locking stub exits via the Set Exits GUI, and map room name label colour handling. Fixed: plain Copy after Copy HTML, and thirteen more.',
		'body'    => '<p>The full changelog for 4.22.0 is on GitHub: <a href="https://github.com/Mudlet/Mudlet/compare/Mudlet-4.21.0...Mudlet-4.22.0">Mudlet-4.21.0…Mudlet-4.22.0</a>.</p>',
	),
	array(
		'slug'    => '4-21-mudlet-made-better',
		'title'   => '4.21 — Mudlet, made better.',
		'date'    => '2026-06-13 12:00:00',
		'cat'     => 'release',
		'release' => array( '4.21.0', '47', '77', '207' ),
		'excerpt' => '47 new features, 77 improvements, a whopping 207 bug fixes and 203 behind-the-scenes infrastructure changes. This release places a strong emphasis on stability.',
		'body'    => '<p>47 new features, 77 improvements, 207 bug fixes and 203 infrastructure changes.</p>

<h2>Stability first</h2>
<p>This release places a strong emphasis on stability — the largest single batch of fixes Mudlet has shipped.</p>

<h2>What is next</h2>
<p>Work now moves towards 5.0. See the direction post for what that means.</p>',
	),
	array(
		'slug'    => 'the-direction-of-mudlet-and-our-focus-for-5-0',
		'title'   => 'The direction of Mudlet and our focus for 5.0',
		'date'    => '2026-03-24 12:00:00',
		'cat'     => 'informational',
		'excerpt' => 'As we plan for Mudlet 5.0, the team has taken a step back to reevaluate the overall direction of the project — how we approach development, feature requests, and our release milestones.',
		'body'    => '<p>As we plan for Mudlet 5.0, the team has taken a step back to reevaluate the overall direction of the project.</p>

<h2>How we approach development</h2>
<p>Smaller, more frequent releases, and a clearer line between what is stable and what is being tried out.</p>

<h2>Feature requests</h2>
<p>Everything goes through GitHub, where it can be discussed in the open.</p>

<h2>Release milestones</h2>
<p>Dated rather than scoped, so a release is never held hostage by one unfinished feature.</p>',
	),
	array(
		'slug'    => 'mudlet-4-20-0',
		'title'   => 'Mudlet-4.20.0',
		'date'    => '2026-03-14 12:00:00',
		'cat'     => 'release',
		'release' => array( '4.20.0', '46', '91', '142' ),
		'excerpt' => 'Dependency fixes for Lua, and infrastructure work towards code-signed builds on every platform.',
		'body'    => '<p>Dependency fixes for Lua, and infrastructure work towards code-signed builds on every platform.</p>',
	),
	array(
		'slug'    => 'let-your-players-connect-to-your-mud-in-under-30s',
		'title'   => 'Let your players connect to your MUD in under 30s',
		'date'    => '2026-02-04 12:00:00',
		'cat'     => 'informational',
		'excerpt' => 'For MUD administrators: let players launch your game on the desktop without ever typing an IP address or a port number.',
		'body'    => '<p>For MUD administrators: let players launch your game on the desktop without ever typing an IP address or a port number.</p>

<h2>Add your game to Mudlet</h2>
<p>Mudlet ships connection profiles for 42 games. Adding yours is a pull request.</p>

<h2>Link straight into a session</h2>
<p>A single link from your website can open Mudlet on the right host and port.</p>',
	),
	array(
		'slug'    => 'mudlet-2025-survey-responses',
		'title'   => 'Mudlet 2025 survey responses',
		'date'    => '2025-09-01 12:00:00',
		'cat'     => '',
		'excerpt' => '162 of you answered this year’s survey. Here is what you told us about how you play, what you script, and what you want us to build next.',
		'body'    => '<p>162 of you answered this year’s survey.</p>

<h2>How you play</h2>
<p>Most of you play on a desktop, most nights, on one or two games.</p>

<h2>What you script</h2>
<p>Aliases and triggers dominate; the mapper is close behind.</p>

<h2>What you want next</h2>
<p>Better package management, and a gentler first hour.</p>',
	),
	array(
		'slug'    => 'introducing-the-mudlet-package-manager',
		'title'   => 'Introducing the Mudlet Package Manager',
		'date'    => '2025-01-25 12:00:00',
		'cat'     => '',
		'excerpt' => 'A streamlined way to find, install and manage packages for all your Mudlet games — whether you are a seasoned player or new to the scene.',
		'body'    => '<p>A streamlined way to find, install and manage packages for all your Mudlet games.</p>',
	),
	array(
		'slug'    => 'mudlet-and-carrion-fields-on-steam',
		'title'   => 'Mudlet and Carrion Fields on Steam',
		'date'    => '2024-07-10 12:00:00',
		'cat'     => 'press',
		'excerpt' => 'Mudlet and Carrion Fields have joined forces to bring MUDding to the Steam desktop. Carrion Fields has been a dominating MUD ever since its inception over 30 years ago.',
		'body'    => '<p>Mudlet and Carrion Fields have joined forces to bring MUDding to the Steam desktop.</p>',
	),
	array(
		'slug'    => '4-19-mudlet-is-now-portable',
		'title'   => '4.19 — Mudlet is now portable',
		'date'    => '2024-12-26 12:00:00',
		'cat'     => 'release',
		'release' => array( '4.19.0', '17', '37', '39' ),
		'excerpt' => '17 new features, 37 improvements, 39 bug fixes and 62 behind-the-scenes infrastructure changes — plus drag-and-drop images and profile modification from Lua.',
		'body'    => '<p>Moving profiles between computers has been a long-time dream for many players. In 4.19 it is a supported way to run Mudlet.</p>

<h2>Portable profiles</h2>
<p>Extract the launcher somewhere permanent and run Mudlet from there.</p>

<h2>Drag and drop images</h2>
<p>Drop an image onto the map to set it as an area background.</p>',
	),
);

$made = 0;
foreach ( $posts as $p ) {
	if ( get_page_by_path( $p['slug'], OBJECT, 'post' ) ) {
		continue;
	}

	$id = wp_insert_post(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'post_title'     => $p['title'],
			'post_name'      => $p['slug'],
			'post_date'      => $p['date'],
			'post_author'    => $author,
			'post_excerpt'   => $p['excerpt'],
			'post_content'   => $p['body'],
			'comment_status' => 'closed',
		),
		true
	);

	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( $p['slug'] . ': ' . $id->get_error_message() );
		continue;
	}

	if ( '' !== $p['cat'] ) {
		$term = get_term_by( 'slug', $p['cat'], 'category' );
		if ( $term ) {
			wp_set_post_categories( $id, array( (int) $term->term_id ) );
		}
	}

	// Marked so setup.sh can find and remove them the moment a real export
	// arrives. Without this they keep the good slugs and the imported posts
	// land as "mudlet-4-22-0-2".
	update_post_meta( $id, '_mudlet_placeholder', '1' );

	if ( ! empty( $p['release'] ) ) {
		list( $version, $added, $improved, $fixed ) = $p['release'];
		update_post_meta( $id, '_mudlet_version', $version );
		update_post_meta( $id, '_mudlet_added', $added );
		update_post_meta( $id, '_mudlet_improved', $improved );
		update_post_meta( $id, '_mudlet_fixed', $fixed );
	}

	++$made;
}

WP_CLI::log( "    wrote $made placeholder posts" );
