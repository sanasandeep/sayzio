import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

// Regression guard: the admin "pause registrations" switch
// (`auth_registration_paused`) must keep blocking the WhatsApp SIGN-UP
// affordance from minting accounts.
//
// Unlike the WhatsApp sign-IN flow (covered by
// otp-login-whatsapp-unknown-number.spec.ts — an unknown number must NEVER
// create an account), the auth modal's explicit "Sign up with WhatsApp" form
// posts `intent=signup` to `POST /user/send-otp`, and THAT branch DOES create
// the account at SEND time (`AuthController::sendOtp` →
// `createOtpSignupUser`), gated ONLY by `AuthMethods::registrationPaused()`.
// A regression in that one gate would let sign-ups slip straight past the
// admin pause switch, so this spec pins both sides of it:
//
//   1. PAUSED: with `auth_registration_paused=true`, submitting the sign-up
//      form for a brand-new number creates NO user row, issues NO code, and
//      renders the graceful registration-paused ("temporarily paused new
//      sign-ups") page — never the verify screen, never a session.
//   2. OPEN (control): with the switch back off, the very same form DOES mint
//      the account and lands on the verify screen with "Account created"; the
//      correct code then signs in but the fresh account stays GATED — it is
//      routed into onboarding/name-entry, not dropped on a bare dashboard.
//      This proves the paused-case failure isn't a broken form (the sign-up
//      path demonstrably works when open) and that a minted account is still
//      funneled through the first-run gates.
//
// Context (WhatsApp delivery): codes are sent by `OtpService::sendWhatsApp`,
// which runs in "preview mode" — logging the code instead of calling the Meta
// WhatsApp Cloud API — whenever the Meta credentials are absent (the default
// in dev/CI/e2e), so nothing here opens a real outbound socket.
//
// Design notes (shared with the sibling OTP specs):
//   - baseURL comes from APP_URL (the runner points it at the ephemeral e2e
//     server; localhost:80 hits the Express api-server instead).
//   - Each test uses a FRESH browser context so every case starts as a guest.
//   - A per-run-unique mobile number avoids colliding with rows left by an
//     interrupted previous run on the SHARED RDS; the shared "+1996" prefix
//     (distinct from the sibling specs' +1997/+1998 markers) lets the
//     beforeAll prune + afterAll teardown target only this spec's rows.
//   - The paused switch is flipped per-test and ALWAYS restored to false in
//     afterAll — leaving it on would break every other auth spec (and the dev
//     app) sharing the RDS.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Per-run-unique numbers. "+1" dialling code (allow-listed below so
// `isAllowedMobile` accepts it) + a distinctive "996" marker for THIS spec.
// Two numbers so the paused case's "nothing created" oracle can never be
// polluted by the open case's legitimately-created account.
const MOBILE_PREFIX = "+1996";
const RUN_TAG = `${Date.now()}${Math.floor(Math.random() * 1e4)}`.slice(-8);
const PAUSED_MOBILE = `${MOBILE_PREFIX}1${RUN_TAG}`; // e.g. +19961...
const OPEN_MOBILE = `${MOBILE_PREFIX}2${RUN_TAG}`; // e.g. +19962...

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
 * ON so the sign-up affordance renders; the "+1" dialling code allow-listed
 * so the fixture numbers pass `isAllowedMobile`; demo-reveal on) and prune
 * any rows a crashed previous run left behind. The paused switch itself is
 * flipped per-test via setRegistrationPaused().
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
AppSetting::put('auth_demo_reveal_otp_enabled', true);
AppSetting::put('auth_registration_paused', false);

// Prune anything a crashed previous run of THIS spec left behind (accounts a
// past run legitimately created in the open-registrations control case, or —
// if a regression DID mint one while paused — that too).
$stale = User::where('mobile', 'like', '${MOBILE_PREFIX}%')->pluck('id')->all();
$viaLinked = DB::table('linked_identifiers')
  ->where('kind', 'phone')
  ->where('value', 'like', '%1996%')
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

echo 'OTP_WA_SIGNUP_PAUSED_SEED_OK';
`.trim();

  const out = runTinker(php);
  if (!out.includes("OTP_WA_SIGNUP_PAUSED_SEED_OK")) {
    throw new Error(
      "WhatsApp-signup-paused fixture seed failed, output:\n" + out,
    );
  }
}

/**
 * Flip the admin `auth_registration_paused` switch — the exact gate under
 * test in `AuthController::sendOtp`'s signup-intent branch.
 */
function setRegistrationPaused(paused: boolean): void {
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
AppSetting::put('auth_registration_paused', ${paused ? "true" : "false"});
echo 'PAUSED_SET_OK';
`.trim();
  const out = runTinker(php);
  if (!out.includes("PAUSED_SET_OK")) {
    throw new Error("Failed to set auth_registration_paused, output:\n" + out);
  }
}

