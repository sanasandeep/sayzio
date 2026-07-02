import { execFileSync } from "node:child_process";
import * as path from "node:path";
import { fileURLToPath } from "node:url";
import { expect, test, type Locator, type Page } from "@playwright/test";

// Guards the store-badge "coming soon" popup against the blank-modal regression
// (task: "Catch the blank popup bug coming back if another section wraps the
// store badges"). Root cause of the original bug: the modal markup lived inside
// a CSS-transformed / opacity-animated ancestor (the homepage dialer section's
// `.reveal` wrapper), so its `position: fixed` overlay resolved against that
// transformed ancestor instead of the viewport — the popup opened somewhere
// off-screen / clipped and looked "blank". The fix teleports the modal directly
// under <body> via Alpine `x-teleport="body"`
// (resources/views/public/partials/store-buttons.blade.php).
//
// Nothing controller-level can observe that failure mode — it only exists in a
// real layout engine. These tests pin the invariant for BOTH current include
// contexts of the partial (the homepage dialer section and the shared footer):
// clicking a store badge with no store URL configured must open a modal card
// that is genuinely visible, un-clipped, fully inside the viewport, and
// actually hit-testable at its center; the close button must dismiss it. If a
// future page wraps `@include('public.partials.store-buttons')` in a new
// animated/transformed section and the teleport is lost, these assertions fail.
//
// Self-bootstrapping: the seed clears the admin store-URL settings via
// `php artisan tinker` (AppSetting::put also invalidates the 5-min settings
// cache), which is the default state — with no URL configured the badges render
// as <button>s that dispatch the `open-store-coming-soon` window event instead
// of <a> links to the stores.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points
// it at its ephemeral server, since localhost:80 hits the Express api-server).

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);
const CONSENT_COOKIE = "1inme_cookie_consent";
const MODAL_LABEL = "Sayzio mobile app — coming soon";

