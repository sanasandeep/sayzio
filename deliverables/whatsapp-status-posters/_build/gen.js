const fs = require("fs");
const path = require("path");
const { TYPES, SETS } = require("./posters.js");

const ROOT = path.resolve(__dirname, "..");
const ILLU = path.join(ROOT, "illustrations");
const ICONS = path.join(__dirname, "icons");
const OUTSVG = path.join(__dirname, "svg");
fs.mkdirSync(OUTSVG, { recursive: true });

const LOGO_B64 = fs.readFileSync(path.join(__dirname, "logo.b64"), "utf8").trim();

const W = 1080, H = 1920;
const SAFE_TOP = 150, SAFE_BOT = 235, SIDE = 84;

function hexToRgb(h) { const m = h.replace("#", ""); return [parseInt(m.slice(0,2),16),parseInt(m.slice(2,4),16),parseInt(m.slice(4,6),16)]; }
function rgba(h,a){ const [r,g,b]=hexToRgb(h); return `rgba(${r},${g},${b},${a})`; }
function lighten(h,f){ let [r,g,b]=hexToRgb(h); r=Math.round(r+(255-r)*f); g=Math.round(g+(255-g)*f); b=Math.round(b+(255-b)*f); return `rgb(${r},${g},${b})`; }
function esc(s){ return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;"); }

// font families registered via fontconfig
const FF = { 400:"Space Grotesk", 500:"Space Grotesk Medium", 600:"Space Grotesk SemiBold", 700:"Space Grotesk" };
const FW = { 400:"normal", 500:"normal", 600:"normal", 700:"bold" };

function icon(name){
  const file = name.replace(/^fa-/, "");
  const raw = fs.readFileSync(path.join(ICONS, file + ".svg"), "utf8");
  const vb = raw.match(/viewBox="([^"]+)"/)[1].split(/\s+/).map(Number); // [0,0,w,h]
  const d = raw.match(/<path d="([^"]+)"/)[1];
  return { w: vb[2], h: vb[3], d };
}

// greedy word-wrap using avg char width estimate
function wrap(text, fontSize, maxWidth, avg = 0.515){
  const words = text.split(/\s+/);
  const lines = []; let cur = "";
  const charW = fontSize * avg;
  for (const w of words){
    const test = cur ? cur + " " + w : w;
    if (test.length * charW > maxWidth && cur){ lines.push(cur); cur = w; }
    else cur = test;
  }
  if (cur) lines.push(cur);
  return lines;
}

