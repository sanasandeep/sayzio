import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";
import type { Route } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * e2e: AI Brand Studio BULK mode (Task #5575) — form controls (mode toggle,
 * kind selector, count input), estimate wiring, plan payload, proposal
 * review and confirm → N created assets.
 *
 * The kit-mode flow is covered by brand-studio-flow.spec.ts; this spec covers
 * the bulk-only wiring a regression could break unnoticed:
 *   - switching to "Bulk variations" reveals the kind selector + count input,
 *   - the "Estimate cost" button POSTs /user/brand-studio/estimate with the
 *     bulk payload (bulk_kind + bulk_count) and the response drives BOTH the
 *     "≈ N credits" line and the bulk per-variant breakdown line,
 *   - "Generate plan" POSTs mode=bulk with the picked kind/count,
 *   - the review page renders all N same-kind variations with pre-checked
 *     keep[] boxes, and
 *   - confirming materializes all N assets through the real server path.
 *
 * Deterministic AI: BOTH non-deterministic endpoints (estimate + plan) are
 * intercepted with page.route(), mirroring the kit spec: assert the
 * Alpine-built payloads, fulfill with the exact controller response shapes.
 * The plan response points at a bulk-mode proposal kit seeded via tinker with
 * the same asset shapes AiBrandStudioService::sanitizeAssets() emits for a
 * short_link bulk run. Every other request hits the real server.
 *
 * Self-bootstrapping: seeds its own bulk BrandStudioKit proposal owned by the
 * demo user via `php artisan tinker`. Names/titles are unique per run
 * (shared-RDS parallel envs collide on fixed fixtures — see repo memory
 * e2e-shared-rds-fixture-aliases); stale fixtures from prior runs are pruned
 * in the seed.
 */

const RUN =
  Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
const KIT_NAME = `E2E BS Bulk ${RUN}`;
const BULK_COUNT = 3;
const LINK_TITLES = Array.from(
  { length: BULK_COUNT },
  (_, i) => `E2E BSB Link ${RUN} v${i + 1}`,
);
const BRIEF =
  "E2E bulk run - I need several short links for our summer sale campaign posts.";

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
 * Seed a proposal-status BULK BrandStudioKit for the demo user — exactly what
 * AiBrandStudioService::plan() persists after sanitizeAssets() clamps a model
 * response to N short_link variations. Also prunes stale fixtures from
 * previous runs.
 */
