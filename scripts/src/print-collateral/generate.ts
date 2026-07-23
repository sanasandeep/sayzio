/**
 * Sayzio event-stall print collateral generator.
 *
 * Renders 12 print-ready PDFs (visiting card front/back, 2 roll-up standees,
 * trifold pamphlet outside/inside, 6 A3 boards) via headless Chromium.
 * All pages include 3mm bleed on every edge; everything is vector: text, QR
 * codes and all artwork. Visuals are CSS-composed glassmorphic mockups in the
 * same style as the Sayzio marketing site (phone frames, chat bubbles, charts),
 * not raster images.
 *
 * Run: pnpm --filter @workspace/scripts run print:collateral
 * Output: .local/print-out/
 */
import { chromium } from "playwright";
import QRCode from "qrcode";
import { readFileSync, mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";

const ROOT = path.resolve(import.meta.dirname, "../../..");
const OUT = path.join(ROOT, ".local/print-out");
const BRANDING = path.join(ROOT, "artifacts/1inme/public/branding");
const FONTS = path.join(import.meta.dirname, "assets/fonts");

export const BLEED = 3; // mm each side

// ---------- assets ----------
const b64 = (p: string) => readFileSync(p).toString("base64");
const png = (p: string) => `data:image/png;base64,${b64(p)}`;
const jpg = (p: string) => `data:image/jpeg;base64,${b64(p)}`;
const font = (f: string) => `data:font/ttf;base64,${b64(path.join(FONTS, f))}`;

const ATTACHED = path.join(ROOT, "attached_assets");
export const PHOTOS = path.join(import.meta.dirname, "assets/photos");
export const ASSET = {
  mark: png(path.join(ATTACHED, "icon_1784787352733.png")),
  logo: png(path.join(ATTACHED, "logo_white_1784787374729.png")),
  mascot: png(path.join(ATTACHED, "icon_1784787352733.png")),
  zioBot: png(path.join(BRANDING, "zio-bot.png")),
};
// Real photography embedded inside the mockups (AI-generated, print-safe).
const PHOTO = {
  pizza: jpg(path.join(PHOTOS, "pizza.jpg")),
  pasta: jpg(path.join(PHOTOS, "pasta.jpg")),
  limesoda: jpg(path.join(PHOTOS, "limesoda.jpg")),
  bakery: jpg(path.join(PHOTOS, "bakery.jpg")),
  maya: jpg(path.join(PHOTOS, "avatar-maya.jpg")),
  arjun: jpg(path.join(PHOTOS, "avatar-arjun.jpg")),
};

// ---------- QR ----------
// Designer QR: brand-gradient modules on white (EC level H tolerates the
// centre logo badge that .qr-card::after overlays in CSS).
export async function qrSvg(url: string): Promise<string> {
  const darkKey = "#0a0f22";
  let svg = await QRCode.toString(url, {
    type: "svg",
    errorCorrectionLevel: "H",
    margin: 0,
    color: { dark: darkKey, light: "#ffffff" },
  });
  svg = svg
    .replace(
      /<svg([^>]*)>/,
      `<svg$1><defs><linearGradient id="qg" x1="0%" y1="0%" x2="100%" y2="100%">` +
        `<stop offset="0%" stop-color="#101d52"/><stop offset="55%" stop-color="#1d3aad"/>` +
        `<stop offset="100%" stop-color="#0a0f22"/></linearGradient></defs>`
    )
    .replace(new RegExp(`stroke="${darkKey}"`, "g"), `stroke="url(#qg)"`)
    .replace(new RegExp(`fill="${darkKey}"`, "g"), `fill="url(#qg)"`);
  return `data:image/svg+xml;base64,${Buffer.from(svg).toString("base64")}`;
}

// QR destinations: all live pages.
export const QR_URLS = {
  home: "https://sayzio.app/",
  pricing: "https://sayzio.app/pricing",
  demos: "https://sayzio.app/demos",
  demoChat: "https://sayzio.app/aichat-support-bot", // live seeded AI chat demo
};

// Contact details (visiting card + footers)
export const CONTACT = {
  founder: "Sana Sandeep",
  phone: "+91 70134 06816",
  email: "support@sayzio.app",
  site: "sayzio.app",
};

// ---------- shared CSS ----------
export const BASE_CSS = `
@font-face { font-family:'Space Grotesk'; font-weight:300; src:url(${font("SpaceGrotesk-Light.ttf")}) format('truetype'); }
@font-face { font-family:'Space Grotesk'; font-weight:400; src:url(${font("SpaceGrotesk-Regular.ttf")}) format('truetype'); }
@font-face { font-family:'Space Grotesk'; font-weight:500; src:url(${font("SpaceGrotesk-Medium.ttf")}) format('truetype'); }
@font-face { font-family:'Space Grotesk'; font-weight:600; src:url(${font("SpaceGrotesk-SemiBold.ttf")}) format('truetype'); }
@font-face { font-family:'Space Grotesk'; font-weight:700; src:url(${font("SpaceGrotesk-Bold.ttf")}) format('truetype'); }
:root{
  --ink:#f4f7ff; --muted:rgba(224,232,255,.72); --faint:rgba(224,232,255,.45);
  --blue:#3d6bff; --blue-soft:#5c83ff; --blue-deep:#2342c7; --sky:#8fd0ff; --mint:#59e3c0;
  --bg:#070b18; --bg2:#0b1226;
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
html,body{width:100%;height:100%;}
body{font-family:'Space Grotesk',sans-serif;color:var(--ink);background:var(--bg);overflow:hidden;}
.page{position:relative;width:100%;height:100%;overflow:hidden;background:
  radial-gradient(120% 70% at 85% -10%, rgba(61,107,255,.38), transparent 55%),
  radial-gradient(90% 60% at -10% 105%, rgba(35,66,199,.35), transparent 60%),
  linear-gradient(160deg, #0b1226 0%, #070b18 55%, #05070f 100%);}
.grid-tex{position:absolute;inset:0;opacity:.5;background-image:
  linear-gradient(rgba(143,208,255,.05) 1px, transparent 1px),
  linear-gradient(90deg, rgba(143,208,255,.05) 1px, transparent 1px);}
.glass{background:linear-gradient(150deg, rgba(255,255,255,.09), rgba(255,255,255,.035));
  border:1px solid rgba(255,255,255,.14);border-radius:14px;}
.accent{color:var(--blue-soft);}
.pill{display:inline-flex;align-items:center;gap:.45em;border:1px solid rgba(92,131,255,.55);
  border-radius:999px;color:var(--sky);background:rgba(61,107,255,.14);font-weight:600;}
.qr-card{position:relative;z-index:0;background:#fff;border-radius:9%;display:flex;align-items:center;justify-content:center;}
.qr-card::before{content:'';position:absolute;inset:-5.5%;border-radius:11%;z-index:-1;
  background:var(--qr-ring,linear-gradient(150deg,var(--blue-soft),var(--blue-deep) 60%,var(--sky)));}
.qr-card::after{content:'';position:absolute;left:50%;top:50%;width:19%;height:19%;transform:translate(-50%,-50%);
  border-radius:24%;background:#fff url(${ASSET.mark}) center/74% no-repeat;box-shadow:0 0 0 .35mm #fff;}
.qr-card img{width:88%;height:88%;}
.wordmark{display:flex;align-items:center;font-weight:700;letter-spacing:-.02em;}
.wordmark img{object-fit:contain;}
.dot{color:var(--blue-soft);}
.img-frame{border-radius:14px;border:1px solid rgba(255,255,255,.16);overflow:hidden;min-height:0;min-width:0;
  box-shadow:0 0 0 1px rgba(61,107,255,.18);background:#0b1226;}
.img-cap{font-weight:500;color:var(--faint);text-align:center;}

/* ---- CSS-composed artwork (em-scaled; set font-size on .art to scale) ---- */
.art{position:relative;width:100%;height:100%;overflow:hidden;display:flex;align-items:center;justify-content:center;
  background:
    radial-gradient(90% 70% at 72% -6%, rgba(61,107,255,.32), transparent 60%),
    radial-gradient(70% 55% at 8% 108%, rgba(35,66,199,.30), transparent 62%),
    linear-gradient(155deg,#111a3c 0%,#0b1226 55%,#070b18 100%);}
.art-tex{position:absolute;inset:0;opacity:.6;background-image:
  linear-gradient(rgba(143,208,255,.055) 1px,transparent 1px),
  linear-gradient(90deg,rgba(143,208,255,.055) 1px,transparent 1px);background-size:2.6em 2.6em;}
.art-fit{position:relative;width:100%;height:100%;display:flex;align-items:center;justify-content:center;}
.ph{position:relative;width:10.6em;border-radius:1.7em;padding:1.35em .85em .9em;
  background:linear-gradient(170deg,#151f44,#0a1130);border:.12em solid rgba(255,255,255,.22);
  box-shadow:0 .7em 2.2em rgba(0,0,0,.55),0 0 0 .34em rgba(61,107,255,.16);}
.ph::before{content:'';position:absolute;top:.42em;left:50%;transform:translateX(-50%);
  width:3.2em;height:.44em;border-radius:.3em;background:rgba(255,255,255,.16);}
.gcard{background:linear-gradient(150deg,rgba(255,255,255,.10),rgba(255,255,255,.045));
  border:.07em solid rgba(255,255,255,.17);border-radius:.9em;}
.bub{max-width:82%;padding:.5em .75em;border-radius:.85em;border-bottom-left-radius:.25em;
  font-size:.72em;line-height:1.35;font-weight:500;background:rgba(255,255,255,.10);
  border:.06em solid rgba(255,255,255,.15);color:var(--ink);}
.bub.me{margin-left:auto;border-radius:.85em;border-bottom-right-radius:.25em;
  background:linear-gradient(150deg,var(--blue),var(--blue-deep));border-color:transparent;}
.lrow{display:flex;align-items:center;gap:.55em;padding:.52em .7em;border-radius:.75em;
  background:rgba(255,255,255,.09);border:.06em solid rgba(255,255,255,.16);
  font-size:.74em;font-weight:600;}
.avatar{border-radius:50%;background:linear-gradient(150deg,var(--blue-soft),var(--blue-deep));
  display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;flex:none;}
.wave{display:flex;align-items:center;gap:.26em;}
.wave i{display:block;width:.36em;border-radius:.3em;background:linear-gradient(180deg,var(--sky),var(--blue));}
.bars{display:flex;align-items:flex-end;gap:.45em;}
.bars i{display:block;flex:1;border-radius:.28em .28em 0 0;
  background:linear-gradient(180deg,var(--blue-soft),rgba(61,107,255,.22));}
.chip{display:inline-flex;align-items:center;gap:.4em;padding:.32em .68em;border-radius:99em;
  font-size:.62em;font-weight:600;background:rgba(61,107,255,.18);
  border:.07em solid rgba(92,131,255,.5);color:var(--sky);white-space:nowrap;}
.chip.ok{background:rgba(89,227,192,.14);border-color:rgba(89,227,192,.5);color:var(--mint);}
.tag{font-size:.6em;font-weight:600;color:var(--faint);letter-spacing:.14em;}
.key{display:flex;align-items:center;justify-content:center;flex-direction:column;border-radius:.6em;
  background:rgba(255,255,255,.08);border:.06em solid rgba(255,255,255,.14);font-weight:600;}
.dotbg{position:absolute;inset:0;background-image:radial-gradient(rgba(143,208,255,.20) .085em, transparent .085em);
  background-size:1.15em 1.15em;}
.city{position:absolute;width:.5em;height:.5em;border-radius:50%;background:var(--sky);
  box-shadow:0 0 .8em .28em rgba(92,131,255,.55);}
.mono{font-family:ui-monospace,monospace;}
`;

/* ================= CSS artwork components =================
 * Each returns a full-bleed .art tile. Scale with fontMm. */
export const art = (fontMm: number, inner: string) =>
  `<div class="art" style="font-size:${fontMm}mm;"><div class="art-tex"></div><div class="art-fit">${inner}</div></div>`;

/** AI chat conversation inside a phone frame. */
export const artChat = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="ph">
    <div style="display:flex;align-items:center;gap:.55em;padding-bottom:.6em;border-bottom:.06em solid rgba(255,255,255,.12);">
      <img src="${ASSET.mascot}" style="width:1.7em;height:1.7em;" alt="">
      <div><div style="font-size:.78em;font-weight:700;">Zio · @maya</div>
        <div style="font-size:.58em;color:var(--mint);font-weight:600;">● online · answers 24/7</div></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:.55em;margin-top:.7em;">
      <div class="bub">Hi! Do you ship to Berlin?</div>
      <div class="bub me">Yes, 2 to 4 days, free over €50. Want the bestseller list?</div>
      <div class="bub">Yes please 🙌</div>
      <div class="bub me">Here you go. I can take the order right here.</div>
    </div>
    <div style="margin-top:.75em;display:flex;align-items:center;gap:.45em;">
      <div class="gcard" style="flex:1;padding:.42em .65em;font-size:.62em;color:var(--faint);font-weight:500;">Ask me anything…</div>
      <div class="avatar" style="width:1.55em;height:1.55em;font-size:.72em;">🎙</div>
    </div>
  </div>`
  );

/** Voice answer card: waveform + mic. */
export const artVoice = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="gcard" style="position:relative;width:84%;padding:1em 1.1em;">
    <div class="tag">AI VOICE · LIVE</div>
    <div style="display:flex;align-items:center;gap:.9em;margin-top:.7em;">
      <div class="avatar" style="width:2.6em;height:2.6em;font-size:1.15em;box-shadow:0 0 1.2em .2em rgba(61,107,255,.5);">🎙</div>
      <div class="wave" style="flex:1;height:2.6em;">
        ${[0.9, 1.7, 2.4, 1.4, 2.1, 2.6, 1.8, 1.1, 2.2, 1.5, 0.8, 1.9, 2.5, 1.2, 1.6, 0.9]
          .map((h) => `<i style="height:${h}em;"></i>`)
          .join("")}
      </div>
    </div>
    <div style="margin-top:.8em;font-size:.72em;color:var(--muted);font-weight:500;line-height:1.4;">
      “We're open till 9. Want me to book you in for tonight?”</div>
    <div style="margin-top:.7em;display:flex;gap:.5em;"><span class="chip">🔊 Speech in, speech out</span><span class="chip ok">✓ Booked</span></div>
  </div>`
  );

