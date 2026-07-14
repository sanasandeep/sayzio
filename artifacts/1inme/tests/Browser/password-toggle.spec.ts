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
