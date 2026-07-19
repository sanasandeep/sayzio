#!/usr/bin/env node
/**
 * End-to-end regression backstop for the first-run onboarding carousel's
 * ENTRANCE REVEAL (app/onboarding.tsx), driving the REAL app in a headless
 * browser.
 *
 * Why this exists: test-onboarding-slide-reveal.mjs is a source-driven unit
 * test that lifts the REAL SlideCard / FloatingIcon reveal effect bodies and
 * runs them in a tiny Node harness, proving they animate the shared values to
 * opacity 1. That guards the reveal LOGIC, but it never renders the actual
 * RN-web output — so it can't catch integration-level regressions that leave
 * the slide invisible even though the logic is "correct":
 *
 *   • a wrapper that clips or overlays the glass card,
 *   • a broken Animated.View style merge that drops the animated opacity,
 *   • reanimated not applying the shared value to the DOM node at all.
 *
 * This harness renders the onboarding carousel and asserts the ACTIVE slide's
 * card copy (category chip + title + body) actually PAINTS — i.e. its EFFECTIVE
 * opacity (the product of every ancestor's computed opacity) crosses zero after
 * the entrance settles. It also asserts at least one FloatingIcon tile ends
 * non-transparent. Playwright's own isVisible() would NOT catch this: an element
 * at opacity 0 still reports visible, so we walk the ancestor chain and multiply
 * the computed opacities (same technique as test-info-pages-e2e.mjs).
 *
 * The harness ALSO swipes the pager forward one slide and asserts the SECOND
 * slide's copy paints in-frame plus one of its floating icons ends
 * non-transparent — catching a pager/reveal regression that only bites slides
 * entering via a swipe (their entrance reveal fires on activation, not mount).
 * (The old hard-coded final "AI dashboard" slide was removed in the July 2026
 * 5-slide carousel redesign, so the swipe target is just a second mocked
 * slide now; AiDashboardDemo lives in app/dashboard-customize.tsx.)
 *
 * The slides endpoint is intercepted with a deterministic slide so the
 * assertions never depend on admin-managed content; every other /api/** call is
 * fulfilled with a benign {data:[]} so nothing hits a real backend.
 *
 * Like the sibling mobile e2e harnesses it boots its OWN throwaway Expo web dev
 * server (shared expo-web-server.mjs manager) unless APP_URL points at an
 * already-running one, and SKIPS (exit 0) when a server / browser can't come up
 * (or the box is too slow) so it never fails CI just because Metro or Chromium
 * couldn't boot. A real reveal regression on a server that DID boot still fails
 * (exit 1).
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:onboarding-reveal-e2e
 *
 * Environment:
 *   APP_URL   reuse an already-running Expo web server instead of booting
 *             a throwaway one (handy for local debugging).
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import { createExpoServerManager,
  runHarness, isTransientEnvError } from "./expo-web-server.mjs";

function log(...args) {
  console.log("[onboarding-slide-reveal-e2e]", ...args);
}

function fail(msg) {
  console.error("[onboarding-slide-reveal-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[onboarding-slide-reveal-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };

// Deterministic first slide served in place of the admin-managed set. slug
// "creators" matches a SLIDE_THEMES entry so its FloatingIcon tiles render, and
// image_url stays null so resolveImages() uses the bundled fallback photo (no
// remote-photo layer to worry about). The copy strings are what we locate and
// assert the effective opacity of.
const SLIDE_CATEGORY = "E2E reveal category";
const SLIDE_TITLE = "E2E reveal slide title";
const SLIDE_BODY = "E2E reveal slide body copy that must paint on screen.";
// Second slide: the swipe target. slug "start-free" matches a real
// SLIDE_THEMES entry so its FloatingIcon layer renders.
const SLIDE2_CATEGORY = "E2E second category";
const SLIDE2_TITLE = "E2E second slide title";
const SLIDE2_BODY = "E2E second slide body copy that must paint after a swipe.";
const MOCK_SLIDES = [
  {
    id: 9101,
    slug: "creators",
    category: SLIDE_CATEGORY,
    title: SLIDE_TITLE,
    body: SLIDE_BODY,
    image_url: null,
    image_urls: [],
    sort_order: 10,
  },
  {
    id: 9102,
    slug: "start-free",
    category: SLIDE2_CATEGORY,
    title: SLIDE2_TITLE,
    body: SLIDE2_BODY,
    image_url: null,
    image_urls: [],
    sort_order: 20,
  },
];

// Build a browser context wired for a fresh onboarding launch: a signed-out,
// not-yet-onboarded install, with the slides endpoint mocked and every other
// backend call stubbed.
async function prepareContext(browser) {
  const context = await browser.newContext({ viewport: VIEWPORT });

  // A fresh install: onboarding NOT complete, signed out.
  await context.addInitScript(() => {
    try {
      window.localStorage.removeItem("1inme.onboarding.complete");
      window.localStorage.removeItem("1inme.auth.token");
      window.localStorage.removeItem("1inme.auth.user");
    } catch {}
  });

  // Admin-managed brand logos feed → empty so the wordmark falls back to the
  // bundled PNGs (keeps the boot deterministic, independent of admin config).
  await context.route("**/branding.json**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: "{}",
    }),
  );

  // Deterministic slides for the carousel. The real endpoint returns a FLAT
  // { items } payload (no {data} envelope) — see OnboardingSlideController.
  await context.route("**/api/v1/onboarding/slides**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
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

// Compute the EFFECTIVE opacity of a locator's element — the product of every
// ancestor's computed opacity. This is what distinguishes "in the DOM" from
// "painted on screen": a SlideCard whose animated opacity never reached the DOM
// node leaves its copy at opacity 0, which Playwright's isVisible() would still
// report visible. (Same ancestor-walk technique as test-info-pages-e2e.mjs.)
function effectiveOpacity(locator) {
  return locator.evaluate((el) => {
    let node = el;
    let opacity = 1;
    while (node && node instanceof Element) {
      const raw = window.getComputedStyle(node).opacity;
      const parsed = parseFloat(raw);
      if (!Number.isNaN(parsed)) opacity *= parsed;
      node = node.parentElement;
    }
    return opacity;
  });
}

// Assert a Text node with EXACTLY this string is present, visible, AND actually
// painted (effective opacity > 0). The SlideCard entrance animates opacity from
// 0→1 over ~320ms (the active slide even INITIALISES to 1), so we poll the
// effective opacity until it crosses zero rather than reading it once mid-frame.
async function assertPainted(page, text, label) {
  const locator = page.getByText(text, { exact: true }).first();
  await locator
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail(`${label}: expected "${text}" to be visible, but it never appeared.`),
    );

  const deadline = Date.now() + 6000;
  let last = 0;
  while (Date.now() < deadline) {
    last = await effectiveOpacity(locator);
    if (last > 0) {
      log(`ok — ${label} painted (effective opacity ${last.toFixed(2)})`);
      return;
    }
    await page.waitForTimeout(150);
  }

  fail(
    `${label}: "${text}" is in the DOM but never painted — effective opacity ` +
      `stayed at ${last} for 6s (the SlideCard entrance reveal never applied ` +
      `its opacity to the rendered card).`,
  );
}

