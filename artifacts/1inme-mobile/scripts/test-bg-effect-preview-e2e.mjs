#!/usr/bin/env node
/**
 * E2E gate for the rich Tiles / Mesh / Pattern background textures in the
 * mobile Appearance preview (components/BiolinkBackgroundPreview.tsx +
 * components/BiolinkEffectBackground.tsx, Task #6212), driving the REAL
 * app in a headless browser.
 *
 * For each effect type it loads the Appearance screen with a mocked link
 * whose settings carry the stored catalog key(s) and asserts the preview
 * renders the actual texture, not just the flat gradient fallback:
 *   - tiles:   many LinearGradient cells inside the preview (a packed grid)
 *   - mesh:    an SVG with radial-gradient blob circles
 *   - pattern: an SVG with a <pattern> definition tiling the canvas
 * Finally it loads a link with an UNKNOWN pattern key and asserts the
 * graceful gradient fallback still renders (no SVG, preview visible).
 *
 * Every /api/** call is intercepted in-memory; nothing reaches a backend.
 * Boots its own throwaway Expo web server unless APP_URL points at one,
 * and SKIPs on transient environment errors.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:bg-effect-preview-e2e
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  runHarness,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[bg-effect-preview-e2e]", ...args);
}
function fail(msg) {
  console.error("[bg-effect-preview-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[bg-effect-preview-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-bg-effect-token";
const MOCK_USER = {
  id: 6212,
  display_name: "Effect Tester",
  email: "bg-effects@example.com",
};
const LINK_ID = 6212;
const EXPLICIT_APP_URL = process.env.APP_URL || null;

// Mutated between scenarios; the /links/{id} mock always serves this.
let biolinkSettings = { background_type: "color" };

function makeLink() {
  return {
    id: LINK_ID,
    type: "biolink",
    alias: "effect-tester",
    title: "Effect Tester",
    short_url: "https://1in.me/effect-tester",
    long_url: null,
    visibility: "public",
    is_active: true,
    settings: { biolink: biolinkSettings },
  };
}

async function mockApi(context) {
  await context.route("**/api/**", async (route) => {
    const path = new URL(route.request().url()).pathname;
    let body = { data: [] };
    if (/\/api\/v1\/auth\/me$/.test(path)) {
      body = { data: { user: MOCK_USER } };
    } else if (/\/api\/v1\/onboarding$/.test(path)) {
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
    } else if (/\/api\/v1\/bg-presets$/.test(path)) {
      body = { data: { groups: [], presets: [] } };
    } else if (/\/api\/v1\/bg-templates$/.test(path)) {
      body = { data: { templates: [] } };
    } else if (new RegExp(`/api/v1/links/${LINK_ID}$`).test(path)) {
      body = { data: { link: makeLink() } };
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

// DOM probes evaluated inside the preview card.
async function previewStats(page) {
  return page.evaluate(() => {
    const root = document.querySelector('[data-testid="bg-preview"]');
    if (!root) return null;
    const all = Array.from(root.querySelectorAll("*"));
    const gradientDivs = all.filter((el) => {
      const bg = window.getComputedStyle(el).backgroundImage || "";
      return bg.includes("linear-gradient");
    }).length;
    const svgs = root.querySelectorAll("svg").length;
    const radial = root.querySelectorAll("svg radialGradient").length;
    const patternDefs = root.querySelectorAll("svg pattern").length;
    return { gradientDivs, svgs, radial, patternDefs };
  });
}

async function loadPreview(page, appUrl, caption) {
  await page.goto(`${appUrl}/links/${LINK_ID}/settings/appearance`, {
    waitUntil: "domcontentloaded",
    timeout: NAV_TIMEOUT_MS,
  });
  await page.getByTestId("bg-preview").waitFor({ state: "visible" });
  await page
    .getByTestId("bg-preview")
    .getByText(caption)
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  // Give onLayout + the texture layer a beat to paint.
  await page.waitForTimeout(400);
}

async function run(appUrl) {
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({ viewport: VIEWPORT });
    await seedSession(context);
    await mockApi(context);
    const page = await context.newPage();
    page.setDefaultTimeout(STEP_TIMEOUT_MS);

    // 1. Tiles: metro layout should place a packed grid of gradient cells.
    biolinkSettings = {
      background_type: "tiles",
      tiles_palette: "tiles_sunset",
      tiles_layout: "metro",
      bg_effect_colors: ["#f97316", "#db2777", "#7c2d12"],
    };
    await loadPreview(page, appUrl, /Tiles background/);
    let s = await previewStats(page);
    if (!s) fail("tiles: preview root not found");
    if (s.gradientDivs < 10) {
      fail(`tiles: expected a grid of gradient cells, saw ${s.gradientDivs}`);
    }
    await page.getByTestId("bg-preview").screenshot({ path: "/tmp/bg-effect-tiles.png" });
    log(`tiles texture renders (${s.gradientDivs} gradient cells)`);

    // 2. Mesh: SVG radial-gradient blobs over the base color.
    biolinkSettings = {
      background_type: "mesh",
      mesh_preset: "mesh_aurora",
      bg_effect_colors: ["#22d3ee", "#a78bfa", "#34d399", "#0b1026"],
    };
    await loadPreview(page, appUrl, /Mesh gradient background/);
    s = await previewStats(page);
    if (!s) fail("mesh: preview root not found");
    if (s.svgs < 1 || s.radial < 3) {
      fail(`mesh: expected radial blob SVG, saw svgs=${s.svgs} radial=${s.radial}`);
    }
    await page.getByTestId("bg-preview").screenshot({ path: "/tmp/bg-effect-mesh.png" });
    log(`mesh texture renders (${s.radial} radial blobs)`);

    // 3. Pattern: SVG <pattern> motif tiling the canvas.
    biolinkSettings = {
      background_type: "pattern",
      pattern_preset: "pattern_grid_dark",
      bg_effect_colors: ["#0f172a", "#334155"],
    };
    await loadPreview(page, appUrl, /Pattern background/);
    s = await previewStats(page);
    if (!s) fail("pattern: preview root not found");
    if (s.svgs < 1 || s.patternDefs < 1) {
      fail(`pattern: expected SVG pattern motif, saw svgs=${s.svgs} patterns=${s.patternDefs}`);
    }
    await page.getByTestId("bg-preview").screenshot({ path: "/tmp/bg-effect-pattern.png" });
    log(`pattern texture renders (${s.patternDefs} pattern def)`);

    // 4. Unknown key: graceful gradient fallback, no texture SVG.
    biolinkSettings = {
      background_type: "pattern",
      pattern_preset: "pattern_added_on_web_later",
      bg_effect_colors: ["#123456", "#654321"],
    };
    await loadPreview(page, appUrl, /Pattern background/);
    s = await previewStats(page);
    if (!s) fail("fallback: preview root not found");
    if (s.patternDefs !== 0) {
      fail("fallback: unknown key unexpectedly rendered a pattern texture");
    }
    if (s.gradientDivs < 1) {
      fail("fallback: gradient approximation missing for unknown key");
    }
    log("unknown catalog key falls back to the stamped-color gradient");

    console.log("[bg-effect-preview-e2e] PASS");
  } finally {
    await browser.close();
  }
}

async function main() {
  const { acquireServer, stopExpo } = createExpoServerManager(log);
  const server = await acquireServer("bg-effect-preview", EXPLICIT_APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
    return;
  }
  const appUrl = server.appUrl.replace(/\/$/, "");
  try {
    await run(appUrl);
  } catch (err) {
    if (isTransientEnvError(err)) {
      skip(`transient environment error: ${err.message}`);
    }
    throw err;
  } finally {
    if (!server.explicit && server.child) stopExpo(server.child);
  }
  // Explicit exit: the detached throwaway Expo child keeps the event loop
  // alive otherwise (siblings do the same).
  process.exit(0);
}

runHarness(main, { log, onError: (err) => fail(err?.stack || String(err)) });
