#!/usr/bin/env node
/**
 * End-to-end regression check that the Sayzio brand wordmark stays VISIBLE on
 * the sign-in and first-run setup screens under BOTH light and dark OS color
 * schemes, driving the REAL app in a headless browser.
 *
 * Why this exists: components/Brand.tsx ships two wordmark PNGs —
 * wordmark-dark-text.png (dark ink, for light backgrounds) and
 * wordmark-white-text.png (white ink, for dark backgrounds). <BrandWordmark>
 * normally picks the variant from useColorScheme(). A sibling fix
 * (test-onboarding-slider-e2e.mjs) proved the onboarding splash — which sits on
 * a FIXED dark surface — always forces the white PNG via forceVariant="dark-bg",
 * because otherwise a light-mode device would render the dark-text logo,
 * invisible on the dark bar.
 *
 * The sign-in screen (app/(auth)/index.tsx) sits on a dark gradient
 * (LinearGradient over colors.background) and passes forceVariant="dark-bg"
 * so the white/light wordmark is always used regardless of the OS color
 * scheme — the dark-text logo would be invisible on the dark bar in light
 * mode. This harness asserts the white variant wins on both schemes for the
 * sign-in screen.
 *
 * The setup screen (app/setup.tsx) renders <BrandWordmark> WITHOUT
 * forceVariant, on the THEME-ADAPTIVE colors.background (white in light mode,
 * near-black in dark mode). There the adaptive default is correct — the
 * rendered variant must match the surface: dark-text on the light surface,
 * white on the dark surface. This harness asserts exactly that, so a future
 * change that hard-codes a dark background without forcing the white logo is
 * caught.
 *
 * It reuses assertWordmarkVisibleVariant from ./lib/wordmark-variant.mjs (the
 * same scanning logic the onboarding guard uses) and, like the other mobile
 * e2e harnesses, boots its OWN throwaway Expo web dev server (shared
 * expo-web-server.mjs manager) unless APP_URL points at a running one, and
 * SKIPS (exit 0) when a server can't come up so it never fails CI just because
 * Metro couldn't boot.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:wordmark-legibility-e2e
 *
 * Environment:
 *   APP_URL   reuse an already-running Expo web server instead of booting a
 *             throwaway one (handy for local debugging).
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import { createExpoServerManager, runHarness } from "./expo-web-server.mjs";
import {
  assertWordmarkVisibleVariant,
  assertWordmarkWhiteVariant,
} from "./lib/wordmark-variant.mjs";

function log(...args) {
  console.log("[wordmark-legibility-e2e]", ...args);
}

function fail(msg) {
  console.error("[wordmark-legibility-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[wordmark-legibility-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };

// A mock signed-in session used to reach the setup screen (the launch gate
// only routes a user with a token + a null server-side onboarded_at to /setup).
const MOCK_TOKEN = "sanctum-token-wordmark-e2e";
const MOCK_USER = { id: 501, display_name: "Wordmark E2E", email: "w@e2e.test" };

// Build a browser context wired for a given OS color scheme, with every backend
// call stubbed so nothing hits a real server. `signedIn` seeds the persisted
// Sanctum session (for the setup screen); when false the install is signed out
// (for the sign-in screen). Onboarding intro is always marked complete so the
// pre-auth slides never intercept us.
async function prepareContext(browser, { colorScheme, signedIn }) {
  const context = await browser.newContext({ viewport: VIEWPORT, colorScheme });

  await context.addInitScript(
    ({ signedIn, token, user }) => {
      try {
        window.localStorage.setItem("1inme.onboarding.complete", "1");
        if (signedIn) {
          window.localStorage.setItem("1inme.auth.token", token);
          window.localStorage.setItem("1inme.auth.user", JSON.stringify(user));
        } else {
          window.localStorage.removeItem("1inme.auth.token");
          window.localStorage.removeItem("1inme.auth.user");
        }
      } catch {}
    },
    { signedIn, token: MOCK_TOKEN, user: MOCK_USER },
  );

  // Admin-managed brand logos feed. Empty payload → fetchBrandLogos() returns
  // null so the wordmark falls back to the BUNDLED PNGs, making the variant
  // assertion deterministic (independent of any real admin config).
  await context.route("**/branding.json**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: "{}",
    }),
  );

  // The launch gate reads the server onboarding status (GET /api/v1/onboarding)
  // to decide /setup vs /(tabs); a null onboarded_at means "first-run setup
  // still owed" → /setup. getOnboardingStatus() unwraps the {data} envelope.
  await context.route(
    (url) => /\/api\/v1\/onboarding$/.test(url.pathname),
    (route) =>
      route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            onboarded_at: null,
            has_handle: true,
            email_verified: true,
            has_links: false,
            has_biolink: false,
            whatsapp_pending: false,
            privacy_pending: false,
          },
        }),
      }),
  );

  // Catch-all so nothing else reaches a real backend. Registered last, so
  // Playwright consults it first; it defers the onboarding-status path to the
  // handler above via fallback().
  await context.route("**/api/**", (route) => {
    const path = new URL(route.request().url()).pathname;
    if (/\/api\/v1\/onboarding$/.test(path)) return route.fallback();
    return route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ data: [] }),
    });
  });

  return context;
}

