/*
 * The editor half of mudlet/games.
 *
 * Plain ES5 against the wp.* globals: there is no build step anywhere under
 * wordpress/, and no element of this is worth introducing one for. That means
 * wp.element.createElement rather than JSX, and var rather than const, the same
 * way the theme's own script is written.
 *
 * What an editor sees: the real cards, rendered by PHP through ServerSideRender
 * so the preview cannot drift from the page, and a token field to say which
 * games. What is stored is a list of slugs - never a card, never a blurb.
 *
 * See includes/class-block.php for why the block belongs to the plugin.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element) return;

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;

	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var FormTokenField = wp.components.FormTokenField;
	var Placeholder = wp.components.Placeholder;
	var ServerSideRender = wp.serverSideRender;

	var DATA = window.MudletGamesBlock || {};
	var GAMES = DATA.games || [];

	// Two lookups over the same forty rows. Names are what a person picks from;
	// slugs are what the block stores.
	var NAME_OF = {};
	var SLUG_OF = {};
	var NAMES = [];

	GAMES.forEach(function (game) {
		NAME_OF[game.slug] = game.name;
		// Case-insensitively, so typing "achaea" finds "Achaea".
		SLUG_OF[game.name.toLowerCase()] = game.slug;
		NAMES.push(game.name);
	});

	function namesOf(slugs) {
		return (slugs || []).map(function (slug) {
			// A slug with no record left still shows, so an editor can see
			// which one to remove. The front end drops it silently.
			return NAME_OF[slug] || slug;
		});
	}

	function slugsOf(names) {
		var out = [];
		(names || []).forEach(function (name) {
			var slug = SLUG_OF[String(name).toLowerCase()];
			// Free text that matches no game is dropped rather than stored:
			// the field is a picker, not somewhere to invent a game.
			if (slug && out.indexOf(slug) === -1) out.push(slug);
		});
		return out;
	}

	function edit(props) {
		var games = props.attributes.games || [];

		var picker = el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Games', 'mudlet-games') },
				el(FormTokenField, {
					label: __('Show these games', 'mudlet-games'),
					value: namesOf(games),
					suggestions: NAMES,
					__experimentalExpandOnFocus: true,
					__nextHasNoMarginBottom: true,
					onChange: function (next) {
						props.setAttributes({ games: slugsOf(next) });
					}
					// No `help` here: FormTokenField prints its own "separate
					// with commas" line and ignores one. What a card is made of
					// is in the block's description, at the top of this panel.
				})
			)
		);

		var body = games.length
			? el(ServerSideRender, {
				block: 'mudlet/games',
				attributes: { games: games },
				// The block renders nothing when every slug has gone stale;
				// say so rather than leaving a blank rectangle.
				EmptyResponsePlaceholder: function () {
					return el(
						Placeholder,
						{ label: __('Games in this release', 'mudlet-games') },
						__('None of these games are on record any more.', 'mudlet-games')
					);
				}
			})
			: el(
				Placeholder,
				{
					icon: 'games',
					label: __('Games in this release', 'mudlet-games'),
					instructions: GAMES.length
						? __('Pick the games to show in the block settings panel.', 'mudlet-games')
						: __('No games have synced yet. Run a sync from the Games screen.', 'mudlet-games')
				}
			);

		return el(Fragment, null, picker, el('div', useBlockProps(), body));
	}

	wp.blocks.registerBlockType('mudlet/games', {
		edit: edit,
		// Dynamic: the cards are looked up at render time, so there is nothing
		// to save but the slugs the attributes already hold.
		save: function () {
			return null;
		}
	});
})(window.wp);
