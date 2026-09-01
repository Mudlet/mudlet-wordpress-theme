# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A redesigned mudlet.org: a classic WordPress theme in `wordpress/`, whose hero
embeds a real build of `@mudlet/mudlet-web` running a small offline MUD out of
`demo/` (a Vite/React app). Two halves, joined by one iframe and one
`postMessage`.

The theme grew out of a static prototype, which has been deleted now that the
theme is the product. The design lives in
`wordpress/theme/mudlet/assets/css/theme.css`, which used to be generated from
that prototype and is hand-authored now.

There is no test suite and no linter. The only static check is
`npx tsc --noEmit -p demo/tsconfig.json`, which covers `demo/src` only.

## Commands

```sh
# the demo client
cd demo && npm ci
npm run build            # -> demo/dist/ ; also rebuilds src/assets/*.mpackage
npm run package          # rebuild only the .mpackage (fast, for Lua edits)
npm run dev              # Vite dev server, client alone

# the site
cd wordpress && docker compose up -d   # -> http://localhost:8080

# zips for a test site
node wordpress/tools/build-dist.mjs    # -> wordpress/dist/*.zip
```

## Architecture

### The design lives in the theme, and only there

`wordpress/theme/mudlet/assets/css/theme.css` is hand-authored and is the whole
design, scoped to `#site`; `wp.css` loads after it for the rules that only make
sense once WordPress is drawing the page. **A change to how the site looks goes
in `theme.css`.**

(It was generated from a static `prototype/` until that became a second copy of
the design and was deleted. `013c8e5` is the last commit with it, if a comment
somewhere still points at it.)

### The games list is not ours to type

Which MUDs Mudlet bundles is a fact about the client: it lives in
`src/TGameDetails.h` upstream (`scmDefaultGames`), with the logos in
`src/icons/` beside it. The `mudlet-games` plugin reads that header over
`raw.githubusercontent.com` — no auth, and not under the API’s 60/hr cap —
parses the C++ and writes one post per game, logo attached, into the database.
Cron does it daily; `wp mudlet-games sync` does it now.

**Nothing in this repo holds a copy of that list, or of the logos**, and nothing
should: a checked-in copy of something read from upstream anyway only decides
how stale a new site starts out. Sync is the only way in, deliberately.

The front page's grid shows a random fifteen, chosen in the query. If you add a
card to the grid by hand you have misread this — add the game to Mudlet.

### Neither is the list of people

Same argument, same shape: who makes Mudlet is the `aboutMakers` vector in
`src/dlgAboutDialog.cpp` upstream — the credits behind Help → About — and
the `mudlet-makers` plugin reads it the way the games plugin reads the profiles,
and keeps nothing in the repo either. Thirty people, the eight currently on the
project flagged `core` because the dialog draws them larger. WordPress consumes
it at `/the-makers/`.

Two departures from a faithful copy, both deliberate: **email addresses are
dropped** — the dialog carries one for two thirds of these people, and a credits
page that prints them is a spam list — and **avatars are not from the Mudlet
repo**, they are `github.com/<handle>.png`. Twelve makers publish no handle and two more
have handles GitHub now 404s; all fourteen are drawn as initials, which is the
normal case rather than an error path.

### The one contract between the halves

`demo/src/main.tsx` polls for the console's first printed line and posts
`{ type: 'mudlet-demo:ready', ok }` to `window.parent`;
`theme/mudlet/assets/js/theme.js` listens for it. Until it arrives the hero
shows an opaque *scripted* terminal — hand-written HTML mimicking the same room
the world opens in — with the real iframe booting underneath. The swap is that
cover fading off an already-painted frame.

Both sides of that scripted fallback have to agree: if you change the demo
world's opening room, change the hero's static copy in
`template-parts/home/hero.php` too.

If the message never comes (slow frame, no build, or a second tab — a Mudlet
profile is single-owner across tabs and the second one reports `ok: false`),
the hero stays scripted after a 12s timeout. A page that "won't go live" is
usually one of those, not a bug.

### Same origin is a hard requirement

