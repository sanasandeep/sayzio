import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test as base, type BrowserContext, type Page } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * Task #6017 — browser-level guard for the Task #6015 curated background
 * gallery in the biolink appearance settings (biolink-background-card).
 *
 * The endpoint + save paths are covered by feature tests; this spec proves
 * the Alpine picker actually renders, selects, persists, and that the pick
 * reaches the public page:
 *   1. gallery toggle fetches /user/platform-assets/biolink-backgrounds and
 *      renders a tile per asset,
 *   2. clicking a tile fills the hidden `background_image_asset` input (and
 *      clicking again deselects),
 *   3. saving the appearance form stores the resolved public URL in
 *      settings.biolink.background_image with background_type=image,
 *   4. the public page inlines that URL in its background CSS,
 *   5. the dropzone-input "Stock" tab renders the curated grid too.
 *
 * The platform-assets responses are mocked via route interception (the
 * shared S3 folders may be empty in a dev env); the save path never does an
 * S3 round-trip (PlatformAssetCatalog::isValidKey is shape-only), so a
 * fabricated key persists end-to-end exactly like a real one.
 */

const RUN_ID = Date.now().toString(36);
const ALIAS_PREFIX = "e2e-bggal-";
const ALIAS = `${ALIAS_PREFIX}${RUN_ID}`;

const BG_KEY_A = `assets/biolink-backgrounds/e2e-sunset-beach-${RUN_ID}.jpg`;
const BG_KEY_B = `assets/biolink-backgrounds/e2e-city-night-${RUN_ID}.jpg`;

// 1x1 transparent PNG — tile <img> sources resolve instantly, no network.
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
let linkId = 0;

const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await mockPlatformAssets(page);
    await use(page);
    await page.close();
  },
});

test.describe.configure({ mode: "serial" });

