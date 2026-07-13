import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

// Error-path regression guard for the passwordless WhatsApp/mobile-OTP LOGIN
// flow. The sibling otp-login-whatsapp-flow.spec.ts pins the HAPPY path (an
// existing, onboarded user with a verified mobile number logs in and lands on
// the dashboard). The EMAIL method already has a dedicated error-path spec
// (otp-signup-error-paths.spec.ts). This spec is the MOBILE analog of those
// error paths, proving the WhatsApp method fails GRACEFULLY — never a 500,
// never a signed-in session — for the three ways a real user's code goes bad:
//
//   1. WRONG code   -> `OtpService::verify` returns false; `AuthController::
//      verifyOtp` bounces back to the verify screen with "Invalid or expired
//      OTP." The seeded account is NEVER authenticated.
//   2. EXPIRED code -> even the *correct* value fails once the row's
//      `expires_at` has passed (same "Invalid or expired OTP." error, same
//      never-signed-in outcome). We age the row via tinker to simulate the
//      10-minute TTL lapsing rather than waiting it out.
//   3. BURNED code  -> submitting wrong codes up to `OtpService::MAX_ATTEMPTS`
//      (5) force-marks the row used, so a *subsequent* submission of the
//      CORRECT code is STILL rejected. This is the core anti-brute-force
//      guarantee: the attempt cap slams the window shut before the correct
//      code is ever compared. Same graceful outcome — stays on verify, never
//      a login.
//
// Why MOBILE differs from the email error spec: mobile OTP does NOT double as
// sign-up. `verifyOtp` only auto-creates an account for the EMAIL type; a
// mobile identifier with no matching user falls through to "User not found".
// So the mobile analog of "no account created behind a bad code" is "the
// SEEDED account is never signed in" — a bad code must never advance the
// existing verified-mobile account into an authenticated session. Each test
// therefore drives an EXISTING, onboarded fixture (seeded exactly like the
// happy-path spec) and asserts the failure leaves it a guest.
//
// Context (WhatsApp delivery): mobile login is WhatsApp-only. Codes are sent by
// `OtpService::sendWhatsApp`, which runs in "preview mode" — logging the code
// instead of calling the Meta WhatsApp Cloud API — whenever the Meta
// credentials are absent (the default in dev/CI/e2e). So a fixture OTP send can
// NEVER open a real outbound socket here, exactly like the email path's
// log-only mailer.
//
// Design notes (shared with the sibling OTP specs):
//   - baseURL comes from APP_URL (the runner points it at the ephemeral e2e
//     server; localhost:80 hits the Express api-server instead).
//   - Each test uses a FRESH browser context so every case starts as a guest.
//   - A per-run-unique mobile number avoids colliding with a leftover row from
//     an interrupted previous run on the SHARED RDS; the shared prefix lets the
//     beforeAll prune + afterAll teardown target only this spec's account.
//   - In non-production `OtpService::generate` always issues the fixed dev code
//     `123456` and demo-reveal surfaces it on the verify screen; we read it
//     from the banner rather than hard-coding it (falling back to the otps
//     table).
//   - One seeded onboarded user is shared across all three tests. Each test
//     requests a FRESH code (which invalidates any prior unused code for the
//     tuple), and the specs run serially, so there is no cross-test bleed.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Distinctive, per-run-unique test fixtures. The mobile number begins with the
// "+1" dialling code (seeded into the allow-list below so `isAllowedMobile`
// accepts it) and stays within the users.mobile 20-char column. Unique-per-run
// avoids collisions with a leftover row from an interrupted previous run on the
// SHARED RDS (see repo memory "e2e fixture aliases on shared RDS"); the shared
// email/mobile prefixes let both the afterAll teardown and the beforeAll
// stale-prune target only this spec's rows.
const EMAIL_PREFIX = "e2e-otp-wa-err-";
const MOBILE_PREFIX = "+1998"; // "+1" dialling code + a distinctive marker.
const RUN_TAG = `${Date.now()}${Math.floor(Math.random() * 1e4)}`.slice(-9);
const LOGIN_EMAIL = `${EMAIL_PREFIX}${RUN_TAG}@example.com`;
const LOGIN_MOBILE = `${MOBILE_PREFIX}${RUN_TAG}`; // e.g. +1998123456789 (<=20 chars)
const LOGIN_NAME = "E2E WhatsApp OTP Error User";

