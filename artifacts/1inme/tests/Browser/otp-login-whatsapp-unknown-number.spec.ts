import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

// Regression guard: an UNKNOWN phone number can NEVER sneak in a new account
// via the WhatsApp/mobile-OTP login flow.
//
// Unlike email OTP (which doubles as sign-up: `AuthController::verifyOtp`
// auto-creates an account after a code verifies, but ONLY for type=email),
// mobile/WhatsApp OTP must NOT create accounts. A mobile identifier with no
// matching user falls through to the "User not found." branch. The sibling
// otp-login-whatsapp-error-paths.spec.ts covers wrong/expired/burned codes for
// an EXISTING user; this spec covers the missing "no user exists for this
// number" branch, so a future change that made mobile verify auto-create (or
// resolve the wrong user) is caught instead of silently letting an unknown
// number mint or hijack an account.
//
// Two layers are pinned, matching how the controller actually defends:
//
//   1. SEND path (enumeration safety): `sendOtp` with login intent for an
//      unknown mobile creates NOTHING and issues NO code — it just bounces to
//      the verify screen with the neutral "If an account exists..." status.
//      With no issued code, even the fixed dev code (123456) fails at verify
//      with the generic "Invalid or expired OTP." — never a 500, never a
//      session, never a new users row.
//
//   2. VERIFY path (the decisive branch): even when a VALID, unexpired code
//      DOES exist for the unknown number (planted directly in the `otps`
//      table — the state a future send-path regression would produce), the
//      CORRECT code verifies but `resolveUserByIdentifier` finds no user and
//      type !== 'email', so verifyOtp lands on "User not found.". The verify
//      form posts via the shared auth-ajax handler, so the JSON error renders
//      inline on the verify screen (the non-AJAX fallback redirects to
//      /user/login with the same error); either way: NO account created,
//      NEVER a session, NEVER the dashboard.
//
// Context (WhatsApp delivery): mobile login is WhatsApp-only. Codes are sent
// by `OtpService::sendWhatsApp`, which runs in "preview mode" — logging the
// code instead of calling the Meta WhatsApp Cloud API — whenever the Meta
// credentials are absent (the default in dev/CI/e2e), so nothing here opens a
// real outbound socket.
//
// Design notes (shared with the sibling OTP specs):
//   - baseURL comes from APP_URL (the runner points it at the ephemeral e2e
//     server; localhost:80 hits the Express api-server instead).
//   - Each test uses a FRESH browser context so every case starts as a guest.
//   - A per-run-unique mobile number avoids colliding with rows left by an
//     interrupted previous run on the SHARED RDS; the shared prefix lets the
//     beforeAll prune + afterAll teardown target only this spec's rows.
//   - The one thing this spec must NEVER do is seed a user for the number:
//     the whole point is that the number has NO matching account, before and
//     after every step.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Per-run-unique unknown number. "+1" dialling code (allow-listed below so
// `isAllowedMobile` accepts it) + a distinctive "997" marker distinguishing
// this spec's rows from the sibling error-paths spec ("+1998"). Stays within
// the users.mobile 20-char column.
const MOBILE_PREFIX = "+1997";
const RUN_TAG = `${Date.now()}${Math.floor(Math.random() * 1e4)}`.slice(-9);
const UNKNOWN_MOBILE = `${MOBILE_PREFIX}${RUN_TAG}`; // e.g. +1997123456789

// The fixed non-production dev code `OtpService::generate` always issues.
// Used both as the "even the well-known dev code fails" probe on the send
// path and as the planted row's value on the verify path.
const DEV_CODE = "123456";

/**
 * Run a `php artisan tinker` snippet, retrying on a transient failure. Over
 * the distant RDS the tinker process occasionally fails to connect with a
 * hard "Command failed" and no PHP error; a couple of quick retries absorb
 * that blip, while a genuine PHP error fails every attempt and is surfaced.
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
 * Pin the framework auth toggles this flow relies on (WhatsApp/mobile login
 * ON; the "+1" dialling code allow-listed so the fixture number passes
 * `isAllowedMobile`; registrations OPEN — the guard under test must hold even
 * while sign-ups are allowed, since it is the TYPE, not the paused switch,
 * that blocks mobile auto-create) and prune any rows a crashed previous run
 * left behind. Crucially, NO user is seeded: the number must stay unknown.
 */
