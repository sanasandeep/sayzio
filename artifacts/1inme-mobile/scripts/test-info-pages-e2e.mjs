#!/usr/bin/env node
/**
 * End-to-end regression gate that every /info/* screen — help, terms, privacy,
 * nfc (all InfoPage.tsx) and About (AboutPage.tsx) — actually RENDERS VISIBLE
 * body copy after the scroll-reveal entrance-animation change, driving the REAL
 * app in a headless browser.
 *
 * Why this exists: each of these screens wraps its content in the shared
 * <ScrollReveal> primitive (components/ScrollReveal.tsx), which starts every
 * section at opacity 0 and only animates it to 1 once the section measures
 * near/inside the viewport (or a failsafe timer fires). The existing
 * test-info-scroll-reveal.mjs guard is source-driven — it lifts the reveal
 * logic out of ScrollReveal.tsx and proves the failsafe ships, but it NEVER
 * boots the app. An integration-level break the unit test can't see —
 * a reanimated version bump that stops applying the animated opacity on web, a
 * layout change that breaks measurement, or a router/mount regression on the
 * /info/* routes — would leave a section stuck permanently invisible while the
 * unit test stays green. This harness catches exactly that: it opens each
 * screen in a running Expo web build and asserts the rendered copy has an
 * EFFECTIVE opacity > 0 (the product of the element's own opacity and every
 * ancestor's, so a ScrollReveal wrapper stuck at 0 is caught even though the
 * text node itself is opacity 1).
 *
 * Note Playwright's own isVisible() would NOT catch this: an element at
 * opacity 0 still has a bounding box and is reported "visible". We must read
 * the computed opacity explicitly.
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
const MOCK_TOKEN = "e2e-info-pages-token";
const MOCK_USER = {
  id: 71,
  display_name: "Info Pages Tester",
  email: "info-pages@example.com",
};

// Each /info/* screen, with body copy that is rendered INSIDE a <ScrollReveal>
// (so its effective opacity proves the reveal fired). `title` doubles as the
// navigation-header title, so we assert on section body text — copy that only
// appears in the scroll-revealed body, never the header — to prove the actual
// content became visible. About's "Link in Bio" is a hard-coded feature card
// (AboutPage.tsx), independent of the backend /about content, so it's stable
// even with the API stubbed to empty.
const PAGES = [
  {
    route: "info/help",
    title: "Help & Support",
    body: "Sign-in problems",
  },
  {
    route: "info/terms",
    title: "Terms of Service",
    body: "Acceptable use",
  },
  {
    route: "info/privacy",
    title: "Privacy",
    body: "Account data",
  },
  {
    route: "info/nfc",
    title: "How NFC works",
    body: "What gets written",
  },
  {
    route: "info/about",
    title: "About Sayzio",
    body: "Link in Bio",
  },
];

// Effective opacity of the deepest element whose trimmed textContent EXACTLY
// equals `target`, multiplied up the ancestor chain to the document root. This
// is the whole point of the check: react-native-reanimated paints the reveal by
// animating the <ScrollReveal> wrapper's opacity, so a section stuck at 0 shows
// as effectiveOpacity 0 even though the text node's own opacity is 1.
async function effectiveOpacity(page, target) {
  return page.evaluate((text) => {
    // Document order is pre-order, so a matching descendant is visited AFTER
    // its matching ancestors; keeping the last match yields the deepest (leaf)
    // element that holds exactly this string.
    let node = null;
    for (const el of document.querySelectorAll("*")) {
      if ((el.textContent || "").trim() === text) node = el;
    }
    if (!node) return { found: false, opacity: null };
    let eff = 1;
    let cur = node;
    while (cur && cur !== document.documentElement) {
      const o = parseFloat(getComputedStyle(cur).opacity);
      if (!Number.isNaN(o)) eff *= o;
      cur = cur.parentElement;
    }
    return { found: true, opacity: eff };
  }, target);
}

// Poll until the target copy is present AND its effective opacity clears the
// threshold, or the deadline passes. The generous window absorbs the reveal
// animation (≈480ms) and, worst case, the 2200ms failsafe timer that guarantees
// visibility when measurement never fires.
async function waitForVisibleOpacity(page, target, deadlineMs = 8000) {
  const deadlineAt = Date.now() + deadlineMs;
  let last = { found: false, opacity: null };
  while (Date.now() < deadlineAt) {
    last = await effectiveOpacity(page, target);
    if (last.found && last.opacity > 0.05) return last;
    await page.waitForTimeout(150);
  }
  return last;
}

async function checkPage(context, appUrl, spec) {
  const page = await context.newPage();
  page.setDefaultTimeout(STEP_TIMEOUT_MS);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
  page.on("pageerror", (e) => log(`[${spec.route}] pageerror:`, e.message));

  try {
    const url = appUrl + spec.route;
    log(`[${spec.route}] navigating to ${url}`);
    await page.goto(url, { waitUntil: "domcontentloaded" });

    // Wait for the screen to mount — the body copy must appear as a DOM node
    // first (it may still be mid-fade). getByText proves it's in the tree; the
    // opacity poll below proves it's actually visible.
    await page
      .getByText(spec.body, { exact: true })
      .first()
      .waitFor({ state: "attached", timeout: STEP_TIMEOUT_MS })
      .catch(() =>
        fail(
          `[${spec.route}] the body copy "${spec.body}" never mounted — the ` +
            `screen failed to render (routing/mount regression).`,
        ),
      );

    const res = await waitForVisibleOpacity(page, spec.body);
    if (!res.found) {
      fail(
        `[${spec.route}] could not locate the body copy "${spec.body}" to ` +
          `measure its opacity.`,
      );
    }
    if (!(res.opacity > 0.05)) {
      fail(
        `[${spec.route}] the body copy "${spec.body}" is stuck at effective ` +
          `opacity ${res.opacity} — the ScrollReveal entrance animation never ` +
          `revealed it, so the screen ships blank/invisible content.`,
      );
    }
    log(
      `[${spec.route}] PASS: "${spec.body}" visible ` +
        `(effective opacity ${res.opacity.toFixed(3)})`,
    );
  } finally {
    await page.close().catch(() => {});
  }
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

  // Boot signed in (onboarding complete + token + user) so no splash/onboarding/
  // login gate can intercept a direct deep link into /info/*. The screens are
  // reachable both signed-in (Profile) and signed-out (login footer); a signed-in
  // seed is simply the most permissive. Every /api/** call is stubbed with a
  // benign empty envelope so nothing reaches a real backend — About then falls
  // back to its bundled copy.
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
  await context.route("**/api/**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ data: [] }),
    }),
  );

  try {
    for (const spec of PAGES) {
      await checkPage(context, appUrl, spec);
    }
    log(
      "PASS: every /info/* screen (help, terms, privacy, nfc) and About " +
        "renders its body copy with a real, non-zero effective opacity — the " +
        "scroll-reveal animation never leaves content stuck invisible.",
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

run().catch((e) => {
  failOrSkipInfra(e, Boolean(process.env.APP_URL));
});