Mudlet Web keeps every profile in IndexedDB, which Safari and Firefox deny to
cross-origin frames, and its VFS service worker needs a secure context. So the
page and the client must share an origin. That is why the theme frames its own
`assets/demo/index.html` — `demo/dist` bind-mounted in — rather than pointing
at wherever a client build happens to be hosted. Do not "simplify" it to a CDN
or a second host.

### Two iframe hazards already paid for

Both are documented at length in `theme/mudlet/assets/js/theme.js`;
re-deriving them is expensive.

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
profile playable. The package version is **derived**, not typed:
`build-package.mjs` hashes the Lua it zips onto `config.lua`'s number and
writes the result into both the packaged `config.lua` and the generated
`src/assets/mudlet-demo.version.ts` that `src/main.tsx` imports, so an edited
world reaches returning visitors without anyone bumping two files. It also
substitutes the world's `local SCRIPT_LINES` line with the real line count,
which is what the terminal on the plinth quotes at the visitor.

**The world is a directory of modules, not one file.** An `.mpackage` is
unzipped into `<profile>/<packageName>/` and Mudlet Web seeds `package.path`
with that directory, so the Lua ships as files that `require` each other by
path. `init.lua` is the entry; the generated XML carries only the catch-all
alias, `embed.lua` and a three-line bootstrap that requires the package. One
file per concern — `core.lua` (the palette, the two kinds of link, the one
`say()`), `urls.lua`, `site.lua` and `seed.lua`, `download.lua`,
`people.lua` (the sage's ledger), `github.lua` (the clerk), `map.lua`,
`verbs.lua`, `boot.lua`, and `rooms/<name>.lua` one per room, assembled by
`rooms/init.lua`. The build globs `**/*.lua` and ships everything it finds,
but a file nothing requires is dead weight — reach a new module from one that
already loads. Because the Lua is on disk rather than pasted into a script
node, an error names the file and the line: `mudlet-demo/rooms/home.lua:47`.

**The map is derived, not drawn.** A room's square, its id and the lines to its
neighbours all come out of one walk in `map.lua`: breadth-first from the front
page, one square per exit, with `up`/`down` stepping on the same z so the vault
reads as a cellar. The exits are declared once, in the room. **Adding a
connected room is a file in `rooms/`, a line in `rooms/init.lua`, and the exit
in the room it hangs off — nothing in `map.lua`.** If you find yourself typing a
coordinate you have misread this. Exits that contradict each other, point at
nothing, or leave a room unreachable are reported to the debug console and the
map is drawn without them, because the visitor must never see it.

**The bar over the console is the world's, not the page's.** The room you are in,
the ways out of it and the `map` pill are Geyser labels the package draws into the
strip `setBorderTop` reserves. The exits are clickable, and they are the same
directions the console prints because both come out of one `D.look()`: it raises
`core.ROOM_EVENT` and `map.lua` listens. **`raiseEvent`, not `raiseGlobalEvent`** —
the display and the world are one package in one profile, so nothing crosses a
frame and no page-side listener exists. An earlier attempt put the exits in the
hero's HTML title bar and reached them over the BroadcastChannel behind
`raiseGlobalEvent`, which is a cross-profile bus borrowed for a job it does not
mean. `demo/README.md` has the drawing rules, and every one of them is a
consequence of a label being unable to measure its own text.

**The facts in the prose are not typed either.** The version chalked on the
vault wall, the crate weights and hashes, the notices on the board, the count
of boxed worlds and the size of the ledger come from one request the world
makes while the console animates its connect — `GET /wp-json/mudlet/v1/demo`,
answered by `wordpress/theme/mudlet/inc/demo-seed.php` out of the same plugins
the pages use. `SITE` in `site.lua` is both the shape of that
answer and the fallback for everywhere there is no WordPress to ask
(`npm run dev`, a `file://` copy). If you find yourself typing a version or
a count into the world's prose, add it to the endpoint instead.

The one exception is the Workshop, north of the commons, where a clerk answers
`ask about this week` and `ask about issues` out of `api.github.com` — no token,
no server of ours in between — at the moment the question is put. That is not a
fact about the site and cannot come from the seed: it is out of date by the time
the page has finished loading. Every way it can fail has a line in the clerk's
own voice carrying the link to the page it could not read, GitHub's rate limit
included, which the clerk owns up to. See `github.lua`, and
`demo/README.md`.

`demo/README.md` is thorough on the rest — how the embed strips the toolbar
and login, the mapper, the link colour rules, the `say()` output path. Read it
before changing anything in `demo/`.

## The site: `wordpress/`

A classic PHP theme, plus a Docker stack that boots a local mudlet.org with
menus, categories and news already in place, and every page created empty for
somebody to write.

```sh
cd wordpress && docker compose up -d     # -> http://localhost:8080
node wordpress/tools/build-dist.mjs      # -> wordpress/dist/*.zip, for a test site
```

`wordpress/README.md` is thorough. The parts worth knowing before touching
anything:

- **`theme/mudlet/assets/css/theme.css` is the design, and it is
  hand-authored.** It was lifted from the prototype's one `<style>` block by a
  tool until the theme became the product; the tool is gone and the file is
  edited here now. Everything in it is scoped to `#site`. WordPress-only rules
  go in `assets/css/wp.css`, which loads after — that split is worth keeping:
  `theme.css` is how the site looks, `wp.css` is what WordPress makes it do.
- **`assets/js/theme.js` was forked once** from the prototype's script block
  and is the theme's own from here on. Its header lists what changed in the
  fork, because the shapes it explains are still in the file.
- **Block CSS is a third stylesheet, and it has no `#site`.** A release post is
  written in Gutenberg, and the editor canvas has neither `#site` nor `.prose`,
  so `theme.css` cannot reach it. `assets/css/blocks.css` is hand-written,
  class-scoped, and loaded *twice* — on the page and, through
  `add_editor_style()` in `inc/setup.php`, in the canvas. The price is that
  `#site .prose …` outranks it on the front end; those collisions, and only
  those, live at the end of `wp.css`. `assets/css/editor.css` restates the
  custom properties for the canvas, because they are declared on `#site`.
- **Two shapes a release post needs are core blocks, not ours.** The feature
  panel and the highlights card are a `register_block_style` over
  `core/media-text` and `core/group` plus a pattern each, in `inc/blocks.php`.
  Nothing there stores data, and a block that stores prose is a paragraph with
  extra steps. The one real block is `mudlet/games`, and it lives in the games
  plugin — see below.
- The homepage sections are template markup, not database content — the copy is
  load-bearing on the layout, and the point of this exercise is to stop the
  design living in `wp_options`. Pages, posts and menus *are* editable.
- **The games grid comes from Mudlet, not from anyone typing.** One
  `mudlet_game` post per bundled profile, created by
  `wordpress/plugin/mudlet-games/` from the same upstream header, with the
  logo as the featured image. A plugin for the same reason as releases: a
  theme rewrite must not take forty pages with it. The theme reads it through
  `function_exists()` guards in `inc/games.php`, and with the plugin gone the
  front page simply has no games section — there used to be fifteen games typed
  into the theme for that case, which is the exact thing the plugin replaces,
  so an admin notice says why instead. `/games/` and `/games/<slug>/` are theme
  templates over the same posts. It also owns the **`mudlet/games` block**, so
  an announcement can introduce the games a release added without anybody
  retyping four cards: it stores slugs and resolves them at render time through
  `template-parts/blocks/games.php`. It does not guess which games — deriving
  that from the changelog and from `TGameDetails.h`'s history were both tried
  and both cost more than picking four from a list. In the plugin because
  WordPress renders an unregistered dynamic block as *nothing* — a games block
  owned by the theme would silently blank that section in every past post the
  day the theme changed. See `wordpress/plugin/mudlet-games/README.md`.
- **`/games/` is a showcase, and its facets are derived like everything else.**
  A panel for one game picked per request, then all forty-three as cards
  carrying the blurb, filtered in the browser — the whole list is already on
  the page, so filtering is hiding rather than fetching, and the haystack is
  each card's own `textContent` (which is why the tail of every blurb ships
  hidden inside the card rather than as a second copy in a data attribute). The
  panel's *another* button rebuilds itself out of a card already in the
  document, which is what the cards' `data-host` / `data-portline` /
  `data-telnet` and the tags' `data-url` are for. Every row of that panel is a
  fixed number of lines tall on purpose: it swaps games in place, and a panel
  that resized would throw the grid under it around the screen. The only two
  filters are the connection flags, because how a game connects is a fact about
  playing it and where it keeps its forum is not — everything else people
  choose a MUD by is prose, and the search box reads the whole blurb.
  `inc/games.php` holds the derivations; nothing about a game is typed into
  `archive-mudlet_game.php`.
