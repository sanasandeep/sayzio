import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Frame,
  type FrameLocator,
  type Page,
} from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * End-to-end verification of catalog preset block backgrounds (Task #6190):
 *
 * 1. Editor flow: pick a preset swatch on a heading's Look tab, drag the
 *    transparency slider, let the drawer autosave persist both — then load
 *    the PUBLIC page and assert the block's .block-bg-preset layer renders
 *    the catalog gradient at the chosen opacity.
 * 2. Card containers: a container-level preset layer AND a child block's
 *    preset layer both render on the public page at their stored opacities.
 * 3. Page-level: the appearance page's bg_preset_opacity slider live-updates
 *    the draft phone preview (no save), and the form save persists it.
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

// Per-run unique alias — the RDS is shared across parallel task environments.
const ALIAS = `e2e-blk-bge2e-${Date.now().toString(36)}${process.pid.toString(36)}`;

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
 * Seed the demo user plus one biolink holding:
 *  - a plain heading (no preset yet — the editor test picks one),
 *  - a card container with its own preset (opacity 25) wrapping a child
 *    heading with its own preset (opacity 55).
 * The page background is seeded to preset/gradient_zero at 100 so the
 * appearance-page slider test starts from a known state.
 */
function seedFixtures(): {
  linkId: number;
  headingId: number;
  cardId: number;
  childId: number;
} {
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
  ->where('alias', 'like', 'e2e-blk-bge2e-%')
  ->where('created_at', '<', now()->subHours(2))
  ->pluck('id');
if ($stale->isNotEmpty()) {
  BiolinkBlock::whereIn('link_id', $stale)->delete();
  Link::withoutGlobalScope('workspace')->whereIn('id', $stale)->delete();
}

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Block BG Preset E2E', 'is_active' => true,
  'settings' => ['biolink' => [
    'background_type' => 'preset',
    'bg_preset_key' => 'gradient_zero',
    'bg_preset_opacity' => 100,
  ]],
]);
$h = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'heading', 'sort_order' => 0, 'is_active' => true,
  'settings' => ['text' => 'Preset E2E Heading'],
]);
$card = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'card', 'sort_order' => 1, 'is_active' => true,
  'settings' => [
    'title' => 'Preset Card',
    '_style' => ['bg_preset_key' => 'gradient_zero', 'bg_preset_opacity' => 25],
  ],
]);
$child = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'heading', 'sort_order' => 0, 'is_active' => true,
  'parent_id' => $card->id,
  'settings' => [
    'text' => 'Child In Card',
    '_style' => ['bg_preset_key' => 'gradient_zero', 'bg_preset_opacity' => 55],
  ],
]);

