// Inlines prototype/assets/* as data: URIs so the prototype is self-contained
// (the Artifact viewer's CSP blocks all external hosts except Google Fonts).
//
// Each asset is emitted exactly ONCE into a dictionary at the end of the document;
// <img> tags carry data-img="<name>" and a tiny loader swaps in the src. Naive
// per-occurrence inlining ballooned the page to 7.8 MB for 2.6 MB of pixels.
//
// Every {{IMG:name}} placeholder in index.src.html sits inside src="...".
const fs = require('fs');
const path = require('path');

const assetDir = path.join(__dirname, 'assets');
const src = path.join(__dirname, 'index.src.html');
const out = path.join(__dirname, 'index.html');

const MIME = { '.png': 'image/png', '.gif': 'image/gif', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.svg': 'image/svg+xml', '.webp': 'image/webp' };
const BLANK = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

let html = fs.readFileSync(src, 'utf8');
const assets = new Map();   // name -> data URI
let uses = 0;

html = html.replace(/src="\{\{IMG:([^}]+)\}\}"/g, (_, raw) => {
  const name = raw.trim();
  uses++;
  if (!assets.has(name)) {
    const file = path.join(assetDir, name);
    if (!fs.existsSync(file)) {
      console.warn(`  MISSING  ${name}`);
      assets.set(name, null);
    } else {
      const mime = MIME[path.extname(file).toLowerCase()] || 'application/octet-stream';
      const b64 = fs.readFileSync(file).toString('base64');
      assets.set(name, `data:${mime};base64,${b64}`);
      console.log(`  ${name.padEnd(34)} ${(b64.length / 1024).toFixed(0).padStart(5)} KB`);
    }
  }
  return `src="${BLANK}" data-img="${name}"`;
});

const leftover = (html.match(/\{\{IMG:/g) || []).length;
if (leftover) console.warn(`  WARNING: ${leftover} placeholder(s) not inside src="..."`);

const dict = {};
for (const [k, v] of assets) if (v) dict[k] = v;

html += `
<script id="asset-loader">
(function(){
  var BLOB = ${JSON.stringify(dict)};
  Array.prototype.forEach.call(document.querySelectorAll('img[data-img]'), function(img){
    var src = BLOB[img.getAttribute('data-img')];
    if (src) img.src = src;
  });
})();
</script>`;

fs.writeFileSync(out, html);
const mb = (fs.statSync(out).size / 1024 / 1024).toFixed(2);
console.log(`\n  -> prototype/index.html  (${Object.keys(dict).length} unique assets, ${uses} uses, ${mb} MB)`);
