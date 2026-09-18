# Mudlet Releases

**A release post needs a tag. Everything else follows from it.**

Set `Mudlet-4.22.0` — or just `4.22.0` — on a post and this supplies:

| | from |
|---|---|
| version | the tag |
| date | the release's `published_at` |
| release notes | its Markdown body, rendered |
| changelog | every pull request merged since the previous release |
| counts — *"47 new features, 78 improvements, 207 fixes"* | those pull requests, by category |
| download rows — size, URL, SHA-256 per platform | the release assets, whose JSON carries the hash |

Nobody types a number that can drift from what shipped.

## Where this runs

Normally **inside the theme**. `mudlet.zip` carries this plugin under
`plugins/mudlet-releases/` and the theme’s `functions.php` requires it, so a site
installs one archive and activates nothing.

`mudlet-releases.zip` is still built, and still published on every release, for a
site that would rather have it in `wp-content/plugins` — and a copy there
**wins**: WordPress loads plugins long before it reaches a theme, so
`MUDLET_RELEASES_VERSION` is already defined by the time the theme looks and the
theme stands down. An installed copy older than the theme’s gets an admin
notice rather than being a silent surprise.

Either way the data is the same — `mudlet_release` records in the database, which
outlive both. What changes is only wiring, and all of it is in
`shared/mudlet-bundle.php`: when to boot (`plugins_loaded` has already fired
when a theme is read), where the assets are, where the translations are. See
the theme’s `inc/bundled-plugins.php`.

## The changelog comes from pull requests, not from the notes

A release body is prose somebody wrote. Sometimes it is a tidy
Added/Improved/Fixed list; 5.0's is marketing sections with their own titles.
Either way it is a summary, and parsing it for counts only works when the author
happened to use the right headings.

The record is what merged between the previous tag and this one. Mudlet
squash-merges, so every commit title *is* its pull request's title with the
number on the end:

```
fix: media start events fired too early when replaying a just-stopped sound (#9611)
```

402 of the 420 commits in 4.22.0→5.0.0 look like that. So one walk of the
compare endpoint yields a full categorised changelog and honest counts, with no
per-PR requests.

### How well it matches

Mudlet's own 5.0 announcement says *"24 New Features, 25 Improvements, 214 Bug
Fixes, 156 Infrastructure Updates"* and links
`compare/Mudlet-4.22.0...Mudlet-5.0.0`. This derives, over that same range:

| | Mudlet | here |
|---|---|---|
| added | 24 | **24** |
| improved | 25 | **25** |
| fixed | 214 | **214** |
| infrastructure | 156 | **156** |

Four for four, from the same compare range they chose. The remaining entry of
the 420 falls into `other`.

4.21 is close but not exact — 207 fixes and 47 features match, 78 improvements
against a published 77, 213 infrastructure against 203. Whatever the difference
is, it is small and old. Since the rules here are inferred rather than shared,
`mudlet_releases_changelog_prefixes` is filterable and
`tools/probe-categories.js` shows what a change does without booting WordPress.

Categories come from the title's leading word, with or without a colon, so both
`fix: thing` and `Fix thing` land together. `adding` is in the `added` pattern
because *"Adding selectAll function"* is the single entry that separated 46 from
the published 47.

### Nothing is dropped

Five buckets: `added`, `improved`, `fixed`, `infrastructure`, and **`other`** for
anything that matched no pattern. Version bumps land there too — they did merge,
and there are only ever one or two.

`other` is **listed, not hidden**, and that is the point. It is the feedback loop
for the patterns above: a real change whose title used an unexpected word shows
up there instead of vanishing, and someone notices and adds a rule. A changelog
that silently swallows what it cannot classify is worse than one with an untidy
last section.

The release panel shows four figures — added, improved, fixed, infrastructure —
which is what Mudlet's own announcements show: 5.0 was published as *"24 New
Features, 25 Improvements, 214 Bug Fixes, 156 Infrastructure Updates"*. It used
to show three, on the reasoning that infrastructure is the largest bucket and
the least interesting to a player; the trouble with that is a panel which drops
the largest group describes a smaller release than the one that shipped.

