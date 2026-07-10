import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

// Error-path regression guard for the passwordless email-OTP SIGN-UP flow.
//
// The sibling spec (otp-signup-name-flow.spec.ts) covers the HAPPY path: a
// correct code auto-creates the account, forces the name gate, and drops the
// user into the app. This spec covers the ERROR paths a real user hits, which
// must all fail GRACEFULLY (no 500, no half-signed-in state, no account
// created behind a bad code):
//
//   1. WRONG code   -> `OtpService::verify` returns false; `verifyOtp` bounces
//      back to the verify screen with "Invalid or expired OTP." The account is
//      NOT created (email OTP only auto-creates AFTER a code verifies) and the
//      visitor is NOT signed in.
//   2. EXPIRED code -> even the *correct* value fails once the row's
//      `expires_at` has passed (same "Invalid or expired OTP." error, same
//      no-account / no-login outcome). We age the row via tinker to simulate
//      the 10-minute TTL lapsing.
//   3. RESEND       -> re-issuing a code keeps the visitor on the verify screen
//      with a fresh "a new code was sent" status and, crucially, a code that
//      still verifies afterwards — so a resend never breaks the flow.
//
// Design notes shared with the sibling spec:
//   - baseURL comes from APP_URL (the runner points it at the ephemeral e2e
//     server; localhost:80 hits the Express api-server instead).
//   - Each test uses a FRESH browser context so every case starts as a guest.
//   - Per-run-unique emails avoid colliding with a leftover row from an
//     interrupted previous run on the SHARED RDS; the shared prefix lets the
//     beforeAll prune + afterAll teardown target only this spec's accounts.
//   - In non-production `OtpService::generate` always issues the fixed dev code
//     `123456`, and demo-reveal surfaces it on the verify screen; we read it
//     from the banner rather than hard-coding it.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

const EMAIL_PREFIX = "e2e-otp-errpath-";
const RUN_TAG = `${Date.now()}-${Math.floor(Math.random() * 1e6)}`;
const WRONG_CODE_EMAIL = `${EMAIL_PREFIX}wrong-${RUN_TAG}@example.com`;
const EXPIRED_CODE_EMAIL = `${EMAIL_PREFIX}expired-${RUN_TAG}@example.com`;
const RESEND_EMAIL = `${EMAIL_PREFIX}resend-${RUN_TAG}@example.com`;

const ALL_EMAILS = [WRONG_CODE_EMAIL, EXPIRED_CODE_EMAIL, RESEND_EMAIL];

/**
 * Run a `php artisan tinker` seed, retrying on a transient failure. Over the
 * distant RDS the tinker process occasionally fails to connect with a hard
 * "Command failed" and no PHP error; a couple of quick retries absorb that
 * blip, while a genuine PHP error fails every attempt and is surfaced.
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

/**
 * Pin the framework defaults the email-OTP sign-up path relies on (email OTP
 * on, demo-reveal on so we can read the code, registration not paused) and
 * prune any accounts a crashed previous run of THIS spec left behind.
 */
function seedAuthSettings(): void {
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Workspace;
use Illuminate\\Support\\Facades\\DB;

AppSetting::put('auth_email_otp_enabled', true);
AppSetting::put('auth_demo_reveal_otp_enabled', true);
AppSetting::put('auth_registration_paused', false);

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

  const out = runTinker(php);
  if (!out.includes("AUTH_SEED_OK")) {
    throw new Error("Auth-settings seed failed, output:\n" + out);
  }
}

/**
 * Remove every account + issued OTP row this spec touched, in FK-dependency
 * order. Best-effort: teardown failures never fail the suite.
 */
function cleanupCreatedUsers(): void {
  const list = ALL_EMAILS.map((e) => `'${e}'`).join(", ");
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Workspace;
use Illuminate\\Support\\Facades\\DB;

$emails = [${list}];
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
    runTinker(php);
  } catch {
    // best-effort — the next run's beforeAll prune handles anything left.
  }
}

/** True iff a users row exists for `email` (proves no account was created). */
function userExists(email: string): boolean {
  const php = `
use App\\Modules\\User\\Models\\User;
echo User::where('email', '${email}')->exists() ? 'USER_YES' : 'USER_NO';
`.trim();
  return runTinker(php).includes("USER_YES");
}

/**
 * Age every issued OTP for `email` past its TTL so even the correct code
 * value can no longer verify — the deterministic way to exercise the
 * expired-code path without waiting 10 real minutes.
 */
function expireOtps(email: string): void {
  const php = `
use Illuminate\\Support\\Facades\\DB;
use Carbon\\Carbon;
DB::table('otps')->where('identifier', '${email}')
  ->update(['expires_at' => Carbon::now()->subMinutes(20)]);
echo 'OTP_EXPIRED';
`.trim();
  const out = runTinker(php);
  if (!out.includes("OTP_EXPIRED")) {
    throw new Error("Failed to expire OTP rows, output:\n" + out);
  }
}

