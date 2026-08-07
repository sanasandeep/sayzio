import { expect, test } from "@playwright/test";
import fs from "fs";

import { loginAsDemo } from "./login-as-demo";

// Task #6738 — export the marketing plan as XLSX/CSV, respecting the
// INR/USD display toggle. All export logic is client-side (Alpine).

test.describe("marketing plan export", () => {
  test("CSV export downloads all sections and respects the currency toggle", async ({
    page,
  }) => {
    test.setTimeout(300_000);
    await loginAsDemo(page);
    await page.goto("/user/marketing-plan/create", { timeout: 240_000 });
    await page
      .getByRole("button", { name: /Export/ })
      .first()
      .waitFor({ state: "visible", timeout: 120_000 });

    // --- INR (default) CSV export ---
    await page.getByRole("button", { name: /Export/ }).first().click();
    const [dl] = await Promise.all([
      page.waitForEvent("download", { timeout: 60_000 }),
      page.getByRole("button", { name: /CSV \(\.csv\)/ }).click(),
    ]);
    expect(dl.suggestedFilename()).toMatch(/\.csv$/);
    const csvPath = await dl.path();
    const csv = fs.readFileSync(csvPath!, "utf8");

    // All four sections present.
    for (const section of [
      "=== Assumptions ===",
      "=== Monthly Plan ===",
      "=== Dashboard ===",
      "=== Sayzio ROI ===",
    ]) {
      expect(csv).toContain(section);
    }
    // Monthly table shape + INR currency markers.
    expect(csv).toContain("Channel,Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec,Total");
    expect(csv).toContain("Display currency,INR");
    expect(csv).toContain("Spend (₹)");
    expect(csv).toContain("TOTAL VALUE (₹)");
    expect(csv).toContain("All channels");

    // --- Toggle to USD and re-export ---
    await page.getByRole("button", { name: "$ USD" }).click();
    await page.getByRole("button", { name: /Export/ }).first().click();
    const [dlUsd] = await Promise.all([
      page.waitForEvent("download", { timeout: 60_000 }),
      page.getByRole("button", { name: /CSV \(\.csv\)/ }).click(),
    ]);
    const csvUsd = fs.readFileSync((await dlUsd.path())!, "utf8");
    expect(csvUsd).toContain("Display currency,USD");
    expect(csvUsd).toContain("Spend ($)");
    expect(csvUsd).not.toContain("Spend (₹)");

    // Numbers actually converted: annual budget row differs between the two.
    const budgetRow = (s: string) =>
      s.split(/\r?\n/).find((l) => l.startsWith("Total annual ad-spend budget"));
    const inrBudget = parseFloat(budgetRow(csv)!.split(",")[1]);
    const usdBudget = parseFloat(budgetRow(csvUsd)!.split(",")[1]);
    expect(inrBudget).toBeGreaterThan(0);
    expect(usdBudget).toBeGreaterThan(0);
    expect(usdBudget).toBeLessThan(inrBudget);
  });

  test("XLSX export downloads a real zip-based workbook", async ({ page }) => {
    test.setTimeout(300_000);
    await loginAsDemo(page);
    await page.goto("/user/marketing-plan/create", { timeout: 240_000 });
    const exportBtn = page.getByRole("button", { name: /Export/ }).first();
    await exportBtn.waitFor({ state: "visible", timeout: 120_000 });
    // Wait for the deferred SheetJS vendor script.
    await page.waitForFunction(() => typeof (window as any).XLSX !== "undefined", null, {
      timeout: 60_000,
    });

    await exportBtn.click();
    const [dl] = await Promise.all([
      page.waitForEvent("download", { timeout: 60_000 }),
      page.getByRole("button", { name: /Excel \(\.xlsx\)/ }).click(),
    ]);
    expect(dl.suggestedFilename()).toMatch(/\.xlsx$/);
    const buf = fs.readFileSync((await dl.path())!);
    expect(buf.length).toBeGreaterThan(1000);
    // XLSX is a zip: PK magic bytes.
    expect(buf[0]).toBe(0x50);
    expect(buf[1]).toBe(0x4b);
  });

  // Task #6746 — client-side PDF one-pager (html2canvas + minimal PDF writer).
  test("PDF one-pager export downloads a real single-page PDF", async ({ page }) => {
    test.setTimeout(300_000);
    await loginAsDemo(page);
    await page.goto("/user/marketing-plan/create", { timeout: 240_000 });
    const exportBtn = page.getByRole("button", { name: /Export/ }).first();
    await exportBtn.waitFor({ state: "visible", timeout: 120_000 });
    // Wait for the deferred html2canvas vendor script + Chart.js.
    await page.waitForFunction(
      () =>
        typeof (window as any).html2canvas !== "undefined" &&
        typeof (window as any).Chart !== "undefined",
      null,
      { timeout: 60_000 },
    );

    await exportBtn.click();
    const [dl] = await Promise.all([
      page.waitForEvent("download", { timeout: 120_000 }),
      page.getByRole("button", { name: /PDF one-pager/ }).click(),
    ]);
    expect(dl.suggestedFilename()).toMatch(/\.pdf$/);
    const buf = fs.readFileSync((await dl.path())!);
    // Valid PDF header/trailer and a rendered JPEG payload of real size.
    expect(buf.subarray(0, 5).toString("latin1")).toBe("%PDF-");
    expect(buf.toString("latin1")).toContain("%%EOF");
    expect(buf.toString("latin1")).toContain("/DCTDecode");
    expect(buf.length).toBeGreaterThan(50_000);
    // No error surfaced in the UI.
    await expect(page.locator("text=Could not generate the PDF")).toHaveCount(0);
  });

  // Task #6763 — the one-pager must stay readable when a plan carries many
  // extra channels: the summary table caps rows (small channels aggregate
  // into an "Other" row) and the PDF still generates within a sane height.
  test("PDF one-pager stays a sane single page with an inflated channel list", async ({
    page,
  }) => {
    test.setTimeout(300_000);
    await loginAsDemo(page);
    await page.goto("/user/marketing-plan/create", { timeout: 240_000 });
    const exportBtn = page.getByRole("button", { name: /Export/ }).first();
    await exportBtn.waitFor({ state: "visible", timeout: 120_000 });
    await page.waitForFunction(
      () =>
        typeof (window as any).html2canvas !== "undefined" &&
        typeof (window as any).Chart !== "undefined" &&
        typeof (window as any).Alpine !== "undefined",
      null,
      { timeout: 60_000 },
    );

    // Inflate the channel list to 40 channels via the Alpine component state.
    const counts = await page.evaluate(() => {
      const el = document.querySelector('[x-data^="mpcApp"]') as any;
      const d = (window as any).Alpine.$data(el);
      const before = d.p.channels.length;
      for (let i = 1; i <= 40 - before; i++) {
        d.p.channels.push({
          key: `extra_${i}`,
          name: `Extra Channel ${i}`,
          alloc: 0.5,
          cpv: 10,
          vl: 3,
          lc: 5,
          acv: 4000,
          notes: "",
          fixed: false,
        });
      }
      return { before, after: d.p.channels.length };
    });
    expect(counts.after).toBe(40);

    // The PDF summary caps rows and aggregates the tail into "Other".
    const summary = await page.evaluate(() => {
      const el = document.querySelector('[x-data^="mpcApp"]') as any;
      const d = (window as any).Alpine.$data(el);
      const rows = d.pdfSummaryRows();
      return {
        rowCount: rows.length,
        lastName: rows[rows.length - 1].name,
        html: d.pdfPageHtml() as string,
      };
    });
    expect(summary.rowCount).toBeLessThanOrEqual(16);
    expect(summary.lastName).toMatch(/^Other \(\d+ channels\)$/);
    expect(summary.html).toContain("Other (");

    // Full export still succeeds and the page stays a sane one-pager height.
    await exportBtn.click();
    const [dl] = await Promise.all([
      page.waitForEvent("download", { timeout: 120_000 }),
      page.getByRole("button", { name: /PDF one-pager/ }).click(),
    ]);
    expect(dl.suggestedFilename()).toMatch(/\.pdf$/);
    const buf = fs.readFileSync((await dl.path())!);
    const s = buf.toString("latin1");
    expect(s.startsWith("%PDF-")).toBe(true);
    expect(s).toContain("%%EOF");
    expect(s).toContain("/DCTDecode");
    // Parse the single page's MediaBox and assert a sane aspect ratio —
    // roughly one landscape-ish sheet, never a mile-long scroll.
    const mb = s.match(/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]/);
    expect(mb).not.toBeNull();
    const wPt = parseFloat(mb![1]);
    const hPt = parseFloat(mb![2]);
    expect(wPt).toBeGreaterThan(0);
    expect(hPt / wPt).toBeLessThan(2.1);
    // No error surfaced in the UI.
    await expect(page.locator("text=Could not generate the PDF")).toHaveCount(0);
  });
});
