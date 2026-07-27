#!/usr/bin/env node
/**
 * Pre-renders every background preset's REAL CSS into a small PNG thumbnail
 * so the mobile swatch gallery can show the actual texture (stripes, dots,
 * checker, blend-mode abstracts) instead of a LinearGradient approximation.
 *
 * How it works:
 *   1. Dumps the catalog straight from BgPresetCatalog.php with plain `php`
 *      (the class has no framework dependencies).
 *   2. Opens a headless Chromium page and, for each preset, applies the raw
 *      CSS to a swatch-sized div (110x171 CSS px — the gallery cell size —
 *      at 2x device scale) and screenshots it.
 *   3. Writes PNGs to artifacts/1inme/public/img/bg-preset-swatches/{key}.png
 *      plus a manifest.json mapping each key to the md5 of the CSS it was
 *      rendered from. BgPresetCatalog::forApi() only advertises a thumbnail
 *      when the manifest hash still matches the live CSS, so editing a
 *      preset without re-running this script degrades that preset back to
 *      the gradient approximation instead of showing a stale image.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run generate:bg-preset-swatches
 *
 * Re-run whenever presets are added or their CSS changes, and commit the
 * regenerated files.
 */

import { createHash } from "node:crypto";
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { chromium } from "playwright";

const MOBILE_ROOT = path.resolve(fileURLToPath(import.meta.url), "..", "..");
const LARAVEL_ROOT = path.resolve(MOBILE_ROOT, "..", "1inme");
const CATALOG_PHP = path.join(
  LARAVEL_ROOT,
  "app",
  "Modules",
  "User",
  "Support",
  "BgPresetCatalog.php",
);
const OUT_DIR = path.join(LARAVEL_ROOT, "public", "img", "bg-preset-swatches");

// Gallery cell is ~110 CSS px wide with a 9/14 aspect ratio; render at 2x
// for crisp texture on retina screens.
const W = 110;
const H = 171;
const SCALE = 2;

function log(...args) {
  console.log("[gen-bg-swatches]", ...args);
}

function loadCatalog() {
  const code = `require ${JSON.stringify(CATALOG_PHP)}; echo json_encode(\\App\\Modules\\User\\Support\\BgPresetCatalog::all());`;
  const res = spawnSync("php", ["-r", code], {
    encoding: "utf8",
    timeout: 30_000,
  });
  if (res.error || res.status !== 0) {
    throw new Error(
      `could not dump catalog: ${res.error?.message || res.stderr || res.status}`,
    );
  }
  const all = JSON.parse(res.stdout);
  if (!all || typeof all !== "object" || Array.isArray(all)) {
    throw new Error("catalog dump was not an object");
  }
  return all;
}

async function main() {
  const catalog = loadCatalog();
  const keys = Object.keys(catalog);
  log(`rendering ${keys.length} preset swatches at ${W}x${H}@${SCALE}x…`);

  fs.mkdirSync(OUT_DIR, { recursive: true });

  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: W + 40, height: H + 40 },
    deviceScaleFactor: SCALE,
  });
  const page = await context.newPage();
  await page.setContent(
    `<!doctype html><html><head><style>
      html,body{margin:0;padding:20px;background:#888}
      #sw{width:${W}px;height:${H}px}
    </style></head><body><div id="sw"></div></body></html>`,
  );

  const manifest = {};
  let done = 0;
  for (const key of keys) {
    const css = catalog[key].css;
    // Apply the raw preset CSS, then re-assert the swatch box size (setting
    // the style attribute wipes the id-rule-independent inline sizing).
    await page.evaluate(
      ({ style, w, h }) => {
        const el = document.getElementById("sw");
        el.setAttribute("style", style);
        el.style.width = `${w}px`;
        el.style.height = `${h}px`;
      },
      { style: css, w: W, h: H },
    );
    const el = page.locator("#sw");
    const png = await el.screenshot({ type: "png" });
    fs.writeFileSync(path.join(OUT_DIR, `${key}.png`), png);
    manifest[key] = createHash("md5").update(css).digest("hex");
    done++;
    if (done % 25 === 0) log(`…${done}/${keys.length}`);
  }

  fs.writeFileSync(
    path.join(OUT_DIR, "manifest.json"),
    JSON.stringify(manifest, null, 2) + "\n",
  );
  await browser.close();

  const total = keys.reduce(
    (sum, k) => sum + fs.statSync(path.join(OUT_DIR, `${k}.png`)).size,
    0,
  );
  log(
    `PASS — wrote ${done} thumbnails + manifest.json (${(total / 1024).toFixed(0)} KiB total) to ${path.relative(process.cwd(), OUT_DIR)}`,
  );
}

main().catch((err) => {
  console.error("[gen-bg-swatches] FAIL:", err?.stack || String(err));
  process.exit(1);
});
