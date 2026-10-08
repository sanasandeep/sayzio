const fs = require('fs');
const path = require('path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
(async () => {
  const source = fs.readFileSync(path.join(__dirname, '../../resources/views/common/partials/biolink-block-render.blade.php'), 'utf8');
  const rule = source.match(/\.block-inner-surface \.glass-block \{[^}]+\}/)[0];
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    for (const background of ['#123', 'rgb(12, 34, 56)', 'navy', 'transparent', 'linear-gradient(90deg, red, blue)']) {
      await page.setContent(`<style>.glass-block{background:white}${rule}</style><div class="block-inner-surface" style="display:contents;--block-inner-surface:${background}"><div id="panel" class="glass-block" style="background:white">Content</div></div><div id="default" class="glass-block">Default</div><div id="expected" style="background:${background}"></div>`);
      const colors = await page.evaluate(() => {
        const read = id => { const c = getComputedStyle(document.getElementById(id)); return [c.backgroundColor, c.backgroundImage]; };
        return { panel: read('panel'), expected: read('expected'), legacy: read('default') };
      });
      assert.deepEqual(colors.panel, colors.expected, background);
      assert.equal(colors.legacy[0], 'rgb(255, 255, 255)', 'Unedited surfaces retain their defaults');
    }
    console.log('PASS: inner panels honor five background formats, including transparent and gradients; unedited blocks retain defaults.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
