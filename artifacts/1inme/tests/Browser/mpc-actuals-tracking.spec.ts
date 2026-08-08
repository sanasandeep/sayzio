import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Task #6771 — Track actual monthly results against projections.
// Real-browser check that the Actuals tab's entry grid updates the variance
// table, tracked-months count and the dashboard chart's cumulative overlay
// live, and that logged actuals save and reload with the plan.

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

async function waitForApp(page: Page) {
  await page.locator(APP).waitFor({ state: "attached", timeout: 120_000 });
  await expect(page.locator("span", { hasText: /Allocation/ }).first()).toContainText(
    /Allocation\s[\d,.]+/,
    { timeout: 60_000 },
  );
}

test.describe("marketing plan calculator — actuals tracking (Task #6771)", () => {
  test("entry grid drives variance, chart overlay & export live; actuals persist across save/reload", async ({
    page,
  }) => {
    test.setTimeout(540_000);
    // Accept any teardown-time beforeunload prompts.
    page.on("dialog", (d) => void d.accept());

    await loginAsDemo(page);
    await page.goto("/user/marketing-plan/create", { timeout: 240_000 });
    await waitForApp(page);

    // ---- Actuals tab present with a normalized empty log ----
    expect(await alpine<number>(page, "d.p.actuals_log.length")).toBe(12);
    expect(await alpine<number>(page, "d.trackedCount")).toBe(0);
    await page.getByRole("button", { name: /· Actuals$/ }).click();
    const grid = page.locator("[data-mpc-actuals-grid]");
    await expect(grid).toBeVisible();
    await expect(page.locator("[data-mpc-variance-empty]")).toBeVisible();
    await expect(page.locator("[data-mpc-tracked-count]")).toContainText("0/12 months tracked");
    await expect(grid.locator("tr", { hasText: "Jan" })).toContainText("Not yet tracked");

    // ---- log two months (Feb only partially) ----
    await grid.locator('[data-mpc-actual="spend-0"]').fill("50000");
    await grid.locator('[data-mpc-actual="revenue-0"]').fill("90000");
    await grid.locator('[data-mpc-actual="revenue-1"]').fill("120000");
    await expect.poll(() => alpine<number>(page, "d.trackedCount")).toBe(2);
    await expect(page.locator("[data-mpc-tracked-count]")).toContainText("2/12 months tracked");
    await expect(grid.locator("tr", { hasText: "Jan" })).toContainText("Tracked");
    await expect(page.locator("[data-mpc-variance-empty]")).toBeHidden();

    // ---- variance table (default metric: revenue) shows both months ----
    const vt = page.locator("[data-mpc-variance-table]");
    await expect(vt).toBeVisible();
    await expect(vt.locator("tbody tr")).toHaveCount(2);
    const planRevJan = await alpine<number>(page, "d.model.monthTotals.revenue[0]");
    const row = await alpine<{ actual: number; plan: number; delta: number; pct: number | null }>(
      page,
      "d.varianceRows[0]",
    );
    expect(row.actual).toBe(90000);
    expect(row.plan).toBeCloseTo(planRevJan, 6);
    expect(row.delta).toBeCloseTo(90000 - planRevJan, 6);
    if (row.pct !== null) {
      expect(row.pct).toBeCloseTo(((90000 - planRevJan) / planRevJan) * 100, 4);
    }
    // Color coding: over-plan revenue green, under-plan red.
    const janCells = vt.locator("tbody tr").first().locator("td");
    await expect(janCells.nth(3)).toHaveClass(
      row.delta > 0 ? /text-emerald-400/ : /text-red-400/,
    );

    // Spend metric: a tracked month missing spend reads "Not entered".
    await page.locator("[data-mpc-variance-metric]").selectOption("spend");
    await expect(vt.locator("tbody tr", { hasText: "Feb" })).toContainText("Not entered");

    // Summary cards: revenue summary counts only months with revenue logged.
    const summary = await alpine<
      { key: string; months: number; actual: number }[]
    >(page, "d.varianceSummary");
    const rev = summary.find((s) => s.key === "revenue")!;
    expect(rev.months).toBe(2);
    expect(rev.actual).toBe(210000);
    const spend = summary.find((s) => s.key === "spend")!;
    expect(spend.months).toBe(1);

    // ---- dashboard chart gains the cumulative overlay datasets ----
    await page.getByRole("button", { name: /· Dashboard/ }).click();
    await expect
      .poll(async () =>
        alpine<string[]>(page, "d.charts.month.data.datasets.map(x => x.label)"),
      )
      .toEqual(
        expect.arrayContaining([
          expect.stringContaining("Cum. actual revenue"),
          expect.stringContaining("Cum. projected revenue"),
          expect.stringContaining("Cum. actual spend"),
        ]),
      );
    // Actual revenue line: cumulative at Jan/Feb, null (untracked) after.
    const actLine = await alpine<(number | null)[]>(
      page,
      "d.charts.month.data.datasets.find(x => x.label.includes('Cum. actual revenue')).data",
    );
    expect(actLine[0]).toBeCloseTo(90000, 3);
    expect(actLine[1]).toBeCloseTo(210000, 3);
    expect(actLine[2]).toBeNull();

    // ---- export sections include Actuals vs Plan ----
    const sections = await alpine<[string, unknown[][]][]>(page, "d.exportSections()");
    const actualsSection = sections.find(([name]) => name === "Actuals vs Plan");
    expect(actualsSection).toBeTruthy();
    const flat = actualsSection![1].map((r) => (r as unknown[]).join("|")).join("\n");
    expect(flat).toContain("Months tracked|2 of 12");
    expect(flat).toContain("Not tracked");
    expect(flat).toContain("Revenue");

    // ---- save, reload: actuals persist; list shows the indicator ----
    const planName = `E2E Actuals ${Date.now()}`;
    await page.getByPlaceholder(/Plan name/).fill(planName);
    await page.getByRole("button", { name: /Save plan/ }).click({ noWaitAfter: true });
    await page.waitForURL(/\/user\/marketing-plan\/\d+\/edit/, { timeout: 180_000 });
    await waitForApp(page);
    expect(await alpine<number>(page, "d.trackedCount")).toBe(2);
    expect(await alpine<number>(page, "d.p.actuals_log[1].revenue")).toBe(120000);
    expect(await alpine<boolean>(page, "d.dirty")).toBe(false);

    // Re-edit an assumption and save — actuals must survive.
    const origBudget = await alpine<number>(page, "d.p.annual_budget");
    const budgetInput = page
      .locator(".mpc-card", { hasText: "Total annual ad-spend budget" })
      .locator('input[type="number"]');
    await budgetInput.fill(String(origBudget + 50_000));
    await budgetInput.blur();
    await page.getByRole("button", { name: /Save plan/ }).click();
    await expect(page.locator("p", { hasText: "Saved." })).toBeVisible({ timeout: 60_000 });
    await page.reload({ timeout: 240_000 });
    await waitForApp(page);
    expect(await alpine<number>(page, "d.p.annual_budget")).toBe(origBudget + 50_000);
    expect(await alpine<number>(page, "d.trackedCount")).toBe(2);
    expect(await alpine<number>(page, "d.p.actuals_log[0].spend")).toBe(50000);

    // Plan list shows the tracked-months badge for this plan.
    await page.goto("/user/marketing-plan", { timeout: 240_000 });
    const card = page.locator(".mpc-card", { hasText: planName });
    await expect(card.locator("[data-mpc-tracked-badge]")).toContainText("2/12 months tracked");
  });
});
