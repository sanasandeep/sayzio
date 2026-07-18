import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

// RESEND regression guard for the passwordless WhatsApp/mobile-OTP LOGIN flow.
//
// The email path already proves (in otp-signup-error-paths.spec.ts) that
// clicking "Resend code" keeps the visitor on the verify screen with a fresh
// "a new code was sent" status and re-issues a code that STILL verifies. The
// WhatsApp/mobile method shares the same `/user/resend-otp` endpoint and the
// same "invalidate prior unused codes on re-issue" behaviour in
// `OtpService::generate`, but had no equivalent Browser guard. A regression in
// `AuthController::resendOtp`'s MOBILE branch — e.g. re-issuing the code under
// the wrong type/purpose so the fresh code no longer matches what
// `OtpService::verify` looks up (`type=mobile`, `purpose=login`) — would let
// a resend silently strand every WhatsApp login. This spec pins that path:
//
//   1. An EXISTING, active + onboarded user with a VERIFIED mobile number
//      requests a code via the login page's WhatsApp-OTP form and lands on the
//      verify screen (code surfaced by the demo-reveal banner; the WhatsApp
//      sender runs in preview mode — logged, never delivered).
//   2. Clicking "Resend code" POSTs `/user/resend-otp`; the mobile branch
//      re-checks the send-time policy (mobile login enabled + allowed dialling
//      code), resolves the SAME user, generates a fresh code under the SAME
//      (identifier, type=mobile, purpose=login) tuple — invalidating the prior
//      unused row — and redirects back to the verify screen with the
//      "a new code was sent" status.
//   3. The RE-ISSUED code (re-read after the resend, from the banner or the
//      otps table) still verifies, taking the fast `resolveUserByIdentifier ->
//      Auth::login` path, and the onboarded account lands squarely on
//      `/user/dashboard` — a resend never breaks the login flow.
//
// Contrast with the sibling specs, which deliberately cover DIFFERENT surfaces:
//   - otp-login-whatsapp-flow.spec.ts — the WhatsApp happy path WITHOUT resend.
//   - otp-login-whatsapp-error-paths.spec.ts — wrong/expired/burned mobile
//     codes (graceful failures, never a login).
//   - otp-signup-error-paths.spec.ts — the EMAIL method's error paths incl.
//     the email RESEND case this spec mirrors for mobile.
//
// Design notes (shared with the sibling OTP specs):
//   - baseURL comes from APP_URL (the runner points it at the ephemeral e2e
//     server; localhost:80 hits the Express api-server instead).
//   - A per-run-unique mobile number avoids colliding with a leftover row from
//     an interrupted previous run on the SHARED RDS; the shared prefix lets the
//     beforeAll prune + afterAll teardown target only this spec's account.
//   - In non-production `OtpService::generate` always issues the fixed dev
//     code and demo-reveal surfaces it on the verify screen; we RE-READ the
//     code after the resend (banner first, otps table as recovery) rather than
//     assuming the pre-resend value, so the assertion holds even if the dev
//     code ever stops being fixed.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Distinctive, per-run-unique test fixtures. The mobile number begins with the
// "+1" dialling code (seeded into the allow-list below so `isAllowedMobile`
// accepts it) and stays within the users.mobile 20-char column. The "+1997"
// marker is unique to THIS spec (flow uses +1999, error-paths +1998) so the
// prune/teardown never touch a sibling spec's rows in a parallel run.
const EMAIL_PREFIX = "e2e-otp-wa-resend-";
const MOBILE_PREFIX = "+1997"; // "+1" dialling code + a distinctive marker.
const RUN_TAG = `${Date.now()}${Math.floor(Math.random() * 1e4)}`.slice(-9);
const LOGIN_EMAIL = `${EMAIL_PREFIX}${RUN_TAG}@example.com`;
const LOGIN_MOBILE = `${MOBILE_PREFIX}${RUN_TAG}`; // e.g. +1997123456789 (<=20 chars)
const LOGIN_NAME = "E2E WhatsApp OTP Resend User";

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
 * makes a good verify a fast OTP *login* that lands straight on the dashboard.
 *
 * `onboarded_at` is stamped well in the past so the RedirectToOnboarding gate
 * treats the account as an established user, and `ensureDefaultWorkspace()`
 * gives it the personal workspace every real account has so the dashboard
 * renders normally.
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

// The GOOD-code path must log into an EXISTING account (the fast
// resolveUserByIdentifier -> Auth::login path). A name + a PAST onboarded_at
// means the post-verify redirect skips the complete-profile AND onboarding
// gates and lands directly on the dashboard.
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

