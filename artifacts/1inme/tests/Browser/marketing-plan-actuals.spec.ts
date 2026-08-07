import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Task #6772 — "Use my Sayzio data" prefill + Plan vs. Actual dashboard.
// Verifies in a real browser that:
//  1. the assumptions tab offers the prefill button and clicking it fetches
//     the real-usage endpoint and either applies values or explains why not,
//  2. the Dashboard shows the Plan vs. Actual card, loads actuals on demand,
//     and renders either the comparison chart or the graceful empty state,
//  3. feature-usage badges appear once actuals are loaded.

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

test.describe("marketing plan calculator — real Sayzio data", () => {
  test("prefill button, Plan vs Actual card and usage badges", async ({ page }) => {
    test.setTimeout(540_000);
    await loginAsDemo(page);
    await page.goto(`/user/marketing-plan/create`, { timeout: 240_000 });
    await page.locator(APP).waitFor({ state: "attached", timeout: 120_000 });

    // ---- prefill button fetches the endpoint and reacts visibly ----
    const btn = page.locator("[data-mpc-use-actuals]");
    await expect(btn).toBeVisible();
    const [resp] = await Promise.all([
      page.waitForResponse((r) => r.url().includes("/user/marketing-plan/actuals"), {
        timeout: 120_000,
      }),
      btn.click(),
    ]);
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.ok).toBe(true);
    expect(body.actuals).toHaveProperty("has_data");
    expect(body.actuals).toHaveProperty("months");

    // Applied flash (has data) or the graceful explanation (empty workspace).
    if (body.actuals.has_data) {
      await expect(page.locator("[data-mpc-actuals-applied]")).toBeVisible({ timeout: 30_000 });
      if (body.actuals.monthly_visitors > 0) {
        expect(await alpine<number>(page, "d.p.organic_visitors")).toBe(
          body.actuals.monthly_visitors,
        );
      }
    } else {
      await expect(page.locator("[data-mpc-actuals-error]")).toBeVisible({ timeout: 30_000 });
    }

    // ---- feature badges render once actuals are loaded ----
    await expect(page.locator("[data-mpc-feature-badges]")).toBeVisible();
    await expect(page.locator("[data-mpc-feature-badges] span")).toHaveCount(3);

    // ---- Dashboard: Plan vs Actual card ----
    await page.locator(".mpc-step", { hasText: "Dashboard" }).click();
    const card = page.locator("[data-mpc-plan-vs-actual]");
    await expect(card).toBeVisible();
    if (body.actuals.has_data) {
      await expect(card.locator("#mpcActualChart")).toBeVisible({ timeout: 30_000 });
      // Chart.js actually painted onto the canvas.
      await expect
        .poll(async () =>
          page.evaluate(() => {
            const c = document.getElementById("mpcActualChart") as HTMLCanvasElement | null;
            return c ? c.width : 0;
          }),
        )
        .toBeGreaterThan(0);
    } else {
      await expect(card.locator("[data-mpc-actuals-empty]")).toBeVisible();
    }
  });
});
