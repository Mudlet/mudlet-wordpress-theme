// Pack the theme and the plugins into zips wp-admin will accept.
//
//   node wordpress/tools/build-dist.mjs                 # -> wordpress/dist/*.zip
//   node wordpress/tools/build-dist.mjs --with-demo     # ...theme carries the hero
//   node wordpress/tools/build-dist.mjs --version 1.2.3 # ...stamped, without touching the tree
//   node wordpress/tools/build-dist.mjs --no-plugins    # ...theme carries only itself
//   node wordpress/tools/build-dist.mjs --only mudlet-games
//   node wordpress/tools/build-dist.mjs --out ../somewhere
//
// Upload each one at Appearance -> Themes -> Add New -> Upload, and Plugins ->
// Add New -> Upload. WordPress installs by the **directory inside the zip**,
// not by the file's name, so each archive has exactly one top-level folder
// named for the slug - upload a second time and it offers to replace what is
// there, because the folder matches.
//
// mudlet.zip is the whole site: the theme, and the three plugins under
// plugins/, which the theme requires from functions.php unless the site has
// them installed in wp-content/plugins. See the theme's inc/bundled-plugins.php
// for the arbitration. The plugin zips are still built - they are how a site
// that would rather have them as plugins gets them, and how a site that has
// changed theme keeps its games - but nothing needs them to stand a site up.
//
// --version stamps the number into every header **in the archives only**; the
// working tree is not touched. That is what the release workflow uses, so the
// tag is the one place a version is written down and the checked-in headers
// stay at whatever the last release was.
//
// Why the zip is written out by hand: nothing under wordpress/ has a
// package.json, and the tools here run on bare node - `fetch-releases.mjs` does
// too. Pulling in a dependency for four archives would mean an npm install
// standing between somebody and a build. Same argument as the QR encoder in
// theme.js. It is about ninety lines below, store or deflate, no zip64: the
// inputs are text and images measured in megabytes.
//
// Timestamps are fixed rather than taken from the files, so two runs over
// unchanged sources produce byte-identical archives - the cheapest way to see
// whether anything actually changed since the last upload.

import { deflateRawSync } from 'node:zlib';
import { fileURLToPath } from 'node:url';
import {
	existsSync, mkdirSync, readFileSync, readdirSync, rmSync, statSync, writeFileSync,
} from 'node:fs';
import { dirname, join, relative, resolve, sep } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const WP = resolve(HERE, '..');          // wordpress/
const ROOT = resolve(WP, '..');          // the repo

// ── what gets built ───────────────────────────────────────────────────
// `slug` is the folder inside the zip, and the folder WordPress installs to.
// It has to match what is already on the test site or an upload lands beside
// the old copy instead of replacing it.
const PACKAGES = [
	{ slug: 'mudlet', kind: 'theme', from: 'theme/mudlet', version: readTheme },
	{ slug: 'mudlet-games', kind: 'plugin', from: 'plugin/mudlet-games', version: readPlugin },
	{ slug: 'mudlet-makers', kind: 'plugin', from: 'plugin/mudlet-makers', version: readPlugin },
	{ slug: 'mudlet-releases', kind: 'plugin', from: 'plugin/mudlet-releases', version: readPlugin },
];

// Never shipped, from anywhere.
const SKIP = new Set(['node_modules', '.git', '.DS_Store', 'Thumbs.db', 'desktop.ini']);
const SKIP_EXT = ['.log', '.orig', '.rej', '.swp'];

// ── arguments ─────────────────────────────────────────────────────────
const argv = process.argv.slice(2);
const has = (f) => argv.includes(f);
const val = (f, d) => { const i = argv.indexOf(f); return i >= 0 && argv[i + 1] ? argv[i + 1] : d; };

const withDemo = has('--with-demo');
const noPlugins = has('--no-plugins');
const stampVersion = val('--version', null);
const only = val('--only', null);
const outDir = resolve(ROOT, val('--out', 'wordpress/dist'));

if (has('--help') || has('-h')) {
	console.log(readFileSync(fileURLToPath(import.meta.url), 'utf8')
		.split('\n').filter((l) => l.startsWith('//')).map((l) => l.slice(3)).join('\n'));
	process.exit(0);
}

