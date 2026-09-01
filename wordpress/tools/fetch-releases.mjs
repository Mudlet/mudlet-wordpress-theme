// Dump Mudlet's releases to JSON, for backfilling the release store.
//
//   node wordpress/tools/fetch-releases.mjs [--limit 30] [--out seed/releases.json]
//
// Uses the `gh` CLI, which is authenticated: 5000 requests an hour instead of
// the 60 an anonymous WordPress gets. Backfilling ~40 releases needs a few
// hundred, so doing it from PHP would take most of a day of waiting. This does
// it in a couple of minutes and hands WordPress a file to import.
//
// It deliberately does **not** categorise anything. Commit titles go into the
// file raw and the plugin buckets them on import, so the rules live in exactly
// one place (Mudlet_Releases_Changelog::prefixes) and the dump cannot drift
// from what the site would have worked out itself.
//
// Prereleases are skipped: Mudlet publishes a public test build most days, and
// they are not what anyone means by "releases".

import { execFileSync } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

const args = process.argv.slice(2);
const arg = (name, fallback) => {
  const i = args.indexOf(`--${name}`);
  return i !== -1 && args[i + 1] ? args[i + 1] : fallback;
};

const REPO = arg('repo', 'Mudlet/Mudlet');
const LIMIT = parseInt(arg('limit', '40'), 10);
const OUT = resolve(here, '..', arg('out', 'seed/releases.json'));

function gh(endpoint) {
  const out = execFileSync('gh', ['api', '--paginate', '--slurp', endpoint], {
    encoding: 'utf8',
    maxBuffer: 256 * 1024 * 1024,
  });
  // --slurp gives an array of pages; each page is itself an array or an object
  const pages = JSON.parse(out);
  return pages;
}

function releases() {
  const pages = gh(`repos/${REPO}/releases?per_page=100`);
  return pages.flat();
}

function compare(base, head) {
  // The compare endpoint returns an object per page, each with its own
  // `commits` slice; flatten them into one list.
  const pages = gh(`repos/${REPO}/compare/${base}...${head}?per_page=100`);
  const commits = [];
  let total = null;
  for (const page of pages) {
    if (total === null && typeof page.total_commits === 'number') total = page.total_commits;
    for (const c of page.commits || []) commits.push(c);
  }
  return { total, commits };
}

async function checksums(assets) {
  const sums = assets.find((a) => a.name === 'SHA256SUMS.txt');
  if (!sums) return {};
  try {
    // A release asset, not the API — no rate limit, no token needed.
    const res = await fetch(sums.browser_download_url, { redirect: 'follow' });
    if (!res.ok) return {};
    const text = await res.text();
    const out = {};
    for (const line of text.split(/\r?\n/)) {
      const m = line.trim().match(/^([0-9a-f]{64})\s+\*?(.+)$/i);
      if (m) out[m[2].trim()] = m[1].toLowerCase();
    }
    return out;
  } catch {
    return {};
  }
}

const all = releases();
const stable = all
  .filter((r) => !r.prerelease && !r.draft && r.tag_name)
  .sort((a, b) => (b.published_at || '').localeCompare(a.published_at || ''));

console.log(`${all.length} releases, ${stable.length} stable; taking the newest ${Math.min(LIMIT, stable.length)}`);

const wanted = stable.slice(0, LIMIT);
const out = [];

for (let i = 0; i < wanted.length; i++) {
  const r = wanted[i];
  // The previous *stable* release, which is what a changelog should span.
  const prev = stable[stable.indexOf(r) + 1];

  let changes = null;
  if (prev) {
    try {
      const { total, commits } = compare(prev.tag_name, r.tag_name);
      changes = {
        previous: prev.tag_name,
        total_commits: total,
        // raw titles only — the plugin categorises
        commit_titles: commits.map((c) => (c.commit?.message || '').split('\n')[0]).filter(Boolean),
        // One row per commit, for the contributor tally. Raw identities only:
        // which of these are people, how co-authors fold into the same person,
        // and who counts as a bot are all decided by
        // Mudlet_Releases_Changelog::contributors_from_rows, so this cannot
        // drift from what the live path would have produced.
        //
        // The trailers are extracted here because the full message is not in
        // the file — dumping every body to recover four lines of "Co-authored-
        // by" would multiply its size for nothing.
        commit_authors: commits.map((c) => ({
          login: c.author?.login || '',
          name: c.commit?.author?.name || '',
          email: c.commit?.author?.email || '',
          avatar: c.author?.avatar_url || '',
          coauthors: [...(c.commit?.message || '').matchAll(/^\s*Co-authored-by:\s*(.+?)\s*<([^>]+)>\s*$/gim)].map(
            (m) => ({ name: m[1].trim(), email: m[2].trim() })
          ),
        })),
      };
    } catch (e) {
      console.log(`  ! compare ${prev.tag_name}...${r.tag_name} failed: ${String(e.message).slice(0, 80)}`);
    }
  }

  out.push({
    id: r.id,
    tag_name: r.tag_name,
    name: r.name,
    published_at: r.published_at,
    prerelease: !!r.prerelease,
    html_url: r.html_url,
    body: r.body || '',
    assets: (r.assets || []).map((a) => ({
      name: a.name,
      size: a.size,
      browser_download_url: a.browser_download_url,
    })),
    checksums: await checksums(r.assets || []),
    changes,
  });

  const n = changes ? changes.commit_titles.length : 0;
  console.log(`  ${String(i + 1).padStart(2)}/${wanted.length}  ${r.tag_name.padEnd(20)} ${String(n).padStart(4)} commits`);
}

mkdirSync(dirname(OUT), { recursive: true });
writeFileSync(OUT, JSON.stringify({ repo: REPO, generated: new Date().toISOString(), releases: out }, null, 1));

const kb = (JSON.stringify(out).length / 1024).toFixed(0);
console.log(`\n  -> ${OUT}  (${out.length} releases, ${kb} KB)`);
console.log('  import it with:  docker compose run --rm --entrypoint sh seed -c "wp --path=/var/www/html mudlet-releases import /seed/releases.json"');
