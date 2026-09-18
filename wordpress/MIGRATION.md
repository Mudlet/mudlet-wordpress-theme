# Migrating mudlet.org to the new theme

Draft, 2026-09-03. Everything here was measured against the live site, the CI
scripts in `Mudlet/Mudlet`, and this repo. Items marked **unverified** need
someone with wp-admin to confirm them.

Companion document: [`ANALYTICS.md`](ANALYTICS.md), which covers Matomo.

## What is actually being replaced

The live site is Divi plus a `mudlet-divi` child theme plus a handful of
plugins. Switching theme replaces the design and the templates. It does **not**
replace the plugins, the uploads, the database, or the release pipeline that
writes into all three — and the release pipeline is the part that can be broken
from a distance, by a repository nobody is looking at during the migration.

### Plugins on the live site

Read off the front end; confirm the full list in wp-admin.

| Plugin | Job | Plan |
| --- | --- | --- |
| WP-DownloadManager | Download entries, `/download/<id>/` links | **Keep** |
| Connect Matomo | The tracking snippet | **Keep** — it is a plugin, so it survives the theme change untouched |
| Contact Form 7 | The contact form | **Keep** — the new `/contact/` has a shortcode slot for it |
| Cookie Notice | The consent banner | **Keep**; see `ANALYTICS.md` |
| Polylang | `/de/`, `/it/`, `/ru/`, `/zh/` | **Drop** — see decision 4 |
| wp-lightbox-bank | Image lightbox — site-wide, on 551 linked images across 133 posts | **Drop** — the theme's lightbox covers `/media/`, the front page and, since 0.1.1, every picture linked from a post |
| mudlet-release (upstream) | Release announcement posts | **Drop** — both its jobs are now in `mudlet-releases`; see decision 2 |
| Divi + mudlet-divi | The design | Replaced by `theme/mudlet` |

## The release pipeline: do not break this

Nothing in this repo drives it, and nothing in the release workflow does either.
It runs from the **build** workflows, per platform, at tag time — which is why
the files land on mudlet.org *before* the GitHub release exists. For
`Mudlet-5.0.1` the mirror was written 19:50–20:24 and the release was published
at 20:31.

The three scripts are `CI/linux.after_success.sh`, `CI/osx.after_success.sh`
and `CI/deploy-mudlet-for-windows.sh` in `Mudlet/Mudlet`. Each one:

1. `scp`s its asset to `mudmachine@make.mudlet.org:$DEPLOY_PATH`, where
   `DEPLOY_PATH` is `/wp-content/files/` (the two hosts share the filesystem).
2. Polls `https://www.mudlet.org/wp-content/files/<name>` until Cloudflare
   serves it. Failure here is a warning, not an error — the `scp` is treated as
   the authoritative check.
3. POSTs to `https://make.mudlet.org/download-add.php` with the header
   `x-wp-download-token: $X_WP_DOWNLOAD_TOKEN` and WP-DownloadManager's own
   field names:

   ```
   file_type=2                                     # remote file
   file_remote=https://www.mudlet.org/wp-content/files/<name>
   file_name=Mudlet 5.0.1 (Linux)
   file_des=sha256: <sum>
   file_cat=5
   file_permission=-1
   file_timestamp_{day,month,year,hour,minute,second}
   output=json
   do=Add File
   ```

Categories: `2` Windows, `3` macOS Intel, `4` macOS Apple Silicon, `5` Linux,
`6` Source. Confirmed by fetching `/downloads?dl_cat=1..6` and diffing against
the sidebar block every listing shares: each of 2–5 adds exactly its own
platform.

### How the live page resolves a download link

It does not. The Divi tab body is:

```
[download category="1"][download category="2"][sc name="download_link_by_email" GID=rc1][/sc]
```

So the page names **categories** and WP-DownloadManager renders whatever is in
them; no URL is written on the page, and "which build is current" is the
plugin's ordering within a category. That is why the download page never needed
touching for a release — and it is also why the whole thing is invisible to
Matomo, since what the plugin renders is a `/download/<id>/` link.

Two anomalies fell out of checking this:

- **Category 1 is empty** and the Windows tab asks for it anyway. Almost
  certainly a leftover — 32-bit Windows, at a guess.
- **Category 6 (Source) is empty too**, although `linux.after_success.sh` posts
  a `Mudlet <version> (Source Code)` entry to it and `Mudlet-5.0.1.tar.xz` is
  mirrored correctly. So the file arrives and the WordPress entry does not.
  **Unverified** — confirm in the Downloads list; if it is real, the source
  tarball has been unreachable from the download page for some time.

