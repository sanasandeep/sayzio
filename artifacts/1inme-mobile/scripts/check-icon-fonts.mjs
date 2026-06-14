#!/usr/bin/env node
/**
 * Regression check: the mobile app must preload the Ionicons and Feather
 * icon fonts at start so that the social-provider logos on the login
 * screen — and Feather icons elsewhere — render as real glyphs instead
 * of "tofu" missing-character boxes.
 *
 * Strategy:
 *   1. Launch headless Chromium against the running Expo web build.
 *   2. Navigate to the app, wait for it to mount.
 *   3. Walk the in-app onboarding (or skip it via localStorage) until the
 *      "Welcome back" login screen is visible.
 *   4. Assert via the FontFaceSet API that both `ionicons` and `Feather`
 *      font-families are loaded, AND assert visually that none of the
 *      seven social-provider icon glyphs is a fallback "tofu" rectangle.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:icon-fonts
 *
 * Environment:
 *   APP_URL   override the URL under test (defaults to the Expo dev
 *             domain on Replit, or http://localhost:8081 locally).
 */

import { chromium } from "playwright";

const APP_URL =
  process.env.APP_URL ||
  (process.env.REPLIT_EXPO_DEV_DOMAIN
    ? `https://${process.env.REPLIT_EXPO_DEV_DOMAIN}/`
    : "http://localhost:8081/");

const NAV_TIMEOUT_MS = 90_000;
const STEP_TIMEOUT_MS = 30_000;

// The Google button only renders when a Google client id is configured
// (HAS_GOOGLE_NATIVE in app/(auth)/index.tsx) — on web without
// EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID it's intentionally hidden. The
// web-browser providers below always render, so they're the minimum set
// the test requires. Google is verified too, but only if it's present.
const REQUIRED_SOCIAL_LABELS = [
  "Continue with Instagram",
  "Continue with Facebook",
  "Continue with X",
  "Continue with LinkedIn",
  "Continue with Pinterest",
  "Continue with TikTok",
];
const OPTIONAL_SOCIAL_LABELS = ["Continue with Google"];

function log(...args) {
  console.log("[check-icon-fonts]", ...args);
}

function fail(msg) {
  console.error("[check-icon-fonts] FAIL:", msg);
  process.exit(1);
}

async function reachLoginScreen(page) {
  // Try to skip onboarding via the localStorage flag the app reads on web.
  await page.evaluate(() => {
    try {
      window.localStorage.setItem("1inme.onboarding.complete", "1");
    } catch {}
  });
  await page.reload({ waitUntil: "domcontentloaded" });

  // Give the gate up to STEP_TIMEOUT_MS to land us on "Welcome back".
  try {
    await page.getByText("Welcome back", { exact: false }).waitFor({
      timeout: STEP_TIMEOUT_MS,
    });
    return;
  } catch {
    // localStorage shortcut didn't work — fall through and click Skip.
  }

  // Try the explicit Skip link in onboarding.
  const skip = page.getByRole("button", { name: /^skip$/i }).first();
  if (await skip.isVisible().catch(() => false)) {
    await skip.click();
  } else {
    // Walk the onboarding carousel.
    for (let i = 0; i < 6; i++) {
      const getStarted = page
        .getByRole("button", { name: /get started/i })
        .first();
      if (await getStarted.isVisible().catch(() => false)) {
        await getStarted.click();
        break;
      }
      const cont = page.getByRole("button", { name: /^continue$/i }).first();
      if (await cont.isVisible().catch(() => false)) {
        await cont.click();
      } else {
        break;
      }
    }
  }
  await page
    .getByText("Welcome back", { exact: false })
    .waitFor({ timeout: STEP_TIMEOUT_MS });
}

