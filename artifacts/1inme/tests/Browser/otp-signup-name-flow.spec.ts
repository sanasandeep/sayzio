import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

// End-to-end regression guard for the passwordless email-OTP SIGN-UP path and
// the mandatory display-name gate that follows it.
//
// The flow under test (all server-side, no client trust):
//   1. A brand-new (never-seen) email requests a one-time code from the login
//      page. Email OTP doubles as sign-up: `AuthController::sendOtp` issues a
//      code for the unknown address and (in demo-reveal mode) surfaces it in an
//      amber banner on the verify screen.
//   2. Verifying that code auto-creates a free account
//      (`createOtpSignupUser`), logs the user in, sets the `auth_needs_name`
//      session flag and redirects to `/user/complete-profile` — the mandatory
//      name form, because OTP sign-up collects no name.
//   3. Until a name is saved, `RequiresNameMiddleware` bounces EVERY protected
//      `/user/*` route back to the complete-profile form (except the form
//      itself + logout), so the user cannot navigate around it.
//   4. Saving a valid name clears the flag and drops the user into the app
//      (dashboard); protected routes then load normally.
//
// This is the "new OTP signup + mandatory name" flow. Social-OAuth (Google)
// sign-up hits the SAME `auth_needs_name` gate server-side, but its callback
// makes a real outbound HTTP call to Google that cannot be faked against a
// standalone `artisan serve`; that surface (new account -> name modal, skipped
// for returning) is exercised in the mobile harness
// (artifacts/1inme-mobile/scripts/test-auth-flow-e2e.mjs, e2e-mobile-google).
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points
// it at the ephemeral e2e server — localhost:80 hits the Express api-server —
// see the sibling specs). Each test uses a FRESH browser context (Playwright's
// default), so every case starts as a genuine guest.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Distinctive, per-run-unique test emails. Unique-per-run avoids collisions
// with a leftover row from an interrupted previous run on the SHARED RDS (see
// repo memory "e2e fixture aliases on shared RDS"); the shared prefix lets both
// the afterAll teardown and the beforeAll stale-prune target only this spec's
// accounts.
const EMAIL_PREFIX = "e2e-otp-signup-";
const RUN_TAG = `${Date.now()}-${Math.floor(Math.random() * 1e6)}`;
const SIGNUP_EMAIL = `${EMAIL_PREFIX}happy-${RUN_TAG}@example.com`;
const MIDDLEWARE_EMAIL = `${EMAIL_PREFIX}gate-${RUN_TAG}@example.com`;

/**
 * Run a `php artisan tinker` seed, retrying on a transient failure. Over the
 * distant RDS the tinker process occasionally fails to connect with a hard
 * "Command failed" and no PHP error; a couple of quick retries absorb that
 * blip, while a genuine PHP error fails every attempt and is surfaced.
 */
