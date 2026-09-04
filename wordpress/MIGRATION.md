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
| wp-lightbox-bank | Image lightbox | **Drop** — the new `/media/` carousel has its own |
| mudlet-release (upstream) | Release announcement posts | **Keep, and fix** — see below |
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

- **`download-add.php` is in no repository.** It exists only on the server. It
  is an unauthenticated-by-default endpoint holding a static token and it is the
  hinge of the whole release pipeline. It should be in version control.
- The Linux script uploads an unversioned `Mudlet.AppImage` alongside the
  versioned tar, commented "for appimage.github.io". That is a stable-alias
  pattern that already works and is not used anywhere else. See decision 3.

## Decisions

### 1. WP-DownloadManager stays

Decided. It is also the safer order: `download-add.php` POSTs to it on every
release, and if the plugin is gone those writes go nowhere **without failing** —
CI would keep reporting success while the download entries silently stopped
being created.

Retiring it later is therefore a coordinated change across two repositories:
this one, plus the three CI scripts. It is not a step in this migration.

### 2. The old release plugin stays, and gets a one-line fix

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

**Open decision:** whether to keep job 2 or write announcement posts by hand.
Keeping it is cheaper and already works in four languages. Writing them by hand
gains editorial control over the opening paragraphs, which is the part the
webhook cannot write anyway. Either way job 1 stays.

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
in the same plugin that owns the rewrite rule above. Until then, track them with
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
| `/downloads?dl_cat=2..5` | Category listings | 301 to `/download/` when the plugin goes |
| `/download/` | Becomes the new download page | Watch for the collision below |
| `/de/…`, `/it/…`, `/ru/…`, `/zh/…` (~162 URLs) | **All 404 the moment Polylang is deactivated** | Export the translation map first, then 301 each to its English equivalent. See decision 4 |

**The collision to know about:** the new theme's download page is at
`/download/`, and WP-DownloadManager's entries are at `/download/<id>/`. They
coexist only while the plugin is active — its rewrite rule is what resolves the
numeric child. Deactivate it and WordPress reads `71` as a missing child page
and 404s. If the plugin is ever dropped, the redirect map belongs in the
`mudlet-releases` **plugin**, not the theme: legacy URLs are a fact about the
site, and by this repo's own rule that means it must survive a theme rewrite.

## Order of operations

1. Confirm the plugin list in wp-admin, and whether category 6 is really empty.
   **Unverified.**
2. Put `download-add.php` in a repository.
3. Fix `releases/tags/$content` upstream in `mudlet-release-plugin`; drop the
   local patch from `seed/setup.sh` once that is released.
4. **Export the Polylang translation map while Polylang still works**, and build
   the 301 table from it. This is the one step that cannot be done afterwards.
5. ~~Land the mirror-URL change in `mudlet-releases`.~~ **Done** — set
   `mudlet_releases_mirror` on production to switch the links over. No CI change
   needed, no pipeline disturbed, and Matomo starts recording downloads.
6. Apply the Matomo fixes in `ANALYTICS.md` — they are independent of the theme
   and can go in before it.
7. Switch the theme.
8. **Deactivate and delete the pre-installed `mudlet-games` and
   `mudlet-makers`**, so the theme's bundled copies take over and future theme
   updates carry them. Only after step 7, never before. Then check
   `Mudlet → Sync` and one URL of each post type.
9. Deploy the 301s, then deactivate Polylang. Watch 404s for a week.
10. Deactivate wp-lightbox-bank; check `/media/`.
11. Add the stable aliases for Windows and macOS in CI, and point the QR/email
    drawer at them.
12. *Later, and only as a coordinated change:* retire WP-DownloadManager and
    `download-add.php` together.

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

## Open questions

- Is category 6 (Source) really empty on the live site, and if so, since when?
- Category 1 is asked for by the Windows tab but holds nothing. Safe to drop
  from the page copy?
- Job 2 of the old release plugin: keep the webhook, or write announcement posts
  by hand? Decision 4 pushes this towards "by hand".
- Do the translated posts get deleted or kept unpublished after the 301s?