/** Prompt → assembled page (AI builder). */
export const artBuilder = (fontMm: number) =>
  art(
    fontMm,
    `
  <div style="width:84%;display:flex;flex-direction:column;align-items:center;gap:.7em;">
    <div class="gcard" style="width:100%;padding:.6em .8em;display:flex;align-items:center;gap:.55em;">
      <span style="font-size:.9em;">✨</span>
      <span style="font-size:.68em;font-weight:500;color:var(--muted);">“a page for my bakery: menu, hours, WhatsApp ordering”</span>
    </div>
    <div style="color:var(--blue-soft);font-weight:700;line-height:1;">↓</div>
    <div class="ph" style="width:9.6em;padding:1.2em .75em .8em;">
      <div style="display:flex;align-items:center;gap:.5em;">
        <img src="${PHOTO.bakery}" style="width:1.9em;height:1.9em;border-radius:50%;object-fit:cover;flex:none;
          border:.09em solid rgba(92,131,255,.6);" alt="">
        <div><div style="font-size:.72em;font-weight:700;">Luna Bakery</div>
          <div style="font-size:.55em;color:var(--faint);">Fresh daily · 7am–9pm</div></div>
      </div>
      <img src="${PHOTO.bakery}" style="width:100%;height:3.4em;object-fit:cover;border-radius:.55em;margin-top:.6em;
        border:.06em solid rgba(255,255,255,.16);" alt="">
      <div style="display:flex;flex-direction:column;gap:.42em;margin-top:.55em;">
        <div class="lrow" style="transform:rotate(-1.4deg);">📋 Today's menu</div>
        <div class="lrow" style="transform:rotate(1.1deg);">💬 Order on WhatsApp</div>
        <div class="lrow">📍 Find the shop</div>
      </div>
    </div>
    <span class="chip ok">✓ Published in 58s · live URL ready</span>
  </div>`
  );

/** A finished creator biolink page in a phone. */
export const artBiolink = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="ph" style="width:10em;">
    <div style="display:flex;flex-direction:column;align-items:center;">
      <img src="${PHOTO.maya}" style="width:2.7em;height:2.7em;border-radius:50%;object-fit:cover;
        border:.1em solid rgba(92,131,255,.7);box-shadow:0 0 1em .18em rgba(61,107,255,.45);" alt="">
      <div style="margin-top:.45em;font-size:.82em;font-weight:700;">@maya <span style="color:var(--sky);">✔</span></div>
      <div style="font-size:.58em;color:var(--faint);font-weight:500;">Singer · Producer · Berlin</div>
    </div>
    <div style="display:flex;flex-direction:column;gap:.45em;margin-top:.75em;">
      <div class="lrow">🎵 Latest single · out now</div>
      <div class="lrow">🛍 Merch store</div>
      <div class="lrow">📅 Book a session</div>
      <div class="lrow" style="background:linear-gradient(150deg,rgba(61,107,255,.4),rgba(35,66,199,.3));">💬 Chat with my AI</div>
    </div>
    <div style="margin-top:.7em;display:flex;justify-content:center;gap:.5em;">
      ${["#5c83ff", "#8fd0ff", "#59e3c0", "#5c83ff"].map((c) => `<span style="width:.85em;height:.85em;border-radius:50%;background:${c};opacity:.85;"></span>`).join("")}
    </div>
  </div>`
  );

/** Dashboard analytics card (browser style). */
export const artDashboard = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="gcard" style="width:88%;padding:.7em .8em;">
    <div style="display:flex;align-items:center;gap:.45em;">
      <span style="width:.5em;height:.5em;border-radius:50%;background:#ff6b6b;"></span>
      <span style="width:.5em;height:.5em;border-radius:50%;background:#ffd166;"></span>
      <span style="width:.5em;height:.5em;border-radius:50%;background:var(--mint);"></span>
      <div class="gcard" style="flex:1;margin-left:.4em;padding:.22em .6em;font-size:.56em;color:var(--faint);font-weight:500;">sayzio.app/dashboard</div>
    </div>
    <div style="display:flex;gap:.5em;margin-top:.65em;">
      <div class="gcard" style="flex:1;padding:.45em;text-align:center;"><div style="font-size:.85em;font-weight:700;color:var(--blue-soft);">12.4k</div><div style="font-size:.5em;color:var(--faint);font-weight:600;">CLICKS</div></div>
      <div class="gcard" style="flex:1;padding:.45em;text-align:center;"><div style="font-size:.85em;font-weight:700;color:var(--blue-soft);">3.1k</div><div style="font-size:.5em;color:var(--faint);font-weight:600;">SCANS</div></div>
      <div class="gcard" style="flex:1;padding:.45em;text-align:center;"><div style="font-size:.85em;font-weight:700;color:var(--mint);">+18%</div><div style="font-size:.5em;color:var(--faint);font-weight:600;">THIS WEEK</div></div>
    </div>
    <div class="bars" style="height:3.4em;margin-top:.65em;">
      ${[0.35, 0.55, 0.42, 0.7, 0.6, 0.85, 0.75, 1, 0.65, 0.9].map((h) => `<i style="height:${h * 100}%;"></i>`).join("")}
    </div>
  </div>`
  );

/** Designer QR tiles fanned out. `qr` should be a QR svg data URL. */
export const artQrTiles = (fontMm: number, qr: string) =>
  art(
    fontMm,
    `
  <div style="display:flex;align-items:center;justify-content:center;">
    <div class="qr-card" style="width:5.6em;height:5.6em;border-radius:.8em;transform:rotate(-8deg) translateX(1em);opacity:.85;
      box-shadow:0 .5em 1.6em rgba(0,0,0,.5);border:.3em solid #5c83ff;"><img src="${qr}" alt=""></div>
    <div class="qr-card" style="width:7em;height:7em;border-radius:1em;z-index:2;
      box-shadow:0 .7em 2em rgba(0,0,0,.6),0 0 0 .34em rgba(61,107,255,.35);
      border:.4em solid transparent;background:linear-gradient(#fff,#fff) padding-box,linear-gradient(150deg,var(--blue-soft),var(--mint)) border-box;"><img src="${qr}" alt=""></div>
    <div class="qr-card" style="width:5.6em;height:5.6em;border-radius:.8em;transform:rotate(8deg) translateX(-1em);opacity:.85;
      box-shadow:0 .5em 1.6em rgba(0,0,0,.5);border:.3em solid #59e3c0;"><img src="${qr}" alt=""></div>
  </div>`
  );

/** World-map click heatmap. */
export const artMap = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="dotbg"></div>
  ${[
    [22, 30],
    [34, 52],
    [45, 24],
    [52, 60],
    [63, 38],
    [72, 55],
    [80, 28],
    [58, 74],
    [28, 68],
  ]
    .map(([x, y]) => `<div class="city" style="left:${x}%;top:${y}%;"></div>`)
    .join("")}
  <div class="chip" style="position:absolute;left:8%;bottom:9%;">📍 Live · 2,318 clicks today</div>
  <div class="chip ok" style="position:absolute;right:8%;top:9%;">Berlin +214</div>`
  );

/** Creator earnings / payouts wallet. */
export const artPayouts = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="gcard" style="width:84%;padding:.9em 1em;">
    <div class="tag">CREATOR EARNINGS</div>
    <div style="margin-top:.4em;font-size:1.5em;font-weight:700;">$1,284<span style="color:var(--faint);font-size:.6em;">.50</span></div>
    <div style="margin-top:.55em;display:flex;gap:.4em;flex-wrap:wrap;">
      <span class="chip">Stripe</span><span class="chip">PayPal</span><span class="chip">Razorpay</span>
    </div>
    <div style="margin-top:.6em;display:flex;align-items:center;justify-content:space-between;">
      <span class="chip ok">0% platform fee</span>
      <span style="font-size:.68em;font-weight:600;color:var(--mint);">→ your account</span>
    </div>
  </div>`
  );

/** Live restaurant orders dashboard rows. */
export const artOrders = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="gcard" style="width:88%;padding:.7em .8em;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div style="font-size:.78em;font-weight:700;">Orders · live</div><span class="chip">🔔 3 new</span>
    </div>
    ${[
      ["Table 4 · Margherita ×2", "Pending", ""],
      ["Table 1 · Ramen + Gyoza", "Preparing", "ok"],
      ["Table 6 · Espresso ×3", "Served", "ok"],
    ]
      .map(
        ([t, s, k]) => `
      <div style="display:flex;justify-content:space-between;align-items:center;gap:.5em;margin-top:.5em;
        padding:.42em .55em;border-radius:.6em;background:rgba(255,255,255,.07);border:.06em solid rgba(255,255,255,.12);">
        <span style="font-size:.62em;font-weight:600;">${t}</span><span class="chip ${k}" style="font-size:.5em;">${s}</span>
      </div>`
      )
      .join("")}
  </div>`
  );

/** Unified AI inbox rows. */
export const artInbox = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="gcard" style="width:88%;padding:.7em .8em;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div style="font-size:.78em;font-weight:700;">Inbox · all channels</div><span class="chip">AI on</span>
    </div>
    ${[
      ["🟢", "WhatsApp · Ana", "“Is the studio free Friday?”"],
      ["📸", "Instagram · @leo", "“Price for a custom order?”"],
      ["✉️", "Email · Priya", "“Invoice for March please.”"],
    ]
      .map(
        ([ic, who, msg]) => `
      <div style="display:flex;gap:.5em;align-items:center;margin-top:.5em;padding:.42em .55em;border-radius:.6em;
        background:rgba(255,255,255,.07);border:.06em solid rgba(255,255,255,.12);">
        <span style="font-size:.75em;">${ic}</span>
        <div style="min-width:0;flex:1;"><div style="font-size:.6em;font-weight:700;">${who}</div>
          <div style="font-size:.54em;color:var(--muted);">${msg}</div></div>
        <span class="chip ok" style="font-size:.46em;">AI draft ✓</span>
      </div>`
      )
      .join("")}
  </div>`
  );

