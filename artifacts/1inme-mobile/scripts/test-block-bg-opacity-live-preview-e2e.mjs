#!/usr/bin/env node
/**
 * E2E gate for the block-level background-preset LIVE preview (Task #5984 —
 * app/links/[id]/blocks/[blockId].tsx). Drives the REAL app in a headless
 * browser on a device-like viewport.
 *
 * What it asserts, in order:
 *   1. Opening a block editor whose block already carries
 *      _style.bg_preset_key + bg_preset_opacity renders the
 *      "Live preview" panel (testID block-bg-preset-live-preview) with a
 *      preset layer whose DOM opacity matches the saved value.
 *   2. Dragging the transparency slider (block-bg-preset-opacity-slider)
 *      updates the "Transparency · N%" label AND the preview layer's
 *      opacity immediately — no save round-trip, no PATCH fired.
 *   3. "Save block" still PATCHes _style.bg_preset_opacity with the
 *      dragged value exactly as before.
 *
 * Every /api/** call is intercepted against an in-memory link/block/catalog
 * fixture so nothing reaches a real backend. Like the sibling harnesses it
 * boots its own throwaway Expo web server unless APP_URL points at a
 * running one, and SKIPS on transient env errors.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:block-bg-opacity-live-preview-e2e
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  runHarness,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[block-bg-opacity-live-preview-e2e]", ...args);
}
function fail(msg) {
  console.error("[block-bg-opacity-live-preview-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[block-bg-opacity-live-preview-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-block-bg-opacity-token";
const MOCK_USER = {
  id: 5984,
  display_name: "Opacity Tester",
  email: "block-bg-opacity@example.com",
};
const LINK_ID = 5984;
const BLOCK_ID = 84;
const PRESET_KEY = "abstract_one";
const SAVED_OPACITY = 80;
const EXPLICIT_APP_URL = process.env.APP_URL || null;

const link = {
  id: LINK_ID,
  type: "biolink",
  alias: "opacity-tester",
  title: "Opacity Tester",
  short_url: "https://1in.me/opacity-tester",
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
  settings: {
    text: "Hello there",
    _style: { bg_preset_key: PRESET_KEY, bg_preset_opacity: SAVED_OPACITY },
  },
  start_date: null,
  end_date: null,
  max_clicks: null,
  click_count: 0,
  created_at: "2026-07-01T00:00:00Z",
  updated_at: "2026-07-01T00:00:00Z",
};

const CATALOG = {
  groups: [{ key: "abstract", label: "Abstract" }],
  presets: [
    {
      key: PRESET_KEY,
      label: "Abstract One",
      group: "abstract",
      colors: ["#3d3654", "#8a5cf6"],
      swatch: null,
      paper: false,
    },
  ],
};

const patchBodies = [];

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
      body = { data: CATALOG };
    } else if (
      new RegExp(`/api/v1/links/${LINK_ID}/blocks/${BLOCK_ID}$`).test(path)
    ) {
      if (method === "PATCH") {
        let payload = {};
        try {
          payload = JSON.parse(req.postData() || "{}");
        } catch {}
        patchBodies.push(payload);
        if (payload.settings) {
          block = { ...block, settings: payload.settings };
        }
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

async function waitFor(predicate, what) {
  const deadline = Date.now() + STEP_TIMEOUT_MS;
  while (!predicate() && Date.now() < deadline) {
    await new Promise((r) => setTimeout(r, 100));
  }
  if (!predicate()) fail(`timed out waiting for ${what}`);
}

/**
 * Read the effective opacity of the preset layer inside the live-preview
 * container: the layer is the (only) descendant whose computed opacity is
 * strictly between 0 and 1, or exactly the expected value. Returns the
 * closest match to any non-1 opacity found, or 1 if all are opaque.
 */
async function readPreviewLayerOpacity(page) {
  return page.evaluate(() => {
    const host = document.querySelector(
      '[data-testid="block-bg-preset-live-preview"]',
    );
    if (!host) return null;
    const opacities = [];
    host.querySelectorAll("*").forEach((el) => {
      const o = parseFloat(window.getComputedStyle(el).opacity);
      if (Number.isFinite(o) && o < 0.999) opacities.push(o);
    });
    // The preset layer is the least-opaque styled descendant.
    return opacities.length ? Math.min(...opacities) : 1;
  });
}

