# Things the demo world could grow

Parked ideas for `demo/packages/mudlet-demo/`, written down rather than built.
Each one is here because it teaches something the world does not teach yet — a
room that only adds furniture is not worth the lines.

What is already spoken for, so nothing below repeats it: seven rooms standing in
for the site's pages, a sage reading a fixed ledger, a clerk reading GitHub live,
an imp counting `_G`, a trigger in the Workshop, an alias in the Stacks, a
kettle on the Workshop bench for the timer. Between them those three cover every
direction a client points in, so a fourth is not the thing to look for.

---

## 1. The visitors' book — proof the profile is real

**An object**, on the front page or in the commons. `sign` writes your name in
it; `read book` lists who has signed. The trick is that it is still there
tomorrow: `getMudletHomeDir()` is a real directory in a real profile, and
`table.save`/`table.load` put a real file in it.

**What it teaches.** The one thing about this hero that a static page could not
fake, and that nothing in `demo/` currently demonstrates: there is a profile.
Mudlet Web keeps it in IndexedDB, it survives a reload, it is single-owner across
tabs — which is also the reason a second tab reports `ok: false` and leaves the
hero scripted (see `theme.js`). A visitor who signs the book, reloads the page,
and finds their own name is told all of that without a word of explanation.

**Nothing typed.** The dates come from `os.date`, the count from the file.

### The side twist: sign it for real

The version above is a diary — it never leaves the browser, and the second
visitor sees an empty book. The twist is to make it a **guest book**: `sign`
POSTs to the site the hero is framed in, and `read book` shows who else has
signed. Same origin, same `mudlet/v1` namespace the seed already uses, so there
is nothing new to plumb — `inc/demo-seed.php` is the neighbour it would sit
beside, and the world already knows how to make a request and degrade when there
is no WordPress behind the frame (`npm run dev`, a `file://` copy).

That is a much better ending and a much worse endpoint, and the difference is
worth writing down before anyone starts:

- **It is an unauthenticated write from the busiest page on the site.** The seed
  endpoint is `permission_callback => __return_true` and that is fine because it
  only reads. A route that stores what a stranger types is a different animal:
  rate limit per IP the way `inc/download-email.php` already does, cap the length
  hard, one entry per IP per day, and store it as an option or a transient rather
  than as posts — a spammed custom post type is somebody's afternoon.
- **Somebody will type something vile into it, on the front page, in a hero.**
  So it cannot be read back verbatim. Two ways out, and the second is better:
  hold entries unpublished until a human approves them (which means somebody has
  to look, forever), or **never print what was typed at all** — store the name,
  print only the count and the shape of it. "Four hundred and eleven people have
  signed this book, eleven of them today" is a true sentence, needs no
  moderation, and is arguably the better line anyway: it makes the visitor one of
  a number rather than one of a list.
- **A name is personal data.** If nothing is ever printed back, do not store it
  either — count the signature and drop the name. Then the endpoint holds an
  integer, there is nothing to leak, nothing to moderate, and no GDPR question to
  answer. The local half above still keeps *your* name, on your own disk, where
  it belongs.

So: local file for the visitor's own name and their own visits, remote counter
for the crowd. Both halves honest, neither half a liability. If that still feels
thin, the count is a fact the site could show elsewhere too.

**Cost.** The local half is an evening: one object, `table.save`/`table.load`, a
`sign` verb. The remote half is a REST route, a rate limiter and a decision from
whoever owns the site about storing anything a stranger types.

---

## 2. The lantern — Geyser, built in front of you

**An object.** `light the lantern` and a real Geyser gauge or mini-console
assembles itself over the console, live, followed by the six lines that did it.
`douse it` takes it away again.

**What it teaches.** The UI layer, which is Mudlet's actual differentiator and
the thing the 5.0 notes lead on — and the demo currently builds Geyser only for
*itself* (the bar over the console in `map.lua`) and never hands it over. A
visitor watching a health bar appear out of six lines of Lua has understood what
"scriptable client" means in a way no amount of prose achieves.

**The hazards, all in `map.lua`.** The top border is spoken for: `setBorderTop`
reserves 30px and the bar, the room name, the exits and the `map` pill live in
it. A lantern must take another edge or float, and it must not fight the mapper
widget for the corner. Nothing here is near the iframe rules in `theme.js` — this
is all inside the frame — but a label that cannot measure its own text still
cannot, so the same character-counting arithmetic applies (`demo/README.md` has
the drawing rules and every one of them is a consequence of that).

**What would make it good rather than clever.** The gauge has to be attached to
something. A number that means nothing moving on a bar is a screensaver. Options:
the kettle's `remainingTime`, which is already a real countdown and would tie the
two objects together; or the walk in `map.lua`, which already steps room to room
on a timer.

**Cost.** The largest of the three here, and the only one with a layout risk.

---

## 3. The cellar below the cellar — the package reading itself

**A room**, `down` from the Release Vault. Shelves of the world's own modules,
one crate per file under `mudlet-demo/`, each stencilled with its line count. The
terminal on the plinth already boasts that every line the visitor types is
answered by "a Lua package 3,854 lines long"; this is where you go to open it.

**Nothing typed, as usual.** `scripts/build-package.mjs` already counts the Lua it
zips and rewrites `SCRIPT_LINES` in `core.lua` on every build. Per-file counts are
the same walk — it globs `**/*.lua` already — written into the generated
`mudlet-demo.version.ts` or a second generated constant, so the shelves cannot
disagree with what shipped. If you find yourself typing a line count you have
misread this.

**Adding it is three edits**, and the map takes care of itself: a file in
`rooms/`, a line in `rooms/init.lua`, and `down = 'cellar'` in `rooms/vault.lua`.
`map.lua` walks the exits and works out the square — `up`/`down` step on the same
z, so it would read as a second cellar under the first, which is exactly the
joke.

**What it is for.** Nothing, and that is allowed once. It is the easter egg: the
world admitting what it is made of, to the one visitor in a thousand who tries
`down` twice. It costs one file and carries no risk, which is the argument for
building it on a slow afternoon rather than not at all.
