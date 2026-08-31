# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A static prototype of a redesigned mudlet.org front page, whose hero embeds a
real build of `@mudlet/mudlet-web` running a small offline MUD. Two halves —
`prototype/` (hand-written HTML) and `demo/` (a Vite/React app) — joined by
one iframe and one `postMessage`.

There is no test suite, no linter, and no framework in `prototype/`. The only
static check is `npx tsc --noEmit -p demo/tsconfig.json`, which covers
`demo/src` only.

## Commands

```sh
# the demo client
cd demo && npm ci
npm run build            # -> demo/dist/ ; also rebuilds src/assets/*.mpackage
npm run package          # rebuild only the .mpackage (fast, for world.lua edits)
npm run dev              # Vite dev server, client alone

# the page
node prototype/build.js  # index.src.html + prototype/assets/ -> index.html

# run the whole thing
node demo/scripts/serve.mjs      # repo root on :8765
# -> http://localhost:8765/prototype/index.html
```

`serve.mjs` serves the **repo root**, not `prototype/`. That is deliberate:
see the same-origin constraint below. `file://` gets you the page but a dead
hero.

## Architecture

### The page is generated — edit the source, not the output

`prototype/index.html` is a build artifact and is committed anyway (the
Pages workflow deploys it as-is). **Never edit it.** Edit
`prototype/index.src.html` and re-run `node prototype/build.js`.

The build inlines every image as a `data:` URI so the page is a single
self-contained file. Images are written `src="{{IMG:name}}"` where `name` is a
path under `prototype/assets/`; the placeholder must sit inside a `src="..."`
attribute, which is the only shape the regex rewrites (it warns about strays).
Each asset lands once in a dictionary at the end of the document and a small
loader assigns it, so repeating an image is free — per-occurrence inlining
previously ballooned the page to 7.8 MB.

The build is deterministic: rebuilding untouched sources reproduces the
committed `index.html` byte for byte, which is a cheap way to confirm you have
not desynced source and output.

`prototype/news.src.html` is a second page in progress with no build target;
it resolves its own placeholders against `assets/` via an inline script marked
"standalone only".

### The one contract between the halves

`demo/src/main.tsx` polls for the console's first printed line and posts
`{ type: 'mudlet-demo:ready', ok }` to `window.parent`;
`prototype/index.src.html` listens for it. Until it arrives the hero shows an
opaque *scripted* terminal — hand-written HTML mimicking the same room the
world opens in — with the real iframe booting underneath. The swap is that
cover fading off an already-painted frame.

Both sides of that scripted fallback have to agree: if you change the demo
world's opening room, change the hero's static copy in `index.src.html` too.

If the message never comes (slow frame, `file://`, or a second tab — a Mudlet
profile is single-owner across tabs and the second one reports `ok: false`),
the hero stays scripted after a 12s timeout. A page that "won't go live" is
usually one of those, not a bug.

### Same origin is a hard requirement

The hero points at `../demo/dist/index.html` — relative, and same-origin.
Mudlet Web keeps every profile in IndexedDB, which Safari and Firefox deny to
cross-origin frames, and its VFS service worker needs a secure context. So the
page and the client must share an origin with that relative hop intact. This
is why `serve.mjs` serves the repo root, and why
`.github/workflows/pages.yml` mirrors the repo layout onto Pages
(`/prototype/index.html` + `/demo/dist/`, root being a redirect) instead of
flattening the page to the site root. Do not "simplify" that layout.

### Two iframe hazards already paid for

Both are documented at length in `index.src.html`; re-deriving them is
expensive.

- **Never re-parent the frame.** Moving a node containing an iframe reloads
  it, which drops the session. The expand-to-fullscreen interaction pins the
  panel with `position: fixed` and only changes its box.
- **Never transform an ancestor of the frame.** Chrome then cannot paint the
  iframe's scrolling content — the console comes up blank with its lines still
  in the DOM. The FLIP animation therefore transforms a stand-in box while the
  real panel waits hidden at its destination.

### The demo world

`demo/packages/mudlet-demo/` is a Mudlet package (Lua) zipped by
`scripts/build-package.mjs` and installed into the profile on first open — a
catch-all alias answers every command, which is what makes an *offline*
profile playable. **Bump `version` in both `packages/mudlet-demo/config.lua`
and the matching `BrandPackage` in `src/main.tsx`** or returning profiles keep
the old world and your edits appear to do nothing.

`demo/README.md` is thorough on the rest — how the embed strips the toolbar
and login, the mapper, the link colour rules, the `say()` output path. Read it
before changing anything in `demo/`.

## Not in the repo

`reference/` is a local scrape of the live mudlet.org used for recon
(`FINDINGS.md`, page copies). It is gitignored and nothing in the build needs
it — `prototype/assets/` holds the images the pages actually reference.