function seedSettingsAndPrune(): void {
  // NOTE: passed straight to `tinker --execute=`. In a JS template literal,
  // `\\` becomes the single backslash PHP namespaces need.
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
use App\\Modules\\Common\\Support\\AuthMethods;
use App\\Modules\\User\\Models\\User;
use Illuminate\\Support\\Facades\\DB;

AppSetting::put(AuthMethods::SETTING_MOBILE_ENABLED, true);
AppSetting::put(AuthMethods::SETTING_ALLOWED_CODES, ['+1', '+91']);
AppSetting::put('auth_email_otp_enabled', true);
AppSetting::put('auth_demo_reveal_otp_enabled', true);
AppSetting::put('auth_registration_paused', false);

// Prune anything a crashed previous run of THIS spec left behind. If a past
// regression DID mint an account for one of our marker numbers, remove it so
// this run starts from the required "no user exists" baseline.
$stale = User::where('mobile', 'like', '${MOBILE_PREFIX}%')->pluck('id')->all();
$viaLinked = DB::table('linked_identifiers')
  ->where('kind', 'phone')
  ->where('value', 'like', '%1997%')
  ->pluck('user_id')->all();
$stale = array_values(array_unique(array_merge($stale, $viaLinked)));
if (!empty($stale)) {
  $wsIds = DB::table('workspaces')->whereIn('owner_user_id', $stale)->pluck('id')->all();
  if (!empty($wsIds)) { DB::table('workspace_members')->whereIn('workspace_id', $wsIds)->delete(); }
  DB::table('workspace_members')->whereIn('user_id', $stale)->delete();
  DB::table('workspaces')->whereIn('owner_user_id', $stale)->delete();
  DB::table('linked_identifiers')->whereIn('user_id', $stale)->delete();
  User::whereIn('id', $stale)->delete();
}
DB::table('otps')->where('identifier', 'like', '${MOBILE_PREFIX}%')->delete();

echo 'OTP_WA_UNKNOWN_SEED_OK';
`.trim();

  const out = runTinker(php);
  if (!out.includes("OTP_WA_UNKNOWN_SEED_OK")) {
    throw new Error(
      "WhatsApp-OTP-unknown-number fixture seed failed, output:\n" + out,
    );
  }
}

/**
 * Best-effort teardown: remove any OTP rows this run issued for the fixture
 * number, and — belt and braces — any account that a regression might have
 * minted for it (the assertions will already have failed the suite in that
 * case; this just avoids polluting the shared RDS).
 */
function cleanup(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use Illuminate\\Support\\Facades\\DB;

DB::table('otps')->where('identifier', '${UNKNOWN_MOBILE}')->delete();
$ids = User::where('mobile', '${UNKNOWN_MOBILE}')->pluck('id')->all();
if (!empty($ids)) {
  $wsIds = DB::table('workspaces')->whereIn('owner_user_id', $ids)->pluck('id')->all();
  if (!empty($wsIds)) { DB::table('workspace_members')->whereIn('workspace_id', $wsIds)->delete(); }
  DB::table('workspace_members')->whereIn('user_id', $ids)->delete();
  DB::table('workspaces')->whereIn('owner_user_id', $ids)->delete();
  DB::table('linked_identifiers')->whereIn('user_id', $ids)->delete();
  User::whereIn('id', $ids)->delete();
}
echo 'CLEANUP_OK';
`.trim();

  try {
    runTinker(php);
  } catch {
    // best-effort — the next run's beforeAll prune handles anything left.
  }
}

/**
 * The core "no account was created" oracle: count users matching the fixture
 * number by EITHER resolution path `resolveUserByIdentifier` uses — the
 * users.mobile column and verified phone linked_identifiers (matched loosely
 * on the digits so a normalized value is still caught). Also reports whether
 * any unused OTP row exists for the number (the send-path enumeration check).
 */
