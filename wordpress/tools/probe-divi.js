// Which posts and pages are built with Divi, and what the new theme does with
// each one.
//
//   node wordpress/tools/probe-divi.js [seed/export/*.xml]
//
// The question this answers is not "what is Divi-built" - that is one meta key
// - but the one after it: **whose body still reaches the page once the theme
// changes**. Those are two different lists, and only the second is work.
//
// front-page.php never calls the_content(), so the front page's 20KB of
// et_pb_section is dead the moment the theme is active and can be left alone
// forever. page-download.php and page-contact.php *do* render the body, under
// and over their own furniture respectively - so the old Divi page arrives
// underneath the new one unless the body is emptied. Everything else goes
// through page.php or single.php, where the body is the whole page.
//
// inc/divi-cleanup.php strips the tags at display time and keeps what they
// wrapped, so nothing here is broken-looking. It is a repair, not a migration:
// the markup is still in the database and the layout it described is gone.
//
// Reads the *unfiltered* export, because seed/wxr/*.filtered.xml has had
// content removed. Nothing is written; this only reports.
const fs = require('fs');
const path = require('path');

const CD_OPEN = '<![' + 'CDATA[';
const CD_CLOSE = ']]' + '>';

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

function metaValue(block, key) {
  for (const chunk of block.split('<wp:postmeta>').slice(1)) {
    if (field(chunk, 'wp:meta_key') === key) return field(chunk, 'wp:meta_value');
  }
  return '';
}

// What the theme does with a body, by the path it answers on. The templates
// are slug-keyed, so this is the same lookup WordPress does.
function fate(row) {
  if (row.type !== 'page') {
    return ['single.php', 'REWRITE', 'the body is the post'];
  }
  const slug = row.path.replace(/^\/|\/$/g, '');
  if (slug === '') return ['front-page.php', 'drop', 'the_content() is never called'];
  if (slug === 'download') return ['page-download.php', 'EMPTY', 'renders below the build table'];
  if (slug === 'contact') return ['page-contact.php', 'EMPTY', 'renders above the Discord panel'];
  if (slug === 'the-makers') return ['page-the-makers.php', 'REWRITE', 'renders above the roster'];
  return ['page.php', 'REWRITE', 'the body is the page'];
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
const items = xml.split('<item>').slice(1).map((s) => s.split('</item>')[0]);

const rows = items.map((block) => {
  const body = (block.split('<content:encoded>')[1] || '').split('</content:encoded>')[0];
  const link = field(block, 'link');
  let lang = '';
  const langAt = block.indexOf('domain="language"');
  if (langAt >= 0) {
    const m = block.slice(langAt, langAt + 120).match(/nicename="([a-z_-]+)"/);
    if (m) lang = m[1];
  }
  return {
    id: Number(field(block, 'wp:post_id')),
    type: field(block, 'wp:post_type'),
    status: field(block, 'wp:status'),
    title: field(block, 'title'),
    name: field(block, 'wp:post_name'),
    path: link.replace(/^https?:\/\/[^/]+/, '') || '?p=' + field(block, 'wp:post_id'),
    lang,
    builder: metaValue(block, '_et_pb_use_builder'),
    shortcode: body.includes('[et_pb_'),
    bytes: body.length,
  };
});

// A Divi page is one the builder owns (the meta) or one whose body still
// carries its shortcodes (the leftovers). The union, because the second set is
// what divi-cleanup.php exists for and the first is what the layout was.
const divi = rows.filter((r) => r.builder === 'on' || r.shortcode);

console.log(path.relative(process.cwd(), file));
console.log(
  '  ' + rows.length + ' items; ' +
  rows.filter((r) => r.builder === 'on').length + ' with _et_pb_use_builder=on, ' +
  rows.filter((r) => r.shortcode).length + ' with [et_pb_ in the body\n'
);

const groups = [
  ['English pages', (r) => r.type === 'page' && r.lang !== 'de' && r.lang !== 'zh' && r.lang !== 'it' && r.lang !== 'ru'],
  ['Translated pages (going with Polylang)', (r) => r.type === 'page' && ['de', 'zh', 'it', 'ru'].includes(r.lang)],
  ['English posts', (r) => r.type === 'post' && r.lang !== 'de' && r.lang !== 'zh' && r.lang !== 'it' && r.lang !== 'ru'],
  ['Translated posts (going with Polylang)', (r) => r.type === 'post' && ['de', 'zh', 'it', 'ru'].includes(r.lang)],
  ['Divi library layouts (not public URLs)', (r) => r.type === 'et_pb_layout'],
];

for (const [label, match] of groups) {
  const found = divi.filter(match).sort((a, b) => a.id - b.id);
  if (!found.length) continue;
  console.log('== ' + label + ': ' + found.length);
  for (const r of found) {
    const [template, verdict, why] = r.type === 'et_pb_layout'
      ? ['-', 'drop', 'a Divi library entry']
      : fate(r);
    console.log(
      '  ' + String(r.id).padStart(5) +
      '  ' + r.status.padEnd(7) +
      '  ' + String(r.bytes).padStart(6) + 'b' +
      '  ' + verdict.padEnd(7) +
      '  ' + r.path.padEnd(46) +
      '  ' + template.padEnd(20) +
      '  ' + why
    );
  }
  console.log('');
}

// The actual work: published, English, and rendered by whatever template picks
// it up. Drafts and private pages are Divi too and nobody is reading them.
const work = divi.filter((r) =>
  ['page', 'post'].includes(r.type) &&
  r.status === 'publish' &&
  !['de', 'zh', 'it', 'ru'].includes(r.lang) &&
  fate(r)[1] !== 'drop'
);
console.log('Published English bodies that reach a reader after the switch: ' + work.length);
for (const r of work) console.log('  ' + fate(r)[1].padEnd(8) + r.path);
