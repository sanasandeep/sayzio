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
const ALIAS = `e2e-blk-ctr-${Date.now().toString(36)}${process.pid.toString(36)}`;

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
 * Seed the demo user plus a biolink with a heading block that already stores
 * a LOW-contrast pair (light gray text on white) so the warning must appear
 * as soon as the style drawer opens.
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
  ->where('alias', 'like', 'e2e-blk-ctr-%')
  ->where('created_at', '<', now()->subHours(2))
  ->pluck('id');
if ($stale->isNotEmpty()) {
  BiolinkBlock::whereIn('link_id', $stale)->delete();
  Link::withoutGlobalScope('workspace')->whereIn('id', $stale)->delete();
}

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Block Contrast Warning', 'is_active' => true,
]);
$h = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'heading', 'sort_order' => 0, 'is_active' => true,
  'settings' => [
    'text' => 'Contrast Warning Fixture',
    '_style' => ['text_color' => '#cccccc', 'bg_color' => '#ffffff'],
  ],
]);
$cta = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'cta_button', 'sort_order' => 1, 'is_active' => true,
  'settings' => [
    'text' => 'Accent Contrast Fixture', 'url' => 'https://example.com',
    'color' => '#ffffff', 'text_color' => '#cccccc',
  ],
]);

// Block with NO background: inherits the page backdrop. The page has no
// saved background_type, so the effective backdrop is the default dark
// #0a0612 — dark text on it must warn (Task #6052).
$h2 = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'heading', 'sort_order' => 1, 'is_active' => true,
  'settings' => [
    'text' => 'Inherited Backdrop Fixture',
    '_style' => ['text_color' => '#333333'],
  ],
]);

// Second biolink with a GRADIENT page background: unresolvable backdrop,
// so an inherit-bg block must stay silent (no false positives).
$bio2 = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}-g', 'title' => 'E2E Gradient Backdrop', 'is_active' => true,
  'settings' => ['biolink' => ['background_type' => 'gradient']],
]);
$h3 = BiolinkBlock::create([
  'link_id' => $bio2->id, 'type' => 'heading', 'sort_order' => 0, 'is_active' => true,
  'settings' => [
    'text' => 'Gradient Backdrop Fixture',
    '_style' => ['text_color' => '#333333'],
  ],
]);

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'headingId' => $h->id, 'ctaId' => $cta->id, 'inheritId' => $h2->id, 'gradientLinkId' => $bio2->id, 'gradientHeadingId' => $h3->id]);
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/IDS=(\{.*\})/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return JSON.parse(m[1]);
}

let ids: {
  linkId: number;
  headingId: number;
  ctaId: number;
  inheritId: number;
  gradientLinkId: number;
  gradientHeadingId: number;
};

