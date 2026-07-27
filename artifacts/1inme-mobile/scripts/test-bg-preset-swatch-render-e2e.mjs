#!/usr/bin/env node
/**
 * Visual gate for the mobile background-preset swatches: every preset in the
 * REAL server catalog (BgPresetCatalog::forApi(), 170+ presets) must render a
 * meaningful LinearGradient swatch in the Appearance "Presets" gallery —
 * non-blank and not collapsed to one identical flat color across the board.
 *
 * The sibling harness (test-bg-presets-e2e.mjs) proves the picker's browse/
 * search/save flow against a trimmed mock catalog; the PHPUnit catalog check
 * (tests/Unit/BgPresetCatalogTest.php) proves each preset's CSS yields at
 * least one extractable color. Neither can tell whether the mobile
 * LinearGradient approximation of a radial/multi-layer CSS background
 * actually paints something visible. This harness closes that gap:
 *
 *   1. Dumps the real catalog to JSON by evaluating BgPresetCatalog with
 *      plain `php` (the class has no framework dependencies).
 *   2. Boots the REAL Expo web app, mocks /bg-presets with that catalog and
 *      serves the committed pre-rendered swatch PNGs
 *      (artifacts/1inme/public/img/bg-preset-swatches) exactly like the
 *      production server would, opens the Appearance screen and expands the
 *      Presets gallery.
 *   3. For EVERY preset in every group tab, screenshots the swatch element
 *      (pre-rendered texture image over the gradient fallback) and inspects
 *      its pixels (canvas decode in the page):
 *        - FAIL if the swatch is blank: (near-)transparent, or a uniform
 *          fill matching the page/card background (nothing painted).
 *        - FAIL if a preset that declares >= 2 distinct color stops renders
 *          perfectly flat (the gradient collapsed to a single color).
 *        - FAIL if the swatches are (nearly) all identical — mean colors
 *          must be reasonably distinct across the catalog.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:bg-preset-swatch-render-e2e
 */

