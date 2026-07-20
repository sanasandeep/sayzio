import { expect, test } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Regression guard: the Live Preview iframe on /user/settings/creator once
// collapsed to the browser-default 300x150 because an Alpine STRING-syntax
// :style binding replaced the static style attribute (wiping width:390px /
// height:760px / transform-origin). The pane then showed only a tiny corner
// of the public profile (a red hero blob) inside a big white wrapper.
// The binding now uses Alpine OBJECT syntax, which merges instead.

test("creator settings live preview iframe keeps its 390x760 sizing", async ({
  page,
}) => {
  // Cold first hit against the distant RDS can take >45s; give it room.
  test.setTimeout(240_000);
  await loginAsDemo(page);
  await page.goto("/user/settings/creator", {
    waitUntil: "domcontentloaded",
    timeout: 180_000,
  });

  const aside = page.locator('aside:has-text("Live preview")').first();
  await expect(aside).toBeVisible({ timeout: 30_000 });

  // The demo user may not have claimed a handle; without one there is no
  // preview iframe at all and this guard has nothing to check.
  const iframe = aside.locator("iframe");
  if ((await iframe.count()) === 0) {
    const claimMsg = aside.getByText(/Claim your handle/i);
    await expect(claimMsg).toBeVisible();
    return;
  }

  await aside.getByText("Large", { exact: true }).click();

  await expect
    .poll(
      async () =>
        iframe.evaluate((f) => {
          const r = f.getBoundingClientRect();
          return {
            w: Math.round(r.width),
            h: Math.round(r.height),
            inlineWidth: (f as HTMLElement).style.width,
            inlineHeight: (f as HTMLElement).style.height,
            origin: (f as HTMLElement).style.transformOrigin,
          };
        }),
      { timeout: 30_000 },
    )
    .toEqual(
      expect.objectContaining({
        inlineWidth: "390px",
        inlineHeight: "760px",
      }),
    );

  const box = await iframe.evaluate((f) => {
    const r = f.getBoundingClientRect();
    return { w: r.width, h: r.height };
  });
  // Broken state rendered ~226x113 (default 300x150 scaled). Fixed Large
  // mode renders roughly 390-430 wide and 700+ tall.
  expect(box.w).toBeGreaterThan(300);
  expect(box.h).toBeGreaterThan(600);
});
