import { expect, test } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// One-off verification for the Creator Profile editor polish round:
//   1. the form grid actually renders with the enlarged row gap (3.5rem = 56px),
//      i.e. consecutive card fieldsets are visually separated by ~56px, and
//   2. the "Manage posts / Save profile" action row is sticky: while the form
//      extends below the scrollport it hugs the bottom of the visible area.

test("creator profile editor: card gaps + sticky save row", async ({ page }) => {
  // Cold first hit against the distant RDS can take >45s; give it room.
  test.setTimeout(240_000);
  await loginAsDemo(page);
  await page.goto("/user/settings/creator", {
    waitUntil: "domcontentloaded",
    timeout: 180_000,
  });

  // The update route posts back to /user/settings/creator (route name
  // user.creator-profile.update, but the URL has no "creator-profile" in it).
  const form = page
    .locator('form[action*="settings/creator"][enctype="multipart/form-data"]')
    .first();
  await expect(form).toBeVisible({ timeout: 30_000 });

  const metrics = await form.evaluate((f) => {
    const cs = getComputedStyle(f);
    const kids = Array.from(f.children).filter(
      (c) => c.tagName === "FIELDSET",
    ) as HTMLElement[];
    // measure the visual distance between the first two stacked fieldsets
    let visualGap = -1;
    for (let i = 0; i + 1 < kids.length; i++) {
      const a = kids[i].getBoundingClientRect();
      const b = kids[i + 1].getBoundingClientRect();
      if (b.top > a.bottom) {
        visualGap = b.top - a.bottom;
        break;
      }
    }
    return {
      display: cs.display,
      rowGap: cs.rowGap,
      fieldsets: kids.length,
      visualGap,
    };
  });

  expect(metrics.display).toBe("grid");
  expect(parseFloat(metrics.rowGap)).toBeGreaterThanOrEqual(55);
  expect(metrics.fieldsets).toBeGreaterThan(5);
  expect(metrics.visualGap).toBeGreaterThanOrEqual(50);

  // ── Sticky save row ────────────────────────────────────────────────
  const bar = page
    .locator("form .sticky.bottom-0", { hasText: "Save profile" })
    .first();
  await expect(bar).toBeVisible();

  const sticky = await page.evaluate(() => {
    const main = document.querySelector("main");
    const bar = Array.from(
      document.querySelectorAll("form .sticky"),
    ).find((el) => el.textContent?.includes("Save profile")) as HTMLElement;
    if (!main || !bar) return { ok: false, why: "missing main or bar" };
    const cs = getComputedStyle(bar);
    // Scroll near the top so the form's natural end is far below the fold.
    main.scrollTop = 300;
    const mainRect = main.getBoundingClientRect();
    const barRect = bar.getBoundingClientRect();
    return {
      ok: true,
      position: cs.position,
      barBottom: barRect.bottom,
      scrollportBottom: mainRect.bottom,
      visible: barRect.top < mainRect.bottom && barRect.bottom > mainRect.top,
      delta: Math.abs(mainRect.bottom - barRect.bottom),
    };
  });

  expect(sticky.ok).toBe(true);
  expect((sticky as any).position).toBe("sticky");
  // While scrolled high up the page, the bar must be pinned at (or very near)
  // the bottom edge of the scrollport instead of sitting off-screen below.
  expect((sticky as any).visible).toBe(true);
  expect((sticky as any).delta).toBeLessThanOrEqual(24);
});
