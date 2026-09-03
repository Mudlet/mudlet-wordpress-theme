# wordpress/ — the theme

A classic PHP theme, and a Docker stack that boots a mudlet.org with it: pages,
menus, categories and news already in place. This is the product; the static
prototype it grew out of is history, and nothing here reads it any more.

```sh
cd wordpress
cp .env.example .env          # optional — every value has a default
docker compose up -d
docker compose logs -f seed   # watch it provision
```

Then <http://localhost:8080>, admin at `/wp-admin` (`admin` / `admin`).

`docker compose down` stops it and keeps the database; `down -v` throws the
database away, and the next `up` provisions from scratch.

## The three pieces

| | |
|---|---|
| `theme/mudlet/` | the theme, bind-mounted into the container — edit on the host, reload the browser |
| `seed/` | WP-CLI provisioning: the pages (empty), menus, categories, languages, news |
| `plugin/mudlet-releases/` | release data, read from GitHub releases |
| `plugin/mudlet-games/` | the bundled games, read from Mudlet’s own source |
| `plugin/mudlet-makers/` | the credits, read from Mudlet’s own About dialog |
| `plugin/shared/` | one menu, the sync schedules, and the seams that let a plugin run from the theme — carried by all three |

The three plugins **ship inside the theme**, under `plugins/`, and the stack
mounts them there rather than into `wp-content/plugins` so that the local site
runs the same arrangement a real one does. They are still plugins in every
other sense — the data they own is in the database, and a copy installed the
old way still wins over the theme’s. See `theme/mudlet/inc/bundled-plugins.php`
for why, and *Shipping it to another site* below for what that means for an
install.

## The stylesheet

`theme/mudlet/assets/css/theme.css` is the design, and it is hand-authored.
It was generated out of the prototype's one `<style>` block until the theme
became the product; that tool is gone, and the file is edited here now.
Everything in it is scoped to `#site`.

WordPress-specific rules — core block classes, `paginate_links`' own class
vocabulary, the admin bar — live in `assets/css/wp.css`, which loads after it.
That split is worth keeping even now that both are hand-written: `theme.css` is
how the site looks, `wp.css` is what WordPress makes it do.

`assets/js/theme.js` was **forked once** from that same prototype's script block
and is the theme's own from there. Its header lists what changed in the fork,
because the shapes it explains are still in the file.

## What is editable, and what is not

Honest answer, because this is the thing that went wrong with Divi:

- **Editable in wp-admin**: every page's body, all news posts, the header and
  footer-project menus, categories, the release version and download URLs
  (option `mudlet_release`), per-post release details (the "Release details"
  box in the post editor, which draws the version panel), and the contact
  form shortcode (the "Contact form" box on `/contact/`). `/media/` is the
  furthest end of that: the screencasts and the whole screenshot gallery are
  blocks in the page body, so all of it is Gutenberg — see **Media** below.
- **Not editable — it is theme markup**: the homepage sections. The hero
  headline, the six feature panels, the games grid, the "hop in" cards. The copy
  there is load-bearing on the layout, so moving it into the database on day one
  would recreate a smaller version of the problem this replaces. The parts are
  split along the seams where that would happen (`template-parts/home/*.php`).
- **Not editable — it comes from GitHub**: the release figures, the games grid,
  and the makers roster. Which MUDs Mudlet bundles and who Mudlet credits are
  facts about the client, so the plugins below read them rather than offering a
  screen to type them into. All three stores show their records on a
  purpose-built read-only screen — all three under one **Mudlet** menu, as
  **Games**, **Makers** and **Releases** — with a write guard behind it, so
  read-only holds on REST and Quick Edit too. The resync button is over each
  **list**, because a games or makers sync is always all of them, and because a
  plugin that has just been activated has no records to hang a button on.
  (Releases keep one on the record as well: those are read one at a time.)
- **How often each one refreshes is on `Mudlet → Sync`.** Every cron job the
  three plugins run, with its cadence, when it last ran, when it runs next and
  a button to run it now. Weekly by default — a bundled-games list moves a few
  times a year — and `Never` turns one off. The screen is one shared file,
  `plugin/shared/mudlet-sync.php`, that each plugin carries a copy of and each
  registers its own jobs into; see its header before editing it. The release *announcement posts*
  are ordinary posts and stay editable, as is the prose on `/the-makers/` above
  the roster.

## Getting the real content in

Without an export the seed writes ten placeholder posts so the templates have
something to render. To load the real news:

```sh
# 1. on the live site: wp-admin -> Tools -> Export -> All content -> Download
# 2. put the file in seed/export/ (gitignored, never imported directly)
node tools/filter-wxr.js seed/export/mudlet.WordPress.2026-08-31.xml
# 3. re-run the seed
docker compose up seed
```