function probeDbState(): { users: number; unusedOtps: number } {
  const php = `
use Illuminate\\Support\\Facades\\DB;
$digits = preg_replace('/\\D+/', '', '${UNKNOWN_MOBILE}');
$byMobile = DB::table('users')->where('mobile', '${UNKNOWN_MOBILE}')->count();
$byLinked = DB::table('linked_identifiers')
  ->where('kind', 'phone')
  ->where(function ($q) use ($digits) {
    $q->where('value', '${UNKNOWN_MOBILE}')->orWhere('value', 'like', '%' . $digits . '%');
  })
  ->count();
$otps = DB::table('otps')->where('identifier', '${UNKNOWN_MOBILE}')->where('used', false)->count();
echo 'DB_STATE:' . json_encode(['users' => $byMobile + $byLinked, 'unusedOtps' => $otps]);
`.trim();
  const out = runTinker(php);
  const match = out.match(/DB_STATE:(\{.*\})/);
  if (!match) {
    throw new Error("Could not probe DB state, output:\n" + out);
  }
  return JSON.parse(match[1]) as { users: number; unusedOtps: number };
}

/**
 * Plant a VALID, unexpired, unused OTP row for the unknown number — the exact
 * state a future send-path regression (issuing codes for unknown mobiles)
 * would produce. This lets the spec drive `OtpService::verify` to SUCCEED so
 * the assertion lands squarely on verifyOtp's "User not found." branch rather
 * than the earlier "Invalid or expired OTP." short-circuit.
 */
function plantValidOtp(): void {
  const php = `
use Illuminate\\Support\\Facades\\DB;
use Carbon\\Carbon;
DB::table('otps')->insert([
  'identifier' => '${UNKNOWN_MOBILE}',
  'type' => 'mobile',
  'code' => '${DEV_CODE}',
  'purpose' => 'login',
  'guard' => 'web',
  'expires_at' => Carbon::now()->addMinutes(10),
  'used' => false,
  'attempts' => 0,
  'created_at' => now(),
  'updated_at' => now(),
]);
echo 'OTP_PLANTED';
`.trim();
  const out = runTinker(php);
  if (!out.includes("OTP_PLANTED")) {
    throw new Error("Failed to plant a valid OTP row, output:\n" + out);
  }
}

/**
 * Read the code of the newest unused mobile-login OTP row for the fixture
 * number straight from the `otps` table — the same source OtpService::verify
 * reads, so "the correct code" can never drift from what the server expects.
 * (The demo-reveal banner only surfaces codes the SEND path issued; for the
 * planted row the table is the authoritative source.)
 */
