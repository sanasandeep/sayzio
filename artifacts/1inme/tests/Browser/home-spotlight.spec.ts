import { expect, test, type Page } from "@playwright/test";

// Guards the home page's "What you can create" spotlight (task: add e2e
// coverage for the chip rail + rotating stage). The spotlight is a single
// Alpine component (`.lt-spotlight` in home.blade.php): a horizontal chip rail
// (role=tablist) where each chip `pick(i)`s a link type, an info zone whose
// `.lt-pane`s cross-fade via `.lt-pane-on`, and a setInterval auto-rotation
// that advances `active` every 4.2s unless paused (hover / recent pick /
// prefers-reduced-motion).
//
// A regression in the Alpine wiring (chip @click, the :class pane bindings, or
// the init() interval) would silently freeze the whole section; these tests
// pin all three behaviours.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points
// it at its ephemeral server, since localhost:80 hits the Express api-server).

const CONSENT_COOKIE = "1inme_cookie_consent";

/**
 * Load the home page with cookie consent pre-granted (so the bottom-pinned
 * banner can't intercept clicks) and wait for Alpine + the spotlight mount.
 * The default Playwright context reports prefers-reduced-motion:no-preference,
 * which is what the auto-rotation test relies on (init() skips the interval
 * under reduce).
 */
async function gotoSpotlight(page: Page): Promise<void> {
  await page.context().addCookies([
    {
      name: CONSENT_COOKIE,
      value: encodeURIComponent(
        JSON.stringify({
          v: 1,
          t: Date.now(),
          c: { analytics: false, marketing: false, functional: false },
        }),
      ),
      domain: "localhost",
      path: "/",
    },
  ]);
  // The home page is heavy (maps, embeds); don't wait for full network idle.
  await page.goto("/", { waitUntil: "domcontentloaded" });
  await page.waitForFunction(
    () => !!(window as unknown as { Alpine?: unknown }).Alpine,
  );
  await page.locator(".lt-spotlight").waitFor({ state: "attached" });
  await page.locator(".lt-spotlight").scrollIntoViewIfNeeded();
}

/** The currently visible info pane (exactly one carries .lt-pane-on). */
function activePaneName(page: Page) {
  return page.locator(".lt-pane.lt-pane-on .lt-pane-name");
}

test.describe("home 'What you can create' spotlight", () => {
  test.beforeEach(async () => {
    // Distant RDS makes the first paint slow; give each test headroom.
    test.setTimeout(90_000);
  });

  test("the chip rail renders at least 10 chips and one active pane", async ({
    page,
  }) => {
    await gotoSpotlight(page);

    const chips = page.locator(".lt-rail .lt-chip");
    expect(await chips.count()).toBeGreaterThanOrEqual(10);

    // Exactly one pane is active at a time, and it matches the first chip
    // (active starts at 0).
    await expect(page.locator(".lt-pane.lt-pane-on")).toHaveCount(1);
    const firstChipName = (
      await chips.first().locator("span").nth(1).innerText()
    ).trim();
    await expect(activePaneName(page)).toHaveText(firstChipName);
  });

  test("clicking a chip activates its stage pane", async ({ page }) => {
    await gotoSpotlight(page);

    const chips = page.locator(".lt-rail .lt-chip");
    const count = await chips.count();
    expect(count).toBeGreaterThanOrEqual(10);

    // Pick a chip well away from index 0 so the assertion can't pass by
    // accident. `pick(i)` also pauses auto-rotation for 9s, so the pane we
    // assert on can't be advanced out from under us mid-check.
    const target = chips.nth(5);
    const targetName = (await target.locator("span").nth(1).innerText()).trim();
    await target.click();

    await expect(target).toHaveClass(/lt-chip-on/);
    await expect(page.locator(".lt-pane.lt-pane-on")).toHaveCount(1);
    await expect(activePaneName(page)).toHaveText(targetName);

    // Clicking another chip moves the stage again — the binding is live, not a
    // one-shot.
    const other = chips.nth(2);
    const otherName = (await other.locator("span").nth(1).innerText()).trim();
    await other.click();
    await expect(activePaneName(page)).toHaveText(otherName);
  });

  test("the stage auto-advances after ~5s without interaction", async ({
    page,
  }) => {
    await gotoSpotlight(page);

    const before = (await activePaneName(page).innerText()).trim();

    // Auto-rotation ticks every 4.2s (init() interval). Don't hover the
    // component (mouseenter pauses it) — the default mouse position stays at
    // (0,0), so the interval keeps running. Poll for the active pane to move
    // on; one tick lands within ~5s, but allow slack for a loaded CI box.
    await expect
      .poll(async () => (await activePaneName(page).innerText()).trim(), {
        timeout: 15_000,
      })
      .not.toBe(before);

    // Still exactly one pane visible after the transition.
    await expect(page.locator(".lt-pane.lt-pane-on")).toHaveCount(1);
  });
});
