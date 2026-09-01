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
| download rows — size, URL, SHA-256 per platform | the release assets and its `SHA256SUMS.txt` |

Nobody types a number that can drift from what shipped.

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

The release panel still shows three figures — added, improved, fixed — because
that is what the design draws and what a player cares about. Infrastructure and
other appear in the changelog block, infrastructure as a single counted line
since it is the largest and least interesting group.

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
request (the releases list includes assets, so thirty releases' download rows
come free), and an hourly detail pass that fills in checksums and changelogs two
at a time.

`wp mudlet-releases list` shows what is stored and what is still pending.

### One trap worth knowing

`store()` recomputes download rows without checksums, because fetching those
costs a request. Anything that misses the store and falls back to the API lands
there — so it explicitly carries existing hashes forward rather than blanking a
column that was correct a moment earlier. That bug bit once already.

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

## Relationship to the upstream plugin

[`Mudlet/mudlet-release-plugin`](https://github.com/Mudlet/mudlet-release-plugin)
does a different job and the two are meant to run together:

- **it** receives the GitHub `release` webhook and creates the announcement post
  in every Polylang language, stamping each with a `release-post` meta key;
- **this** turns a tag into data, and renders changelogs.

Where they touch:

- **`[MudletRelease]` is only registered here when that plugin is not active**,
  so the two never fight over the same content — and a site that drops it does
  not lose the body of every release post it ever published.
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
