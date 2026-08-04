import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";
import { DEMO_LOGIN_EMAIL } from "./demo-account";

// Scan-to-connect "RSVP & Connect" prompt on the public event page — Task #6688
// (browser coverage for the Task #6685 Connect QR flow).
//
// The API surface (send/verify/confirm endpoints, badge gate, idempotency) is
// already pinned by tests/Feature/EventConnectQrTest.php. What that suite can't
// see is the Alpine-driven prompt itself: the `?src=connect_qr` conditional
// render, the email → OTP → success-card JS state machine (fetch + CSRF
// handshake), and the signed-in one-tap confirm button. A JS regression there
// would keep the feature tests green while every real scanner hits a dead
// prompt — this spec closes that gap in a real browser.
//
// Scenarios:
//   1. Signed-out visitor: opens /{alias}?src=connect_qr, enters a brand-new
//      per-run email, receives the fixed non-production dev OTP ('123456' —
//      same constant the feature suite relies on), verifies, and sees the
//      success card. DB assertions (via tinker) then prove the one-shot
//      connect really happened: a confirmed "yes" RSVP with source
//      'connect_qr', a follow of the host, and the event_qr_connects
//      attribution row stamped was_new_user=true.
//   2. Already-signed-in visitor (the demo account, which is NOT the host):
//      the prompt short-circuits to the one-tap "RSVP & Connect" button;
//      clicking it lands the same success card + DB rows without any OTP.
//
// Shared-RDS discipline (repo memory e2e-shared-rds-fixture-aliases.md): the
// event alias, host email, and visitor email are all per-run unique under
// fixed prefixes, and the seed prunes stale (>2h) rows under those prefixes.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

const ALIAS_PREFIX = "e2e-cqr";
const EMAIL_PREFIX = "e2e-cqr-";
const RUN_ID = `${Date.now().toString(36)}${process.pid.toString(36)}`;

const ALIAS = `${ALIAS_PREFIX}-${RUN_ID}`;
const HOST_EMAIL = `${EMAIL_PREFIX}host-${RUN_ID}@example.com`;
const VISITOR_EMAIL = `${EMAIL_PREFIX}visitor-${RUN_ID}@example.com`;

/** Fixed non-production dev OTP (OtpService::generate outside production). */
const DEV_OTP = "123456";

/** tinker runner with retry for transient distant-RDS connect blips. */
function runTinker(php: string): string {
  let lastErr: unknown;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
        cwd: ARTIFACT_ROOT,
        encoding: "utf8",
        maxBuffer: 20 * 1024 * 1024,
      });
    } catch (err) {
      lastErr = err;
      const e = err as { stdout?: Buffer | string; stderr?: Buffer | string };
      if (attempt === 3) {
        const detail =
          (e.stdout ? e.stdout.toString() : "") +
          (e.stderr ? e.stderr.toString() : "");
        throw new Error(
          `tinker failed after ${attempt} attempts:\n` + detail.slice(-4000),
        );
      }
    }
  }
  throw lastErr;
}

/**
 * Seed: a dedicated per-run HOST (so the signed-in demo visitor's auto-follow
 * is exercised — follow is skipped when the visitor IS the host) owning one
 * RSVP-open future ics event. Prunes stale fixture links/users from earlier
 * interrupted runs first. Also pins email OTP + demo-reveal on so the prompt's
 * banner path renders (the code itself is the fixed dev value regardless).
 */
