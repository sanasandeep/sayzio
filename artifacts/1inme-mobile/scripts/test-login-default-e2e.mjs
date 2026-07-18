#!/usr/bin/env node
/**
 * End-to-end regression check that the sign-in screen (app/(auth)/index.tsx)
 * DEFAULTS to password sign-in on first load — driving the REAL app in a
 * headless browser.
 *
 * Why this exists: the login screen's `loginMethod` state seeds to "password"
 * so a returning user lands on the familiar email+password form, not the OTP
 * "Send code" path. That default is a single initializer
 * (useState<"otp" | "password">("password")); flipping it back to "otp" would
 * silently revert the whole screen with no test to catch it. This harness locks
 * the default in:
 *
 *   - On first load (email channel) a Password field is visible and the primary
 *     CTA reads "Sign in" (NOT "Send code").
 *   - The method-toggle link reads "Get a one-time code instead" on first load
 *     (i.e. it offers the OTP path, because password is already the default).
 *   - The round-trip toggle (password → OTP → password) still swaps the field,
 *     CTA, and toggle-label in lockstep, so neither direction rots.
 *
 * Like the other mobile e2e harnesses it boots its OWN throwaway Expo web dev
 * server (shared expo-web-server.mjs manager) unless APP_URL points at a
 * running one, and SKIPS (exit 0) when a server can't come up so it never fails
 * CI just because Metro couldn't boot.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:login-default-e2e
 *
 * Environment:
 *   APP_URL   reuse an already-running Expo web server instead of booting a
 *             throwaway one (handy for local debugging).
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import { createExpoServerManager, runHarness } from "./expo-web-server.mjs";

function log(...args) {
  console.log("[login-default-e2e]", ...args);
}

function fail(msg) {
  console.error("[login-default-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[login-default-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };

// Exact strings the screen renders. Kept as constants so the intent is obvious
// and a copy change forces a deliberate edit here too.
const CTA_PASSWORD = "Sign in";
const CTA_OTP = "Send code";
const PASSWORD_PLACEHOLDER = "Your password";
const TOGGLE_TO_OTP = "Get a one-time code instead";
const TOGGLE_TO_PASSWORD = "Sign in with password instead";

// Build a browser context wired signed-out with every backend call stubbed so
// nothing hits a real server. Onboarding intro is marked complete so the
// pre-auth slides never intercept us, and /auth/config reports WhatsApp login
// disabled so the screen stays on the plain email channel (no tab strip).
async function prepareContext(browser) {
  const context = await browser.newContext({ viewport: VIEWPORT });

  await context.addInitScript(() => {
    try {
      window.localStorage.setItem("1inme.onboarding.complete", "1");
      window.localStorage.removeItem("1inme.auth.token");
      window.localStorage.removeItem("1inme.auth.user");
    } catch {}
  });

  // Admin-managed brand logos feed — empty payload keeps the wordmark on the
  // bundled fallback and off the network.
  await context.route("**/branding.json**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: "{}",
    }),
  );

  // Login-method policy: WhatsApp login OFF so the screen renders the plain
  // email channel with no tab strip, keeping the password-default assertions
  // deterministic.
  await context.route(
    (url) => /\/auth\/config$/.test(url.pathname),
    (route) =>
      route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: { mobile_login_enabled: false, allowed_country_codes: [] },
        }),
      }),
  );

  // Catch-all so nothing else reaches a real backend. Registered last, so
  // Playwright consults it first; it defers the /auth/config path to the
  // handler above via fallback().
  await context.route("**/api/**", (route) => {
    const path = new URL(route.request().url()).pathname;
    if (/\/auth\/config$/.test(path)) return route.fallback();
    return route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ data: [] }),
    });
  });

  return context;
}

// Poll: is a Text node with EXACTLY this string currently visible?
async function isTextVisible(page, text) {
  return page
    .getByText(text, { exact: true })
    .first()
    .isVisible()
    .catch(() => false);
}

