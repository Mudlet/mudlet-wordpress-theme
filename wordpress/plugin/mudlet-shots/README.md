# Mudlet Screenshots

A form anybody can use to send the site a screenshot, a queue that keeps what
they send out of reach until somebody has looked at it, and one button that
puts the ones that pass into the gallery on `/media/`.

The other three plugins here read facts out of Mudlet. This one is the only one
whose input is a stranger, which is why it is written the way it is.

## Where this runs

Normally **inside the theme**. `mudlet.zip` carries this plugin under
`plugins/mudlet-shots/` and the theme's `functions.php` requires it, so a site
installs one archive and activates nothing.

`mudlet-shots.zip` is still built, and still published on every release, for a
site that would rather have it in `wp-content/plugins` — and a copy there
**wins**: WordPress loads plugins long before it reaches a theme, so
`MUDLET_SHOTS_VERSION` is already defined by the time the theme looks and the
theme stands down. An installed copy older than the theme's gets an admin
notice rather than being a silent surprise.

Either way the data is the same — `mudlet_shot` posts and the files behind them
— and it outlives both. What changes is only wiring, all of it in
`shared/mudlet-bundle.php`. See the theme's `inc/bundled-plugins.php`.

## Putting the form on a page

Nothing draws until somebody puts `[mudlet_screenshot_submit]` in a page body.
That is deliberate: `/media/` has no template — it is blocks and nothing else —
so where a form goes is a decision about the page, and a plugin does not get to
make it. Mudlet → Screenshots says so on a site where nobody has yet.

A shortcode rather than a block, and for one reason: WordPress renders an
unregistered dynamic block as **nothing at all**, silently, which is the same
argument that puts the `mudlet/games` block in the games plugin. A shortcode
nobody has registered renders as its own source — visible, ugly, and fixable by
the first person who reads the page. For a submission form, visible-and-ugly is
the better failure: a form that has quietly not been there for three months
looks exactly like a form nobody has used.

The **look** is the theme's. `mudlet_shots_form()` hands off to
`template-parts/blocks/screenshot-submit.php` when the theme has one and falls
back to a plain form when it does not, the same arrangement the games block has.
The class names in that markup are a contract with `assets/shots-form.js`, which
lists them in its own header.

## The queue is not the media library

**This is the whole design, and everything else here follows from it.**

An attachment has a public URL from the instant it exists. Not when it is
published — when the *file* is written, because the file is served by the web
server, which knows nothing about post status. So a submission form that made
attachments would not be a moderation queue at all: it would be an open image
host on mudlet.org, and the review would only decide whether the picture was
*also* on a page.

A pending submission is therefore a file in
`wp-content/uploads/mudlet-shots/<32 hex characters>/` and a `mudlet_shot` post
pointing at it. Nothing queries it, no picker offers it, and the only way to see
it is the review screen, which streams the bytes through `admin-post.php` behind
a capability check and a nonce.

Three things stand between the queue and the web, in decreasing order of how
much they are relied on:

1. it is not an attachment, so nothing on the site knows it is there;
2. the directory has an `index.html` and a deny-all `.htaccess` — which Apache
   honours and nginx does not;
3. the path carries 32 random characters, which is what actually holds when the
   server is nginx.

An attachment is created at exactly one moment: when somebody with `edit_pages`
presses **Add to the gallery**.

## Nothing is stored as it arrived

Every accepted file is decoded by GD or Imagick and written back out as WebP,
scaled to fit 2560px on the long edge. The uploaded bytes are unlinked and never
reach the media library.

That is the space saving — a 4MB PNG of a terminal lands around 300KB, and
`/media/` is a page of fifteen of them — but the re-encode is doing three other
jobs at once, and any one would justify it alone:

- **It is the file-type check that cannot be fooled.** An extension is a
  suggestion and a `Content-Type` is whatever the client typed. A file that
  survives being decoded and re-encoded is an image, because a decoder read it
  and produced pixels. PHP-in-a-PNG and every other polyglot is *gone* rather
  than guarded against.
- **It drops EXIF.** A screenshot carries none. A phone photo of somebody's
  screen carries where they were standing, and people do send those.
- **It makes the pictures one shape**, which a gallery of community shots
  running from 0.86 to 1.86 wide can use.