/** Menu + table ordering in a phone. */
export const artRestaurant = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="ph" style="width:10.2em;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div style="font-size:.78em;font-weight:700;">Bella's Kitchen</div><span class="chip" style="font-size:.52em;">Table 6</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:.42em;margin-top:.6em;">
      ${[
        [PHOTO.pizza, "Margherita", "₹349"],
        [PHOTO.pasta, "Alfredo pasta", "₹429"],
        [PHOTO.limesoda, "Fresh lime soda", "₹99"],
      ]
        .map(
          ([img, n, p]) => `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.4em .5em;border-radius:.65em;
          background:rgba(255,255,255,.08);border:.06em solid rgba(255,255,255,.14);">
          <span style="display:flex;align-items:center;gap:.5em;font-size:.64em;font-weight:600;">
            <img src="${img}" style="width:2em;height:2em;border-radius:.5em;object-fit:cover;flex:none;" alt="">${n}</span>
          <span style="display:flex;align-items:center;gap:.45em;font-size:.6em;font-weight:700;color:var(--sky);">${p}
            <span class="avatar" style="width:1.25em;height:1.25em;font-size:.8em;border-radius:.35em;">+</span></span>
        </div>`
        )
        .join("")}
    </div>
    <div style="margin-top:.6em;padding:.5em .6em;border-radius:.65em;display:flex;justify-content:space-between;align-items:center;
      background:linear-gradient(150deg,rgba(61,107,255,.35),rgba(35,66,199,.28));border:.06em solid rgba(92,131,255,.4);">
      <span style="font-size:.58em;font-weight:600;">Est. bill · GST incl.</span>
      <span style="font-size:.72em;font-weight:700;">₹877</span>
    </div>
  </div>`
  );

/** Zio Dialer keypad in a phone. */
export const artDialer = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="ph" style="width:10.2em;">
    <div style="text-align:center;">
      <div style="font-size:.95em;font-weight:700;letter-spacing:.06em;">98•• •• 210</div>
      <div class="chip" style="margin-top:.4em;font-size:.5em;display:inline-flex;align-items:center;gap:.5em;">
        <img src="${PHOTO.maya}" style="width:1.5em;height:1.5em;border-radius:50%;object-fit:cover;" alt="">
        → resolves to sayzio.app/maya ✔</div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.4em;margin-top:.7em;">
      ${[
        ["1", ""],
        ["2", "ABC"],
        ["3", "DEF"],
        ["4", "GHI"],
        ["5", "JKL"],
        ["6", "MNO"],
        ["7", "PQRS"],
        ["8", "TUV"],
        ["9", "WXYZ"],
        ["*", ""],
        ["0", "+"],
        ["#", ""],
      ]
        .map(
          ([d, l]) => `
        <div class="key" style="height:1.9em;"><span style="font-size:.72em;">${d}</span>
          ${l ? `<span style="font-size:.38em;color:var(--faint);letter-spacing:.14em;">${l}</span>` : ""}</div>`
        )
        .join("")}
    </div>
    <div style="margin-top:.6em;display:flex;justify-content:center;">
      <div class="avatar" style="width:2em;height:2em;font-size:.85em;background:linear-gradient(150deg,var(--mint),#2aa47f);">📞</div>
    </div>
  </div>`
  );

/** Browser extension popup over a toolbar. */
export const artExtension = (fontMm: number) =>
  art(
    fontMm,
    `
  <div style="width:88%;">
    <div class="gcard" style="padding:.5em .7em;display:flex;align-items:center;gap:.45em;">
      <span style="width:.5em;height:.5em;border-radius:50%;background:#ff6b6b;"></span>
      <span style="width:.5em;height:.5em;border-radius:50%;background:#ffd166;"></span>
      <span style="width:.5em;height:.5em;border-radius:50%;background:var(--mint);"></span>
      <div class="gcard" style="flex:1;margin-left:.35em;padding:.24em .6em;font-size:.56em;color:var(--faint);font-weight:500;">yourshop.com/spring-collection-2026</div>
      <img src="${ASSET.mark}" style="width:1.15em;height:1.15em;filter:drop-shadow(0 0 .35em rgba(92,131,255,.8));" alt="">
    </div>
    <div class="gcard" style="width:78%;margin:.5em 0 0 auto;padding:.7em .8em;box-shadow:0 .6em 1.8em rgba(0,0,0,.5);">
      <div class="tag">SAYZIO · THIS PAGE</div>
      <div style="margin-top:.5em;display:flex;align-items:center;gap:.45em;padding:.4em .55em;border-radius:.55em;
        background:rgba(61,107,255,.16);border:.06em solid rgba(92,131,255,.4);">
        <span style="font-size:.62em;font-weight:700;color:var(--sky);">szy.io/x7Kq</span>
        <span class="chip ok" style="margin-left:auto;font-size:.48em;">Copied ✓</span>
      </div>
      <div style="margin-top:.5em;display:flex;gap:.4em;">
        <span class="chip" style="font-size:.5em;">▦ Designer QR</span>
        <span class="chip" style="font-size:.5em;">📊 Track scans</span>
      </div>
    </div>
  </div>`
  );

/** Forms builder card. */
export const artForms = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="gcard" style="width:84%;padding:.8em .9em;">
    <div style="font-size:.78em;font-weight:700;">Booking form</div>
    ${["Full name", "Email address"]
      .map(
        (l) => `
      <div style="margin-top:.5em;"><div style="font-size:.52em;color:var(--faint);font-weight:600;">${l.toUpperCase()}</div>
        <div class="gcard" style="margin-top:.25em;height:1.35em;border-radius:.5em;"></div></div>`
      )
      .join("")}
    <div style="margin-top:.55em;display:flex;justify-content:space-between;align-items:center;">
      <span style="font-size:.58em;font-weight:600;color:var(--muted);">WhatsApp updates</span>
      <span style="width:1.7em;height:.95em;border-radius:.5em;background:linear-gradient(150deg,var(--blue),var(--blue-deep));position:relative;">
        <span style="position:absolute;right:.12em;top:.12em;width:.7em;height:.7em;border-radius:50%;background:#fff;"></span></span>
    </div>
    <div style="margin-top:.6em;padding:.5em;border-radius:.6em;text-align:center;font-size:.66em;font-weight:700;
      background:linear-gradient(150deg,var(--blue),var(--blue-deep));">₹499 · Pay &amp; submit</div>
  </div>`
  );

/** Team workspace card. */
export const artWorkspace = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="gcard" style="width:84%;padding:.9em 1em;">
    <div style="display:flex;align-items:center;">
      ${["A", "R", "S", "+2"]
        .map(
          (c, i) =>
            `<div class="avatar" style="width:1.9em;height:1.9em;font-size:.72em;margin-left:${i ? "-.55em" : "0"};
              border:.12em solid #0b1226;${i === 3 ? "background:rgba(255,255,255,.14);" : ""}">${c}</div>`
        )
        .join("")}
      <div style="margin-left:.8em;"><div style="font-size:.74em;font-weight:700;">Acme Team</div>
        <div style="font-size:.54em;color:var(--faint);font-weight:500;">4 members · 2 workspaces</div></div>
    </div>
    <div style="margin-top:.65em;display:flex;gap:.4em;flex-wrap:wrap;">
      <span class="chip" style="font-size:.52em;">Owner</span><span class="chip" style="font-size:.52em;">Editor ×2</span>
      <span class="chip ok" style="font-size:.52em;">All links shared</span>
    </div>
  </div>`
  );

/** REST API code card. */
export const artApi = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="gcard mono" style="width:88%;padding:.85em 1em;font-size:.66em;line-height:1.75;color:var(--sky);">
    <div><span style="color:var(--mint);">POST</span> /api/v1/links <span style="color:var(--faint);">→ 201</span></div>
    <div><span style="color:var(--mint);">GET</span>&nbsp;&nbsp;/api/v1/biolinks/{alias}</div>
    <div><span style="color:var(--mint);">POST</span> /api/v1/qr-codes</div>
    <div><span style="color:var(--mint);">GET</span>&nbsp;&nbsp;/api/v1/links/{id}/analytics</div>
    <div style="color:rgba(224,232,255,.55);">{ "data": { "clicks": 12481 } }</div>
  </div>`
  );

/** Digital vCard. */
export const artVcard = (fontMm: number) =>
  art(
    fontMm,
    `
  <div class="gcard" style="width:84%;padding:.85em 1em;">
    <div style="display:flex;align-items:center;gap:.6em;">
      <img src="${PHOTO.arjun}" style="width:2.2em;height:2.2em;border-radius:50%;object-fit:cover;
        border:.09em solid rgba(92,131,255,.6);" alt="">
      <div><div style="font-size:.76em;font-weight:700;">Rahul Mehta</div>
        <div style="font-size:.55em;color:var(--faint);font-weight:500;">Founder · Nexbyte</div></div>
    </div>
    <div style="margin-top:.6em;display:flex;flex-direction:column;gap:.35em;font-size:.58em;color:var(--muted);font-weight:500;">
      <div>📞 +91 98••• ••210</div><div>✉️ rahul@nexbyte.io</div>
    </div>
    <div style="margin-top:.55em;"><span class="chip ok" style="font-size:.52em;">⬇ Save contact · one tap</span></div>
  </div>`
  );

/** Standee A hero: big chat phone with floating chips. */
const artHeroChat = (fontMm: number) =>
  art(
    fontMm,
    `
  <div style="position:relative;">
    ${artChatInner()}
    <div class="chip" style="position:absolute;top:-1.2em;right:-2.4em;font-size:.7em;transform:rotate(4deg);">🎙 Voice on</div>
    <div class="chip ok" style="position:absolute;bottom:2.2em;left:-3em;font-size:.7em;transform:rotate(-4deg);">✓ Order taken</div>
    <img src="${ASSET.mascot}" style="position:absolute;bottom:-1.4em;right:-2.6em;width:4.4em;height:4.4em;
      filter:drop-shadow(0 .4em 1em rgba(0,0,0,.5));" alt="">
  </div>`
  );

// chat phone markup shared by artChat/artHeroChat
function artChatInner(): string {
  return `
  <div class="ph">
    <div style="display:flex;align-items:center;gap:.55em;padding-bottom:.6em;border-bottom:.06em solid rgba(255,255,255,.12);">
      <img src="${ASSET.mascot}" style="width:1.7em;height:1.7em;" alt="">
      <div><div style="font-size:.78em;font-weight:700;">Zio · @maya</div>
        <div style="font-size:.58em;color:var(--mint);font-weight:600;">● online · answers 24/7</div></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:.55em;margin-top:.7em;">
      <div class="bub">Hi! Are you free for a shoot next week?</div>
      <div class="bub me">Thursday & Friday are open. Want the booking link?</div>
      <div class="bub">Yes! And your rates?</div>
      <div class="bub me">Sent 👇 Full price list + booking in one tap.</div>
      <div class="lrow" style="font-size:.66em;background:linear-gradient(150deg,rgba(61,107,255,.4),rgba(35,66,199,.3));">📅 Book a session · from $180</div>
    </div>
    <div style="margin-top:.75em;display:flex;align-items:center;gap:.45em;">
      <div class="gcard" style="flex:1;padding:.42em .65em;font-size:.62em;color:var(--faint);font-weight:500;">Ask me anything…</div>
      <div class="avatar" style="width:1.55em;height:1.55em;font-size:.72em;">🎙</div>
    </div>
  </div>`;
}

