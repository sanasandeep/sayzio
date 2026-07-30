#!/usr/bin/env node
/**
 * E2E gate for the Borders quick-pick color swatches (Task #6094 —
 * app/links/[id]/blocks/[blockId].tsx). Drives the REAL app in a headless
 * browser on a device-like viewport.
 *
 * What it asserts, in order:
 *   1. The Borders section renders the fixed preset swatch row
 *      (testID block-border-color-swatches) with every BORDER_COLOR_SWATCHES
 *      entry.
 *   2. Typing a CUSTOM hex into the shorthand Color field and blurring adds
 *      it as an extra tappable swatch AND persists it to
 *      localStorage["biolink.editor.recentBorderColors"].
 *   3. Typing a PRESET hex does NOT duplicate the preset swatch.
 *   4. Tapping a recent swatch fills the Color field.
 *   5. Reloading the editor re-hydrates the remembered swatch from storage.
 *
 * Every /api/** call is intercepted against an in-memory link/block fixture
 * so nothing reaches a real backend. Like the sibling harnesses it boots its
 * own throwaway Expo web server unless APP_URL points at a running one, and
 * SKIPS on transient env errors.
 *
 * Usage:
 *   node artifacts/1inme-mobile/scripts/test-border-color-recents-e2e.mjs
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  runHarness,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[border-color-recents-e2e]", ...args);
}
function fail(msg) {
  console.error("[border-color-recents-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[border-color-recents-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-border-recents-token";
const MOCK_USER = {
  id: 6094,
  display_name: "Border Tester",
  email: "border-recents@example.com",
};
const LINK_ID = 6094;
const BLOCK_ID = 94;
const RECENTS_KEY = "biolink.editor.recentBorderColors";
const CUSTOM_HEX = "#123abc";
const PRESET_HEX = "#7d9bff"; // must match a BORDER_COLOR_SWATCHES entry
const EXPLICIT_APP_URL = process.env.APP_URL || null;

const link = {
  id: LINK_ID,
  type: "biolink",
  alias: "border-tester",
  title: "Border Tester",
  short_url: "https://1in.me/border-tester",
  long_url: null,
  visibility: "public",
  is_active: true,
  design_locked: false,
  settings: { biolink: {} },
};
let block = {
  id: BLOCK_ID,
  link_id: LINK_ID,
  type: "heading",
  sort_order: 0,
  parent_id: null,
  is_active: true,
  settings: { text: "Hello there", _style: {} },
  start_date: null,
  end_date: null,
  max_clicks: null,
  click_count: 0,
  created_at: "2026-07-01T00:00:00Z",
  updated_at: "2026-07-01T00:00:00Z",
};

async function mockApi(context) {
  await context.route("**/api/**", async (route) => {
    const req = route.request();
    const method = req.method();
    const path = new URL(req.url()).pathname;

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
    } else if (
      new RegExp(`/api/v1/links/${LINK_ID}/blocks/${BLOCK_ID}$`).test(path)
    ) {
      if (method === "PATCH") {
        try {
          const payload = JSON.parse(req.postData() || "{}");
          if (payload.settings) block = { ...block, settings: payload.settings };
        } catch {}
      }
      body = { data: { block } };
    } else if (new RegExp(`/api/v1/links/${LINK_ID}/blocks$`).test(path)) {
      body = { data: { items: [block] } };
    } else if (new RegExp(`/api/v1/links/${LINK_ID}$`).test(path)) {
      body = { data: { link } };
    } else if (/\/api\/v1\/block-catalog$/.test(path)) {
      body = { data: { items: [] } };
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

function swatchTestId(hex) {
  return `block-border-color-swatch-${hex.replace("#", "")}`;
}

async function countSwatches(page, hex) {
  return page.evaluate(
    (id) => document.querySelectorAll(`[data-testid="${id}"]`).length,
    swatchTestId(hex),
  );
}

async function readRecents(page) {
  return page.evaluate((key) => {
    try {
      return JSON.parse(window.localStorage.getItem(key) || "[]");
    } catch {
      return null;
    }
  }, RECENTS_KEY);
}

async function openEditor(page, appUrl) {
  await page.goto(`${appUrl}/links/${LINK_ID}/blocks/${BLOCK_ID}`, {
    waitUntil: "domcontentloaded",
    timeout: NAV_TIMEOUT_MS,
  });
  const row = page.getByTestId("block-border-color-swatches");
  await row.scrollIntoViewIfNeeded();
  await row.waitFor({ state: "visible" });
}

async function run(appUrl) {
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({ viewport: VIEWPORT });
    await seedSession(context);
    await mockApi(context);
    const page = await context.newPage();
    page.setDefaultTimeout(STEP_TIMEOUT_MS);

    log("opening block editor…");
    await openEditor(page, appUrl);

    // 1. Preset swatches render.
    if ((await countSwatches(page, PRESET_HEX)) !== 1) {
      fail(`preset swatch ${PRESET_HEX} missing from the row`);
    }
    if ((await countSwatches(page, "#ffffff")) !== 1) {
      fail("preset swatch #ffffff missing from the row");
    }
    log("preset swatch row rendered");

    // 2. Custom hex typed + blurred becomes a recent swatch, persisted.
    const colorInput = page.getByTestId("block-border-color-input");
    await colorInput.scrollIntoViewIfNeeded();
    await colorInput.fill(CUSTOM_HEX);
    await colorInput.blur();
    await page.getByTestId(swatchTestId(CUSTOM_HEX)).waitFor({ state: "visible" });
    const stored = await readRecents(page);
    if (!Array.isArray(stored) || stored[0] !== CUSTOM_HEX) {
      fail(`recents not persisted to localStorage: ${JSON.stringify(stored)}`);
    }
    log(`custom color ${CUSTOM_HEX} remembered and persisted`);

    // 3. A preset hex is never duplicated as a recent.
    await colorInput.fill(PRESET_HEX);
    await colorInput.blur();
    await page.waitForTimeout(300);
    if ((await countSwatches(page, PRESET_HEX)) !== 1) {
      fail(`preset ${PRESET_HEX} was duplicated as a recent swatch`);
    }
    const stored2 = await readRecents(page);
    if (stored2.includes(PRESET_HEX)) {
      fail("preset hex leaked into the persisted recents list");
    }
    log("preset duplicates are not repeated");

    // 4. Tapping the recent swatch fills the Color field.
    await colorInput.fill("");
    await page.getByTestId(swatchTestId(CUSTOM_HEX)).click();
    const val = await colorInput.inputValue();
    if (val !== CUSTOM_HEX) {
      fail(`tapping the recent swatch set "${val}", expected ${CUSTOM_HEX}`);
    }
    log("tapping a recent swatch fills the field");

    // 5. Recents survive a reload (re-hydrated from storage).
    await openEditor(page, appUrl);
    if ((await countSwatches(page, CUSTOM_HEX)) !== 1) {
      fail("recent swatch did not re-hydrate after reload");
    }
    log("recent swatch re-hydrated after reload");

    await context.close();
    log("PASS — border color recents remembered, deduped, tappable, persistent.");
  } finally {
    await browser.close();
  }
}

async function main() {
  const { acquireServer, stopExpo } = createExpoServerManager(log);
  const server = await acquireServer("border-color-recents", EXPLICIT_APP_URL);
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
  process.exit(0);
}

runHarness(main, { log, onError: (err) => fail(err?.stack || String(err)) });
