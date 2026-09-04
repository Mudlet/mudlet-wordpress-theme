# Matomo on mudlet.org

Draft, 2026-09-03. Companion to [`MIGRATION.md`](MIGRATION.md).

Two halves: **the three bugs**, which are not opinions and should be fixed
whatever else happens, and **what to track**, which is a proposal.

## Where we are

- Matomo On-Premise at `stats.mudlet.org`, site ID 1, injected by the **Connect
  Matomo** plugin. Stock tracker, no premium plugins, no Tag Manager.
- **There is a second, hand-pasted copy of the same snippet** — same site ID,
  byte-identical config — in the Apache template behind
  `/wp-content/files/` (the download archive index). It is not WordPress, so
  Connect Matomo does not manage it. **Every tracker config change below has to
  be made in both places**, or the archive page keeps counting PNGs and missing
  AppImages after the site has been fixed. This is the most likely thing to
  drift silently.
- Loaded on `www.mudlet.org` and `forums.mudlet.org`. **Not** on
  `wiki.mudlet.org`, although the wiki is listed in `setDomains`.
- No Google Analytics anywhere, despite what the privacy policy still says.
- The whole configuration is: `setDocumentTitle`, `setCookieDomain`,
  `setDomains`, `enableCrossDomainLinking`, `trackPageView`, `enableLinkTracking`.
- No custom events. One goal, "Download Mudlet". Site search enabled.
- Opt-out via Matomo's `CoreAdminHome&action=optOut` iframe on the privacy page.
  Cookie Notice is installed but does **not** gate the tracker.

## The three bugs

### 1. Downloads are not being recorded at all

The download buttons link to `/download/71/`…`/74/` — WP-DownloadManager
permalinks with **no file extension**. Matomo classifies a link as a download by
its extension, so it never sees them, and the goal (which anchors on an
extension at end-of-string) never fires.

What the Downloads report actually contains is clicks on the archive index at
`/wp-content/files/?C=M;O=D` — which carries its own copy of the tracker, and
whose links are real filenames with real extensions, so they *are* counted:
4.0.0, 4.1.1, 4.10.0, 4.15.0, 4.15.1, 4.21.1, one each — all old, **and the
current release absent entirely**, because the current release is only reachable
from the download page, whose links Matomo cannot see.

Ten recorded downloads, none of them from the download page. Real numbers for
the current build exist only in WP-DownloadManager's own counter.

**Fix, two halves.** Point the download rows at `/wp-content/files/<name>`, per
`MIGRATION.md` decision 3 — the goal's second branch,
`wp-content\/files\/mudlet.*`, is already written and starts matching on its own.

Then, for the links that keep no extension by design — `/download/71/`…`/74/`
are stable per-platform aliases and worth keeping — track them explicitly rather
than restructuring them:

```js
_paq.push(['trackLink', url, 'download']);
```

That records a no-extension URL as a download, which means the choice is not
between stable links and countable ones.

### 2. Images count as downloads, and `/media/` will make it much worse

Matomo's default extension list includes `png`, `jpg` and `gif` — which is why
`LIxJiq6.png` and `screen_druid.jpg` are in the downloads report.

On the new site every `/media/` gallery image links to its own file, so **every
lightbox open would register as a download**. Fifteen screenshots and a carousel
will bury the actual installers.

**Fix:** replace the list rather than extend it.

```js
_paq.push(['setDownloadExtensions',
           'exe|dmg|zip|tar|gz|xz|bz2|appimage|bin|deb|run|txt']);
```

`appimage` and `run` are **not** in Matomo's default list — verified by reading
the live `matomo.js`. `Mudlet.AppImage` is already in the archive, so this is
needed the moment it is linked.

### 3. The goal misses half the builds

The pattern is:

```
downloads?\/mudlet.*(dmg|exe|AppImage|tar|bin|run)$|wp-content\/files\/mudlet.*(dmg|exe|AppImage|tar|bin|run)$
```

Anchored at end-of-string, so `Mudlet-5.0.1.tar.xz`,
`-linux-x64-portable.tar.gz` and `-windows-64-portable.zip` **never count as
conversions** — `xz`, `gz` and `zip` are not in the set. Add them.

## What to track

Nothing here is instrumented today, so all of it is a decision rather than a
repair. Ordered by what it would actually answer.

