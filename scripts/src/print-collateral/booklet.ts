/**
 * Sayzio A3 saddle-stitch exhibition booklet (16 pages).
 *
 * Finished size: A4 portrait (210x297mm) per page, printed as 4 A3 landscape
 * sheets (420x297mm trim), 2 finished pages per side, folded + stapled on the
 * spine. 3mm bleed everywhere; all output is vector (text, QR, CSS artwork).
 *
 * Outputs (.local/print-out/booklet/):
 *  - sayzio-booklet-imposed-a3.pdf   8 pages (4 sheets front+back), imposition:
 *      sheet1 front=[16,1] back=[2,15]; sheet2 front=[14,3] back=[4,13];
 *      sheet3 front=[12,5] back=[6,11]; sheet4 front=[10,7] back=[8,9]
 *  - sayzio-booklet-reader.pdf       16 sequential A4 pages for proofing
 *  - README-booklet.md               print spec
 *
 * Run: pnpm --filter @workspace/scripts run print:booklet
 */
import { chromium, type Page } from "playwright";
import { mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";
import {
  BLEED,
  ASSET,
  qrSvg,
  QR_URLS,
  CONTACT,
  doc,
  wordmark,
  vis,
  art,
  artChat,
  artVoice,
  artBuilder,
  artBiolink,
  artDashboard,
  artQrTiles,
  artMap,
  artPayouts,
  artOrders,
  artInbox,
  artRestaurant,
  artDialer,
  artExtension,
  artForms,
  artWorkspace,
  artApi,
  artVcard,
} from "./generate.js";

const ROOT = path.resolve(import.meta.dirname, "../../..");
const OUT = path.join(ROOT, ".local/print-out/booklet");

// Finished page: A4 portrait. Bleed box per page: 216 x 303mm.
const PW = 210;
const PH = 297;
const BW = PW + 2 * BLEED; // 216
const BH = PH + 2 * BLEED; // 303
// Imposed sheet: A3 landscape 420x297 trim -> 426x303 incl. bleed.
const SW = 2 * PW + 2 * BLEED; // 426
const SH = BH; // 303
const HALF = SW / 2; // 213: fold line in bleed coordinates

// Safe margins measured from trim: generous inner (gutter) margin.
const M_OUT = 12; // outer edge
const M_IN = 18; // spine/gutter edge
const M_TOP = 14;
const M_BOT = 14;

/* ---------- booklet page CSS (A3 board CSS scaled to A4, ~0.68x) ---------- */
const BOOK_CSS = `
.k-kick{font-size:4.8mm;font-weight:700;letter-spacing:.22em;color:var(--sky);}
.k-h{font-size:16.5mm;font-weight:700;letter-spacing:-.025em;line-height:1.05;margin-top:5mm;}
.k-p{font-size:4.9mm;color:var(--muted);line-height:1.5;font-weight:500;margin-top:5.5mm;}
.k-li{display:flex;gap:4.5mm;align-items:flex-start;margin-top:5.5mm;}
.k-ic{flex:none;width:9.5mm;height:9.5mm;border-radius:3mm;display:flex;align-items:center;justify-content:center;
  font-size:4.6mm;background:linear-gradient(150deg,var(--blue),var(--blue-deep));}
.k-lt{font-size:5mm;font-weight:700;}
.k-ld{font-size:3.9mm;color:var(--muted);line-height:1.45;margin-top:1mm;}
.k-foot{margin-top:auto;display:flex;align-items:center;gap:7mm;}
.k-qr{width:38mm;height:38mm;flex:none;}
.k-cta{font-size:5.4mm;font-weight:700;line-height:1.25;}
.k-url{margin-top:2mm;font-size:4.1mm;color:var(--sky);font-weight:600;}
.k-stat{text-align:center;padding:5mm 2.5mm;}
.k-sv{font-size:10mm;font-weight:700;color:var(--blue-soft);}
.k-sl{font-size:3.2mm;color:var(--muted);margin-top:1mm;font-weight:500;}
.k-num{position:absolute;bottom:${BLEED + 6}mm;font-size:3.4mm;font-weight:600;color:var(--faint);}
.k-cap{font-size:3.2mm;}
`;

/* ---------- small layout helpers for richer pages ---------- */
const chips = (items: string[], size = 3.2) =>
  `<div style="display:flex;gap:2.8mm;flex-wrap:wrap;margin-top:5mm;">${items
    .map((t) => `<span class="chip" style="font-size:${size}mm;padding:1.3mm 3.4mm;">${t}</span>`)
    .join("")}</div>`;
const stats3 = (items: Array<[string, string]>) =>
  `<div style="display:flex;gap:4mm;margin-top:5.5mm;">${items
    .map(
      ([v, l]) =>
        `<div class="glass k-stat" style="flex:1;"><div class="k-sv">${v}</div><div class="k-sl">${l}</div></div>`
    )
    .join("")}</div>`;
const duo = (a: string, ca: string, b: string, cb: string) => `
    <div style="flex:1;min-height:0;display:flex;gap:4mm;margin-top:6mm;">
      <div style="flex:1;display:flex;flex-direction:column;gap:2.5mm;">${vis(a, "flex:1;")}<div class="img-cap k-cap">${ca}</div></div>
      <div style="flex:1;display:flex;flex-direction:column;gap:2.5mm;">${vis(b, "flex:1;")}<div class="img-cap k-cap">${cb}</div></div>
    </div>`;

/* Subscribers growth mockup (booklet-only). */
const artSubscribers = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="gcard" style="width:84%;padding:.85em 1em;">
    <div class="tag">SUBSCRIBERS</div>
    <div style="margin-top:.5em;font-size:1.05em;font-weight:700;">1,204 <span style="color:var(--mint);font-size:.6em;">&#9650; +38 this week</span></div>
    <div class="bars" style="height:2.6em;margin-top:.55em;">${[0.35, 0.5, 0.45, 0.7, 0.6, 0.85, 1]
      .map((h) => `<i style="height:${h * 100}%;"></i>`)
      .join("")}</div>
    <div style="margin-top:.55em;display:flex;gap:.4em;flex-wrap:wrap;">
      <span class="chip" style="font-size:.52em;">&#9993; Email 812</span>
      <span class="chip ok" style="font-size:.52em;">WhatsApp 392</span>
      <span class="chip" style="font-size:.52em;">&#11015; Export CSV</span>
    </div>
  </div>`
  );

/* Event ticket mockup (booklet-only). */
const artEvent = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="gcard" style="width:86%;padding:.85em 1em;">
    <div class="tag">EVENT</div>
    <div style="margin-top:.4em;font-size:.8em;font-weight:700;">Design Meetup &middot; Vol. 8</div>
    <div style="margin-top:.3em;font-size:.56em;color:var(--faint);font-weight:500;">Fri 20 Mar &middot; 6:30 PM &middot; Bengaluru</div>
    <div style="margin-top:.55em;display:flex;gap:.4em;flex-wrap:wrap;">
      <span class="chip" style="font-size:.5em;">&#128197; Add to calendar</span>
      <span class="chip ok" style="font-size:.5em;">&#127903; RSVP &middot; 214 going</span>
    </div>
    <div style="margin-top:.55em;padding:.45em;border-radius:.55em;text-align:center;font-size:.62em;font-weight:700;
      background:linear-gradient(150deg,var(--blue),var(--blue-deep));">Get tickets</div>
  </div>`
  );

