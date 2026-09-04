/*
 * mudlet.org — front-end behaviour.
 *
 * Forked once from the <script> block in the static prototype this theme grew
 * out of, and the theme's own from then on. What changed in the fork, all of it
 * deliberate, because the shapes it explains are still here:
 *
 *   - the prototype's client-side router is gone; these are real URLs now
 *   - the category filter is gone; the chips are links to category archives,
 *     which is fewer moving parts and a filter you can bookmark
 *   - the archive year select navigates instead of scrolling
 *   - every string and every piece of release data comes from window.MUDLET,
 *     localised by inc/enqueue.php
 *   - a download row carries a second hand-off button, for the form that mails
 *     the link; a static page had no server to hand an address to
 *   - the wrapper is #site, not #vb
 *
 * No jQuery, no build step, no modules. It runs from a deferred <script> in the
 * footer, so the DOM is there by the time any of this executes.
 */
(function () {
	'use strict';

	var DATA = window.MUDLET || {};
	var S = DATA.strings || {};

	// ── copy on click ────────────────────────────────────────────────────
	// Three buttons on the download page do the same thing: put a string on
	// the clipboard, say "copied" for a second and a half, and put themselves
	// back. What differs between them is only where the string comes from,
	// how a button says a word (its own label, a span inside it, or a tick
	// swapped in for the copy icon) and what to do when there is no clipboard
	// at all - so those are the arguments, and the rest is here.
	//
	//   read()       the string, read at the moment of the press
	//   show(word)   paint the button with the word, or '' to put it back
	//   fail(flash)  no clipboard at all. `flash` says a word and undoes it,
	//                for a caller that answers in the button; return true to
	//                leave the button dead, for one that has given up on it.
	//
	// Held apart from the clipboard call below because they answer different
	// questions - this one is what a button does, that one is what a browser
	// can do - and the browser half is the one with the history in it.
	function copyOnClick(btn, read, show, fail) {
		var spent = false;   // a caller that gave up stays given up
		var busy = false;    // and a press mid-flash is the same press twice

		function flash(word) {
			busy = true;
			show(word);
			setTimeout(function () { busy = false; show(''); }, 1500);
		}

		btn.addEventListener('click', function () {
			if (spent || busy) return;
			copyText(
				read(),
				function () { flash(S.copied || 'copied'); },
				function () { spent = fail(flash) === true; }
			);
		});
	}

	// ── put a string on the clipboard ────────────────────────────────────
	// No page here can assume the async clipboard is there: an older browser
	// has only execCommand, and an insecure origin or a sandboxed frame
	// withholds both. Try the modern one, fall back to the hidden textarea,
	// and call `fail` only when there is no clipboard at all.
	function copyText(text, ok, fail) {
		function legacy() {
			try {
				var ta = document.createElement('textarea');
				ta.value = text;
				ta.setAttribute('readonly', '');
				ta.style.cssText = 'position:fixed;top:0;left:-9999px';
				document.body.appendChild(ta);
				ta.select();
				var done = document.execCommand('copy');
				document.body.removeChild(ta);
				if (done) ok();
				return done;
			} catch (err) { return false; }
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(ok, function () { if (!legacy()) fail(); });
		} else if (!legacy()) {
			fail();
		}
	}

	// ── the narrow-screen menu ───────────────────────────────────────────
	// One nav in the document, drawn two ways: a row in the bar, or a panel
	// under it. Which one is a media query's business, so the open state has
	// to live on an attribute the query can ignore - setting `hidden` here
	// would leave the bar's own nav display:none the moment the window got
	// wide again.
	(function () {
		var bar = document.querySelector('#site .top');
		var burger = bar && bar.querySelector('.burger');
		if (!burger) return;

		function open() { return bar.getAttribute('data-nav') === 'open'; }
		function set(on) {
			bar.setAttribute('data-nav', on ? 'open' : 'shut');
			burger.setAttribute('aria-expanded', on ? 'true' : 'false');
		}

		burger.addEventListener('click', function () { set(!open()); });

		// A link taken, or a tap anywhere but inside the panel, ends it. The
		// language button inside stops its own click, so opening that menu
		// does not close the drawer under it.
		document.addEventListener('click', function (e) {
			if (!open()) return;
			var t = e.target;
			if (t.closest && t.closest('#site .burger')) return;
			if (!t.closest || !t.closest('#site .menu') || t.closest('#site .menu a')) set(false);
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && open()) { set(false); burger.focus(); }
		});
	})();

	// ── header utilities: language menu and the theme toggle ─────────────
	(function () {
		// The language switcher and the nav's dropdowns are one behaviour: a
		// button, a panel it owns, one open at a time, and anything else that
		// gets clicked shuts them. Collected as pairs rather than written
		// twice, because a second copy of this is a second copy of the Escape
		// handling and the outside-click handling too.
		var pops = [];
		var langBtn = document.querySelector('#site .lang__btn');
		var langMenu = document.querySelector('#site .lang__menu');
		if (langBtn && langMenu) pops.push([langBtn, langMenu]);
		Array.prototype.forEach.call(document.querySelectorAll('#site .nav__grp'), function (grp) {
			var b = grp.querySelector('.nav__top');
			var m = grp.querySelector('.nav__sub');
			if (b && m) pops.push([b, m]);
		});

		if (pops.length) {
			var show = function (pair, on) {
				pair[1].hidden = !on;
				pair[0].setAttribute('aria-expanded', on ? 'true' : 'false');
			};
			var closeAll = function () { pops.forEach(function (p) { show(p, false); }); };

			pops.forEach(function (pair) {
				pair[0].addEventListener('click', function (e) {
					e.stopPropagation();
					var opening = pair[1].hidden;
					closeAll();
					show(pair, opening);
				});
			});
			// The language menu swallows its own clicks so that picking a
			// language out of the narrow-screen drawer does not also close the
			// drawer under it. A nav panel is only links, which navigate.
			if (langMenu) langMenu.addEventListener('click', function (e) { e.stopPropagation(); });
			document.addEventListener('click', closeAll);
			document.addEventListener('keydown', function (e) {
				if (e.key !== 'Escape') return;
				var open = document.querySelector('#site .nav__top[aria-expanded="true"]');
				closeAll();
				if (open) open.focus();
			});
		}

		var theme = document.querySelector('#site .theme');
		if (!theme) return;

		var root = document.documentElement;
		function relabel() {
			var dark = root.getAttribute('data-theme') === 'dark';
			theme.setAttribute('aria-label', dark ? (S.lightTheme || 'Switch to light theme')
			                                      : (S.darkTheme || 'Switch to dark theme'));
			theme.setAttribute('aria-pressed', dark ? 'true' : 'false');
		}
		relabel();

		theme.addEventListener('click', function () {
			var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
			root.setAttribute('data-theme', next);
			try { localStorage.setItem('mudlet-theme', next); } catch (e) {}
			relabel();
		});

		// keep following the OS until the visitor has actually chosen
		if (window.matchMedia) {
			matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
				var stored;
				try { stored = localStorage.getItem('mudlet-theme'); } catch (err) {}
				if (stored === 'light' || stored === 'dark') return;
				root.setAttribute('data-theme', e.matches ? 'dark' : 'light');
				relabel();
			});
		}
	})();

	// ── search palette — "/" or ctrl/cmd-K, IDE style ────────────────────
	//
	// Three sources, one list. DATA.search is a flat [title, source, url] index
	// of the newest pages and posts, inline with the page: it draws on the
	// keystroke, matches titles only, and is all there is when the REST API
	// cannot be reached. DATA.searchUrl is mudlet/v1/search, which runs the
	// query the results page runs — the documents, not their titles — and
	// replaces that first pass a moment later. DATA.searchWikiUrl is
	// mudlet/v1/search/wiki, which is wiki.mudlet.org.
	//
	// The second exists because the box used to disagree with itself: a word in
	// the body of a page suggested nothing as you typed and then found it the
	// instant you pressed Enter. The third exists because most of what anyone
	// searches this site for is in the manual, and the site's own content is
	// forty pages and a news log. Submitting still falls through to WordPress's
	// own search when nothing is highlighted, so the palette stays a shortcut
	// over real search rather than a replacement for it.
	//
	// The two requests go out together and neither waits for the other: the
	// site answers from the database and the wiki over somebody else's network,
	// and each half draws as it lands.
	(function () {
		var open = document.querySelector('#site .searchbtn');
		var dlg = document.querySelector('#site .palette');
		var input = dlg && dlg.querySelector('input[name="s"]'); // the form also carries a hidden language
		var list = dlg && dlg.querySelector('.palette__list');
		var empty = dlg && dlg.querySelector('.palette__empty');
		var form = dlg && dlg.querySelector('form');
		if (!open || !dlg || !input || !form || !dlg.showModal) return;

		var ITEMS = Array.isArray(DATA.search) ? DATA.search : [];
		var URL_ = typeof DATA.searchUrl === 'string' ? DATA.searchUrl : '';
		// Empty when the site has switched the wiki off, which is the whole of
		// how this side knows not to ask.
		var WIKI = typeof DATA.searchWikiUrl === 'string' ? DATA.searchWikiUrl : '';
		// The palette asks in the language the visitor is reading, because the
		// results page it hands off to answers in that language and a count
		// that disagrees with the page it links to is worse than no count.
		var LANG = typeof DATA.searchLang === 'string' ? DATA.searchLang : '';
		var LIVE = !!URL_ && typeof window.fetch === 'function';
		var MIN = 2;    // in step with the floor in inc/search.php
		var WAIT = 160; // one pause in the typing, not one request per letter
		// How much of the site's half is shown once the wiki has answered. The
		// route sends eight, and the panel holds ten rows — measured, at the
		// 34rem theme.css gives it — so eight of them and then the wiki's three
		// is a block that exists only below the fold. Five, three, and a row out
		// of each is exactly ten. Nothing is lost: the row that offers the rest
		// of the site is one of them.
		var SITE_MAX = 5;
		var NONE = empty.textContent;

		var shown = [], cursor = 0;
		var timer = null, seq = 0, ctrl = null, busy = false;
		// What each half of one question has answered so far, and how many of
		// them are still out. Both are cleared on every keystroke.
		var got = { site: null, wiki: null }, waiting = 0;

		function draw(items, keep) {
			// `keep` holds the highlight on the same destination when the
			// fetched list replaces the typed-ahead one under someone who has
			// already arrowed down it.
			var was = keep && shown[cursor] ? shown[cursor][2] : null;
			shown = items;
			cursor = 0;
			list.innerHTML = '';
			shown.forEach(function (item, i) {
				if (was && item[2] === was) cursor = i;
				var li = document.createElement('li');
				var b = document.createElement('button');
				b.type = 'button';
				// item[3] is what the row is when it is not a result: 'all' for
				// the sticky way out to the results page, 'out' for the way out
				// to the wiki's own search, 'group' for the first row of the
				// wiki's block, which is where the list is divided.
				if (item[3]) li.className = 'palette__' + item[3];
				b.setAttribute('aria-selected', 'false');
				b.innerHTML = '<span class="t"></span><span class="src"></span>';
				b.querySelector('.t').textContent = item[0];
				b.querySelector('.src').textContent = item[1];
				b.addEventListener('mousemove', function () { move(i - cursor); });
				b.addEventListener('click', function () { go(item); });
				li.appendChild(b);
				list.appendChild(li);
			});
			var btns = list.querySelectorAll('button');
			if (btns[cursor]) btns[cursor].setAttribute('aria-selected', 'true');
			// A pending request owns the empty slot: a query the titles miss
			// must not answer "No matches." before the documents have replied.
			empty.textContent = busy ? (S.searching || NONE) : NONE;
			empty.hidden = shown.length > 0;
		}
		function local(q) {
			q = q.trim().toLowerCase();
			return q
				? ITEMS.filter(function (i) { return String(i[0]).toLowerCase().indexOf(q) > -1; })
				: ITEMS.slice(0, 8);
		}
		function stop() {
			clearTimeout(timer);
			if (ctrl) { ctrl.abort(); ctrl = null; }
			seq++; // anything already in flight answers to a number nobody holds
			busy = false;
			waiting = 0;
			got.site = null;
			got.wiki = null;
		}
		function render(q) {
			stop();
			busy = LIVE && q.trim().length >= MIN;
			draw(local(q));
			if (busy) timer = setTimeout(function () { ask(q.trim()); }, WAIT);
		}
		// A palette is ten rows tall and a search is not, so the list ends
		// in a row out of it — a real row, so it arrows and clicks like the
		// others and go() needs to know nothing about it beyond the URL it
		// carries. Sticky, so it is reachable without scrolling to it.
		//
		// It counts against what is *shown* rather than what came back, because
		// the site's half is cut short to make room for the wiki's: eight rows
		// and then three is a wiki nobody ever sees, which is what this looked
		// like first.
		function all(q, count) {
			if (!got.site || got.site.total <= count) return null;
			// The form's own action and field, so the row lands exactly where
			// submitting would - language included.
			var href = (form.getAttribute('action') || '') + '?s=' + encodeURIComponent(q);
			if (LANG) href += '&lang=' + encodeURIComponent(LANG);
			return [
				(S.searchAll || 'See all %s results').replace('%s', String(got.site.total)),
				S.searchSrc || 'Search',
				href,
				'all'
			];
		}
		// The list as it stands, redrawn each time a half of it lands. The
		// site's documents replace the typed-ahead titles; until they arrive —
		// or on a site whose REST API cannot be reached, where they never do —
		// the titles stay on screen and the wiki's rows go under them.
		//
		// The wiki's block ends in its own way out, and it is offered whenever
		// the wiki said anything at all: a row on the wiki is a page, and the
		// question "what else does the manual have" is the one this block is
		// there to raise. Not sticky — one row can pin to the bottom edge, and
		// the site's owns it.
		function paint(q) {
			var wiki = got.wiki && got.wiki.rows.length ? got.wiki.rows : null;
			var rows = got.site ? got.site.rows.slice() : local(q);
			if (wiki) rows = rows.slice(0, SITE_MAX);

			var end = all(q, rows.length);

			if (wiki) {
				// The first of them carries the divider, and the flag is put on
				// a copy: these rows are redrawn on every keystroke that lands.
				rows = rows.concat(wiki.map(function (row, i) {
					return i ? row : row.slice(0, 3).concat('group');
				}));
				if (got.wiki.url) {
					rows.push([
						S.searchWikiAll || 'Search the wiki',
						S.searchWikiSrc || 'Wiki',
						got.wiki.url,
						'out'
					]);
				}
			}
			if (end) rows.push(end);
			draw(rows, true);
		}
		function ask(q) {
			var token = seq;
			var opts = {};
			if (window.AbortController) { ctrl = new AbortController(); opts.signal = ctrl.signal; }
			var tail = '?q=' + encodeURIComponent(q.slice(0, 100));
			if (LANG) tail += '&lang=' + encodeURIComponent(LANG);
			waiting = WIKI ? 2 : 1;
			grab(URL_ + tail, 'site', token, q, opts);
			if (WIKI) grab(WIKI + tail, 'wiki', token, q, opts);
		}
		// One request, one half of the list. A half that fails — offline, 404,
		// a site with the REST API turned off, a wiki behind a firewall — leaves
		// its slot null, which is what paint() reads as "draw the other one".
		function grab(url, half, token, q, opts) {
			fetch(url, opts).then(function (r) {
				return r.ok ? r.json() : null;
			}).catch(function () {
				return null;
			}).then(function (data) {
				if (token !== seq) return; // a later keystroke won
				got[half] = data && Array.isArray(data.rows) ? data : null;
				waiting--;
				busy = waiting > 0;
				// The controller aborts both halves, so it is only spent once
				// the second of them has answered.
				if (!busy) ctrl = null;
				paint(q);
			});
		}
		function go(item) {
			if (item && item[2]) window.location.href = item[2];
			else close();
		}
		function move(delta) {
			if (!shown.length) return;
			var btns = list.querySelectorAll('button');
			btns[cursor].setAttribute('aria-selected', 'false');
			cursor = (cursor + delta + shown.length) % shown.length;
			btns[cursor].setAttribute('aria-selected', 'true');
			btns[cursor].scrollIntoView({ block: 'nearest' });
		}
		function show() {
			if (dlg.open) return;
			render('');
			input.value = '';
			dlg.showModal(); // native modal: esc and the focus trap come free
			input.focus();
		}
		function close() { if (dlg.open) dlg.close(); }

		open.addEventListener('click', show);
		// deferred: at close time the page is still inert, so a synchronous
		// focus() is dropped and the caret stays in the hidden dialog
		dlg.addEventListener('close', function () {
			stop(); // nothing in flight outlives the dialog that asked for it
			setTimeout(function () { open.focus(); }, 0);
		});

		form.addEventListener('submit', function (e) {
			// Enter on a highlighted suggestion opens it; Enter on a query with
			// no suggestions falls through to WordPress search.
			if (shown.length) {
				e.preventDefault();
				go(shown[cursor]);
			}
		});
		input.addEventListener('input', function () { render(input.value); });
		// The clear button in a type=search field fires 'search', not 'input'.
		// Only when it has emptied the field, though: 'search' is also what
		// Enter fires, and re-rendering there would swap the highlighted row
		// out from under the submit handler about to open it.
		input.addEventListener('search', function () { if (!input.value) render(''); });
		input.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
			if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
		});
		// click the backdrop (anything outside the panel) to dismiss
		dlg.addEventListener('click', function (e) {
			var r = dlg.getBoundingClientRect();
			if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) close();
		});

		document.addEventListener('keydown', function (e) {
			var t = e.target, tag = t && t.tagName;
			var typing = tag === 'INPUT' || tag === 'TEXTAREA' || (t && t.isContentEditable);
			if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); show(); return; }
			if (e.key === '/' && !typing && !e.metaKey && !e.ctrlKey && !e.altKey) { e.preventDefault(); show(); }
		});
	})();

	// ── the live client in the hero ──────────────────────────────────────
	//
	// Deliberately late and deliberately quiet. The client is a multi-megabyte
	// bundle plus three wasm blobs, so it must not compete with the page: the
	// iframe is only created after 'load', in idle time. Until the frame reports
	// that it has actually printed something, the terminal holds a boot state —
	// so the hero shows one client connecting once, rather than a scripted
	// session that a second, different session then replaces.
	//
	// Every exit from that state leads somewhere sensible: the frame prints and
	// takes over; or it fails, or takes too long, or was never built at all, and
	// the scripted session plays instead.
	(function () {
		var term = document.getElementById('term');
		var host = term && term.querySelector('.herolive');
		if (!host) return;

		var src = DATA.demoSrc;
		// A phone gets the real client too. This used to stop at the hero's
		// single-column breakpoint, because what a phone got was a 300px-tall
		// console under an on-screen keyboard that covered the reply to every
		// command you sent. Mudlet Web fixed its half of that - the command box
		// blurs itself after each submit on a touch layout, so the keyboard
		// drops and the output is visible - and the stylesheet fixes the other
		// half by giving the panel most of the screen down there. What is left
		// is a small console, which is what the expand control is for.
		if (!src) return;

		var frame = null, settled = false;

		// Synchronously, before the poster below plays its first line: from here
		// on the scripted session is the fallback, not the opening act.
		term.classList.add('is-booting');

		function fallback() {
			if (settled) return;
			settled = true;
			term.classList.remove('is-booting');
			term.dispatchEvent(new CustomEvent('poster:play'));
		}

		window.addEventListener('message', function (e) {
			if (!frame || e.source !== frame.contentWindow) return;
			if (e.origin !== window.location.origin) return;
			if (!e.data || e.data.type !== 'mudlet-demo:ready') return;
			if (e.data.ok) {
				settled = true;
				// Both classes in one frame: the frame underneath is painted and
				// already showing these same two lines, so there is nothing to tween.
				term.classList.remove('is-booting');
				term.classList.add('is-live');
			} else {
				// Booted but never printed — a Mudlet profile is single-owner
				// across tabs, so a second tab reports ok:false. Take the dead
				// frame back out rather than leaving an invisible session eating
				// memory behind the poster.
				frame.remove();
				frame = null;
				fallback();
			}
		});

		function boot() {
			frame = document.createElement('iframe');
			frame.title = 'Mudlet running in your browser, in a small demo world';
			frame.src = src;
			host.appendChild(frame);
			// A frame that never answers must not leave "connecting" on screen
			// for good. It is left to finish loading — if it reports in later it
			// still swaps in — but the hero stops waiting on it.
			setTimeout(fallback, 12000);
		}

		if (window.requestIdleCallback) requestIdleCallback(boot, { timeout: 2500 });
		else setTimeout(boot, 600);
	})();

	// ── expand the terminal over the page ────────────────────────────────
	//
	// The frame is pinned with position:fixed rather than moved into a dialog:
	// an iframe reloads when its node is re-parented, and reloading this one
	// would drop the session — the profile, the map, the room you were standing
	// in. So the same element stays where it is in the DOM and only its box
	// changes, which also means the client sees one plain resize and
	// repositions its own map for free.
	//
	// Never transform an ancestor of the frame either: Chrome then cannot paint
	// the iframe's scrolling content, and the console comes up blank with its
	// lines still in the DOM. Hence animating the box, not a transform of it.
	(function () {
		var term = document.getElementById('term');
		var btn = term && term.querySelector('.term__grow');
		if (!btn) return;

		// A panel this large needs longer than a button does — 280ms read as a
		// jump. Opening takes the slower half of the pair because it is the move
		// that has to be understood; closing is a dismissal and can be brisker.
		var GROW = 480, SHRINK = 360;
		var still = window.matchMedia('(prefers-reduced-motion:reduce)');
		var scrim = null, busy = false;
		var hold = null;   // stand-in holding the panel's place in the grid
		var origin = null; // where the panel sits in the page, in document coords

		// Pinning the panel takes it out of flow, and an out-of-flow grid item
		// stops being placed at all — so the row it sat in disappears and
		// everything below the hero jumps up by its height, then drops back on
		// close. On desktop that never showed: the hero is two columns and the
		// copy beside the panel goes on sizing the row without it. Below the
		// single-column breakpoint the panel is the only thing in its row, so
		// there is nothing left to hold the height.
		//
		// A sibling of the panel's own shape, not a wrapper around it: wrapping
		// would re-parent the iframe, and re-parenting an iframe reloads it —
		// the session, the profile and the room you were standing in, gone to
		// keep the page from twitching. It goes in after the panel, which is
		// where auto-placement wants it once the panel is out of the running.
		function holdOpen(h) {
			hold = document.createElement('div');
			hold.className = 'heroterm__hold';
			hold.setAttribute('aria-hidden', 'true');
			hold.style.height = h + 'px';
			term.parentNode.insertBefore(hold, term.nextSibling);
		}
		function letGo() {
			if (hold) { hold.remove(); hold = null; }
		}

		// Document coordinates, not viewport: the page can be scrolled between
		// opening and closing, and the collapsed box travels with it.
		function pageRect(el) {
			var r = el.getBoundingClientRect();
			return { x: r.left + window.scrollX, y: r.top + window.scrollY, w: r.width, h: r.height };
		}
		function viewRect(page) {
			return { x: page.x - window.scrollX, y: page.y - window.scrollY, w: page.w, h: page.h };
		}

		// Animating left/top/width/height keeps the type at one size throughout
		// and lets the room appear around it, which is what an expanding client
		// actually looks like. A transform would have been cheaper but scales
		// the contents, which reads as a screenshot being zoomed.
		function fly(collapsed, shrinking, ms, done) {
			var box = term.getBoundingClientRect();
			var wide = { x: box.left, y: box.top, w: box.width, h: box.height };
			var from = shrinking ? wide : collapsed;
			var to = shrinking ? collapsed : wide;
			var frame = function (r) {
				return { left: r.x + 'px', top: r.y + 'px', width: r.w + 'px', height: r.h + 'px' };
			};

			if (!term.animate) { done(); return; }
			// .is-wide positions with `inset`, which sets all four edges;
			// right/bottom are dropped to auto for the flight so left/top/width/
			// height are the only things describing the box.
			term.style.right = 'auto';
			term.style.bottom = 'auto';

			var anim = term.animate([frame(from), frame(to)], {
				duration: ms,
				easing: shrinking ? 'cubic-bezier(.4,0,.2,1)' : 'cubic-bezier(.32,.72,.28,1)',
				fill: 'forwards'
			});
			anim.finished.then(function () {
				done();        // drops .is-wide on the way out, so the CSS box is right
				anim.cancel(); // release the held frame
				term.style.right = '';
				term.style.bottom = '';
			}, done);
		}

		function onKey(e) { if (e.key === 'Escape') close(); }

		function open() {
			if (busy) return;
			origin = pageRect(term);

			scrim = document.createElement('div');
			scrim.className = 'termscrim';
			scrim.addEventListener('click', close);
			document.getElementById('site').appendChild(scrim);

			// Same tick as the class that pins it, so the row is never briefly
			// missing (or briefly doubled) between the two.
			term.classList.add('is-wide');
			holdOpen(origin.h);
			btn.setAttribute('aria-expanded', 'true');
			document.addEventListener('keydown', onKey);

			if (still.matches) return;
			busy = true;
			fly(viewRect(origin), false, GROW, function () { busy = false; });
		}

		function close() {
			if (!scrim || busy) return;
			document.removeEventListener('keydown', onKey);
			scrim.classList.add('is-shrinking');

			var finish = function () {
				term.classList.remove('is-wide');
				letGo();
				if (scrim) { scrim.remove(); scrim = null; }
				btn.setAttribute('aria-expanded', 'false');
				btn.focus();
				busy = false;
			};

			if (still.matches) { finish(); return; }
			busy = true;
			fly(viewRect(origin), true, SHRINK, finish);
		}

		btn.addEventListener('click', function () {
			if (term.classList.contains('is-wide')) close(); else open();
		});
	})();

	// ── the scripted session — the poster frame the live client replaces ──
	(function () {
		var term = document.getElementById('term');
		if (!term) return;
		var timer = null;

		// Replays from the top whenever it is asked to, so the session arrives
		// line by line rather than as a wall of already-finished text.
		function play() {
			if (term.classList.contains('is-booting')) return;
			clearTimeout(timer);
			var steps = term.querySelectorAll('.step');
			Array.prototype.forEach.call(steps, function (s) { s.classList.remove('is-on'); });
			if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) {
				Array.prototype.forEach.call(steps, function (s) { s.classList.add('is-on'); });
				return;
			}
			var i = 0;
			(function next() {
				if (i >= steps.length) return;
				var el = steps[i++];
				el.classList.add('is-on');
				timer = setTimeout(next, el.classList.contains('ln--gap') ? 40
				                       : el.classList.contains('ln--in') ? 420 : 190);
			})();
		}

		term.addEventListener('poster:play', play);
		play();
	})();

	// ── download page: lead with the build for the visitor's OS ──────────
	// and mark the matching row, so the table agrees with the button.
	(function () {
		var btn = document.getElementById('dlbtn');
		var dl = DATA.downloads;
		if (!btn || !dl || !dl.builds) return;

		var os = document.getElementById('dlos');
		var meta = document.getElementById('dlmeta');
		var alt = document.getElementById('dlalt');
		var icon = document.getElementById('dlicon');
		var icons = dl.icons || {};
		var str = dl.strings || {};

		var ua = navigator.userAgent || '';
		var plat = (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || '';
		var key = null;
		if (/Android|iPhone|iPad|iPod/i.test(ua)) key = null;   // no mobile build
		else if (/CrOS/i.test(ua)) key = 'cros';
		else if (/Win/i.test(plat) || /Windows/i.test(ua)) key = 'win';
		// Safari reports Intel even on Apple Silicon, so arm64 is the safer default
		else if (/Mac/i.test(plat) || /Mac OS X/i.test(ua)) key = 'macarm';
		else if (/Linux|X11/i.test(plat + ua)) key = 'linux';

		// The Public Test Build's page takes the same platform this detection
		// just made, so the visitor lands on their own build rather than a
		// picker. Anything else - a phone, ChromeOS, an OS we did not
		// recognise - drops the parameter: the snapshots page then offers all
		// three, which is a better answer than a guessed platform.
		var ptb = document.getElementById('ptb');
		if (ptb) {
			var snap = key === 'win' ? 'windows'
			         : (key === 'macarm' || key === 'macx86') ? 'macos'
			         : key === 'linux' ? 'linux' : '';
			if (snap) ptb.href += (ptb.href.indexOf('?') < 0 ? '?' : '&') + 'platform=' + snap;
		}

		if (key === 'cros') {
			if (os) os.textContent = str.crosName || 'Mudlet on ChromeOS';
			if (meta) meta.textContent = str.crosMeta || '';
			if (icon && icons.cros) icon.innerHTML = icons.cros;
			btn.textContent = str.crosCta || 'Read the instructions';
			if (dl.crosUrl) btn.href = dl.crosUrl;
			return;
		}
		// A phone, a tablet, an OS we cannot name: there is no build to lead
		// with, so the panel goes rather than standing there offering the site
		// name and a version with nothing behind the button. The table below
		// names all four builds, which is the honest answer to a platform we
		// did not recognise.
		if (!key || !dl.builds[key]) {
			var panel = btn.closest ? btn.closest('.dlmain') : null;
			if (panel) panel.hidden = true;
			return;
		}

		var b = dl.builds[key];
		if (os) os.textContent = (str.heading || 'Mudlet %1$s for %2$s')
			.replace('%1$s', dl.version).replace('%2$s', b.short);
		if (meta) meta.innerHTML = b.meta;
		if (icon && icons[key]) icon.innerHTML = icons[key];
		btn.textContent = b.cta;
		if (b.url) btn.href = b.url;

		if (key === 'macarm' && alt && dl.intelUrl) {
			var text = str.intelAlt || '';
			// The link is built rather than interpolated from a translated
			// string, so a translation cannot inject markup.
			var a = document.createElement('a');
			a.href = dl.intelUrl;
			a.textContent = text;
			alt.textContent = '';
			alt.appendChild(a);
			alt.hidden = false;
		}

		var row = document.querySelector('#site .dlrow[data-os="' + key + '"]');
		if (row) row.setAttribute('data-here', 'true');
	})();

	// ── the hand-off: the same download, on a machine you are not at ─────
	// A code for the build's own URL, a copy button, and - where the site can
	// send mail - a form that posts the *build key* to inc/download-email.php,
	// which looks the URL up again at its end. All of it lives in the row that
	// names the build, so the build is chosen by which row was pressed and
	// nothing here knows a version, a size or a platform name. The href is made
	// absolute first: a phone reading the code has no page to resolve a
	// relative one against.
	//
	// The code is drawn on the drawer's first open rather than up front. Four
	// of them is four runs of the encoder below, and most visitors want none.
	(function () {
		var dl = DATA.downloads || {};
		var str = dl.strings || {};
		var rows = document.querySelectorAll('#site .dlrow[data-os]');

		Array.prototype.forEach.call(rows, function (row) {
			var key = row.getAttribute('data-os');
			var name = row.querySelector('b');
			var link = row.querySelector('a.btn[href]');
			var more = row.querySelector('.dlmore');
			var acts = row.querySelectorAll('.dlact[data-face]');
			if (!key || !name || !link || !more || !acts.length) return;

			// The row's link is version-pinned - the checksum beside it names
			// that exact file - but everything in the drawer hands the build to
			// somewhere else: a phone, an inbox, a thread somebody reads next
			// year. Those want the alias that always resolves to the current
			// build. Falls back to the row's link where there is no alias.
			var url = new URL(row.getAttribute('data-latest') || link.getAttribute('href'), location.href).href;
			var art = row.querySelector('.dlqr');
			var say = row.querySelector('.dlpane__say');
			var out = row.querySelector('.dlurl');
			var cp = row.querySelector('.dlcopy');

			if (say) say.textContent = (str.handoff || '').replace('%s', name.textContent);
			if (out) out.textContent = url;

			// One face at a time, and the pressed state of every button in the
			// row follows from it: the drawer is the only thing on screen
			// saying which one opened it.
			var drawn = false;
			function open(face) {
				if (face === 'qr' && !drawn) {
					var svg = qr(url);
					if (!svg || !art) return;   // no code, no drawer: an empty box says nothing
					art.innerHTML = svg;
					drawn = true;
				}
				more.setAttribute('data-face', face || more.getAttribute('data-face') || '');
				more.setAttribute('data-open', face ? 'true' : 'false');
				Array.prototype.forEach.call(acts, function (b) {
					b.setAttribute('aria-expanded',
						face && b.getAttribute('data-face') === face ? 'true' : 'false');
				});
			}

			Array.prototype.forEach.call(acts, function (b) {
				var face = b.getAttribute('data-face');
				b.hidden = false;               // without this script it opens nothing
				b.addEventListener('click', function () {
					var showing = more.getAttribute('data-open') === 'true'
						&& more.getAttribute('data-face') === face;
					open(showing ? '' : face);
					if (!showing && face === 'mail') {
						var field = more.querySelector('input[type="email"]');
						if (field) field.focus();
					}
				});
			});

			if (cp) {
				// The label, taken once, for the same reason the checksum's
				// truncation is: read per press it would become "copied".
				var was = cp.textContent;
				copyOnClick(
					cp,
					function () { return url; },
					function (word) { cp.textContent = word || was; },
					// no clipboard (older browser, or a sandbox that withholds
					// it): the link is already printed beside this button, so
					// send the visitor there rather than fail quietly. It says
					// so in the button, so it borrows the same timer.
					function (flash) { flash(str.selectIt || ''); }
				);
			}

			// ── and the form that mails it ───────────────────────────────
			// Rendered by page-download.php only where the site can send mail
			// at all; what reaches the endpoint is this row's key, never the
			// URL beside it.
			var form = more.querySelector('.dlmail');
			if (!form || !dl.email || !dl.email.url) return;
			var field = form.querySelector('input[type="email"]');
			var trap = form.querySelector('.dlmail__hp');
			var send = form.querySelector('button[type="submit"]');
			var note = form.querySelector('.dlmail__msg');
			if (!field || !send || !note) return;

			form.addEventListener('submit', function (e) {
				e.preventDefault();
				if (!field.value) return;
				send.disabled = true;
				note.removeAttribute('data-state');
				note.textContent = str.sending || '';

				fetch(dl.email.url, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					// The build key, not the link. The endpoint resolves it.
					body: JSON.stringify({
						email: field.value,
						build: key,
						website: trap ? trap.value : ''
					})
				}).then(function (r) {
					return r.json().then(function (body) { return { ok: r.ok, body: body }; },
						function () { return { ok: r.ok, body: null }; });
				}).then(function (res) {
					// Every answer the endpoint gives carries a line of its own,
					// the refusals included; the fallback is for the ones that
					// never got there.
					note.textContent = (res.body && res.body.message) || (res.ok ? '' : (str.mailFail || ''));
					note.setAttribute('data-state', res.ok ? 'ok' : 'bad');
					if (res.ok) field.value = '';
				}).catch(function () {
					note.textContent = str.mailFail || '';
					note.setAttribute('data-state', 'bad');
				}).then(function () {
					send.disabled = false;
				});
			});
		});

		// ── QR, in as much of the spec as a download link needs ──────────────
		// Byte mode, error correction M, versions 1 to 10 - 213 bytes, which is
		// four times the longest URL this table can hold. Written out rather than
		// pulled in because the page is one file with no build step behind it, and
		// a code for a link we already have is arithmetic, not a dependency.
		function qr(text){
			var m = modules(text);
			if (!m) return '';
			var n = m.length, quiet = 4, w = n + quiet * 2, d = '', r, c;
			for (r = 0; r < n; r++) for (c = 0; c < n; c++)
				if (m[r][c]) d += 'M' + (c + quiet) + ' ' + (r + quiet) + 'h1v1h-1z';
			return '<svg viewBox="0 0 ' + w + ' ' + w + '" shape-rendering="crispEdges" xmlns="http://www.w3.org/2000/svg">'
				+ '<rect width="' + w + '" height="' + w + '" fill="#fff"/>'
				+ '<path d="' + d + '" fill="#111"/></svg>';
		}

		function modules(str){
			var CAP = [14,26,42,62,84,106,122,152,180,213];
			// per version: ec codewords a block, then the two block groups
			var ECB = [[10,1,16,0,0],[16,1,28,0,0],[26,1,44,0,0],[18,2,32,0,0],[24,2,43,0,0],
								[16,4,27,0,0],[18,4,31,0,0],[22,2,38,2,39],[22,3,36,2,37],[26,4,43,1,44]];
			var ALIGN = [[],[6,18],[6,22],[6,26],[6,30],[6,34],[6,22,38],[6,24,42],[6,26,46],[6,28,50]];
			var i, j, k;

			var esc = encodeURIComponent(str), data = [];
			for (i = 0; i < esc.length; i++){
				if (esc.charAt(i) === '%'){ data.push(parseInt(esc.substr(i + 1, 2), 16)); i += 2; }
				else data.push(esc.charCodeAt(i));
			}
			var ver = 0;
			for (i = 0; i < CAP.length; i++) if (data.length <= CAP[i]){ ver = i + 1; break; }
			if (!ver) return null;                     // longer than a version 10 code holds

			// mode, length, payload, terminator, padding
			var ec = ECB[ver - 1], words = ec[1] * ec[2] + ec[3] * ec[4], bits = [];
			function put(v, n){ for (var b = n - 1; b >= 0; b--) bits.push((v >>> b) & 1); }
			put(4, 4);
			put(data.length, ver < 10 ? 8 : 16);
			for (i = 0; i < data.length; i++) put(data[i], 8);
			for (i = 0; i < 4 && bits.length < words * 8; i++) bits.push(0);
			while (bits.length % 8) bits.push(0);
			var cw = [];
			for (i = 0; i < bits.length; i += 8){
				var byte = 0;
				for (j = 0; j < 8; j++) byte = (byte << 1) | bits[i + j];
				cw.push(byte);
			}
			for (i = 0; cw.length < words; i++) cw.push(i % 2 ? 0x11 : 0xEC);

			// Reed-Solomon over GF(256)
			var EXP = [], LOG = [];
			for (i = 0, j = 1; i < 255; i++){ EXP[i] = j; LOG[j] = i; j <<= 1; if (j & 256) j ^= 0x11D; }
			for (i = 255; i < 512; i++) EXP[i] = EXP[i - 255];
			function mul(a, b){ return (a && b) ? EXP[LOG[a] + LOG[b]] : 0; }
			var gen = [1];
			for (i = 0; i < ec[0]; i++){
				var next = gen.concat([0]);
				for (j = 0; j < gen.length; j++) next[j + 1] ^= mul(gen[j], EXP[i]);
				gen = next;
			}
			function parity(block){
				var r = block.slice(), a, b;
				for (a = 0; a < ec[0]; a++) r.push(0);
				for (a = 0; a < block.length; a++){
					var f = r[a];
					if (!f) continue;
					for (b = 1; b <= ec[0]; b++) r[a + b] ^= mul(gen[b], f);
				}
				return r.slice(block.length);
			}
			var blocks = [], checks = [], at = 0, count = ec[1] + ec[3];
			for (i = 0; i < count; i++){
				var take = i < ec[1] ? ec[2] : ec[4];
				blocks.push(cw.slice(at, at + take));
				checks.push(parity(blocks[i]));
				at += take;
			}
			var stream = [];                           // the blocks go out interleaved
			for (i = 0; i < Math.max(ec[2], ec[4]); i++)
				for (j = 0; j < count; j++) if (i < blocks[j].length) stream.push(blocks[j][i]);
			for (i = 0; i < ec[0]; i++)
				for (j = 0; j < count; j++) stream.push(checks[j][i]);

			// the grid: function patterns first, so the data can flow around them
			var size = ver * 4 + 17, m = [], fixed = [];
			for (i = 0; i < size; i++){
				m.push([]); fixed.push([]);
				for (j = 0; j < size; j++){ m[i][j] = 0; fixed[i][j] = 0; }
			}
			function set(r, c, v){
				if (r < 0 || c < 0 || r >= size || c >= size) return;
				m[r][c] = v ? 1 : 0; fixed[r][c] = 1;
			}
			function eye(r, c){                        // the 7x7 finder and its separator
				for (var dr = -1; dr <= 7; dr++) for (var dc = -1; dc <= 7; dc++){
					var edge = dr === 0 || dr === 6 || dc === 0 || dc === 6;
					var core = dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4;
					set(r + dr, c + dc, dr >= 0 && dr <= 6 && dc >= 0 && dc <= 6 && (edge || core));
				}
			}
			eye(0, 0); eye(0, size - 7); eye(size - 7, 0);
			for (i = 8; i < size - 8; i++){ set(6, i, i % 2 === 0); set(i, 6, i % 2 === 0); }
			var centres = ALIGN[ver - 1];
			for (i = 0; i < centres.length; i++) for (j = 0; j < centres.length; j++){
				var ar = centres[i], ac = centres[j];
				if ((ar <= 8 && ac <= 8) || (ar <= 8 && ac >= size - 9) || (ar >= size - 9 && ac <= 8)) continue;
				for (var dr = -2; dr <= 2; dr++) for (var dc = -2; dc <= 2; dc++)
					set(ar + dr, ac + dc, Math.max(Math.abs(dr), Math.abs(dc)) !== 1);
			}
			// reserve the format strip, stepping over the two timing modules in it
			for (i = 0; i <= 8; i++) if (i !== 6){ set(8, i, 0); set(i, 8, 0); }
			for (i = 0; i < 8; i++){ set(8, size - 1 - i, 0); set(size - 1 - i, 8, 0); }
			set(size - 8, 8, 1);                       // the one module that is always dark
			if (ver >= 7){
				var vb = ver;
				for (i = 0; i < 12; i++) vb = (vb << 1) ^ ((vb >>> 11) * 0x1F25);
				vb = (ver << 12) | vb;
				for (i = 0; i < 18; i++){
					var on = (vb >>> i) & 1, far = size - 11 + i % 3, near = Math.floor(i / 3);
					set(near, far, on); set(far, near, on);
				}
			}

			// the data, zigzagging up and down column pairs from the bottom right
			var idx = 0;
			for (var right = size - 1; right >= 1; right -= 2){
				if (right === 6) right = 5;              // the timing column is not data
				for (var step = 0; step < size; step++) for (k = 0; k < 2; k++){
					var col = right - k, up = ((right + 1) & 2) === 0;
					var row = up ? size - 1 - step : step;
					if (fixed[row][col]) continue;
					m[row][col] = idx < stream.length * 8 ? (stream[idx >> 3] >>> (7 - (idx & 7))) & 1 : 0;
					idx++;
				}
			}

			// and the mask: all eight, scored by the four penalties, lowest wins
			function masked(k, r, c){
				switch (k){
					case 0: return (r + c) % 2 === 0;
					case 1: return r % 2 === 0;
					case 2: return c % 3 === 0;
					case 3: return (r + c) % 3 === 0;
					case 4: return (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0;
					case 5: return (r * c) % 2 + (r * c) % 3 === 0;
					case 6: return ((r * c) % 2 + (r * c) % 3) % 2 === 0;
					default: return ((r + c) % 2 + (r * c) % 3) % 2 === 0;
				}
			}
			function flip(k){                          // its own inverse, so it also undoes one
				for (var r = 0; r < size; r++) for (var c = 0; c < size; c++)
					if (!fixed[r][c] && masked(k, r, c)) m[r][c] ^= 1;
			}
			function format(k){
				var rem = k;                             // M is 00, so the level adds nothing
				for (var b = 0; b < 10; b++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
				var f = ((k << 10) | rem) ^ 0x5412, i;
				function bit(i){ return (f >>> i) & 1; }
				for (i = 0; i <= 5; i++) set(i, 8, bit(i));
				set(7, 8, bit(6)); set(8, 8, bit(7)); set(8, 7, bit(8));
				for (i = 9; i < 15; i++) set(8, 14 - i, bit(i));
				for (i = 0; i < 8; i++) set(8, size - 1 - i, bit(i));
				for (i = 8; i < 15; i++) set(size - 15 + i, 8, bit(i));
				set(size - 8, 8, 1);
			}
			function run(line){                        // runs of five, and the finder lookalike
				var s = '', score = 0, i, len = 1, prev;
				for (i = 0; i < size; i++) s += line(i) ? '1' : '0';
				prev = s.charAt(0);
				for (i = 1; i <= size; i++){
					var ch = i < size ? s.charAt(i) : '';
					if (ch === prev) len++;
					else { if (len >= 5) score += len - 2; len = 1; prev = ch; }
				}
				var look = ['10111010000', '00001011101'];
				for (i = 0; i < 2; i++){
					var from = s.indexOf(look[i]);
					while (from !== -1){ score += 40; from = s.indexOf(look[i], from + 1); }
				}
				return score;
			}
			function penalty(){
				var score = 0, dark = 0, r, c;
				for (r = 0; r < size; r++) for (c = 0; c < size; c++) if (m[r][c]) dark++;
				for (r = 0; r < size; r++) score += run(alongRow(r));
				for (c = 0; c < size; c++) score += run(alongCol(c));
				for (r = 0; r < size - 1; r++) for (c = 0; c < size - 1; c++)
					if (m[r][c] === m[r][c + 1] && m[r][c] === m[r + 1][c] && m[r][c] === m[r + 1][c + 1]) score += 3;
				return score + Math.floor(Math.abs(dark * 20 / (size * size) - 10)) * 10;
			}
			function alongRow(r){ return function(i){ return m[r][i]; }; }
			function alongCol(c){ return function(i){ return m[i][c]; }; }

			var best = 0, low = Infinity;
			for (k = 0; k < 8; k++){
				flip(k); format(k);
				var score = penalty();
				flip(k);
				if (score < low){ low = score; best = k; }
			}
			flip(best); format(best);
			return m;
		}
	})();

	// ── checksums: show a short form, copy the whole thing ───────────────
	(function () {
		Array.prototype.forEach.call(document.querySelectorAll('#site .sha'), function (b) {
			var full = b.getAttribute('data-sha');
			var v = b.querySelector('.sha__v');
			if (!full || !v) return;

			// The truncation, taken once. Read per press it would eventually
			// be the word "copied", and the row would keep it.
			var shown = v.innerHTML;

			copyOnClick(
				b,
				function () { return full; },
				function (word) {
					if (word) {
						b.setAttribute('data-copied', '');
						v.textContent = word;
					} else {
						b.removeAttribute('data-copied');
						v.innerHTML = shown;
					}
				},
				// no clipboard (older browser, or a sandbox that withholds it):
				// show the full value instead so it can still be selected, and
				// stop offering a button that has been shown not to work
				function () {
					b.setAttribute('data-revealed', '');
					v.textContent = full;
					return true;
				}
			);
		});
	})();

	// ── the clone line, under "Build it yourself" ────────────────────────
	// The command is read out of the <code> beside the button rather than
	// carried a second time in an attribute, so the page has one copy of it
	// and the button cannot go stale against what the visitor is looking at.
	// The tick is the only feedback there is room for, so the label under it
	// changes too - it is all a screen reader has.
	(function () {
		var box = document.querySelector('#site .clone');
		var btn = box && box.querySelector('.clone__cp');
		var code = box && box.querySelector('code');
		if (!btn || !code) return;

		var label = btn.querySelector('.screen-reader-text');
		var said = label ? label.textContent : '';
		btn.hidden = false;   // without this script it copies nothing

		copyOnClick(
			btn,
			function () { return code.textContent.trim(); },
			function (word) {
				if (word) {
					btn.setAttribute('data-copied', '');
				} else {
					btn.removeAttribute('data-copied');
				}
				if (label) label.textContent = word || said;
			},
			// No clipboard at all: the command is printed right there and is
			// selectable, so select it and let the visitor take it from there
			// rather than leave the press unanswered. The button stays live -
			// unlike the checksum it has nothing left to reveal.
			function () {
				var sel = window.getSelection && window.getSelection();
				if (!sel || !document.createRange) return;
				var range = document.createRange();
				range.selectNodeContents(code);
				sel.removeAllRanges();
				sel.addRange(range);
			}
		);
	})();

	// ── post outline ─────────────────────────────────────────────────────
	// The jump itself is a plain anchor and works without this; scroll-margin on
	// the headings keeps them clear of the sticky header. All this adds is the
	// easing, and only for visitors who have not asked for less motion.
	(function () {
		var links = document.querySelectorAll('#site .olist a');
		if (!links.length) return;
		if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;

		Array.prototype.forEach.call(links, function (a) {
			a.addEventListener('click', function (e) {
				var h = document.getElementById(a.getAttribute('href').slice(1));
				if (!h) return;
				e.preventDefault();
				var from = window.scrollY;
				h.scrollIntoView({ block: 'start', behavior: 'smooth' });
				// A smooth scroll is a request, not a promise: a browser that has
				// the animation turned off — or a tab that is not visible — drops
				// it and leaves the page where it was. Having taken the click away
				// from the anchor, this owes the visitor the jump either way.
				setTimeout(function () {
					if (window.scrollY === from) h.scrollIntoView({ block: 'start' });
				}, 320);
			});
		});
	})();

	// ── the archive's year jump ──────────────────────────────────────────
	// It used to be a <select> whose options carried archive URLs and a change
	// handler that navigated to one; it is a <details> full of <a> now, so the
	// jump itself needs no script and works with this file absent. See .ydrop
	// in theme.css.
	//
	// What is left is the one thing a <details> does not do and every other
	// panel on this page does: a native one stays open until its own summary is
	// pressed again, so a rail with an abandoned list hanging out of it is the
	// visitor's to tidy up. This is the same dismissal the header's menus have —
	// a click outside, or Escape — and nothing else. Picking a year navigates,
	// which closes it by loading a page.
	(function () {
		var drop = document.querySelector('#site .ydrop');
		if (!drop) return;

		function close(focus) {
			if (!drop.open) return;
			drop.open = false;
			if (focus) {
				var sum = drop.querySelector('summary');
				if (sum) sum.focus();
			}
		}

		document.addEventListener('click', function (e) {
			var t = e.target;
			if (t.closest && t.closest('#site .ydrop')) return;
			close(false);
		});

		// Escape puts the focus back on the summary, because the visitor who
		// pressed it is on the keyboard; an outside click leaves it alone.
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') close(true);
		});
	})();

	// ── /games/: filter the shelf, and reshuffle the panel ───────────────
	//
	// The whole list is already on the page - forty-odd cards, alphabetical, no
	// pagination - so filtering is hiding rather than fetching, and there is no
	// state worth putting in the URL that a visitor could not retype in the box
	// faster than they could copy the link.
	//
	// The haystack is the card's own textContent, which is why the template
	// puts the tail of each blurb in a hidden span: everything a card knows is
	// searchable without any of it being written down twice. A card whose blurb
	// says "roleplay-enforced" or "deutschsprachig" three paragraphs in is
	// findable even though only three lines of it are on screen.
	(function () {
		var shelf = document.getElementById('gshelf');
		var bar = document.getElementById('gbar');
		if (!shelf || !bar) return;

		var cards = Array.prototype.slice.call(shelf.querySelectorAll('.gcard'));
		if (!cards.length) return;

		var find = document.getElementById('gfind');
		var count = document.getElementById('gcount');
		var none = document.getElementById('gnone');
		var chips = Array.prototype.slice.call(bar.querySelectorAll('.chip'));
		var facet = '';

		// Read each card once. textContent takes hidden elements with it, which
		// is the whole trick; normalising here means the keystroke handler only
		// ever compares two strings.
		cards.forEach(function (card) {
			card._hay = (card.textContent || '').toLowerCase().replace(/\s+/g, ' ');
			card._tags = ' ' + (card.getAttribute('data-tags') || '') + ' ';
		});

		function apply() {
			var q = (find ? find.value : '').trim().toLowerCase();
			var shown = 0;

			cards.forEach(function (card) {
				var ok = (!q || card._hay.indexOf(q) !== -1) &&
					(!facet || card._tags.indexOf(' ' + facet + ' ') !== -1);
				card.hidden = !ok;
				if (ok) shown++;
			});

			if (none) none.hidden = shown !== 0;
			if (count) count.textContent = (S.gamesShown || '%s shown').replace('%s', String(shown));
		}

		if (find) {
			find.addEventListener('input', apply);
			// The clear button in a type=search field fires 'search', not 'input',
			// in some browsers.
			find.addEventListener('search', apply);
			find.addEventListener('keydown', function (e) {
				// Escape empties the box rather than reaching the page behind it.
				if (e.key === 'Escape' && find.value) { e.stopPropagation(); find.value = ''; apply(); }
			});
		}

		chips.forEach(function (chip) {
			chip.addEventListener('click', function () {
				var key = chip.getAttribute('data-facet') || '';
				// Clicking the pressed chip clears it, which saves reaching back
				// across the row for "All".
				facet = (facet === key) ? '' : key;
				chips.forEach(function (c) {
					c.setAttribute('aria-pressed', (c.getAttribute('data-facet') || '') === facet ? 'true' : 'false');
				});
				apply();
			});
		});

		bar.hidden = false;
		apply();

		// ── the panel's "another" ──────────────────────────────────────────
		//
		// Nothing is fetched and nothing is duplicated: the panel is rebuilt out
		// of a card that is already in the document, which is why the cards
		// carry the host, the port line and each tag's URL as data. It draws
		// from the cards currently showing, so after a filter "another" means
		// another one of these rather than another one of anything.
		var feat = document.getElementById('gfeat');
		var again = feat && feat.querySelector('.gfeat__again');
		if (!feat || !again) return;

		function draw(card) {
			var link = card.querySelector('.gcard__name a');
			var logo = card.querySelector('.gcard__logo img');
			var lede = card.querySelector('.gcard__lede');
			var name = feat.querySelector('.gfeat__name a');
			var tile = feat.querySelector('.gfeat__logo');
			var body = feat.querySelector('.gfeat__lede');
			var play = feat.querySelector('.gplay');
			var addr = feat.querySelector('.gplay__addr');
			var tags = feat.querySelector('.gfeat__tags');
			var telnet = card.getAttribute('data-telnet') || '';
			var browse = feat.querySelector('.gfeat__browser');
			var gweb = feat.querySelector('.gweb');
			var web = card.getAttribute('data-web') || '';

			// Trimmed because the template's own indentation is in there.
			if (name && link) {
				name.textContent = (link.textContent || '').trim();
				name.href = link.href;
			}
			if (body && lede) body.textContent = (lede.textContent || '').trim();

			if (tile) {
				tile.innerHTML = '';
				if (logo) {
					var img = document.createElement('img');
					img.src = logo.currentSrc || logo.src;
					img.alt = '';
					img.decoding = 'async';
					tile.appendChild(img);
				}
			}

			if (addr) {
				// "port 23" is localised, so it is carried whole from PHP rather
				// than reassembled here out of a number and a word.
				var sep = document.createElement('span');
				sep.className = 'sep';
				sep.textContent = '·';
				addr.textContent = card.getAttribute('data-host') || '';
				addr.appendChild(sep);
				addr.appendChild(document.createTextNode(card.getAttribute('data-portline') || ''));
			}

			// An anchor with no href is text, which is what a game with no
			// address worth linking should be. See mudlet_game_telnet_url().
			if (play) {
				if (telnet) play.setAttribute('href', telnet);
				else play.removeAttribute('href');
			}

			// The browser row is a link or it is nothing: unlike the address above,
			// which still reads as a fact with no client to hand it to, "play it in
			// Mudlet Web" with nowhere to go says nothing. Every synced game has
			// one, so this hides no row in practice and the panel keeps its height.
			if (gweb) {
				if (web) gweb.setAttribute('href', web);
				else gweb.removeAttribute('href');
			}
			if (browse) browse.hidden = !web;

			if (tags) {
				tags.innerHTML = '';
				Array.prototype.forEach.call(card.querySelectorAll('.gtag'), function (t) {
					var url = t.getAttribute('data-url') || '';
					var el = document.createElement(url ? 'a' : 'span');
					el.className = url ? 'gtag gtag--link' : 'gtag';
					el.setAttribute('data-tag', t.getAttribute('data-tag') || '');
					// Cloned rather than copied as text: the chip is an icon and
					// a word, and the icon is inline SVG the card already holds.
					Array.prototype.forEach.call(t.childNodes, function (n) {
						el.appendChild(n.cloneNode(true));
					});
					if (url) { el.href = url; el.target = '_blank'; el.rel = 'external nofollow noopener'; }
					tags.appendChild(el);
				});
			}
		}

		again.hidden = false;
		again.addEventListener('click', function () {
			var pool = cards.filter(function (c) { return !c.hidden; });
			if (pool.length < 2) pool = cards;

			var here = feat.querySelector('.gfeat__name a');
			var href = here ? here.href : '';
			var pick = pool.filter(function (c) {
				var a = c.querySelector('.gcard__name a');
				return a && a.href !== href;
			});
			if (!pick.length) pick = pool;

			draw(pick[Math.floor(Math.random() * pick.length)]);
		});
	})();

	// ── the screenshot carousel, the screencasts, and the lightbox ───────
	// A core gallery carrying the "Screenshot carousel" block style. Core drew
	// a grid of every image, each one linking to itself; this rearranges that
	// into one-at-a-time with arrows, dots and a full-size view, and does it
	// on the front end only. Nothing here runs in the editor and nothing here
	// is required for the block to make sense — with the script gone the page
	// still shows every screenshot and every click still reaches the full
	// image. See assets/css/blocks.css, where the whole carousel hangs off the
	// [data-carousel] this sets.
	//
	// The track is a scroll-snap scroller rather than a translated strip. That
	// is the one decision the rest of this follows from: the browser keeps the
	// position across a resize, a font swap and an image loading late, it
	// handles the touch gesture, and moving is a scrollTo rather than arithmetic
	// nothing else can see. What is left to do here is read which slide is
	// showing and say so.
	// The lightbox at the bottom of this serves both: the carousel opens it on a
	// screenshot, a screencast list opens it on the video. Same dialog, same
	// arrows, same counter — a visitor learns it once.
	(function () {
		var gals = document.querySelectorAll('#site .is-style-mudlet-carousel');
		var casts = document.querySelectorAll('#site .is-style-mudlet-screencasts');
		// The front page's row of thumbnails wants the same lightbox, so it has to
		// count towards this guard or the whole block never runs there.
		var shots = document.querySelectorAll('#site .shots');
		if (!gals.length && !casts.length && !shots.length) return;

		var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
		var DWELL = 5000;
		var box = null; // the lightbox, built once and shared by every gallery

		function svg(d) {
			var el = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
			el.setAttribute('viewBox', '0 0 24 24');
			el.setAttribute('fill', 'none');
			el.setAttribute('stroke', 'currentColor');
			el.setAttribute('stroke-width', '2');
			el.setAttribute('stroke-linecap', 'round');
			el.setAttribute('stroke-linejoin', 'round');
			el.setAttribute('aria-hidden', 'true');
			var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
			p.setAttribute('d', d);
			el.appendChild(p);
			return el;
		}
		var CHEV_L = 'M15 18l-6-6 6-6';
		var CHEV_R = 'M9 18l6-6-6-6';
		var CROSS = 'M18 6L6 18M6 6l12 12';

		function button(cls, label, d) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = cls;
			b.setAttribute('aria-label', label);
			b.appendChild(svg(d));
			return b;
		}

		// The full-size URL. linkTo:"media" is what the pattern and the seed
		// both set, so the anchor is normally there and is also the no-script
		// fallback; the srcset's own source is the backstop for a gallery
		// somebody linked to nothing.
		function full(slide) {
			var a = slide.querySelector('a[href]');
			var img = slide.querySelector('img');
			if (a && a.href) return a.href;
			return img ? (img.currentSrc || img.src) : '';
		}

		function caption(slide) {
			var cap = slide.querySelector('figcaption');
			return cap ? (cap.textContent || '').trim() : '';
		}

		// A YouTube watch URL turned into the embed for it, or '' for anything
		// that is not one. Anything else stays an ordinary link and navigates:
		// the day somebody adds a Vimeo or a write-up to that list, it works,
		// because nothing here claims a link it cannot actually play.
		//
		// youtube-nocookie.com, not youtube.com: it is the same player without
		// the cookie set on arrival, and the visitor has not asked to be counted
		// yet — they have asked to watch a video about aliases.
		function youtube(href) {
			var u;
			try { u = new URL(href, location.href); } catch (e) { return ''; }

			var host = u.hostname.replace(/^www\./, '');
			var id = '';
			if (host === 'youtu.be') {
				id = u.pathname.slice(1);
			} else if (host === 'youtube.com' || host === 'm.youtube.com' || host === 'youtube-nocookie.com') {
				var m = u.pathname.match(/^\/(?:embed|v|shorts|live)\/([^/]+)/);
				id = u.pathname === '/watch' ? (u.searchParams.get('v') || '') : (m ? m[1] : '');
			}
			if (!/^[\w-]{6,}$/.test(id)) return '';

			// rel=0 keeps the end screen on the same channel. The playlist is
			// carried through because three of these eight are one, and the
			// start time because a link into the middle of a video means it.
			var q = 'autoplay=1&rel=0';
			var list = u.searchParams.get('list');
			if (list) q += '&list=' + encodeURIComponent(list);
			var at = u.searchParams.get('t') || u.searchParams.get('start');
			if (at) q += '&start=' + encodeURIComponent(String(at).replace(/[^0-9]/g, ''));

			return 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?' + q;
		}

		// ── the lightbox ─────────────────────────────────────────────────
		// One <dialog>, built on the first open and reused. showModal() is what
		// makes it a lightbox: the top layer, the backdrop, the focus trap, the
		// rest of the page inert and Escape, none of which is written here.
		function lightbox() {
			if (box) return box;

			var dlg = document.createElement('dialog');
			dlg.className = 'mlb';
			dlg.setAttribute('aria-label', S.galLabel || 'Screenshots');

			var bar = document.createElement('div');
			bar.className = 'mlb__bar';
			var n = document.createElement('p');
			n.className = 'mlb__n';
			var x = button('mlb__btn mlb__x', S.galClose || 'Close', CROSS);
			bar.appendChild(n);
			bar.appendChild(x);

			var prev = button('mlb__btn mlb__arrow mlb__arrow--prev', S.galPrev || 'Previous', CHEV_L);
			var next = button('mlb__btn mlb__arrow mlb__arrow--next', S.galNext || 'Next', CHEV_R);

			var fig = document.createElement('figure');
			fig.className = 'mlb__fig';
			var img = document.createElement('img');
			img.alt = '';
			var frame = document.createElement('iframe');
			frame.className = 'mlb__frame';
			frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
			frame.referrerPolicy = 'strict-origin-when-cross-origin';
			frame.allowFullscreen = true;
			frame.hidden = true;
			fig.appendChild(img);
			fig.appendChild(frame);

			var cap = document.createElement('p');
			cap.className = 'mlb__cap';
			// Always an out. A video whose owner has disabled embedding plays
			// nowhere but on YouTube, and the player says so inside a box the
			// visitor cannot click past — so the way out is on our side of it,
			// not inside the frame.
			var out = document.createElement('a');
			out.className = 'mlb__out';
			out.target = '_blank';
			out.rel = 'noopener external';
			out.textContent = S.galWatch || 'Watch on YouTube';
			out.hidden = true;

			cap.appendChild(document.createTextNode(''));
			cap.appendChild(out);

			dlg.appendChild(bar);
			dlg.appendChild(prev);
			dlg.appendChild(next);
			dlg.appendChild(fig);
			dlg.appendChild(cap);

			// Inside #site, not on <body>: every colour in the sheet is a custom
			// property declared on #site, and the theme toggle sets its attribute
			// on <html>. A dialog parked outside would come up unstyled.
			(document.getElementById('site') || document.body).appendChild(dlg);

			box = {
				el: dlg, img: img, cap: cap, n: n,
				items: [], i: 0,
				show: function (i) {
					var it = this.items[(i + this.items.length) % this.items.length];
					if (!it) return;
					this.i = (i + this.items.length) % this.items.length;

					// Clearing the src is what stops the sound. Hiding an iframe
					// leaves it playing, and moving from video three to video
					// four has to end video three.
					frame.removeAttribute('src');
					// A picture takes the room it is given; a video keeps its own
					// shape and the dialog closes up around it. See theme.css.
					dlg.dataset.kind = it.embed ? 'video' : 'image';

					if (it.embed) {
						img.hidden = true;
						img.removeAttribute('src');
						frame.hidden = false;
						frame.title = it.cap;
						frame.src = it.embed;
					} else {
						frame.hidden = true;
						img.hidden = false;
						img.src = it.src;
						img.alt = it.alt;
					}

					this.cap.firstChild.textContent = it.cap;
					this.cap.hidden = !it.cap && !it.href;
					out.hidden = !it.href;
					if (it.href) out.href = it.href;

					this.n.textContent = (S.galCount || '%1$s / %2$s')
						.replace('%1$s', this.i + 1).replace('%2$s', this.items.length);
					// A gallery of one is a picture with a close button.
					prev.hidden = next.hidden = this.items.length < 2;
				},
				open: function (items, i, label) {
					dlg.setAttribute('aria-label', label || (S.galLabel || 'Screenshots'));
					this.items = items;
					this.show(i);
					if (!dlg.open) dlg.showModal();
				}
			};

			x.addEventListener('click', function () { dlg.close(); });
			prev.addEventListener('click', function () { box.show(box.i - 1); });
			next.addEventListener('click', function () { box.show(box.i + 1); });
			dlg.addEventListener('keydown', function (e) {
				if (e.key === 'ArrowLeft') { e.preventDefault(); box.show(box.i - 1); }
				if (e.key === 'ArrowRight') { e.preventDefault(); box.show(box.i + 1); }
			});
			// Clicking the ground closes it, the way every lightbox does. The
			// picture, the caption and the buttons are all elements of their own,
			// so a click that lands on the dialog itself landed on the backdrop
			// or on the padding around the figure — either is "not the picture".
			dlg.addEventListener('click', function (e) {
				if (e.target === dlg || e.target === fig) dlg.close();
			});
			// Closing has to stop the video, however it was closed - the button,
			// the backdrop or Escape, which never reaches this script at all.
			//
			// The guard is not paranoia: the close event is queued rather than
			// fired inline, so closing and opening again before it is delivered
			// - Escape, then a click on the next card - arrives after the new
			// video has already been put in the frame, and would tear down the
			// one that is playing.
			dlg.addEventListener('close', function () {
				if (dlg.open) return;
				frame.removeAttribute('src');
				frame.hidden = true;
			});

			return box;
		}

		Array.prototype.forEach.call(gals, function (gal) {
			var slides = Array.prototype.filter.call(gal.children, function (el) {
				return el.tagName === 'FIGURE';
			});
			if (!slides.length) return;

			var track = document.createElement('div');
			track.className = 'mgal__track';
			slides.forEach(function (sl) { track.appendChild(sl); });
			gal.insertBefore(track, gal.firstChild);

			var prev = button('mgal__arrow mgal__arrow--prev', S.galPrev || 'Previous', CHEV_L);
			var next = button('mgal__arrow mgal__arrow--next', S.galNext || 'Next', CHEV_R);
			var dots = document.createElement('div');
			dots.className = 'mgal__dots';

			// Dots are direct access, and they only work for as long as a
			// visitor can count them. At a dozen they are already a second
			// gallery under the first (see assets/css/blocks.css); at a hundred
			// they are eight rows of them on a phone and a hundred tab stops
			// between the picture and the rest of the page — and dot fifty-seven
			// was never really clickable anyway. So past the threshold they
			// become the counter the lightbox already draws, and this page has
			// one way of saying where you are rather than two.
			//
			// /media/ takes submissions from strangers (see the mudlet-shots
			// plugin, whose cap is on the review queue and not on the gallery),
			// so a gallery that outgrows its dots is the end state, not a
			// hypothetical.
			var DOTS_MAX = 12;
			var many = slides.length > DOTS_MAX;
			var count = null;
			var buttons = [];

			if (many) {
				count = document.createElement('p');
				count.className = 'mgal__count';
			} else {
				buttons = slides.map(function (sl, i) {
					var d = document.createElement('button');
					d.type = 'button';
					d.className = 'mgal__dot';
					d.setAttribute('aria-label', (S.galGo || 'Screenshot %s').replace('%s', i + 1));
					d.addEventListener('click', function () { surrender(); go(i); });
					dots.appendChild(d);
					return d;
				});
			}

			if (slides.length > 1) {
				gal.appendChild(prev);
				gal.appendChild(next);
				gal.appendChild(many ? count : dots);
			}
			// "Cropped" is the gallery's default and it is a grid's answer, not a
			// carousel's: core fills each tile with object-fit:cover, which on a
			// full-width frame means a screenshot with its edges cut off. The
			// class comes off rather than being out-specified, because core writes
			// those rules as :not(#individual-image) — an id, deliberately, to
			// make them hard to beat — and a stylesheet arms race over one class
			// that is simply the wrong class is a fight worth not having.
			gal.classList.remove('is-cropped');
			gal.setAttribute('data-carousel', 'on');

			var at = 0, timer = null, surrendered = reduce, ticking = false;
			var settle = null;

			// Core marks all but the first image lazy, which is right for a grid
			// and wrong for a strip: horizontally off-screen slides are not near
			// the viewport, so arrowing to slide nine would arrive on a blank.
			// The neighbours of wherever we are get loaded eagerly instead.
			function warm(i) {
				[-1, 0, 1].forEach(function (d) {
					var sl = slides[(i + d + slides.length) % slides.length];
					var img = sl && sl.querySelector('img[loading="lazy"]');
					if (img) img.removeAttribute('loading');
				});
			}

			function mark(i) {
				at = i;
				buttons.forEach(function (b, j) {
					b.setAttribute('aria-current', j === i ? 'true' : 'false');
				});
				if (count) {
					count.textContent = (S.galCount || '%1$s / %2$s')
						.replace('%1$s', i + 1).replace('%2$s', slides.length);
				}
			}

			function go(i) {
				i = (i + slides.length) % slides.length; // both ends wrap: it is a loop
				// Neighbours animate; anything further cuts. The reason is the
				// wrap: stepping off the last slide is one smooth scroll across
				// the entire strip, which on a long gallery is a whip-pan and —
				// because the scroll handler is what reads the position back —
				// used to warm every slide it flew past. A hundred screenshots
				// fetched by one press of Next.
				var far = Math.abs(i - at) > 1;
				track.scrollTo({ left: i * track.clientWidth, behavior: (reduce || far) ? 'auto' : 'smooth' });
				mark(i);
				warm(i);
			}

			function play() {
				if (surrendered || slides.length < 2) return;
				clearInterval(timer);
				timer = setInterval(function () { go(at + 1); }, DWELL);
			}
			function hold() { clearInterval(timer); }
			function surrender() { surrendered = true; hold(); }

			prev.addEventListener('click', function () { surrender(); go(at - 1); });
			next.addEventListener('click', function () { surrender(); go(at + 1); });

			// Reading the position back out of the scroller, rather than trusting
			// what we last asked for: a swipe, a shift-wheel and a focused image
			// scrolled into view all move it without going through go().
			track.addEventListener('scroll', function () {
				// Two jobs at two rates. Reading the position is free and wants
				// to be live, so the dots and the counter keep up with a finger
				// on the strip. Warming neighbours is the network, and wants the
				// scroll to have finished: warming once per frame means a fast
				// drag across a long gallery downloads everything it passed.
				if (!ticking) {
					ticking = true;
					requestAnimationFrame(function () {
						ticking = false;
						var w = track.clientWidth;
						if (w) mark(Math.max(0, Math.min(slides.length - 1, Math.round(track.scrollLeft / w))));
					});
				}
				clearTimeout(settle);
				settle = setTimeout(function () { warm(at); }, 150);
			}, { passive: true });

			// A hand on the strip is somebody driving it themselves.
			['pointerdown', 'wheel', 'touchstart'].forEach(function (ev) {
				track.addEventListener(ev, surrender, { passive: true });
			});
			gal.addEventListener('mouseenter', hold);
			gal.addEventListener('mouseleave', play);
			gal.addEventListener('focusin', hold);
			gal.addEventListener('focusout', play);

			// never cycle a gallery nobody is looking at
			if (window.IntersectionObserver) {
				new IntersectionObserver(function (entries) {
					if (entries[0].isIntersecting) play(); else hold();
				}, { threshold: 0.3 }).observe(gal);
			} else {
				play();
			}

			// The click that opens the full size. The anchor core wrote is the
			// no-script path and stays exactly where it is — this only gets in
			// front of it, so a middle click or ctrl-click still opens the image
			// in a tab, which is what those gestures mean on a link.
			track.addEventListener('click', function (e) {
				if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
				var sl = e.target.closest ? e.target.closest('figure.wp-block-image') : null;
				if (!sl) return;
				var i = slides.indexOf(sl);
				if (i < 0) return;
				e.preventDefault();
				surrender();
				lightbox().open(slides.map(function (s2) {
					var img = s2.querySelector('img');
					return { src: full(s2), alt: img ? img.alt : '', cap: caption(s2) };
				}), i);
			});

			mark(0);
			warm(0);
		});

		// ── the front page's three screenshots ───────────────────────────
		// Same lightbox as the gallery on /media/, so a visitor learns it once
		// rather than meeting two ways of looking at a screenshot on one site.
		// The anchor each thumbnail already is stays the no-script path and the
		// modifier-click path, exactly as the carousel's slides do: this only
		// gets in front of a plain left click.
		//
		// The caption comes off the image's alt, which the seed sets to the
		// game's name. That is why the thumbnails carry no caption of their own
		// and the lightbox still names what you are looking at.
		Array.prototype.forEach.call(shots, function (row) {
			var links = Array.prototype.slice.call(row.querySelectorAll('a.shot'));
			if (!links.length) return;

			row.addEventListener('click', function (e) {
				if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
				var a = e.target.closest ? e.target.closest('a.shot') : null;
				if (!a) return;
				var i = links.indexOf(a);
				if (i < 0) return;

				e.preventDefault();
				lightbox().open(links.map(function (l) {
					var img = l.querySelector('img');
					var name = img ? img.alt : '';
					return { src: l.href, alt: name, cap: name };
				}), i);
			});
		});

		// A screencast list plays where it is rather than sending the visitor to
		// YouTube and expecting them to find their way back. The anchor keeps
		// working for everything that is not a plain left click - a middle click
		// or ctrl-click still opens the video in its own tab, which is what
		// those gestures mean on a link, and with no script this list is exactly
		// the eight links it has always been.
		Array.prototype.forEach.call(casts, function (list) {
			var links = Array.prototype.filter.call(
				list.querySelectorAll('li > a[href]'),
				function (a) { return youtube(a.href); }
			);
			if (!links.length) return;

			var items = links.map(function (a) {
				return {
					embed: youtube(a.href),
					href: a.href,
					cap: (a.textContent || '').trim()
				};
			});

			list.addEventListener('click', function (e) {
				if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
				var a = e.target.closest ? e.target.closest('li > a[href]') : null;
				if (!a) return;
				var i = links.indexOf(a);
				if (i < 0) return;   // a link in the list that is not a video
				e.preventDefault();
				lightbox().open(items, i, S.galCasts || 'Screencasts');
			});
		});
	})();
})();
