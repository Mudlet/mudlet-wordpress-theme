/**
 * The submission form, in the browser.
 *
 * Plain ES5 against the DOM, the same way the theme's own script and the games
 * block's editor script are, because there is no npm anywhere under wordpress/
 * and a hundred lines of fetch is a poor reason to add one.
 *
 * ---------------------------------------------------------------------------
 *
 * The contract with whatever drew the form.
 *
 * The plugin renders a plain version of this form and the theme renders a
 * styled one (template-parts/blocks/screenshot-submit.php). Both are the same
 * form as far as this file is concerned, and this is the whole list of what it
 * looks for:
 *
 *   form.shotform[data-endpoint][data-max]   the form itself
 *   input[type=file][name=file]              required
 *   input[name=credit], input[name=about]    optional, sent if present
 *   .shotform__hp                            the honeypot, sent as `website`
 *   button[type=submit]                      disabled while in flight
 *   .shotform__msg                           where every sentence goes
 *   .shotdrop                                optional - the drop zone
 *   .shotdrop__file                          optional - the chosen filename
 *
 * Everything below `.shotform__msg` is optional and the form works without it:
 * with no `.shotdrop` the whole form becomes the drop target, and with no
 * `.shotdrop__file` nothing writes the filename out, because the plain markup
 * has not hidden the input's own copy of it.
 *
 * ---------------------------------------------------------------------------
 *
 * What is scripted here and what is not.
 *
 * **Clicking the box is not.** The drop zone is a <label> wrapped round the
 * real file input, so opening the picker is the browser doing what a label
 * does — no click handler, nothing to get wrong, and it works from the
 * keyboard because the input is still in the tab order. This file only adds
 * *dragging*, which has no declarative form, and writes out the filename the
 * label's styling has hidden.
 *
 * A dropped file has to be put back into the input, so that the field is a
 * real one carrying a real value rather than a variable this file remembers -
 * which is what `new DataTransfer()` is for. Where that is not allowed the
 * file is remembered instead, and the submit reads whichever of the two there
 * is: the form still works, it just no longer says `required` truthfully.
 *
 * ---------------------------------------------------------------------------
 *
 * Why the browser barely validates anything.
 *
 * Two checks here, and both exist to save an upload rather than to be trusted:
 * a file was chosen, and it is not larger than the site takes. Everything else
 * - what the file actually is, how big the picture is, whether the queue is
 * full - is decided by the endpoint, which is the only side that can decide it,
 * and every refusal it makes carries a sentence written for the visitor. So the
 * message here is nearly always the server's own words rather than this file's.
 */
