import { chromium } from '@playwright/test';
const b = await chromium.launch();
const p = await b.newPage();
const errs = [];
p.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0,200)); });
p.on('pageerror', e => errs.push('PAGEERROR: ' + String(e).slice(0,300)));
await p.goto('http://localhost:5000/', { waitUntil: 'domcontentloaded', timeout: 60000 });
await p.waitForTimeout(3000);
const alpine = await p.evaluate(() => ({
  alpine: typeof window.Alpine,
  cloak: document.querySelectorAll('[x-cloak]').length,
}));
// what's on top at the Login button position?
const info = await p.evaluate(() => {
  const btn = [...document.querySelectorAll('button, a')].find(el => /login/i.test(el.textContent||''));
  if (!btn) return { found: false };
  const r = btn.getBoundingClientRect();
  const top = document.elementFromPoint(r.x + r.width/2, r.y + r.height/2);
  return { found: true, rect: {x:r.x,y:r.y,w:r.width,h:r.height}, topEl: top ? top.tagName + '.' + (top.className||'').toString().slice(0,80) : null, isBtn: top === btn || (top && btn.contains(top)) };
});
// click login and see if auth modal shows
let clickRes = 'skipped';
try {
  const btn = p.locator('header button:has-text("Login"), header a:has-text("Login")').first();
  await btn.click({ timeout: 5000 });
  await p.waitForTimeout(600);
  clickRes = await p.evaluate(async () => {
    const modal = document.querySelector('[x-show="authOpen"]');
    if (!modal) return 'no authOpen element';
    return 'display=' + getComputedStyle(modal).display;
  });
} catch (e) { clickRes = 'CLICK FAILED: ' + String(e).slice(0,200); }
console.log(JSON.stringify({ alpine, info, clickRes, errs: errs.slice(0,10) }, null, 1));
await b.close();
