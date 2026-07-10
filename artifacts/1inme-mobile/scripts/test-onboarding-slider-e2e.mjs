#!/usr/bin/env node
/**
 * End-to-end regression check for the onboarding intro slider
 * (app/onboarding.tsx), driving the REAL app in a headless browser.
 *
 * Why this exists: a layout bug once pushed the glass card (category chip +
 * title) off-screen, and nothing in CI rendered the slide to notice. This
 * harness renders the actual onboarding carousel and asserts the copy is
 * VISIBLE WITHIN THE FRAME, so a purely visual break surfaces:
 *
 *   1. Slide 1's category chip AND title are visible and their bounding
 *      boxes sit fully inside the viewport (not clipped, not off-screen).
 *   2. Swiping to the next slide (paging the horizontal FlatList by one
 *      viewport width) shows a DIFFERENT category chip, itself within frame.
 *   3. Tapping "Skip" routes to the auth screen ("Welcome back").
 *
 * The slides endpoint is intercepted with two deterministic slides so the
 * assertions never depend on admin-managed content; every other /api/** call
 * is fulfilled with a benign {data:[]} so nothing hits a real backend.
 *
 * Like the other mobile e2e harnesses, it boots its OWN throwaway Expo web
 * dev server (shared expo-web-server.mjs manager) unless APP_URL points at an
 * already-running one, and SKIPS (exit 0) when a server can't come up so it
 * never fails CI just because Metro couldn't boot.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:onboarding-e2e
 *
 * Environment:
 *   APP_URL   reuse an already-running Expo web server instead of booting
 *             a throwaway one (handy for local debugging).
 */

import { chromium } from "playwright";

import {
  NAV_TIMEOUT_MS,
  STEP_TIMEOUT_MS,
} from "./check-icon-fonts.mjs";
import { createExpoServerManager } from "./expo-web-server.mjs";

function log(...args) {
  console.log("[onboarding-slider-e2e]", ...args);
}

function fail(msg) {
  console.error("[onboarding-slider-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[onboarding-slider-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };

// Deterministic slides served to the app in place of the admin-managed set.
// image_url stays null so resolveImages() uses the bundled fallback photos
// (slugs must match FALLBACK_IMAGES keys in app/onboarding.tsx).
const SLIDE_ONE_CATEGORY = "E2E category one";
const SLIDE_TWO_CATEGORY = "E2E category two";
const SLIDE_ONE_TITLE = "E2E first slide title";
const MOCK_SLIDES = [
  {
    id: 9001,
    slug: "creators",
    category: SLIDE_ONE_CATEGORY,
    title: SLIDE_ONE_TITLE,
    body: "First e2e slide body copy.",
    image_url: null,
    image_urls: [],
    sort_order: 10,
  },
  {
    id: 9002,
    slug: "business",
    category: SLIDE_TWO_CATEGORY,
    title: "E2E second slide title",
    body: "Second e2e slide body copy.",
    image_url: null,
    image_urls: [],
    sort_order: 20,
  },
];

// Assert the element is visible AND its bounding box sits fully inside the
// viewport frame — this is the check that would have caught the glass card
// sliding off the bottom of the screen.
async function assertWithinFrame(page, locator, label) {
  await locator.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  const box = await locator.boundingBox();
  if (!box) fail(`${label} has no bounding box (not rendered?)`);
  const outOfFrame =
    box.x < 0 ||
    box.y < 0 ||
    box.x + box.width > VIEWPORT.width + 1 ||
    box.y + box.height > VIEWPORT.height + 1;
  if (outOfFrame) {
    fail(
      `${label} is rendered OUTSIDE the visible frame: ` +
        `box=${JSON.stringify(box)} viewport=${JSON.stringify(VIEWPORT)}`,
    );
  }
  log(`${label} is visible within the frame (${JSON.stringify(box)})`);
}

// Page the horizontal FlatList by one viewport width, like a swipe. React
// Native Web renders the carousel as a horizontally scrollable element, so we
// walk up from the slide copy to the scroll container and advance scrollLeft;
// the scroll event drives the same onScroll/index logic as a finger swipe.
async function swipeToNextSlide(page) {
  const advanced = await page.evaluate(() => {
    // Only a genuinely scrollable element counts: overflowing children exist
    // inside every slide wrapper too (overflow: visible), and setting
    // scrollLeft on those silently does nothing. The FlatList pager is the
    // element with horizontal overflow AND scrollable/snap overflow-x.
    const scroller = Array.from(document.querySelectorAll("div")).find((n) => {
      if (n.scrollWidth <= n.clientWidth + 10) return false;
      const cs = getComputedStyle(n);
      return (
        cs.overflowX === "auto" ||
        cs.overflowX === "scroll" ||
        (cs.scrollSnapType && cs.scrollSnapType.includes("x"))
      );
    });
    if (!scroller) return false;
    scroller.scrollLeft = scroller.scrollLeft + scroller.clientWidth;
    return true;
  });
  if (!advanced) fail("could not find the horizontal slider to swipe");
  log("swiped the carousel forward by one slide width");
}

// Build a browser context wired for a fresh onboarding launch: a signed-out,
// not-yet-onboarded install, with the slides endpoint mocked and every other
// backend call stubbed. `colorScheme` emulates the OS light/dark preference
// (drives react-native-web's useColorScheme via prefers-color-scheme) so we
// can prove the top-bar wordmark stays on the white variant in BOTH themes.
async function prepareContext(browser, colorScheme) {
  const context = await browser.newContext(
    colorScheme
      ? { viewport: VIEWPORT, colorScheme }
      : { viewport: VIEWPORT },
  );

  // A fresh install: onboarding NOT complete, signed out.
  await context.addInitScript(() => {
    try {
      window.localStorage.removeItem("1inme.onboarding.complete");
      window.localStorage.removeItem("1inme.auth.token");
      window.localStorage.removeItem("1inme.auth.user");
    } catch {}
  });

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

  // Deterministic slides for the carousel.
  await context.route("**/api/v1/onboarding/slides**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      // The real endpoint returns a FLAT { items } payload (no {data}
      // envelope) — see OnboardingSlideController::index.
      body: JSON.stringify({ items: MOCK_SLIDES }),
    }),
  );

  // Catch-all so nothing else reaches a real backend. Registered last, so
  // Playwright consults it first; it defers the slides path to the handler
  // above via fallback().
  await context.route("**/api/**", (route) => {
    const path = new URL(route.request().url()).pathname;
    if (/\/api\/v1\/onboarding\/slides$/.test(path)) return route.fallback();
    return route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ data: [] }),
    });
  });

  return context;
}