async function setSlider(page, value) {
  // @react-native-community/slider renders a native <input type="range">
  // on web. Set the value programmatically and fire the events RN-web
  // listens for; fall back to keyboard nudges if no range input exists.
  const done = await page.evaluate((v) => {
    const host = document.querySelector(
      '[data-testid="block-bg-preset-opacity-slider"]',
    );
    const input =
      host?.tagName === "INPUT"
        ? host
        : host?.querySelector('input[type="range"]') ||
          document.querySelector('input[type="range"]');
    if (!input) return false;
    const setter = Object.getOwnPropertyDescriptor(
      window.HTMLInputElement.prototype,
      "value",
    ).set;
    setter.call(input, String(v));
    input.dispatchEvent(new Event("input", { bubbles: true }));
    input.dispatchEvent(new Event("change", { bubbles: true }));
    return true;
  }, value);
  if (done) return true;
  // Keyboard fallback: focus the slider and arrow-key toward the target.
  const slider = page.getByTestId("block-bg-preset-opacity-slider");
  await slider.focus();
  for (let i = 0; i < SAVED_OPACITY - value; i += 1) {
    await page.keyboard.press("ArrowLeft");
  }
  return false;
}

async function run(appUrl) {
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({ viewport: VIEWPORT });
    await seedSession(context);
    await mockApi(context);
    const page = await context.newPage();
    page.setDefaultTimeout(STEP_TIMEOUT_MS);

    const editorUrl = `${appUrl}/links/${LINK_ID}/blocks/${BLOCK_ID}`;
    log("opening block editor…");
    await page.goto(editorUrl, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });

    // 1. Live preview renders with the saved opacity.
    await page
      .getByTestId("block-bg-preset-live-preview")
      .waitFor({ state: "visible" });
    await page
      .getByText(`Transparency · ${SAVED_OPACITY}%`, { exact: true })
      .waitFor({ state: "visible" });
    await page.getByText("Live preview", { exact: true }).waitFor({ state: "visible" });
    await waitFor(
      () => true,
      "noop",
    );
    let layerOpacity = null;
    const expectClose = (got, want, what) => {
      if (got == null || Math.abs(got - want) > 0.02) {
        fail(`${what}: expected opacity ≈ ${want}, got ${got}`);
      }
    };
    // The gradient layer paints synchronously; poll briefly for styles.
    {
      const deadline = Date.now() + STEP_TIMEOUT_MS;
      while (Date.now() < deadline) {
        layerOpacity = await readPreviewLayerOpacity(page);
        if (layerOpacity != null && Math.abs(layerOpacity - SAVED_OPACITY / 100) <= 0.02) break;
        await new Promise((r) => setTimeout(r, 150));
      }
    }
    expectClose(layerOpacity, SAVED_OPACITY / 100, "initial preview layer");
    log(`live preview rendered at saved opacity ${SAVED_OPACITY}%`);

    // 2. Dragging the slider fades the preview immediately (no PATCH).
    const TARGET = 30;
    const usedRangeInput = await setSlider(page, TARGET);
    await page
      .getByText(`Transparency · ${TARGET}%`, { exact: true })
      .waitFor({ state: "visible" });
    {
      const deadline = Date.now() + STEP_TIMEOUT_MS;
      while (Date.now() < deadline) {
        layerOpacity = await readPreviewLayerOpacity(page);
        if (layerOpacity != null && Math.abs(layerOpacity - TARGET / 100) <= 0.02) break;
        await new Promise((r) => setTimeout(r, 150));
      }
    }
    expectClose(layerOpacity, TARGET / 100, "post-drag preview layer");
    if (patchBodies.length !== 0) {
      fail("dragging the slider fired a PATCH before Save was pressed");
    }
    log(
      `slider drag (${usedRangeInput ? "range input" : "keyboard"}) faded the preview to ${TARGET}% with no network save`,
    );

    // 3. Save still lands the dragged value in _style.bg_preset_opacity.
    await page.getByText("Save block", { exact: true }).click();
    await waitFor(() => patchBodies.length > 0, "the block PATCH");
    const sentStyle = patchBodies[patchBodies.length - 1]?.settings?._style;
    if (sentStyle?.bg_preset_key !== PRESET_KEY) {
      fail(`PATCH lost bg_preset_key: ${JSON.stringify(sentStyle)}`);
    }
    if (sentStyle?.bg_preset_opacity !== TARGET) {
      fail(
        `PATCH carried bg_preset_opacity=${sentStyle?.bg_preset_opacity}, expected ${TARGET}`,
      );
    }
    log("Save block PATCHed _style.bg_preset_opacity with the dragged value");

    await context.close();
    log("PASS — live preview fades while dragging; save path unchanged.");
  } finally {
    await browser.close();
  }
}

async function main() {
  const { acquireServer, stopExpo } = createExpoServerManager(log);
  const server = await acquireServer("block-bg-opacity-live-preview", EXPLICIT_APP_URL);
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
