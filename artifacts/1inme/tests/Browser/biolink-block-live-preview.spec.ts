import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type FrameLocator,
  type Page,
} from "@playwright/test";

// Shared logged-in context (demo-login is rate-limited at throttle:5,1).
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

// Per-run unique alias. The RDS is SHARED across parallel task environments,
// and a fixed alias meant two environments running this suite concurrently
// seeded/deleted the SAME link row — the preview iframe then rendered the
// other run's block ids and every wait timed out. A unique alias per worker
// process (retries spawn a fresh worker, re-evaluating this module) gives
// each run its own isolated fixture link. Old fixtures are pruned in the
// seeder.
const ALIAS = `e2e-block-live-${Date.now().toString(36)}${process.pid.toString(36)}`;

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
 * Seed the demo user plus a biolink covering the live-preview block families:
 * heading (baseline), badge, video, product, countdown (fallback case), a
 * card container and the repeater family (socials rows, progress rows, list
 * items). Idempotent: prior blocks on the fixture biolink are wiped.
 */
function seedFixtures(): {
  linkId: number;
  headingId: number;
  badgeId: number;
  videoId: number;
  productId: number;
  countdownId: number;
  cardId: number;
  socialsId: number;
  socialsMultiId: number;
  progressId: number;
  listId: number;
} {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

$u = User::where('email', 'demo@1inme.com')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => 'demo@1inme.com',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
$rid = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
if ($rid) { $u->roles()->syncWithoutDetaching([$rid]); $u->flushPermissionCache(); }
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

// Prune fixture links left behind by previous runs (unique per-run aliases
// accumulate otherwise). Only prune STALE ones (>2h) so a concurrently
// running suite in another environment is never torn down mid-run.
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', 'e2e-block-live-%')
  ->where('created_at', '<', now()->subHours(2))
  ->pluck('id');
if ($stale->isNotEmpty()) {
  BiolinkBlock::whereIn('link_id', $stale)->delete();
  Link::withoutGlobalScope('workspace')->whereIn('id', $stale)->delete();
}

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Block Live', 'is_active' => true,
]);
$h = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'heading', 'sort_order' => 0, 'is_active' => true, 'settings' => ['text' => 'Live Hello']]);
$badge = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'badge', 'sort_order' => 1, 'is_active' => true, 'settings' => ['text' => 'Fresh Badge']]);
$video = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'video', 'sort_order' => 2, 'is_active' => true, 'settings' => ['url' => 'https://cdn.example.com/one.mp4']]);
$product = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'product', 'sort_order' => 3, 'is_active' => true, 'settings' => ['name' => 'Prod One', 'price' => '19', 'description' => 'Desc one', 'url' => 'https://example.com/buy']]);
$cd = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'countdown', 'sort_order' => 4, 'is_active' => true, 'settings' => ['title' => 'Launch', 'target_date' => now()->addDays(7)->format('Y-m-d\\\\TH:i')]]);
$card = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'card', 'sort_order' => 5, 'is_active' => true, 'settings' => ['title' => 'Card Title']]);
$soc = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'socials', 'sort_order' => 6, 'is_active' => true, 'settings' => ['platforms' => [['name' => 'twitter', 'url' => 'https://twitter.com/before'], ['name' => 'github', 'url' => 'https://github.com/before']]]]);
$socMulti = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'socials_multi', 'sort_order' => 7, 'is_active' => true, 'settings' => ['groups' => [['name' => 'Work', 'platforms' => [['name' => 'twitter', 'url' => 'https://twitter.com/work-before'], ['name' => 'github', 'url' => 'https://github.com/work-before']]], ['name' => 'Fun', 'platforms' => [['name' => 'instagram', 'url' => 'https://instagram.com/fun-before']]]]]]);
$prog = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'progress', 'sort_order' => 8, 'is_active' => true, 'settings' => ['items' => [['label' => 'Skill A', 'value' => 40], ['label' => 'Skill B', 'value' => 60]]]]);
$list = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'list', 'sort_order' => 9, 'is_active' => true, 'settings' => ['items' => ['First item', 'Second item']]]);

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'headingId' => $h->id, 'badgeId' => $badge->id, 'videoId' => $video->id, 'productId' => $product->id, 'countdownId' => $cd->id, 'cardId' => $card->id, 'socialsId' => $soc->id, 'socialsMultiId' => $socMulti->id, 'progressId' => $prog->id, 'listId' => $list->id]);
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/IDS=(\{.*\})/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return JSON.parse(m[1]);
}

