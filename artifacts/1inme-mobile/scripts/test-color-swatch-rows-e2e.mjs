#!/usr/bin/env node
/**
 * E2E gate for the shared tap-to-pick color swatch rows (Task #6099 —
 * components/ColorSwatchRow.tsx). Confirms a swatch tap is not a
 * decorative no-op: it must flow through the SAME state as the text
 * input and land in the saved payload.
 *
 * What it asserts, in order:
 *   1. Block editor (app/links/[id]/blocks/[blockId].tsx): tapping
 *      `block-border-color-swatch-#2563eb` updates the
 *      `block-border-color-input` value to #2563eb, and "Save block"
 *      PATCHes settings._style.border_color = "#2563eb".
 *   2. Appearance settings (SettingsForm): tapping
 *      `settings-text_color-swatch-#059669` updates the Text color
 *      input, enables Apply, and the PATCH /links/{id} payload carries
 *      settings.appearance.text_color = "#059669".
 *
 * Every /api/** call is intercepted against an in-memory fixture so
 * nothing reaches a real backend. Boots its own throwaway Expo web
 * server unless APP_URL points at a running one; SKIPs on transient
 * env errors (sibling-harness convention).
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:color-swatch-rows-e2e
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  runHarness,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[color-swatch-rows-e2e]", ...args);
}
function fail(msg) {
  console.error("[color-swatch-rows-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[color-swatch-rows-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-color-swatch-token";
const MOCK_USER = {
  id: 6099,
  display_name: "Swatch Tester",
  email: "color-swatch@example.com",
};
const LINK_ID = 6099;
const BLOCK_ID = 99;
const BORDER_SWATCH = "#2563eb";
const TEXT_SWATCH = "#059669";
const EXPLICIT_APP_URL = process.env.APP_URL || null;

let link = {
  id: LINK_ID,
  type: "biolink",
  alias: "swatch-tester",
  title: "Swatch Tester",
  short_url: "https://1in.me/swatch-tester",
  long_url: null,
  visibility: "public",
  is_active: true,
  design_locked: false,
  settings: { biolink: {}, appearance: { text_color: "#111111" } },
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
    _style: { border_width: "2", border_color: "#ffffff" },
  },
  start_date: null,
  end_date: null,
  max_clicks: null,
  click_count: 0,
  created_at: "2026-07-01T00:00:00Z",
  updated_at: "2026-07-01T00:00:00Z",
};

const blockPatches = [];
const linkPatches = [];

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
        let payload = {};
        try {
          payload = JSON.parse(req.postData() || "{}");
        } catch {}
        blockPatches.push(payload);
        if (payload.settings) block = { ...block, settings: payload.settings };
      }
      body = { data: { block } };
    } else if (new RegExp(`/api/v1/links/${LINK_ID}/blocks$`).test(path)) {
      body = { data: { items: [block] } };
    } else if (new RegExp(`/api/v1/links/${LINK_ID}$`).test(path)) {
      if (method === "PATCH") {
        let payload = {};
        try {
          payload = JSON.parse(req.postData() || "{}");
        } catch {}
        linkPatches.push(payload);
        if (payload.settings) {
          link = {
            ...link,
            settings: { ...link.settings, ...payload.settings },
          };
        }
      }
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
  while (!(await predicate()) && Date.now() < deadline) {
    await new Promise((r) => setTimeout(r, 100));
  }
  if (!(await predicate())) fail(`timed out waiting for ${what}`);
}

// Tap a swatch via an in-page click: RN-web renders Pressables as divs,
// and Playwright's actionability scroll can fight the editor's own
// scroll containers.
async function tapTestId(page, testId) {
  const el = page.getByTestId(testId);
  await el.waitFor({ state: "attached" });
  await page.evaluate((id) => {
    const node = document.querySelector(`[data-testid="${CSS.escape(id)}"]`);
    if (!node) throw new Error(`missing ${id}`);
    node.scrollIntoView({ block: "center" });
    node.click();
  }, testId);
}

async function inputValue(page, testId) {
  return page.evaluate((id) => {
    const node = document.querySelector(`[data-testid="${CSS.escape(id)}"]`);
    if (!node) return null;
    if ("value" in node) return node.value;
    const inner = node.querySelector("input,textarea");
    return inner ? inner.value : null;
  }, testId);
}

async function run(appUrl) {
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({ viewport: VIEWPORT });
    await seedSession(context);
    await mockApi(context);
    const page = await context.newPage();
    page.setDefaultTimeout(STEP_TIMEOUT_MS);

    // ── 1. Block editor: border color swatch → input → PATCH ──────────
    log("opening block editor…");
    await page.goto(`${appUrl}/links/${LINK_ID}/blocks/${BLOCK_ID}`, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });
    await page
      .getByTestId("block-border-color-input")
      .waitFor({ state: "attached" });

    const before = await inputValue(page, "block-border-color-input");
    if (before !== "#ffffff") {
      fail(`border color input did not hydrate: got ${JSON.stringify(before)}`);
    }

    await tapTestId(page, `block-border-color-swatch-${BORDER_SWATCH}`);
    await waitFor(
      async () =>
        (await inputValue(page, "block-border-color-input")) === BORDER_SWATCH,
      `border color input to become ${BORDER_SWATCH}`,
    );
    log(`swatch tap updated the border color input to ${BORDER_SWATCH}`);
    if (blockPatches.length !== 0) {
      fail("tapping a swatch fired a block save before Save was pressed");
    }

    await page.getByText("Save block", { exact: true }).click();
    await waitFor(async () => blockPatches.length > 0, "the block PATCH");
    const sentStyle =
      blockPatches[blockPatches.length - 1]?.settings?._style ?? {};
    if (sentStyle.border_color !== BORDER_SWATCH) {
      fail(
        `block PATCH carried _style.border_color=${JSON.stringify(
          sentStyle.border_color,
        )}, expected ${BORDER_SWATCH}`,
      );
    }
    if (sentStyle.border_width !== "2") {
      fail("block PATCH lost the sibling border_width value");
    }
    log("Save block PATCHed _style.border_color with the tapped swatch color");

    // ── 2. SettingsForm (Appearance): text_color swatch → PATCH ───────
    log("opening appearance settings…");
    await page.goto(`${appUrl}/links/${LINK_ID}/settings/appearance`, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });
    await page
      .getByTestId(`settings-text_color-swatch-${TEXT_SWATCH}`)
      .waitFor({ state: "attached" });

    // Wait for hydration: the Text color field carries the fixture value.
    await waitFor(async () => {
      return page.evaluate(() =>
        [...document.querySelectorAll("input")].some(
          (i) => i.value === "#111111",
        ),
      );
    }, "the appearance form to hydrate");

    await tapTestId(page, `settings-text_color-swatch-${TEXT_SWATCH}`);
    await waitFor(async () => {
      return page.evaluate(
        (want) =>
          [...document.querySelectorAll("input")].some(
            (i) => i.value === want,
          ),
        TEXT_SWATCH,
      );
    }, `the Text color input to become ${TEXT_SWATCH}`);
    log(`swatch tap updated the Text color input to ${TEXT_SWATCH}`);

    await tapTestId(page, "settings-apply");
    await waitFor(async () => linkPatches.length > 0, "the link PATCH");
    const sentAppearance =
      linkPatches[linkPatches.length - 1]?.settings?.appearance ?? {};
    if (sentAppearance.text_color !== TEXT_SWATCH) {
      fail(
        `link PATCH carried appearance.text_color=${JSON.stringify(
          sentAppearance.text_color,
        )}, expected ${TEXT_SWATCH}`,
      );
    }
    log("Apply PATCHed settings.appearance.text_color with the swatch color");

    await context.close();
    log("PASS — swatch taps flow through to the saved payloads.");
  } finally {
    await browser.close();
  }
}

async function main() {
  const { acquireServer, stopExpo } = createExpoServerManager(log);
  const server = await acquireServer("color-swatch-rows", EXPLICIT_APP_URL);
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
