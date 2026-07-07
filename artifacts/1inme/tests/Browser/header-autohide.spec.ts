import { expect, test, type Page } from "@playwright/test";

// Regression guard for the marketing header's auto-hide-on-scroll behaviour
// (`public/partials/header.blade.php` + `.mkt-nav-autohide` /
// `.mkt-nav-hidden` in `public/css/marketing-anim.css`).
//
// The behaviour under test is driven entirely by a multi-statement Alpine
// expression on the header wrapper (`@scroll.window.passive="... navHidden
// ..."`) plus a `:class` binding on the <nav>. A future edit to that string
// could silently break it with no build/typecheck error — this spec makes the
// contract self-checking:
//
//   1. At the very top of the page the header is visible.
//   2. Scrolling DOWN past the 96px grace zone by more than the 24px
//      accumulated threshold hides it — fully off-viewport (the transform
//      slides it above the top edge, drop shadow included).
//   3. A small UPWARD scroll brings it back instantly (accumulator resets).
//   4. While a mega-menu panel is open the header must NEVER hide, even while
//      scrolling down (`navHidden` may flip true internally, but the `:class`
//      binding force-shows while `openMenu !== null`). Closing the menu with
//      the page still scrolled-down lets the pending hide apply — proving the
//      force-show was genuinely overriding, not just "never hidden".
//   5. On a mobile viewport, while the hamburger DRAWER is open the header
//      must never hide either: the `:class` binding force-shows while
//      `mobileOpen` is true, and `mkt-nav-drawer-open` pins the bar to the
//      viewport (position:fixed) because the drawer's body scroll lock breaks
//      position:sticky on a scrolled page.
//   6. Under prefers-reduced-motion the hide/show still happens, just without
//      the slide animation (CSS drops the transition but keeps the transform).
//
// Implementation notes:
// - Runs on /features (a long marketing page). /features sets CSS
//   `scroll-behavior: smooth`, so every programmatic scroll uses
//   `behavior: "instant"` and then WAITS for both window.scrollY to land AND
//   the nav's 300ms transform transition to settle before asserting.
// - The mega-menu is opened PROGRAMMATICALLY (Alpine `openMenu` state), never
//   via pointer hover: Chrome re-evaluates hover targets on scroll, which
//   would fire the header's real `@mouseleave="openMenu=null"` and close the
//   panel mid-assertion — the force-show would then be (correctly) released
//   and the test would be checking nothing.
// - Consent cookie is pre-seeded so the scroll-locking cookie banner never
//   mounts (same recipe as marketing-background-seam.spec.ts).

const APP_BASE = process.env.APP_URL || "http://localhost:5000";
const CONSENT_COOKIE = "1inme_cookie_consent";

// Selector for the Alpine component root that owns navHidden/openMenu state.
const WRAPPER_SEL = 'div[x-data*="navHidden"]';
const NAV_SEL = "nav.mkt-nav-autohide";

/** Pre-seed a valid consent decision so the cookie banner never mounts. */
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

/**
 * Navigate to /features and wait until Alpine has hydrated the header
 * component (the auto-hide behaviour doesn't exist until then).
 */
async function openFeatures(page: Page): Promise<void> {
  await page.goto(`${APP_BASE}/features`, { waitUntil: "domcontentloaded" });
  await page.locator(NAV_SEL).first().waitFor({ state: "attached" });
  await page.waitForFunction((sel) => {
    const w = window as unknown as {
      Alpine?: { $data: (el: Element) => Record<string, unknown> };
    };
    const el = document.querySelector(sel);
    if (!w.Alpine || !el) return false;
    try {
      const data = w.Alpine.$data(el);
      return data && typeof data.navHidden === "boolean";
    } catch {
      return false;
    }
  }, WRAPPER_SEL);
}

type NavState = {
  hiddenClass: boolean;
  top: number;
  bottom: number;
  scrollY: number;
};

