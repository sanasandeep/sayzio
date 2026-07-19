import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

// End-to-end regression guard for the AJAX 2FA (TOTP) login challenge.
//
// Task #4582 switched the login-time second-factor challenge form to AJAX
// submission so a user with an authenticator app no longer sees a jarring
// full-page RELOAD after typing a code — a wrong code now shows an inline
// error in place, and a correct code navigates via a JSON `redirect`. This
// spec locks that behaviour in so a future change to
// TwoFactorController@verifyChallenge, the challenge view, or auth-ajax.js
// (e.g. dropping the `$request->ajax()` branch or the auth-ajax.js include)
// can't silently reintroduce a reload or break inline error display.
//
// The flow under test (all server-side, no client trust):
//   1. A user with email+password login AND a confirmed TOTP authenticator
//      signs in with their password. Because 2FA is enrolled,
//      AuthController::loginWithPassword parks the login in the session
//      (`2fa_pending_user_id`) and redirects to the challenge form instead of
//      completing login.
//   2. On the challenge form, submitting a WRONG code hits verifyChallenge,
//      which (for an ajax request) returns `{ok:false, errors:{code:…}}` with
//      NO `redirect`. auth-ajax.js renders that inline under the code field and
//      leaves the page exactly where it was — no navigation, no reload.
//   3. Submitting the CORRECT code (computed from the known TOTP secret)
//      completes login and returns `{ok:true, redirect: <dashboard>}`, which
//      auth-ajax.js follows — the only navigation in the whole flow.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points
// it at the ephemeral e2e server — localhost:80 hits the Express api-server —
// see the sibling specs). Each test uses a FRESH browser context (Playwright's
// default), so every case starts as a genuine guest.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Distinctive, per-run-unique test email. Unique-per-run avoids collisions
// with a leftover row from an interrupted previous run on the SHARED RDS (see
// repo memory "e2e fixture aliases on shared RDS"); the shared prefix lets both
// the afterAll teardown and the beforeAll stale-prune target only this spec's
// account.
const EMAIL_PREFIX = "e2e-2fa-challenge-";
const RUN_TAG = `${Date.now()}-${Math.floor(Math.random() * 1e6)}`;
const USER_EMAIL = `${EMAIL_PREFIX}${RUN_TAG}@example.com`;
const USER_PASSWORD = "e2e-2fa-password-123";

// A fixed, valid base32 TOTP secret (20 bytes). Kept in the spec so the test
// can compute the live 6-digit code itself via the app's own TotpService — no
// need to scrape a QR or reveal-banner.
const TOTP_SECRET = "JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP";

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
 * Create the test account: email + password login enabled, a usable password,
 * a name + verified email + onboarding stamp (so a successful login lands on
 * the dashboard, not a name/onboarding gate), and a CONFIRMED TOTP enrollment
 * using the fixed secret above. Also pins the auth-method settings and prunes
 * any leftover accounts this spec created on a previous run.
 */
