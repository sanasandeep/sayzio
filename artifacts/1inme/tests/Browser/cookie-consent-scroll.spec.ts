import { expect, test } from "@playwright/test";

// Guards the task "Let visitors scroll marketing pages before accepting cookies".
//
// The bottom-pinned "bar" cookie-consent layouts (banner / inline / corner /
// pill) must NEVER lock page scrolling. A fresh visitor landing on a marketing
// page (Pricing/Features/etc.) with the consent bar showing should be able to
// keep reading — scroll freely — without first clicking "Accept all" /
// "Customize". Only the full-screen blocker layouts (modal / takeover) are
// allowed to hold the visitor on the prompt.
//
// The default layout is `banner` (see CookieConsentConfig::defaults), so a
// fresh context on /pricing renders the bottom bar. We assert the page scrolls
// while the bar is still on screen and that no scroll-locking inline overflow
// leaked onto <html>/<body>.

test.describe("cookie consent — bottom-bar does not lock scroll", () => {
  test("first-time visitor can scroll /pricing before dismissing the bar", async ({
    page,
  }) => {
    // Fresh context (default per-test) → no consent cookie → the prompt shows.
    await page.goto("/pricing");

    // The consent prompt must be present (this is the pre-condition the bug
    // report describes: consent visible, not yet dismissed).
    const host = page.locator(".cc-host");
    await host.waitFor({ state: "attached" });

    // This spec asserts the invariant for the bottom-pinned "bar" family
    // (banner / inline / corner / pill). The default layout is `banner`, but
    // an admin may have configured another bar layout — all must scroll freely.
    // The full-screen blocker layouts (modal / takeover) are *allowed* to lock
    // scroll, so if one is somehow configured here the invariant doesn't apply.
    const layout = await host.getAttribute("data-layout");
    const barLayouts = ["banner", "inline", "corner", "pill"];
    test.skip(
      !barLayouts.includes(layout ?? ""),
      `consent layout is "${layout}" (a full-screen blocker), scroll-lock is allowed`,
    );

    // A bar layout must not paint a full-screen click/scroll blocker.
    await expect(page.locator(".cc-backdrop")).toHaveCount(0);

    const before = await page.evaluate(() => ({
      scrollHeight: document.documentElement.scrollHeight,
      innerHeight: window.innerHeight,
      htmlOverflow: document.documentElement.style.overflow,
      bodyOverflow: document.body.style.overflow,
    }));
    // Sanity: the page must actually be taller than the viewport, otherwise a
    // "scroll worked" assertion would be meaningless.
    expect(before.scrollHeight).toBeGreaterThan(before.innerHeight + 200);
    // No scroll-lock inline overflow should have leaked onto the root/body.
    expect(before.htmlOverflow).not.toBe("hidden");
    expect(before.bodyOverflow).not.toBe("hidden");

    // Drive a real wheel scroll (the interaction a visitor would use) and a
    // programmatic scroll; both must move the page.
    await page.mouse.move(640, 360);
    await page.mouse.wheel(0, 1200);
    await page.waitForFunction(() => window.scrollY > 100);
    const wheelY = await page.evaluate(() => window.scrollY);
    expect(wheelY).toBeGreaterThan(100);

    await page.evaluate(() => window.scrollTo(0, 1500));
    await page.waitForFunction(() => window.scrollY > 1000);
    const toY = await page.evaluate(() => window.scrollY);
    expect(toY).toBeGreaterThan(1000);

    // The bar is still there (we never dismissed it) — scrolling did not force
    // a decision or hide the prompt.
    await expect(host).toHaveCount(1);
  });
});