/** Read the nav's class + viewport geometry in one page round-trip. */
async function readNav(page: Page): Promise<NavState> {
  return page.evaluate((sel) => {
    const nav = document.querySelector(sel) as HTMLElement;
    const rect = nav.getBoundingClientRect();
    return {
      hiddenClass: nav.classList.contains("mkt-nav-hidden"),
      top: rect.top,
      bottom: rect.bottom,
      scrollY: window.scrollY,
    };
  }, NAV_SEL);
}

/**
 * Instant-scroll to `y` (bypassing the page's CSS smooth scrolling so the
 * landing position is deterministic), then wait for scrollY to actually land.
 * The Alpine @scroll.window handler fires from this same scroll, so once
 * scrollY has settled the state flip has been dispatched too.
 *
 * The requested `y` is clamped to the page's real max scroll: under
 * prefers-reduced-motion (or before every lazy section has settled) /features
 * can be shorter than the naive target, and an unreachable target would spin
 * the wait forever. The auto-hide thresholds (96px zone + 24px accumulator)
 * are far below any clamped landing point used here.
 */
async function scrollTo(page: Page, y: number): Promise<number> {
  const target = await page.evaluate((requested) => {
    const max = Math.max(
      0,
      document.documentElement.scrollHeight - window.innerHeight,
    );
    const clamped = Math.min(requested, max);
    window.scrollTo({ top: clamped, behavior: "instant" });
    return clamped;
  }, y);
  // Late layout shifts (lazy media settling, reduced-motion reveal
  // differences) can move scrollY by a few px AFTER the instant scroll lands
  // — e.g. content above the landing point shrinking 14px leaves scrollY at
  // 886 for a 900 target, which an exact-match wait would spin on forever.
  // Require the position to be NEAR the target (well within the 96px grace
  // zone margin) and STABLE across consecutive polls, then return the real
  // landing so relative follow-up scrolls stay coherent.
  await page.waitForFunction(
    (t) => {
      const w = window as unknown as { __sc?: { y: number; n: number } };
      const cur = window.scrollY;
      const prev = w.__sc;
      w.__sc =
        prev && prev.y === cur ? { y: cur, n: prev.n + 1 } : { y: cur, n: 0 };
      return Math.abs(cur - t) <= 48 && w.__sc.n >= 2;
    },
    target,
    { timeout: 5000, polling: 100 },
  );
  return page.evaluate(() => window.scrollY);
}

/**
 * Scroll down to `y` and wait for the header to hide, defeating a late-layout
 * -shift race: content above the landing point can settle (lazy media,
 * reduced-motion reveal differences) AFTER the scroll lands, firing a tiny
 * *upward* scroll event that — correctly, per the widget's "any up-delta
 * shows" rule — cancels the pending hide. `scrollTo` already waits for a
 * stable landing, so one guaranteed down-nudge afterwards deterministically
 * re-arms the hide (64px > the 24px accumulator threshold; harmless if the
 * header is already hidden). Returns the final scrollY so follow-up relative
 * scrolls stay coherent. All targets used here are far below the page bottom,
 * so the nudge always produces a real scroll event.
 */
async function scrollDownAndWaitHidden(
  page: Page,
  y: number,
): Promise<number> {
  await scrollTo(page, y);
  await page.evaluate(() =>
    window.scrollBy({ top: 64, behavior: "instant" }),
  );
  await waitHidden(page);
  return page.evaluate(() => window.scrollY);
}

/**
 * Wait until the nav is fully OFF-viewport (hidden). The hide is a 300ms
 * transform transition, so poll geometry rather than sleeping a fixed time —
 * `rect.bottom <= 0` only becomes true once the slide has fully settled
 * (the transform overshoots by 2rem + the announcement offset by design, so
 * the drop shadow clears too).
 */
