import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Locator, type Page } from "@playwright/test";

// Regression guard for the show/hide password toggle rendered by the shared
// partial resources/views/common/partials/password-field.blade.php.
//
// WHY a browser spec: the toggle is driven entirely by Alpine.js x-data
// (`_pwShow`) — the input's `:type`, the button's `:aria-label` /
// `:aria-pressed`, and the eye icon's `:class` are all client-side bindings.
// A typo'd variable name, a broken Alpine scope (e.g. an outer x-data
// swallowing the partial's), or a bad icon binding compiles fine and renders
// fine, and only fails when a real browser evaluates the bindings. This spec
// clicks the actual toggle in Chromium and asserts the observable contract.
//
// Surfaces covered (every guest-facing include of the partial):
//   - /user/login    — the "password" method form (one field)
//   - /user/register — password + confirm-password (two INDEPENDENT fields:
//     each include gets its own x-data scope, so toggling one must not flip
//     the other)
//   - home-page auth modal (public/partials/auth-modal.blade.php) — the same
//     partial included three times inside the pop-up sign-in window. The modal
//     nests the partial under the header's big x-data (authOpen/authTab) plus
//     the login panel's own `{ method }` scope, so a scope clash there is a
//     DISTINCT way to break the toggle that the standalone pages can't catch.
//
// Contract asserted per field, through a full show -> hide round trip:
//   - input `type` flips password -> text -> password
//   - button `aria-label` flips "Show password" -> "Hide password" -> back
//   - button `aria-pressed` flips "false" -> "true" -> "false"
//   - the icon inside the button swaps fa-eye <-> fa-eye-slash
//
// Design notes (shared with the sibling auth specs):
//   - baseURL comes from APP_URL (the runner points it at the ephemeral e2e
//     server; localhost:80 hits the Express api-server instead).
//   - beforeAll pins the admin auth toggles this UI depends on: the password
//     method must be enabled or the login form hides its password tab and the
//     register page omits both password fields entirely.
//   - No user fixtures are needed — both pages are guest pages and we never
//     submit a form.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/** Run a `php artisan tinker` snippet, retrying transient RDS blips. */
function runTinker(php: string): string {
  let lastErr: unknown;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
        cwd: ARTIFACT_ROOT,
        encoding: "utf8",
      });
    } catch (err) {
      lastErr = err;
    }
  }
  throw lastErr;
}

