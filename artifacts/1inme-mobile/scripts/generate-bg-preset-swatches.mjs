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
 * Flags:
 *   --keep-partial   Opt-in: if some presets persistently fail to render,
 *                    still write the successful PNGs plus a manifest covering
 *                    only them (BgPresetCatalog::forApi() degrades unlisted
 *                    presets to the gradient fallback), list the failed keys,
 *                    and exit non-zero. Default behavior without the flag is
 *                    unchanged: abort on the first persistent failure and
 *                    leave manifest.json untouched.
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

// Reuse the exact validation the bg-preset-swatches guard applies
// (scripts/src/check-bg-preset-swatches.ts) so the generator can never write
// a file the guard would reject. Node 24 strips the types natively.
import {
  inspectPng,
  MIN_PNG_BYTES,
  PNG_SIGNATURE,
} from "../../../scripts/src/check-bg-preset-swatches.ts";

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
  const keepPartial = process.argv.includes("--keep-partial");
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

  // Renders one preset and validates the screenshot bytes BEFORE anything
  // touches disk: same rules the check:bg-preset-swatches guard enforces
  // (real PNG signature, plausible size). Throws on a bad render.
  async function renderPreset(key, css) {
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

    if (png.length < MIN_PNG_BYTES) {
      throw new Error(
        `render of preset "${key}" produced only ${png.length} bytes (< ${MIN_PNG_BYTES}) — refusing to save a broken thumbnail`,
      );
    }
    if (!png.subarray(0, PNG_SIGNATURE.length).equals(PNG_SIGNATURE)) {
      throw new Error(
        `render of preset "${key}" is not a valid PNG (bad signature) — refusing to save a broken thumbnail`,
      );
    }
    return png;
  }

  const manifest = {};
  const failed = [];
  let done = 0;
  for (const key of keys) {
    const css = catalog[key].css;

    // A single flaky Chromium screenshot shouldn't abort a 176-preset run:
    // retry the render once. On a persistent failure the default is to abort
    // with a message naming the preset and how far the run got (old PNGs and
    // manifest.json stay untouched); with --keep-partial the failure is
    // recorded and the run keeps going so one broken preset can't block
    // refreshing the rest.
    let png;
    try {
      png = await renderPreset(key, css);
    } catch (firstErr) {
      log(
        `render of preset "${key}" failed (${firstErr?.message || firstErr}) — retrying once…`,
      );
      try {
        png = await renderPreset(key, css);
      } catch (retryErr) {
        if (keepPartial) {
          log(
            `render of preset "${key}" failed again (${retryErr?.message || retryErr}) — skipping it (--keep-partial)`,
          );
          failed.push(key);
          continue;
        }
        throw new Error(
          `preset "${key}" failed to render even after a retry (${done}/${keys.length} thumbnails had rendered successfully before the abort; manifest.json was NOT updated): ${retryErr?.message || retryErr}`,
        );
      }
    }

    // Write atomically (temp file + rename) so an interrupted write can never
    // leave a truncated {key}.png behind, then re-verify what landed on disk
    // with the guard's own inspector before recording the manifest md5.
    const finalPath = path.join(OUT_DIR, `${key}.png`);
    const tmpPath = `${finalPath}.tmp`;
    fs.writeFileSync(tmpPath, png);
    const info = inspectPng(tmpPath);
    if (
      !info ||
      info.size < MIN_PNG_BYTES ||
      info.head.length < PNG_SIGNATURE.length ||
      !info.head.subarray(0, PNG_SIGNATURE.length).equals(PNG_SIGNATURE)
    ) {
      fs.rmSync(tmpPath, { force: true });
      if (keepPartial) {
        log(
          `written file for preset "${key}" failed validation (${info ? `${info.size} bytes` : "missing"}) — skipping it (--keep-partial)`,
        );
        failed.push(key);
        continue;
      }
      throw new Error(
        `written file for preset "${key}" failed validation (${info ? `${info.size} bytes` : "missing"}) — refusing to update thumbnails`,
      );
    }
    fs.renameSync(tmpPath, finalPath);
    manifest[key] = createHash("md5").update(css).digest("hex");
    done++;
    if (done % 25 === 0) log(`…${done}/${keys.length}`);
  }

  if (keepPartial && done === 0) {
    await browser.close();
    throw new Error(
      `every one of the ${keys.length} presets failed to render — manifest.json was NOT updated (failed keys: ${failed.join(", ")})`,
    );
  }

  fs.writeFileSync(
    path.join(OUT_DIR, "manifest.json"),
    JSON.stringify(manifest, null, 2) + "\n",
  );
  await browser.close();

  const total = Object.keys(manifest).reduce(
    (sum, k) => sum + fs.statSync(path.join(OUT_DIR, `${k}.png`)).size,
    0,
  );

  if (failed.length > 0) {
    log(
      `PARTIAL — wrote ${done}/${keys.length} thumbnails + a partial manifest.json (${(total / 1024).toFixed(0)} KiB total) to ${path.relative(process.cwd(), OUT_DIR)}`,
    );
    log(
      `the ${failed.length} preset(s) below failed to render and are NOT in the manifest — mobile degrades them to the gradient fallback:`,
    );
    for (const key of failed) log(`  - ${key}`);
    process.exitCode = 1;
    return;
  }

  log(
    `PASS — wrote ${done} thumbnails + manifest.json (${(total / 1024).toFixed(0)} KiB total) to ${path.relative(process.cwd(), OUT_DIR)}`,
  );
}

main().catch((err) => {
  console.error("[gen-bg-swatches] FAIL:", err?.stack || String(err));
  process.exit(1);
});
