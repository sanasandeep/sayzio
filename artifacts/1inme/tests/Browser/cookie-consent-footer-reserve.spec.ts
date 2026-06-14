import { expect, test, type Page } from "@playwright/test";

// Guards the cookie-consent footer-reserve invariant (task: "Guard against the
// cookie banner growing too tall again"). The banner is fixed to the bottom of
// the viewport, so the page reserves an equal band of bottom padding on
// `document.body` to keep the footer scrolled clear of (and clickable beneath)
// the card. A regression once made the *collapsed* category list lay out inside
// the banner, ballooning the reserve to ~416px while the visible prompt was only
// ~154px. These tests assert the reserve tracks the *visible* prompt height —
// for a first-time visitor, after the in-place "Customize" expansion, and that
// a returning visitor (consent already given) gets no reserve at all.

const CONSENT_COOKIE = "1inme_cookie_consent";

/** Geometry snapshot read in one pass so the values can't drift between reads. */
type Geometry = {
  hasPrompt: boolean;
  promptHeight: number;
  paddingBottom: number;
  footerBottomAbs: number;
  docHeight: number;
};

async function readGeometry(page: Page): Promise<Geometry> {
  return page.evaluate(() => {
    const host = document.querySelector(".cc-host");
    const footer = document.querySelector("footer");
    const footerRect = footer?.getBoundingClientRect();
    return {
      hasPrompt: !!host,
      promptHeight: host ? host.getBoundingClientRect().height : 0,
      paddingBottom: parseFloat(
        getComputedStyle(document.body).paddingBottom || "0",
      ),
      footerBottomAbs: footerRect ? footerRect.bottom + window.scrollY : 0,
      docHeight: document.documentElement.scrollHeight,
    };
  });
}

/** Wait until the bottom-pinned banner is mounted and its reserve has settled. */
async function waitForReserveSettled(page: Page): Promise<void> {
  await page.locator(".cc-host").waitFor({ state: "attached" });
  // The reserve is written inside requestAnimationFrame after the card mounts;
  // wait for body padding to match the prompt's measured height (± rounding).
  await page.waitForFunction(() => {
    const host = document.querySelector(".cc-host");
    if (!host) return false;
    const h = host.getBoundingClientRect().height;
    const pad = parseFloat(getComputedStyle(document.body).paddingBottom || "0");
    return h > 0 && Math.abs(pad - Math.ceil(h)) <= 2;
  });
}

test.describe("cookie consent — footer reserve invariant", () => {
  test("first-time visitor: reserve matches the visible prompt height", async ({
    page,
  }) => {
    // Fresh context (default per-test) → no consent cookie → banner shows.
    await page.goto("/contact");
    await waitForReserveSettled(page);

    const g = await readGeometry(page);
    expect(g.hasPrompt).toBe(true);

    // The reserve must equal the *visible* prompt height (the bug reserved
    // ~416px for a ~154px prompt). Small tolerance for sub-pixel rounding.
    expect(Math.abs(g.paddingBottom - g.promptHeight)).toBeLessThanOrEqual(2);

    // Sanity: the collapsed prompt is modest — nowhere near the ballooned
    // ~416px the regression produced. This pins the failure mode directly.
    expect(g.promptHeight).toBeLessThan(320);

    // The footer is held clear of the fixed card: there is a bottom band
    // (the reserve) below the footer equal to the prompt height.
    expect(g.docHeight - g.footerBottomAbs).toBeGreaterThanOrEqual(
      g.promptHeight - 2,
    );
  });

  test('"Customize" expansion grows the reserve to keep the footer clear', async ({
    page,
  }) => {
    await page.goto("/contact");
    await waitForReserveSettled(page);

    const collapsed = await readGeometry(page);

    // Reveal the category list in place (banner layout expands, no re-render).
    await page.locator('.cc-host [data-act="customize"]').click();
    await expect(page.locator(".cc-host .cc-cats")).toBeVisible();

    // Reserve re-measures after the prompt grows; wait for it to catch up.
    await page.waitForFunction((prev) => {
      const host = document.querySelector(".cc-host");
      if (!host) return false;
      const h = host.getBoundingClientRect().height;
      const pad = parseFloat(
        getComputedStyle(document.body).paddingBottom || "0",
      );
      return h > prev && Math.abs(pad - Math.ceil(h)) <= 2;
    }, collapsed.promptHeight);

    const expanded = await readGeometry(page);

    // Prompt genuinely grew once the categories are visible…
    expect(expanded.promptHeight).toBeGreaterThan(collapsed.promptHeight);
    // …and the reserve grew in lockstep to match the new visible height.
    expect(
      Math.abs(expanded.paddingBottom - expanded.promptHeight),
    ).toBeLessThanOrEqual(2);
    // Footer still sits clear of the (now taller) card.
    expect(expanded.docHeight - expanded.footerBottomAbs).toBeGreaterThanOrEqual(
      expanded.promptHeight - 2,
    );
  });

  test("returning visitor: no prompt, no reserve, footer flush at bottom", async ({
    page,
  }) => {
    // Seed a valid consent decision for the current policy version so the
    // banner stays hidden (readDecision() requires v === policyVersion === 1).
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

    await page.goto("/contact");
    // Give the consent script a chance to (not) mount the banner.
    await page.waitForLoadState("networkidle");

    const g = await readGeometry(page);
    expect(g.hasPrompt).toBe(false);

    // No banner → no reserve.
    expect(g.paddingBottom).toBe(0);
    // Footer is flush with the bottom of the document — no empty band below it.
    expect(Math.abs(g.docHeight - g.footerBottomAbs)).toBeLessThanOrEqual(2);
  });
});