function runTinkerSeed(php: string): string {
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

/**
 * Make the passwordless email-OTP sign-up path deterministic:
 *   - email one-time-code login ON,
 *   - demo-reveal ON (so the verify screen shows the code we must type),
 *   - registration NOT paused (auto-create at verify time is allowed).
 * These are the framework defaults, but pinning them here keeps the spec
 * self-bootstrapping regardless of what an admin toggled in this environment.
 * Also prunes any stale accounts this spec created on a previous run so a
 * crashed teardown can't leave the shared RDS accumulating test users.
 */
function seedAuthSettings(): void {
  // NOTE: this string is passed straight to `tinker --execute=`. In a JS
  // template literal, `\\` becomes the single backslash PHP namespaces need,
  // while `$var` stays literal.
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Workspace;
use Illuminate\\Support\\Facades\\DB;

AppSetting::put('auth_email_otp_enabled', true);
AppSetting::put('auth_demo_reveal_otp_enabled', true);
AppSetting::put('auth_registration_paused', false);

// Prune leftover accounts from any interrupted previous run of THIS spec
// (dependency order: members -> workspaces -> otps -> users).
$stale = User::where('email', 'like', '${EMAIL_PREFIX}%')->pluck('id')->all();
if (!empty($stale)) {
  $wsIds = Workspace::whereIn('owner_user_id', $stale)->pluck('id')->all();
  if (!empty($wsIds)) { DB::table('workspace_members')->whereIn('workspace_id', $wsIds)->delete(); }
  DB::table('workspace_members')->whereIn('user_id', $stale)->delete();
  Workspace::whereIn('owner_user_id', $stale)->delete();
  DB::table('linked_identifiers')->whereIn('user_id', $stale)->delete();
  User::whereIn('id', $stale)->delete();
}
DB::table('otps')->where('identifier', 'like', '${EMAIL_PREFIX}%')->delete();

echo 'AUTH_SEED_OK';
`.trim();

  const out = runTinkerSeed(php);
  if (!out.includes("AUTH_SEED_OK")) {
    throw new Error("Auth-settings seed failed, output:\n" + out);
  }
}

/**
 * Remove every account this spec created (both emails), plus their workspaces,
 * memberships, linked identifiers, and any issued OTP rows, in FK-dependency
 * order. Best-effort: teardown failures never fail the suite (the beforeAll
 * prune will mop up next run), so we don't throw on a non-OK marker.
 */
function cleanupCreatedUsers(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Workspace;
use Illuminate\\Support\\Facades\\DB;

$emails = ['${SIGNUP_EMAIL}', '${MIDDLEWARE_EMAIL}'];
$ids = User::whereIn('email', $emails)->pluck('id')->all();
if (!empty($ids)) {
  $wsIds = Workspace::whereIn('owner_user_id', $ids)->pluck('id')->all();
  if (!empty($wsIds)) { DB::table('workspace_members')->whereIn('workspace_id', $wsIds)->delete(); }
  DB::table('workspace_members')->whereIn('user_id', $ids)->delete();
  Workspace::whereIn('owner_user_id', $ids)->delete();
  DB::table('linked_identifiers')->whereIn('user_id', $ids)->delete();
  User::whereIn('id', $ids)->delete();
}
DB::table('otps')->whereIn('identifier', $emails)->delete();

echo 'CLEANUP_OK';
`.trim();

  try {
    runTinkerSeed(php);
  } catch {
    // best-effort — the next run's beforeAll prune handles anything left.
  }
}

/**
 * Drive the real login page's email-OTP form for `email` and land on the
 * verify screen. The email-OTP form is one of two/three method forms toggled
 * client-side by Alpine (`x-cloak` until its tab is picked), so rather than
 * depend on the Alpine tab toggle we fill the hidden identifier field and
 * submit that specific form directly — robust against tab-visibility timing.
 * Waits on the send-otp POST (not the heavy redirect render) so a cold app
 * server never blocks here.
 */
async function requestOtp(page: Page, email: string): Promise<void> {
  await page.goto("/user/login", { timeout: 120_000 });
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().includes("/user/send-otp") && r.request().method() === "POST",
      { timeout: 120_000 },
    ),
    page.evaluate((addr) => {
      const form = Array.from(
        document.querySelectorAll<HTMLFormElement>("form"),
      ).find((f) =>
        f.querySelector('input[name="login_method"][value="email_otp"]'),
      );
      if (!form) throw new Error("email-OTP form not found on /user/login");
      const input = form.querySelector<HTMLInputElement>(
        'input[name="identifier"]',
      );
      if (!input) throw new Error("identifier input not found");
      input.value = addr;
      form.submit();
    }, email),
  ]);
  await page.waitForURL("**/user/verify-otp**", {
    timeout: 120_000,
    waitUntil: "commit",
  });
}

/**
 * Read the 6-digit code the demo-reveal amber banner surfaces on the verify
 * screen, then submit the verify form. Returns once the post-verify redirect
 * has committed (the caller asserts the destination URL). The verify page has
 * three forms (verify / resend / different-email); we scope to the verify form
 * by its action so we click the right button.
 */
async function verifyOtp(page: Page): Promise<void> {
  const banner = page.getByText(/verification code is/i);
  await expect(banner).toBeVisible({ timeout: 30_000 });
  const bannerText = (await banner.textContent()) ?? "";
  const match = bannerText.match(/(\d{6})/);
  if (!match) {
    throw new Error(
      "Could not read a 6-digit demo-reveal code from the verify banner: " +
        JSON.stringify(bannerText),
    );
  }
  const code = match[1];

  const verifyForm = page.locator('form[action$="/user/verify-otp"]');
  await verifyForm.locator('input[name="code"]').fill(code);
  // noWaitAfter: let the caller's waitForURL own the post-submit navigation
  // wait; a plain click() would block on the heavy authenticated re-render.
  await verifyForm.locator('button[type="submit"]').click({ noWaitAfter: true });
}