/**
 * Wrap page content in the booklet page shell. `n` is the reader page number
 * (1-based). Odd pages are recto (gutter left); even pages verso (gutter
 * right). Page number sits toward the outer edge; none on covers.
 */
function pageShell(n: number, inner: string, opts: { chrome?: boolean } = {}): string {
  const recto = n % 2 === 1;
  const padL = BLEED + (recto ? M_IN : M_OUT);
  const padR = BLEED + (recto ? M_OUT : M_IN);
  const chrome = opts.chrome !== false;
  const num =
    chrome && n !== 1 && n !== 16
      ? `<div class="k-num" style="${recto ? `right:${BLEED + M_OUT}mm;` : `left:${BLEED + M_OUT}mm;`}">${String(n).padStart(2, "0")} · SAYZIO</div>`
      : "";
  return `
  <div class="page" style="padding:${BLEED + M_TOP}mm ${padR}mm ${BLEED + M_BOT}mm ${padL}mm;">
    <div class="grid-tex" style="background-size:14mm 14mm;"></div>
    <div style="position:relative;height:100%;display:flex;flex-direction:column;">
      ${chrome && n !== 1 && n !== 16 ? wordmark(8) : ""}
      ${inner}
    </div>
    ${num}
  </div>`;
}

/* ============================ the 16 pages ============================ */

async function p01Cover(): Promise<string> {
  const qr = await qrSvg(QR_URLS.home);
  return pageShell(
    1,
    `
    <div style="display:flex;align-items:center;justify-content:space-between;">
      ${wordmark(11)}
      <div class="pill" style="padding:1.6mm 5mm;font-size:3.2mm;">AI-FIRST LINK PLATFORM</div>
    </div>
    <div style="margin-top:14mm;">
      <div class="k-kick">EXHIBITION EDITION</div>
      <div style="font-size:22mm;font-weight:700;letter-spacing:-.025em;line-height:1.03;margin-top:5mm;">
        Your link,<br>now it <span class="accent">talks back.</span></div>
      <p class="k-p" style="max-width:150mm;">Biolinks, QR codes, stores, menus and analytics — with an AI concierge
        that chats, answers, sells and books for you, 24/7.</p>
      ${chips(["\uD83D\uDCAC AI Chat", "\uD83C\uDF99 AI Voice", "\u25A6 QR Studio", "\uD83D\uDECD Stores & menus", "\uD83D\uDCCA Live analytics"], 3.3)}
    </div>
    <div style="flex:1;min-height:0;display:flex;flex-direction:column;gap:3mm;margin-top:8mm;">
      ${vis(artChat(6.2), "flex:1;")}
      <div class="img-cap k-cap">A Sayzio page, mid-conversation with a visitor.</div>
    </div>
    <div class="k-foot" style="margin-top:7mm;">
      <div class="qr-card k-qr"><img src="${qr}" alt="QR: sayzio.app"></div>
      <div><div class="k-cta">Scan to meet<br>your new link.</div><div class="k-url">sayzio.app</div></div>
      <img src="${ASSET.mascot}" style="margin-left:auto;width:34mm;height:34mm;" alt="">
    </div>`,
    { chrome: false }
  );
}