### Filter it first — this is not optional

An "All content" export is not just posts. mudlet.org's carries:

| dropped | why |
|---|---|
| 33 `flamingo_contact` + 15 `flamingo_inbound` | **Contact Form 7 submissions — real names and email addresses.** Personal data that should not leave the production server, let alone land in git. |
| 28 `page` | bodies are `[et_pb_section]` soup that renders as nothing without Divi |
| 16 `et_pb_layout`, 8 `shortcoder`, 3 `wpcf7_contact_form`, 2 `custom_css` | dead plugin records |
| 49 `nav_menu_item` | points at the Divi pages we are not importing |

`tools/filter-wxr.js` keeps posts and attachments plus the four Polylang
taxonomies (`language`, `post_translations`, `term_language`,
`term_translations` — drop any of them and five languages flatten into one) and
writes the result to `seed/wxr/`, which is what the seed imports. Raw exports
stay in `seed/export/`, which is gitignored.

It parses with a CDATA-aware scanner rather than a regex, because post bodies
legitimately contain things like `</item>` and a line-anchored pattern cuts the
file in the wrong place.

### The Divi bodies come through as they are

Eight items in the export — five distinct articles, the rest translations, plus
one draft release template — still carry `[et_pb_*]` shortcodes in the body.
Nothing converts them: they reach `seed/wxr/` as written, and
`inc/divi-cleanup.php` strips the unregistered tags at display time so a reader
never sees `[et_pb_text]` in the middle of an announcement. Turning them into
blocks properly is still to do.

The 28 Divi *pages* are dropped rather than kept, so the modules that make Divi
conversion miserable are not part of that problem — what is left is `section`,
`row`, `column`, `text`, `heading`, `image`, `button` and `testimonial`.

### What that import actually produced

279 published posts across five languages — 185 en, 42 zh, 39 de, 15 it, 9 ru —
with categories and translation links intact. Attachments are skipped by default
(the export carries URLs, not files, so fetching them means hundreds of requests
at the live site); set `IMPORT_MEDIA=1` when you want the images.

Two things worth knowing about the result, neither a bug in this theme:

- **43 posts have slugs like `mudlet-4-22-0-5`.** The Italian and Chinese
  translations of a release both want `mudlet-4-22-0`; that collision is
  resolved the same way on the live site, and these slugs are what it serves.
- **Category slugs are per-language**: `release-en`, `release-de-de`,
  `release-zh-zh`, not a single `release`. The theme maps them onto the design's
  three colour families by leading word (`mudlet_category_family()`, filterable),
  so every translation of a category gets its English twin's colour.

The seed refuses to create its own categories or placeholder posts when an
export is present, and deletes any placeholders left from an earlier run before
importing — they hold the slugs the real posts want, and WordPress resolves that
by renaming the incoming post, not the squatter.

A `mysqldump` would carry more fidelity, but it is an opaque blob nobody can
review, and the Divi site's whole problem is that its design lives in
`wp_options`. The script is diffable; keep it that way.

## Releases

Release announcements are not ordinary posts, and the theme does not treat them
as such. Two pieces are involved, and they do different jobs.

### The release plugin — required

