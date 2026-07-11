#!/usr/bin/env node
/**
 * End-to-end regression gate for the native Plans & billing screen's
 * "reverse a scheduled downgrade on the web, come back to the app" refresh
 * loop (app/plans.tsx, Task #4526), driving the REAL app in a headless
 * browser.
 *
 * Why this exists: Task #4516 added the sibling runtime e2e
 * (test-plans-foreground-refresh-e2e.mjs) proving the Plans screen
 * live-refreshes an UPGRADE after the app returns to the foreground. The
 * downgrade case shares the exact same wiring — useForegroundRefresh
 * invalidates ["billing","downgrade"] + ["billing","subscription"] +
 * ["billing","plans"] on AppState "active" — but was NOT covered at runtime.
 * A regression there would silently leave a stale "Downgrade scheduled"
 * banner on screen after the user already cancelled that downgrade on the web.
 * The source-driven test-plans-foreground-refresh.mjs pins the callback wiring
 * but can NOT see the full RUNTIME loop: open /plans with a scheduled downgrade,
 * background the app, cancel that downgrade on the web, return to the
 * foreground, and watch React Query actually refetch and the banner actually
 * disappear. A regression that only surfaces at runtime — useForegroundRefresh
 * no longer firing on AppState "active", or a React Query staleness config
 * swallowing the refetch — would slip past the source-driven test entirely.
 *
 * react-native-web's AppState maps "change" -> "active" onto the DOM
 * `visibilitychange` event (it reads document.visibilityState), so this
 * harness simulates the app going to the background and returning to the
 * foreground by flipping document.visibilityState and dispatching
 * `visibilitychange` — exactly the signal the shipped useForegroundRefresh
 * hook subscribes to on web.
 *
 * What it asserts, in order:
 *   1. Booted signed-in on /plans with a scheduled downgrade in place: the
 *      "Downgrade scheduled" banner is on screen.
 *   2. The scheduled downgrade is reversed server-side (the mocked
 *      /billing/downgrade + /billing/subscription now report no scheduled
 *      change) while the app is "backgrounded".
 *   3. Returning to the foreground (visibilitychange -> active) triggers the
 *      refetch WITHOUT a manual reload: the "Downgrade scheduled" banner
 *      disappears.
 *
 * Every /api/** call is intercepted so nothing reaches a real backend. Like
 * the sibling mobile e2e harnesses it boots its OWN throwaway, self-contained
 * Expo web dev server (shared expo-web-server.mjs manager) unless APP_URL
 * points at an already-running one, and SKIPS (exit 0) when a server can't
 * come up or a throwaway run dies of a transient environment error so it never
 * fails CI just because Metro couldn't boot on a starved box.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:plans-downgrade-refresh-e2e
 *
 * Environment:
 *   APP_URL   reuse an already-running Expo web server instead of booting a
 *             throwaway one (skips are disabled then, so local debugging never
 *             silently skips).
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[plans-downgrade-refresh-e2e]", ...args);
}

function fail(msg) {
  console.error("[plans-downgrade-refresh-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[plans-downgrade-refresh-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-plans-downgrade-refresh-token";
const MOCK_USER = {
  id: 4526,
  display_name: "Downgrade Refresh Tester",
  email: "plans-downgrade-refresh@example.com",
};

// The banner headline the shipped Plans screen renders when the account has a
// scheduled downgrade (app/plans.tsx). Its exact text is the assertion target.
const DOWNGRADE_BANNER_TEXT = "Downgrade scheduled";
// A standalone text leaf that always renders once the plan cards load — used
// only to confirm the Plans screen actually mounted before we assert anything
// about the downgrade banner. (The upgrade-banner copy is a mixed <Text> with
// nested spans, so it never renders as an exact-match DOM leaf; a plan name
// does, exactly like the sibling upgrade e2e keys off "Pro".)
const MOUNT_MARKER_TEXT = "Pro";

const EXPLICIT_APP_URL = process.env.APP_URL || null;

// The billing state the mocked backend reports. Starts WITH a scheduled
// downgrade in place; flipping this to false is our stand-in for "the user
// cancelled the scheduled downgrade on the web". Playwright route handlers run
// in this Node process, so the very next /billing/downgrade +
// /billing/subscription fetches after the flip return the reversed state.
let scheduled = true;

function plansPayload() {
  const price = (minor, formatted) => ({ amount_minor: minor, formatted });
  const mk = (id, slug, name, monthlyMinor, monthlyFmt, isCurrent, highlights) => ({
    id,
    slug,
    name,
    description: null,
    feature_highlights: highlights,
    currency: "USD",
    is_default: slug === "free",
    is_current: isCurrent,
    trial_days: 0,
    monthly: price(monthlyMinor, monthlyFmt),
    annual: price(monthlyMinor * 10, null),
    prices: {
      USD: {
        monthly: price(monthlyMinor, monthlyFmt),
        annual: price(monthlyMinor * 10, null),
      },
    },
  });
  return {
    data: {
      currency: "USD",
      currencies: ["USD", "INR"],
      plans: [
        // The user is currently on the paid Pro plan (they scheduled a
        // downgrade off it); Free is the plan they were scheduled to move to.
        mk(1, "free", "Free", 0, "$0.00", false, [
          "1 biolink page",
          "Basic analytics",
        ]),
        mk(3, "pro", "Pro", 1500, "$15.00", true, [
          "Unlimited biolinks",
          "Advanced analytics",
        ]),
      ],
      addons: [],
    },
  };
}

// The scheduled-downgrade slice of GET /billing/downgrade. Present while
// `scheduled` is true; null once the downgrade has been reversed on the web.
function scheduledDowngradePayload() {
  return scheduled
    ? {
        plan_id: 1,
        plan_name: "Free",
        applies_at: "2026-08-01T00:00:00Z",
      }
    : null;
}

// Intercept every backend call. Only a handful of endpoints matter for the
// boot + plans + downgrade-banner path; everything else gets an empty success
// envelope so the authenticated tab/data burst never reaches a real API.
async function mockApi(context) {
  await context.route("**/api/**", async (route) => {
    const url = new URL(route.request().url());
    const path = url.pathname;
    let body = { data: {} };
    if (/\/api\/v1\/auth\/me$/.test(path)) {
      body = { data: { user: MOCK_USER } };
    } else if (/\/api\/v1\/onboarding$/.test(path)) {
      // A fully onboarded account so the launch gate lands straight on the
      // requested /plans route instead of bouncing through /setup.
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
    } else if (/\/api\/v1\/billing\/plans$/.test(path)) {
      body = plansPayload();
    } else if (/\/api\/v1\/billing\/downgrade$/.test(path)) {
      // Drives the "Downgrade scheduled" banner via scheduled_downgrade.
      body = {
        data: {
          subscription: {
            id: 55,
            billing_cycle: "monthly",
            currency: "USD",
            current_period_end: "2026-08-01T00:00:00Z",
          },
          current_plan: { id: 3, name: "Pro", formatted: "$15.00" },
          plans: [],
          scheduled_downgrade: scheduledDowngradePayload(),
        },
      };
    } else if (/\/api\/v1\/billing\/subscription$/.test(path)) {
      // An active (not cancel-at-period-end) subscription, so the
      // "Cancellation scheduled" banner never renders and the only scheduled
      // banner under test is the "Downgrade scheduled" one. Its
      // scheduled_downgrade mirrors the downgrade endpoint so both billing
      // queries agree on the reversed state after the flip.
      body = {
        data: {
          subscription: {
            id: 55,
            plan_id: 3,
            plan_name: "Pro",
            status: "active",
            billing_cycle: "monthly",
            current_period_start: "2026-07-01T00:00:00Z",
            current_period_end: "2026-08-01T00:00:00Z",
            cancel_at: null,
            cancel_at_period_end: false,
            scheduled_downgrade: scheduledDowngradePayload(),
            gateway: "paypal",
            currency: "USD",
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

// True when a text leaf with EXACTLY `text` is present in the rendered DOM.
// Mirrors what the user sees without depending on RNW's generated class names
// or any test-only markup.
async function hasText(page, text) {
  return page.evaluate((needle) => {
    const leaves = Array.from(document.querySelectorAll("*")).filter(
      (el) => el.children.length === 0 && el.textContent.trim().length > 0,
    );
    return leaves.some((el) => el.textContent.trim() === needle);
  }, text);
}

// Poll the DOM until `predicate(present)` holds or the step budget expires, so
// we absorb the async refetch + re-render after the foreground event instead of
// racing a single snapshot.
async function waitForText(page, text, wantPresent, whatFailed) {
  const deadline = Date.now() + STEP_TIMEOUT_MS;
  let present = false;
  while (Date.now() < deadline) {
    present = await hasText(page, text);
    if (present === wantPresent) return;
    await page.waitForTimeout(150);
  }
  fail(`${whatFailed} — "${text}" present=${present}, wanted present=${wantPresent}`);
}

async function runCheck(page, appUrl) {
  const target = `${appUrl.replace(/\/$/, "")}/plans`;
  log("navigating to", target, "(signed-in, scheduled downgrade in place)");
  await page.goto(target, {
    waitUntil: "domcontentloaded",
    timeout: NAV_TIMEOUT_MS,
  });

  // Wait for the app to mount and the Plans screen to render at all.
  await page
    .getByText(MOUNT_MARKER_TEXT, { exact: true })
    .first()
    .waitFor({ timeout: NAV_TIMEOUT_MS })
    .catch(() =>
      fail(
        "the Plans screen never rendered — the /plans route did not mount " +
          "(a gate/layout redirect may have stolen it)",
      ),
    );

  // ---- 1. Scheduled downgrade in place: the banner is on screen -----------
  await waitForText(
    page,
    DOWNGRADE_BANNER_TEXT,
    true,
    "the 'Downgrade scheduled' banner did not render for an account with a " +
      "scheduled downgrade",
  );
  log("initial state OK: 'Downgrade scheduled' banner visible");

  // ---- 2. Downgrade reversed on the web while the app is backgrounded -----
  // Flip the mocked backend BEFORE simulating the foreground so the refetch
  // fired by useForegroundRefresh sees the reversed (no-scheduled-change) state.
  scheduled = false;
  log("scheduled downgrade cancelled server-side (mock now reports no change)");

  // ---- 3. App returns to the foreground: assert the banner clears ---------
  // react-native-web's AppState reads document.visibilityState and emits its
  // "change" event on `visibilitychange`. Simulate background -> foreground so
  // the shipped useForegroundRefresh hook fires "active" and invalidates the
  // billing queries — no manual reload.
  await page.evaluate(() => {
    const setVisibility = (value) => {
      Object.defineProperty(document, "visibilityState", {
        configurable: true,
        get: () => value,
      });
    };
    // Background first (fires "change" -> background; no refresh), then
    // foreground (fires "change" -> active; triggers the refresh).
    setVisibility("hidden");
    document.dispatchEvent(new Event("visibilitychange"));
    setVisibility("visible");
    document.dispatchEvent(new Event("visibilitychange"));
  });
  log("dispatched background -> foreground (AppState active) visibility change");

  await waitForText(
    page,
    DOWNGRADE_BANNER_TEXT,
    false,
    "returning to the foreground did NOT clear the 'Downgrade scheduled' " +
      "banner — the foreground refetch/re-render did not happen " +
      "(useForegroundRefresh regression or a stale-query config)",
  );
  log(
    "foreground refresh OK: 'Downgrade scheduled' banner cleared live, " +
      "without a reload",
  );
}

async function run() {
  const { acquireServer, stopExpo } = createExpoServerManager(log);
  const server = await acquireServer("plans-downgrade-refresh", EXPLICIT_APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
    return;
  }
  const { appUrl, child, explicit } = server;
  log("driving the plans downgrade-refresh check against", appUrl);

  let browser;
  try {
    browser = await chromium.launch({ headless: true });
  } catch (e) {
    stopExpo(child);
    skip(
      `could not launch a headless browser in this environment: ${e?.message || e}`,
    );
    return;
  }

  try {
    const context = await browser.newContext({ viewport: VIEWPORT });
    // Seed a signed-in, onboarded session BEFORE any app code runs so the
    // launch gate lands straight on the requested route (addInitScript runs
    // on the first document, exactly like an app resumed while signed in).
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
      await runCheck(page, appUrl);
    } catch (e) {
      // Best-effort contract: transient environment errors on a throwaway
      // server SKIP; real regressions exited 1 via fail() before reaching
      // here. Against an explicit APP_URL we always fail hard.
      if (!explicit && isTransientEnvError(e)) {
        await browser.close().catch(() => {});
        stopExpo(child);
        skip(
          `the environment was too slow to drive the check ` +
            `(${e?.message?.split("\n")[0] ?? "unknown error"}); ` +
            `skipping (best-effort, not a plans-refresh regression)`,
        );
        return;
      }
      throw e;
    }

    log(
      "PASS: reversing a scheduled downgrade on the web and returning to the " +
        "app clears the 'Downgrade scheduled' banner, live, without a manual " +
        "reload.",
    );
    await browser.close().catch(() => {});
    stopExpo(child);
    // Explicit exit: the throwaway Metro child would otherwise keep the event
    // loop alive; the manager's process-exit hook reaps it.
    process.exit(0);
  } finally {
    await browser.close().catch(() => {});
    stopExpo(child);
  }
}

run().catch((e) => {
  const msg = e?.message || String(e);
  const infra =
    /Target page, context or browser has been closed|browser has been closed|browserType\.launch|pthread_create|Browser closed|Target closed/i.test(
      msg,
    );
  if (infra) {
    skip(
      `browser crashed under environment load, not a product failure: ${msg.split("\n")[0]}`,
    );
  }
  fail(e?.stack || msg);
});
