import { chromium } from 'playwright';
const BASE='http://localhost:80';
const b=await chromium.launch({args:['--no-sandbox']});
const pg=await (await b.newContext({viewport:{width:1440,height:900}})).newPage();
await pg.goto(BASE+'/user/login',{waitUntil:'domcontentloaded'});
const token=await pg.getAttribute('input[name="_token"]','value');
await pg.evaluate(([t,base])=>{const f=document.createElement('form');f.method='POST';f.action=base+'/user/demo-login';const i=document.createElement('input');i.name='_token';i.value=t;f.appendChild(i);document.body.appendChild(f);f.submit();},[token,BASE]);
await pg.waitForLoadState('networkidle');
await pg.goto(BASE+'/user/links/7385/edit#blocks',{waitUntil:'domcontentloaded'});
await pg.waitForTimeout(4000);
const info=await pg.evaluate(()=>{
  const out={url:location.pathname+location.hash,vw:innerWidth};
  const c=document.getElementById('editorLayout');
  if(c) out.cols=getComputedStyle(c).gridTemplateColumns;
  const el=document.querySelector('.device-preview-root')||document.getElementById('editorPreviewCol');
  if(el){const r=el.getBoundingClientRect();out.preview={x:Math.round(r.x),y:Math.round(r.y),w:Math.round(r.width),h:Math.round(r.height),display:getComputedStyle(el).display,pos:getComputedStyle(el).position};}
  return JSON.stringify(out);});
console.log(info);
await pg.screenshot({path:'/tmp/editor-blocks-1440.png'});
await b.close();
