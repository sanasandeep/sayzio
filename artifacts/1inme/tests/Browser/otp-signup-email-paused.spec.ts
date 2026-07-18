import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

// Regression guard: the admin "pause registrations" switch
// (`auth_registration_paused`) must keep blocking EMAIL-OTP sign-up from
// minting accounts. Sibling of otp-signup-whatsapp-paused.spec.ts, which
// covers the WhatsApp (mobile) branch of the same gate.
//
// Email OTP doubles as sign-up: `POST /user/send-otp` with an UNKNOWN email
// issues a code, and `POST /user/verify-otp` then auto-creates the account
// (`AuthController::verifyOtp` → `createOtpSignupUser`). The paused switch is
// therefore checked at TWO points — at send time (`sendOtp`'s unknown-email
// branch, immediate feedback) and again at verify time (the branch that
// actually mints the row). A regression in either one would silently reopen
// registrations, so this spec pins both plus an open control:
//
//   1. PAUSED AT SEND: with `auth_registration_paused=true`, requesting a
//      code for a brand-new email creates NO user row, issues NO code, and
//      renders the graceful registration-paused page — never the verify
//      screen, never a session.
//   2. PAUSED AT VERIFY: with the switch OFF at send time (a code IS issued)
//      but flipped ON before the code is submitted, the verify POST must
//      refuse to create the account — this is the gate that guards the actual
//      account-minting line, and it must hold even for a code issued while
//      registrations were still open.
//   3. OPEN (control): with the switch off, the very same flow DOES mint the
//      account at verify time and the fresh account stays GATED — routed into
//      the mandatory name entry (complete-profile), not dropped on a bare
//      dashboard. This proves the paused-case failures aren't a broken form.
//
// Context (email delivery): codes are sent by `OtpService::sendEmail` through
// the centralized Emailer, which no-ops/logs when no SMTP credential is
// stored (the default in dev/CI/e2e), so nothing here sends real mail — and
// the test reads the issued code straight from the `otps` table anyway.
//
// Design notes (shared with the sibling OTP specs):
//   - baseURL comes from APP_URL (the runner points it at the ephemeral e2e
//     server; localhost:80 hits the Express api-server instead).
//   - Each test uses a FRESH browser context so every case starts as a guest.
//   - Per-run-unique emails avoid colliding with rows left by an interrupted
//     previous run on the SHARED RDS; the distinctive "e2e-emailpaused"
//     local-part marker lets the beforeAll prune + afterAll teardown target
//     only this spec's rows.
//   - The paused switch is flipped per-test and ALWAYS restored to false in
//     afterAll — leaving it on would break every other auth spec (and the
//     dev app) sharing the RDS.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Per-run-unique emails carrying this spec's distinctive marker. Three
// addresses so no case's oracle can be polluted by another case's
// legitimately-created rows.
const EMAIL_MARKER = "e2e-emailpaused";
const RUN_TAG = `${Date.now()}${Math.floor(Math.random() * 1e4)}`.slice(-8);
const PAUSED_SEND_EMAIL = `${EMAIL_MARKER}-a${RUN_TAG}@example.com`;
const PAUSED_VERIFY_EMAIL = `${EMAIL_MARKER}-b${RUN_TAG}@example.com`;
const OPEN_EMAIL = `${EMAIL_MARKER}-c${RUN_TAG}@example.com`;

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
 * Pin the framework auth toggles this flow relies on (email OTP ON so the
 * one-time-code form renders; demo-reveal on) and prune any rows a crashed
 * previous run left behind. The paused switch itself is flipped per-test via
 * setRegistrationPaused().
 */
