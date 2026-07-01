import { expect, test, type Page } from "@playwright/test";

// Regression guard for the shared marketing layout background (see repo memory
// `marketing-site-layout-bg-sticky.md`). The non-home marketing pages render
// through `public/layouts/site.blade.php`, which carries the home page's DEEP
// base (`--bg:#0a0a14` dark / `#f8fafc` light) plus a FIXED `.aurora` glow
// sitting behind everything at `z-index:-1`. Every content section is meant to
// be transparent or a semi-transparent glass card so the single aurora shows
// through seamlessly from the hero, through the mid sections, down to the
// footer.
//
// The failure this pins: a future page (or edit) reintroducing an OPAQUE,
// full-bleed hero/section backdrop (e.g. the retired flat `#1e2330`). An opaque
// band spanning the whole width paints over the fixed aurora just in that strip,
// producing a visible seam/band against the aurora-lit rest of the page — even
// if its colour happens to match the base, because it flattens the glow there.
// A code audit confirmed no page does this today; this headless spec makes the
// invariant self-checking so a regression is caught automatically.
//
// Detection is DOM-based (deterministic, fast, mode-agnostic): within `<main>`
// (the page content — the intentionally-opaque footer/header live outside it),
// no element that spans the full viewport width (touches both left and right
// edges) and is tall may have a FULLY OPAQUE background-colour. Centered glass
// cards never touch both edges (they sit inside padded max-width containers) and
// are semi-transparent (alpha < 1), so they are correctly ignored — only a
// reintroduced full-bleed opaque band trips it. Each page is checked in BOTH
// dark and light mode, and the sticky header is asserted to stay pinned on
// scroll.
//
// Runs against the Laravel app on :5000 — localhost:80 hits the Express
// api-server, not Laravel (see APP_URL override below).

const APP_BASE = process.env.APP_URL || "http://localhost:5000";
const CONSENT_COOKIE = "1inme_cookie_consent";

// Every page that `@extends('public.layouts.site')` and is reachable as a
// stable marketing route. `/ai-chatbot` renders the shared `ai-product` blade;
// `/for/creators` renders the `use-case` blade. [path, humanLabel].
const PAGES: ReadonlyArray<readonly [string, string]> = [
  ["/about", "about"],
  ["/analytics", "analytics"],
  ["/audience", "audience"],
  ["/how-it-works", "how-it-works"],
  ["/integrations", "integrations"],
  ["/forms", "forms"],
  ["/notifications", "notifications"],
  ["/buzz", "buzz"],
  ["/domains", "domains"],
  ["/discovery", "discovery"],
  ["/ai-chatbot", "ai-product"],
  ["/for/creators", "use-case"],
  ["/workspace-team", "workspace-team"],
  ["/contact", "contact"],
  ["/demos", "demos"],
  ["/features", "features"],
  ["/pricing", "pricing"],
] as const;

/**
 * Pre-seed a valid consent decision (policy version 1) so the cookie-consent
 * banner never mounts. This is the "already dismissed" state — the banner locks
 * page scroll while it is up (see memory note), so getting it out of the way is
 * what lets the sticky-nav scroll assertion move the page at all. Must run
 * before navigation.
 */
async function seedConsent(page: Page): Promise<void> {
  const decision = JSON.stringify({
    v: 1,
    t: Date.now(),
    c: { analytics: true, marketing: false, functional: false },
  });
  await page.context().addCookies([
    {
      name: CONSENT_COOKIE,
      value: encodeURIComponent(decision),
      domain: "localhost",
      path: "/",
    },
  ]);
}

/** Force the site into dark mode before any page script runs. */
async function preferDark(page: Page): Promise<void> {
  await page.addInitScript(() => {
    try {
      localStorage.setItem("1inme_theme", "dark");
    } catch (e) {
      /* ignore */
    }
  });
}

type OpaqueBand = {
  tag: string;
  cls: string;
  bg: string;
  top: number;
  height: number;
};

/**
 * Scan `<main>` for full-bleed, tall elements that carry a fully-opaque
 * background-colour — the signature of a reintroduced opaque hero/section band.
 * Returns the offenders (empty array = clean). Runs entirely in the page so it
 * reflects the live computed styles in whichever mode is active.
 */
async function findOpaqueBands(page: Page): Promise<OpaqueBand[]> {
  return page.evaluate(() => {
    const vw = window.innerWidth;
    const main = document.querySelector("main");
    if (!main) {
      return [
        { tag: "NO-MAIN", cls: "", bg: "", top: 0, height: 0 },
      ] as OpaqueBand[];
    }
    const out: OpaqueBand[] = [];
    const els = main.querySelectorAll<HTMLElement>("*");
    for (const el of els) {
      const rect = el.getBoundingClientRect();
      // Full-bleed = touches both the left and right viewport edges. A centered
      // max-width container/card always keeps a horizontal gutter, so it never
      // qualifies — only a true edge-to-edge band does.
      const fullBleed =
        rect.left <= 4 && rect.right >= vw - 4 && rect.width >= vw - 8;
      if (!fullBleed) continue;
      // Ignore thin full-width rules/dividers; a seam-forming band is tall.
      if (rect.height < 160) continue;
      const bg = getComputedStyle(el).backgroundColor;
      const m = bg.match(/rgba?\(([^)]+)\)/);
      if (!m) continue;
      const parts = m[1].split(",").map((s) => parseFloat(s.trim()));
      const alpha = parts.length >= 4 ? parts[3] : 1;
      // Transparent (alpha 0) and glass (alpha < 1) let the aurora through and
      // are fine. Only a fully-opaque full-bleed fill paints over the aurora.
      if (alpha >= 0.999) {
        out.push({
          tag: el.tagName,
          cls: (el.className && el.className.toString
            ? el.className.toString()
            : String(el.className || "")
          ).slice(0, 140),
          bg,
          top: Math.round(rect.top + window.scrollY),
          height: Math.round(rect.height),
        });
      }
    }
    return out;
  });
}

