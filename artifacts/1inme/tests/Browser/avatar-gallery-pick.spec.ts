import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test as base, type BrowserContext, type Page } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * Task #6017 — browser-level guard for the Task #6015 curated avatar
 * gallery (user/partials/avatar-gallery-picker) on the Profile Settings
 * page, plus a presence check on the Creator Profile page.
 *
 * Feature tests cover the endpoint + ProfileController save path; this
 * spec proves the Alpine picker itself works in a real browser:
 *   1. toggling the picker fetches all three avatar folders and renders
 *      the "Photos" tab tiles,
 *   2. the tab pills switch the visible folder,
 *   3. clicking a tile fills the hidden `avatar_asset` input (click again
 *      deselects),
 *   4. submitting the profile form stores the resolved public URL on
 *      users.avatar,
 *   5. the Creator Profile editor embeds the same picker wired to the
 *      `creator_avatar_asset` input.
 *
 * The platform-assets responses are mocked via route interception (S3 may
 * be empty in dev); the save path validates keys by shape only, so a
 * fabricated key persists exactly like a real one. The demo account's
 * original avatar is restored in afterAll.
 */

const RUN_ID = Date.now().toString(36);
const AVA_PHOTO_KEY = `assets/people-avatars/e2e-portrait-${RUN_ID}.jpg`;
const AVA_ILLU_KEY = `assets/stock-avatars/e2e-robot-${RUN_ID}.png`;

// 1x1 transparent PNG so tile <img> sources resolve instantly.
const PIXEL =
  "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==";

const ARTIFACT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

function runTinker(php: string): string {
  let lastErr: unknown;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
        cwd: ARTIFACT_ROOT,
        encoding: "utf8",
      });
    } catch (err) {
      lastErr = err;
    }
  }
  throw lastErr;
}

let sharedContext: BrowserContext;
let originalAvatarB64 = "";

const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await mockPlatformAssets(page);
    await use(page);
    await page.close();
  },
});

test.describe.configure({ mode: "serial" });

async function mockPlatformAssets(page: Page) {
  await page.route("**/user/platform-assets/*", async (route) => {
    const url = route.request().url();
    const folder = url.split("/user/platform-assets/")[1]?.split("?")[0] ?? "";
    let assets: Array<{ key: string; name: string; label: string; url: string }> = [];
    if (folder === "people-avatars") {
      assets = [
        { key: AVA_PHOTO_KEY, name: path.basename(AVA_PHOTO_KEY), label: "Portrait One", url: PIXEL },
      ];
    } else if (folder === "stock-avatars") {
      assets = [
        { key: AVA_ILLU_KEY, name: path.basename(AVA_ILLU_KEY), label: "Robot One", url: PIXEL },
      ];
    } else if (folder === "hand-drawn") {
      assets = [
        {
          key: `assets/hand-drawn/e2e-sketch-${RUN_ID}.png`,
          name: `e2e-sketch-${RUN_ID}.png`,
          label: "Sketch One",
          url: PIXEL,
        },
      ];
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ success: true, folder, assets }),
    });
  });
}

test.beforeAll(async ({ browser }) => {
  // Snapshot the demo account's avatar so the spec can restore it — this is
  // the shared demo login account, not a throwaway fixture.
  const out = runTinker(
    `$u = App\\Modules\\User\\Models\\User::where('email', '${DEMO_LOGIN_EMAIL}')->firstOrFail();` +
      `echo 'AVATAR=' . base64_encode((string) $u->avatar) . '=END';`,
  );
  const m = out.match(/AVATAR=([A-Za-z0-9+/=]*)=END/);
  if (!m) throw new Error("Avatar snapshot failed, tinker output:\n" + out);
  originalAvatarB64 = m[1];

  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
  // Restore the demo account's original avatar (may be empty = null).
  runTinker(
    `$u = App\\Modules\\User\\Models\\User::where('email', '${DEMO_LOGIN_EMAIL}')->firstOrFail();` +
      `$a = base64_decode('${originalAvatarB64}');` +
      `$u->avatar = $a === '' ? null : $a; $u->save(); echo 'RESTORED';`,
  );
});

test("avatar gallery renders, tab-switches, selects and saves", async ({ page }) => {
  // Cold-RDS settings render + profile save POST can be slow on a cold worker.
  test.setTimeout(240_000);
  await page.goto("/user/settings/profile", {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  const form = page.locator('form[action*="settings/profile"]').first();
  await expect(form).toBeVisible({ timeout: 30_000 });

  // Open the picker — the toggle fetches all three folders (mocked).
  await form.locator("button", { hasText: "Or pick from our avatar gallery" }).click();

  // Default tab is Photos (people-avatars).
  const photoTile = form.locator('button[title="Portrait One"]');
  await expect(photoTile).toBeVisible();

  // Tab pills switch the visible folder.
  await form.locator("button", { hasText: /^Illustrated$/ }).click();
  const illuTile = form.locator('button[title="Robot One"]');
  await expect(illuTile).toBeVisible();
  await expect(photoTile).toBeHidden();

  await form.locator("button", { hasText: /^Hand-drawn$/ }).click();
  await expect(form.locator('button[title="Sketch One"]')).toBeVisible();
  await form.locator("button", { hasText: /^Illustrated$/ }).click();

  // Select → hidden input carries the key; click again → deselect.
  const hidden = form.locator('input[name="avatar_asset"]');
  await illuTile.click();
  await expect(hidden).toHaveValue(AVA_ILLU_KEY);
  await expect(illuTile).toHaveClass(/ring-2/);
  await illuTile.click();
  await expect(hidden).toHaveValue("");
  await illuTile.click();
  await expect(hidden).toHaveValue(AVA_ILLU_KEY);

  // Submit the profile form. Cold RDS POSTs are slow — arm the response
  // wait first, submit without navigation auto-wait.
  const saved = page.waitForResponse(
    (r) => r.url().includes("/user/settings/profile") && r.request().method() === "POST",
    { timeout: 60_000 },
  );
  await form.locator('button[type="submit"]', { hasText: /Save/ }).first().click({ noWaitAfter: true });
  const resp = await saved;
  expect(resp.status(), "profile POST should redirect back").toBeLessThan(400);

  // The server resolved the key to a public URL and stored it on the user.
  const out = runTinker(
    `$u = App\\Modules\\User\\Models\\User::where('email', '${DEMO_LOGIN_EMAIL}')->firstOrFail();` +
      `echo 'AVATAR_NOW=' . json_encode($u->avatar) . '=END';`,
  );
  const m = out.match(/AVATAR_NOW=(".*?"|null)=END/);
  expect(m, "tinker output:\n" + out).toBeTruthy();
  const avatarNow = JSON.parse(m![1]) as string | null;
  expect(avatarNow ?? "").toContain(AVA_ILLU_KEY);
});

test("creator profile page embeds the picker on creator_avatar_asset", async ({ page }) => {
  test.setTimeout(120_000);
  await page.goto("/user/settings/creator", {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });

  const toggle = page.locator("button", { hasText: "Or pick from our avatar gallery" }).first();
  await expect(toggle).toBeVisible({ timeout: 30_000 });
  await expect(page.locator('input[name="creator_avatar_asset"]')).toHaveCount(1);

  // Open it and confirm the mocked tiles render here too.
  await toggle.click();
  await expect(page.locator('button[title="Portrait One"]').first()).toBeVisible();
});
