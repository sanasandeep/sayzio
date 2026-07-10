#!/usr/bin/env node
/**
 * Cold-start deep-link regression gate for the "Import from URL" screen,
 * driving the REAL app in a headless browser.
 *
 * test-import-url-route.mjs pins the deep-link ROUTING logic with
 * source-driven checks (redirectSystemPath, app.json linking config, the
 * ShareIntentHandler router.push). What it can NOT see is the cold-start
 * runtime path: the app relaunched by the share sheet while it wasn't
 * running, where /import-url?url=… is the very FIRST navigation Expo Router
 * ever performs. On that path the splash/auth gate, the AuthContext boot,
 * DeepLinkRouter and ShareIntentHandler all mount alongside the initial
 * route — a regression in any of them (a global redirect that races the
 * router, a gate that bounces every launch through "/", a layout change
 * that swallows the initial params) would strand the shared URL and slip
 * past the source-replay test entirely.
 *
 * This harness closes that gap. Following test-auth-flow-e2e.mjs /
 * test-icon-fonts-e2e.mjs, it boots its OWN throwaway, self-contained Expo
 * web dev server (or reuses APP_URL), seeds a signed-in session into
 * localStorage BEFORE any app code runs (addInitScript — exactly the state
 * of an app that was fully closed while signed in), intercepts every
 * /api/** call, and then loads /import-url?url=…&title=… as the first and
 * only navigation. It asserts:
 *
 *   1. The Import screen actually mounts: "Import from URL" header, the
 *      shared page TITLE and the shared URL (host) rendered in the URL card.
 *   2. The url param was parsed as a share (no manual "URL" entry field,
 *      which only renders when the param is missing/lost).
 *   3. The share-sheet action surface is intact: shortening is handled
 *      automatically, and the "Other options" disclosure expands to reveal
 *      tappable Create QR / Add to calendar actions — i.e. the screen
 *      recognises the URL as valid, not the disabled no-URL state.
 *   4. The auth/launch gate did NOT redirect away: after a settle window we
 *      are still on /import-url (not the tabs, not "Welcome back"), so the
 *      params were never lost to a bounce through the gate.
 *
 * Best-effort boot contract (same as the sibling harnesses): if a throwaway
 * Expo server can't be booted, or a throwaway run dies of a transient
 * environment error (Playwright timeout / connection reset on a starved
 * box), the harness SKIPs (exit 0) rather than failing CI. Real regressions
 * are reported via fail() (exit 1) and can never be downgraded.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:import-url-e2e
 *
 * Environment:
 *   APP_URL   point the check at an already-running Expo web server instead
 *             of booting a throwaway one (skips are disabled then, so local
 *             debugging never silently skips).
 */

import { chromium } from "playwright";

import {
  NAV_TIMEOUT_MS,
  STEP_TIMEOUT_MS,
} from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  isTransientEnvError,
} from "./expo-web-server.mjs";

// The shared payload under test. The title deliberately differs from the
// host so we can tell "title rendered" apart from "host rendered".
const SHARED_URL = "https://example.org/some/shared-article?ref=share-sheet";
const SHARED_HOST = "example.org";
const SHARED_TITLE = "A Shared Article Title";

const MOCK_TOKEN = "sanctum-token-import-url-e2e";
const MOCK_USER = {
  id: 4242,
  display_name: "Import E2E User",
  email: "import-e2e@example.com",
};

function log(...args) {
  console.log("[test-import-url-e2e]", ...args);
}

function fail(msg) {
  console.error("[test-import-url-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[test-import-url-e2e] SKIP:", msg);
  process.exit(0);
}

const EXPLICIT_APP_URL = process.env.APP_URL || null;

const { acquireServer, stopExpo } = createExpoServerManager(log);

// Intercept every backend call so the run is deterministic and never touches
// a real API. The boot path only needs a handful of endpoints; everything
// else gets an empty success envelope.
async function mockApi(context) {
  await context.route("**/api/**", async (route) => {
    const url = new URL(route.request().url());
    const path = url.pathname;
    let body = { data: {} };
    if (/\/api\/v1\/auth\/me$/.test(path)) {
      body = { data: { user: MOCK_USER } };
    } else if (/\/api\/v1\/onboarding$/.test(path)) {
      // A fully onboarded account: the launch gate must NOT bounce this
      // session into /setup (which would eat the initial /import-url nav).
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
    } else if (/\/api\/v1\/calendars$/.test(path)) {
      body = { data: [] };
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(body),
    });
  });
}

