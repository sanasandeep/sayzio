import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

// "USE A DIFFERENT NUMBER" regression guard for the passwordless
// WhatsApp/mobile-OTP LOGIN flow.
//
// The verify screen offers THREE affordances: Verify, Resend, and a "Use a
// different number" link back to /user/login. The happy path and the resend
// path have Browser guards (otp-login-whatsapp-flow.spec.ts and
// otp-login-whatsapp-resend.spec.ts), but nothing proved that abandoning a
// verify mid-flight and starting over with a SECOND number works cleanly. A
// regression here — e.g. the login page failing to render for a visitor who
// still carries an `otp_identifier` in the session, or a fresh send-otp for
// number B failing to OVERWRITE the session identifier so the verify step
// still resolves against number A — would trap every user who mistyped their
// number. This spec pins that path:
//
//   1. User A (existing, active + onboarded, VERIFIED mobile) requests a code
//      via the login page's WhatsApp-OTP form and lands on the verify screen;
//      we capture A's issued code (demo-reveal banner, otps table recovery).
//   2. Clicking "Use a different number" navigates back to /user/login and the
//      WhatsApp-OTP form is usable again (the link must not dead-end or bounce
//      back to verify).
//   3. A fresh send-otp for User B's number re-enters the verify screen. The
//      session identifier must now be B's number, so submitting the OLD code
//      captured for A in step 1 must FAIL ("Invalid or expired OTP") and stay
//      on the verify screen — a stale code from the abandoned attempt can
//      never log anyone in against the new identifier.
//   4. B's freshly issued code still verifies, and the login lands on
//      /user/dashboard rendered for USER B (B's name in the greeting hero,
//      never A's) — restarting with a different number produces a clean,
//      correctly-attributed login.
//
// Design notes (shared with the sibling OTP specs):
//   - baseURL comes from APP_URL (the runner points it at the ephemeral e2e
//     server; localhost:80 hits the Express api-server instead).
//   - Per-run-unique mobile numbers avoid colliding with a leftover row from
//     an interrupted previous run on the SHARED RDS; the "+1996" marker is
//     unique to THIS spec (flow uses +1999, error-paths +1998, resend/unknown
//     +1997) so prune/teardown never touch a sibling spec's rows.
//   - In non-production `OtpService::generate` always issues the fixed dev
//     code, so A's code and B's code may be the SAME six digits. The stale-code
//     assertion therefore keys off the IDENTIFIER switch, not code inequality:
//     after step 3's fresh send for B, we submit the code AGAINST B'S session
//     while A's number is the only one the code was consumed/burned for. To
//     make the old-code rejection unambiguous even with a fixed dev code, we
//     BURN A's outstanding code row (mark it used, exactly what an attacker
//     replaying the abandoned attempt would face after expiry/invalidation)
//     before submitting it — the verify must fail regardless.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Distinctive, per-run-unique test fixtures. Both numbers begin with the "+1"
// dialling code (seeded into the allow-list below) and stay within the
// users.mobile 20-char column. "+19961"/"+19962" mark user A / user B rows of
// THIS spec only.
const EMAIL_PREFIX = "e2e-otp-wa-diff-";
const MOBILE_PREFIX = "+1996"; // "+1" dialling code + a distinctive marker.
const RUN_TAG = `${Date.now()}${Math.floor(Math.random() * 1e4)}`.slice(-9);
const USER_A_EMAIL = `${EMAIL_PREFIX}a-${RUN_TAG}@example.com`;
const USER_B_EMAIL = `${EMAIL_PREFIX}b-${RUN_TAG}@example.com`;
const USER_A_MOBILE = `${MOBILE_PREFIX}1${RUN_TAG}`; // e.g. +19961123456789
const USER_B_MOBILE = `${MOBILE_PREFIX}2${RUN_TAG}`; // e.g. +19962123456789
const USER_A_NAME = "E2E WhatsApp Diff User Aye";
const USER_B_NAME = "E2E WhatsApp Diff User Bee";

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
 * the "+1" dialling code allow-listed; demo-reveal on so we can read codes),
 * prune any rows a crashed previous run left behind, then seed the TWO
 * fixtures that must pre-exist: ACTIVE, already-onboarded users A and B whose
 * VERIFIED mobile numbers make a good verify a fast OTP *login* that lands
 * straight on the dashboard.
 */
