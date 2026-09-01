// Check how a Mudlet GitHub release parses, without booting WordPress.
//
//   node wordpress/tools/probe-release.js 349392806
//
// Prints the version, date, and the per-section entry counts that
// inc/github-releases.php derives for the release panel. Useful when a release
// panel shows no counts and you want to know whether that is a parser problem
// or - as with 5.0 - a changelog that simply does not use Added/Improved/Fixed
// headings. Keep the regex here in step with mudlet_changelog_counts().
const https = require('https');

const id = process.argv[2] || '349392806';

https.get(
  { host: 'api.github.com', path: `/repos/Mudlet/Mudlet/releases/${id}`, headers: { 'User-Agent': 'mudlet-probe' } },
  (res) => {
    let buf = '';
    res.on('data', (d) => (buf += d));
    res.on('end', () => {
      const r = JSON.parse(buf);
      if (r.message) return console.log('API:', r.message);

      const body = String(r.body || '').replace(/\r/g, '');
      const heads = [...body.matchAll(/^#+[ \t]*([A-Za-z][A-Za-z ]*?):?[ \t]*$/gm)];

      console.log('tag        ', r.tag_name, '-> version', r.tag_name.replace(/^Mudlet[-_ ]?/i, ''));
      console.log('published  ', r.published_at);
      console.log('sections   ', heads.map((h) => h[1]).join(', '));
      console.log('');

      for (let i = 0; i < heads.length; i++) {
        const start = heads[i].index + heads[i][0].length;
        const end = i + 1 < heads.length ? heads[i + 1].index : body.length;
        const chunk = body.slice(start, end);
        // bullets are written "\- text" (an escaped dash) or plain "- text"
        const items = chunk.split('\n').filter((l) => /^\s*\\?[-*]\s+\S/.test(l));
        console.log(`  ${heads[i][1].padEnd(16)} ${String(items.length).padStart(3)}`);
        if (items[0]) console.log(`      e.g. ${items[0].trim().replace(/^\\?[-*]\s+/, '').slice(0, 70)}`);
      }
    });
  }
).on('error', (e) => console.log('error', e.message));