### The entry IDs are stable, and that matters

The rendered listing resolves to:

| | | |
| --- | --- | --- |
| `/download/71/` | Mudlet 5.0.1 (Windows) | category 2 |
| `/download/72/` | Mudlet 5.0.1 (macOS - x86_64) | category 3 |
| `/download/73/` | Mudlet 5.0.1 (macOS - arm64) | category 4 |
| `/download/74/` | Mudlet 5.0.1 (Linux) | category 5 |

Low IDs, current version. So `download-add.php` **overwrites the entry in
place** rather than appending one per release — one live entry per platform,
and the ID has been stable for years.

Three consequences:

- **`/download/71/` is already the stable "latest Windows build" link** this
  migration was about to invent. It is presumably linked from the forums, the
  wiki and elsewhere, and it has always resolved to the current build.
- **No release older than the current one has a WordPress entry at all.** The
  only route to an old version is the archive index at
  `/wp-content/files/?C=M;O=D`, which the page links as "all previous installers
  for Mudlet". See below — it is not WordPress.
- Those four URLs are therefore **the most valuable links on the site to keep
  working**, and the cheapest, since keeping WP-DownloadManager keeps them.

### The archive index is not WordPress

`/wp-content/files/?C=M;O=D` is **Apache's own directory listing** —
`mod_autoindex`, with `?C=` / `?O=` its sort parameters and "Parent Directory"
its signature — dressed up with `AddDescription` for the "Windows Executable" /
"Source Code Archive" column, titled *Mudlet Download Archives*, and styled from
`make.mudlet.org/snapshots/tpl/`. So it borrows the snapshot service's CSS, JS
and images, which is a cosmetic coupling worth knowing about: reorganise `tpl/`
over there and this page loses its styling.

Nothing about it goes through WordPress, so **the theme change cannot affect
it** — and neither can any WordPress-side fix. Two consequences:

- It carries its own **hand-pasted copy of the Matomo snippet**, identical
  config, same site ID. That is why the only downloads Matomo has ever recorded
  are old versions clicked here: real filenames, real extensions, a tracker on
  the page. It also means every tracker config change has to be made twice. See
  `ANALYTICS.md`.
- It is the archive, and **"Browse the archive" points at it** —
  `mudlet_download_archive_url()`, filterable. Same argument as decision 3: if
  the download comes from mudlet.org the archive should too, it stays reachable
  where GitHub is throttled, and unlike the GitHub page it is tracked. Worth
  revisiting only if the archive ever needs to be more than a list of files —
  GitHub's release list carries per-release changelogs and checksums that a bare
  Apache index cannot.

`create-github-release.yml` adds one more upload over the same credential: the
Sparkle appcast, to `/wp-content/files/appcast/`. **macOS auto-update reads
that path.** Do not reorganise the directory.

Two things worth fixing regardless of the migration:

- **`download-add.php` is in no repository.** It exists only on the server,
  nobody working on this has access to it, and **that is fine for the
  migration**: it POSTs to WP-DownloadManager, which is being kept, so a theme
  change cannot disturb it and there is nothing to do here. It is worth writing
  down only because an unauthenticated-by-default endpoint holding a static
  token, in no version control, is the hinge of the whole release pipeline. A
  someday item for whoever has the server, not a step.
- The Linux script uploads an unversioned `Mudlet.AppImage` alongside the
  versioned tar, commented "for appimage.github.io". That is a stable-alias
  pattern that already works and is not used anywhere else. See decision 3.

## Decisions

### 1. WP-DownloadManager stays

Decided. It is also the safer order: `download-add.php` POSTs to it on every
release, and if the plugin is gone those writes go nowhere **without failing** —
CI would keep reporting success while the download entries silently stopped
being created.

Retiring it is possible but optional, and is not part of this migration — see
**Optional, after the migration** at the end.

### 2. The old release plugin goes, and both its jobs are covered here

`Mudlet/mudlet-release-plugin` does two jobs, and only one of them is the
webhook:

1. **The `[MudletRelease]<id>[/MudletRelease]` shortcode.** Twenty-one existing
   release posts have *nothing else* in `post_content`. Without the plugin they
   render as a bare number. This is a permanent compatibility requirement and it
   does not go away no matter who writes future posts.
2. **The webhook** that creates the announcement post in every language and
   stamps the release-post meta.

So the plugin is not made obsolete by writing announcement posts ourselves —
only job 2 would be. This repo's `mudlet-releases` plugin deliberately does not
overlap with either: it owns the release **record**, and says so
(`includes/class-admin.php:15`). The announcement post is an ordinary post.