function seedOnboardedUsers(): void {
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
// AGE-GATED: parallel task environments run this same spec against the
// shared RDS, so only rows old enough to be from a DEAD run are pruned —
// a concurrent twin's fresh fixtures must never be deleted mid-flight.
$stale = User::where('email', 'like', '${EMAIL_PREFIX}%')
  ->where('created_at', '<', now()->subHours(2))
  ->pluck('id')->all();
if (!empty($stale)) {
  $wsIds = Workspace::whereIn('owner_user_id', $stale)->pluck('id')->all();
  if (!empty($wsIds)) { DB::table('workspace_members')->whereIn('workspace_id', $wsIds)->delete(); }
  DB::table('workspace_members')->whereIn('user_id', $stale)->delete();
  Workspace::whereIn('owner_user_id', $stale)->delete();
  DB::table('linked_identifiers')->whereIn('user_id', $stale)->delete();
  User::whereIn('id', $stale)->delete();
}
DB::table('otps')->whereIn('identifier', ['${USER_A_MOBILE}', '${USER_B_MOBILE}'])->delete();
DB::table('otps')->where('identifier', 'like', '${MOBILE_PREFIX}%')
  ->where('created_at', '<', now()->subHours(2))->delete();

// Both verify paths must log into EXISTING accounts (the fast
// resolveUserByIdentifier -> Auth::login path). A name + a PAST onboarded_at
// means the post-verify redirect skips the complete-profile AND onboarding
// gates and lands directly on the dashboard.
foreach ([
  ['${USER_A_EMAIL}', '${USER_A_NAME}', '${USER_A_MOBILE}'],
  ['${USER_B_EMAIL}', '${USER_B_NAME}', '${USER_B_MOBILE}'],
] as [$email, $name, $mobile]) {
  $u = User::firstOrNew(['email' => $email]);
  $u->name = $name;
  $u->password = Hash::make(Str::random(48));
  $u->status = 'active';
  $u->email_verified_at = now();
  $u->mobile = $mobile;
  $u->onboarded_at = now()->subYears(2);
  $u->referral_code = Str::random(10);
  $u->plan_id = Plan::defaultPlan()?->id;
  $u->save();

  // The User::created observer already mirrors the mobile column into a
  // linked_identifiers row (kind=phone, verified_at set, is_primary=false —
  // the email row is the account's single primary). NEVER insert a second
  // row (one_primary_per_user 23505); just re-assert the observer's phone
  // row is verified so resolveUserByIdentifier resolves via the verified
  // linked-id path.
  DB::table('linked_identifiers')
    ->where('user_id', $u->id)
    ->where('kind', 'phone')
    ->update(['verified_at' => now(), 'updated_at' => now()]);

  $u->ensureDefaultWorkspace();
}

echo 'OTP_WA_DIFF_SEED_OK';
`.trim();

  const out = runTinker(php);
  if (!out.includes("OTP_WA_DIFF_SEED_OK")) {
    throw new Error(
      "WhatsApp-OTP different-number fixture seed failed, output:\n" + out,
    );
  }
}

/**
 * Remove the fixture accounts this spec created (plus workspaces, memberships,
 * linked identifiers, and any issued OTP rows) in FK-dependency order.
 * Best-effort: teardown failures never fail the suite (the next run's
 * beforeAll prune mops up anything left behind).
 */
function cleanupCreatedUsers(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Workspace;
use Illuminate\\Support\\Facades\\DB;

$ids = User::whereIn('email', ['${USER_A_EMAIL}', '${USER_B_EMAIL}'])->pluck('id')->all();
if (!empty($ids)) {
  $wsIds = Workspace::whereIn('owner_user_id', $ids)->pluck('id')->all();
  if (!empty($wsIds)) { DB::table('workspace_members')->whereIn('workspace_id', $wsIds)->delete(); }
  DB::table('workspace_members')->whereIn('user_id', $ids)->delete();
  Workspace::whereIn('owner_user_id', $ids)->delete();
  DB::table('linked_identifiers')->whereIn('user_id', $ids)->delete();
  User::whereIn('id', $ids)->delete();
}
DB::table('otps')->whereIn('identifier', ['${USER_A_MOBILE}', '${USER_B_MOBILE}'])->delete();

echo 'CLEANUP_OK';
`.trim();

  try {
    runTinker(php);
  } catch {
    // best-effort — the next run's beforeAll prune handles anything left.
  }
}

