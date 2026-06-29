import { expect, test, type Locator, type Page } from "@playwright/test";

// Guards the home hero's orbital tool nodes (task: "Add a browser test that the
// hero tool popovers open and close"). The hero gained an interactive ring of
// feature nodes around Zio: clicking a node opens a small glass popover with a
// title + one-line description; only one popover is open at a time; it dismisses
// via re-click on the same node, clicking another node, an outside click, or
// Escape. The orbit pauses while a popover is open. This Alpine-driven
// interaction (`x-data="{ open: null }"` on `.zio-orbit`) had no automated
// coverage; these tests pin the open/close/switch contract against regressions
// (e.g. an Alpine break or an `x-show`/`x-transition` leave bug that leaves a
// popover stuck open or never showing).
//
// Runs with reduced motion so the orbit animation is frozen and the nodes stay
// statically placed — clicking a slowly-spinning node would otherwise be flaky.
// Reduced motion only freezes the CSS animations; the popovers still work.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral :5000 server, since localhost:80 hits the Express api-server).

const CONSENT_COOKIE = "1inme_cookie_consent";

// Node titles in flat ring order — inner ring (4), then middle ring (6), then
// outer ring (7) — mirroring $zioRings / $zioNodes in
// resources/views/home/partials/hero.blade.php. The node button's aria-label is
// "{title}: {description}" and the popover dialog's aria-label is "{title}".
const NODE_TITLES = [
  // Inner ring
  "AI Page Builder",
  "Growth Coach",
  "AI Phone",
  "Live Analytics",
  // Middle ring
  "Smart Links",
  "QR Studio",
  "Built-in Store",
  "Forms",
  "Subscribers",
  "Social Proof",
  // Outer ring
  "Developer API",
  "Reviews",
  "Restaurant Menu",
  "Resume",
  "Calendar",
  "Digital Cards",
  "Custom Domain",
] as const;

/**
 * Load the home page with consent already given (so the bottom-pinned cookie
 * banner can't intercept clicks) and wait for Alpine — every popover
 * interaction is Alpine-driven.
 */
async function gotoHome(page: Page): Promise<void> {
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
  // The orbit visual lives in the right-hand column; make sure it's mounted.
  await page.locator(".zio-orbit").first().waitFor({ state: "attached" });
  // Hard-freeze the orbit animations. The orbit spins continuously
  // (.zio-rotor / .zio-node-ic), so its nodes never satisfy Playwright's
  // "element is stable" actionability check and clicks would time out. This
  // mirrors the blade's own `prefers-reduced-motion` freeze block but applies
  // it unconditionally here (the `reducedMotion: "reduce"` emulation alone did
  // not reliably stop the spin in this headless render), so the nodes stay
  // statically placed and clickable. Popover open/close is Alpine-driven and
  // unaffected by freezing the CSS animations.
  await page.addStyleTag({
    content: `.zio-rotor, .zio-node-ic, .zio-node, .zio-node-thumb,
      .zio-mascot, .zio-mascot-halo, .zio-glow, .zio-pulse {
        animation: none !important;
      }
      .zio-node, .zio-node-thumb { opacity: 1 !important; }
      .zio-node-thumb { transform: scale(1) !important; }`,
  });
}

/** The orbit node button for a given index, scoped by its title aria-label. */
function nodeButton(page: Page, index: number): Locator {
  return page
    .getByRole("button", { name: new RegExp(`^${NODE_TITLES[index]}:`) })
    .first();
}

/** The popover dialog for a given index, scoped by its title aria-label. */
function popover(page: Page, index: number): Locator {
  return page.getByRole("dialog", { name: NODE_TITLES[index], exact: true });
}

/** How many popover cards are currently visible (display !== none). */
async function visiblePopoverCount(page: Page): Promise<number> {
  return page.evaluate(
    () =>
      Array.from(document.querySelectorAll<HTMLElement>(".zio-pop")).filter(
        (el) => el.offsetParent !== null,
      ).length,
  );
}

test.describe("home hero orbital tool popovers", () => {
  // Freeze the orbit so the nodes are statically placed and reliably clickable.
  test.use({ reducedMotion: "reduce" });

  test.beforeEach(async ({ page }) => {
    // Distant RDS makes the first paint slow; give each test headroom.
    test.setTimeout(60_000);
  });

  test("clicking a node opens its popover with the right title + description", async ({
    page,
  }) => {
    await gotoHome(page);

    // Nothing is open initially.
    expect(await visiblePopoverCount(page)).toBe(0);
    await expect(popover(page, 0)).toBeHidden();

    await nodeButton(page, 0).click();

    const pop = popover(page, 0);
    await expect(pop).toBeVisible();
    await expect(pop.locator(".zio-pop-title")).toHaveText("AI Page Builder");
    await expect(pop.locator(".zio-pop-desc")).toHaveText(
      "Describe it — Zio builds your page in seconds.",
    );
    // The node button reflects the open state for assistive tech.
    await expect(nodeButton(page, 0)).toHaveAttribute("aria-expanded", "true");

    expect(await visiblePopoverCount(page)).toBe(1);
  });

  test("clicking another node switches — only one popover open at a time", async ({
    page,
  }) => {
    await gotoHome(page);

    await nodeButton(page, 0).click();
    await expect(popover(page, 0)).toBeVisible();
    expect(await visiblePopoverCount(page)).toBe(1);

    // Open a different node — the first one must close, the new one opens.
    await nodeButton(page, 2).click();
    await expect(popover(page, 2)).toBeVisible();
    await expect(popover(page, 0)).toBeHidden();
    await expect(nodeButton(page, 0)).toHaveAttribute("aria-expanded", "false");
    await expect(nodeButton(page, 2)).toHaveAttribute("aria-expanded", "true");

    // Still exactly one popover visible.
    expect(await visiblePopoverCount(page)).toBe(1);
  });

  test("re-clicking the open node closes its popover", async ({ page }) => {
    await gotoHome(page);

    const btn = nodeButton(page, 1);
    await btn.click();
    await expect(popover(page, 1)).toBeVisible();

    await btn.click();
    await expect(popover(page, 1)).toBeHidden();
    await expect(btn).toHaveAttribute("aria-expanded", "false");
    expect(await visiblePopoverCount(page)).toBe(0);
  });

  test("pressing Escape closes the open popover", async ({ page }) => {
    await gotoHome(page);

    await nodeButton(page, 3).click();
    await expect(popover(page, 3)).toBeVisible();

    await page.keyboard.press("Escape");

    await expect(popover(page, 3)).toBeHidden();
    await expect(nodeButton(page, 3)).toHaveAttribute("aria-expanded", "false");
    expect(await visiblePopoverCount(page)).toBe(0);
  });

  test("clicking outside the orbit closes the open popover", async ({
    page,
  }) => {
    await gotoHome(page);

    await nodeButton(page, 0).click();
    await expect(popover(page, 0)).toBeVisible();

    // The hero heading sits outside `.zio-orbit`, so clicking it triggers the
    // orbit's @click.outside handler.
    await page.locator("#hero-h").click();

    await expect(popover(page, 0)).toBeHidden();
    await expect(nodeButton(page, 0)).toHaveAttribute("aria-expanded", "false");
    expect(await visiblePopoverCount(page)).toBe(0);
  });
});