// Mirror of OtpService::MAX_ATTEMPTS — the number of wrong guesses that burns
// an issued code. Kept in lockstep with the service constant.
const MAX_ATTEMPTS = 5;

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
 * Pin the framework auth toggles this flow relies on (WhatsApp/mobile login ON;
 * the "+1" dialling code allow-listed so the fixture number passes
 * `isAllowedMobile`; demo-reveal on so we can read the code), prune any rows a
 * crashed previous run left behind, then seed the ONE fixture that must
 * pre-exist: an ACTIVE, already-onboarded user whose VERIFIED mobile number
 * would make a GOOD verify a fast OTP login. Every case here submits a BAD code
 * against that account, so the decisive assertion is that it is never signed in.
 *
 * `onboarded_at` is stamped well in the past so the RedirectToOnboarding gate
 * treats the account as an established user. The mobile number is mirrored into
 * a VERIFIED `linked_identifiers` row (by the User::created observer) so
 * `resolveUserByIdentifier` resolves it exactly as a genuine verified number
 * would (with the users.mobile column as a belt-and-braces fallback).
 */
function seedOnboardedUser(): void {
  // NOTE: this string is passed straight to `tinker --execute=`. In a JS
  // template literal, `\\` becomes the single backslash PHP namespaces need,
  // while `$var` stays literal.
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
use App\\Modules\\Admin\\Models\\Plan;
use App\\Modules\\Common\\Support\\AuthMethods;
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Workspace;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Str;

AppSetting::put(AuthMethods::SETTING_MOBILE_ENABLED, true);
AppSetting::put(AuthMethods::SETTING_ALLOWED_CODES, ['+1', '+91']);
AppSetting::put('auth_email_otp_enabled', true);
AppSetting::put('auth_demo_reveal_otp_enabled', true);
AppSetting::put('auth_registration_paused', false);

// Prune leftover accounts from any interrupted previous run of THIS spec
// (dependency order: members -> workspaces -> linked ids/otps -> users).
$stale = User::where('email', 'like', '${EMAIL_PREFIX}%')->pluck('id')->all();
if (!empty($stale)) {
  $wsIds = Workspace::whereIn('owner_user_id', $stale)->pluck('id')->all();
  if (!empty($wsIds)) { DB::table('workspace_members')->whereIn('workspace_id', $wsIds)->delete(); }
  DB::table('workspace_members')->whereIn('user_id', $stale)->delete();
  Workspace::whereIn('owner_user_id', $stale)->delete();
  DB::table('linked_identifiers')->whereIn('user_id', $stale)->delete();
  User::whereIn('id', $stale)->delete();
}
DB::table('otps')->where('identifier', 'like', '${MOBILE_PREFIX}%')->delete();

// A name + a PAST onboarded_at means a GOOD verify would skip the
// complete-profile AND onboarding gates and land on the dashboard — which is
// exactly the outcome each error case must PREVENT.
$u = User::firstOrNew(['email' => '${LOGIN_EMAIL}']);
$u->name = '${LOGIN_NAME}';
$u->password = Hash::make(Str::random(48));
$u->status = 'active';
$u->email_verified_at = now();
$u->mobile = '${LOGIN_MOBILE}';
$u->onboarded_at = now()->subYears(2);
$u->referral_code = Str::random(10);
$u->plan_id = Plan::defaultPlan()?->id;
$u->save();

// The User::created observer already mirrors the mobile column into a
// linked_identifiers row (kind=phone, value normalized, verified_at set,
// is_primary=false because the email row is this account's single primary).
// We must NOT insert our own row: matching on the raw '+...' value would miss
// the observer's normalized row and INSERT a SECOND primary, violating the
// one-primary-per-user unique constraint. Instead, idempotently re-assert that
// the observer's phone row is verified (leaving is_primary untouched) so
// resolveUserByIdentifier resolves the number via the verified linked-id path.
DB::table('linked_identifiers')
  ->where('user_id', $u->id)
  ->where('kind', 'phone')
  ->update(['verified_at' => now(), 'updated_at' => now()]);

// Every real account has a personal workspace; give the fixture one too.
$u->ensureDefaultWorkspace();

echo 'OTP_WA_ERR_SEED_OK';
`.trim();

  const out = runTinker(php);
  if (!out.includes("OTP_WA_ERR_SEED_OK")) {
    throw new Error("WhatsApp-OTP-error fixture seed failed, output:\n" + out);
  }
}

/**
 * Remove the fixture account this spec created (plus its workspace, membership,
 * linked identifiers, and any issued OTP rows) in FK-dependency order.
 * Best-effort: teardown failures never fail the suite (the next run's beforeAll
 * prune mops up anything left behind).
 */
function cleanupCreatedUser(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Workspace;
use Illuminate\\Support\\Facades\\DB;

$ids = User::where('email', '${LOGIN_EMAIL}')->pluck('id')->all();
if (!empty($ids)) {
  $wsIds = Workspace::whereIn('owner_user_id', $ids)->pluck('id')->all();
  if (!empty($wsIds)) { DB::table('workspace_members')->whereIn('workspace_id', $wsIds)->delete(); }
  DB::table('workspace_members')->whereIn('user_id', $ids)->delete();
  Workspace::whereIn('owner_user_id', $ids)->delete();
  DB::table('linked_identifiers')->whereIn('user_id', $ids)->delete();
  User::whereIn('id', $ids)->delete();
}
DB::table('otps')->where('identifier', '${LOGIN_MOBILE}')->delete();

echo 'CLEANUP_OK';
`.trim();

  try {
    runTinker(php);
  } catch {
    // best-effort — the next run's beforeAll prune handles anything left.
  }
}

