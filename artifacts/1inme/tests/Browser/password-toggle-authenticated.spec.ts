import { expect, test, type Locator, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Regression guard for the show/hide password toggle partial
// (resources/views/common/partials/password-field.blade.php) on LOGGED-IN
// surfaces. The sibling password-toggle.spec.ts covers every guest-facing
// include (/user/login, /user/register, home-page auth modal); a toggle
// regression on an authenticated page would sail past that spec and only
// surface to signed-in users, so the dashboard-side includes need their own
// coverage.
//
// The account Security tab (/user/settings/security → two-factor / devices /
// recent logins / merge) contains NO include of the partial — there is no
// change-password form there (password is set at register; login is
// password-or-OTP). The authenticated surfaces asserted here instead are:
//
//   - /user/settings/connections (Connected Accounts tab of the Settings
//     hub, user/social-accounts/index.blade.php): the manual "Access token"
//     field for OAuth platforms. This include sits inside the page's
//     platform-tab x-data (`{ tab }`) — an outer-scope clash with the
//     partial's `_pwShow` is exactly the failure mode the auth-modal tests
//     guard against on the guest side, so the settings hub gets the same
//     treatment.
//   - /user/links-url/create (create short link): the "Password protect this
//     link" field, nested under TWO Alpine gates (showAdvanced accordion +
//     passwordProtect checkbox) inside the form's own x-data — the deepest
//     nesting of the partial anywhere in the logged-in app.
//
// Contract asserted per field (same as the sibling spec): through a full
// show -> hide round trip the input `type`, button `aria-label` /
// `aria-pressed`, and the fa-eye/fa-eye-slash icon all flip together.
//
// Design notes:
//   - baseURL comes from APP_URL (run-validation.sh points it at the
//     ephemeral e2e server; localhost:80 hits the Express api-server).
//   - Auth via the shared loginAsDemo helper (synthesized CSRF POST to the
//     non-prod /user/demo-login route; see login-as-demo.ts for why we don't
//     click a demo button). The demo user owns its workspace, so the
//     settings.view / links.create gates these pages sit behind all pass.
//   - Cold first renders of authenticated pages over the distant RDS are
//     slow — per-test timeouts are generous and gotos use domcontentloaded.

/** Wrapper div the partial renders around each input + its toggle button. */
function pwWrap(scope: Page | Locator, inputName: string): Locator {
  return scope.locator(`._pw-wrap:has(input[name="${inputName}"])`);
}

/**
 * Assert the full toggle contract for one field: initial hidden state, click
 * to reveal, click again to re-hide — checking type, aria-label, aria-pressed
 * and the eye icon class at every step. (Mirrors the helper in the sibling
 * password-toggle.spec.ts; duplicated because importing a spec file would
 * re-register its tests.)
 */
async function assertToggleRoundTrip(wrap: Locator, inputName: string): Promise<void> {
  const input = wrap.locator(`input[name="${inputName}"]`);
  const button = wrap.locator("button");
  const icon = button.locator("i");

  // Initial state: masked, toggle announces "Show password", not pressed.
  await expect(input).toBeVisible();
  await expect(input).toHaveAttribute("type", "password");
  await expect(button).toHaveAttribute("aria-label", "Show password");
  await expect(button).toHaveAttribute("aria-pressed", "false");
  await expect(icon).toHaveClass(/fa-eye(?!-slash)/);

  // Reveal.
  await button.click();
  await expect(input).toHaveAttribute("type", "text");
  await expect(button).toHaveAttribute("aria-label", "Hide password");
  await expect(button).toHaveAttribute("aria-pressed", "true");
  await expect(icon).toHaveClass(/fa-eye-slash/);

  // Hide again — the round trip proves the binding isn't a one-shot fluke.
  await button.click();
  await expect(input).toHaveAttribute("type", "password");
  await expect(button).toHaveAttribute("aria-label", "Show password");
  await expect(button).toHaveAttribute("aria-pressed", "false");
  await expect(icon).toHaveClass(/fa-eye(?!-slash)/);
}

/** Navigate an authenticated page and wait for Alpine to boot. */
async function gotoApp(page: Page, path: string): Promise<void> {
  await page.goto(path, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await page.waitForFunction(() => !!(window as unknown as { Alpine?: unknown }).Alpine);
}

test.describe("password show/hide toggle — authenticated surfaces", () => {
  test.beforeEach(async ({ page }, testInfo) => {
    // Cold Blade renders over the distant RDS routinely exceed the default
    // 30s budget (login + one heavy page navigation per test).
    testInfo.setTimeout(180_000);
    await loginAsDemo(page);
  });

  test("settings hub Connected Accounts tab: access-token field toggles", async ({ page }) => {
    await gotoApp(page, "/user/settings/connections");

    // The "Connect a new account" card renders one hidden panel per platform
    // (x-show="tab === key"); switch to an OAuth-kind platform whose form
    // carries the manual token password-field. Instagram is the first
    // platform in PLATFORM_META so its tab button is always rendered.
    const card = page.locator("#new-connection");
    await expect(card).toBeVisible();
    await card.locator('button:has-text("Instagram")').first().click();

    // Scope to the Instagram store form — every OAuth platform's hidden
    // panel contains an identically-named access_token include.
    const form = card.locator('form:has(input[name="platform"][value="instagram"])');
    await assertToggleRoundTrip(pwWrap(form, "access_token"), "access_token");
  });

  test("create short link: password-protect field toggles under nested Alpine gates", async ({ page }) => {
    await gotoApp(page, "/user/links-url/create");

    // The field hides behind the Advanced Options accordion AND the
    // password-protect checkbox — open both.
    await page.locator('button:has-text("Advanced Options")').click();
    const protect = page.locator('input[name="is_password_protected"]');
    await expect(protect).toBeVisible();
    await protect.check();

    await assertToggleRoundTrip(pwWrap(page, "password"), "password");
  });
});