async function p02Rebrand(): Promise<string> {
  return pageShell(
    2,
    `
    <div style="margin-top:8mm;" class="k-kick">THE STORY</div>
    <div class="k-h">1INME is now<br><span class="accent">Sayzio.</span></div>
    <p class="k-p">Same team, same links, a much bigger idea. 1INME grew from a link-in-bio tool into an
      AI-first platform where every link can talk, sell and answer for you. That deserved a new name.</p>
    ${chips(["Same handle", "Same links", "Same analytics", "New AI brain"])}
    <div class="k-li"><div class="k-ic">🔗</div><div><div class="k-lt">Every 1INME link still works</div><div class="k-ld">Old links, QR codes and pages redirect automatically. Nothing breaks.</div></div></div>
    <div class="k-li"><div class="k-ic">🤖</div><div><div class="k-lt">Upgraded to AI-first</div><div class="k-ld">Zio now builds pages, answers visitors and drafts replies for you.</div></div></div>
    <div class="k-li"><div class="k-ic">🛍</div><div><div class="k-lt">From links to business</div><div class="k-ld">Stores, menus, forms, invoices, bookings and payouts, all built in.</div></div></div>
    <div style="flex:1;min-height:0;display:flex;align-items:center;justify-content:center;margin-top:7mm;">
      <div class="glass" style="display:flex;flex-direction:column;align-items:center;gap:4.5mm;padding:9mm 18mm;">
        <div style="font-size:7mm;font-weight:600;color:var(--muted);letter-spacing:.08em;text-decoration:line-through;text-decoration-thickness:1mm;text-decoration-color:var(--sky);">1INME</div>
        <div style="font-size:4.6mm;color:var(--sky);">&#8595;</div>
        ${wordmark(9)}
        <div class="pill" style="padding:1.2mm 3.5mm;font-size:2.9mm;">NOW AI-FIRST</div>
      </div>
    </div>
    <div style="display:flex;gap:5mm;margin-top:7mm;">
      <div class="glass k-stat" style="flex:1;"><div class="k-sv">20+</div><div class="k-sl">link types on one handle</div></div>
      <div class="glass k-stat" style="flex:1;"><div class="k-sv">60s</div><div class="k-sl">from prompt to live page</div></div>
      <div class="glass k-stat" style="flex:1;"><div class="k-sv">0%</div><div class="k-sl">platform fee on earnings</div></div>
    </div>`
  );
}

async function p03WhatIs(): Promise<string> {
  return pageShell(
    3,
    `
    <div style="margin-top:8mm;" class="k-kick">WHAT IS SAYZIO</div>
    <div class="k-h">One handle runs<br>the whole <span class="accent">operation.</span></div>
    <p class="k-p">Sayzio is a link-management platform for creators, businesses and teams: build pages, brand
      links and QR codes, capture leads, take orders and watch it all in live analytics.</p>
    <div class="k-li"><div class="k-ic">🌐</div><div><div class="k-lt">Biolinks &amp; smart links</div><div class="k-ld">Mini-websites, short links, files, events, vCards and more — deeply customizable.</div></div></div>
    <div class="k-li"><div class="k-ic">💬</div><div><div class="k-lt">AI everywhere</div><div class="k-ld">Chat links, voice answers, an AI builder and an AI-drafted inbox.</div></div></div>
    <div class="k-li"><div class="k-ic">🛒</div><div><div class="k-lt">Commerce built in</div><div class="k-ld">Restaurant menus, storefronts, paid pages and creator payouts.</div></div></div>
    <div class="k-li"><div class="k-ic">📊</div><div><div class="k-lt">Analytics on everything</div><div class="k-ld">Every click, scan and order counted, mapped and exportable.</div></div></div>
    ${chips(["Web platform", "Mobile app", "Zio Dialer", "Browser extension", "REST API"], 3.1)}
    ${duo(
      artDashboard(3.3),
      "The dashboard: everything in one place.",
      artBiolink(3.3),
      "A biolink built in minutes."
    )}`
  );
}

async function p04AiChat(): Promise<string> {
  const qr = await qrSvg(QR_URLS.demoChat);
  return pageShell(
    4,
    `
    <div style="margin-top:8mm;" class="k-kick">AI CHAT &amp; VOICE</div>
    <div class="k-h">Talk to<br>this <span class="accent">page.</span></div>
    <p class="k-p">This QR opens a real Sayzio AI chat link. Ask it anything about Sayzio — pricing, features,
      how it works. It answers like a founder who never sleeps, and it can speak its answers out loud.</p>
    <div class="k-li"><div class="k-ic">1</div><div><div class="k-lt">Scan the code</div><div class="k-ld">No app, no signup. It opens in your browser.</div></div></div>
    <div class="k-li"><div class="k-ic">2</div><div><div class="k-lt">Ask a question</div><div class="k-ld">Type or talk. "What does Sayzio cost?" is a classic.</div></div></div>
    <div class="k-li"><div class="k-ic">🧠</div><div><div class="k-lt">Grounded, not guessing</div><div class="k-ld">Answers come from a knowledge base you control.</div></div></div>
    ${duo(
      artChat(3.1),
      "AI Chat: your page holds the conversation.",
      artVoice(3.1),
      "AI Voice: speech in, speech out."
    )}
    <div class="k-foot" style="margin-top:6mm;">
      <div class="qr-card k-qr"><img src="${qr}" alt="QR: live AI chat demo"></div>
      <div><div class="k-cta">Go on.<br>Say hi.</div><div class="k-url">sayzio.app</div></div>
    </div>`
  );
}