It does carry a real bug, which `seed/setup.sh` patches locally and which should
go upstream instead. The plugin writes the release **id** into the post body but
reads it back as a **tag name**:

```php
GetHttpWrapper::get(GITHUB_API_URL . "releases/tags/$content")   // 404
GetHttpWrapper::get(GITHUB_API_URL . "releases/$content")        // correct
```

On mudlet.org this is invisible, because the webhook that creates the post also
calls `set_transient()` with no expiry, so the body is cached forever and the
fallback is never reached. Any fresh install — a staging copy of the new theme,
for instance — has no transients, and every imported release post renders
"Can't get releases post for &lt;id&gt;". Dropping `tags/` fixes it.

**Both jobs are now covered by `mudlet-releases`, so the plugin can be
deactivated.**

- Job 1 was already covered: `Mudlet_Releases_Content::legacy_shortcode()`
  registers `[MudletRelease]` when that plugin is not active, and resolves the
  id it carries the way it should have been resolved — which is also why the
  `tags/` bug above stops mattering the moment this takes over.
- Job 2 is `includes/class-webhook.php`. It answers on the **same**
  `admin-ajax.php?action=post_newest_release` the old plugin used, so nothing
  changes in the webhook's settings on `Mudlet/Mudlet` — a migration step in
  somebody else's repository, at a particular moment, is a migration step that
  gets forgotten. It registers only when the old plugin is absent, so both can
  be installed while the switch happens.

Two things are better rather than merely the same, and both were free:

- **The endpoint can be authenticated.** The old one could not: it read
  `$_POST['payload']` and published it, so anyone who knew the URL could post
  to mudlet.org. Setting `MUDLET_RELEASES_WEBHOOK_SECRET` in `wp-config.php`
  turns on `X-Hub-Signature-256` verification. Do that when the webhook is
  re-pointed; until then the endpoint reads only the *tag* out of the request
  and re-fetches the release from GitHub itself, so a forged POST cannot put a
  character on the page.
- **The post is a tag, not a body.** The old plugin wrote
  `[MudletRelease]<id>[/MudletRelease]` into `post_content`; this writes an
  empty body and `_mudlet_release_tag`, which renders the same changelog and
  leaves the body free for the opening paragraph a webhook cannot write —
  the exact editorial control this decision used to be weighing against
  keeping the automation. It is no longer a trade.

The one thing to check before deactivating: **Polylang.** Job 2 created the post
in four languages, and the replacement creates one. That is decision 4 either
way, but it means the two cannot run in parallel for long without producing
different things.

### 3. Download links point at mudlet.org, not GitHub

Today the new theme links every download row at GitHub's
`browser_download_url`. That is wrong for three reasons, in increasing order of
importance:

1. GitHub is throttled or blocked on many networks. The site is going
   English-only (decision 4), but the *audience* is not — the reason those
   translations existed in the first place is the reason this matters.
2. The mirror already exists, is complete and current, is verified live by CI
   before the release is published, and is served from Cloudflare's edge
   (`cf-cache-status: HIT`, `max-age=14400` on a 130 MB installer). The
   bandwidth objection is already answered.
3. It is the only way Matomo can see a download at all. See `ANALYTICS.md`.

**Three link shapes**, each with one job.

#### a. Versioned files — what the table rows link

```
https://www.mudlet.org/wp-content/files/Mudlet-5.0.1-windows-64-installer.exe
```

Already exists, already correct. The row prints a SHA-256 beside the link, and a
checksum has to describe *the file at the other end of that link* — which rules
out pointing a row at anything that can change under it. Carries an extension,
so Matomo classifies it with no extra work, and the Downloads report breaks down
by version for free.

#### b. Stable descriptive URLs — what we publish everywhere else

`/download/71/` works but says nothing: nobody can tell what it is, and nobody
can guess it. The replacement should be readable, guessable, permanent, and
carry a file extension:

```
https://www.mudlet.org/latest/mudlet-windows-x64.exe
https://www.mudlet.org/latest/mudlet-windows-x64-portable.zip
https://www.mudlet.org/latest/mudlet-macos-apple-silicon.dmg
https://www.mudlet.org/latest/mudlet-macos-intel.dmg
https://www.mudlet.org/latest/mudlet-linux-x64.AppImage.tar
https://www.mudlet.org/latest/mudlet-linux-x64-portable.tar.gz
https://www.mudlet.org/latest/mudlet-source.tar.xz
```