/** Standee B hero: stacked business cards. */
const artHeroBiz = (fontMm: number, qr: string) =>
  art(
    fontMm,
    `
  <div style="display:flex;flex-direction:column;gap:.9em;width:78%;">
    <div class="gcard" style="padding:.8em .9em;transform:rotate(-1.6deg);">
      <div class="tag">STOREFRONT</div>
      <div style="margin-top:.45em;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:.7em;font-weight:700;">Ceramic mug · sand</span><span class="chip" style="font-size:.55em;">₹799</span></div>
      <div style="margin-top:.4em;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:.7em;font-weight:700;">Tote · canvas</span><span class="chip" style="font-size:.55em;">₹499</span></div>
    </div>
    <div style="display:flex;gap:.9em;align-items:stretch;">
      <div class="qr-card" style="flex:none;width:6.4em;height:6.4em;border-radius:.9em;transform:rotate(2deg);
        box-shadow:0 .5em 1.5em rgba(0,0,0,.5),0 0 0 .3em rgba(61,107,255,.3);"><img src="${qr}" alt=""></div>
      <div class="gcard" style="flex:1;padding:.7em .8em;">
        <div class="tag">TODAY</div>
        <div class="bars" style="height:2.8em;margin-top:.5em;">
          ${[0.4, 0.7, 0.5, 0.9, 0.65, 1, 0.8].map((h) => `<i style="height:${h * 100}%;"></i>`).join("")}
        </div>
        <div style="margin-top:.4em;font-size:.6em;color:var(--muted);font-weight:600;">2,318 scans &amp; clicks</div>
      </div>
    </div>
    <div class="gcard" style="padding:.7em .9em;transform:rotate(1.2deg);display:flex;justify-content:space-between;align-items:center;">
      <div><div class="tag">PAYMENT RECEIVED</div>
        <div style="margin-top:.3em;font-size:1em;font-weight:700;">₹2,499 <span style="color:var(--mint);font-size:.7em;">✓</span></div></div>
      <span class="chip ok" style="font-size:.6em;">0% platform fee</span>
    </div>
  </div>`
  );

export function doc(bodyHtml: string, extraCss = ""): string {
  return `<!doctype html><html><head><meta charset="utf-8"><style>${BASE_CSS}${extraCss}</style></head><body>${bodyHtml}</body></html>`;
}

// Official white logo lockup (mascot + SAYZIO wordmark), height = imgMm.
export const wordmark = (imgMm: number, _fontMm = 0, _gapMm = 0) => `
  <div class="wordmark">
    <img src="${ASSET.logo}" style="height:${imgMm}mm;width:auto;" alt="Sayzio">
  </div>`;

// ---------- pieces ----------
type Piece = { file: string; trimW: number; trimH: number; html: () => Promise<string> };

// helper: full page div sized to bleed box, safe padding = bleed + safe margin
const page = (inner: string, padMm: number) =>
  `<div class="page" style="padding:${BLEED + padMm}mm;">${inner}</div>`;

// framed art tile with optional caption
export const vis = (inner: string, style: string) => `<div class="img-frame" style="${style}">${inner}</div>`;

/* ============ 1–2. Visiting card 3.5in x 2in (88.9 x 50.8mm) ============ */
// Shared card chrome: angled electric-blue band + accent hairline.
const CARD_CSS = `
.card-band{position:absolute;top:-8mm;bottom:-8mm;width:46mm;
  background:linear-gradient(165deg,#5c83ff 0%,#3d6bff 45%,#2342c7 100%);}
.card-band::after{content:'';position:absolute;inset:0;opacity:.35;background-image:
  radial-gradient(rgba(255,255,255,.5) .35mm, transparent .35mm);background-size:4mm 4mm;}
.card-hair{position:absolute;top:-8mm;bottom:-8mm;width:1.4mm;background:var(--sky);opacity:.85;}
.card-chip{display:flex;align-items:center;gap:2.2mm;font-size:2.7mm;font-weight:500;color:var(--muted);}
.card-chip .ci{flex:none;width:5.6mm;height:5.6mm;border-radius:1.8mm;display:flex;align-items:center;justify-content:center;
  font-size:2.8mm;background:linear-gradient(150deg,var(--blue),var(--blue-deep));color:#fff;}
`;

async function cardFront(): Promise<string> {
  return doc(
    `
  <div class="page" style="padding:0;">
    <div class="grid-tex" style="background-size:6mm 6mm;"></div>
    <div class="card-band" style="right:-14mm;transform:rotate(7deg);"></div>
    <div class="card-hair" style="right:31mm;transform:rotate(7deg);"></div>
    <img src="${ASSET.mark}" style="position:absolute;right:3mm;top:13mm;width:24mm;height:24mm;
      filter:drop-shadow(0 1.5mm 2.5mm rgba(4,8,20,.55));" alt="">
    <div style="position:absolute;left:${BLEED + 5}mm;top:${BLEED + 4.5}mm;right:38mm;bottom:${BLEED + 4.5}mm;
      display:flex;flex-direction:column;justify-content:space-between;">
      ${wordmark(6.5)}
      <div>
        <div style="font-size:5.6mm;font-weight:700;line-height:1.14;">Your link,<br>now it <span class="accent">talks back.</span></div>
        <div class="pill" style="margin-top:2.6mm;padding:.9mm 3mm;font-size:2.1mm;">AI-FIRST LINK PLATFORM</div>
      </div>
      <div>
        <div style="font-size:4mm;font-weight:700;">${CONTACT.founder}</div>
        <div style="font-size:2.4mm;color:var(--sky);font-weight:600;letter-spacing:.14em;margin-top:.6mm;">FOUNDER</div>
      </div>
    </div>
  </div>`,
    CARD_CSS
  );
}

async function cardBack(): Promise<string> {
  const profileUrl = "https://sayzio.app/sana";
  const qr = await qrSvg(profileUrl);
  return doc(
    `
  <div class="page" style="padding:0;">
    <div class="grid-tex" style="background-size:6mm 6mm;"></div>
    <div class="card-band" style="left:-16mm;transform:rotate(-7deg);"></div>
    <div class="card-hair" style="left:29mm;transform:rotate(-7deg);"></div>
    <div class="qr-card" style="position:absolute;left:5.5mm;top:50%;transform:translateY(-50%);width:27mm;height:27mm;
      box-shadow:0 2mm 5mm rgba(4,8,20,.5);border-radius:3mm;">
      <img src="${qr}" alt="QR: sayzio.app">
    </div>
    <div style="position:absolute;left:41mm;top:${BLEED + 4.5}mm;right:${BLEED + 5}mm;bottom:${BLEED + 4.5}mm;
      display:flex;flex-direction:column;justify-content:space-between;">
      <div>
        <div style="font-size:4.6mm;font-weight:700;line-height:1.18;">Scan it.<br>The link <span class="accent">answers.</span></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:2mm;">
        <div class="card-chip"><div class="ci">📞</div>${CONTACT.phone}</div>
        <div class="card-chip"><div class="ci">✉️</div>sana@sayzio.app</div>
        <div class="card-chip"><div class="ci">🌐</div><span style="color:var(--sky);font-weight:600;">${profileUrl}</span></div>
      </div>
    </div>
  </div>`,
    CARD_CSS
  );
}

/* ============ 3–4. Roll-up standees 33in x 78in (838.2 x 1981.2mm) ============ */
// Bottom ~200mm of a roll-up disappears into the cassette on some hardware; keep
// critical content in the upper 2/3 anyway (standee best practice).
function standeeShell(inner: string): string {
  return doc(
    `
  <div class="page" style="padding:${BLEED + 55}mm ${BLEED + 55}mm ${BLEED + 110}mm;">
    <div class="grid-tex" style="background-size:42mm 42mm;"></div>
    ${inner}
  </div>`,
    `.s-head{font-weight:700;letter-spacing:-.025em;line-height:1.04;}
     .s-item{display:flex;gap:20mm;align-items:flex-start;}
     .s-ic{flex:none;width:48mm;height:48mm;border-radius:14mm;display:flex;align-items:center;justify-content:center;
       font-size:23mm;font-weight:700;color:#fff;background:linear-gradient(150deg,var(--blue),var(--blue-deep));}
     .s-t{font-size:30mm;font-weight:700;}
     .s-d{margin-top:5mm;font-size:18.5mm;color:var(--muted);line-height:1.42;font-weight:500;}`
  );
}

async function standeeA(): Promise<string> {
  const qr = await qrSvg(QR_URLS.demoChat);
  return standeeShell(`
  <div style="position:relative;height:100%;display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;justify-content:space-between;">
      ${wordmark(42, 34, 10)}
      <div class="pill" style="padding:7mm 18mm;font-size:13mm;">AI-FIRST LINK PLATFORM</div>
    </div>
    <div style="margin-top:42mm;display:flex;gap:34mm;align-items:stretch;">
      <div style="flex:1;min-width:0;">
        <div class="s-head" style="font-size:78mm;">What if<br>your link<br>could <span class="accent">talk?</span></div>
        <div style="margin-top:24mm;font-size:20mm;color:var(--muted);line-height:1.45;font-weight:500;">
          Sayzio turns your link-in-bio into an AI concierge that chats, answers, sells and books while you sleep.</div>
      </div>
      <div style="flex:none;width:310mm;display:flex;flex-direction:column;gap:8mm;">
        ${vis(artHeroChat(22), "flex:1;")}
        <div class="img-cap" style="font-size:11mm;">Your page, mid-conversation with a visitor.</div>
      </div>
    </div>
    <div style="flex:1;display:flex;flex-direction:column;justify-content:space-evenly;margin:24mm 0;">
      <div class="s-item"><div class="s-ic">💬</div><div><div class="s-t">AI Chat Links</div><div class="s-d">Every link becomes a conversation. Visitors ask, your link answers, trained on your content, your products, your FAQ.</div></div></div>
      <div class="s-item"><div class="s-ic">🎙</div><div><div class="s-t">AI Voice</div><div class="s-d">Your page speaks. Voice answers for visitors who'd rather talk than tap.</div></div></div>
      <div class="s-item"><div class="s-ic">⚡</div><div><div class="s-t">AI Builder</div><div class="s-d">Prompt to published page in 60 seconds. Describe it and Zio builds it.</div></div></div>
      <div class="s-item"><div class="s-ic">📥</div><div><div class="s-t">AI Inbox</div><div class="s-d">Every DM, lead and order in one inbox, with AI drafting the replies.</div></div></div>
      <div class="s-item"><div class="s-ic">🧠</div><div><div class="s-t">AI Minds</div><div class="s-d">Ground every answer in your own knowledge base, so no made-up replies.</div></div></div>
    </div>
    <div style="display:flex;gap:16mm;margin-bottom:24mm;">
      <div style="flex:1;display:flex;flex-direction:column;gap:6mm;">
        ${vis(artVoice(14), "height:240mm;")}
        <div class="img-cap" style="font-size:11mm;">AI Voice: your page answers out loud.</div>
      </div>
      <div style="flex:1;display:flex;flex-direction:column;gap:6mm;">
        ${vis(artInbox(13), "height:240mm;")}
        <div class="img-cap" style="font-size:11mm;">AI Inbox: every channel, one queue.</div>
      </div>
      <div style="flex:1;display:flex;flex-direction:column;gap:6mm;">
        ${vis(artBuilder(9.5), "height:240mm;")}
        <div class="img-cap" style="font-size:11mm;">AI Builder: prompt in, page out.</div>
      </div>
    </div>
    <div class="glass" style="padding:24mm;display:flex;align-items:center;gap:28mm;">
      <div class="qr-card" style="width:170mm;height:170mm;flex:none;"><img src="${qr}" alt="QR: live AI chat demo"></div>
      <div>
        <div style="font-size:22mm;font-weight:700;line-height:1.2;">Scan. Say hi.<br>It answers.</div>
        <div style="margin-top:9mm;font-size:15mm;color:var(--sky);font-weight:600;">sayzio.app</div>
      </div>
      <img src="${ASSET.mascot}" style="margin-left:auto;width:165mm;height:165mm;" alt="">
    </div>
  </div>`);
}