async function p05Builder(): Promise<string> {
  return pageShell(
    5,
    `
    <div style="margin-top:8mm;" class="k-kick">AI BUILDER</div>
    <div class="k-h">Prompt to page<br>in <span class="accent">60 seconds.</span></div>
    <p class="k-p">Type one sentence, like "a page for my bakery with a menu, hours and WhatsApp ordering",
      and Zio designs, writes and publishes the whole biolink.</p>
    <div class="glass" style="padding:6mm;margin-top:6mm;">
      <div style="font-size:3.8mm;color:var(--sky);font-weight:600;">You type:</div>
      <div style="font-size:4.4mm;font-weight:500;margin-top:1.8mm;line-height:1.4;">"Landing page for my SaaS beta: waitlist form, three features, dark theme."</div>
      <div style="font-size:3.8mm;color:var(--mint);font-weight:600;margin-top:4mm;">Zio ships:</div>
      <div style="font-size:4.4mm;font-weight:500;margin-top:1.8mm;line-height:1.4;">Hero + copy · feature blocks · working form · your brand colors · live URL.</div>
    </div>
    <div class="k-li"><div class="k-ic">✨</div><div><div class="k-lt">On-brand by default</div><div class="k-ld">Brand kit colors, fonts and tone applied automatically.</div></div></div>
    <div class="k-li"><div class="k-ic">🖼</div><div><div class="k-lt">Bring your assets</div><div class="k-ld">Add photos and links to the prompt and Zio places them for you.</div></div></div>
    ${stats3([["60s", "prompt to live page"], ["1", "sentence to start"], ["\u221E", "revisions by chat"]])}
    <div style="flex:1;min-height:0;display:flex;flex-direction:column;gap:3mm;margin-top:6mm;">
      ${vis(artBuilder(3.9), "flex:1;")}
      <div class="img-cap k-cap">One prompt in, one live page out.</div>
    </div>`
  );
}

async function p06Biolinks(): Promise<string> {
  return pageShell(
    6,
    `
    <div style="margin-top:8mm;" class="k-kick">BIOLINKS</div>
    <div class="k-h">Your page,<br>your <span class="accent">rules.</span></div>
    <p class="k-p">A drag-and-drop editor with per-block styling, global themes, display rules and device
      previews. Make it unmistakably yours, down to custom CSS.</p>
    <div class="k-li"><div class="k-ic">🎨</div><div><div class="k-lt">Style every block</div><div class="k-ld">11 style properties, 10 templates, image masks, borders and shadows.</div></div></div>
    <div class="k-li"><div class="k-ic">🗓</div><div><div class="k-lt">Smart display rules</div><div class="k-ld">Show blocks by schedule, location, device, language and more.</div></div></div>
    <div class="k-li"><div class="k-ic">🔍</div><div><div class="k-lt">SEO, OG &amp; PWA</div><div class="k-ld">Search-ready meta, share cards, install-to-home and custom branding.</div></div></div>
    <div class="k-li"><div class="k-ic">🌐</div><div><div class="k-lt">Custom domains</div><div class="k-ld">Serve every page from your own domain, verified in minutes.</div></div></div>
    ${chips(["10 image masks", "6 shadows", "Card containers", "Grid spans", "Custom CSS & JS", "PWA install"], 3.0)}
    <div style="flex:1;min-height:0;display:flex;flex-direction:column;gap:3mm;margin-top:6mm;">
      ${vis(artBiolink(4.6), "flex:1;")}
      <div class="img-cap k-cap">One biolink, endless layouts.</div>
    </div>`
  );
}

async function p07LinkTypes(): Promise<string> {
  const qr = await qrSvg(QR_URLS.demos);
  return pageShell(
    7,
    `
    <div style="margin-top:8mm;" class="k-kick">LINK TYPES</div>
    <div class="k-h">One handle.<br><span class="accent">20+ kinds</span> of link.</div>
    <p class="k-p">A Sayzio link can be a bio page, a menu, a store, a resume, an event, a paid page or a
      talking AI companion. Same handle, different superpower.</p>
    <div style="display:flex;gap:4mm;margin-top:6mm;flex-wrap:wrap;">
      ${["Biolink", "Short link", "QR", "Restaurant menu", "Store", "Resume", "Event", "vCard", "Calendar", "Paid page", "AI chat", "Forms", "File", "WiFi", "Reviews"]
        .map((t) => `<span class="chip" style="font-size:3.4mm;padding:1.4mm 3.6mm;">${t}</span>`)
        .join("")}
    </div>
    <div class="k-li"><div class="k-ic">🪪</div><div><div class="k-lt">Resume &amp; portfolio</div><div class="k-ld">A resume builder with AI tailoring, shared as a simple link.</div></div></div>
    <div class="k-li"><div class="k-ic">🔒</div><div><div class="k-lt">Paid &amp; gated pages</div><div class="k-ld">Content for followers, subscribers or paying fans only.</div></div></div>
    ${duo(
      artVcard(3.2),
      "A digital card, saved in one tap.",
      artEvent(3.2),
      "An event page with RSVPs and tickets."
    )}
    <div class="k-foot" style="margin-top:6mm;">
      <div class="qr-card k-qr"><img src="${qr}" alt="QR: demos"></div>
      <div><div class="k-cta">Browse live demos<br>of every type.</div><div class="k-url">sayzio.app/demos</div></div>
    </div>`
  );
}

async function p08QrStudio(): Promise<string> {
  const qr = await qrSvg(QR_URLS.home);
  return pageShell(
    8,
    `
    <div style="margin-top:8mm;" class="k-kick">QR STUDIO PRO</div>
    <div class="k-h">QR codes that<br>look like <span class="accent">art.</span></div>
    <p class="k-p">Stop printing black-and-white squares. Design QR codes people actually want to scan,
      and see exactly when and where every scan happens.</p>
    <div class="k-li"><div class="k-ic">🎨</div><div><div class="k-lt">30+ designer templates</div><div class="k-ld">Gradients, frames, per-corner eye styling and your logo in the middle.</div></div></div>
    <div class="k-li"><div class="k-ic">🖼</div><div><div class="k-lt">AI artistic QR</div><div class="k-ld">Turn a code into artwork that still scans, checked before you download.</div></div></div>
    <div class="k-li"><div class="k-ic">📦</div><div><div class="k-lt">Bulk generation</div><div class="k-ld">Upload a CSV, download a ZIP of finished codes for every row.</div></div></div>
    ${stats3([["30+", "designer templates"], ["16", "content types"], ["100%", "scan-checked"]])}
    <div style="flex:1;min-height:0;display:flex;flex-direction:column;gap:3mm;margin-top:6mm;">
      ${vis(artQrTiles(4.6, qr), "flex:1;")}
      <div class="img-cap k-cap">Every code on this booklet was made in Studio.</div>
    </div>`
  );
}