async function loginAsDemo(page: Page): Promise<void> {
  await page.goto("/user/login");
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().endsWith("/user/demo-login") &&
        r.request().method() === "POST",
      { timeout: 90_000 },
    ),
    page.evaluate(() => {
      const form = document.querySelector<HTMLFormElement>(
        'form[action$="/user/demo-login"]',
      );
      if (!form) throw new Error("demo-login form not found");
      form.submit();
    }),
  ]);
  // The POST response can arrive before the resulting navigation commits;
  // navigating away immediately then races ("interrupted by another
  // navigation to /user/demo-login"). Let the post-login page settle first.
  await page
    .waitForLoadState("load", { timeout: 90_000 })
    .catch(() => undefined);
}

let ids: ReturnType<typeof seedFixtures>;

test.beforeAll(async ({ browser }) => {
  // Seed (tinker over distant RDS), demo-login and the public-page warm-up
  // together can exceed the default 60s hook budget on a cold environment.
  test.setTimeout(240_000);
  ids = seedFixtures();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  // Warm the public biolink page once: a cold render over the distant RDS can
  // take 30s+, which otherwise eats the preview-iframe wait budget in test 1.
  await page.goto(`/${ALIAS}`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
});

function bodySel(id: number): string {
  return `[data-inline-editor-body="${id}"]`;
}
function editBtnSel(id: number): string {
  return `[data-block-id="${id}"] .edit-btn`;
}

/**
 * Open the editor, wait for the phone preview iframe to fully render the
 * public page, and install a reload counter on that iframe element so tests
 * can assert "patched WITHOUT a reload" vs "fell back to a reload".
 */
async function gotoEditorWithPreview(
  page: Page,
): Promise<{ preview: FrameLocator }> {
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".block-card", { timeout: 45_000 });

  const preview = page.frameLocator(".preview-iframe").first();
  // Wait for the public page to have rendered our blocks inside the iframe.
  await expect(
    preview.locator(`[data-block-id="${ids.headingId}"]`),
  ).toBeVisible({ timeout: 60_000 });

  // Reload detection: stamp the CURRENT iframe document. Any full reload
  // produces a fresh document without the stamp, so "stamp still present"
  // proves the live channel patched in place with no round-trip refresh.
  // (Counting `load` events is unreliable here: the hidden tablet/desktop
  // device iframes lazy-load and fire loads unrelated to the phone preview.)
  await preview
    .locator("body")
    .evaluate((b) => b.setAttribute("data-e2e-live-marker", "1"));

  return { preview };
}

async function expectNoReload(preview: FrameLocator): Promise<void> {
  await expect(
    preview.locator('body[data-e2e-live-marker="1"]'),
  ).toBeVisible();
}

async function openDrawer(page: Page, blockId: number) {
  const form = page.locator(`${bodySel(blockId)} form`);
  // The edit-button click occasionally doesn't open the drawer within the
  // wait budget (slow Alpine init / distant-RDS lag). Because this suite is
  // serial, a single flaky failure retries ALL tests (~doubling the run), so
  // retry the click here instead of failing the first pass.
  for (let attempt = 1; attempt <= 3; attempt++) {
    await page.click(editBtnSel(blockId));
    try {
      await expect(form).toBeVisible({ timeout: 20_000 });
      break;
    } catch (err) {
      if (attempt === 3) throw err;
    }
  }
  // Give _initDrawerAutoSave's 100ms baseline capture a moment to run.
  await page.waitForTimeout(400);
  return form;
}

async function waitForAutosave(page: Page, blockId: number) {
  await page.waitForResponse(
    (r) =>
      r.url().includes(`/blocks/${blockId}`) &&
      r.request().method() === "POST" &&
      r.ok(),
    { timeout: 60_000 },
  );
}

test.describe.configure({ mode: "serial" });

