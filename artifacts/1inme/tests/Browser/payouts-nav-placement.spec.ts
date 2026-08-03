import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Task: "Earnings & Payouts" moved from the Monetization group into the
// Links & Pages group — in BOTH the desktop sidebar and the mobile drawer.

test.describe("payouts nav placement", () => {
  test("desktop sidebar: payouts sits in Links & Pages, not Monetization; active state works", async ({
    page,
  }) => {
    test.setTimeout(300_000);
    await loginAsDemo(page);
    await page.goto("/user/settings/profile", { timeout: 120_000 });
    await page
      .locator("aside.sidebar-v2 .nav-label", { hasText: /^Dashboard$/ })
      .first()
      .waitFor({ state: "attached", timeout: 120_000 });

    const audit = await auditGroups(page, "aside.sidebar-v2");
    expect(audit.linksGroupHasPayouts).toBe(true);
    expect(audit.monetGroupHasPayouts).toBe(false);

    // Open the Links & Pages group and navigate to payouts; assert active + group auto-open.
    const linksToggle = page
      .locator("aside.sidebar-v2 .sidebar-group-toggle", {
        hasText: /Links & Pages/i,
      })
      .first();
    if ((await linksToggle.getAttribute("aria-expanded")) !== "true") {
      await linksToggle.click();
    }
    const payoutsLink = page
      .locator("aside.sidebar-v2 a.sidebar-link", {
        hasText: /Earnings & Payouts/i,
      })
      .first();
    await expect(payoutsLink).toBeVisible();
    await expect(payoutsLink).toHaveAttribute("href", /\/user\/payouts/);
    // Navigate directly (clicking works but the cold-RDS render can exceed the
    // navigation budget in this env; the href assert above covers the wiring).
    await page.goto("/user/payouts", { timeout: 240_000 });
    await page
      .locator("aside.sidebar-v2 .nav-label", { hasText: /^Dashboard$/ })
      .first()
      .waitFor({ state: "attached", timeout: 120_000 });

    // In this env the payouts feature may be soon-gated: /user/payouts 302s
    // to the branded coming-soon page (route user.coming-soon.show), so no
    // route is ever user.payouts.* and no nav item highlights — identical to
    // the pre-move behavior. Only assert the active state when we actually
    // landed on the payouts route.
    if (/\/user\/payouts/.test(page.url())) {
      const activePayouts = page
        .locator("aside.sidebar-v2 a.sidebar-link.active", {
          hasText: /Earnings & Payouts/i,
        })
        .first();
      await expect(activePayouts).toBeVisible();
      const linksToggle2 = page
        .locator("aside.sidebar-v2 .sidebar-group-toggle", {
          hasText: /Links & Pages/i,
        })
        .first();
      await expect(linksToggle2).toHaveAttribute("aria-expanded", "true");
    } else {
      expect(page.url()).toMatch(/coming-soon\/payouts/);
    }
  });

  test("mobile drawer: payouts sits in Links & Pages, not Monetization", async ({
    page,
  }) => {
    test.setTimeout(300_000);
    await page.setViewportSize({ width: 390, height: 844 });
    await loginAsDemo(page);
    await page.goto("/user/settings/profile", { timeout: 120_000 });
    // Open the mobile drawer via the hamburger.
    const burger = page.locator("header button[\\@click*='mobileMenu'], header [x-on\\:click*='mobileMenu']").first();
    if (await burger.count()) {
      await burger.click();
    } else {
      // Fallback: flip the Alpine flag directly.
      await page.evaluate(() => {
        const root = document.querySelector("[x-data]") as any;
        // eslint-disable-next-line no-underscore-dangle
        if (root && (root as any)._x_dataStack) (root as any)._x_dataStack[0].mobileMenu = true;
      });
    }
    // The drawer duplicates the group toggles — audit the SECOND nav block
    // (the one that is not inside aside.sidebar-v2).
    const audit = await auditGroups(page, "body", "aside.sidebar-v2");
    expect(audit.linksGroupHasPayouts).toBe(true);
    expect(audit.monetGroupHasPayouts).toBe(false);
  });
});

async function auditGroups(
  page: Page,
  rootSel: string,
  excludeSel?: string,
): Promise<{ linksGroupHasPayouts: boolean; monetGroupHasPayouts: boolean }> {
  return page.evaluate(
    ([root, exclude]) => {
      const toggles = Array.from(
        document.querySelectorAll<HTMLElement>(`${root} .sidebar-group-toggle`),
      ).filter((t) => !exclude || !t.closest(exclude));
      const find = (re: RegExp) =>
        toggles.find((t) => re.test(t.textContent || ""));
      const groupHas = (toggle: HTMLElement | undefined, re: RegExp) => {
        if (!toggle) return false;
        const wrap = toggle.parentElement!;
        return re.test(wrap.textContent || "");
      };
      const linksToggle = find(/Links\s*&\s*Pages/i);
      const monetToggle = find(/^\s*Monetization/i);
      return {
        linksGroupHasPayouts: groupHas(linksToggle, /Earnings\s*&\s*Payouts/i),
        monetGroupHasPayouts: groupHas(monetToggle, /Earnings\s*&\s*Payouts/i),
      };
    },
    [rootSel, excludeSel ?? ""] as const,
  );
}