// ── build ─────────────────────────────────────────────────────────────
mkdirSync(outDir, { recursive: true });

const built = [];
for (const pkg of PACKAGES) {
	if (only && only !== pkg.slug) continue;

	const src = join(WP, pkg.from);
	if (!existsSync(src)) {
		console.error(`  ! ${pkg.from} is missing - skipped`);
		continue;
	}

	const files = [];
	walk(src, src, files, pkg);

	// The Mudlet menu, the sync schedules, and the seams that let a plugin run
	// from inside the theme. Two source files under plugin/shared/, carried by
	// all three plugins because a plugin reaching into a sibling breaks when
	// the sibling is deactivated - whichever loads first wins the
	// class_exists() race. Edited in plugin/shared/, never in a plugin, which
	// is also how docker compose mounts it.
	if (pkg.kind === 'plugin') {
		for (const f of sharedFiles(pkg)) files.push(f);
	}

	// The theme carries the plugins. This is the whole reason mudlet.zip is
	// the only archive a site needs: functions.php requires whatever it finds
	// under plugins/, and stands down for any of the three that is installed
	// in wp-content/plugins instead. Each gets its own copy of shared/, the
	// same as its own zip does and for the same reason.
	if (pkg.kind === 'theme' && !noPlugins) {
		for (const p of PACKAGES.filter((x) => x.kind === 'plugin')) {
			const src = join(WP, p.from);
			if (!existsSync(src)) {
				console.error(`  ! ${p.from} is missing - the theme will ship without it`);
				continue;
			}

			const carried = [];
			walk(src, src, carried, p);
			for (const f of sharedFiles(p)) carried.push(f);
			for (const f of carried) files.push({ abs: f.abs, rel: `plugins/${p.slug}/${f.rel}` });
		}
	}

	// The hero's client, when asked for. It is not in the theme on disk:
	// assets/demo/ is an empty bind-mount target for demo/dist (gitignored), so
	// it is copied in from the real build here rather than shipped in the repo.
	if (pkg.kind === 'theme' && withDemo) {
		const dist = join(ROOT, 'demo/dist');
		if (existsSync(dist)) {
			const demo = [];
			walk(dist, dist, demo, pkg);
			for (const f of demo) files.push({ abs: f.abs, rel: `assets/demo/${f.rel}` });
		} else {
			console.error('  ! --with-demo, but demo/dist is not built (cd demo && npm run build)');
		}
	}

	// --version, if given. Rewritten into the entry rather than onto disk, so
	// a release is reproducible from a clean checkout and no commit is needed
	// to cut one.
	if (stampVersion) for (const f of files) stamp(pkg, f, stampVersion);

	files.sort((a, b) => (a.rel < b.rel ? -1 : 1));

	const zip = makeZip(pkg.slug, files);
	const out = join(outDir, `${pkg.slug}.zip`);
	writeFileSync(out, zip);
	built.push({ ...pkg, files: files.length, bytes: zip.length, ver: stampVersion || pkg.version(src), out });
}

// ── say what happened ─────────────────────────────────────────────────
if (!built.length) {
	console.error(only ? `Nothing matched --only ${only}.` : 'Nothing to build.');
	process.exit(1);
}

console.log(`\n  ${relative(ROOT, outDir).split(sep).join('/')}/\n`);
for (const b of built) {
	console.log(`  ${b.slug.padEnd(17)} ${String(b.ver).padEnd(8)} ${String(b.files).padStart(4)} files  ${mb(b.bytes)}`);
}

