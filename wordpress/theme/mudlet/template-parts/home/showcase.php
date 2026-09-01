<?php
/**
 * The feature switcher.
 *
 * Autoplays on a six-second dwell until the visitor takes it over; theme.js
 * owns that. Each panel needs an image, which is why this is six fixed entries
 * rather than a loop over something editable.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$img = get_template_directory_uri() . '/assets/img/';

$mudlet_panels = array(
	array(
		'icon'  => 'monitor',
		'title' => __( 'Works anywhere', 'mudlet' ),
		'img'   => 'The-Land-MUD.png',
		'alt'   => __( 'The Land running in Mudlet on a desktop, with custom health bars and encounter panels', 'mudlet' ),
		'body'  => __( 'Windows, macOS and Linux — even Chromebooks and Raspberry Pi. Scripts written on one machine run on the next, and profiles sync however you like.', 'mudlet' ),
	),
	array(
		'icon'  => 'bolt',
		'title' => __( 'Fast &amp; lightweight', 'mudlet' ),
		'img'   => 'group-combat-big-1.png',
		'alt'   => __( 'A crowded group-combat session: health bars, target portraits and spell lists all updating live', 'mudlet' ),
		'body'  => __( 'Performance defined Mudlet from the start. A custom text display and Lua-powered scripting handle the biggest raids without dropping a frame.', 'mudlet' ),
	),
	array(
		'icon'  => 'sliders',
		'title' => __( '100% modifiable', 'mudlet' ),
		'img'   => 'Cybersphere-UI.png',
		'alt'   => __( 'Cybersphere running in Mudlet behind a full custom cyberpunk interface', 'mudlet' ),
		'body'  => __( 'Every part of the interface is designed to be modded — from the space inside the window to the look and feel of the client itself.', 'mudlet' ),
	),
	array(
		'icon'  => 'map',
		'title' => __( 'A real mapper', 'mudlet' ),
		'img'   => 'MapBackgroundImageLabel.png',
		'alt'   => __( 'A Mudlet map of an area, drawn over a desert photograph set as its background', 'mudlet' ),
		'body'  => __( '2D and 3D mapping with built-in pathfinding. Walk once and Mudlet remembers — then draw custom exits or drop a background image over an area.', 'mudlet' ),
	),
	array(
		'icon'  => 'share',
		'title' => __( 'Free and open source', 'mudlet' ),
		'img'   => 'academy4.png',
		'alt'   => __( 'Mudlet 1.2 from 2010, mapper open beside the game window', 'mudlet' ),
		'body'  => __( 'Free to download, modify and extend, under the GPL. Build on a powerful foundation and join us in making MUDing awesome.', 'mudlet' ),
	),
	array(
		'icon'  => 'smile',
		'title' => __( 'Approachable', 'mudlet' ),
		'img'   => 'map-stat-big.png',
		'alt'   => __( 'A friendly in-game statistics panel drawn as a parchment scroll over the game text', 'mudlet' ),
		'body'  => __( 'A friendly Discord of over 5,000 players, and a scripting API carefully designed to be simple and intuitive before it is powerful.', 'mudlet' ),
	),
);

$mudlet_specs = array(
	__( 'Multiple simultaneous games', 'mudlet' ),
	__( 'Lua scripting API', 'mudlet' ),
	__( 'In-app script editor', 'mudlet' ),
	__( 'Import/export profiles', 'mudlet' ),
	__( 'Broad MUD protocol support', 'mudlet' ),
	__( 'Secure connections', 'mudlet' ),
	__( 'In-app IRC client', 'mudlet' ),
	__( 'Discord Rich Presence', 'mudlet' ),
	__( 'Accessible to visually impaired players', 'mudlet' ),
);
?>
<section class="showcase">
	<div class="w">
		<div class="head">
			<p class="eyebrow"><?php esc_html_e( 'why mudlet', 'mudlet' ); ?></p>
			<h2><?php esc_html_e( 'What keeps people playing', 'mudlet' ); ?></h2>
		</div>

		<div class="sw">
			<div class="swlist" role="tablist" aria-label="<?php esc_attr_e( 'Features', 'mudlet' ); ?>">
				<?php foreach ( $mudlet_panels as $i => $panel ) : ?>
					<button class="swbtn" role="tab" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>" data-p="p<?php echo (int) ( $i + 1 ); ?>">
						<?php mudlet_icon( $panel['icon'] ); ?>
						<b><?php echo wp_kses( $panel['title'], array() ); ?></b>
					</button>
				<?php endforeach; ?>
			</div>

			<div>
				<?php foreach ( $mudlet_panels as $i => $panel ) : ?>
					<div class="swpanel" id="p<?php echo (int) ( $i + 1 ); ?>"<?php echo 0 === $i ? '' : ' hidden'; ?>>
						<img src="<?php echo esc_url( $img . $panel['img'] ); ?>" alt="<?php echo esc_attr( $panel['alt'] ); ?>" loading="lazy" decoding="async">
						<div class="cap">
							<h3><?php echo wp_kses( $panel['title'], array() ); ?></h3>
							<p><?php echo esc_html( $panel['body'] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<?php // The spec line: everything the six panels have no room to say. ?>
		<p class="specs"><span class="mk">&gt;</span><b><?php esc_html_e( 'also', 'mudlet' ); ?></b><?php
			echo implode( '<span class="sep">·</span>', array_map( 'esc_html', $mudlet_specs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?></p>
	</div>
</section>