function readIssuedCodeFromDb(): string {
  const php = `
use Illuminate\\Support\\Facades\\DB;
$row = DB::table('otps')
  ->where('identifier', '${UNKNOWN_MOBILE}')
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
 * Drive the real login page's WhatsApp/mobile-OTP form (login_method=mobile,
 * plain sign-IN intent — never the explicit sign-up affordance) and land on
 * the verify screen. The mobile form is one of several method forms toggled
 * client-side by Alpine, so rather than depend on the tab toggle we fill the
 * hidden identifier field of the form carrying `login_method=mobile` and
 * submit that specific form directly (robust against tab visibility timing).
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
 * Fill + submit the verify form (scoped by its action so we click the right
 * button among verify / resend / different-identifier) and wait for the
 * verify POST to complete. The form carries `data-ajax`, so the shared
 * auth-ajax handler submits it via fetch and renders errors INLINE — there is
 * no navigation on failure; returns the POST's JSON body (or null when the
 * response isn't JSON) so callers can assert the server's verdict directly.
 */
async function submitCode(
  page: Page,
  code: string,
): Promise<Record<string, unknown> | null> {
  const verifyForm = page.locator('form[action$="/user/verify-otp"]');
  await verifyForm.locator('input[name="code"]').fill(code);
  const [response] = await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().includes("/user/verify-otp") &&
        r.request().method() === "POST",
      { timeout: 120_000 },
    ),
    verifyForm.locator('button[type="submit"]').click({ noWaitAfter: true }),
  ]);
  try {
    return (await response.json()) as Record<string, unknown>;
  } catch {
    return null;
  }
}

/**
 * Assert the visitor is still a GUEST: a protected route bounces to login.
 */
async function expectNotSignedIn(page: Page): Promise<void> {
  await page.goto("/user/dashboard", { timeout: 120_000, waitUntil: "commit" });
  await page.waitForURL("**/user/login**", {
    timeout: 120_000,
    waitUntil: "commit",
  });
  expect(page.url()).toContain("/user/login");
}

/**
 * Assert the fixture number STILL has no account — the invariant every step
 * of this spec must preserve.
 */
function expectNoAccountCreated(): void {
  const state = probeDbState();
  expect(state.users, "no account may exist for the unknown number").toBe(0);
}

test.describe("WhatsApp-OTP login with an UNKNOWN number never creates or signs in an account", () => {
  // Cold renders over the distant RDS push these past the default 60s budget;
  // give the suite real headroom (mirrors the sibling OTP specs).
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    seedSettingsAndPrune();
  });

  test.afterAll(() => {
    cleanup();
  });

  // Abort heavy sub-resources (images/fonts/CSS/media) so each navigation
  // resolves fast over the PHP-served ephemeral app; documents/redirects/XHR
  // still flow. This suite asserts server-rendered auth behaviour, not styling.
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

  test("SEND path is enumeration-safe: no code is issued for an unknown number and the dev code cannot verify", async ({
    page,
  }) => {
    // Baseline: the number is unknown.
    expectNoAccountCreated();

    await requestOtp(page, UNKNOWN_MOBILE);

    // The controller redirected to the verify screen WITHOUT minting an
    // account or issuing a code (the enumeration-safe branch).
    const state = probeDbState();
    expect(state.users, "send must not create an account").toBe(0);
    expect(state.unusedOtps, "send must not issue a code").toBe(0);

    // Even the well-known fixed dev code fails: with no issued row,
    // OtpService::verify returns false. The data-ajax form gets the JSON
    // error envelope and renders it inline on the verify screen — never a
    // 500, never a session, never a redirect into the app.
    const body = await submitCode(page, DEV_CODE);
    expect(body?.ok, "verify must reject the dev code").toBe(false);
    expect(page.url()).toContain("/user/verify-otp");
    await expect(page.getByText(/invalid or expired otp/i)).toBeVisible({
      timeout: 30_000,
    });

    await expectNotSignedIn(page);
    expectNoAccountCreated();
  });

  test("VERIFY path: even a CORRECT, valid code for an unknown number lands on 'User not found.' — no account, no session", async ({
    page,
  }) => {
    // Baseline: the number is unknown.
    expectNoAccountCreated();

    // Establish the verify-screen session for the unknown number (send-path
    // issues nothing, but stores otp_identifier/otp_type in the session).
    await requestOtp(page, UNKNOWN_MOBILE);

    // Plant the state a send-path regression would produce: a VALID,
    // unexpired code for the unknown number. Read it back from the table so
    // the submitted value is provably the CORRECT one.
    plantValidOtp();
    const correctCode = readIssuedCodeFromDb();

    // Submit the correct code. OtpService::verify SUCCEEDS — the decisive
    // guard is verifyOtp itself: no user resolves for the number and mobile
    // OTP must never auto-create, so the AJAX envelope must come back
    // ok:false with "User not found." and NO redirect — never a token that
    // navigates into the dashboard / complete-profile / onboarding.
    const body = await submitCode(page, correctCode);
    expect(body?.ok, "verify must NOT log anyone in").toBe(false);
    expect(
      body?.redirect,
      "the failure envelope must not carry a redirect into the app",
    ).toBeUndefined();
    expect(JSON.stringify(body?.errors ?? {})).toMatch(/user not found/i);

    // The inline error renders on the verify screen; the visitor never
    // leaves the auth surface.
    expect(page.url()).toContain("/user/verify-otp");
    await expect(page.getByText(/user not found/i)).toBeVisible({
      timeout: 30_000,
    });

    // The invariant held: nothing was minted, nobody was signed in.
    await expectNotSignedIn(page);
    expectNoAccountCreated();
  });
});
