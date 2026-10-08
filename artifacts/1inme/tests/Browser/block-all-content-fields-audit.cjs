const fs = require('fs');
const path = require('path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
(async () => {
  const source = fs.readFileSync(path.join(__dirname, '../../resources/views/user/links/partials/block-settings-form.blade.php'), 'utf8');
  const script = source.match(/<script>\s*(function blockAllContentValues[\s\S]+?)<\/script>/)[1];
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    await page.setContent('<form><div class="block-settings-form"><input name="settings[title]" value="Unsaved title"><input name="settings[items][0][name]" value="Updated row"><input name="settings[items][0][taken]" value="0"><input name="settings[items][0][description]" value="New detail"><input name="settings[constructor][prototype][polluted]" value="yes"></div><input name="style[text_color]" value="#ffffff"></form>');
    await page.addScriptTag({ content: script });
    const result = await page.evaluate(() => blockAllContentValues(document.querySelector('.block-settings-form'), { title: 'Saved', items: [{ name: 'Saved row', taken: false, description: 'Saved detail' }], accent_color: '#123456' }));
    assert.equal(result.title, 'Unsaved title');
    assert.deepEqual(result.items, [{ name: 'Updated row', taken: false, description: 'New detail' }]);
    assert.equal(result.accent_color, '#123456');
    assert.equal(Object.hasOwn(result, 'constructor'), false);
    await page.setContent('<form><div class="block-settings-form"><input name="settings[items]" value=""></div></form>');
    assert.deepEqual(await page.evaluate(() => blockAllContentValues(document.querySelector('.block-settings-form'), { items: [{ name: 'Saved' }] })), { items: [] });
    console.log('PASS: opening all fields preserves unsaved edits, row data, unchecked flags and omitted colors; empty lists stay empty.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
