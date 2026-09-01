// Cut a wp-admin export down to the parts worth importing.
//
//   node wordpress/tools/filter-wxr.js seed/export/whatever.xml
//   -> seed/wxr/whatever.filtered.xml
//
// A "Tools -> Export -> All content" export is not just posts. mudlet.org's
// carries Divi layout records, Shortcoder entries, theme CSS, the nav menus
// that point at Divi pages, the pages themselves (whose bodies are et_pb_*
// shortcodes that render as nothing without Divi) - and, most importantly,
// Flamingo's archive of Contact Form 7 submissions: real names and real email
// addresses from people who wrote to the project.
//
// None of that belongs in a development database, and the last item does not
// belong outside the production server at all. So the raw export stays in
// seed/export/ (gitignored, never imported) and only the filtered file reaches
// seed/wxr/, which is what the seed script imports.
//
// Why a hand-written scanner rather than a regex or an XML library: WXR wraps
// every value in CDATA, and post bodies legitimately contain things like
// "</item>". A line-anchored regex would cut the file in the wrong place. This
// walks the document once, skipping the interior of every CDATA section, so a
// tag is only ever recognised where it is really a tag. That is the whole
// trick; everything else here is bookkeeping.
//
// Bodies are passed through as they are. Eight of the posts still carry Divi
// shortcodes, and inc/divi-cleanup.php papers over them at display time until
// somebody deals with them properly.

const fs = require('fs');
const path = require('path');

// Post types worth having. Everything else is dropped.
//
//   post              the news, in every language - this is the point
//   attachment        kept in the file, but `wp import --skip=attachment`
//                     ignores them unless IMPORT_MEDIA=1
const KEEP_TYPES = new Set(['post', 'attachment']);

// Taxonomies worth having. Four of these are Polylang's own bookkeeping -
// language/post_translations for posts, term_language/term_translations for the
// categories, which are translated too. Dropping any of them flattens five
// languages into one.
const KEEP_TAXONOMIES = new Set([
  'category',
  'post_tag',
  'language',
  'post_translations',
  'term_language',
  'term_translations',
]);

const argv = process.argv.slice(2);
if (!argv.length) {
  console.error('usage: node wordpress/tools/filter-wxr.js <export.xml> [out.xml]');
  process.exit(2);
}

const inPath = path.resolve(argv[0]);
const outPath = path.resolve(
  argv[1] || path.join(__dirname, '..', 'seed', 'wxr',
    path.basename(inPath).replace(/\.xml$/i, '') + '.filtered.xml')
);

const xml = fs.readFileSync(inPath, 'utf8');

/**
 * Every top-level block of one kind, as [start, end) offsets.
 *
 * Scans the whole document once, skipping CDATA interiors, and tracks nesting
 * so a block containing another of the same name is still closed correctly.
 */
function findBlocks(src, name) {
  const open = `<${name}>`;
  const close = `</${name}>`;
  const blocks = [];
  let i = 0;
  let depth = 0;
  let start = -1;

  while (i < src.length) {
    if (src.startsWith('<![CDATA[', i)) {
      const end = src.indexOf(']]>', i);
      i = end === -1 ? src.length : end + 3;
      continue;
    }
    if (src.startsWith('<!--', i)) {
      const end = src.indexOf('-->', i);
      i = end === -1 ? src.length : end + 3;
      continue;
    }
    if (src.startsWith(open, i)) {
      if (depth === 0) start = i;
      depth++;
      i += open.length;
      continue;
    }
    if (src.startsWith(close, i)) {
      depth--;
      i += close.length;
      if (depth === 0 && start !== -1) {
        blocks.push([start, i]);
        start = -1;
      }
      continue;
    }
    i++;
  }
  return blocks;
}

/** First value of <tag>…</tag> inside a chunk, CDATA unwrapped. */
function tagValue(chunk, tag) {
  const m = chunk.match(new RegExp(`<${tag}>([\\s\\S]*?)</${tag}>`));
  if (!m) return '';
  return m[1].replace(/^\s*<!\[CDATA\[/, '').replace(/\]\]>\s*$/, '').trim();
}

