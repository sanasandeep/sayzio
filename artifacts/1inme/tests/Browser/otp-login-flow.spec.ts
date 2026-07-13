import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

// End-to-end regression guard for the passwordless email-OTP LOGIN path of an
// EXISTING, already-onboarded account — the exact flow the "email isolation"
// change had to keep working after the boot-time SMTP override was made
// production-only.
//
// Context (email isolation): in every non-production environment (dev, CI, e2e,
// the PHPUnit suite) the mail transport is forced onto the non-delivering "log"
// driver so a fixture OTP send can NEVER open a real SMTP socket. A deterministic
// PHP feature test (tests/Feature/OtpLoginEmailIsolationTest.php) already proves
// the transport stays black-holed AND that the send/verify CONTROLLERS still
// round-trip a code. What that feature test can't see is the REAL browser flow:
// the JS-driven login page, the session/CSRF handshake across the send->verify
// navigation, the demo-reveal banner that surfaces the issued code, and the
// post-verify redirect landing an onboarded user squarely on the dashboard.
// This spec drives that browser flow against a booted `artisan serve` (where the
// production-only mail gate applies), so a UI-level regression the feature test
// misses still fails a gate.
//
// The flow under test (all server-side, no client trust):
//   1. An EXISTING active + onboarded user requests a one-time code from the
//      login page's email-OTP form. `AuthController::sendOtp` issues a code and
//      (in demo-reveal mode) surfaces it in an amber banner on the verify screen.
//   2. Verifying that code takes the fast `resolveUserByIdentifier -> Auth::login`
//      path (NOT the new-account signup pipeline), and because the account has a
//      name and a past `onboarded_at`, the post-verify redirect lands directly on
//      `/user/dashboard` — no complete-profile gate, no onboarding gate.
//
// Contrast with the sibling specs, which deliberately cover DIFFERENT surfaces:
//   - otp-signup-name-flow.spec.ts — a BRAND-NEW email (signup) forced through
//     the complete-profile + onboarding gates (never reaches the dashboard).
//   - otp-signup-error-paths.spec.ts — wrong/expired/burned codes + paused
//     registration (all graceful failures, never a login).
//   - auth-ajax-flows.spec.ts — the fetch/JSON layer; its OTP verify asserts
//     only that the redirect leaves the verify screen (its fixture user isn't
//     onboarded, so it can't assert the dashboard landing).
// None of them prove the plain (native-submit) OTP LOGIN of an onboarded user
// all the way to the dashboard, which is what this spec pins.
//
// Design notes (shared with the sibling OTP specs):
//   - baseURL comes from APP_URL (the runner points it at the ephemeral e2e
//     server; localhost:80 hits the Express api-server instead).
//   - Each test uses a FRESH browser context so every case starts as a guest.
//   - Per-run-unique emails avoid colliding with a leftover row from an
//     interrupted previous run on the SHARED RDS; the shared prefix lets the
//     beforeAll prune + afterAll teardown target only this spec's account.
//   - In non-production `OtpService::generate` always issues the fixed dev code
//     and demo-reveal surfaces it on the verify screen; we read it from the
//     banner rather than hard-coding it.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Distinctive, per-run-unique test email. Unique-per-run avoids collisions with
// a leftover row from an interrupted previous run on the SHARED RDS (see repo
// memory "e2e fixture aliases on shared RDS"); the shared prefix lets both the
// afterAll teardown and the beforeAll stale-prune target only this spec's rows.
const EMAIL_PREFIX = "e2e-otp-login-";
const RUN_TAG = `${Date.now()}-${Math.floor(Math.random() * 1e6)}`;
const LOGIN_EMAIL = `${EMAIL_PREFIX}existing-${RUN_TAG}@example.com`;
const LOGIN_NAME = "E2E OTP Login User";

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
 * Pin the framework auth toggles this flow relies on (email OTP on; demo-reveal
 * on so we can read the code; registration not paused — irrelevant here since
 * the account pre-exists, but kept for parity), prune any rows a crashed
 * previous run left behind, then seed the ONE fixture that must pre-exist: an
 * ACTIVE, already-onboarded user whose good verify is a fast OTP *login* (not a
 * signup) that lands straight on the dashboard.
 *
 * `onboarded_at` is stamped well in the past so the RedirectToOnboarding gate
 * treats the account as an established user (no "recently onboarded" WhatsApp /
 * privacy nudge redirects), and `ensureDefaultWorkspace()` gives it the personal
 * workspace every real account has so the dashboard renders normally.
 */