function seedUser(): void {
  // NOTE: this string is passed straight to `tinker --execute=`. In a JS
  // template literal, `\\` becomes the single backslash PHP namespaces need,
  // while `$var` stays literal.
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Workspace;
use App\\Modules\\Admin\\Models\\Plan;
use Illuminate\\Support\\Facades\\Crypt;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

// Pin the login policy so password sign-in (which routes through the 2FA
// challenge) is available regardless of what an admin toggled here.
AppSetting::put('auth_email_password_enabled', true);
AppSetting::put('auth_registration_paused', false);

// Prune leftover accounts from any interrupted previous run of THIS spec.
$stale = User::where('email', 'like', '${EMAIL_PREFIX}%')->pluck('id')->all();
if (!empty($stale)) {
  $wsIds = Workspace::whereIn('owner_user_id', $stale)->pluck('id')->all();
  if (!empty($wsIds)) { DB::table('workspace_members')->whereIn('workspace_id', $wsIds)->delete(); }
  DB::table('workspace_members')->whereIn('user_id', $stale)->delete();
  Workspace::whereIn('owner_user_id', $stale)->delete();
  DB::table('linked_identifiers')->whereIn('user_id', $stale)->delete();
  User::whereIn('id', $stale)->delete();
}

$plan = Plan::defaultPlan();
$user = User::create([
  'name' => '2FA Challenge User',
  'email' => '${USER_EMAIL}',
  'password' => Hash::make('${USER_PASSWORD}'),
  'plan_id' => $plan?->id,
  'status' => 'active',
  'email_verified_at' => now(),
  'onboarded_at' => now(),
]);

// Confirmed TOTP enrollment with the fixed secret + a throwaway recovery set.
$user->forceFill([
  'two_factor_secret' => Crypt::encryptString('${TOTP_SECRET}'),
  'two_factor_confirmed_at' => now(),
  'two_factor_recovery_codes' => Crypt::encryptString(json_encode([Hash::make('unused-recovery-code')])),
])->save();

$user->ensureDefaultWorkspace();

echo 'TWOFA_SEED_OK';
`.trim();

  const out = runTinker(php);
  if (!out.includes("TWOFA_SEED_OK")) {
    throw new Error("2FA-challenge user seed failed, output:\n" + out);
  }
}

/**
 * Remove the account this spec created, plus its workspace, memberships,
 * linked identifiers, and any issued OTP rows, in FK-dependency order.
 * Best-effort: teardown failures never fail the suite (the beforeAll prune
 * mops up next run).
 */
function cleanupUser(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Workspace;
use Illuminate\\Support\\Facades\\DB;

$ids = User::where('email', '${USER_EMAIL}')->pluck('id')->all();
if (!empty($ids)) {
  $wsIds = Workspace::whereIn('owner_user_id', $ids)->pluck('id')->all();
  if (!empty($wsIds)) { DB::table('workspace_members')->whereIn('workspace_id', $wsIds)->delete(); }
  DB::table('workspace_members')->whereIn('user_id', $ids)->delete();
  Workspace::whereIn('owner_user_id', $ids)->delete();
  DB::table('linked_identifiers')->whereIn('user_id', $ids)->delete();
  User::whereIn('id', $ids)->delete();
}
DB::table('otps')->where('identifier', '${USER_EMAIL}')->delete();

echo 'CLEANUP_OK';
`.trim();

  try {
    runTinker(php);
  } catch {
    // best-effort — the next run's beforeAll prune handles anything left.
  }
}

/**
 * Compute the live 6-digit TOTP code for the fixed secret using the app's own
 * TotpService (the same code path the server verifies against). Computed fresh
 * right before submitting; the server's +/-1 step window absorbs a rollover
 * that happens between here and the POST.
 */
function currentTotpCode(): string {
  const php = `
$svc = app(App\\Modules\\User\\Services\\TotpService::class);
echo 'CODE:' . $svc->codeAt('${TOTP_SECRET}', intdiv(time(), 30)) . ':END';
`.trim();
  const out = runTinker(php);
  const m = out.match(/CODE:(\d{6}):END/);
  if (!m) {
    throw new Error("Could not compute a TOTP code, tinker output:\n" + out);
  }
  return m[1];
}

/**
 * Drive the real login page's email+password form and land on the 2FA
 * challenge. The password form is one of several method forms toggled
 * client-side by Alpine (`x-cloak` until its tab is picked), so rather than
 * depend on the tab toggle we fill and submit that specific form directly.
 * Waits on the login POST (not the heavy redirect render) so a cold app server
 * never blocks here, then follows the JSON redirect to the challenge screen.
 */
async function signInWithPassword(page: Page): Promise<void> {
  await page.goto("/user/login", { timeout: 120_000 });
  await Promise.all([
    page.waitForResponse(
      (r) => r.url().includes("/user/login") && r.request().method() === "POST",
      { timeout: 120_000 },
    ),
    page.evaluate(
      ({ email, password }) => {
        const form = Array.from(
          document.querySelectorAll<HTMLFormElement>("form"),
        ).find((f) =>
          f.querySelector('input[name="login_method"][value="password"]'),
        );
        if (!form) throw new Error("password form not found on /user/login");
        const emailInput = form.querySelector<HTMLInputElement>(
          'input[name="email"]',
        );
        const pwInput = form.querySelector<HTMLInputElement>(
          'input[name="password"]',
        );
        if (!emailInput || !pwInput) {
          throw new Error("email/password inputs not found");
        }
        emailInput.value = email;
        pwInput.value = password;
        // Submit the real form so auth-ajax.js intercepts it (native submit()
        // bypasses the submit-event listener, so dispatch a submit event that
        // the requestSubmit path fires).
        form.requestSubmit();
      },
      { email: USER_EMAIL, password: USER_PASSWORD },
    ),
  ]);
  // auth-ajax follows the JSON redirect to the challenge form; wait for the
  // page to fully load so auth-ajax.js (a Vite module at the bottom of the
  // body) has been fetched and executed before the test interacts with the
  // form.  Using "commit" caused a timing race: the form became visible in the
  // server-rendered HTML before the module script attached the submit handler,
  // so the test's requestSubmit triggered a native POST instead of AJAX and
  // the inline [data-err="code"] slot was never unhidden.
  await page.waitForURL("**/account/two-factor/challenge**", {
    timeout: 120_000,
    waitUntil: "load",
  });
}