async function standeeB(): Promise<string> {
  const qr = await qrSvg(QR_URLS.pricing);
  const qrDeco = await qrSvg(QR_URLS.home);
  return standeeShell(`
  <div style="position:relative;height:100%;display:flex;flex-direction:column;">
    ${wordmark(42, 34, 10)}
    <div style="margin-top:44mm;display:flex;gap:34mm;align-items:stretch;">
      <div style="flex:1;min-width:0;">
        <div class="s-head" style="font-size:50mm;color:var(--faint);">Linktree shows links.</div>
        <div class="s-head" style="margin-top:8mm;font-size:66mm;">Sayzio <span class="accent">does<br>business.</span></div>
        <div style="margin-top:22mm;font-size:19mm;color:var(--muted);line-height:1.45;font-weight:500;">
          Menus, stores, payments and analytics: one handle runs the whole operation.</div>
      </div>
      <div style="flex:none;width:300mm;display:flex;flex-direction:column;gap:8mm;">
        ${vis(artHeroBiz(19, qrDeco), "flex:1;")}
        <div class="img-cap" style="font-size:11mm;">Storefront · QR · analytics · payouts · one platform.</div>
      </div>
    </div>
    <div style="flex:1;display:flex;flex-direction:column;justify-content:space-evenly;margin:24mm 0;">
      <div class="s-item"><div class="s-ic">🍽</div><div><div class="s-t">Run a restaurant on one QR</div><div class="s-d">Menus, per-table QR ordering and a live orders dashboard. No app, no POS.</div></div></div>
      <div class="s-item"><div class="s-ic">💸</div><div><div class="s-t">Get paid with 0% creator fee</div><div class="s-d">Paid pages, storefronts and creator payouts. Your money stays yours.</div></div></div>
      <div class="s-item"><div class="s-ic">📊</div><div><div class="s-t">Know every click</div><div class="s-d">Live analytics with geographic heatmaps for every link, page and QR scan.</div></div></div>
      <div class="s-item"><div class="s-ic">🎨</div><div><div class="s-t">QR codes that look like art</div><div class="s-d">30+ templates, AI artistic QR, bulk generation, and every scan tracked.</div></div></div>
      <div class="s-item"><div class="s-ic">🧩</div><div><div class="s-t">A platform, not just a page</div><div class="s-d">Forms, workspaces, custom domains and integrations. Build on top of it.</div></div></div>
    </div>
    <div style="display:flex;gap:16mm;margin-bottom:24mm;">
      <div style="flex:1;display:flex;flex-direction:column;gap:6mm;">
        ${vis(artDashboard(13), "height:240mm;")}
        <div class="img-cap" style="font-size:11mm;">One dashboard for links, orders &amp; analytics.</div>
      </div>
      <div style="flex:1;display:flex;flex-direction:column;gap:6mm;">
        ${vis(artQrTiles(13, qrDeco), "height:240mm;")}
        <div class="img-cap" style="font-size:11mm;">QR Studio Pro: art that scans.</div>
      </div>
      <div style="flex:1;display:flex;flex-direction:column;gap:6mm;">
        ${vis(artPayouts(13), "height:240mm;")}
        <div class="img-cap" style="font-size:11mm;">Payouts land in your own accounts.</div>
      </div>
    </div>
    <div class="glass" style="padding:24mm;display:flex;align-items:center;gap:28mm;">
      <div class="qr-card" style="width:170mm;height:170mm;flex:none;"><img src="${qr}" alt="QR: pricing"></div>
      <div>
        <div style="font-size:22mm;font-weight:700;line-height:1.2;">Plans &amp; pricing.<br>Scan to compare.</div>
        <div style="margin-top:9mm;font-size:15mm;color:var(--sky);font-weight:600;">sayzio.app/pricing</div>
      </div>
      <img src="${ASSET.zioBot}" style="margin-left:auto;width:200mm;height:auto;" alt="">
    </div>
  </div>`);
}

/* ============ 5–6. Trifold A4 landscape (297 x 210mm), 6 panels ============ */
// Outside spread (page 1), panels left→right: [inside flap | back cover | front cover]
// Inside spread (page 2), panels left→right: [inside 1 | inside 2 | inside 3]
const TRIFOLD_CSS = `
.tri{position:relative;height:100%;display:flex;}
.panel{position:relative;width:99mm;height:100%;padding:12mm 9mm;display:flex;flex-direction:column;}
.fold{position:absolute;top:0;bottom:0;width:0;border-left:0.2mm dashed rgba(255,255,255,.10);}
.t-h{font-size:7.4mm;font-weight:700;line-height:1.12;letter-spacing:-.02em;}
.t-p{font-size:3.4mm;color:var(--muted);line-height:1.5;font-weight:500;}
.t-li{display:flex;gap:3mm;align-items:flex-start;margin-top:4mm;}
.t-ic{flex:none;width:7.5mm;height:7.5mm;border-radius:2.4mm;display:flex;align-items:center;justify-content:center;
  font-size:3.6mm;background:linear-gradient(150deg,var(--blue),var(--blue-deep));}
.t-lt{font-size:3.8mm;font-weight:700;}
.t-ld{font-size:3.1mm;color:var(--muted);line-height:1.45;margin-top:.8mm;}
`;

async function trifoldOutside(): Promise<string> {
  const qrHome = await qrSvg(QR_URLS.home);
  const qrDemo = await qrSvg(QR_URLS.demoChat);
  return doc(
    `
  <div class="page" style="padding:${BLEED}mm;">
    <div class="grid-tex" style="background-size:12mm 12mm;"></div>
    <div class="tri">
      <!-- inside flap: the pitch -->
      <div class="panel">
        <div class="t-h" style="font-size:6mm;">Built for founders.<br>Loved by <span class="accent">creators.</span></div>
        <p class="t-p" style="margin-top:3.5mm;">Sayzio is the AI-first link platform: one handle that carries your bio page, short links, QR codes, forms, storefront and analytics. On top sits an AI layer that talks to your audience for you.</p>
        <div class="t-li"><div class="t-ic">🏪</div><div><div class="t-lt">20+ link types</div><div class="t-ld">Biolinks, restaurant menus, stores, events, resumes, paid pages and more.</div></div></div>
        <div class="t-li"><div class="t-ic">🛡</div><div><div class="t-lt">Your brand, your domain</div><div class="t-ld">Custom domains, custom CSS and PWA support. White-label ready.</div></div></div>
        <div class="t-li"><div class="t-ic">👥</div><div><div class="t-lt">Workspaces for teams</div><div class="t-ld">Shared links, roles and one dashboard for the whole crew.</div></div></div>
        <div style="display:flex;gap:3mm;margin-top:4mm;">
          ${vis(artWorkspace(2.3), "flex:1;height:26mm;")}
          ${vis(artDashboard(2.2), "flex:1;height:26mm;")}
        </div>
        <div class="img-cap" style="font-size:2.6mm;margin-top:1.4mm;">Workspaces for teams · one dashboard for everything.</div>
        <div style="margin-top:auto;display:flex;align-items:center;gap:4mm;" class="glass">
          <div style="padding:3.5mm;display:flex;align-items:center;gap:4mm;">
            <div class="qr-card" style="width:22mm;height:22mm;flex:none;"><img src="${qrDemo}" alt="QR: live AI chat demo"></div>
            <div class="t-p" style="font-size:3mm;">Scan for a live demo.<br>Talk to a Sayzio link.</div>
          </div>
        </div>
      </div>
      <div class="fold" style="left:99mm;"></div>
      <!-- back cover -->
      <div class="panel" style="align-items:center;text-align:center;">
        <img src="${ASSET.mascot}" style="margin-top:2mm;width:26mm;height:26mm;" alt="">
        <div style="margin-top:3.5mm;font-size:5.4mm;font-weight:700;">Meet Zio.</div>
        <p class="t-p" style="margin-top:2.5mm;max-width:74mm;">The AI behind every Sayzio link. It chats, answers, sells and books, so your link keeps working even when you don't.</p>
        ${vis(artChat(2.6), "margin-top:4mm;width:100%;height:52mm;")}
        <div class="img-cap" style="font-size:2.6mm;margin-top:1.4mm;">A Sayzio page mid-conversation with a visitor.</div>
        <div class="qr-card" style="margin-top:5mm;width:26mm;height:26mm;"><img src="${qrHome}" alt="QR: sayzio.app"></div>
        <div style="margin-top:2.5mm;font-size:3.4mm;color:var(--sky);font-weight:600;">sayzio.app</div>
        <div style="margin-top:auto;font-size:2.7mm;color:var(--muted);font-weight:500;line-height:1.6;">
          📞 ${CONTACT.phone} · ✉️ ${CONTACT.email}<br>🌐 https://${CONTACT.site}/
        </div>
      </div>
      <div class="fold" style="left:198mm;"></div>
      <!-- front cover -->
      <div class="panel" style="justify-content:space-between;">
        ${wordmark(10)}
        <div>
          <div class="pill" style="padding:1.6mm 4mm;font-size:2.9mm;">AI-FIRST LINK PLATFORM</div>
          <div class="t-h" style="margin-top:4mm;font-size:10.5mm;">Your link,<br>now it<br><span class="accent">talks back.</span></div>
          ${vis(artBiolink(4.6), "margin-top:5mm;height:60mm;")}
          <p class="t-p" style="margin-top:4mm;">One link. Every channel. An AI concierge that never sleeps.</p>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:flex-end;">
          <div style="font-size:3.4mm;font-weight:600;color:var(--sky);">sayzio.app</div>
          <img src="${ASSET.mark}" style="width:14mm;height:14mm;opacity:.9;" alt="">
        </div>
      </div>
    </div>
  </div>`,
    TRIFOLD_CSS
  );
}