`/latest/` rather than `/get/` or `/dl/` because the URL then states its own
contract: someone pasting one into a forum thread can see that it will still be
the current build in three years, which is exactly the property the version-
pinned GitHub URLs lack. The extension keeps them countable without
`trackLink`.

A 302 (not 301 — the target moves every release) to the current versioned file.
It belongs in the **`mudlet-releases` plugin**, because it has to survive a
theme rewrite and because that plugin already knows which release is current:
one `add_rewrite_rule`, resolving through the existing `builds()` platform keys
(`win`, `macarm`, `macx86`, `linux`) so the platform list is not typed twice.

These are what go on the wiki, in forum posts, in the QR code and in the
email-a-link message.

#### c. The legacy numeric URLs — kept, unchanged

`/download/71/`…`/74/` stay exactly as they are. They are linked from places
nobody can enumerate and they have resolved to the current build for years;
keeping WP-DownloadManager keeps them working at no cost. They are not deprecated
so much as superseded — nothing needs to chase them down.

If WP-DownloadManager is ever retired, map them to their `/latest/` equivalents
in the same plugin that owns the rewrite rule above (see **Optional, after the
migration**). Until then, track them with
`trackLink` so they stop being invisible — see `ANALYTICS.md`.

#### Implemented

All three shapes are in the code. What remains for this decision is the CI half.

- `plugin/mudlet-releases/includes/class-links.php` — the mirror, the alias
  derivation, and the `/latest/<name>` route. The route answers on
  `parse_request`, not through `add_rewrite_rule()`, so it works the moment the
  plugin loads rather than 404ing until somebody flushes permalinks.
- The alias name is **derived**: the asset name with the version taken out, so
  `Mudlet-5.0.1-windows-64-installer.exe` answers at
  `/latest/Mudlet-windows-64-installer.exe`. A curated table of prettier names
  would read better and would be one more list to keep in step with the release
  workflow — the trade this plugin refuses everywhere else.
- `url`, `github` and `latest` are added by `Links::decorate()` **on the way out
  of the store**, not stored — they depend on where this site serves builds
  from and on `home_url()`, neither of which is a fact about a release. Nothing
  needs re-syncing for this change.
- The mirror is `wp-content/files` on the site itself, and whether to use it is
  answered per asset by a `file_exists()` — a `stat`, not a request, because the
  files are on the same filesystem as the code. A release CI failed to upload, a
  fork, or a development copy falls through to GitHub for exactly the assets it
  is missing and no others. mudlet.org already has the files, so the links move
  the moment this ships. A `mudlet_releases_mirror` filter covers a site serving
  builds from somewhere else.
- `page-download.php` carries `data-latest` on each row and a GitHub mark beside
  the Download button, rendered only when the row is not already pointing there.
- `theme.js` builds the QR, the copy button and the email form from
  `data-latest`, falling back to the row's `href`.

**The Docker site gets placeholders.** `seed/php/mirror.php` writes a few
hundred bytes per asset into `wp-content/files/` in place of ~130 MB, and drops
an `.htaccess` turning on the directory index the archive link needs — so a
development copy exercises the mirror instead of quietly taking the GitHub
branch, which is the one thing you cannot see by looking. Downloading one gets a
text file that explains itself. `SEED_MIRROR=0` skips it and leaves every link
on GitHub.

Still outside this repo: the stable-alias uploads for Windows and macOS in CI
(the Linux one already exists as `Mudlet.AppImage`), which are only needed if
those files should be reachable without the redirect.

### 4. Multilingual support is dropped

Decided: translations cannot be produced reliably, so Polylang goes and the site
becomes English-only.

**The theme needs no changes.** `inc/languages.php` is written over
`mudlet_has_polylang()`, which is a `function_exists()` check; with the plugin
gone `mudlet_languages()` returns an empty list and both switchers — the header
dropdown and the footer row — simply stop rendering. `inc/search.php` iterates
the same empty list. Nothing breaks.

**The content is the work.** From the live sitemap:

| | posts | pages | categories | tags |
| --- | --- | --- | --- | --- |
| German | 49 | 4 | 3 | 6 |
| Chinese | 49 | 7 | 3 | 2 |
| Russian | 17 | 0 | 2 | 0 |
| Italian | 15 | 3 | 2 | 0 |
| **total** | **130** | **14** | **10** | **8** |

That is ~162 indexed URLs, and they are indexed — they are in the site's own
`wp-sitemap.xml`, and the live download page links to four of them by hand.
Deactivating Polylang removes the rewrite rules that make `/de/…` resolve, so
all of them 404 at once unless something is done first.