// A theme carrying the client is ~4x the size of one that is not, and a stock
// PHP caps an upload at 2 MB. Worth saying before somebody meets the error.
const fat = built.filter((b) => b.bytes > 2 * 1024 * 1024);
if (fat.length) {
	console.log('\n  Bigger than PHP\'s default 2 MB upload_max_filesize:');
	for (const b of fat) console.log(`    ${b.slug}.zip  ${mb(b.bytes)}`);
	console.log('  Raise upload_max_filesize and post_max_size on the test site, or');
	console.log('  put these in wp-content/ over SFTP instead - the folder names are');
	console.log('  the same either way.');
}
if (!withDemo) {
	console.log('\n  The theme has no assets/demo/, so the hero stays on its scripted');
	console.log('  session. --with-demo packs demo/dist into it (about +9 MB).');
}
if (noPlugins) {
	console.log('\n  --no-plugins: the theme carries none, so a site built from mudlet.zip');
	console.log('  alone has no games, makers or releases until the plugin zips are');
	console.log('  installed the old way.');
}
console.log();

// ── walking ───────────────────────────────────────────────────────────

function walk(root, dir, out, pkg) {
	for (const name of readdirSync(dir)) {
		if (SKIP.has(name) || SKIP_EXT.some((e) => name.endsWith(e))) continue;

		const abs = join(dir, name);
		const rel = relative(root, abs).split(sep).join('/');

		// The bind-mount target. Empty on disk, and --with-demo fills it from
		// demo/dist below rather than from here.
		if (pkg.kind === 'theme' && rel === 'assets/demo') continue;

		const st = statSync(abs);
		if (st.isDirectory()) walk(root, abs, out, pkg);
		else if (st.isFile()) out.push({ abs, rel });
	}
}

// The two files under plugin/shared/, addressed from inside one plugin. A
// declaration rather than a const for the same reason crc32 is: the build
// above runs at the top level, before anything down here is initialised.
function sharedFiles(pkg) {
	const shared = join(WP, 'plugin/shared');
	if (!existsSync(shared)) {
		console.error('  ! plugin/shared is missing - the plugins will have no Mudlet menu');
		return [];
	}

	const carried = [];
	walk(shared, shared, carried, pkg);
	return carried.map((f) => ({ abs: f.abs, rel: `shared/${f.rel}` }));
}

// Whether an entry is the file somebody reads a version out of: style.css for
// a theme, and <slug>.php for a plugin - wherever that plugin has been put,
// which for the theme's copies is plugins/<slug>/.
function isVersionFile(pkg, rel) {
	if (pkg.kind === 'theme' && rel === 'style.css') return true;

	const m = rel.match(/^(?:plugins\/([^/]+)\/)?([^/]+)\.php$/);
	return !!m && m[2] === (m[1] || pkg.slug);
}