async function trifoldInside(): Promise<string> {
  const qrPricing = await qrSvg(QR_URLS.pricing);
  return doc(
    `
  <div class="page" style="padding:${BLEED}mm;">
    <div class="grid-tex" style="background-size:12mm 12mm;"></div>
    <div class="tri">
      <div class="panel">
        <div class="t-h" style="font-size:5.6mm;">The AI layer</div>
        <div style="display:flex;gap:3mm;margin-top:3mm;">
          ${vis(artVoice(2.5), "flex:1;height:29mm;")}
          ${vis(artInbox(2.4), "flex:1;height:29mm;")}
        </div>
        <div class="img-cap" style="font-size:2.6mm;margin-top:1.2mm;">AI Voice live on a page · AI Inbox with every channel.</div>
        <div class="t-li"><div class="t-ic">💬</div><div><div class="t-lt">AI Chat Links</div><div class="t-ld">Turn any link into a conversation, trained on your content and products.</div></div></div>
        <div class="t-li"><div class="t-ic">🎙</div><div><div class="t-lt">AI Voice</div><div class="t-ld">Pages that answer out loud. Speech in, speech out.</div></div></div>
        <div class="t-li"><div class="t-ic">⚡</div><div><div class="t-lt">AI Builder</div><div class="t-ld">Describe your page, get a published biolink in about 60 seconds.</div></div></div>
        <div class="t-li"><div class="t-ic">📥</div><div><div class="t-lt">AI Inbox</div><div class="t-ld">Leads, DMs and orders in one place, with AI-drafted replies and autopilot guardrails.</div></div></div>
        <div class="t-li"><div class="t-ic">🧠</div><div><div class="t-lt">AI Minds</div><div class="t-ld">Ground every AI feature in your own knowledge base. No made-up answers.</div></div></div>
        ${vis(artBuilder(1.9), "margin-top:auto;height:24mm;")}
        <div class="img-cap" style="font-size:2.6mm;margin-top:1.2mm;">AI Builder: one prompt in, one live page out.</div>
      </div>
      <div class="fold" style="left:99mm;"></div>
      <div class="panel">
        <div class="t-h" style="font-size:5.6mm;">The business layer</div>
        <div style="display:flex;gap:3mm;margin-top:3mm;">
          ${vis(artOrders(2.4), "flex:1;height:29mm;")}
          ${vis(artVcard(2.5), "flex:1;height:29mm;")}
        </div>
        <div class="img-cap" style="font-size:2.6mm;margin-top:1.2mm;">Live orders dashboard · digital business card.</div>
        <div class="t-li"><div class="t-ic">🍽</div><div><div class="t-lt">Restaurant &amp; store QR</div><div class="t-ld">Per-table ordering, live order dashboards, coupons and tax, all on one QR.</div></div></div>
        <div class="t-li"><div class="t-ic">💸</div><div><div class="t-lt">0% creator fee payouts</div><div class="t-ld">Paid pages, subscriptions and storefronts via Stripe, PayPal, Razorpay &amp; more.</div></div></div>
        <div class="t-li"><div class="t-ic">🎨</div><div><div class="t-lt">QR Studio Pro</div><div class="t-ld">30+ designer templates, AI artistic QR, bulk CSV to ZIP, scan analytics.</div></div></div>
        <div class="t-li"><div class="t-ic">📝</div><div><div class="t-lt">Forms &amp; subscribers</div><div class="t-ld">21 field types, payments in forms, email + WhatsApp subscriber lists.</div></div></div>
        <div class="t-li"><div class="t-ic">📇</div><div><div class="t-lt">Digital cards &amp; dialer</div><div class="t-ld">vCards, contact sync and a smart dialer that resolves numbers to pages.</div></div></div>
        ${vis(artRestaurant(2.1), "margin-top:auto;height:24mm;")}
        <div class="img-cap" style="font-size:2.6mm;margin-top:1.2mm;">Scan at the table, order in seconds.</div>
      </div>
      <div class="fold" style="left:198mm;"></div>
      <div class="panel">
        <div class="t-h" style="font-size:5.6mm;">The growth layer</div>
        <div style="display:flex;gap:3mm;margin-top:3mm;">
          ${vis(artMap(2.6), "flex:1;height:29mm;")}
          ${vis(artPayouts(2.3), "flex:1;height:29mm;")}
        </div>
        <div class="img-cap" style="font-size:2.6mm;margin-top:1.2mm;">Click heatmaps by city · payouts to your own accounts.</div>
        <div class="t-li"><div class="t-ic">📊</div><div><div class="t-lt">Analytics heatmaps</div><div class="t-ld">See every click and scan on a live world map, per link and per page.</div></div></div>
        <div class="t-li"><div class="t-ic">🎯</div><div><div class="t-lt">Pixels &amp; retargeting</div><div class="t-ld">Facebook, Google, TikTok, LinkedIn and more on every short link.</div></div></div>
        <div class="t-li"><div class="t-ic">📱</div><div><div class="t-lt">Zio Dialer &amp; extension</div><div class="t-ld">A smart dialer on your phone and a browser extension in your tab, both in sync.</div></div></div>
        <div class="t-li"><div class="t-ic">🔔</div><div><div class="t-lt">Social proof widgets</div><div class="t-ld">Reviews walls and notification popups that turn visits into trust.</div></div></div>
        <div style="margin-top:auto;" class="glass">
          <div style="padding:5mm;display:flex;align-items:center;gap:4mm;">
            <div class="qr-card" style="width:24mm;height:24mm;flex:none;"><img src="${qrPricing}" alt="QR: pricing"></div>
            <div>
              <div class="t-lt">Start free.</div>
              <div class="t-ld">Scan for plans &amp; pricing.<br><span style="color:var(--sky);font-weight:600;">sayzio.app/pricing</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>`,
    TRIFOLD_CSS
  );
}

/* ============ 7–12. A3 boards 297 x 420mm portrait ============ */
const BOARD_CSS = `
.b-kick{font-size:7mm;font-weight:700;letter-spacing:.22em;color:var(--sky);}
.b-h{font-size:26mm;font-weight:700;letter-spacing:-.025em;line-height:1.04;margin-top:8mm;}
.b-p{font-size:7.4mm;color:var(--muted);line-height:1.5;font-weight:500;margin-top:9mm;}
.b-li{display:flex;gap:7mm;align-items:flex-start;margin-top:9mm;}
.b-ic{flex:none;width:14mm;height:14mm;border-radius:4.5mm;display:flex;align-items:center;justify-content:center;
  font-size:7mm;background:linear-gradient(150deg,var(--blue),var(--blue-deep));}
.b-lt{font-size:7.6mm;font-weight:700;}
.b-ld{font-size:5.8mm;color:var(--muted);line-height:1.45;margin-top:1.6mm;}
.b-foot{margin-top:auto;display:flex;align-items:center;gap:10mm;}
.b-qr{width:62mm;height:62mm;flex:none;}
.b-cta{font-size:8mm;font-weight:700;line-height:1.25;}
.b-url{margin-top:3mm;font-size:6mm;color:var(--sky);font-weight:600;}
.b-stat{text-align:center;padding:8mm 4mm;}
.b-sv{font-size:15mm;font-weight:700;color:var(--blue-soft);}
.b-sl{font-size:4.6mm;color:var(--muted);margin-top:1.5mm;font-weight:500;}
`;

function board(inner: string): string {
  return doc(
    `
  <div class="page" style="padding:${BLEED + 16}mm;">
    <div class="grid-tex" style="background-size:20mm 20mm;"></div>
    <div style="position:relative;height:100%;display:flex;flex-direction:column;">
      ${wordmark(14, 11, 3.5)}
      ${inner}
    </div>
  </div>`,
    BOARD_CSS
  );
}

async function board1(): Promise<string> {
  const qr = await qrSvg(QR_URLS.demoChat);
  return board(`
    <div style="margin-top:16mm;" class="b-kick">LIVE DEMO</div>
    <div class="b-h">Talk to<br>this <span class="accent">poster.</span></div>
    <p class="b-p">This QR opens a real Sayzio AI chat link. Ask it anything about Sayzio: pricing, features, how it works. It answers like a founder who never sleeps.</p>
    <div style="display:flex;gap:10mm;margin-top:10mm;align-items:stretch;flex:1;min-height:0;margin-bottom:10mm;">
      <div style="flex:1;min-width:0;">
        <div class="b-li" style="margin-top:0;"><div class="b-ic">1</div><div><div class="b-lt">Scan the code</div><div class="b-ld">No app, no signup. It opens in your browser.</div></div></div>
        <div class="b-li"><div class="b-ic">2</div><div><div class="b-lt">Ask a question</div><div class="b-ld">Type or talk. "What does Sayzio cost?" is a classic.</div></div></div>
        <div class="b-li"><div class="b-ic">3</div><div><div class="b-lt">Imagine it's yours</div><div class="b-ld">Your content, your products, your voice, answering 24/7.</div></div></div>
        <div class="b-li"><div class="b-ic">🧠</div><div><div class="b-lt">Grounded, not guessing</div><div class="b-ld">Answers come from a knowledge base you control.</div></div></div>
      </div>
      <div style="flex:none;width:96mm;display:flex;flex-direction:column;gap:4mm;">
        ${vis(artChat(7.2), "flex:1;")}
        ${vis(artVoice(4.4), "height:44mm;flex:none;")}
        <div class="img-cap" style="font-size:4.2mm;">Chat or voice: your link answers both.</div>
      </div>
    </div>
    <div class="b-foot">
      <div class="qr-card b-qr"><img src="${qr}" alt="QR: live AI chat demo"></div>
      <div><div class="b-cta">Go on.<br>Say hi.</div><div class="b-url">sayzio.app</div></div>
      <img src="${ASSET.mascot}" style="margin-left:auto;width:58mm;height:58mm;" alt="">
    </div>`);
}

async function board2(): Promise<string> {
  const qr = await qrSvg(QR_URLS.home);
  return board(`
    <div style="margin-top:16mm;" class="b-kick">AI BUILDER</div>
    <div class="b-h">Prompt to page<br>in <span class="accent">60 seconds.</span></div>
    <p class="b-p">Type one sentence, like "a page for my bakery with a menu, hours and WhatsApp ordering", and Zio designs, writes and publishes the whole biolink.</p>
    <div style="display:flex;gap:10mm;margin-top:10mm;align-items:stretch;">
      <div style="flex:1;min-width:0;">
        <div class="glass" style="padding:9mm;">
          <div style="font-size:5.4mm;color:var(--sky);font-weight:600;">You type:</div>
          <div style="font-size:6.4mm;font-weight:500;margin-top:2.5mm;line-height:1.4;">"Landing page for my SaaS beta: waitlist form, three features, dark theme."</div>
          <div style="font-size:5.4mm;color:var(--mint);font-weight:600;margin-top:6mm;">Zio ships:</div>
          <div style="font-size:6.4mm;font-weight:500;margin-top:2.5mm;line-height:1.4;">Hero + copy · feature blocks · working form · your brand colors · live URL.</div>
        </div>
        <div class="b-li"><div class="b-ic">✨</div><div><div class="b-lt">On-brand by default</div><div class="b-ld">Brand kit colors, fonts and tone applied automatically.</div></div></div>
        <div class="b-li"><div class="b-ic">🖼</div><div><div class="b-lt">Bring your assets</div><div class="b-ld">Add photos and links to the prompt and Zio places them for you.</div></div></div>
      </div>
      <div style="flex:none;width:96mm;display:flex;flex-direction:column;gap:4mm;">
        ${vis(artBuilder(5.4), "flex:1;")}
        ${vis(artBiolink(3.4), "height:44mm;flex:none;")}
        <div class="img-cap" style="font-size:4.2mm;">One prompt in, one live page out.</div>
      </div>
    </div>
    <div class="b-foot">
      <div class="qr-card b-qr"><img src="${qr}" alt="QR: sayzio.app"></div>
      <div><div class="b-cta">Try your first<br>prompt today.</div><div class="b-url">sayzio.app</div></div>
    </div>`);
}

async function board3(): Promise<string> {
  const qr = await qrSvg(QR_URLS.demos);
  return board(`
    <div style="margin-top:16mm;" class="b-kick">COMMERCE</div>
    <div class="b-h">A restaurant runs<br>on <span class="accent">one QR.</span></div>
    <p class="b-p">Menu, per-table ordering and a live kitchen dashboard. No app to install, no POS contract, no per-order commission.</p>
    <div style="display:flex;gap:10mm;margin-top:10mm;align-items:stretch;">
      <div style="flex:1;min-width:0;">
        <div class="b-li" style="margin-top:0;"><div class="b-ic">🪑</div><div><div class="b-lt">Per-table QR codes</div><div class="b-ld">Each table gets its own code; orders arrive tagged with the table.</div></div></div>
        <div class="b-li"><div class="b-ic">🧾</div><div><div class="b-lt">Live itemized bill</div><div class="b-ld">Coupons and GST handled; guests see the estimate as they order.</div></div></div>
        <div class="b-li"><div class="b-ic">🔔</div><div><div class="b-lt">Kitchen dashboard</div><div class="b-ld">Pending → preparing → served, updating in near real time.</div></div></div>
        <div class="b-li"><div class="b-ic">🏪</div><div><div class="b-lt">Works for retail too</div><div class="b-ld">Store mode: products, order requests and WhatsApp handoff.</div></div></div>
      </div>
      <div style="flex:none;width:96mm;display:flex;flex-direction:column;gap:4mm;">
        ${vis(artRestaurant(7.4), "flex:1;")}
        ${vis(artOrders(4.2), "height:44mm;flex:none;")}
        <div class="img-cap" style="font-size:4.2mm;">Scan at the table and orders hit your dashboard live.</div>
      </div>
    </div>
    <div class="b-foot">
      <div class="qr-card b-qr"><img src="${qr}" alt="QR: demos"></div>
      <div><div class="b-cta">See the demo<br>menus live.</div><div class="b-url">sayzio.app/demos</div></div>
    </div>`);
}