test.describe("email-OTP signup + mandatory name flow", () => {
  // Cold authenticated renders over the distant RDS push these past the default
  // 60s budget; give the suite real headroom (mirrors the sibling specs).
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    seedAuthSettings();
  });

  test.afterAll(() => {
    cleanupCreatedUsers();
  });

  // This suite asserts only server-rendered form behaviour (the demo-reveal
  // banner, the mandatory-name gate, redirects) — never visual styling. Under
  // the ephemeral `artisan serve` used by the e2e gate every static asset
  // (logos, web-fonts, compiled CSS) is served through PHP at ~500ms each, so
  // waiting on the `load` event of each navigation would spend the whole budget
  // fetching decoration. Abort those heavy sub-resource requests up front:
  // documents/redirects/XHR still flow, keeping the flow assertions intact while
  // making each navigation resolve in ~1s instead of tens of seconds.
  test.beforeEach(async ({ page }) => {
    await page.route("**/*", (route) => {
      const type = route.request().resourceType();
      if (
        type === "image" ||
        type === "font" ||
        type === "stylesheet" ||
        type === "media"
      ) {
        return route.abort();
      }
      return route.continue();
    });
  });

  test("a brand-new email signs up via OTP, is forced to set a name, then enters the app", async ({
    page,
  }) => {
    // 1) New email -> request a code -> verify screen with the demo-reveal code.
    await requestOtp(page, SIGNUP_EMAIL);

    // 2) Verifying auto-creates the account and, because no name was collected,
    //    lands on the mandatory complete-profile form (NOT the dashboard).
    await Promise.all([
      page.waitForURL("**/user/complete-profile**", {
        timeout: 120_000,
        waitUntil: "commit",
      }),
      verifyOtp(page),
    ]);
    expect(page.url()).toContain("/user/complete-profile");

    // The name form is really shown: its heading + the display-name field.
    await expect(
      page.getByRole("heading", { name: /One last step/i }),
    ).toBeVisible({ timeout: 60_000 });
    const nameInput = page.locator('input[name="name"]');
    await expect(nameInput).toBeVisible();

    // 3) Entering a valid name clears the gate and drops the user into the app.
    //    A brand-new account has not been through onboarding yet, so the moment
    //    the mandatory-name gate clears the onboarding gate takes over and the
    //    post-save redirect lands on /user/onboarding (not the dashboard).
    //    Either destination proves the same thing: the user is now OUT of the
    //    complete-profile form and inside the authenticated app.
    await nameInput.fill("E2E OTP Signup User");
    await Promise.all([
      page.waitForURL(/\/user\/(onboarding|dashboard)/, {
        timeout: 120_000,
        waitUntil: "commit",
      }),
      page
        .locator('form[action$="/user/complete-profile"] button[type="submit"]')
        .click({ noWaitAfter: true }),
    ]);
    expect(page.url()).not.toContain("/user/complete-profile");
    expect(page.url()).toMatch(/\/user\/(onboarding|dashboard)/);
  });

  test("RequiresNameMiddleware blocks every protected /user/* route until a name is saved", async ({
    page,
  }) => {
    // A second fresh signup that STOPS at the name gate (auth_needs_name set,
    // logged in, no name yet) — the exact state the middleware guards.
    await requestOtp(page, MIDDLEWARE_EMAIL);
    await Promise.all([
      page.waitForURL("**/user/complete-profile**", {
        timeout: 120_000,
        waitUntil: "commit",
      }),
      verifyOtp(page),
    ]);
    expect(page.url()).toContain("/user/complete-profile");

    // Any protected /user/* route is bounced back to the name form while the
    // flag is set — the user cannot navigate around it. Check two distinct
    // routes so it's the middleware (not a per-page redirect) being proven.
    for (const guarded of ["/user/dashboard", "/user/links"]) {
      await page.goto(guarded, { timeout: 120_000, waitUntil: "commit" });
      await page.waitForURL("**/user/complete-profile**", {
        timeout: 120_000,
        waitUntil: "commit",
      });
      expect(page.url()).toContain("/user/complete-profile");
    }

    // The complete-profile form itself is exempt (no redirect loop): it renders.
    await page.goto("/user/complete-profile", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    await expect(
      page.getByRole("heading", { name: /One last step/i }),
    ).toBeVisible({ timeout: 60_000 });

    // Saving a valid name clears the RequiresName flag, so the gate stops
    // firing: the user is allowed past the mandatory-name form. (A brand-new
    // account then proceeds to the onboarding gate — never back to
    // complete-profile, which is the behaviour under test here.)
    await page.locator('input[name="name"]').fill("E2E Gate User");
    await Promise.all([
      page.waitForURL(/\/user\/(onboarding|dashboard)/, {
        timeout: 120_000,
        waitUntil: "commit",
      }),
      page
        .locator('form[action$="/user/complete-profile"] button[type="submit"]')
        .click({ noWaitAfter: true }),
    ]);
    expect(page.url()).not.toContain("/user/complete-profile");

    // And a protected route is no longer bounced to the NAME gate. It may now
    // be intercepted by the onboarding gate instead, but it is never sent back
    // to the mandatory-name form — which is exactly what this test proves.
    await page.goto("/user/links", { timeout: 120_000, waitUntil: "commit" });
    expect(page.url()).not.toContain("/user/complete-profile");
  });
});