/**
 * Best-effort teardown: ALWAYS restore the paused switch to false (shared
 * RDS!), then remove this run's OTP rows and any accounts it created (the
 * open-case control account, plus — belt and braces — anything a regression
 * minted while paused; the assertions will already have failed in that case).
 */
function cleanup(): void {
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
use App\\Modules\\User\\Models\\User;
use Illuminate\\Support\\Facades\\DB;

AppSetting::put('auth_registration_paused', false);

DB::table('otps')->where('identifier', 'like', '${MOBILE_PREFIX}%')->delete();
$ids = User::whereIn('mobile', ['${PAUSED_MOBILE}', '${OPEN_MOBILE}'])->pluck('id')->all();
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
 * The core oracle: count users matching a fixture number by EITHER resolution
 * path `resolveUserByIdentifier` uses — the users.mobile column and verified
 * phone linked_identifiers (matched loosely on the digits so a normalized
 * value is still caught). Also reports whether any unused OTP row exists for
 * the number (paused sign-up must issue nothing).
 */
function probeDbState(mobile: string): { users: number; unusedOtps: number } {
  const php = `
use Illuminate\\Support\\Facades\\DB;
$digits = preg_replace('/\\D+/', '', '${mobile}');
$byMobile = DB::table('users')->where('mobile', '${mobile}')->pluck('id')->all();
$byLinked = DB::table('linked_identifiers')
  ->where('kind', 'phone')
  ->where(function ($q) use ($digits) {
    $q->where('value', '${mobile}')->orWhere('value', 'like', '%' . $digits . '%');
  })
  ->pluck('user_id')->all();
// One account legitimately matches BOTH paths (the User::created observer
// mirrors users.mobile into linked_identifiers), so count DISTINCT users.
$users = count(array_unique(array_merge($byMobile, $byLinked)));
$otps = DB::table('otps')->where('identifier', '${mobile}')->where('used', false)->count();
echo 'DB_STATE:' . json_encode(['users' => $users, 'unusedOtps' => $otps]);
`.trim();
  const out = runTinker(php);
  const match = out.match(/DB_STATE:(\{.*\})/);
  if (!match) {
    throw new Error("Could not probe DB state, output:\n" + out);
  }
  return JSON.parse(match[1]) as { users: number; unusedOtps: number };
}

/**
 * Read the code of the newest unused mobile-login OTP row for a number
 * straight from the `otps` table — the same source OtpService::verify reads,
 * so "the correct code" can never drift from what the server expects.
 */
function readIssuedCodeFromDb(mobile: string): string {
  const php = `
use Illuminate\\Support\\Facades\\DB;
$row = DB::table('otps')
  ->where('identifier', '${mobile}')
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
 * Drive the auth modal's REAL "Sign up with WhatsApp" affordance: the form on
 * the marketing header's Register tab carrying the hidden
 * `intent=signup` + `type=mobile` inputs (public/partials/auth-modal). Rather
 * than depend on Alpine modal/tab visibility timing, we fill the identifier
 * of the form that carries `intent=signup` and submit that specific form
 * directly (a plain, non-AJAX POST — the paused branch responds with the
 * registration-paused VIEW, the open branch redirects to verify-otp).
 *
 * The home page is the marketing surface that renders the modal. In the e2e
 * server's first ~20s a dev-only startup-probe splash can be served on `/`
 * instead of the real home page, so we retry the navigation until the sign-up
 * form is actually present.
 */
async function submitSignup(page: Page, mobile: string): Promise<void> {
  let found = false;
  for (let attempt = 1; attempt <= 6 && !found; attempt++) {
    await page.goto("/", { timeout: 120_000, waitUntil: "domcontentloaded" });
    found = await page
      .waitForFunction(
        () =>
          Array.from(document.querySelectorAll<HTMLFormElement>("form")).some(
            (f) =>
              f.querySelector('input[name="intent"][value="signup"]') &&
              f.querySelector('input[name="type"][value="mobile"]'),
          ),
        undefined,
        { timeout: 15_000 },
      )
      .then(() => true)
      .catch(() => false);
  }
  if (!found) {
    throw new Error(
      "WhatsApp sign-up form (intent=signup) never appeared on the home page",
    );
  }

  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().includes("/user/send-otp") && r.request().method() === "POST",
      { timeout: 120_000 },
    ),
    page.evaluate((num) => {
      const form = Array.from(
        document.querySelectorAll<HTMLFormElement>("form"),
      ).find(
        (f) =>
          f.querySelector('input[name="intent"][value="signup"]') &&
          f.querySelector('input[name="type"][value="mobile"]'),
      );
      if (!form) throw new Error("WhatsApp sign-up form not found");
      const input = form.querySelector<HTMLInputElement>(
        'input[name="identifier"]',
      );
      if (!input) throw new Error("identifier input not found");
      input.value = num;
      form.submit();
    }, mobile),
  ]);
}

/**
 * Fill + submit the verify form (scoped by its action so we click the right
 * button among verify / resend / different-identifier) and wait for the
 * verify POST to complete. The form carries `data-ajax`, so the shared
 * auth-ajax handler submits it via fetch; returns the POST's HTTP status.
 * (A successful verify navigates immediately, which can evict the JSON body
 * before it can be read — so the status is the only reliable oracle here.)
 */
async function submitCode(page: Page, code: string): Promise<number> {
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
  return response.status();
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

test.describe("paused-registrations switch blocks WhatsApp SIGN-UP from creating accounts", () => {
  // Cold renders over the distant RDS push these past the default 60s budget;
  // the heavy home page + signup writes need real headroom.
  test.describe.configure({ timeout: 240_000 });

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

  test("PAUSED: sign-up intent creates nothing, issues no code, and shows the paused page", async ({
    page,
  }) => {
    setRegistrationPaused(true);
    try {
      // Baseline: the number is unknown.
      expect(probeDbState(PAUSED_MOBILE).users).toBe(0);

      await submitSignup(page, PAUSED_MOBILE);

      // The graceful paused outcome renders — never the verify screen.
      await expect(
        page.getByText(/temporarily paused new sign-ups/i),
      ).toBeVisible({ timeout: 60_000 });
      expect(page.url()).not.toContain("/user/verify-otp");

      // The decisive invariants: NO account minted, NO code issued.
      const state = probeDbState(PAUSED_MOBILE);
      expect(state.users, "paused sign-up must not create an account").toBe(0);
      expect(state.unusedOtps, "paused sign-up must not issue a code").toBe(0);

      // And the visitor was never signed in.
      await expectNotSignedIn(page);
      expect(probeDbState(PAUSED_MOBILE).users).toBe(0);
    } finally {
      // Never leave the shared switch on, even when the test fails.
      setRegistrationPaused(false);
    }
  });

  test("OPEN (control): the same form mints a gated account — proving the affordance works and the pause gate is what blocked it", async ({
    page,
  }) => {
    setRegistrationPaused(false);

    // Baseline: the control number is unknown too.
    expect(probeDbState(OPEN_MOBILE).users).toBe(0);

    await submitSignup(page, OPEN_MOBILE);

    // Open registrations: the account IS minted at send time and the visitor
    // lands on the verify screen with the "Account created" confirmation.
    await page.waitForURL("**/user/verify-otp**", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    await expect(page.getByText(/account created/i)).toBeVisible({
      timeout: 60_000,
    });
    const state = probeDbState(OPEN_MOBILE);
    expect(state.users, "open sign-up must create the account").toBe(1);
    expect(state.unusedOtps, "open sign-up must issue a code").toBe(1);

    // The correct code signs in, and the brand-new account is properly
    // GATED: it is routed into the first-run surfaces (onboarding wizard /
    // name entry), never dropped on a bare dashboard.
    const code = readIssuedCodeFromDb(OPEN_MOBILE);
    // A successful verify makes the auth-ajax handler navigate immediately,
    // which can evict the JSON body before it is read — so the oracle is the
    // POST status + the resulting navigation, never the body.
    const verifyStatus = await submitCode(page, code);
    expect(verifyStatus, "the correct code must verify").toBeLessThan(400);

    // The auth-ajax handler follows the JSON redirect; the onboarding gate
    // middleware then funnels the fresh account into the wizard.
    await page.waitForURL(/\/user\/(onboarding|complete-profile)/, {
      timeout: 120_000,
    });
    expect(page.url()).toMatch(/\/user\/(onboarding|complete-profile)/);
  });
});