### High value

| Event | Why |
| --- | --- |
| Drawer opened on a download row (`category: download`, `action: drawer`, `name: <platform>`) | The QR and email-a-link features exist so the build can be fetched on **another machine**. Matomo can never see that fetch, so the drawer opening is the only intent signal that exists. Without it the new download page will appear to convert *worse* than the old one while actually working better. |
| QR shown / link copied / email sent | Same reason, one level finer. Also tells you which of the three people actually use — the drawers were built on a guess. |
| `telnet://` "Play in Mudlet" click on `/games/` | Not http, so not an outlink, not a download, not anything. This is the single most interesting conversion on the new site: someone chose a MUD and launched it. Probably deserves a goal of its own. |
| Palette search (`trackSiteSearch`) | Site search is enabled but the palette answers over XHR and jumps straight to the result, so the primary search path records nothing. Include which source the chosen row came from — site, or wiki. |

### Worth having

| Event | Why |
| --- | --- |
| Demo hero: reached ready vs. stayed scripted | The hero has a 12s timeout and a documented single-owner-per-tab failure. Right now nobody would know how often visitors see the scripted fallback instead of the live client. |
| Demo hero: first command typed | The difference between "saw a terminal" and "played with it". One event, not a stream — this is not session recording. |
| `/games/` filter and search use | The facets were chosen on an argument (connection flags are facts about playing, forums are not). This is how you find out whether the argument held. |
| Screenshot submitted / approved | The queue is new and its abuse story is untested. Volume is worth watching. |
| Download row: which platform was chosen vs. which the page auto-detected | Tells you whether OS detection is right, which is otherwise unfalsifiable. |

### For the migration itself, temporarily

| Event | Why |
| --- | --- |
| **404s** | Connect Matomo can report these. Turn it on *before* Polylang is deactivated: ~162 translated URLs stop resolving that day, and a 301 table built by hand will have holes in it. This is the cheapest way to find them, and it is the difference between "we redirected the ones we thought of" and "we redirected the ones people actually ask for". |
| Referrers to `/de/`, `/it/`, `/ru/`, `/zh/` | Tells you whether anything still links to the translations, and from where, before you decide whether to delete the posts or leave them unpublished. |

Both can come back off once the 404 rate settles.

### Deliberately not tracked

- **Nothing about who.** No user IDs, no custom dimension identifying a visitor,
  no names from the Discord widget. The contact page already refuses to print
  names for a reason.
- **No scroll depth, no heatmaps, no session recording.** The stock tracker does
  not do these and adding them would change what mudlet.org is.
- **No third-party analytics.** The privacy policy names Google Analytics; the
  site does not load it, and it should not start.

## Also worth fixing

- **The privacy policy is wrong.** It claims Google Analytics and Webalizer. The
  new site seeds `/privacy-policy/` empty (`seed/setup.sh:162`), so it has to be
  rewritten anyway — and the Matomo opt-out iframe has to be carried over with
  it, or the opt-out silently disappears.
- **Consent is not wired.** Cookie Notice is installed; the tracker fires
  unconditionally and sets cookies on `*.mudlet.org`. Carrying that over is a
  choice, not an oversight to preserve. `requireCookieConsent` is the
  alternative.
- **The wiki has no tracker** but is in `setDomains`. Either add it or drop it
  from the list; as it stands the cross-domain config describes a site that does
  not exist.
- **Keep `setDomains` and `setCookieDomain` byte-identical** through the
  migration. `forums.mudlet.org` runs the same tracker and cross-domain visit
  stitching depends on them matching.
- **Annotate the launch** in Matomo. URL shapes change underneath every report;
  a break in the download series should have a reason attached to it.

## Implementation notes

`_paq` is a global array, so extra configuration can be pushed after Connect
Matomo's snippet from an ordinary `wp_footer` hook — it does not require
modifying the plugin. Pushes are queued and applied in order, so a later
`setDownloadExtensions` still takes effect before any click is evaluated.

Events belong in `theme/mudlet/assets/js/theme.js` beside the interactions they
describe, behind a single guard so a site with no Matomo is unaffected:

```js
function track(category, action, name) {
  if (window._paq) window._paq.push(['trackEvent', category, action, name]);
}
```