- **"Play in Mudlet" is a `telnet://` link, and means it.** Mudlet registers
  itself for `telnet://` and `telnets://` — `mudlet.desktop` declares both as
  `x-scheme-handler` MIME types and `src/main.cpp` hands the URI to a running
  instance — so the client comes up already connected. `mudlet_game_telnet_url()`
  builds it and picks the scheme off the profile's TLS flag; sending a secure
  profile to plain `telnet://` would connect it in the clear. Two traps:
  `esc_url()` allows `telnet` but **not** `telnets`, so every call site passes
  `mudlet_telnet_protocols()` or a fifth of the links come out empty; and a port
  is an identifier, not a quantity, so it never goes through
  `number_format_i18n()` — that is where "port 4,000" comes from.
- **The makers roster comes from Mudlet too.** One `mudlet_maker` post per
  person the client credits, created by `wordpress/plugin/mudlet-makers/` from
  `src/dlgAboutDialog.cpp`, with the GitHub avatar as the featured image.
  `/the-makers/` stays an editable page — the theme's `page-the-makers.php`
  draws the roster under its prose, and an **Also credited** editor below it on
  the same screen holds anybody the client's credits miss (three of the live
  page's names have never been in `dlgAboutDialog.cpp`) — and each person gets
  `/the-makers/<name>/`. There is deliberately **no** hardcoded fallback list:
  a typed list of people is the exact thing this replaces, and the live site's
  fifteen-year-old version of that page is the evidence. See
  `wordpress/plugin/mudlet-makers/README.md`.