type StickyState = {
  found: boolean;
  scrollable: boolean;
  topBefore: number;
  topAfter: number;
  scrolledBy: number;
};

/**
 * Assert the sticky header stays pinned: record the nav's viewport-top before
 * and after scrolling. A correctly-pinned sticky nav keeps the same top; a
 * regression (e.g. the Alpine wrapper regaining a layout box and trapping the
 * sticky nav — see memory note) lets it scroll away.
 */
async function readStickyState(page: Page): Promise<StickyState> {
  // Ensure scroll is unlocked (no banner) and settle at the top first.
  await page.evaluate(() => window.scrollTo(0, 0));
  const before = await page.evaluate(() => {
    const navs = Array.from(document.querySelectorAll("nav"));
    const nav =
      navs.find((n) => getComputedStyle(n).position === "sticky") || navs[0];
    return {
      found: !!nav,
      top: nav ? nav.getBoundingClientRect().top : 0,
      maxScroll:
        document.documentElement.scrollHeight - window.innerHeight,
    };
  });

  const target = Math.min(1200, Math.max(0, before.maxScroll));
  await page.evaluate((y) => window.scrollTo(0, y), target);
  await page
    .waitForFunction(
      (y) => Math.abs(window.scrollY - y) <= 3,
      target,
      { timeout: 3000 },
    )
    .catch(() => {
      /* short page may not scroll; handled below */
    });

  const after = await page.evaluate(() => {
    const navs = Array.from(document.querySelectorAll("nav"));
    const nav =
      navs.find((n) => getComputedStyle(n).position === "sticky") || navs[0];
    return {
      top: nav ? nav.getBoundingClientRect().top : 0,
      scrollY: window.scrollY,
    };
  });

  return {
    found: before.found,
    scrollable: target > 20,
    topBefore: before.top,
    topAfter: after.top,
    scrolledBy: after.scrollY,
  };
}

test.describe("marketing layout — no opaque background seam, sticky nav pinned", () => {
  for (const [path, label] of PAGES) {
    test(`${label} (${path}): uniform aurora background + pinned nav in dark & light`, async ({
      page,
    }) => {
      await seedConsent(page);
      await preferDark(page);
      await page.goto(`${APP_BASE}${path}`, { waitUntil: "domcontentloaded" });
      // The sticky <nav> is the first thing the layout renders; wait for it so
      // computed-style reads below are stable.
      await page.locator("nav").first().waitFor({ state: "attached" });

      // Confirm we are genuinely in dark mode for the dark pass.
      const isDark = await page.evaluate(
        () => !document.documentElement.classList.contains("light-mode"),
      );
      expect(isDark, `${label} should start in dark mode`).toBe(true);

      // ---- Dark mode: no opaque full-bleed band in the content area. ----
      const darkBands = await findOpaqueBands(page);
      expect(
        darkBands,
        `${label} (dark) has an opaque full-bleed band that would seam against the aurora: ${JSON.stringify(
          darkBands,
        )}`,
      ).toEqual([]);

      // ---- Sticky header stays pinned on scroll (mode-independent). ----
      const sticky = await readStickyState(page);
      expect(sticky.found, `${label}: a nav element must exist`).toBe(true);
      if (sticky.scrollable) {
        expect(
          Math.abs(sticky.topAfter - sticky.topBefore),
          `${label}: sticky nav drifted on scroll (before=${sticky.topBefore}, after=${sticky.topAfter}, scrolledBy=${sticky.scrolledBy})`,
        ).toBeLessThanOrEqual(3);
      }

      // ---- Light mode: flip the real site switch, re-check for a band. ----
      await page.evaluate(() => window.scrollTo(0, 0));
      await page.waitForFunction(
        () =>
          typeof (window as unknown as { inmeToggleTheme?: unknown })
            .inmeToggleTheme === "function",
      );
      await page.evaluate(() =>
        (
          window as unknown as { inmeToggleTheme: () => boolean }
        ).inmeToggleTheme(),
      );
      await page.waitForFunction(() =>
        document.documentElement.classList.contains("light-mode"),
      );

      const lightBands = await findOpaqueBands(page);
      expect(
        lightBands,
        `${label} (light) has an opaque full-bleed band that would seam against the aurora: ${JSON.stringify(
          lightBands,
        )}`,
      ).toEqual([]);
    });
  }
});