Recommended: **301 each to its English equivalent**, which Polylang knows while
it is still active — export the translation map *before* deactivating, not
after. A blanket `/de/* → /` is worse than it looks; most of those 130 posts are
release announcements with a real English counterpart.

Then: delete the translated posts (or leave them unpublished — they cost
nothing), drop `seed/php/languages.php` and its step in `seed/setup.sh`, and
remove the four language links from the download page copy.

**This also touches decision 2.** The old release plugin's webhook creates the
announcement post *in every language*. With Polylang gone that half either
errors or produces nothing useful, so it needs checking — and it is another
argument for retiring job 2 and writing announcement posts by hand.

## Link compatibility

| URL in the wild | Fate | Action |
| --- | --- | --- |
| `/wp-content/files/<file>` | Unaffected | None. Files on disk, not WordPress routes |
| `/wp-content/files/appcast/*.xml` | Unaffected | None — **macOS auto-update depends on it** |
| `/download/71/` `/72/` `/73/` `/74/` — Windows, macOS x86_64, macOS arm64, Linux | Stable per-platform "latest build" links, overwritten in place each release. Work while WP-DownloadManager is active | Keep the plugin. These are the site's most valuable links; map them explicitly if it is ever dropped |
| `/downloads?dl_cat=2..5` | Category listings | None while the plugin stays |
| `/download/` | Becomes the new download page | Watch for the collision below |
| `/de/…`, `/it/…`, `/ru/…`, `/zh/…` (~162 URLs) | **All 404 the moment Polylang is deactivated** | Export the translation map first, then 301 each to its English equivalent. See decision 4 |

**The collision to know about:** the new theme's download page is at
`/download/`, and WP-DownloadManager's entries are at `/download/<id>/`. They
coexist only while the plugin is active — its rewrite rule is what resolves the
numeric child. Deactivate it and WordPress reads `71` as a missing child page
and 404s. The plugin stays, so this is a note for **Optional, after the
migration** rather than something to act on.

## Before the switch

Everything here can be done while Divi is still drawing the site. Sorted by
whether it can be done afterwards, because only one of these cannot.

### The structure is already there

Read off the live sitemap, 2026-09-08: `about`, `about/vision`, `contribute`,
`the-makers`, `contact`, `download`, `news`, `terms-of-service`,
`privacy-policy`, `media`, and the front page. That is every page the theme
has a template or a menu entry for, and the slugs match — so
`page-download.php`, `page-contact.php` and `page-the-makers.php` attach
themselves through the ordinary template hierarchy the moment the theme is
active. **Nothing needs creating, and no `_wp_page_template` meta needs
writing**; the seed sets it because a site whose slugs had drifted would need
it, and this one's have not.

### Getting the theme onto the site at all

`mudlet.zip` is the whole site — theme, four plugins, and the hero's client —
and with the demo in it that is **about 14 MB**. PHP's default
`upload_max_filesize` is 2 MB, plenty of hosts leave it at 8 MB, and on many of
them it is not something an admin can raise. So the one archive that was meant
to make this a single Upload Theme is the one thing that cannot be uploaded.

Nothing is wrong with the archive: the server can fetch 14 MB from GitHub
without noticing, and only the browser → PHP leg is limited. Three ways round
it, cheapest first.

1. **`plugin/mudlet-installer/`** — a 4 KB plugin that does nothing but ask
   GitHub for the latest release and install `mudlet.zip` from it, server-side.
   Upload that (it fits anywhere), press the button under Appearance → Install
   Mudlet theme, delete it. It never activates the theme — installing and
   switching are separate decisions.
2. **`wp theme install <release url> --activate`**, if there is shell access.
3. **Upload the no-demo build.** `build-dist.mjs` without `--with-demo` is
   5.1 MB, which fits an 8 MB limit. The hero stays on its scripted session
   until a later update brings the client.

**After the first install none of this matters again.** The theme carries its
own updater — `inc/updates.php`, over the `Update URI` header and
`update_themes_github.com` — so every later version is offered on Dashboard →
Updates at any size, plugins and demo client included, with nothing to upload.

The one thing missing today: **no release has been published yet**, so there is
nothing for either the installer or the updater to find. `git push origin
v0.1.0` runs `.github/workflows/release.yml` and fixes that.

### Do it now

