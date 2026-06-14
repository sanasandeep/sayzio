import { expect, test, type Page } from "@playwright/test";

// Extends the footer-gap guard (cookie-consent-footer-gap.spec.ts) beyond the
// default "banner" layout. Admins can configure the consent widget to use other
// display styles (corner, pill, inline, modal, takeover) and positions, each
// with its own footer-reserve / overlay behaviour driven by bottomPinned() /
// reserveVisible() / updateFooterReserve() in
// resources/views/common/partials/cookie-consent.blade.php. A spacing or
// visibility regression in one of those styles would otherwise go uncaught.
//
// The effective layout/position is baked into the page server-side as a JSON
// config blob (`@json($cfgJson)`), so rather than mutate the admin AppSetting we
// intercept the /pricing document response and rewrite the layout/position in
// that blob — exercising the real client-side reserve logic per style.
//
// Invariants pinned here:
//   - bottom-pinned styles (corner / pill at a bottom-* position) keep the
//     footer clear by reserving exactly the prompt's height — no phantom empty
//     band, mirroring the banner check.
//   - modal / takeover styles are full-screen blockers, NOT bottom-pinned, so
//     they must apply no body bottom-reserve at all.
//
// Runs against the Laravel app on :5000 — localhost:80 hits the Express
// api-server, not Laravel.

const APP_BASE = process.env.APP_URL || "http://localhost:5000";

/**
 * Intercept the /pricing document and rewrite the consent layout/position in
 * the server-rendered config blob. Must be installed before navigation.
 */
async function forceLayout(
  page: Page,
  layout: string,
  position: string,
): Promise<void> {
  await page.route(`${APP_BASE}/pricing`, async (route) => {
    if (route.request().resourceType() !== "document") {
      await route.continue();
      return;
    }
    const resp = await route.fetch();
    const headers = resp.headers();
    delete headers["content-length"];
    delete headers["content-encoding"];
    let body = await resp.text();
    body = body
      .replace(/"layout":"banner"/, `"layout":"${layout}"`)
      .replace(/"position":"bottom-center"/, `"position":"${position}"`);
    await route.fulfill({ status: resp.status(), headers, body });
  });
}

/** Geometry + visibility snapshot read in one pass so values can't drift. */
type Snapshot = {
  hasHost: boolean;
  layout: string;
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
      layout: host ? host.getAttribute("data-layout") || "" : "",
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

/** Wait until a bottom-pinned prompt is mounted and its reserve has settled. */
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

// ---- Bottom-pinned styles: corner + pill at a bottom-* position ----
for (const layout of ["corner", "pill"] as const) {
  test(`bottom-pinned ${layout} (bottom-right): footer clear, no phantom band`, async ({
    page,
  }) => {
    await forceLayout(page, layout, "bottom-right");

    // Fresh context (default per-test) → no consent cookie → prompt shows.
    await page.goto(`${APP_BASE}/pricing`);
    await waitForReserveSettled(page);

    // Scroll to the very bottom so the footer reaches its final resting
    // position beneath the reserved band.
    await page.evaluate(() =>
      window.scrollTo(0, document.documentElement.scrollHeight),
    );
    await page.waitForFunction(() => {
      const max = document.documentElement.scrollHeight - window.innerHeight;
      return Math.abs(window.scrollY - Math.max(0, max)) <= 2;
    });

    const s = await readSnapshot(page);

    // The host exists, reports the forced layout, and is genuinely VISIBLE —
    // the regression family this guards is an invisible host still reserving
    // space.
    expect(s.hasHost).toBe(true);
    expect(s.layout).toBe(layout);
    expect(s.opacity).toBe("1");
    expect(s.hostHeight).toBeGreaterThan(0);

    // The card is pinned to the bottom of the viewport: its bottom edge sits at
    // the viewport bottom.
    expect(Math.abs(s.hostRectBottom - s.innerHeight)).toBeLessThanOrEqual(2);

    // The reserved band below the footer equals the prompt height — the prompt
    // exactly covers the reserve, so there is no *visible* empty band.
    expect(Math.abs(s.gapBelowFooter - s.hostHeight)).toBeLessThanOrEqual(2);

    // Footer's bottom edge sits flush at the prompt's top edge: no daylight
    // between the end of the footer and the start of the prompt.
    expect(
      Math.abs(s.footerBottomViewport - s.hostRectTop),
    ).toBeLessThanOrEqual(2);
  });
}

// ---- Full-screen blockers: modal + takeover apply no bottom-reserve ----
for (const layout of ["modal", "takeover"] as const) {
  test(`${layout} layout: full-screen blocker applies no body bottom-reserve`, async ({
    page,
  }) => {
    // Position is irrelevant for modal/takeover (they're centered overlays),
    // but rewriting it keeps the blob consistent.
    await forceLayout(page, layout, "bottom-center");

    await page.goto(`${APP_BASE}/pricing`);
    // The prompt still mounts (it's a blocker), so wait for the host, then give
    // updateFooterReserve() a beat to run. The point is that it must NOT write a
    // reserve — bottomPinned() returns false for these layouts.
    await page.locator(".cc-host").waitFor({ state: "attached" });
    await page.waitForTimeout(500);

    const s = await readSnapshot(page);

    // Host is present and reports the forced full-screen layout…
    expect(s.hasHost).toBe(true);
    expect(s.layout).toBe(layout);

    // …but no body bottom-reserve was applied (these are not bottom-pinned).
    expect(s.inlinePaddingBottom).toBe("");
  });
}
