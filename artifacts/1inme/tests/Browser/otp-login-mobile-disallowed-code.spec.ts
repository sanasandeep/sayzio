import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

// End-to-end regression guard for the ALLOW-LIST GATE on the mobile/WhatsApp OTP
// login path. Mobile OTP login is off by default; when an admin switches it on
// they also restrict it to an allow-list of international dialling codes
// (`AuthMethods::isAllowedMobile`, e.g. only "+1"/"+91"). The happy-path sibling
// (otp-login-whatsapp-flow.spec.ts) proves an ALLOWED number gets a code; this
// spec proves the inverse — a number OUTSIDE the allow-list is refused at
// `sendOtp` before any code is generated.
//
// Why this matters (threat model, DoS section): if a regression ever dropped the
// `isAllowedMobile` guard in `AuthController::sendOtp`, ANY number worldwide
// could request one-time codes, opening the WhatsApp/OTP send path (and the
// mail/AI/OTP abuse surface behind it) to unbounded, unauthenticated use. A
// deterministic feature test can't see the real JS-driven login page's mobile
// method form, the CSRF-protected native submit, or the round-trip that lands
// the visitor back on the login page with the rejection surfaced in the
// `identifier` error slot. This spec drives that browser flow against a booted
// `artisan serve` so a UI/controller-level regression still fails a gate.
//
// The flow under test (all server-side, no client trust):
//   1. Mobile login is enabled and the allow-list is pinned to "+1"/"+91"
//      (NOT the disallowed dialling code used below).
//   2. A guest submits the login page's mobile-OTP form with a "+44"
//      (disallowed) number. `AuthController::sendOtp` takes the mobile branch,
//      fails the `isAllowedMobile` check, and returns `back()->withErrors(...)`
//      WITHOUT resolving an account or generating/sending a code.
//   3. The visitor lands back on `/user/login` (NOT the verify screen) with the
//      "That country code isn't supported" message rendered in the mobile
//      form's `identifier` error slot, and — asserted straight from the `otps`
//      table — NO code was ever issued for the disallowed number.
//
// Design notes (shared with the sibling OTP specs):
//   - baseURL comes from APP_URL (the runner points it at the ephemeral e2e
//     server; localhost:80 hits the Express api-server instead).
//   - Each test uses a FRESH browser context so every case starts as a guest.
//   - A per-run-unique mobile number avoids colliding with a leftover row from
//     an interrupted previous run on the SHARED RDS; the shared prefix lets the
//     beforeAll prune + afterAll teardown target only this spec's rows.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Distinctive, per-run-unique test fixtures. The mobile number begins with the
// "+44" (United Kingdom) dialling code — deliberately NOT on the allow-list
// seeded below ("+1"/"+91") — so `isAllowedMobile` MUST reject it. The unique
// suffix avoids collisions with a leftover row from an interrupted previous run
// on the SHARED RDS (see repo memory "e2e fixture aliases on shared RDS"); the
// shared prefix lets both the afterAll teardown and the beforeAll stale-prune
// target only this spec's otps rows. Stays within the users.mobile 20-char
// column even though no user is created here.
const MOBILE_PREFIX = "+44777"; // disallowed "+44" dialling code + a marker.
const RUN_TAG = `${Date.now()}${Math.floor(Math.random() * 1e4)}`.slice(-9);
const DISALLOWED_MOBILE = `${MOBILE_PREFIX}${RUN_TAG}`; // e.g. +44777123456789

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
 * Pin the auth toggles this gate relies on (mobile/WhatsApp login ON; the
 * allow-list restricted to "+1"/"+91" so the fixture "+44" number is refused)
 * and prune any `otps` rows a crashed previous run of THIS spec left behind. No
 * user fixture is needed: the allow-list check runs BEFORE any account lookup,
 * so the rejection is proven without seeding an account.
 */