// Put `version` in the archive's copy of a header, leaving the file on disk
// alone. Two forms, because a theme writes Version: in a CSS comment and a
// plugin writes it in a docblock and again as a constant its own code reads.
function stamp(pkg, f, version) {
	if (!isVersionFile(pkg, f.rel)) return;

	const text = readFileSync(f.abs, 'utf8')
		.replace(/^(\s*(?:\*\s*)?Version:\s*).+$/m, `$1${version}`)
		.replace(/(define\( 'MUDLET_[A-Z_]*VERSION', ')[^']*(' \);)/, `$1${version}$2`);

	f.data = Buffer.from(text, 'utf8');
}

function readTheme(dir) {
	return header(join(dir, 'style.css'), /^\s*Version:\s*(.+)$/m);
}

function readPlugin(dir) {
	const main = readdirSync(dir).find((f) => f.endsWith('.php'));
	return main ? header(join(dir, main), /^\s*\*?\s*Version:\s*(.+)$/m) : '?';
}

function header(file, re) {
	if (!existsSync(file)) return '?';
	const m = readFileSync(file, 'utf8').slice(0, 4096).match(re);
	return m ? m[1].trim() : '?';
}

// A declaration, not a const: it is called from the top-level work above, which
// runs before this point in the file.
function mb(n) {
	return n < 1024 * 1024 ? `${(n / 1024).toFixed(0)} KB` : `${(n / 1024 / 1024).toFixed(1)} MB`;
}

// ── the zip ───────────────────────────────────────────────────────────
// Store or deflate, no zip64, no directory entries - every path is spelled in
// full, which is what every unzip since the nineties expects and what
// WordPress's own ZipArchive and PclZip both handle.

// The table and the fixed timestamp hang off the function rather than sitting
// in module consts: the build above runs at the top level, before a const
// declared down here has been initialised, and a `const` read too early throws
// rather than reading undefined. A function declaration is hoisted whole.
function crc32(buf) {
	const t = (crc32.table ??= (() => {
		const tbl = new Int32Array(256);
		for (let i = 0; i < 256; i++) {
			let c = i;
			for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
			tbl[i] = c;
		}
		return tbl;
	})());

	let c = -1;
	for (let i = 0; i < buf.length; i++) c = t[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
	return (c ^ -1) >>> 0;
}

// 2020-01-01 00:00, in DOS's packed date. Fixed, so a rebuild of unchanged
// sources is byte-identical; see the header.
function dosStamp() {
	return { time: 0, date: ((2020 - 1980) << 9) | (1 << 5) | 1 };
}

function makeZip(folder, files) {
	const { time: DOS_TIME, date: DOS_DATE } = dosStamp();
	const locals = [];
	const central = [];
	let offset = 0;

	for (const f of files) {
		const name = Buffer.from(`${folder}/${f.rel}`, 'utf8');
		const raw = f.data ?? readFileSync(f.abs);
		const deflated = raw.length ? deflateRawSync(raw, { level: 9 }) : Buffer.alloc(0);

		// Only compress when it actually pays. Already-compressed PNG and JPEG
		// come out bigger through deflate as often as not.
		const store = deflated.length >= raw.length;
		const body = store ? raw : deflated;
		const method = store ? 0 : 8;
		const crc = crc32(raw);

		const lh = Buffer.alloc(30);
		lh.writeUInt32LE(0x04034b50, 0);
		lh.writeUInt16LE(20, 4);          // version needed
		lh.writeUInt16LE(0x0800, 6);      // flags: names are UTF-8
		lh.writeUInt16LE(method, 8);
		lh.writeUInt16LE(DOS_TIME, 10);
		lh.writeUInt16LE(DOS_DATE, 12);
		lh.writeUInt32LE(crc, 14);
		lh.writeUInt32LE(body.length, 18);
		lh.writeUInt32LE(raw.length, 22);
		lh.writeUInt16LE(name.length, 26);
		lh.writeUInt16LE(0, 28);          // no extra field
		locals.push(lh, name, body);

		const ch = Buffer.alloc(46);
		ch.writeUInt32LE(0x02014b50, 0);
		ch.writeUInt16LE(0x031e, 4);      // made by: unix, so the mode below is read
		ch.writeUInt16LE(20, 6);
		ch.writeUInt16LE(0x0800, 8);
		ch.writeUInt16LE(method, 10);
		ch.writeUInt16LE(DOS_TIME, 12);
		ch.writeUInt16LE(DOS_DATE, 14);
		ch.writeUInt32LE(crc, 16);
		ch.writeUInt32LE(body.length, 20);
		ch.writeUInt32LE(raw.length, 24);
		ch.writeUInt16LE(name.length, 28);
		ch.writeUInt16LE(0, 30);          // extra
		ch.writeUInt16LE(0, 32);          // comment
		ch.writeUInt16LE(0, 34);          // disk
		ch.writeUInt16LE(0, 36);          // internal attrs
		// 0100644: a regular file, rw-r--r--. Unzipping on the server otherwise
		// inherits whatever the umask feels like.
		// >>>0 because << returns a signed int32 and this one has the top bit
		// set: 0o100644 << 16 comes out negative, which writeUInt32LE rejects.
		ch.writeUInt32LE((0o100644 << 16) >>> 0, 38);
		ch.writeUInt32LE(offset, 42);
		central.push(ch, name);

		offset += lh.length + name.length + body.length;
	}

	const cd = Buffer.concat(central);
	const end = Buffer.alloc(22);
	end.writeUInt32LE(0x06054b50, 0);
	end.writeUInt16LE(0, 4);
	end.writeUInt16LE(0, 6);
	end.writeUInt16LE(files.length, 8);
	end.writeUInt16LE(files.length, 10);
	end.writeUInt32LE(cd.length, 12);
	end.writeUInt32LE(offset, 16);
	end.writeUInt16LE(0, 20);

	return Buffer.concat([...locals, cd, end]);
}
