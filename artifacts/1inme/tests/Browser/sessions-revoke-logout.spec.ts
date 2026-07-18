import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test,
  type Browser,
  type BrowserContext,
  type Page,
} from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

// Task #5123: confirm the Devices & sessions revoke flows actually LOG the
// other browser OUT, instead of merely deleting the row and hiding it from the
// list. The revoke handlers (SessionManagerController::destroy /
// destroyOthers) delete rows from the `sessions` table; with Laravel's
// database session driver, the revoked browser's cookie then points at a
// session id with no row, so its next request starts a fresh EMPTY session —
// no authenticated user — and the `auth` middleware bounces it to /user/login.
// This spec proves that end to end with two independent browser contexts:
//
//   1. Context A and context B both sign in as the demo user.
//   2. From A's Devices & sessions page, every non-current WEB session is
//      revoked one by one (per-session "Revoke" buttons). B's next navigation
//      to a protected page must land on /user/login.
//   3. B signs back in, then A clicks "Sign out everywhere except this"
//      (destroyOthers). B is bounced to /user/login again, while A itself
//      stays signed in.
//
// IMPORTANT — this spec requires the server to run with
// SESSION_DRIVER=database. Dev `.env` pins SESSION_DRIVER=file (a DB session
// write per request is too slow over the distant RDS), and under the file
// driver the controller neither lists nor deletes web sessions, so there is
// nothing to test. The dedicated validation command boots the e2e server with
// `SESSION_DRIVER=database bash tests/Browser/run-validation.sh
// sessions-revoke-logout.spec.ts` (AppServiceProvider forwards SESSION_DRIVER
// through `artisan serve`'s child-process env allowlist). When the suite runs
// WITHOUT the database driver (e.g. the plain full-suite gate), the spec
// detects the missing "This device" web row and SKIPS with an explanation
// instead of failing.
//
// Shared-RDS note: the demo user is shared across parallel task environments,
// so A's list may contain stale web sessions from other runs (including the
// runner's own warm-up demo-login). The per-session step therefore revokes
// ALL non-current web rows rather than trying to identify "the" B row — the
// assertion that matters is that B's live cookie is actually dead afterwards.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

const DEVICES_PAGE = "/user/settings/security/devices";

