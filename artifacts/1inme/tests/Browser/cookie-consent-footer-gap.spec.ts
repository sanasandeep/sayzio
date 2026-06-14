import { expect, test, type Page } from "@playwright/test";

// Guards against the "empty band below the footer" regression (twice-seen) on
// public marketing pages. Root cause: the dynamically-injected cookie-consent
// host was rendered invisible (opacity:0) by the marketing reveal stylesheet
// (/css/marketing-anim.css, which sets `[data-anim]{opacity:0}` until an
// IntersectionObserver adds `.in-view`) while still reserving footer space — so
// the page reserved a bottom band for a prompt nobody could see. The host now
// uses a namespaced `data-cc-anim` attribute to dodge that rule. These tests pin
// the invariant: for a first-time visitor the host is actually VISIBLE
// (opacity 1, non-zero height) and the footer sits flush at the banner's top
// (the reserve band is exactly covered by the banner — no phantom gap); for a
// returning visitor there is no host and no leftover bottom padding.
//
// Runs against the Laravel app on :5000 — localhost:80 hits the Express
// api-server, not Laravel (see APP_URL override below).

const APP_BASE = process.env.APP_URL || "http://localhost:5000";
const CONSENT_COOKIE = "1inme_cookie_consent";

/** Geometry + visibility snapshot read in one pass so values can't drift. */
type Snapshot = {
  hasHost: boolean;
  opacity: string;
  hostHeight: number;
  hostRectTop: number;
  hostRectBottom: number;
  footerBottomViewport: number;
  gapBelowFooter: number;
  innerHeight: number;
  inlinePaddingBottom: string;
};

async function readSnapshot(page: Page): Promise<Snapshot> {
  return page.evaluate(() => {
    const host = document.querySelector(".cc-host");
    const footer = document.querySelector("footer");
    const hostRect = host?.getBoundingClientRect();
    const footerRect = footer?.getBoundingClientRect();
    return {
      hasHost: !!host,
      opacity: host ? getComputedStyle(host).opacity : "",
      hostHeight: hostRect ? hostRect.height : 0,
      hostRectTop: hostRect ? hostRect.top : 0,
      hostRectBottom: hostRect ? hostRect.bottom : 0,
      footerBottomViewport: footerRect ? footerRect.bottom : 0,
      gapBelowFooter: footerRect
        ? document.documentElement.scrollHeight -
          (footerRect.bottom + window.scrollY)
        : 0,
      innerHeight: window.innerHeight,
      // Read the inline style (what the consent script writes), not the
      // computed value — the invariant is "no reserve was applied".
      inlinePaddingBottom: document.body.style.paddingBottom,
    };
  });
}

/** Wait until the bottom-pinned banner is mounted and its reserve has settled. */
async function waitForReserveSettled(page: Page): Promise<void> {
  await page.locator(".cc-host").waitFor({ state: "attached" });
  await page.waitForFunction(() => {
    const host = document.querySelector(".cc-host");
    if (!host) return false;
    const h = host.getBoundingClientRect().height;
    const pad = parseFloat(getComputedStyle(document.body).paddingBottom || "0");
    return h > 0 && Math.abs(pad - Math.ceil(h)) <= 2;
  });
}

test.describe("cookie consent — no phantom gap below the footer", () => {
  test("first-time visitor on /pricing: banner is visible and footer sits flush at its top", async ({
    page,
  }) => {
    // Fresh context (default per-test) → no consent cookie → banner shows.
    await page.goto(`${APP_BASE}/pricing`);
    await waitForReserveSettled(page);

    // Scroll to the very bottom so the footer is at its final resting position
    // and the fixed banner overlaps the reserved band.
    await page.evaluate(() =>
      window.scrollTo(0, document.documentElement.scrollHeight),
    );
    await page.waitForFunction(() => {
      const max = document.documentElement.scrollHeight - window.innerHeight;
      return Math.abs(window.scrollY - Math.max(0, max)) <= 2;
    });

    const s = await readSnapshot(page);

    // The host exists and is genuinely VISIBLE — the exact failure mode of the
    // regression was a host stuck at opacity:0 (invisible) yet reserving space.
    expect(s.hasHost).toBe(true);
    expect(s.opacity).toBe("1");
    expect(s.hostHeight).toBeGreaterThan(0);

    // The banner is pinned to the bottom of the viewport, covering the reserved
    // band: its bottom edge is at the viewport bottom and its top edge is one
    // banner-height up.
    expect(Math.abs(s.hostRectBottom - s.innerHeight)).toBeLessThanOrEqual(2);
    expect(
      Math.abs(s.hostRectTop - (s.innerHeight - s.hostHeight)),
    ).toBeLessThanOrEqual(2);

    // The reserved band below the footer equals the banner height — i.e. there
    // is no *visible* empty band, the banner exactly covers the reserve.
    expect(Math.abs(s.gapBelowFooter - s.hostHeight)).toBeLessThanOrEqual(2);

    // Footer's bottom edge sits flush at the banner's top edge: no daylight
    // between the end of the footer and the start of the banner.
    expect(
      Math.abs(s.footerBottomViewport - s.hostRectTop),
    ).toBeLessThanOrEqual(2);
  });

  test("returning visitor: no host, no leftover bottom padding", async ({
    page,
  }) => {
    // Seed a valid consent decision for the current policy version (1) so the
    // banner stays hidden (readDecision() requires v === policyVersion).
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

    await page.goto(`${APP_BASE}/pricing`, { waitUntil: "domcontentloaded" });
    // The consent module initialises on DOMContentLoaded; wait for it to be
    // present, then give it a beat to (decline to) mount the banner. We can't
    // wait on the banner appearing because the whole point is that it must not.
    await page.waitForFunction(
      () => !!(window as unknown as { __cookieConsent?: unknown }).__cookieConsent,
    );
    await page.waitForTimeout(500);

    const s = await readSnapshot(page);
    expect(s.hasHost).toBe(false);
    // No banner → the consent script must not have written any bottom reserve.
    expect(s.inlinePaddingBottom).toBe("");
  });
});