// Verify the sign-in screen ("Welcome back") shows the WHITE wordmark under
// both schemes. The sign-in screen sits on a dark gradient and uses
// forceVariant="dark-bg", so the white variant must always win regardless of
// the OS color scheme.
async function checkSignInScreen(browser, appUrl, colorScheme) {
  const context = await prepareContext(browser, { colorScheme, signedIn: false });
  const page = await context.newPage();
  page.setDefaultTimeout(STEP_TIMEOUT_MS);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
  page.on("pageerror", (e) => log(`pageerror (sign-in/${colorScheme}):`, e.message));
  try {
    log(`sign-in: navigating to ${appUrl} with ${colorScheme} scheme`);
    await page.goto(appUrl, { waitUntil: "domcontentloaded" });
    // Signed out + onboarding complete → the gate lands on "Welcome back".
    await page
      .getByText("Welcome back", { exact: false })
      .first()
      .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
    await assertWordmarkWhiteVariant(page, {
      label: `sign-in screen (${colorScheme} scheme)`,
      fail,
      log,
    });
  } finally {
    await context.close().catch(() => {});
  }
}

// Verify the first-run setup screen shows the theme-appropriate, visible
// wordmark under the given scheme.
async function checkSetupScreen(browser, appUrl, colorScheme) {
  const context = await prepareContext(browser, { colorScheme, signedIn: true });
  const page = await context.newPage();
  page.setDefaultTimeout(STEP_TIMEOUT_MS);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
  page.on("pageerror", (e) => log(`pageerror (setup/${colorScheme}):`, e.message));
  try {
    log(`setup: navigating to ${appUrl} with ${colorScheme} scheme`);
    await page.goto(appUrl, { waitUntil: "domcontentloaded" });
    // Signed in + null onboarded_at → the gate routes to /setup, whose top bar
    // carries the wordmark and the persistent "Skip setup" escape hatch.
    await page
      .getByText("Skip setup", { exact: false })
      .first()
      .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
    await assertWordmarkVisibleVariant(page, {
      colorScheme,
      label: `setup screen (${colorScheme} scheme)`,
      fail,
      log,
    });
  } finally {
    await context.close().catch(() => {});
  }
}

async function run() {
  const { acquireServer } = createExpoServerManager(log);
  const server = await acquireServer("wordmark", process.env.APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
  }
  const appUrl = server.appUrl.endsWith("/")
    ? server.appUrl
    : server.appUrl + "/";

  const browser = await chromium.launch();
  try {
    for (const scheme of ["light", "dark"]) {
      await checkSignInScreen(browser, appUrl, scheme);
      await checkSetupScreen(browser, appUrl, scheme);
    }
    log("PASS: sign-in + setup wordmark stays visible in light and dark modes");
  } finally {
    await browser.close().catch(() => {});
  }
}

// Termination guarantee: runHarness exits the process as soon as run()
// settles and arms a watchdog, so a leaked handle can never stall the run.
runHarness(run, { log, onError: (e) => fail(e?.stack || e?.message || String(e)) });
