// Standalone browser regression audit: node tests/Browser/biolink-runtime-audit.cjs
// No server, credentials, database writes or third-party submissions.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const views = path.resolve(__dirname, '../../resources/views/common/partials');
const motion = fs.readFileSync(path.join(views, 'block-element-motion.blade.php'), 'utf8');
const rawRuntime = fs.readFileSync(path.join(views, 'biolink-interactive-runtime.blade.php'), 'utf8');
const runtime = rawRuntime.slice(rawRuntime.indexOf('<script>') + 8, rawRuntime.lastIndexOf('</script>'));
const designs = ['editorial','quote','marker','outline','wobble','split_words','split_chars','bracket','ruled','vertical','letterpress','double_rule','word_wave','spring','blur_reveal','word_flip','fire','glow','liquid_flow','laser_flow'];
(async () => {
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('https://preview.test/**', route => route.fulfill({contentType:'text/html',body:'<!doctype html><html><body></body></html>'}));
    for (const width of [320,375,768]) {
      await page.setViewportSize({width, height:900});
      await page.goto('https://preview.test/audit');
      await page.setContent(`<style>body{margin:0}section{box-sizing:border-box;padding:10px;width:100%;overflow-wrap:anywhere}p{margin:0}</style>` +
        ['#172033','#ffffff'].flatMap(color => designs.map(design => `<section data-text-design="${design}" style="color:${color};background:${color==='#ffffff'?'#101020':'#ffffff'}"><p>Make something memorable. <a href="#">Explore my work</a></p></section>`)).join('') + motion);
      await page.waitForFunction(() => document.querySelector('[data-text-design="wobble"]').dataset.textPrepared === '1');
      const rows = await page.locator('section').evaluateAll(es => es.map(e => ({design:e.dataset.textDesign,text:e.textContent,overflow:e.scrollWidth>e.clientWidth+1,wrappedLink:!!e.querySelector('a .sz-text-piece'),pieces:e.querySelectorAll('.sz-text-piece').length})));
      assert.equal(rows.length,40);
      for (const row of rows) {
        assert.equal(row.text,'Make something memorable. Explore my work',row.design);
        assert.equal(row.overflow,false,`${row.design} overflows at ${width}px`);
        assert.equal(row.wrappedLink,false,`${row.design} rewrites links`);
      }
      await page.emulateMedia({reducedMotion:'reduce'});
      assert(await page.locator('.sz-text-piece').evaluateAll(es => es.every(e => getComputedStyle(e).animationName==='none' && getComputedStyle(e).opacity==='1')));
      await page.emulateMedia({reducedMotion:'no-preference'});
    }
    await page.addScriptTag({content:runtime});
    await page.route('**/api/v1/biolinks/**', async route => {
      if (route.request().url().endsWith('poll-vote')) return route.fulfill({status:200,json:{success:true}});
      return route.fulfill({status:200,json:{data:{total:1,options:[{label:'Design',votes:1}]}}});
    });
    await page.evaluate(() => { window.poll=biolinkPoll({alias:'audit',blockId:1,options:['Design']}); window.poll.init(); });
    await page.evaluate(() => window.poll.vote(0,'Design'));
    assert.deepEqual(await page.evaluate(() => ({voted:window.poll.voted,total:window.poll.results.total,submitting:window.poll.submitting,error:window.poll.error})),{voted:0,total:1,submitting:null,error:''});
    await page.unroute('**/api/v1/biolinks/**');
    await page.route('**/api/v1/biolinks/**', route => route.fulfill({status:422,json:{success:false}}));
    await page.evaluate(() => { window.poll=biolinkPoll({alias:'audit',blockId:1,options:['Design']}); });
    await page.evaluate(() => window.poll.vote(0,'Design'));
    assert.deepEqual(await page.evaluate(() => ({voted:window.poll.voted,submitting:window.poll.submitting,error:!!window.poll.error})),{voted:null,submitting:null,error:true});
    await page.evaluate(() => { window.poll=biolinkPoll({alias:'audit',blockId:1,revealAt:'2999-01-01T00:00:00Z'}); window.poll.init(); });
    assert(await page.evaluate(() => window.poll.resultsLocked && !!window.poll.revealAtDisplay));
    assert.deepEqual(errors,[]);
    console.log('PASS: 20 text designs × 2 backgrounds × 3 widths; text preservation, wrapping, reduced motion; poll success, rejection and result deadline; no JavaScript errors.');
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode=1; });