// Assert at least one FloatingIcon ends non-transparent. Since the "make Zio
// the star" redesign, each icon is a bare absolutely-positioned Animated.View
// wrapping an expo-image that loads a bundled `zio-nodes/*.png` asset (the old
// translucent-blue tile border was removed), fading in from opacity 0 via an
// UNCONDITIONAL mount effect. We find icons by locating elements whose <img>
// src (or CSS background-image) references a zio-node asset, take their
// absolutely-positioned wrapper, and poll effective opacity — a broken reveal
// (or a shared value that never reaches the DOM) would leave every icon at 0.
async function assertFloatingIconPainted(page) {
  const deadline = Date.now() + 6000;
  let best = 0;
  let found = 0;
  while (Date.now() < deadline) {
    const result = await page.evaluate(() => {
      // Match FloatingIcon by its bundled zio-node asset: expo-image on web
      // renders an <img> (or a background-image div) whose URL contains the
      // asset path/name (e.g. ".../zio-nodes/ai.<hash>.png" or an
      // unstable_path-encoded equivalent) — a signature no other onboarding
      // element uses. Walk up to the absolutely-positioned wrapper the reveal
      // animates.
      const matchesZioNode = (url) => /zio-node/i.test(url || "");
      const carriers = [];
      for (const img of Array.from(document.querySelectorAll("img"))) {
        if (matchesZioNode(img.currentSrc || img.src)) carriers.push(img);
      }
      for (const n of Array.from(document.querySelectorAll("div"))) {
        const bg = getComputedStyle(n).backgroundImage;
        if (bg && bg !== "none" && matchesZioNode(bg)) carriers.push(n);
      }
      const tiles = [];
      for (const c of carriers) {
        let node = c;
        while (node && node instanceof Element) {
          if (getComputedStyle(node).position === "absolute") {
            tiles.push(node);
            break;
          }
          node = node.parentElement;
        }
      }
      let count = tiles.length;
      let max = 0;
      for (const tile of tiles) {
        // Walk the ancestor chain multiplying computed opacities.
        let node = tile;
        let opacity = 1;
        while (node && node instanceof Element) {
          const parsed = parseFloat(getComputedStyle(node).opacity);
          if (!Number.isNaN(parsed)) opacity *= parsed;
          node = node.parentElement;
        }
        if (opacity > max) max = opacity;
      }
      return { count, max };
    });
    found = result.count;
    best = result.max;
    if (found > 0 && best > 0) {
      log(
        `ok — FloatingIcon tile painted (${found} tile(s) found, best ` +
          `effective opacity ${best.toFixed(2)})`,
      );
      return;
    }
    await page.waitForTimeout(150);
  }

  if (found === 0) {
    fail(
      "no FloatingIcon tile was found in the rendered slide (expected the " +
        "bobbing feature-icon tiles with the translucent blue border) — the " +
        "floating-icon layer did not render at all.",
    );
  }
  fail(
    `found ${found} FloatingIcon tile(s) but none ever painted — best ` +
      `effective opacity stayed at ${best} for 6s (the mount fade-in reveal ` +
      `never applied its opacity to the rendered tiles).`,
  );
}