// Prove the onboarding top-bar wordmark resolves to the WHITE (dark-background)
// PNG — `wordmark-white-text.png` — and never the dark-text variant. This guards
// the `forceVariant="dark-bg"` prop on <BrandWordmark>: if it's removed or the
// forceVariant branch is short-circuited, a light-mode device would render the
// dark-text logo, which is invisible on the dark top bar. On Expo web the
// bundled require() resolves to a URL that keeps the original filename, so we
// scan every <img src>/currentSrc and CSS background-image for the two names.
async function assertWordmarkWhiteVariant(page, label) {
  const hits = await page.evaluate(() => {
    const found = { white: false, darkText: false, srcs: [] };
    const scan = (val) => {
      if (!val) return;
      let d = val;
      try {
        d = decodeURIComponent(val);
      } catch {}
      if (d.includes("wordmark-white-text")) {
        found.white = true;
        found.srcs.push(d);
      }
      if (d.includes("wordmark-dark-text")) {
        found.darkText = true;
        found.srcs.push(d);
      }
    };
    document.querySelectorAll("img").forEach((img) => {
      scan(img.getAttribute("src"));
      scan(img.currentSrc);
    });
    document.querySelectorAll("*").forEach((el) => {
      const bg = window.getComputedStyle(el).backgroundImage;
      if (bg && bg !== "none") scan(bg);
    });
    return found;
  });

  if (!hits.white) {
    fail(
      `${label}: white/dark-bg wordmark (wordmark-white-text.png) not found ` +
        `in the DOM; srcs=${JSON.stringify(hits.srcs)}`,
    );
  }
  if (hits.darkText) {
    fail(
      `${label}: dark-text wordmark (wordmark-dark-text.png) rendered — it is ` +
        `invisible on the dark top bar; srcs=${JSON.stringify(hits.srcs)}`,
    );
  }
  log(`${label}: brand wordmark resolved to the white/dark-bg variant`);
}

