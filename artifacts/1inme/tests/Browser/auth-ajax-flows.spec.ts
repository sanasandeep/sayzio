import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page, type Route } from "@playwright/test";

// Regression guard for the fetch-based ("AJAX") progressive-enhancement layer
// that `public/js/auth-ajax.js` bolts onto every `<form data-ajax>` auth form.
//
// The existing OTP specs (otp-signup-*.spec.ts) drive the NATIVE `form.submit()`
// path, which BYPASSES the auth-ajax submit listener entirely. Nothing else
// covers the JS layer, so a regression in auth-ajax.js — a broken fetch, a
// dropped CSRF-token rotation, an error slot that never fills — would ship
// silently while the server keeps returning correct JSON.
//
// auth-ajax.js contract (the behaviour these specs pin):
//   - Intercepts submit on `<form data-ajax>`, POSTs via fetch with
//     `X-Requested-With: XMLHttpRequest` so the controller returns its JSON
//     envelope instead of a redirect/HTML render.
//   - On any JSON response carrying `csrf_token`, rewrites BOTH the
//     `<meta name="csrf-token">` content AND every `input[name="_token"]` in
//     the document to the server's fresh token (so the next request/form still
//     validates after the session token rotates).
//   - If the response carries `redirect`, navigates there (full page nav).
//   - Otherwise stays in place: restores the button, fills `[data-ajax-status]`
//     from `status`, and routes `errors` into `[data-err="field"]` (or the
//     `[data-general-err]` box for the `_` key / unknown fields).
//
// What each spec asserts for the IN-PLACE (error / status) paths:
//   1. NO full-page reload — a `window` sentinel planted before submit survives,
//      and the URL is unchanged.
//   2. The correct INLINE message appears in the right slot.
//   3. The CSRF `<meta>` tag (and every `_token` input) is rotated to the
//      server-sent token. See `primeCsrfSentinels`/`assertInPlaceAjax` for how
//      this is proven without breaking the request's own CSRF check.
//
// The SUCCESS paths (password login, OTP send, OTP verify with the right code)
// return a `redirect`; auth-ajax.js navigates, so those specs assert the
// AJAX-driven navigation lands where the server pointed.
//
// Design notes (shared with the sibling OTP specs):
//   - baseURL comes from APP_URL (the runner points it at the ephemeral e2e
//     server; localhost:80 hits the Express api-server instead).
//   - Each test uses a FRESH context so every case starts as a guest.
//   - Per-run-unique emails avoid colliding with a leftover row from an
//     interrupted previous run on the SHARED RDS; the shared prefix lets the
//     beforeAll prune + afterAll teardown target only this spec's rows.
//   - In non-production `OtpService::generate` always issues the fixed dev code
//     and demo-reveal surfaces it on the verify screen; we read it from the
//     banner rather than hard-coding it.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

const EMAIL_PREFIX = "e2e-authajax-";
const RUN_TAG = `${Date.now()}-${Math.floor(Math.random() * 1e6)}`;

// Fixture user with a KNOWN password for the password-login success path.
const PW_USER_EMAIL = `${EMAIL_PREFIX}pw-${RUN_TAG}@example.com`;
const PW_USER_PASSWORD = "Sayzio-e2e-Pw-123!";
// Brand-new address for the register invalid-referral path (never created —
// the referral check fails before account creation).
const REGISTER_EMAIL = `${EMAIL_PREFIX}register-${RUN_TAG}@example.com`;
// New address whose OTP we send + verify (account IS created on the good code).
const OTP_EMAIL = `${EMAIL_PREFIX}otp-${RUN_TAG}@example.com`;
// Unknown admin address for the forgot-password send+resend path (the neutral,
// existence-safe branch returns ok:true without touching mail).
const FORGOT_EMAIL = `${EMAIL_PREFIX}forgot-${RUN_TAG}@example.com`;
// Address for the aged admin password-reset token (expiry path). No real admin
// is needed — the expiry check fires before the admin lookup.
const RESET_EMAIL = `${EMAIL_PREFIX}reset-${RUN_TAG}@example.com`;
const RESET_TOKEN = `e2e-reset-token-${RUN_TAG}`;

