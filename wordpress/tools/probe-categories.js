// Check how a release range categorises, without booting WordPress.
//
//   node wordpress/tools/probe-categories.js Mudlet-4.20.1 Mudlet-4.21.0
//
// Mirrors Mudlet_Releases_Changelog: it walks the compare endpoint, pulls the
// pull request title out of each squash-merge commit, and buckets it by its
// leading word. Use it when tuning the prefix patterns - RULES here and
// prefixes() there have to agree, and this is much the faster way to see what a
// change does.
//
// Checked against the numbers Mudlet published for 4.21 ("47 new features, 77
// improvements, 207 bug fixes"): fixed and added match exactly, improved comes
// out one over and infrastructure ten over. Close enough to be the same method,
// not close enough to call the output official.
//
// Note the 60-requests-an-hour limit on unauthenticated GitHub. A large range
// takes six.
const https = require('https');
const [base, head] = process.argv.slice(2);

// candidate mapping — keep in step with the plugin's
const RULES = [
  ['added', /^(add|adds|added|feat|feature|new)\b/i],
  ['improved', /^(improve[ds]?|improvement|enhance[ds]?|change[ds]?|update[ds]?)\b/i],
  ['fixed', /^(fix|fixes|fixed|bugfix|hotfix)\b/i],
  ['infrastructure', /^(infra|infrastructure|infrastucture|ci|build|chore|docs?|test|tests|refactor|revert)\b/i],
];
// no 'release' bucket: version bumps fall through to 'other', which is listed
// rather than hidden so a bad pattern is noticeable

function get(path) {
  return new Promise((resolve, reject) => {
    https.get({ host: 'api.github.com', path, headers: { 'User-Agent': 'mudlet-probe' } }, (res) => {
      let b = '';
      res.on('data', (d) => (b += d));
      res.on('end', () => (res.statusCode === 200 ? resolve(JSON.parse(b)) : reject(new Error(res.statusCode + ' ' + b.slice(0, 120)))));
    }).on('error', reject);
  });
}

function categorise(title) {
  // strip a trailing "(#1234)" then look at the leading word, with or without a colon
  const t = title.replace(/\s*\(#\d+\)\s*$/, '').trim();
  const lead = t.replace(/^([A-Za-z]+)\s*:\s*/, '$1 ');
  for (const [name, re] of RULES) if (re.test(lead)) return name;
  return 'other';
}

(async () => {
  let page = 1, all = [], total = null;
  while (page <= 20) {
    const c = await get(`/repos/Mudlet/Mudlet/compare/${base}...${head}?per_page=100&page=${page}`);
    total = c.total_commits;
    const got = (c.commits || []).length;
    all = all.concat(c.commits || []);
    if (got < 100) break;
    page++;
  }

  const prs = [];
  for (const c of all) {
    const title = c.commit.message.split('\n')[0];
    const m = title.match(/^(.*)\s*\(#(\d+)\)\s*$/);
    prs.push({ title: (m ? m[1] : title).trim(), pr: m ? m[2] : null, cat: categorise(title) });
  }

  const tally = {};
  for (const p of prs) tally[p.cat] = (tally[p.cat] || 0) + 1;

  console.log(`${base} -> ${head}`);
  console.log(`  commits ${total}, with PR number ${prs.filter((p) => p.pr).length}\n`);
  for (const k of ['added', 'improved', 'fixed', 'infrastructure', 'other'])
    if (tally[k]) console.log('  ' + k.padEnd(16) + String(tally[k]).padStart(4));

  const other = prs.filter((p) => p.cat === 'other');
  if (other.length) {
    console.log('\n  uncategorised samples:');
    for (const p of other.slice(0, 8)) console.log('    ', p.title.slice(0, 90));
  }
})().catch((e) => console.log('error:', e.message));
