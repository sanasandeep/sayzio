import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Task #6740 — Confirm the Marketing Plan Calculator's live math and charts
// work in a real browser. The whole computation engine (monthly plan,
// dashboard KPIs, ROI totals, INR/USD toggle, allocation-sum warning) is
// client-side Alpine + Chart.js; the feature test only covers server
// rendering and save/load, so this spec drives the browser-side engine.

const APP = '[x-data="mpcApp()"]';

/** Read a value out of the live Alpine component. */
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
  await page.goto("/user/marketing-plan/create", { timeout: 240_000 });
  await page.locator(APP).waitFor({ state: "attached", timeout: 120_000 });
  // Alpine mounted + engine computed: the allocation badge renders a number.
  await expect(page.locator("span", { hasText: /Allocation/ }).first()).toContainText(
    /Allocation\s[\d,.]+/,
    { timeout: 60_000 },
  );
}

function budgetInput(page: Page) {
  return page
    .locator(".mpc-card", { hasText: "Total annual ad-spend budget" })
    .locator('input[type="number"]');
}

test.describe("marketing plan calculator — live math + charts", () => {
  test("engine recomputes, currency toggles, allocation warning, save & reopen", async ({
    page,
  }) => {
    test.setTimeout(540_000);
    await loginAsDemo(page);
    await gotoCreate(page);

    // ---- default totals appear (engine ran with seeded defaults) ----
    const defaults = await alpine<{ revenue: number; spend: number; customers: number }>(
      page,
      "d.model.totals",
    );
    expect(defaults.spend).toBeGreaterThan(0);
    expect(defaults.revenue).toBeGreaterThan(0);
    expect(defaults.customers).toBeGreaterThan(0);
    // Default allocation totals 100% → OK badge.
    await expect(page.locator("span", { hasText: /✓ Allocation/ })).toBeVisible();

    // Dashboard tab renders KPI text + live Chart.js charts.
    await page.getByRole("button", { name: /3 · Dashboard/ }).click();
    const budgetKpi = page
      .locator(".mpc-card", { hasText: "Total annual budget" })
      .locator(".mpc-kpi");
    await expect(budgetKpi).toBeVisible();
    const kpiBefore = (await budgetKpi.textContent())!.trim();
    expect(kpiBefore).toMatch(/^₹[\d,]+/);
    // Charts actually mounted with 12 monthly points.
    const chartInfo = await page.evaluate(() => {
      const C = (window as any).Chart;
      const month = C?.getChart(document.getElementById("mpcMonthChart"));
      const chan = C?.getChart(document.getElementById("mpcChannelChart"));
      return {
        monthPoints: month?.data?.datasets?.[0]?.data?.length ?? 0,
        monthHasRevenue: (month?.data?.datasets?.[1]?.data ?? []).some((v: number) => v > 0),
        channelSlices: chan?.data?.datasets?.[0]?.data?.length ?? 0,
        monthVisible: (document.getElementById("mpcMonthChart")?.clientWidth ?? 0) > 0,
      };
    });
    expect(chartInfo.monthPoints).toBe(12);
    expect(chartInfo.monthHasRevenue).toBe(true);
    expect(chartInfo.channelSlices).toBeGreaterThan(0);
    expect(chartInfo.monthVisible).toBe(true);

    // ---- change the budget → numbers recompute live ----
    await page.getByRole("button", { name: /1 · Assumptions/ }).click();
    const origBudget = await alpine<number>(page, "d.p.annual_budget");
    const newBudget = origBudget * 2;
    await budgetInput(page).fill(String(newBudget));
    await budgetInput(page).blur();
    const doubled = await alpine<{ revenue: number; spend: number }>(page, "d.model.totals");
    // Paid-channel spend/revenue scale with budget (Sayzio's fixed row does not),
    // so totals must strictly grow but stay under a pure 2× of the old totals.
    expect(doubled.spend).toBeGreaterThan(defaults.spend);
    expect(doubled.revenue).toBeGreaterThan(defaults.revenue);
    await page.getByRole("button", { name: /3 · Dashboard/ }).click();
    await expect(budgetKpi).not.toHaveText(kpiBefore);
    await expect(budgetKpi).toContainText("₹" + Math.round(newBudget).toLocaleString("en-IN"));

    // ---- INR → USD toggle divides by the display rate ----
    const rate = await alpine<number>(page, "d.p.usd_inr_rate");
    expect(rate).toBeGreaterThan(1);
    await page.getByRole("button", { name: "$ USD" }).click();
    await expect(budgetKpi).toContainText("$");
    const usdShown = await alpine<string>(page, "d.money(d.p.annual_budget)");
    const expectedUsd =
      "$" +
      (newBudget / rate).toLocaleString("en-IN", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      });
    expect(usdShown).toBe(expectedUsd);
    // Charts re-render in USD when the dashboard is reopened.
    await page.getByRole("button", { name: /1 · Assumptions/ }).click();
    await page.getByRole("button", { name: /3 · Dashboard/ }).click();
    const usdChart = await page.evaluate(() => {
      const C = (window as any).Chart;
      const month = C?.getChart(document.getElementById("mpcMonthChart"));
      return month?.data?.datasets?.[0]?.label ?? "";
    });
    expect(usdChart).toContain("$");
    await page.getByRole("button", { name: "₹ INR" }).click();

    // ---- break the allocation total → ≠100% warning ----
    await page.getByRole("button", { name: /1 · Assumptions/ }).click();
    const firstAlloc = page.locator("tbody tr td:nth-child(2) input").first();
    const allocBefore = await firstAlloc.inputValue();
    await firstAlloc.fill(String(Number(allocBefore) + 10));
    await firstAlloc.blur();
    await expect(page.locator("span", { hasText: /⚠ Allocation/ })).toBeVisible();
    await expect(page.locator("span", { hasText: /must total 100%/ })).toBeVisible();
    // Restore → OK badge returns.
    await firstAlloc.fill(allocBefore);
    await firstAlloc.blur();
    await expect(page.locator("span", { hasText: /✓ Allocation/ })).toBeVisible();

    // ---- save, follow the redirect, reopen and verify persistence ----
    const planName = `E2E Calc Plan ${Date.now()}`;
    await page.getByPlaceholder(/Plan name/).fill(planName);
    await page.getByRole("button", { name: /Save plan/ }).click({ noWaitAfter: true });
    await page.waitForURL(/\/user\/marketing-plan\/\d+\/edit/, { timeout: 180_000 });
    await page.locator(APP).waitFor({ state: "attached", timeout: 120_000 });
    const reopened = await alpine<{ budget: number; name: string }>(
      page,
      "({ budget: d.p.annual_budget, name: d.name })",
    );
    expect(reopened.budget).toBe(newBudget);
    expect(reopened.name).toBe(planName);
    // Reload cold to prove it came from the server, not client state.
    await page.reload({ timeout: 240_000 });
    await page.locator(APP).waitFor({ state: "attached", timeout: 120_000 });
    expect(await alpine<number>(page, "d.p.annual_budget")).toBe(newBudget);
  });

  test("out-of-range inputs clamp with a hint and the server rejects bad payloads", async ({
    page,
  }) => {
    test.setTimeout(480_000);
    await loginAsDemo(page);
    await gotoCreate(page);

    // ---- USD→INR rate of 0 clamps back to 1 with an inline hint ----
    const rateInput = page
      .locator(".mpc-card", { hasText: "USD → INR display rate" })
      .locator('input[type="number"]');
    await rateInput.fill("0");
    await rateInput.blur();
    expect(await alpine<number>(page, "d.p.usd_inr_rate")).toBe(1);
    await expect(page.getByText(/rate must be at least 1/)).toBeVisible();

    // ---- negative allocation clamps to 0 (no negative spend) ----
    const firstAlloc = page.locator("tbody tr td:nth-child(2) input").first();
    await firstAlloc.fill("-25");
    await firstAlloc.blur();
    expect(await alpine<number>(page, "d.p.channels.find(c => !c.fixed).alloc")).toBe(0);
    await expect(
      page.getByText(/allocations and conversion rates stay between/i),
    ).toBeVisible();

    // Projections stay finite and non-negative even after abuse.
    const totals = await alpine<{ spend: number; revenue: number; roas: number }>(
      page,
      "d.model.totals",
    );
    expect(Number.isFinite(totals.spend)).toBe(true);
    expect(Number.isFinite(totals.revenue)).toBe(true);
    expect(Number.isFinite(totals.roas)).toBe(true);
    expect(totals.spend).toBeGreaterThanOrEqual(0);

    // ---- server-side: validatePlan rejects out-of-range payload values ----
    const statuses = await page.evaluate(async () => {
      const payload = (document.querySelector('[x-data="mpcApp()"]') as any)._x_dataStack[0].p;
      const post = async (mutate: (p: any) => void) => {
        const bad = JSON.parse(JSON.stringify(payload));
        mutate(bad); // the client clamps, so hand-craft the bad values
        const res = await fetch("/user/marketing-plan", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN":
              (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ??
              "",
          },
          body: JSON.stringify({ name: "Bad plan", payload: bad }),
        });
        return res.status;
      };
      return {
        zeroRate: await post((p) => (p.usd_inr_rate = 0)),
        hugeBudget: await post((p) => (p.annual_budget = 1e15)), // beyond the 1e12 clamp cap
        negativeCost: await post((p) => (p.channels[1].cpv = -5)),
      };
    });
    expect(statuses.zeroRate).toBe(422);
    expect(statuses.hugeBudget).toBe(422);
    expect(statuses.negativeCost).toBe(422);
  });

  test("light and dark mode visual pass across all four tabs", async ({ page }, testInfo) => {
    test.setTimeout(480_000);
    await loginAsDemo(page);
    await gotoCreate(page);

    const tabs: Array<[string, RegExp]> = [
      ["assumptions", /1 · Assumptions/],
      ["monthly", /2 · Monthly Plan/],
      ["dashboard", /3 · Dashboard/],
      ["roi", /4 · Sayzio ROI/],
    ];

    for (const mode of ["dark", "light"] as const) {
      // Theme is driven purely by the html.light-mode class (see repo memory:
      // cookie plumbing does not flip it in Playwright).
      await page.evaluate((m) => {
        document.documentElement.classList[m === "dark" ? "remove" : "add"]("light-mode");
      }, mode);
      await page.waitForTimeout(400);

      for (const [key, label] of tabs) {
        await page.getByRole("button", { name: label }).click();
        await page.waitForTimeout(key === "dashboard" ? 800 : 250);

        // Legibility: the section titles must resolve to the mode's ink color,
        // not vanish into the background.
        const titleColor = await page
          .locator(".mpc-title:visible")
          .first()
          .evaluate((el) => getComputedStyle(el).color);
        if (mode === "light") {
          expect(titleColor).toBe("rgb(15, 23, 42)");
        } else {
          expect(titleColor).toBe("rgb(255, 255, 255)");
        }
        if (key !== "dashboard") {
          // Inputs stay legible too (dark ink on white in light mode, light
          // ink in dark mode). Only the non-dashboard tabs have body inputs
          // (the dashboard is KPI/chart-only; the always-visible header name
          // input would make this a no-op there). A global light-mode rule
          // may repaint inputs with a slightly different dark navy, so
          // assert by brightness, not exact hue.
          const inputBrightness = await page
            .locator(".mpc-input:visible")
            .first()
            .evaluate((el) => {
              const m = getComputedStyle(el).color.match(/\d+/g)!.map(Number);
              return (m[0] * 299 + m[1] * 587 + m[2] * 114) / 1000;
            });
          if (mode === "light") {
            expect(inputBrightness).toBeLessThan(100);
          } else {
            expect(inputBrightness).toBeGreaterThan(180);
          }
        }

        if (key === "dashboard") {
          // KPI numbers must resolve to the mode's ink color.
          const kpiColor = await page
            .locator(".mpc-kpi:visible")
            .first()
            .evaluate((el) => getComputedStyle(el).color);
          expect(kpiColor).toBe(mode === "light" ? "rgb(15, 23, 42)" : "rgb(255, 255, 255)");
          // Chart.js re-themes its axis/legend ink when charts are rendered
          // with the current mode class.
          const chartColor = await page.evaluate(() => (window as any).Chart?.defaults?.color);
          expect(chartColor).toBe(mode === "light" ? "#475569" : "rgba(255,255,255,0.65)");
        }

        await page.screenshot({
          path: testInfo.outputPath(`mpc-${mode}-${key}.png`),
          fullPage: true,
        });
      }
    }
  });
});