- **Export the Polylang translation map.** The reason decision 4 gives is that
  `pll_get_post()` answers only while the plugin is active. That is true of the
  live database and not of an export: a WXR carries Polylang's
  `post_translations` terms whole, so **`wp export` before deactivating
  preserves the answer permanently**, and
  `node wordpress/tools/translation-map.js` rebuilds the redirect table from it
  — 169 URLs, of which 161 resolve to an English post and 8 need somebody to
  pick. `seed/php/migrate-polylang.php` remains the live-site path; where both
  exist they should agree. Take the export first and the rest of the migration
  stops being time-critical.
- **Build the two menus and pre-assign them.** A menu is an ordinary taxonomy
  object and belongs to no theme; only the *assignment* is theme state, and it
  lives in `theme_mods_mudlet`, which nothing Divi reads ever touches. So the
  header menu the site already has, plus a **Footer - Project** menu over
  `about`, `vision`, `the-makers`, `contribute`, `contact`, can both be pointed
  at the theme's two locations before it is active:

  ```sh
  wp option update theme_mods_mudlet --format=json \
    '{"nav_menu_locations":{"primary":<id>,"footer-project":<id>}}'
  ```

  Worth doing first among the reversible steps: an unassigned header is the
  most alarming thing about a fresh activation and it is the least real. The
  theme falls back to the hardcoded links in `mudlet_nav_links()` rather than
  drawing nothing, which is exactly why it is easy to miss that no menu is
  attached.
- **Let the releases sync run.** It schedules itself on `init` in any request
  where the theme is loaded, so previewing a few pages is enough to arm it; the
  hourly detail pass fills in the changelogs from there. Nothing needs setting
  for the mirror. Check `Mudlet -> Sync` shows a next run and a record count
  before the switch, so the download page is populated on its first public
  render rather than falling back to hardcoded figures.
- **Set `mudlet_contact_email`.** An option the theme reads and nothing else
  does; `admin_email` only backstops it.
- **The Matomo fixes in `ANALYTICS.md`.** Independent of the theme, and the
  sooner they land the sooner the downloads are countable.

### Do it at the switch, not before

- **Six Divi bodies that still reach a reader.** `node
  wordpress/tools/probe-divi.js` lists them out of the export, with what each
  template does with the body. Only three shapes matter:

  - **`/download/` and `/contact/` must be emptied.** Both templates render the
    page body — under the build table and over the Discord panel respectively —
    so the old Divi page arrives *underneath the new one* if the body is left
    in place. `/download/` is 27KB of tabs, `[download category]` shortcodes
    and the four language links. This is the one that looks like a bug rather
    than an unfinished page, and it is the easiest to miss because both
    templates draw correctly above it.
  - **`/media/`** wants rewriting as a `core/gallery` and a `core/list` under
    the two block styles in `inc/blocks.php` — 7 screencasts and 13 screenshots
    that are already in the media library, so a body rewrite rather than an
    upload.
  - **`/terms-of-service/`, `/privacy-policy/` and four posts** go through
    `page.php` and `single.php`, where `inc/divi-cleanup.php` strips the tags
    and keeps the text. They read acceptably unattended, so they are not a
    switch-day job — they are on the list in **Manual rewrites** below.

  **The front page needs nothing at all**: `front-page.php` never calls
  `the_content()`, so its 20KB of `et_pb_section` is simply never read again.
- **`[mudlet_screenshot_submit]`.** Not until the theme is active. Nothing
  registers it before then, and WordPress prints an unregistered shortcode
  verbatim — the submission form would read as literal brackets on a live page.
- **`posts_per_page` 18.** It changes the length of the news listing Divi is
  drawing right now.
- **The contact form shortcode**, into the page's **Contact form** box. The box
  is the theme's meta box, so it needs either the theme active in wp-admin or
  `wp post meta update`.

### Decide before, apply after

- **Comments — and they are already visible.** An earlier draft of this page
  said the threads had been live and unrendered for years, and that the theme
  would surface 155 of them on the day of the switch. That is wrong, and it was
  wrong because the posts it was checked against happened to be ones whose
  comments are all in the trash. Measured properly: the export holds **645
  comments, of which 157 are approved and 488 are trashed**, and the 157 sit on
  **55 posts** — every one of which renders its thread on mudlet.org today.
  `/2021/09/mapper-commandlines-colors/` has 24 comments of which exactly one is
  approved, and that one is on the page right now. A post whose comments are all
  trashed renders nothing, which is what made the whole thread look hidden.

  So **the switch surfaces nothing**, and this is not a migration risk. What is
  left is an ordinary editorial question — whether the new site keeps *taking*
  comments — plus one thing worth a look: 488 trashed against 157 approved is a
  spam ratio, and it says the moderation queue has been carrying the site for
  years.
