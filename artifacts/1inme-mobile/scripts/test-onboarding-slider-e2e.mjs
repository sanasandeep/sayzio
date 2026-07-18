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
 * This spec walks the ENTIRE 5-slide AI-business-companion arc:
 *
 *   welcome → every-business → everything-you-need → work-smarter → start-free
 *
 * The harness runs the arc across FOUR feed shapes, to prove the intro slides
 * land correctly no matter what the admin has (or hasn't) seeded:
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
 *   PASS 3 (PARTIAL feed): the admin seeded only SOME slugs (a subset of the
 *   arc). The app must render exactly those slides — a shorter but still
 *   coherent arc — with the final page's CTA flipping to "Get started" and a
 *   working Skip. Nothing is injected client-side; the app renders precisely
 *   what the feed carries.
 *
 *   PASS 4 (SHUFFLED + UNRECOGNIZED feed): the admin seeded slides OUT OF
 *   nominal order (a framing slide ahead of another) AND a brand-new slug the
 *   app has no bundled theme/image for. The app must render them in the ARRAY
 *   order it received (it trusts the backend's sort and never re-sorts or
 *   drops rows), fall back gracefully for the unknown slug, and never crash or
 *   strand the user.
 *
 * Assertions (PASS 1 covers 1–7; PASS 2 re-walks the arc via the API; PASS 3
 * and PASS 4 walk their own shorter/misordered arcs and assert the same
 * coherence guarantees — dot count, in-order chips, CTA flip, and — for
 * PASS 3 — a working Skip):
 *   1. Each of the 5 pages, walked in order, shows its category chip VISIBLE
 *      WITHIN THE FRAME (not clipped, not off-screen) after paging to it.
 *   2. Slide 1 also has its title within frame (glass-card layout guard).
 *   3. The bottom pager renders exactly 5 dots.
 *   4. The bottom CTA reads "Continue" on the non-final pages and flips to
 *      "Get started" on the final (start-free) page.
 *   5. The footer "Website" link is present and opens https://sayzio.app in a
 *      new window (external), captured via a window.open stub.
 *   6. Tapping "Skip" routes to the auth screen ("Welcome back").
 *   7. With the admin-seeded API payload, the SAME 5-page arc renders (same
 *      category chips within frame, same dot count) and a sentinel body proves
 *      the app actually applied the API slides rather than the fallback.
 *   8. The top-bar wordmark stays the WHITE variant in BOTH light and dark
 *      color schemes (invisible-logo regression guard).
 *   9. Slide 3 ("everything-you-need") renders the 3×3 business-tools icon grid
 *      (all 9 tool labels present, one within frame) — NOT a blank body.
 *  10. CACHE-BUST guard: an install that ALREADY has a stale v1 slides-cache
 *      entry (the old, longer intro arc an existing user carried across the
 *      key bump) still renders the NEW 5-slide arc — 5 dots, no stale category
 *      copy — proving the v1 → v2 cache-key bump orphans the old cache instead
 *      of resurrecting it. On that same install, tapping the final "Get started"
 *      CTA routes a signed-out user to the auth screen. This is the closest an
 *      automated harness can get to the real-device "upgrade over a v1 cache"
 *      check, since AsyncStorage on web is backed by localStorage.
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
import { createExpoServerManager, runHarness } from "./expo-web-server.mjs";
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

// The 5-slide AI-business-companion arc, in the exact order the app renders.
// Mirrors FALLBACK_SLIDES in app/onboarding.tsx.
// `category` is the visible chip copy asserted within the frame for each page.
const EXPECTED_PAGES = [
  { slug: "welcome", category: "Welcome" },
  { slug: "every-business", category: "Built for Every Business" },
  { slug: "everything-you-need", category: "Everything You Need" },
  { slug: "work-smarter", category: "Work Smarter" },
  { slug: "start-free", category: "Start Free" },
];
const EXPECTED_PAGE_COUNT = EXPECTED_PAGES.length; // 5

const WELCOME_CATEGORY = EXPECTED_PAGES[0].category;
const WELCOME_TITLE = "Meet Sayzio";
const WEBSITE_URL = "https://sayzio.app";

// The 9 business-tool labels the "everything-you-need" slide renders as a 3×3
// icon grid (mirrors TOOLS_GRID in app/onboarding.tsx). Slide 3 has a null
// body on purpose — this grid IS its body — so asserting these prove the grid
// renders instead of leaving a blank card.
const TOOLS_GRID_LABELS = [
  "AI Chatbots",
  "CRM",
  "WhatsApp",
  "Forms",
  "Booking",
  "Store",
  "QR Codes",
  "Campaigns",
  "Analytics",
];

// The AsyncStorage key an EXISTING user's device carried BEFORE the v1 → v2
// cache-key bump. On web, AsyncStorage is backed by localStorage under the raw
// key, so seeding this simulates upgrading over a stale cache. The app now
// reads the v2 key only, so this entry must be orphaned (never rendered).
const LEGACY_V1_CACHE_KEY = "1inme.onboarding.slides.cache.v1";

// A stale, longer intro arc (the shape an old cache would hold) with a category
// that exists NOWHERE in the new arc — so if the app ever read v1 again, this
// copy (and a 10-dot pager) would surface and fail the guard below.
const LEGACY_STALE_CATEGORY = "Legacy Intro Slide";
const LEGACY_V1_SLIDES = Array.from({ length: 10 }, (_, i) => ({
  id: 900 + i,
  slug: `legacy-${i + 1}`,
  category: `${LEGACY_STALE_CATEGORY} ${i + 1}`,
  title: `Old headline ${i + 1}`,
  body: `Stale cached body ${i + 1}.`,
  image_url: null,
  image_urls: [],
  sort_order: (i + 1) * 10,
}));

// A distinctive welcome-slide body that ONLY the mocked admin API payload
// carries (the real fallback welcome body is different). Asserting it renders
// in the second pass proves the app actually swapped in the admin-managed
// slides rather than silently falling back to the bundled ones again — without
// it, the second pass could pass even if the API-render path were broken.
const API_SENTINEL_BODY = "Seeded via the admin API.";

// The admin-managed slides feed used by the SECOND pass: the same 5-slide arc
// the admin would seed, returned as the flat { items } payload
// OnboardingSlideController emits.
// Positive ids mirror real DB rows (the bundled fallback uses negative ids);
// categories mirror EXPECTED_PAGES so the walk asserts the SAME copy renders.
const MOCK_API_SLIDES = EXPECTED_PAGES.map((p, i) => ({
  id: 100 + i,
  slug: p.slug,
  category: p.category,
  title: p.slug === "welcome" ? WELCOME_TITLE : `${p.category} headline`,
  body: p.slug === "welcome" ? API_SENTINEL_BODY : `${p.category} details.`,
  image_url: null,
  image_urls: [],
  sort_order: (i + 1) * 10,
}));

// ─── Partial / misordered admin feeds (the "middle ground") ──────────────────
// The two passes above cover the clean extremes: an EMPTY feed (bundled
// fallback) and a FULL admin-seeded feed. The passes below cover the untested
// middle — an admin who seeds only SOME slugs, seeds them OUT OF sort_order, or
// seeds a slug the app doesn't recognize. In every case the arc must stay
// coherent: the app renders the slides in the order the API returns them (it
// trusts the backend's `->ordered()` sort and never re-sorts), renders exactly
// what the feed carries (nothing is injected client-side), and keeps a working
// Continue→Get started CTA and Skip.

// Helper: build a slide row, marking the welcome slide's body with a per-pass
// sentinel so we can prove THIS payload (not the bundled fallback) rendered.
function slideRow(id, slug, category, sentinel) {
  return {
    id,
    slug,
    category,
    title: slug === "welcome" ? WELCOME_TITLE : `${category} headline`,
    body: slug === "welcome" ? sentinel : `${category} details.`,
    image_url: null,
    image_urls: [],
    // sort_order is intentionally NOT monotonic in these payloads — the app
    // must honour ARRAY order (what the backend already sorted), not this field.
    sort_order: 0,
  };
}

// PASS 3 — PARTIAL feed: the admin seeded only a subset of the arc (welcome,
// every-business, work-smarter, start-free), in a sensible order with
// start-free last. The app should render exactly those 4 slides — no more, no
// fewer — with start-free last and its CTA flipped to "Get started".
const PARTIAL_SENTINEL_BODY = "Seeded as a partial admin feed.";
const MOCK_PARTIAL_SLIDES = [
  slideRow(200, "welcome", "Welcome to Sayzio", PARTIAL_SENTINEL_BODY),
  slideRow(201, "every-business", "Built for teams"),
  slideRow(202, "work-smarter", "Automate the busywork"),
  slideRow(203, "start-free", "Ready when you are"),
];
const PARTIAL_EXPECTED_PAGES = [
  { slug: "welcome", category: "Welcome to Sayzio" },
  { slug: "every-business", category: "Built for teams" },
  { slug: "work-smarter", category: "Automate the busywork" },
  { slug: "start-free", category: "Ready when you are" },
];

// PASS 4 — SHUFFLED + UNRECOGNIZED feed: the admin seeded a framing slide
// (work-smarter) ahead of another (every-business), included a brand-new slug
// the app has no bundled theme/image for (spotlight), and left start-free last.
// The app must render them in the exact ARRAY order it received (proving it
// doesn't silently drop or re-sort rows), fall back gracefully for the unknown
// slug, and never crash.
const SHUFFLED_SENTINEL_BODY = "Seeded out of order with a brand-new slide.";
const UNKNOWN_SLIDE_CATEGORY = "Fresh this week";
const MOCK_SHUFFLED_SLIDES = [
  slideRow(300, "welcome", "Welcome to Sayzio", SHUFFLED_SENTINEL_BODY),
  slideRow(301, "work-smarter", "Automate the busywork"),
  slideRow(302, "every-business", "Built for teams"),
  slideRow(303, "spotlight", UNKNOWN_SLIDE_CATEGORY),
  slideRow(304, "start-free", "Ready when you are"),
];
const SHUFFLED_EXPECTED_PAGES = [
  { slug: "welcome", category: "Welcome to Sayzio" },
  { slug: "work-smarter", category: "Automate the busywork" },
  { slug: "every-business", category: "Built for teams" },
  { slug: "spotlight", category: UNKNOWN_SLIDE_CATEGORY },
  { slug: "start-free", category: "Ready when you are" },
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
async function countPagerDots(page, minDots = 5) {
  return page.evaluate((min) => {
    const parents = Array.from(document.querySelectorAll("div"));
    for (const p of parents) {
      const kids = Array.from(p.children).filter((c) => c.tagName === "DIV");
      if (kids.length < min) continue;
      const allDots = kids.every((k) => {
        const r = k.getBoundingClientRect();
        return r.height >= 4 && r.height <= 14 && r.width >= 4 && r.width <= 40;
      });
      if (allDots) return kids.length;
    }
    return -1;
  }, minDots);
}

// Read the visible label of the bottom CTA button. It is the "Continue" /
// "Get started" text; return whichever is present in the DOM.
async function ctaLabelPresent(page, label) {
  return (await page.getByText(label, { exact: true }).count()) > 0;
}

// Assert the "everything-you-need" slide's 3×3 business-tools grid rendered.
// That slide ships a null body on purpose — the grid IS its body — so a
// regression that dropped the grid would leave a blank card. We prove every
// one of the 9 tool labels is in the DOM (the grid populated) AND that a
// representative label is geometrically within the frame (the grid is on the
// active, paged-in slide, not clipped or off-screen). Caller must already be
// on slide 3.
async function assertToolsGridRenders(page, label) {
  // Wait for the grid to have paged into the frame — key off a distinctive
  // label so this can't race the slide swap.
  await waitForChipInFrame(page, TOOLS_GRID_LABELS[0]);
  for (const tool of TOOLS_GRID_LABELS) {
    const count = await page.getByText(tool, { exact: true }).count();
    if (count < 1) {
      fail(
        `${label}: slide 3 tools grid missing "${tool}" — the 3×3 grid did ` +
          `not render (blank body regression)`,
      );
    }
  }
  await assertWithinFrame(
    page,
    page.getByText(TOOLS_GRID_LABELS[0], { exact: true }).first(),
    `${label}: slide 3 tools grid ("${TOOLS_GRID_LABELS[0]}")`,
  );
  log(`${label}: slide 3 renders all ${TOOLS_GRID_LABELS.length} tool labels`);
}

// Walk a whole arc for a given admin-managed payload and assert it renders as a
// coherent carousel: the per-pass welcome sentinel body proves THIS payload
// (not the bundled fallback) is what rendered; the pager dot count matches the
// expected page count; every page's category chip pages into the frame in the
// expected ARRAY order; and the CTA starts on "Continue" and flips to
// "Get started" only on the final page. `expected` is the exact set of slides
// the feed carried, in order, so a coherent walk also proves the app rendered
// precisely what it received for the partial/misordered feed.
async function assertArcRenders(pg, expected, { label, sentinelBody }) {
  // Prove the mocked admin payload actually swapped in (its unique welcome
  // body only exists in this payload). setSlides(items) commits the new copy
  // and the new pager dot count in the same render, so once the sentinel is
  // visible the dot count below reflects THIS payload, not the fallback.
  await pg
    .getByText(sentinelBody, { exact: false })
    .first()
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  log(`${label}: welcome sentinel rendered → admin payload applied`);

  const count = expected.length;
  // These arcs may be shorter than the full 5-page one, so lower the dot-count
  // floor to the expected count (the default assumes the full arc's 5 dots).
  const dots = await countPagerDots(pg, count);
  if (dots !== count) {
    fail(`${label}: expected ${count} pager dots, found ${dots}`);
  }
  log(`${label}: pager renders exactly ${dots} dots`);

  // Page 0 chip within frame + starting CTA reads "Continue".
  await assertWithinFrame(
    pg,
    pg.getByText(expected[0].category, { exact: true }).first(),
    `${label}: page 1/${count} (${expected[0].slug}) category chip`,
  );
  if (!(await ctaLabelPresent(pg, "Continue"))) {
    fail(`${label}: CTA should read "Continue" on the first page`);
  }
  if (await ctaLabelPresent(pg, "Get started")) {
    fail(`${label}: CTA should NOT read "Get started" on the first page`);
  }

  // Walk the rest of the arc in order — page 0 already asserted above.
  for (let i = 1; i < count; i++) {
    const { slug, category } = expected[i];
    await swipeToNextSlide(pg);
    await waitForChipInFrame(pg, category);
    await assertWithinFrame(
      pg,
      pg.getByText(category, { exact: true }).first(),
      `${label}: page ${i + 1}/${count} (${slug}) category chip`,
    );
  }

  // Final page flips the CTA — proving the last-seeded slide stayed last. The
  // label is driven by the onScroll `index` state, which settles a beat after
  // the last swipe's scroll lands, so wait for the flip rather than reading it
  // immediately (a shorter arc has fewer swipes, so less time to catch up).
  await pg
    .getByText("Get started", { exact: true })
    .first()
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  if (await ctaLabelPresent(pg, "Continue")) {
    fail(`${label}: CTA should NOT read "Continue" on the final page`);
  }
  log(`${label}: all ${count} pages rendered in order; CTA behaved`);
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
async function prepareContext(browser, colorScheme, slidesPayload, opts = {}) {
  const context = await browser.newContext(
    colorScheme
      ? { viewport: VIEWPORT, colorScheme }
      : { viewport: VIEWPORT },
  );

  // A fresh install: onboarding NOT complete, signed out. Also stub
  // window.open so Linking.openURL (react-native-web opens external links via
  // window.open) is captured instead of spawning a real popup.
  //
  // `opts.legacyV1Slides` (optional) pre-seeds a STALE v1 slides-cache entry so
  // we can prove an upgrade over an old cache still renders the new arc.
  await context.addInitScript(
    ({ legacyKey, legacyV1Slides }) => {
    try {
      window.localStorage.removeItem("1inme.onboarding.complete");
      window.localStorage.removeItem("1inme.auth.token");
      window.localStorage.removeItem("1inme.auth.user");
      // Always start from a clean v2 cache so the fallback/API path drives the
      // render (no leftover v2 entry from a prior context on the same origin).
      window.localStorage.removeItem("1inme.onboarding.slides.cache.v2");
      window.localStorage.removeItem(legacyKey);
      if (legacyV1Slides) {
        window.localStorage.setItem(legacyKey, JSON.stringify(legacyV1Slides));
      }
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
    },
    { legacyKey: LEGACY_V1_CACHE_KEY, legacyV1Slides: opts.legacyV1Slides ?? null },
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

    // ---- 2. Pager renders exactly 5 dots -------------------------------
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

    // ---- 4. Walk the ENTIRE 5-page arc, asserting each page renders ----
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
      // Slide 3 ("everything-you-need") carries a null body — its 3×3 tools
      // icon grid IS the body. Assert the grid rendered (all 9 labels present,
      // one within frame) so a blank card would surface here.
      if (slug === "everything-you-need") {
        await assertToolsGridRenders(page, "fallback");
      }
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

      // Prove the API path actually swapped in FIRST — the sentinel body only
      // the mocked payload carries must appear (so this pass can't silently be
      // re-testing the bundled fallback if setSlides(items) ever regresses).
      // Waiting on the sentinel before the chip assertions below also avoids a
      // render-swap race: the fallback paints the welcome chip, then the API
      // slides commit and re-mount it, so a chip check that runs mid-swap can
      // catch a detached node with no bounding box.
      await seededPage
        .getByText(API_SENTINEL_BODY, { exact: false })
        .first()
        .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
      log("seeded welcome body sentinel rendered → admin API slides applied");

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

      // Same page count as the fallback arc.
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

    // ---- 8. PARTIAL admin feed still renders a coherent arc -------------
    // The admin seeded only SOME slugs (welcome, every-business, work-smarter,
    // start-free). The app must render exactly those 4 slides — nothing
    // injected — keep start-free last, keep a working Continue→Get started
    // CTA, and Skip must still route to the auth screen.
    log("partial pass: a subset feed should render a coherent, shorter arc");
    const partialCtx = await prepareContext(
      browser,
      undefined,
      MOCK_PARTIAL_SLIDES,
    );
    const partialPage = await partialCtx.newPage();
    partialPage.setDefaultTimeout(STEP_TIMEOUT_MS);
    partialPage.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
    partialPage.on("pageerror", (e) => log("pageerror (partial):", e.message));
    try {
      await partialPage.goto(appUrl, { waitUntil: "domcontentloaded" });
      await assertArcRenders(partialPage, PARTIAL_EXPECTED_PAGES, {
        label: "partial",
        sentinelBody: PARTIAL_SENTINEL_BODY,
      });

      // Skip still works with a partial feed — routes to the auth screen.
      const partialSkip = partialPage.getByText("Skip", { exact: true }).first();
      await assertWithinFrame(partialPage, partialSkip, "partial Skip button");
      await partialSkip.click();
      await partialPage
        .getByText("Welcome back", { exact: false })
        .first()
        .waitFor({ timeout: STEP_TIMEOUT_MS });
      log("partial pass: Skip landed on the auth screen (Welcome back)");
    } finally {
      await partialCtx.close().catch(() => {});
    }

    // ---- 9. SHUFFLED + UNRECOGNIZED feed still renders a coherent arc ----
    // The admin seeded a framing slide (work-smarter) ahead of another
    // (every-business), included a brand-new slug the app has no bundled
    // theme/image for (spotlight), and left start-free last. The app must
    // render them in the exact ARRAY order it received (it doesn't drop or
    // re-sort rows), fall back gracefully for the unknown slug, and never
    // crash or strand the user.
    log("shuffled pass: an out-of-order feed with a new slug stays coherent");
    const shuffledCtx = await prepareContext(
      browser,
      undefined,
      MOCK_SHUFFLED_SLIDES,
    );
    const shuffledPage = await shuffledCtx.newPage();
    shuffledPage.setDefaultTimeout(STEP_TIMEOUT_MS);
    shuffledPage.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
    shuffledPage.on("pageerror", (e) =>
      log("pageerror (shuffled):", e.message),
    );
    try {
      await shuffledPage.goto(appUrl, { waitUntil: "domcontentloaded" });
      await assertArcRenders(shuffledPage, SHUFFLED_EXPECTED_PAGES, {
        label: "shuffled",
        sentinelBody: SHUFFLED_SENTINEL_BODY,
      });
    } finally {
      await shuffledCtx.close().catch(() => {});
    }

    // ---- 10. CACHE-BUST: a stale v1 cache still renders the NEW arc ------
    // Simulate an EXISTING user upgrading over a device that already holds the
    // old, longer intro arc under the pre-bump v1 cache key. AsyncStorage on
    // web is localStorage under the raw key, so seeding LEGACY_V1_CACHE_KEY
    // reproduces exactly that state. The app now reads the v2 key only, so:
    //   • the pager must show the NEW 5-dot arc (not the stale 10-slide one)
    //   • NO stale category copy may appear anywhere
    //   • slide 3's tools grid must still render (not a blank body)
    //   • the final "Get started" CTA must route a signed-out user to auth
    // This is the closest an automated harness gets to the real-device
    // "first launch after the cache busts" check the task calls for.
    log("cache-bust pass: a stale v1 cache must not resurrect the old arc");
    const legacyCtx = await prepareContext(browser, undefined, [], {
      legacyV1Slides: LEGACY_V1_SLIDES,
    });
    const legacyPage = await legacyCtx.newPage();
    legacyPage.setDefaultTimeout(STEP_TIMEOUT_MS);
    legacyPage.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
    legacyPage.on("pageerror", (e) => log("pageerror (cache-bust):", e.message));
    try {
      await legacyPage.goto(appUrl, { waitUntil: "domcontentloaded" });

      // The NEW welcome chip proves the fresh 5-slide fallback arc rendered.
      await assertWithinFrame(
        legacyPage,
        legacyPage.getByText(WELCOME_CATEGORY, { exact: true }).first(),
        "cache-bust slide 1 (welcome) category chip",
      );

      // Exactly 5 dots — a stale 10-slide cache would have surfaced 10.
      const legacyDots = await countPagerDots(legacyPage);
      if (legacyDots !== EXPECTED_PAGE_COUNT) {
        fail(
          `cache-bust: expected ${EXPECTED_PAGE_COUNT} pager dots (new arc), ` +
            `found ${legacyDots} — a stale v1 cache leaked into the render`,
        );
      }
      log(`cache-bust: pager renders the NEW ${legacyDots}-dot arc`);

      // The stale category copy must NOT be anywhere in the DOM.
      const staleHits = await legacyPage
        .getByText(LEGACY_STALE_CATEGORY, { exact: false })
        .count();
      if (staleHits > 0) {
        fail(
          `cache-bust: stale v1 copy "${LEGACY_STALE_CATEGORY}" leaked into ` +
            `the render (${staleHits} node(s)) — the cache bump did not orphan ` +
            `the old cache`,
        );
      }
      log("cache-bust: no stale v1 category copy present");

      // Walk to slide 3 and prove the tools grid still renders over a stale
      // cache (the exact "blank body on slide 3" regression the task flags).
      for (let i = 1; i <= 2; i++) {
        const { category } = EXPECTED_PAGES[i];
        await swipeToNextSlide(legacyPage);
        await waitForChipInFrame(legacyPage, category);
      }
      await assertToolsGridRenders(legacyPage, "cache-bust");

      // Page to the final slide and tap "Get started" — a signed-out user must
      // land on the auth screen (finish() → router.replace("/(auth)")).
      for (let i = 3; i < EXPECTED_PAGE_COUNT; i++) {
        const { category } = EXPECTED_PAGES[i];
        await swipeToNextSlide(legacyPage);
        await waitForChipInFrame(legacyPage, category);
      }
      const getStarted = legacyPage
        .getByText("Get started", { exact: true })
        .first();
      await getStarted.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
      await assertWithinFrame(legacyPage, getStarted, "cache-bust Get started CTA");
      await getStarted.click();
      await legacyPage
        .getByText("Welcome back", { exact: false })
        .first()
        .waitFor({ timeout: STEP_TIMEOUT_MS });
      log('cache-bust: "Get started" routed a signed-out user to auth');
    } finally {
      await legacyCtx.close().catch(() => {});
    }

    // ---- 11. Wordmark stays the WHITE variant in BOTH color schemes -----
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

// Termination guarantee: runHarness exits the process as soon as run()
// settles and arms a watchdog, so a leaked handle can never stall the run.
runHarness(run, { log, onError: (e) => fail(e?.stack || e?.message || String(e)) });