async function p09Commerce(): Promise<string> {
  const qr = await qrSvg(QR_URLS.demos);
  return pageShell(
    9,
    `
    <div style="margin-top:8mm;" class="k-kick">COMMERCE</div>
    <div class="k-h">A restaurant runs<br>on <span class="accent">one QR.</span></div>
    <p class="k-p">Menu, per-table ordering and a live kitchen dashboard. No app to install, no POS contract,
      no per-order commission. Store mode does the same for retail.</p>
    <div class="k-li"><div class="k-ic">🪑</div><div><div class="k-lt">Per-table QR codes</div><div class="k-ld">Orders arrive tagged with the table; bills stay itemized with GST and coupons.</div></div></div>
    <div class="k-li"><div class="k-ic">🔔</div><div><div class="k-lt">Live orders dashboard</div><div class="k-ld">Pending → preparing → served, updating in near real time.</div></div></div>
    <div class="k-li"><div class="k-ic">🏪</div><div><div class="k-lt">Store mode for retail</div><div class="k-ld">Products, order requests and WhatsApp handoff — one QR for the shop.</div></div></div>
    ${chips(["Per-table codes", "GST & coupons", "Itemized bills", "Pause orders", "WhatsApp handoff"], 3.0)}
    <div style="flex:1;min-height:0;display:flex;gap:4mm;margin-top:6mm;">
      <div style="flex:1;display:flex;flex-direction:column;gap:2.5mm;">
        ${vis(artRestaurant(3.6), "flex:1;")}
        <div class="img-cap k-cap">Guests order from the table.</div>
      </div>
      <div style="flex:1;display:flex;flex-direction:column;gap:2.5mm;">
        ${vis(artOrders(3.4), "flex:1;")}
        <div class="img-cap k-cap">The kitchen sees it live.</div>
      </div>
    </div>
    <div class="k-foot" style="margin-top:6mm;">
      <div class="qr-card k-qr"><img src="${qr}" alt="QR: demos"></div>
      <div><div class="k-cta">See the demo<br>menus live.</div><div class="k-url">sayzio.app/demos</div></div>
    </div>`
  );
}

async function p10Analytics(): Promise<string> {
  return pageShell(
    10,
    `
    <div style="margin-top:8mm;" class="k-kick">ANALYTICS</div>
    <div class="k-h">Every click<br><span class="accent">counted.</span></div>
    <p class="k-p">Live analytics with world heatmaps on every link, page and QR scan. Nine retargeting
      pixels per link, CSV exports, and per-city insight the moment it happens.</p>
    <div class="k-li"><div class="k-ic">🗺</div><div><div class="k-lt">Click heatmaps</div><div class="k-ld">Clicks and scans light up city by city on a live map.</div></div></div>
    <div class="k-li"><div class="k-ic">🎯</div><div><div class="k-lt">Retargeting pixels</div><div class="k-ld">Facebook, Google, TikTok, LinkedIn and more, per link.</div></div></div>
    <div class="k-li"><div class="k-ic">📤</div><div><div class="k-lt">Own your data</div><div class="k-ld">Exports and a full REST API for every metric.</div></div></div>
    ${chips(["Facebook", "Google", "GTM", "TikTok", "LinkedIn", "Pinterest", "Snapchat", "+2 more"], 2.9)}
    ${duo(
      artMap(3.3),
      "Clicks light up city by city, live.",
      artDashboard(3.1),
      "Totals, trends and top links."
    )}`
  );
}

async function p11Monetization(): Promise<string> {
  const qr = await qrSvg(QR_URLS.pricing);
  return pageShell(
    11,
    `
    <div style="margin-top:8mm;" class="k-kick">CREATOR ECONOMY</div>
    <div class="k-h"><span class="accent">0%</span> platform fee.<br>Your money is yours.</div>
    <p class="k-p">Paid pages, storefronts, paid forms and subscriptions — and when you sell, Sayzio takes
      nothing. Payouts land in your own accounts via hosted onboarding.</p>
    <div class="k-li"><div class="k-ic">💳</div><div><div class="k-lt">Payouts your way</div><div class="k-ld">Stripe, PayPal, Razorpay and more. Your account, your schedule.</div></div></div>
    <div class="k-li"><div class="k-ic">🔒</div><div><div class="k-lt">Gated content</div><div class="k-ld">Public, followers-only, subscribers-only or pay-to-view pages.</div></div></div>
    <div class="k-li"><div class="k-ic">🧾</div><div><div class="k-lt">Invoices built in</div><div class="k-ld">Client invoicing with PDFs, refunds and tax handled.</div></div></div>
    ${chips(["Stripe Connect", "PayPal", "Razorpay Route", "CCBill", "Segpay"], 3.0)}
    <div style="flex:1;min-height:0;display:flex;flex-direction:column;gap:3mm;margin-top:6mm;">
      ${vis(artPayouts(4.4), "flex:1;")}
      <div class="img-cap k-cap">Earnings flow straight to you.</div>
    </div>
    <div class="k-foot" style="margin-top:6mm;">
      <div class="qr-card k-qr"><img src="${qr}" alt="QR: pricing"></div>
      <div><div class="k-cta">Compare plans.<br>Start free.</div><div class="k-url">sayzio.app/pricing</div></div>
    </div>`
  );
}

