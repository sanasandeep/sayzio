import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

const raw = fs.readFileSync(fileURLToPath(new URL('../../resources/views/common/partials/biolink-block-live-listener.blade.php', import.meta.url)), 'utf8');
const listener = raw.slice(raw.indexOf('<script>') + 8, raw.lastIndexOf('</script>'))
  .replace(/var BG_PRESET_CSS = .*;/, 'var BG_PRESET_CSS = {};');

test('live label edits preserve decorations and alignment targets the text wrapper', async ({ page }) => {
  await page.route('https://preview.test/**', route => route.fulfill({ contentType: 'text/html', body: '<html><body></body></html>' }));
  await page.goto('https://preview.test/?_preview=1');
  await page.setContent(`<div data-block-id="1" data-block-type="link"><a href="#"><span data-link-label>Old title</span><span aria-hidden="true">↗</span><span data-link-description>Old description</span><span data-number>01</span></a></div><div data-block-id="2" data-block-type="paragraph"><div class="block-styled"><div data-tilt-wrap class="text-center"><p>Text</p></div></div></div>`);
  await page.addScriptTag({ content: listener });
  await page.evaluate(() => {
    window.postMessage({ type: '1inme-block-live', blockId: 1, blockType: 'link', changed: ['settings[text]', 'settings[description]'], fields: { 'settings[text]': 'New title', 'settings[description]': 'New description' } }, location.origin);
    window.postMessage({ type: '1inme-block-live', blockId: 2, blockType: 'paragraph', changed: ['settings[align]'], fields: { 'settings[align]': 'right' } }, location.origin);
  });
  await expect(page.locator('[data-link-label]')).toHaveText('New title');
  await expect(page.locator('[data-link-description]')).toHaveText('New description');
  await expect(page.locator('[aria-hidden]')).toHaveText('↗');
  await expect(page.locator('[data-number]')).toHaveText('01');
  await expect(page.locator('[data-tilt-wrap]')).toHaveClass('text-right');
});
