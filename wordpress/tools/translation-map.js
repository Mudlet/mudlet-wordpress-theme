// Every translated URL on mudlet.org, and the English one it should 301 to.
//
//   node wordpress/tools/translation-map.js [seed/export/*.xml]
//   -> wordpress/seed/out/redirects.csv            (the table, with reasons)
//   -> wordpress/seed/out/redirection-import.csv  (Tools -> Redirection -> Import)
//
// MIGRATION.md decision 4 calls the translation map "the one step that cannot
// be done afterwards", because Polylang answers `pll_get_post()` only while it
// is active and its translation groups are meaningless without it.
//
// That is true of the *live database* and not of an export. A WXR carries the
// `post_translations` and `term_translations` terms whole - each one a
// serialised lang => id map in its description - so `wp export` taken before
// the plugin is deactivated preserves the answer permanently, and this rebuilds
// it from there. Two insurance policies rather than one, and this is the one
// that can be re-run in a year by somebody looking at a 404 report.
//
// seed/php/migrate-polylang.php remains the production path: it runs on the
// live site with Polylang still up and writes the same shape. Where both exist
// they should agree, and disagreeing means the export is older than the site.
//
// Terms as well as posts, which the Polylang script does not do: /de/category/
// and /de/tag/ archives are in the sitemap and 404 with everything else.
const fs = require('fs');
const path = require('path');

const CD_OPEN = '<![' + 'CDATA[';
const CD_CLOSE = ']]' + '>';
const LANGS = ['de', 'it', 'ru', 'zh'];

function clean(v) {
  v = v.trim();
  if (v.startsWith(CD_OPEN) && v.endsWith(CD_CLOSE)) v = v.slice(CD_OPEN.length, -CD_CLOSE.length);
  return v.trim();
}

function field(block, tag) {
  const open = block.indexOf('<' + tag + '>');
  if (open < 0) return '';
  const close = block.indexOf('</' + tag + '>', open);
  if (close < 0) return '';
  return clean(block.slice(open + tag.length + 2, close));
}

// The description of a translation term is PHP's serialize() of lang => id.
// Pulling the pairs out with one pass is enough - there is no nesting, and a
// serialiser in here would be a dependency for four lines of format.
function group(description) {
  const out = {};
  const re = /s:\d+:"([a-z_-]+)";i:(\d+);/g;
  let m;
  while ((m = re.exec(description)) !== null) out[m[1]] = Number(m[2]);
  return out;
}

function blocks(xml, tag) {
  return xml.split('<' + tag + '>').slice(1).map((s) => s.split('</' + tag + '>')[0]);
}

const file = process.argv[2] || defaultExport();

function defaultExport() {
  const dir = path.join(__dirname, '..', 'seed', 'export');
  const found = fs.existsSync(dir) ? fs.readdirSync(dir).filter((n) => n.endsWith('.xml')).sort() : [];
  if (!found.length) {
    console.error('No export found in wordpress/seed/export/. Pass one as an argument.');
    process.exit(1);
  }
  return path.join(dir, found[found.length - 1]);
}

const xml = fs.readFileSync(file, 'utf8');

// ── posts and pages ───────────────────────────────────────────────────

const byId = new Map();
for (const block of blocks(xml, 'item')) {
  const type = field(block, 'wp:post_type');
  if (type !== 'post' && type !== 'page') continue;
  const link = field(block, 'link');
  let lang = '';
  const at = block.indexOf('domain="language"');
  if (at >= 0) {
    const m = block.slice(at, at + 120).match(/nicename="([a-z_-]+)"/);
    if (m) lang = m[1];
  }
  byId.set(Number(field(block, 'wp:post_id')), {
    id: Number(field(block, 'wp:post_id')),
    type,
    lang,
    status: field(block, 'wp:status'),
    title: field(block, 'title'),
    path: link.replace(/^https?:\/\/[^/]+/, ''),
  });
}

const rows = [];
const seen = new Set();

for (const block of blocks(xml, 'wp:term')) {
  if (field(block, 'wp:term_taxonomy') !== 'post_translations') continue;
  const members = group(field(block, 'wp:term_description'));
  // A trashed English post is no destination - its link is a bare ?p= that
  // resolves to nothing. That is how the 5.0 copies came out: the webhook's
  // English stub went to the trash when the announcement was written by hand,
  // and its translations stayed live pointing at it. Left empty here, so a
  // case like it surfaces as needing a pick rather than a redirect to a 404.
  const found = members.en ? byId.get(members.en) : null;
  const english = found && found.status !== 'trash' ? found : null;

  for (const lang of LANGS) {
    if (!members[lang]) continue;
    const post = byId.get(members[lang]);
    // A trashed translation has no public URL to redirect from.
    if (!post || !post.path || post.status === 'trash') continue;
    seen.add(post.id);
    rows.push({
      kind: post.type,
      lang,
      from: post.path,
      to: english && english.path ? english.path : '',
      status: post.status,
      title: post.title,
    });
  }
}

