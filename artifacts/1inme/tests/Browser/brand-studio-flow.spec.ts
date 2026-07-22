import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";
import type { Route } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * e2e: AI Brand Studio full flow (Task #5565) — brief form → proposal review
 * (keep/drop) → confirm → results page.
 *
 * The PHP feature test (AiBrandStudioTest) covers plan gating, refunds and
 * materialize at the service level; this spec covers the Alpine/Blade wiring
 * the service test cannot reach:
 *   - the brief form builds the correct JSON payload (brand fields + brief +
 *     mode) and the "Generate plan" button drives the plan POST → redirect,
 *   - the review page renders every proposed asset with a pre-checked
 *     keep[] checkbox, and unticking one really drops it,
 *   - confirming materializes ONLY the kept assets through the real server
 *     path (confirm() is deterministic — no AI involved), and
 *   - the results page lists the created assets with their action buttons,
 *     and the studio home shows the kit as Created.
 *
 * Deterministic OpenAI: the ONLY non-deterministic step in the whole flow is
 * the server-side OpenAI call inside POST /user/brand-studio/plan. Following
 * the established pattern for AI endpoints in this suite (see
 * voice-assistant-panel.spec.ts), the spec intercepts that single POST with
 * page.route(): it asserts the Alpine-built request payload, and fulfills
 * with the exact JSON shape BrandStudioController::plan() returns, pointing
 * at a proposal kit seeded via tinker with a fixed "model response" (the
 * same asset shapes AiBrandStudioService::sanitizeAssets() emits). Every
 * other request in the flow hits the real server.
 *
 * Self-bootstrapping: seeds its own BrandStudioKit proposal owned by the
 * demo user via `php artisan tinker`. Names/titles are unique per run
 * (shared-RDS parallel envs collide on fixed fixtures — see repo memory
 * e2e-shared-rds-fixture-aliases); stale fixtures from prior runs are pruned
 * in the seed.
 */

const RUN =
  Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
const KIT_NAME = `E2E Brand Studio ${RUN}`;
const BIO_TITLE = `E2E BS Bio ${RUN}`;
const LINK_TITLE = `E2E BS Link ${RUN}`;
const QR_NAME = `E2E BS QR ${RUN}`;
const BRIEF =
  "Launching our e2e summer sale - I need a landing bio page, a short link and a QR code for posters.";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

let kitId: number;

function runTinker(php: string): string {
  return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
}

/**
 * Seed a proposal-status BrandStudioKit for the demo user — exactly what
 * AiBrandStudioService::plan() would persist after parsing a model response
 * proposing a biolink, a short link and a QR code. Also prunes stale
 * fixtures (kits + materialized links/QRs) from previous runs.
 */
function seedProposalKit(): number {
  // NOTE: passed straight to `tinker --execute=`. In a JS template literal,
  // `\\` becomes the single backslash PHP namespaces need, while `$var` stays
  // literal (only `${"$"}{...}` would interpolate). Do NOT write `\\$`.
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\QrCode;
use App\\Modules\\User\\Models\\BrandStudioKit;

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->firstOrFail();

// Prune stale fixtures from previous runs (names are per-run unique).
BrandStudioKit::where('user_id', $u->id)
  ->where('name', 'like', 'E2E Brand Studio %')
  ->where('created_at', '<', now()->subDay())->delete();
Link::withoutGlobalScope('workspace')->where('user_id', $u->id)
  ->where('title', 'like', 'E2E BS %')
  ->where('created_at', '<', now()->subDay())
  ->get()->each->delete();
QrCode::withoutGlobalScope('workspace')->where('user_id', $u->id)
  ->where('name', 'like', 'E2E BS QR %')
  ->where('created_at', '<', now()->subDay())
  ->get()->each->delete();

$kit = BrandStudioKit::create([
  'user_id'  => $u->id,
  'name'     => '${KIT_NAME}',
  'mode'     => BrandStudioKit::MODE_KIT,
  'status'   => BrandStudioKit::STATUS_PROPOSAL,
  'request'  => '${BRIEF}',
  'brand'    => ['name' => 'E2E Brand', 'colors' => '#112233 and gold'],
  'proposal' => ['assets' => [
    [
      'kind' => 'biolink', 'title' => '${BIO_TITLE}', 'theme_color' => '#112233',
      'blocks' => [
        ['type' => 'heading',   'settings' => ['text' => 'Summer Sale', 'size' => 'h2']],
        ['type' => 'paragraph', 'settings' => ['text' => 'Everything you need for the e2e summer sale.']],
        ['type' => 'cta_button','settings' => ['text' => 'Shop now', 'url' => 'https://example.com/sale']],
      ],
    ],
    ['kind' => 'short_link', 'title' => '${LINK_TITLE}', 'url' => 'https://example.com/e2e-sale'],
    ['kind' => 'qr_code',    'name'  => '${QR_NAME}',    'url' => 'https://example.com/e2e-poster'],
  ]],
  'credits_spent' => 7,
]);
echo 'KITID=' . $kit->id;
`.trim();

  const out = runTinker(php);
  const m = out.match(/KITID=(\d+)/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return Number(m[1]);
}

test.describe("AI Brand Studio — brief → review → confirm → results", () => {
  // Blade renders + writes go over a distant RDS; lift the shared 60s ceiling.
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    kitId = seedProposalKit();
  });

  test("full flow: form payload, plan redirect, drop one asset, confirm, results", async ({
    page,
  }) => {
    test.setTimeout(180_000);
    await loginAsDemo(page);

    // ── Studio home: brief form renders (plan-gated form, not the lock card).
    await page.goto("/user/brand-studio", {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });
    await expect(
      page.getByRole("heading", { name: "AI Brand Studio" }),
    ).toBeVisible();
    const brief = page.locator("textarea[maxlength='4000']");
    await expect(brief).toBeVisible();

    // Fill inline brand details + the brief.
    await page.locator("input[placeholder='Brand name']").fill("E2E Brand");
    await page
      .locator("input[placeholder^='Brand colors']")
      .fill("#112233 and gold");
    await brief.fill(BRIEF);

    // ── Intercept the ONE non-deterministic step: the plan POST (server-side
    // OpenAI call). Assert the Alpine-built payload, fulfill with the exact
    // controller response shape pointing at the seeded proposal kit.
    let planPayload: Record<string, unknown> | null = null;
    await page.route("**/user/brand-studio/plan", async (route: Route) => {
      planPayload = route.request().postDataJSON() as Record<string, unknown>;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          credits_spent: 7,
          balance: 993,
          kit_id: kitId,
          redirect: `/user/brand-studio/${kitId}`,
        }),
      });
    });

    const generate = page.getByRole("button", { name: "Generate plan" });
    await expect(generate).toBeEnabled();
    await Promise.all([
      page.waitForURL(`**/user/brand-studio/${kitId}`, { timeout: 60_000 }),
      generate.click(),
    ]);

    // The Alpine form built the payload the controller validates.
    expect(planPayload).not.toBeNull();
    const p = planPayload!;
    expect(p["request"]).toBe(BRIEF);
    expect(p["mode"]).toBe("kit");
    expect(p["brand_name"]).toBe("E2E Brand");
    expect(p["brand_colors"]).toBe("#112233 and gold");
    expect(p["brand_kit_id"]).toBeNull();

    // ── Review page: all three proposed assets render, keep[] pre-checked.
    await expect(page.getByText("Review the plan")).toBeVisible();
    await expect(page.getByText("Awaiting your review")).toBeVisible();
    await expect(page.getByText(BIO_TITLE)).toBeVisible();
    await expect(page.getByText(LINK_TITLE)).toBeVisible();
    await expect(page.getByText(QR_NAME)).toBeVisible();
    const keeps = page.locator("input[name='keep[]']");
    await expect(keeps).toHaveCount(3);
    for (let i = 0; i < 3; i++) {
      await expect(keeps.nth(i)).toBeChecked();
    }

    // Drop the QR code (proposal index 2).
    await keeps.nth(2).uncheck();
    await expect(keeps.nth(2)).not.toBeChecked();

    // ── Confirm: a real (non-AJAX) POST that materializes the kept assets.
    // The cold first-write over the distant RDS can take >10s — wait on the
    // POST response, not just the navigation (see repo memory
    // e2e-editor-create-cold-rds-latency).
    const confirmResponse = page.waitForResponse(
      (r) =>
        r.url().includes(`/user/brand-studio/${kitId}/confirm`) &&
        r.request().method() === "POST",
      { timeout: 120_000 },
    );
    // noWaitAfter: the click's implicit navigation wait is capped by the 30s
    // actionTimeout, but materializing the kept assets (biolink + blocks +
    // short link inserts over the distant RDS) can take longer on a cold
    // worker. The explicit waitForResponse/waitForURL below own the waiting.
    await page
      .getByRole("button", { name: "Create selected assets" })
      .click({ noWaitAfter: true });
    expect((await confirmResponse).status()).toBe(302);

    // ── Results page: only the two kept assets were created.
    // NOTE: the confirm redirect lands back on the SAME URL as the review page
    // (/user/brand-studio/{kit}), so waitForURL would match the old page
    // instantly — instead let the first assertion retry across the navigation
    // with a budget generous enough for the post-write show render over the
    // distant RDS.
    await expect(page.getByText("2 asset(s) created.")).toBeVisible({
      timeout: 90_000,
    });
    await expect(page.getByText("Created assets")).toBeVisible();
    await expect(
      page.getByText("Created", { exact: true }).first(),
    ).toBeVisible();
    await expect(page.getByText(BIO_TITLE)).toBeVisible();
    await expect(page.getByText(LINK_TITLE)).toBeVisible();
    await expect(page.getByText(QR_NAME)).toHaveCount(0);
    await expect(
      page.getByRole("link", { name: "Open editor" }),
    ).toBeVisible();
    await expect(page.getByRole("link", { name: "Open link" })).toBeVisible();
    // No review form remains once the kit is created.
    await expect(page.locator("input[name='keep[]']")).toHaveCount(0);

    // ── Studio home lists the kit as Created.
    await page.goto("/user/brand-studio", {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });
    const row = page
      .locator("div.rounded-2xl", { hasText: KIT_NAME })
      .first();
    await expect(row).toBeVisible();
    await expect(row.getByText("Created", { exact: true })).toBeVisible();
    await expect(
      row.getByRole("link", { name: "View results" }),
    ).toBeVisible();
  });

  // ── Discard flow: the review page's "Discard plan" action opens an
  // in-app confirmation modal that states the credit refund, refunds the
  // planning charge, and lands the user back on the studio home with the
  // kit gone.
  test("discard: confirm modal with refund note, refund banner, kit removed from home", async ({
    page,
  }) => {
    test.setTimeout(180_000);

    // Seed a dedicated proposal kit so this test is independent of the
    // full-flow test's kit (which gets confirmed/created).
    const discardKitName = `${KIT_NAME} Discard`;
    const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\BrandStudioKit;
$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->firstOrFail();
$kit = BrandStudioKit::create([
  'user_id'  => $u->id,
  'name'     => '${discardKitName}',
  'mode'     => BrandStudioKit::MODE_KIT,
  'status'   => BrandStudioKit::STATUS_PROPOSAL,
  'request'  => 'Discard-path e2e brief.',
  'brand'    => [],
  'proposal' => ['assets' => [
    ['kind' => 'short_link', 'title' => 'E2E BS Discard Link ${RUN}', 'url' => 'https://example.com/e2e-discard'],
  ]],
  'credits_spent' => 7,
]);
echo 'KITID=' . $kit->id;
`.trim();
    const out = runTinker(php);
    const m = out.match(/KITID=(\d+)/);
    if (!m) throw new Error("Discard seed failed, output:\n" + out);
    const discardKitId = Number(m[1]);

    await loginAsDemo(page);

    // Review page renders the proposal with the discard form.
    await page.goto(`/user/brand-studio/${discardKitId}`, {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });
    await expect(page.getByText("Review the plan")).toBeVisible();
    const discard = page.getByRole("button", { name: "Discard plan" });
    await expect(discard).toBeVisible();

    // First click: opens the in-app confirmation modal (no JS confirm).
    // The modal must state the credit refund; "Keep plan" closes it.
    const modal = page.getByRole("dialog");
    await discard.click();
    await expect(modal).toBeVisible();
    await expect(
      modal.getByRole("heading", { name: "Discard this plan?" }),
    ).toBeVisible();
    await expect(
      modal.getByText("7 credits will be refunded"),
    ).toBeVisible();
    await modal.getByRole("button", { name: "Keep plan" }).click();
    await expect(modal).toBeHidden();
    await expect(page.getByText("Review the plan")).toBeVisible();

    // Second pass: reopen the modal and confirm — the DELETE POST refunds
    // + deletes. Cold first-write over the distant RDS can be slow; wait on
    // the POST response, not just navigation (repo memory
    // e2e-editor-create-cold-rds-latency).
    await discard.click();
    await expect(modal).toBeVisible();
    const destroyResponse = page.waitForResponse(
      (r) =>
        r.url().includes(`/user/brand-studio/${discardKitId}`) &&
        r.request().method() === "POST",
      { timeout: 120_000 },
    );
    await modal
      .getByRole("button", { name: "Discard plan" })
      .click({ noWaitAfter: true });
    expect((await destroyResponse).status()).toBe(302);

    // Lands on the studio home with the refund banner; kit is gone.
    await expect(
      page.getByText("Plan discarded — 7 credits refunded."),
    ).toBeVisible({ timeout: 90_000 });
    await expect(page).toHaveURL(/\/user\/brand-studio$/);
    await expect(page.getByText(discardKitName)).toHaveCount(0);

    // Server-side truth: the kit row is really deleted and the refund is a
    // ledger row keyed to this kit.
    const verify = runTinker(
      `
use App\\Modules\\User\\Models\\BrandStudioKit;
use App\\Modules\\User\\Models\\WalletTransaction;
echo 'KIT=' . BrandStudioKit::whereKey(${discardKitId})->count();
echo ' REFUND=' . WalletTransaction::where('idempotency_key', 'brand_studio_discard_${discardKitId}')->where('type', 'refund')->where('delta_coins', 7)->count();
`.trim(),
    );
    expect(verify).toContain("KIT=0");
    expect(verify).toContain("REFUND=1");
  });
});