function seedBulkProposalKit(): number {
  // NOTE: passed straight to `tinker --execute=`. In a JS template literal,
  // `\\` becomes the single backslash PHP namespaces need, while `$var` stays
  // literal (only `${"$"}{...}` would interpolate). Do NOT write `\\$`.
  const assetsPhp = LINK_TITLES.map(
    (t, i) =>
      `['kind' => 'short_link', 'title' => '${t}', 'url' => 'https://example.com/e2e-bulk-${i + 1}']`,
  ).join(",\n    ");
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\BrandStudioKit;

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->firstOrFail();

// Prune stale fixtures from previous runs (names are per-run unique).
BrandStudioKit::where('user_id', $u->id)
  ->where('name', 'like', 'E2E BS Bulk %')
  ->where('created_at', '<', now()->subDay())->delete();
Link::withoutGlobalScope('workspace')->where('user_id', $u->id)
  ->where('title', 'like', 'E2E BSB Link %')
  ->where('created_at', '<', now()->subDay())
  ->get()->each->delete();

$kit = BrandStudioKit::create([
  'user_id'  => $u->id,
  'name'     => '${KIT_NAME}',
  'mode'     => BrandStudioKit::MODE_BULK,
  'status'   => BrandStudioKit::STATUS_PROPOSAL,
  'request'  => '${BRIEF}',
  'brand'    => ['name' => 'E2E Brand', 'colors' => '#112233 and gold'],
  'proposal' => ['assets' => [
    ${assetsPhp},
  ]],
  'credits_spent' => 12,
]);
echo 'KITID=' . $kit->id;
`.trim();

  const out = runTinker(php);
  const m = out.match(/KITID=(\d+)/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return Number(m[1]);
}

test.describe("AI Brand Studio bulk mode — controls → estimate → plan → review → confirm", () => {
  // Blade renders + writes go over a distant RDS; lift the shared 60s ceiling.
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    kitId = seedBulkProposalKit();
  });

  test("bulk flow: kind+count controls, estimate wiring, plan payload, confirm N assets", async ({
    page,
  }) => {
    test.setTimeout(180_000);
    await loginAsDemo(page);

    // ── Intercept BOTH non-deterministic endpoints up-front (the estimate
    // also auto-fires ~600ms after typing via the Alpine $watch debounce, so
    // the route must be in place before we touch the form).
    let estimatePayload: Record<string, unknown> | null = null;
    await page.route("**/user/brand-studio/estimate", async (route: Route) => {
      estimatePayload = route
        .request()
        .postDataJSON() as Record<string, unknown>;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ estimated_credits: 12, balance: 993 }),
      });
    });

    let planPayload: Record<string, unknown> | null = null;
    await page.route("**/user/brand-studio/plan", async (route: Route) => {
      planPayload = route.request().postDataJSON() as Record<string, unknown>;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          credits_spent: 12,
          balance: 981,
          kit_id: kitId,
          redirect: `/user/brand-studio/${kitId}`,
        }),
      });
    });

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

    // ── Bulk controls are hidden in kit mode and revealed by the toggle.
    const kindSelect = page.locator("select[x-model='bulkKind']");
    const countInput = page.locator("input[type='number'][x-model\\.number='bulkCount']");
    await expect(kindSelect).toHaveCount(0);
    await page.getByRole("button", { name: "Bulk variations" }).click();
    await expect(kindSelect).toBeVisible();
    await expect(countInput).toBeVisible();
    // Defaults: short_link kind, count 5.
    await expect(kindSelect).toHaveValue("short_link");
    await expect(countInput).toHaveValue("5");

    // Pick kind + count and fill brand details + the brief.
    await kindSelect.selectOption("short_link");
    await countInput.fill(String(BULK_COUNT));
    await page.locator("input[placeholder='Brand name']").fill("E2E Brand");
    await page
      .locator("input[placeholder^='Brand colors']")
      .fill("#112233 and gold");
    await brief.fill(BRIEF);

    // ── Estimate wiring: the explicit button POSTs the bulk payload and the
    // response drives both the credits line and the per-variant breakdown.
    await page.getByRole("button", { name: "Estimate cost" }).click();
    await expect(
      page.getByText("≈ 12 credits (you have 993)"),
    ).toBeVisible();
    await expect(
      page.getByText(`${BULK_COUNT} variants × ~4 credits each ≈ 12 credits total`),
    ).toBeVisible();
    expect(estimatePayload).not.toBeNull();
    const est = estimatePayload!;
    expect(est["mode"]).toBe("bulk");
    expect(est["bulk_kind"]).toBe("short_link");
    expect(est["bulk_count"]).toBe(BULK_COUNT);
    expect(est["request"]).toBe(BRIEF);

    // ── Generate plan: the Alpine form posts mode=bulk with kind + count.
    const generate = page.getByRole("button", { name: "Generate plan" });
    await expect(generate).toBeEnabled();
    await Promise.all([
      page.waitForURL(`**/user/brand-studio/${kitId}`, { timeout: 60_000 }),
      generate.click(),
    ]);

    expect(planPayload).not.toBeNull();
    const p = planPayload!;
    expect(p["mode"]).toBe("bulk");
    expect(p["bulk_kind"]).toBe("short_link");
    expect(p["bulk_count"]).toBe(BULK_COUNT);
    expect(p["request"]).toBe(BRIEF);
    expect(p["brand_name"]).toBe("E2E Brand");
    expect(p["brand_colors"]).toBe("#112233 and gold");
    expect(p["brand_kit_id"]).toBeNull();

    // ── Review page: labelled as a bulk kit, all N same-kind variations
    // render with pre-checked keep[] boxes.
    await expect(page.getByText("Review the plan")).toBeVisible();
    await expect(page.getByText("Awaiting your review")).toBeVisible();
    await expect(page.getByText("Bulk variations")).toBeVisible();
    for (const title of LINK_TITLES) {
      await expect(page.getByText(title)).toBeVisible();
    }
    const keeps = page.locator("input[name='keep[]']");
    await expect(keeps).toHaveCount(BULK_COUNT);
    for (let i = 0; i < BULK_COUNT; i++) {
      await expect(keeps.nth(i)).toBeChecked();
    }
    // Every proposed asset is the picked kind — N "Short link" chips.
    await expect(
      page.getByText("Short link", { exact: true }),
    ).toHaveCount(BULK_COUNT);

    // ── Confirm: a real (non-AJAX) POST that materializes ALL N variations.
    // The cold first-write over the distant RDS can take >10s — wait on the
    // POST response, not just the navigation (see repo memory
    // e2e-editor-create-cold-rds-latency).
    const confirmResponse = page.waitForResponse(
      (r) =>
        r.url().includes(`/user/brand-studio/${kitId}/confirm`) &&
        r.request().method() === "POST",
      { timeout: 120_000 },
    );
    await page
      .getByRole("button", { name: "Create selected assets" })
      .click({ noWaitAfter: true });
    expect((await confirmResponse).status()).toBe(302);

    // ── Results page: all N variations were created.
    // NOTE: the confirm redirect lands back on the SAME URL as the review page
    // (/user/brand-studio/{kit}), so waitForURL would match the old page
    // instantly — instead let the first assertion retry across the navigation
    // with a budget generous enough for the post-write show render over the
    // distant RDS (see repo memory same-url-redirect-waitforurl-noop).
    await expect(
      page.getByText(`${BULK_COUNT} asset(s) created.`),
    ).toBeVisible({ timeout: 90_000 });
    await expect(page.getByText("Created assets")).toBeVisible();
    for (const title of LINK_TITLES) {
      await expect(page.getByText(title)).toBeVisible();
    }
    await expect(
      page.getByRole("link", { name: "Open link" }),
    ).toHaveCount(BULK_COUNT);
    // No review form remains once the kit is created.
    await expect(page.locator("input[name='keep[]']")).toHaveCount(0);

    // ── Studio home lists the kit as Created with the bulk label + N assets.
    await page.goto("/user/brand-studio", {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });
    const row = page
      .locator("div.rounded-2xl", { hasText: KIT_NAME })
      .first();
    await expect(row).toBeVisible();
    await expect(row.getByText("Created", { exact: true })).toBeVisible();
    await expect(row.getByText(/Bulk variations/)).toBeVisible();
    await expect(row.getByText(`${BULK_COUNT} asset(s)`)).toBeVisible();
    await expect(
      row.getByRole("link", { name: "View results" }),
    ).toBeVisible();
  });
});
