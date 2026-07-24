import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
} from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * End-to-end coverage for the inline "Upload instead" action inside the
 * auto-sourced image preview panel on the web AI builder intake
 * (/user/links/{id}/ai-builder). The panel is only rendered while the
 * creator has NO uploads attached (x-show="images.length === 0"), and the
 * "Upload instead" button proxies a click to the hidden vault file input —
 * so an upload must win outright: the extracted/generated preview flow
 * disappears and the uploaded image lands in the uploads grid.
 *
 * This spec proves the Alpine wiring in a real browser:
 *
 *  - "Preview images" paints the extracted keep/remove thumbnails
 *  - "Upload instead" opens the SAME hidden file input as the dropzone
 *    (filechooser fires; multi-image input)
 *  - picking a file POSTs to the vault upload endpoint and the returned
 *    vault URL appears as a thumbnail in the uploads grid
 *  - the whole preview panel (extracted thumbs, "Preview images" control,
 *    upload-instead row) hides — uploads replace the auto-sourced flow
 *  - a Generate POST after uploading carries images=[vault url] and NO
 *    kept_images/skip_generated_slots keys (uploads win server-side too)
 *  - removing the upload brings the preview panel back
 *
 * The source-preview, files/upload and generate endpoints are stubbed with
 * page.route so no real extraction, storage write or coin charge happens.
 */

let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

// Per-run unique alias (parallel task envs share one RDS).
const ALIAS_PREFIX = "e2e-aiupl";
const RUN_SUFFIX = Date.now().toString(36);
const BIO_ALIAS = `${ALIAS_PREFIX}-bio-${RUN_SUFFIX}`;

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

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

/**
 * Seed: demo user + one biolink. The intake page only renders the builder
 * form when the AI Engine is enabled server-side, so flip it on for the run
 * (and report whether THIS run enabled it, so teardown restores exactly
 * that). Every AI-costing endpoint is stubbed in-browser.
 */
function seedFixtures(): { linkId: number; setEngine: boolean } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Services\\WorkspaceContext;
use App\\Services\\AI\\AiEngineSettings;
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

// Prune stale twins (created > 2h ago) sharing the alias prefix.
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}-%')
  ->where('created_at', '<', now()->subHours(2))
  ->get();
foreach ($stale as $s) {
  BiolinkBlock::where('link_id', $s->id)->delete();
  $s->delete();
}

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${BIO_ALIAS}', 'title' => 'E2E Upload Instead', 'is_active' => true,
]);

$setEngine = false;
if (!AiEngineSettings::isEnabled()) {
  AiEngineSettings::setEnabled(true);
  $setEngine = true;
}

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'setEngine' => $setEngine]);
`.trim();

  const out = runTinker(php);
  const m = out.match(/IDS=(\{.*\})/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return JSON.parse(m[1]);
}

let ids: ReturnType<typeof seedFixtures>;

test.beforeAll(async ({ browser }) => {
  ids = seedFixtures();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
  // Restore the AI Engine flag only if THIS run enabled it.
  if (ids?.setEngine) {
    runTinker(
      `