/**
 * Drive the real login page's email-OTP form for `email` and land on the
 * verify screen. Fills the hidden identifier field of the email-OTP form and
 * submits it directly (robust against the Alpine tab-visibility timing), then
 * waits on the send-otp POST rather than the heavy redirect render.
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

/** Read the 6-digit demo-reveal code surfaced on the verify screen. */
async function readRevealCode(page: Page): Promise<string> {
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
  return match[1];
}

/** Submit `code` in the verify form (scoped by its action). */
async function submitCode(page: Page, code: string): Promise<void> {
  const verifyForm = page.locator('form[action$="/user/verify-otp"]');
  await verifyForm.locator('input[name="code"]').fill(code);
  await verifyForm.locator('button[type="submit"]').click({ noWaitAfter: true });
}

test.describe("email-OTP signup error paths", () => {
  // Cold authenticated renders over the distant RDS push these past the default
  // budget; give real headroom (mirrors the sibling specs).
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    seedAuthSettings();
  });

  test.afterAll(() => {
    cleanupCreatedUsers();
  });

  // Abort heavy sub-resources (images/fonts/CSS/media) so each navigation
  // resolves in ~1s over the PHP-served ephemeral app; documents/redirects/XHR
  // still flow, keeping the flow assertions intact.
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

  test("a WRONG code keeps the user on the verify screen, shows an error, creates no account, and does not sign them in", async ({
    page,
  }) => {
    await requestOtp(page, WRONG_CODE_EMAIL);

    // Deliberately submit a code that is NOT the issued one. (The dev code is
    // 123456; read the real one and pick a guaranteed-different value.)
    const realCode = await readRevealCode(page);
    const wrongCode = realCode === "000000" ? "999999" : "000000";
    await submitCode(page, wrongCode);

    // Bounced back to the verify screen with the graceful error — never a 500,
    // never a redirect into the app.
    await page.waitForURL("**/user/verify-otp**", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    expect(page.url()).toContain("/user/verify-otp");
    await expect(page.getByText(/invalid or expired otp/i)).toBeVisible({
      timeout: 30_000,
    });

    // No account was created behind the bad code (email OTP only auto-creates
    // AFTER a code verifies).
    expect(userExists(WRONG_CODE_EMAIL)).toBe(false);

    // And the visitor is NOT signed in: a protected route bounces to login.
    await page.goto("/user/dashboard", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    await page.waitForURL("**/user/login**", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    expect(page.url()).toContain("/user/login");
  });

  test("an EXPIRED code fails gracefully even when the value is correct", async ({
    page,
  }) => {
    await requestOtp(page, EXPIRED_CODE_EMAIL);
    const code = await readRevealCode(page);

    // Age the issued row past its TTL, then submit the otherwise-correct code.
    expireOtps(EXPIRED_CODE_EMAIL);
    await submitCode(page, code);

    // Same graceful failure as a wrong code: stays on verify, shows the error.
    await page.waitForURL("**/user/verify-otp**", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    expect(page.url()).toContain("/user/verify-otp");
    await expect(page.getByText(/invalid or expired otp/i)).toBeVisible({
      timeout: 30_000,
    });

    // No account created, not signed in.
    expect(userExists(EXPIRED_CODE_EMAIL)).toBe(false);
    await page.goto("/user/dashboard", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    await page.waitForURL("**/user/login**", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    expect(page.url()).toContain("/user/login");
  });

  test("RESEND keeps the user on the verify screen and re-issues a code that still verifies", async ({
    page,
  }) => {
    await requestOtp(page, RESEND_EMAIL);
    // Read the initial code before resending. In dev, generate() always issues
    // the same fixed value, so this code still matches the freshly re-issued
    // row after a resend (which invalidates the prior row).
    const code = await readRevealCode(page);

    // Click "Resend code" and wait on the resend POST (not the heavy re-render).
    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes("/user/resend-otp") &&
          r.request().method() === "POST",
        { timeout: 120_000 },
      ),
      page
        .locator('form[action$="/user/resend-otp"] button[type="submit"]')
        .click({ noWaitAfter: true }),
    ]);

    // Still on the verify screen with the fresh "new code was sent" status.
    await page.waitForURL("**/user/verify-otp**", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    expect(page.url()).toContain("/user/verify-otp");
    await expect(page.getByText(/a new code was sent/i)).toBeVisible({
      timeout: 30_000,
    });
    // The verify form is still present to accept the re-issued code.
    await expect(page.locator('form[action$="/user/verify-otp"]')).toBeVisible();

    // The re-issued code still verifies -> a resend never breaks the flow. A
    // brand-new account then lands on the mandatory complete-profile gate.
    await Promise.all([
      page.waitForURL("**/user/complete-profile**", {
        timeout: 120_000,
        waitUntil: "commit",
      }),
      submitCode(page, code),
    ]);
    expect(page.url()).toContain("/user/complete-profile");
    await expect(
      page.getByRole("heading", { name: /One last step/i }),
    ).toBeVisible({ timeout: 60_000 });

    // The successful resend-then-verify DID create the account (contrast with
    // the wrong/expired cases above).
    expect(userExists(RESEND_EMAIL)).toBe(true);
  });
});
