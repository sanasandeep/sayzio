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
 * End-to-end coverage for the auto-sourced image preview step on the web
 * AI builder intake (/user/links/{id}/ai-builder). Feature tests cover the
 * BuilderImageSourcer service + controller; this spec proves the Alpine
 * wiring in a real browser:
 *
 *  - "Preview images" POSTs the entered links to the source-preview endpoint
 *  - extracted candidates paint as keep/remove thumbnails (all kept)
 *  - deselecting one drops it from kept_images on the Generate POST
 *  - deselecting ALL reveals the generation-slot chips (avatar/cover);
 *    unticking one sends it in skip_generated_slots on the Generate POST
 *
 * The source-preview + generate endpoints are stubbed with page.route so no
 * real extraction/OpenAI/coin charge happens; the point is that the exact
 * image choices the creator made reach the POST payloads.
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
const ALIAS_PREFIX = "e2e-aisrc";
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
 * that). The engine flag only gates the page render — every AI-costing
 * endpoint is stubbed in-browser.
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
  'alias' => '${BIO_ALIAS}', 'title' => 'E2E Source Preview', 'is_active' => true,
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
  "https://images.example.com/site-c.webp",
];
const GENERATION = { enabled: true, slots: ["avatar", "cover"], cost_per_image: 2 };
const SOURCE_LINK = "https://example.com/my-portfolio";
const DESCRIPTION =
  "I am a freelance photographer and want a portfolio page with a gallery.";

test("preview, deselect and skip choices reach the generate POST", async ({
  page,
}) => {
  test.setTimeout(180_000);

  let previewBody: unknown = null;
  await page.route("**/ai-builder/source-preview", async (route) => {
    previewBody = route.request().postDataJSON();
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ extracted: EXTRACTED, generation: GENERATION }),
    });
  });

  const generateBodies: Array<Record<string, unknown>> = [];
  await page.route("**/ai-builder/generate", async (route) => {
    generateBodies.push(route.request().postDataJSON());
    // No redirect: keeps the page in place so a second Generate can run.
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

  // Run the preview: entered links reach the source-preview POST.
  const previewBtn = page.getByRole("button", { name: /Preview images/i });
  await expect(previewBtn).toBeVisible({ timeout: 30_000 });
  const previewResponse = page.waitForResponse(
    (r) => r.url().includes("/ai-builder/source-preview"),
    { timeout: 30_000 },
  );
  await previewBtn.click();
  await previewResponse;
  expect(previewBody).toMatchObject({ links: [SOURCE_LINK] });

  // Extracted candidates painted, all kept (blue ring, check icon).
  const thumbA = page.locator(`button:has(img[src="${EXTRACTED[0]}"])`);
  const thumbB = page.locator(`button:has(img[src="${EXTRACTED[1]}"])`);
  const thumbC = page.locator(`button:has(img[src="${EXTRACTED[2]}"])`);
  await expect(thumbA).toBeVisible({ timeout: 15_000 });
  await expect(thumbB).toBeVisible();
  await expect(thumbC).toBeVisible();
  await expect(thumbA.locator(".fa-check")).toBeVisible();

  // The button label flips to "Refresh preview" once previewed.
  await expect(
    page.getByRole("button", { name: /Refresh preview/i }),
  ).toBeVisible();

  // Deselect the middle candidate — it greys out (fa-times state).
  await thumbB.click();
  await expect(thumbB.locator(".fa-times")).toBeVisible();
  // Generation chips stay hidden while at least one image is kept.
  await expect(page.getByRole("button", { name: "avatar" })).toBeHidden();

  // Generate #1: kept_images excludes the deselected URL; no skipped slots.
  const gen1 = page.waitForResponse(
    (r) => r.url().includes("/ai-builder/generate"),
    { timeout: 30_000 },
  );
  await page.getByRole("button", { name: /Generate my page/i }).click();
  await gen1;
  expect(generateBodies).toHaveLength(1);
  expect(generateBodies[0]).toMatchObject({
    description: DESCRIPTION,
    links: [SOURCE_LINK],
    kept_images: [EXTRACTED[0], EXTRACTED[2]],
    skip_generated_slots: [],
  });

  // Deselect the rest → the generation fallback chips appear (both ticked).
  await thumbA.click();
  await thumbC.click();
  const avatarChip = page.getByRole("button", { name: "avatar" });
  const coverChip = page.getByRole("button", { name: "cover" });
  await expect(avatarChip).toBeVisible({ timeout: 15_000 });
  await expect(coverChip).toBeVisible();
  await expect(avatarChip.locator(".fa-check")).toBeVisible();
  await expect(coverChip.locator(".fa-check")).toBeVisible();

  // Untick the cover slot.
  await coverChip.click();
  await expect(coverChip.locator(".fa-times")).toBeVisible();

  // Generate #2: nothing kept, cover slot skipped.
  const gen2 = page.waitForResponse(
    (r) => r.url().includes("/ai-builder/generate"),
    { timeout: 30_000 },
  );
  await page.getByRole("button", { name: /Generate my page/i }).click();
  await gen2;
  expect(generateBodies).toHaveLength(2);
  expect(generateBodies[1]).toMatchObject({
    kept_images: [],
    skip_generated_slots: ["cover"],
  });
});
