# demo/ — the live client in the homepage hero

A real build of [`@mudlet/mudlet-web`](https://www.npmjs.com/package/@mudlet/mudlet-web),
stripped to an embeddable session: no toolbar, no login, no server. The world is
a Lua package (`packages/mudlet-demo/`) installed into the profile on first
open, so the whole thing is a static site — no telnet proxy, nothing to run.

```
npm install
npm run build          # -> dist/  (also rebuilds the .mpackage)
node scripts/serve.mjs # the client on its own at :8765
                       # the site serves it from the theme - see ../README.md
```

`npm run dev` for the Vite dev server; `npm run package` rebuilds only the
`.mpackage`.

## How the three "make it an embed" bits work

**No login.** `brand.mud` is what switches Mudlet Web into branded mode (one
profile, no picker), so the config pins a MUD it never dials —
`AutoLanding` calls `openProfile(id, false)` on mount, which is the stock
login screen's "Open offline" path. Nothing connects; every command typed is
answered by Lua.

**No title bar.** `BrandConfig.toolbar` can hide individual *buttons*, not the
bar — the root `.mudix-toolbar` still carries the wordmark, profile name,
status dot and hamburger. `src/embed.css` removes it (and the fullscreen
reveal strip) outright. This is the one hack here that wants an upstream fix:
an `embed`/`chrome: false` flag in `BrandConfig` would replace both this and
the dummy `mud` target above.

**The world.** `packages/mudlet-demo/` plus a catch-all alias, zipped
into a `.mpackage` by `scripts/build-package.mjs` and listed in
`brand.packages` — which *replaces* the stock defaults, so the mapper doesn't
come along. Aliases run in `hostSend` whether or not a session is connected,
which is what makes an offline profile playable.

The package is a **directory of Lua modules**, not one file. mudlet-web unzips
an `.mpackage` into `<profile>/<packageName>/` and seeds `package.path` with
`<profile>/?.lua;<profile>/?/init.lua`, so the files `require` each other by
path and the generated XML carries only three things that cannot be files: the
catch-all alias, `embed.lua`, and a bootstrap that clears `package.loaded` and
requires the package.

| file | what is in it |
| --- | --- |
| `init.lua` | the entry: the requires, in the order the world reads |
| `core.lua` | the palette, the two kinds of link, `say()`, numbers into words |
| `urls.lua` | every link that leaves the world |
| `site.lua` | `SITE` — the shape of the seed's answer, and the fallback |
| `seed.lua` | the one request that replaces what it can reach |
| `download.lua` | the crates and the orange button — one release, described twice |
| `rooms/init.lua` | assembles `D.rooms` from one file per room |
| `rooms/*.lua` | six rooms, one file each |
| `people.lua` | `MAKERS`, and everything the sage says out of it |
| `github.lua` | the clerk: the only thing here that talks to anything but the site |
| `map.lua` | the mapper, the status bar, and the room ids they share |
| `verbs.lua` | the parser and the verbs behind it; everything arrives at `D.input` |
| `boot.lua` | the fake connect, and the first room on the other side of it |

Two things follow from the files being real files. A Lua error names the file
and the line it happened on — `mudlet-demo/rooms/home.lua:47` — which a script
node holding two thousand lines cannot do. And a version bump wipes the package
directory before unzipping, so a module you delete is actually gone from a
returning visitor's profile.

Shared state lives on the global `demo` table (`D` in every file): anything a
visitor can reach — `D.input`, `D.look`, `D.tell`, `D.week` — is late-bound
there, which is what lets the alias, the timers and the clickable links call
into the world without any module having to require the one that defines them.
Everything else is a module export, imported at the top of the file that needs
it, so what a file depends on is the first thing you read in it.

Mudlet's own `run-lua-code` **is** installed (copied out of `node_modules` at
package-build time, so it tracks the installed library), which gives the demo
a `lua <code>` command — the shortest proof that the thing in the page is a
real Lua runtime and not a transcript. Only the first matching permanent alias
fires, so the catch-all carries a negative lookahead for `lua ` rather than
matching everything; a bare `lua` still falls through to the world's normal
reply. Both patterns live in `scripts/build-package.mjs`.