- **The front page's three editable regions.** They default to the copy the
  templates ship with (`inc/front-content.php`), so an untouched site renders
  correctly and this is not a blocker. It is only worth opening Pages -> Home
  early if the six cards or the spec line are already known to be wrong.

### Manual rewrites

Old bodies worth rewriting by hand in the block editor. None of them blocks the
switch — `inc/divi-cleanup.php` keeps every one readable meanwhile — but each is
a page that renders as leftovers rather than as a page, and rewriting is what
takes it off the cleanup's hands. Measured over every published English post
and page in the 2026-08-31 export; `node wordpress/tools/probe-divi.js`
re-derives the Divi half from a newer one.

Divi bodies, after the switch (the block editor is the new theme's, and a
rewrite done under Divi would be drawn by Divi until then):

- [ ] `/2026/08/5-0/` — 56KB, the current release announcement and the most-read
      of these. First.
- [ ] `/terms-of-service/`
- [ ] `/privacy-policy/`
- [ ] `/2018/07/mudlet-3-11-quality-improvements-all-around/`
- [ ] `/2019/06/translation_summary_after_one_year/`
- [ ] `/2024/12/mudlet-as-a-portable-app/`

Dead plugin shortcodes, any time — neither depends on the theme:

- [ ] `/2009/12/quick-poll-which-kind-of-text-in-mudlet-do-you-prefer/` — the
      whole body is `[polldaddy poll="2401281"]`, from a plugin long gone, so
      the live page already shows the bare tag. Unpublish it; there is no poll
      left to link to.
- [ ] `/2008/11/mudlet-pre-alpha-is-out/` — `[page_download]` drops
      WP-DownloadManager's whole current download list into a 2008 announcement.
      Replace it with a link to `/download/`. (It renders while the plugin
      stays; it is wrong rather than broken.)

Duplicate release posts, **together with the 301s in step 9 and not before**.
Both are live and in the sitemap, so unpublishing one ahead of its redirect is a
404 on an indexed URL. The release webhook re-created two announcements that
already existed on 2024-12-26, each a one-line `[MudletRelease]` body. The 301s
are already in `translation-map.js` (`RETIRED`), which also sends their eight
translations straight to the real posts:

- [ ] `/2024/12/4-17-now-more-screenreader-friendly/` → unpublish; 301 to
      `/2023/03/mudlet-4-17-now-more-screenreader-friendly/`
- [ ] `/2024/12/4-19-mudlet-is-now-portable-2/` → unpublish; 301 to
      `/2024/12/4-19-mudlet-is-now-portable/`

Not on the list, and checked: `[caption]`, `[gallery]` and `[video]` are
core's own and render as they always did; `[MudletRelease]` is answered by
`mudlet-releases`; and `[CodeFactor]`, `[the setup]`, `saveMap([location])`
and `rel="lightbox[…]"` are prose and markup that happen to hold brackets,
which WordPress leaves alone. `/download/`, `/contact/` and `/media/` are the
switch's job, above.

## Order of operations

0. Everything in **Before the switch** above that can be done now — the
   Polylang export above all.
1. Confirm the plugin list in wp-admin, and whether category 6 is really empty.
   **Unverified.**
2. Put `download-add.php` in a repository.
3. Deactivate the upstream `mudlet-release` plugin once `mudlet-releases` is
   loaded — it answers the same webhook URL and the same shortcode, and stands
   down until that one is gone. Then set `MUDLET_RELEASES_WEBHOOK_SECRET` and
   put the same secret on the webhook in `Mudlet/Mudlet`. The
   `releases/tags/$content` bug and its local patch in `seed/setup.sh` become
   moot at that point rather than needing an upstream release.
4. **Export the Polylang translation map while Polylang still works**, and build
   the 301 table from it. This is the one step that cannot be done afterwards.
5. ~~Land the mirror-URL change in `mudlet-releases`.~~ **Done**, and nothing
   to configure: `Links::mirror()` defaults to `content_url( 'files' )` and a
   `file_exists()` answers per asset, so mudlet.org takes the mirror branch the
   moment the plugin loads. The `mudlet_releases_mirror` filter is for a site
   serving builds from somewhere else. No CI change needed, no pipeline
   disturbed, and Matomo starts recording downloads.
6. Apply the Matomo fixes in `ANALYTICS.md` — they are independent of the theme
   and can go in before it.
