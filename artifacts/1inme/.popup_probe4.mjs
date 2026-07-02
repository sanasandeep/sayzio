import { chromium } from '@playwright/test';
const b = await chromium.launch();
const p = await b.newPage({ viewport: { width: 1024, height: 640 } });
const errs = [];
p.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0,200)); });
p.on('pageerror', e => errs.push('PAGEERROR: ' + String(e).slice(0,300)));
await p.goto('http://localhost:80/', { waitUntil: 'domcontentloaded', timeout: 60000 });
await p.waitForTimeout(3500);
const res = await p.evaluate(async () => {
  const sleep = ms => new Promise(r => setTimeout(r, ms));
  const out = {};
  const badge = document.querySelector('button.store-badge');
  out.badgeFound = !!badge;
  if (!badge) return out;
  badge.scrollIntoView({ block: 'center' });
  await sleep(300);
  badge.click();
  await sleep(700);
  const modal = document.querySelector('[aria-label="Sayzio mobile app — coming soon"]');
  if (!modal) return { ...out, modal: 'NOT FOUND' };
  const ms = getComputedStyle(modal);
  out.modal = { display: ms.display, opacity: ms.opacity, zIndex: ms.zIndex, position: ms.position };
  const mr = modal.getBoundingClientRect();
  out.modalRect = { x: mr.x, y: mr.y, w: mr.width, h: mr.height };
  const card = modal.querySelector('.store-cs-card');
  if (card) {
    const cs = getComputedStyle(card);
    const cr = card.getBoundingClientRect();
    out.card = { display: cs.display, opacity: cs.opacity, visibility: cs.visibility, rect: {x:cr.x,y:cr.y,w:cr.width,h:cr.height} };
  }
  // walk ancestors for transform/filter/opacity/containment that breaks fixed
  const anc = [];
  let el = modal.parentElement;
  while (el && el !== document.body) {
    const s = getComputedStyle(el);
    if (s.transform !== 'none' || s.filter !== 'none' || s.backdropFilter && s.backdropFilter !== 'none' || parseFloat(s.opacity) < 1 || s.contain !== 'none' || s.willChange.includes('transform') || s.overflow !== 'visible') {
      anc.push({ tag: el.tagName + '.' + String(el.className).slice(0,60), transform: s.transform !== 'none', filter: s.filter !== 'none', backdropFilter: s.backdropFilter, opacity: s.opacity, overflow: s.overflow, contain: s.contain, dataAnim: el.hasAttribute('data-anim') });
    }
    el = el.parentElement;
  }
  out.suspectAncestors = anc.slice(0, 8);
  // what's clickable at center of viewport (where card should be)?
  const top = document.elementFromPoint(innerWidth/2, innerHeight/2);
  out.centerEl = top ? top.tagName + '.' + String(top.className).slice(0,80) : null;
  // close button hit test
  const close = modal.querySelector('.store-cs-close');
  if (close) { const r = close.getBoundingClientRect(); const t = document.elementFromPoint(r.x+r.width/2, r.y+r.height/2); out.closeHit = { rect:{x:r.x,y:r.y}, top: t ? t.tagName + '.' + String(t.className).slice(0,60) : null, isClose: t===close || close.contains(t) }; }
  out.parentChain = (() => { const c=[]; let e=modal.parentElement; while(e && c.length<5){ c.push(e.tagName + '.' + String(e.className).slice(0,50)); e=e.parentElement;} return c; })();
  return out;
});
console.log(JSON.stringify({ res, errs: errs.slice(0,6) }, null, 1));
await b.close();
