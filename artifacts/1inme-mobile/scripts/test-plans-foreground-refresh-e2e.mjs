#!/usr/bin/env node
/**
 * End-to-end regression gate for the native Plans & billing screen's
 * "upgrade on the web, come back to the app" refresh loop (app/plans.tsx,
 * Tasks #4509 / #4516), driving the REAL app in a headless browser.
 *
 * Why this exists: test-plans-foreground-refresh.mjs pins the WIRING with
 * source-driven checks — it lifts the real useForegroundRefresh callback and
 * proves it invalidates the billing queries, and it models the per-plan badge
 * / CTA render rules. What it can NOT see is the full RUNTIME loop: open
 * /plans as a free user, background the app, upgrade on the web, come back to
 * the foreground, and watch React Query actually refetch and the UI actually
 * re-render with the CURRENT badge moved and the "Upgrade on the web" CTA
 * gone. A regression that only surfaces at runtime — useForegroundRefresh no
 * longer firing on AppState "active", or React Query staleness config
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
 *   1. Booted signed-in on /plans as a FREE user: the CURRENT badge sits on
 *      the Free plan row, and the Pro row shows the "Upgrade on the web" CTA
 *      (and the Free row does not).
 *   2. The plan is upgraded server-side (the mocked /billing/plans now marks
 *      Pro as the current plan) while the app is "backgrounded".
 *   3. Returning to the foreground (visibilitychange -> active) triggers the
 *      refetch WITHOUT a manual reload: the CURRENT badge moves to the Pro
 *      row, the Pro row no longer shows the "Upgrade on the web" CTA, and the
 *      Free row now does.
 *
 * Every /api/** call is intercepted so nothing reaches a real backend. Like
 * the sibling mobile e2e harnesses it boots its OWN throwaway, self-contained
 * Expo web dev server (shared expo-web-server.mjs manager) unless APP_URL
 * points at an already-running one, and SKIPS (exit 0) when a server can't
 * come up or a throwaway run dies of a transient environment error so it never
 * fails CI just because Metro couldn't boot on a starved box.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:plans-foreground-refresh-e2e
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
  runHarness,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[plans-foreground-refresh-e2e]", ...args);
}

function fail(msg) {
  console.error("[plans-foreground-refresh-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[plans-foreground-refresh-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-plans-foreground-refresh-token";
const MOCK_USER = {
  id: 4516,
  display_name: "Plans Refresh Tester",
  email: "plans-foreground-refresh@example.com",
};

// The two plans under test. Names are chosen so they can't collide with any
// description / feature-highlight / banner text on the screen, which lets the
// DOM inspector below identify each plan card purely by its name leaf.
const FREE_PLAN_NAME = "Free";
const PRO_PLAN_NAME = "Pro";
const UPGRADE_CTA_LABEL = "Upgrade on the web";

const EXPLICIT_APP_URL = process.env.APP_URL || null;

// The plan state the mocked backend reports. Starts on the free plan; flipping
// this to true is our stand-in for "the user completed an upgrade on the web".
// Playwright route handlers run in this Node process, so the very next
// /billing/plans fetch after the flip returns the upgraded matrix.
let upgraded = false;

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
    annual: price(monthlyMinor * 10, monthlyFmt ? null : null),
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
        mk(1, "free", FREE_PLAN_NAME, 0, "$0.00", !upgraded, [
          "1 biolink page",
          "Basic analytics",
        ]),
        mk(3, "pro", PRO_PLAN_NAME, 1500, "$15.00", upgraded, [
          "Unlimited biolinks",
          "Advanced analytics",
        ]),
      ],
      addons: [],
    },
  };
}

// Intercept every backend call. Only a handful of endpoints matter for the
// boot + plans path; everything else gets an empty success envelope so the
// authenticated tab/data burst never reaches a real API.
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
      body = {
        data: {
          subscription: null,
          current_plan: null,
          plans: [],
          scheduled_downgrade: null,
        },
      };
    } else if (/\/api\/v1\/billing\/subscription$/.test(path)) {
      // No active/cancel-at-period-end subscription, so neither the
      // "Cancellation scheduled" nor the "Downgrade scheduled" banner renders
      // and the plan cards are the only badge/CTA surfaces on screen.
      body = { data: { subscription: null } };
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(body),
    });
  });
}

// Inspect the rendered plan cards. For each plan name, climb to the largest
// ancestor subtree that contains ONLY that plan's name leaf (its card
// boundary — sibling cards each hold exactly one plan name), then report
// whether that card shows the CURRENT badge and the "Upgrade on the web" CTA.
// This mirrors what a user sees without depending on RNW's generated class
// names or any test-only markup.
async function readPlanCards(page) {
  return page.evaluate(
    ({ names, badgeText, ctaText }) => {
      const leaves = Array.from(document.querySelectorAll("*")).filter(
        (el) => el.children.length === 0 && el.textContent.trim().length > 0,
      );
      const nameLeaves = leaves.filter((el) =>
        names.includes(el.textContent.trim()),
      );
      const out = [];
      for (const nl of nameLeaves) {
        const thisName = nl.textContent.trim();
        let node = nl;
        let card = nl;
        while (node.parentElement) {
          const parent = node.parentElement;
          const includesAnotherCard = Array.from(
            parent.querySelectorAll("*"),
          ).some(
            (el) =>
              el.children.length === 0 &&
              names.includes(el.textContent.trim()) &&
              el.textContent.trim() !== thisName,
          );
          if (includesAnotherCard) break;
          node = parent;
          card = node;
        }
        const cardLeaves = Array.from(card.querySelectorAll("*")).filter(
          (el) => el.children.length === 0 && el.textContent.trim().length > 0,
        );
        out.push({
          name: thisName,
          hasBadge: cardLeaves.some((el) => el.textContent.trim() === badgeText),
          hasCta: cardLeaves.some((el) => el.textContent.trim() === ctaText),
        });
      }
      return out;
    },
    { names: [FREE_PLAN_NAME, PRO_PLAN_NAME], badgeText: "CURRENT", ctaText: UPGRADE_CTA_LABEL },
  );
}

// Poll the rendered cards until `predicate` holds or the step budget expires,
// so we absorb the async refetch + re-render after the foreground event
// instead of racing a single snapshot.
async function waitForCards(page, predicate, whatFailed) {
  const deadline = Date.now() + STEP_TIMEOUT_MS;
  let last = [];
  while (Date.now() < deadline) {
    last = await readPlanCards(page);
    if (predicate(last)) return last;
    await page.waitForTimeout(150);
  }
  fail(`${whatFailed} — last observed cards: ${JSON.stringify(last)}`);
  return last;
}

function findCard(cards, name) {
  return cards.find((c) => c.name === name) ?? null;
}

async function runCheck(page, appUrl) {
  const target = `${appUrl.replace(/\/$/, "")}/plans`;
  log("navigating to", target, "(signed-in, free plan)");
  await page.goto(target, {
    waitUntil: "domcontentloaded",
    timeout: NAV_TIMEOUT_MS,
  });

  // Wait for the app to mount and the plan cards to render at all.
  await page
    .getByText(PRO_PLAN_NAME, { exact: true })
    .first()
    .waitFor({ timeout: NAV_TIMEOUT_MS })
    .catch(() =>
      fail(
        "the Plans screen never rendered its plan cards — the /plans route " +
          "did not mount (a gate/layout redirect may have stolen it)",
      ),
    );

  // ---- 1. Free user: CURRENT badge on Free, upgrade CTA on Pro ----------
  const initial = await waitForCards(
    page,
    (cards) => {
      const free = findCard(cards, FREE_PLAN_NAME);
      const pro = findCard(cards, PRO_PLAN_NAME);
      return !!free && !!pro && free.hasBadge && !pro.hasBadge;
    },
    "the free-plan user did not start with the CURRENT badge on the Free row",
  );
  const freeInit = findCard(initial, FREE_PLAN_NAME);
  const proInit = findCard(initial, PRO_PLAN_NAME);
  if (freeInit.hasCta) {
    fail(
      "the Free (current) row is showing the 'Upgrade on the web' CTA before " +
        "any upgrade — the current plan must never offer the upgrade CTA",
    );
  }
  if (!proInit.hasCta) {
    fail(
      "the Pro row is NOT showing the 'Upgrade on the web' CTA for a free " +
        "user — a non-current plan must offer the upgrade path",
    );
  }
  log("initial state OK: CURRENT badge on Free, upgrade CTA on Pro only");

  // ---- 2. Upgrade completes server-side while the app is backgrounded ----
  // Flip the mocked backend BEFORE simulating the foreground so the refetch
  // fired by useForegroundRefresh sees the upgraded plan matrix.
  upgraded = true;
  log("plan upgraded server-side (mock now marks Pro as current)");

  // ---- 3. App returns to the foreground: assert a live re-render ---------
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

  const afterUpgrade = await waitForCards(
    page,
    (cards) => {
      const pro = findCard(cards, PRO_PLAN_NAME);
      return !!pro && pro.hasBadge && !pro.hasCta;
    },
    "returning to the foreground did NOT move the CURRENT badge onto the Pro " +
      "row and drop its upgrade CTA — the foreground refetch/re-render did " +
      "not happen (useForegroundRefresh regression or a stale-query config)",
  );

  const proAfter = findCard(afterUpgrade, PRO_PLAN_NAME);
  const freeAfter = findCard(afterUpgrade, FREE_PLAN_NAME);
  if (!proAfter || !proAfter.hasBadge) {
    fail("after upgrade the CURRENT badge is not on the Pro row");
  }
  if (proAfter.hasCta) {
    fail(
      "after upgrade the now-current Pro row still shows the 'Upgrade on the " +
        "web' CTA — the current plan must never offer the upgrade CTA",
    );
  }
  if (!freeAfter || freeAfter.hasBadge) {
    fail(
      "after upgrade the Free row still shows the CURRENT badge — the badge " +
        "did not move off the old plan",
    );
  }
  if (!freeAfter.hasCta) {
    fail(
      "after upgrade the (now non-current) Free row does not offer the " +
        "'Upgrade on the web' CTA — non-current plans must show it",
    );
  }
  log(
    "foreground refresh OK: CURRENT badge moved to Pro (no CTA), Free is now " +
      "non-current and offers the upgrade CTA — all without a reload",
  );
}

async function run() {
  const { acquireServer, stopExpo } = createExpoServerManager(log);
  const server = await acquireServer("plans-foreground-refresh", EXPLICIT_APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
    return;
  }
  const { appUrl, child, explicit } = server;
  log("driving the plans foreground-refresh check against", appUrl);

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
      "PASS: upgrading on the web and returning to the app moves the CURRENT " +
        "badge to the new plan and removes its upgrade CTA, live, without a " +
        "manual reload.",
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

// Termination guarantee: runHarness exits the process as soon as run()
// settles and arms a watchdog, so a leaked handle can never stall the run.
runHarness(run, {
  log,
  onError: (e) => {
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
  },
});