use App\\Services\\AI\\AiEngineSettings;
AiEngineSettings::setEnabled(false);
echo 'CLEANED';
`.trim(),
    );
  }
});

// Stubbed source-preview payload (shape mirrors BuilderImageSourcer::preview).
const EXTRACTED = [
  "https://images.example.com/site-a.jpg",
  "https://images.example.com/site-b.png",
];
const GENERATION = {
  enabled: true,
  slots: ["avatar", "cover"],
  cost_per_image: 2,
};
const SOURCE_LINK = "https://example.com/my-portfolio";
const DESCRIPTION =
  "I am a freelance photographer and want a portfolio page with a gallery.";
// Vault-relative URL, same shape user.files.upload returns after storing.
const UPLOADED_URL = "/f/e2e-upload-instead.png";
// Tiny valid 1x1 PNG so the filechooser hands the input a real image file.
const PNG_BYTES = Buffer.from(
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==",
  "base64",
);

test("upload instead replaces the extracted/generated preview flow", async ({
  page,
}) => {
  test.setTimeout(180_000);

  await page.route("**/ai-builder/source-preview", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ extracted: EXTRACTED, generation: GENERATION }),
    });
  });

  let uploadCount = 0;
  await page.route("**/user/files/upload", async (route) => {
    uploadCount += 1;
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        file: { url: UPLOADED_URL, name: "e2e-upload-instead.png" },
      }),
    });
  });

  const generateBodies: Array<Record<string, unknown>> = [];
  await page.route("**/ai-builder/generate", async (route) => {
    generateBodies.push(route.request().postDataJSON());
    // No redirect: keeps the page in place after the assertion.
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ redirect: null, message: "stubbed (e2e)" }),
    });
  });

  // Authenticated intake page; cold first paint over the distant RDS can be
  // slow — generous nav budget.
  await page.goto(`/user/links/${ids.linkId}/ai-builder`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });

  // Fill description (canSubmit gate) + one source link.
  await page
    .locator('textarea[maxlength="4000"]')
    .fill(DESCRIPTION, { timeout: 30_000 });
  await page.locator('input[type="url"]').first().fill(SOURCE_LINK);

  // Run the preview so the extracted thumbnails paint — this is the flow the
  // upload is about to replace.
  const previewBtn = page.getByRole("button", { name: /Preview images/i });
  await expect(previewBtn).toBeVisible({ timeout: 30_000 });
  const previewResponse = page.waitForResponse(
    (r) => r.url().includes("/ai-builder/source-preview"),
    { timeout: 30_000 },
  );
  await previewBtn.click();
  await previewResponse;

  const thumbA = page.locator(`button:has(img[src="${EXTRACTED[0]}"])`);
  const thumbB = page.locator(`button:has(img[src="${EXTRACTED[1]}"])`);
  await expect(thumbA).toBeVisible({ timeout: 15_000 });
  await expect(thumbB).toBeVisible();

  // The upload-instead row renders inside the preview panel.
  const uploadInsteadBtn = page.getByRole("button", {
    name: /Upload instead/i,
  });
  await expect(uploadInsteadBtn).toBeVisible();

  // Clicking "Upload instead" proxies to the hidden vault file input —
  // the native file chooser opens (multi-image input) and we hand it a PNG.
  const chooserPromise = page.waitForEvent("filechooser", {
    timeout: 15_000,
  });
  const uploadResponse = page.waitForResponse(
    (r) => r.url().includes("/user/files/upload"),
    { timeout: 30_000 },
  );
  await uploadInsteadBtn.click();
  const chooser = await chooserPromise;
  expect(chooser.isMultiple()).toBe(true);
  await chooser.setFiles({
    name: "e2e-upload-instead.png",
    mimeType: "image/png",
    buffer: PNG_BYTES,
  });
  await uploadResponse;
  expect(uploadCount).toBe(1);

  // The uploaded vault URL appears as a thumbnail in the uploads grid.
  await expect(page.locator(`img[src="${UPLOADED_URL}"]`)).toBeVisible({
    timeout: 15_000,
  });

  // Uploads win outright: the whole auto-sourced preview panel hides —
  // extracted thumbs, the "Preview images"/"Refresh preview" control and
  // the upload-instead row all disappear.
  await expect(thumbA).toBeHidden();
  await expect(thumbB).toBeHidden();
  await expect(
    page.getByRole("button", { name: /Refresh preview|Preview images/i }),
  ).toBeHidden();
  await expect(uploadInsteadBtn).toBeHidden();
  await expect(
    page.getByText(/uploads replace the extracted and generated images/i),
  ).toBeHidden();

  // Generate: the POST carries the uploaded image and NO preview keys
  // (kept_images / skip_generated_slots are only sent when images is empty).
  const gen = page.waitForResponse(
    (r) => r.url().includes("/ai-builder/generate"),
    { timeout: 30_000 },
  );
  await page.getByRole("button", { name: /Generate my page/i }).click();
  await gen;
  expect(generateBodies).toHaveLength(1);
  expect(generateBodies[0]).toMatchObject({
    description: DESCRIPTION,
    images: [UPLOADED_URL],
  });
  expect(generateBodies[0]).not.toHaveProperty("kept_images");
  expect(generateBodies[0]).not.toHaveProperty("skip_generated_slots");

  // Removing the upload brings the auto-sourced preview flow back.
  const uploadedThumb = page.locator(
    `div:has(> img[src="${UPLOADED_URL}"])`,
  );
  await uploadedThumb.hover();
  await uploadedThumb.locator("button").click();
  await expect(page.locator(`img[src="${UPLOADED_URL}"]`)).toBeHidden();
  await expect(thumbA).toBeVisible({ timeout: 15_000 });
  await expect(
    page.getByRole("button", { name: /Upload instead/i }),
  ).toBeVisible();
});

/**
 * Failure path: when /user/files/upload rejects (quota exceeded, oversized
 * file, …) the intake must not strand the creator. The inline uploadError
 * message renders, the auto-sourced preview panel stays visible (no upload
 * landed, so images.length is still 0), and the button flips back from
 * "Uploading…" to "Upload instead" (uploading flag reset in finally) so a
 * retry is possible — and the retry succeeds.
 */
test("failed upload shows the inline error, keeps the preview panel and allows a retry", async ({
  page,
}) => {
  test.setTimeout(180_000);

  await page.route("**/ai-builder/source-preview", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ extracted: EXTRACTED, generation: GENERATION }),
    });
  });

  const QUOTA_ERROR =
    "Storage quota exceeded. Delete some files or upgrade your plan.";
  let uploadCount = 0;
  await page.route("**/user/files/upload", async (route) => {
    uploadCount += 1;
    if (uploadCount === 1) {
      // First attempt fails the way FileController does: 422 envelope with
      // success:false and a human error message.
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({ success: false, error: QUOTA_ERROR }),
      });
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        file: { url: UPLOADED_URL, name: "e2e-upload-instead.png" },
      }),
    });
  });

  await page.goto(`/user/links/${ids.linkId}/ai-builder`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });

  await page
    .locator('textarea[maxlength="4000"]')
    .fill(DESCRIPTION, { timeout: 30_000 });
  await page.locator('input[type="url"]').first().fill(SOURCE_LINK);

  // Paint the extracted preview so we can prove the panel survives the
  // failed upload.
  const previewBtn = page.getByRole("button", { name: /Preview images/i });
  await expect(previewBtn).toBeVisible({ timeout: 30_000 });
  const previewResponse = page.waitForResponse(
    (r) => r.url().includes("/ai-builder/source-preview"),
    { timeout: 30_000 },
  );
  await previewBtn.click();
  await previewResponse;

  const thumbA = page.locator(`button:has(img[src="${EXTRACTED[0]}"])`);
  const thumbB = page.locator(`button:has(img[src="${EXTRACTED[1]}"])`);
  await expect(thumbA).toBeVisible({ timeout: 15_000 });
  await expect(thumbB).toBeVisible();

  const uploadInsteadBtn = page.getByRole("button", {
    name: /Upload instead/i,
  });
  await expect(uploadInsteadBtn).toBeVisible();

  // Attempt 1: the stub rejects with 422.
  const chooser1Promise = page.waitForEvent("filechooser", {
    timeout: 15_000,
  });
  const failedUpload = page.waitForResponse(
    (r) => r.url().includes("/user/files/upload"),
    { timeout: 30_000 },
  );
  await uploadInsteadBtn.click();
  const chooser1 = await chooser1Promise;
  await chooser1.setFiles({
    name: "e2e-upload-fail.png",
    mimeType: "image/png",
    buffer: PNG_BYTES,
  });
  await failedUpload;
  expect(uploadCount).toBe(1);

  // The server's error message renders inline.
  await expect(page.getByText(QUOTA_ERROR)).toBeVisible({ timeout: 15_000 });

  // Nothing landed in the uploads grid, so the auto-sourced preview panel
  // stays fully visible — the creator is not stranded.
  await expect(page.locator(`img[src="${UPLOADED_URL}"]`)).toHaveCount(0);
  await expect(thumbA).toBeVisible();
  await expect(thumbB).toBeVisible();

  // The uploading flag reset: the button reads "Upload instead" again (not
  // stuck on "Uploading…") and is clickable for a retry.
  await expect(uploadInsteadBtn).toBeVisible();
  await expect(uploadInsteadBtn).toContainText("Upload instead");
  await expect(uploadInsteadBtn).toBeEnabled();
  await expect(page.getByText("Uploading…")).toBeHidden();

  // Attempt 2: retry through the same button succeeds.
  const chooser2Promise = page.waitForEvent("filechooser", {
    timeout: 15_000,
  });
  const retryUpload = page.waitForResponse(
    (r) => r.url().includes("/user/files/upload"),
    { timeout: 30_000 },
  );
  await uploadInsteadBtn.click();
  const chooser2 = await chooser2Promise;
  await chooser2.setFiles({
    name: "e2e-upload-instead.png",
    mimeType: "image/png",
    buffer: PNG_BYTES,
  });
  await retryUpload;
  expect(uploadCount).toBe(2);

  // The retry wins: uploaded thumb shows, the error clears (handleFiles
  // resets uploadError at the top) and the preview panel hides as usual.
  await expect(page.locator(`img[src="${UPLOADED_URL}"]`)).toBeVisible({
    timeout: 15_000,
  });
  await expect(page.getByText(QUOTA_ERROR)).toBeHidden();
  await expect(thumbA).toBeHidden();
  await expect(thumbB).toBeHidden();
  await expect(uploadInsteadBtn).toBeHidden();
});
