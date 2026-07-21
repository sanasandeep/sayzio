import { expect, test as base, type BrowserContext, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Task #5537 — Profile Settings fixes:
//  1. The ISD (country-code) dropdown in the shared phone-input partial used
//     to be clipped by the wrapper's `overflow-hidden`. It must now be fully
//     visible when opened — in BOTH dark and light mode.
//  2. A WhatsApp add/change card now lives on the Profile Settings page.
//  3. The duplicated Public Profile editor (handle/bio/persona) is replaced
//     by a pointer card to the Creator Profile tab; the two visibility
//     checkboxes (discoverable / allow_followers) remain.

let sharedContext: BrowserContext;

const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

test.describe.configure({ mode: "serial" });

test.beforeAll(async ({ browser }) => {
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
});

async function gotoProfile(page: Page) {
  await page.goto("/user/settings/profile", {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  await expect(page.locator('form[action*="settings/profile"]').first()).toBeVisible({
    timeout: 30_000,
  });
}

/**
 * Open the ISD dropdown on the main profile form's phone input and assert the
 * country list is genuinely visible (not clipped to zero height by an
 * overflow-hidden ancestor).
 */
async function assertDropdownUsable(page: Page) {
  const wrap = page.locator('[id^="profile-phone-"][id$="-wrap"]').first();
  await expect(wrap).toBeVisible();

  const trigger = wrap.locator('button[aria-label="Select country code"]');
  await trigger.click();

  const listbox = wrap.locator('ul[role="listbox"]');
  await expect(listbox).toBeVisible();

  // The dropdown must actually render below the field with real height —
  // when clipped by overflow-hidden its visible box collapses.
  const box = await listbox.boundingBox();
  expect(box, "listbox should have a bounding box").toBeTruthy();
  expect(box!.height).toBeGreaterThan(100);

  // A country option must be clickable; picking it updates the dial code.
  const option = listbox.locator("li[role=option]", { hasText: "India" }).first();
  await option.scrollIntoViewIfNeeded();
  await option.click();
  await expect(trigger).toContainText("+91");

  // No ancestor between the listbox and the wrap should clip it: verify the
  // element at the listbox's former position is gone (dropdown closed) but,
  // more importantly, it WAS hit-testable — the successful click above proves
  // it was not covered/clipped.
  await expect(listbox).toBeHidden();
}

test("ISD dropdown opens fully in dark mode", async ({ page }) => {
  await gotoProfile(page);
  await assertDropdownUsable(page);
});

test("ISD dropdown opens fully in light mode", async ({ page }) => {
  await gotoProfile(page);
  await page.evaluate(() => document.documentElement.classList.add("light-mode"));
  await assertDropdownUsable(page);
});

test("WhatsApp card present with add/change flow", async ({ page }) => {
  await gotoProfile(page);

  const card = page
    .locator("div.glass", { has: page.locator("h2", { hasText: "WhatsApp number" }) })
    .first();
  await expect(card).toBeVisible();

  // Send form posts to the shared onboarding endpoint with the settings flag.
  const sendForm = card.locator('form[action*="whatsapp/send"]');
  await expect(sendForm).toHaveCount(1);
  await expect(sendForm.locator('input[name="from"]')).toHaveValue("settings");

  // The card embeds the shared phone-input partial (sm size).
  await expect(card.locator('[id^="wa-settings-"][id$="-wrap"]')).toBeVisible();
});

test("Public Profile card is a pointer; checkboxes remain; no duplicate editors", async ({
  page,
}) => {
  await gotoProfile(page);

  // Pointer link to the Creator Profile tab.
  await expect(
    page.locator('a[href*="settings/creator"]', { hasText: "Edit Creator Profile" }),
  ).toBeVisible();

  // The duplicated editors are gone from this page.
  await expect(page.locator('input[name="handle"]')).toHaveCount(0);
  await expect(page.locator('textarea[name="bio"]')).toHaveCount(0);
  await expect(page.locator('select[name="persona"]')).toHaveCount(0);

  // The two account-level toggles stay in the main form.
  await expect(page.locator('input[name="discoverable"]')).toHaveCount(1);
  await expect(page.locator('input[name="allow_followers"]')).toHaveCount(1);
});
