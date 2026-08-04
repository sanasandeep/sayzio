#!/usr/bin/env node
/**
 * E2E regression gate for the auto-hiding top bar (Task #6681).
 *
 * The mobile top bar (hamburger + logo + bell — components/PinnedTopBar.tsx)
 * hides on scroll-down and reappears on scroll-up, driven by the SAME shared
 * scroll context (contexts/TabBarContext.tsx reportScroll) as the floating
 * bottom tab bar (components/FloatingTabBar.tsx). Nothing in CI verified the
 * geometry, so a regression — a screen dropping its onScroll reportScroll
 * wiring, or the reanimated translateY transform breaking on web — would
 * silently pin the bar or hide it permanently.
 *
 * What it asserts, driving the REAL app signed-in in headless Chromium on the
 * Links tab (fed 40 mocked rows so the FlatList genuinely scrolls):
 *   1. At the top of the page both bars sit at translateY 0 (fully visible).
 *   2. Scrolling DOWN moves the top bar's translateY off-screen upward
 *      (<= -TOP_BAR_H) AND the bottom tab bar off-screen downward (>= +100),
 *      in lockstep.
 *   3. Scrolling UP (while still far from the top) brings BOTH back to 0.
 *   4. Returning to the top (< 40px) keeps both visible, and a small jiggle
 *      within that near-top zone never hides them.
 *
 * Geometry is asserted via getComputedStyle().transform matrix ty (see
 * .agents/memory/sticky-header-drawer-scroll-lock.md: assert geometry, not
 * state). Scroll landings wait for a STABLE scrollTop, and every hide attempt
 * ends with a guaranteed large down-nudge so a layout-shift-induced upward
 * scroll event can't cancel the hide (autohide e2e scroll-landing race).
 *
 * Every /api/** call is mocked ({data:...}) so the authenticated shell never
 * reaches a real backend — the autohide behaviour is pure client-side. Like
 * the sibling mobile harnesses it boots its OWN throwaway Expo web dev server
 * (shared expo-web-server.mjs manager) unless APP_URL points at a running
 * one, and SKIPs (exit 0) when a server/browser can't come up. ALWAYS grep
 * the log for the literal PASS line — a SKIP also exits 0.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:topbar-autohide-e2e
 */

import { chromium } from "playwright";

