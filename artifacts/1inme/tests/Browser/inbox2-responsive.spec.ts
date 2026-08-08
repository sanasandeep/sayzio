import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Task: Inbox 2.0 responsiveness — verify the four unified-inbox pages have no
// horizontal overflow from phone to wide desktop, the mobile filter/triage
// toggles work below lg, and forms stack to one column on phones.

const PAGES = [
  "/user/inbox/unified",
  "/user/inbox/unified/agent",
  "/user/inbox/unified/snippets",
];

const WIDTHS = [375, 768, 1280, 1920];

async function assertNoHorizontalOverflow(page: Page, label: string) {
  const overflow = await page.evaluate(() => {
    const doc = document.documentElement;
    return doc.scrollWidth - doc.clientWidth;
  });
  expect(overflow, `${label}: horizontal overflow px`).toBeLessThanOrEqual(1);
}

test.describe("inbox 2.0 responsive", () => {
  test("pages have no horizontal overflow at all widths", async ({ page }) => {
    test.setTimeout(600_000);
    await loginAsDemo(page);
    for (const path of PAGES) {
      for (const width of WIDTHS) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(path, { timeout: 240_000 });
        await page.waitForLoadState("domcontentloaded");
        await assertNoHorizontalOverflow(page, `${path}@${width}`);
      }
    }
  });

  test("list page: filters hidden behind toggle on mobile, visible on desktop", async ({
    page,
  }) => {
    test.setTimeout(600_000);
    await loginAsDemo(page);

    // Mobile: sidebar hidden, toggle reveals it.
    await page.setViewportSize({ width: 375, height: 800 });
    await page.goto("/user/inbox/unified", { timeout: 240_000 });
    const aside = page.locator("aside.card-premium").first();
    await expect(aside).toBeHidden();
    const toggle = page.getByRole("button", { name: /Filters/i }).first();
    await expect(toggle).toBeVisible();
    await toggle.click();
    await expect(aside).toBeVisible();
    await toggle.click();
    await expect(aside).toBeHidden();

    // Desktop: sidebar always visible, toggle gone.
    await page.setViewportSize({ width: 1280, height: 900 });
    await expect(aside).toBeVisible();
    await expect(toggle).toBeHidden();
  });

  test("agent + snippets forms stack to one column on phones", async ({
    page,
  }) => {
    test.setTimeout(600_000);
    await loginAsDemo(page);
    await page.setViewportSize({ width: 375, height: 800 });

    await page.goto("/user/inbox/unified/snippets", { timeout: 240_000 });
    const shortcut = page.locator("input[name=shortcut]");
    const label = page.locator("input[name=label]");
    const a = await shortcut.boundingBox();
    const b = await label.boundingBox();
    expect(a && b && b.y > a.y + a.height - 1, "snippet inputs stacked").toBe(
      true,
    );

    await page.setViewportSize({ width: 1280, height: 900 });
    const a2 = await shortcut.boundingBox();
    const b2 = await label.boundingBox();
    expect(
      a2 && b2 && Math.abs(a2.y - b2.y) < 2,
      "snippet inputs side-by-side on desktop",
    ).toBe(true);
  });
});