function seed(): void {
  const php = `
use App\\Modules\\Admin\\Models\\AppSetting;
use App\\Modules\\Admin\\Models\\Plan;
use App\\Modules\\User\\Models\\IcsData;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Rsvp;
use App\\Modules\\User\\Models\\User;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Str;

AppSetting::put('auth_email_otp_enabled', true);
AppSetting::put('auth_demo_reveal_otp_enabled', true);

// Prune stale (>2h) fixture links + users from interrupted previous runs.
$staleLinks = Link::query()->withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}-%')
  ->where('created_at', '<', now()->subHours(2))
  ->pluck('id');
if ($staleLinks->isNotEmpty()) {
  DB::table('event_qr_connects')->whereIn('link_id', $staleLinks)->delete();
  DB::table('rsvps')->whereIn('link_id', $staleLinks)->delete();
  DB::table('ics_data')->whereIn('link_id', $staleLinks)->delete();
  Link::query()->withoutGlobalScope('workspace')->whereKey($staleLinks)->delete();
}
User::where('email', 'like', '${EMAIL_PREFIX}%')
  ->where('created_at', '<', now()->subHours(2))
  ->get()->each(function ($u) { try { $u->delete(); } catch (\\Throwable $e) {} });

// Per-run host + event.
$host = User::create([
  'name' => 'CQR Host', 'email' => '${HOST_EMAIL}',
  'password' => Hash::make(Str::random(40)),
  'plan_id' => Plan::defaultPlan()?->id,
  'status' => 'active', 'email_verified_at' => now(), 'onboarded_at' => now(),
]);
$ws = $host->ownedWorkspaces()->first() ?: $host->ensureDefaultWorkspace();

$link = new Link([
  'user_id' => $host->id, 'type' => 'ics', 'alias' => '${ALIAS}',
  'title' => 'Connect QR E2E Party', 'is_active' => true,
  'settings' => ['rsvp_enabled' => true, 'event_category' => 'community'],
]);
$link->workspace_id = $ws->id;
$link->save();

IcsData::create([
  'link_id' => $link->id, 'event_name' => $link->title,
  'description' => 'Connect QR e2e fixture event.',
  'location' => 'San Francisco, CA', 'organizer' => $host->name,
  'start_date' => now()->addDays(7), 'end_date' => now()->addDays(7)->addHours(2),
  'timezone' => 'UTC',
]);

echo 'SEED_OK id=' . $link->id;
`;
  const out = runTinker(php);
  if (!/SEED_OK id=\d+/.test(out)) {
    throw new Error("connect-qr seed did not confirm SEED_OK:\n" + out);
  }
}

/**
 * DB-side proof the one-shot connect completed for a given visitor email:
 * confirmed "yes" RSVP with source connect_qr, follow of the host, and the
 * attribution row (with the expected was_new_user stamp + rsvp link).
 */
function assertConnectedInDb(visitorEmail: string, expectNew: boolean): void {
  const php = `
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Rsvp;
use App\\Modules\\User\\Models\\User;
use Illuminate\\Support\\Facades\\DB;

$link = Link::query()->withoutGlobalScope('workspace')->where('alias', '${ALIAS}')->firstOrFail();
$visitor = User::where('email', '${visitorEmail}')->firstOrFail();

$rsvp = Rsvp::where('link_id', $link->id)->where('email', '${visitorEmail}')->orderByDesc('id')->first();
echo 'RSVP=' . ($rsvp ? ($rsvp->response . '/' . $rsvp->status . '/' . $rsvp->source) : 'none') . "\\n";

$connect = DB::table('event_qr_connects')->where('link_id', $link->id)->where('user_id', $visitor->id)->first();
echo 'CONNECT=' . ($connect ? (($connect->was_new_user ? 'new' : 'existing') . '/' . ($connect->followed ? 'followed' : 'nofollow') . '/' . ($connect->rsvp_id ? 'rsvp-linked' : 'no-rsvp')) : 'none') . "\\n";
`;
  const out = runTinker(php);
  if (!out.includes("RSVP=yes/confirmed/connect_qr")) {
    throw new Error(`expected confirmed connect_qr RSVP for ${visitorEmail}:\n${out}`);
  }
  const expected = `CONNECT=${expectNew ? "new" : "existing"}/followed/rsvp-linked`;
  if (!out.includes(expected)) {
    throw new Error(`expected "${expected}" for ${visitorEmail}:\n${out}`);
  }
}

