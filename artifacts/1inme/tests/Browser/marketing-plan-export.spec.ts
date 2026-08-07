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
});