async function p12Forms(): Promise<string> {
  return pageShell(
    12,
    `
    <div style="margin-top:8mm;" class="k-kick">FORMS &amp; LEADS</div>
    <div class="k-h">From scan<br>to <span class="accent">signed up.</span></div>
    <p class="k-p">Every Sayzio page can capture leads on the spot: forms with 21 field types, payments
      inside the form, and subscriber lists that grow while you sleep.</p>
    <div class="k-li"><div class="k-ic">📝</div><div><div class="k-lt">21 field types</div><div class="k-ld">Text, files, ratings, signatures, repeat groups and more.</div></div></div>
    <div class="k-li"><div class="k-ic">💳</div><div><div class="k-lt">Payments in forms</div><div class="k-ld">Charge per submission or per field. Bookings, orders, donations.</div></div></div>
    <div class="k-li"><div class="k-ic">📣</div><div><div class="k-lt">Email + WhatsApp lists</div><div class="k-ld">Collect subscribers from any biolink, export or message them anytime.</div></div></div>
    ${duo(
      artForms(3.2),
      "Build once; leads flow in.",
      artSubscribers(3.4),
      "Your list grows on every visit."
    )}`
  );
}

async function p13Ecosystem(): Promise<string> {
  return pageShell(
    13,
    `
    <div style="margin-top:8mm;" class="k-kick">THE ECOSYSTEM</div>
    <div class="k-h">One platform.<br><span class="accent">Three apps.</span></div>
    <p class="k-p">Sayzio follows you everywhere you work: the full web platform, the Zio Dialer on your
      phone, and a browser extension one click away. One account, always in sync.</p>
    <div class="k-li"><div class="k-ic">📞</div><div><div class="k-lt">Zio Dialer</div><div class="k-ld">Caller ID, T9 search, contact sync — every number resolves to its Sayzio page.</div></div></div>
    <div class="k-li"><div class="k-ic">🧩</div><div><div class="k-lt">Browser extension</div><div class="k-ld">Shorten the page you're on and mint a designer QR without leaving the tab.</div></div></div>
    <div style="flex:1;min-height:0;display:flex;gap:4mm;margin-top:6mm;">
      <div style="flex:1;display:flex;flex-direction:column;gap:2.5mm;">
        ${vis(artDialer(3.5), "flex:1;")}
        <div class="img-cap k-cap">Numbers become pages.</div>
      </div>
      <div style="flex:1;display:flex;flex-direction:column;gap:2.5mm;">
        ${vis(artExtension(3.2), "flex:1;")}
        <div class="img-cap k-cap">Links from any tab.</div>
      </div>
    </div>`
  );
}

async function p14Inbox(): Promise<string> {
  return pageShell(
    14,
    `
    <div style="margin-top:8mm;" class="k-kick">AI INBOX &amp; TEAMS</div>
    <div class="k-h">Never miss<br>a <span class="accent">lead</span> again.</div>
    <p class="k-p">DMs, form leads, orders and subscriber messages land in one queue, and the AI drafts the
      reply before you open it. Teams run it all from shared workspaces.</p>
    <div class="k-li"><div class="k-ic">✍️</div><div><div class="k-lt">AI-drafted replies</div><div class="k-ld">On-brand drafts grounded in your own knowledge base.</div></div></div>
    <div class="k-li"><div class="k-ic">🛡</div><div><div class="k-lt">Autopilot with guardrails</div><div class="k-ld">Routine replies go out alone; anything sensitive waits for you.</div></div></div>
    <div class="k-li"><div class="k-ic">👥</div><div><div class="k-lt">Workspaces &amp; roles</div><div class="k-ld">Every client or campaign gets its own space, links and permissions.</div></div></div>
    <div style="flex:1;min-height:0;display:flex;gap:4mm;margin-top:6mm;">
      <div style="flex:1;display:flex;flex-direction:column;gap:2.5mm;">
        ${vis(artInbox(3.4), "flex:1;")}
        <div class="img-cap k-cap">One queue, every channel.</div>
      </div>
      <div style="flex:1;display:flex;flex-direction:column;gap:2.5mm;">
        ${vis(artWorkspace(3.6), "flex:1;")}
        <div class="img-cap k-cap">Workspaces keep teams in sync.</div>
      </div>
    </div>`
  );
}

async function p15Api(): Promise<string> {
  const qr = await qrSvg(QR_URLS.pricing);
  return pageShell(
    15,
    `
    <div style="margin-top:8mm;" class="k-kick">THE PLATFORM</div>
    <div class="k-h">Built to be<br><span class="accent">built on.</span></div>
    <p class="k-p">A full REST API covers links, biolinks, QR codes, analytics and more — the same engine
      behind the web, mobile and dialer apps. Plans start free and scale with you.</p>
    <div class="k-li"><div class="k-ic">🔑</div><div><div class="k-lt">Developer API keys</div><div class="k-ld">Sanctum bearer tokens, metered plans and clear rate limits.</div></div></div>
    <div class="k-li"><div class="k-ic">📚</div><div><div class="k-lt">Documented end to end</div><div class="k-ld">Auth, CRUD, public resolution, feeds, discovery and webhooks.</div></div></div>
    <div class="k-li"><div class="k-ic">🆓</div><div><div class="k-lt">Start free</div><div class="k-ld">A generous free plan; upgrade only when your usage says so.</div></div></div>
    ${chips(["Links CRUD", "Biolinks", "QR codes", "Analytics", "Feeds", "Follows", "Discovery"], 3.0)}
    <div style="flex:1;min-height:0;display:flex;flex-direction:column;gap:3mm;margin-top:6mm;">
      ${vis(artApi(4.4), "flex:1;")}
      <div class="img-cap k-cap">The same API our own apps run on.</div>
    </div>
    <div class="k-foot" style="margin-top:6mm;">
      <div class="qr-card k-qr"><img src="${qr}" alt="QR: pricing"></div>
      <div><div class="k-cta">Plans &amp; pricing.<br>Scan to compare.</div><div class="k-url">sayzio.app/pricing</div></div>
    </div>`
  );
}