// Editor render + preview-iframe public render both cross the distant RDS;
// a cold first test can legitimately take >60s end-to-end.
test.setTimeout(180_000);

test("heading text patches the preview instantly without a reload", async ({
  page,
}) => {
  const { preview } = await gotoEditorWithPreview(page);
  const form = await openDrawer(page, ids.headingId);

  await form.locator('input[name="settings[text]"]').fill("Live Patched H");

  // The DOM patch is applied by postMessage before any save round-trip.
  await expect(
    preview.locator(`[data-block-id="${ids.headingId}"]`),
  ).toContainText("Live Patched H", { timeout: 10_000 });
  await expectNoReload(preview);

  // After the debounced autosave lands the editor must SKIP the reload
  // because every changed field was acked as handled.
  await waitForAutosave(page, ids.headingId);
  await page.waitForTimeout(2_000);
  await expectNoReload(preview);
  await expect(
    preview.locator(`[data-block-id="${ids.headingId}"]`),
  ).toContainText("Live Patched H");
});

test("badge text patches live without a reload", async ({ page }) => {
  const { preview } = await gotoEditorWithPreview(page);
  const form = await openDrawer(page, ids.badgeId);

  await form.locator('input[name="settings[text]"]').fill("Hot Badge");

  await expect(
    preview.locator(`[data-block-id="${ids.badgeId}"]`),
  ).toContainText("Hot Badge", { timeout: 10_000 });
  await waitForAutosave(page, ids.badgeId);
  await page.waitForTimeout(2_000);
  await expectNoReload(preview);
});

test("video URL swap patches the <video> source live", async ({ page }) => {
  const { preview } = await gotoEditorWithPreview(page);
  const form = await openDrawer(page, ids.videoId);

  // The video URL field is the file-upload widget: the NAMED input is hidden
  // (Alpine x-model), the user-visible field is a plain type="url" input.
  await form
    .locator('input[type="url"]')
    .first()
    .fill("https://cdn.example.com/two.mp4");

  const vid = preview.locator(
    `[data-block-id="${ids.videoId}"] video, [data-block-id="${ids.videoId}"] video source`,
  );
  await expect(async () => {
    const srcs = await vid.evaluateAll((els) =>
      els.map((el) => el.getAttribute("src") || ""),
    );
    expect(srcs.join(" ")).toContain("two.mp4");
  }).toPass({ timeout: 10_000 });

  await waitForAutosave(page, ids.videoId);
  await page.waitForTimeout(2_000);
  await expectNoReload(preview);
});

test("product name and price patch live without a reload", async ({
  page,
}) => {
  const { preview } = await gotoEditorWithPreview(page);
  const form = await openDrawer(page, ids.productId);

  await form.locator('input[name="settings[name]"]').fill("Prod Two");
  await form.locator('input[name="settings[price]"]').fill("29");

  const root = preview.locator(`[data-block-id="${ids.productId}"]`);
  await expect(root).toContainText("Prod Two", { timeout: 10_000 });
  await expect(root).toContainText("29", { timeout: 10_000 });

  await waitForAutosave(page, ids.productId);
  await page.waitForTimeout(2_000);
  await expectNoReload(preview);
});

test("card container title patches live without a reload", async ({
  page,
}) => {
  const { preview } = await gotoEditorWithPreview(page);
  const form = await openDrawer(page, ids.cardId);

  await form.locator('input[name="settings[title]"]').fill("New Card Title");

  await expect(
    preview.locator(`[data-block-id="${ids.cardId}"]`),
  ).toContainText("New Card Title", { timeout: 10_000 });

  await waitForAutosave(page, ids.cardId);
  await page.waitForTimeout(2_000);
  await expectNoReload(preview);
});