[`Mudlet/mudlet-release-plugin`](https://github.com/Mudlet/mudlet-release-plugin),
installed and activated by the seed. It owns the post itself:

- a GitHub `release` webhook creates the announcement in **every** Polylang
  language, links the translations, and stamps each with a `release-post` meta
  key holding the GitHub release id;
- a `[MudletRelease]<id>[/MudletRelease]` shortcode renders the release's
  changelog Markdown as the post body, via Parsedown.

**It is not optional if you import real news.** Twenty-one of the imported posts
have nothing in `post_content` but that shortcode. Without the plugin they
render as a bare number.

#### One upstream bug, patched locally

The webhook writes the release **id** into the shortcode, but the shortcode
reads it back as a **tag name**:

```php
'post_content' => '[MudletRelease]' . $result->id . '[/MudletRelease]'   // 378895178
GetHttpWrapper::get(GITHUB_API_URL . "releases/tags/$content")           // 404
```

`releases/tags/378895178` does not exist; `releases/378895178` does. On
mudlet.org this never surfaces, because the same webhook also calls
`set_transient()` **with no expiry** — the rendered body is cached forever and
the fallback is never reached. A fresh install has no transients, so every
imported release post falls straight through it and shows *"Can't get releases
post for 378895178"*.

`seed/setup.sh` patches the installed copy (drop `tags/`), idempotently and only
if the buggy string is present. `SEED_PATCH_RELEASE_PLUGIN=0` to see the
unpatched behaviour. **The real fix is one line upstream** — worth doing, since
it is also what makes the plugin survive a transient flush in production.

### `plugin/mudlet-releases` — the data, in this repo

**A release post needs a tag. Everything else follows from it** — release notes,
changelog, counts, and the download table's sizes, URLs and checksums. Set
`Mudlet-4.22.0` or just `4.22.0` on a post and it supplies the rest; a tagged
post with an empty body renders the release's notes.

The changelog and the counts come from **the pull requests merged since the
previous release**, not from the release body — the body is a summary somebody
wrote, and 5.0's has no structure to parse at all. Mudlet squash-merges, so each
commit title carries its PR title and number, and one walk of the compare
endpoint gives a categorised changelog with no per-PR requests. Checked against
Mudlet's published 4.21 figures it reproduces *fixed* and *added* exactly and
*improved* within one.

That costs several requests per release, so it is only ever done on a single
post view — an archive uses cached figures or falls back to the body. Watch the
60-an-hour ceiling on unauthenticated GitHub; the plugin README covers setting a
token.

It runs backwards too. A release announcement is written once, in the editor,
and `wp mudlet-releases markdown <post>` - or the **Markdown for GitHub** panel
under the post - hands back the Markdown for the GitHub release: the prose
somebody wrote, with the changelog, the contributors and the download table
left out, since the release carries its own.

It is a real plugin, bind-mounted like the theme, written to be lifted into
`Mudlet/mudlet-release-plugin` later. `plugin/mudlet-releases/README.md` has the
API and the reasoning; the short version:

- **Data belongs in a plugin.** A release post's body *is* the GitHub changelog,
  and the download table's hashes are facts about a release. Both would die with
  the theme if the theme owned them.
- **Legacy ids upgrade themselves.** Imported posts store a release id, not a
  tag; the first read resolves it and writes the tag back.
- **`[MudletRelease]` is only claimed when the upstream plugin is inactive**, so
  the two never fight — and dropping that plugin does not blank 21 posts.

The theme reads it through `inc/github-releases.php`, which is now nothing but
`function_exists()` guards. With the plugin deactivated the download page falls
back to the 4.22.0 figures in `inc/downloads.php` and the release panel to a
manual "Release details" box, and an admin notice says so — silently showing a
hardcoded version is exactly the staleness nobody notices for months.

Verified against the real data: `4.22.0` gives *1 new feature, 2 improvements,
9 fixes*; the download table serves 5.0.0 with all four SHA-256 hashes matching
`SHA256SUMS.txt` byte for byte. Those hashes are read off each asset’s own
`digest` in the releases JSON rather than out of that file — same number, no
request — and the file is the fallback for releases too old to carry one.

### Carrying the download to another machine

The live site has this as wp-downloadmanager's *Send this link via E-mail?* box,
one under every tab of the download page: an address field, a reCAPTCHA, and a
post to `/mudlet-dl-link.php` carrying the address, the token, and the download
URL read out of the page. Here it is a panel under the table — a QR of one
build's URL, a **Copy link** button, and an **Or have it emailed** form — and
the whole thing is built out of the rows above it, so nothing in it names a
version, a size or a platform.

The QR came with `theme.css` from the prototype; the form could not have,
because a static page has nowhere to send an address, so its markup is in
`page-download.php` and its rules are the one hand-written block in `wp.css`
that has no counterpart in `index.src.html`.

Three things about the mail, all of them departures from the live version:

- **The browser posts a build key, never a URL.** `inc/download-email.php`
  looks the URL up again in `mudlet_release_builds()`. An endpoint that mails
  whatever link the page hands it is a way to send mail from mudlet.org with a
  stranger's link inside.
- **Nothing the visitor types reaches the message.** The address is a
  recipient; it is never a line in the body.
- **No captcha, and no nonce either.** A nonce is printed into the page, so it
  is only as fresh as the cache in front of it, and the first symptom of that
  is a form that works for a logged-in editor and fails for everyone else.
  Standing in for both: a honeypot field, five sends an hour per IP and three a
  day per address, and `mudlet_download_email_verify` for a site that wants a
  captcha in front after all. `mudlet_download_email_enabled` turns the whole
  thing off — a site with no working mailer should, rather than let the form
  fail after somebody has typed into it.

Exercised against the running stack: a URL in place of a build key and a
malformed address are both refused, a filled honeypot is answered as if it had
been sent, the fourth request for one address and the sixth from one IP are
429s, and the message that goes out carries the version, the platform, the size
and the SHA-256 of the build the select was showing.

## Games

The logo grid on the front page, `/games/`, and a page per game are all one
thing: a `mudlet_game` post per connection profile Mudlet bundles, read from
the client’s own `src/TGameDetails.h` by `plugin/mudlet-games`. Nobody types a
game — see that plugin’s README for the parser, the two ways in, and why it
uses raw.githubusercontent.com rather than the API.

The theme’s half is small: `inc/games.php` is the `function_exists()` seam and
`template-parts/home/games.php` draws **a random fifteen** of the forty-three
per request, because a fixed fifteen means the same fifteen games get all the
attention for as long as the page exists. The "+28 more" tile goes to `/games/`,
which lists all of them alphabetically on one page.

Deactivate the plugin and the section is not drawn at all, with an admin notice
saying why. There was a fallback here once - fifteen games typed into
`inc/games.php`, their logos in the theme - and it was the thing the plugin
exists to replace: wrong the day a game is added upstream, and wrong quietly. A
missing section is a bug somebody reports.

Refresh the list and the logos with:

```sh
wp mudlet-games sync
```

The seed runs it once, cron runs it daily, and the record screen has a button.
Nothing in the repo holds a copy of the list or the logos to load instead: a
checked-in one only decides how stale a new site starts out.

### Contributors on a release post

Under the changelog, every person whose work merged since the previous
release — avatar, name and commit count, all of them, because a credits list
that stops at "and 15 others" is worse than none. The data comes from the
releases plugin at no request cost; the theme half is
`template-parts/post/contributors.php` and the `.credits` block in the
stylesheet.

The block is deliberately **not** called `.who`: that class already exists in
this design as the byline author name (`.byline .who`), and an unscoped
`.who` rule turned every byline on the site into a bordered card. It is also
doubled up as `.credits .credits__body li` — the block sits inside `.prose`,
whose list rules are specific enough to win otherwise and hang an en dash off
every chip.

## The makers

`/the-makers/` is two halves. The prose at the top is an ordinary editable page.
The roster under it is a `mudlet_maker` post per person Mudlet credits in
Help → About, read from the client’s own `src/dlgAboutDialog.cpp` by
`plugin/mudlet-makers` — thirty people, the eight currently on the project drawn
first, each with the sentence they wrote about their own contribution. Nobody
types a maker. Every name links to `/the-makers/<name>/`.

The list this replaces is the reason to bother: the live site’s makers page was
typed by hand around 2010, credits twelve people, and omits most of the team
that exists now.

Two decisions worth knowing before touching it:

- **Emails are dropped.** The dialog carries one for two thirds of these people.
  A dialog is not a crawled web page, and a credits page that prints addresses is
  a spam list nobody signed up for.
- **Avatars are GitHub’s**, at `github.com/<handle>.png` — the only pictures of
  these people the project has. Eighteen publish a handle, two of those 404
  (renamed or closed accounts), and everybody else is drawn as their initials. The
  monogram is the normal case, not an error state.

There is deliberately **no** hardcoded fallback roster: a typed list of people is
the exact thing this replaces. Deactivate the plugin and the page keeps its prose
and points at the contributors graph, with an admin notice saying why.

### Naming somebody the client does not

The generated list is not the whole truth. Three people on the live page —
**Nickpick** (`joen-d`), **xtian** (`xtian-avalon`) and **Larkin**
(`larkin-dischai`) — have never been in `dlgAboutDialog.cpp`, and a page that
can only print what the client knows drops them silently. Why they are on one
list and not the other is not recorded anywhere; the live page is the only
source for them, and it gives a role each and a Launchpad id.

Adding them to Mudlet is the durable fix, at which point they appear in the
roster on their own and the hand-written note can go.

So the page has **two editable regions, and the edit screen says so**: the body,
which renders above the roster, and an **Also credited** editor below it, which
renders under the roster. Both are ordinary wp-admin editors on
`/the-makers/`'s own edit screen; the second stores its HTML in post meta
(`_mudlet_makers_extra`), nonce-checked and `wp_kses_post`-filtered on save, and
left untouched by any save that does not come from that screen.

Two supporting decisions:

- The template carries `Template Name: The makers`, like `page-download.php`, so
  it can be assigned explicitly as well as matched by slug — and the second
  editor appears on whichever page uses it.
- That page gets the **classic editor** (`use_block_editor_for_post`). Not a
  preference about editors: a `wp_editor()` in a meta box is a first-class
  citizen on the classic screen and an afterthought bolted under the block
  canvas, and the body is already a single Classic block anyway. Two editors that
  look and behave alike beats one of each.

The first version of this split the body on WordPress's own `<!--more-->` marker.
It worked, and it was invisible — the marker is only drawn inside the Classic
block's own editor — and a seam nobody can see is a seam somebody types on the
wrong side of.

Nothing in that field is seeded from this repo, and neither is the prose above
it — the seed creates `/the-makers/` and leaves it empty. An empty field renders
nothing, which is the right default: a person somebody added after a
conversation is not something a script should be inventing.

Refresh the roster and the avatars with:

```sh
wp mudlet-makers sync
```

Seeded once, cron daily, button on the record screen — same as the games. Theme
half: `inc/makers.php`, `page-the-makers.php`,
`single-mudlet_maker.php`, and the `.mkgrid` block in `assets/css/wp.css`.

## Contact

`/contact/` is `page-contact.php`: the page's own prose, then two panels — a
message form, and Discord on the right — then the forum, GitHub and the wiki as
link cards.
The old page was three headings of prose whose Email one ended in "email the
site admins" with no way to do it.

### The Discord panel is drawn here, not embedded

The counts and the faces come from Discord over two anonymous endpoints, cached
ten minutes in a transient (`inc/discord.php`):

| endpoint | what it answers |
| --- | --- |
| `/api/guilds/<id>/widget.json` | who is online, up to 100 avatars, an instant invite |
| `/api/v10/invites/<code>?with_counts=true` | the member total, which the widget does not carry |

No token, no application, no server of ours in between. The first works only
because the server has **the widget switched on**, which is the opt-in that
makes any of this publishable.

- **Deliberately not Discord's own iframe.** That widget is a fixed dark
  350×500 box with its own type, its own button and its own idea of the colour
  scheme — one element on a two-theme site that never matches it. Everything it
  draws is in the JSON behind it, so the theme draws the panel and Discord
  supplies only the numbers.
- **Nobody's name is rendered.** The member list carries usernames and the faces
  strip could print them, but those people did not choose to appear on
  mudlet.org — a server admin enabled a widget. Avatars with no names are
  texture; a wall of usernames is a directory of strangers. The strip is
  `aria-hidden`, and the count above it is what carries the meaning.
- **It degrades in halves.** Widget off: the counts stay, the faces go. Discord
  unreachable: a plain invite button, which is what the page had before. No
  count is ever invented — a number nobody knows is simply not printed.
- Change the server with `mudlet_discord_invite_url` and
  `mudlet_discord_guild_id`; the invite code is read off the end of the URL, so
  the panel cannot end up counting one server and linking to another.
  `pre_mudlet_discord_server` short-circuits the lookup entirely, for a site
  that would rather not call out to Discord on a page render.

### The form is a slot, not a form

The real one is coming from a contact form plugin. Until it does, the panel
draws a **disabled placeholder** — every control carries `disabled`, and a
dashed notice under it says so in words and offers the address, spelled through
`antispambot()`. A form that reads as live and goes nowhere is worse than no
form.

The plugin takes the slot the day it is installed: paste its shortcode into the
**Contact form** box on `/contact/`'s edit screen (post meta
`_mudlet_contact_form`, nonce-checked, and untouched by any save that does not
come from that screen). `inc/contact.php` runs it, and falls back to the
placeholder if the shortcode renders as its own source — which is what an
unregistered shortcode does, and a visitor reading `[contact-form-7 id="42"]`
on the contact page is worse off than with the placeholder it replaced.

