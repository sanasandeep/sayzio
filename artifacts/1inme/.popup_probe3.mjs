import { chromium } from '@playwright/test';
const b = await chromium.launch();
for (const [name, url, vp] of [
  ['proxy80-1024', 'http://localhost:80/', {width:1024, height:640}],
  ['proxy80-1280', 'http://localhost:80/', {width:1280, height:720}],
]) {
  const p = await b.newPage({ viewport: vp });
  const errs = [];
  p.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0,200)); });
  p.on('pageerror', e => errs.push('PAGEERROR: ' + String(e).slice(0,300)));
  const failed = [];
  p.on('requestfailed', r => failed.push(r.url().slice(0,120) + ' :: ' + (r.failure()?.errorText||'')));
  try {
    await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await p.waitForTimeout(3500);
    const res = await p.evaluate(async () => {
      const sleep = ms => new Promise(r => setTimeout(r, ms));
      const out = { alpine: typeof window.Alpine, cloak: document.querySelectorAll('[x-cloak]').length };
      const loginBtn = [...document.querySelectorAll('button')].find(el => /^\s*login\s*$/i.test(el.textContent||'') && el.offsetParent);
      if (loginBtn) { loginBtn.click(); await sleep(600);
        const modal = document.querySelector('[x-show="authOpen"]');
        out.login = modal ? 'display=' + getComputedStyle(modal).display : 'no authOpen el';
      } else out.login = 'no visible login button';
      return out;
    });
    console.log(name, JSON.stringify({ res, errs: errs.slice(0,8), failed: failed.slice(0,8) }));
  } catch (e) { console.log(name, 'NAV FAIL', String(e).slice(0,200)); }
  await p.close();
}
await b.close();
