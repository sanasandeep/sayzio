import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Page,
} from "@playwright/test";

/**
 * Confirms the BROWSER dialer page visually refreshes when another device makes
 * a change (Task #3315). Task #3314 already proved the server contract of the
 * live-sync endpoint (`GET /user/dialer/live` — see
 * tests/Feature/DialerLiveSyncTest.php); this spec covers the missing half: that
 * the dialer page's client-side poll/render loop
 * (resources/views/user/dialer/index.blade.php) actually APPLIES the fresh
 * favorites / recents to the DOM when the cursor advances, with no full reload.
 * A regression in `pollLive()` / `applyLive()` would let one device's change
 * silently never appear on another even while the endpoint works perfectly.
 *
 * Conventions (see .agents/memory/1inme-browser-e2e-fast-login.md &
 * browser-e2e-validation-gate.md): fast demo login (wait only for the
 * demo-login POST, never the heavy dashboard render), the gate boots its own
 * standalone server, generous per-test timeouts for cold renders over the
 * distant RDS. Saturation guard (.agents/memory/editor-e2e-heartbeat-saturation.md):
 * the change is applied by DRIVING `window.pollLive()` on demand rather than
 * leaning on the 12s background interval, so the poll fires a bounded number of
 * times instead of hammering the few PHP-CLI workers for the whole run. The
 * 12s auto-poll timer is still asserted to be wired (see below) so removing it
 * would fail the spec even though the manual drive would otherwise mask it.
 *
 * All tests share ONE logged-in context (the `demo-login` route is rate-limited)
 * and run serially.
 */
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Distinctive fixtures a real account is exceedingly unlikely to already hold,
// so the "absent before, present after" assertions stay deterministic even
// though the demo user accumulates dialer state from other specs/runs.
const FAV_LABEL = "E2E Live Sync Fav";
const FAV_NUMBER = "+15550199001";
const RECENT_NUMBER = "+15550199002";