async function assertVisible(page, text, ctx) {
  const ok = await isTextVisible(page, text);
  if (!ok) fail(`${ctx}: expected "${text}" to be visible, but it was not.`);
  log(`ok — "${text}" visible (${ctx})`);
}

async function assertHidden(page, text, ctx) {
  const ok = await isTextVisible(page, text);
  if (ok) fail(`${ctx}: expected "${text}" to be absent, but it was visible.`);
  log(`ok — "${text}" absent (${ctx})`);
}

async function run() {
  const { acquireServer } = createExpoServerManager(log);
  const server = await acquireServer("login-default", process.env.APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
  }
  const appUrl = server.appUrl.endsWith("/")
    ? server.appUrl
    : server.appUrl + "/";

  const browser = await chromium.launch();
  const context = await prepareContext(browser);
  const page = await context.newPage();
  page.setDefaultTimeout(STEP_TIMEOUT_MS);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
  page.on("pageerror", (e) => log("pageerror:", e.message));

  try {
    log(`navigating to ${appUrl}`);
    await page.goto(appUrl, { waitUntil: "domcontentloaded" });

    // Signed out + onboarding complete → the gate lands on "Welcome back".
    await page
      .getByText("Welcome back", { exact: false })
      .first()
      .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });

    // 1) First load defaults to PASSWORD: password field + "Sign in" CTA, and
    //    the toggle offers the OTP path (never the reverse).
    const first = "first load (password default)";
    await page
      .getByPlaceholder(PASSWORD_PLACEHOLDER)
      .first()
      .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
      .catch(() =>
        fail(`${first}: password field ("${PASSWORD_PLACEHOLDER}") not visible.`),
      );
    log(`ok — password field visible (${first})`);
    await assertVisible(page, CTA_PASSWORD, first);
    await assertHidden(page, CTA_OTP, first);
    await assertVisible(page, TOGGLE_TO_OTP, first);
    await assertHidden(page, TOGGLE_TO_PASSWORD, first);

    // 2) Toggle password → OTP: field hides, CTA becomes "Send code", and the
    //    toggle now offers the password path back.
    const otp = "after toggle → OTP";
    await page.getByText(TOGGLE_TO_OTP, { exact: true }).first().click();
    await page
      .getByText(CTA_OTP, { exact: true })
      .first()
      .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
      .catch(() => fail(`${otp}: "${CTA_OTP}" CTA did not appear.`));
    log(`ok — "${CTA_OTP}" visible (${otp})`);
    await assertHidden(page, CTA_PASSWORD, otp);
    await assertVisible(page, TOGGLE_TO_PASSWORD, otp);
    await assertHidden(page, TOGGLE_TO_OTP, otp);
    if (await page.getByPlaceholder(PASSWORD_PLACEHOLDER).first().isVisible().catch(() => false)) {
      fail(`${otp}: password field should be hidden in OTP mode.`);
    }
    log(`ok — password field hidden (${otp})`);

    // 3) Toggle OTP → password (round-trip): field returns, CTA back to
    //    "Sign in", toggle back to offering OTP.
    const back = "after toggle → password (round-trip)";
    await page.getByText(TOGGLE_TO_PASSWORD, { exact: true }).first().click();
    await page
      .getByPlaceholder(PASSWORD_PLACEHOLDER)
      .first()
      .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
      .catch(() =>
        fail(`${back}: password field ("${PASSWORD_PLACEHOLDER}") did not return.`),
      );
    log(`ok — password field visible (${back})`);
    await assertVisible(page, CTA_PASSWORD, back);
    await assertHidden(page, CTA_OTP, back);
    await assertVisible(page, TOGGLE_TO_OTP, back);
    await assertHidden(page, TOGGLE_TO_PASSWORD, back);

    log("PASS: sign-in screen defaults to password and toggles round-trip cleanly");
  } finally {
    await context.close().catch(() => {});
    await browser.close().catch(() => {});
  }
}

// Termination guarantee: runHarness exits the process as soon as run()
// settles and arms a watchdog, so a leaked handle can never stall the run.
runHarness(run, { log, onError: (e) => fail(e?.stack || e?.message || String(e)) });