async function board4(): Promise<string> {
  const qr = await qrSvg(QR_URLS.pricing);
  return board(`
    <div style="margin-top:16mm;" class="b-kick">GROWTH</div>
    <div class="b-h">Every click counted.<br><span class="accent">0%</span> creator fee.</div>
    <p class="b-p">Live analytics with world heatmaps on every link, page and QR scan. And when you sell, the platform takes nothing.</p>
    <div style="display:flex;gap:8mm;margin-top:10mm;">
      <div class="glass b-stat" style="flex:1;"><div class="b-sv">0%</div><div class="b-sl">platform fee on creator earnings</div></div>
      <div class="glass b-stat" style="flex:1;"><div class="b-sv">Live</div><div class="b-sl">click &amp; scan heatmaps</div></div>
      <div class="glass b-stat" style="flex:1;"><div class="b-sv">9+</div><div class="b-sl">retargeting pixels per link</div></div>
    </div>
    <div style="display:flex;gap:6mm;margin-top:10mm;align-items:stretch;">
      <div style="flex:1;display:flex;flex-direction:column;gap:3mm;">
        ${vis(artMap(5.4), "flex:1;min-height:54mm;")}
        <div class="img-cap" style="font-size:4mm;">Clicks light up city by city.</div>
      </div>
      <div style="flex:1;display:flex;flex-direction:column;gap:3mm;">
        ${vis(artPayouts(4.4), "flex:1;min-height:54mm;")}
        <div class="img-cap" style="font-size:4mm;">Earnings flow straight to you.</div>
      </div>
      <div style="flex:1;display:flex;flex-direction:column;gap:3mm;">
        ${vis(artWorkspace(4.4), "flex:1;min-height:54mm;")}
        <div class="img-cap" style="font-size:4mm;">Workspaces keep teams in sync.</div>
      </div>
    </div>
    <div class="b-li"><div class="b-ic">💳</div><div><div class="b-lt">Payouts your way</div><div class="b-ld">Stripe, PayPal, Razorpay and more. Hosted onboarding, your account.</div></div></div>
    <div class="b-foot">
      <div class="qr-card b-qr"><img src="${qr}" alt="QR: pricing"></div>
      <div><div class="b-cta">Compare plans.<br>Start free.</div><div class="b-url">sayzio.app/pricing</div></div>
    </div>`);
}

async function board5(): Promise<string> {
  const qr = await qrSvg(QR_URLS.home);
  return board(`
    <div style="margin-top:16mm;" class="b-kick">QR STUDIO PRO</div>
    <div class="b-h">QR codes that<br>look like <span class="accent">art.</span></div>
    <p class="b-p">Stop printing black-and-white squares. Design QR codes people actually want to scan, and see exactly when and where every scan happens.</p>
    <div style="display:flex;gap:10mm;margin-top:10mm;align-items:stretch;">
      <div style="flex:1;min-width:0;">
        <div class="b-li" style="margin-top:0;"><div class="b-ic">🎨</div><div><div class="b-lt">30+ designer templates</div><div class="b-ld">Gradients, frames, per-corner eye styling and your logo in the middle.</div></div></div>
        <div class="b-li"><div class="b-ic">🖼</div><div><div class="b-lt">AI artistic QR</div><div class="b-ld">Turn a code into artwork that still scans, checked before you download.</div></div></div>
        <div class="b-li"><div class="b-ic">📦</div><div><div class="b-lt">Bulk generation</div><div class="b-ld">Upload a CSV, download a ZIP of finished codes for every row.</div></div></div>
        <div class="b-li"><div class="b-ic">📊</div><div><div class="b-lt">Every scan tracked</div><div class="b-ld">Attach a trackable link and watch scans on a live map.</div></div></div>
      </div>
      <div style="flex:none;width:96mm;display:flex;flex-direction:column;gap:4mm;">
        ${vis(artQrTiles(6.6, qr), "flex:1;")}
        ${vis(artMap(4.0), "height:44mm;flex:none;")}
        <div class="img-cap" style="font-size:4.2mm;">Designer codes on top, live scan map below.</div>
      </div>
    </div>
    <div class="b-foot">
      <div class="qr-card b-qr"><img src="${qr}" alt="QR: sayzio.app"></div>
      <div><div class="b-cta">This one was<br>made in Studio.</div><div class="b-url">sayzio.app</div></div>
    </div>`);
}

async function board6(): Promise<string> {
  const qr = await qrSvg(QR_URLS.home);
  return board(`
    <div style="margin-top:14mm;" class="b-kick">THE ECOSYSTEM</div>
    <div class="b-h" style="font-size:23mm;">One platform.<br><span class="accent">Three apps.</span></div>
    <p class="b-p" style="margin-top:7mm;">Sayzio follows you everywhere you work: the full web platform, the Zio Dialer on your phone, and a browser extension one click away.</p>
    <div style="display:flex;gap:8mm;margin-top:8mm;align-items:flex-start;">
      <div style="flex:1;min-width:0;">
        <div class="b-li" style="margin-top:0;"><div class="b-ic">🖥</div><div><div class="b-lt">The main app</div><div class="b-ld">Web + mobile: build biolinks, run stores and menus, watch analytics, manage your inbox. The whole business in one dashboard.</div></div></div>
        <div class="b-li" style="margin-top:6mm;"><div class="b-ic">📞</div><div><div class="b-lt">Zio Dialer</div><div class="b-ld">A smart phone dialer with caller ID, T9 search, contact sync, and every number resolved to its Sayzio page.</div></div></div>
        <div class="b-li" style="margin-top:6mm;"><div class="b-ic">🧩</div><div><div class="b-lt">Browser extension</div><div class="b-ld">Shorten the page you're on, mint a designer QR and copy the link, without leaving the tab.</div></div></div>
        <div class="b-li" style="margin-top:6mm;"><div class="b-ic">🔄</div><div><div class="b-lt">Always in sync</div><div class="b-ld">One account, one handle. Links, contacts and analytics stay identical across all three.</div></div></div>
      </div>
      <div style="flex:none;width:92mm;display:flex;flex-direction:column;gap:3mm;">
        ${vis(artDialer(6.4), "height:88mm;")}
        <div class="img-cap" style="font-size:4.2mm;">Zio Dialer: numbers become pages.</div>
      </div>
    </div>
    <div style="display:flex;gap:6mm;margin-top:6mm;margin-bottom:8mm;">
      <div style="flex:1;display:flex;flex-direction:column;gap:3mm;">
        ${vis(artDashboard(3.8), "height:38mm;")}
        <div class="img-cap" style="font-size:4mm;">The main app: your command center.</div>
      </div>
      <div style="flex:1;display:flex;flex-direction:column;gap:3mm;">
        ${vis(artExtension(3.6), "height:38mm;")}
        <div class="img-cap" style="font-size:4mm;">The extension: links from any tab.</div>
      </div>
    </div>
    <div class="b-foot">
      <div class="qr-card b-qr"><img src="${qr}" alt="QR: sayzio.app"></div>
      <div><div class="b-cta">One account.<br>Every screen.</div><div class="b-url">sayzio.app</div></div>
      <img src="${ASSET.zioBot}" style="margin-left:auto;width:52mm;height:auto;" alt="">
    </div>`);
}

async function board7(): Promise<string> {
  const qr = await qrSvg(QR_URLS.home);
  return board(`
    <div style="margin-top:16mm;" class="b-kick">FORMS &amp; LEADS</div>
    <div class="b-h">From scan<br>to <span class="accent">signed up.</span></div>
    <p class="b-p">Every Sayzio page can capture leads on the spot: forms with 21 field types, payments inside the form, and subscriber lists that grow while you sleep.</p>
    <div style="display:flex;gap:10mm;margin-top:10mm;align-items:stretch;">
      <div style="flex:1;min-width:0;">
        <div class="b-li" style="margin-top:0;"><div class="b-ic">📝</div><div><div class="b-lt">21 field types</div><div class="b-ld">Text, files, ratings, signatures, repeat groups and more.</div></div></div>
        <div class="b-li"><div class="b-ic">💳</div><div><div class="b-lt">Payments in forms</div><div class="b-ld">Charge per submission or per field. Bookings, orders, donations.</div></div></div>
        <div class="b-li"><div class="b-ic">📣</div><div><div class="b-lt">Email + WhatsApp lists</div><div class="b-ld">Collect subscribers from any biolink, export or message them anytime.</div></div></div>
        <div class="b-li"><div class="b-ic">🔔</div><div><div class="b-lt">Instant notifications</div><div class="b-ld">Email, SMS or webhook the moment a lead lands.</div></div></div>
      </div>
      <div style="flex:none;width:96mm;display:flex;flex-direction:column;gap:4mm;">
        ${vis(artForms(6.6), "flex:1;")}
        ${vis(artInbox(4.0), "height:44mm;flex:none;")}
        <div class="img-cap" style="font-size:4.2mm;">Build the form, watch leads land in the inbox.</div>
      </div>
    </div>
    <div class="b-foot">
      <div class="qr-card b-qr"><img src="${qr}" alt="QR: sayzio.app"></div>
      <div><div class="b-cta">Build your first<br>form free.</div><div class="b-url">sayzio.app</div></div>
    </div>`);
}

async function board8(): Promise<string> {
  const qr = await qrSvg(QR_URLS.home);
  return board(`
    <div style="margin-top:16mm;" class="b-kick">DIGITAL CARDS</div>
    <div class="b-h">Paper cards get lost.<br><span class="accent">This one calls back.</span></div>
    <p class="b-p">A digital business card that saves straight to any phone, plus the Zio Dialer that turns every number in your contacts into a live Sayzio page.</p>
    <div style="display:flex;gap:10mm;margin-top:10mm;align-items:stretch;">
      <div style="flex:1;min-width:0;">
        <div class="b-li" style="margin-top:0;"><div class="b-ic">📇</div><div><div class="b-lt">Full vCard, one QR</div><div class="b-ld">Numbers, emails, socials and addresses saved in one tap.</div></div></div>
        <div class="b-li"><div class="b-ic">🔄</div><div><div class="b-lt">Two-way contact sync</div><div class="b-ld">Google Contacts stays in step, automatically, every 30 minutes.</div></div></div>
        <div class="b-li"><div class="b-ic">📞</div><div><div class="b-lt">Numbers become pages</div><div class="b-ld">The dialer resolves callers to their Sayzio page with T9 smart search.</div></div></div>
        <div class="b-li"><div class="b-ic">🔍</div><div><div class="b-lt">One universal search</div><div class="b-ld">Contacts, people, links and workspaces from a single search bar.</div></div></div>
      </div>
      <div style="flex:none;width:96mm;display:flex;flex-direction:column;gap:4mm;">
        ${vis(artVcard(6.8), "flex:1;")}
        ${vis(artDialer(4.0), "height:44mm;flex:none;")}
        <div class="img-cap" style="font-size:4.2mm;">Your card up top, the Zio Dialer below.</div>
      </div>
    </div>
    <div class="b-foot">
      <div class="qr-card b-qr"><img src="${qr}" alt="QR: sayzio.app"></div>
      <div><div class="b-cta">Retire the<br>paper stack.</div><div class="b-url">sayzio.app</div></div>
    </div>`);
}

async function board9(): Promise<string> {
  const qr = await qrSvg(QR_URLS.demos);
  return board(`
    <div style="margin-top:16mm;" class="b-kick">LINK TYPES</div>
    <div class="b-h">One handle.<br><span class="accent">20+ kinds</span> of link.</div>
    <p class="b-p">A Sayzio link can be a bio page, a menu, a store, a resume, an event, a paid page or a talking AI companion. Same handle, different superpower.</p>
    <div style="display:flex;gap:8mm;margin-top:10mm;">
      <div class="glass b-stat" style="flex:1;"><div class="b-sv">20+</div><div class="b-sl">link types on one handle</div></div>
      <div class="glass b-stat" style="flex:1;"><div class="b-sv">1</div><div class="b-sl">QR to reach them all</div></div>
      <div class="glass b-stat" style="flex:1;"><div class="b-sv">60s</div><div class="b-sl">from prompt to live page</div></div>
    </div>
    <div style="display:flex;gap:10mm;margin-top:10mm;align-items:stretch;">
      <div style="flex:1;min-width:0;">
        <div class="b-li" style="margin-top:0;"><div class="b-ic">🪪</div><div><div class="b-lt">Resume &amp; portfolio</div><div class="b-ld">A resume builder with AI tailoring, shared as a simple link.</div></div></div>
        <div class="b-li"><div class="b-ic">🎟</div><div><div class="b-lt">Events &amp; calendars</div><div class="b-ld">Event pages, followable calendars and add-to-calendar links.</div></div></div>
        <div class="b-li"><div class="b-ic">🔒</div><div><div class="b-lt">Paid &amp; gated pages</div><div class="b-ld">Content for followers, subscribers or paying fans only.</div></div></div>
      </div>
      <div style="flex:none;width:96mm;display:flex;flex-direction:column;gap:4mm;">
        ${vis(artBiolink(5.2), "flex:1;")}
        <div class="img-cap" style="font-size:4.2mm;">One biolink, endless layouts.</div>
      </div>
    </div>
    <div class="b-foot">
      <div class="qr-card b-qr"><img src="${qr}" alt="QR: demos"></div>
      <div><div class="b-cta">Browse live demos<br>of every type.</div><div class="b-url">sayzio.app/demos</div></div>
    </div>`);
}

