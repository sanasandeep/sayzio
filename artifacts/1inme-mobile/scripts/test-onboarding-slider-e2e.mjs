#!/usr/bin/env node
/**
 * End-to-end regression check for the onboarding intro slider
 * (app/onboarding.tsx), driving the REAL app in a headless browser.
 *
 * Why this exists: a layout bug once pushed the glass card (category chip +
 * title) off-screen, and nothing in CI rendered the slide to notice. This
 * harness renders the actual onboarding carousel and asserts the copy is
 * VISIBLE WITHIN THE FRAME, so a purely visual break surfaces.
 *
 * The intro arc grew from a handful of slides to the full story arc of ELEVEN
 * pages, so this spec now walks the ENTIRE arc and asserts every page renders:
 *
 *   welcome → creators → business → freelancer → networker → students →
 *   coaches → platform → grow → ai-dashboard → get-started
 *
 * (The ai-dashboard page is inserted by the app just before the get-started
 * CTA — see the `ctaIdx` / `pages` logic in app/onboarding.tsx — so it is not
 * part of the raw slide list but IS one of the eleven rendered pages.)
 *
 * The harness runs the arc TWICE, to prove the intro slides land correctly on
 * BOTH the fresh-install/not-yet-seeded path AND the admin-seeded path:
 *
 *   PASS 1 (bundled fallback): the slides endpoint returns an EMPTY payload,
 *   so the app renders its bundled FALLBACK_SLIDES — the real shipped intro
 *   arc. This is exactly what a fresh env (or one where the admin-seeding
 *   migration hasn't run yet) shows.
 *
 *   PASS 2 (admin-seeded API): the slides endpoint returns the admin-managed
 *   slides (the raw seeded slugs + categories), so the app swaps out the
 *   fallback. The same arc must render — same categories, same page count —
 *   so a slug/copy mismatch between the seeded content and what the app
 *   expects surfaces here, before it reaches production.
 *
 * Assertions (PASS 1 covers 1–7; PASS 2 re-walks the arc via the API):
 *   1. Each of the 11 pages, walked in order, shows its category chip VISIBLE
 *      WITHIN THE FRAME (not clipped, not off-screen) after paging to it.
 *   2. Slide 1 also has its title within frame (glass-card layout guard).
 *   3. The bottom pager renders exactly 11 dots.
 *   4. The bottom CTA reads "Continue" on the non-final pages and flips to
 *      "Get started" on the final (get-started) page.
 *   5. The footer "Website" link is present and opens https://sayzio.app in a
 *      new window (external), captured via a window.open stub.
 *   6. Tapping "Skip" routes to the auth screen ("Welcome back").
 *   7. With the admin-seeded API payload, the SAME 11-page arc renders (same
 *      category chips within frame, same dot count) and a sentinel body proves
 *      the app actually applied the API slides rather than the fallback.
 *   8. The top-bar wordmark stays the WHITE variant in BOTH light and dark
 *      color schemes (invisible-logo regression guard).
 *
 * Every other /api/** call is fulfilled with a benign {data:[]} so nothing
 * hits a real backend.
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
import { assertWordmarkWhiteVariant } from "./lib/wordmark-variant.mjs";

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

// The full shipped intro arc, in the exact order the app renders the pages.
// These mirror app/onboarding.tsx: FALLBACK_SLIDES (10 pages) with the
// ai-dashboard page inserted just before the get-started CTA (11 pages total).
// `category` is the visible chip copy asserted within the frame for each page.
const EXPECTED_PAGES = [
  { slug: "welcome", category: "Welcome to Sayzio" },
  { slug: "creators", category: "For creators" },
  { slug: "business", category: "For small businesses" },
  { slug: "freelancer", category: "For freelancers" },
  { slug: "networker", category: "For networkers" },
  { slug: "students", category: "For students & job seekers" },
  { slug: "coaches", category: "For coaches & educators" },
  { slug: "platform", category: "One platform, endless possibilities" },
  { slug: "grow", category: "Grow with confidence" },
  { slug: "ai-dashboard", category: "AI dashboard" },
  { slug: "get-started", category: "Ready when you are" },
];
const EXPECTED_PAGE_COUNT = EXPECTED_PAGES.length; // 11

const WELCOME_CATEGORY = EXPECTED_PAGES[0].category;
const WELCOME_TITLE = "One link for everything you do";
const WEBSITE_URL = "https://sayzio.app";

// A distinctive welcome-slide body that ONLY the mocked admin API payload
// carries (the real fallback welcome body is different). Asserting it renders
// in the second pass proves the app actually swapped in the admin-managed
// slides rather than silently falling back to the bundled ones again — without
// it, the second pass could pass even if the API-render path were broken.
const API_SENTINEL_BODY = "Seeded via the admin API.";

// The admin-managed slides feed used by the SECOND pass: the same intro arc
// the admin would seed (every raw slug + category the app expects), returned
// as the flat { items } payload OnboardingSlideController emits. The
// ai-dashboard page is NOT included here — the app inserts it client-side just
// before get-started, so both passes still render EXPECTED_PAGE_COUNT pages.
// Positive ids mirror real DB rows (the bundled fallback uses negative ids);
// categories mirror EXPECTED_PAGES so the walk asserts the SAME copy renders.
const MOCK_API_SLIDES = EXPECTED_PAGES.filter(
  (p) => p.slug !== "ai-dashboard",
).map((p, i) => ({
  id: 100 + i,
  slug: p.slug,
  category: p.category,
  title: p.slug === "welcome" ? WELCOME_TITLE : `${p.category} headline`,
  body: p.slug === "welcome" ? API_SENTINEL_BODY : `${p.category} details.`,
  image_url: null,
  image_urls: [],
  sort_order: (i + 1) * 10,
}));

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

// Wait until the chip with the given text is mounted AND horizontally inside
// the frame (x within the viewport width), i.e. the slide it belongs to has
// paged into view. React Native Web keeps the text in the DOM even at
// opacity 0, so we assert geometry rather than Playwright visibility.
async function waitForChipInFrame(page, chipText) {
  await page.waitForFunction(
    (text) => {
      const nodes = Array.from(document.querySelectorAll("div, span"));
      const chip = nodes.find((n) => n.textContent === text);
      if (!chip) return false;
      const r = chip.getBoundingClientRect();
      return r.width > 0 && r.x >= 0 && r.x + r.width <= window.innerWidth + 1;
    },
    chipText,
    { timeout: STEP_TIMEOUT_MS },
  );
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
}

// Count the pager dots. The dots row is a div whose children are all tiny
// bars (height 8, width 8/24). We locate the first div whose direct children
// are all small bars and there are enough of them to be the pager.
async function countPagerDots(page) {
  return page.evaluate(() => {
    const parents = Array.from(document.querySelectorAll("div"));
    for (const p of parents) {
      const kids = Array.from(p.children).filter((c) => c.tagName === "DIV");
      if (kids.length < 8) continue;
      const allDots = kids.every((k) => {
        const r = k.getBoundingClientRect();
        return r.height >= 4 && r.height <= 14 && r.width >= 4 && r.width <= 40;
      });
      if (allDots) return kids.length;
    }
    return -1;
  });
}

// Read the visible label of the bottom CTA button. It is the "Continue" /
// "Get started" text; return whichever is present in the DOM.
async function ctaLabelPresent(page, label) {
  return (await page.getByText(label, { exact: true }).count()) > 0;
}

// Build a browser context wired for a fresh onboarding launch: a signed-out,
// not-yet-onboarded install, with the slides endpoint mocked and every other
// backend call stubbed. A window.open stub records external opens so the
// footer "Website" link can be verified. `colorScheme` emulates the OS
// light/dark preference (drives react-native-web's useColorScheme via
// prefers-color-scheme) so we can prove the top-bar wordmark stays on the
// white variant in BOTH themes.
//
// `slidesPayload` controls what the slides endpoint returns:
//   • undefined / [] → EMPTY, so the app keeps its bundled FALLBACK_SLIDES
//     (the "admin hasn't seeded yet" / fresh-install / offline path).
//   • an array of slide objects → the admin-managed API path, so the app
//     replaces the fallback with these (the "admin HAS seeded" path).
async function prepareContext(browser, colorScheme, slidesPayload) {
  const context = await browser.newContext(
    colorScheme
      ? { viewport: VIEWPORT, colorScheme }
      : { viewport: VIEWPORT },
  );

  // A fresh install: onboarding NOT complete, signed out. Also stub
  // window.open so Linking.openURL (react-native-web opens external links via
  // window.open) is captured instead of spawning a real popup.
  await context.addInitScript(() => {
    try {
      window.localStorage.removeItem("1inme.onboarding.complete");
      window.localStorage.removeItem("1inme.auth.token");
      window.localStorage.removeItem("1inme.auth.user");
    } catch {}
    window.__openedUrls = [];
    const realOpen = window.open;
    window.open = function (url) {
      try {
        window.__openedUrls.push(String(url));
      } catch {}
      // Return a benign stub so callers that read the return value don't blow
      // up, and never actually navigate away from the onboarding screen.
      return { closed: false, close() {}, focus() {} };
    };
    window.__realWindowOpen = realOpen;
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

  // Slides feed. With no payload this is EMPTY → the app keeps its bundled
  // FALLBACK_SLIDES, i.e. the real shipped intro arc (admin-not-seeded path).
  // With a payload it returns those admin-managed slides instead. The real
  // endpoint returns a FLAT { items } payload (no {data} envelope) — see
  // OnboardingSlideController.
  await context.route("**/api/v1/onboarding/slides**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ items: slidesPayload ?? [] }),
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

    // ---- 1. Slide 1 (welcome): chip + title visible within the frame ----
    const welcomeChip = page
      .getByText(WELCOME_CATEGORY, { exact: true })
      .first();
    await assertWithinFrame(page, welcomeChip, "slide 1 (welcome) category chip");
    const welcomeTitle = page.getByText(WELCOME_TITLE, { exact: true }).first();
    await assertWithinFrame(page, welcomeTitle, "slide 1 (welcome) title");

    // The CTA starts as "Continue" (NOT the final "Get started").
    if (!(await ctaLabelPresent(page, "Continue"))) {
      fail('bottom CTA should read "Continue" on the first page');
    }
    if (await ctaLabelPresent(page, "Get started")) {
      fail('bottom CTA should NOT read "Get started" on the first page');
    }
    log('bottom CTA reads "Continue" on the first page');

    // ---- 2. Pager renders exactly 11 dots ------------------------------
    const dotCount = await countPagerDots(page);
    if (dotCount !== EXPECTED_PAGE_COUNT) {
      fail(
        `expected ${EXPECTED_PAGE_COUNT} pager dots, found ${dotCount} ` +
          `(the intro arc should be ${EXPECTED_PAGE_COUNT} pages)`,
      );
    }
    log(`pager renders exactly ${dotCount} dots`);

    // ---- 3. Footer "Website" link opens https://sayzio.app externally --
    const websiteLink = page.getByText("Website", { exact: true }).first();
    await assertWithinFrame(page, websiteLink, 'footer "Website" link');
    await websiteLink.click();
    await page.waitForFunction(
      (url) =>
        Array.isArray(window.__openedUrls) &&
        window.__openedUrls.some((u) => u === url || u === url + "/"),
      WEBSITE_URL,
      { timeout: STEP_TIMEOUT_MS },
    );
    const opened = await page.evaluate(() => window.__openedUrls);
    log(`"Website" link opened external URL(s): ${JSON.stringify(opened)}`);

    // ---- 4. Walk the ENTIRE 11-page arc, asserting each page renders ----
    // Page 0 (welcome) is already asserted above; page through the rest.
    for (let i = 1; i < EXPECTED_PAGE_COUNT; i++) {
      const { slug, category } = EXPECTED_PAGES[i];
      await swipeToNextSlide(page);
      await waitForChipInFrame(page, category);
      await assertWithinFrame(
        page,
        page.getByText(category, { exact: true }).first(),
        `page ${i + 1}/${EXPECTED_PAGE_COUNT} (${slug}) category chip`,
      );
    }
    log(`all ${EXPECTED_PAGE_COUNT} intro pages rendered in order`);

    // ---- 5. Final page: CTA flips to "Get started" ----------------------
    if (!(await ctaLabelPresent(page, "Get started"))) {
      fail('bottom CTA should read "Get started" on the final page');
    }
    if (await ctaLabelPresent(page, "Continue")) {
      fail('bottom CTA should NOT read "Continue" on the final page');
    }
    log('bottom CTA reads "Get started" on the final page');

    // ---- 6. Skip routes to the auth screen ------------------------------
    const skipBtn = page.getByText("Skip", { exact: true }).first();
    await assertWithinFrame(page, skipBtn, "Skip button");
    await skipBtn.click();
    await page
      .getByText("Welcome back", { exact: false })
      .first()
      .waitFor({ timeout: STEP_TIMEOUT_MS });
    log("Skip landed on the auth screen (Welcome back)");

    // ---- 7. Admin-seeded API renders the SAME intro arc ----------------
    // Second pass: instead of an EMPTY slides feed (which keeps the bundled
    // fallback), the endpoint now returns the admin-managed slides. This is
    // the "admin HAS seeded" path — the app should replace the fallback and
    // render the exact same arc (same categories, same page count), so a
    // slug/copy mismatch between the seeded content and what the app expects
    // surfaces here rather than only in production after the migration runs.
    log("second pass: admin-seeded API should render the same arc");
    const seededCtx = await prepareContext(browser, undefined, MOCK_API_SLIDES);
    const seededPage = await seededCtx.newPage();
    seededPage.setDefaultTimeout(STEP_TIMEOUT_MS);
    seededPage.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
    seededPage.on("pageerror", (e) => log("pageerror (seeded):", e.message));
    try {
      await seededPage.goto(appUrl, { waitUntil: "domcontentloaded" });

      // Slide 1 (welcome): same category chip + title as the fallback arc.
      await assertWithinFrame(
        seededPage,
        seededPage.getByText(WELCOME_CATEGORY, { exact: true }).first(),
        "seeded slide 1 (welcome) category chip",
      );
      await assertWithinFrame(
        seededPage,
        seededPage.getByText(WELCOME_TITLE, { exact: true }).first(),
        "seeded slide 1 (welcome) title",
      );

      // Prove the API path actually swapped in — the sentinel body only the
      // mocked payload carries must appear (so this pass can't silently be
      // re-testing the bundled fallback if setSlides(items) ever regresses).
      await seededPage
        .getByText(API_SENTINEL_BODY, { exact: false })
        .first()
        .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
      log("seeded welcome body sentinel rendered → admin API slides applied");

      // Same page count as the fallback arc (ai-dashboard still inserted).
      const seededDots = await countPagerDots(seededPage);
      if (seededDots !== EXPECTED_PAGE_COUNT) {
        fail(
          `seeded arc: expected ${EXPECTED_PAGE_COUNT} pager dots, found ` +
            `${seededDots}`,
        );
      }
      log(`seeded arc renders exactly ${seededDots} dots`);

      // Walk the ENTIRE arc, asserting each page's category chip renders the
      // SAME copy within the frame — page 0 (welcome) already asserted above.
      for (let i = 1; i < EXPECTED_PAGE_COUNT; i++) {
        const { slug, category } = EXPECTED_PAGES[i];
        await swipeToNextSlide(seededPage);
        await waitForChipInFrame(seededPage, category);
        await assertWithinFrame(
          seededPage,
          seededPage.getByText(category, { exact: true }).first(),
          `seeded page ${i + 1}/${EXPECTED_PAGE_COUNT} (${slug}) category chip`,
        );
      }
      log(`all ${EXPECTED_PAGE_COUNT} seeded intro pages rendered the same copy`);
    } finally {
      await seededCtx.close().catch(() => {});
    }

    // ---- 8. Wordmark stays the WHITE variant in BOTH color schemes ------
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
        // Wait for the carousel (welcome chip) so the top bar is mounted.
        await themePage
          .getByText(WELCOME_CATEGORY, { exact: true })
          .first()
          .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
        await assertWordmarkWhiteVariant(themePage, {
          label: `top-bar wordmark (${scheme} scheme)`,
          fail,
          log,
        });
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