test.beforeAll(() => {
  // Pin the auth-method settings the password UI depends on. Without
  // email+password enabled the login page hides the password form and the
  // register page renders no password fields at all, and this spec would
  // silently test nothing.
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;

AppSetting::put('auth_email_password_enabled', true);
AppSetting::put('auth_registration_paused', false);

echo 'PW_TOGGLE_SETTINGS_OK';
`.trim();
  const out = runTinker(php);
  if (!out.includes("PW_TOGGLE_SETTINGS_OK")) {
    throw new Error("password-toggle settings pin failed, output:\n" + out);
  }
});

/**
 * The partial wraps each password input + its toggle button in a `._pw-wrap`
 * div with its own x-data scope; scoping all lookups to that wrapper keeps
 * the field and ITS button paired even when a page has several fields.
 */
function pwWrap(page: Page, inputName: string): Locator {
  return page.locator(`._pw-wrap:has(input[name="${inputName}"])`);
}

/**
 * Assert the full toggle contract for one field: initial hidden state, click
 * to reveal, click again to re-hide — checking type, aria-label, aria-pressed
 * and the eye icon class at every step.
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

test.describe("password show/hide toggle", () => {
  test("login page: toggle reveals and re-hides the password", async ({ page }) => {
    await page.goto("/user/login");

    // When more than one sign-in method is enabled the password form sits
    // behind a "Password" method tab (x-show). Click it if it's rendered;
    // in password-only mode the form is already visible.
    const passwordTab = page.locator('button:has-text("Password")').first();
    if (await passwordTab.isVisible().catch(() => false)) {
      await passwordTab.click();
    }

    await assertToggleRoundTrip(pwWrap(page, "password"), "password");
  });

  test("register page: password and confirm fields toggle independently", async ({ page }) => {
    await page.goto("/user/register");

    const password = pwWrap(page, "password");
    const confirm = pwWrap(page, "password_confirmation");

    // Each field passes the full round trip on its own.
    await assertToggleRoundTrip(password, "password");
    await assertToggleRoundTrip(confirm, "password_confirmation");

    // Independence: each include owns its OWN x-data scope, so revealing one
    // field must leave the other masked (a broken/shared Alpine scope would
    // flip both together).
    await password.locator("button").click();
    await expect(password.locator("input")).toHaveAttribute("type", "text");
    await expect(confirm.locator("input")).toHaveAttribute("type", "password");
    await expect(confirm.locator("button")).toHaveAttribute("aria-pressed", "false");

    await confirm.locator("button").click();
    await expect(confirm.locator("input")).toHaveAttribute("type", "text");
    // And back: hiding the first must not hide the second.
    await password.locator("button").click();
    await expect(password.locator("input")).toHaveAttribute("type", "password");
    await expect(confirm.locator("input")).toHaveAttribute("type", "text");
  });
});

// ---------------------------------------------------------------------------
// Home-page auth modal (public/partials/auth-modal.blade.php)
//
// The pop-up sign-in window includes the same partial three times: once in the
// login tab's password form and twice (password + confirm) in the register
// form. Unlike the standalone pages, every include here sits inside the
// header's big x-data (authOpen/authTab/authHandle/…) and — for the login one —
// the login panel's own `{ method }` x-data too. A clash between those outer
// scopes and the partial's `_pwShow` (e.g. the outer scope declaring/absorbing
// `_pwShow`, or a refactor flattening the nested x-data) would break the
// toggle ONLY in the modal, so it needs its own coverage.
// ---------------------------------------------------------------------------

const CONSENT_COOKIE = "1inme_cookie_consent";

/**
 * Load the home page with cookie consent pre-granted (the bottom-pinned
 * consent banner would otherwise intercept clicks) and wait for Alpine —
 * both the modal itself and the toggle under test are Alpine-driven.
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

/** The auth popup, scoped by its dialog aria-label (see auth-modal.blade.php). */
function authModal(page: Page): Locator {
  return page.getByRole("dialog", { name: "Sign in or create an account" });
}

/**
 * Open the modal via the desktop header CTA and land on the requested tab.
 * (The specs run on Playwright's default desktop viewport, so the nav
 * Login/Register buttons are visible — no hamburger menu needed.)
 */
async function openAuthModal(page: Page, tab: "Login" | "Register"): Promise<void> {
  await page.getByRole("button", { name: tab, exact: true }).first().click();
  await expect(authModal(page)).toBeVisible();
}

/**
 * Scope a `._pw-wrap` lookup to a specific form inside the modal. Needed
 * because the login and register forms BOTH contain an input named
 * "password" — pwWrap() alone would be ambiguous inside the dialog.
 */
function modalPwWrap(form: Locator, inputName: string): Locator {
  return form.locator(`._pw-wrap:has(input[name="${inputName}"])`);
}

test.describe("password show/hide toggle — home-page auth modal", () => {
  test.beforeEach(({}, testInfo) => {
    // Cold first paint of the heavy home page over the distant RDS is slow.
    testInfo.setTimeout(90_000);
  });

  test("login tab: toggle works on the password field", async ({ page }) => {
    await gotoHome(page);
    await openAuthModal(page, "Login");

    const dialog = authModal(page);

    // With multiple sign-in methods enabled the password form sits behind a
    // "Password" method chip inside the dialog; click it if it's rendered
    // (in password-only mode the form is already the visible one).
    const passwordChip = dialog.locator('button:has-text("Password")').first();
    if (await passwordChip.isVisible().catch(() => false)) {
      await passwordChip.click();
    }

    const loginForm = dialog.locator('form:has(input[name="login_method"][value="password"])');
    await assertToggleRoundTrip(modalPwWrap(loginForm, "password"), "password");
  });

  test("register tab: password and confirm fields toggle independently", async ({ page }) => {
    await gotoHome(page);
    await openAuthModal(page, "Register");

    const dialog = authModal(page);
    // The register form is the one carrying the desired_handle claim input.
    const registerForm = dialog.locator('form:has(input[name="desired_handle"])');
    await expect(registerForm.locator('input[name="name"]')).toBeVisible();

    const password = modalPwWrap(registerForm, "password");
    const confirm = modalPwWrap(registerForm, "password_confirmation");

    await assertToggleRoundTrip(password, "password");
    await assertToggleRoundTrip(confirm, "password_confirmation");

    // Independence across the two includes: each owns its OWN x-data scope,
    // so revealing one must not flip the other (a shared/hoisted `_pwShow`
    // in the modal's outer Alpine scope would flip both together).
    await password.locator("button").click();
    await expect(password.locator("input")).toHaveAttribute("type", "text");
    await expect(confirm.locator("input")).toHaveAttribute("type", "password");
    await expect(confirm.locator("button")).toHaveAttribute("aria-pressed", "false");

    await confirm.locator("button").click();
    await expect(confirm.locator("input")).toHaveAttribute("type", "text");
    await password.locator("button").click();
    await expect(password.locator("input")).toHaveAttribute("type", "password");
    await expect(confirm.locator("input")).toHaveAttribute("type", "text");
  });
});