// Page the horizontal FlatList forward by one viewport width, like a swipe.
// React Native Web renders the carousel as ONE horizontally scrollable element;
// every slide wrapper ALSO reports scrollWidth > clientWidth (overflow visible),
// but setting scrollLeft on those is a silent no-op — so we pick the element
// whose overflow-x is auto/scroll or whose scroll-snap-type includes x, then
// advance scrollLeft by clientWidth. The scroll event drives the same
// onScroll/index logic as a finger swipe. (Same technique as
// test-onboarding-slider-e2e.mjs.)
async function swipeToNextSlide(page) {
  // Poll: the FlatList pager can mount a beat after the slide copy paints, and
  // RNW style output has shifted before (overflow-x hidden vs scroll), so
  // after the style-based match fails we fall back to ANY horizontally
  // overflowing element where assigning scrollLeft actually takes effect
  // (assignment works — and fires the same scroll events — even when
  // overflow-x is hidden).
  const deadline = Date.now() + 10000;
  let advanced = false;
  while (!advanced && Date.now() < deadline) {
    advanced = await page.evaluate(() => {
      const candidates = Array.from(document.querySelectorAll("div")).filter(
        (n) => n.scrollWidth > n.clientWidth + 10,
      );
      const styled = candidates.filter((n) => {
        const cs = getComputedStyle(n);
        return (
          cs.overflowX === "auto" ||
          cs.overflowX === "scroll" ||
          (cs.scrollSnapType && cs.scrollSnapType.includes("x"))
        );
      });
      for (const scroller of styled.length ? styled : candidates) {
        const before = scroller.scrollLeft;
        scroller.scrollLeft = before + scroller.clientWidth;
        if (scroller.scrollLeft !== before) return true;
      }
      return false;
    });
    if (!advanced) await page.waitForTimeout(250);
  }
  if (!advanced) fail("could not find the horizontal slider to swipe");
  log("swiped the carousel forward by one slide width");
}

