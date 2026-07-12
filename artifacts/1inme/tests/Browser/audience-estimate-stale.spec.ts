import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * e2e: "estimate is outdated" hint on the Audience Insights panel
 * (/user/links/{id}). Covers the client-side staleness logic no PHP feature
 * test can reach:
 *
 *   1. A link whose settings.biolink.audience_estimate.generated_at is older
 *      than 30 days renders the amber [data-testid=text-estimate-stale] hint
 *      plus the "Estimated on <date>" label (old date).
 *   2. After a successful Re-estimate (endpoint mocked via page.route so no
 *      real AI call / coin charge happens) the stale hint disappears WITHOUT
 *      a reload and the date label updates to today.
 *
 * Self-bootstrapping: seeds its own biolink (owned by the demo user) with a
 * 40-day-old cached estimate via `php artisan tinker`. The demo user is
 * bumped onto a paid plan in the seed because the Re-estimate button (and
 * the stale hint next to it) only renders when
 * AiPlanAccess::featureAllowed(owner, 'audience_type_estimation') passes,
 * which is gated to non-free plans. Alias is unique per run (shared-RDS
 * parallel envs collide on fixed aliases — see repo memory
 * e2e-shared-rds-fixture-aliases); stale fixtures from prior runs are pruned.
 */

const ALIAS_PREFIX = "e2e-audstale-";
const ALIAS =
  ALIAS_PREFIX +
  Date.now().toString(36) +
  Math.random().toString(36).slice(2, 6);

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

let linkId: number;

function runTinkerSeed(php: string): string {
  return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
}

/**
 * Seed a biolink owned by the demo user carrying a 40-day-old cached AI
 * audience estimate. Returns the link id for the /user/links/{id} URL.
 */
function seedStaleEstimateBiolink(): number {
  // NOTE: passed straight to `tinker --execute=`. In a JS template literal,
  // `\\` becomes the single backslash PHP namespaces need, while `$var` stays
  // literal (only `${"$"}{...}` would interpolate). Do NOT write `\\$`.
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\Admin\\Models\\Plan;
use App\\Modules\\User\\Services\\WorkspaceContext;
use App\\Services\\AI\\AiPlanAccess;
use App\\Services\\AI\\AudienceTypeEstimationService;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

// Seed under the SAME account demo-login authenticates
// (AuthController::demoLogin -> sayzioapp@gmail.com). Owning the fixture as
// any other email trips the analytics page's owner guard
// (\$link->user_id !== workspace_owner_id()) and 403s before the card renders.
$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => '${DEMO_LOGIN_EMAIL}',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
$rid = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
if ($rid) { $u->roles()->syncWithoutDetaching([$rid]); $u->flushPermissionCache(); }
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }

// The stale hint lives next to the Re-estimate button, which only renders
// when the AI feature gate passes (non-free plans). Bump demo onto a paid
// plan if needed, then hard-verify the gate so a gating regression fails
// loudly at seed time instead of as a silent missing-element flake.
if ($u->isOnFreePlan()) {
  $paid = Plan::public()->where('slug', '!=', 'free')->orderBy('id')->first();
  if (!$paid) { echo 'SEED_ERROR=no paid plan'; return; }
  $u->plan_id = $paid->id;
  $u->save();
}
$u->refresh();
if (!AiPlanAccess::featureAllowed($u, AudienceTypeEstimationService::FEATURE_KEY)) {
  echo 'SEED_ERROR=audience_type_estimation still gated for demo user';
  return;
}
$ws = app(WorkspaceContext::class)->resolve($u);

// Prune stale fixtures from previous runs (aliases are per-run unique).
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}%')
  ->where('created_at', '<', now()->subDay())
  ->get();
foreach ($stale as $s) { $s->delete(); }

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Stale Estimate', 'is_active' => true,
  'settings' => ['biolink' => [
    'audience_estimate' => [
      'data' => [
        ['type' => 'student', 'label' => 'Student', 'pct' => 60],
        ['type' => 'creator', 'label' => 'Creator', 'pct' => 40],
      ],
      'generated_at' => now()->subDays(40)->toIso8601String(),
    ],
  ]],
]);
echo 'LINKID=' . $bio->id;
`.trim();

  const out = runTinkerSeed(php);
  const err = out.match(/SEED_ERROR=(.+)/);
  if (err) throw new Error("Seed failed: " + err[1]);
  const m = out.match(/LINKID=(\d+)/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return Number(m[1]);
}

/**
 * Log in as the demo user (non-prod quick-login). Submits the demo-login form
 * via JS and waits only for the POST response (not the heavy post-login
 * dashboard render) — see 1inme-browser-e2e-fast-login.
 */
test.describe("audience insights — stale estimate hint", () => {
  // Blade renders over a distant RDS; lift the ceiling above the shared 60s.
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    linkId = seedStaleEstimateBiolink();
  });

  test("40-day-old estimate shows the stale hint + old date; re-estimate clears it in place", async ({
    page,
  }) => {
    await loginAsDemo(page);

    // Mock the estimate endpoint BEFORE navigation so the later click never
    // reaches the real AI service (no coin charge, deterministic payload).
    await page.route(
      (url) => url.pathname.endsWith(`/user/links/${linkId}/audience/estimate`),
      async (route) => {
        expect(route.request().method()).toBe("POST");
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            estimated: [
              { type: "professional", label: "Professional", pct: 70 },
              { type: "other", label: "Other", pct: 30 },
            ],
          }),
        });
      },
    );

    // Per-link analytics page is heavy on a cold cache (stats queries over
    // the distant RDS): generous first-render budget, don't wait for every
    // subresource — the Audience Insights card is server-rendered.
    await page.goto(`/user/links/${linkId}`, {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });

    const card = page
      .locator(".section-card", { hasText: "Audience Insights" })
      .first();
    await expect(card).toBeVisible();

    // (a) The stale hint + "Estimated on <old date>" label are visible.
    const staleHint = card.locator("[data-testid=text-estimate-stale]");
    const dateLabel = card.locator("[data-testid=text-estimate-date]");
    await expect(staleHint).toBeVisible();
    await expect(staleHint).toContainText(
      "Estimate is over 30 days old — re-estimate?",
    );
    await expect(dateLabel).toBeVisible();
    const oldDate = new Date();
    oldDate.setDate(oldDate.getDate() - 40);
    const fmt = (d: Date) =>
      d.toLocaleDateString(undefined, {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
    await expect(dateLabel).toContainText("Estimated on");
    await expect(dateLabel).toContainText(fmt(oldDate));

    // Cached rows render, and the button reads "Re-estimate with AI"
    // (estimateDone seeded true from the cached estimate).
    await expect(card).toContainText("AI Estimate");
    await expect(card).toContainText("Student");
    const reBtn = card.getByRole("button", { name: /Re-estimate with AI/ });
    await expect(reBtn).toBeVisible();

    // (b) Click Re-estimate: the mocked POST resolves, the stale hint clears
    // WITHOUT a reload, the date bumps to today, and rows swap to the payload.
    await reBtn.click();

    await expect(staleHint).toBeHidden();
    await expect(dateLabel).toBeVisible();
    await expect(dateLabel).toContainText(fmt(new Date()));
    await expect(dateLabel).not.toContainText(fmt(oldDate));
    await expect(card).toContainText("Professional");
    await expect(card).not.toContainText("Student");
  });
});