7. Switch the theme.
8. **Deactivate and delete the pre-installed `mudlet-games` and
   `mudlet-makers`**, so the theme's bundled copies take over and future theme
   updates carry them. Only after step 7, never before. Then check
   `Mudlet → Sync` and one URL of each post type.
9. Deploy the 301s, then deactivate Polylang. Watch 404s for a week. Shortcoder can go at the same time: its three live uses are all on the download pages being emptied or redirected, and `[sc]` is stripped from anything left.
10. Deactivate wp-lightbox-bank (needs theme 0.1.1 or later); check `/media/` and a release post with screenshots.
11. Work through **Manual rewrites** — `/2026/08/5-0/` first. The two
    shortcode fixes on that list can be done at any point, before the switch
    included.
12. Add the stable aliases for Windows and macOS in CI, and point the QR/email
    drawer at them.

Steps 5 and 6 need no CI change and disturb no pipeline, so they can land before
the theme switch and start producing real download numbers immediately.

## The plugins are already on production, and come back off

`mudlet-games` and `mudlet-makers` were uploaded to `wp-content/plugins` ahead
of the theme, so the data is in place: the live sitemap already carries
`wp-sitemap-posts-mudlet_game-1.xml` (43 URLs) and
`wp-sitemap-posts-mudlet_maker-1.xml` (30). They are removed once the theme,
which carries its own copies under `plugins/`, is active.

**Order matters, in one direction only: activate the theme first, remove the
installed copies second.** An installed copy always wins the `defined()` /
`class_exists()` race, so while both exist the installed one is authoritative
and the theme's stands down — harmless, but it means theme updates stop
carrying the plugin half, which is the whole point of shipping one archive.
Removing them while Divi is still active is the case to avoid: nothing would
register the post types, and 73 live URLs would 404 at once.

Removal itself is safe, and was checked rather than assumed:

- **No `uninstall.php` and no uninstall hook** in any of the four plugins, so
  deleting one deletes no content. `mudlet_games_deactivate()` says so out loud
  — "deactivating a plugin is not an instruction to delete forty pages". The
  game and maker posts survive.
- The deactivation hooks do `wp_clear_scheduled_hook()` and
  `flush_rewrite_rules()`. Both **self-heal**: the bundled copy re-registers the
  post types on `after_setup_theme` and calls `Mudlet_Sync::reschedule()` on
  `init`, i.e. on the next request. No manual re-arming needed.

Afterwards, check `Mudlet → Sync` shows a next run for each job, and spot-check
one `/games/<slug>/` and one `/the-makers/<name>/` URL.

## Optional, after the migration

Not part of the migration and not needed for it. Listed so the reasoning is not
lost.

### Retiring WP-DownloadManager

**Status: optional. The plugin stays; see decision 1.**

After the switch nothing on the site reads it — the download page is drawn from
`mudlet-releases` and the mirror in `wp-content/files/`. What it still does:

1. Keeps `/download/71/`…`/74/` resolving to the current build per platform —
   the stable links in forum posts and on the wiki.
2. Receives the CI's POSTs through `download-add.php`, which exist only to keep
   (1) pointing at the newest build.

So the case for retiring it is that a plugin, plus an unversioned server script
holding a static token, is carrying four redirects. It also owns the
`/download/<id>/` collision above, hides those downloads from Matomo, and has
the empty Source category (open question below). None of that is urgent.

If it is done, it is **one coordinated change across two repositories**, and
the halves must land together — with the plugin gone first, `download-add.php`
writes go nowhere and CI keeps reporting success:

- **This repo:** `mudlet-releases` answers `/download/71/`…`/74/` with a 302 to
  the matching `/latest/<name>` (the same `parse_request` route as
  `class-links.php`), and `/downloads?dl_cat=2..5` with a 301 to `/download/`.
  In the plugin, not the theme: legacy URLs are a fact about the site and must
  survive a theme rewrite.
- **`Mudlet/Mudlet`:** drop the `download-add.php` POST from
  `CI/linux.after_success.sh`, `CI/osx.after_success.sh` and
  `CI/deploy-mudlet-for-windows.sh`. The `scp` to `wp-content/files/` stays —
  that is the mirror, and macOS auto-update reads the appcast beside it.
- **The server:** delete `download-add.php`, then deactivate the plugin, then
  check all four numeric URLs and one `?dl_cat=` listing.

## Open questions

- Is category 6 (Source) really empty on the live site, and if so, since when?
- Category 1 is asked for by the Windows tab but holds nothing. Safe to drop
  from the page copy?
- Do the translated posts get deleted or kept unpublished after the 301s?