echo 'OTP_WA_RESEND_SEED_OK';
`.trim();

  const out = runTinker(php);
  if (!out.includes("OTP_WA_RESEND_SEED_OK")) {
    throw new Error(
      "WhatsApp-OTP-resend fixture seed failed, output:\n" + out,
    );
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
 * Read the 6-digit code the latest UNUSED issued OTP is stored under, straight
 * from the `otps` table — the same source `OtpService::verify` reads. Note the
 * `type=mobile` + `purpose=login` filters: this is exactly the tuple the
 * verify step will look up, so if a resend regression ever re-issued the code
 * under a different type/purpose, this read finds NO row and the test fails
 * loudly. Used as a belt-and-braces recovery when the demo-reveal banner
 * can't be parsed.
 */
function readIssuedCodeFromDb(): string {
  const php = `
use App\\Modules\\User\\Models\\User;
use Illuminate\\Support\\Facades\\DB;
$row = DB::table('otps')
  ->where('identifier', '${LOGIN_MOBILE}')
  ->where('type', 'mobile')
  ->where('purpose', 'login')
  ->where('used', false)
  ->orderByDesc('id')
  ->first();
if ($row) { echo 'OTP_CODE:' . $row->code; }
else {
  // Diagnostic dump so a NONE failure explains itself: is the fixture user
  // still present, and what otps rows (if any) exist for this run's prefix?
  echo 'OTP_CODE:NONE ';
  echo 'users_by_email=', User::where('email', '${LOGIN_EMAIL}')->count(), ' ';
  echo 'users_by_mobile=', User::where('mobile', '${LOGIN_MOBILE}')->count(), ' ';
  echo 'linked=', DB::table('linked_identifiers')->where('value', '${LOGIN_MOBILE}')->whereNotNull('verified_at')->count(), ' ';
  echo 'otps_any=', json_encode(DB::table('otps')->where('identifier', 'like', '${MOBILE_PREFIX}%')->get(['identifier','type','purpose','used']));
}
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
 * the heavy authenticated re-render.
 */
async function submitCode(page: Page, code: string): Promise<void> {
  const verifyForm = page.locator('form[action$="/user/verify-otp"]');
  await verifyForm.locator('input[name="code"]').fill(code);
  await verifyForm.locator('button[type="submit"]').click({ noWaitAfter: true });
}

test.describe("WhatsApp-OTP resend -> re-issued code still logs in", () => {
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
  // server-rendered form behaviour + the post-login destination, never styling.
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

  test("RESEND keeps the user on the verify screen and the re-issued WhatsApp code still logs in to the dashboard", async ({
    page,
  }) => {
    // 1) Request a code for the existing account's verified number -> verify
    //    screen (issued through the preview-mode WhatsApp sender; logged,
    //    never delivered). Confirm the first code was actually issued so the
    //    resend below genuinely exercises the RE-issue path.
    await requestOtp(page, LOGIN_MOBILE);
    const firstCode = await readRevealCode(page);
    expect(firstCode).toMatch(/^\d{6}$/);

    // 2) Click "Resend code" and wait on the resend POST (not the heavy
    //    re-render). The mobile branch of AuthController::resendOtp must
    //    re-issue under the SAME (identifier, type=mobile, purpose=login)
    //    tuple, invalidating the prior unused row.
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

    // 3) RE-READ the code after the resend (banner first, otps table as
    //    recovery — the DB read filters on type=mobile/purpose=login/used=false,
    //    so a resend that re-issued under the wrong tuple fails loudly here).
    const reissuedCode = await readRevealCode(page);

    //    The re-issued code still verifies -> a resend never breaks the login
    //    flow. Because the account has a name + a past onboarded_at, the
    //    post-verify redirect lands directly on the dashboard — NOT
    //    complete-profile, NOT onboarding, and NEVER back on verify.
    await Promise.all([
      page.waitForURL("**/user/dashboard**", {
        timeout: 120_000,
        waitUntil: "commit",
      }),
      submitCode(page, reissuedCode),
    ]);
    expect(page.url()).toContain("/user/dashboard");
    expect(page.url()).not.toContain("/user/complete-profile");
    expect(page.url()).not.toContain("/user/onboarding");
    expect(page.url()).not.toContain("/user/verify-otp");

    // The dashboard really rendered for this user: its greeting hero echoes the
    // account's display name (server-rendered text, so it survives the
    // sub-resource aborts above).
    await expect(
      page.getByRole("heading", { name: new RegExp(LOGIN_NAME, "i") }),
    ).toBeVisible({ timeout: 60_000 });
  });
});