function runTinker(php: string): string {
  let lastErr: unknown;
  // Tinker over the distant RDS transiently fails; retry (mirrors sibling specs).
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
 * Idempotently prepare the demo user (active, verified, onboarded so the
 * onboarding gate never bounces the login through the slow wizard, plus the
 * `user-admin` web role + a resolved active workspace so the dialer route's
 * `workspace.can:settings.view` gate passes), and REMOVE any leftover sentinel
 * favorites / call-log rows so the page first loads WITHOUT them — the change is
 * then made mid-test to simulate another device.
 */
function seedBaseline(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\DialerFavorite;
use App\\Modules\\User\\Models\\DialerLookup;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

$u = User::where('email', 'demo@1inme.com')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first() ?? Plan::defaultPlan();
  $u = User::create([
    'name' => 'Demo User', 'email' => 'demo@1inme.com',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
if ($u->status !== 'active') { $u->status = 'active'; }
if ($u->email_verified_at === null) { $u->email_verified_at = now(); }
if ($u->onboarded_at === null) { $u->onboarded_at = now(); }
$u->save();

$rid = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
if ($rid) { $u->roles()->syncWithoutDetaching([$rid]); $u->flushPermissionCache(); }

// Resolve (creating if needed) the personal workspace the dialer route gates on.
app(WorkspaceContext::class)->resolve($u);

// Clear any leftover sentinel rows from a prior run so the page loads clean.
DialerFavorite::where('user_id', $u->id)
  ->where(function ($q) { $q->where('label', '${FAV_LABEL}')->orWhere('number_e164', '${FAV_NUMBER}'); })
  ->delete();
DialerLookup::where('user_id', $u->id)->where('number_e164', '${RECENT_NUMBER}')->delete();

echo 'SEED_OK';
`.trim();

  const out = runTinker(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("Dialer live-sync baseline seed failed, output:\n" + out);
  }
}

/** Create a speed-dial favorite out-of-band, i.e. "on another device". */
function addFavoriteOnAnotherDevice(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\DialerFavorite;
$u = User::where('email', 'demo@1inme.com')->firstOrFail();
DialerFavorite::firstOrCreate(
  ['user_id' => $u->id, 'number_e164' => '${FAV_NUMBER}'],
  ['label' => '${FAV_LABEL}', 'sort_order' => 1]
);
echo 'FAV_OK';
`.trim();
  if (!runTinker(php).includes("FAV_OK")) {
    throw new Error("Failed to add out-of-band favorite");
  }
}

/** Log a brand-new call out-of-band, i.e. "on another device". */
function logCallOnAnotherDevice(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\DialerLookup;
$u = User::where('email', 'demo@1inme.com')->firstOrFail();
DialerLookup::create([
  'user_id' => $u->id, 'number_e164' => '${RECENT_NUMBER}', 'looked_up_at' => now(),
]);
echo 'CALL_OK';
`.trim();
  if (!runTinker(php).includes("CALL_OK")) {
    throw new Error("Failed to log out-of-band call");
  }
}

async function loginAsDemo(page: Page): Promise<void> {
  await page.goto("/user/login");
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().endsWith("/user/demo-login") && r.request().method() === "POST",
    ),
    page.evaluate(() => {
      const form = document.querySelector<HTMLFormElement>(
        'form[action$="/user/demo-login"]',
      );
      form?.submit();
    }),
  ]);
}

/**
 * Open the dialer and mark the live JS context so we can later prove the update
 * happened WITHOUT a full page reload: a full navigation would wipe this window
 * global. Also records `setInterval` delays (armed via addInitScript before the
 * page's own script runs) so we can assert the 12s auto-poll timer is wired.
 */
async function openDialer(page: Page): Promise<void> {
  await page.addInitScript(() => {
    const w = window as unknown as {
      __intervalDelays?: number[];
      setInterval: typeof setInterval;
    };
    w.__intervalDelays = [];
    const orig = w.setInterval.bind(window);
    w.setInterval = ((fn: TimerHandler, delay?: number, ...rest: unknown[]) => {
      if (typeof delay === "number") w.__intervalDelays!.push(delay);
      return orig(fn as never, delay as never, ...(rest as never[]));
    }) as typeof setInterval;
  });
  await page.goto("/user/dialer", { waitUntil: "domcontentloaded" });
  await expect(page.locator("#dialer-root")).toBeAttached();
  // Prove the page reached the real dialer (not a redirect to onboarding/login).
  await expect(page.getByRole("heading", { name: "Dialer" })).toBeVisible();
  // A survives-across-refresh sentinel: cleared by any full document reload.
  await page.evaluate(() => {
    (window as unknown as { __noReload?: string }).__noReload = "alive";
  });
}

/**
 * Repeatedly drive the page's own `pollLive()` (the exact fetch -> applyLive ->
 * DOM path under test) until the target container reflects the out-of-band
 * change, or time out. Driving it on demand keeps poll volume bounded instead
 * of relying on the 12s background interval.
 */
async function pollUntilContains(
  page: Page,
  selector: string,
  needle: string,
): Promise<void> {
  await expect
    .poll(
      async () => {
        await page.evaluate(() => {
          const fn = (window as unknown as { pollLive?: () => unknown })
            .pollLive;
          return typeof fn === "function" ? fn() : undefined;
        });
        return (await page.locator(selector).textContent()) ?? "";
      },
      { timeout: 30000, intervals: [500, 1000, 1500, 2000, 3000] },
    )
    .toContain(needle);
}

async function assertNoFullReload(page: Page): Promise<void> {
  const stillAlive = await page.evaluate(
    () => (window as unknown as { __noReload?: string }).__noReload,
  );
  expect(stillAlive).toBe("alive");
}

test.beforeAll(async ({ browser }) => {
  seedBaseline();
  sharedContext = await browser.newContext({
    viewport: { width: 1280, height: 900 },
  });
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
});

test("a favorite added on another device appears in the grid without a reload", async ({
  page,
}) => {
  test.setTimeout(180000);
  seedBaseline();

  await openDialer(page);

  // The auto-poll timer must be wired at the documented 12s cadence, otherwise
  // a live change would never surface on its own.
  const delays = await page.evaluate(
    () => (window as unknown as { __intervalDelays?: number[] }).__intervalDelays,
  );
  expect(delays).toContain(12000);

  // Not present before the out-of-band change (the initial on-load poll used the
  // server-seeded cursor, so it hasn't churned).
  await expect(page.locator("#favorites-grid")).not.toContainText(FAV_LABEL);

  // Another device adds a speed-dial favorite; the cursor advances.
  addFavoriteOnAnotherDevice();

  // The browser page re-renders the fresh favorite into the grid...
  await pollUntilContains(page, "#favorites-grid", FAV_LABEL);
  // ...and the favorites card (hidden while empty) becomes visible.
  await expect(page.locator("#favorites-card")).toBeVisible();

  // ...all without a full page reload.
  await assertNoFullReload(page);
});

test("a new call logged on another device appears in recents without a reload", async ({
  page,
}) => {
  test.setTimeout(180000);
  seedBaseline();

  await openDialer(page);

  await expect(page.locator("#recent-list")).not.toContainText(RECENT_NUMBER);

  // Another device logs a brand-new call (append-only log -> cursor advances).
  logCallOnAnotherDevice();

  await pollUntilContains(page, "#recent-list", RECENT_NUMBER);
  await expect(page.locator("#recent-card")).toBeVisible();

  await assertNoFullReload(page);
});
