# mudlet.org redesign

A static prototype of a redesigned mudlet.org front page, with a real Mudlet
running in its hero — [`@mudlet/mudlet-web`](https://www.npmjs.com/package/@mudlet/mudlet-web)
built as a four-room MUD you can type into.

```
prototype/index.src.html   the page source, with {{IMG:name}} asset placeholders
prototype/assets/          the 22 images those placeholders name
prototype/build.js         inlines them as data: URIs
prototype/index.html       the built, self-contained page  (committed)
demo/                      the embedded client — see demo/README.md
```

## Run it

Two pieces: the demo is built, the page is already built. Node 22.

```sh
cd demo
npm ci
npm run build            # -> demo/dist/  (also rebuilds the .mpackage world)
node scripts/serve.mjs   # serves the repo root on :8765
```

Then open **<http://localhost:8765/prototype/index.html>**.

The hero shows a scripted terminal until the client reports it has printed its
first line, then cross-fades to the live session. If it stays scripted, the
frame either never loaded or is already open in another tab — a Mudlet profile
is single-owner across tabs, so a second tab falls back to the poster on
purpose.

`npm run dev` in `demo/` gives you the Vite dev server for iterating on the
client alone; the page's iframe points at the built `dist/`, not at it.

### Why a server, and why the repo root

The hero embeds the demo at `../demo/dist/index.html` — relative, and
**same-origin**. Mudlet Web keeps every profile in IndexedDB, which Safari and
Firefox deny to a cross-origin iframe, so the page and the client have to be
served from one origin with that relative hop intact. `scripts/serve.mjs`
serves the repo root for exactly that reason. Opening `prototype/index.html`
over `file://` gets you the page but not the live client.

## Rebuild the page itself

```sh
node prototype/build.js   # index.src.html + assets/ -> index.html
```

Everything it reads is in the repo, and it is deterministic — rebuilding
without touching the source reproduces the committed `index.html` byte for
byte. Edit `index.src.html`, never `index.html`.

Images go in as `src="{{IMG:name}}"`, where `name` is a path under
`prototype/assets/`. The placeholder has to sit inside a `src="..."`
attribute — that is the only shape the build rewrites, and it warns about any
it finds elsewhere. Each asset is emitted once into a dictionary at the end of
the document and swapped in by a small loader, so repeating one costs nothing.

`prototype/news.src.html` is a second page in progress. It has no build target
yet; opened directly it rewrites its own placeholders against `assets/` with
an inline script.

## Deploying

`.github/workflows/pages.yml` publishes to GitHub Pages on every push to
`master`: it builds `demo/`, then lays the site out to mirror this repo so the
iframe's relative hop keeps resolving —

```
/index.html            redirect to ./prototype/
/prototype/index.html  --(../demo/dist/index.html)-->  /demo/dist/index.html
```

Nothing in the built HTML is rewritten; the deployed tree is what
`scripts/serve.mjs` serves locally. Set **Settings → Pages → Source** to
**GitHub Actions** to enable it.
