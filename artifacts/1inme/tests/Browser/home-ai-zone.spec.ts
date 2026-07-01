import { expect, test, type Locator, type Page } from "@playwright/test";

// Guards the marketing home "AI Zone" (task: "Catch AI Suite demo breakage
// before it ships"). The consolidated AI zone (home.blade.php `#ai-zone`) opens
// with three jump chips that anchor-scroll to the three AI sub-sections, and its
// centrepiece is the interactive AI Suite demo (home/partials/ai-suite.blade.php
// `#ai-suite`): four selectable product cards / tabs (`[data-aisx-tab]`) each
// swap the single live "screen" (`[data-aisx-screen]`) to a different demo scene
// (`[data-aisx-scene]`), updating the screen's title (`[data-aisx-title]`) and
// accent colour.
//
// This zone was only ever verified by hand on a live instance — the cold home
// render over the distant RDS exceeds the browser navigation budget in the
// isolated task env, so there was NO automated coverage. A future CSS / Alpine /
// vanilla-JS change could silently break tab switching, the jump-chip anchor
// scrolling, or the reduced-motion "assembled" fallback and no gate would catch
// it. These specs lock in the behaviour that was verified by hand, wired into
// the existing browser e2e gate against the warm dev server.
//
// Everything runs with reducedMotion:"reduce". WHY: the AI Suite auto-cycles the
// scenes on a 5.4s timer while in view, which would race the assertions below
// non-deterministically. Under reduced motion the suite's own JS skips
// `startTimer()` entirely (see ai-suite.blade.php: `if (reduce || timer)
// return;`), so the scene only changes in response to an explicit tab click —
// making the tab-switch assertions deterministic — and the row-reveal keyframes
// are disabled so the active scene rests in its fully-assembled state (every
// `.aisx-row` at opacity 1), which is exactly the reduced-motion fallback this
// spec also pins.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral e2e server, since localhost:80 hits the Express api-server).

const CONSENT_COOKIE = "1inme_cookie_consent";

// The AI Suite product cards in DOM order, keyed to their eyebrow label (the
// text the demo screen title mirrors on selection). Mirrors $__aiProducts in
// resources/views/home/partials/ai-suite.blade.php.
const PRODUCT_EYEBROWS = [
  "AI Chatbot",
  "AI Agent",
  "AI Widget",
  "AI Voice Assistant",
] as const;

// The three jump chips in the zone intro and the section id each anchors to.
// Mirrors the `a.ai-zone-chip[href="#..."]` list in home.blade.php.
const JUMP_TARGETS = [
  "ai-suite",
  "ai-marketing-strategist",
  "whatsapp-agent",
] as const;

/**
 * Load the home page with consent already given (so the bottom-pinned cookie
 * banner can't intercept clicks / lock scroll) and wait for the AI Suite to be
 * mounted and initialised — the tab switching is driven by the partial's own
 * vanilla JS, which flips `window.__aisxInit` once it has wired the cards.
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
  // The AI Suite section and its cards must be present before we interact.
  await page.locator("#ai-suite").waitFor({ state: "attached" });
  await page.locator("[data-aisx-tab]").first().waitFor({ state: "attached" });
  // The suite's init flips this flag once the cards are wired for clicks.
  await page.waitForFunction(
    () => (window as unknown as { __aisxInit?: boolean }).__aisxInit === true,
  );
}

/** The AI Suite tab/card button at a given index. */
function tab(page: Page, index: number): Locator {
  return page.locator(`[data-aisx-tab="${index}"]`);
}

/** The AI Suite demo scene at a given index. */
function scene(page: Page, index: number): Locator {
  return page.locator(`[data-aisx-scene="${index}"]`);
}

/**
 * The index of the currently-active demo scene (the one with `.is-active`,
 * which is the only one displayed). Returns -1 if none is active.
 */
async function activeSceneIndex(page: Page): Promise<number> {
  return page.evaluate(() => {
    const scenes = Array.from(
      document.querySelectorAll<HTMLElement>("[data-aisx-scene]"),
    );
    return scenes.findIndex((s) => s.classList.contains("is-active"));
  });
}

