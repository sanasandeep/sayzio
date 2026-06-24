import { test, expect } from "@playwright/test";

const APP_BASE = process.env.APP_URL || "http://localhost:5000";

test("diagnose monthly/annual toggle", async ({ page }) => {
  const errors: string[] = [];
  page.on("console", (m) => {
    if (m.type() === "error") errors.push(m.text());
  });
  page.on("pageerror", (e) => errors.push("PAGEERROR: " + e.message));

  await page.goto(`${APP_BASE}/pricing`, { waitUntil: "domcontentloaded" });

  // Dismiss cookie consent if present
  const accept = page.getByRole("button", { name: /accept all/i });
  if (await accept.count()) {
    await accept.first().click().catch(() => {});
  }

  await page.waitForTimeout(1500);

  // Grab the first paid plan's price number before/after toggling.
  const priceSel = ".plan-card .price-num";
  const before = await page.locator(priceSel).allInnerTexts();

  const annualBtn = page.getByRole("button", { name: /^Annual$/ });
  console.log("ANNUAL_BTN_COUNT=" + (await annualBtn.count()));
  await annualBtn.first().click();
  await page.waitForTimeout(1200);

  const after = await page.locator(priceSel).allInnerTexts();

  // Knob position (translate class) on the slider
  const knobClass = await page
    .locator(".plan-card")
    .first()
    .evaluate(() => "n/a")
    .catch(() => "n/a");

  const fs = await import("node:fs");
  fs.writeFileSync(
    "/tmp/diag.json",
    JSON.stringify(
      {
        annualBtnCount: await annualBtn.count(),
        before: before.slice(0, 8),
        after: after.slice(0, 8),
        changed: JSON.stringify(before) !== JSON.stringify(after),
        errors,
      },
      null,
      2,
    ),
  );
  expect(true).toBe(true);
});