/** Open a block's edit drawer and expand the Block Styling panel. */
async function openStylePanel(
  page: import("@playwright/test").Page,
  linkId: number,
  blockId: number,
) {
  await page.goto(`/user/links/${linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".block-card", { timeout: 45_000 });
  const form = page.locator(`[data-inline-editor-body="${blockId}"] form`);
  for (let attempt = 1; attempt <= 3; attempt++) {
    await page.click(`[data-block-id="${blockId}"] .edit-btn`);
    try {
      await expect(form).toBeVisible({ timeout: 20_000 });
      break;
    } catch (err) {
      if (attempt === 3) throw err;
    }
  }
  await page.waitForTimeout(400);
  const styleRoot = form.locator("[data-style-root]");
  await styleRoot.locator('button:has-text("Block Styling")').click();
  return { form, styleRoot };
}

test.beforeAll(async ({ browser }) => {
  test.setTimeout(240_000);
  ids = seedFixtures();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page
    .waitForLoadState("load", { timeout: 90_000 })
    .catch(() => undefined);
  await page.close();
});

test.afterAll(async () => {
  try { await sharedContext?.close(); } catch {}
});

test.describe.configure({ mode: "serial" });
test.setTimeout(180_000);

test("style drawer shows a non-blocking contrast warning that clears when colors are fixed", async ({
  page,
}) => {
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".block-card", { timeout: 45_000 });

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
  await page.waitForTimeout(400);

  // Expand the style panel and go to the Text tab.
  const styleRoot = form.locator("[data-style-root]");
  await styleRoot.locator('button:has-text("Block Styling")').click();
  await styleRoot.locator('button:has-text("Text")').first().click();

  // Seeded #cccccc on #ffffff is ~1.6:1 → warning visible with the ratio.
  const textWarning = styleRoot.locator(
    '[data-testid="block-contrast-warning-text"]',
  );
  await expect(textWarning).toBeVisible({ timeout: 15_000 });
  await expect(textWarning).toContainText("Low contrast (1.6:1)");

  // The Look tab shows the mirrored warning under Background Color.
  await styleRoot.locator('button:has-text("Look")').click();
  const bgWarning = styleRoot.locator(
    '[data-testid="block-contrast-warning-bg"]',
  );
  await expect(bgWarning).toBeVisible({ timeout: 15_000 });

  // Fixing the text color to a dark value clears both warnings live.
  await styleRoot.locator('button:has-text("Text")').first().click();
  const textInput = form.locator('input[name="style[text_color]"]');
  await textInput.fill("#111111");
  await expect(textWarning).toBeHidden({ timeout: 10_000 });

  // Warning re-appears when a low-contrast color is typed again — and it
  // never blocks saving: the debounced autosave POST still succeeds.
  const autosave = page.waitForResponse(
    (r) =>
      r.url().includes(`/blocks/${ids.headingId}`) &&
      r.request().method() === "POST" &&
      r.ok(),
    { timeout: 60_000 },
  );
  await textInput.fill("#dddddd");
  await expect(textWarning).toBeVisible({ timeout: 10_000 });
  await autosave;

  // The low-contrast value saved fine (non-blocking).
  const saved = runTinkerSeed(
    `echo 'TC=' . (App\\Modules\\User\\Models\\BiolinkBlock::find(${ids.headingId})->settings['_style']['text_color'] ?? 'missing');`,
  );
  expect(saved).toContain("TC=#dddddd");
});

test("cta button drawer warns on low-contrast accent pair without blocking saves", async ({
  page,
}) => {
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".block-card", { timeout: 45_000 });

  const form = page.locator(`[data-inline-editor-body="${ids.ctaId}"] form`);
  for (let attempt = 1; attempt <= 3; attempt++) {
    await page.click(`[data-block-id="${ids.ctaId}"] .edit-btn`);
    try {
      await expect(form).toBeVisible({ timeout: 20_000 });
      break;
    } catch (err) {
      if (attempt === 3) throw err;
    }
  }
  await page.waitForTimeout(400);

  // Seeded #cccccc button text on #ffffff button color is ~1.6:1 → the
  // accent-pair warning is visible with the computed ratio.
  const accentWarning = form.locator(
    '[data-testid="block-contrast-warning-accent"]',
  );
  await expect(accentWarning).toBeVisible({ timeout: 15_000 });
  await expect(accentWarning).toContainText("Low contrast (1.6:1)");

  // Fixing the button text color to a dark value clears the warning live.
  const textColor = form.locator('input[name="settings[text_color]"]');
  await textColor.fill("#111111");
  await expect(accentWarning).toBeHidden({ timeout: 10_000 });

  // Warning re-appears with a low-contrast pick — and never blocks the
  // debounced autosave POST.
  const autosave = page.waitForResponse(
    (r) =>
      r.url().includes(`/blocks/${ids.ctaId}`) &&
      r.request().method() === "POST" &&
      r.ok(),
    { timeout: 60_000 },
  );
  await textColor.fill("#dddddd");
  await expect(accentWarning).toBeVisible({ timeout: 10_000 });
  await autosave;

  const saved = runTinkerSeed(
    `echo 'TC=' . (App\\Modules\\User\\Models\\BiolinkBlock::find(${ids.ctaId})->settings['text_color'] ?? 'missing');`,
  );
  expect(saved).toContain("TC=#dddddd");
});

test("empty block bg falls back to the resolved page backdrop (default dark page warns on dark text)", async ({
  page,
}) => {
  const { form, styleRoot } = await openStylePanel(
    page,
    ids.linkId,
    ids.inheritId,
  );
  await styleRoot.locator('button:has-text("Text")').first().click();

  // Seeded #333333 text, NO block bg → compared against the page's effective
  // dark backdrop (#0a0612) → low contrast, and the copy names the page bg.
  const textWarning = styleRoot.locator(
    '[data-testid="block-contrast-warning-text"]',
  );
  await expect(textWarning).toBeVisible({ timeout: 15_000 });
  await expect(textWarning).toContainText("against the page background");

  // The Look tab mirrors the warning; an explicit light block background
  // clears it (the explicit value wins over the page fallback), and
  // clearing it re-inherits the dark page backdrop.
  await styleRoot.locator('button:has-text("Look")').click();
  // The block has no bg → the Background mode starts on "None"; the color
  // input (and its warning) lives in Color mode, so switch modes first.
  await styleRoot.locator('button:text-is("Color")').click();
  const bgWarning = styleRoot.locator(
    '[data-testid="block-contrast-warning-bg"]',
  );
  await expect(bgWarning).toBeVisible({ timeout: 15_000 });
  const bgInput = form.locator('input[name="style[bg_color]"]');
  await bgInput.fill("#ffffff");
  await expect(bgWarning).toBeHidden({ timeout: 10_000 });
  await bgInput.fill("");
  await expect(bgWarning).toBeVisible({ timeout: 10_000 });
});

test("gradient page background stays silent for inherit-bg blocks (no false positives)", async ({
  page,
}) => {
  const { styleRoot } = await openStylePanel(
    page,
    ids.gradientLinkId,
    ids.gradientHeadingId,
  );
  await styleRoot.locator('button:has-text("Text")').first().click();

  // Same dark #333333 text, but the page background is a gradient — the
  // backdrop is unresolvable so the warning must NOT appear.
  const textWarning = styleRoot.locator(
    '[data-testid="block-contrast-warning-text"]',
  );
  await page.waitForTimeout(1_500);
  await expect(textWarning).toBeHidden();
});