function seedSettings(): void {
  // NOTE: this string is passed straight to `tinker --execute=`. In a JS
  // template literal, `\\` becomes the single backslash PHP namespaces need,
  // while `$var` stays literal.
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
use App\\Modules\\Common\\Support\\AuthMethods;
use Illuminate\\Support\\Facades\\DB;

AppSetting::put(AuthMethods::SETTING_MOBILE_ENABLED, true);
AppSetting::put(AuthMethods::SETTING_ALLOWED_CODES, ['+1', '+91']);
AppSetting::put('auth_email_otp_enabled', true);
AppSetting::put('auth_demo_reveal_otp_enabled', true);
AppSetting::put('auth_registration_paused', false);

// Prune any codes a crashed previous run of THIS spec left behind so the
// "no OTP was issued" assertion can never trip over stale rows.
DB::table('otps')->where('identifier', 'like', '${MOBILE_PREFIX}%')->delete();

echo 'OTP_DISALLOWED_SEED_OK';
`.trim();

  const out = runTinker(php);
  if (!out.includes("OTP_DISALLOWED_SEED_OK")) {
    throw new Error(
      "Disallowed-code OTP fixture seed failed, output:\n" + out,
    );
  }
}

/**
 * Best-effort teardown: remove any `otps` rows issued for this spec's mobile
 * number. There should be none (the whole point of the gate), but if a
 * regression let one through we still tidy up so the next run starts clean.
 * Teardown failures never fail the suite (the next run's beforeAll prune mops
 * up anything left behind).
 */
function cleanup(): void {
  const php = `
use Illuminate\\Support\\Facades\\DB;
DB::table('otps')->where('identifier', 'like', '${MOBILE_PREFIX}%')->delete();
echo 'CLEANUP_OK';
`.trim();
  try {
    runTinker(php);
  } catch {
    // best-effort — the next run's beforeAll prune handles anything left.
  }
}

/**
 * Count the OTP rows issued for the disallowed number, straight from the `otps`
 * table — the same source `OtpService::generate` writes to. A passing gate
 * leaves this at zero: the request is refused before a code is ever generated.
 */
function countIssuedCodes(): number {
  const php = `
use Illuminate\\Support\\Facades\\DB;
$n = DB::table('otps')->where('identifier', '${DISALLOWED_MOBILE}')->count();
echo 'OTP_COUNT:' . $n;
`.trim();
  const out = runTinker(php);
  const match = out.match(/OTP_COUNT:(\d+)/);
  if (!match) {
    throw new Error(
      "Could not read the otps row count from the table: " +
        JSON.stringify(out),
    );
  }
  return Number(match[1]);
}

/**
 * Drive the real login page's mobile-OTP form for `mobile` and submit it. The
 * mobile-OTP form is one of several method forms toggled client-side by Alpine,
 * so rather than depend on the tab toggle we fill the identifier field of the
 * form carrying `login_method=mobile` and submit that specific form directly
 * (robust against tab visibility timing). Waits on the send-otp POST (not the
 * heavy redirect render) so a cold app server never blocks here.
 */
async function submitDisallowedMobile(page: Page, mobile: string): Promise<void> {
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
      if (!form) throw new Error("mobile-OTP form not found on /user/login");
      const input = form.querySelector<HTMLInputElement>(
        'input[name="identifier"]',
      );
      if (!input) throw new Error("identifier input not found");
      input.value = num;
      form.submit();
    }, mobile),
  ]);
  // The native submit's `back()->withErrors(...)` redirects to the referring
  // login page; wait for that navigation to settle before asserting.
  await page.waitForURL("**/user/login**", {
    timeout: 120_000,
    waitUntil: "commit",
  });
}

test.describe("mobile-OTP login rejects a disallowed dialling code", () => {
  // Cold renders over the distant RDS push these past the default 60s budget;
  // give the suite real headroom (mirrors the sibling OTP specs).
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    seedSettings();
  });

  test.afterAll(() => {
    cleanup();
  });

  // Abort heavy sub-resources (images/fonts/CSS/media) so each navigation
  // resolves quickly over the PHP-served ephemeral app; documents/redirects/XHR
  // still flow, keeping the flow assertions intact. This spec asserts only
  // server-rendered form behaviour + the otps table, never styling.
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

  test("a number outside the allow-list is refused and no code is issued", async ({
    page,
  }) => {
    // 1) Submit the mobile-OTP form with a disallowed "+44" number.
    await submitDisallowedMobile(page, DISALLOWED_MOBILE);

    // 2) The visitor is bounced back to the login page — NOT the verify screen.
    //    A code was never issued, so there is nothing to verify.
    expect(page.url()).toContain("/user/login");
    expect(page.url()).not.toContain("/user/verify-otp");

    // 3) The rejection is surfaced clearly: the "unsupported country code"
    //    message (from `AuthController::sendOtp`) renders in the MOBILE form's
    //    `identifier` error slot. We scope to that form because the login page
    //    echoes `$errors->first('identifier')` into BOTH the email-OTP and the
    //    mobile method forms (only one is shown at a time via Alpine `x-show`);
    //    an unscoped match could pick the hidden email-OTP copy. After a failed
    //    native submit `old('login_method')` makes the mobile tab the active
    //    one, so this slot is the one genuinely visible to the user. The `<p>`'s
    //    `hidden` attribute is also dropped when `$errors->has('identifier')` is
    //    true.
    const mobileForm = page.locator(
      'form:has(input[name="login_method"][value="mobile"])',
    );
    const rejection = mobileForm.locator('[data-err="identifier"]');
    await expect(rejection).toBeVisible({ timeout: 30_000 });
    await expect(rejection).toContainText(/country code isn't supported/i);
    // The message also names the allowed codes so the user knows what works.
    await expect(rejection).toContainText("+1");
    await expect(rejection).toContainText("+91");

    // 4) The decisive server-side assertion: NO OTP was ever generated for the
    //    disallowed number. This is what a dropped `isAllowedMobile` guard would
    //    break (it would issue a code for any number worldwide).
    expect(countIssuedCodes()).toBe(0);
  });
});
