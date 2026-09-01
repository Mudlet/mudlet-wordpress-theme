#!/bin/sh
# Provision the local mudlet.org.
#
# Idempotent on purpose: every step checks before it writes, so this can be
# re-run after dropping a WXR export into seed/wxr/ without duplicating pages,
# menus or categories.
#
#   docker compose up seed        # re-run against an existing database
#
# Why a script and not a database dump: a dump is an opaque binary blob nobody
# can review, and the design decisions that live in wp_options are exactly what
# went wrong with the Divi site this replaces. This is diffable.

set -eu

WP="wp --path=/var/www/html"
SEED=/seed

log() { printf '\n\033[1;33m==>\033[0m %s\n' "$1"; }
note() { printf '    %s\n' "$1"; }

# ── wait for the core files ───────────────────────────────────────────
# The wordpress container unpacks WordPress into the shared volume on its first
# start. depends_on only waits for the process, not for that copy to finish.
log "Waiting for WordPress core files"
i=0
while [ ! -f /var/www/html/wp-includes/version.php ] || [ ! -f /var/www/html/wp-config.php ]; do
	i=$((i + 1))
	if [ "$i" -gt 120 ]; then
		echo "WordPress core never appeared in /var/www/html - giving up." >&2
		exit 1
	fi
	sleep 1
done
note "core is present"

# ── install ───────────────────────────────────────────────────────────
if $WP core is-installed 2>/dev/null; then
	log "WordPress is already installed - updating settings only"
else
	log "Installing WordPress"
	$WP core install \
		--url="$SITE_URL" \
		--title="$SITE_TITLE" \
		--admin_user="$ADMIN_USER" \
		--admin_password="$ADMIN_PASSWORD" \
		--admin_email="$ADMIN_EMAIL" \
		--skip-email
fi

log "Site options"
$WP option update blogname "$SITE_TITLE"
$WP option update blogdescription "$SITE_TAGLINE"
$WP option update timezone_string "UTC"
$WP option update date_format "j F Y"
$WP option update start_of_week 1
# The permalink shape the post links assume: /2026/07/slug/.
$WP rewrite structure '/%year%/%monthnum%/%postname%/'
$WP option update posts_per_page 18
# Comments are off: the forum is where discussion happens, and the theme draws
# no comment list.
$WP option update default_comment_status closed
$WP option update default_ping_status closed

log "Activating the theme"
$WP theme activate mudlet