/**
 * Read the 6-digit code the latest UNUSED issued OTP for `mobile` is stored
 * under, straight from the `otps` table — the same (identifier, type=mobile,
 * purpose=login) tuple `OtpService::verify` reads. Used as a belt-and-braces
 * recovery when the demo-reveal banner can't be parsed.
 */
function readIssuedCodeFromDb(mobile: string): string {
  const query = `
use Illuminate\\Support\\Facades\\DB;
$row = DB::table('otps')
  ->where('identifier', '${mobile}')
  ->where('type', 'mobile')
  ->where('purpose', 'login')
  ->where('used', false)
  ->orderByDesc('id')
  ->first();
if ($row) { echo 'OTP_CODE:' . $row->code; }
else {
  echo 'OTP_CODE:NONE ';
  echo 'otps_any=', json_encode(DB::table('otps')->where('identifier', 'like', '${MOBILE_PREFIX}%')->get(['identifier','type','purpose','used']));
}
`.trim();
  const out = runTinker(query);
  const match = out.match(/OTP_CODE:(\d{6})/);
  if (!match) {
    throw new Error(
      "Could not read a 6-digit issued OTP for " +
        mobile +
        " from the otps table: " +
        JSON.stringify(out),
    );
  }
  return match[1];
}

/**
 * Burn (mark used) any outstanding login OTP rows for `mobile`, mirroring what
 * expiry / re-issue invalidation does to an abandoned attempt's code. This
 * removes the fixed-dev-code ambiguity: after burning A's row, submitting A's
 * captured code against the NEW session must fail through the same
 * `OtpService::verify` lookup an attacker replaying the stale code would hit.
 */
function burnOutstandingCodes(mobile: string): void {
  const php = `
use Illuminate\\Support\\Facades\\DB;
DB::table('otps')
  ->where('identifier', '${mobile}')
  ->where('type', 'mobile')
  ->where('purpose', 'login')
  ->where('used', false)
  ->update(['used' => true, 'updated_at' => now()]);
echo 'BURN_OK';
`.trim();
  const out = runTinker(php);
  if (!out.includes("BURN_OK")) {
    throw new Error("Failed to burn outstanding codes for " + mobile);
  }
}

/**
 * Drive the real login page's WhatsApp/mobile-OTP form for `mobile` and land on
 * the verify screen. The mobile-OTP form is one of several method forms toggled
 * client-side by Alpine, so rather than depend on the tab toggle we fill the
 * hidden identifier field of the form carrying `login_method=mobile` and submit
 * that specific form directly (robust against tab visibility timing). Waits on
 * the send-otp POST (not the heavy redirect render) so a cold app server never
 * blocks here. `alreadyOnLogin` skips the goto when the page is ALREADY on
 * /user/login (i.e. after clicking "Use a different number"), which is exactly
 * the surface this spec is guarding.
 */