test.describe("2FA login challenge — AJAX (no page reload)", () => {
  // Cold authenticated renders over the distant RDS push these past the default
  // 60s budget; give the suite real headroom (mirrors the sibling specs).
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    seedUser();
  });

  test.afterAll(() => {
    cleanupUser();
  });

  // This spec asserts only server-rendered form behaviour and client AJAX
  // wiring — never visual styling. Under the ephemeral `artisan serve` used by
  // the e2e gate every static asset is served through PHP at ~500ms each, so
  // aborting heavy sub-resources keeps navigations fast. auth-ajax.js is a
  // SCRIPT (not stylesheet/image/font/media), so it is NOT aborted — the AJAX
  // behaviour under test still runs.
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

  test("a wrong code shows an inline error WITHOUT reloading, then a valid code signs in", async ({
    page,
  }) => {
    await signInWithPassword(page);

    // Confirm we're really on the challenge form (its verify form + code field).
    const challengeForm = page.locator(
      'form[action$="/account/two-factor/challenge"]',
    );
    await expect(challengeForm).toBeVisible({ timeout: 60_000 });
    const codeInput = challengeForm.locator('input[name="code"]');
    await expect(codeInput).toBeVisible();

    const challengeUrl = page.url();

    // Plant a page-load sentinel on the window. A full-page reload/navigation
    // wipes window globals, so if this survives the wrong-code submit we know
    // the AJAX path did NOT reload the page. Also count actual document loads
    // as a second, independent signal.
    await page.evaluate(() => {
      (window as unknown as { __noReload: boolean }).__noReload = true;
    });
    let documentLoads = 0;
    page.on("load", () => {
      documentLoads += 1;
    });

    // 1) WRONG code -> inline error, no navigation, no reload.
    await codeInput.fill("000000");
    // The verifyChallenge JSON response with the error envelope; auth-ajax.js
    // renders it inline. Wait on that POST so we assert after the round-trip.
    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes("/account/two-factor/challenge") &&
          r.request().method() === "POST",
        { timeout: 120_000 },
      ),
      challengeForm.locator('button[type="submit"]').click(),
    ]);

    // The inline error for the code field is shown (auth-ajax routes the
    // `errors.code` message into the [data-err="code"] slot).
    const codeError = challengeForm.locator('[data-err="code"]');
    await expect(codeError).toBeVisible({ timeout: 15_000 });
    await expect(codeError).toHaveText(/invalid 2fa code/i);

    // NO navigation happened: URL is unchanged, the sentinel survived, and no
    // new document load fired.
    expect(page.url()).toBe(challengeUrl);
    const sentinelSurvived = await page.evaluate(
      () =>
        (window as unknown as { __noReload?: boolean }).__noReload === true,
    );
    expect(sentinelSurvived).toBe(true);
    expect(documentLoads).toBe(0);

    // The code field is still there and interactive (form was not replaced).
    await expect(codeInput).toBeVisible();

    // 2) CORRECT code -> completes login and navigates to the dashboard via the
    //    JSON redirect. Compute the live code right before submitting.
    const validCode = currentTotpCode();
    await codeInput.fill("");
    await codeInput.fill(validCode);
    await Promise.all([
      page.waitForURL(/\/user\/(dashboard|onboarding)/, {
        timeout: 120_000,
        waitUntil: "commit",
      }),
      challengeForm
        .locator('button[type="submit"]')
        .click({ noWaitAfter: true }),
    ]);

    // Login is complete: we're off the challenge form and inside the app.
    expect(page.url()).not.toContain("/account/two-factor/challenge");
    expect(page.url()).toMatch(/\/user\/(dashboard|onboarding)/);
  });
});
