import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { expect, test, type Locator, type Page } from "@playwright/test";

// Guards the home-page auth popup's "reopen on the tab you were on" behaviour
// (task: "Confirm signup errors in the popup reopen on the tab the user was
// on"). The modal (public/partials/auth-modal.blade.php) has Login / Sign up
// tabs plus Email / WhatsApp sub-tabs on the Sign up side, and a small reopen
// script keyed off old('name') / old('intent')==='signup' that restores the
// right tab after a failed (non-AJAX) submit round-trips through the server.
// If a future modal edit breaks that script, validation errors land behind the
// wrong tab and signups silently look "broken". These tests pin:
//   1. Invalid email signup  → popup reopens on Sign up → Email, errors shown.
//   2. Invalid WhatsApp signup → popup reopens on Sign up → WhatsApp.
//   3. Failed login → popup reopens on the Login tab.

const CONSENT_COOKIE = "1inme_cookie_consent";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Per-run unique suffix so nothing collides across parallel task envs on the
// shared RDS (no rows are actually created here — every submit fails
// validation — but keep emails unique anyway).
const RUN = `${Date.now().toString(36)}${Math.floor(Math.random() * 1e4)}`;

/**
 * Run a `php artisan tinker` snippet, retrying on a transient failure. Over
 * the distant RDS the tinker process occasionally fails to connect with no
 * PHP error; a couple of quick retries absorb that blip while a genuine PHP
 * error fails every attempt and is surfaced. (Mirrors the sibling specs.)
 */
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

// The WhatsApp Sign up sub-tab only renders when an admin has enabled mobile
// login (AppSetting auth_mobile_login_enabled). Enable it for the run and
// restore the prior state afterwards so the shared setting isn't left flipped.
let mobileWasEnabled = false;
let mobileRowExisted = false;

test.beforeAll(() => {
  const out = runTinker(`
use App\\Modules\\Admin\\Models\\AppSetting;
$key = \\App\\Modules\\Common\\Support\\AuthMethods::SETTING_MOBILE_ENABLED;
$row = AppSetting::where('key', $key)->first();
echo 'PRIOR:' . ($row ? ($row->value ? '1' : '0') : 'none') . PHP_EOL;
AppSetting::put($key, true);
echo 'MOBILE_ENABLED_OK' . PHP_EOL;
`);
  expect(out).toContain("MOBILE_ENABLED_OK");
  mobileRowExisted = !out.includes("PRIOR:none");
  mobileWasEnabled = out.includes("PRIOR:1");
});

test.afterAll(() => {
  runTinker(`
use App\\Modules\\Admin\\Models\\AppSetting;
use Illuminate\\Support\\Facades\\Cache;
$key = \\App\\Modules\\Common\\Support\\AuthMethods::SETTING_MOBILE_ENABLED;
${
    mobileRowExisted
      ? `AppSetting::put($key, ${mobileWasEnabled ? "true" : "false"});`
      : `AppSetting::where('key', $key)->delete(); Cache::forget('app_setting:' . $key);`
  }
echo 'RESTORED' . PHP_EOL;
`);
});

/**
 * Load the home page with consent already given (so the bottom-pinned cookie
 * banner can't intercept clicks) and wait for Alpine — the modal open/close
 * and tab switching are all Alpine-driven.
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

/** The auth popup, scoped by its dialog aria-label. */
function modal(page: Page): Locator {
  return page.getByRole("dialog", { name: "Sign in or create an account" });
}

/** The Sign up tab's Email register form (posts to user.register.submit). */
function emailRegisterForm(page: Page): Locator {
  return modal(page).locator('form[action*="register"]');
}

/**
 * The Sign up tab's WhatsApp signup form — the only form in the modal
 * carrying intent=signup.
 */
function whatsappRegisterForm(page: Page): Locator {
  return modal(page)
    .locator("form")
    .filter({ has: page.locator('input[name="intent"][value="signup"]') });
}

/** The Login tab's email+password form (login_method=password). */
function passwordLoginForm(page: Page): Locator {
  return modal(page)
    .locator("form")
    .filter({
      has: page.locator('input[name="login_method"][value="password"]'),
    });
}

/**
 * Submit a modal form and wait for the failed POST to round-trip. The server
 * redirects BACK TO `/` (the same URL the page is already on), so
 * page.waitForURL is a no-op here — wait on the POST response instead, then
 * let the error-box assertion below auto-wait for the reloaded DOM.
 */
async function submitAndAwaitRoundTrip(
  page: Page,
  submit: Locator,
  postPathPart: string,
): Promise<void> {
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().includes(postPathPart) && r.request().method() === "POST",
      // Cold RDS POSTs can be slow; give the round trip real headroom.
      { timeout: 45_000 },
    ),
    submit.click(),
  ]);
}

/**
 * After the failed-submit round trip the modal's reopen script
 * (DOMContentLoaded) restores the popup with the shared error box — which
 * only exists on the reloaded page, so asserting it also proves the new DOM.
 */
async function expectReopenedWithErrors(page: Page): Promise<Locator> {
  const dialog = modal(page);
  await expect(dialog).toBeVisible();
  // The shared error box at the bottom of the form panel.
  const errors = dialog.locator(".bg-red-500\\/10");
  await expect(errors).toBeVisible();
  return errors;
}

