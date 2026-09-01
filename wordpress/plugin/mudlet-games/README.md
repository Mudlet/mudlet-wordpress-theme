# Mudlet Games

The games Mudlet bundles, as WordPress posts — name, host, port, TLS flag,
website links, description, and the logo, all read from the client's own source
rather than typed by anyone.

```sh
wp mudlet-games sync            # read upstream, write what changed
wp mudlet-games sync --force    # rewrite every post regardless
wp mudlet-games status          # what is on record
```

## Where the list comes from

Mudlet ships connection profiles for forty-odd MUDs. That list is not an
editorial decision anybody makes on the website — it is
[`src/TGameDetails.h`](https://github.com/Mudlet/Mudlet/blob/development/src/TGameDetails.h),
`scmDefaultGames`, with the logos in `src/icons/` beside it. A game is added to
*Mudlet*, not to mudlet.org.

The front page's grid used to be a PHP array in the theme kept in step by hand,
and it was drifting. This reads the header instead.

Two of the forty-five entries are not games and are flagged `internal` and never
drawn: `Mudlet Tutorial` connects to localhost, and `Mudlet self-test` says in
its own description that it "isn't a game profile". They are recognised by what
they lack — a real game has a host and an icon — rather than by name, so a
renamed one does not quietly reappear in the grid.

## Why raw.githubusercontent.com and not the API

`api.github.com` allows 60 requests an hour unauthenticated. A first sync is one
header plus forty-odd logos, so the API would rate-limit the very first run on a
site with no token, and requiring a token to draw a logo grid is a bad trade.
`raw.githubusercontent.com` is CDN-served, needs no auth, and is not under that
cap. Nothing here touches the API.

Provenance is a SHA-256 of the header that was actually parsed, because a raw URL
carries no commit id. Unchanged digest, unchanged list.

## Why there is a C++ parser in a WordPress plugin

Upstream publishes no JSON, no API and no generated manifest: the list is a
brace-initialised `QList<GameDetail>` a human maintains, with comments between
the fields and descriptions spread over a dozen adjacent string literals.
Getting upstream to emit a machine-readable copy is the better long-term answer;
until then, this reads what exists.

`includes/class-source.php` is a scanner rather than a regex, and deliberately
so: both `//` and `,` occur *inside* the data — every website field is an
`<a href='http://…'>` — so anything not string-aware corrupts entries instead of
failing loudly. It was written twice — once in node, once here — and the two
were diffed field by field over the same header; the node one has since been
deleted, so this is now the only copy, and a change to it has nothing to check
itself against.

## How it gets in

**`sync`** reads the header live. First run costs one header, one `.qrc` and
forty-odd images; every run after that is one header, because an unchanged
digest short-circuits. Cron does this daily.

A digest match alone is not enough to skip, though, and that is the subtle part:
the store can be short of what upstream has — a post somebody deleted, a logo
whose download failed — and the only thing that would trigger a repair is
upstream changing, which is exactly what has not happened. So it skips only when
the store *also* looks complete: as many posts as the last pass counted, every
one of them with a logo.

"With a logo" is a question about the picture, not about `_thumbnail_id`. A WXR
import sets that meta on every record it carries and can only remap it if the
attachment travelled in the same file and its media downloaded — so a record
imported from a site the new one cannot reach names an attachment that is not
there. The number survives, the picture does not, and asking the easy question
meant the store looked complete while the grid drew nothing. A thumbnail that
resolves to no attachment counts as missing, and is cleared and re-fetched.

Upstream also redraws a logo now and then and keeps the filename, so nothing in
the header says it happened. On a **refresh** — `sync --force`, the record
screen's button, an `import`, or a game whose icon upstream has renamed — the
bytes are read again and compared with a digest of what is attached
(`_mudlet_game_icon_sha`). Same picture, nothing happens; different, the new one
is attached and the old deleted, so a forced sync never churns the media library.
A scheduled run does not refresh: its whole budget is the one header.

There is deliberately no offline path beside it — no checked-in list, no logos
in the theme, no `import`. One of those decided how stale a new site started
out, and two ways in were two things to keep true.

The upsert is keyed on the upstream game name.

## Posts, not an option

A `mudlet_game` post gives every game a URL, a featured image, an excerpt, REST
and search — all for free, and all reusable by any template that runs a
`WP_Query`. An option holding a serialised array gives none of that.

Unlike the release store next door this post type is **public**: a release
already has a canonical page (its announcement post), so a second URL for the
record would be two addresses for one thing, whereas a game has no page at all
and wants one. `/games/` and `/games/achaea/` are drawn by
`archive-mudlet_game.php` and `single-mudlet_game.php` in the theme.

Every field is overwritten on the next sync, the body included. It was not
always — the description used to be written only on creation, so an editor could
improve on upstream's blurb without a nightly job reverting it. That made sense
while the record was editable; now that the screen is read-only, "written once,
never again" would mean a description frozen at whatever it was the day the post
appeared, with nothing on earth able to correct it. A field upstream owns beats a
field nobody owns.

Creating games in wp-admin is disallowed (`create_posts => do_not_allow`): a
hand-made one would be a page for a profile nobody can connect to.

Deactivating the plugin leaves the posts alone. It is not an instruction to
delete forty pages.

## The admin screen is a reader, not an editor

A game post is not authored, it is *observed*, and the default post editor
gets that exactly wrong: a body box and a custom-fields table, both of which
look editable and neither of which survives the next sync. Somebody fixes a
typo, cron reverts it, and the lesson learned is that the site is broken.

So `includes/class-admin.php` replaces it with a record screen — the logo, the
connection facts, the links, the description as it renders, and a sidebar
saying where it came from and when it last synced. No inputs. The list table
gets the logo, `host:port` and website instead of a date nobody set, and Quick
Edit is gone.

Two actions remain, because they are the only two that make sense on a record:
**Sync from Mudlet**, and a link to the source header on GitHub.

Behind the screen is a real guard, not just an absence of fields:
`wp_insert_post_data` restores the stored title, body, excerpt and slug for any
write the plugin did not make itself. That covers REST and Quick Edit as well
as the form, because read-only that only holds on one path is a suggestion.
Status changes pass through, so a record can still be trashed — that is
housekeeping, not authoring.

## The theme seam

Everything the theme calls goes through `includes/api.php` and is guarded with
`function_exists()`, so deactivating this degrades the site rather than breaking
it: `inc/games.php` returns nothing, the front page skips its games section,
and an admin notice says why. Deliberately no typed fallback list - see the
makers plugin, which has never had one, for the same argument.

| | |
|---|---|
| `mudlet_games( [ 'number' => 15, 'orderby' => 'rand' ] )` | the games |
| `mudlet_game( $slug_or_post )` | one game |
| `mudlet_games_count()` | how many are bundled |
| `mudlet_games_url()` | the `/games/` archive |

## Putting games in a post

A release announcement wants to introduce the games that release added. Mudlet's
own 5.0 post does it with four page-builder cards — logo, name, blurb, link —
typed into the post body, which is a copy of four records this plugin already
keeps and is wrong the day one of them moves.

`mudlet/games` is a block that stores **slugs**. An editor picks games from a
token field; what gets drawn is looked up when the page renders, the same way
the front page's grid is. A slug whose record has gone is dropped, and a block
with nothing left renders nothing at all.

It does **not** guess which games those are. Deriving it — from the release's
changelog, or from the history of `TGameDetails.h` — was tried both ways and
both are more machinery than picking four games from a list is worth. The block
opens empty.

### Why the block is here and not in the theme

WordPress renders an unregistered dynamic block as nothing at all: no markup, no
comment, no trace. A games block owned by the theme would therefore delete that
section from every past announcement the day somebody changes themes, silently
— the same argument that puts the post type here rather than there.

The look is still the theme's. The render callback hands off to
`template-parts/blocks/games.php` when the theme provides one, and only falls
back to a plain list of links when it does not.

There is no build step: `assets/block-games.js` is plain ES5 against the `wp.*`
globals, and the game list the picker offers is localised into it rather than
fetched, because forty rows of two short strings do not need a REST route.
