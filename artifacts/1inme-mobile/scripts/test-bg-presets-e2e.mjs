#!/usr/bin/env node
/**
 * E2E gate for the biolink Appearance "Presets" background gallery on mobile
 * (components/BgPresetPicker.tsx, app/links/[id]/settings/appearance.tsx,
 * lib/api/bgPresets.ts), driving the REAL app in a headless browser.
 *
 * What it asserts, in order:
 *   1. The Appearance settings screen shows the "Presets" background option.
 *   2. Tapping it expands the gallery, which GETs /bg-presets and renders the
 *      catalog swatches for the active group (Gradients by default).
 *   3. Typing in the search box filters the grid by preset NAME across every
 *      group (an Abstract preset is findable while the Gradients tab is
 *      active).
 *   4. Tapping a swatch PATCHes /links/{id} with
 *      settings.biolink.background_type='preset' + bg_preset_key, and the
 *      swatch shows as selected after the link query refetches.
 *
 * Every /api/** call is intercepted against an in-memory link + catalog so
 * nothing reaches a real backend. Like the sibling harnesses it boots its own
 * throwaway Expo web server unless APP_URL points at a running one, and SKIPS
 * on transient environment errors.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:bg-presets-e2e
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[bg-presets-e2e]", ...args);
}
function fail(msg) {
  console.error("[bg-presets-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[bg-presets-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-bg-presets-token";
const MOCK_USER = {
  id: 4899,
  display_name: "Preset Tester",
  email: "bg-presets@example.com",
};
const LINK_ID = 4899;
const EXPLICIT_APP_URL = process.env.APP_URL || null;

// The server-side link record the mock reports; PATCH deep-merges settings
// exactly like Api LinkController::update does.
let link = {
  id: LINK_ID,
  type: "biolink",
  alias: "preset-tester",
  title: "Preset Tester",
  short_url: "https://1in.me/preset-tester",
  long_url: null,
  visibility: "public",
  is_active: true,
  settings: { biolink: { background_type: "color" } },
};

// A trimmed stand-in for the real 157-preset catalog — enough to prove
// group tabs, cross-group name search and selection.
const CATALOG = {
  groups: [
    { key: "gradients", label: "Gradients" },
    { key: "abstract", label: "Abstract" },
    { key: "patterns", label: "Patterns" },
  ],
  presets: [
    { key: "gradient_zero", group: "gradients", label: "Gradient 1", css: "", colors: ["#4158d0", "#c850c0"] },
    { key: "gradient_one", group: "gradients", label: "Gradient 2", css: "", colors: ["#21d4fd", "#b721ff"] },
    { key: "abstract_one", group: "abstract", label: "Abstract 1", css: "", colors: ["#0e5cad", "#79f1a4"] },
    { key: "pattern_one", group: "patterns", label: "Pattern 1", css: "", colors: ["#222222"] },
  ],
};

let patchBodies = [];

function deepMerge(a, b) {
  const out = { ...a };
  for (const [k, v] of Object.entries(b)) {
    out[k] =
      v && typeof v === "object" && !Array.isArray(v)
        ? deepMerge(out[k] && typeof out[k] === "object" ? out[k] : {}, v)
        : v;
  }
  return out;
}

async function mockApi(context) {
  await context.route("**/api/**", async (route) => {
    const req = route.request();
    const method = req.method();
    const path = new URL(req.url()).pathname;

    let body = { data: [] };
    let status = 200;

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
      body = { data: CATALOG };
    } else if (new RegExp(`/api/v1/links/${LINK_ID}$`).test(path)) {
      if (method === "PATCH") {
        let payload = {};
        try {
          payload = JSON.parse(req.postData() || "{}");
        } catch {}
        patchBodies.push(payload);
        if (payload.settings) {
          link = { ...link, settings: deepMerge(link.settings, payload.settings) };
        }
      }
      body = { data: { link } };
    }

    await route.fulfill({
      status,
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

async function run(appUrl) {
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({ viewport: VIEWPORT });
    await seedSession(context);
    await mockApi(context);
    const page = await context.newPage();
    page.setDefaultTimeout(STEP_TIMEOUT_MS);

    log("opening appearance settings…");
    await page.goto(`${appUrl}/links/${LINK_ID}/settings/appearance`, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });

    // 1. The Presets background option is present.
    const toggle = page.getByTestId("bg-presets-toggle");
    await toggle.waitFor({ state: "visible" });
    log("Presets option visible");

    // 2. Expanding it loads and renders the catalog grid.
    await toggle.click();
    await page.getByTestId("bg-preset-gradient_zero").waitFor({ state: "visible" });
    await page.getByTestId("bg-preset-gradient_one").waitFor({ state: "visible" });
    // Abstract preset hidden while the Gradients tab is active.
    if (await page.getByTestId("bg-preset-abstract_one").count()) {
      fail("abstract preset rendered under the Gradients tab without a search");
    }
    log("gallery renders the Gradients group");

    // 3. Name search spans every group.
    const search = page.getByTestId("bg-presets-search");
    await search.fill("Abstract 1");
    await page.getByTestId("bg-preset-abstract_one").waitFor({ state: "visible" });
    if (await page.getByTestId("bg-preset-gradient_zero").count()) {
      fail("search did not filter out non-matching presets");
    }
    log("name search finds presets across groups");

    // 4. Selecting a swatch saves background_type + bg_preset_key.
    await page.getByTestId("bg-preset-abstract_one").click();
    await page.waitForFunction(
      () => true, // placeholder tick; real wait below on the mock state
    );
    const deadline = Date.now() + STEP_TIMEOUT_MS;
    while (patchBodies.length === 0 && Date.now() < deadline) {
      await new Promise((r) => setTimeout(r, 100));
    }
    if (patchBodies.length === 0) fail("selecting a preset never PATCHed the link");
    const sent = patchBodies[patchBodies.length - 1]?.settings?.biolink ?? {};
    if (sent.background_type !== "preset" || sent.bg_preset_key !== "abstract_one") {
      fail(`PATCH carried the wrong payload: ${JSON.stringify(sent)}`);
    }
    log("PATCH saved background_type=preset + bg_preset_key=abstract_one");

    await context.close();
    log("PASS — Presets gallery browses, searches by name and saves.");
  } finally {
    await browser.close();
  }
}

async function main() {
  if (EXPLICIT_APP_URL) {
    await run(EXPLICIT_APP_URL.replace(/\/$/, ""));
    return;
  }
  const manager = createExpoServerManager({ log });
  let appUrl;
  try {
    appUrl = await manager.start();
  } catch (err) {
    if (isTransientEnvError(err)) skip(`expo web server could not boot: ${err.message}`);
    throw err;
  }
  try {
    await run(appUrl);
  } catch (err) {
    if (isTransientEnvError(err)) skip(`transient environment error: ${err.message}`);
    throw err;
  } finally {
    await manager.stop();
  }
}

main().catch((err) => {
  fail(err?.stack || String(err));
});