(function () {
	'use strict';

	var DATA = window.MUDLET_SHOTS || {};
	var S = DATA.strings || {};

	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	ready(function () {
		var forms = document.querySelectorAll('form.shotform');
		if (!forms.length) return;

		// A file dropped anywhere else on the page is a file the browser opens
		// in place of the page, which throws away whatever else somebody had
		// typed into the form. Only worth stopping on a page that has one.
		['dragover', 'drop'].forEach(function (type) {
			document.addEventListener(type, function (e) { e.preventDefault(); });
		});

		Array.prototype.forEach.call(forms, function (form) {
			var url = form.getAttribute('data-endpoint') || DATA.url;
			var max = parseInt(form.getAttribute('data-max'), 10) || DATA.max || 0;
			var file = form.querySelector('input[type="file"]');
			var send = form.querySelector('button[type="submit"]');
			var note = form.querySelector('.shotform__msg');
			var trap = form.querySelector('.shotform__hp');
			var zone = form.querySelector('.shotdrop') || form;
			var name = form.querySelector('.shotdrop__file');

			if (!url || !file || !send || !note) return;

			// Only set when the input would not take the dropped file. See the
			// header: it is the fallback, not the normal path.
			var kept = null;

			function say(text, state) {
				note.textContent = text || '';
				if (state) note.setAttribute('data-state', state);
				else note.removeAttribute('data-state');
			}

			function chosen() {
				return (file.files && file.files[0]) || kept || null;
			}

			// Everything that happens when the file changes, however it got
			// there — picked in the dialog, dropped on the box, or cleared by
			// the reset after a successful send.
			function settle() {
				var picked = chosen();

				if (name) name.textContent = picked ? picked.name : '';
				if (zone !== form) {
					if (picked) zone.setAttribute('data-has', '');
					else zone.removeAttribute('data-has');
				}

				// Choosing another file is how somebody says they have another
				// one to send, which is the moment to clear the thank-you.
				form.removeAttribute('data-sent');

				// The one thing worth saying before anybody presses anything: a
				// file too large to send, said when it is chosen rather than
				// after it has spent a minute uploading.
				if (picked && max && picked.size > max) say(S.toobig, 'bad');
				else say('');
			}

			file.addEventListener('change', function () {
				kept = null;
				settle();
			});

			// ── dragging ────────────────────────────────────────────────
			// dragenter and dragleave fire again for every child element the
			// pointer crosses, so the box would flicker if the handler just
			// toggled. Counting entries against leaves is the usual answer and
			// the only one that survives a box with an icon in it.
			var depth = 0;

			function over(on) {
				if (zone === form) return;
				if (on) zone.setAttribute('data-over', '');
				else zone.removeAttribute('data-over');
			}

			zone.addEventListener('dragenter', function (e) {
				e.preventDefault();
				depth++;
				over(true);
			});

			zone.addEventListener('dragover', function (e) {
				// Without this the drop never happens: the default action for
				// dragover is to refuse the drop.
				e.preventDefault();
				if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
			});

			zone.addEventListener('dragleave', function () {
				depth = Math.max(0, depth - 1);
				if (!depth) over(false);
			});

			zone.addEventListener('drop', function (e) {
				e.preventDefault();
				depth = 0;
				over(false);

				var dropped = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
				if (!dropped) return;

				// Put it back into the real input where possible, so the field
				// carries the value rather than this closure. Some browsers
				// refuse; then it is remembered instead and chosen() finds it.
				try {
					var dt = new DataTransfer();
					dt.items.add(dropped);
					file.files = dt.files;
					kept = file.files && file.files[0] ? null : dropped;
				} catch (err) {
					kept = dropped;
				}

				settle();
			});

			// ── sending ─────────────────────────────────────────────────
			form.addEventListener('submit', function (e) {
				e.preventDefault();

				var picked = chosen();
				if (!picked) return say(S.nofile, 'bad');
				if (max && picked.size > max) return say(S.toobig, 'bad');

				var body = new FormData();
				body.append('file', picked);
				body.append('website', trap ? trap.value : '');

				['credit', 'about'].forEach(function (field) {
					var input = form.querySelector('[name="' + field + '"]');
					if (input) body.append(field, input.value);
				});

				send.disabled = true;
				form.setAttribute('data-sending', '');
				say(S.sending);

				// No Content-Type header: the browser has to set it itself, so
				// that the multipart boundary it invents is the one it writes.
				fetch(url, { method: 'POST', body: body })
					.then(function (r) {
						return r.json().then(
							function (json) { return { ok: r.ok, body: json }; },
							function () { return { ok: r.ok, body: null }; }
						);
					})
					.then(function (res) {
						// Every answer the endpoint gives carries a line of its
						// own, refusals included; the fallback is for the ones
						// that never reached it.
						var line = (res.body && res.body.message) || (res.ok ? '' : S.failed);

						if (res.ok) {
							kept = null;
							form.reset();
							settle();
							// After settle(), which clears it: a form that keeps
							// its thank-you is one somebody reads, and a form
							// that keeps the file is one they send twice.
							form.setAttribute('data-sent', '');
						}

						say(line, res.ok ? 'ok' : 'bad');
					})
					.catch(function () {
						say(S.failed, 'bad');
					})
					.then(function () {
						send.disabled = false;
						form.removeAttribute('data-sending');
					});
			});
		});
	});
})();