/**
 * Read the 6-digit code the issued OTP is stored under, straight from the
 * `otps` table — the same source `OtpService::verify` reads. Used as a
 * belt-and-braces recovery in case the demo-reveal banner ever changes copy;
 * the primary path reads the banner (see `readRevealCode`). Note the
 * `type=mobile` filter (the WhatsApp path stores codes under the mobile type).
 */
function readIssuedCodeFromDb(): string {
  const php = `
use Illuminate\\Support\\Facades\\DB;
$row = DB::table('otps')
  ->where('identifier', '${LOGIN_MOBILE}')
  ->where('type', 'mobile')
  ->where('purpose', 'login')
  ->where('used', false)
  ->orderByDesc('id')
  ->first();
echo $row ? ('OTP_CODE:' . $row->code) : 'OTP_CODE:NONE';
`.trim();
  const out = runTinker(php);
  const match = out.match(/OTP_CODE:(\d{6})/);
  if (!match) {
    throw new Error(
      "Could not read a 6-digit issued OTP from the otps table: " +
        JSON.stringify(out),
    );
  }
  return match[1];
}

/**
 * Age every issued mobile OTP for the fixture number past its TTL so even the
 * correct code value can no longer verify — the deterministic way to exercise
 * the expired-code path without waiting out the 10-minute TTL.
 */
function expireOtps(): void {
  const php = `
use Illuminate\\Support\\Facades\\DB;
use Carbon\\Carbon;
DB::table('otps')->where('identifier', '${LOGIN_MOBILE}')
  ->update(['expires_at' => Carbon::now()->subMinutes(20)]);
echo 'OTP_EXPIRED';
`.trim();
  const out = runTinker(php);
  if (!out.includes("OTP_EXPIRED")) {
    throw new Error("Failed to expire OTP rows, output:\n" + out);
  }
}

/**
 * Drive the real login page's WhatsApp/mobile-OTP form for `mobile` and land on
 * the verify screen. The mobile-OTP form is one of several method forms toggled
 * client-side by Alpine, so rather than depend on the tab toggle we fill the
 * hidden identifier field of the form carrying `login_method=mobile` and submit
 * that specific form directly (robust against tab visibility timing). Waits on
 * the send-otp POST (not the heavy redirect render) so a cold app server never
 * blocks here.
 */
async function requestOtp(page: Page, mobile: string): Promise<void> {
  await page.goto("/user/login", { timeout: 120_000 });
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().includes("/user/send-otp") && r.request().method() === "POST",
      { timeout: 120_000 },
    ),
    page.evaluate((num) => {
      const form = Array.from(
        document.querySelectorAll<HTMLFormElement>("form"),
      ).find((f) =>
        f.querySelector('input[name="login_method"][value="mobile"]'),
      );
      if (!form) throw new Error("WhatsApp-OTP form not found on /user/login");
      const input = form.querySelector<HTMLInputElement>(
        'input[name="identifier"]',
      );
      if (!input) throw new Error("identifier input not found");
      input.value = num;
      form.submit();
    }, mobile),
  ]);
  await page.waitForURL("**/user/verify-otp**", {
    timeout: 120_000,
    waitUntil: "commit",
  });
}

