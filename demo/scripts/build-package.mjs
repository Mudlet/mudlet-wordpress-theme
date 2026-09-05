// Zips packages/mudlet-demo/ into src/assets/mudlet-demo.mpackage.
//
// A .mpackage is a plain zip. mudlet-web unzips one into
// <profile>/<packageName>/ and seeds package.path with that directory, so the
// world ships as a *directory of .lua files* that require() each other by path
// — packages/mudlet-demo/init.lua is the entry, and every other module is
// reached from it. The generated XML carries only three things that cannot be
// files: the catch-all alias, the embed settings, and a bootstrap that requires
// the package.
//
// That is why nothing here concatenates or escapes the world any more. A Lua
// error in a required file reports the file and the line it happened on, which
// a script node pasted full of 2,000 lines cannot do.
import { readFileSync, writeFileSync, mkdirSync, copyFileSync, readdirSync, existsSync } from 'node:fs';
import { dirname, join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import { zipSync, strToU8 } from 'fflate';

const here = dirname(fileURLToPath(import.meta.url));
const pkgDir = join(here, '..', 'packages', 'mudlet-demo');
const outFile = join(here, '..', 'src', 'assets', 'mudlet-demo.mpackage');

const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

// config.lua is the package manifest, not a module. embed.lua is deliberately
// its own script node — see the file — so it is counted and hashed like the
// rest but installed through the XML rather than required, and shipping it as a
// file as well would put two copies of it in the profile.
const MANIFEST = 'config.lua';
const EMBED = 'embed.lua';
const ENTRY = 'init.lua';

// Every .lua in the package, at its path relative to the package root, sorted
// so the fingerprint below does not depend on what order the filesystem hands
// them back in.
const luaFiles = (dir, base = dir) =>
	readdirSync(dir, { withFileTypes: true })
		.flatMap(e => {
			const full = join(dir, e.name);
			if (e.isDirectory()) return luaFiles(full, base);
			return e.isFile() && e.name.endsWith('.lua')
				? [relative(base, full).split(sep).join('/')]
				: [];
		})
		.sort();

const paths = luaFiles(pkgDir);
const src = Object.fromEntries(paths.map(f => [f, readFileSync(join(pkgDir, f), 'utf8')]));
const modules = paths.filter(f => f !== MANIFEST && f !== EMBED);

if (!modules.includes(ENTRY)) {
	throw new Error(`packages/mudlet-demo/${ENTRY} is missing — the bootstrap has nothing to require`);
}

// The world quotes its own size back at the visitor — the terminal on the
// plinth claims a line count, and the cellar under the Release Vault has the
// files themselves on shelves, one crate each. Counting the Lua being zipped is
// the only way either of them stays true: a number typed into the prose is
// wrong at the next edit, including the edit that changes the prose.
//
// One module carries an empty `local FILES = {}` for this to fill in — one line
// of table literal replacing one line of nothing, so the counts still describe
// the files they were counted from, that module included. The total is summed
// out of the same table in Lua rather than injected beside it: two generated
// numbers can disagree and one cannot.
//
// Comment lines and code lines are counted apart because the cellar's joke is
// the ratio between them, and the crate worth opening for it is a *small* file
// — the shelf shows the heaviest dozen, and the most over-explained thing in
// this package is thirty-six lines long. Counting here is what lets the shelf
// point at it. The room counts the same file again when the lid comes off, off
// the disk rather than out of this table, and the two agree because they are
// two counts of one file: `^%s*%-%-` there is this `/^\s*--/`.
//
// The last field is whether the file ships as a file. Everything does except
// embed.lua, which the XML carries as a script node — so the one crate whose
// lid will not come off says why rather than leaving the room to guess.
const measure = s => {
	const lines = s.split('\n');
	if (lines[lines.length - 1] === '') lines.pop();
	let comment = 0;
	let code = 0;
	for (const line of lines) {
		if (/^\s*--/.test(line)) comment += 1;
		else if (line.trim() !== '') code += 1;
	}
	return { lines: lines.length, comment, code };
};
const scriptLines = paths.reduce((n, f) => n + measure(src[f]).lines, 0);
const inventory = paths
	.map(f => {
		const m = measure(src[f]);
		return `{[[${f}]],${m.lines},${m.comment},${m.code},${f !== EMBED}}`;
	})
	.join(', ');
const marker = /^local FILES = \{\}(?=\r?$)/m;
const carriers = modules.filter(f => marker.test(src[f]));
if (carriers.length !== 1) {
	throw new Error(`expected exactly one \`local FILES = {}\` line in the package, found ${carriers.length}`);
}
src[carriers[0]] = src[carriers[0]].replace(marker, () => `local FILES = { ${inventory} }`);

// A returning visitor keeps the world they already have unless the package's
// version string changes, and that string had to be bumped by hand in two
// files — miss one and your edits simply do not appear, which is a bad hour.
// So the build derives it: the number in config.lua, plus a hash of the Lua
// that actually ships. Edit any file in the world and it changes; rebuild an
// untouched world and it does not, which keeps the mpackage reproducible.
//
// config.lua's own version line is excluded from the hash (it is the thing
// being computed) and rewritten on the way into the zip. The file on disk
// keeps the plain number — bump that by hand for a release, not for an edit.
const versionLine = /^version\s*=\s*\[\[(.*?)\]\](?=\r?$)/m;
const baseVersion = src[MANIFEST].match(versionLine)?.[1];
if (!baseVersion) {
	throw new Error('config.lua: no `version = [[<n>]]` line to build a version from');
}
const nameLine = /^mpackage\s*=\s*\[\[(.*?)\]\]/m;
const pkgName = src[MANIFEST].match(nameLine)?.[1];
if (!pkgName) {
	throw new Error('config.lua: no `mpackage = [[<name>]]` line — the bootstrap would not know what to require');
}
const hash = createHash('sha256').update(src[MANIFEST].replace(versionLine, ''));
for (const f of paths) if (f !== MANIFEST) hash.update(f).update(src[f]);
const version = `${baseVersion}+${hash.digest('hex').slice(0, 8)}`;
const configSrc = src[MANIFEST].replace(versionLine, () => `version = [[${version}]]`);

// The client has to declare the same string, or it reinstalls the package on
// every load (or never). One generated line, imported by src/main.tsx.
//
// Read before it is overwritten: the string a built dist/ is carrying is the
// one this file held when Vite ran, and --sync-dist below needs it to find the
// version baked into the bundle.
const versionFile = join(here, '..', 'src', 'assets', 'mudlet-demo.version.ts');
const prevVersion = existsSync(versionFile)
	? readFileSync(versionFile, 'utf8').match(/DEMO_PACKAGE_VERSION = '(.*?)'/)?.[1]
	: undefined;
writeFileSync(
	versionFile,
	'// Generated by scripts/build-package.mjs. Do not edit.\n'
		+ `export const DEMO_PACKAGE_VERSION = '${version}';\n`,
);

// Everything the world is, in one require. package.loaded is cleared first so
// that re-running this node genuinely reloads the files rather than handing
// back the copies from the last run.
const bootstrap = `local pkg = '${pkgName}'
for name in pairs(package.loaded) do
	if name == pkg or name:sub(1, #pkg + 1) == pkg .. '.' then
		package.loaded[name] = nil
	end
end
require(pkg)
`;

// The catch-all alias is what makes an offline profile playable: aliases run in
// hostSend regardless of connection state, so every line typed reaches Lua
// instead of a socket that isn't there.
//
// Only the *first* matching permanent alias fires (ScriptingEngine.matchPerm),
// so a bare ^(.*)$ would swallow `lua ...` before run-lua-code ever saw it —
// hence the negative lookahead. A bare `lua` with no code still lands here
// and gets the normal "Nothing happens.", rather than vanishing into the
// socket that isn't there.
const xml = `<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE MudletPackage>
<MudletPackage version="1.001">
	<TriggerPackage />
	<TimerPackage />
	<AliasPackage>
		<Alias isActive="yes" isFolder="no">
			<name>demo input</name>
			<script>demo.input(matches[2])</script>
			<command></command>
			<packageName>${pkgName}</packageName>
			<regex>^(?!lua\\s)(.*)$</regex>
		</Alias>
	</AliasPackage>
	<ActionPackage />
	<ScriptPackage>
		<Script isActive="yes" isFolder="no">
			<name>embed settings</name>
			<packageName>${pkgName}</packageName>
			<script>${esc(src[EMBED])}</script>
			<eventHandlerList />
		</Script>
		<Script isActive="yes" isFolder="no">
			<name>demo world</name>
			<packageName>${pkgName}</packageName>
			<script>${esc(bootstrap)}</script>
			<eventHandlerList />
		</Script>
	</ScriptPackage>
	<KeyPackage />
	<VariablePackage>
		<HiddenVariables />
	</VariablePackage>
</MudletPackage>
`;

const zip = zipSync({
	[MANIFEST]: strToU8(configSrc),
	[`${pkgName}.xml`]: strToU8(xml),
	...Object.fromEntries(modules.map(f => [f, strToU8(src[f])])),
});

mkdirSync(dirname(outFile), { recursive: true });
writeFileSync(outFile, zip);
console.log(`  -> src/assets/mudlet-demo.mpackage (${zip.length} bytes, `
	+ `${modules.length} modules, ${scriptLines} lines of Lua, v${version})`);

// Mudlet's own run-lua-code, copied out of the installed library rather than
// vendored, so it tracks whatever version of Mudlet Web is in node_modules.
// It gives the demo a `lua <code>` command — the shortest possible proof that
// the thing in the page is a real Lua runtime and not a scripted transcript.
const runLua = join(here, '..', 'node_modules', '@mudlet', 'mudlet-web', 'dist-lib',
	'import', 'defaults', 'run-lua-code', 'run-lua-code.mpackage');
copyFileSync(runLua, join(here, '..', 'src', 'assets', 'run-lua-code.mpackage'));
console.log('  -> src/assets/run-lua-code.mpackage (copied from @mudlet/mudlet-web)');

// --sync-dist: put the world that was just zipped into an already-built dist/,
// which is what the theme frames.
//
// The page loads dist/, not src/, so editing a room and running this script
// used to change nothing the visitor could see until somebody remembered to
// run `vite build` as well — fifteen seconds of rebuilding Monaco to ship two
// lines of Lua, and a page still showing the old world if they forgot. This is
// the whole of what that rebuild would have changed:
//
//   * the .mpackage itself, which Vite emits under a content-hashed name;
//   * the version string, which is *not* in the package — it is compiled into
//     the JS out of mudlet-demo.version.ts, and the client reinstalls the
//     world only when it changes.
//
// Both are patched in place, which means the hashed filename now names bytes
// other than the ones it was hashed from. That is a lie a dist/ can afford
// (it is untracked build output, served locally with revalidation) and one a
// release cannot — the tag builds from clean through `npm run build`, which
// does not pass this flag. Anything else — a change under src/, a new
// dependency, an upgraded mudlet-web — still needs the real build, and this
// says so rather than half-updating a page and letting somebody debug it.
if (process.argv.includes('--sync-dist')) {
	const dist = join(here, '..', 'dist', 'assets');
	const note = why => console.log(`  .. dist/ not updated: ${why} — run \`npm run build\``);

	if (!existsSync(dist)) {
		note('nothing built there yet');
	} else {
		const names = readdirSync(dist);
		const shipped = names.filter(f => /^mudlet-demo-.*\.mpackage$/.test(f));
		if (shipped.length !== 1) {
			note(`found ${shipped.length} mudlet-demo mpackages, expected 1`);
		} else if (prevVersion === undefined) {
			note('no previous version to find in the bundle');
		} else {
			writeFileSync(join(dist, shipped[0]), zip);

			// The version only moves when the Lua does, so an unchanged world
			// leaves the bundle alone rather than reporting a patch it did not
			// make.
			let patched = 0;
			if (version !== prevVersion) {
				for (const f of names.filter(f => f.endsWith('.js'))) {
					const js = readFileSync(join(dist, f), 'utf8');
					if (!js.includes(prevVersion)) continue;
					writeFileSync(join(dist, f), js.split(prevVersion).join(version));
					patched += 1;
				}
			}
			if (version !== prevVersion && patched === 0) {
				note(`v${prevVersion} is not in that bundle`);
			} else {
				console.log(`  -> dist/assets/${shipped[0]}`
					+ (patched ? ` and v${version} in ${patched} bundle file${patched > 1 ? 's' : ''}` : ''));
			}
		}
	}
}
