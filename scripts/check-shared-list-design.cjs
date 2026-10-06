const {chromium}=require('playwright'),fs=require('fs'),assert=require('assert');
(async()=>{
 const browser=await chromium.launch({headless:true});const page=await browser.newPage();
 const script=fs.readFileSync('artifacts/1inme/public/js/list-design.js','utf8');
 const css=fs.readFileSync('artifacts/1inme/resources/views/user/partials/list-design.blade.php','utf8').match(/<style>([\s\S]*?)<\/style>/)[1];
 const html=`<style>:root{--bg-card:white;--bg-glass-hover:#f7f7f8;--border-soft:#ddd;--text-primary:#20202b;--text-secondary:#555;--text-muted:#777;--text-faint:#888;--accent:#3d6bff;--accent-light:#7c3aed}*{box-sizing:border-box}body{margin:16px}button,input,select{font:inherit} ${css}</style><span hidden data-list-history-scope="1.2"></span><form data-list-filters method="get"><input name="q" value="Menu"><input type="hidden" name="context" value="orders"><label>Status<select name="status"><option value="">All</option><option value="active" selected>Active</option></select></label><label>From<input type="date" name="from" value="2026-10-01"></label><button type="submit">Apply</button></form><script>${script}</script>`;
 await page.route('https://fixture.test/**',r=>r.fulfill({body:html,contentType:'text/html'}));
 await page.goto('https://fixture.test/list?q=Menu&status=active&from=2026-10-01&page=3');
 assert.equal(await page.locator('.app-search-bar input[name=q]').count(),1);
 assert.equal(await page.locator('.app-filter-panel input[name=q]').count(),0);
 assert.equal(await page.locator('input[name=context]').inputValue(),'orders');
 await page.locator('.app-filter-toggle').click();assert(await page.locator('.app-filter-panel').isVisible());
 const href=await page.locator('.app-filter-tag').first().getAttribute('href');assert(!new URL(href).searchParams.has('status'));assert.equal(new URL(href).searchParams.get('from'),'2026-10-01');assert(!new URL(href).searchParams.has('page'));
 await page.locator('input[name=q]').focus();assert(await page.locator('.app-search-recent').isVisible());
 await page.locator('.app-recent-remove').click();assert.equal(await page.locator('.app-recent-row').count(),0);
 await page.locator('input[name=q]').fill('Fresh');await page.locator('.app-filter-submit').click();await page.waitForURL('**q=Fresh**');assert.equal(new URL(page.url()).searchParams.get('context'),'orders');assert.equal(new URL(page.url()).searchParams.get('status'),'active');
 for(const width of [320,375,768,1440]){await page.setViewportSize({width,height:900});await page.locator('.app-filter-toggle').click();assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));if(width===375)await page.screenshot({path:'/private/tmp/shared-filters-mobile.png'});await page.locator('.app-filter-toggle').click()}
 console.log('Browser checks passed: preserved fields, search placement, tag removal, page reset, recent removal, GET submission and no overflow at 320/375/768/1440px.');await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