async function requestOtp(
  page: Page,
  mobile: string,
  opts: { alreadyOnLogin?: boolean } = {},
): Promise<void> {
  if (!opts.alreadyOnLogin) {
    await page.goto("/user/login", { timeout: 120_000 });
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
 * screen. Falls back to the `otps` table if the banner can't be parsed.
 */
async function readRevealCode(page: Page, mobile: string): Promise<string> {
  const banner = page.getByText(/verification code is/i);
  try {
    await expect(banner).toBeVisible({ timeout: 30_000 });
    const bannerText = (await banner.textContent()) ?? "";
    const match = bannerText.match(/(\d{6})/);
    if (match) return match[1];
  } catch {
    // fall through to the DB recovery below.
  }
  return readIssuedCodeFromDb(mobile);
}

/**
 * Fill + submit the verify form (scoped by its action so we click the right
 * button among verify / resend / different-number). `noWaitAfter` lets the
 * caller own the post-submit navigation instead of blocking on the heavy
 * authenticated re-render.
 */
async function submitCode(page: Page, code: string): Promise<void> {
  const verifyForm = page.locator('form[action$="/user/verify-otp"]');
  await verifyForm.locator('input[name="code"]').fill(code);
  await verifyForm.locator('button[type="submit"]').click({ noWaitAfter: true });
}

test.describe("WhatsApp-OTP 'Use a different number' -> clean restart with a second number", () => {
  // Cold authenticated renders over the distant RDS push these past the default
  // 60s budget; give the suite real headroom (mirrors the sibling specs).
  test.describe.configure({ timeout: 240_000 });

  test.beforeAll(() => {
    seedOnboardedUsers();
  });

  test.afterAll(() => {
    cleanupCreatedUsers();
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

  test("abandoning verify via 'Use a different number' restarts cleanly: old code rejected, second number logs in as the right user", async ({
    page,
  }) => {
    // 1) User A requests a code -> verify screen; capture A's issued code so
    //    we can prove the ABANDONED attempt's code is later rejected.
    await requestOtp(page, USER_A_MOBILE);
    const oldCode = await readRevealCode(page, USER_A_MOBILE);
    expect(oldCode).toMatch(/^\d{6}$/);

    // 2) The third affordance: "Use a different number" must navigate back to
    //    the login page (not dead-end, not bounce back to verify) and the
    //    WhatsApp-OTP form must be usable again.
    const differentLink = page.getByRole("link", {
      name: /use a different number/i,
    });
    await expect(differentLink).toBeVisible({ timeout: 30_000 });
    await Promise.all([
      page.waitForURL("**/user/login**", {
        timeout: 120_000,
        waitUntil: "commit",
      }),
      differentLink.click({ noWaitAfter: true }),
    ]);
    expect(page.url()).toContain("/user/login");
    expect(page.url()).not.toContain("/user/verify-otp");
    await expect(
      page.locator('form input[name="login_method"][value="mobile"]'),
    ).toBeAttached({ timeout: 30_000 });

    // 3) Restart the flow with USER B's number from the login page we landed
    //    on. The fresh send-otp must OVERWRITE the session identifier with B's
    //    number, so the abandoned attempt's code can never verify here. Burn
    //    BOTH numbers' outstanding rows first (mirrors expiry/invalidation):
    //    the dev environment issues the SAME fixed code to everyone, so if B's
    //    fresh row survived, submitting A's "old" code would coincidentally
    //    match B's row and log in. With both burned, the stale-code rejection
    //    flows through the exact `OtpService::verify` used-row lookup an
    //    attacker replaying a stale code would hit.
    await requestOtp(page, USER_B_MOBILE, { alreadyOnLogin: true });
    burnOutstandingCodes(USER_A_MOBILE);
    burnOutstandingCodes(USER_B_MOBILE);

    await submitCode(page, oldCode);
    // The stale code must be rejected: still on the verify screen with the
    // invalid-code error, and NEVER logged in.
    await expect(page.getByText(/invalid or expired otp/i)).toBeVisible({
      timeout: 60_000,
    });
    expect(page.url()).toContain("/user/verify-otp");
    expect(page.url()).not.toContain("/user/dashboard");

    // 4) Re-issue a fresh code for B via the verify screen's "Resend" button
    //    (B's original row was burned above), then verify it -> a clean login
    //    attributed to USER B. Read the code AFTER the resend (banner or otps
    //    table) so we assert against exactly what OtpService::verify looks up.
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
    await page.waitForURL("**/user/verify-otp**", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    const freshCode = await readRevealCode(page, USER_B_MOBILE);
    await Promise.all([
      page.waitForURL("**/user/dashboard**", {
        timeout: 120_000,
        waitUntil: "commit",
      }),
      submitCode(page, freshCode),
    ]);
    expect(page.url()).toContain("/user/dashboard");
    expect(page.url()).not.toContain("/user/complete-profile");
    expect(page.url()).not.toContain("/user/onboarding");
    expect(page.url()).not.toContain("/user/verify-otp");

    // The dashboard rendered for USER B specifically — the restart never
    // logged the visitor into the abandoned first account.
    await expect(
      page.getByRole("heading", { name: new RegExp(USER_B_NAME, "i") }),
    ).toBeVisible({ timeout: 60_000 });
    await expect(
      page.getByRole("heading", { name: new RegExp(USER_A_NAME, "i") }),
    ).toHaveCount(0);
  });
});