echo 'IDS=' . json_encode([
  'linkId' => $bio->id, 'headingId' => $h->id,
  'cardId' => $card->id, 'childId' => $child->id,
]);
`.trim();

  const out = runTinker(php);
  const m = out.match(/IDS=(\{.*\})/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return JSON.parse(m[1]);
}

let ids: { linkId: number; headingId: number; cardId: number; childId: number };

test.beforeAll(async ({ browser }) => {
  test.setTimeout(240_000);
  ids = seedFixtures();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.waitForLoadState("load", { timeout: 90_000 }).catch(() => undefined);
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

test.describe.configure({ mode: "serial" });
test.setTimeout(180_000);

async function gotoEditorWithPreview(page: Page): Promise<FrameLocator> {
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".block-card", { timeout: 45_000 });
  const preview = page.frameLocator(".preview-iframe").first();
  await expect(preview.locator(`[data-block-id="${ids.headingId}"]`)).toBeVisible({
    timeout: 60_000,
  });
  return preview;
}

test("editor: pick a preset + set transparency on the Look tab, saved values render on the public page", async ({
  page,
}) => {
  await gotoEditorWithPreview(page);

  // Open the heading's edit drawer (retry — slow Alpine init over distant RDS).
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

  const styleRoot = form.locator("[data-style-root]");
  await styleRoot.locator('button:has-text("Block Styling")').click();
  await styleRoot.locator('button:has-text("Look")').click();
  // The preset picker lives behind the unified background mode chips.
  await styleRoot.getByRole("button", { name: "Preset", exact: true }).click();

  const swatch = styleRoot.locator('button[title="Gradient 1"]');
  await expect(swatch).toBeVisible({ timeout: 15_000 });

  // Pick the swatch; the debounced drawer autosave persists the key.
  const autosave1 = page.waitForResponse(
    (r) =>
      r.url().includes(`/blocks/${ids.headingId}`) &&
      r.request().method() === "POST" &&
      r.ok(),
    { timeout: 60_000 },
  );
  await swatch.click();
  await autosave1;

  // Drag the transparency slider to 40; a second autosave persists it.
  const slider = form.locator('input[name="style[bg_preset_opacity]"]');
  await expect(slider).toBeVisible({ timeout: 15_000 });
  const autosave2 = page.waitForResponse(
    (r) =>
      r.url().includes(`/blocks/${ids.headingId}`) &&
      r.request().method() === "POST" &&
      r.ok(),
    { timeout: 60_000 },
  );
  await slider.evaluate((el: HTMLInputElement) => {
    for (const v of ["80", "60", "40"]) {
      el.value = v;
      el.dispatchEvent(new Event("input", { bubbles: true }));
    }
  });
  await autosave2;

  // Both values are in the DB before we look at the public page.
  const saved = runTinker(
    `$s = App\\Modules\\User\\Models\\BiolinkBlock::find(${ids.headingId})->settings['_style'] ?? [];` +
      `echo 'SAVED=' . ($s['bg_preset_key'] ?? 'missing') . '/' . ($s['bg_preset_opacity'] ?? 'missing');`,
  );
  expect(saved).toContain("SAVED=gradient_zero/40");

  // Public page renders the block's preset layer at the chosen opacity.
  await page.goto(`/${ALIAS}`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  const layer = page.locator(
    `[data-block-id="${ids.headingId}"] .block-bg-preset`,
  );
  await expect(layer).toBeAttached({ timeout: 30_000 });
  expect(await layer.evaluate((el) => getComputedStyle(el).opacity)).toBe("0.4");
  expect(
    await layer.evaluate((el) => getComputedStyle(el).backgroundImage),
  ).toContain("linear-gradient");
});

test("public page: card container + child block preset layers render at their stored opacities", async ({
  page,
}) => {
  await page.goto(`/${ALIAS}`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });

  const container = page.locator(".card-container-render").first();
  await expect(container).toBeAttached({ timeout: 30_000 });

  // Container-level layer: a direct child of the card container div.
  const containerLayer = container.locator("> .block-bg-preset");
  await expect(containerLayer).toBeAttached({ timeout: 15_000 });
  expect(
    await containerLayer.evaluate((el) => getComputedStyle(el).opacity),
  ).toBe("0.25");
  expect(
    await containerLayer.evaluate((el) => getComputedStyle(el).backgroundImage),
  ).toContain("linear-gradient");

  // Child-level layer: inside the child's styled wrap within the grid.
  const childLayer = container.locator(".block-styled .block-bg-preset");
  await expect(childLayer).toBeAttached({ timeout: 15_000 });
  expect(
    await childLayer.evaluate((el) => getComputedStyle(el).opacity),
  ).toBe("0.55");
  // The child heading text confirms we grabbed the right cell.
  await expect(container).toContainText("Child In Card");
});

function findPreviewFrame(page: Page): Frame | null {
  for (const frame of page.frames()) {
    if (frame.url().includes(ALIAS)) return frame;
  }
  return null;
}

test("appearance page: bg_preset_opacity slider live-previews in the draft iframe and persists on save", async ({
  page,
}) => {
  const linkId = ids.linkId;
  await page.goto(`/user/links/${linkId}/settings/appearance`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });

  // Wait for the phone preview iframe to load the signed public page.
  await expect
    .poll(() => findPreviewFrame(page) !== null, { timeout: 60_000 })
    .toBe(true);

  // The page background is seeded as preset — the Presets section (and the
  // transparency slider) shows once we switch to the Presets type tab.
  const presetsTab = page.locator('button:has-text("Presets")').first();
  await presetsTab.waitFor({ state: "visible", timeout: 30_000 });
  await presetsTab.click();

  const slider = page.locator('input[name="bg_preset_opacity"]').first();
  await slider.waitFor({ state: "visible", timeout: 30_000 });

  // Register the response listener BEFORE the drag so the debounced draft
  // push (500ms) can never race past us.
  const draftPush = page.waitForResponse(
    (r) =>
      r.url().includes("/preview-draft") && r.request().method() === "POST",
    { timeout: 30_000 },
  );
  await slider.evaluate((el: HTMLInputElement) => {
    for (const v of ["70", "50", "30"]) {
      el.value = v;
      el.dispatchEvent(new Event("input", { bubbles: true }));
    }
  });
  const resp = await draftPush;
  expect(resp.ok()).toBe(true);

  // The draft iframe reloads and renders the page preset layer faded to 0.3.
  await expect
    .poll(
      async () => {
        const frame = findPreviewFrame(page);
        if (!frame || !frame.url().includes("_draft=1")) return "no-draft";
        try {
          return await frame.evaluate(() => {
            const layer = document.querySelector(".bg-page-fixed");
            return layer ? getComputedStyle(layer).opacity : "missing-layer";
          });
        } catch {
          return "navigating";
        }
      },
      { timeout: 45_000 },
    )
    .toBe("0.3");

  // Unsaved-preview badge should be visible.
  await expect(page.locator("#draftPreviewBadge")).toBeVisible();

  // Save the page-settings form; the slider value persists.
  const saveResp = page.waitForResponse(
    (r) =>
      r.url().includes("/page-settings") && r.request().method() === "POST",
    { timeout: 90_000 },
  );
  await page
    .locator('form[action*="page-settings"] button[type="submit"]')
    .first()
    .click({ noWaitAfter: true });
  await saveResp;
  // Let the redirect land before reading the DB (slow-write RDS race).
  await page
    .waitForLoadState("domcontentloaded", { timeout: 60_000 })
    .catch(() => undefined);

  const saved = runTinker(
    `$b = App\\Modules\\User\\Models\\Link::withoutGlobalScope('workspace')->find(${linkId})->settings['biolink'] ?? [];` +
      `echo 'PAGE=' . ($b['bg_preset_key'] ?? 'missing') . '/' . ($b['bg_preset_opacity'] ?? 'missing');`,
  );
  expect(saved).toContain("PAGE=gradient_zero/30");
});