// A translated post in no group at all - Polylang knows its language and
// nothing else. It still has an indexed URL, so it still needs a destination.
for (const post of byId.values()) {
  if (seen.has(post.id) || !LANGS.includes(post.lang) || !post.path || post.status === 'trash') continue;
  rows.push({ kind: post.type, lang: post.lang, from: post.path, to: '', status: post.status, title: post.title });
}

// ── the ones Polylang never grouped ───────────────────────────────────
//
// Seventeen of these are release announcements whose English post is plainly
// still there - "4.19" in German, with /2024/12/4-19/ sitting in English a
// directory away - and Polylang simply has no group joining them. A URL with an
// obvious destination should not be filed under "somebody has to decide".
//
// So a second pass, by slug: strip the language suffix Polylang appends and the
// -2 WordPress adds on a collision, and look for an English post of the same
// name. Marked `slug` in the output rather than `group`, because it is an
// inference about names and the first pass is a fact about the database.
const english = new Map();
for (const post of byId.values()) {
  if (LANGS.includes(post.lang) || !post.path) continue;
  const slug = post.path.replace(/\/$/, '').split('/').pop();
  if (slug && !english.has(slug)) english.set(slug, post.path);
}

const strip = (slug) => {
  const tries = new Set();
  let s = slug.replace(/-\d+$/, '');
  for (const candidate of [slug, s]) {
    tries.add(candidate);
    for (const lang of LANGS) {
      if (candidate.endsWith('-' + lang)) tries.add(candidate.slice(0, -(lang.length + 1)));
    }
  }
  return [...tries];
};

for (const row of rows) {
  if (row.to) continue;
  const slug = row.from.replace(/\/$/, '').split('/').pop();
  for (const candidate of strip(slug)) {
    if (english.has(candidate)) {
      row.to = english.get(candidate);
      row.status = 'slug';
      break;
    }
  }
}

// ── picked by hand ────────────────────────────────────────────────────
//
// What neither pass can find, decided by a person looking at the posts
// (2026-09-18) and written here rather than into the CSV, so the answer
// survives the next run against a fresher export. Marked `picked`.
const PICKED = [
  // All four "translations" are the English text, posted minutes after it.
  // Not the PR-payout post three weeks later, which is a different post.
  {
    from: /^\/(de|it|ru|zh)\/2022\/02\/4-15-gifs-music-and-editable-shortcuts-\1\/$/,
    to: '/2022/02/4-15-gifs-music-shortcuts-n-more/',
  },
  // Webhook stubs of release 4.19 ([MudletRelease]192356251). The hand-written
  // announcement, not /4-19-mudlet-is-now-portable-2/, which is another stub.
  {
    from: /^\/(de|it|ru|zh)\/2024\/12\/4-19-\d+\/$/,
    to: '/2024/12/4-19-mudlet-is-now-portable/',
  },
  // Translations of the webhook's 5.0 stub, which is in the trash; the
  // announcement is the hand-written post that replaced it.
  {
    from: /^\/(de|it|ru|zh)\/2026\/08\/mudlet-5-0-\d+\/$/,
    to: '/2026/08/5-0/',
  },
];

for (const row of rows) {
  if (row.to) continue;
  const pick = PICKED.find((p) => p.from.test(row.from));
  if (pick) {
    row.to = pick.to;
    row.status = 'picked';
  }
}

// ── English duplicates being retired ──────────────────────────────────
//
// On 2024-12-26 the release webhook re-created two announcements that already
// existed, each a one-line [MudletRelease] body. Both are live and in the
// sitemap, so they are unpublished with a 301 to the real post (MIGRATION.md,
// Manual rewrites) - and a translation that pointed at one goes straight to
// the real post rather than through two redirects. The stubs themselves are
// written out as `en` rows, marked `retired`, so this file is the whole table.
const RETIRED = {
  '/2024/12/4-17-now-more-screenreader-friendly/': '/2023/03/mudlet-4-17-now-more-screenreader-friendly/',
  '/2024/12/4-19-mudlet-is-now-portable-2/': '/2024/12/4-19-mudlet-is-now-portable/',
};

for (const row of rows) {
  if (RETIRED[row.to]) row.to = RETIRED[row.to];
}
for (const [from, to] of Object.entries(RETIRED)) {
  const post = [...byId.values()].find((p) => p.path === from);
  rows.push({ kind: 'post', lang: 'en', from, to, status: 'retired', title: post ? post.title : '' });
}

// ── categories and tags ───────────────────────────────────────────────

const terms = new Map();
for (const block of blocks(xml, 'wp:category')) {
  const slug = field(block, 'wp:category_nicename');
  if (slug) terms.set('category:' + slug, { taxonomy: 'category', slug, name: field(block, 'wp:cat_name') });
}
for (const block of blocks(xml, 'wp:tag')) {
  const slug = field(block, 'wp:tag_slug');
  if (slug) terms.set('post_tag:' + slug, { taxonomy: 'post_tag', slug, name: field(block, 'wp:tag_name') });
}

