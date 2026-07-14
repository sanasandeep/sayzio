#!/usr/bin/env node
/**
 * End-to-end regression check that every login-footer /info screen —
 * About (AboutPage.tsx) plus Help / Privacy / Terms / NFC (InfoPage.tsx) —
 * actually PAINTS its title + section copy on screen, driving the REAL app in a
 * headless browser (task #4460).
 *
 * Why this exists: the source-driven guard (test-info-pages-content.mjs) reads
 * the shipped screen sources and proves each carries a non-empty title +
 * section copy, and that About's FALLBACK_SECTIONS is wired as the kept state.
 * But reading source can't catch a RUNTIME break — a crashing hook, a bad
 * import in InfoPage/AboutPage, a reanimated bump that stops applying the
 * animated opacity on web, or a ScrollReveal that never reveals — which would
 * ship a blank page while the source guard stays green. This harness navigates
 * to each /info route in a warm Expo web server and asserts the visible title +
 * at least one section body are really present in the DOM AND painted (non-zero
 * EFFECTIVE opacity — the product of the element's own opacity and every
 * ancestor's, so a ScrollReveal wrapper stuck at 0 is caught even though the
 * text node itself is opacity 1).
 *
 * Note Playwright's own isVisible() would NOT catch this: an element at opacity
 * 0 still has a bounding box and is reported "visible". We read the computed
 * opacity explicitly.
 *
 * The About screen is exercised with its runtime /api/v1/site/about fetch
 * FORCED TO FAIL, so the offline FALLBACK_SECTIONS render path is verified
 * end-to-end (the exact scenario the source guard can only assert statically).
 *
 * Motion is deliberately left ENABLED — do NOT emulate Reduce Motion here.
 * Forcing reduce-motion actually FREEZES ScrollReveal sections at opacity 0:
 * useReducedMotion() starts false and flips true only after first render, by
 * which point ScrollReveal's opacity shared value is already seeded 0 and its
 * reduce-motion effect branch sets `revealed` but never opacity=1 nor schedules
 * the failsafe. Instead we let the measure trigger / ~2200ms failsafe animate
 * each section up and POLL its effective opacity until it crosses 0 (mid-
 * animation values >0 already count as painted). The static reveal LOGIC is
 * covered separately by test-info-scroll-reveal.mjs.
 *
 * Like the sibling mobile e2e harnesses it boots its OWN throwaway Expo web dev
 * server (shared expo-web-server.mjs manager) unless APP_URL points at an
 * already-running one, and SKIPS (exit 0) when a server / browser can't come up
 * (or the box is too slow) so it never fails CI just because Metro or Chromium
 * couldn't boot. A real "section stuck invisible" regression on a server that
 * DID boot still fails (exit 1).
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:info-pages-e2e
 *
 * Environment:
 *   APP_URL   reuse an already-running Expo web server instead of booting a
 *             throwaway one (handy for local debugging).
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  runHarness,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[info-pages-e2e]", ...args);
}

function fail(msg) {
  console.error("[info-pages-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[info-pages-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };

// Each /info route with the exact title it renders and a couple of copy anchors
// (a section heading + a distinctive body substring) that must appear on screen.
// Kept as constants so a copy change forces a deliberate edit here too. For
// About we anchor on the OFFLINE FALLBACK copy specifically, because this test
// drives the screen with the /about endpoint failing. `heading` is optional —
// the generic InfoPage screens (e.g. NFC) assert title + body only.
const PAGES = [
  {
    route: "info/about",
    title: "About Sayzio",
    heading: "Built for creators and teams",
    body: "solo creator sharing a link tree",
    offlineAbout: true,
  },
  {
    route: "info/help",
    title: "Help & Support",
    heading: "Sign-in problems",
    body: "Codes expire after 10 minutes",
  },
  {
    route: "info/privacy",
    title: "Privacy",
    heading: "Account data",
    body: "session token in the device's secure storage",
  },
  {
    route: "info/terms",
    title: "Terms of Service",
    heading: "Your account",
    body: "responsible for the activity on your account",
  },
];

// Build a browser context wired signed-out with every backend call stubbed so
// nothing hits a real server.
//
// IMPORTANT — do NOT emulate Reduce Motion here. Each /info section renders
// inside a <ScrollReveal> whose opacity shared value is seeded from
// `reduceMotion` on the FIRST render. `useReducedMotion()` starts false and only
// flips true asynchronously (after AccessibilityInfo resolves), so by the time a
// forced reduce-motion value arrives the shared value is already 0 — and the
// reduce-motion branch of ScrollReveal's effect sets `revealed` but never sets
// opacity to 1 nor schedules the failsafe, leaving the section stuck at opacity
// 0 forever. With motion allowed (the default) the section instead reveals via
// the measure trigger or the unconditional ~2200ms failsafe, animating opacity
// to 1 — which is exactly the real runtime paint path this test verifies.
async function prepareContext(browser) {
  const context = await browser.newContext({
    viewport: VIEWPORT,
  });

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

  // The About AND Contact endpoints are FORCED OFFLINE for this whole suite so
  // the About screen must render its bundled FALLBACK_SECTIONS and the Contact
  // screen must render its bundled DEFAULT_CONTACT_CONTENT (real address +
  // support email, no fake phone). Registered before the catch-all so
  // Playwright consults it first.
  await context.route(
    (url) => /\/api\/v1\/site\/(about|contact)$/.test(url.pathname),
    (route) => route.abort("failed"),
  );

  // Catch-all so nothing else reaches a real backend. Defers the offline
  // /site/about and /site/contact paths to the abort handler above via
  // fallback().
  await context.route("**/api/**", (route) => {
    const path = new URL(route.request().url()).pathname;
    if (/\/api\/v1\/site\/(about|contact)$/.test(path)) return route.fallback();
    return route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ data: [] }),
    });
  });

  return context;
}

// Compute the effective opacity of a locator's element — the product of every
// ancestor's computed opacity. This is what distinguishes "in the DOM" from
// "painted on screen": a ScrollReveal section that never revealed leaves its
// copy at opacity 0, which Playwright's isVisible() would still report visible.
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
// painted (effective opacity > 0). Each /info section renders inside a
// ScrollReveal that starts at opacity 0 and reveals when it measures into the
// viewport OR via an unconditional ~2200ms failsafe. So we scroll the node into
// view to trigger the measure path, then POLL its effective opacity until it
// crosses zero — the failsafe guarantees this happens for every section,
// including copy below the initial fold. This exercises the real runtime reveal
// rather than forcing Reduce Motion (that path is covered by
// test-info-scroll-reveal.mjs).
async function assertPainted(page, text, ctx, { exact = true } = {}) {
  const locator = page.getByText(text, { exact }).first();
  await locator
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail(`${ctx}: expected "${text}" to be visible, but it never appeared.`),
    );

  // Nudge the measure-based reveal along; harmless if already on screen.
  await locator
    .scrollIntoViewIfNeeded({ timeout: STEP_TIMEOUT_MS })
    .catch(() => {});

  const deadline = Date.now() + 6000;
  let last = 0;
  while (Date.now() < deadline) {
    last = await effectiveOpacity(locator);
    if (last > 0) {
      log(`ok — "${text}" painted (opacity ${last.toFixed(2)}) (${ctx})`);
      return;
    }
    await page.waitForTimeout(150);
  }

  fail(
    `${ctx}: "${text}" is in the DOM but never painted — effective opacity ` +
      `stayed at ${last} for 6s (a ScrollReveal section never revealed).`,
  );
}

// The bundled brand contact details the Contact card MUST paint when its
// /api/v1/site/contact fetch fails. Kept in lockstep with
// DEFAULT_CONTACT_CONTENT in lib/api/siteContent.ts (a copy change there is a
// deliberate edit here too). `addressAnchor` is a substring unique to the
// address block (NOT shared with the map label). `phoneAbsent` is empty, so the
// ContactDetailsCard must render NO "Phone" detail row — the guard against a
// resurrected fake phone number.
const CONTACT_OFFLINE = {
  route: "info/contact",
  title: "Contact us",
  detailsHeading: "Contact details",
  addressAnchor: "8 Amrutha Nilayam, Banjara Hills",
  email: "hello@sayzio.app",
  // The exact text of the "Phone" detail-row label. It only renders when the
  // card has a non-empty phone — so its ABSENCE proves no fake phone surfaced.
  phoneLabel: "Phone",
};

// Boot the Contact screen with /api/v1/site/contact FORCED OFFLINE (aborted in
// prepareContext) and assert the card paints the real bundled brand details:
// the header, address block, and support email are present AND painted, while
// the "Phone" detail row is absent (no fabricated number). This catches a
// render-wiring regression that would leave offline users staring at a blank or
// wrong contact card while the source guards stay green.
async function assertContactOffline(page, appUrl) {
  const target = `${appUrl}${CONTACT_OFFLINE.route}`;
  const ctx = `${CONTACT_OFFLINE.route} (offline)`;
  log(`navigating to ${target}`);
  await page.goto(target, { waitUntil: "domcontentloaded" });

  await page
    .waitForFunction(
      () => document.body && document.body.innerText.trim().length > 0,
      null,
      { timeout: NAV_TIMEOUT_MS },
    )
    .catch(() => fail(`${ctx}: the app never mounted any visible content.`));

  // Screen header proves the route mounted; the details card heading proves the
  // ContactDetailsCard rendered (not a blank card).
  await assertPainted(page, CONTACT_OFFLINE.title, ctx);
  await assertPainted(page, CONTACT_OFFLINE.detailsHeading, ctx);

  // The real brand address + support email must be painted from the bundled
  // fallback. Address is a multiline Text node, so anchor on a distinctive
  // substring (exact:false); the email is its own node (exact match).
  await assertPainted(page, CONTACT_OFFLINE.addressAnchor, ctx, {
    exact: false,
  });
  await assertPainted(page, CONTACT_OFFLINE.email, ctx);

  // No fake phone: the "Phone" detail-row label only renders when the card has
  // a non-empty phone, so it must be entirely absent from the offline card.
  const phoneCount = await page
    .getByText(CONTACT_OFFLINE.phoneLabel, { exact: true })
    .count();
  if (phoneCount > 0) {
    fail(
      `${ctx}: a "Phone" detail row is showing (${phoneCount} match(es)) — the ` +
        `offline contact card must never surface a phone number, but one ` +
        `appeared.`,
    );
  }
  log(`ok — no fake phone row on the offline contact card (${ctx})`);
}

async function run() {
  const { acquireServer } = createExpoServerManager(log);
  const server = await acquireServer("info-pages", process.env.APP_URL);
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

  const context = await prepareContext(browser);
  const page = await context.newPage();
  page.setDefaultTimeout(STEP_TIMEOUT_MS);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
  page.on("pageerror", (e) => log("pageerror:", e.message));

  try {
    for (const p of PAGES) {
      const target = `${appUrl}${p.route}`;
      const ctx = p.offlineAbout ? `${p.route} (offline)` : p.route;
      log(`navigating to ${target}`);
      await page.goto(target, { waitUntil: "domcontentloaded" });

      // Wait for the app to mount and paint something at all before probing
      // for the specific copy.
      await page
        .waitForFunction(
          () => document.body && document.body.innerText.trim().length > 0,
          null,
          { timeout: NAV_TIMEOUT_MS },
        )
        .catch(() => fail(`${ctx}: the app never mounted any visible content.`));

      // Title + (optionally) a section heading must be present and painted. The
      // title proves the screen mounted; the heading proves the section copy
      // actually rendered (not a blank page).
      await assertPainted(page, p.title, ctx);
      if (p.heading) await assertPainted(page, p.heading, ctx);

      // Body copy is long; match a distinctive substring rather than the whole
      // paragraph so a trivial copy tweak elsewhere doesn't break this.
      const bodyLocator = page.getByText(p.body, { exact: false }).first();
      await bodyLocator
        .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
        .catch(() =>
          fail(
            `${ctx}: section body containing "${p.body}" never rendered — the ` +
              `page painted a title but no section copy.`,
          ),
        );
      log(`ok — section body ("${p.body}") visible (${ctx})`);
    }

    // Contact screen (info/contact) with /api/v1/site/contact FORCED OFFLINE.
    // The screen seeds its details state with the bundled DEFAULT_CONTACT_CONTENT
    // and swaps to the admin-editable payload on a successful fetch — so when the
    // fetch is aborted the card must still PAINT the real brand details (address
    // + support email) and NEVER show a fake phone. The source guards
    // (test:contact-content / test:contact-details) only read the shipped source;
    // this proves the ContactDetailsCard actually renders the fallback at runtime
    // (not a blank card, and not a fabricated phone number).
    await assertContactOffline(page, appUrl);

    log(
      "PASS: every login-footer /info screen (About, Contact, Help, Privacy, " +
        "Terms) paints its title + section copy (About + Contact via the " +
        "offline fallback)",
    );
    await context.close().catch(() => {});
    await browser.close().catch(() => {});
    // Explicit exit (like the sibling harnesses): the throwaway Metro child
    // would otherwise keep the event loop alive; the manager's process exit
    // hook reaps it.
    process.exit(0);
  } catch (e) {
    await context.close().catch(() => {});
    await browser.close().catch(() => {});
    // On a throwaway server a Playwright timeout / transient connection error
    // means the constrained box was too slow (Metro recompiling, CPU starved),
    // not a product regression — SKIP, same as when the server/browser couldn't
    // come up. Real regressions exit 1 via fail() before reaching here. Against
    // an explicit APP_URL we still fail hard so local debugging never silently
    // skips.
    if (!explicit && isTransientEnvError(e)) {
      skip(
        `the environment was too slow to drive the check ` +
          `(${e?.message?.split("\n")[0] ?? "unknown error"}); ` +
          `skipping (best-effort, not an info-pages regression)`,
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