const FIXTURE_EMAILS = [PW_USER_EMAIL, REGISTER_EMAIL, OTP_EMAIL];

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
 * Pin the framework auth toggles these forms rely on (email password login on
 * so the password form renders + validates; email OTP on; demo-reveal on so we
 * can read the code; registration not paused), prune any rows a crashed
 * previous run left behind, then seed the two fixtures that need to pre-exist:
 * a password-login user and an ALREADY-EXPIRED admin reset token.
 */
function seedAuthFixtures(): void {
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
use App\\Modules\\Admin\\Models\\Plan;
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Workspace;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Str;
use Carbon\\Carbon;

AppSetting::put('auth_email_password_enabled', true);
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
DB::table('password_reset_tokens')->where('email', 'like', '${EMAIL_PREFIX}%')->delete();

$u = User::firstOrNew(['email' => '${PW_USER_EMAIL}']);
$u->name = 'E2E AjaxPw User';
$u->password = Hash::make('${PW_USER_PASSWORD}');
$u->status = 'active';
$u->email_verified_at = now();
$u->referral_code = Str::random(10);
$u->plan_id = Plan::defaultPlan()?->id;
$u->save();

// OTP verify's GOOD-code path must log into an EXISTING account (the fast
// resolveUserByIdentifier -> Auth::login path, same as password login). If
// this email did not already exist, a good verify would instead run the new
// account createOtpSignupUser signup pipeline (covered by the otp-signup
// specs), which is out of scope here and stalls this single-worker e2e server.
$o = User::firstOrNew(['email' => '${OTP_EMAIL}']);
$o->name = 'E2E AjaxOtp User';
$o->password = Hash::make(Str::random(24));
$o->status = 'active';
$o->email_verified_at = now();
$o->referral_code = Str::random(10);
$o->plan_id = Plan::defaultPlan()?->id;
$o->save();

DB::table('password_reset_tokens')->updateOrInsert(
  ['email' => '${RESET_EMAIL}'],
  ['token' => Hash::make('${RESET_TOKEN}'), 'created_at' => Carbon::now()->subMinutes(90)]
);

echo 'AUTH_AJAX_SEED_OK';
`.trim();

  const out = runTinker(php);
  if (!out.includes("AUTH_AJAX_SEED_OK")) {
    throw new Error("Auth-AJAX fixture seed failed, output:\n" + out);
  }
}

/**
 * Remove every fixture row this spec touched, in FK-dependency order.
 * Best-effort: teardown failures never fail the suite (the next run's beforeAll
 * prune handles anything left behind).
 */
function cleanupFixtures(): void {
  const list = FIXTURE_EMAILS.map((e) => `'${e}'`).join(", ");
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
DB::table('password_reset_tokens')->where('email', 'like', '${EMAIL_PREFIX}%')->delete();

echo 'CLEANUP_OK';
`.trim();

  try {
    runTinker(php);
  } catch {
    // best-effort
  }
}

/**
 * Plant the sentinels an IN-PLACE assertion later checks, WITHOUT breaking the
 * request's own CSRF verification:
 *   - `window.__authAjaxNoReload = 'ALIVE'` — a full-page reload wipes it.
 *   - Corrupt `<meta name="csrf-token">` to a sentinel. Safe: Laravel reads the
 *     token from the POSTed `_token` field FIRST (the submitted form's own
 *     `@csrf` input is untouched and valid), so the corrupted header is never
 *     consulted. If auth-ajax.js rotates the meta, the sentinel is overwritten.
 *   - Append a DETACHED (outside any form) `input[name="_token"]` probe set to a
 *     sentinel. It is never part of any FormData, so it can't affect the CSRF
 *     check — but `updateCsrfToken` rewrites EVERY `_token` input, so it proves
 *     the rotation reached the token inputs too.
 */
async function primeCsrfSentinels(page: Page): Promise<void> {
  await page.evaluate(() => {
    (window as unknown as { __authAjaxNoReload?: string }).__authAjaxNoReload =
      "ALIVE";
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.setAttribute("content", "SENTINEL_STALE_META");
    let probe = document.getElementById(
      "__e2e_csrf_probe",
    ) as HTMLInputElement | null;
    if (!probe) {
      probe = document.createElement("input");
      probe.type = "hidden";
      probe.name = "_token";
      probe.id = "__e2e_csrf_probe";
      document.body.appendChild(probe);
    }
    probe.value = "SENTINEL_STALE_TOKEN";
  });
}

/**
 * Assert the three IN-PLACE guarantees after an AJAX error/status response:
 * no reload happened, and the CSRF meta + all `_token` inputs were rotated to
 * the server-sent token (both were sentinels a moment ago).
 */