A profile whose installed copy claims a different version reinstalls the
package on open, which is how an edited world reaches a returning visitor.
That version used to be a number bumped by hand in two files, and forgetting
either meant editing the world, rebuilding, reloading and reading the old
world back. The build derives it instead: `config.lua`'s number plus a hash
of the Lua being zipped, written into the packaged `config.lua` and into
`src/assets/mudlet-demo.version.ts`, which `src/main.tsx` imports. Edit the
world and the version moves; rebuild an untouched world and it does not.
Bump the number in `config.lua` for a release, not for an edit.

`src/embed.css` also shrinks the command bar and sets the console type to the
hero terminal's own 12.48px/1.8, so the swap from the scripted session to the
live one doesn't move the text. The font *family* still differs — the page
uses IBM Plex Mono, the client Mudlet's Bitstream Vera Sans Mono.

## The world itself

It is mudlet.org, walked instead of scrolled. Six rooms — the front page, the
release vault (`down`), the news room (`north`), the commons (`west`), the
Makers Hall beyond it and the workshop north of it — each standing in for a part
of the site, each thing in them linking out to the real page it parodies.
`look windows` is the download page's Windows row, sha256 and all; `read board`
is the three latest posts; the commons is the forum, wiki, Discord, GitHub and
package-repository links, behind doors.

In Makers Hall a sage keeps the ledger of everyone who ever built Mudlet:
`ask about vadi`, `ask about everyone` (thirty names in two columns), or click
any name. The sage is the one living thing in the world and is listed the way a
MUD lists one — its own sentence under the room's `Here:` line, in its own
colour, rather than among the furniture. That colour is also the one it speaks
in: the sage answers out loud, and speech has a colour of its own — everything between the quotes is
Mudlet's own words about that person, and the narration around it is the world's.
Under each answer sits their GitHub handle and a way back to the ledger, labelled
with how many people are still in it. The thirty entries and
their order are Mudlet's own `aboutMakers` list from the client's About box, cut
to a line or two each and carrying the public GitHub handle where there is one —
the email addresses in that list are deliberately not copied here, and every
entry links out to mudlet.org/the-makers for the full text.

Two kinds of clickable text, one rule: **underlined means clickable, orange
means it leaves the site.** Every noun, exit and suggested command prints as a
link, so the whole world is playable with a mouse, which is what most visitors
will try first in a hero. `say()` in `core.lua` is the single output path —
adjacent strings coalesce into one `decho`, because both `decho` and
`dechoLink` reset the format before printing and a colour tag passed as its own
argument would be reset away by the very next call.

**The bar and the map.** `setBorderTop(BAR_H)` reserves a 30px strip across the
top of the console and Geyser labels furnish it: the strip itself, the room you
are in on the left, the ways out of it after that, and a `map` pill on the right.
Reserved rather than overlaid, so console text never runs under any of it. All of
it is the package's own doing — border plus labels, the way any Mudlet package
would build a HUD.

The exits are there because the console's own `Exits:` line scrolls away with the
room that printed it, and in a hero most visitors click before they type. They are
plain text in the colour that line uses, with no border or fill: a row of buttons
across the top would be louder than the world underneath it.

Three things about drawing them are not obvious.

**Nothing sets a vertical offset.** A Mudlet label centres its own text — TLabel's
default is `Qt::AlignLeft | Qt::AlignVCenter` — so a label given the bar's full
height is centred in it by doing nothing at all. The bar used to carry a
`padding-top` on two of its labels, which was fighting that default rather than
helping it.

**A label cannot be asked how wide its text came out**, in Mudlet or in Mudlet
Web, so the row is placed by counting characters against a 6.6px advance. Two
things follow. The name gets a fixed column, as wide as the longest room title in
the world rather than as wide as this room's — otherwise walking from the Release
Vault to Makers Hall slid the exits 40px left under the cursor. And every box
carries the 4px a label insets its text by before drawing it: Qt gives a
`QTextDocument` that `documentMargin` and Mudlet Web reproduces it, so a box
measured for the glyphs alone loses the last character's right-hand pixels — which
on `up` is most of the `p`.

**The bar follows the world by event.** `D.look()` raises `core.ROOM_EVENT` with
the room's name and its exits, and `map.lua` listens with
`registerAnonymousEventHandler`, so a verb can announce where the visitor is
without knowing a bar exists. `raiseEvent` and not `raiseGlobalEvent`: this stays
inside the profile, and nothing outside the client is listening. In `look()`
rather than `enter()` because `boot()` opens the first room with a bare `look()`
and never calls `enter()`, so a bar filled from the latter would stay empty until
the visitor moved.

