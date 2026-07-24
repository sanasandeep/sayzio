import { execFileSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

// End-to-end verification of the Zio Browser 2FA sign-in against the REAL
// Laravel API (Task: confirm the browser's 2FA sign-in works end-to-end
// against a real account).
//
// The Zio Browser's AuthModal ('2fa' step) was previously covered only by
// unit tests that mock the network. This spec renders the REAL AuthModal
// component (built via a tiny Vite harness in
// artifacts/zio-browser/tests/e2e-2fa/) in a Playwright page and drives the
// actual sign-in UI against the live Laravel /api/v1 endpoints, proving the
// real contract end-to-end:
//   - password login on a TOTP-enrolled account fails with 403
//     `totp_required` + `details.challenge_token`, and the modal switches to
//     the challenge step;
//   - a WRONG authenticator code hits POST /auth/2fa/challenge/verify, gets
//     400 `invalid_2fa_code`, and shows an inline error while allowing retry;
//   - the recovery-code toggle switches the input and a real single-use
//     recovery code completes sign-in via /auth/2fa/backup-codes/verify;
//   - a valid TOTP code (computed with the app's own TotpService) completes
//     sign-in and the issued Sanctum token is proven against GET /auth/me;
//   - an EXPIRED challenge token gets the server's 410 `challenge_expired`
//     and the modal restarts the flow from the method step.
//
// Network wiring: AuthModal hard-codes `https://1in.me` as its API base (the
// production origin baked into the desktop build). The spec intercepts those
// requests and forwards them to the live local Laravel server (APP_URL), so
// every request/response is the genuine server contract — nothing is mocked.
// The ONLY payload substitution is in the expired-challenge test, where the
// server-minted challenge_token in the login response is swapped for another
// server-minted (but expired) token, so the 410 path can be exercised without
// waiting out the 10-minute TTL.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);
const WORKSPACE_ROOT = path.resolve(ARTIFACT_ROOT, "../..");
const HARNESS_DIR = path.join(
  WORKSPACE_ROOT,
  "artifacts/zio-browser/tests/e2e-2fa",
);
const HARNESS_DIST = path.join(HARNESS_DIR, "dist");

// The origin the harness page is served from (fully intercepted — never
// resolved by DNS) and the production API origin AuthModal hard-codes.
const HARNESS_ORIGIN = "http://zio-2fa-harness.localtest";
const PROD_API_ORIGIN = "https://1in.me";

// Live Laravel server. The runner (run-validation.sh) always exports APP_URL;
// NOTE: localhost:80 would hit the Express api-server, not Laravel.
const APP_URL = (process.env.APP_URL || "http://localhost:5050").replace(/\/$/, "");

// Per-run-unique fixture account (shared-RDS-safe; see repo memory on e2e
// fixture aliases). The prefix lets the prune/teardown target only this spec.
const EMAIL_PREFIX = "e2e-zio-2fa-";
const RUN_TAG = `${Date.now()}-${Math.floor(Math.random() * 1e6)}`;
const USER_EMAIL = `${EMAIL_PREFIX}${RUN_TAG}@example.com`;
const USER_PASSWORD = "e2e-zio-2fa-password-123";

// Fixed valid base32 TOTP secret so the spec can compute live codes via the
// app's own TotpService (same as the sibling 2FA challenge spec).
const TOTP_SECRET = "JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP";

// A known plaintext recovery code seeded (hashed) on the account.
const RECOVERY_CODE = "ZIOE2E-RECOVERY-0001";

/** Run a `php artisan tinker` snippet with retries (distant-RDS blips). */
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

/** Seed the TOTP-enrolled fixture account (and prune stale ones). */
function seedUser(): void {
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Workspace;
use App\\Modules\\Admin\\Models\\Plan;
use Illuminate\\Support\\Facades\\Crypt;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

AppSetting::put('auth_email_password_enabled', true);
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

$plan = Plan::defaultPlan();
$user = User::create([
  'name' => 'Zio Browser 2FA User',
  'email' => '${USER_EMAIL}',
  'password' => Hash::make('${USER_PASSWORD}'),
  'plan_id' => $plan?->id,
  'status' => 'active',
  'email_verified_at' => now(),
  'onboarded_at' => now(),
]);

$user->forceFill([
  'two_factor_secret' => Crypt::encryptString('${TOTP_SECRET}'),
  'two_factor_confirmed_at' => now(),
  'two_factor_recovery_codes' => Crypt::encryptString(json_encode([
    Hash::make('${RECOVERY_CODE}'),
    Hash::make('unused-spare-code'),
  ])),
])->save();

$user->ensureDefaultWorkspace();

echo 'ZIO2FA_SEED_OK:' . $user->id . ':END';
`.trim();
  const out = runTinker(php);
  if (!out.includes("ZIO2FA_SEED_OK")) {
    throw new Error("Zio 2FA fixture seed failed, output:\n" + out);
  }
}

/** Best-effort teardown (FK-dependency order). */
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
  DB::table('personal_access_tokens')->whereIn('tokenable_id', $ids)->where('tokenable_type', 'like', '%User')->delete();
  User::whereIn('id', $ids)->delete();
}
echo 'CLEANUP_OK';
`.trim();
  try {
    runTinker(php);
  } catch {
    // best-effort — the next run's beforeAll prune mops up.
  }
}

/** Compute the live 6-digit TOTP code via the app's own TotpService. */
function currentTotpCode(): string {
  const php = `
$svc = app(App\\Modules\\User\\Services\\TotpService::class);
echo 'CODE:' . $svc->codeAt('${TOTP_SECRET}', intdiv(time(), 30)) . ':END';
`.trim();
  const out = runTinker(php);
  const m = out.match(/CODE:(\d{6}):END/);
  if (!m) throw new Error("Could not compute a TOTP code, tinker output:\n" + out);
  return m[1];
}

/** Mint a REAL (server-encrypted) but already-expired challenge token. */
function expiredChallengeToken(): string {
  const php = `
use App\\Modules\\User\\Models\\User;
use Illuminate\\Support\\Facades\\Crypt;
$user = User::where('email', '${USER_EMAIL}')->firstOrFail();
echo 'TOK[' . Crypt::encrypt(['p' => '2fa_login', 'uid' => $user->id, 'exp' => now()->subMinute()->getTimestamp()]) . ']END';
`.trim();
  const out = runTinker(php);
  const m = out.match(/TOK\[(.+?)\]END/s);
  if (!m) throw new Error("Could not mint an expired challenge token:\n" + out);
  return m[1].trim();
}

/** Build the Vite harness bundle (real AuthModal + window.zio shim). */
function buildHarness(): void {
  execFileSync(
    "pnpm",
    ["exec", "vite", "build", "--config", "tests/e2e-2fa/vite.config.ts"],
    { cwd: path.join(WORKSPACE_ROOT, "artifacts/zio-browser"), encoding: "utf8" },
  );
  if (!fs.existsSync(path.join(HARNESS_DIST, "index.html"))) {
    throw new Error("Harness build produced no dist/index.html");
  }
}

const MIME: Record<string, string> = {
  ".html": "text/html",
  ".js": "text/javascript",
  ".css": "text/css",
  ".map": "application/json",
};

// CORS headers for the intercepted cross-origin API responses. The harness
// page's fetch() to https://1in.me is cross-origin, so the browser enforces
// CORS on the fulfilled responses (and preflights the JSON POSTs).
const CORS_HEADERS = {
  "access-control-allow-origin": "*",
  "access-control-allow-methods": "GET,POST,PUT,PATCH,DELETE,OPTIONS",
  "access-control-allow-headers": "*",
};

interface HarnessState {
  /** When set, the next successful login response gets its challenge_token swapped. */
  swapChallengeTokenWith: string | null;
}

/**
 * Wire up the page: serve the built harness from HARNESS_ORIGIN and forward
 * every https://1in.me/api/v1/* request to the live Laravel server.
 */
async function wirePage(page: Page, state: HarnessState): Promise<void> {
  await page.route(`${HARNESS_ORIGIN}/**`, async (route) => {
    const url = new URL(route.request().url());
    const rel = url.pathname === "/" ? "/index.html" : url.pathname;
    const file = path.join(HARNESS_DIST, rel);
    if (!file.startsWith(HARNESS_DIST) || !fs.existsSync(file)) {
      return route.fulfill({ status: 404, body: "not found" });
    }
    return route.fulfill({
      status: 200,
      contentType: MIME[path.extname(file)] ?? "application/octet-stream",
      body: fs.readFileSync(file),
    });
  });

  await page.route(`${PROD_API_ORIGIN}/**`, async (route) => {
    const request = route.request();
    if (request.method() === "OPTIONS") {
      return route.fulfill({ status: 204, headers: CORS_HEADERS });
    }
    const target = request.url().replace(PROD_API_ORIGIN, APP_URL);
    const response = await route.fetch({ url: target });
    let body: Buffer | string = await response.body();

    // Expired-challenge scenario: substitute the server-minted token in the
    // totp_required error with a server-minted EXPIRED one.
    if (
      state.swapChallengeTokenWith &&
      request.url().includes("/api/v1/auth/login")
    ) {
      try {
        const json = JSON.parse(body.toString("utf8"));
        if (json?.error?.details?.challenge_token) {
          json.error.details.challenge_token = state.swapChallengeTokenWith;
          body = JSON.stringify(json);
          state.swapChallengeTokenWith = null;
        }
      } catch {
        // non-JSON body — leave untouched
      }
    }

    return route.fulfill({
      status: response.status(),
      headers: { ...response.headers(), ...CORS_HEADERS },
      body,
    });
  });
}

/** Open the harness and drive email+password up to the point of submission. */
async function signInWithPassword(page: Page): Promise<void> {
  await page.goto(`${HARNESS_ORIGIN}/`, { waitUntil: "load" });
  await expect(page.getByText("Sign in to Sayzio")).toBeVisible();
  await page.getByPlaceholder("Email address").fill(USER_EMAIL);
  await page.getByRole("button", { name: "Continue with password" }).click();
  await page.getByPlaceholder("Password").fill(USER_PASSWORD);
  await Promise.all([
    page.waitForResponse(
      (r) => r.url().includes("/api/v1/auth/login") && r.request().method() === "POST",
      { timeout: 60_000 },
    ),
    page.getByRole("button", { name: "Sign In" }).click(),
  ]);
}

/** The 2FA step's authenticator prompt (also asserts the challenge step is shown). */
function challengePrompt(page: Page) {
  return page.getByText(/Enter the 6-digit code from your authenticator app/);
}

async function fillChallengeCode(page: Page, code: string): Promise<void> {
  await page.getByPlaceholder("6-digit code").fill(code);
}

/** Read the harness's recorded auth result (token stored via window.zio shim). */
async function readAuthResult(page: Page): Promise<{ token: string | null; user: { email?: string } | null; closed: boolean }> {
  return page.evaluate(() => {
    const w = window as unknown as {
      __zio: { token: string | null; user: { email?: string } | null };
      __modalClosed: boolean;
    };
    return { token: w.__zio.token, user: w.__zio.user, closed: w.__modalClosed };
  });
}

test.describe("Zio Browser AuthModal — 2FA sign-in against the live API", () => {
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    seedUser();
    buildHarness();
  });

  test.afterAll(() => {
    cleanupUser();
  });

  test("wrong code shows an inline error and a valid TOTP code completes sign-in", async ({ page, request }) => {
    const state: HarnessState = { swapChallengeTokenWith: null };
    await wirePage(page, state);
    await signInWithPassword(page);

    // The totp_required gate switched the modal to the challenge step.
    await expect(challengePrompt(page)).toBeVisible({ timeout: 30_000 });

    // 1) WRONG code → real 400 invalid_2fa_code rendered inline; step stays.
    await fillChallengeCode(page, "000000");
    const [wrongResp] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes("/api/v1/auth/2fa/challenge/verify") && r.request().method() === "POST",
        { timeout: 60_000 },
      ),
      page.getByRole("button", { name: "Verify" }).click(),
    ]);
    expect(wrongResp.status()).toBe(400);
    await expect(page.getByText(/not valid/i)).toBeVisible();
    await expect(challengePrompt(page)).toBeVisible();

    // Retry is allowed: the input is still there and editable.
    const codeInput = page.getByPlaceholder("6-digit code");
    await expect(codeInput).toBeVisible();
    await expect(codeInput).toBeEditable();

    // 2) VALID TOTP code (computed via the app's own TotpService) → signed in.
    const validCode = currentTotpCode();
    await fillChallengeCode(page, validCode);
    const [okResp] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes("/api/v1/auth/2fa/challenge/verify") && r.request().method() === "POST",
        { timeout: 60_000 },
      ),
      page.getByRole("button", { name: "Verify" }).click(),
    ]);
    expect(okResp.status()).toBe(200);

    // The modal stored the auth result via the window.zio bridge and closed.
    await expect
      .poll(async () => (await readAuthResult(page)).closed, { timeout: 15_000 })
      .toBe(true);
    const auth = await readAuthResult(page);
    expect(auth.token).toBeTruthy();
    expect(auth.user?.email).toBe(USER_EMAIL);

    // Prove the issued token is REAL: call the live API directly with it.
    const me = await request.get(`${APP_URL}/api/v1/auth/me`, {
      headers: { Authorization: `Bearer ${auth.token}`, Accept: "application/json" },
    });
    expect(me.status()).toBe(200);
    const meJson = (await me.json()) as { data: { user: { email: string } } };
    expect(meJson.data.user.email).toBe(USER_EMAIL);
  });

  test("recovery-code toggle signs in with a real single-use backup code", async ({ page }) => {
    const state: HarnessState = { swapChallengeTokenWith: null };
    await wirePage(page, state);
    await signInWithPassword(page);
    await expect(challengePrompt(page)).toBeVisible({ timeout: 30_000 });

    // Toggle to recovery-code entry: prompt and input change.
    await page.getByRole("button", { name: "Use a recovery code instead" }).click();
    await expect(page.getByText(/Enter one of your recovery codes/)).toBeVisible();
    const recoveryInput = page.getByPlaceholder("Recovery code");
    await expect(recoveryInput).toBeVisible();

    // Toggle BACK works too, then return to recovery mode.
    await page.getByRole("button", { name: "Use authenticator code instead" }).click();
    await expect(challengePrompt(page)).toBeVisible();
    await page.getByRole("button", { name: "Use a recovery code instead" }).click();

    await page.getByPlaceholder("Recovery code").fill(RECOVERY_CODE);
    const [resp] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes("/api/v1/auth/2fa/backup-codes/verify") && r.request().method() === "POST",
        { timeout: 60_000 },
      ),
      page.getByRole("button", { name: "Verify" }).click(),
    ]);
    expect(resp.status()).toBe(200);

    await expect
      .poll(async () => (await readAuthResult(page)).closed, { timeout: 15_000 })
      .toBe(true);
    const auth = await readAuthResult(page);
    expect(auth.token).toBeTruthy();
    expect(auth.user?.email).toBe(USER_EMAIL);
  });

  test("an expired challenge gets the server's 410 and restarts the sign-in flow", async ({ page }) => {
    const state: HarnessState = { swapChallengeTokenWith: expiredChallengeToken() };
    await wirePage(page, state);
    await signInWithPassword(page);
    await expect(challengePrompt(page)).toBeVisible({ timeout: 30_000 });

    await fillChallengeCode(page, "123456");
    const [resp] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes("/api/v1/auth/2fa/challenge/verify") && r.request().method() === "POST",
        { timeout: 60_000 },
      ),
      page.getByRole("button", { name: "Verify" }).click(),
    ]);
    expect(resp.status()).toBe(410);

    // AuthModal restarts the flow: back on the method step with the expiry notice.
    await expect(page.getByText(/sign-in session expired/i)).toBeVisible();
    await expect(page.getByPlaceholder("Email address")).toBeVisible();

    // And the restart genuinely works: sign in again → fresh challenge step.
    await page.getByPlaceholder("Email address").fill(USER_EMAIL);
    await page.getByRole("button", { name: "Continue with password" }).click();
    await page.getByPlaceholder("Password").fill(USER_PASSWORD);
    await page.getByRole("button", { name: "Sign In" }).click();
    await expect(challengePrompt(page)).toBeVisible({ timeout: 30_000 });
  });
});