async function assertInPlaceAjax(
  page: Page,
  serverToken: string,
): Promise<void> {
  expect(serverToken, "server response carried a csrf_token").toBeTruthy();
  expect(serverToken).not.toBe("SENTINEL_STALE_META");

  const state = await page.evaluate(() => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    const probe = document.getElementById(
      "__e2e_csrf_probe",
    ) as HTMLInputElement | null;
    return {
      alive:
        (window as unknown as { __authAjaxNoReload?: string })
          .__authAjaxNoReload === "ALIVE",
      meta: meta ? meta.getAttribute("content") : null,
      probe: probe ? probe.value : null,
    };
  });

  // (1) No full-page reload — the pre-submit sentinel survived.
  expect(state.alive, "no full-page reload occurred").toBe(true);
  // (3) CSRF meta + every _token input rotated to the fresh server token.
  expect(state.meta, "csrf meta rotated to server token").toBe(serverToken);
  expect(state.probe, "every _token input rotated to server token").toBe(
    serverToken,
  );
}

/**
 * Fire a `<form data-ajax>` through the auth-ajax.js listener and capture the
 * server's JSON response.
 *
 * Uses `form.requestSubmit()` (with `novalidate` set so HTML5 constraints can't
 * silently abort the submit) — this DISPATCHES the submit event the listener
 * hooks, unlike `form.submit()` which bypasses it. Fields are filled via the
 * DOM so forms that are `x-cloak`/`display:none` under Alpine still submit.
 */
