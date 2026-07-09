/**
 * content-dial.ts — opt-in click-to-dial overlay.
 *
 * When the user enables dial detection the background service worker
 * dynamically registers this content script. It scans the DOM for
 * `<a href="tel:…">` elements **and** bare phone-number text matching
 * E.164 / common formats, then adds a lightweight "📞 Dial via Sayzio"
 * affordance next to each one.
 *
 * The content script:
 *  1. Reads only plain `tel:` links and text patterns — zero network.
 *  2. Sends a `DIAL_LOOKUP` message to the background only when the
 *     user explicitly clicks the affordance (lazy, not on page load).
 *  3. Re-scans after navigation events so SPAs are covered.
 *
 * Security: the script never reads arbitrary page content and never
 * sends the full DOM anywhere.
 */

const PHONE_RE = /(?<!\d)(\+?1?\s?[-.(]?\d{3}[-.\s)]?\s?\d{3}[-.\s]?\d{4})(?!\d)/g;
const PROCESSED_ATTR = "data-1inme-dial";
const STYLE_ID = "1inme-dial-styles";

function ensureStyles() {
  if (document.getElementById(STYLE_ID)) return;
  const s = document.createElement("style");
  s.id = STYLE_ID;
  s.textContent = `
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
    .inme-dial-popup-close {
      position: absolute; top: 8px; right: 10px;
      cursor: pointer; opacity: .5; font-size: 15px; background: none; border: none;
      color: inherit; line-height: 1;
    }
  `;
  document.head.appendChild(s);
}

function cleanNumber(raw: string): string {
  return raw.replace(/[^\d+]/g, "");
}

function e164ish(raw: string): string {
  const d = cleanNumber(raw);
  if (d.startsWith("+")) return d;
  if (d.length === 10) return `+1${d}`;
  if (d.length === 11 && d.startsWith("1")) return `+${d}`;
  return `+${d}`;
}

let popup: HTMLElement | null = null;

function closePopup() {
  popup?.remove();
  popup = null;
}

async function openDialPopup(number: string, anchor: HTMLElement) {
  closePopup();
  const e164 = e164ish(number);
  const el = document.createElement("div");
  el.className = "inme-dial-popup";
  el.style.cssText = `top:0;left:0;opacity:0`;
  el.innerHTML = `
    <button class="inme-dial-popup-close" title="Close">✕</button>
    <div class="inme-dial-popup-title">📞 Sayzio Dialer</div>
    <div class="inme-dial-popup-name" id="inme-cn">…</div>
    <div class="inme-dial-popup-num">${e164}</div>
    <div class="inme-dial-popup-row">
      <a href="tel:${e164}" title="Call">📞 Call</a>
      <a href="sms:${e164}" title="SMS">💬 SMS</a>
      <a href="https://wa.me/${e164.replace("+", "")}" target="_blank" rel="noreferrer" title="WhatsApp">WhatsApp</a>
    </div>
  `;
  document.body.appendChild(el);
  popup = el;

  const rect = anchor.getBoundingClientRect();
  const top = Math.min(rect.bottom + window.scrollY + 4, window.scrollY + window.innerHeight - 160);
  const left = Math.min(rect.left + window.scrollX, window.scrollX + window.innerWidth - 310);
  el.style.cssText = `position:absolute;top:${top}px;left:${left}px`;

  (el.querySelector(".inme-dial-popup-close") as HTMLElement)?.addEventListener("click", closePopup);

  try {
    const resp = await chrome.runtime.sendMessage({ type: "DIAL_LOOKUP", number: e164 });
    if (resp?.ok && resp.data?.contact?.name) {
      const nameEl = el.querySelector("#inme-cn");
      if (nameEl) nameEl.textContent = resp.data.contact.name;
    } else {
      const nameEl = el.querySelector("#inme-cn");
      if (nameEl) nameEl.textContent = "Unknown caller";
    }
  } catch {
    const nameEl = el.querySelector("#inme-cn");
    if (nameEl) nameEl.textContent = "Unknown caller";
  }
}

document.addEventListener("click", (e) => {
  if (!(e.target as HTMLElement).closest(".inme-dial-popup")) closePopup();
}, true);

function addDialButton(number: string): HTMLElement {
  const btn = document.createElement("a");
  btn.className = "inme-dial-btn";
  btn.setAttribute("role", "button");
  btn.textContent = "📞";
  btn.title = `Dial ${number} via Sayzio`;
  btn.addEventListener("click", (e) => {
    e.preventDefault();
    e.stopPropagation();
    openDialPopup(number, btn);
  });
  return btn;
}

function processTelLinks() {
  const links = document.querySelectorAll<HTMLAnchorElement>(`a[href^="tel:"]:not([${PROCESSED_ATTR}])`);
  for (const a of Array.from(links)) {
    const number = a.href.replace("tel:", "").trim();
    if (!number || number.length < 7) continue;
    a.setAttribute(PROCESSED_ATTR, "1");
    a.insertAdjacentElement("afterend", addDialButton(number));
  }
}

function processTextNodes() {
  const walker = document.createTreeWalker(
    document.body,
    NodeFilter.SHOW_TEXT,
    {
      acceptNode(node) {
        const p = node.parentElement;
        if (!p) return NodeFilter.FILTER_REJECT;
        const tag = p.tagName?.toLowerCase() ?? "";
        if (["script", "style", "textarea", "input", "code", "pre"].includes(tag)) return NodeFilter.FILTER_REJECT;
        if (p.getAttribute(PROCESSED_ATTR)) return NodeFilter.FILTER_REJECT;
        if (p.closest(`[${PROCESSED_ATTR}]`)) return NodeFilter.FILTER_REJECT;
        if (PHONE_RE.test(node.textContent || "")) return NodeFilter.FILTER_ACCEPT;
        return NodeFilter.FILTER_REJECT;
      },
    },
  );

  const nodes: Text[] = [];
  let n: Text | null;
  while ((n = walker.nextNode() as Text | null)) nodes.push(n);

  for (const textNode of nodes) {
    const text = textNode.textContent || "";
    PHONE_RE.lastIndex = 0;
    const parts: (string | HTMLElement)[] = [];
    let last = 0;
    let m: RegExpExecArray | null;
    while ((m = PHONE_RE.exec(text)) !== null) {
      if (m.index > last) parts.push(text.slice(last, m.index));
      parts.push(m[1]);
      last = m.index + m[0].length;
    }
    if (parts.length === 0) continue;
    if (last < text.length) parts.push(text.slice(last));

    const parent = textNode.parentElement;
    if (!parent) continue;
    parent.setAttribute(PROCESSED_ATTR, "1");
    const frag = document.createDocumentFragment();
    for (const part of parts) {
      if (typeof part === "string") {
        frag.appendChild(document.createTextNode(part));
      } else {
        frag.appendChild(part);
      }
    }
    textNode.replaceWith(frag);
  }
}

function scan() {
  ensureStyles();
  PHONE_RE.lastIndex = 0;
  processTelLinks();
  processTextNodes();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", scan);
} else {
  scan();
}

const obs = new MutationObserver(() => {
  PHONE_RE.lastIndex = 0;
  processTelLinks();
});
obs.observe(document.body, { childList: true, subtree: true });
