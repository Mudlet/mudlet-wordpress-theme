# demo/ — the live client in the homepage hero

A real build of [`@mudlet/mudlet-web`](https://www.npmjs.com/package/@mudlet/mudlet-web),
stripped to an embeddable session: no toolbar, no login, no server. The world is
a Lua package (`packages/mudlet-demo/`) installed into the profile on first
open, so the whole thing is a static site — no telnet proxy, nothing to run.

```
npm install
npm run build          # -> dist/  (also rebuilds the .mpackage)
node scripts/serve.mjs # serves the repo root at :8765
                       # http://localhost:8765/prototype/index.html
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

**The world.** `packages/mudlet-demo/world.lua` plus a catch-all alias, zipped
into a `.mpackage` by `scripts/build-package.mjs` and listed in
`brand.packages` — which *replaces* the stock defaults, so the mapper doesn't
come along. Aliases run in `hostSend` whether or not a session is connected,
which is what makes an offline profile playable.

Mudlet's own `run-lua-code` **is** installed (copied out of `node_modules` at
package-build time, so it tracks the installed library), which gives the demo
a `lua <code>` command — the shortest proof that the thing in the page is a
real Lua runtime and not a transcript. Only the first matching permanent alias
fires, so the catch-all carries a negative lookahead for `lua ` rather than
matching everything; a bare `lua` still falls through to the world's normal
reply. Both patterns live in `scripts/build-package.mjs`.

Bump `version` in **both** `packages/mudlet-demo/config.lua` and the
`BrandPackage` in `src/main.tsx` to push a new world to returning visitors —
a profile whose installed copy has a different version reinstalls on open.
Forget this and you will edit `world.lua`, rebuild, reload, and read the old
world back, because the installed copy still claims the same version.

`src/embed.css` also shrinks the command bar and sets the console type to the
hero terminal's own 12.48px/1.8, so the swap from the scripted session to the
live one doesn't move the text. The font *family* still differs — the page
uses IBM Plex Mono, the client Mudlet's Bitstream Vera Sans Mono.

## The world itself

It is mudlet.org, walked instead of scrolled. Five rooms — the front page, the
release vault (`down`), the news room (`north`), the commons (`west`) and the
Makers Hall beyond it — each standing in for a part of the site, each thing in
them linking out to the real page it parodies. `look windows` is the download
page's Windows row, sha256 and all; `read board` is the three latest posts; the
commons is the forum, wiki, Discord, GitHub and package-repository links, behind
doors.

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
will try first in a hero. `say()` in `world.lua` is the single output path —
adjacent strings coalesce into one `decho`, because both `decho` and
`dechoLink` reset the format before printing and a colour tag passed as its own
argument would be reset away by the very next call.

**The bar and the map.** `setBorderTop(BAR_H)` reserves a 30px strip across the
top of the console and three Geyser labels furnish it: the strip itself, the
room you are in on the left, and a `map` pill on the right. Reserved rather than
overlaid, so console text never runs under the button. All of it is the package's
own doing — border plus labels, the way any Mudlet package would build a HUD.

Behind the pill is Mudlet's own mapper: the real widget, the real map database,
`centerview` following you from room to room, floating in the corner over the
text. Four rooms is a tiny map, but it is the same mapper a twelve-thousand-room
game drives, and "a real mapper" is one of the six claims the page makes two
sections down. The vault is `down`/`up` only — it sits one square below the
front page so it reads as a cellar, and the mapper draws the stair markers with
no line, because a line would say you can walk south into it. Zoom is set so all
four rooms stay in frame from *any* of them, not just from the middle.

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
opens it" — those URLs are 130 MiB, and a `heavy` thing hands over the link
instead of firing `openUrl`, rather than dropping an installer into the
downloads tray because somebody typed a word at a demo.

Content — versions, weights, hashes, headlines — is typed out at the top of
`world.lua`, copied from the live site (4.22.0, 6 July 2026). Feeding it from
the page's own release and post data later is a change to those tables, not to
the machinery under them.

The hero's scripted fallback in `prototype/index.src.html` shows the same room
this world opens in, so the two halves don't disagree when the frame never
loads.

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
posts `{ type: 'mudlet-demo:ready', ok }` to `window.parent`, and
`prototype/index.src.html` listens for it.
