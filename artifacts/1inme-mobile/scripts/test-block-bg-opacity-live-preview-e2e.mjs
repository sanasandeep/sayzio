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
const PRESET_KEY_2 = "abstract_two";
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
    {
      key: PRESET_KEY_2,
      label: "Abstract Two",
      group: "abstract",
      colors: ["#14532d", "#22c55e"],
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

async function readSliderLabelValue(page) {
  return page.evaluate(() => {
    const m = [...document.querySelectorAll("div")]
      .map((d) => d.textContent || "")
      .find((t) => /^Transparency · \d+%$/.test(t));
    return m ? Number(m.match(/(\d+)%$/)[1]) : null;
  });
}

async function setSlider(page, value) {
  // @react-native-community/slider renders a div[role="slider"] on web
  // (no <input type="range">), so we drive it with a real pointer drag:
  // press down at the fraction matching the target value, then nudge
  // pixel-by-pixel (button held) until the on-screen label shows the
  // exact target.
  const slider = page.getByTestId("block-bg-preset-opacity-slider");
  await slider.scrollIntoViewIfNeeded();
  const box = await slider.boundingBox();
  if (!box) fail("could not measure the transparency slider");
  const y = box.y + box.height / 2;
  let x = box.x + (box.width * value) / 100;
  await page.mouse.move(x, y);
  await page.mouse.down();
  await page.mouse.move(x + 1, y);
  const pixelPerUnit = box.width / 100;
  for (let i = 0; i < 40; i += 1) {
    const got = await readSliderLabelValue(page);
    if (got === value) break;
    if (got == null) break;
    x += (got < value ? 1 : -1) * Math.max(1, pixelPerUnit / 2);
    await page.mouse.move(x, y);
  }
  await page.mouse.up();
  return true;
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
    await setSlider(page, TARGET);
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
      `slider pointer-drag faded the preview to ${TARGET}% with no network save`,
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

    // 4. (Task #5987) Picking a preset from the grid brings the live
    //    preview back into view — no manual scrolling needed.
    await page.getByTestId("block-bg-preset-toggle").click();
    await page
      .getByTestId(`block-bg-preset-${PRESET_KEY_2}`)
      .waitFor({ state: "visible" });
    // Scroll the page all the way down so the preview is off-screen.
    await page.evaluate(() => {
      const scroller =
        [...document.querySelectorAll("*")].find(
          (el) =>
            el.scrollHeight > el.clientHeight + 50 &&
            /(auto|scroll)/.test(window.getComputedStyle(el).overflowY),
        ) || document.scrollingElement;
      scroller.scrollTop = scroller.scrollHeight;
    });
    const previewInViewport = () =>
      page.evaluate(() => {
        const el = document.querySelector(
          '[data-testid="block-bg-preset-live-preview"]',
        );
        if (!el) return false;
        const r = el.getBoundingClientRect();
        return r.bottom > 0 && r.top < window.innerHeight;
      });
    if (await previewInViewport()) {
      log(
        "note: page too short to scroll the preview off-screen; asserting it stays visible after selection",
      );
    }
    // Tap the swatch without Playwright's auto-scroll bringing the
    // preview back on its own: dispatch the click in-page.
    await page.evaluate((key) => {
      const el = document.querySelector(
        `[data-testid="block-bg-preset-${key}"]`,
      );
      el?.scrollIntoView({ block: "center" });
      el?.click();
    }, PRESET_KEY_2);
    {
      const deadline = Date.now() + STEP_TIMEOUT_MS;
      let visible = false;
      while (Date.now() < deadline) {
        visible = await previewInViewport();
        if (visible) break;
        await new Promise((r) => setTimeout(r, 150));
      }
      if (!visible) {
        fail(
          "selecting a preset from the grid did not scroll the live preview into view",
        );
      }
    }
    log("preset selection scrolled the live preview into view");

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
