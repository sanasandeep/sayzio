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
  "AI Growth Coach",
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
      "Describe your idea in a sentence and Zio assembles a complete, on-brand page for you.",
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

// ---------------------------------------------------------------------------
// Small-phone legibility guard.
//
// The orbit grew from 8 to 17 nodes across 3 rings. On narrow screens the rings
// tighten and tiles risk crowding the central Zio mascot or each other — the
// popover contract above doesn't cover layout. This block pins the geometry at
// real small-phone widths so a future radius / node-size / icon-count change
// that reintroduces crowding fails CI instead of shipping.
//
// What "overlap" means here, and why two different checks:
//  - Node vs node: the tiles are (rounded) squares, so their bounding boxes ARE
//    the visible tile. A bounding-box intersection is a real visual collision.
//  - Node vs mascot: the mascot's bounding box is its full PNG, which carries
//    transparent padding and is nudged slightly upward by the "Zio runs it all"
//    label that shares its centred column — so a raw bbox test would false-flag
//    the transparent corners (and the mascot renders ABOVE the tiles anyway via
//    z-index, hiding any incidental kiss). Instead we model the mascot the way
//    the blade's own CSS comment reasons about it: a disk centred on the orbit,
//    radius = half the mascot width (its `--size * 0.42`). A tile must stay
//    outside that disk. This is robust (width is symmetric, no label shift) and
//    catches the real regression: rings pulled in until the inner tiles sit on
//    top of Zio.
// ---------------------------------------------------------------------------

type Rect = { x: number; y: number; width: number; height: number };

/** Common small-phone CSS widths (iPhone SE/mini ... iPhone 14/15). */
const SMALL_VIEWPORTS = [
  { w: 360, h: 780 },
  { w: 390, h: 844 },
] as const;

// Representative widths for the orbit's other two sizing breakpoints (see
// hero.blade.php): the tablet block `@media (min-width:640px) and
// (max-width:1023.98px)` where `--size` grows to clamp(360px,54vw,460px), and
// the two-column desktop layout `@media (min-width:1024px)` where `--size`
// climbs to 500px. The radii/node clamps differ there, so the phone-only guard
// wouldn't catch crowding reintroduced at these sizes.
const LARGE_VIEWPORTS = [
  { w: 768, h: 1024, label: "tablet" },
  { w: 1280, h: 900, label: "desktop" },
] as const;

/** Collect every node tile rect plus the mascot rect and the orbit centre. */
async function collectOrbitGeometry(page: Page): Promise<{
  nodes: { title: string; rect: Rect }[];
  mascot: Rect | null;
  center: { x: number; y: number } | null;
}> {
  return page.evaluate(() => {
    const toRect = (el: Element): Rect => {
      const b = el.getBoundingClientRect();
      return { x: b.x, y: b.y, width: b.width, height: b.height };
    };
    const nodes = Array.from(
      document.querySelectorAll<HTMLElement>(".zio-node-btn"),
    ).map((el, i) => ({
      title: (el.getAttribute("aria-label") || `node ${i}`).split(":")[0],
      rect: toRect(el),
    }));
    const mascotEl = document.querySelector(".zio-mascot");
    const orbitEl = document.querySelector(".zio-orbit");
    const orbit = orbitEl ? orbitEl.getBoundingClientRect() : null;
    return {
      nodes,
      mascot: mascotEl ? toRect(mascotEl) : null,
      center: orbit
        ? { x: orbit.x + orbit.width / 2, y: orbit.y + orbit.height / 2 }
        : null,
    };
  });
}

/** True when two rects share more than `eps` px on BOTH axes (a real overlap). */
function rectsOverlap(a: Rect, b: Rect, eps = 0.5): boolean {
  const ix = Math.min(a.x + a.width, b.x + b.width) - Math.max(a.x, b.x);
  const iy = Math.min(a.y + a.height, b.y + b.height) - Math.max(a.y, b.y);
  return ix > eps && iy > eps;
}

/** Shortest distance from a point to (the nearest edge/corner of) a rect. */
function pointToRectDistance(px: number, py: number, r: Rect): number {
  const dx = Math.max(r.x - px, 0, px - (r.x + r.width));
  const dy = Math.max(r.y - py, 0, py - (r.y + r.height));
  return Math.hypot(dx, dy);
}

