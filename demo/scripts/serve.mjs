// Minimal static server for local review: serves the repo root, so
// prototype/index.html and demo/dist/ share one origin — which is what the
// embed needs (a cross-origin iframe gets no IndexedDB in Safari/Firefox,
// and Mudlet Web keeps every profile in one).
import { createServer } from 'node:http';
import { createReadStream, statSync } from 'node:fs';
import { extname, join, normalize, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = normalize(join(dirname(fileURLToPath(import.meta.url)), '..', '..'));
const port = Number(process.argv[2] ?? 8765);

const TYPES = {
	'.html': 'text/html; charset=utf-8', '.js': 'text/javascript', '.mjs': 'text/javascript',
	'.css': 'text/css', '.json': 'application/json', '.wasm': 'application/wasm',
	'.svg': 'image/svg+xml', '.png': 'image/png', '.gif': 'image/gif', '.jpg': 'image/jpeg',
	'.woff2': 'font/woff2', '.ttf': 'font/ttf', '.xml': 'application/xml',
};

createServer((req, res) => {
	let p = join(root, decodeURIComponent(req.url.split('?')[0]));
	if (!normalize(p).startsWith(root)) { res.writeHead(403).end(); return; }
	try {
		if (statSync(p).isDirectory()) p = join(p, 'index.html');
		res.writeHead(200, { 'content-type': TYPES[extname(p)] ?? 'application/octet-stream' });
		createReadStream(p).pipe(res);
	} catch {
		res.writeHead(404, { 'content-type': 'text/plain' }).end('not found');
	}
}).listen(port, () => console.log(`serving ${root} on http://localhost:${port}`));