function seedStoreUrlsUnconfigured(): void {
  // NOTE: passed straight to `tinker --execute=`. `\\` in this JS template
  // literal becomes the single backslash PHP namespaces need; do NOT write
  // `\\$` (yields invalid `\$var` PHP — psysh ParseErrorException).
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
AppSetting::put('marketing_play_store_url', '');
AppSetting::put('marketing_app_store_url', '');
echo 'OK_STORE_URLS_CLEARED';
`.trim();
  execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    stdio: "inherit",
  });
}

/**
 * Load the home page with consent already given (so the bottom-pinned cookie
 * banner can't cover the footer badges or intercept clicks) and wait for
 * Alpine — the modal only exists in the DOM once Alpine processes the
 * x-teleport template. reducedMotion is set per-describe below so the
 * `.reveal` entrance animations resolve to their settled opacity-1 state.
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
}

/** The single (@once) coming-soon dialog, teleported under <body>. */
function dialog(page: Page): Locator {
  return page.locator(`[role="dialog"][aria-label="${MODAL_LABEL}"]`);
}

/**
 * Assert the modal is genuinely OPEN and usable — the exact opposite of the
 * "blank popup" failure mode:
 *  - the dialog overlay is a direct child of <body> (the teleport invariant;
 *    if it ever renders inside a transformed section again, this fails first),
 *  - the card is visible, opacity 1, with real dimensions,
 *  - the card sits fully inside the viewport (not shoved off-screen or
 *    clipped by an ancestor),
 *  - the card's center actually hit-tests to the card (nothing painting over
 *    it, not invisible to the layout engine).
 */
async function expectModalOpenAndCentered(page: Page): Promise<void> {
  const overlay = dialog(page);
  await expect(overlay).toBeVisible();

  const card = overlay.locator(".store-cs-card");
  await expect(card).toBeVisible();

  const s = await page.evaluate((label) => {
    const overlayEl = document.querySelector(
      `[role="dialog"][aria-label="${label}"]`,
    );
    const cardEl = overlayEl?.querySelector(".store-cs-card");
    if (!overlayEl || !cardEl) return null;
    const rect = cardEl.getBoundingClientRect();
    // Effective opacity: any ancestor stuck at opacity 0 (the marketing
    // reveal stylesheet's signature failure) makes the card invisible even
    // when its own opacity is 1.
    let effectiveOpacity = 1;
    let el: Element | null = cardEl;
    while (el instanceof Element) {
      effectiveOpacity *= parseFloat(getComputedStyle(el).opacity || "1");
      el = el.parentElement;
    }
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;
    const hit = document.elementFromPoint(cx, cy);
    return {
      overlayParentIsBody: overlayEl.parentElement === document.body,
      rect: {
        top: rect.top,
        left: rect.left,
        bottom: rect.bottom,
        right: rect.right,
        width: rect.width,
        height: rect.height,
      },
      effectiveOpacity,
      centerHitsCard: !!hit && (hit === cardEl || cardEl.contains(hit)),
      innerWidth: window.innerWidth,
      innerHeight: window.innerHeight,
    };
  }, MODAL_LABEL);

  expect(s).not.toBeNull();
  if (!s) return;

  // Teleport invariant: the overlay must live directly under <body>, never
  // inside whatever section included the store-buttons partial.
  expect(s.overlayParentIsBody).toBe(true);

  // Real, visible card…
  expect(s.rect.width).toBeGreaterThan(200);
  expect(s.rect.height).toBeGreaterThan(100);
  expect(s.effectiveOpacity).toBeGreaterThan(0.95);

  // …fully inside the viewport (small tolerance for sub-pixel rounding).
  expect(s.rect.top).toBeGreaterThanOrEqual(-2);
  expect(s.rect.left).toBeGreaterThanOrEqual(-2);
  expect(s.rect.bottom).toBeLessThanOrEqual(s.innerHeight + 2);
  expect(s.rect.right).toBeLessThanOrEqual(s.innerWidth + 2);

  // Hit-testable at its center — a card that is technically "visible" but
  // painted over / zero-stacked would fail here.
  expect(s.centerHitsCard).toBe(true);
}

async function closeModalAndExpectGone(page: Page): Promise<void> {
  const overlay = dialog(page);
  await overlay.getByRole("button", { name: "Close" }).click();
  await expect(overlay).toBeHidden();
}

test.describe("store-badge coming-soon modal — never blank/trapped", () => {
  // Freeze the marketing entrance animations at their settled state so the
  // dialer section's `.reveal` wrappers rest at opacity 1 and the badges are
  // deterministically clickable (home.blade.php's reduced-motion block).
  test.use({ reducedMotion: "reduce" });

  test.beforeAll(() => {
    seedStoreUrlsUnconfigured();
  });

  test("dialer-section Play badge opens a visible, in-viewport modal; close dismisses it", async ({
    page,
  }) => {
    await gotoHome(page);

    // The dialer section renders the partial inside animated `.reveal`
    // wrappers — the exact context that originally trapped the modal. With no
    // store URL configured the Play badge is a <button>, not an <a>.
    const badge = page
      .locator("#dialer-contacts")
      .getByRole("button", { name: /Google Play/ });
    await badge.scrollIntoViewIfNeeded();
    await expect(badge).toBeVisible();
    await badge.click();

    await expectModalOpenAndCentered(page);
    // Store-aware headline: the Play badge must open the Google Play copy.
    await expect(dialog(page).locator("h3")).toContainText(
      "coming to Google Play",
    );

    await closeModalAndExpectGone(page);
  });

  test("footer Play badge opens the same modal; close dismisses it", async ({
    page,
  }) => {
    await gotoHome(page);

    const badge = page
      .locator("footer")
      .getByRole("button", { name: /Google Play/ });
    await badge.scrollIntoViewIfNeeded();
    await expect(badge).toBeVisible();
    await badge.click();

    await expectModalOpenAndCentered(page);
    await closeModalAndExpectGone(page);

    // Same page, other store: the footer App Store badge re-opens the shared
    // (@once) modal with the App Store copy — proving the single teleported
    // modal serves every include context, not just the first.
    const appBadge = page
      .locator("footer")
      .getByRole("button", { name: /App Store/ });
    await appBadge.click();
    await expectModalOpenAndCentered(page);
    await expect(dialog(page).locator("h3")).toContainText(
      "coming to the App Store",
    );
    await closeModalAndExpectGone(page);
  });
});