async function p16Back(): Promise<string> {
  const qrHome = await qrSvg(QR_URLS.home);
  const qrPricing = await qrSvg(QR_URLS.pricing);
  const qrDemo = await qrSvg(QR_URLS.demoChat);
  return pageShell(
    16,
    `
    <div style="display:flex;align-items:center;justify-content:space-between;">
      ${wordmark(10)}
      <div class="pill" style="padding:1.6mm 5mm;font-size:3.2mm;">AI-FIRST LINK PLATFORM</div>
    </div>
    <div style="margin-top:12mm;">
      <div style="font-size:17mm;font-weight:700;letter-spacing:-.025em;line-height:1.05;">
        Say hello.<br>Your link will <span class="accent">answer.</span></div>
    </div>
    <div style="flex:1;display:flex;flex-direction:column;justify-content:center;gap:6mm;">
      ${[
        [qrDemo, "Talk to the live AI demo", "sayzio.app"],
        [qrHome, "Start free today", "sayzio.app"],
        [qrPricing, "Plans &amp; pricing", "sayzio.app/pricing"],
      ]
        .map(
          ([q, t, u]) => `
      <div class="glass" style="padding:5mm 6mm;display:flex;align-items:center;gap:6mm;">
        <div class="qr-card" style="width:26mm;height:26mm;flex:none;"><img src="${q}" alt="QR"></div>
        <div><div style="font-size:5.4mm;font-weight:700;">${t}</div>
          <div style="font-size:4mm;color:var(--sky);font-weight:600;margin-top:1.2mm;">${u}</div></div>
      </div>`
        )
        .join("")}
    </div>
    <div class="glass" style="padding:5.5mm 7mm;display:flex;align-items:center;gap:6mm;">
      <img src="${ASSET.mascot}" style="width:16mm;height:16mm;flex:none;" alt="">
      <div style="font-size:3.9mm;font-weight:500;color:var(--muted);line-height:1.6;">
        <span style="color:var(--ink);font-weight:700;">${CONTACT.founder}</span> · Founder<br>
        ${CONTACT.phone} · ${CONTACT.email} · <span style="color:var(--sky);font-weight:600;">${CONTACT.site}</span>
      </div>
    </div>`,
    { chrome: false }
  );
}

/* ============================ rendering ============================ */

const PAGES = [
  p01Cover,
  p02Rebrand,
  p03WhatIs,
  p04AiChat,
  p05Builder,
  p06Biolinks,
  p07LinkTypes,
  p08QrStudio,
  p09Commerce,
  p10Analytics,
  p11Monetization,
  p12Forms,
  p13Ecosystem,
  p14Inbox,
  p15Api,
  p16Back,
];

// Saddle-stitch imposition for 16 pages on 4 sheets (front, back) pairs:
// each entry is [leftPage, rightPage] of one A3 side.
const IMPOSITION: Array<[number, number]> = [
  [16, 1], // sheet 1 front
  [2, 15], // sheet 1 back
  [14, 3], // sheet 2 front
  [4, 13], // sheet 2 back
  [12, 5], // sheet 3 front
  [6, 11], // sheet 3 back
  [10, 7], // sheet 4 front
  [8, 9], // sheet 4 back
];

const AUTO_FIT = `(() => {
  document.querySelectorAll(".art-fit").forEach((fit) => {
    const fr = fit.parentElement.getBoundingClientRect();
    let l = Infinity, t = Infinity, r = -Infinity, b = -Infinity;
    for (const child of fit.children) {
      const pos = getComputedStyle(child).position;
      if (pos === "absolute" || pos === "fixed") continue;
      const cr = child.getBoundingClientRect();
      if (cr.width === 0 && cr.height === 0) continue;
      l = Math.min(l, cr.left); t = Math.min(t, cr.top);
      r = Math.max(r, cr.right); b = Math.max(b, cr.bottom);
    }
    if (!isFinite(l)) return;
    const pad = Math.min(fr.width, fr.height) * 0.05;
    const s = Math.min((fr.width - 2 * pad) / (r - l), (fr.height - 2 * pad) / (b - t));
    if (Math.abs(s - 1) > 0.01) fit.style.transform = "scale(" + s + ")";
  });
})()`;

async function renderMultiPagePdf(
  pg: Page,
  file: string,
  wMm: number,
  hMm: number,
  pagesHtml: string[],
  extraCss: string
): Promise<void> {
  const css = `
    @page{size:${wMm}mm ${hMm}mm;margin:0;}
    html,body{width:${wMm}mm;height:auto;}
    body{overflow:visible;}
    .sheetbox{position:relative;width:${wMm}mm;height:${hMm}mm;overflow:hidden;break-after:page;}
    .sheetbox:last-child{break-after:auto;}
    ${BOOK_CSS}
    ${extraCss}`;
  const html = doc(pagesHtml.map((p) => `<div class="sheetbox">${p}</div>`).join(""), css);
  const pxW = Math.ceil((wMm * 96) / 25.4);
  const pxH = Math.ceil((hMm * 96) / 25.4);
  await pg.setViewportSize({ width: pxW, height: pxH });
  await pg.setContent(html, { waitUntil: "networkidle" });
  await pg.evaluate(AUTO_FIT);
  await pg.pdf({
    path: path.join(OUT, file),
    width: `${wMm}mm`,
    height: `${hMm}mm`,
    printBackground: true,
    margin: { top: 0, bottom: 0, left: 0, right: 0 },
  });
  console.log(`✔ ${file} (${pagesHtml.length} page(s) @ ${wMm}x${hMm}mm incl. ${BLEED}mm bleed)`);
}