function poster(type, set){
  const accent = type.accent;
  const aLight = lighten(accent, 0.30);
  const illuFile = path.join(ILLU, `${set.letter}_${type.slug.replace(/-/g,"_")}.png`);
  const illuB64 = Buffer.from(fs.readFileSync(illuFile)).toString("base64");

  // logo geometry (native 1940x531)
  const logoH = 80, logoW = Math.round(logoH * 1940 / 531);
  const logoX = SIDE, logoY = SAFE_TOP;

  // eyebrow pill (right aligned, same vertical center as logo)
  const ebText = set.eyebrow;
  const ebFs = 30;
  const ebTextW = ebText.length * ebFs * 0.62; // letter-spacing heavy caps
  const ebPadX = 30, ebDot = 16, ebGap = 16;
  const ebW = ebPadX*2 + ebDot + ebGap + ebTextW;
  const ebH = 64;
  const ebX = W - SIDE - ebW;
  const ebY = logoY + logoH/2 - ebH/2;

  // ---- bottom card ----
  const cardW = W - SIDE*2;
  const cardX = SIDE;
  const cardBottom = H - SAFE_BOT;
  const padX = 56, padTop = 54, padBot = 58;
  const innerW = cardW - padX*2;

  // chip + title row
  const chip = 118, chipGap = 30;
  const titleW = innerW - chip - chipGap;
  let titleFs = 72;
  if (type.name.length > 12) titleFs = 60;
  const kickerFs = 26;

  // caption
  const capFs = 42, capLH = Math.round(capFs * 1.36);
  const capLines = wrap(type[set.field], capFs, innerW);
  const capBlockH = capLines.length * capLH;

  // footer
  const footMt = 40, footPadTop = 30, footFs = 33, footH = 42;

  const contentH = padTop + chip + 34 /*gap after chiprow*/ + capBlockH + footMt + footPadTop + footH + padBot;
  const cardH = contentH;
  const cardY = cardBottom - cardH;
  const rx = 46;

  // chiprow geometry
  const chipX = cardX + padX, chipY = cardY + padTop;
  const titleX = chipX + chip + chipGap;
  const kickerY = chipY + 34;
  const titleBaseY = chipY + chip - 22; // baseline near bottom of chip
  // caption start
  const capStartY = chipY + chip + 34 + capFs; // first baseline
  // footer
  const footDivY = capStartY + (capLines.length-1)*capLH + footMt;
  const footTextY = footDivY + footPadTop + footFs*0.78;

  const ic = icon(type.icon);
  const iconBox = 58;
  const iconScale = iconBox / Math.max(ic.w, ic.h);
  const iconW = ic.w*iconScale, iconH = ic.h*iconScale;
  const iconTx = chipX + chip/2 - iconW/2;
  const iconTy = chipY + chip/2 - iconH/2;

  // footer pieces
  const fX = cardX + padX;
  const fBadge = 14;
  const fUrlX = fX + fBadge + 18;
  const fUrl = "1in.me";
  const fUrlW = fUrl.length * footFs * 0.6;
  const fTagX = fUrlX + fUrlW + 16;
  const fRight = "Get yours free";

  function txt(x,y,s,fs,weight,fill,opts=""){ return `<text x="${x}" y="${y}" font-family="${FF[weight]}" font-weight="${FW[weight]}" font-size="${fs}" fill="${fill}" ${opts}>${esc(s)}</text>`; }

  const capTspans = capLines.map((l,i)=> txt(fX, capStartY + i*capLH, l, capFs, 400, "rgba(255,255,255,0.93)")).join("\n      ");

  return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">
  <defs>
    <linearGradient id="gtop" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#0a0516" stop-opacity="0.94"/>
      <stop offset="0.32" stop-color="#0a0516" stop-opacity="0.72"/>
      <stop offset="1" stop-color="#0a0516" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="gbot" x1="0" y1="1" x2="0" y2="0">
      <stop offset="0" stop-color="#070310" stop-opacity="0.98"/>
      <stop offset="0.30" stop-color="#070310" stop-opacity="0.92"/>
      <stop offset="0.62" stop-color="#070310" stop-opacity="0.5"/>
      <stop offset="1" stop-color="#070310" stop-opacity="0"/>
    </linearGradient>
    <radialGradient id="glow" cx="0.5" cy="0.5" r="0.5">
      <stop offset="0" stop-color="${accent}" stop-opacity="0.40"/>
      <stop offset="0.72" stop-color="${accent}" stop-opacity="0"/>
    </radialGradient>
    <linearGradient id="cardfill" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#ffffff" stop-opacity="0.12"/>
      <stop offset="1" stop-color="#ffffff" stop-opacity="0.035"/>
    </linearGradient>
    <linearGradient id="accentbar" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="${accent}" stop-opacity="1"/>
      <stop offset="1" stop-color="${accent}" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="chipg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="${aLight}"/>
      <stop offset="1" stop-color="${accent}"/>
    </linearGradient>
    <clipPath id="cardclip"><rect x="${cardX}" y="${cardY}" width="${cardW}" height="${cardH}" rx="${rx}"/></clipPath>
  </defs>

  <rect width="${W}" height="${H}" fill="#120a26"/>
  <image x="0" y="0" width="${W}" height="${H}" preserveAspectRatio="xMidYMid slice" xlink:href="data:image/png;base64,${illuB64}"/>
  <rect x="0" y="0" width="${W}" height="780" fill="url(#gtop)"/>
  <rect x="0" y="${H-1040}" width="${W}" height="1040" fill="url(#gbot)"/>
  <ellipse cx="${W/2}" cy="${H-130}" rx="640" ry="420" fill="url(#glow)"/>

  <!-- header -->
  <image x="${logoX}" y="${logoY}" width="${logoW}" height="${logoH}" xlink:href="data:image/png;base64,${LOGO_B64}"/>
  <g>
    <rect x="${ebX}" y="${ebY}" width="${ebW}" height="${ebH}" rx="${ebH/2}" fill="${rgba(accent,0.16)}" stroke="${rgba(accent,0.55)}" stroke-width="1.5"/>
    <circle cx="${ebX + ebPadX + ebDot/2}" cy="${ebY + ebH/2}" r="${ebDot/2}" fill="${accent}"/>
    <text x="${ebX + ebPadX + ebDot + ebGap}" y="${ebY + ebH/2 + ebFs*0.35}" font-family="${FF[600]}" font-size="${ebFs}" letter-spacing="3" fill="#ffffff">${esc(ebText)}</text>
  </g>

  <!-- card -->
  <g>
    <rect x="${cardX}" y="${cardY}" width="${cardW}" height="${cardH}" rx="${rx}" fill="#0a0614" fill-opacity="0.42"/>
    <rect x="${cardX}" y="${cardY}" width="${cardW}" height="${cardH}" rx="${rx}" fill="url(#cardfill)" stroke="rgba(255,255,255,0.16)" stroke-width="1.5"/>
    <g clip-path="url(#cardclip)"><rect x="${cardX}" y="${cardY}" width="${cardW}" height="6" fill="url(#accentbar)"/></g>

    <!-- chip -->
    <rect x="${chipX}" y="${chipY}" width="${chip}" height="${chip}" rx="30" fill="url(#chipg)"/>
    <g transform="translate(${iconTx},${iconTy}) scale(${iconScale})"><path d="${ic.d}" fill="#ffffff"/></g>

    <!-- title -->
    <text x="${titleX}" y="${kickerY}" font-family="${FF[500]}" font-size="${kickerFs}" letter-spacing="5" fill="${aLight}">LINK TYPE</text>
    <text x="${titleX}" y="${titleBaseY}" font-family="${FF[700]}" font-weight="bold" font-size="${titleFs}" fill="#ffffff">${esc(type.name)}</text>

    <!-- caption -->
      ${capTspans}

    <!-- footer -->
    <line x1="${fX}" y1="${footDivY}" x2="${cardX+cardW-padX}" y2="${footDivY}" stroke="rgba(255,255,255,0.13)" stroke-width="1.5"/>
    <circle cx="${fX + fBadge/2}" cy="${footTextY - footFs*0.32}" r="${fBadge/2}" fill="${accent}"/>
    <text x="${fUrlX}" y="${footTextY}" font-family="${FF[600]}" font-size="${footFs}" fill="#ffffff">${fUrl}</text>
    <text x="${fTagX}" y="${footTextY}" font-family="${FF[400]}" font-size="${footFs-3}" fill="rgba(255,255,255,0.6)">· one link to everything</text>
    <text x="${cardX+cardW-padX}" y="${footTextY}" text-anchor="end" font-family="${FF[500]}" font-size="${footFs-3}" fill="${aLight}">${esc(fRight)} →</text>
  </g>
</svg>`;
}

const manifest = [];
for (const type of TYPES){
  for (const set of SETS){
    const svg = poster(type, set);
    const name = `${type.slug}__${set.key}`;
    fs.writeFileSync(path.join(OUTSVG, name + ".svg"), svg);
    manifest.push(name);
  }
}
fs.writeFileSync(path.join(__dirname, "manifest.json"), JSON.stringify(manifest, null, 2));
console.log("wrote", manifest.length, "svgs");