WebP where the site can write it, JPEG where it cannot. PNG is deliberately not
the fallback: re-encoding a screenshot to PNG saves nothing, which is the one
thing this was asked to do.

## Animation is a second path, and it has to be

`wp_get_image_editor()` **flattens an animation to its first frame** — not as a
bug, it is an editor for *an* image — so the pipeline above would silently turn
somebody's twelve-second recording of a trigger firing into a picture of a
terminal. Measured on this stack: four frames in, one frame out.

So an animated upload is decoded frame by frame through Imagick directly,
`coalesceImages()` first because an optimised GIF's frames are partial and
resizing them apart is how you get a smear. Every frame is resized and
stripped and the lot is written back out as **animated WebP** — smaller than
GIF by a wide margin (a real 14-frame test: 540 KB in, 154 KB out, all 14
frames, loop count and per-frame delays intact) and understood by every browser
this site targets.

Two things there are worth not undoing:

- **The output is verified, not assumed.** A libwebp built without its mux
  library writes one frame and reports success, so the file is read back and its
  frames counted; a flattened result falls back to animated GIF rather than
  being published as a still of something that moved.
- **A site with no Imagick refuses animations rather than flattening them.** GD
  cannot write an animated anything. Somebody who sends a GIF sent it because it
  moves, and quietly publishing frame one answers a question they did not ask.
  Still GIFs are accepted everywhere — they go down the ordinary path.

