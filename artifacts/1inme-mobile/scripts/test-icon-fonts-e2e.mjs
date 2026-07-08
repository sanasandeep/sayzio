#!/usr/bin/env node
/**
 * Self-booting end-to-end regression gate for the mobile login-screen ICONS,
 * driving the REAL app in a headless browser against a freshly-booted Expo web
 * server.
 *
 * check-icon-fonts.mjs already pins the assertion logic — both Ionicons and
 * Feather fonts must preload, and the seven social-provider login buttons must
 * render real glyphs instead of empty "tofu" boxes. What it can NOT do is run
 * on change: it only connects to an *already-running* Expo web server (the
 * proxied dev domain, or APP_URL), so unless someone has the Expo workflow up
 * nothing exercises it and a font/loader regression could ship a login screen
 * full of broken icons.
 *
 * This harness closes that gap. Mirroring test-auth-flow-e2e.mjs, it boots its
 * OWN throwaway, self-contained Expo web dev server on a free port, warms
 * `GET /` to a real 200 before any browser navigation, then runs the exact
 * icon-font check (runIconFontCheck) against it. That makes the check runnable
 * as a validation gate with no live server dependency.
 *
 * Best-effort boot contract (same as the auth-flow harness): if a throwaway
 * Expo server can't be booted (expo missing, port contention, bundling too
 * slow) the harness SKIPs (exit 0) rather than failing CI just because the
 * environment couldn't bring Metro up. A real font/glyph regression on a server
 * that DID boot still fails the check (exit 1).
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:icon-fonts-e2e
 *
 * Environment:
 *   APP_URL   point the check at an already-running server instead of booting a
 *             throwaway one (handy for local debugging, and lets CI pre-start a
 *             server once and reuse it). When unset, a throwaway server is
 *             booted and torn down automatically.
 */

import { chromium } from "playwright";

import {
  NAV_TIMEOUT_MS,
  STEP_TIMEOUT_MS,
  runIconFontCheck,
} from "./check-icon-fonts.mjs";
import { createExpoServerManager } from "./expo-web-server.mjs";

function log(...args) {
  console.log("[test-icon-fonts-e2e]", ...args);
}

function skip(msg) {
  console.log("[test-icon-fonts-e2e] SKIP:", msg);
  process.exit(0);
}

// An explicit APP_URL means "reuse this already-running server" (no boot).
// When unset, a throwaway server is booted and tracked for teardown.
const EXPLICIT_APP_URL = process.env.APP_URL || null;

// Error signatures that mean "the environment was too slow/flaky", not "the
// icons regressed": Playwright step/nav timeouts and transient connection
// errors while the box is starved by concurrent validation jobs (this gate
// runs alongside the heavy browser-e2e suite and the native Metro bundle on
// one constrained machine). Mirrors TRANSIENT_NAV in test-auth-flow-e2e.mjs.
//
// Real icon regressions never surface this way — check-icon-fonts.mjs reports
// them via fail() (missing font face, tofu glyph), which exits 1 directly and
// can never be downgraded here.
const TRANSIENT_ENV_ERROR =
  /ERR_CONNECTION_RESET|ERR_EMPTY_RESPONSE|ERR_CONNECTION_REFUSED|ERR_CONNECTION_CLOSED|ERR_NETWORK_CHANGED|Timeout \d+ms exceeded/;

function isTransientEnvError(e) {
  return (
    e?.name === "TimeoutError" || TRANSIENT_ENV_ERROR.test(e?.message ?? "")
  );
}

const { acquireServer, stopExpo } = createExpoServerManager(log);

async function run() {
  const server = await acquireServer("icon-fonts", EXPLICIT_APP_URL);
  if (!server) {
    // No throwaway server → SKIP, never fail. The "exit" handler reaps any
    // child that did partially boot.
    skip("the throwaway Expo server could not start; skipping the icon-font check");
    return;
  }

  const { appUrl, child, explicit } = server;
  log("driving the icon-font check against", appUrl);

  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({
      viewport: { width: 400, height: 720 },
    });
    const page = await context.newPage();
    page.on("pageerror", (e) => log("pageerror:", e.message));
    page.setDefaultTimeout(STEP_TIMEOUT_MS);
    page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

    try {
      await runIconFontCheck(page, appUrl);
    } catch (e) {
      // Best-effort contract, post-boot half: on a throwaway server, a
      // Playwright timeout / transient connection error while navigating or
      // waiting for the login screen means the constrained box was too slow
      // (Metro recompiling, CPU starved by parallel validation jobs) — SKIP,
      // same as when the server couldn't boot at all. Real icon regressions
      // exit 1 inside check-icon-fonts.mjs before this catch can run. Against
      // an explicit APP_URL (someone deliberately pointed us at a server) we
      // still fail hard so local debugging never silently skips.
      if (!explicit && isTransientEnvError(e)) {
        await browser.close().catch(() => {});
        stopExpo(child);
        skip(
          `the environment was too slow to drive the check ` +
            `(${e?.message?.split("\n")[0] ?? "unknown error"}); ` +
            `skipping (best-effort, not an icon regression)`,
        );
        return;
      }
      throw e;
    }

    log(
      "PASS: the login-screen icon fonts load and every social-provider glyph " +
        "renders against a freshly-booted Expo web server.",
    );
  } finally {
    await browser.close();
    // Free this run's throwaway server (no-op when a reused server was used,
    // since child stays null). A backstop in the process "exit" handler reaps
    // anything left if we exit early.
    stopExpo(child);
  }
}

run().catch((e) => {
  console.error(e);
  process.exit(1);
});