/** Tinker seed with retries (transient distant-RDS connection blips). */
function runTinkerSeed(php: string): string {
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
 * Idempotently prepare the demo user (active + verified + onboarded) so demo
 * login works and never detours through onboarding. Mirrors the sibling specs.
 */
function seedFixtures(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Plan;
use Illuminate\\Support\\Facades\\Hash;

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => '${DEMO_LOGIN_EMAIL}',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
$u->status = 'active';
if ($u->email_verified_at === null) { $u->email_verified_at = now(); }
if ($u->onboarded_at === null) { $u->onboarded_at = now(); }
$u->save();

// Prune ALL existing web sessions for the shared demo user (stale rows from
// other runs/environments and the runner's own warm-up login). Keeps the
// per-session revoke loop bounded to just this spec's own contexts — each
// revoke is a full authenticated round-trip over the distant RDS, and a pile
// of stale rows previously blew the test's time budget.
\\Illuminate\\Support\\Facades\\DB::table('sessions')->where('user_id', $u->id)->delete();

echo 'SEED_OK';
`.trim();

  const out = runTinkerSeed(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("sessions-revoke seed failed, output:\n" + out);
  }
}

/**
 * Demo-login with one retry across the throttle window. The demo-login route
 * is rate-limited (throttle:5,1) and this spec performs three logins in one
 * run (A, B, B-again) on top of the runner's warm-up login; if a retry attempt
 * lands inside the same minute the POST can come back 429, so on a
 * non-redirect response we wait out the window once and try again.
 */
async function loginWithThrottleRetry(page: Page): Promise<void> {
  for (let attempt = 1; ; attempt++) {
    await loginAsDemo(page);
    // Prove the session is real by loading a protected page.
    await page.goto(DEVICES_PAGE, { timeout: 120_000 });
    if (!page.url().includes("/user/login")) return;
    if (attempt >= 2) {
      throw new Error(
        "demo login did not authenticate after retry (still bounced to /user/login)",
      );
    }
    await page.waitForTimeout(61_000); // let the 1-minute throttle window pass
  }
}

/** Non-current WEB session revoke forms on the Devices & sessions page. */
function sessionRevokeForms(page: Page) {
  // route('user.settings.sessions.destroy', ['id' => 'session:...']) — matches
  // both a raw and a %3A-encoded colon; token rows use 'token:N' and are
  // deliberately excluded (we must not nuke API tokens in the per-session step).
  return page.locator('form[action*="settings/sessions/session"]');
}

test.describe("Devices & sessions — revoke actually logs the other browser out", () => {
  // Two contexts, several authenticated round-trips over the distant RDS, and
  // possibly a 61s throttle wait: give the single test generous headroom.
  test.describe.configure({ timeout: 600_000 });

  let ctxA: BrowserContext;
  let ctxB: BrowserContext;
  let pageA: Page;
  let pageB: Page;

  test.beforeAll(async ({ browser }: { browser: Browser }) => {
    seedFixtures();
    ctxA = await browser.newContext();
    ctxB = await browser.newContext();
    pageA = await ctxA.newPage();
    pageB = await ctxB.newPage();
    // Both revoke forms are guarded by an onsubmit confirm(); accept them.
    pageA.on("dialog", (d) => void d.accept());
  });

  test.afterAll(async () => {
    await ctxA?.close();
    await ctxB?.close();
  });

  test("per-session revoke and sign-out-everywhere both kill the other context's session", async () => {
    // ---- Sign in from two independent browser contexts -------------------
    await loginWithThrottleRetry(pageA);
    await loginWithThrottleRetry(pageB);

    // B can see a protected page right now (control for the later assertion).
    await pageB.goto(DEVICES_PAGE, { timeout: 120_000 });
    expect(pageB.url()).not.toContain("/user/login");

    // ---- Precondition: the database session driver must be active --------
    await pageA.goto(DEVICES_PAGE, { timeout: 120_000 });
    const currentBadge = pageA.getByText("This device", { exact: true });
    if ((await currentBadge.count()) === 0) {
      test.skip(
        true,
        "Web sessions are not listed — the server is not running with SESSION_DRIVER=database " +
          "(dev .env pins the file driver). Run via the dedicated validation command, which boots " +
          "the e2e server with SESSION_DRIVER=database.",
      );
    }

    // With A's current session listed and B signed in, there must be at least
    // one non-current web session row to revoke.
    const revokeForms = sessionRevokeForms(pageA);
    expect(
      await revokeForms.count(),
      "expected at least one non-current web session row (context B's) on A's Devices & sessions page",
    ).toBeGreaterThan(0);

    // ---- Step 1: per-session revoke ---------------------------------------
    // Revoke every non-current web session (B's row plus any stale rows from
    // the shared demo account). Bounded loop; the page reloads via back()
    // after each revoke.
    for (let i = 0; i < 15; i++) {
      if ((await sessionRevokeForms(pageA).count()) === 0) break;
      // noWaitAfter: the confirmed form submit triggers a DELETE + redirect
      // back to the heavy devices page; a plain click() would block its whole
      // actionTimeout on "waiting for scheduled navigations to finish". The
      // sibling waitForNavigation owns the navigation instead.
      await Promise.all([
        pageA.waitForNavigation({ timeout: 90_000 }),
        sessionRevokeForms(pageA)
          .first()
          .locator("button[type=submit]")
          .click({ noWaitAfter: true }),
      ]);
    }
    expect(
      await sessionRevokeForms(pageA).count(),
      "all non-current web sessions should be revoked",
    ).toBe(0);

    // THE core assertion: context B's next navigation to a protected page must
    // land on the login screen — the deleted row means its cookie now maps to
    // an empty (guest) session, not a resurrected one.
    await pageB.goto(DEVICES_PAGE, { timeout: 120_000 });
    expect(
      pageB.url(),
      "context B must be ACTUALLY logged out after its session row is revoked",
    ).toContain("/user/login");

    // ---- Step 2: "Sign out everywhere except this" ------------------------
    await loginWithThrottleRetry(pageB);
    await pageB.goto(DEVICES_PAGE, { timeout: 120_000 });
    expect(pageB.url()).not.toContain("/user/login");

    await pageA.goto(DEVICES_PAGE, { timeout: 120_000 });
    const signOutEverywhere = pageA.locator(
      'form[action$="settings/sessions/others"] button[type=submit]',
    );
    await expect(signOutEverywhere).toBeVisible();
    await Promise.all([
      pageA.waitForNavigation({ timeout: 90_000 }),
      signOutEverywhere.click({ noWaitAfter: true }),
    ]);

    // A itself must stay signed in (its own row is excluded from the delete).
    expect(
      pageA.url(),
      "context A must remain signed in after signing out everywhere else",
    ).not.toContain("/user/login");
    await expect(pageA.getByText("This device", { exact: true })).toBeVisible();

    // ...and B is dead again.
    await pageB.goto(DEVICES_PAGE, { timeout: 120_000 });
    expect(
      pageB.url(),
      "context B must be ACTUALLY logged out after sign-out-everywhere",
    ).toContain("/user/login");
  });
});