Animations are held to a smaller box (1280px, half the stills') and capped at
120 frames, with a third budget on `frames × width × height` because coalescing
holds every frame at full canvas size. All three are checked from the file's
header — `getimagesize()` and Imagick's `ping`, neither of which decodes pixels
— before any decoder is handed a stranger's file.

### Why the gallery points at the original

Every size WordPress derives goes through that same flattening editor, so **an
animated attachment's sub-sizes are all stills**. A slide pointing at `large` is
therefore a frozen picture that only moves once somebody clicks it. So for an
animated attachment the image block is written with `sizeSlug: full` and its
`src` aimed at the file this plugin wrote, and `wp_calculate_image_srcset` is
filtered to nothing for it — `srcset` is a set of *equally acceptable*
alternatives, and offering static ones would make the gallery animate or not
depending on the width of the window, which gets reported as "sometimes it
works".

The pleasant part is that this costs nothing elsewhere. Those flattened
sub-sizes are exactly the poster frames the rest of the site wants, and
WordPress generates them without being asked:

| | frames | size, on the 14-frame test |
| --- | --- | --- |
| `thumbnail` / `medium` / `large` | 1 | 748 B / 4 KB / 34 KB |
| `full` — the gallery slide and the lightbox | 14 | 154 KB |

So the carousel on `/media/` moves, and the front page's thumbnail row and the
demo world's Gallery — which ask for named sizes — get a cheap still. The review
screen animates too, because it streams the stored file rather than a sub-size,
which is the only honest way to review one.

The floor is as deliberate as the ceiling. A 320px-wide picture is not a
screenshot of Mudlet, and refusing it at the form with a sentence saying why is
kinder than a reviewer rejecting it silently a week later. The pixel cap is not
about disk at all — GD allocates four bytes a pixel while it decodes, so a
20,000 × 20,000 PNG that compresses to 400KB asks for 1.6GB of memory. That one
is checked before an image library is handed the file, which is the only place
it *can* be checked.

## What the visitor is asked for

A file — PNG, JPEG, GIF or WebP, still or animated — an optional name to be
credited as, and an optional line about what it shows. The omissions are the
interesting part:

- **No email address.** There is nothing to send anybody, and "we will let you
  know" is a promise a queue cannot keep.
- **No link with the credit.** A credit line carrying a URL is a do-follow link
  on mudlet.org that anybody can have for the price of uploading a picture, and
  no amount of review makes that not worth somebody's while. A name is a name.
- **No IP address is stored.** The rate limit counts against one, and the record
  keeps eight characters of a salted hash of it — enough to answer "are these
  six the same person", which is the only question a reviewer actually has, and
  nothing else. It cannot be turned back into an address by anybody who walks
  off with the database.

## Abuse

Shaped after the theme's `inc/download-email.php`, which is the other endpoint
on this site that answers to nobody in particular:

- **no nonce** — a nonce is printed into the page, so it is only as fresh as the
  cache in front of it, and the first symptom is a form that works for a
  logged-in editor and fails for everyone else;
- **a honeypot** named for what a bot expects to find, answering a filled-in
  trap with the same cheerful message a real submission gets;
- **caps per origin**, counted on attempts rather than successes, so a refusal
  is not a free knock;
- **a cap on the queue itself**, which the download form has no equivalent of:
  an upload costs disk, and a hundred people sending one picture each is
  indistinguishable from one person sending a hundred until somebody looks. The
  intake closes and says so, rather than filling a disk;
- **one filter before anything is written**, `mudlet_shots_verify`, which is
  where a captcha goes on a site that wants one.

An email goes to the site admin when something arrives, throttled to one an hour
however many come. It carries a count and a link and nothing else: what is in
the queue is unreviewed, and mailing it out is the thing this plugin exists to
avoid.

## Approving one

Three things happen in this order, and the order is the point: the file becomes
an attachment, the attachment gets its caption and alt text, and only then is a
`core/image` block appended to the first `core/gallery` on `/media/` — exactly
what an editor would do by hand.

The reward is that everything downstream keeps working without knowing this
plugin exists. The front page's thumbnail row and the demo world's Gallery both
read that same gallery through `mudlet_front_thumbs()`, so **one approval puts
the picture in three places** and nothing holds a second copy of the list.

Where there is no `/media/` page, or no gallery on it, the attachment is still
made and the screen says where it did not go. That is something an editor can
act on, unlike a refusal that throws the picture away because a page was not the
shape this code expected.

Turning one down deletes the file immediately and trashes the record, which sits
there for the usual thirty days in case the wrong button was pressed.

## Housekeeping

One cron job, daily, listed on **Mudlet → Sync** with the three real syncs. It
deletes the files behind submissions that have been decided about, and any
directory with no record behind it.

It never touches a pending one. A queue that quietly deletes what nobody got
round to reviewing is a queue that loses the picture somebody sent while the
person who reviews them was on holiday.

## The screen

**Mudlet → Screenshots**, with the count on the menu in the bubble comments use
— the same thing comments are: a queue whose whole failure mode is nobody
noticing it has anything in it.

A wall of pictures with two buttons under each, rather than a list table. The
other three plugins hang their records off the default list table and are right
to; a game is a name and a host, and a name in a column is what you identify it
by. A screenshot is not, and a table reading "Screenshot sent 2026-03-04 14:22"
thirty times over is a screen that makes somebody click into every row to do the
job.

## Filters

| Filter | What it decides |
| --- | --- |
| `mudlet_shots_enabled` | whether the form draws and the endpoint answers at all |
| `mudlet_shots_limits` | sizes, dimensions, frame counts, quality — one array, because they are not independent |
| `mudlet_shots_animation` | whether animated uploads are accepted at all; defaults to whether Imagick can write one |
| `mudlet_shots_rate_limits` | per origin per hour, per origin per day, and how long the queue may get |
| `mudlet_shots_verify` | last word before anything is written; where a captcha goes |
| `mudlet_shots_ip` | the address the cap counts against, for a site that knows its own proxy |
| `mudlet_shots_notify` | who hears about new submissions; empty turns the mail off |
| `mudlet_shots_gallery_page` | which page holds the gallery, if not `/media/` |

Actions: `mudlet_shots_received`, `mudlet_shots_approved`, `mudlet_shots_rejected`.

## Files

| Path | What it is |
| --- | --- |
| `mudlet-shots.php` | header, the argument for all of the above, wiring |
| `includes/class-store.php` | the post type, the queue on disk, the sweeper |
| `includes/class-image.php` | **read this first** — what is accepted, the re-encode, and the animated path |
| `includes/class-intake.php` | the one route a stranger can reach |
| `includes/class-publish.php` | approve, reject, and the gallery surgery |
| `includes/class-admin.php` | the review screen and the preview stream |
| `includes/api.php` | the surface a theme may touch, and the shortcode |
| `assets/shots-form.js` | the form in the browser, and its contract with the markup |