async function board10(): Promise<string> {
  const qr = await qrSvg(QR_URLS.demoChat);
  return board(`
    <div style="margin-top:16mm;" class="b-kick">AI INBOX</div>
    <div class="b-h">Never miss<br>a <span class="accent">lead</span> again.</div>
    <p class="b-p">DMs, form leads, orders and subscriber messages land in one queue, and the AI drafts the reply before you even open it.</p>
    <div style="display:flex;gap:10mm;margin-top:10mm;align-items:stretch;">
      <div style="flex:1;min-width:0;">
        <div class="b-li" style="margin-top:0;"><div class="b-ic">📥</div><div><div class="b-lt">Every channel, one queue</div><div class="b-ld">Page messages, leads and orders in a single inbox.</div></div></div>
        <div class="b-li"><div class="b-ic">✍️</div><div><div class="b-lt">AI-drafted replies</div><div class="b-ld">On-brand drafts grounded in your own knowledge base.</div></div></div>
        <div class="b-li"><div class="b-ic">🛡</div><div><div class="b-lt">Autopilot with guardrails</div><div class="b-ld">Routine replies go out alone; anything sensitive waits for you.</div></div></div>
        <div class="b-li"><div class="b-ic">🔗</div><div><div class="b-lt">Answers that sell</div><div class="b-ld">Replies can reference your live links, products and pages.</div></div></div>
      </div>
      <div style="flex:none;width:96mm;display:flex;flex-direction:column;gap:4mm;">
        ${vis(artInbox(6.4), "flex:1;")}
        ${vis(artChat(4.2), "height:44mm;flex:none;")}
        <div class="img-cap" style="font-size:4.2mm;">One queue in, AI-drafted replies out.</div>
      </div>
    </div>
    <div class="b-foot">
      <div class="qr-card b-qr"><img src="${qr}" alt="QR: live AI chat demo"></div>
      <div><div class="b-cta">Message the demo,<br>watch it reply.</div><div class="b-url">sayzio.app</div></div>
    </div>`);
}

async function board11(): Promise<string> {
  const qr = await qrSvg(QR_URLS.pricing);
  return board(`
    <div style="margin-top:16mm;" class="b-kick">TEAMS</div>
    <div class="b-h">Built for teams<br>that <span class="accent">ship.</span></div>
    <p class="b-p">Agencies, brands and startups run every client and campaign from shared workspaces, with one dashboard telling the whole story.</p>
    <div style="display:flex;gap:10mm;margin-top:10mm;align-items:stretch;">
      <div style="flex:1;min-width:0;">
        <div class="b-li" style="margin-top:0;"><div class="b-ic">👥</div><div><div class="b-lt">Shared workspaces</div><div class="b-ld">Every client or campaign gets its own space, links and analytics.</div></div></div>
        <div class="b-li"><div class="b-ic">🔐</div><div><div class="b-lt">Roles &amp; permissions</div><div class="b-ld">Decide who can view, edit or publish, per workspace.</div></div></div>
        <div class="b-li"><div class="b-ic">🌐</div><div><div class="b-lt">Client-ready branding</div><div class="b-ld">Custom domains and white-label pages for every account.</div></div></div>
        <div class="b-li"><div class="b-ic">📊</div><div><div class="b-lt">One rollup dashboard</div><div class="b-ld">Links, orders and analytics for the whole team in one view.</div></div></div>
      </div>
      <div style="flex:none;width:96mm;display:flex;flex-direction:column;gap:4mm;">
        ${vis(artWorkspace(6.4), "flex:1;")}
        ${vis(artDashboard(4.0), "height:44mm;flex:none;")}
        <div class="img-cap" style="font-size:4.2mm;">Workspaces up top, the shared dashboard below.</div>
      </div>
    </div>
    <div class="b-foot">
      <div class="qr-card b-qr"><img src="${qr}" alt="QR: pricing"></div>
      <div><div class="b-cta">Team plans<br>start free.</div><div class="b-url">sayzio.app/pricing</div></div>
    </div>`);
}

async function board12(): Promise<string> {
  const qr = await qrSvg(QR_URLS.home);
  return board(`
    <div style="margin-top:16mm;" class="b-kick">THE REBRAND</div>
    <div class="b-h">1INME is now<br><span class="accent">Sayzio.</span></div>
    <p class="b-p">Same team, same links, a much bigger idea. 1INME grew from a link-in-bio tool into an AI-first platform where every link can talk, sell and answer for you. That deserved a new name.</p>
    <div style="display:flex;gap:10mm;margin-top:10mm;align-items:stretch;">
      <div style="flex:1;min-width:0;">
        <div class="b-li" style="margin-top:0;"><div class="b-ic">🔗</div><div><div class="b-lt">Every 1INME link still works</div><div class="b-ld">Old links, QR codes and pages redirect automatically. Nothing breaks.</div></div></div>
        <div class="b-li"><div class="b-ic">🤖</div><div><div class="b-lt">Upgraded to AI-first</div><div class="b-ld">Zio now builds pages, answers visitors and drafts replies for you.</div></div></div>
        <div class="b-li"><div class="b-ic">🛍</div><div><div class="b-lt">From links to business</div><div class="b-ld">Stores, menus, forms, invoices, bookings and payouts, all built in.</div></div></div>
        <div class="b-li"><div class="b-ic">🚀</div><div><div class="b-lt">Same account, more power</div><div class="b-ld">Your plan, data and analytics carried over on day one.</div></div></div>
      </div>
      <div style="flex:none;width:96mm;display:flex;flex-direction:column;gap:4mm;">
        <div class="glass" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6mm;padding:10mm;">
          <div style="font-size:9mm;font-weight:600;color:var(--muted);letter-spacing:.08em;text-decoration:line-through;text-decoration-thickness:1.2mm;text-decoration-color:var(--sky);">1INME</div>
          <div style="font-size:6mm;color:var(--sky);">&#8595;</div>
          ${wordmark(12, 10, 3.2)}
          <div class="pill" style="padding:1.4mm 4mm;font-size:3.4mm;">NOW AI-FIRST</div>
        </div>
        <div class="img-cap" style="font-size:4.2mm;">New name, new brain, same links.</div>
      </div>
    </div>
    <div class="b-foot">
      <div class="qr-card b-qr"><img src="${qr}" alt="QR: sayzio.app"></div>
      <div><div class="b-cta">Meet the<br>upgrade.</div><div class="b-url">sayzio.app</div></div>
    </div>`);
}

/* ============ manifest & render ============ */
const PIECES: Piece[] = [
  { file: "sayzio-card-front.pdf", trimW: 88.9, trimH: 50.8, html: cardFront },
  { file: "sayzio-card-back.pdf", trimW: 88.9, trimH: 50.8, html: cardBack },
  { file: "sayzio-standee-A-what-if-your-link-could-talk.pdf", trimW: 838.2, trimH: 1981.2, html: standeeA },
  { file: "sayzio-standee-B-sayzio-does-business.pdf", trimW: 838.2, trimH: 1981.2, html: standeeB },
  { file: "sayzio-trifold-outside.pdf", trimW: 297, trimH: 210, html: trifoldOutside },
  { file: "sayzio-trifold-inside.pdf", trimW: 297, trimH: 210, html: trifoldInside },
  { file: "sayzio-board-1-talk-to-this-poster.pdf", trimW: 297, trimH: 420, html: board1 },
  { file: "sayzio-board-2-prompt-to-page.pdf", trimW: 297, trimH: 420, html: board2 },
  { file: "sayzio-board-3-restaurant-one-qr.pdf", trimW: 297, trimH: 420, html: board3 },
  { file: "sayzio-board-4-analytics-zero-fee.pdf", trimW: 297, trimH: 420, html: board4 },
  { file: "sayzio-board-5-qr-studio-pro.pdf", trimW: 297, trimH: 420, html: board5 },
  { file: "sayzio-board-6-one-platform-three-apps.pdf", trimW: 297, trimH: 420, html: board6 },
  { file: "sayzio-board-7-forms-and-leads.pdf", trimW: 297, trimH: 420, html: board7 },
  { file: "sayzio-board-8-digital-cards-dialer.pdf", trimW: 297, trimH: 420, html: board8 },
  { file: "sayzio-board-9-twenty-link-types.pdf", trimW: 297, trimH: 420, html: board9 },
  { file: "sayzio-board-10-ai-inbox.pdf", trimW: 297, trimH: 420, html: board10 },
  { file: "sayzio-board-11-built-for-teams.pdf", trimW: 297, trimH: 420, html: board11 },
  { file: "sayzio-board-12-1inme-is-now-sayzio.pdf", trimW: 297, trimH: 420, html: board12 },
];

async function main() {
  mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const pg = await browser.newPage();
  for (const piece of PIECES) {
    const w = piece.trimW + 2 * BLEED;
    const h = piece.trimH + 2 * BLEED;
    const html = await piece.html();
    if (process.env.DUMP_HTML) writeFileSync(path.join(OUT, piece.file + ".html"), html);
    // Match the viewport to the physical page so layout (and the auto-fit
    // measurements below) is identical to what pg.pdf() renders.
    const pxW = Math.ceil((w * 96) / 25.4);
    const pxH = Math.ceil((h * 96) / 25.4);
    await pg.setViewportSize({ width: pxW, height: pxH });
    await pg.setContent(html, { waitUntil: "networkidle" });
    // Auto-fit: scale each CSS-composed art tile down so its mockup content
    // fits fully inside the frame (never cropped or distorted).
    await pg.evaluate(`(() => {
      document.querySelectorAll(".art-fit").forEach((fit) => {
        const fr = fit.parentElement.getBoundingClientRect();
        // Measure the union bbox of in-flow children (absolute full-bleed
        // layers like dot grids already conform to the frame).
        let l = Infinity, t = Infinity, r = -Infinity, b = -Infinity;
        for (const child of fit.children) {
          const pos = getComputedStyle(child).position;
          if (pos === "absolute" || pos === "fixed") continue;
          const cr = child.getBoundingClientRect();
          if (cr.width === 0 && cr.height === 0) continue;
          l = Math.min(l, cr.left);
          t = Math.min(t, cr.top);
          r = Math.max(r, cr.right);
          b = Math.max(b, cr.bottom);
        }
        if (!isFinite(l)) return;
        const pad = Math.min(fr.width, fr.height) * 0.05;
        const s = Math.min((fr.width - 2 * pad) / (r - l), (fr.height - 2 * pad) / (b - t));
        if (Math.abs(s - 1) > 0.01) fit.style.transform = "scale(" + s + ")";
      });
    })()`);
    await pg.pdf({
      path: path.join(OUT, piece.file),
      width: `${w}mm`,
      height: `${h}mm`,
      printBackground: true,
      pageRanges: "1",
      margin: { top: 0, bottom: 0, left: 0, right: 0 },
    });
    console.log(`✔ ${piece.file} (${w}x${h}mm incl. ${BLEED}mm bleed)`);
  }
  await browser.close();
}

// Only run when executed directly (booklet.ts imports this module).
import { pathToFileURL } from "node:url";
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main().catch((e) => {
    console.error(e);
    process.exit(1);
  });
}