# Is there a real export to import? Decided once, up front, because it changes
# what the rest of the script should create: a WXR from the live site brings its
# own categories and its own posts, and anything seeded here first would collide
# with them on slug - leaving "release" beside "release-en", and the real
# "Mudlet 4.22.0" renamed to "mudlet-4-22-0-2" behind a placeholder.
if ls "$SEED"/wxr/*.xml >/dev/null 2>&1; then
	HAVE_EXPORT=1
else
	HAVE_EXPORT=0
fi

# ── clear WordPress's own sample content ──────────────────────────────
# Only the untouched originals, matched by slug, so a re-run never eats
# something somebody wrote.
log "Removing sample content"
for slug in hello-world sample-page privacy-policy-2; do
	id=$($WP post list --post_type=post,page --name="$slug" --format=ids 2>/dev/null || true)
	if [ -n "$id" ]; then
		$WP post delete $id --force
		note "deleted $slug"
	fi
done

# ── categories ────────────────────────────────────────────────────────
# The ones the live site uses. The theme keys its pill colours off these slugs.
# Only seeded when there is no export: otherwise the import supplies the real
# ones, and these would sit alongside them as near-duplicates.
log "Categories"
if [ "$HAVE_EXPORT" = "1" ]; then
	note "an export is present - leaving categories to the import"
else
	create_term() {
		if ! $WP term list category --slug="$2" --format=ids | grep -q .; then
			$WP term create category "$1" --slug="$2" >/dev/null
			note "created $2"
		fi
	}
	create_term "Release" "release"
	create_term "Informational" "informational"
	create_term "Press" "press"
fi

# ── pages ─────────────────────────────────────────────────────────────
log "Pages"

# ensure_page <slug> <title> [parent-slug]
#
# The page, not its prose. Every page here is somebody's to write in wp-admin;
# what the seed owns is that it exists, because the templates, the front page,
# the posts page and both menus are all attached to these ids.
ensure_page() {
	slug=$1; title=$2; parent=${3:-}

	existing=$($WP post list --post_type=page --post_status=any --name="$slug" --format=ids 2>/dev/null || true)
	if [ -n "$existing" ]; then
		PAGE_ID=$existing
		# WordPress creates its own Privacy Policy page as a draft, with its own
		# boilerplate, and it takes the slug we want. Publishing a draft here only
		# ever touches a page nobody has published, so it cannot eat live content.
		status=$($WP post get "$existing" --field=post_status)
		if [ "$status" = "draft" ] || [ "$status" = "auto-draft" ]; then
			$WP post update "$existing" --post_status=publish >/dev/null
			note "$slug was a $status - published it (#$existing)"
		else
			note "$slug exists (#$existing)"
		fi
		return 0
	fi

	parent_id=0
	if [ -n "$parent" ]; then
		parent_id=$($WP post list --post_type=page --name="$parent" --format=ids 2>/dev/null || echo 0)
		[ -n "$parent_id" ] || parent_id=0
	fi

	PAGE_ID=$($WP post create --post_type=page --post_status=publish \
		--post_title="$title" --post_name="$slug" --post_parent="$parent_id" --porcelain)
	note "created $slug (#$PAGE_ID)"
}

ensure_page home        "Home"
HOME_ID=$PAGE_ID
ensure_page news        "News"
NEWS_ID=$PAGE_ID
ensure_page download    "Download Mudlet"
DOWNLOAD_ID=$PAGE_ID
ensure_page about       "About"
ensure_page vision      "Vision"              about
ensure_page the-makers  "The makers"
ensure_page contribute  "Contribute"
ensure_page contact     "Contact us"
CONTACT_ID=$PAGE_ID
ensure_page media       "Media"
MEDIA_ID=$PAGE_ID
ensure_page privacy-policy    "Privacy policy"
ensure_page terms-of-service  "Terms of service"

log "Front page and posts page"
$WP option update show_on_front page
$WP option update page_on_front "$HOME_ID"
$WP option update page_for_posts "$NEWS_ID"
# front-page.php takes precedence over the Home page's own content, which is
# why that page is created empty. It exists so the front page has a real ID for
# Polylang to translate and for menus to point at.

# The download page needs the template that draws the build table.
$WP post meta update "$DOWNLOAD_ID" _wp_page_template page-download.php >/dev/null
note "download page uses page-download.php"

# The contact page needs the template that draws the Discord panel and the
# form slot. The slot stays empty here: which contact form plugin this site
# ends up with is not the seed's decision, and the template shows a disabled
# placeholder and the admin address until somebody pastes a shortcode into the
# "Contact form" box on the page. See theme/mudlet/inc/contact.php.
$WP post meta update "$CONTACT_ID" _wp_page_template page-contact.php >/dev/null
note "contact page uses page-contact.php"

# The address that page publishes. The live site renders it as a PNG - the old
# trick against address harvesters - so it is retyped here once and kept as
# text; the theme runs it through antispambot() rather than through an image
# nobody can copy, click or hear read aloud. Its own option and not
# admin_email: that address resets the site, and is nobody's business.
CONTACT_EMAIL=${CONTACT_EMAIL:-vadim.peretokin@mudlet.org}
if [ -z "$($WP option get mudlet_contact_email 2>/dev/null)" ]; then
	$WP option update mudlet_contact_email "$CONTACT_EMAIL" >/dev/null
	note "contact address set to $CONTACT_EMAIL"
fi

# ── the release the download page describes ───────────────────────────
# Nothing to seed. The download table is read from the GitHub release by the
# theme (inc/github-releases.php) - version, sizes, URLs and SHA-256 hashes all
# come from there, so writing anything here would be inventing values that then
# override the real ones. The `mudlet_release` option remains available as a
# deliberate override, but nothing writes it automatically.

# ── menus ─────────────────────────────────────────────────────────────
log "Menus"
if ! $WP menu list --fields=slug --format=csv | grep -qx "header"; then
	$WP menu create "Header" >/dev/null
	$WP menu item add-post header "$NEWS_ID" --title="News" >/dev/null
	$WP menu item add-post header "$MEDIA_ID" --title="Gallery" --classes="lo" >/dev/null
	$WP menu item add-custom header "Docs" "https://wiki.mudlet.org" --classes="lo" >/dev/null
	$WP menu item add-custom header "Forum" "https://forums.mudlet.org" --classes="lo" >/dev/null
	$WP menu location assign header primary >/dev/null
	note "header menu assigned"
fi

if ! $WP menu list --fields=slug --format=csv | grep -qx "footer-project"; then
	$WP menu create "Footer Project" >/dev/null
	for slug in about vision the-makers contribute contact; do
		id=$($WP post list --post_type=page --name="$slug" --format=ids)
		[ -n "$id" ] && $WP menu item add-post footer-project "$id" >/dev/null
	done
	$WP menu location assign footer-project footer-project >/dev/null
	note "footer menu assigned"
fi

# ── languages ─────────────────────────────────────────────────────────
if [ "${SEED_LANGUAGES:-1}" = "1" ]; then
	log "Polylang"
	if ! $WP plugin is-installed polylang 2>/dev/null; then
		$WP plugin install polylang
	fi
	$WP plugin activate polylang
	# Polylang ships no WP-CLI commands, so the languages are created through
	# its own admin model. See the file for why that is done the long way.
	$WP eval-file "$SEED/php/languages.php"
fi

# ── the releases plugin (this repo) ───────────────────────────────────
# Bind-mounted from wordpress/plugin/mudlet-releases. It owns the release data:
# give a post a tag and it supplies the changelog, the counts and the download
# table's sizes, URLs and checksums. The theme reads it through function_exists,
# so deactivating this degrades the site rather than breaking it.
log "Mudlet Releases plugin"
$WP plugin activate mudlet-releases

# Backfill the release store from a dump, if one has been generated. Without it
# the store fills itself from the API over the following day - correct, but slow,
# because anonymous GitHub allows 60 requests an hour and a large release costs
# six. Generate the dump with the authenticated gh CLI:
#
#   node wordpress/tools/fetch-releases.mjs
#
if [ -f "$SEED/releases.json" ]; then
	$WP mudlet-releases import "$SEED/releases.json"
else
	note "no seed/releases.json - the store will fill itself from the API"
	note "generate one with: node wordpress/tools/fetch-releases.mjs"
fi

# ── the games plugin (this repo) ──────────────────────────────────────
# Bind-mounted from wordpress/plugin/mudlet-games. It owns the games data:
# which MUDs Mudlet bundles, read from the client's own src/TGameDetails.h,
# one post per game. The theme reads it through function_exists, so
# deactivating this leaves the front page on its built-in fifteen.
if [ "${SEED_GAMES:-1}" = "1" ]; then
	log "Mudlet Games plugin"
	$WP plugin activate mudlet-games

	# One header and forty-odd logos, read from Mudlet's own source. Cron
	# re-syncs daily from here.
	$WP mudlet-games sync
fi

# ── the makers plugin (this repo) ─────────────────────────────────────
# Bind-mounted from wordpress/plugin/mudlet-makers. It owns the credits: the
# people Mudlet names in Help -> About, read from the client's own
# src/dlgAboutDialog.cpp, one post per person. /the-makers/ keeps its editable
# prose and the theme draws the roster underneath it - so deactivating this
# leaves the page reading fine, just with no names on it.
if [ "${SEED_MAKERS:-1}" = "1" ]; then
	log "Mudlet Makers plugin"
	$WP plugin activate mudlet-makers

	# One file and eighteen small avatars, read from Mudlet's own credits. Two
	# of the eighteen GitHub handles 404 - accounts long since renamed or
	# deleted - and those people are drawn as initials, which is also what the
	# twelve who publish no handle at all get.
	$WP mudlet-makers sync
fi

# ── the release automation plugin (upstream) ──────────────────────────
# https://github.com/Mudlet/mudlet-release-plugin
#
# Not optional if you import real news. Twenty-one of the release posts have
# nothing in post_content but [MudletRelease]<github-release-id>[/MudletRelease];
# the plugin is what turns that into the changelog. Without it those posts
# render as a bare number.
#
# It also carries the other half of the workflow: a GitHub release webhook that
# creates the announcement post in every language and stamps it with the
# release-post meta the theme reads.
if [ "${SEED_RELEASE_PLUGIN:-1}" = "1" ]; then
	log "Mudlet release plugin"
	if $WP plugin is-installed mudlet-release 2>/dev/null; then
		note "already installed"
	else
		$WP plugin install \
			https://github.com/Mudlet/mudlet-release-plugin/releases/latest/download/mudlet-release.zip
	fi
	$WP plugin activate mudlet-release

	# --- local patch, pending an upstream fix -------------------------------
	# The plugin writes the GitHub release *id* into the post body:
	#
	#     'post_content' => '[MudletRelease]' . $result->id . '[/MudletRelease]'
	#
	# but the shortcode reads it back as a *tag name*:
	#
	#     GetHttpWrapper::get(GITHUB_API_URL . "releases/tags/$content")
	#
	# api.github.com/repos/Mudlet/Mudlet/releases/tags/378895178 is a 404;
	# releases/378895178 is the release. On mudlet.org this never shows, because
	# the same webhook that creates the post also does set_transient() with no
	# expiry - so the body is cached forever and the broken fallback is never
	# reached. A fresh install has no transients, so every imported release post
	# falls straight through it and renders "Can't get releases post for <id>".
	#
	# One character short of a one-line fix upstream: drop "tags/".
	# Set SEED_PATCH_RELEASE_PLUGIN=0 to see the unpatched behaviour.
	if [ "${SEED_PATCH_RELEASE_PLUGIN:-1}" = "1" ]; then
		plugin=/var/www/html/wp-content/plugins/mudlet-release/mudlet-release.php
		if [ -f "$plugin" ] && grep -q 'releases/tags/\$content' "$plugin"; then
			sed -i 's|releases/tags/\$content|releases/\$content|' "$plugin"
			note "patched mudlet-release: releases/tags/\$content -> releases/\$content"
		fi
	fi
fi

# ── news ──────────────────────────────────────────────────────────────
log "News posts"
if [ "$HAVE_EXPORT" = "1" ]; then
	# Clear any placeholders left by an earlier run before importing. They are
	# tagged with _mudlet_placeholder precisely so this can find them, and they
	# have to go first: they hold the slugs the real posts want, and WordPress
	# resolves that collision by renaming the incoming post, not the squatter.
	stale=$($WP post list --post_type=post --post_status=any \
		--meta_key=_mudlet_placeholder --format=ids 2>/dev/null || true)
	if [ -n "$stale" ]; then
		# shellcheck disable=SC2086
		$WP post delete $stale --force >/dev/null
		note "removed $(echo "$stale" | wc -w | tr -d ' ') placeholder posts"
		# and the categories that were seeded for them, if nothing else uses them
		for slug in release informational press; do
			id=$($WP term list category --slug="$slug" --field=term_id 2>/dev/null || true)
			count=$($WP term list category --slug="$slug" --field=count 2>/dev/null || echo 1)
			if [ -n "$id" ] && [ "$count" = "0" ]; then
				$WP term delete category "$id" >/dev/null
				note "removed empty seeded category $slug"
			fi
		done
	fi

	$WP plugin is-installed wordpress-importer 2>/dev/null || $WP plugin install wordpress-importer
	$WP plugin activate wordpress-importer

	# Attachments are skipped by default: the export carries URLs, not files, so
	# fetching them means 178 posts' worth of requests against the live site.
	# Set IMPORT_MEDIA=1 when you actually want the images.
	if [ "${IMPORT_MEDIA:-0}" = "1" ]; then
		skip=""
	else
		skip="--skip=attachment"
	fi

	for f in "$SEED"/wxr/*.xml; do
		note "importing $(basename "$f")"
		# shellcheck disable=SC2086
		$WP import "$f" --authors=create $skip
	done
elif [ "${SEED_DEMO_POSTS:-1}" = "1" ]; then
	if [ "$($WP post list --post_type=post --format=count)" = "0" ]; then
		note "no export in seed/wxr/ - writing placeholder posts instead"
		$WP eval-file "$SEED/php/demo-posts.php"
	else
		note "posts already present - leaving them alone"
	fi
else
	note "no export in seed/wxr/ and demo posts disabled"
fi

$WP rewrite flush
$WP cache flush 2>/dev/null || true

log "Done"
note "site:  $SITE_URL"
note "admin: $SITE_URL/wp-admin  ($ADMIN_USER / $ADMIN_PASSWORD)"
if ! ls "$SEED"/wxr/*.xml >/dev/null 2>&1; then
	note ""
	note "To load the real news: export from the live site"
	note "  wp-admin -> Tools -> Export -> All content"
	note "then drop the .xml into wordpress/seed/wxr/ and run:"
	note "  docker compose up seed"
fi