// Every change to the document, as [start, end) plus what goes in its place.
// A drop is a replacement with nothing; keeping both in one list means the
// removals and the rewritten bodies cannot get out of order with each other.
const edits = [];
const drop = (start, end) => edits.push({ start, end, text: '', swallow: true });
const replace = (start, end, text) => edits.push({ start, end, text, swallow: false });

const kept = {};
const dropped = {};
const tally = (bag, key) => { bag[key] = (bag[key] || 0) + 1; };

/** A value back inside CDATA, with any `]]>` in it split so it cannot end early. */
const cdata = (tag, value) =>
  `<${tag}><![CDATA[${String(value).split(']]>').join(']]]]><![CDATA[>')}]]></${tag}>`;

/**
 * The prose of a body, as characters, for checking that a conversion did not
 * quietly lose any.
 *
 * Characters rather than words because unwrapping the Etherpad spans in the
 * 3.11 announcement rejoins words they had split down the middle - "Y" and "ou"
 * become "You" - and a word count reads that repair as two words lost.
 */
let metaDropped = 0;

for (const [start, end] of findBlocks(xml, 'item')) {
  const chunk = xml.slice(start, end);
  const type = tagValue(chunk, 'wp:post_type') || '(none)';

  if (!KEEP_TYPES.has(type)) {
    tally(dropped, type);
    drop(start, end);
    continue;
  }
  tally(kept, type);
}

for (const [start, end] of findBlocks(xml, 'wp:term')) {
  const chunk = xml.slice(start, end);
  const tax = tagValue(chunk, 'wp:term_taxonomy') || '(none)';
  if (KEEP_TAXONOMIES.has(tax)) {
    tally(kept, `term:${tax}`);
  } else {
    tally(dropped, `term:${tax}`);
    drop(start, end);
  }
}

// Divi's own postmeta. A few hundred rows saying which builder version last
// touched each body: bookkeeping for a plugin this site does not have, and no
// use to a fresh database. The bodies keep their shortcodes; this is only the
// builder's own state.
for (const [start, end] of findBlocks(xml, 'wp:postmeta')) {
  if (/^_et_/.test(tagValue(xml.slice(start, end), 'wp:meta_key'))) {
    metaDropped++;
    drop(start, end);
  }
}

// Also drop the <wp:tag> and <wp:category> header blocks for nothing - they are
// fine - but nav menus come through as their own term type handled above.

edits.sort((a, b) => a.start - b.start);

let out = '';
let cursor = 0;
for (const edit of edits) {
  if (edit.start < cursor) continue; // nested inside an already-dropped block
  out += xml.slice(cursor, edit.start) + edit.text;
  cursor = edit.end;
  // swallow the newline a removed block leaves behind
  if (edit.swallow) {
    while (cursor < xml.length && (xml[cursor] === '\n' || xml[cursor] === '\r' || xml[cursor] === '\t')) cursor++;
  }
}
out += xml.slice(cursor);

fs.mkdirSync(path.dirname(outPath), { recursive: true });
fs.writeFileSync(outPath, out);

const pad = (s) => String(s).padStart(5);
console.log(`\n  ${path.basename(inPath)}  ${(xml.length / 1048576).toFixed(1)} MB\n`);
console.log('  kept');
for (const [k, v] of Object.entries(kept).sort((a, b) => b[1] - a[1])) console.log(`    ${pad(v)}  ${k}`);
console.log('\n  dropped');
for (const [k, v] of Object.entries(dropped).sort((a, b) => b[1] - a[1])) console.log(`    ${pad(v)}  ${k}`);
if (metaDropped) console.log(`    ${pad(metaDropped)}  postmeta:_et_* (Divi's own bookkeeping)`);

console.log(`\n  -> ${path.relative(process.cwd(), outPath)}  ${(out.length / 1048576).toFixed(1)} MB\n`);

if (dropped.flamingo_contact || dropped.flamingo_inbound) {
  console.log('  NOTE: this export contained Contact Form 7 submissions (names and');
  console.log('        email addresses of people who wrote to the project). They have');
  console.log('        been dropped. Keep the raw export out of git and off shared');
  console.log('        machines.\n');
}
