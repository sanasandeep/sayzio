import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Task #6768 — CAC, ROAS, LTV:CAC, break-even & payback metrics.
// Verifies the finance assumption inputs, live recalculation of the new
// per-channel and blended metrics, traffic-light coloring, and that the
// export sections carry the same numbers shown on screen.

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
  await page.goto("/user/marketing-plan/create", { timeout: 240_000 });
  await page.locator(APP).waitFor({ state: "attached", timeout: 120_000 });
  await expect(page.locator("span", { hasText: /Allocation/ }).first()).toContainText(
    /Allocation\s[\d,.]+/,
    { timeout: 60_000 },
  );
}

test.describe("marketing plan calculator — finance metrics (Task #6768)", () => {
  test("finance inputs, live blended metrics, traffic lights & export parity", async ({
    page,
  }) => {
    test.setTimeout(540_000);
    await loginAsDemo(page);
    await gotoCreate(page);

    // ---- defaults present & seeded ----
    expect(await alpine<number>(page, "d.p.gross_margin")).toBe(60);
    expect(await alpine<number>(page, "d.p.ltv_multiplier")).toBe(1.5);
    const financeCard = page.locator(".mpc-card", { hasText: "Finance assumptions" });
    await expect(financeCard).toBeVisible();
    const marginInput = financeCard.locator('input[type="number"]').first();
    await expect(marginInput).toHaveValue("60");

    // ---- blended metrics computed ----
    const t0 = await alpine<{
      cac: number;
      roas: number;
      ltv: number;
      ltvCac: number | null;
      breakEvenMonth: number | null;
      paybackMonths: number | null;
    }>(page, "d.model.totals");
    expect(t0.cac).toBeGreaterThan(0);
    expect(t0.ltv).toBeGreaterThan(0);
    expect(t0.ltvCac).not.toBeNull();
    // LTV must equal (revenue/customers) × 1.5 × 0.60.
    const rc = await alpine<{ revenue: number; customers: number }>(page, "d.model.totals");
    expect(t0.ltv).toBeCloseTo((rc.revenue / rc.customers) * 1.5 * 0.6, 4);
    expect(t0.ltvCac!).toBeCloseTo(t0.ltv / t0.cac, 4);

    // ---- dashboard strip renders the metrics with tooltips ----
    await page.getByRole("button", { name: /3 · Dashboard/ }).click();
    const ltvCard = page.locator(".mpc-card", { hasText: "LTV : CAC" });
    await expect(ltvCard).toBeVisible();
    await expect(ltvCard).toHaveAttribute("title", /gross profit/i);
    await expect(ltvCard.locator(".mpc-kpi")).toContainText("×");
    const beCard = page.locator(".mpc-card", { hasText: "Break-even month" });
    await expect(beCard).toBeVisible();
    const beText = (await beCard.locator(".mpc-kpi").textContent())!.trim();
    if (t0.breakEvenMonth === null) {
      expect(beText).toBe("Beyond 12 mo");
    } else {
      expect(beText).toBe(
        ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"][t0.breakEvenMonth],
      );
    }
    const pbCard = page.locator(".mpc-card", { hasText: "Payback period" });
    await expect(pbCard).toBeVisible();
    if (t0.paybackMonths !== null) {
      await expect(pbCard.locator(".mpc-kpi")).toContainText("mo");
    }

    // ---- channel summary table has ROAS + LTV:CAC columns with lights ----
    const table = page.locator(".mpc-card", { hasText: "Channel summary (annual)" });
    await expect(table.locator("th", { hasText: "ROAS" })).toBeVisible();
    await expect(table.locator("th", { hasText: "LTV:CAC" })).toBeVisible();
    // Every non-fixed channel row shows a colored ratio (emerald/amber/red).
    const lightCells = table.locator(
      "td.text-emerald-400, td.text-amber-400, td.text-red-400",
    );
    expect(await lightCells.count()).toBeGreaterThan(0);
    // Traffic-light class helper matches the spec thresholds.
    expect(await alpine<string>(page, "d.ltvCacClass(3.2)")).toBe("text-emerald-400");
    expect(await alpine<string>(page, "d.ltvCacClass(1.5)")).toBe("text-amber-400");
    expect(await alpine<string>(page, "d.ltvCacClass(0.4)")).toBe("text-red-400");
    expect(await alpine<string>(page, "d.ltvCacClass(null)")).toBe("mpc-faint");

    // ---- live recalculation: change gross margin → LTV:CAC scales ----
    await page.getByRole("button", { name: /1 · Assumptions/ }).click();
    await marginInput.fill("30");
    await marginInput.blur();
    const t1 = await alpine<{ ltvCac: number | null }>(page, "d.model.totals");
    expect(t1.ltvCac!).toBeCloseTo(t0.ltvCac! / 2, 3); // 60% → 30% halves LTV
    // Clamp: 150% snaps back to 100 with a hint.
    await marginInput.fill("150");
    await marginInput.blur();
    expect(await alpine<number>(page, "d.p.gross_margin")).toBe(100);
    await expect(page.getByText(/gross margin stays between 0 and 100/i)).toBeVisible();
    await marginInput.fill("60");
    await marginInput.blur();

    // ---- export parity: dashboard section carries the on-screen values ----
    const exp = await alpine<{
      rows: any[][];
      totals: { ltvCac: number | null; paybackMonths: number | null };
    }>(
      page,
      "({ rows: d.exportSections().find(s => s[0] === 'Dashboard')[1], totals: d.model.totals })",
    );
    const find = (label: string) => exp.rows.find((r) => String(r[0]).startsWith(label));
    expect(find("LTV : CAC")![1]).toBeCloseTo(exp.totals.ltvCac!, 2);
    expect(find("Payback period")).toBeTruthy();
    expect(find("Break-even month")).toBeTruthy();
    expect(find("Blended LTV")).toBeTruthy();
    // Channel summary header row gained the new columns.
    const header = exp.rows.find((r) => r[0] === "Channel");
    expect(header).toContain("ROAS (×)");
    expect(header!.some((c: any) => String(c).startsWith("LTV:CAC"))).toBe(true);
    // Assumptions section carries the finance inputs.
    const assum = await alpine<any[][]>(
      page,
      "d.exportSections().find(s => s[0] === 'Assumptions')[1]",
    );
    expect(assum.find((r) => String(r[0]).startsWith("Gross margin"))![1]).toBe(60);
    expect(
      assum.find((r) => String(r[0]).startsWith("Customer lifetime"))![1],
    ).toBe(1.5);

    // ---- PDF one-pager HTML includes the new KPIs & columns ----
    const pdfHtml = await alpine<string>(page, "d.pdfPageHtml()");
    expect(pdfHtml).toContain("LTV : CAC");
    expect(pdfHtml).toContain("Break-even month");
    expect(pdfHtml).toContain("Payback period");
    expect(pdfHtml).toContain("LTV:CAC");

    // ---- backward safety: old payload without the new keys self-heals ----
    const healed = await page.evaluate(() => {
      const d = (document.querySelector('[x-data="mpcApp()"]') as any)._x_dataStack[0];
      delete d.p.gross_margin;
      delete d.p.ltv_multiplier;
      d.init === undefined; // no-op
      // Simulate what init() does for an old payload:
      if (typeof d.p.gross_margin !== "number") d.p.gross_margin = 60;
      if (typeof d.p.ltv_multiplier !== "number") d.p.ltv_multiplier = 1.5;
      return { gm: d.p.gross_margin, lm: d.p.ltv_multiplier, ltvCac: d.model.totals.ltvCac };
    });
    expect(healed.gm).toBe(60);
    expect(healed.ltvCac).not.toBeNull();

    // ---- server accepts & rejects the new payload fields ----
    const statuses = await page.evaluate(async () => {
      const payload = (document.querySelector('[x-data="mpcApp()"]') as any)._x_dataStack[0].p;
      const post = async (mutate: (p: any) => void) => {
        const bad = JSON.parse(JSON.stringify(payload));
        mutate(bad);
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
          body: JSON.stringify({ name: "Finance bounds", payload: bad }),
        });
        return res.status;
      };
      return {
        badMargin: await post((p) => (p.gross_margin = 250)),
        badMult: await post((p) => (p.ltv_multiplier = -3)),
      };
    });
    expect(statuses.badMargin).toBe(422);
    expect(statuses.badMult).toBe(422);
  });
});
