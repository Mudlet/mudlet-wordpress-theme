// Scrub a wp-admin export of what must never leave the production server.
//
//   node wordpress/tools/filter-wxr.js seed/export/whatever.xml
//   -> seed/wxr/whatever.filtered.xml
//
// This used to cut the export down to "the parts worth importing" - posts and
// attachments, nothing else. It does the opposite now, and the reason is the
// seed's shape: seed/setup.sh runs a BASELINE phase that reproduces mudlet.org
// as it stands and then a MIGRATION phase that applies this project's changes
// on top, so that wordpress/MIGRATION.md can be rehearsed locally instead of
// being performed once, on production, from a document. A baseline that has
// already dropped the pages and the menus is not a baseline.
//
// So everything in the export is kept, with one exception, and it is not a
// matter of taste: Flamingo's archive of Contact Form 7 submissions is real
// names and real email addresses of people who wrote to the project. That does
// not belong outside the production server at all. The raw export stays in
// seed/export/ (gitignored, never imported) and only the scrubbed file reaches
// seed/wxr/, which is what the seed imports.
//
// What this means for the things that used to be dropped:
//
//   page              28 of them, bodies untouched. Nine are prose; two are
//                     Divi soup whose templates never call the_content()
//                     anyway. inc/divi-cleanup.php strips orphaned et_pb_*
//                     tags at display time, so nothing here rewrites a body.
//   nav_menu_item     49, in four menus. The live "Main" already carries the
//                     About and Help dropdowns, so the seed no longer builds
//                     a header menu by hand - it assigns this one.
//   et_pb_layout      Divi's own library. Inert without Divi, and part of the
//   custom_css        starting state. Pretending they are not there is the
//   shortcoder        instruction: nothing here alters a body to suit us.
//   wpcf7_contact_form
//
// Why a hand-written scanner rather than a regex or an XML library: WXR wraps
// every value in CDATA, and post bodies legitimately contain things like
// "</item>". A line-anchored regex would cut the file in the wrong place. This
// walks the document once, skipping the interior of every CDATA section, so a
// tag is only ever recognised where it is really a tag. That is the whole
// trick; everything else here is bookkeeping.

const fs = require('fs');
const path = require('path');

// The only post types dropped, and the only reason to drop one: these two hold
// personal data. flamingo_contact is one record per person who ever used the
// contact form; flamingo_inbound is the messages themselves.
const DROP_TYPES = new Set(['flamingo_contact', 'flamingo_inbound']);

// Every taxonomy is kept. Polylang's four - language/post_translations for
// posts, term_language/term_translations for the categories - are what make the
// migration's "deactivate Polylang" step something that can be rehearsed:
// without them there are no translations to unpublish and no map to build 301s
// from. nav_menu is kept for the menus above.


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

for (const [start, end] of findBlocks(xml, 'item')) {
  const chunk = xml.slice(start, end);
  const type = tagValue(chunk, 'wp:post_type') || '(none)';

  if (DROP_TYPES.has(type)) {
    tally(dropped, type);
    drop(start, end);
    continue;
  }
  tally(kept, type);
}

for (const [start, end] of findBlocks(xml, 'wp:term')) {
  const tax = tagValue(xml.slice(start, end), 'wp:term_taxonomy') || '(none)';
  tally(kept, `term:${tax}`);
}

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

console.log(`\n  -> ${path.relative(process.cwd(), outPath)}  ${(out.length / 1048576).toFixed(1)} MB\n`);

if (dropped.flamingo_contact || dropped.flamingo_inbound) {
  console.log('  NOTE: this export contained Contact Form 7 submissions (names and');
  console.log('        email addresses of people who wrote to the project). They have');
  console.log('        been dropped. Keep the raw export out of git and off shared');
  console.log('        machines.\n');
}
