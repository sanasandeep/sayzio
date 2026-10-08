// Verify opacity expressions from the actual block templates in Chromium.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../../resources/views/common/blocks');
const expressions = [];
for (const file of fs.readdirSync(root).filter(f => f.endsWith('.blade.php'))) {
  const source = fs.readFileSync(path.join(root, file), 'utf8');
  assert(!/\}\}[0-9a-f]{2}(?=[;" ,)])/i.test(source), `${file}: appended alpha creates invalid non-hex CSS`);
  for (const match of source.matchAll(/color-mix\(in srgb, (\{\{[^{}\n]+\}\}) ([\d.]+)%, transparent\)/g)) {
    for (const color of ['#123', '#123456', 'rgb(12, 34, 56)', 'navy', 'rgba(12, 34, 56, .5)']) {
      expressions.push({file, css: match[0].replace(match[1], color)});
    }
  }
}
(async () => {
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage();
    const results = await page.evaluate(rows => rows.map(row => ({...row,
      color: CSS.supports('color', row.css),
      gradient: CSS.supports('background-image', `linear-gradient(135deg, navy, ${row.css})`),
      shadow: CSS.supports('box-shadow', `0 6px 20px ${row.css}`),
    })), expressions);
    assert(results.length > 100, 'Expected broad renderer coverage');
    for (const row of results) assert(row.color && row.gradient && row.shadow, JSON.stringify(row));
    console.log(`PASS: ${results.length} renderer opacity expressions across five color formats; color, gradient and shadow syntax valid.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