/** Intercept every platform-assets folder listing with deterministic fixtures. */
async function mockPlatformAssets(page: Page) {
  await page.route("**/user/platform-assets/*", async (route) => {
    const url = route.request().url();
    const folder = url.split("/user/platform-assets/")[1]?.split("?")[0] ?? "";
    let assets: Array<{ key: string; name: string; label: string; url: string }> = [];
    if (folder === "biolink-backgrounds") {
      assets = [
        { key: BG_KEY_A, name: path.basename(BG_KEY_A), label: "Sunset Beach", url: PIXEL },
        { key: BG_KEY_B, name: path.basename(BG_KEY_B), label: "City Night", url: PIXEL },
      ];
    } else if (folder === "grid-images") {
      assets = [
        {
          key: `assets/grid-images/e2e-stock-photo-${RUN_ID}.jpg`,
          name: `e2e-stock-photo-${RUN_ID}.jpg`,
          label: "Stock Photo",
          url: PIXEL,
        },
      ];
    } else if (folder === "hand-drawn") {
      assets = [
        {
          key: `assets/hand-drawn/e2e-doodle-${RUN_ID}.png`,
          name: `e2e-doodle-${RUN_ID}.png`,
          label: "Doodle",
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
  // Seed the fixture biolink (owned by the demo-login account, or the owner
  // guard 403s the editor) and prune stale fixtures from earlier runs.
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Services\\WorkspaceContext;

Link::withoutGlobalScope('workspace')
    ->where('alias', 'like', '${ALIAS_PREFIX}%')
    ->where('created_at', '<', now()->subHours(2))
    ->delete();

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->firstOrFail();
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);
$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E BG Gallery', 'is_active' => true,
  'settings' => ['biolink' => ['background_type' => 'color', 'background_color' => '#0a0612']],
]);
echo 'LINK_ID=' . $bio->id . '=END';
`.trim();
  const out = runTinker(php);
  const m = out.match(/LINK_ID=(\d+)=END/);
  if (!m) throw new Error("Seed failed, tinker output:\n" + out);
  linkId = Number(m[1]);

  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
  if (linkId) {
    runTinker(
      `App\\Modules\\User\\Models\\Link::withoutGlobalScope('workspace')->where('id', ${linkId})->delete();`,
    );
  }
});

async function gotoAppearance(page: Page) {
  await page.goto(`/user/links/${linkId}/settings/appearance`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  await expect(page.locator('form[action*="page-settings"]')).toBeVisible({ timeout: 30_000 });
  // Alpine boots on DOMContentLoaded (deferred vendored script). Wait for it
  // explicitly — the type buttons are x-for-rendered and don't exist pre-boot.
  await page.waitForFunction(() => (window as unknown as { Alpine?: unknown }).Alpine !== undefined, undefined, {
    timeout: 60_000,
  });
}

test("gallery renders, selects, saves and reaches the public page", async ({ page }) => {
  // Cold-RDS editor page render + save POST + public-page render can each be
  // slow on a cold worker — this test does all three.
  test.setTimeout(240_000);
  await gotoAppearance(page);

  const form = page.locator('form[action*="page-settings"]');

  // Switch the background type to Image so the gallery section shows. The
  // label lives in a child span (x-text), so match it via :text-is rather
  // than the button's accessible name.
  await form.locator('button:has(span:text-is("Image"))').click();
  await expect(form.locator('input[name="background_type"]')).toHaveValue("image");

  // Open the gallery — the toggle triggers the (mocked) listing fetch.
  await form.locator("button", { hasText: "Or choose from our gallery" }).click();

  // Both mocked assets render as tiles.
  const tileA = form.locator('button[title="Sunset Beach"]');
  const tileB = form.locator('button[title="City Night"]');
  await expect(tileA).toBeVisible();
  await expect(tileB).toBeVisible();

  const hidden = form.locator('input[name="background_image_asset"]');

  // Select → hidden input carries the S3 key; the tile shows the ring.
  await tileA.click();
  await expect(hidden).toHaveValue(BG_KEY_A);
  await expect(tileA).toHaveClass(/ring-2/);

  // Click again → deselect; pick the other tile → value swaps.
  await tileA.click();
  await expect(hidden).toHaveValue("");
  await tileB.click();
  await expect(hidden).toHaveValue(BG_KEY_B);

  // Save. Cold RDS POSTs can be slow — arm waitForResponse before clicking.
  const saved = page.waitForResponse(
    (r) => r.url().includes(`/page-settings`) && r.request().method() === "POST",
    { timeout: 60_000 },
  );
  // The POST 302s back to the appearance page; wait for that follow-up GET
  // too, or the later public-page goto gets interrupted by the redirect
  // navigation (waitForURL is a no-op — the URL never changes).
  const redirected = page.waitForResponse(
    (r) => r.url().includes("/settings/appearance") && r.request().method() === "GET",
    { timeout: 120_000 },
  );
  await form.locator("#saveAppearanceBtn").click({ noWaitAfter: true });
  const resp = await saved;
  expect(resp.status(), "page-settings POST should redirect back").toBeLessThan(400);
  await redirected;
  // Let the redirect navigation fully commit + load before navigating away,
  // or the later goto races it and gets interrupted.
  await page.waitForLoadState("load", { timeout: 60_000 }).catch(() => {});

  // Server persisted the resolved public URL + image background type.
  const out = runTinker(
    `$l = App\\Modules\\User\\Models\\Link::withoutGlobalScope('workspace')->findOrFail(${linkId});` +
      `echo 'STATE=' . json_encode(['type' => $l->settings['biolink']['background_type'] ?? null, 'image' => $l->settings['biolink']['background_image'] ?? null]) . '=END';`,
  );
  const m = out.match(/STATE=(\{.*?\})=END/);
  expect(m, "tinker state output:\n" + out).toBeTruthy();
  const state = JSON.parse(m![1]) as { type: string | null; image: string | null };
  expect(state.type).toBe("image");
  expect(state.image ?? "").toContain(BG_KEY_B);

  // The public page inlines that URL into its background CSS. The save
  // redirect's navigation commit can race this goto and interrupt it —
  // retry on interruption.
  for (let attempt = 1; ; attempt++) {
    try {
      await page.goto(`/${ALIAS}`, { waitUntil: "domcontentloaded", timeout: 120_000 });
      break;
    } catch (err) {
      if (attempt >= 5 || !String(err).includes("interrupted by another navigation")) throw err;
      await page.waitForLoadState("load", { timeout: 30_000 }).catch(() => {});
      await page.waitForTimeout(1000);
    }
  }
  const html = await page.content();
  expect(html).toContain(BG_KEY_B);
});

test("dropzone Stock tab lists the curated grid", async ({ page }) => {
  test.setTimeout(120_000);
  await gotoAppearance(page);

  const form = page.locator('form[action*="page-settings"]');
  await form.locator('button:has(span:text-is("Image"))').click();

  // The background-image dropzone exposes the new Stock tab.
  const stockBtn = form.locator("button", { hasText: /Stock/ }).first();
  await expect(stockBtn).toBeVisible();
  await stockBtn.click();

  // Mocked grid-images asset renders; switching to Hand-drawn swaps the list.
  await expect(form.locator('button[title="Stock Photo"]')).toBeVisible();
  await form.locator("button", { hasText: /^Hand-drawn$/ }).click();
  await expect(form.locator('button[title="Doodle"]')).toBeVisible();
  await expect(form.locator('button[title="Stock Photo"]')).toBeHidden();
});
