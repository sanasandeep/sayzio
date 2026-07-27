#!/usr/bin/env node
/**
 * Pre-renders every ACTIVE background template's REAL CSS into a small PNG
 * thumbnail so the mobile "Templates" gallery can show the actual texture
 * (animated auroras, meshes, patterns, neon glows) instead of a
 * LinearGradient approximation.
 *
 * How it works:
 *   1. Dumps the active bg_templates rows from the database via
 *      `php artisan tinker` (templates are admin-managed DB rows, unlike
 *      the static BgPresetCatalog).
 *   2. Applies the same CSS rewrite the web thumbnail grid uses
 *      (`.bg-template-` → `.bg-thumb-`, position fixed → absolute,
 *      z-index -1 → 0) inside a headless Chromium page, renders each
 *      template into a swatch-sized div (110x171 CSS px at 2x scale) and
 *      screenshots it. Animated templates capture a representative frame.
 *   3. Writes PNGs to artifacts/1inme/public/img/bg-template-swatches/{slug}.png
 *      plus a manifest.json mapping each slug to the md5 of the RAW css it
 *      was rendered from. BgTemplateCatalog::forApi() only advertises a
 *      thumbnail when the manifest hash still matches the live CSS, so an
 *      admin editing a template without re-running this script degrades
 *      that template back to the gradient approximation instead of showing
 *      a stale image.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run generate:bg-template-swatches
 *
 * Re-run whenever templates are added or their CSS changes, and commit the
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
const OUT_DIR = path.join(
  LARAVEL_ROOT,
  "public",
  "img",
  "bg-template-swatches",
);

// Gallery cell is ~110 CSS px wide with a 9/14 aspect ratio; render at 2x
// for crisp texture on retina screens.
const W = 110;
const H = 171;
const SCALE = 2;

function log(...args) {
  console.log("[gen-bg-tpl-swatches]", ...args);
}

function loadTemplates() {
  const code =
    'echo json_encode(\\App\\Modules\\Admin\\Models\\BgTemplate::active()->get(["id","slug","name","css"]));';
  const res = spawnSync("php", ["artisan", "tinker", `--execute=${code}`], {
    cwd: LARAVEL_ROOT,
    encoding: "utf8",
    timeout: 120_000,
    maxBuffer: 64 * 1024 * 1024,
  });
  if (res.error || res.status !== 0) {
    throw new Error(
      `could not dump bg_templates: ${res.error?.message || res.stderr || res.status}`,
    );
  }
  // tinker may prepend deprecation noise; the JSON array is the last line
  // starting with "[".
  const line = res.stdout
    .split("\n")
    .filter((l) => l.trim().startsWith("["))
    .pop();
  const rows = JSON.parse(line ?? "");
  if (!Array.isArray(rows) || rows.length === 0) {
    throw new Error("bg_templates dump was empty — is the DB seeded?");
  }
  return rows;
}

// Same rewrite the web appearance picker applies for its thumbnail grid.
function thumbCss(css) {
  return css
    .replaceAll(".bg-template-", ".bg-thumb-")
    .replaceAll("position:fixed", "position:absolute")
    .replaceAll("position: fixed", "position:absolute")
    .replaceAll("z-index:-1", "z-index:0")
    .replaceAll("z-index: -1", "z-index:0");
}

async function main() {
  const templates = loadTemplates();
  log(`rendering ${templates.length} template swatches at ${W}x${H}@${SCALE}x…`);

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
      #frame{position:relative;width:${W}px;height:${H}px;overflow:hidden;background:#0f172a}
    </style><style id="tpl"></style></head>
    <body><div id="frame"><div id="sw" style="position:absolute;inset:0;"></div></div></body></html>`,
  );

  const manifest = {};
  let done = 0;
  for (const tpl of templates) {
    await page.evaluate(
      ({ css, slug }) => {
        document.getElementById("tpl").textContent = css;
        const el = document.getElementById("sw");
        el.className = `bg-thumb-${slug}`;
        el.setAttribute("style", "position:absolute;inset:0;");
      },
      { css: thumbCss(tpl.css), slug: tpl.slug },
    );
    const png = await page.locator("#frame").screenshot({ type: "png" });
    fs.writeFileSync(path.join(OUT_DIR, `${tpl.slug}.png`), png);
    // Manifest hashes the RAW css (what the API compares against).
    manifest[tpl.slug] = createHash("md5").update(tpl.css).digest("hex");
    done++;
    if (done % 50 === 0) log(`…${done}/${templates.length}`);
  }

  fs.writeFileSync(
    path.join(OUT_DIR, "manifest.json"),
    JSON.stringify(manifest, null, 2) + "\n",
  );
  await browser.close();

  const total = templates.reduce(
    (sum, t) => sum + fs.statSync(path.join(OUT_DIR, `${t.slug}.png`)).size,
    0,
  );
  log(
    `PASS — wrote ${done} thumbnails + manifest.json (${(total / 1024).toFixed(0)} KiB total) to ${path.relative(process.cwd(), OUT_DIR)}`,
  );
}

main().catch((err) => {
  console.error("[gen-bg-tpl-swatches] FAIL:", err?.stack || String(err));
  process.exit(1);
});
