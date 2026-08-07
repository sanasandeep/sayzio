import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Task #6751 — Confirm the Marketing Plan Calculator's unsaved-changes guard
// can't silently break. The editor flags unsaved edits (`dirty`), warns via an
// in-app confirm() on link clicks plus a browser beforeunload prompt, and
// saving clears the flag. All of this is client-side Alpine, so it needs a
// real browser.
//
// Dialog gotcha (see .agents/memory/playwright-beforeunload-dialog.md):
// Playwright auto-DISMISSES dialogs, which cancels navigation. When leaving a
// dirty page TWO dialogs fire (in-app confirm, then beforeunload), so a
// persistent `page.on('dialog', ...)` handler is required to let navigation
// proceed — `page.once` is not enough.

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

async function waitForApp(page: Page) {
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

function backLink(page: Page) {
  // The editor header's back link (the sidebar has same-text links too).
  return page.locator(`${APP} a[href*="/user/marketing-plan"]`, {
    hasText: "Marketing Plan Calculator",
  });
}

test.describe("marketing plan calculator — unsaved-changes guard", () => {
  test("dirty flag lifecycle: load clean, flips on edit, confirm blocks, save clears, accept leaves", async ({
    page,
  }) => {
    test.setTimeout(540_000);

    // Persistent dialog policy, swappable per test phase. Default: accept
    // (also covers any teardown-time beforeunload prompt).
    const dialogs: string[] = [];
    let dialogAction: "accept" | "dismiss" = "accept";
    page.on("dialog", (d) => {
      dialogs.push(`${d.type()}:${dialogAction}`);
      if (dialogAction === "accept") void d.accept();
      else void d.dismiss();
    });

    await loginAsDemo(page);
    await page.goto("/user/marketing-plan/create", { timeout: 240_000 });
    await waitForApp(page);

    // ---- 1. clean on load ----
    expect(await alpine<boolean>(page, "d.dirty")).toBe(false);

    // ---- 2. a name edit flips the flag ----
    const planName = `E2E Dirty Guard ${Date.now()}`;
    await page.getByPlaceholder(/Plan name/).fill(planName);
    await expect.poll(() => alpine<boolean>(page, "d.dirty")).toBe(true);

    // ---- 3. dismissed in-app confirm blocks navigation ----
    dialogAction = "dismiss";
    const urlBefore = page.url();
    await backLink(page).click({ noWaitAfter: true });
    // Only in-app confirm() dialogs fire (dismissing prevents navigation, so
    // beforeunload never gets a chance). Chromium may re-dispatch the click,
    // so more than one confirm is possible — every one must be a dismissed
    // confirm and the page must not have navigated.
    await expect.poll(() => dialogs.length).toBeGreaterThan(0);
    await page.waitForTimeout(1500);
    expect(page.url()).toBe(urlBefore);
    expect(dialogs.every((d) => d === "confirm:dismiss")).toBe(true);
    expect(await alpine<boolean>(page, "d.dirty")).toBe(true);
    dialogAction = "accept";

    // ---- 4. saving clears the flag (create → redirect to edit page) ----
    await page.getByRole("button", { name: /Save plan/ }).click({ noWaitAfter: true });
    await page.waitForURL(/\/user\/marketing-plan\/\d+\/edit/, { timeout: 180_000 });
    await waitForApp(page);
    expect(await alpine<boolean>(page, "d.dirty")).toBe(false);

    // ---- 5. an assumption edit flips it again on the edit page ----
    const origBudget = await alpine<number>(page, "d.p.annual_budget");
    await budgetInput(page).fill(String(origBudget + 50_000));
    await budgetInput(page).blur();
    await expect.poll(() => alpine<boolean>(page, "d.dirty")).toBe(true);

    // ---- 6. in-place save (PUT, no redirect) clears it ----
    await page.getByRole("button", { name: /Save plan/ }).click();
    await expect(page.locator("p", { hasText: "Saved." })).toBeVisible({ timeout: 60_000 });
    expect(await alpine<boolean>(page, "d.dirty")).toBe(false);

    // ---- 7. dirty again; accepting both prompts lets navigation proceed ----
    await budgetInput(page).fill(String(origBudget + 75_000));
    await budgetInput(page).blur();
    await expect.poll(() => alpine<boolean>(page, "d.dirty")).toBe(true);
    dialogs.length = 0;
    await backLink(page).click({ noWaitAfter: true });
    await page.waitForURL(/\/user\/marketing-plan\/?$/, { timeout: 180_000 });
    // The in-app confirm always fires; Chromium only shows the beforeunload
    // prompt after a user gesture, so 1–2 accepted dialogs are both fine —
    // the point is that accepting them lets the navigation complete.
    expect(dialogs.length).toBeGreaterThanOrEqual(1);
    expect(dialogs[0]).toBe("confirm:accept");
  });
});