// term_translations groups by term_id, and a WXR gives terms no ids in the
// <wp:category> blocks - so the ids in the group cannot be resolved to slugs
// from the export alone. What is resolvable is the shape Polylang gives a
// translated slug: the English slug with the language appended. That is a
// convention rather than a guarantee, so these are reported separately and
// marked, never silently mixed in with the post rows.
// Polylang stacks the suffix: the German "Release" is `release-de-de`, one for
// the term and one for the collision it made with itself. So strip until
// nothing comes off, rather than once.
const base = (slug) => {
  let out = slug.replace(/_$/, '');
  let stripped = false;
  for (;;) {
    const lang = LANGS.find((l) => out.endsWith('-' + l));
    if (!lang) break;
    out = out.slice(0, -(lang.length + 1));
    stripped = true;
  }
  return stripped ? out : null;
};

const termRows = [];
for (const term of terms.values()) {
  const root = base(term.slug);
  if (!root) continue;
  const lang = LANGS.find((l) => term.slug.includes('-' + l));
  const prefix = term.taxonomy === 'category' ? '/category/' : '/tag/';
  const englishExists = terms.has(term.taxonomy + ':' + root) || terms.has(term.taxonomy + ':' + root + '-en');
  termRows.push({
    kind: term.taxonomy,
    lang,
    from: '/' + lang + prefix + term.slug + '/',
    to: englishExists ? prefix + (terms.has(term.taxonomy + ':' + root) ? root : root + '-en') + '/' : '',
    status: 'derived',
    title: term.name,
  });
}

// ── out ───────────────────────────────────────────────────────────────

const all = [...rows, ...termRows].sort((a, b) => a.lang.localeCompare(b.lang) || a.from.localeCompare(b.from));

const outDir = path.join(__dirname, '..', 'seed', 'out');
fs.mkdirSync(outDir, { recursive: true });
const csv = ['from,to,lang,kind,status,title']
  .concat(all.map((r) => [r.from, r.to, r.lang, r.kind, r.status, '"' + String(r.title).replace(/"/g, '""') + '"'].join(',')))
  .join('\n');
fs.writeFileSync(path.join(outDir, 'redirects.csv'), csv + '\n');

// The same table in the shape the Redirection plugin imports (Tools ->
// Redirection -> Import/Export): source, target, regex, code - no header, since
// its importer would take one for a redirect. Only rows with a target, and
// never one pointing at itself, which Redirection would loop on.
const importable = all.filter((r) => r.to && r.to !== r.from);
fs.writeFileSync(
  path.join(outDir, 'redirection-import.csv'),
  importable.map((r) => [r.from, r.to, 0, 301].join(',')).join('\n') + '\n'
);

const count = (f) => all.filter(f).length;
console.log(path.relative(process.cwd(), file));
console.log('  ' + all.length + ' translated URLs\n');
for (const lang of LANGS) {
  const mine = all.filter((r) => r.lang === lang);
  console.log(
    '  ' + lang + '  ' + String(mine.length).padStart(3) + ' urls   ' +
    ['post', 'page', 'category', 'post_tag'].map((k) => k + ' ' + mine.filter((r) => r.kind === k).length).join('  ') +
    '   without a target: ' + mine.filter((r) => !r.to).length
  );
}
console.log('\n  ' + count((r) => !r.to) + ' of ' + all.length + ' have no English equivalent and need a decision.');
console.log('  wrote ' + path.relative(process.cwd(), path.join(outDir, 'redirects.csv')));
console.log('  wrote ' + path.relative(process.cwd(), path.join(outDir, 'redirection-import.csv')) + ' (' + importable.length + ' rows, for the Redirection plugin)');

// What is left is a URL whose English post exists under a name nothing can
// derive - "4.19" in four languages against /2024/12/4-19-mudlet-is-now-
// portable/ in English. Guessing that from the slug is not possible and
// guessing it from the title is worse, so each one is printed with the English
// posts published the same month beside it. A person picks; there are eight.
const orphans = all.filter((r) => !r.to);
if (orphans.length) {
  const month = (p) => (p.match(/^\/(\d{4})\/(\d{2})\//) || []).slice(1).join('/');
  console.log('\n  no English counterpart - pick one, or send it to /news/:');
  for (const r of orphans) {
    console.log('    ' + r.lang + '  ' + r.kind.padEnd(9) + r.from + '   ' + r.title);
    const when = month(r.from.replace(/^\/[a-z]{2}\//, '/'));
    if (!when) continue;
    for (const [, candidate] of english) {
      if (month(candidate) === when) console.log('        candidate: ' + candidate);
    }
  }
}