function seedOnboardedUser(): void {
  // NOTE: this string is passed straight to `tinker --execute=`. In a JS
  // template literal, `\\` becomes the single backslash PHP namespaces need,
  // while `$var` stays literal.
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
use App\\Modules\\Admin\\Models\\Plan;
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Workspace;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Str;

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

// The GOOD-code path must log into an EXISTING account (the fast
// resolveUserByIdentifier -> Auth::login path). A name + a PAST onboarded_at
// means the post-verify redirect skips the complete-profile AND onboarding
// gates and lands directly on the dashboard.
$u = User::firstOrNew(['email' => '${LOGIN_EMAIL}']);
$u->name = '${LOGIN_NAME}';
$u->password = Hash::make(Str::random(48));
$u->status = 'active';
$u->email_verified_at = now();
$u->onboarded_at = now()->subYears(2);
$u->referral_code = Str::random(10);
$u->plan_id = Plan::defaultPlan()?->id;
$u->save();

// Every real account has a personal workspace; give the fixture one too so the
// dashboard renders exactly as it would for a genuine returning user.
$u->ensureDefaultWorkspace();

echo 'OTP_LOGIN_SEED_OK';
`.trim();

  const out = runTinker(php);
  if (!out.includes("OTP_LOGIN_SEED_OK")) {
    throw new Error("OTP-login fixture seed failed, output:\n" + out);
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
DB::table('otps')->where('identifier', '${LOGIN_EMAIL}')->delete();

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
 * the primary path reads the banner (see `readRevealCode`).
 */
function readIssuedCodeFromDb(): string {
  const php = `
use Illuminate\\Support\\Facades\\DB;
$row = DB::table('otps')
  ->where('identifier', '${LOGIN_EMAIL}')
  ->where('type', 'email')
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
 * Drive the real login page's email-OTP form for `email` and land on the verify
 * screen. The email-OTP form is one of several method forms toggled client-side
 * by Alpine, so rather than depend on the tab toggle we fill the hidden
 * identifier field and submit that specific form directly (robust against tab
 * visibility timing). Waits on the send-otp POST (not the heavy redirect render)
 * so a cold app server never blocks here.
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
 * button among verify / resend / different-email). `noWaitAfter` lets the
 * caller's `waitForURL` own the post-submit navigation instead of blocking on
 * the heavy authenticated re-render.
 */
async function submitCode(page: Page, code: string): Promise<void> {
  const verifyForm = page.locator('form[action$="/user/verify-otp"]');
  await verifyForm.locator('input[name="code"]').fill(code);
  await verifyForm.locator('button[type="submit"]').click({ noWaitAfter: true });
}

test.describe("email-OTP login (existing onboarded user) -> dashboard", () => {
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

  test("an existing onboarded user logs in via email OTP and lands on the dashboard", async ({
    page,
  }) => {
    // 1) Request a code for the existing account -> verify screen with the
    //    demo-reveal code (issued through the log-only mailer; never delivered).
    await requestOtp(page, LOGIN_EMAIL);
    const code = await readRevealCode(page);

    // 2) Verifying the good code takes the fast Auth::login path and, because
    //    the account has a name + a past onboarded_at, the post-verify redirect
    //    lands directly on the dashboard — NOT complete-profile, NOT onboarding.
    await Promise.all([
      page.waitForURL("**/user/dashboard**", {
        timeout: 120_000,
        waitUntil: "commit",
      }),
      submitCode(page, code),
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

    // 3) The session is genuinely authenticated: a second protected route loads
    //    in place instead of bouncing to login.
    await page.goto("/user/links", { timeout: 120_000, waitUntil: "commit" });
    expect(page.url()).toContain("/user/links");
    expect(page.url()).not.toContain("/user/login");
  });
});