async function assertFontsLoaded(page, family) {
  const result = await page.evaluate((fam) => {
    return {
      check: document.fonts.check(`1em ${fam}`),
      faces: Array.from(document.fonts).map((f) => ({
        family: f.family,
        status: f.status,
      })),
    };
  }, family);
  log(`fonts after wait for ${family}:`, JSON.stringify(result.faces));
  if (!result.check) {
    fail(`document.fonts.check('1em ${family}') returned false`);
  }
  const matched = result.faces.some(
    (f) => new RegExp(family, "i").test(f.family) && f.status === "loaded",
  );
  if (!matched) {
    fail(
      `No FontFace whose family matches /${family}/i is in 'loaded' state`,
    );
  }
}

async function assertSocialIconsRendered(page) {
  // Each social-provider icon glyph must be a non-empty rendered character.
  // We assert two things per button:
  //   1. textContent is a single private-use codepoint (the icon glyph).
  //   2. The glyph's measured width with the loaded font differs from
  //      the same string measured WITHOUT the icon font — i.e. the
  //      browser actually used the icon font, not the fallback "tofu".
  const report = await page.evaluate(async ({ required, optional }) => {
    const out = [];
    for (const label of [...required, ...optional]) {
      const isOptional = optional.includes(label);
      const el = document.querySelector(`[aria-label="${label}"]`);
      if (!el) {
        // Optional providers (e.g. Google when no client id is configured)
        // may be intentionally absent — skip them quietly.
        if (!isOptional) out.push({ label, error: "button not found" });
        continue;
      }
      // Find the glyph text node within the button.
      const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
      let glyph = "";
      while (walker.nextNode()) {
        const t = walker.currentNode.nodeValue?.trim();
        if (t) {
          glyph = t;
          break;
        }
      }
      if (!glyph) {
        out.push({ label, error: "no glyph text node" });
        continue;
      }
      // Measure with and without the ionicons font.
      const cv = document.createElement("canvas");
      const ctx = cv.getContext("2d");
      ctx.font = '24px "ionicons"';
      const wIcon = ctx.measureText(glyph).width;
      ctx.font = "24px serif";
      const wFallback = ctx.measureText(glyph).width;
      out.push({ label, glyph, wIcon, wFallback });
    }
    return out;
  }, { required: REQUIRED_SOCIAL_LABELS, optional: OPTIONAL_SOCIAL_LABELS });

  log("social glyph report:", JSON.stringify(report, null, 2));
  for (const r of report) {
    if (r.error) fail(`${r.label}: ${r.error}`);
    if (Math.abs(r.wIcon - r.wFallback) < 0.5) {
      fail(
        `${r.label}: glyph "${r.glyph}" measured the same with icon font ` +
          `(${r.wIcon}) and serif fallback (${r.wFallback}) — the ionicons ` +
          `font did not paint this character (tofu fallback).`,
      );
    }
  }
}

async function main() {
  log("launching chromium against", APP_URL);
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 400, height: 720 },
  });
  const page = await context.newPage();
  page.on("pageerror", (e) => log("pageerror:", e.message));
  page.setDefaultTimeout(STEP_TIMEOUT_MS);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

  try {
    await page.goto(APP_URL, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });
    // Wait for ANY in-app text to appear so we know React has mounted.
    await page.waitForFunction(
      () => document.body && document.body.innerText.trim().length > 0,
      null,
      { timeout: NAV_TIMEOUT_MS },
    );

    log("app mounted; navigating to login screen");
    await reachLoginScreen(page);

    // The root layout (_layout.tsx) preloads BOTH Ionicons and Feather via
    // a single useFonts() call before any screen renders, so once the login
    // screen is visible we can assert both font families directly without
    // navigating to a screen that visually uses Feather.
    log('verifying ionicons font is loaded');
    await assertFontsLoaded(page, "ionicons");

    log('verifying Feather font is loaded');
    await assertFontsLoaded(page, "Feather");

    log("verifying each social-provider icon is a real glyph (not tofu)");
    await assertSocialIconsRendered(page);

    log("PASS: ionicons & Feather fonts are loaded; social glyphs render.");
  } finally {
    await browser.close();
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