/**
 * Pin a viewport + colour scheme, load the home page, and assert the orbit
 * geometry is uncrowded: every tile clears the central mascot disk and no two
 * tiles overlap. Shared by the small-phone guard and the tablet/desktop guard
 * so all three sizing breakpoints (see hero.blade.php) get identical checks.
 */
async function assertOrbitGeometryClear(
  page: Page,
  theme: "dark" | "light",
  vp: { w: number; h: number },
): Promise<void> {
  // Pin the colour scheme deterministically: the home page falls back to the
  // OS `prefers-color-scheme` when no preference is stored, so a bare "dark"
  // run could render light on a light-preferring headless browser. The
  // `1inme_theme` cookie is read on first paint (see home.blade.php).
  await page.context().addCookies([
    { name: "1inme_theme", value: theme, domain: "localhost", path: "/" },
  ]);
  await page.setViewportSize({ width: vp.w, height: vp.h });
  await gotoHome(page);

  // Sanity: the intended theme actually applied (guards the cookie path so the
  // "light mode" run isn't silently testing dark mode).
  const isLight = await page.evaluate(() =>
    document.documentElement.classList.contains("light-mode"),
  );
  expect(isLight).toBe(theme === "light");

  const { nodes, mascot, center } = await collectOrbitGeometry(page);
  expect(mascot).not.toBeNull();
  expect(center).not.toBeNull();
  // Mascot must be laid out — its width feeds the keep-out disk radius.
  expect(mascot!.width).toBeGreaterThan(0);
  // All 17 nodes must be present and laid out (not collapsed/hidden).
  expect(nodes.length).toBe(NODE_TITLES.length);
  for (const n of nodes) {
    expect(n.rect.width).toBeGreaterThan(0);
    expect(n.rect.height).toBeGreaterThan(0);
  }

  // (1) No tile may encroach the central mascot disk (radius = mascot
  // half-width, centred on the orbit). Allow 1px slack for sub-pixel
  // rounding; the design clearance here is several px.
  const mascotRadius = mascot!.width / 2;
  const mascotHits = nodes
    .filter((n) => {
      const d = pointToRectDistance(center!.x, center!.y, n.rect);
      return mascotRadius - d > 1;
    })
    .map((n) => n.title);
  expect(
    mascotHits,
    `tiles overlapping the central mascot: ${mascotHits.join(", ")}`,
  ).toEqual([]);

  // (2) No two tiles may overlap each other.
  const pairHits: string[] = [];
  for (let i = 0; i < nodes.length; i++) {
    for (let j = i + 1; j < nodes.length; j++) {
      if (rectsOverlap(nodes[i].rect, nodes[j].rect)) {
        pairHits.push(`${nodes[i].title} ↔ ${nodes[j].title}`);
      }
    }
  }
  expect(pairHits, `overlapping tile pairs: ${pairHits.join("; ")}`).toEqual([]);
}

test.describe("home hero orbital legibility on small phones", () => {
  // Freeze the orbit so nodes are statically placed and geometry is stable.
  test.use({ reducedMotion: "reduce" });

  for (const theme of ["dark", "light"] as const) {
    for (const vp of SMALL_VIEWPORTS) {
      test(`nodes clear the mascot and each other @ ${vp.w}px (${theme} mode)`, async ({
        page,
      }) => {
        test.setTimeout(60_000);
        await assertOrbitGeometryClear(page, theme, vp);
      });
    }
  }
});

// ---------------------------------------------------------------------------
// Tablet + desktop legibility guard.
//
// The orbit also changes geometry at the tablet block (640–1023px, `--size`
// clamp(360px,54vw,460px)) and the two-column desktop layout (≥1024px, `--size`
// up to 500px). A future change to the radius/node-clamp fractions could
// reintroduce node-on-node or node-on-mascot crowding at those widths without
// failing the phone-only guard above, so pin the same geometry contract here.
// ---------------------------------------------------------------------------
test.describe("home hero orbital legibility at tablet and desktop sizes", () => {
  // Freeze the orbit so nodes are statically placed and geometry is stable.
  test.use({ reducedMotion: "reduce" });

  for (const theme of ["dark", "light"] as const) {
    for (const vp of LARGE_VIEWPORTS) {
      test(`nodes clear the mascot and each other @ ${vp.w}px ${vp.label} (${theme} mode)`, async ({
        page,
      }) => {
        test.setTimeout(60_000);
        await assertOrbitGeometryClear(page, theme, vp);
      });
    }
  }
});
