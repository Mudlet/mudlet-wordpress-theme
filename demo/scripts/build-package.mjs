// Zips packages/mudlet-demo/ into src/assets/mudlet-demo.mpackage.
//
// A .mpackage is a plain zip of config.lua + a Mudlet package XML. The XML is
// generated rather than hand-maintained so the Lua stays in a .lua file the
// editor can lint — the script body and the catch-all alias are injected here,
// XML-escaped.
import { readFileSync, writeFileSync, mkdirSync, copyFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { zipSync, strToU8 } from 'fflate';

const here = dirname(fileURLToPath(import.meta.url));
const pkgDir = join(here, '..', 'packages', 'mudlet-demo');
const outFile = join(here, '..', 'src', 'assets', 'mudlet-demo.mpackage');

const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const config = readFileSync(join(pkgDir, 'config.lua'), 'utf8');
const world = readFileSync(join(pkgDir, 'world.lua'), 'utf8');
const embed = readFileSync(join(pkgDir, 'embed.lua'), 'utf8');

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
			<packageName>mudlet-demo</packageName>
			<regex>^(?!lua\\s)(.*)$</regex>
		</Alias>
	</AliasPackage>
	<ActionPackage />
	<ScriptPackage>
		<Script isActive="yes" isFolder="no">
			<name>embed settings</name>
			<packageName>mudlet-demo</packageName>
			<script>${esc(embed)}</script>
			<eventHandlerList />
		</Script>
		<Script isActive="yes" isFolder="no">
			<name>demo world</name>
			<packageName>mudlet-demo</packageName>
			<script>${esc(world)}</script>
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
	'config.lua': strToU8(config),
	'mudlet-demo.xml': strToU8(xml),
});

mkdirSync(dirname(outFile), { recursive: true });
writeFileSync(outFile, zip);
console.log(`  -> src/assets/mudlet-demo.mpackage (${zip.length} bytes)`);

// Mudlet's own run-lua-code, copied out of the installed library rather than
// vendored, so it tracks whatever version of Mudlet Web is in node_modules.
// It gives the demo a `lua <code>` command — the shortest possible proof that
// the thing in the page is a real Lua runtime and not a scripted transcript.
const runLua = join(here, '..', 'node_modules', '@mudlet', 'mudlet-web', 'dist-lib',
	'import', 'defaults', 'run-lua-code', 'run-lua-code.mpackage');
copyFileSync(runLua, join(here, '..', 'src', 'assets', 'run-lua-code.mpackage'));
console.log('  -> src/assets/run-lua-code.mpackage (copied from @mudlet/mudlet-web)');
