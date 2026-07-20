#!/usr/bin/env node
/**
 * Rendering-level regression gate for the block editor's "Fetch details"
 * OG preview card, driving the REAL app in a headless browser.
 *
 * test-og-preview-card.mjs pins the runOgFetch/applyOgPreview LOGIC and the
 * JSX wiring statically (source-driven). What it can NOT see is the rendered
 * screen: whether the card section actually mounts for link-family blocks in
 * the running app, whether tapping "Fetch details from URL" really pops the
 * card, and whether Apply/Dismiss reach the visible form inputs. A
 * rendering-level regression (the section gated off, a layout change that
 * swallows the card, a Button wiring break) would slip past the source test.
 *
 * This harness closes that gap. Following test-import-url-e2e.mjs, it boots
 * its OWN throwaway Expo web dev server (or reuses APP_URL), seeds a
 * signed-in session BEFORE any app code runs, mocks every /api/** call —
 * including the block list, the parent link, and GET /og-meta — then opens
 * the block editor for a `link_big` block and asserts:
 *
 *   1. The editor mounts with the "Fetch details from URL" button and the
 *      form inputs (Link text / Description / Thumbnail URL) empty.
 *   2. Tapping Fetch renders the preview card: fetched title + description
 *      visible, Apply + Dismiss buttons, and the "Apply fills only the
 *      empty fields below." explainer — while the form inputs stay EMPTY
 *      (fetch must never auto-fill).
 *   3. Dismiss removes the card and leaves every input untouched.
 *   4. Fetching again and tapping Apply fills the visible inputs with the
 *      fetched title / description / thumbnail, clears the card, and shows
 *      the "Details pre-filled below." success note.
 *
 * Best-effort boot contract (same as the sibling harnesses): if a throwaway
 * Expo server can't boot, or a throwaway run dies of a transient environment
 * error, the harness SKIPs (exit 0). Real regressions exit 1 via fail().
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:og-preview-e2e
 *
 * Environment:
 *   APP_URL   point at an already-running Expo web server (skips disabled).
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  runHarness,
  isTransientEnvError,
} from "./expo-web-server.mjs";

const LINK_ID = 77;
const BLOCK_ID = 9;

// The staged OG payload. Distinctive values so we can tell exactly which
// field each one landed in (or didn't).
const OG_TITLE = "Fetched OG Page Title";
const OG_DESCRIPTION = "A fetched Open Graph description for the card.";
const OG_IMAGE = "https://cdn.example.com/og-image.png";

const MOCK_TOKEN = "sanctum-token-og-preview-e2e";
const MOCK_USER = {
  id: 4243,
  display_name: "OG Preview E2E User",
  email: "og-preview-e2e@example.com",
};

// A link_big block: its editor renders Link text + Description + Thumbnail
// URL inputs (all empty here) and the trackable destination URL that
// "Fetch details from URL" reads.
const MOCK_BLOCK = {
  id: BLOCK_ID,
  link_id: LINK_ID,
  type: "link_big",
  sort_order: 0,
  parent_id: null,
  is_active: true,
  settings: { _link: { url: "https://example.com/some-article" } },
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

const MOCK_LINK = {
  id: LINK_ID,
  type: "biolink",
  alias: "og-preview-e2e",
  title: "OG Preview E2E Biolink",
  short_url: "https://1in.me/og-preview-e2e",
  is_active: true,
  settings: {},
};

function log(...args) {
  console.log("[test-og-preview-e2e]", ...args);
}

function fail(msg) {
  console.error("[test-og-preview-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[test-og-preview-e2e] SKIP:", msg);
  process.exit(0);
}

const EXPLICIT_APP_URL = process.env.APP_URL || null;

const { acquireServer, stopExpo } = createExpoServerManager(log);

// Deterministic backend: every /api/** call is intercepted. The block
// editor's boot path needs auth/me, onboarding, the block list, and the
// parent link; GET /og-meta returns the staged payload and counts calls.
function mockApi(context, state) {
  return context.route("**/api/**", async (route) => {
    const url = new URL(route.request().url());
    const path = url.pathname;
    let body = { data: {} };
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
    } else if (new RegExp(`/api/v1/links/${LINK_ID}/blocks$`).test(path)) {
      body = { data: { items: [MOCK_BLOCK] } };
    } else if (new RegExp(`/api/v1/links/${LINK_ID}$`).test(path)) {
      body = { data: { link: MOCK_LINK } };
    } else if (/\/api\/v1\/links$/.test(path)) {
      body = { data: { items: [], total: 0 } };
    } else if (/\/api\/v1\/og-meta$/.test(path)) {
      state.ogFetches += 1;
      body = {
        data: {
          meta: {
            title: OG_TITLE,
            description: OG_DESCRIPTION,
            image_url: OG_IMAGE,
            favicon_url: "https://example.com/favicon.ico",
          },
        },
      };
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(body),
    });
  });
}

// Snapshot every rendered text input's current value. RN-web renders
// TextInput as <input>/<textarea>, so this sees exactly what the creator
// sees — the truest possible "did Apply/Dismiss touch the form" oracle.
function readInputValues(page) {
  return page.evaluate(() =>
    Array.from(document.querySelectorAll("input, textarea")).map(
      (el) => el.value ?? "",
    ),
  );
}

async function assertNoFetchedValues(page, where) {
  const values = await readInputValues(page);
  for (const v of values) {
    if (
      v === OG_TITLE ||
      v === OG_DESCRIPTION ||
      v === OG_IMAGE
    ) {
      fail(
        `a form input already contains the fetched value "${v}" ${where} — ` +
          `the OG meta leaked into the form without Apply`,
      );
    }
  }
}

async function tapButton(page, label, why) {
  const btn = page.getByText(label, { exact: true }).first();
  await btn
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
    .catch(() => fail(`the "${label}" button never rendered — ${why}`));
  await btn.click();
}

async function waitForCard(page, why) {
  await page
    .getByText(OG_TITLE, { exact: false })
    .first()
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail(`the preview card never showed the fetched title — ${why}`),
    );
  const explainer = page.getByText("Apply fills only the empty fields below.", {
    exact: false,
  });
  await explainer
    .first()
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail("the preview card's fill-only-empty explainer copy is missing"),
    );
  const desc = page.getByText(OG_DESCRIPTION, { exact: false }).first();
  if (!(await desc.isVisible().catch(() => false))) {
    fail("the preview card does not show the fetched description");
  }
  for (const label of ["Apply", "Dismiss"]) {
    const btn = page.getByText(label, { exact: true }).first();
    if (!(await btn.isVisible().catch(() => false))) {
      fail(`the preview card's "${label}" button is missing`);
    }
  }
}

async function waitForCardGone(page, why) {
  await page
    .getByText("Apply fills only the empty fields below.", { exact: false })
    .first()
    .waitFor({ state: "hidden", timeout: STEP_TIMEOUT_MS })
    .catch(() => fail(`the preview card did not disappear — ${why}`));
}

async function runOgPreviewCheck(page, appUrl, state) {
  const target = `${appUrl.replace(/\/$/, "")}/links/${LINK_ID}/blocks/${BLOCK_ID}`;
  log("navigating to the block editor at", target);
  await page.goto(target, {
    waitUntil: "domcontentloaded",
    timeout: NAV_TIMEOUT_MS,
  });

  await page.waitForFunction(
    () => document.body && document.body.innerText.trim().length > 0,
    null,
    { timeout: NAV_TIMEOUT_MS },
  );

  // 1. The editor mounted with the Fetch button; the form starts empty.
  const fetchBtn = page
    .getByText("Fetch details from URL", { exact: false })
    .first();
  await fetchBtn
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail(
        'the "Fetch details from URL" button never rendered — the OG ' +
          "section is not mounting for a link_big block",
      ),
    );
  await assertNoFetchedValues(page, "before any fetch");
  log("block editor mounted; Fetch button visible; form empty");

  // 2. Fetch → the preview card appears; the form must STAY empty.
  await fetchBtn.click();
  await waitForCard(page, "after tapping Fetch");
  if (state.ogFetches < 1) {
    fail("the og-meta endpoint was never called after tapping Fetch");
  }
  await assertNoFetchedValues(
    page,
    "while the preview card is showing (before Apply)",
  );
  log("Fetch staged the card without touching the form");

  // 3. Dismiss → card gone, inputs untouched.
  await tapButton(page, "Dismiss", "the card must offer Dismiss");
  await waitForCardGone(page, "after tapping Dismiss");
  await assertNoFetchedValues(page, "after Dismiss");
  log("Dismiss removed the card and left the form untouched");

  // 4. Fetch again → Apply → the visible inputs are filled.
  await fetchBtn.click();
  await waitForCard(page, "on the second fetch (after a Dismiss)");
  await tapButton(page, "Apply", "the card must offer Apply");
  await waitForCardGone(page, "after tapping Apply");

  // Poll the DOM inputs until all three fetched values landed (the state
  // update commits on the next render).
  await page
    .waitForFunction(
      ({ t, d, i }) => {
        const vals = Array.from(
          document.querySelectorAll("input, textarea"),
        ).map((el) => el.value ?? "");
        return vals.includes(t) && vals.includes(d) && vals.includes(i);
      },
      { t: OG_TITLE, d: OG_DESCRIPTION, i: OG_IMAGE },
      { timeout: STEP_TIMEOUT_MS },
    )
    .catch(async () => {
      const vals = await readInputValues(page);
      fail(
        "Apply did not fill the visible inputs with the fetched " +
          `title/description/thumbnail. Input values: ${JSON.stringify(vals)}`,
      );
    });

  // The success note the creator sees after Apply.
  const successNote = await page
    .getByText("Details pre-filled below.", { exact: false })
    .first()
    .isVisible()
    .catch(() => false);
  if (!successNote) {
    fail('the "Details pre-filled below." success note is missing after Apply');
  }
  log("Apply filled the visible inputs and showed the success note");
}

async function run() {
  const server = await acquireServer("og-preview", EXPLICIT_APP_URL);
  if (!server) {
    skip(
      "the throwaway Expo server could not start; skipping the OG preview " +
        "card check",
    );
    return;
  }
  const { appUrl, child, explicit } = server;
  log("driving the OG preview card check against", appUrl);

  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({
      viewport: { width: 400, height: 900 },
    });
    // Seed a signed-in, fully-onboarded session before any app code runs so
    // the launch gate never bounces the deep navigation to the editor.
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
    const state = { ogFetches: 0 };
    await mockApi(context, state);

    const page = await context.newPage();
    page.on("pageerror", (e) => log("pageerror:", e.message));
    page.setDefaultTimeout(STEP_TIMEOUT_MS);
    page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

    try {
      await runOgPreviewCheck(page, appUrl, state);
    } catch (e) {
      if (!explicit && isTransientEnvError(e)) {
        await browser.close().catch(() => {});
        stopExpo(child);
        skip(
          `the environment was too slow to drive the check ` +
            `(${e?.message?.split("\n")[0] ?? "unknown error"}); ` +
            `skipping (best-effort, not an OG-preview regression)`,
        );
        return;
      }
      throw e;
    }

    log(
      "PASS: the OG preview card renders on Fetch, Dismiss leaves the form " +
        "untouched, and Apply fills the visible inputs end-to-end.",
    );
  } finally {
    await browser.close();
    stopExpo(child);
  }
}

runHarness(run, { log, onError: (e) => console.error(e) });
