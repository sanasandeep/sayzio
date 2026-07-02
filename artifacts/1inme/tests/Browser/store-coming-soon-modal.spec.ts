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
// real layout engine. These tests pin the invariant for ALL include contexts of
// the partial (the homepage dialer section, the dedicated Dialer & Contacts page
// section, and the shared footer):
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
const MODAL_LABEL = "Sayzio and Dialer apps — coming soon";

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
 * Count app-launch signup rows for an email, straight from the real DB via
 * tinker. Used by the notify-form tests to prove the end-to-end effect the
 * browser can't otherwise see: a valid submit actually persists a row, while a
 * honeypot-tripped submit persists NOTHING even though the UI shows "success".
 * The controller lowercases the stored email, so we compare lowercased.
 */
function countSignups(email: string): number {
  const php = `
use App\\Modules\\Common\\Models\\AppLaunchSignup;
echo 'COUNT:' . AppLaunchSignup::where('email', strtolower('${email}'))->count() . ':END';
`.trim();
  const out = execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
  const m = out.match(/COUNT:(\d+):END/);
  return m ? parseInt(m[1], 10) : -1;
}

/**
 * Remove any rows created by the notify-form tests so repeated local runs and
 * the shared RDS don't accumulate throwaway signups (and so a later run can't
 * hit the duplicate-email path for a reused address).
 */
function deleteNotifyTestSignups(): void {
  const php = `
use App\\Modules\\Common\\Models\\AppLaunchSignup;
AppLaunchSignup::where('email', 'like', 'browser-notify-%@example.com')
    ->orWhere('email', 'like', 'browser-honeypot-%@example.com')
    ->delete();
echo 'OK_NOTIFY_SIGNUPS_CLEARED';
`.trim();
  execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    stdio: "inherit",
  });
}

/**
 * Navigate to `path` with consent already given (so the bottom-pinned cookie
 * banner cannot cover footer badges or intercept clicks) and wait for Alpine
 * to both load AND finish processing the x-teleport template so the modal is
 * actually attached to <body> before any badge is clicked.
 *
 * Waiting for `window.Alpine` alone is not sufficient: Alpine.start() is
 * called synchronously after the Alpine IIFE sets window.Alpine, but
 * Playwright's waitForFunction polling resolves as soon as the condition is
 * truthy — which can happen in a CDP round-trip that lands before the
 * queueMicrotask-scheduled init queue drains. The extra waitForSelector on the
 * teleported dialog element (state:'attached') is the definitive gate: it
 * resolves only once Alpine has appended the cloned template content to <body>
 * and set up the @open-store-coming-soon.window listener. This makes every
 * badge click reliable regardless of CPU load or VM scheduling jitter.
 */
async function gotoPageWithConsent(page: Page, path: string): Promise<void> {
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
  await page.goto(path, { waitUntil: "domcontentloaded" });
  await page.waitForFunction(
    () => !!(window as unknown as { Alpine?: unknown }).Alpine,
  );
  // Definitive Alpine-init gate: wait until the x-teleport has fired and the
  // modal div is attached to <body>. Without this the window event can fire
  // before the @open-store-coming-soon listener is registered, silently
  // dropping the open signal.
  await page.waitForSelector(
    `[role="dialog"][aria-label="${MODAL_LABEL}"]`,
    { state: "attached" },
  );
}

/**
 * Load the home page with consent already given and wait for Alpine —
 * the modal only exists in the DOM once Alpine processes the x-teleport
 * template. reducedMotion is set per-describe below so the `.reveal`
 * entrance animations resolve to their settled opacity-1 state.
 */
async function gotoHome(page: Page): Promise<void> {
  await gotoPageWithConsent(page, "/");
}

/**
 * Load the dedicated Dialer & Contacts page with consent given and wait for
 * Alpine. On this page the @once block fires in the #dcp-store section (first
 * include), so the modal is teleported from there — not from the footer.
 */