test.describe("home AI Suite interactive demo", () => {
  // Freeze auto-cycling + row-reveal so the scene only changes on an explicit
  // click and assertions are deterministic (see file header).
  test.use({ reducedMotion: "reduce" });

  test.beforeEach(() => {
    // Distant RDS makes the first paint slow; give each test headroom.
    test.setTimeout(60_000);
  });

  test("all four AI Suite tabs are present and each swaps the live demo scene", async ({
    page,
  }) => {
    await gotoHome(page);

    const tabs = page.locator("[data-aisx-tab]");
    await expect(tabs).toHaveCount(PRODUCT_EYEBROWS.length);

    // The first scene is active on load; its title matches the first product.
    expect(await activeSceneIndex(page)).toBe(0);
    await expect(page.locator("[data-aisx-title]")).toHaveText(
      PRODUCT_EYEBROWS[0],
    );

    // Click each tab (including revisiting the first) and assert the demo scene,
    // the selected state, and the screen title all follow.
    for (const order of [1, 2, 3, 0]) {
      await tab(page, order).click();

      // Only the clicked scene is active/displayed.
      await expect(scene(page, order)).toHaveClass(/\bis-active\b/);
      await expect(scene(page, order)).toBeVisible();
      expect(await activeSceneIndex(page)).toBe(order);

      // Exactly one scene is active at a time.
      const activeCount = await page.evaluate(
        () =>
          document.querySelectorAll("[data-aisx-scene].is-active").length,
      );
      expect(activeCount).toBe(1);

      // The clicked tab reflects the selection for assistive tech; others don't.
      await expect(tab(page, order)).toHaveAttribute("aria-selected", "true");
      for (let other = 0; other < PRODUCT_EYEBROWS.length; other++) {
        if (other === order) continue;
        await expect(tab(page, other)).toHaveAttribute(
          "aria-selected",
          "false",
        );
      }

      // The live screen title mirrors the selected product's eyebrow label.
      await expect(page.locator("[data-aisx-title]")).toHaveText(
        PRODUCT_EYEBROWS[order],
      );
    }
  });

  test("under reduced motion the active scene settles into its assembled state", async ({
    page,
  }) => {
    await gotoHome(page);

    // The active scene must SETTLE into its fully-assembled state: every
    // `.aisx-row` ends at opacity 1, never stranded invisible. Under a genuine
    // reduced-motion match the rows are opacity 1 immediately (the reveal
    // keyframes are gated behind `prefers-reduced-motion: no-preference`); if the
    // headless render doesn't honour the emulation the row-reveal animation runs
    // but fills `forwards` to opacity 1 within a few hundred ms. Either way a
    // healthy demo converges on opacity 1 — so we poll for the settled state
    // rather than reading a single (possibly mid-animation) frame. A regression
    // that leaves rows permanently invisible (e.g. dropping the `forwards` fill
    // or hard-setting opacity 0) never settles and fails here. Check the initial
    // scene and one selected via a tab click.
    for (const order of [0, 2]) {
      if (order !== 0) await tab(page, order).click();
      await expect(scene(page, order)).toBeVisible();

      // The minimum row opacity in the active scene, so a single stuck row fails.
      await expect
        .poll(
          () =>
            page.evaluate((i) => {
              const s = document.querySelector(`[data-aisx-scene="${i}"]`);
              if (!s) return -1;
              const ops = Array.from(
                s.querySelectorAll<HTMLElement>(".aisx-row"),
              ).map((el) => parseFloat(getComputedStyle(el).opacity));
              return ops.length ? Math.min(...ops) : -1;
            }, order),
          {
            timeout: 8_000,
            message: `scene ${order} rows should settle to opacity 1`,
          },
        )
        .toBeGreaterThan(0.99);
    }
  });
});

test.describe("home AI zone jump chips", () => {
  test.use({ reducedMotion: "reduce" });

  test.beforeEach(() => {
    test.setTimeout(60_000);
  });

  for (const targetId of JUMP_TARGETS) {
    test(`chip anchors to #${targetId}`, async ({ page }) => {
      await gotoHome(page);

      const chip = page.locator(`a.ai-zone-chip[href="#${targetId}"]`);
      await expect(chip).toHaveCount(1);
      await expect(page.locator(`#${targetId}`)).toHaveCount(1);

      await chip.click();

      // Clicking the chip must land the target section at (or near) the top of
      // the viewport. The home page intercepts in-page anchor clicks with a
      // global handler (home.blade.php: `a[href^="#"]` → `preventDefault()` +
      // `scrollIntoView({behavior:'smooth', block:'start'})`), so the URL hash is
      // NOT updated — the scroll position is the source of truth. Poll to tolerate
      // the smooth-scroll animation and any sticky-header offset. A regression
      // (removed id / broken chip href / handler) leaves the section far
      // off-screen and this times out.
      await page.waitForFunction(
        (id) => {
          const el = document.getElementById(id);
          if (!el) return false;
          const top = el.getBoundingClientRect().top;
          return top >= -4 && top <= 250;
        },
        targetId,
        { timeout: 10_000 },
      );
    });
  }
});

// ---------------------------------------------------------------------------
// No horizontal overflow guard.
//
// The AI zone packs a wide two-column suite layout, floating badges nudged with
// negative offsets, and full-bleed ambient blobs/auras that are only masked, not
// clipped by the page. A future width/offset change could push content past the
// viewport and introduce a horizontal scrollbar (the classic mobile "the whole
// page wiggles sideways" regression). Assert the document never overflows
// horizontally at a desktop and a mobile width.
// ---------------------------------------------------------------------------
test.describe("home AI zone — no horizontal overflow", () => {
  test.use({ reducedMotion: "reduce" });

  const VIEWPORTS = [
    { w: 1280, h: 900, label: "desktop" },
    { w: 390, h: 844, label: "mobile" },
  ] as const;

  for (const vp of VIEWPORTS) {
    test(`no horizontal scroll @ ${vp.w}px (${vp.label})`, async ({ page }) => {
      test.setTimeout(60_000);
      await page.setViewportSize({ width: vp.w, height: vp.h });
      await gotoHome(page);

      // Bring the AI zone into view so any overflow it introduces is realised
      // (lazy/animated children only lay out once near the viewport).
      await page.locator("#ai-zone").scrollIntoViewIfNeeded();

      const { scrollWidth, clientWidth } = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
      }));

      expect(
        scrollWidth,
        `horizontal overflow at ${vp.w}px (scrollWidth=${scrollWidth} clientWidth=${clientWidth})`,
      ).toBe(clientWidth);
    });
  }
});