Behind the pill is Mudlet's own mapper: the real widget, the real map database,
`centerview` following you from room to room, floating in the corner over the
text. Six rooms is a tiny map, but it is the same mapper a twelve-thousand-room
game drives, and "a real mapper" is one of the six claims the page makes two
sections down. The vault is `down`/`up` only — it sits one square below the
front page so it reads as a cellar, and the mapper draws the stair markers with
no line, because a line would say you can walk south into it. The workshop goes
north of the commons, inside the bounding box the other five already made, so
adding it did not change the framing. Zoom is set so all six rooms stay in frame
from *any* of them, not just from the middle.

**None of that layout is written down.** `map.lua` walks the rooms breadth-first
from the front page, one square per exit, so where a room is drawn is a
consequence of how you reach it — and the exits are already declared once, in
the room that has them. `up` and `down` step a square too, on the same z, which
is exactly what puts the vault under the front page while its exit stays a
stair. Room ids are derived the same way, from the sorted room names; renumbering
is safe because the area is torn down before it is rebuilt and `deleteArea`
takes its rooms with it. Adding a connected room is therefore a file in
`rooms/`, a line in `rooms/init.lua`, and the exit in the room it hangs off —
no coordinate, no id, no map exit.

Four things can go wrong in that walk, and all four are mistakes in the exits
rather than anything a visitor can do: an exit to a room that does not exist, an
exit in a direction the map cannot step in, two paths that disagree about where
a room is, and a room nothing leads to. Each is reported to the debug console —
`mudlet-demo map: ...`, which surfaces in the browser's own console — and the
map is drawn anyway, minus whatever could not be placed. A hero has no business
showing anybody a stack trace, and most of a map beats none of one.

It starts closed. Console height is the scarce thing in a homepage hero, and a
map that opens itself costs the visitor space before they have asked for
anything; closed it costs one 34px pill. **This also used to be forced:** below
900px Mudlet Web routed the embedded mapper into its phone layout's tab strip,
and a blanket `.floating-window-root { display: none }` in the same media query
hid the nested overlay root that mini-consoles portal into — so `createMapper`
produced a "Mapper" tab and no map. Both are fixed upstream (`MobileLayout.tsx`
and `App.css`); a client older than that fix will still tab it.

Closing collapses the mapper to 0×0, which is how `Geyser.Mapper` hides an
embedded mapper. `closeMapWidget()` is no help — it hides the *dockable* map
widget, a different window entirely.

The mapper's own panel toolbar is hidden in `src/embed.css`. That toolbar is not
per-map: `MapPanel` always renders it, and what varies is `mapperPanelVisible`,
a per-profile field that collapses it to a ▾ arrow for every map view at once.

In-world links run `expandAlias`, not `send`: `send` goes straight at a socket
that isn't there, and it is the catch-all alias that makes an offline profile
answer at all. The installer crates are the one exception to "taking a thing
opens it" — those URLs are 130 MiB, and a `heavy` thing (or a `crate`, which
is the same rule wearing the current release's label) hands over the link
instead of firing `openUrl`, rather than dropping an installer into the
downloads tray because somebody typed a word at a demo.

### The world asks the site what it says

The prose is written; the facts inside it are not. The version chalked on the
vault wall, the four crate weights and hashes, the notices on the board, the
number of boxed worlds on the shelf and the size of the ledger all come from
one request, made while the console animates its fake connect:

```
GET /wp-json/mudlet/v1/demo
```

The other end is `wordpress/theme/mudlet/inc/demo-seed.php`, which answers out
of the same plugins the pages are drawn from — so the vault and `/download/`
cannot disagree about a release, and the shelves and the games grid cannot
disagree about how many games there are. The URL is site-relative because the
frame is served from the site's own origin anyway; a second spelling
(`/?rest_route=…`) is tried when the first fails, since `/wp-json/` needs
pretty permalinks.

`SITE`, in `site.lua`, is both the shape of that answer and the
fallback: the July 2026 snapshot the rooms were composed against. It has to be
a fallback, because the demo also runs from a Vite dev server and `file://`
copies, neither of which has a WordPress behind it. There
the request fails, the world keeps what is written, and the visitor is told
nothing — a hero has no business showing an error.

Two consequences worth knowing:

- **The first room waits for it, briefly.** `D.boot()` fires the request with
  the first dot and prints the room when the animation has run its 1.5s *and*
  the answer has landed or `SEED_WAIT` has passed. Worst case the console says
  `connecting…` for three seconds; where there is no site to ask, the request
  fails at once and none of that is spent.
- **Descriptions that quote a fact are functions**, evaluated at print time, so
  an answer that arrives late is still right the next time somebody looks.

The ledger is seeded too, prose included. `MAKERS` in `people.lua` keeps the
names, the nouns the sage answers to and the GitHub handles; the sentence it
says about each person comes from the site, which is the About dialog by way of
the makers plugin. Somebody the hall has never heard of is seated rather than
met with "not in this ledger", and who is core developer comes across as well,
so the eight at the front of the ledger are the eight the dialog draws large.
Matching is on the full name, not on the sage's deliberately loose `keys`.

One entry is marked `own` and keeps its written line: it describes Mudlet Web as
the thing the visitor is standing in, which is a joke only available from inside
the demo — there is no upstream version of it to take instead.

The hero's scripted fallback, in the theme's `template-parts/home/hero.php`,
shows the same room this world opens in, so the two halves don't disagree when
the frame never loads.

## The one thing it fetches live

Everything above arrives once, at boot, from the site. The workshop is the
exception, and deliberately so: what landed this week is not a fact about
mudlet.org, it is a fact about the repository, and it is out of date by the time
the page has finished loading. So the clerk at the slanted desk asks GitHub
directly, from the visitor's browser, at the moment the question is put —
`ask about this week` and `ask about issues`, or `look book` and `look board`,
which are the same two answers behind clickable nouns.

```
GET https://api.github.com/repos/Mudlet/Mudlet/commits?per_page=100&since=<7d>
GET https://api.github.com/search/issues?q=repo:Mudlet/Mudlet+is:issue+is:open
GET https://api.github.com/search/issues?…+label:"good first issue"
```

No token, and no server of ours in the middle: `api.github.com` allows any
origin, so this works from a `file://` copy as readily as from WordPress, and mudlet-web falls back to its own proxy for any origin that
refuses a direct fetch. Open issues are counted through the search API rather
than the repo endpoint's `open_issues_count`, which adds pull requests to the
total.

Notes on the shape of it, all of them in `github.lua`:

- **One request in flight**, keyed on its url, with an 8s timeout — the
  browser's fetch has none of its own, and a hung request would otherwise leave
  the clerk mid-sentence for good. A second question replaces the first rather
  than queueing behind it.
- **Every failure has a line, and two of them are different.** GitHub's rate
  limit (403, or 429 on the secondary one) gets the clerk admitting they have
  said their sixty for the hour; everything else gets the wire being down. Both
  carry the link to the page they could not read, so a visitor who gets the
  apology is one click from the answer.
- **Answers are kept for five minutes.** Asking twice is what people do to a
  live number, and the second ask should cost the room nothing.
- **An answer that lands after you have left the room is dropped**, the same
  rule the sage's greeting follows.
- **Hands and machines are counted apart.** GitHub types dependabot as a `Bot`
  and `mudlet-machine-account` as an ordinary user, so both are matched by name
  as well — "from seven hands and two machines" is the interesting half of the
  number.

## Deploying it

`dist/` is a static site (`base: './'`, so it can sit at any path).

**It must be served from the same origin as the page that frames it.** Mudlet
Web keeps each profile in IndexedDB, and Safari and Firefox deny storage to
third-party frames — a demo hosted on GitHub Pages and embedded in
mudlet.org would fail to open a profile there. It also needs a secure context
(HTTPS or localhost) for the VFS service worker.

The hero creates the frame after `load`, in idle time, *underneath* an opaque
terminal showing "mudlet web / connecting…" — so the client renders, boots and
paints out of sight, and the swap is that cover fading off an already-painted
frame rather than a frame fading up out of nothing. The cover lifts only when
the frame posts `mudlet-demo:ready`, which it sends once the console has
actually printed. If that never comes — the frame is slow, fails, or can't
load at all (the Artifact preview, `file://`) — the hero falls back to its
scripted session.

That handshake is the one contract between the two halves of this: `src/main.tsx`
posts `{ type: 'mudlet-demo:ready', ok }` to `window.parent`, and the theme's
`assets/js/theme.js` listens for it.