async function waitHidden(page: Page): Promise<void> {
  await page.waitForFunction(
    (sel) => {
      const nav = document.querySelector(sel) as HTMLElement;
      return (
        nav.classList.contains("mkt-nav-hidden") &&
        nav.getBoundingClientRect().bottom <= 0
      );
    },
    NAV_SEL,
    { timeout: 5000 },
  );
}

/**
 * Wait until the nav is back ON-viewport (visible): no hidden class and the
 * bar's box genuinely occupies the top of the viewport again (bottom well
 * past 0 — the bar is 64px tall — and top not below a plausible sticky
 * offset). Polling covers the 300ms show transition.
 */
async function waitVisible(page: Page): Promise<void> {
  await page.waitForFunction(
    (sel) => {
      const nav = document.querySelector(sel) as HTMLElement;
      const rect = nav.getBoundingClientRect();
      return (
        !nav.classList.contains("mkt-nav-hidden") &&
        rect.bottom > 40 &&
        rect.top >= -2 &&
        rect.top < 120
      );
    },
    NAV_SEL,
    { timeout: 5000 },
  );
}

/** Set the header's Alpine `openMenu` state programmatically (no hover). */
async function setOpenMenu(page: Page, value: string | null): Promise<void> {
  await page.evaluate(
    ({ sel, v }) => {
      const w = window as unknown as {
        Alpine: { $data: (el: Element) => { openMenu: string | null } };
      };
      const el = document.querySelector(sel) as HTMLElement;
      w.Alpine.$data(el).openMenu = v;
    },
    { sel: WRAPPER_SEL, v: value },
  );
}

/** Set the header's Alpine `mobileOpen` (drawer) state programmatically. */
async function setMobileOpen(page: Page, value: boolean): Promise<void> {
  await page.evaluate(
    ({ sel, v }) => {
      const w = window as unknown as {
        Alpine: { $data: (el: Element) => { mobileOpen: boolean } };
      };
      const el = document.querySelector(sel) as HTMLElement;
      w.Alpine.$data(el).mobileOpen = v;
    },
    { sel: WRAPPER_SEL, v: value },
  );
}

/** Force the internal `navHidden` flag directly (bypassing scroll). */
async function setNavHidden(page: Page, value: boolean): Promise<void> {
  await page.evaluate(
    ({ sel, v }) => {
      const w = window as unknown as {
        Alpine: { $data: (el: Element) => { navHidden: boolean } };
      };
      const el = document.querySelector(sel) as HTMLElement;
      w.Alpine.$data(el).navHidden = v;
    },
    { sel: WRAPPER_SEL, v: value },
  );
}

/** Read the header component's internal Alpine state. */
async function readAlpine(
  page: Page,
): Promise<{ navHidden: boolean; openMenu: string | null }> {
  return page.evaluate((sel) => {
    const w = window as unknown as {
      Alpine: {
        $data: (el: Element) => { navHidden: boolean; openMenu: string | null };
      };
    };
    const el = document.querySelector(sel) as HTMLElement;
    const d = w.Alpine.$data(el);
    return { navHidden: d.navHidden, openMenu: d.openMenu };
  }, WRAPPER_SEL);
}