`other` stays out of the counts and stays visible in the changelog block, for
the reason above: a number against it measures the parser, not the release.

The list is `Mudlet_Releases_Changelog::counted()` and nothing else — the
sidebar panel on a release post and the box on the news summary both draw
whatever rows come back, so neither template knows which categories exist.
`Mudlet_Releases_Release::counts()`, the fallback that reads the release body's
own headings, carries the same four so the panel cannot change shape depending
on whether the changelog has been fetched yet.

**Records stored before this change hold three counts**, since the numbers are
written into `_mudlet_counts` when a release is detailed. Re-run the detail pass
— *Check GitHub for releases*, or `wp mudlet-releases sync` — to pick up the
fourth.

## Releases are stored, not just fetched

Each release is a `mudlet_release` post carrying its version, assets, counts and
changelog. GitHub stays the source of truth; these are a cache of record,
refreshed by sync and never hand-edited (the admin screen is read-only).

That exists so something can *ask*: the last five releases, every installer ever
shipped, what shipped in 2026. `mudlet_releases_all()` is a plain `WP_Query` —
no API, nothing to rate limit, nothing to miss.

**Deliberately not publicly queryable.** A release has no front-end URL of its
own: the announcement post is the canonical page, and a second URL for the same
thing is a "which link do I share" problem. It has an admin screen and REST, and
a download archive would be a page template that queries it. Making it public
later is easy; taking published URLs away is not.

The payoff is easy to demonstrate — with every transient dropped and the GitHub
budget at **0 of 60**, the download table, the release panels and the changelog
all still render, because none of them touch the network.

### Backfilling

Forty-odd releases, each needing a compare of up to six pages, is several
hundred requests — most of a day at 60 an hour. So the backfill runs outside
WordPress with the authenticated `gh` CLI (5000 an hour) and is imported:

```sh
node wordpress/tools/fetch-releases.mjs          # -> seed/releases.json
wp mudlet-releases import /seed/releases.json    # the seed does this for you
```

54 releases in about two minutes. The dump carries commit titles **raw** and the
plugin categorises them on import, so the rules live in one place and the file
cannot drift from what the site would have worked out itself.

