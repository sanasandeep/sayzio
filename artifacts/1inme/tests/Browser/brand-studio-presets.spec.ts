import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";
import type { Route } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * e2e: Brand Studio SAVED COMBOS (Task #5581) — the save/reuse/delete UI for
 * user-saved kit compositions on /user/brand-studio.
 *
 * The PHP feature test (BrandStudioPresetTest) covers the store/destroy
 * endpoints at the HTTP level; this spec covers the Alpine/Blade wiring the
 * PHP tests cannot reach:
 *   - "Save this combo" only appears once a valid composition exists, and
 *     reveals the name row (input + Save/Cancel),
 *   - saving really POSTs (CSRF header wiring) and the new bookmark chip
 *     renders immediately from the JSON response — and SURVIVES a reload
 *     (server-rendered from the DB, not just client state),
 *   - clicking the chip re-applies the exact saved rows (kind/count/purpose)
 *     after the composition was cleared,
 *   - the chip's × asks for confirm() and, once accepted, DELETEs the row and
 *     removes the chip — also verified across a reload.
 *
 * Deterministic AI: the Alpine watcher debounces a POST to
 * /user/brand-studio/estimate on every composition change; it is intercepted
 * with page.route() (same as brand-studio-composer.spec.ts) so no AI credits
 * or OpenAI calls are involved. No plan is ever generated here.
 *
 * Fixtures: the combo name is per-run unique and stale fixtures from prior
 * runs are pruned via tinker (shared-RDS parallel envs — see repo memory
 * e2e-shared-rds-fixture-aliases).
 */

const RUN = Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
const COMBO_NAME = `E2E Combo ${RUN}`;
const PURPOSE = `E2E preset purpose ${RUN}`;

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

/** Prune stale saved combos from previous runs (names are per-run unique). */
function pruneStalePresets(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\BrandStudioPreset;
$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->firstOrFail();
BrandStudioPreset::where('user_id', $u->id)
  ->where('name', 'like', 'E2E Combo %')
  ->where('created_at', '<', now()->subDay())->delete();
echo 'PRUNED=ok';
`.trim();
  const out = runTinker(php);
  if (!out.includes("PRUNED=ok")) {
    throw new Error("Prune failed, output:\n" + out);
  }
}

/** Keep the auto-estimate watcher deterministic and free. */
async function stubEstimate(page: import("@playwright/test").Page) {
  await page.route("**/user/brand-studio/estimate", (route: Route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ estimated_credits: 6, balance: 900 }),
    }),
  );
}

test.describe("Brand Studio — save, reuse and delete a combo", () => {
  // Blade renders + writes go over a distant RDS; lift the shared 60s ceiling.
  test.describe.configure({ timeout: 240_000 });

  test.beforeAll(() => {
    pruneStalePresets();
  });

  test("save row → chip → reload persists → apply → delete confirm → gone", async ({
    page,
  }) => {
    test.setTimeout(240_000);
    await loginAsDemo(page);
    await stubEstimate(page);

    await page.goto("/user/brand-studio", {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });
    await expect(
      page.getByRole("heading", { name: "AI Brand Studio" }),
    ).toBeVisible();

    // ── Build a composition: with no rows, "Save this combo" is hidden.
    const saveToggle = page.getByRole("button", { name: "Save this combo" });
    await expect(saveToggle).toBeHidden();
    await page.getByRole("button", { name: "Add asset" }).click();

    // One default row (Link in Bio page × 1); bump the count to 2 and give
    // it a purpose so the reapply assertion is meaningful.
    const row = page
      .locator("div.flex.items-center.gap-2", {
        has: page.locator("input[placeholder^='Purpose']"),
      })
      .first();
    await expect(row.locator("select")).toHaveValue("biolink");
    await row.getByRole("button", { name: "+", exact: true }).click();
    await expect(row.locator("span.tabular-nums")).toHaveText("2");
    await row.locator("input[placeholder^='Purpose']").fill(PURPOSE);

    // ── Save row appears; name it and save. Wait on the real POST (cold
    // first-write over the distant RDS — see repo memory
    // e2e-editor-create-cold-rds-latency).
    await expect(saveToggle).toBeVisible();
    await saveToggle.click();
    const nameInput = page.locator("input[placeholder^='Combo name']");
    await expect(nameInput).toBeVisible();
    await nameInput.fill(COMBO_NAME);

    const saveResponse = page.waitForResponse(
      (r) =>
        r.url().includes("/user/brand-studio/presets") &&
        r.request().method() === "POST",
      { timeout: 120_000 },
    );
    await page.getByRole("button", { name: "Save combo" }).click();
    expect((await saveResponse).status()).toBe(200);

    // Chip renders from the JSON response; the save row closes.
    const chip = page.getByRole("button", { name: COMBO_NAME });
    await expect(chip).toBeVisible();
    await expect(nameInput).toBeHidden();

    // ── Reload: the chip is server-rendered from the DB, not client state.
    // (page.route interceptions survive reloads, so the estimate stub holds.)
    await page.reload({ waitUntil: "domcontentloaded", timeout: 120_000 });
    await expect(chip).toBeVisible();

    // ── Apply: start from an empty composition, click the chip, and the
    // exact saved rows come back (kind/count/purpose).
    await expect(page.locator("input[placeholder^='Purpose']")).toHaveCount(0);
    await chip.click();
    const applied = page
      .locator("div.flex.items-center.gap-2", {
        has: page.locator("input[placeholder^='Purpose']"),
      })
      .first();
    await expect(applied.locator("select")).toHaveValue("biolink");
    await expect(applied.locator("span.tabular-nums")).toHaveText("2");
    await expect(applied.locator("input[placeholder^='Purpose']")).toHaveValue(
      PURPOSE,
    );

    // ── Delete: the × asks for confirm(). First dismiss → chip stays.
    const chipWrap = page
      .locator("span.inline-flex", { hasText: COMBO_NAME })
      .first();
    const deleteBtn = chipWrap.locator(`button[title*="${COMBO_NAME}"]`);
    await expect(deleteBtn).toBeVisible();

    page.once("dialog", (d) => {
      expect(d.message()).toContain(COMBO_NAME);
      void d.dismiss();
    });
    await deleteBtn.click();
    await expect(chip).toBeVisible();

    // Accept → real DELETE → chip removed.
    const deleteResponse = page.waitForResponse(
      (r) =>
        r.url().includes("/user/brand-studio/presets/") &&
        r.request().method() === "DELETE",
      { timeout: 120_000 },
    );
    page.once("dialog", (d) => void d.accept());
    await deleteBtn.click();
    expect((await deleteResponse).status()).toBe(200);
    await expect(chip).toBeHidden();

    // ── Reload: really gone from the DB.
    await page.reload({ waitUntil: "domcontentloaded", timeout: 120_000 });
    await expect(
      page.getByRole("heading", { name: "AI Brand Studio" }),
    ).toBeVisible();
    await expect(page.getByRole("button", { name: COMBO_NAME })).toHaveCount(0);
  });
});
