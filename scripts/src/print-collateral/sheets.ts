/**
 * Impose the 12 A3 boards as 4-up A3 sheets (each board at A5) so a print
 * shop can print four boards on a single A3 side and cut into quarters.
 *
 * Inputs: .local/print-out/board-q-01..12.png (300dpi rasters of the board
 * PDFs, bleed included, produced from the generate.ts outputs).
 * Outputs: sayzio-boards-a3-sheet-{1..3}.pdf, 303x426mm incl. 3mm bleed.
 *
 * Run: pnpm --filter @workspace/scripts run print:sheets
 */
import { chromium } from "playwright";
import { readFileSync } from "node:fs";
import path from "node:path";

const ROOT = path.resolve(import.meta.dirname, "../../..");
const OUT = path.join(ROOT, ".local/print-out");

const W = 303; // mm, A3 + 3mm bleed each side
const H = 426;

async function main() {
  const browser = await chromium.launch();
  const pg = await browser.newPage();
  for (let s = 0; s < 3; s++) {
    const imgs = [1, 2, 3, 4].map((k) => {
      const n = String(s * 4 + k).padStart(2, "0");
      const b64 = readFileSync(path.join(OUT, `board-q-${n}.png`)).toString("base64");
      return `data:image/png;base64,${b64}`;
    });
    const html = `<!doctype html><html><head><style>
      *{margin:0;padding:0;} @page{size:${W}mm ${H}mm;margin:0;}
      body{width:${W}mm;height:${H}mm;display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;}
      img{width:${W / 2}mm;height:${H / 2}mm;display:block;}
    </style></head><body>${imgs.map((src) => `<img src="${src}">`).join("")}</body></html>`;
    await pg.setContent(html, { waitUntil: "networkidle" });
    const file = `sayzio-boards-a3-sheet-${s + 1}.pdf`;
    await pg.pdf({
      path: path.join(OUT, file),
      width: `${W}mm`,
      height: `${H}mm`,
      printBackground: true,
      pageRanges: "1",
      margin: { top: 0, bottom: 0, left: 0, right: 0 },
    });
    console.log(`✔ ${file} (4-up A3, boards ${s * 4 + 1}-${s * 4 + 4})`);
  }
  await browser.close();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
