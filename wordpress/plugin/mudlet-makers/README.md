# Mudlet Makers

The people who make Mudlet, as WordPress posts — name, GitHub and Discord
handles, what they did, and their avatar, all read from the client's own credits
rather than typed by anyone.

```sh
wp mudlet-makers sync            # read upstream, write what changed
wp mudlet-makers sync --force    # rewrite every post, and retry refused avatars
wp mudlet-makers status          # what is on record
```

## Where the list comes from

Mudlet credits thirty people in Help → About → About Mudlet, and that list is
not an editorial decision anybody makes on the website — it is the `aboutMakers`
vector in
[`src/dlgAboutDialog.cpp`](https://github.com/Mudlet/Mudlet/blob/development/src/dlgAboutDialog.cpp),
maintained by the people it names. Somebody joins the project and is added to
*Mudlet*, not to mudlet.org.

The live site's `/the-makers/` page is a hand-typed copy of that list from around
2010. It credits twelve people, several long since moved on, and omits most of
the team that exists now — including two of the three people who have written the
most of the client. That is what happens to a list somebody has to remember to
update. This reads the dialog instead.

Alongside the people it carries two things that belong to the page rather than to
a person, both kept in options:

- the **acknowledgements** — the prose Mudlet prints under its own credits: that
  the list is incomplete and where the rest of the names are, who drew the icons,
  and thanks to a few people who never committed a line but shaped Mudlet anyway;
- the **supporters** — the patreon names, in the two tiers the dialog frames them
  in, and the sentence it puts above them. A name on a plaque is not a record and
  gets no post; the sentence comes along because the page has no sword frames or
  plaques to paint the names onto, and upstream's wording beats one invented
  here. It is a C++ *raw* string literal (it carries an `<a href>`), which is why
  the parser learned `R"(…)"` — and why it reads that one function from the raw
  file: `strip_comments()` does not know about raw strings, and the `https://`
  inside the sentence looks to it like a line comment.

## What is deliberately not copied

The dialog lists an **email address** for two thirds of these people. A dialog is
not a crawled web page: publishing those addresses would turn a credits page into
a spam list, and nobody agreed to that by contributing to a MUD client. The
parser reads the field and drops it; it is never stored, and one address that
rides along inside the acknowledgement prose is stripped there too.

**Avatars** are the other departure, in the opposite direction: they are *not* in
the Mudlet repository. There is no picture of these people anywhere the project
controls, so the avatar is GitHub's, at `https://github.com/<handle>.png`.
Eighteen of the thirty publish a handle; two of those eighteen 404, being
accounts renamed or closed years after the credit was written. Everybody else is
drawn as their initials, which makes the monogram the normal case rather than an
error path.

## Why raw.githubusercontent.com and not the API

Same trade as the games plugin next door. `api.github.com` allows 60 requests an
hour unauthenticated, and requiring a token to draw a credits page is a bad deal.
`raw.githubusercontent.com` is CDN-served, needs no auth, and is not under that
cap.

Provenance is a SHA-256 of the file that was actually parsed, because a raw URL
carries no commit id. Unchanged digest, unchanged list.

## Why there is a C++ parser in a WordPress plugin

Upstream publishes no JSON, no API and no generated manifest: the credits are a
`QVector<aboutMaker>` a human appends to, each entry a brace-initialised struct
whose last field is a `tr()` of a dozen adjacent string literals with translator
comments in between. Getting upstream to emit a machine-readable copy is the
better long-term answer; until then, this reads what exists.

`includes/class-source.php` is a scanner rather than a regex, and deliberately
so: `//`, `,` and `{}` all occur *inside* the data — several descriptions carry a
URL or an `<a href="…">` — so anything not string-aware corrupts entries instead
of failing loudly. It was written twice — once in node, once here — and the two
were diffed field by field over the same file; the node one has since been
deleted, so this is now the only copy.

## How it gets in

**`sync`** reads the dialog live. First run costs one file and eighteen small
images; every run after that is one file, because an unchanged digest
short-circuits. Cron does this daily.

A digest match alone is not enough to skip: the store can be short of what
upstream has — a post somebody deleted, an avatar whose download failed — and the
only thing that would trigger a repair is upstream changing, which is exactly
what has not happened. So it skips only when the store *also* looks complete.

"Complete" has to be defined carefully here, or it is never true. Twelve makers
publish no handle and so can never have a picture, and two more have handles
GitHub 404s; counting either as missing would mean rewriting all thirty posts and
re-asking GitHub for two dead avatars every single night. So a refusal is
recorded on the post (`_mudlet_maker_no_avatar`) and excluded, and only
`sync --force` tries those again.

It is a question about the picture, though, not about `_thumbnail_id`. A WXR
import sets that meta on every record it carries and can only remap it if the
attachment travelled in the same file and its media downloaded — so a record
imported from a site the new one cannot reach names an attachment that is not
there. The number survives, the picture does not, and asking the easy question
meant the roster looked complete while drawing thirty blank circles. A thumbnail
that resolves to no attachment counts as missing, and is cleared and re-fetched.

People also change their GitHub picture, and `github.com/<handle>.png` is the
same URL forever, so nothing upstream says it happened. `sync --force` (and the
record screen's button, and an `import`, and anybody whose handle has changed)
re-reads the bytes and compares them with a digest of what is attached
(`_mudlet_maker_avatar_sha`): same picture, nothing happens; different, the new
one is attached and the old deleted, so forcing never churns the media library.

There is deliberately no offline path beside it — no checked-in list, no avatars
in the theme, no `import`. Same argument as the games plugin next door.

The upsert is keyed on the upstream name, and the dialog's order is
kept in `menu_order` rather than recomputed — it lists the current team first, in
an order nobody could derive, and that is upstream's decision to make.

## Posts, not an option

A `mudlet_maker` post gives every person a URL, a featured image for the avatar,
REST and search — all for free. An option holding a serialised array gives none
of that.

The post type is **public** but has **no archive**: `/the-makers/` is an ordinary
editable page with paragraphs on it, and registering an archive on that path
would have the post type quietly take the page over. The roster is drawn into the
page by `page-the-makers.php` in the theme; the makers keep their own URLs at
`/the-makers/<name>/`, rendered by `single-mudlet_maker.php`.

Every field is overwritten on the next sync, the body included. Creating makers
in wp-admin is disallowed (`create_posts => do_not_allow`). Deactivating the
plugin leaves the posts alone — it is not an instruction to delete thirty pages.

## The admin screen is a reader, not an editor

A maker post is not authored, it is *observed*, and the default post editor gets
that exactly wrong: a body box and a custom-fields table, both of which look
editable and neither of which survives the next sync.

There is a second reason here that the games plugin does not have. These are
people. A screen inviting an editor to reword what somebody wrote about their own
contribution is a bad idea even when a sync is not about to undo it — that
sentence is theirs, and the place to change it is the client, in a pull request
they can see.

So `includes/class-admin.php` replaces it with a record screen: the avatar, the
handles, the sentence as it renders, and a sidebar saying where it came from and
when it last synced. No inputs. The list table gets the face, standing and handle
instead of a date nobody set, ordered as the dialog orders them, and Quick Edit
is gone.

Behind the screen is a real guard, not just an absence of fields:
`wp_insert_post_data` restores the stored title, body, excerpt, slug and order for
any write the plugin did not make itself. That covers REST and Quick Edit as well
as the form, because read-only that only holds on one path is a suggestion.

## The theme seam

Everything the theme calls goes through `includes/api.php` and is guarded with
`function_exists()`, so deactivating this degrades the site rather than breaking
it. There is no hardcoded fallback roster on purpose: a typed list of people is
the exact thing this replaces, and shipping one in the theme would reintroduce
the bug deliberately. Without the plugin the page keeps its prose and points at
the contributors graph, and an admin notice says why.

| | |
|---|---|
| `mudlet_makers( [ 'group' => 'core' \| 'past' \| 'all' ] )` | the makers, in upstream's order |
| `mudlet_maker( $slug_or_post )` | one maker |
| `mudlet_makers_count()` | how many are credited |
| `mudlet_makers_acknowledgements()` | the prose under the credits |
| `mudlet_makers_supporters()` | the patreon names, by tier |
