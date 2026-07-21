import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";
import type { Route } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * e2e: Brand Studio BULK VARIATIONS mode (Task #5579) — the "pick a kind +
 * count" composer on /user/brand-studio.
 *
 * The kit-composer spec (brand-studio-composer.spec.ts) covers the "Full kit"
 * mode; this spec covers the Alpine wiring of the other mode the PHP tests
 * cannot reach:
 *   - switching to "Bulk variations" reveals the kind select + count input,
 *     whose `max` attribute is the server-rendered plan bulk cap,
 *   - typing a count ABOVE the cap: the estimate line's variant count is
 *     clamped to the cap by bulkVariants(), and the per-variant credits math
 *     divides by the CLAMPED count ("N variants × ~X credits each ≈ Y ..."),
 *   - the plan POST payload carries bulk_kind + bulk_count (and a null
 *     composition) — bulk_count is sent as typed; the clamp is enforced
 *     client-side only in the estimate math (the server re-clamps).
 *
 * Deterministic AI: /user/brand-studio/estimate (auto-fired by the Alpine
 * watcher) is stubbed so estCredits = 2 × cap → per-variant is exactly ~2;
 * /user/brand-studio/plan is stubbed with a redirect back to the studio page,
 * capturing the Alpine-built payload — no OpenAI, no credits burned.
 *
 * The effective cap is read per-run via tinker (AiBrandStudioService::bulkCap
 * on the demo user) since it is plan-driven and differs across environments.
 */

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

function runTinker(php: string): string {
  return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
}

let bulkCap: number;

test.describe("Brand Studio — bulk variations (cap clamp + payload)", () => {
  // Blade renders go over a distant RDS; lift the shared 60s ceiling.
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    const php = `
use App\\Modules\\User\\Models\\User;
use App\\Services\\Brand\\AiBrandStudioService;
$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->firstOrFail();
echo 'CAP=' . AiBrandStudioService::bulkCap($u);
`.trim();
    const out = runTinker(php);
    const m = out.match(/CAP=(\d+)/);
    if (!m) throw new Error("Cap lookup failed, output:\n" + out);
    bulkCap = Number(m[1]);
    if (bulkCap < 2) throw new Error(`Unusable bulk cap ${bulkCap} for spec`);
  });

  test("bulk mode → over-cap count clamps estimate → payload bulk_kind/bulk_count", async ({
    page,
  }) => {
    test.setTimeout(180_000);
    await loginAsDemo(page);

    // Deterministic estimate: 2 credits per (clamped) variant, so the line
    // must read "<cap> variants × ~2 credits each ≈ <2*cap> credits total".
    const EST_CREDITS = 2 * bulkCap;
    await page.route("**/user/brand-studio/estimate", (route: Route) =>
      route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ estimated_credits: EST_CREDITS, balance: 9000 }),
      }),
    );

    await page.goto("/user/brand-studio", {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });
    await expect(
      page.getByRole("heading", { name: "AI Brand Studio" }),
    ).toBeVisible();

    // Bulk controls are hidden until the mode toggle flips.
    const countInput = page.locator("input[type='number']");
    await expect(countInput).toHaveCount(0);

    await page
      .locator("textarea[maxlength='4000']")
      .fill("E2E bulk run - QR codes for the pop-up store posters.");
    await page.getByRole("button", { name: "Bulk variations" }).click();

    // Kind select + count input appear; the input's max is the plan cap the
    // server rendered into the Blade.
    const kindSelect = page.locator("select").filter({
      has: page.locator("option[value='qr_code']"),
    });
    await expect(kindSelect).toBeVisible();
    await expect(countInput).toHaveAttribute("max", String(bulkCap));
    await expect(page.getByText(`max ${bulkCap} / run`)).toBeVisible();

    await kindSelect.selectOption("qr_code");

    // ── Type a count ABOVE the cap. The estimate line must clamp the variant
    // count to the cap and divide credits by the CLAMPED count (~2 each).
    const overCap = bulkCap + 5;
    await countInput.fill(String(overCap));

    const estimateLine = page.getByText(
      `${bulkCap} variants × ~2 credits each ≈ ${EST_CREDITS} credits total`,
    );
    // The auto-estimate watcher debounces 600ms before POSTing.
    await expect(estimateLine).toBeVisible({ timeout: 15_000 });

    // ── Generate plan: capture the Alpine payload, redirect back to the page.
    let planPayload: Record<string, unknown> | null = null;
    await page.route("**/user/brand-studio/plan", async (route: Route) => {
      planPayload = route.request().postDataJSON() as Record<string, unknown>;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          credits_spent: EST_CREDITS,
          balance: 9000 - EST_CREDITS,
          redirect: "/user/brand-studio",
        }),
      });
    });

    await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes("/user/brand-studio/plan") && r.ok(),
        { timeout: 60_000 },
      ),
      page.getByRole("button", { name: "Generate plan" }).click(),
    ]);

    expect(planPayload).not.toBeNull();
    const p = planPayload!;
    expect(p["mode"]).toBe("bulk");
    expect(p["bulk_kind"]).toBe("qr_code");
    // bulk_count is sent as typed (the estimate clamp is display math; the
    // server re-clamps to the plan cap on its side).
    expect(p["bulk_count"]).toBe(overCap);
    expect(p["composition"]).toBeNull();
  });
});