async function submitAjaxForm(
  page: Page,
  opts: {
    selector: string;
    fill?: Record<string, string>;
    match: (url: string) => boolean;
  },
): Promise<{ status: number; body: Record<string, unknown> }> {
  // Capture the POST response body INSIDE a route handler (fetch + fulfill)
  // rather than reading it afterwards via `response.json()`. WHY: on a success
  // envelope auth-ajax.js sets `window.location.href = redirect` the instant it
  // parses the JSON, and that navigation discards the page-side response
  // resource — so a later `response.json()` intermittently throws
  // "Protocol error (Network.getResponseBody): No resource with given
  // identifier found". Reading the bytes in the route (before the page ever
  // sees the response) removes the race; fulfilling with the same response
  // keeps Set-Cookie/session-token rotation intact so the redirect nav stays
  // authenticated.
  let captured: { status: number; body: Record<string, unknown> } | null = null;
  let captureErr: unknown = null;
  const handler = async (route: Route) => {
    const req = route.request();
    if (req.method() !== "POST" || !opts.match(req.url())) {
      return route.fallback();
    }
    try {
      // Default route.fetch timeout is 30s; cold authenticated POSTs over the
      // distant RDS can exceed that, so give it the same headroom as the rest
      // of the spec's navigations.
      const res = await route.fetch({ timeout: 120_000 });
      const text = await res.text();
      captured = {
        status: res.status(),
        body: JSON.parse(text) as Record<string, unknown>,
      };
      await route.fulfill({ response: res, body: text });
    } catch (err) {
      captureErr = err;
      await route.abort().catch(() => {});
    }
  };

  await page.route("**/*", handler);
  try {
    await page.evaluate(
      ({ selector, fill }) => {
        const form = document.querySelector<HTMLFormElement>(selector);
        if (!form) throw new Error("form not found: " + selector);
        form.setAttribute("novalidate", "novalidate");
        if (fill) {
          for (const [name, value] of Object.entries(fill)) {
            const input = form.querySelector<HTMLInputElement>(
              `[name="${name}"]`,
            );
            if (!input)
              throw new Error(`field "${name}" not found in ${selector}`);
            input.value = value;
          }
        }
        form.requestSubmit();
      },
      { selector: opts.selector, fill: opts.fill ?? null },
    );

    await expect
      .poll(() => (captured !== null || captureErr !== null ? "done" : "wait"), {
        timeout: 120_000,
      })
      .toBe("done");
  } finally {
    await page.unroute("**/*", handler);
  }

  if (captureErr) throw captureErr;
  return captured!;
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

test.describe("auth AJAX (fetch) flows", () => {
  // Cold authenticated renders over the distant RDS push these past the default
  // budget; give real headroom (mirrors the sibling OTP specs).
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    seedAuthFixtures();
  });

  test.afterAll(() => {
    cleanupFixtures();
  });

  // Abort heavy sub-resources (images/fonts/CSS/media) so each navigation
  // resolves quickly over the PHP-served ephemeral app; documents/redirects/XHR
  // and the auth-ajax.js script itself still load, keeping assertions intact.
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

  test("password login: a WRONG password shows the inline error in place, no reload, CSRF rotated", async ({
    page,
  }) => {
    await page.goto("/user/login", { timeout: 120_000 });
    // The password form is the only auth form whose action ends in /user/login.
    const form = 'form[action$="/user/login"]';
    await page.locator(form).waitFor({ state: "attached", timeout: 60_000 });

    await primeCsrfSentinels(page);
    const { status, body } = await submitAjaxForm(page, {
      selector: form,
      fill: { email: PW_USER_EMAIL, password: "definitely-the-wrong-password" },
      match: (u) => u.endsWith("/user/login"),
    });

    // Business error is delivered as a 200 JSON envelope, not a redirect.
    expect(status).toBe(200);
    expect(body.ok).toBe(false);

    // (1) still on the login page, (2) inline password error is visible with
    // the exact controller message, (3) CSRF rotated + no reload.
    expect(page.url()).toContain("/user/login");
    const err = page.locator(`${form} [data-err="password"]`);
    await expect(err).toBeVisible({ timeout: 15_000 });
    await expect(err).toHaveText("Invalid email or password.");
    await assertInPlaceAjax(page, String(body.csrf_token ?? ""));
  });

  test("password login: the CORRECT password drives the AJAX redirect into the app", async ({
    page,
  }) => {
    await page.goto("/user/login", { timeout: 120_000 });
    const form = 'form[action$="/user/login"]';
    await page.locator(form).waitFor({ state: "attached", timeout: 60_000 });

    const { status, body } = await submitAjaxForm(page, {
      selector: form,
      fill: { email: PW_USER_EMAIL, password: PW_USER_PASSWORD },
      match: (u) => u.endsWith("/user/login"),
    });

    // Success envelope carries a redirect; auth-ajax.js then navigates there.
    expect(status).toBe(200);
    expect(body.ok).toBe(true);
    expect(typeof body.redirect).toBe("string");

    await page.waitForURL((u) => !u.pathname.endsWith("/user/login"), {
      timeout: 120_000,
      waitUntil: "commit",
    });
    expect(page.url()).not.toContain("/user/login");
    // Landed in the authenticated area (dashboard, or an onboarding gate).
    expect(page.url()).toContain("/user/");
  });

  test("OTP send: submitting the email form redirects to the verify screen via AJAX", async ({
    page,
  }) => {
    await page.goto("/user/login", { timeout: 120_000 });
    // Two forms post to /user/send-otp (email + mobile); target the email one.
    const form =
      'form[action$="/user/send-otp"]:has(input[name="login_method"][value="email_otp"])';
    await page.locator(form).waitFor({ state: "attached", timeout: 60_000 });

    const { status, body } = await submitAjaxForm(page, {
      selector: form,
      fill: { identifier: OTP_EMAIL },
      match: (u) => u.includes("/user/send-otp"),
    });

    expect(status).toBe(200);
    expect(body.ok).toBe(true);
    expect(String(body.redirect)).toContain("/user/verify-otp");

    await page.waitForURL("**/user/verify-otp**", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    expect(page.url()).toContain("/user/verify-otp");
  });

  test("OTP verify: a WRONG code errors in place (no reload, CSRF rotated), the GOOD code redirects on", async ({
    page,
  }) => {
    // Reach the verify screen via a PLAIN (non-AJAX) send so the server flashes
    // the demo-reveal banner we read the issued code from. The AJAX send path
    // (covered by the previous test) returns a JSON envelope and never flashes
    // the reveal, so we can't harvest the code through it. This test's focus is
    // the AJAX *verify* behaviour below, not the send.
    await page.goto("/user/login", { timeout: 120_000 });
    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes("/user/send-otp") &&
          r.request().method() === "POST",
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
        // Plain submit() bypasses the auth-ajax.js listener, taking the server's
        // redirect path that flashes otp_demo_reveal onto the verify screen.
        form.submit();
      }, OTP_EMAIL),
    ]);
    await page.waitForURL("**/user/verify-otp**", {
      timeout: 120_000,
      waitUntil: "commit",
    });
    // Fully settle the page before firing the intercepted AJAX POST below. The
    // ephemeral `php -S` app server is single-threaded (one connection at a
    // time); if the browser is still holding its connection loading the verify
    // document/scripts, the separate connection route.fetch() opens deadlocks
    // until timeout. Mirrors the full-load semantics page.goto() gives the
    // other tests.
    await page.waitForLoadState("load", { timeout: 120_000 });
    await page.waitForLoadState("networkidle", { timeout: 120_000 });

    const code = await readRevealCode(page);
    const wrongCode = code === "000000" ? "999999" : "000000";
    const verifyForm = 'form[action$="/user/verify-otp"]';

    // Drive the verify submits through auth-ajax.js's OWN fetch (not the route
    // interception helper the other tests use). WHY: this test reaches the
    // verify screen via a real navigation, and opening a SECOND connection with
    // route.fetch() against the single-threaded `php -S` e2e server on that
    // already-connected page deadlocks. The browser's own in-page fetch reuses
    // its existing connection, so we assert the in-place guarantees off the DOM
    // that auth-ajax.js mutates (mirrors the sibling OTP specs).
    const domSubmit = (selector: string, value: string) =>
      page.evaluate(
        ({ sel, code }) => {
          const form = document.querySelector<HTMLFormElement>(sel);
          if (!form) throw new Error("verify form not found: " + sel);
          form.setAttribute("novalidate", "novalidate");
          const input = form.querySelector<HTMLInputElement>('[name="code"]');
          if (!input) throw new Error("code input not found");
          input.value = code;
          form.requestSubmit();
        },
        { sel: selector, code: value },
      );

    // WRONG code -> in-place error, no reload, CSRF rotated.
    await primeCsrfSentinels(page);
    await domSubmit(verifyForm, wrongCode);

    const codeErr = page.locator(`${verifyForm} [data-err="code"]`);
    await expect(codeErr).toBeVisible({ timeout: 120_000 });
    await expect(codeErr).toHaveText("Invalid or expired OTP.");
    expect(page.url()).toContain("/user/verify-otp");

    // (1) no full-page reload, (3) CSRF meta + every _token input rotated away
    // from the sentinels to the fresh server token (both now share it).
    const state = await page.evaluate(() => {
      const meta = document.querySelector('meta[name="csrf-token"]');
      const probe = document.getElementById(
        "__e2e_csrf_probe",
      ) as HTMLInputElement | null;
      return {
        alive:
          (window as unknown as { __authAjaxNoReload?: string })
            .__authAjaxNoReload === "ALIVE",
        meta: meta ? meta.getAttribute("content") : null,
        probe: probe ? probe.value : null,
      };
    });
    expect(state.alive, "no full-page reload occurred").toBe(true);
    expect(state.meta, "csrf meta rotated off the sentinel").not.toBe(
      "SENTINEL_STALE_META",
    );
    expect(state.probe, "every _token input rotated off the sentinel").not.toBe(
      "SENTINEL_STALE_TOKEN",
    );
    expect(state.meta, "server sent a fresh csrf token").toBeTruthy();
    expect(state.probe, "meta + _token share the fresh server token").toBe(
      state.meta,
    );

    // GOOD code -> success drives the AJAX redirect out of the verify screen.
    // Confirm the verify POST itself returns HTTP 200 (server responded, no
    // hang), then that auth-ajax.js followed the success envelope's redirect
    // off the verify screen. We assert on the response STATUS (available from
    // the status line) rather than its JSON body: on success auth-ajax.js
    // reads the body and immediately navigates, which evicts the response body
    // before Playwright can parse it, so a body read would race the redirect.
    const goodRespP = page.waitForResponse(
      (r) =>
        r.url().includes("/user/verify-otp") &&
        r.request().method() === "POST",
      { timeout: 120_000 },
    );
    await domSubmit(verifyForm, code);
    const goodResp = await goodRespP;
    expect(goodResp.status()).toBe(200);
    await page.waitForURL((u) => !u.pathname.endsWith("/user/verify-otp"), {
      timeout: 120_000,
      waitUntil: "commit",
    });
    expect(page.url()).not.toContain("/user/verify-otp");
  });

  test("register: an INVALID referral code shows the inline field error in place, no reload, CSRF rotated", async ({
    page,
  }) => {
    await page.goto("/user/register", { timeout: 120_000 });
    const form = 'form[action$="/user/register"]';
    await page.locator(form).waitFor({ state: "attached", timeout: 60_000 });

    await primeCsrfSentinels(page);
    const { status, body } = await submitAjaxForm(page, {
      selector: form,
      fill: {
        name: "E2E Referral Tester",
        email: REGISTER_EMAIL,
        password: "Sayzio-e2e-Reg-123!",
        password_confirmation: "Sayzio-e2e-Reg-123!",
        referral_code: "ZZ-NOT-A-REAL-CODE-ZZ",
      },
      match: (u) => u.endsWith("/user/register"),
    });

    expect(status).toBe(200);
    expect(body.ok).toBe(false);
    expect(page.url()).toContain("/user/register");
    const err = page.locator(`${form} [data-err="referral_code"]`);
    await expect(err).toBeVisible({ timeout: 15_000 });
    await expect(err).toHaveText("That referral code is not valid.");
    await assertInPlaceAjax(page, String(body.csrf_token ?? ""));
  });

  test("admin forgot-password: send then resend both update status in place, no reload, CSRF rotated", async ({
    page,
  }) => {
    await page.goto("/admin/forgot-password", { timeout: 120_000 });
    const sendForm = "#forgot-send-form";
    await page.locator(sendForm).waitFor({ state: "attached", timeout: 60_000 });

    // SEND (unknown address -> neutral, existence-safe ok:true status).
    await primeCsrfSentinels(page);
    const send = await submitAjaxForm(page, {
      selector: sendForm,
      fill: { email: FORGOT_EMAIL },
      match: (u) => u.endsWith("/admin/forgot-password"),
    });
    expect(send.status).toBe(200);
    expect(send.body.ok).toBe(true);
    expect(page.url()).toContain("/admin/forgot-password");
    // The send form's status box is the first [data-ajax-status] on the page
    // (the resend one lives inside the still-hidden #resend-section).
    const sendStatus = page.locator("[data-ajax-status]").first();
    await expect(sendStatus).toBeVisible({ timeout: 15_000 });
    await expect(sendStatus).toContainText(/reset link has been sent/i);
    await assertInPlaceAjax(page, String(send.body.csrf_token ?? ""));

    // The success handler reveals the resend section and copies the address in.
    const resendForm = 'form[action$="/admin/forgot-password/resend"]';
    await expect(page.locator(resendForm)).toBeVisible({ timeout: 15_000 });

    // RESEND (still neutral for the unknown address).
    await primeCsrfSentinels(page);
    const resend = await submitAjaxForm(page, {
      selector: resendForm,
      match: (u) => u.includes("/admin/forgot-password/resend"),
    });
    expect(resend.status).toBe(200);
    expect(resend.body.ok).toBe(true);
    expect(page.url()).toContain("/admin/forgot-password");
    const resendStatus = page.locator("#resend-section [data-ajax-status]");
    await expect(resendStatus).toBeVisible({ timeout: 15_000 });
    await expect(resendStatus).toContainText(/reset link has been sent/i);
    await assertInPlaceAjax(page, String(resend.body.csrf_token ?? ""));
  });

  test("admin reset-password: an EXPIRED token shows the general error in place, no reload, CSRF rotated", async ({
    page,
  }) => {
    // The seeded token row is aged 90 minutes (past the 60-minute window).
    await page.goto(
      `/admin/reset-password/${RESET_TOKEN}?email=${encodeURIComponent(RESET_EMAIL)}`,
      { timeout: 120_000 },
    );
    const form = 'form[action$="/admin/reset-password"]';
    await page.locator(form).waitFor({ state: "attached", timeout: 60_000 });

    await primeCsrfSentinels(page);
    const { status, body } = await submitAjaxForm(page, {
      selector: form,
      fill: {
        password: "Sayzio-e2e-Reset-123!",
        password_confirmation: "Sayzio-e2e-Reset-123!",
      },
      match: (u) => u.endsWith("/admin/reset-password"),
    });

    expect(status).toBe(200);
    expect(body.ok).toBe(false);
    // Stays on the reset form (no redirect for the expired-token error).
    expect(page.url()).toContain("/admin/reset-password");
    const generalErr = page.locator(`${form} [data-general-err]`);
    await expect(generalErr).toBeVisible({ timeout: 15_000 });
    await expect(generalErr).toContainText(
      "This password reset link has expired. Please request a new one.",
    );
    await assertInPlaceAjax(page, String(body.csrf_token ?? ""));
  });
});
