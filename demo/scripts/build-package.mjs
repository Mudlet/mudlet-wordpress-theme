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
import { readFileSync, writeFileSync, mkdirSync, copyFileSync, readdirSync } from 'node:fs';
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

// The world quotes its own size back at the visitor, from the terminal on the
// plinth. Counting the Lua being zipped is the only way that stays true: a
// number typed into the prose is wrong at the next edit — including the edit
// that changes the prose. One module carries a `local SCRIPT_LINES = <n>` line
// for this to overwrite; the substitution is line-for-line, so the count it
// injects still describes the files it was counted from.
const lineCount = s => s.split('\n').length - (s.endsWith('\n') ? 1 : 0);
const scriptLines = paths.reduce((n, f) => n + lineCount(src[f]), 0);
const marker = /^local SCRIPT_LINES = \d+(?=\r?$)/m;
const carriers = modules.filter(f => marker.test(src[f]));
if (carriers.length !== 1) {
	throw new Error(`expected exactly one \`local SCRIPT_LINES = <n>\` line in the package, found ${carriers.length}`);
}
src[carriers[0]] = src[carriers[0]].replace(marker, () => `local SCRIPT_LINES = ${scriptLines}`);

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
writeFileSync(
	join(here, '..', 'src', 'assets', 'mudlet-demo.version.ts'),
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