- **Plugin-agnostic on purpose.** It stores whatever shortcode it is given —
  Contact Form 7, WPForms, Fluent Forms, Forminator. Which plugin a site ends
  up with is not a decision a theme gets to make, and hard-coding
  `[contact-form-7 …]` would make it one. The field styling is by element and
  not by class, so a shortcode's own markup inherits it.
- **A slot and not just the page body**, because the body is prose and renders
  above the panels at full measure. The form belongs inside the Email panel,
  beside Discord, and a shortcode in the body cannot get there.
- **The address is shown either way**, under the form as well as under the
  placeholder — some people would always rather use their own mail client, and
  it must not be the placeholder's consolation prize that disappears with it.
- **It is text, not a picture — and that is a trade, not a free win.** The live
  site publishes the address as a **PNG of the text**, and the picture is
  honestly the stronger obfuscation: `antispambot()` entity-encodes half the
  characters, which defeats a regex over the raw bytes and nothing else — every
  HTML parser decodes entities on the way in, so anything built on one, on a
  headless browser, or on one call to `html.unescape()` reads it with no extra
  work. Getting it out of a picture takes OCR, and harvesting at scale does not
  OCR every image on every page on the chance one is an address.

  The picture goes anyway, because what it protects is already public: the same
  address is in plain text in Mudlet's own `src/dlgAboutDialog.cpp` on GitHub,
  beside twenty-nine others, and ships inside every binary in Help → About. (The
  same file is why the makers plugin drops the addresses it reads.) So
  the PNG is a lock on a door beside an open wall, at the full price of one: an
  image cannot be selected, copied, searched, read aloud, or clicked to open a
  mail client, and it is the wrong colour in one theme and blurred at any zoom.
  Putting the address in its `alt` text to fix that would leave it weaker than
  the entities. If this ever has to be stronger than a speed bump the answer is
  not a better picture — it is to publish no address once the form plugin is in,
  and let the form be the way through.