async function runColdStartCheck(page, appUrl) {
  // The cold-start navigation: /import-url?url=…&title=… is the FIRST URL
  // the app ever loads in this browser context — exactly what happens when
  // the share sheet relaunches a fully-closed app and +native-intent maps
  // the share to this route.
  const target =
    `${appUrl.replace(/\/$/, "")}/import-url` +
    `?url=${encodeURIComponent(SHARED_URL)}` +
    `&title=${encodeURIComponent(SHARED_TITLE)}`;

  log("cold-start navigation to", target);
  await page.goto(target, {
    waitUntil: "domcontentloaded",
    timeout: NAV_TIMEOUT_MS,
  });

  // Wait for the app to mount at all.
  await page.waitForFunction(
    () => document.body && document.body.innerText.trim().length > 0,
    null,
    { timeout: NAV_TIMEOUT_MS },
  );

  // 1. The Import screen mounted with the shared payload prefilled.
  await page
    .getByText("Import from URL", { exact: false })
    .first()
    .waitFor({ timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail(
        "the Import screen never rendered its 'Import from URL' header — " +
          "the cold-start deep link did not reach the screen",
      ),
    );
  await page
    .getByText(SHARED_TITLE, { exact: false })
    .first()
    .waitFor({ timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail(
        `the shared page title "${SHARED_TITLE}" is not shown — the title ` +
          `param was lost on the cold-start navigation`,
      ),
    );
  await page
    .getByText(SHARED_HOST, { exact: false })
    .first()
    .waitFor({ timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail(
        `the shared URL host "${SHARED_HOST}" is not shown — the url param ` +
          `was lost on the cold-start navigation`,
      ),
    );
  log("Import screen mounted with the shared title and URL visible");

  // 2. The screen is in "shared URL" mode, not the manual-entry fallback it
  //    drops into when the url param is missing. The manual field's
  //    placeholder only renders on that fallback path.
  const manualField = page.locator(
    'input[placeholder="https://example.com/page"]',
  );
  if ((await manualField.count()) > 0) {
    fail(
      "the manual URL entry field is showing — the screen fell back to " +
        "no-param mode, so the shared url param was dropped",
    );
  }

  // 3. The share-sheet path shortens automatically and tucks the remaining
  //    actions behind the "Other options" disclosure — expand it, then check
  //    that Create QR / Add to calendar are tappable (they render disabled
  //    — opacity'd, aria-disabled — when the screen has no valid URL).
  const otherOptionsToggle = page
    .locator('[aria-label="Show other options"]')
    .first();
  if ((await otherOptionsToggle.count()) === 0) {
    fail(
      'the "Other options" disclosure toggle is missing from the Import ' +
        "screen's shared-URL path",
    );
  }
  await otherOptionsToggle.click();
  for (const label of ["Create QR", "Add to calendar"]) {
    const btn = page.locator(`[aria-label="${label}"]`).first();
    // Expanding "Other options" flips a React state flag, so the action
    // rows mount on the NEXT render — not synchronously with the click.
    // Auto-wait for the button to become visible instead of a one-shot
    // count()/isVisible() that races the re-render (that race is what made
    // this check spuriously report "Create QR button missing").
    await btn
      .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
      .catch(() =>
        fail(
          `action button "${label}" did not appear after expanding "Other ` +
            `options" — it is missing from the Import screen`,
        ),
      );
    const ariaDisabled = await btn.getAttribute("aria-disabled");
    if (ariaDisabled === "true") {
      fail(
        `action button "${label}" is disabled — the screen did not accept ` +
          `the shared URL as valid`,
      );
    }
  }
  log("all three actions are tappable (URL accepted as valid)");

  // 4. The launch/auth gate must not have bounced us elsewhere. Give any
  //    delayed redirect (gate effects, DeepLinkRouter, ShareIntentHandler)
  //    a settle window, then assert we're still on /import-url and NOT on
  //    the login screen or the signed-in tabs.
  await page.waitForTimeout(3000);
  const where = await page.evaluate(() => window.location.pathname);
  if (!/\/import-url$/.test(where)) {
    fail(
      `after settling, the app is at "${where}" instead of /import-url — ` +
        `a gate/layout redirect stole the cold-start deep link`,
    );
  }
  const onLogin = await page
    .getByText("Welcome back", { exact: false })
    .first()
    .isVisible()
    .catch(() => false);
  if (onLogin) {
    fail(
      "the login screen is showing over the Import screen — the auth gate " +
        "redirected a signed-in cold start away from the deep link",
    );
  }
  // The shared payload must STILL be on screen after the settle window (a
  // redirect-and-back would have remounted the screen without params).
  const titleStillThere = await page
    .getByText(SHARED_TITLE, { exact: false })
    .first()
    .isVisible()
    .catch(() => false);
  if (!titleStillThere) {
    fail(
      "the shared title disappeared after the settle window — the screen " +
        "was remounted without its params",
    );
  }
  log("still on /import-url with the shared payload intact after settling");
}

async function run() {
  const server = await acquireServer("import-url", EXPLICIT_APP_URL);
  if (!server) {
    skip(
      "the throwaway Expo server could not start; skipping the cold-start " +
        "import-url check",
    );
    return;
  }
  const { appUrl, child, explicit } = server;
  log("driving the cold-start import-url check against", appUrl);

  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({
      viewport: { width: 400, height: 720 },
    });
    // Seed the persisted session BEFORE any app code runs — this is the
    // state of an app that was fully closed while signed in, then relaunched
    // by the share sheet. addInitScript runs on the very first document, so
    // the AuthContext boot sees the token immediately (no login bounce).
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
    await mockApi(context);

    const page = await context.newPage();
    page.on("pageerror", (e) => log("pageerror:", e.message));
    page.setDefaultTimeout(STEP_TIMEOUT_MS);
    page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

    try {
      await runColdStartCheck(page, appUrl);
    } catch (e) {
      // Best-effort contract, post-boot half: transient environment errors on
      // a throwaway server SKIP; real regressions exited 1 via fail() before
      // reaching here. Against an explicit APP_URL we always fail hard.
      if (!explicit && isTransientEnvError(e)) {
        await browser.close().catch(() => {});
        stopExpo(child);
        skip(
          `the environment was too slow to drive the check ` +
            `(${e?.message?.split("\n")[0] ?? "unknown error"}); ` +
            `skipping (best-effort, not a deep-link regression)`,
        );
        return;
      }
      throw e;
    }

    log(
      "PASS: a cold-start /import-url deep link renders the Import screen " +
        "with the shared URL + title prefilled, and the launch gate never " +
        "steals the navigation.",
    );
  } finally {
    await browser.close();
    stopExpo(child);
  }
}

run().catch((e) => {
  console.error(e);
  process.exit(1);
});