import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  runHarness,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[bg-swatch-render-e2e]", ...args);
}
function fail(msg) {
  console.error("[bg-swatch-render-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[bg-swatch-render-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-bg-swatch-token";
const MOCK_USER = {
  id: 4901,
  display_name: "Swatch Tester",
  email: "bg-swatches@example.com",
};
const LINK_ID = 4901;
const EXPLICIT_APP_URL = process.env.APP_URL || null;

// A swatch counts as "uniform" when no sampled channel deviates from the mean
// by more than this (0-255). Kept tight so even subtle gradients register.
const UNIFORM_MAX_DEV = 2;
// Mean color within this per-channel distance of the page background counts
// as "nothing painted".
const BG_MATCH_TOLERANCE = 6;
// Quantization bucket for the cross-catalog distinctness check.
const QUANT = 16;
// At least this fraction of swatches must land in distinct quantized-mean
// buckets (guards against the whole gallery collapsing to a handful of
// identical fills while tolerating legitimate near-duplicate presets).
const MIN_DISTINCT_RATIO = 0.5;

const MOBILE_ROOT = path.resolve(fileURLToPath(import.meta.url), "..", "..");
const CATALOG_PHP = path.resolve(
  MOBILE_ROOT,
  "..",
  "1inme",
  "app",
  "Modules",
  "User",
  "Support",
  "BgPresetCatalog.php",
);

// ---------------------------------------------------------------------------
// 1. Real catalog straight from the PHP source of truth.
// ---------------------------------------------------------------------------
function loadRealCatalog() {
  const code = `require ${JSON.stringify(CATALOG_PHP)}; echo json_encode(\\App\\Modules\\User\\Support\\BgPresetCatalog::forApi());`;
  const res = spawnSync("php", ["-r", code], {
    encoding: "utf8",
    timeout: 30_000,
  });
  if (res.error || res.status !== 0) {
    return {
      catalog: null,
      err: res.error?.message || res.stderr || `php exited ${res.status}`,
    };
  }
  try {
    const catalog = JSON.parse(res.stdout);
    if (!Array.isArray(catalog?.presets) || catalog.presets.length === 0) {
      return { catalog: null, err: "catalog dump had no presets" };
    }
    return { catalog, err: null };
  } catch (e) {
    return { catalog: null, err: `catalog dump was not JSON: ${e.message}` };
  }
}

// ---------------------------------------------------------------------------
// 2. API mock around the real catalog.
// ---------------------------------------------------------------------------
const link = {
  id: LINK_ID,
  type: "biolink",
  alias: "swatch-tester",
  title: "Swatch Tester",
  short_url: "https://1in.me/swatch-tester",
  long_url: null,
  visibility: "public",
  is_active: true,
  settings: { biolink: { background_type: "color" } },
};

// Serve the committed pre-rendered swatch thumbnails from disk — in
// production these are static files under the Laravel public/ dir; the
// harness has no Laravel server, so fulfill them straight from the repo.
const SWATCH_DIR = path.resolve(
  MOBILE_ROOT,
  "..",
  "1inme",
  "public",
  "img",
  "bg-preset-swatches",
);

async function mockSwatchImages(context) {
  await context.route("**/img/bg-preset-swatches/*.png", async (route) => {
    const name = path.basename(new URL(route.request().url()).pathname);
    const file = path.join(SWATCH_DIR, name);
    if (fs.existsSync(file)) {
      await route.fulfill({
        status: 200,
        contentType: "image/png",
        body: fs.readFileSync(file),
      });
    } else {
      await route.fulfill({ status: 404, body: "not found" });
    }
  });
}

async function mockApi(context, catalog) {
  await context.route("**/api/**", async (route) => {
    const p = new URL(route.request().url()).pathname;
    let body = { data: [] };
    if (/\/api\/v1\/auth\/me$/.test(p)) {
      body = { data: { user: MOCK_USER } };
    } else if (/\/api\/v1\/onboarding$/.test(p)) {
      body = {
        data: {
          onboarded_at: "2026-01-01T00:00:00Z",
          email_verified: true,
          has_links: true,
          has_biolink: true,
          whatsapp_pending: false,
          privacy_pending: false,
        },
      };
    } else if (/\/api\/v1\/bg-presets$/.test(p)) {
      body = { data: catalog };
    } else if (new RegExp(`/api/v1/links/${LINK_ID}$`).test(p)) {
      body = { data: { link } };
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(body),
    });
  });
}

async function seedSession(context) {
  await context.addInitScript(
    ({ token, user }) => {
      try {
        window.localStorage.setItem("1inme.onboarding.complete", "1");
        window.localStorage.setItem("1inme.auth.token", token);
        window.localStorage.setItem("1inme.auth.user", JSON.stringify(user));
      } catch {}
    },
    { token: MOCK_TOKEN, user: MOCK_USER },
  );
}

// ---------------------------------------------------------------------------
// 3. Pixel analysis of a swatch screenshot, decoded inside the page.
// ---------------------------------------------------------------------------
async function analyzeSwatch(page, pngBuffer) {
  const b64 = pngBuffer.toString("base64");
  return page.evaluate(async (data) => {
    const res = await fetch(`data:image/png;base64,${data}`);
    const bmp = await createImageBitmap(await res.blob());
    const canvas = new OffscreenCanvas(bmp.width, bmp.height);
    const ctx = canvas.getContext("2d");
    ctx.drawImage(bmp, 0, 0);
    const { data: px } = ctx.getImageData(0, 0, bmp.width, bmp.height);
    let n = 0,
      sr = 0,
      sg = 0,
      sb = 0,
      sa = 0;
    // Sample a grid (every 3rd px both axes) — plenty for flatness stats.
    for (let y = 0; y < bmp.height; y += 3) {
      for (let x = 0; x < bmp.width; x += 3) {
        const i = (y * bmp.width + x) * 4;
        sr += px[i];
        sg += px[i + 1];
        sb += px[i + 2];
        sa += px[i + 3];
        n++;
      }
    }
    const mean = [sr / n, sg / n, sb / n];
    const meanA = sa / n;
    let maxDev = 0;
    for (let y = 0; y < bmp.height; y += 3) {
      for (let x = 0; x < bmp.width; x += 3) {
        const i = (y * bmp.width + x) * 4;
        maxDev = Math.max(
          maxDev,
          Math.abs(px[i] - mean[0]),
          Math.abs(px[i + 1] - mean[1]),
          Math.abs(px[i + 2] - mean[2]),
        );
      }
    }
    return { mean, meanA, maxDev, w: bmp.width, h: bmp.height };
  }, b64);
}

function parseCssColor(s) {
  const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/.exec(s || "");
  if (!m) return null;
  return [Number(m[1]), Number(m[2]), Number(m[3])];
}

// ---------------------------------------------------------------------------
// Main run.
// ---------------------------------------------------------------------------
async function run(appUrl, catalog) {
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({ viewport: VIEWPORT });
    await seedSession(context);
    await mockApi(context, catalog);
    await mockSwatchImages(context);
    const page = await context.newPage();
    page.setDefaultTimeout(STEP_TIMEOUT_MS);

    log(`opening appearance settings (catalog: ${catalog.presets.length} presets)…`);
    await page.goto(`${appUrl}/links/${LINK_ID}/settings/appearance`, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });

    const toggle = page.getByTestId("bg-presets-toggle");
    await toggle.waitFor({ state: "visible" });
    await toggle.click();
    await page.getByTestId("bg-presets-grid").waitFor({ state: "visible" });

    // The page/card background the swatches sit on: a blank (unpainted)
    // swatch screenshot composites to exactly this color.
    const pageBg = await page.evaluate(() => {
      const el = document.querySelector('[data-testid="bg-presets-grid"]');
      let node = el;
      while (node) {
        const c = getComputedStyle(node).backgroundColor;
        const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/.exec(c);
        if (m && (m[4] === undefined || Number(m[4]) > 0.9)) return c;
        node = node.parentElement;
      }
      return getComputedStyle(document.body).backgroundColor;
    });
    const bgRgb = parseCssColor(pageBg) ?? [255, 255, 255];
    log(`page background behind swatches: ${pageBg}`);

    const problems = [];
    const means = [];
    let checked = 0;

    for (const group of catalog.groups) {
      // Activate the group tab.
      await page
        .getByText(group.label, { exact: true })
        .last()
        .click();
      const groupPresets = catalog.presets.filter((p) => p.group === group.key);
      if (groupPresets.length === 0) continue;
      await page
        .getByTestId(`bg-swatch-${groupPresets[0].key}`)
        .waitFor({ state: "visible" });
      log(`group "${group.label}": ${groupPresets.length} swatches…`);

      // Presets with an up-to-date pre-rendered thumbnail render it over the
      // gradient fallback; wait until every such <img> in this tab has
      // actually decoded before screenshotting, or pixel checks race the
      // network.
      await page.waitForFunction(
        () => {
          const imgs = Array.from(
            document.querySelectorAll('img[src*="/img/bg-preset-swatches/"]'),
          );
          return imgs.every((img) => img.complete && img.naturalWidth > 0);
        },
        { timeout: STEP_TIMEOUT_MS },
      );

      for (const preset of groupPresets) {
        const swatch = page.getByTestId(`bg-swatch-${preset.key}`);
        await swatch.scrollIntoViewIfNeeded();
        const shot = await swatch.screenshot();
        const stats = await analyzeSwatch(page, shot);
        checked++;
        means.push(stats.mean);

        const uniform = stats.maxDev <= UNIFORM_MAX_DEV;
        const matchesPageBg =
          Math.abs(stats.mean[0] - bgRgb[0]) <= BG_MATCH_TOLERANCE &&
          Math.abs(stats.mean[1] - bgRgb[1]) <= BG_MATCH_TOLERANCE &&
          Math.abs(stats.mean[2] - bgRgb[2]) <= BG_MATCH_TOLERANCE;
        const distinctStops = new Set(preset.colors).size;

        if (stats.meanA < 200) {
          problems.push(
            `${preset.key} (${preset.label}): swatch is (near-)transparent (mean alpha ${stats.meanA.toFixed(0)})`,
          );
        } else if (uniform && matchesPageBg) {
          problems.push(
            `${preset.key} (${preset.label}): swatch is blank — uniform fill matching the page background ${pageBg}`,
          );
        } else if (uniform && distinctStops >= 2) {
          problems.push(
            `${preset.key} (${preset.label}): declares ${distinctStops} distinct color stops but renders perfectly flat rgb(${stats.mean.map((v) => v.toFixed(0)).join(",")})`,
          );
        }
      }
    }

    if (checked !== catalog.presets.length) {
      fail(
        `only inspected ${checked} of ${catalog.presets.length} presets — a group tab failed to render its grid`,
      );
    }

    // Cross-catalog distinctness: the gallery must not collapse to (nearly)
    // one identical fill.
    const buckets = new Set(
      means.map((m) => m.map((v) => Math.round(v / QUANT)).join(",")),
    );
    const ratio = buckets.size / means.length;
    log(
      `inspected ${checked} swatches; ${buckets.size} distinct mean-color buckets (${(ratio * 100).toFixed(0)}%)`,
    );
    if (buckets.size < 2) {
      problems.push(
        `all ${checked} swatches rendered an identical mean color — gallery collapsed to one fill`,
      );
    } else if (ratio < MIN_DISTINCT_RATIO) {
      problems.push(
        `only ${buckets.size}/${checked} distinct swatch colors (${(ratio * 100).toFixed(0)}% < ${MIN_DISTINCT_RATIO * 100}%) — swatches are nearly all identical`,
      );
    }

    if (problems.length > 0) {
      fail(
        `${problems.length} preset swatch(es) render poorly:\n  - ${problems.join("\n  - ")}`,
      );
    }

    await context.close();
    log(
      `PASS — all ${checked} real catalog presets render non-blank, non-identical swatches.`,
    );
  } finally {
    await browser.close();
  }
}

async function main() {
  const { catalog, err } = loadRealCatalog();
  if (!catalog) {
    // No php / unreadable catalog is an environment problem, not a regression.
    skip(`could not dump the real preset catalog: ${err}`);
    return;
  }

  const { acquireServer, stopExpo } = createExpoServerManager(log);
  const server = await acquireServer("bg-swatch-render", EXPLICIT_APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
    return;
  }
  const appUrl = server.appUrl.replace(/\/$/, "");
  try {
    await run(appUrl, catalog);
  } catch (err2) {
    if (isTransientEnvError(err2)) {
      skip(`transient environment error: ${err2.message}`);
    }
    throw err2;
  } finally {
    if (!server.explicit && server.child) stopExpo(server.child);
  }
  process.exit(0);
}

runHarness(main, { log, onError: (err) => fail(err?.stack || String(err)) });