- **It lives in the `mudlet_contact_email` option** (seeded to the live site's
  `vadim.peretokin@mudlet.org`, `CONTACT_EMAIL` to seed a different one, filter
  of the same name to compute it). `admin_email` is only the fallback: that is
  where WordPress sends password resets and fatal-error notices, and "the
  address the site publishes" and "the address that can reset the site" are not
  the same fact and should not be one field.
- Nothing here sends mail, and nothing here is a captcha — for the site's one
  real mail path see [Carrying the download to another
  machine](#carrying-the-download-to-another-machine).

Theme code and not a plugin, both of them, for the same reason as
`inc/demo-seed.php`: unlike a game or a release they own nothing. The Discord
transient is a cache, and the shortcode is a pointer at a form somebody else
owns. Files: `page-contact.php`, `inc/discord.php`, `inc/contact.php`, and the
`/contact/` block in `assets/css/wp.css`.

## Media

`/media/` is the one page that is nothing but its own content. There is no
`page-media.php`, no post type and no plugin: the screencast list and the
screenshot gallery are **core blocks wearing two of the theme's block styles**,
sitting in the page body with prose above, between and after them. Somebody can
rewrite the lot in Gutenberg without touching PHP, which is the whole thing that
page was asked for.

Both styles are registered in `inc/blocks.php`, beside the two a release post
uses, and for the same reason: a style over a core block plus a pattern to
insert it with, rather than blocks of the theme's own.

- **Screenshot carousel** — `core/gallery` with the *Screenshot carousel*
  style. Adding a screenshot is dragging an image into the gallery block;
  reordering is dragging it; the caption is the image's own. On the page,
  `theme.js` turns it into one-shot-at-a-time with arrows, dots, autoplay and
  a lightbox. The editor and a browser with no JavaScript both get the plain
  gallery core drew — every image visible, every one a link to itself, nothing
  lost. It works in a release post too, which is most of why it is not a page
  template.
- **Screencasts** — `core/list` with the *Screencasts* style. An item is
  `<a>the title</a>` and then the sentence saying what the video covers, and
  that is deliberately all it is: a block storing that would be storing prose.
  Adding one is pressing Enter and pasting a URL. The style lays them out as
  cards and stretches the link across the whole card; drop the style and it is
  still a legible list of links with their descriptions.

  A YouTube link **plays in the same lightbox the carousel uses**, rather than
  sending the visitor to YouTube and hoping they come back — arrows step through
  the whole list, so it reads as one set. Three things about that are deliberate:
  the embed is `youtube-nocookie.com`, because somebody who clicked a video
  about aliases has not asked to be counted; a link that is *not* a YouTube URL
  is left alone and navigates, so the day somebody adds a Vimeo or a write-up it
  works; and the caption always carries a **Watch on YouTube** link out, because
  a video whose owner disabled embedding plays nowhere else and says so inside a
  box the visitor cannot click past. With no script, all eight are what they have
  always been — links.

The looks live in `assets/css/blocks.css` — which the block editor loads too,
so both read as themselves while being written — with the `.prose` collisions
in `wp.css` and the lightbox in `theme.css`, that being a dialog over the
page rather than a block.

### What the seed puts there

`seed/php/media-page.php` writes that page's body, and it is the **only** page
this seed writes prose into. The exception is narrow on purpose: it writes only
while the page is still empty and never again, so re-running the seed over a
page somebody has edited does nothing at all.

It lands the live site's eight screencasts — real, still accurate, and nobody
upstream owns the list, so losing it means somebody re-finding eight YouTube
URLs by hand — and downloads the fifteen community screenshots from mudlet.org
into the media library. The pictures are **not** in this repo, for the same
reason the games plugin holds no copy of the game logos. `SEED_MEDIA=0` skips
the download and leaves an empty gallery block to fill; `SEED_MEDIA_PAGE=0`
skips the page entirely.

Three of the fifteen go in without a caption, because their filenames do not say
what game they are looking at and a guess about somebody else's MUD is worse
than nothing. That is also the normal case for a screenshot somebody sends in,
so the carousel has to look right with a few of them — which is easier to notice
when some of them are there.

## Divi leftovers

Eight imported bodies still carry `[et_pb_*]` shortcodes — see [The Divi bodies
come through as they are](#the-divi-bodies-come-through-as-they-are). The filter
drops the `_et_*` postmeta, which is the builder's own bookkeeping, but leaves
the bodies alone.

`inc/divi-cleanup.php` is what keeps those off the page, and it covers more than
Divi: the filter also leaves `[et_bloom]`, `[shortcoder]`, `[dlm_*]` and
`[recaptcha]` alone, and with nothing registering them WordPress would print
them verbatim mid-article. It strips dead shortcode *tags* at display time while
keeping what they wrapped —
`strip_shortcodes()` would have deleted the article along with the wrapper —
and it never touches a shortcode this site does register.

## The hero's embedded client

The homepage hero runs a real build of `@mudlet/mudlet-web`. Build it first:

```sh
cd demo && npm ci && npm run build
```

`demo/dist/` is bind-mounted into the theme at `assets/demo/`, which puts it on
the site's own origin. That is a hard requirement, not a preference: Mudlet Web
keeps every profile in IndexedDB, which Safari and Firefox deny to cross-origin
frames, and its VFS service worker needs a secure context. Without the build the
theme detects the missing `index.html` and the hero simply stays on its scripted
session — no broken iframe.

For a real deployment the build has to be copied into the theme rather than
mounted.

On Docker Desktop for Windows the bind mount does not survive Vite replacing
`dist/`: the container keeps serving the previous build and the client 404s on
its own package. `docker compose restart wordpress` re-resolves it.

### The world asks the site what it says

The hero's world is mudlet.org walked instead of scrolled — a release vault, a
notice board, a shelf of bundled games, a hall of makers — and every fact in it
belongs to this site. It asks for them, once, while the console is animating
its fake connect:

```sh
curl -s localhost:8080/wp-json/mudlet/v1/demo | jq
```

`inc/demo-seed.php` is the whole of it: the current release with per-platform
sizes, URLs and hashes, every bundled game's name and the count, everyone the
client credits with the sentence the About dialog gives them, the three most
recent posts, and two things this site does not know and has no business
holding a copy of: Mudlet's own list of its Lua API, which is what the imp in
the Stacks keeps, and the package repository's index, which is how many drawers
are in the cabinet in the commons and roughly how many people filled them. Both
are read off `raw.githubusercontent.com` the way the games and the makers are,
and cached for a day. The function list is the only one that travels — 36 KB of
JSON, about nine gzipped, less than any one image on the page above it; of the
package index only the two numbers survive the request. Every
value is read back through the same `function_exists()` seams the templates
use, so with the plugins deactivated the endpoint answers whatever the pages
would draw. It is theme code rather than a plugin for that reason — unlike the
data it serves, it owns nothing a theme rewrite could take with it.

One route rather than four because the world can only hold the first room back
so long: a single response either lands inside that window or does not. The
demo treats every field as optional and keeps the July 2026 snapshot written
into `site.lua` as its fallback, which is also what it shows anywhere there is
no WordPress — a dev server, a `file://` copy. Renaming a
field here is a change to `demo/packages/mudlet-demo/site.lua` as well.

## Languages

Polylang is installed and the five languages (en, de, it, ru, zh) are created by
`seed/php/languages.php`. Polylang ships no WP-CLI commands, so that file builds
its admin model directly; if a future version moves things, it prints what to do
by hand rather than half-finishing. Set `SEED_LANGUAGES=0` for a plain
single-language install — the theme handles Polylang being absent by hiding both
switchers.

## Shipping it to another site

The Docker stack bind-mounts the theme and the plugins straight off the working
tree, which is right for developing and no use at all for a test site somewhere
else. `tools/build-dist.mjs` packs them into zips wp-admin will take:

```sh
node wordpress/tools/build-dist.mjs                 # -> wordpress/dist/*.zip
node wordpress/tools/build-dist.mjs --with-demo     # ...theme carries the hero too
node wordpress/tools/build-dist.mjs --version 1.2.3 # ...stamped, tree untouched
node wordpress/tools/build-dist.mjs --no-plugins    # ...theme carries only itself
node wordpress/tools/build-dist.mjs --only mudlet-games
node wordpress/tools/build-dist.mjs --out ~/somewhere
```

**`mudlet.zip` is the whole site.** The theme, the hero's client under
`assets/demo/`, and the games, makers and releases plugins under `plugins/`,
which `functions.php` requires — so Appearance → Themes → Add New → **Upload
Theme** is the entire install and there is nothing to activate.

| archive | where it goes |
| --- | --- |
| `mudlet.zip` | Appearance → Themes → Add New → **Upload Theme** |
| `mudlet-games.zip` | Plugins → Add New → **Upload Plugin** — only if you want it as a plugin |
| `mudlet-makers.zip` | same |
| `mudlet-releases.zip` | same |

The three plugin zips are still built, and still published on every release,
for two cases: a site that would rather run them as plugins, and a site that
has changed theme and wants to keep drawing its games. **An installed copy
always wins** — WordPress loads plugins before it reaches a theme, so the
plugin defines its version constant first and `inc/bundled-plugins.php` stands
down. If that installed copy is older than the one in the theme, an admin
notice says so rather than letting it be a surprise.

Worth knowing before it bites:

- **WordPress installs by the folder inside the zip, not by the file's name.**
  Each archive holds exactly one top-level directory named for the slug, so
  uploading a second time offers to replace what is already there rather than
  landing a `mudlet-2` beside it. Renaming the zip changes nothing.
- **The theme is ~5 MB without the client and ~15 MB with it, and stock PHP
  caps an upload at 2 MB.** Raise `upload_max_filesize` *and* `post_max_size`,
  or drop the folders into `wp-content/` over SFTP — the directory names are the
  same either way. The build prints the sizes and says which ones are over. An
  update fetched from GitHub is pulled server-side and that cap does not apply
  to it.
- **The hero is opt-in.** `assets/demo/` is a bind-mount target and empty on
  disk, so a plain build ships no client and the hero stays on its scripted
  session — which is the theme's designed fallback, not a failure. `--with-demo`
  copies `demo/dist` in; build it first with `cd demo && npm run build`. The
  release workflow always passes it.
- **`--version` writes into the archives only.** The working tree is not
  touched, which is what lets the release workflow take the number off the git
  tag and cut a release without a commit. Without it, whatever is in the
  headers on disk is what ships.
- **Nothing about the site's *content* is in these.** Pages, menus, the games,
  the makers and the releases are all database records — the plugins fill their
  own from GitHub on cron, and the pages are `seed/setup.sh`'s job. A fresh test
  site needs the seed run against it, or those pages created by hand.
- **Rebuilding unchanged sources gives byte-identical archives.** Timestamps
  inside are fixed rather than read off the files, so `md5sum` against the last
  upload answers "has anything actually changed?"

The zip is written out by hand in that script rather than pulled from npm:
nothing under `wordpress/` has a `package.json` and the other tools here run on
bare node, so a dependency would put an `npm install` between somebody and a
build. It is about ninety lines, store-or-deflate, no zip64. `wordpress/dist/`
is gitignored.

## Updating a site that is already running

A site never needs to be handed a zip twice. `style.css` carries

```
Update URI: https://github.com/Mudlet/mudlet-wordpress-theme
```

which since WordPress 6.1 stops core asking wordpress.org about a theme it does
not host, and fires `update_themes_github.com` instead. `inc/updates.php`
answers it: one unauthenticated `releases/latest` call, cached twelve hours,
and the release's own `mudlet.zip` asset as the package. Because that asset is
the whole site, the update carries the plugins and the hero's client with it.

The theme also opts itself into WordPress's automatic theme updates — a site
running a release behind would be a release behind on all four things at once.
`define( 'MUDLET_AUTO_UPDATE', false );` in `wp-config.php` turns that off and
leaves the manual "Update now" button.

Cutting a release is one push:

```sh
git tag v0.2.0 && git push origin v0.2.0
```

`.github/workflows/release.yml` builds the demo client, type-checks it, runs
`build-dist.mjs --with-demo --version 0.2.0`, checks the archive is the shape
WordPress installs, and publishes the release with all four zips on it. **The
tag is the version** — nothing in the repository is bumped, so a tag and a
header can never disagree. A tag with a hyphen in it (`v0.2.0-rc1`) is
published as a prerelease, and `releases/latest` skips those, so no site is
offered it. `workflow_dispatch` does the same build and uploads the zips as
run artifacts without publishing anything.

## Known gaps

Booted and verified: a clean `down -v` + `up` provisions and imports end to end,
and `/`, `/news/`, `/news/page/2/`, `/download/`, the pages, category and year
archives, search and 404 all return the right status with no PHP notices.

- The search palette indexes the twenty most recent pages and posts from PHP. A
  real index belongs behind the REST API.
- Polylang is configured with five languages and the imported translations are
  linked, but the language switcher has only been exercised on English. The
  pages themselves are unwritten in every language, English included.
- The download table's version, sizes and hashes come from the releases plugin
  (`inc/github-releases.php`). Without it `inc/downloads.php` falls back to
  hardcoded figures, and its `mudlet_release` filter can override either.
- The contact form is a **disabled placeholder**. It is a slot waiting on a
  contact form plugin — install one and paste its shortcode into the "Contact
  form" box on `/contact/`. Nothing on that page sends mail today. The
  address beside it does, and is real.
- No comment templates — comments are closed site-wide and the design draws none.
