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

// Shared logged-in context (demo-login is rate-limited at throttle:5,1).
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

const ALIAS = "e2e-slides-block-edit";

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
 * Seed the demo user plus a biolink with a heading block, a slide deck,
 * and one slide containing that block. Idempotent per run.
 */
function seedFixtures(): { linkId: number; blockId: number } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Models\\LinkSlide;
use App\\Modules\\User\\Models\\LinkSlideDeck;
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

$bio = Link::withoutGlobalScope('workspace')->where('alias', '${ALIAS}')->first();
if (!$bio) {
  $bio = Link::create([
    'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
    'alias' => '${ALIAS}', 'title' => 'E2E Slides Block Edit', 'is_active' => true,
  ]);
} else {
  $bio->user_id = $u->id; $bio->workspace_id = $ws?->id; $bio->save();
}

BiolinkBlock::where('link_id', $bio->id)->delete();
$a = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'heading', 'sort_order' => 0, 'is_active' => true, 'settings' => ['text' => 'Slides Heading Original']]);

$deck = LinkSlideDeck::where('link_id', $bio->id)->first();
if (!$deck) {
  $deck = LinkSlideDeck::create([
    'link_id' => $bio->id, 'workspace_id' => $bio->workspace_id, 'version' => 1,
    'is_published' => false,
    'settings' => ['theme' => ['background' => '#0f172a', 'accent' => '#3d6bff', 'text' => '#f8fafc'], 'transition' => 'slide', 'auto_advance' => 0, 'loop' => false],
  ]);
}
LinkSlide::where('deck_id', $deck->id)->delete();
LinkSlide::create([
  'deck_id' => $deck->id, 'sort_order' => 0, 'title' => 'Slide One',
  'block_ids' => [$a->id],
  'background' => ['type' => 'color', 'color' => '#0f172a'],
  'animation' => ['enter' => 'fade', 'duration_ms' => 400],
  'transition' => 'slide',
]);

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'blockId' => $a->id]);
`.trim();

  const out = runTinkerSeed(php);
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
});

async function gotoSlidesEditor(page: Page): Promise<void> {
  await page.goto(`/user/links/${ids.linkId}/slides`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".sl-block-chip", { timeout: 45_000 });
}

test.describe.configure({ mode: "serial" });

test("chip edit opens the shared block edit form in a modal", async ({
  page,
}) => {
  test.setTimeout(180_000);
  await gotoSlidesEditor(page);

  await expect(page.locator("#sl-edit-overlay")).toBeHidden();
  await page.click(".sl-block-chip .sl-chip-edit");
  await expect(page.locator("#sl-edit-overlay")).toBeVisible();

  // The AJAX editForm fetch lands the SAME shared form partial the Blocks
  // tab uses, with the block's current settings prefilled.
  const form = page.locator("#sl-edit-body form");
  await expect(form).toBeVisible({ timeout: 30_000 });
  const textInput = form
    .locator('input[name="settings[text]"], textarea[name="settings[text]"]')
    .first();
  await expect(textInput).toBeVisible();
  await expect(textInput).toHaveValue("Slides Heading Original");

  // Escape closes the modal without saving.
  await page.keyboard.press("Escape");
  await expect(page.locator("#sl-edit-overlay")).toBeHidden();
  await expect(page.locator("#sl-edit-body form")).toHaveCount(0);
});

test("dirty modal warns before discarding on Escape / backdrop close", async ({
  page,
}) => {
  test.setTimeout(180_000);
  await gotoSlidesEditor(page);

  await page.click(".sl-block-chip .sl-chip-edit");
  const form = page.locator("#sl-edit-body form");
  await expect(form).toBeVisible({ timeout: 30_000 });

  const textInput = form
    .locator('input[name="settings[text]"], textarea[name="settings[text]"]')
    .first();
  await textInput.fill("Slides Heading Dirty Draft");

  // Declining the confirm keeps the modal (and the draft) open.
  let confirmMessage = "";
  page.once("dialog", (d) => {
    confirmMessage = d.message();
    void d.dismiss();
  });
  await page.keyboard.press("Escape");
  await expect(page.locator("#sl-edit-overlay")).toBeVisible();
  await expect(textInput).toHaveValue("Slides Heading Dirty Draft");
  expect(confirmMessage).toContain("Discard unsaved changes?");

  // Accepting the confirm via a backdrop click discards and closes.
  page.once("dialog", (d) => void d.accept());
  await page
    .locator("#sl-edit-overlay")
    .click({ position: { x: 5, y: 5 } });
  await expect(page.locator("#sl-edit-overlay")).toBeHidden({
    timeout: 15_000,
  });
  await expect(page.locator("#sl-edit-body form")).toHaveCount(0);
});

test("saving from the modal persists, updates the chip and closes", async ({
  page,
}) => {
  test.setTimeout(180_000);
  await gotoSlidesEditor(page);

  await page.click(".sl-block-chip .sl-chip-edit");
  const form = page.locator("#sl-edit-body form");
  await expect(form).toBeVisible({ timeout: 30_000 });

  const textInput = form
    .locator('input[name="settings[text]"], textarea[name="settings[text]"]')
    .first();
  await textInput.fill("Slides Heading Edited");

  // Submit via requestSubmit to dodge animation actionability flake; the
  // form's onsubmit hands off to our ajaxSaveBlock (PUT via _method spoof).
  const saveResp = page.waitForResponse(
    (r) =>
      r.url().includes(`/blocks/${ids.blockId}`) &&
      r.request().method() === "POST" &&
      r.ok(),
    { timeout: 45_000 },
  );
  await form.evaluate((f: HTMLFormElement) => f.requestSubmit());
  await saveResp;

  // Modal closes and the chip label refreshes to the new heading text.
  await expect(page.locator("#sl-edit-overlay")).toBeHidden({
    timeout: 15_000,
  });
  await expect(page.locator(".sl-block-chip").first()).toContainText(
    "Slides Heading Edited",
    { timeout: 15_000 },
  );

  // Persisted server-side: reload shows the edited label on the chip.
  await gotoSlidesEditor(page);
  await expect(page.locator(".sl-block-chip").first()).toContainText(
    "Slides Heading Edited",
  );
});

test("typing in the modal live-updates the device preview; closing without saving reverts", async ({
  page,
}) => {
  test.setTimeout(180_000);
  await gotoSlidesEditor(page);

  // The device preview iframe hosts the slides public page in ?_preview
  // mode, which carries the shared 1inme-block-live listener.
  const preview = page.frameLocator(".preview-iframe").first();
  const previewBlock = preview.locator(`[data-block-id="${ids.blockId}"]`);
  await expect(previewBlock).toContainText("Slides Heading Edited", {
    timeout: 60_000,
  });

  await page.click(".sl-block-chip .sl-chip-edit");
  const form = page.locator("#sl-edit-body form");
  await expect(form).toBeVisible({ timeout: 30_000 });

  const textInput = form
    .locator('input[name="settings[text]"], textarea[name="settings[text]"]')
    .first();
  // Baseline is captured ~100ms after form injection; wait it out so the
  // first keystroke diffs against the server-rendered value.
  await page.waitForTimeout(400);
  await textInput.fill("Slides Heading LIVE TYPED");

  // The preview patches in place, without any save request.
  await expect(previewBlock).toContainText("Slides Heading LIVE TYPED", {
    timeout: 15_000,
  });

  // Close WITHOUT saving: accept the "Discard unsaved changes?" confirm;
  // the preview then reloads and reverts to the saved text.
  page.once("dialog", (d) => d.accept());
  await page.keyboard.press("Escape");
  await expect(page.locator("#sl-edit-overlay")).toBeHidden();
  await expect(
    page
      .frameLocator(".preview-iframe")
      .first()
      .locator(`[data-block-id="${ids.blockId}"]`),
  ).toContainText("Slides Heading Edited", { timeout: 60_000 });

  // And the unsaved text never persisted.
  await gotoSlidesEditor(page);
  await expect(page.locator(".sl-block-chip").first()).toContainText(
    "Slides Heading Edited",
  );
});
