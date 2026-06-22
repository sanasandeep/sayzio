import { expect, test, type Locator, type Page } from "@playwright/test";

// Guards the home-page mobile sign-in popup (task: "Test the mobile sign-in
// popup so it can't silently break again"). On small screens the only way a
// visitor signs in is: open the hamburger menu → tap Login / Register → the
// two-panel auth modal opens. That flow broke twice — once the background page
// kept scrolling behind the modal (no scroll-lock), and once the round close
// button overlapped the Login / Sign-up tabs so the tabs were untappable.
//
// These tests pin those exact failure modes on an xs viewport:
//   1. The popup opens from the mobile menu (Login → login tab, Register →
//      register tab).
//   2. The close button does NOT overlap the Login / Sign-up tabs.
//   3. The background page is scroll-locked while the popup is open.
//   4. The popup closes cleanly via the X button and via Escape, and each
//      time the background scroll-lock is released.

// iPhone-SE-ish portrait: comfortably below Tailwind's `lg` (1024px) breakpoint
// so the desktop nav is hidden and the hamburger menu is the only entry point.
const XS_VIEWPORT = { width: 375, height: 667 };

const CONSENT_COOKIE = "1inme_cookie_consent";

type Box = { x: number; y: number; width: number; height: number };

/** True when two axis-aligned boxes share any area. */
function overlaps(a: Box, b: Box): boolean {
  return !(
    a.x + a.width <= b.x ||
    b.x + b.width <= a.x ||
    a.y + a.height <= b.y ||
    b.y + b.height <= a.y
  );
}

/** boundingBox() can be null for detached/hidden nodes — assert it isn't. */
async function box(locator: Locator): Promise<Box> {
  const b = await locator.boundingBox();
  expect(b, "expected element to have a bounding box").not.toBeNull();
  return b as Box;
}

/**
 * Load the home page on an xs viewport with consent already given, so the
 * bottom-pinned cookie banner can't intercept taps on the menu or modal.
 * Waits for Alpine to boot — every popup interaction is Alpine-driven.
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
  await page.waitForFunction(() => !!(window as unknown as { Alpine?: unknown }).Alpine);
}

/** The auth popup, scoped by its dialog aria-label. */
function modal(page: Page): Locator {
  return page.getByRole("dialog", { name: "Sign in or create an account" });
}

/** Open the hamburger menu and tap a CTA inside the mobile drawer. */
async function openPopupFromMobileMenu(
  page: Page,
  cta: "Login" | "Register",
): Promise<void> {
  await page.getByRole("button", { name: "Toggle menu" }).click();
  // The drawer CTA is the only visible button with this name (the desktop nav
  // and the modal tabs are display:none, so excluded from the a11y tree).
  await page.getByRole("button", { name: cta, exact: true }).click();
  await expect(modal(page)).toBeVisible();
}

async function bodyOverflow(page: Page): Promise<string> {
  return page.evaluate(
    () => getComputedStyle(document.body).overflow,
  );
}

test.describe("home mobile sign-in popup", () => {
  test.beforeEach(async ({ page }) => {
    // Distant RDS makes the first paint slow; give each test headroom.
    test.setTimeout(60_000);
    await page.setViewportSize(XS_VIEWPORT);
  });

  test("Login opens the popup on the login tab, close button clear of tabs, background scroll-locked", async ({
    page,
  }) => {
    await gotoHome(page);

    // Background scrolls freely before the popup is opened.
    expect(await bodyOverflow(page)).not.toBe("hidden");

    await openPopupFromMobileMenu(page, "Login");

    const dialog = modal(page);
    const loginTab = dialog.getByRole("button", { name: "Login", exact: true });
    const signUpTab = dialog.getByRole("button", {
      name: "Sign up",
      exact: true,
    });
    const closeBtn = dialog.getByRole("button", { name: "Close" });

    await expect(loginTab).toBeVisible();
    await expect(signUpTab).toBeVisible();
    await expect(closeBtn).toBeVisible();

    // The login form (its email field) is the one shown for the Login tab.
    await expect(dialog.locator('input[name="identifier"]')).toBeVisible();

    // The close button must not overlap either tab — the regression made the
    // round X sit on top of the tabs, blocking taps.
    const closeBox = await box(closeBtn);
    const loginBox = await box(loginTab);
    const signUpBox = await box(signUpTab);
    expect(
      overlaps(closeBox, loginBox),
      "close button overlaps the Login tab",
    ).toBe(false);
    expect(
      overlaps(closeBox, signUpBox),
      "close button overlaps the Sign up tab",
    ).toBe(false);
    // The close button sits above the tab row (the form panel reserves top
    // padding for it on mobile).
    expect(closeBox.y + closeBox.height).toBeLessThanOrEqual(loginBox.y + 1);

    // Background page is scroll-locked while the popup is open.
    await expect.poll(() => bodyOverflow(page)).toBe("hidden");
  });

  test("Register opens the popup on the register tab", async ({ page }) => {
    await gotoHome(page);
    await openPopupFromMobileMenu(page, "Register");

    const dialog = modal(page);
    // The register form (name field) is only shown on the register tab.
    await expect(dialog.locator('input[name="name"]')).toBeVisible();
    await expect.poll(() => bodyOverflow(page)).toBe("hidden");
  });

  test("the X button closes the popup and restores background scrolling", async ({
    page,
  }) => {
    await gotoHome(page);
    await openPopupFromMobileMenu(page, "Login");

    const dialog = modal(page);
    await expect.poll(() => bodyOverflow(page)).toBe("hidden");

    await dialog.getByRole("button", { name: "Close" }).click();

    await expect(dialog).toBeHidden();
    await expect.poll(() => bodyOverflow(page)).not.toBe("hidden");
  });

  test("Escape closes the popup and restores background scrolling", async ({
    page,
  }) => {
    await gotoHome(page);
    await openPopupFromMobileMenu(page, "Register");

    const dialog = modal(page);
    await expect.poll(() => bodyOverflow(page)).toBe("hidden");

    await page.keyboard.press("Escape");

    await expect(dialog).toBeHidden();
    await expect.poll(() => bodyOverflow(page)).not.toBe("hidden");
  });
});
