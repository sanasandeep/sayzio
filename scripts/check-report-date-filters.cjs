const { chromium } = require('playwright');
const fs = require('fs');
const assert = require('assert');
(async () => {
    const browser = await chromium.launch({headless:true});
    const page = await browser.newPage();
    const script = fs.readFileSync('artifacts/1inme/public/js/list-design.js', 'utf8');
    const css = fs.readFileSync('artifacts/1inme/resources/views/user/partials/list-design.blade.php', 'utf8').match(/<style>([\s\S]*?)<\/style>/)[1];
    const html = `<style>*{box-sizing:border-box}body{margin:16px} :root{--bg-card:white;--border-soft:#ddd;--text-muted:#555;--text-primary:#222} ${css}</style><form data-date-filters data-list-filters method=get><input type=hidden name=range value=custom><label>From date<input type=date name=from value=2020-01-02></label><label>To date<input type=date name=to value=2020-01-04></label><button>Apply dates</button><a href="/export?range=custom&from=2020-01-02&to=2020-01-04">CSV</a></form><script>${script}</script>`;
    await page.route('https://fixture.test/**', r => r.fulfill({body:html,contentType:'text/html'}));
    await page.goto('https://fixture.test/stats');
    assert.equal(await page.locator('.app-filter-panel').count(), 0);
    for (const width of [320,375,768,1440]) {
        await page.setViewportSize({width,height:900});
        assert(await page.getByLabel('From date').isVisible());
        assert(await page.getByLabel('To date').isVisible());
        assert(await page.getByText('CSV', {exact:true}).isVisible());
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    }
    await page.getByRole('button', {name:'Apply dates'}).click();
    await page.waitForURL('**range=custom**');
    const params = new URL(page.url()).searchParams;
    assert.equal(params.get('from'), '2020-01-02');
    assert.equal(params.get('to'), '2020-01-04');
    console.log('Report controls remain visible at 320/375/768/1440px; date submission and export query preserved.');
    await browser.close();
})().catch(e => {console.error(e);process.exit(1)});
