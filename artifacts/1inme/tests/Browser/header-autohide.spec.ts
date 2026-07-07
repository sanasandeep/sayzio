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
//   5. Under prefers-reduced-motion the hide/show still happens, just without
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
  await page.waitForFunction(
    (t) => Math.abs(window.scrollY - t) <= 3,
    target,
    { timeout: 5000 },
  );
  return target;
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
    const downLanding = await scrollTo(page, 900);
    expect(
      downLanding,
      "page must be tall enough to scroll past the grace zone",
    ).toBeGreaterThan(200);
    await waitHidden(page);
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
    const landed = await scrollTo(page, 900);
    expect(
      landed,
      "page must be tall enough to scroll past the grace zone",
    ).toBeGreaterThan(200);
    await waitHidden(page);
    await scrollTo(page, landed - 120);
    await waitVisible(page);
  });
});