/**
 * Read the 6-digit code the demo-reveal amber banner surfaces on the verify
 * screen. Falls back to the `otps` table if the banner can't be parsed, so the
 * "recover the code (demo-reveal banner or otps table)" contract holds either
 * way.
 */
async function readRevealCode(page: Page): Promise<string> {
  const banner = page.getByText(/verification code is/i);
  try {
    await expect(banner).toBeVisible({ timeout: 30_000 });
    const bannerText = (await banner.textContent()) ?? "";
    const match = bannerText.match(/(\d{6})/);
    if (match) return match[1];
  } catch {
    // fall through to the DB recovery below.
  }
  return readIssuedCodeFromDb();
}

/**
 * Fill + submit the verify form (scoped by its action so we click the right
 * button among verify / resend / different-identifier). `noWaitAfter` lets the
 * caller's `waitForURL` own the post-submit navigation instead of blocking on
 * the heavy re-render.
 */
async function submitCode(page: Page, code: string): Promise<void> {
  const verifyForm = page.locator('form[action$="/user/verify-otp"]');
  await verifyForm.locator('input[name="code"]').fill(code);
  await verifyForm.locator('button[type="submit"]').click({ noWaitAfter: true });
}

/**
 * Assert the visitor is still a GUEST: a protected route bounces to login.
 * This is the decisive "the bad code never authenticated the account" check
 * (the mobile analog of the email spec's "no account created", since mobile
 * OTP never auto-creates accounts and the fixture pre-exists).
 */
async function expectNotSignedIn(page: Page): Promise<void> {
  await page.goto("/user/dashboard", { timeout: 120_000, waitUntil: "commit" });
  await page.waitForURL("**/user/login**", {
    timeout: 120_000,
    waitUntil: "commit",
  });
  expect(page.url()).toContain("/user/login");
}

test.describe("WhatsApp-OTP login error paths (existing onboarded user)", () => {
  // Cold authenticated renders over the distant RDS push these past the default
  // 60s budget; give the suite real headroom (mirrors the sibling specs).
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    seedOnboardedUser();
  });

  test.afterAll(() => {
    cleanupCreatedUser();
  });

  // Abort heavy sub-resources (images/fonts/CSS/media) so each navigation
  // resolves in ~1s over the PHP-served ephemeral app; documents/redirects/XHR
  // still flow, keeping the flow assertions intact. This suite asserts only
  // server-rendered form behaviour, never styling.
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

  test("a WRONG code keeps the user on the verify screen, shows an error, and never signs them in", async ({
    page,
  }) => {
    await requestOtp(page, LOGIN_MOBILE);

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

    // The seeded account was never authenticated behind the bad code.
    await expectNotSignedIn(page);
  });

  test("an EXPIRED code fails gracefully even when the value is correct", async ({
    page,
  }) => {
    await requestOtp(page, LOGIN_MOBILE);
    const code = await readRevealCode(page);

    // Age the issued row past its TTL, then submit the otherwise-correct code.
    expireOtps();
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

    // Not signed in.
    await expectNotSignedIn(page);
  });

  test("submitting wrong codes up to the attempt cap BURNS the code so the CORRECT one is then rejected", async ({
    page,
  }) => {
    await requestOtp(page, LOGIN_MOBILE);

    // The real, issued value — the code that WOULD verify against a fresh row.
    const realCode = await readRevealCode(page);
    const wrongCode = realCode === "000000" ? "999999" : "000000";

    // Exhaust the attempt cap with wrong guesses. Each one bounces back to the
    // verify screen with the graceful error and never signs the visitor in.
    for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
      await submitCode(page, wrongCode);
      await page.waitForURL("**/user/verify-otp**", {
        timeout: 120_000,
        waitUntil: "commit",
      });
      expect(page.url()).toContain("/user/verify-otp");
      await expect(page.getByText(/invalid or expired otp/i)).toBeVisible({
        timeout: 30_000,
      });
    }

    // The cap is now spent. On the NEXT verify the service force-marks the row
    // used BEFORE the code is even compared, so even the CORRECT value is
    // rejected — the brute-force window stays slammed shut.
    await submitCode(page, realCode);
    await page.waitForURL("**/user/verify-otp**", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    expect(page.url()).toContain("/user/verify-otp");
    await expect(page.getByText(/invalid or expired otp/i)).toBeVisible({
      timeout: 30_000,
    });

    // The seeded account was never authenticated behind the burned code.
    await expectNotSignedIn(page);
  });
});
