const p=/(?<!\d)(\+?1?\s?[-.(]?\d{3}[-.\s)]?\s?\d{3}[-.\s]?\d{4})(?!\d)/g,c="data-1inme-dial",x="1inme-dial-styles";function h(){if(document.getElementById(x))return;const n=document.createElement("style");n.id=x,n.textContent=`
    .inme-dial-btn {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      margin-left: 4px;
      padding: 1px 6px;
      border-radius: 10px;
      border: 1px solid rgba(59,130,246,0.5);
      background: rgba(59,130,246,0.08);
      color: #3b82f6;
      font-size: 11px;
      font-family: system-ui, sans-serif;
      cursor: pointer;
      text-decoration: none;
      white-space: nowrap;
      vertical-align: middle;
      line-height: 1.6;
      transition: background .15s;
    }
    .inme-dial-btn:hover { background: rgba(59,130,246,0.18); }
    .inme-dial-popup {
      position: fixed;
      z-index: 2147483647;
      min-width: 240px;
      max-width: 300px;
      border-radius: 10px;
      border: 1px solid rgba(255,255,255,.1);
      background: #1e1e2e;
      color: #e2e8f0;
      font-size: 13px;
      font-family: system-ui, sans-serif;
      padding: 12px 14px;
      box-shadow: 0 8px 32px rgba(0,0,0,.45);
      pointer-events: auto;
    }
    .inme-dial-popup-title { font-weight: 600; margin-bottom: 8px; font-size: 12px; opacity: .7; }
    .inme-dial-popup-name { font-size: 15px; font-weight: 700; margin-bottom: 2px; }
    .inme-dial-popup-num  { font-size: 12px; opacity: .65; margin-bottom: 10px; }
    .inme-dial-popup-row  { display: flex; gap: 6px; flex-wrap: wrap; }
    .inme-dial-popup-row a {
      flex: 1;
      min-width: 80px;
      text-align: center;
      padding: 5px 8px;
      border-radius: 6px;
      border: 1px solid rgba(255,255,255,.12);
      color: #e2e8f0;
      text-decoration: none;
      font-size: 12px;
      cursor: pointer;
    }
    .inme-dial-popup-row a:hover { background: rgba(255,255,255,.08); }
    .inme-dial-popup-save {
      display: block;
      width: 100%;
      margin-top: 8px;
      padding: 5px 8px;
      border-radius: 6px;
      border: 1px solid rgba(59,130,246,0.5);
      background: rgba(59,130,246,0.12);
      color: #93c5fd;
      font-size: 12px;
      font-family: inherit;
      cursor: pointer;
      text-align: center;
    }
    .inme-dial-popup-save:hover { background: rgba(59,130,246,0.22); }
    .inme-dial-popup-save[disabled] { opacity: .5; cursor: default; }
    .inme-dial-popup-close {
      position: absolute; top: 8px; right: 10px;
      cursor: pointer; opacity: .5; font-size: 15px; background: none; border: none;
      color: inherit; line-height: 1;
    }
  `,document.head.appendChild(n)}function y(n){return n.replace(/[^\d+]/g,"")}function E(n){const e=y(n);return e.startsWith("+")?e:e.length===10?`+1${e}`:e.length===11&&e.startsWith("1")?`+${e}`:`+${e}`}let m=null;function u(){m?.remove(),m=null}async function w(n,e){u();const i=E(n),o=document.createElement("div");o.className="inme-dial-popup",o.style.cssText="top:0;left:0;opacity:0",o.innerHTML=`
    <button class="inme-dial-popup-close" title="Close">✕</button>
    <div class="inme-dial-popup-title">📞 Zio Dialer</div>
    <div class="inme-dial-popup-name" id="inme-cn">…</div>
    <div class="inme-dial-popup-num">${i}</div>
    <div class="inme-dial-popup-row">
      <a href="tel:${i}" title="Call">📞 Call</a>
      <a href="sms:${i}" title="SMS">💬 SMS</a>
      <a href="https://wa.me/${i.replace("+","")}" target="_blank" rel="noreferrer" title="WhatsApp">WhatsApp</a>
    </div>
  `,document.body.appendChild(o),m=o;const a=e.getBoundingClientRect(),l=Math.min(a.bottom+window.scrollY+4,window.scrollY+window.innerHeight-160),d=Math.min(a.left+window.scrollX,window.scrollX+window.innerWidth-310);o.style.cssText=`position:absolute;top:${l}px;left:${d}px`,o.querySelector(".inme-dial-popup-close")?.addEventListener("click",u);let s=!1;try{const t=await chrome.runtime.sendMessage({type:"DIAL_LOOKUP",number:i}),r=o.querySelector("#inme-cn");t?.ok&&t.data?.contact?.name?(s=!0,r&&(r.textContent=t.data.contact.name)):r&&(r.textContent="Unknown caller")}catch{const t=o.querySelector("#inme-cn");t&&(t.textContent="Unknown caller")}if(!s&&m===o){const t=document.createElement("button");t.type="button",t.className="inme-dial-popup-save",t.textContent="💾 Save contact in Sayzio",t.addEventListener("click",async()=>{t.disabled=!0,t.textContent="Opening Sayzio…";try{const r=await chrome.runtime.sendMessage({type:"DIAL_SAVE_CONTACT",number:i});r?.ok?u():(t.disabled=!1,t.textContent=r?.error==="Not signed in"?"Sign in to Sayzio first":"💾 Save contact in Sayzio")}catch{t.disabled=!1,t.textContent="💾 Save contact in Sayzio"}}),o.appendChild(t)}}document.addEventListener("click",n=>{n.target.closest(".inme-dial-popup")||u()},!0);function v(n){const e=document.createElement("a");return e.className="inme-dial-btn",e.setAttribute("role","button"),e.textContent="📞",e.title=`Dial ${n} via Sayzio`,e.addEventListener("click",i=>{i.preventDefault(),i.stopPropagation(),w(n,e)}),e}function b(){const n=document.querySelectorAll(`a[href^="tel:"]:not([${c}])`);for(const e of Array.from(n)){const i=e.href.replace("tel:","").trim();!i||i.length<7||(e.setAttribute(c,"1"),e.insertAdjacentElement("afterend",v(i)))}}function C(){const n=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT,{acceptNode(o){const a=o.parentElement;if(!a)return NodeFilter.FILTER_REJECT;const l=a.tagName?.toLowerCase()??"";return["script","style","textarea","input","code","pre"].includes(l)||a.getAttribute(c)||a.closest(`[${c}]`)?NodeFilter.FILTER_REJECT:p.test(o.textContent||"")?NodeFilter.FILTER_ACCEPT:NodeFilter.FILTER_REJECT}}),e=[];let i;for(;i=n.nextNode();)e.push(i);for(const o of e){const a=o.textContent||"";p.lastIndex=0;const l=[];let d=0,s;for(;(s=p.exec(a))!==null;)s.index>d&&l.push(a.slice(d,s.index)),l.push(s[1]),d=s.index+s[0].length;if(l.length===0)continue;d<a.length&&l.push(a.slice(d));const t=o.parentElement;if(!t)continue;t.setAttribute(c,"1");const r=document.createDocumentFragment();for(const f of l)typeof f=="string"?r.appendChild(document.createTextNode(f)):r.appendChild(f);o.replaceWith(r)}}function g(){h(),p.lastIndex=0,b(),C()}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",g):g();const T=new MutationObserver(()=>{p.lastIndex=0,b()});T.observe(document.body,{childList:!0,subtree:!0});
