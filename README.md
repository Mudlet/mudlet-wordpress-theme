# mudlet.org redesign

A redesigned mudlet.org as a real WordPress theme, with a real Mudlet running
in its hero — [`@mudlet/mudlet-web`](https://www.npmjs.com/package/@mudlet/mudlet-web)
built as a six-room MUD you can type into.

```
wordpress/         the theme, three plugins, and a Docker stack that boots a
                   local mudlet.org with menus, categories and news in place
demo/              the embedded client and its world — see demo/README.md
```

## Run it

Two pieces: the site in Docker, the client built once on the host. Node 22.

```sh
cd demo
npm ci
npm run build                # -> demo/dist/  (also rebuilds the .mpackage world)

cd ../wordpress
docker compose up -d         # -> http://localhost:8080
docker compose logs -f seed  # watch it provision
```

Admin at `/wp-admin`, `admin` / `admin`. `demo/dist` is bind-mounted into the
theme as `assets/demo/`, so the hero comes up live once it exists; without it
the hero stays on its scripted terminal rather than framing a 404.

The hero shows that scripted terminal until the client reports it has printed
its first line, then cross-fades to the live session. If it stays scripted, the
frame either never loaded or the profile is already open in another tab — a
Mudlet profile is single-owner across tabs, so the second one falls back on
purpose.

`npm run dev` in `demo/` gives you the Vite dev server for iterating on the
client alone; the theme's iframe points at the built `dist/`, not at it.

### Why the client is served from the theme

Mudlet Web keeps every profile in IndexedDB, which Safari and Firefox deny to a
cross-origin iframe, and its VFS service worker needs a secure context. So the
page and the client have to share an origin — which is why the build is mounted
into the theme rather than hosted somewhere else and linked.

## Where the data comes from

Nothing on this site restates a fact that lives somewhere else:

| | |
|---|---|
| the bundled games | `src/TGameDetails.h` in Mudlet, via the `mudlet-games` plugin |
| the credits | `src/dlgAboutDialog.cpp`, via `mudlet-makers` |
| releases, changelogs, hashes | the GitHub releases API, via `mudlet-releases` |
| the demo world's prose | the site itself, over `GET /wp-json/mudlet/v1/demo` |

`wordpress/README.md` and `demo/README.md` are both thorough. Read the one for
the half you are about to touch.