async function main() {
  mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const pg = await browser.newPage();

  console.log("Rendering 16 booklet pages…");
  const pageHtml: string[] = [];
  for (const fn of PAGES) pageHtml.push(await fn());

  // 1) Reader PDF: pages 1..16 sequential, one A4(+bleed) page each.
  await renderMultiPagePdf(pg, "sayzio-booklet-reader.pdf", BW, BH, pageHtml, "");

  // 2) Imposed A3 PDF: 8 sides. Each side hosts two clipped A4 pages; the
  // inner 3mm bleed of each page is trimmed at the fold (x = 213mm).
  const IMPOSE_CSS = `
    .half{position:absolute;top:0;width:${HALF}mm;height:${SH}mm;overflow:hidden;}
    .half.l{left:0;} .half.r{left:${HALF}mm;}
    .pgslot{position:absolute;top:0;width:${BW}mm;height:${BH}mm;}
    .half.l .pgslot{left:0;} .half.r .pgslot{left:-${BLEED}mm;}`;
  const sides = IMPOSITION.map(
    ([lp, rp], i) => `
    <div class="half l"><div class="pgslot">${pageHtml[lp - 1]}</div></div>
    <div class="half r"><div class="pgslot">${pageHtml[rp - 1]}</div></div>
    <!-- side ${i + 1}: pages ${lp} | ${rp} -->`
  );
  await renderMultiPagePdf(pg, "sayzio-booklet-imposed-a3.pdf", SW, SH, sides, IMPOSE_CSS);

  await browser.close();

  writeFileSync(path.join(OUT, "README-booklet.md"), readme());
  console.log("✔ README-booklet.md");
}

function readme(): string {
  return `# Sayzio A3 Saddle-Stitch Exhibition Booklet — Print Spec

## What's in this folder
- **sayzio-booklet-imposed-a3.pdf** — the file to hand to the print shop.
  8 PDF pages = 4 physical A3 sheets, front and back. Print double-sided
  (flip on the SHORT edge of the landscape sheet), fold the stack in half,
  staple twice on the fold (saddle stitch).
- **sayzio-booklet-reader.pdf** — the same 16 pages in reading order (1 → 16),
  for on-screen proofing only. Do not print this one for production.

## Sizes
- Finished page: **A4 portrait, 210 × 297 mm** (16 pages).
- Sheet: **A3 landscape, 420 × 297 mm** trim; PDF pages are **426 × 303 mm**
  including **3 mm bleed** on every outer edge. Trim marks are not included —
  trim to final size at 3 mm from each edge.
- Fold line runs vertically at the centre of each trimmed A3 sheet.

## Imposition (already applied — no printer imposition needed)
| PDF page | Sheet | Side  | Left page | Right page |
|---------:|:-----:|:------|:---------:|:----------:|
| 1 | 1 | front | 16 | 1  |
| 2 | 1 | back  | 2  | 15 |
| 3 | 2 | front | 14 | 3  |
| 4 | 2 | back  | 4  | 13 |
| 5 | 3 | front | 12 | 5  |
| 6 | 3 | back  | 6  | 11 |
| 7 | 4 | front | 10 | 7  |
| 8 | 4 | back  | 8  | 9  |

Collate sheets 1→4, fold, and page order comes out 1–16 automatically.

## Print settings
- **Colour:** artwork uses rich dark backgrounds; ask the printer to convert
  RGB → CMYK with a rich-black profile (the files are PDF 1.4, RGB, fully
  vector, so conversion is lossless at any resolution; effective raster
  assets are embedded at ≥300 DPI).
- **Paper suggestion:** 200–250 gsm silk/matte for the cover sheet (sheet 1),
  130–170 gsm for inner sheets; or 170 gsm throughout for a uniform feel.
- **Duplex:** double-sided, short-edge flip (landscape sheets).
- **Finishing:** saddle stitch (2 staples on the spine), trim 3 mm all round.
- No content sits within 12 mm of the trim or 18 mm of the spine, so minor
  fold/trim drift is safe.

## QR codes (all verified scannable from the rendered PDF)
| Where | Destination |
|---|---|
| Cover (p1), Back cover (p16) | https://sayzio.app/ |
| p4 "Talk to this page", p16 | https://sayzio.app/aichat-support-bot |
| p7 & p9 demos | https://sayzio.app/demos |
| p11 & p15 pricing, p16 | https://sayzio.app/pricing |

Note: the small QR shown inside the page-8 "QR Studio" mockup artwork is a
decorative design element (rendered too small to scan at print size) — it is
not a call-to-action code.

**Regenerate before printing if URLs change:** the AI-chat demo QR
(\`https://sayzio.app/aichat-support-bot\`, pages 4 and 16) points at the
currently seeded demo chat link — if you rename or replace that demo, rerun
\`pnpm --filter @workspace/scripts run print:booklet\` after updating
\`QR_URLS.demoChat\` in \`scripts/src/print-collateral/generate.ts\`. All other
QRs point at stable pages (home, /pricing, /demos).
`;
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