test.describe("auth popup reopens on the tab the failed submit came from", () => {
  test.beforeEach(async ({ page }) => {
    // Distant RDS makes the first paint slow; give each test headroom.
    test.setTimeout(90_000);
    await gotoHome(page);
  });

  test("invalid email signup reopens on Sign up → Email with errors visible", async ({
    page,
  }) => {
    await page
      .getByRole("button", { name: "Register", exact: true })
      .first()
      .click();
    const form = emailRegisterForm(page);
    await expect(form).toBeVisible();

    // Passes browser-native validation (all required fields filled, valid
    // email shape) but fails server-side: the password confirmation doesn't
    // match, so the submit round-trips back with errors and old('name') set.
    await form.locator('input[name="name"]').fill("Tab Restore Test");
    await form
      .locator('input[name="email"]')
      .fill(`tab-restore-${RUN}@example.com`);
    await form.locator('input[name="password"]').fill("longenough1");
    await form
      .locator('input[name="password_confirmation"]')
      .fill("different-one2");
    await submitAndAwaitRoundTrip(page, form.getByRole("button", { name: /Create account/ }), "/register");

    const errors = await expectReopenedWithErrors(page);
    await expect(errors).toContainText(/password/i);

    // Reopened on the Sign up tab, Email sub-tab: the email register form is
    // visible, the WhatsApp signup form and the login form are not.
    await expect(emailRegisterForm(page)).toBeVisible();
    await expect(whatsappRegisterForm(page)).toBeHidden();
    await expect(passwordLoginForm(page)).toBeHidden();

    // The typed name survived the round trip (old('name') repopulation).
    await expect(emailRegisterForm(page).locator('input[name="name"]')).toHaveValue(
      "Tab Restore Test",
    );
  });

  test("invalid WhatsApp signup reopens on Sign up → WhatsApp with errors visible", async ({
    page,
  }) => {
    await page
      .getByRole("button", { name: "Register", exact: true })
      .first()
      .click();
    await expect(emailRegisterForm(page)).toBeVisible();

    // Switch to the WhatsApp sub-tab (rendered because mobile login is on).
    // NOTE: the Font Awesome glyph (::before content) is part of the button's
    // accessible name, so an exact-name match never resolves — match by
    // substring instead. Only the sub-tab is visible here (the Login tab's
    // WhatsApp method toggle and the "Sign up with WhatsApp" submit are both
    // hidden, so getByRole excludes them).
    await modal(page).getByRole("button", { name: /WhatsApp/ }).click();
    const waForm = whatsappRegisterForm(page);
    await expect(waForm).toBeVisible();

    // A number whose country code is never in the allow-list (defaults are
    // +91/+1), so the server bounces it back with an error and
    // old('intent')==='signup' restores Sign up → WhatsApp.
    await waForm.locator('input[name="identifier"]').fill("+99912345678");
    await submitAndAwaitRoundTrip(
      page,
      waForm.getByRole("button", { name: /Sign up with WhatsApp/ }),
      "/send-otp",
    );

    const errors = await expectReopenedWithErrors(page);
    await expect(errors).toContainText(/country code/i);

    await expect(whatsappRegisterForm(page)).toBeVisible();
    await expect(emailRegisterForm(page)).toBeHidden();
    await expect(passwordLoginForm(page)).toBeHidden();
  });

  test("modal forms are classic full-page submits (no AJAX layer)", async ({
    page,
  }) => {
    // The tab-restore contract above relies on the failed submit round-tripping
    // through the server so old() / $errors drive the reopen script. The
    // fetch-based enhancement layer (resources/js/auth-ajax.js) is opt-in via a
    // data-ajax attribute and is only used on the standalone auth pages (covered
    // by auth-ajax-flows.spec.ts) — it renders errors client-side with no old()
    // state, which would silently bypass tab restore. Pin that no modal form
    // opts in; if someone adds data-ajax here, they must also port tab memory
    // to the client side and extend these specs.
    await page.getByRole("button", { name: "Login", exact: true }).first().click();
    await expect(modal(page)).toBeVisible();
    const forms = modal(page).locator("form");
    const count = await forms.count();
    expect(count).toBeGreaterThan(0);
    for (let i = 0; i < count; i++) {
      const form = forms.nth(i);
      await expect(form).not.toHaveAttribute("data-ajax", /.*/);
      // Classic POST submits, not javascript: or fetch-driven placeholders.
      await expect(form).toHaveAttribute("method", /post/i);
    }
  });

  test("failed login stays on the Login tab", async ({ page }) => {
    await page
      .getByRole("button", { name: "Login", exact: true })
      .first()
      .click();
    const loginForm = passwordLoginForm(page);
    await expect(loginForm).toBeVisible();

    await loginForm
      .locator('input[name="email"]')
      .fill(`nobody-${RUN}@example.com`);
    await loginForm.locator('input[name="password"]').fill("wrong-password-1");
    await submitAndAwaitRoundTrip(
      page,
      loginForm.getByRole("button", { name: /Sign In/ }),
      "/login",
    );

    const errors = await expectReopenedWithErrors(page);
    await expect(errors).toContainText(/Invalid email or password/i);

    // Reopened on the Login tab — the register forms stay hidden.
    await expect(passwordLoginForm(page)).toBeVisible();
    await expect(emailRegisterForm(page)).toBeHidden();
    await expect(whatsappRegisterForm(page)).toBeHidden();
  });
});
