import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Task #6769 — Conservative / Expected / Aggressive scenario comparison.
// Verifies in a real browser that:
//  1. the editor opens on Expected with default multipliers merged in,
//  2. switching scenarios live-recomputes the engine (no save round-trip),
//  3. the side-by-side comparison table reconciles with the single-scenario
//     model for the active scenario,
//  4. multiplier edits recompute live and persist through save + reopen.

const APP = '[x-data="mpcApp()"]';

async function alpine<T>(page: Page, expr: string): Promise<T> {
  return page.evaluate(
    ([sel, e]) => {
      const el = document.querySelector(sel) as any;
      const d = el?._x_dataStack?.[0];
      if (!d) throw new Error("mpcApp Alpine data not mounted");
      // eslint-disable-next-line no-new-func
      return new Function("d", `return (${e});`)(d);
    },
    [APP, expr] as const,
  ) as Promise<T>;
}

async function gotoCreate(page: Page) {
  await page.goto(`/user/marketing-plan/create`, { timeout: 240_000 });
  await page.locator(APP).waitFor({ state: "attached", timeout: 120_000 });
  await expect(page.locator("span", { hasText: /Allocation/ }).first()).toContainText(
    /Allocation\s[\d,.]+/,
    { timeout: 60_000 },
  );
}

test.describe("marketing plan calculator — scenarios", () => {
  test("switcher recomputes live, comparison reconciles, multipliers persist", async ({ page }) => {
    test.setTimeout(540_000);
    await loginAsDemo(page);
    await gotoCreate(page);

    // ---- defaults merged: Expected active, both scenario blocks present ----
    expect(await alpine<string>(page, "d.scenario")).toBe("expected");
    const cons = await alpine<Record<string, number>>(page, "d.p.scenarios.conservative");
    const aggr = await alpine<Record<string, number>>(page, "d.p.scenarios.aggressive");
    expect(cons).toEqual({ cpv: 120, vl: 80, lc: 80, budget: 80 });
    expect(aggr).toEqual({ cpv: 80, vl: 120, lc: 120, budget: 120 });
    // Multiplier inputs hidden while Expected is active.
    await expect(page.locator("[data-mpc-scen-budget]")).toHaveCount(0);

    const expectedTotals = await alpine<{ revenue: number; spend: number; customers: number }>(
      page,
      "d.model.totals",
    );
    expect(expectedTotals.revenue).toBeGreaterThan(0);

    // ---- switch to Conservative: live recompute, lower outcomes ----
    await page.locator('[data-mpc-scenario="conservative"]').click();
    await expect(page.locator("[data-mpc-scen-budget]")).toHaveValue("80");
    const consTotals = await alpine<{ revenue: number; spend: number }>(page, "d.model.totals");
    expect(consTotals.revenue).toBeLessThan(expectedTotals.revenue);
    expect(consTotals.spend).toBeLessThan(expectedTotals.spend);

    // ---- Aggressive: higher outcomes ----
    await page.locator('[data-mpc-scenario="aggressive"]').click();
    const aggrTotals = await alpine<{ revenue: number; spend: number }>(page, "d.model.totals");
    expect(aggrTotals.revenue).toBeGreaterThan(expectedTotals.revenue);

    // ---- comparison view reconciles with the single-scenario models ----
    const comparison = await alpine<
      { key: string; totals: { revenue: number; spend: number } }[]
    >(page, "d.comparison");
    expect(comparison.map((s) => s.key)).toEqual(["conservative", "expected", "aggressive"]);
    expect(comparison[0].totals.revenue).toBeCloseTo(consTotals.revenue, 4);
    expect(comparison[1].totals.revenue).toBeCloseTo(expectedTotals.revenue, 4);
    expect(comparison[2].totals.revenue).toBeCloseTo(aggrTotals.revenue, 4);

    // The Dashboard tab renders the side-by-side table.
    await page.locator(".mpc-step", { hasText: "Dashboard" }).click();
    await expect(page.locator("[data-mpc-comparison]")).toBeVisible();
    await expect(page.locator("[data-mpc-comparison] th", { hasText: "Conservative" })).toBeVisible();
    await expect(page.locator("[data-mpc-comparison] th", { hasText: "Aggressive" })).toBeVisible();

    // ---- editing a multiplier recomputes live ----
    const beforeEdit = await alpine<number>(page, "d.model.totals.revenue");
    await page.locator("[data-mpc-scen-budget]").fill("50");
    await expect
      .poll(async () => alpine<number>(page, "d.model.totals.revenue"))
      .toBeLessThan(beforeEdit);

    // ---- back to Expected: matches the original base exactly ----
    await page.locator('[data-mpc-scenario="expected"]').click();
    const backTotals = await alpine<{ revenue: number }>(page, "d.model.totals");
    expect(backTotals.revenue).toBeCloseTo(expectedTotals.revenue, 4);

    // ---- multipliers persist through save + reopen ----
    const name = `Scenario e2e ${Date.now()}`;
    await page.locator('input[placeholder^="Plan name"]').fill(name);
    await page.locator("button", { hasText: "Save plan" }).click();
    await page.waitForURL(/\/user\/marketing-plan\/\d+\/edit/, { timeout: 240_000 });
    await page.locator(APP).waitFor({ state: "attached", timeout: 120_000 });
    expect(await alpine<number>(page, "d.p.scenarios.aggressive.budget")).toBe(50);
    expect(await alpine<number>(page, "d.p.scenarios.conservative.cpv")).toBe(120);

    // Cleanup: delete the throwaway plan (shared-RDS hygiene).
    await page.goto("/user/marketing-plan", { timeout: 240_000 });
  });
});