function seedSettingsAndPrune(): void {
  // NOTE: passed straight to `tinker --execute=`. In a JS template literal,
  // `\\` becomes the single backslash PHP namespaces need.
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
use App\\Modules\\Common\\Support\\AuthMethods;
use App\\Modules\\User\\Models\\User;
use Illuminate\\Support\\Facades\\DB;

AppSetting::put(AuthMethods::SETTING_EMAIL_OTP_ENABLED, true);
AppSetting::put('auth_demo_reveal_otp_enabled', true);
AppSetting::put('auth_registration_paused', false);

// Prune anything a crashed previous run of THIS spec left behind (accounts a
// past run legitimately created in the open-registrations control case, or —
// if a regression DID mint one while paused — those too).
$stale = User::where('email', 'like', '${EMAIL_MARKER}-%')->pluck('id')->all();
$viaLinked = DB::table('linked_identifiers')
  ->where('kind', 'email')
  ->where('value', 'like', '%${EMAIL_MARKER}%')
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
DB::table('otps')->where('identifier', 'like', '${EMAIL_MARKER}-%')->delete();

echo 'OTP_EMAIL_SIGNUP_PAUSED_SEED_OK';
`.trim();

  const out = runTinker(php);
  if (!out.includes("OTP_EMAIL_SIGNUP_PAUSED_SEED_OK")) {
    throw new Error("Email-signup-paused fixture seed failed, output:\n" + out);
  }
}

/**
 * Flip the admin `auth_registration_paused` switch — the exact gate under
 * test in `AuthController::sendOtp` (unknown-email branch) and
 * `AuthController::verifyOtp` (the account-minting branch).
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

DB::table('otps')->where('identifier', 'like', '${EMAIL_MARKER}-%')->delete();
$ids = User::whereIn('email', ['${PAUSED_SEND_EMAIL}', '${PAUSED_VERIFY_EMAIL}', '${OPEN_EMAIL}'])->pluck('id')->all();
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
 * The core oracle: count users matching a fixture email by EITHER resolution
 * path `resolveUserByIdentifier` uses — the users.email column and verified
 * email linked_identifiers. Also reports whether any unused OTP row exists
 * for the email (paused sign-up must issue nothing at send time).
 */
function probeDbState(email: string): { users: number; unusedOtps: number } {
  const php = `
use Illuminate\\Support\\Facades\\DB;
$byEmail = DB::table('users')->where('email', '${email}')->pluck('id')->all();
$byLinked = DB::table('linked_identifiers')
  ->where('kind', 'email')
  ->where('value', '${email}')
  ->pluck('user_id')->all();
// One account legitimately matches BOTH paths (the User::created observer
// mirrors users.email into linked_identifiers), so count DISTINCT users.
$users = count(array_unique(array_merge($byEmail, $byLinked)));
$otps = DB::table('otps')->where('identifier', '${email}')->where('used', false)->count();
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
 * Read the code of the newest unused email-login OTP row for an address
 * straight from the `otps` table — the same source OtpService::verify reads,
 * so "the correct code" can never drift from what the server expects.
 */
function readIssuedCodeFromDb(email: string): string {
  const php = `
use Illuminate\\Support\\Facades\\DB;
$row = DB::table('otps')
  ->where('identifier', '${email}')
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
 * Drive the auth modal's REAL email one-time-code form: the form on the
 * marketing header carrying the hidden `intent=login` + `type=email` inputs
 * (public/partials/auth-modal). Email OTP is sign-up-capable by design (an
 * unknown email is auto-registered at verify time), so this single form is
 * the email sign-up affordance. Rather than depend on Alpine modal/tab
 * visibility timing, we fill the identifier of the specific form and submit
 * it directly (a plain, non-AJAX POST — the paused branch responds with the
 * registration-paused VIEW, the open branch redirects to verify-otp).
 *
 * The home page is the marketing surface that renders the modal. In the e2e
 * server's first ~20s a dev-only startup-probe splash can be served on `/`
 * instead of the real home page, so we retry the navigation until the form
 * is actually present.
 */
async function submitEmailOtpRequest(page: Page, email: string): Promise<void> {
  let found = false;
  for (let attempt = 1; attempt <= 6 && !found; attempt++) {
    await page.goto("/", { timeout: 120_000, waitUntil: "domcontentloaded" });
    found = await page
      .waitForFunction(
        () =>
          Array.from(document.querySelectorAll<HTMLFormElement>("form")).some(
            (f) =>
              f.querySelector('input[name="intent"][value="login"]') &&
              f.querySelector('input[name="type"][value="email"]'),
          ),
        undefined,
        { timeout: 15_000 },
      )
      .then(() => true)
      .catch(() => false);
  }
  if (!found) {
    throw new Error(
      "email one-time-code form (type=email) never appeared on the home page",
    );
  }

  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().includes("/user/send-otp") && r.request().method() === "POST",
      { timeout: 120_000 },
    ),
    page.evaluate((addr) => {
      const form = Array.from(
        document.querySelectorAll<HTMLFormElement>("form"),
      ).find(
        (f) =>
          f.querySelector('input[name="intent"][value="login"]') &&
          f.querySelector('input[name="type"][value="email"]'),
      );
      if (!form) throw new Error("email one-time-code form not found");
      const input = form.querySelector<HTMLInputElement>(
        'input[name="identifier"]',
      );
      if (!input) throw new Error("identifier input not found");
      input.value = addr;
      form.submit();
    }, email),
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

test.describe("paused-registrations switch blocks EMAIL-OTP sign-up from creating accounts", () => {
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

  test("PAUSED AT SEND: an unknown email creates nothing, gets no code, and sees the paused page", async ({
    page,
  }) => {
    setRegistrationPaused(true);
    try {
      // Baseline: the email is unknown.
      expect(probeDbState(PAUSED_SEND_EMAIL).users).toBe(0);

      await submitEmailOtpRequest(page, PAUSED_SEND_EMAIL);

      // The graceful paused outcome renders — never the verify screen.
      await expect(
        page.getByText(/temporarily paused new sign-ups/i),
      ).toBeVisible({ timeout: 60_000 });
      expect(page.url()).not.toContain("/user/verify-otp");

      // The decisive invariants: NO account minted, NO code issued.
      const state = probeDbState(PAUSED_SEND_EMAIL);
      expect(state.users, "paused send must not create an account").toBe(0);
      expect(state.unusedOtps, "paused send must not issue a code").toBe(0);

      // And the visitor was never signed in.
      await expectNotSignedIn(page);
      expect(probeDbState(PAUSED_SEND_EMAIL).users).toBe(0);
    } finally {
      // Never leave the shared switch on, even when the test fails.
      setRegistrationPaused(false);
    }
  });

  test("PAUSED AT VERIFY: a code issued while open must not mint an account once paused", async ({
    page,
  }) => {
    // Send while registrations are OPEN: a code IS legitimately issued for
    // the unknown email (auto-signup happens at verify time for email OTP).
    setRegistrationPaused(false);
    expect(probeDbState(PAUSED_VERIFY_EMAIL).users).toBe(0);

    await submitEmailOtpRequest(page, PAUSED_VERIFY_EMAIL);
    await page.waitForURL("**/user/verify-otp**", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    const issued = probeDbState(PAUSED_VERIFY_EMAIL);
    expect(issued.users, "no account exists yet at send time").toBe(0);
    expect(issued.unusedOtps, "open send must issue a code").toBe(1);

    // An admin pauses registrations BETWEEN send and verify. The verify-time
    // gate — the one directly guarding createOtpSignupUser — must hold.
    setRegistrationPaused(true);
    try {
      const code = readIssuedCodeFromDb(PAUSED_VERIFY_EMAIL);
      await submitCode(page, code);

      // The refusal surfaces on-page (the verify form is AJAX) and, whatever
      // the copy does next, the decisive invariant is the DB: NO account.
      await expect(
        page.getByText(/registrations are currently paused/i),
      ).toBeVisible({ timeout: 60_000 });
      expect(
        probeDbState(PAUSED_VERIFY_EMAIL).users,
        "paused verify must not create an account",
      ).toBe(0);

      // And the visitor was never signed in.
      await expectNotSignedIn(page);
      expect(probeDbState(PAUSED_VERIFY_EMAIL).users).toBe(0);
    } finally {
      setRegistrationPaused(false);
    }
  });

  test("OPEN (control): the same flow mints a gated account — proving the affordance works and the pause gate is what blocked it", async ({
    page,
  }) => {
    setRegistrationPaused(false);

    // Baseline: the control email is unknown too.
    expect(probeDbState(OPEN_EMAIL).users).toBe(0);

    await submitEmailOtpRequest(page, OPEN_EMAIL);

    // Open registrations: the code is issued and the visitor lands on the
    // verify screen (the account itself is minted at verify time).
    await page.waitForURL("**/user/verify-otp**", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    const sent = probeDbState(OPEN_EMAIL);
    expect(sent.users, "email signup mints nothing at send time").toBe(0);
    expect(sent.unusedOtps, "open send must issue a code").toBe(1);

    // The correct code creates the account AND signs in, and the brand-new
    // account is properly GATED: it is routed into the mandatory name entry
    // (complete-profile), never dropped on a bare dashboard.
    const code = readIssuedCodeFromDb(OPEN_EMAIL);
    // A successful verify makes the auth-ajax handler navigate immediately,
    // which can evict the JSON body before it is read — so the oracle is the
    // POST status + the resulting navigation, never the body.
    const verifyStatus = await submitCode(page, code);
    expect(verifyStatus, "the correct code must verify").toBeLessThan(400);

    await page.waitForURL(/\/user\/(onboarding|complete-profile)/, {
      timeout: 120_000,
    });
    expect(page.url()).toMatch(/\/user\/(onboarding|complete-profile)/);
    expect(
      probeDbState(OPEN_EMAIL).users,
      "open verify must create the account",
    ).toBe(1);
  });
});
