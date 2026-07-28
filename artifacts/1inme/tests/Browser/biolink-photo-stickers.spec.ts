import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Page,
} from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * E2E for the image-block custom sticker overlays (Task #5939 UI wiring):
 *  - upload a PNG sticker in the style drawer → sticker row appears
 *  - drawer autosave persists it → the sticker <img> renders on the public page
 *  - "Pick from vault" grid lists the user's vault images and adds stickers
 *  - adding a 5th sticker surfaces the cap error message
 *
 * Server-side sanitizer coverage lives in
 * tests/Feature/BiolinkPhotoStickersTest.php; this spec covers the Alpine
 * fetch/upload wiring, hidden-input sync event and the cap-4 guard.
 */

// Shared logged-in context (demo-login is rate-limited at throttle:5,1).
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

// Per-run unique alias (shared-RDS envs run this spec in parallel); stale
// fixtures from previous runs are pruned by prefix in the seed.
const ALIAS_PREFIX = "e2e-stkr-";
const ALIAS = ALIAS_PREFIX + Date.now().toString(36);

// 1x1 transparent PNG for the upload path.
const PNG_BUFFER = Buffer.from(
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==",
  "base64",
);

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

function runTinkerSeed(php: string): string {
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

/**
 * Seed the demo user, a biolink with a single image block (no stickers),
 * and three clean vault image files for the picker grid.
 */
function seedFixtures(): { linkId: number; blockId: number } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Models\\UserFile;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => '${DEMO_LOGIN_EMAIL}',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
$rid = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
if ($rid) { $u->roles()->syncWithoutDetaching([$rid]); $u->flushPermissionCache(); }
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

// Prune stale fixtures from previous runs (shared RDS across task envs).
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}%')->where('user_id', $u->id)->get();
foreach ($stale as $sl) { BiolinkBlock::where('link_id', $sl->id)->delete(); $sl->delete(); }
UserFile::where('user_id', $u->id)->where('original_name', 'like', 'e2e-stkr-%')->delete();

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Stickers', 'is_active' => true,
]);
$blk = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'image', 'sort_order' => 0, 'is_active' => true,
  'settings' => ['url' => 'https://placehold.co/400x300.png', 'alt' => 'Hero'],
]);

// Three clean vault images for the "Pick from vault" grid. The stored path
// doesn't need to exist on disk: the picker/public render only read DB rows.
for ($i = 1; $i <= 3; $i++) {
  UserFile::create([
    'user_id' => $u->id,
    'original_name' => 'e2e-stkr-vault-' . $i . '.png',
    'filename' => 'e2e-stkr-vault-' . $i . '-' . $bio->id . '.png',
    'mime_type' => 'image/png', 'size_bytes' => 120, 'type' => 'image',
    'disk' => 'user_files', 'path' => 'e2e/e2e-stkr-vault-' . $i . '.png',
    'scan_status' => 'clean',
  ]);
}

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'blockId' => $blk->id]);
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/IDS=(\{.*\})/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return JSON.parse(m[1]);
}

let ids: { linkId: number; blockId: number };

test.beforeAll(async ({ browser }) => {
  ids = seedFixtures();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
});

const STICKER_FILE_INPUT =
  'input[type="file"][accept="image/png,image/webp,image/svg+xml,image/gif,image/jpeg"]';

async function openStickerSection(page: Page) {
  // The editor is a heavy Blade render over the distant RDS; DOMContentLoaded
  // + an explicit block-card wait is the signal we actually need.
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".block-card", { timeout: 45_000 });

  await page.click(`[data-block-id="${ids.blockId}"] .edit-btn`);
  const form = page.locator(
    `[data-inline-editor-body="${ids.blockId}"] form`,
  );
  await expect(form).toBeVisible({ timeout: 30_000 });

  // Expand the collapsed "Image Styling" section that hosts the sticker UI.
  await form.getByRole("button", { name: /Image Styling/ }).click();
  await expect(form.getByText("Custom Stickers")).toBeVisible();
  return form;
}

function stickerRows(form: ReturnType<Page["locator"]>) {
  return form.locator('button[title="Remove sticker"]');
}

test.describe.configure({ mode: "serial" });

test("upload sticker → autosave → renders on the public page", async ({
  page,
}) => {
  test.setTimeout(240_000);
  const form = await openStickerSection(page);

  const uploadResp = page.waitForResponse(
    (r) => r.url().includes("/user/files/upload") && r.ok(),
    { timeout: 60_000 },
  );
  // The file input's own change event fires an immediate autosave BEFORE the
  // upload response lands, so the first POST carries no stickers. Wait for
  // the save whose body actually contains the synced sticker payload.
  const saveResp = page.waitForResponse(
    (r) =>
      r.url().includes(`/blocks/${ids.blockId}`) &&
      r.request().method() === "POST" &&
      r.ok() &&
      (r.request().postData() ?? "").includes("file_id"),
    { timeout: 60_000 },
  );

  await form.locator(STICKER_FILE_INPUT).setInputFiles({
    name: "e2e-stkr-upload.png",
    mimeType: "image/png",
    buffer: PNG_BUFFER,
  });
  await uploadResp;

  // A sticker row appeared with the position select + remove button.
  await expect(stickerRows(form)).toHaveCount(1, { timeout: 15_000 });
  await expect(
    form.locator("select option", { hasText: "Top right" }).first(),
  ).toBeAttached();

  // The hidden-input sync event triggered the debounced drawer autosave.
  await saveResp;

  // The saved sticker renders on the public page as an absolute overlay
  // <img> inside the decorated photo hero, sourced from the vault route.
  await page.goto(`/${ALIAS}`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  const hero = page.locator("[data-photo-hero]");
  await expect(hero).toBeVisible({ timeout: 30_000 });
  const stickerImg = hero.locator('img[class*="pointer-events-none"]');
  await expect(stickerImg).toHaveCount(1);
  await expect(stickerImg).toHaveAttribute("src", /\/f\/\d+\//);
});

test("vault picker adds stickers and the 5th hits the cap error", async ({
  page,
}) => {
  test.setTimeout(240_000);
  const form = await openStickerSection(page);

  // One sticker persisted by the previous test.
  await expect(stickerRows(form)).toHaveCount(1, { timeout: 15_000 });

  const pickBtn = form.getByRole("button", { name: /Pick from vault/ });
  const vaultGrid = form.locator(".max-h-44");

  // Add stickers 2..4 from the vault grid (each add closes the picker).
  for (let n = 2; n <= 4; n++) {
    await pickBtn.click();
    const thumbs = vaultGrid.locator("button[title]");
    // The grid lists the seeded vault images (plus the uploaded one).
    await expect(thumbs.first()).toBeVisible({ timeout: 30_000 });
    expect(await thumbs.count()).toBeGreaterThanOrEqual(3);
    await thumbs.nth(n - 2).click();
    await expect(stickerRows(form)).toHaveCount(n);
    await expect(vaultGrid).toBeHidden();
  }

  // At the cap the add controls hide…
  await expect(pickBtn).toBeHidden();
  // …but a change event on the (DOM-present) file input still routes through
  // the guard, which must surface the cap error instead of uploading.
  await form.locator(STICKER_FILE_INPUT).setInputFiles({
    name: "e2e-stkr-fifth.png",
    mimeType: "image/png",
    buffer: PNG_BUFFER,
  });
  await expect(form.getByText("Sticker limit reached (4 max).")).toBeVisible({
    timeout: 15_000,
  });
  await expect(stickerRows(form)).toHaveCount(4);
});