import {
  createExpoServerManager,
  runHarness,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[topbar-autohide-e2e]", ...args);
}
function fail(msg) {
  console.error("[topbar-autohide-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[topbar-autohide-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const STEP_TIMEOUT_MS = 90_000;
const NAV_TIMEOUT_MS = 120_000;

// Mirrors contexts/TabBarContext.tsx: TOP_BAR_H = 56 (insets.top is 0 on
// web), hidden top target = -(insets.top + 56); bottom hidden target = +120.
const TOP_HIDDEN_MAX = -50; // ty must be at or beyond this to count as hidden
const BOTTOM_HIDDEN_MIN = 100;
const VISIBLE_EPS = 1;

const MOCK_TOKEN = "e2e-topbar-autohide-token";
const MOCK_USER = {
  id: 78,
  display_name: "Autohide Tester",
  email: "topbar-autohide@example.com",
};

// 40 rows ≈ ~3000px of list — plenty of scroll range in a 720px viewport.
const LINK_COUNT = 40;
function mockLinks() {
  const items = [];
  for (let i = 1; i <= LINK_COUNT; i++) {
    items.push({
      id: i,
      type: "url",
      alias: `autohide-${i}`,
      title: `Autohide fixture link ${i}`,
      long_url: `https://example.test/${i}`,
      visibility: "public",
      is_active: true,
      is_verified: false,
      is_password_protected: false,
      expires_at: null,
      total_clicks: i,
      unique_clicks: i,
      seo_title: null,
      seo_description: null,
      seo_image: null,
      domain_id: null,
      domain: null,
      project: null,
      short_url: `https://1in.me/autohide-${i}`,
      created_at: "2026-01-01T00:00:00Z",
      updated_at: "2026-01-01T00:00:00Z",
      settings: null,
    });
  }
  return {
    items,
    meta: { current_page: 1, per_page: 100, total: LINK_COUNT, last_page: 1 },
  };
}

// ---------------------------------------------------------------------------
// In-page helpers (evaluated in the browser)
// ---------------------------------------------------------------------------

// Read the current viewport-relative position of both bars via bounding
// rects (memory: assert GEOMETRY, not internal state — the reanimated
// translateY moves every descendant, so the rects reflect it exactly):
//  - top bar: the "Open menu" chip inside PinnedTopBar.
//  - bottom bar: the role="tablist" row inside FloatingTabBar.
// Returns null entries while the app is still mounting.
const READ_BARS = () => {
  const menuBtn = document.querySelector('[aria-label="Open menu"]');
  // Measure a real TAB, not the tablist wrapper — RNW can render the
  // tablist role on a zero-height layout wrapper whose rect is useless.
  let tab = null;
  for (const el of document.querySelectorAll('[role="tab"]')) {
    const r = el.getBoundingClientRect();
    if (r.height > 10) {
      tab = r;
      break;
    }
  }
  return {
    top: menuBtn ? menuBtn.getBoundingClientRect().top : null,
    bottom: tab ? tab.top : null,
    viewportH: window.innerHeight,
  };
};

// Find the FlatList scroll container that actually OWNS the onScroll →
// reportScroll wiring: walk up from a rendered link row to the nearest
// scrollable ancestor (a tallest-scroller heuristic can grab an unrelated
// wrapper whose scrolling never reaches the list's onScroll).
const FIND_SCROLLER = (rowText) => {
  const all = document.querySelectorAll("div, span");
  let rowEl = null;
  for (const el of all) {
    if (el.childElementCount === 0 && el.textContent === rowText) {
      rowEl = el;
      break;
    }
  }
  if (!rowEl) return { error: "row text not found" };
  let node = rowEl.parentElement;
  while (node && node !== document.body) {
    const cs = window.getComputedStyle(node);
    const range = node.scrollHeight - node.clientHeight;
    if (/(auto|scroll)/.test(cs.overflowY) && range > 200) {
      node.setAttribute("data-e2e-scroller", "1");
      return { range, clientHeight: node.clientHeight };
    }
    node = node.parentElement;
  }
  return { error: "no scrollable ancestor of the link row" };
};

// ---------------------------------------------------------------------------

async function readBars(page) {
  return page.evaluate(READ_BARS);
}

async function pollBars(page, label, pred) {
  const deadline = Date.now() + 20_000;
  let last = null;
  for (;;) {
    last = await readBars(page);
    if (
      last &&
      typeof last.top === "number" &&
      typeof last.bottom === "number" &&
      pred(last)
    ) {
      return last;
    }
    if (Date.now() > deadline) {
      fail(`${label}: bars never reached the expected geometry: ${JSON.stringify(last)}`);
    }
    await new Promise((r) => setTimeout(r, 200));
  }
}

// Scroll the FlatList scroller to ~target and wait for a STABLE landing
// (two consecutive equal polls) NEAR the target — never exact-match, layout
// can shift a few px under us (memory: autohide e2e scroll-landing race).
async function scrollToStable(page, target) {
  await page.evaluate((t) => {
    const el = document.querySelector("[data-e2e-scroller]");
    el.scrollTop = t;
  }, target);
  const deadline = Date.now() + 15_000;
  let prev = -1;
  for (;;) {
    const cur = await page.evaluate(
      () => document.querySelector("[data-e2e-scroller]").scrollTop,
    );
    if (cur === prev && Math.abs(cur - target) <= 64) return cur;
    prev = cur;
    if (Date.now() > deadline) return cur; // proceed; the nudge below decides
    await new Promise((r) => setTimeout(r, 250));
  }
}

// Nudge the scroller by delta in SMALL steps so each scroll event's delta
// clears the ±6px accumulator in reportScroll with an unambiguous direction.
async function nudge(page, delta) {
  const steps = 4;
  for (let i = 0; i < steps; i++) {
    await page.evaluate((d) => {
      const el = document.querySelector("[data-e2e-scroller]");
      el.scrollTop = Math.max(0, el.scrollTop + d);
    }, delta / steps);
    await new Promise((r) => setTimeout(r, 60));
  }
}

async function run(appUrl) {
  let browser;
  try {
    browser = await chromium.launch({ headless: true });
  } catch (e) {
    skip(`could not launch a headless browser: ${e?.message || e}`);
  }
  try {
    const context = await browser.newContext({ viewport: VIEWPORT });
    await context.addInitScript(
      ([token, user]) => {
        try {
          window.localStorage.setItem("1inme.onboarding.complete", "1");
          window.localStorage.setItem("1inme.auth.token", token);
          window.localStorage.setItem("1inme.auth.user", JSON.stringify(user));
        } catch {}
      },
      [MOCK_TOKEN, MOCK_USER],
    );
    // Catch-all first, links list second (last-registered route wins).
    await context.route("**/api/**", (route) =>
      route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: [] }),
      }),
    );
    const linksBody = JSON.stringify({ data: mockLinks() });
    await context.route(
      (url) => /\/api\/v1\/links(\?|$)/.test(url.href),
      (route) =>
        route.fulfill({
          status: 200,
          contentType: "application/json",
          body: linksBody,
        }),
    );

    const page = await context.newPage();
    page.setDefaultTimeout(STEP_TIMEOUT_MS);

    log("opening the Links tab signed-in…");
    await page.goto(`${appUrl}links`, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });

    // Wait for real content: a mocked row + both bars mounted.
    await page.getByText("Autohide fixture link 1", { exact: true }).waitFor({
      state: "visible",
      timeout: NAV_TIMEOUT_MS,
    });
    await pollBars(page, "mount", (b) => b.top !== null && b.bottom !== null);

    const scroller = await page.evaluate(FIND_SCROLLER, "Autohide fixture link 1");
    if (!scroller || scroller.error || !scroller.range || scroller.range < 400) {
      fail(
        `no sufficiently scrollable list found (need >400px of range): ${JSON.stringify(scroller)}`,
      );
    }
    log(`scroller found: ${scroller.range}px of scroll range`);

    // 1. At the top both bars are fully visible: capture the visible
    //    BASELINE rect positions (top chip in the top strip, tablist above
    //    the bottom edge). Everything after asserts translateY DELTAS from
    //    this baseline — the reanimated transform moves the rects 1:1.
    const base = await pollBars(
      page,
      "initial",
      (b) => b.top >= 0 && b.top < 100 && b.bottom < b.viewportH,
    );
    log(
      `visible baseline: menu chip top=${base.top}px, tab bar top=${base.bottom}px ` +
        `(viewport ${base.viewportH}px)`,
    );
    const topHidden = (b) => b.top - base.top <= TOP_HIDDEN_MAX;
    const bottomHidden = (b) => b.bottom - base.bottom >= BOTTOM_HIDDEN_MIN;
    const bothVisible = (b) =>
      Math.abs(b.top - base.top) <= VISIBLE_EPS &&
      Math.abs(b.bottom - base.bottom) <= VISIBLE_EPS;

    // 2. Scroll DOWN → both bars hide, in lockstep.
    // Modest target: the virtualized list may not have rendered its full
    // extent yet, and the landing check wants to settle near the target.
    await scrollToStable(page, 300);
    await nudge(page, 120); // guaranteed down-nudge past the ±6 accumulator
    const hidden = await pollBars(
      page,
      "scroll-down hide",
      (b) => topHidden(b) && bottomHidden(b),
    );
    log(
      `after scroll-down: top bar translated ${Math.round(hidden.top - base.top)}px up ` +
        `(off-screen), tab bar translated ${Math.round(hidden.bottom - base.bottom)}px down ` +
        `(off-screen) — hidden in lockstep`,
    );

    // 3. Scroll UP (still far from the top) → both bars return to baseline.
    await nudge(page, -160);
    const shown = await pollBars(page, "scroll-up show", bothVisible);
    const offsetMidShow = await page.evaluate(
      () => document.querySelector("[data-e2e-scroller]").scrollTop,
    );
    if (offsetMidShow < 40) {
      fail(
        `scroll-up assertion landed inside the near-top zone (scrollTop=${offsetMidShow}) — not a real mid-page scroll-up check`,
      );
    }
    log(
      `after scroll-up (scrollTop=${Math.round(offsetMidShow)}): ` +
        `menu chip back at ${shown.top}px, tab bar back at ${shown.bottom}px — both returned`,
    );

    // 4. Hide again, then return to the very top → visible; a small jiggle
    //    inside the <40px near-top zone must NOT hide them.
    await nudge(page, 240);
    await pollBars(page, "re-hide", (b) => topHidden(b) && bottomHidden(b));
    await scrollToStable(page, 0);
    await pollBars(page, "back-to-top show", bothVisible);
    log("back at the top: both bars visible again");

    // Jiggle within the near-top zone (offset stays < 40 → always visible).
    await page.evaluate(() => {
      const el = document.querySelector("[data-e2e-scroller]");
      el.scrollTop = 20;
    });
    await new Promise((r) => setTimeout(r, 600)); // give a wrong hide time to run
    const nearTop = await readBars(page);
    if (!bothVisible(nearTop)) {
      fail(
        `bars hid inside the <40px near-top zone: ${JSON.stringify(nearTop)}`,
      );
    }
    log("near-top jiggle (scrollTop=20): both bars stay pinned visible");

    await context.close();
    log(
      "PASS — top bar hides on scroll-down, returns on scroll-up, stays visible near the top, and the bottom tab bar moves in lockstep.",
    );
  } finally {
    await browser.close().catch(() => {});
  }
}

async function main() {
  const { acquireServer, stopExpo } = createExpoServerManager(log);
  const server = await acquireServer("topbar-autohide", process.env.APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
  }
  const appUrl = server.appUrl.endsWith("/") ? server.appUrl : server.appUrl + "/";
  try {
    await run(appUrl);
  } catch (err) {
    if (isTransientEnvError(err)) {
      skip(`transient environment error: ${err.message}`);
    }
    throw err;
  } finally {
    if (!server.explicit && server.child) stopExpo(server.child);
  }
  process.exit(0);
}

runHarness(main, {
  log,
  timeoutMs: 15 * 60_000,
  onError: (err) => fail(err?.stack || String(err)),
});
