/* The front page's card repeater, on the front page's own edit screen.
 *
 * Plain ES5 against the DOM, the way assets/js/theme.js and the games block's
 * editor script are written. There is no build step anywhere under wordpress/
 * and this is not the thing to introduce one for.
 *
 * The whole job is four verbs - add, remove, move up, move down, plus dragging
 * when jQuery UI is there - and one thing that makes them safe: after any
 * structural change every field's name is rewritten from its row's position, so
 * what PHP receives is always a list numbered from zero in the order shown.
 * Nothing here tracks an index; the DOM is the index.
 */
(function () {
	'use strict';

	// #mudlet-front-rows, not #mudlet-front-cards: the latter is the meta box's
	// id, which WordPress puts on the .postbox wrapper around this list. Two
	// nodes with one id and getElementById returns the wrapper instead.
	var list = document.getElementById('mudlet-front-rows');
	var add = document.getElementById('mudlet-front-add');
	var tmpl = document.getElementById('tmpl-mudlet-front-panel');
	if (!list || !add || !tmpl) return;

	function rows() {
		return Array.prototype.slice.call(list.querySelectorAll('.mudlet-front__panel'));
	}

	/* Rewrite every name so [cards][n] matches the row's position. Called after
	   add, remove and every move - never anywhere else, because a row whose
	   inputs disagree with its neighbours is how a repeater silently drops or
	   merges entries on save.

	   A <select>'s value rides through a rename untouched, so unlike a radio
	   group nothing has to be snapshotted and put back here. */
	function reindex() {
		rows().forEach(function (row, i) {
			var fields = row.querySelectorAll('input[name], select[name], textarea[name]');
			Array.prototype.forEach.call(fields, function (field) {
				field.name = field.name.replace(/\[cards\]\[[^\]]*\]/, '[cards][' + i + ']');
			});
		});
	}

	/* Dragging, when WordPress's own jquery-ui-sortable is there. Everything
	   else works without it - the up/down buttons are the keyboard path and the
	   fallback in one - so this is an enhancement, not a requirement.

	   handle: only the grip starts a drag. Without that, a press inside a
	   textarea to place the cursor becomes a drag of the whole row.
	   update: the same reindex() the buttons call, for the same reason. */
	function sortable() {
		var $ = window.jQuery;
		if (!$ || !$.fn || !$.fn.sortable) return null;

		return $(list).sortable({
			handle: '.mudlet-front__grip',
			items: '> .mudlet-front__panel',
			axis: 'y',
			tolerance: 'pointer',
			placeholder: 'mudlet-front__placeholder',
			forcePlaceholderSize: true,
			update: reindex
		});
	}

	// Assigned at the very bottom, after the buttons are wired. See there.
	var $sortable = null;

	/* Tell sortable the list changed shape. With an `items` selector jQuery UI
	   usually rediscovers rows at drag start; refreshing after an add or a
	   remove is the cheap way not to depend on "usually". */
	function refresh() {
		if ($sortable) $sortable.sortable('refresh');
	}

	add.addEventListener('click', function () {
		var wrap = document.createElement('div');
		// The template's markup is one row with __i__ where the number goes;
		// reindex() replaces it a moment later, so the value only has to be
		// unique-ish until then.
		wrap.innerHTML = tmpl.innerHTML.replace(/__i__/g, String(rows().length));

		var row = wrap.querySelector('.mudlet-front__panel');
		if (!row) return;

		list.appendChild(row);
		reindex();
		refresh();

		var first = row.querySelector('input[type="text"]');
		if (first) first.focus();
	});

	list.addEventListener('click', function (e) {
		var btn = e.target.closest('button');
		if (!btn) return;

		var row = btn.closest('.mudlet-front__panel');
		if (!row) return;

		if (btn.classList.contains('mudlet-front__remove')) {
			e.preventDefault();
			row.parentNode.removeChild(row);
			reindex();
			refresh();
			return;
		}

		if (btn.classList.contains('mudlet-front__up')) {
			e.preventDefault();
			if (row.previousElementSibling) {
				list.insertBefore(row, row.previousElementSibling);
				reindex();
				btn.focus();
			}
			return;
		}

		if (btn.classList.contains('mudlet-front__down')) {
			e.preventDefault();
			if (row.nextElementSibling) {
				list.insertBefore(row.nextElementSibling, row);
				reindex();
				btn.focus();
			}
		}
	});

	/* Dragging goes on last, and inside a try.
	 *
	 * Add, remove and the up/down buttons are the feature; dragging is the
	 * nicety on top. Initialising it first - which is how this was written -
	 * meant one throw in jQuery UI took every click handler below it with it,
	 * and the screen arrived with nothing working at all rather than with
	 * everything working except the drag. Order and the try are both the fix.
	 */
	try {
		$sortable = sortable();
	} catch (err) {
		if (window.console && console.error) {
			console.error('[mudlet] card dragging unavailable:', err);
		}
	}
})();
