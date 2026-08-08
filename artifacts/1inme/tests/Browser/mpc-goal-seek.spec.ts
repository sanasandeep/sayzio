import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Task #6770 — Goal seek: enter a 12-month target, back-solve the annual ad
// budget. Verifies in a real browser that:
//  1. a revenue target produces a required budget which, once applied, makes
//     the dashboard's projected revenue meet the target within rounding,
//  2. the delta vs the current budget and per-channel implied spend show,
//  3. customer targets work too,
//  4. degenerate inputs (organic-only allocations) show a clear explanation
//     instead of NaN/Infinity,
//  5. a target already met by organic channels reports a ₹0 budget,
//  6. the currency toggle changes how revenue targets are interpreted, and
//  7. the active scenario's multipliers are respected.

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

async function openGoalSeek(page: Page) {
  await page.locator("[data-mpc-goal-toggle]").click();
  await expect(page.locator("[data-mpc-goal-type]")).toBeVisible();
}

test.describe("marketing plan calculator — goal seek", () => {
  test("revenue target solves, applies and meets the projection", async ({ page }) => {
    test.setTimeout(540_000);
    await loginAsDemo(page);
    await gotoCreate(page);
    await openGoalSeek(page);

    // ---- 1 · revenue target (INR) ----
    const target = 300_000_000; // ₹30 Cr — well above the ~₹10.7 Cr organic-only contribution
    await page.locator("[data-mpc-goal-type]").selectOption("revenue");
    await page.locator("[data-mpc-goal-value]").fill(String(target));

    await expect(page.locator("[data-mpc-goal-results]")).toBeVisible();
    const goal = await alpine<{ state: string; required: number; delta: number; channels: any[] }>(
      page,
      "d.goal",
    );
    expect(goal.state).toBe("ok");
    expect(goal.required).toBeGreaterThan(0);
    expect(Number.isFinite(goal.required)).toBe(true);
    // Delta reconciles with the current budget input.
    const curBudget = await alpine<number>(page, "d.p.annual_budget");
    expect(goal.delta).toBeCloseTo(goal.required - curBudget, 6);
    await expect(page.locator("[data-mpc-goal-delta]")).toBeVisible();

    // Per-channel implied spend: annual sums to the required budget and
    // monthly = annual / 12 (Sayzio's fixed row excluded).
    const channelSum = goal.channels.reduce((a, c) => a + c.annual, 0);
    expect(channelSum).toBeCloseTo(goal.required, 3);
    expect(goal.channels.every((c) => Math.abs(c.monthly * 12 - c.annual) < 1e-6)).toBe(true);
    expect(goal.channels.some((c) => /sayzio/i.test(c.name))).toBe(false);

    // ---- 2 · apply → dashboard projection meets the target ----
    await page.locator("[data-mpc-goal-apply]").click();
    expect(await alpine<number>(page, "d.p.annual_budget")).toBe(goal.required);
    expect(await alpine<boolean>(page, "d.dirty")).toBe(true); // normal unsaved-changes flow
    const projected = await alpine<number>(page, "d.model.totals.revenue");
    expect(projected).toBeGreaterThanOrEqual(target - 1); // within rounding
    expect(projected).toBeLessThan(target * 1.01); // and not wildly over

    // ---- 3 · customers target ----
    await page.locator("[data-mpc-goal-type]").selectOption("customers");
    await page.locator("[data-mpc-goal-value]").fill("3000"); // organic alone yields ~900
    const gCust = await alpine<{ state: string; required: number }>(page, "d.goal");
    expect(gCust.state).toBe("ok");
    await page.locator("[data-mpc-goal-apply]").click();
    const customers = await alpine<number>(page, "d.model.totals.customers");
    expect(customers).toBeGreaterThanOrEqual(2999); // within rounding
  });

  test("degenerate, organic-met, currency and scenario cases", async ({ page }) => {
    test.setTimeout(540_000);
    await loginAsDemo(page);
    await gotoCreate(page);
    await openGoalSeek(page);

    await page.locator("[data-mpc-goal-type]").selectOption("revenue");
    await page.locator("[data-mpc-goal-value]").fill("300000000");
    await expect(page.locator("[data-mpc-goal-results]")).toBeVisible();

    // ---- organic-only allocations → clear infeasible message, no NaN ----
    await page.evaluate(
      ([sel]) => {
        const d = (document.querySelector(sel) as any)._x_dataStack[0];
        for (const c of d.p.channels) if (!c.fixed) c.alloc = 0;
      },
      [APP] as const,
    );
    await expect(page.locator("[data-mpc-goal-infeasible]")).toBeVisible();
    await expect(page.locator("[data-mpc-goal-infeasible]")).toContainText(/allocation/i);
    await expect(page.locator("[data-mpc-goal-results]")).toHaveCount(0);
    // Restore a working allocation on one channel (rates untouched).
    await page.evaluate(
      ([sel]) => {
        const d = (document.querySelector(sel) as any)._x_dataStack[0];
        d.p.channels.find((c: any) => c.key === "search").alloc = 100;
      },
      [APP] as const,
    );
    await expect(page.locator("[data-mpc-goal-results]")).toBeVisible();

    // ---- zero conversion rates → infeasible explanation ----
    await page.locator("[data-mpc-goal-type]").selectOption("customers");
    await page.evaluate(
      ([sel]) => {
        const d = (document.querySelector(sel) as any)._x_dataStack[0];
        for (const c of d.p.channels) c.lc = 0;
      },
      [APP] as const,
    );
    await expect(page.locator("[data-mpc-goal-infeasible]")).toBeVisible();
    await page.evaluate(
      ([sel]) => {
        const d = (document.querySelector(sel) as any)._x_dataStack[0];
        for (const c of d.p.channels) c.lc = 20;
      },
      [APP] as const,
    );

    // ---- target already met by organic → ₹0 budget card ----
    await page.locator("[data-mpc-goal-type]").selectOption("leads");
    await page.locator("[data-mpc-goal-value]").fill("1");
    await expect(page.locator("[data-mpc-goal-organic]")).toBeVisible();

    // ---- currency toggle: USD revenue target is interpreted in USD ----
    await page.locator("[data-mpc-goal-type]").selectOption("revenue");
    await page.locator("[data-mpc-goal-value]").fill("5000000"); // $5M ≈ ₹41.5 Cr
    const reqInr = (await alpine<{ required: number }>(page, "d.goal")).required;
    await page.getByRole("button", { name: "$ USD" }).click();
    const reqUsd = (await alpine<{ required: number }>(page, "d.goal")).required;
    const rate = await alpine<number>(page, "d.p.usd_inr_rate");
    // Same numeral now means USD, so the INR-denominated budget must grow.
    expect(reqUsd).toBeGreaterThan(reqInr);
    // Applying while in USD mode meets the USD target: model revenue (INR)
    // must reach target × rate within rounding.
    await page.locator("[data-mpc-goal-apply]").click();
    const revUsdMode = await alpine<number>(page, "d.model.totals.revenue");
    expect(revUsdMode).toBeGreaterThanOrEqual(5_000_000 * rate - rate);
    await page.getByRole("button", { name: "₹ INR" }).click();
    await page.locator("[data-mpc-goal-value]").fill("300000000");

    // ---- scenario multipliers respected ----
    const reqExpected = (await alpine<{ required: number }>(page, "d.goal")).required;
    await page.locator('[data-mpc-scenario="conservative"]').click();
    const gCons = await alpine<{ state: string; required: number }>(page, "d.goal");
    expect(gCons.state).toBe("ok");
    // Conservative worsens CPV/conversions, so hitting the same target needs more.
    expect(gCons.required).toBeGreaterThan(reqExpected);
    // Applying it meets the target IN that scenario.
    await page.locator("[data-mpc-goal-apply]").click();
    const revCons = await alpine<number>(page, "d.model.totals.revenue");
    expect(revCons).toBeGreaterThanOrEqual(300_000_000 - 1);
  });
});
