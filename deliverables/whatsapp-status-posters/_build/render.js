const fs = require("fs");
const path = require("path");
const { chromium } = require("playwright");
const { TYPES, SETS } = require("./posters.js");

const ROOT = path.resolve(__dirname, "..");
const ILLU = path.join(ROOT, "illustrations");
const LOGO_B64 = fs.readFileSync(path.join(__dirname, "logo.b64"), "utf8").trim();

function hexToRgb(h) {
  const m = h.replace("#", "");
  return [parseInt(m.slice(0, 2), 16), parseInt(m.slice(2, 4), 16), parseInt(m.slice(4, 6), 16)];
}
function rgba(h, a) { const [r, g, b] = hexToRgb(h); return `rgba(${r},${g},${b},${a})`; }

function html({ type, set, illuB64 }) {
  const accent = type.accent;
  const caption = type[set.field];
  return `<!DOCTYPE html><html><head><meta charset="utf8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  html,body { width:1080px; height:1920px; }
  body { font-family:'Space Grotesk',sans-serif; -webkit-font-smoothing:antialiased; }
  .stage { position:relative; width:1080px; height:1920px; overflow:hidden;
    background:#120a26; }
  .illu { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
  /* readability gradients top + bottom */
  .grad-top { position:absolute; top:0; left:0; right:0; height:760px;
    background:linear-gradient(180deg, rgba(10,5,22,0.94) 0%, rgba(10,5,22,0.78) 30%, rgba(10,5,22,0.0) 100%); }
  .grad-bot { position:absolute; bottom:0; left:0; right:0; height:1000px;
    background:linear-gradient(0deg, rgba(8,4,18,0.97) 0%, rgba(8,4,18,0.92) 28%, rgba(8,4,18,0.55) 60%, rgba(8,4,18,0.0) 100%); }
  .accent-glow { position:absolute; bottom:-260px; left:50%; transform:translateX(-50%);
    width:1200px; height:760px; border-radius:50%;
    background:radial-gradient(closest-side, ${rgba(accent,0.34)}, rgba(0,0,0,0) 72%); filter:blur(8px); }
  /* safe content frame: top 150, bottom 235, sides 84 (WhatsApp Status chrome) */
  .frame { position:absolute; inset:0; padding:150px 84px 235px; display:flex; flex-direction:column; }
  /* header */
  .header { display:flex; align-items:center; justify-content:space-between; gap:24px; }
  .logo { height:78px; filter:drop-shadow(0 4px 22px rgba(0,0,0,0.55)); }
  .eyebrow { display:inline-flex; align-items:center; gap:14px;
    padding:16px 26px; border-radius:999px;
    background:${rgba(accent,0.16)}; border:1.5px solid ${rgba(accent,0.55)};
    box-shadow:0 0 30px ${rgba(accent,0.30)}, inset 0 0 0 1px rgba(255,255,255,0.04);
    color:#fff; font-weight:600; font-size:30px; letter-spacing:3px; white-space:nowrap;
    backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px); }
  .eyebrow .dot { width:16px; height:16px; border-radius:50%; background:${accent};
    box-shadow:0 0 16px ${accent}; }
  .spacer { flex:1; }
  /* bottom info card */
  .card { position:relative; border-radius:46px; padding:52px 54px 58px;
    background:linear-gradient(160deg, rgba(255,255,255,0.10), rgba(255,255,255,0.035));
    border:1.5px solid rgba(255,255,255,0.14);
    box-shadow:0 30px 80px rgba(0,0,0,0.55), inset 0 1px 0 rgba(255,255,255,0.18),
      0 0 0 1px ${rgba(accent,0.10)};
    backdrop-filter:blur(26px); -webkit-backdrop-filter:blur(26px); overflow:hidden; }
  .card::before { content:""; position:absolute; top:-2px; left:0; right:0; height:5px;
    background:linear-gradient(90deg, ${accent}, ${rgba(accent,0.0)}); }
  .chiprow { display:flex; align-items:center; gap:28px; margin-bottom:30px; }
  .chip { width:118px; height:118px; min-width:118px; border-radius:30px; display:flex;
    align-items:center; justify-content:center; font-size:54px; color:#fff;
    background:linear-gradient(150deg, ${accent}, ${rgba(accent,0.55)});
    box-shadow:0 14px 36px ${rgba(accent,0.55)}, inset 0 2px 4px rgba(255,255,255,0.4); }
  .titlewrap { display:flex; flex-direction:column; }
  .kicker { font-size:27px; font-weight:500; letter-spacing:5px; text-transform:uppercase;
    color:${accent}; margin-bottom:6px; filter:brightness(1.25); }
  .title { font-size:74px; line-height:1.02; font-weight:700; color:#fff;
    text-shadow:0 2px 24px rgba(0,0,0,0.5); }
  .caption { font-size:42px; line-height:1.36; font-weight:400; color:rgba(255,255,255,0.92);
    margin-top:6px; }
  .footer { display:flex; align-items:center; gap:18px; margin-top:40px;
    padding-top:30px; border-top:1.5px solid rgba(255,255,255,0.12); }
  .footer .badge { width:14px; height:14px; border-radius:50%; background:${accent};
    box-shadow:0 0 14px ${accent}; }
  .footer .url { color:#fff; font-weight:600; font-size:34px; letter-spacing:1px; }
  .footer .tag { color:rgba(255,255,255,0.6); font-weight:400; font-size:30px; }
  .footer .right { margin-left:auto; color:rgba(255,255,255,0.6); font-size:30px; font-weight:500; }
</style></head>
<body>
  <div class="stage">
    <img class="illu" src="data:image/png;base64,${illuB64}">
    <div class="grad-top"></div>
    <div class="grad-bot"></div>
    <div class="accent-glow"></div>
    <div class="frame">
      <div class="header">
        <img class="logo" src="data:image/png;base64,${LOGO_B64}">
        <div class="eyebrow"><span class="dot"></span>${set.eyebrow}</div>
      </div>
      <div class="spacer"></div>
      <div class="card">
        <div class="chiprow">
          <div class="chip"><i class="fa-solid ${type.icon}"></i></div>
          <div class="titlewrap">
            <div class="kicker">Link Type</div>
            <div class="title">${type.name}</div>
          </div>
        </div>
        <div class="caption">${caption}</div>
        <div class="footer">
          <span class="badge"></span>
          <span class="url">1in.me</span>
          <span class="tag">· one link to everything</span>
          <span class="right">Get yours free</span>
        </div>
      </div>
    </div>
  </div>
</body></html>`;
}

(async () => {
  const browser = await chromium.launch({ args: ["--no-sandbox", "--disable-dev-shm-usage"] });
  const page = await browser.newPage({ viewport: { width: 1080, height: 1920 }, deviceScaleFactor: 1 });
  let count = 0;
  for (const type of TYPES) {
    for (const set of SETS) {
      const illuPath = path.join(ILLU, `${set.letter}_${type.slug.replace(/-/g, "_")}.png`);
      const illuB64 = fs.readFileSync(illuPath, "utf8");
      const b64 = Buffer.from(fs.readFileSync(illuPath)).toString("base64");
      await page.setContent(html({ type, set, illuB64: b64 }), { waitUntil: "networkidle" });
      await page.evaluate(() => document.fonts.ready);
      await page.waitForTimeout(350);
      const out = path.join(ROOT, `${type.slug}__${set.key}.png`);
      await page.screenshot({ path: out, clip: { x: 0, y: 0, width: 1080, height: 1920 } });
      count++;
      console.log(`[${count}/20] ${path.basename(out)}`);
    }
  }
  await browser.close();
  console.log("DONE");
})();