- **All three record types are read-only in wp-admin.** A `mudlet_game`, a
  `mudlet_maker` and a `mudlet_release` are observed, not authored: every field
  is rewritten by the next sync, so each plugin replaces the post editor with a
  record screen (no inputs, a resync button) and guards writes in
  `wp_insert_post_data` so read-only holds on REST and Quick Edit too. Release
  **announcement posts** are ordinary posts and are not affected, and so is the
  prose on `/the-makers/` above the roster.
- **Releases come from GitHub, not from anyone typing.** A release post needs a
  tag; `wordpress/plugin/mudlet-releases/` turns it into the changelog, the
  counts, and the download table's sizes, URLs and SHA-256 hashes. That is a
  plugin and not theme code on purpose: a release post's body *is* the GitHub
  changelog, so putting it in the theme would mean the next theme rewrite blanks
  every release post. The theme reads it through `function_exists()` guards in
  `inc/github-releases.php` and degrades to hardcoded figures without it. Runs
  alongside the upstream
  [release plugin](https://github.com/Mudlet/mudlet-release-plugin), which owns
  the webhook; the seed patches one bug in it. It also runs the other way:
  `wp mudlet-releases markdown <post>`, and a **Markdown for GitHub** panel
  under the editor, render a release post's *authored* prose back out as the
  Markdown for the GitHub release - everything the theme generates, the
  changelog included, is left out, because the release already carries it. See
  `wordpress/plugin/mudlet-releases/README.md`.
- **The download page hands the build to another machine, and the mail is not
  the live site's. The build is chosen by which row you press.** Every `.dlrow`
  carries two icon buttons and a drawer they slide open: a QR of *that* build's
  URL and a copy button, read out of the row's own link — that half came from
  the prototype with `theme.css`, encoder and all (~200 lines of byte-mode QR,
  written out because a single static file had no build step to pull a library
  through, and forked into `theme.js` like the rest of that script). There is
  deliberately **no picker**: a select under the table
  restating four rows the visitor is looking at was the first shape of this and
  is the thing the drawers replace. Rows are independent — two open drawers is
  someone comparing two builds — and one drawer shows one face at a time, which
  is what `data-open` and `data-face` on it are. It slides on a `0fr`→`1fr` grid
  track so no height is ever measured or guessed; the clipped box inside gets
  4px of side padding and −4px of margin, because that clip would otherwise cut
  the focus ring off the field at its left edge. The second face is the form
  that mails the link, **only in WordPress**: markup in `page-download.php`,
  rules in `wp.css` because a static page has nowhere to send an address,
  endpoint in `inc/download-email.php`. The
  browser posts a **build key, never a URL** — the live site's
  `mudlet-dl-link.php` mails whatever link the page hands it, which is a way to
  send mail from mudlet.org with a stranger's link inside — and nothing the
  visitor types reaches the message. No captcha and no nonce (a nonce is only
  as fresh as the page cache in front of it): a honeypot, a cap per IP and per
  address, and `mudlet_download_email_verify` for a site that wants one anyway.
- The hero points at `assets/demo/`, which is `demo/dist` bind-mounted in. Same
  origin, for the reasons below. Without the build the theme leaves the hero
  scripted rather than framing a 404. A rebuild reaches the container without a
  restart — Vite empties `dist/` rather than replacing the directory, so the
  mount survives it. (This was once written down as needing
  `docker compose restart wordpress` on Windows; measured on Docker Desktop
  with Vite 8 it does not, so try a reload before reaching for that.)
- **The contact page draws Discord rather than embedding it, and its form is a
  slot.** `/contact/` is `page-contact.php`: two panels over a row of link
  cards. The Discord one is the theme's own markup fed by two anonymous
  endpoints — `widget.json` for who is online and their avatars,
  `/invites/<code>?with_counts=true` for the member total the widget omits —
  cached ten minutes in `inc/discord.php`. **Not** Discord's iframe widget: that
  is a fixed dark 350×500 box with its own type and its own button, and
  everything it draws is in the JSON behind it anyway. No name is ever printed —
  the people in the member list did not choose to appear on mudlet.org, a server
  admin enabled a widget — and no count is ever invented: Discord unreachable
  leaves a plain invite button, which is what the page had before. The form
  panel, on the left, is a disabled placeholder until a contact form plugin
  fills it; paste
  the shortcode into the **Contact form** box on the page and `inc/contact.php`
  runs it, falling back to the placeholder when it renders as its own source.
  Deliberately plugin-agnostic, and deliberately not in the page body — the body
  is prose at full measure, and the form belongs beside Discord. The address
  under it is shown either way, so it does not vanish with the placeholder, and
  it is **text through `antispambot()`, not the live site's PNG of it** — the
  PNG is the stronger obfuscation (entities fall to any HTML parser; a picture
  needs OCR), but it protects an address already sitting in plain text in
  `src/dlgAboutDialog.cpp` and in every binary's Help → About, so it is a lock
  beside an open wall charging the full price of one — unselectable,
  uncopyable, unreadable by a screen reader. Stronger than either, when it
  matters: publish no address once the form plugin is in. It comes from the
  `mudlet_contact_email` option, not from `admin_email`, which only backstops
  it: the address a site publishes and the address that can reset it are not
  one fact.
- **The demo world reads the site through one endpoint.**
  `inc/demo-seed.php` registers `GET /wp-json/mudlet/v1/demo` and answers with
  the current release, the games, the makers and the latest posts, all through
  the same `function_exists()` seams the templates use. It is theme code and
  not a plugin on purpose: unlike the data it serves it owns nothing, so there
  is nothing in it for a theme rewrite to take with it.

## Not in the repo

`reference/` is a local scrape of the live mudlet.org used for recon
(`FINDINGS.md`, page copies). It is gitignored and nothing in the build needs
it — `wordpress/theme/mudlet/assets/img/` holds the images the pages actually
reference.