test("socials row URL patches the anchor live without a reload", async ({
  page,
}) => {
  const { preview } = await gotoEditorWithPreview(page);
  const form = await openDrawer(page, ids.socialsId);

  await form
    .locator('input[name="settings[platforms][1][url]"]')
    .fill("https://github.com/after-live");

  // Live hrefs route through the click tracker (?to=...), so assert the
  // second anchor's href now carries the new destination either way.
  const anchors = preview.locator(`[data-block-id="${ids.socialsId}"] a`);
  await expect(async () => {
    const href = (await anchors.nth(1).getAttribute("href")) || "";
    expect(decodeURIComponent(href)).toContain("github.com/after-live");
  }).toPass({ timeout: 10_000 });
  await expectNoReload(preview);

  await waitForAutosave(page, ids.socialsId);
  await page.waitForTimeout(2_000);
  await expectNoReload(preview);
});

test("grouped socials URL patches the flat anchor live without a reload", async ({
  page,
}) => {
  const { preview } = await gotoEditorWithPreview(page);
  const form = await openDrawer(page, ids.socialsMultiId);

  // Arm the autosave listener BEFORE typing: the debounced POST fires ~800ms
  // after the last keystroke and can otherwise land while the live-patch
  // assertion below is still polling, making waitForAutosave miss it.
  const savePromise = page.waitForResponse(
    (r) =>
      r.url().includes(`/blocks/${ids.socialsMultiId}`) &&
      r.request().method() === "POST" &&
      r.ok(),
    { timeout: 60_000 },
  );

  // Edit the SECOND group's only entry: the public markup flattens both
  // groups into one anchor list, so this is the third (index 2) anchor.
  await form
    .locator('input[name="settings[groups][1][platforms][0][url]"]')
    .fill("https://instagram.com/fun-after-live");

  const anchors = preview.locator(`[data-block-id="${ids.socialsMultiId}"] a`);
  await expect(async () => {
    const href = (await anchors.nth(2).getAttribute("href")) || "";
    expect(decodeURIComponent(href)).toContain("instagram.com/fun-after-live");
  }).toPass({ timeout: 10_000 });
  await expectNoReload(preview);

  await savePromise;
  await page.waitForTimeout(2_000);
  await expectNoReload(preview);
});

test("progress row label and value patch live without a reload", async ({
  page,
}) => {
  const { preview } = await gotoEditorWithPreview(page);
  const form = await openDrawer(page, ids.progressId);

  await form.locator('input[name="settings[items][0][label]"]').fill("Skill Z");
  await form.locator('input[name="settings[items][0][value]"]').fill("85");

  const root = preview.locator(`[data-block-id="${ids.progressId}"]`);
  await expect(root).toContainText("Skill Z", { timeout: 10_000 });
  await expect(root).toContainText("85%", { timeout: 10_000 });
  // The bar width must have been patched in place too.
  await expect(async () => {
    const widths = await root
      .locator("div div")
      .evaluateAll((els) =>
        els.map((el) => (el as HTMLElement).style.width || ""),
      );
    expect(widths.join(" ")).toContain("85%");
  }).toPass({ timeout: 10_000 });
  await expectNoReload(preview);

  await waitForAutosave(page, ids.progressId);
  await page.waitForTimeout(2_000);
  await expectNoReload(preview);
});

test("list item text patches live without a reload", async ({ page }) => {
  const { preview } = await gotoEditorWithPreview(page);
  const form = await openDrawer(page, ids.listId);

  await form
    .locator('input[name="settings[items][1][text]"]')
    .fill("Second item edited live");

  await expect(
    preview.locator(`[data-block-id="${ids.listId}"] li`).nth(1),
  ).toContainText("Second item edited live", { timeout: 10_000 });
  await expectNoReload(preview);

  await waitForAutosave(page, ids.listId);
  await page.waitForTimeout(2_000);
  await expectNoReload(preview);
});

test("unsupported field (countdown target_date) falls back to a reload", async ({
  page,
}) => {
  const { preview } = await gotoEditorWithPreview(page);
  const form = await openDrawer(page, ids.countdownId);

  // target_date has NO live handler: the preview must ack handled=false so
  // the editor keeps the safe save-then-reload path. A reload replaces the
  // iframe document, so the stamp we planted must disappear.
  await form
    .locator('input[name="settings[target_date]"]')
    .fill("2030-01-01T12:00");

  await waitForAutosave(page, ids.countdownId);
  await expect(
    preview.locator('body[data-e2e-live-marker="1"]'),
  ).toHaveCount(0, { timeout: 20_000 });
});