async function gotoDialerContacts(page: Page): Promise<void> {
  await gotoPageWithConsent(page, "/dialer-contacts");
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

/**
 * Pin the two-app ("Sayzio" + "Dialer") copy and the real screenshot so the
 * modal can't silently regress to the old single-app text or a blank mockup:
 *  - the headline names one of the apps (Sayzio/Dialer), not just the
 *    store-name suffix ("coming to Google Play"),
 *  - the phone frame holds an actual <img.store-cs-screenshot> that DECODED
 *    (naturalWidth > 0) — a missing/broken asset or a CSS-only mockup fails,
 *  - at least one feature bullet mentions the Dialer app ("dialer"/"caller").
 */
async function expectDialerCopyAndScreenshot(page: Page): Promise<void> {
  const overlay = dialog(page);

  // Headline names an app, not just the store suffix.
  await expect(overlay.locator("h3")).toContainText(/Sayzio|Dialer/i);

  // The screenshot exists inside the phone frame and actually loaded.
  const screenshot = overlay.locator(".store-cs-phone img.store-cs-screenshot");
  await expect(screenshot).toHaveCount(1);
  await expect
    .poll(() =>
      screenshot.evaluate(
        (img) => (img as HTMLImageElement).naturalWidth,
      ),
    )
    .toBeGreaterThan(0);

  // At least one feature bullet pins the Dialer-app copy.
  const dialerFeature = overlay
    .locator(".store-cs-feat")
    .filter({ hasText: /dialer|caller/i });
  await expect(dialerFeature.first()).toBeVisible();
}

test.describe("store-badge coming-soon modal — never blank/trapped", () => {
  // Freeze the marketing entrance animations at their settled state so the
  // dialer section's `.reveal` wrappers rest at opacity 1 and the badges are
  // deterministically clickable (home.blade.php's reduced-motion block).
  // A realistic desktop viewport: the two-column modal only renders at sm+
  // widths, and the taller "coming to the App Store" headline wraps to an extra
  // line — pushing the card just past a 720px-tall viewport. 900px is a normal
  // desktop height and keeps the strict "fully inside the viewport" invariant
  // meaningful for BOTH store variants (the modal is genuinely scrollable, so a
  // card that overflows a short viewport is by design, not the off-screen bug).
  test.use({ reducedMotion: "reduce", viewport: { width: 1280, height: 900 } });

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
    // Two-app copy + real screenshot must not regress.
    await expectDialerCopyAndScreenshot(page);

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
    // Store-aware headline: Play badge opens the Google Play copy.
    await expect(dialog(page).locator("h3")).toContainText(
      "coming to Google Play",
    );
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

  test("dedicated /dialer-contacts page — section badge opens modal; footer badge switches store copy", async ({
    page,
  }) => {
    await gotoDialerContacts(page);

    // The #dcp-store section on the dedicated page includes the partial in its
    // own content (first include on this page, so @once fires here).  With no
    // store URL configured these are <button>s, not <a> links.
    const sectionBadge = page
      .locator("#dcp-store")
      .getByRole("button", { name: /Google Play/ });
    await sectionBadge.scrollIntoViewIfNeeded();
    await expect(sectionBadge).toBeVisible();
    await sectionBadge.click();

    await expectModalOpenAndCentered(page);
    await expect(dialog(page).locator("h3")).toContainText(
      "coming to Google Play",
    );
    await closeModalAndExpectGone(page);

    // Footer App Store badge on the same page: the @once modal (already
    // teleported from the content section) must re-open with App Store copy,
    // proving that the footer badges work even when @once skips the footer
    // include and only the content-section include rendered the teleport.
    const footerAppBadge = page
      .locator("footer")
      .getByRole("button", { name: /App Store/ });
    await footerAppBadge.scrollIntoViewIfNeeded();
    await expect(footerAppBadge).toBeVisible();
    await footerAppBadge.click();

    await expectModalOpenAndCentered(page);
    await expect(dialog(page).locator("h3")).toContainText(
      "coming to the App Store",
    );
    await closeModalAndExpectGone(page);
  });
});

// ---------------------------------------------------------------------------
// "Notify me at launch" email-capture form inside the coming-soon modal.
//
// The teleport/blank-modal suite above never touches the form. This suite
// exercises the Alpine notifySubmit() handler + honeypot end-to-end so a
// regression in the endpoint, CSRF wiring, client validation, or the inline
// success/error rendering can't silently drop launch-notification signups:
//  - an invalid email surfaces the inline validation error and sends NO request
//    (the client short-circuits before fetch),
//  - a valid email POSTs the real site.app-launch.notify route and flips to the
//    inline "you're on the list" done state (form hidden), AND actually writes a
//    row in the DB,
//  - the honeypot field is present but hidden from humans, and a honeypot-filled
//    submit is a server-side no-op (no row) even though the UI still shows the
//    friendly success state (so bots learn nothing).
//
// Self-bootstrapping: same seed as above (store URLs unconfigured → badges are
// <button>s that open the modal). Rows created here are cleaned up afterAll.
// ---------------------------------------------------------------------------
test.describe("store coming-soon modal — notify-me signup can't silently fail", () => {
  test.use({ reducedMotion: "reduce", viewport: { width: 1280, height: 900 } });

  test.beforeAll(() => {
    seedStoreUrlsUnconfigured();
  });

  test.afterAll(() => {
    deleteNotifyTestSignups();
  });

  /** Open the home dialer-section Play badge → the shared modal, ready to type. */
  async function openModal(page: Page): Promise<Locator> {
    await gotoHome(page);
    const badge = page
      .locator("#dialer-contacts")
      .getByRole("button", { name: /Google Play/ });
    await badge.scrollIntoViewIfNeeded();
    await expect(badge).toBeVisible();
    await badge.click();
    const overlay = dialog(page);
    await expect(overlay).toBeVisible();
    return overlay;
  }

  test("invalid email shows the inline error and sends no request", async ({
    page,
  }) => {
    // Track any POST to the notify endpoint — an invalid email must never
    // reach the server (the client validates and short-circuits first).
    let notifyRequests = 0;
    page.on("request", (req) => {
      if (req.url().includes("/app-launch/notify")) notifyRequests++;
    });

    const overlay = await openModal(page);

    await overlay.locator("#store-cs-notify-email").fill("not-an-email");
    await overlay.getByRole("button", { name: "Notify me" }).click();

    // Inline validation error appears…
    const err = overlay.locator("p").filter({ hasText: /valid email/i });
    await expect(err).toBeVisible();

    // …the form is still shown (no success state) and nothing was sent.
    await expect(overlay.locator("form")).toBeVisible();
    await expect(overlay.locator(".store-cs-notify-done")).toBeHidden();
    expect(notifyRequests).toBe(0);
  });

  test("valid email posts the real route, flips to the done state, and writes a row", async ({
    page,
  }) => {
    const email = `browser-notify-${Date.now()}@example.com`;

    const overlay = await openModal(page);

    await overlay.locator("#store-cs-notify-email").fill(email);
    await overlay.getByRole("button", { name: "Notify me" }).click();

    // Inline success state renders (auto-waits through the async fetch) with
    // the confirmation copy, and the form is hidden.
    const done = overlay.locator(".store-cs-notify-done");
    await expect(done).toBeVisible();
    await expect(done).toContainText(/on the list/i);
    await expect(overlay.locator("form")).toBeHidden();

    // The signup actually persisted against the real endpoint.
    expect(countSignups(email)).toBe(1);
  });

  test("honeypot is present, hidden from humans, and a filled submit writes no row", async ({
    page,
  }) => {
    const email = `browser-honeypot-${Date.now()}@example.com`;

    const overlay = await openModal(page);

    // The honeypot exists and is genuinely hidden from humans: off-screen,
    // fully transparent, and removed from the tab order.
    const honeypot = overlay.locator('input[name="website"]');
    await expect(honeypot).toHaveCount(1);
    const hp = await honeypot.evaluate((el) => {
      const s = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return {
        offscreen: r.left < 0 || r.top < 0,
        opacity: parseFloat(s.opacity || "1"),
        tabindex: el.getAttribute("tabindex"),
      };
    });
    expect(hp.offscreen).toBe(true);
    expect(hp.opacity).toBeLessThan(0.05);
    expect(hp.tabindex).toBe("-1");

    // A bot fills the honeypot (set value + dispatch input so Alpine's x-model
    // picks it up), then submits a valid-looking email.
    await honeypot.evaluate((el) => {
      (el as HTMLInputElement).value = "http://spam.example";
      el.dispatchEvent(new Event("input", { bubbles: true }));
    });
    await overlay.locator("#store-cs-notify-email").fill(email);
    await overlay.getByRole("button", { name: "Notify me" }).click();

    // The UI still shows the friendly success state (bots learn nothing)…
    await expect(overlay.locator(".store-cs-notify-done")).toBeVisible();

    // …but the server no-oped: NO row was written despite the "success" UI.
    expect(countSignups(email)).toBe(0);
  });
});
