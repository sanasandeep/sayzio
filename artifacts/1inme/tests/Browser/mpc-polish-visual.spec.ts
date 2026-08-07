import { expect, test } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

/**
 * Task #6765 — visual verification of the Marketing Plan Calculator polish:
 *  - the Sayzio plan <select> renders a single non-repeating chevron (no
 *    criss-cross tiling artifact) in BOTH themes,
 *  - the stepper tabs render with numbered badges + active state,
 *  - the AI credits field defaults to ₹2,000,
 *  - the Dashboard tab exposes its own export controls incl. per-chart PNG.
 */
const APP = '[x-data="mpcApp()"]';

test("calculator polish — select chevron, stepper, defaults, dashboard exports", async ({ page }) => {
  test.setTimeout(540_000);
  await loginAsDemo(page);
  await page.goto("/user/marketing-plan/create", { timeout: 240_000 });
  await page.locator(APP).waitFor({ state: "attached", timeout: 120_000 });
  await expect(page.locator("span", { hasText: /Allocation/ }).first()).toContainText(
    /Allocation\s[\d,.]+/,
    { timeout: 60_000 },
  );

  const planSelect = page.locator('select[x-model="p.plan_slug"]');

  for (const light of [false, true]) {
    await page.evaluate((l) => {
      document.documentElement.classList.toggle("light-mode", l);
    }, light);
    await page.waitForTimeout(200);

    // The chevron must not tile: computed background-repeat stays no-repeat
    // even though .mpc-input styles the select.
    const bg = await planSelect.evaluate((el) => {
      const cs = getComputedStyle(el);
      return { repeat: cs.backgroundRepeat, size: cs.backgroundSize, image: cs.backgroundImage };
    });
    expect(bg.image).toContain("svg");
    expect(bg.repeat).toBe("no-repeat");

    await page.screenshot({
      path: `/tmp/mpc-${light ? "light" : "dark"}-assumptions.png`,
      fullPage: false,
    });
  }

  // Stepper: 4 numbered steps, step 1 active.
  await expect(page.locator(".mpc-step")).toHaveCount(4);
  await expect(page.locator(".mpc-step.active .mpc-step-num")).toHaveText("1");

  // AI credits default ₹2,000.
  const aiCredits = page.locator('input[x-model\\.number="p.ai_credits"]');
  await expect(aiCredits).toHaveValue("2000");
  // Stays editable.
  await aiCredits.fill("3000");
  await expect(aiCredits).toHaveValue("3000");
  await aiCredits.fill("2000");

  // Dashboard tab: on-tab exports + per-chart PNG buttons.
  await page.getByRole("button", { name: /3 · Dashboard/ }).click();
  await expect(page.getByRole("button", { name: /Excel$/ })).toBeVisible();
  await expect(page.getByRole("button", { name: /CSV$/ })).toBeVisible();
  await expect(page.getByRole("button", { name: /PDF$/ })).toBeVisible();
  await expect(page.getByRole("button", { name: /PNG/ })).toHaveCount(2);

  // Per-chart PNG download actually produces a file.
  const dl = page.waitForEvent("download", { timeout: 30_000 });
  await page.getByRole("button", { name: /PNG/ }).first().click();
  const file = await dl;
  expect(file.suggestedFilename()).toMatch(/spend-vs-revenue\.png$/);

  await page.screenshot({ path: "/tmp/mpc-light-dashboard.png", fullPage: false });
  await page.evaluate(() => document.documentElement.classList.remove("light-mode"));
  await page.waitForTimeout(200);
  await page.screenshot({ path: "/tmp/mpc-dark-dashboard.png", fullPage: false });
});
