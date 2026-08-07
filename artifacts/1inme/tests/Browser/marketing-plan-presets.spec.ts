import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Task #6767 — Industry benchmark presets for the Marketing Plan
// Calculator. Verifies in a real browser that:
//  1. `?preset=` seeds the channel table + header badge,
//  2. applying a preset from the in-editor picker live-updates the
//     assumptions table, engine totals and charts,
//  3. overwriting an edited plan asks for confirmation first (and cancel
//     leaves everything untouched).

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

async function gotoCreate(page: Page, query = "") {
  await page.goto(`/user/marketing-plan/create${query}`, { timeout: 240_000 });
  await page.locator(APP).waitFor({ state: "attached", timeout: 120_000 });
  await expect(page.locator("span", { hasText: /Allocation/ }).first()).toContainText(
    /Allocation\s[\d,.]+/,
    { timeout: 60_000 },
  );
}

test.describe("marketing plan calculator — industry presets", () => {
  test("?preset= seeds the table and the picker applies presets live", async ({ page }) => {
    test.setTimeout(540_000);
    await loginAsDemo(page);

    // ---- create seeded from a preset link ----
    await gotoCreate(page, "?preset=b2b_saas");
    await expect(page.locator("[data-mpc-preset-badge]")).toHaveText("B2B SaaS");
    expect(await alpine<string>(page, "d.p.industry_preset")).toBe("b2b_saas");
    // LinkedIn carries the B2B allocation (18%), not the generic 2.5%.
    expect(
      await alpine<number>(page, 'd.p.channels.find(c => c.key === "linkedin").alloc'),
    ).toBe(18);
    // Preset allocations still total 100% → OK badge.
    await expect(page.locator("span", { hasText: /✓ Allocation/ })).toBeVisible();

    // ---- fresh generic plan: picker applies without confirm ----
    await gotoCreate(page);
    await expect(page.locator("[data-mpc-preset-badge]")).toHaveText("Generic");
    const genericTotals = await alpine<{ revenue: number; customers: number }>(
      page,
      "d.model.totals",
    );

    const picker = page.locator("#mpcPresetPick");
    await picker.selectOption("ecommerce");
    await expect(page.locator("[data-mpc-preset-badge]")).toHaveText("E-commerce / D2C");
    expect(await alpine<string>(page, "d.p.industry_preset")).toBe("ecommerce");
    // Table inputs live-updated: Instagram's alloc input now shows 14.
    const instaAlloc = page.locator("tbody tr", { hasText: "Instagram Ads" }).locator("td:nth-child(2) input");
    await expect(instaAlloc).toHaveValue("14");
    await expect(page.locator("span", { hasText: /✓ Allocation/ })).toBeVisible();
    // Engine recomputed with the new ACV/rates.
    const ecomTotals = await alpine<{ revenue: number; customers: number }>(
      page,
      "d.model.totals",
    );
    expect(ecomTotals.revenue).not.toBe(genericTotals.revenue);
    expect(Number.isFinite(ecomTotals.revenue)).toBe(true);

    // Charts reflect the preset when the dashboard opens.
    await page.getByRole("button", { name: /3 · Dashboard/ }).click();
    const chartOk = await page.evaluate(() => {
      const C = (window as any).Chart;
      const month = C?.getChart(document.getElementById("mpcMonthChart"));
      return {
        points: month?.data?.datasets?.[0]?.data?.length ?? 0,
        hasRevenue: (month?.data?.datasets?.[1]?.data ?? []).some((v: number) => v > 0),
      };
    });
    expect(chartOk.points).toBe(12);
    expect(chartOk.hasRevenue).toBe(true);
    await page.getByRole("button", { name: /1 · Assumptions/ }).click();

    // ---- dirty plan: cancel keeps everything, accept overwrites ----
    // (the plan is already dirty from applying the e-commerce preset)
    let dialogText = "";
    page.once("dialog", (d) => {
      dialogText = d.message();
      void d.dismiss();
    });
    await picker.selectOption("healthcare");
    expect(dialogText).toContain("overwrites");
    // Cancelled → still e-commerce, picker snapped back.
    await expect(page.locator("[data-mpc-preset-badge]")).toHaveText("E-commerce / D2C");
    expect(await alpine<string>(page, "d.p.industry_preset")).toBe("ecommerce");
    await expect(instaAlloc).toHaveValue("14");
    expect(await page.locator("#mpcPresetPick").inputValue()).toBe("ecommerce");

    page.once("dialog", (d) => void d.accept());
    await picker.selectOption("healthcare");
    await expect(page.locator("[data-mpc-preset-badge]")).toHaveText("Healthcare");
    expect(await alpine<string>(page, "d.p.industry_preset")).toBe("healthcare");
    await expect(page.locator("span", { hasText: /✓ Allocation/ })).toBeVisible();

    // ---- save → preset persists on the plan list badge ----
    const planName = `E2E Preset Plan ${Date.now()}`;
    await page.getByPlaceholder(/Plan name/).fill(planName);
    await page.getByRole("button", { name: /Save plan/ }).click({ noWaitAfter: true });
    await page.waitForURL(/\/user\/marketing-plan\/\d+\/edit/, { timeout: 180_000 });
    await page.locator(APP).waitFor({ state: "attached", timeout: 120_000 });
    await expect(page.locator("[data-mpc-preset-badge]")).toHaveText("Healthcare");

    await page.goto("/user/marketing-plan", { timeout: 240_000 });
    const row = page.locator("li", { hasText: planName });
    await expect(row.locator("[data-mpc-preset-badge]")).toHaveText("Healthcare");
  });
});