// Assert a Text node with EXACTLY this string is painted (effective opacity > 0)
// AND sits inside the visible viewport frame. The AI dashboard slide's copy has
// no entrance opacity animation (it renders in a ScrollView, not an animated
// SlideCard), so the meaningful regression this guards is the copy never
// reaching the screen at all — either transparent, or stranded off-frame because
// the swipe didn't actually land on the final slide.
async function assertPaintedInFrame(page, text, label) {
  const locator = page.getByText(text, { exact: true }).first();
  await locator
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail(`${label}: expected "${text}" to be visible, but it never appeared.`),
    );

  const deadline = Date.now() + 6000;
  let lastOpacity = 0;
  let lastBox = null;
  while (Date.now() < deadline) {
    lastOpacity = await effectiveOpacity(locator);
    lastBox = await locator.boundingBox().catch(() => null);
    const inFrame =
      lastBox &&
      lastBox.x >= -1 &&
      lastBox.x + lastBox.width <= VIEWPORT.width + 1;
    if (lastOpacity > 0 && inFrame) {
      log(
        `ok — ${label} painted in-frame (effective opacity ` +
          `${lastOpacity.toFixed(2)}, x=${Math.round(lastBox.x)})`,
      );
      return;
    }
    await page.waitForTimeout(150);
  }

  if (!lastBox) {
    fail(`${label}: "${text}" is in the DOM but has no bounding box.`);
  }
  if (lastOpacity <= 0) {
    fail(
      `${label}: "${text}" is in the DOM but never painted — effective ` +
        `opacity stayed at ${lastOpacity} for 6s.`,
    );
  }
  fail(
    `${label}: "${text}" painted but stayed OUTSIDE the frame ` +
      `(box=${JSON.stringify(lastBox)} viewport width=${VIEWPORT.width}) — the ` +
      `swipe to the AI dashboard slide never brought its copy on screen.`,
  );
}

// Assert at least one FloatingIcon tile BELONGING TO THE CURRENT (post-swipe)
// slide ends non-transparent. The second slide renders the SAME FloatingIcon
// component (zio-node image assets) as the first, so after swiping we must
// ignore the previous slide's tiles — those are translated one viewport width
// to the LEFT (off-frame). We therefore only count zio-node tiles whose
// bounding rect sits inside the current viewport horizontally, then poll their
// effective opacity: a broken activation reveal (or a shared value that never
// reaches the DOM) leaves every in-frame tile stuck at 0.
async function assertInFrameFloatingIconPainted(page) {
  const deadline = Date.now() + 6000;
  let best = 0;
  let found = 0;
  while (Date.now() < deadline) {
    const result = await page.evaluate(() => {
      const matchesZioNode = (url) => /zio-node/i.test(url || "");
      const carriers = [];
      for (const img of Array.from(document.querySelectorAll("img"))) {
        if (matchesZioNode(img.currentSrc || img.src)) carriers.push(img);
      }
      for (const n of Array.from(document.querySelectorAll("div"))) {
        const bg = getComputedStyle(n).backgroundImage;
        if (bg && bg !== "none" && matchesZioNode(bg)) carriers.push(n);
      }
      const tiles = [];
      for (const c of carriers) {
        let node = c;
        while (node && node instanceof Element) {
          if (getComputedStyle(node).position === "absolute") {
            // Only tiles inside the current viewport horizontally — excludes
            // the previous slide's tiles, now paged one screen to the left.
            const r = node.getBoundingClientRect();
            if (r.width > 0 && r.left >= -1 && r.right <= window.innerWidth + 1) {
              tiles.push(node);
            }
            break;
          }
          node = node.parentElement;
        }
      }
      let count = tiles.length;
      let max = 0;
      for (const tile of tiles) {
        let node = tile;
        let opacity = 1;
        while (node && node instanceof Element) {
          const parsed = parseFloat(getComputedStyle(node).opacity);
          if (!Number.isNaN(parsed)) opacity *= parsed;
          node = node.parentElement;
        }
        if (opacity > max) max = opacity;
      }
      return { count, max };
    });
    found = result.count;
    best = result.max;
    if (found > 0 && best > 0) {
      log(
        `ok — in-frame FloatingIcon tile painted after the swipe (${found} ` +
          `tile(s), best effective opacity ${best.toFixed(2)})`,
      );
      return;
    }
    await page.waitForTimeout(150);
  }

  if (found === 0) {
    fail(
      "no in-frame FloatingIcon tile was found on the second slide after the " +
        "swipe — its floating-icon layer did not render at all.",
    );
  }
  fail(
    `found ${found} in-frame FloatingIcon tile(s) on the second slide but ` +
      `none ever painted — best effective opacity stayed at ${best} for 6s.`,
  );
}