async function run() {
  const { acquireServer } = createExpoServerManager(log);
  const server = await acquireServer("onboarding", process.env.APP_URL);
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
    // Load the (already-warmed) root; with the onboarding flag cleared the
    // launch gate redirects to /onboarding client-side — the exact path a
    // fresh install takes. (Requesting /onboarding directly would make Metro
    // compile that route from scratch and can blow the nav timeout.)
    log(`navigating to ${appUrl} (launch gate → /onboarding)`);
    await page.goto(appUrl, { waitUntil: "domcontentloaded" });

    // ---- 1. Slide 1: chip + title visible within the frame -------------
    const chipOne = page.getByText(SLIDE_ONE_CATEGORY, { exact: true }).first();
    await assertWithinFrame(page, chipOne, "slide 1 category chip");
    const titleOne = page.getByText(SLIDE_ONE_TITLE, { exact: true }).first();
    await assertWithinFrame(page, titleOne, "slide 1 title");

    // The second slide's chip must NOT be inside the frame yet (it lives one
    // viewport width to the right, or isn't mounted at all).
    const chipTwo = page.getByText(SLIDE_TWO_CATEGORY, { exact: true }).first();
    if ((await chipTwo.count()) > 0) {
      const box = await chipTwo.boundingBox();
      if (box && box.x < VIEWPORT.width && box.x + box.width > 0) {
        fail(
          "slide 2's category chip is already inside the frame on slide 1: " +
            JSON.stringify(box),
        );
      }
    }
    log("slide 2's chip is correctly outside the frame before swiping");

    // ---- 2. Swipe: next slide shows a DIFFERENT category chip ----------
    await swipeToNextSlide(page);
    await page.waitForFunction(
      (chipText) => {
        const nodes = Array.from(document.querySelectorAll("div, span"));
        const chip = nodes.find((n) => n.textContent === chipText);
        if (!chip) return false;
        const r = chip.getBoundingClientRect();
        return r.width > 0 && r.x >= 0 && r.x + r.width <= window.innerWidth + 1;
      },
      SLIDE_TWO_CATEGORY,
      { timeout: STEP_TIMEOUT_MS },
    );
    await assertWithinFrame(
      page,
      page.getByText(SLIDE_TWO_CATEGORY, { exact: true }).first(),
      "slide 2 category chip (after swipe)",
    );

    // And slide 1's chip must have left the frame.
    const boxOneAfter = await chipOne.boundingBox().catch(() => null);
    if (
      boxOneAfter &&
      boxOneAfter.x + boxOneAfter.width > 0 &&
      boxOneAfter.x < VIEWPORT.width
    ) {
      fail(
        "slide 1's chip is still inside the frame after swiping: " +
          JSON.stringify(boxOneAfter),
      );
    }
    log("swipe advanced the slider: chip changed from slide 1 to slide 2");

    // ---- 3. Skip routes to the auth screen ------------------------------
    const skipBtn = page.getByText("Skip", { exact: true }).first();
    await assertWithinFrame(page, skipBtn, "Skip button");
    await skipBtn.click();
    await page
      .getByText("Welcome back", { exact: false })
      .first()
      .waitFor({ timeout: STEP_TIMEOUT_MS });
    log("Skip landed on the auth screen (Welcome back)");

    // ---- 4. Wordmark stays the WHITE variant in BOTH color schemes -------
    // Regression guard for the invisible-logo bug: the splash top-bar
    // <BrandWordmark forceVariant="dark-bg" /> must resolve to the white PNG
    // even when the device is in LIGHT mode (where useColorScheme() would
    // otherwise pick the dark-text logo, invisible on the dark top bar).
    for (const scheme of ["light", "dark"]) {
      const themeCtx = await prepareContext(browser, scheme);
      const themePage = await themeCtx.newPage();
      themePage.setDefaultTimeout(STEP_TIMEOUT_MS);
      themePage.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
      themePage.on("pageerror", (e) => log(`pageerror (${scheme}):`, e.message));
      try {
        log(`navigating to ${appUrl} with ${scheme} color scheme`);
        await themePage.goto(appUrl, { waitUntil: "domcontentloaded" });
        // Wait for the carousel (slide 1 chip) so the top bar is mounted.
        await themePage
          .getByText(SLIDE_ONE_CATEGORY, { exact: true })
          .first()
          .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
        await assertWordmarkWhiteVariant(
          themePage,
          `top-bar wordmark (${scheme} scheme)`,
        );
      } finally {
        await themeCtx.close().catch(() => {});
      }
    }

    log("PASS: onboarding slider e2e checks all passed");
  } finally {
    await browser.close().catch(() => {});
  }
}

run().catch((e) => {
  fail(e?.stack || e?.message || String(e));
});