Afterwards two cron jobs keep it current: a twice-daily index pass costing one
request (the releases list includes assets, and each asset carries its own
`digest`, so thirty releases' download rows come free — hashes and all), and an
hourly detail pass that fills in changelogs two at a time.

`wp mudlet-releases list` shows what is stored and what is still pending.

### The hash is in the JSON, not in the file

Every release asset GitHub returns carries `"digest": "sha256:..."` in the same
response as its name, size and URL, so a checksum costs no request at all. The
release also publishes a `SHA256SUMS.txt`, which says the same thing for one
extra round trip — that is now the **fallback**, reached only when a matched
asset has no digest, which in practice means a release old enough to predate
the field. `Mudlet_Releases_Release::digests()` is the source; `::checksums()`
is the file.

### One trap worth knowing

`store()` recomputes download rows on the cheap path, which used to mean
without checksums. Anything that misses the store and falls back to the API
lands there — so it explicitly carries existing hashes forward rather than
blanking a column that was correct a moment earlier. That bug bit once already,
and the carry-over stays even now that the digest usually fills the column
first: a release with no digest still depends on it.

## Contributors come from the same compare

The compare that produces the changelog also says who wrote each commit, so
a release knows its contributors at **no extra request**. They are stored as
`_mudlet_contributors` and read with `mudlet_releases_contributors( $ref )`:
login, display name, profile URL, avatar, and how many commits they landed,
most first.

Three things make the list worth printing rather than merely correct:

- **Bots are excluded.** Mudlet’s history has three kinds of non-human author
  and only one is spelled the obvious way — `dependabot[bot]` wears the suffix,
  `mudlet-machine-account` does not, and translation syncs land under Weblate.
  All three are named in `Mudlet_Releases_Changelog::bots()`, which is
  filterable. A credits list with a robot at the top is worse than none.
- **Co-authors count.** `Co-authored-by:` trailers are credited against the
  commit they appear on, which is how pair work and translation contributions
  get their due. In 4.22.0 that is the difference between Zooka having two
  commits and four.
- **One person is one row.** GitHub adds a trailer for the author themselves on
  a web merge, so a naive tally doubles half the list. Trailers carrying a
  `users.noreply.github.com` address give the login back exactly; the rest are
  matched to the login that name commits under elsewhere in the same release.
  Before that pass 5.0.0 listed Vadim Peretokin twice, at 332 commits and 43.

A name that still resolves to no login is listed unlinked rather than guessed
at — 5.0.0 has one, a second git identity whose display name differs by a
suffix. Merging on a fuzzy name match would eventually merge two real people.

On the front end they render under the changelog on a release post — the
theme’s `template-parts/post/contributors.php`, reading
`mudlet_post_contributors()`. Free there: the list is already on the record, so
it is a meta read, not a request.

Both paths run the same tally. `contributors_from_rows()` is the only thing
that decides anything; the live path feeds it compare commits and the backfill
feeds it `commit_authors` rows from the dump, for the same reason the
categorising rules live in one place. Verified by recomputing 4.22.0 from the
API and diffing against the imported record — identical, row for row.

## The record screen is a reader, not an editor

A `mudlet_release` is a cache of record: version, date, assets, sizes,
checksums, counts, changelog and contributors all come from the GitHub
release. The default editor offers a title field and a body box for exactly
those — an invitation to type a checksum that gets silently replaced on
Thursday.

`includes/class-admin.php` replaces it with a record screen: the release and
its counts panel, the contributors, the download table with every size and
SHA-256, and the notes as they render. No inputs, and `wp_insert_post_data`
restores the stored fields for any write the plugin did not make itself — REST
and Quick Edit included.

One action: **Re-read from GitHub**, which drops the cached compare first, so
it actually re-reads rather than handing back the answer it already had.

This plugin's three cron jobs — the index pass, the detail drain and the cache
warm — are listed with their cadences on **Mudlet → Sync**, the page the shared
menu draws. The index ships `weekly`; the detail pass ships `hourly` and is the
one number on that screen that looks wrong and is not, because it costs nothing
at all unless a record is flagged pending.

A record keeps its button here, unlike the games and makers plugins, because a
release *is* read one at a time and "re-read this one" is a real thing to want.
Over the list there is a second, **Check GitHub for releases**: the cheap index
pass, one request, which is how a new release arrives and the only thing there
is to press on a site with no records yet. Index only — the detail pass costs a
compare of up to six pages per release against a 60-an-hour limit, and the
hourly job spends that two records at a time.

Mind the boundary. This is the **record**. A release **announcement post** is
an ordinary post somebody writes, and nothing here touches it.



A release post's body is not written by anyone — it *is* the GitHub changelog.
If the code producing it lived in the theme, changing themes would blank every
release post on the site. Same for the download table's sizes and checksums:
those are facts about a release, not decisions about how a page looks.

So this owns the data; a theme asks it questions and decides what to draw.

## Using it from a theme

`includes/api.php` is the contract — the classes behind it may change shape,
those functions will not. Guard every call, so deactivating the plugin degrades
the site instead of white-screening it:

```php
$release = function_exists( 'mudlet_releases_get' )
    ? mudlet_releases_get( 'latest' )
    : null;
```

| function | cost |
|---|---|
| `mudlet_releases_get( $ref )` | 1–2 requests |
| `mudlet_releases_for_post( $post )` | as above |
| `mudlet_releases_post_tag( $post )` | free, or one request once per legacy post |
| `mudlet_releases_changelog( $ref )` | the release's own notes, as HTML |
| `mudlet_releases_changes( $ref )` | **several requests** — see below |
| `mudlet_releases_changes_cached( $ref )` | free; `null` on a miss |
| `mudlet_releases_contributors( $ref )` | free; stored with the record |
| `mudlet_releases_flush( $ref )` | — |
| `mudlet_release_markdown( $post )` | free; the post's own words, as Markdown |

### Mind the request budget

`mudlet_releases_changes()` costs one request for the releases list plus one per
hundred commits — six for a large release. **Call it on a single post, never in
a loop over an archive.** A news index listing twenty releases would be 120
requests against a limit of 60 an hour.

That is why a release's `counts` uses pull-request figures only when they are
*already cached*, and falls back to parsing the body otherwise; `counts_from`
says which you got. Viewing a post warms the cache and the index picks the
better numbers up afterwards.

For anything busier, set a token in `wp-config.php` — never in a theme or a
repository — which raises the ceiling from 60 to 5000:

```php
define( 'MUDLET_RELEASES_GITHUB_TOKEN', 'ghp_…' );
```

A read-only token with no scopes is enough for public releases.

A release array carries `id, tag, version, name, date, url, prerelease, counts,
builds, contributors, changelog, body`. `builds` is keyed `win|macarm|macx86|linux`; a
platform with no matching asset is simply absent, so iterate rather than index.

## Two things it will not do

**Invent counts.** `counts` is empty when a changelog has no
`Added`/`Improved`/`Fixed` headings — 5.0's is written as prose sections with
their own titles. The panel then shows version and date rather than three
zeroes. Use `tools/probe-release.js <tag>` to see how a release parses without
booting WordPress.

**Fail loudly on a bad network.** Every function returns `null` or an empty
value; nothing raises. Good answers are cached 12h and refreshed twice daily by
WP-Cron; *failures* are cached 15 minutes, because otherwise a GitHub outage
becomes one timing-out request per page view.

Unauthenticated GitHub allows 60 requests an hour per IP. That is ample for a
cached read, but a busy shared host can add a token via the
`mudlet_releases_http_args` filter.

## Rendering

Uses [Parsedown](https://github.com/erusev/parsedown) when available — the
upstream release plugin bundles and autoloads it, so on mudlet.org it always is
— in safe mode, so raw HTML in a changelog cannot become markup on the site.

Without it, `class-markdown.php` falls back to a deliberately small renderer
covering what GitHub release notes actually use: headings, bullets, links,
inline code, bold, italic. It escapes first and adds tags after, and only allows
`http(s)` hrefs. **It is not a Markdown implementation and should not grow into
one** — if a changelog needs more, install Parsedown.

## The other direction: a post, as Markdown

The announcement is written once. A release post is written in the editor and
the same words are wanted on the GitHub release, so `class-markdown-export.php`
renders `post_content` back out as Markdown - the inverse of the file above.

```sh
wp mudlet-releases markdown 4-22-mapping-made-friendlier > notes.md
wp mudlet-releases markdown 5798 --title --no-link
```

In the editor it is the **Markdown for GitHub** panel under the post, with Copy,
Download .md and a Refresh that re-asks
`/wp-json/mudlet-releases/v1/markdown/<id>` - the panel is drawn from the last
*save*, so the loop while writing is save, refresh, copy.

**Only the authored half comes out.** The changelog, the contributors and the
download table are not in `post_content` at all - the theme appends them at
render time - and the `[mudlet_release]` and `[MudletRelease]` shortcodes are
stripped, because the release being pasted onto already carries its own
changelog. A post that is nothing but the shortcode exports as nothing, which is
correct: there is nothing in it that anybody wrote.

Every block is rendered to HTML by WordPress and then walked as a DOM tree, so a
block the exporter has never heard of comes out as its markup's Markdown rather
than vanishing. Three shapes are intercepted first: the shortcodes above,
`core/embed` (its attribute is the URL; its markup is an iframe), and
`mudlet/games`, which stores slugs and becomes one line per game from the same
records the cards use.

Links and images come out absolute, because the text is read on github.com.
Anything Markdown cannot express - two columns, an image beside prose - is
flattened rather than dropped. It is a converter for the subset of HTML a post
is made of and, like the renderer above, **should not grow into a general one**.

## The webhook

`includes/class-webhook.php` receives GitHub's `release` event and turns it into
an announcement post. It is the second of the two jobs
[`Mudlet/mudlet-release-plugin`](https://github.com/Mudlet/mudlet-release-plugin)
did, and it exists here so that plugin can be retired.

**The same endpoint, on purpose:** `admin-ajax.php?action=post_newest_release`,
which is where the old plugin listened — so the webhook already configured on
`Mudlet/Mudlet` keeps working with nothing changed at the GitHub end. It
registers only when the old plugin is absent (`class_exists( 'MudletRelease' )`),
the same arbitration `[MudletRelease]` uses, so both can be installed during a
migration without both answering.

**The payload is not trusted.** The old plugin had no authentication at all:
anyone who knew the URL could publish to mudlet.org. Set
`MUDLET_RELEASES_WEBHOOK_SECRET` in `wp-config.php` to match the webhook's
secret and every delivery is verified against `X-Hub-Signature-256`. Without one
the endpoint still answers — otherwise this could not replace the old plugin
without a flag day — but it reads **only the tag** out of the request and
re-fetches the release from `api.github.com` itself, so nothing a forger sends
can reach the page. The worst a forged POST achieves is an announcement post for
a major Mudlet release that really exists, and it is idempotent.

**What it writes is a post with a tag in it and nothing else.** Not
`[MudletRelease]<id>[/MudletRelease]`, and not the rendered changelog: an empty
body plus `_mudlet_release_tag`, which is the shape the rest of this plugin is
built around. The changelog renders through
`Mudlet_Releases_Content::maybe_append_changelog()`, an editor who writes an
opening paragraph keeps it and gets the changelog underneath, and
`wp mudlet-releases markdown` can still hand that paragraph back to GitHub
without the changelog coming with it. `post_excerpt` is left empty for the same
reason — `wp_trim_excerpt()` applies `the_content`, so the news listing gets an
excerpt without one being stored.

Rules kept from the old plugin: **prereleases skipped**, a **draft release makes
a draft post**, and `created` / `edited` are the actions acted on. Two of its
details were not: the category is resolved by slug (`release-en`, then
`release`) rather than the hardcoded term id `173`, and the author is the site's
oldest administrator rather than user `2` — both of those numbers are facts
about one database rather than about Mudlet.

**Point releases get a post too**, which is the one rule deliberately reversed —
and the one that is a **setting** rather than a filter, because it is a real
editorial choice rather than a bug. The old plugin skipped any tag not ending in
`.0`, and that reads like policy until you count the site: of 92 posts on
mudlet.org whose title carries a version, **16 are point releases** — and
4.17.1, 4.19.1 and 4.20.1 are written in `[MudletRelease]` shortcode style,
which is what a post made by hand from a copy of the previous one looks like.
The rule was never the policy; it was a limitation somebody had been working
around by hand for years.

So it defaults to on and there is a checkbox — **Mudlet → Releases**, *Announce
bugfix releases too* — for a site that would rather have four announcements a
year than twelve. `mudlet_releases_webhook_major_only` still has the last word
over the checkbox, the way a filter should: code beats a setting.

Everything else is a filter: `…_actions`, `…_author`, `…_category`, `…_post`,
`…_secret`.

### The Releases screen

`includes/class-settings.php`, under the Mudlet menu. Three things, and two of
them are not settings at all:

- **The payload URL**, to paste into the webhook form on github.com. It is
  `admin-ajax.php?action=post_newest_release` and it is printed here because
  the one moment anybody wants it is while filling in a form on another site.
- **Whether a secret is set** — never its value. Green if
  `MUDLET_RELEASES_WEBHOOK_SECRET` is defined and deliveries are verified, red
  if not, with what to do about it.
- **The point-releases checkbox** above.

It also says so, in place, when the older `mudlet-release` plugin is still
active and answering the endpoint instead — which is the one state where this
screen would otherwise describe something that is not happening.

A skip answers `200` with a line saying which rule skipped it, so a red delivery
in GitHub's log means something is actually wrong.

### Going back for the assets

A delivery arrives at the one moment the release has **no installers on it**.
That is not a race, it is how `Mudlet/Mudlet` publishes:

```sh
gh release create "${ARGS[@]}" assets/SHA256SUMS.txt \
  || gh release upload "${RELEASE_TAG}" assets/SHA256SUMS.txt --clobber
gh release upload "${RELEASE_TAG}" "${BINARIES[@]}" --clobber
```

The release is created carrying only `SHA256SUMS.txt`; the binaries go up in a
separate call, deliberately, so a half-finished upload never leaves a checksum
file covering a binary that is not there. And uploading an asset fires **no**
webhook at all — the `release` event's actions are `published`, `created`,
`edited`, `deleted`, `prereleased`, `released`, and none of them covers an asset
appearing.

So the webhook books a short chain of single events — **5, 15 and 45 minutes** —
that reads the release again and re-stores it. It **stops the moment the record
has download rows**, so a release that was already complete costs nothing, the
ordinary case costs one or two requests, and there is no idle work of any kind
in between. The event carries the tag as an argument, so a re-delivery finds the
booked event rather than stacking a second one.

`deleted` is handled too: the **record** goes, because a record is an
observation of a release and there is no longer one to observe. The announcement
post is left alone — it is somebody's writing with a public URL people have
linked, and deleting a release on GitHub is not an instruction to unpublish an
article. A `deleted` for a prerelease finds nothing and says so, which is the
answer for every one of the public test builds Mudlet deletes by the dozen.

### What this means for the scheduled syncs

Both are on the `Mudlet → Sync` screen and both can be set to **Never**. What
each is actually worth once the webhook is running:

| job | still worth running? |
| --- | --- |
| detail, hourly | **No.** The webhook details a release on arrival and the chain retries it. With nothing pending this pass makes zero HTTP requests — `pending()` is one indexed `get_posts` — so it costs almost nothing either way, but it no longer has a job. |
| index, weekly | **Yes, as insurance.** One request a week, and it is the only thing that would ever notice a release the webhook *missed* — a delivery that failed while the site was down, mid-deploy, or behind a 5xx. **GitHub does not retry a failed delivery on its own.** |

The argument for keeping the index pass is not that it does work — it will find
nothing, most weeks, for ever. It is that a release happens every few months, so
a missed one is invisible for a long time and costs an announcement post and a
download table. One request a week is a cheap alarm.

## Relationship to the upstream plugin

With the webhook above in place, this plugin does both of
[`Mudlet/mudlet-release-plugin`](https://github.com/Mudlet/mudlet-release-plugin)'s
jobs and that one can be deactivated. While both are installed, this stands
down from both seams:

- **`[MudletRelease]` is only registered here when that plugin is not active**,
  so the two never fight over the same content — and a site that drops it does
  not lose the body of every release post it ever published.
- **the webhook is only answered here when that plugin is not active**, for the
  same reason and by the same check.
- **Legacy ids are upgraded automatically.** Posts created by that plugin store
  a release *id*, not a tag. The first time such a post is read, the id is
  resolved and the tag written back, so it costs one lookup per post ever.

### The bug that makes this matter

That plugin writes the release **id** into the shortcode but reads it back as a
**tag name**:

```php
'post_content' => '[MudletRelease]' . $result->id . '[/MudletRelease]'   // 378895178
GetHttpWrapper::get(GITHUB_API_URL . "releases/tags/$content")           // 404
```

`releases/tags/378895178` does not exist; `releases/378895178` does. On
mudlet.org it never shows, because the same webhook calls `set_transient()`
**with no expiry** — the body is cached forever and the fallback is never
reached. A fresh install has no transients, so every imported release post falls
through it.

Dropping `tags/` fixes it upstream. `wordpress/seed/setup.sh` patches the
installed copy meanwhile; once upstream is fixed, that patch stops matching and
becomes a no-op.

## Moving it upstream

This lives in the mudlet.org site repo for now so it can settle alongside the
theme it was written for. It is structured to be lifted into
`Mudlet/mudlet-release-plugin` as-is: no dependency on the theme, no shared
state, and its whole public surface is `includes/api.php`. Merging it there
would leave one plugin owning releases end to end — webhook, posts, data — and
let the `[MudletRelease]` compatibility shim retire.