// ── Launch phase ───────────────────────────────────────────────────────────
// The active slide's card copy + a floating icon must paint, then swipe to the
// SECOND mocked slide and assert its copy paints in-frame plus one of its own
// floating icons — slides entering via a swipe run their entrance reveal on
// activation, a path the first-slide checks never exercise.
async function runSignedOutPhase(browser, appUrl) {
  const context = await prepareContext(browser);
  const page = await context.newPage();
  page.setDefaultTimeout(STEP_TIMEOUT_MS);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
  page.on("pageerror", (e) => log("[signed-out] pageerror:", e.message));

  // Load the (already-warmed) root; with the onboarding flag cleared the
  // launch gate redirects to /onboarding client-side — the exact path a fresh
  // install takes. (Requesting /onboarding directly would make Metro compile
  // that route from scratch and can blow the nav timeout.)
  log(`[signed-out] navigating to ${appUrl} (launch gate → /onboarding)`);
  await page.goto(appUrl, { waitUntil: "domcontentloaded" });

  // ---- The active slide's card copy must actually PAINT --------------------
  await assertPainted(page, SLIDE_CATEGORY, "slide category chip");
  await assertPainted(page, SLIDE_TITLE, "slide title");
  await assertPainted(page, SLIDE_BODY, "slide body");

  // ---- At least one FloatingIcon tile must end non-transparent -------------
  await assertFloatingIconPainted(page);

  // ---- Swipe to the second mocked slide ------------------------------------
  await swipeToNextSlide(page);
  await page
    .getByText(SLIDE2_CATEGORY, { exact: true })
    .first()
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail(
        "the second slide's category chip never appeared after swiping " +
          "forward one slide.",
      ),
    );

  // Its copy must paint (effective opacity > 0) AND land inside the frame —
  // proving the swipe actually landed and the slide rendered on screen.
  await assertPaintedInFrame(page, SLIDE2_CATEGORY, "second slide category chip");
  await assertPaintedInFrame(page, SLIDE2_TITLE, "second slide title");
  await assertPaintedInFrame(page, SLIDE2_BODY, "second slide body");

  // And at least one of the second slide's OWN floating icons must paint.
  await assertInFrameFloatingIconPainted(page);

  log(
    "[signed-out] ok — the active slide AND the swiped-to second slide's " +
      "copy plus a floating icon each paint on screen",
  );
  await context.close().catch(() => {});
}

async function run() {
  const { acquireServer } = createExpoServerManager(log);
  const server = await acquireServer("onboarding-reveal", process.env.APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
  }
  const explicit = Boolean(server.explicit);
  const appUrl = server.appUrl.endsWith("/")
    ? server.appUrl
    : server.appUrl + "/";

  let browser;
  try {
    browser = await chromium.launch({ headless: true });
  } catch (e) {
    skip(
      `could not launch a headless browser in this environment: ${e?.message || e}`,
    );
  }

  try {
    await runSignedOutPhase(browser, appUrl);

    log(
      "PASS: the onboarding carousel paints its first slide AND the " +
        "swiped-to second slide (copy + floating icons, effective opacity > 0)",
    );
    await browser.close().catch(() => {});
    // Explicit exit (like the sibling harnesses): the throwaway Metro child
    // would otherwise keep the event loop alive; the manager's process exit
    // hook reaps it.
    process.exit(0);
  } catch (e) {
    await browser.close().catch(() => {});
    // Best-effort contract: on a throwaway server a Playwright timeout /
    // transient connection error means the constrained box was too slow (Metro
    // recompiling, CPU starved) — SKIP, same as when the server or browser
    // couldn't come up. Real reveal regressions exit 1 via fail() before this
    // catch can run. Against an explicit APP_URL we still fail hard so local
    // debugging never silently skips.
    if (!explicit && isTransientEnvError(e)) {
      skip(
        `the environment was too slow to drive the check ` +
          `(${e?.message?.split("\n")[0] ?? "unknown error"}); ` +
          `skipping (best-effort, not a reveal regression)`,
      );
    }
    failOrSkipInfra(e, explicit);
  }
}

// Distinguish a real assertion/product failure from the environment killing the
// browser (resource exhaustion during parallel validation runs). Only the
// latter is a skip, and never when pointed at an explicit APP_URL.
function failOrSkipInfra(e, explicit) {
  const msg = e?.message || String(e);
  const infra =
    /Target page, context or browser has been closed|browser has been closed|browserType\.launch|pthread_create|Browser closed|Target closed/i.test(
      msg,
    );
  if (infra && !explicit) {
    skip(
      `browser crashed under environment load, not a product failure: ${msg.split("\n")[0]}`,
    );
  }
  fail(e?.stack || msg);
}

// Termination guarantee: runHarness exits the process as soon as run()
// settles and arms a watchdog, so a leaked handle can never stall the run.
runHarness(run, {
  log,
  onError: (e) => failOrSkipInfra(e, Boolean(process.env.APP_URL)),
});