async function openEventWithQrSource(page: Page): Promise<void> {
  // Cold render over the distant RDS can be slow; give the goto real room.
  await page.goto(`/${ALIAS}?src=connect_qr`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await expect(
    page.getByRole("heading", { name: /RSVP\s*&\s*Connect/i }),
  ).toBeVisible({ timeout: 30_000 });
}

test.describe("event connect-qr prompt", () => {
  test.beforeAll(() => {
    seed();
  });

  // Under the fully-parallel validation gate this box runs many heavy suites
  // at once: Chromium launch can transiently EAGAIN and the send back-off
  // retries add up — give each test a real budget instead of the 60s default.
  test.setTimeout(180_000);

  test("signed-out visitor completes email → OTP → success card", async ({
    page,
  }) => {
    // The default per-test page fixture is a fresh context — a true guest —
    // and avoids spawning an extra browser context on the loaded box.
    {
      await openEventWithQrSource(page);

      // Email step (email channel is the default tab). The page has other
      // email inputs (newsletter/store notify) — target the prompt's by its
      // distinctive placeholder.
      const emailInput = page.getByPlaceholder("you@email.com");
      await expect(emailInput).toBeVisible({ timeout: 15_000 });
      await emailInput.fill(VISITOR_EMAIL);

      // Send the code. Under a fully-parallel validation run the shared box
      // is heavily loaded and the first send can transiently fail (throttle /
      // slow-RDS 5xx); retry a couple of times with back-off, and surface the
      // response body when it never succeeds so the failure is diagnosable.
      let sent = false;
      let lastSend = "";
      for (let attempt = 0; attempt < 3 && !sent; attempt++) {
        if (attempt > 0) await page.waitForTimeout(15_000);
        const sendResp = page.waitForResponse(
          (r) => r.url().includes("/connect-qr/send") && r.request().method() === "POST",
          { timeout: 60_000 },
        );
        // FA-glyph buttons pollute exact names — match by substring regex.
        await page
          .getByRole("button", { name: /Send code|Sending|Resend code/i })
          .first()
          .click();
        const resp = await sendResp;
        sent = resp.ok();
        if (!sent) {
          lastSend = `${resp.status()} ${await resp.text().catch(() => "")}`;
        }
      }
      expect(sent, `connect-qr send never succeeded: ${lastSend}`).toBeTruthy();

      // OTP step: the fixed non-production dev code.
      const otpInput = page.locator('input[placeholder="6-digit code"]');
      await expect(otpInput).toBeVisible({ timeout: 15_000 });
      await otpInput.fill(DEV_OTP);

      const verifyResp = page.waitForResponse(
        (r) => r.url().includes("/connect-qr/verify") && r.request().method() === "POST",
        { timeout: 60_000 },
      );
      await page.getByRole("button", { name: /Verify & connect|Verifying/i }).click();
      expect((await verifyResp).ok()).toBeTruthy();

      // Success card: green check + server-provided message.
      await expect(page.locator(".fa-check").first()).toBeVisible({
        timeout: 15_000,
      });
      await expect(page.locator("p.ev-strong.font-semibold")).toContainText(
        /./,
        { timeout: 10_000 },
      );

      assertConnectedInDb(VISITOR_EMAIL, true);
    }
  });

  test("signed-in visitor gets the one-tap confirm path", async ({
    page,
  }) => {
    {
      await loginAsDemo(page);
      await openEventWithQrSource(page);

      // The signed-in branch renders "Signed in as …" + the one-tap button.
      await expect(page.getByText(/Signed in as/i)).toBeVisible({
        timeout: 15_000,
      });

      const confirmResp = page.waitForResponse(
        (r) => r.url().includes("/connect-qr/confirm") && r.request().method() === "POST",
        { timeout: 60_000 },
      );
      await page
        .getByRole("button", { name: /RSVP & Connect|Connecting/i })
        .click();
      expect((await confirmResp).ok()).toBeTruthy();

      await expect(page.locator(".fa-check").first()).toBeVisible({
        timeout: 15_000,
      });

      assertConnectedInDb(DEMO_LOGIN_EMAIL, false);
    }
  });
});
