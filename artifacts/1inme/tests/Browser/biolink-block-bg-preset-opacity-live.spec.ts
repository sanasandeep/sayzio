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

// Per-run unique alias — the RDS is shared across parallel task environments.
const ALIAS = `e2e-blk-bgop-${Date.now().toString(36)}${process.pid.toString(36)}`;

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
 * Seed the demo user plus a biolink with a heading block that already has a
 * catalog preset background (gradient_zero at 100% opacity) so the preview
 * page renders a .block-bg-preset layer for the live handler to fade.
 */
function seedFixtures(): { linkId: number; headingId: number } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => '${DEMO_LOGIN_EMAIL}',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

// Prune stale fixtures from previous runs (>2h so a concurrent run in
// another environment is never torn down mid-run).
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', 'e2e-blk-bgop-%')
  ->where('created_at', '<', now()->subHours(2))
  ->pluck('id');
if ($stale->isNotEmpty()) {
  BiolinkBlock::whereIn('link_id', $stale)->delete();
  Link::withoutGlobalScope('workspace')->whereIn('id', $stale)->delete();
}

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Block BG Opacity Live', 'is_active' => true,
]);
$h = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'heading', 'sort_order' => 0, 'is_active' => true,
  'settings' => [
    'text' => 'Preset Fade Live',
    '_style' => ['bg_preset_key' => 'gradient_zero', 'bg_preset_opacity' => 100],
  ],
]);

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'headingId' => $h->id]);
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/IDS=(\{.*\})/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return JSON.parse(m[1]);
}

let ids: { linkId: number; headingId: number };

test.beforeAll(async ({ browser }) => {
  test.setTimeout(240_000);
  ids = seedFixtures();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page
    .waitForLoadState("load", { timeout: 90_000 })
    .catch(() => undefined);
  // Warm the public biolink page once (cold render over distant RDS is slow).
  await page.goto(`/${ALIAS}`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.close();
});

test.afterAll(async () => {
  try { await sharedContext?.close(); } catch {}
});

async function gotoEditorWithPreview(
  page: Page,
): Promise<{ preview: FrameLocator }> {
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".block-card", { timeout: 45_000 });

  const preview = page.frameLocator(".preview-iframe").first();
  await expect(
    preview.locator(`[data-block-id="${ids.headingId}"]`),
  ).toBeVisible({ timeout: 60_000 });

  // Reload detection: stamp the CURRENT iframe document; a full reload
  // produces a fresh document without the stamp.
  await preview
    .locator("body")
    .evaluate((b) => b.setAttribute("data-e2e-live-marker", "1"));

  return { preview };
}

test.describe.configure({ mode: "serial" });
test.setTimeout(180_000);

test("dragging the preset transparency slider fades the block layer live without a reload", async ({
  page,
}) => {
  const { preview } = await gotoEditorWithPreview(page);

  // The preset layer starts fully opaque.
  const layer = preview.locator(
    `[data-block-id="${ids.headingId}"] .block-bg-preset`,
  );
  await expect(layer).toBeAttached({ timeout: 20_000 });
  expect(
    await layer.evaluate((el) => getComputedStyle(el).opacity),
  ).toBe("1");

  // Open the edit drawer (retry — slow Alpine init over distant RDS).
  const form = page.locator(`[data-inline-editor-body="${ids.headingId}"] form`);
  for (let attempt = 1; attempt <= 3; attempt++) {
    await page.click(`[data-block-id="${ids.headingId}"] .edit-btn`);
    try {
      await expect(form).toBeVisible({ timeout: 20_000 });
      break;
    } catch (err) {
      if (attempt === 3) throw err;
    }
  }
  // Let _initDrawerAutoSave's 100ms baseline capture run.
  await page.waitForTimeout(400);

  // Style panel is collapsed behind "Block Styling"; the preset slider lives
  // in the Look tab.
  const styleRoot = form.locator("[data-style-root]");
  await styleRoot.locator('button:has-text("Block Styling")').click();
  await styleRoot.locator('button:has-text("Look")').click();

  const slider = form.locator('input[name="style[bg_preset_opacity]"]');
  await expect(slider).toBeVisible({ timeout: 15_000 });

  // Arm the autosave wait BEFORE the drag so the debounced POST can't race.
  const autosave = page.waitForResponse(
    (r) =>
      r.url().includes(`/blocks/${ids.headingId}`) &&
      r.request().method() === "POST" &&
      r.ok(),
    { timeout: 60_000 },
  );

  // Simulate a drag: several intermediate input events, like a real thumb
  // drag, ending at 30%.
  await slider.evaluate((el: HTMLInputElement) => {
    for (const v of ["80", "60", "45", "30"]) {
      el.value = v;
      el.dispatchEvent(new Event("input", { bubbles: true }));
    }
  });

  // The layer fades to 0.3 via the live channel — before/without any save
  // round-trip completing.
  await expect
    .poll(
      () => layer.evaluate((el) => getComputedStyle(el).opacity),
      { timeout: 10_000 },
    )
    .toBe("0.3");

  // No reload happened: the document stamp survived.
  await expect(
    preview.locator('body[data-e2e-live-marker="1"]'),
  ).toBeVisible();

  // Debounced autosave still persists the dragged value.
  await autosave;
  const saved = runTinkerSeed(
    `echo 'OP=' . (App\\Modules\\User\\Models\\BiolinkBlock::find(${ids.headingId})->settings['_style']['bg_preset_opacity'] ?? 'missing');`,
  );
  expect(saved).toContain("OP=30");
});