test.describe("marketing header — auto-hide on scroll", () => {
  test("hides on scroll down, returns on scroll up, never hides while a mega-menu is open", async ({
    page,
  }) => {
    await seedConsent(page);
    await openFeatures(page);

    // ---- 1. At the top: header visible, no hidden class. ----
    await scrollTo(page, 0);
    await waitVisible(page);
    const atTop = await readNav(page);
    expect(atTop.hiddenClass, "header must not be hidden at the top").toBe(
      false,
    );
    expect(atTop.bottom, "header bar must be on-viewport at the top").toBeGreaterThan(
      40,
    );

    // ---- 2. Scroll down well past the 96px grace zone + 24px threshold:
    //         header hides, fully off-viewport. ----
    const downLanding = await scrollDownAndWaitHidden(page, 900);
    expect(
      downLanding,
      "page must be tall enough to scroll past the grace zone",
    ).toBeGreaterThan(200);
    const hidden = await readNav(page);
    expect(hidden.hiddenClass).toBe(true);
    expect(
      hidden.bottom,
      `hidden header must be fully off-viewport (bottom=${hidden.bottom})`,
    ).toBeLessThanOrEqual(0);

    // ---- 3. Small upward scroll (still far from the top): header returns
    //         instantly (any upward delta resets the accumulator). Relative to
    //         wherever the down-scroll actually landed. ----
    await scrollTo(page, downLanding - 120);
    await waitVisible(page);
    const shown = await readNav(page);
    expect(shown.hiddenClass).toBe(false);
    expect(shown.bottom).toBeGreaterThan(40);

    // ---- 4. Open the Product mega-menu PROGRAMMATICALLY (pointer hover
    //         would be re-evaluated by Chrome on scroll and fire the real
    //         @mouseleave close), then scroll down: the header must stay
    //         visible even though the scroll direction says "hide". ----
    await setOpenMenu(page, "product");
    // The panel is x-show-bound to openMenu; confirm it actually opened.
    await expect(
      page.locator('nav [x-show="openMenu === \'product\'"]').first(),
    ).toBeVisible();

    await scrollTo(page, 1500);
    // Re-arm the hide in case a late layout shift fired an upward scroll
    // event after landing (same race scrollDownAndWaitHidden defeats).
    await page.evaluate(() =>
      window.scrollBy({ top: 64, behavior: "instant" }),
    );
    // The scroll handler still runs and flips navHidden internally…
    await page.waitForFunction(
      (sel) => {
        const w = window as unknown as {
          Alpine: { $data: (el: Element) => { navHidden: boolean } };
        };
        const el = document.querySelector(sel) as HTMLElement;
        return w.Alpine.$data(el).navHidden === true;
      },
      WRAPPER_SEL,
      { timeout: 5000 },
    );
    // …but the :class binding must force-show while the menu is open. Give the
    // (absent) transition a beat to prove nothing slides away, then assert.
    await page.waitForTimeout(450);
    const withMenu = await readNav(page);
    expect(
      withMenu.hiddenClass,
      "header must never carry mkt-nav-hidden while a mega-menu is open",
    ).toBe(false);
    expect(
      withMenu.bottom,
      "header bar must stay on-viewport while a mega-menu is open",
    ).toBeGreaterThan(40);

    // Closing the menu releases the force-show: the pending navHidden=true
    // now applies and the header slides away — proving the force-show was a
    // genuine override of an active hide, not a no-op.
    await setOpenMenu(page, null);
    await waitHidden(page);
    const afterClose = await readAlpine(page);
    expect(afterClose.navHidden).toBe(true);
    expect(afterClose.openMenu).toBeNull();
  });

  test("mobile: never hides while the drawer is open", async ({ page }) => {
    // The same :class binding also force-shows the header while the MOBILE
    // drawer is up (`!mobileOpen`): the drawer's close button lives in the
    // header bar, so if the bar slid away the drawer would become
    // un-dismissable. This exercises that third branch of the binding.
    //
    // Two cooperating mechanisms are under test here:
    //   a. the `:class` binding must never apply `mkt-nav-hidden` while
    //      `mobileOpen` is true, and
    //   b. the `mkt-nav-drawer-open` class (also bound to `mobileOpen`) must
    //      pin the bar to the VIEWPORT (`position: fixed`). Without it the
    //      drawer's `body{overflow:hidden}` scroll lock breaks
    //      `position:sticky` and, on a scrolled page, the bar (drawer
    //      included) snaps back to the top of the DOCUMENT — off-viewport —
    //      even with the hidden class correctly absent.
    //
    // Note: the drawer sets `document.body.style.overflow = 'hidden'` via
    // x-effect, so real scrolling is locked while it's open. The test
    // therefore scrolls (and hides the header) BEFORE opening the drawer,
    // and afterwards drives `navHidden` directly to simulate a scroll-down
    // attempt — asserting the binding keeps the header visible regardless.
    await page.setViewportSize({ width: 390, height: 844 });
    await seedConsent(page);
    await openFeatures(page);

    // Sanity: at 390px the hamburger toggle is the visible control.
    await expect(
      page.locator('button[aria-label="Toggle menu"]').first(),
    ).toBeVisible();

    // ---- 1. Scroll down first (drawer closed): header hides normally. ----
    const landed = await scrollDownAndWaitHidden(page, 900);
    expect(
      landed,
      "page must be tall enough to scroll past the grace zone",
    ).toBeGreaterThan(200);

    // ---- 2. Open the drawer programmatically while navHidden is still
    //         true: the :class binding must immediately force-show the
    //         header (with the drawer's close button). ----
    await setMobileOpen(page, true);
    await expect(
      page.locator('div[x-show="mobileOpen"]').first(),
    ).toBeVisible();
    // Body scroll must be locked by the drawer's x-effect.
    await page.waitForFunction(() => document.body.style.overflow === "hidden");
    // The scroll lock breaks position:sticky, so the bar must be pinned to
    // the viewport (mkt-nav-drawer-open → position:fixed) to stay on-screen.
    await page.waitForFunction((sel) => {
      const nav = document.querySelector(sel) as HTMLElement;
      return (
        nav.classList.contains("mkt-nav-drawer-open") &&
        getComputedStyle(nav).position === "fixed"
      );
    }, NAV_SEL);
    await waitVisible(page);

    // ---- 3. Simulate a scroll-down attempt while the drawer is open by
    //         forcing navHidden=true directly (real scrolling is locked).
    //         The header must NEVER gain mkt-nav-hidden. ----
    await setNavHidden(page, true);
    await page.waitForTimeout(450); // let any (wrong) transition start
    const withDrawer = await readNav(page);
    expect(
      withDrawer.hiddenClass,
      "header must never carry mkt-nav-hidden while the mobile drawer is open",
    ).toBe(false);
    expect(
      withDrawer.bottom,
      "header bar must stay on-viewport while the mobile drawer is open",
    ).toBeGreaterThan(40);
    const state = await readAlpine(page);
    expect(state.navHidden, "internal flag stays true — the :class binding is what overrides").toBe(
      true,
    );

    // ---- 4. Closing the drawer releases the force-show: the pending
    //         navHidden=true now applies and the header hides — proving the
    //         override was genuine, not a no-op. Scroll lock also lifts. ----
    await setMobileOpen(page, false);
    await waitHidden(page);
    await page.waitForFunction(() => document.body.style.overflow === "");
  });

  test("reduced motion: still hides and shows, without the slide animation", async ({
    page,
  }) => {
    await seedConsent(page);
    // Emulate BEFORE navigation so the CSS media query applies from first
    // paint. (This reaches CSS reliably; the auto-hide state itself is
    // media-query-independent Alpine logic.)
    await page.emulateMedia({ reducedMotion: "reduce" });
    await openFeatures(page);

    // The reduced-motion rule drops the transition entirely.
    const transition = await page.evaluate((sel) => {
      const nav = document.querySelector(sel) as HTMLElement;
      return getComputedStyle(nav).transitionProperty;
    }, NAV_SEL);
    expect(
      transition,
      "reduced motion must disable the slide transition",
    ).toBe("none");

    // Hide/show still function (instantly). Under reduced motion the page's
    // reveal animations are gated, so the page can be shorter than in the
    // default-motion test — scroll targets are clamped and the up-scroll is
    // relative to wherever the down-scroll actually landed.
    const landed = await scrollDownAndWaitHidden(page, 900);
    expect(
      landed,
      "page must be tall enough to scroll past the grace zone",
    ).toBeGreaterThan(200);
    await scrollTo(page, landed - 120);
    await waitVisible(page);
  });
});
