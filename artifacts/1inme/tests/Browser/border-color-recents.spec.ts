import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Locator,
  type Page,
} from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

// Recent border colors on the web biolink editor (Task #6102, mirroring the
// mobile editor's Task #6094 behavior): custom hex colors committed in the
// Borders controls are remembered in localStorage (last 5) and rendered as
// quick-pick swatches after the fixed presets, deduped against them.

let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

// Per-run unique alias — the RDS is shared across parallel task environments.
const ALIAS = `e2e-bd-recents-${Date.now().toString(36)}${process.pid.toString(36)}`;

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

const PRESETS = [
  "#ffffff",
  "#0f172a",
  "#7d9bff",
  "#f59e0b",
  "#ef4444",
  "#10b981",
  "#ec4899",
  "#8b5cf6",
];
const STORAGE_KEY = "biolink.editor.recentBorderColors";

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

function seedFixtures(): { linkId: number; headingId: number } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\BiolinkBlock;

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->firstOrFail();

// Prune STALE fixtures from prior runs (unique-alias pattern; only >2h old
// so a concurrent run in another environment is never torn down mid-run).
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', 'e2e-bd-recents-%')
  ->where('created_at', '<', now()->subHours(2))
  ->pluck('id');
if ($stale->isNotEmpty()) {
  BiolinkBlock::whereIn('link_id', $stale)->delete();
  Link::withoutGlobalScope('workspace')->whereIn('id', $stale)->delete();
}

$bio = Link::create(['user_id' => $u->id, 'type' => 'biolink', 'alias' => '${ALIAS}', 'title' => 'E2E Border Recents', 'is_active' => true]);
$h = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'heading', 'sort_order' => 1, 'is_active' => true, 'settings' => ['text' => 'Recents Fixture', '_style' => ['border_style' => 'solid', 'border_width' => 2, 'border_color' => '#ff0000']]]);

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
  await page.close();
});

test.afterAll(async () => {
  try {
    await sharedContext?.close();
  } catch {}
});

/**
 * Open the block editor, open the fixture heading's drawer, and navigate to
 * Block Styling → Look → More options where the Borders controls live.
 * Returns the drawer form plus the shorthand border-color field wrapper.
 */
async function openBordersControls(
  page: Page,
): Promise<{ form: Locator; field: Locator }> {
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".block-card", { timeout: 45_000 });

  const form = page.locator(
    `[data-inline-editor-body="${ids.headingId}"] form`,
  );
  for (let attempt = 1; attempt <= 3; attempt++) {
    await page.click(`[data-block-id="${ids.headingId}"] .edit-btn`);
    try {
      await expect(form).toBeVisible({ timeout: 20_000 });
      break;
    } catch (err) {
      if (attempt === 3) throw err;
    }
  }

  const styleRoot = form.locator("[data-style-root]");
  await styleRoot.locator('button:has-text("Block Styling")').click();
  await styleRoot.locator('button:has-text("Look")').click();
  await styleRoot.locator('button:has-text("More options")').click();

  // The shorthand border color field is the wrapper that owns the hidden
  // style[border_color] input.
  const field = styleRoot
    .locator("div[x-data='borderColorField()']")
    .filter({ has: page.locator('input[name="style[border_color]"]') })
    .first();
  await expect(field).toBeVisible({ timeout: 15_000 });
  return { form, field };
}

function swatchRow(field: Locator): Locator {
  return field.locator("[data-border-color-swatches]").first();
}

test("committed custom colors become recent swatches, deduped and persisted", async ({
  page,
}) => {
  // Start from a clean slate for this origin. Use the lightweight /up health
  // route — heavy pages like the dashboard can take >60s over the cold RDS.
  await page.goto("/up", { waitUntil: "domcontentloaded", timeout: 60_000 });
  await page.evaluate((key) => localStorage.removeItem(key), STORAGE_KEY);

  const { field } = await openBordersControls(page);
  const row = swatchRow(field);

  // Baseline: exactly the 8 fixed presets render as swatches.
  await expect(row.locator("button")).toHaveCount(PRESETS.length);

  // Committing a custom color via the picker (fill dispatches input+change)
  // appends it as a recent swatch and persists it.
  const picker = field.locator('input[type="color"]');
  await picker.fill("#123456");
  await expect(row.locator("button")).toHaveCount(PRESETS.length + 1);
  await expect(
    field.locator("p", { hasText: "recent custom colors" }),
  ).toBeVisible();
  const stored = await page.evaluate(
    (key) => JSON.parse(localStorage.getItem(key) || "[]"),
    STORAGE_KEY,
  );
  expect(stored).toEqual(["#123456"]);

  // Committing a preset color must NOT be re-added as a recent.
  await picker.fill("#ef4444");
  await expect(row.locator("button")).toHaveCount(PRESETS.length + 1);
  const stored2 = await page.evaluate(
    (key) => JSON.parse(localStorage.getItem(key) || "[]"),
    STORAGE_KEY,
  );
  expect(stored2).toEqual(["#123456"]);

  // A second custom color goes to the front (most recent first).
  await picker.fill("#abcdef");
  await expect(row.locator("button")).toHaveCount(PRESETS.length + 2);
  const stored3 = await page.evaluate(
    (key) => JSON.parse(localStorage.getItem(key) || "[]"),
    STORAGE_KEY,
  );
  expect(stored3).toEqual(["#abcdef", "#123456"]);

  // Per-side border color fields show the same swatches (recents shared).
  const styleRoot = page
    .locator(`[data-inline-editor-body="${ids.headingId}"] form`)
    .locator("[data-style-root]");
  await styleRoot.locator('button:has-text("Per-side borders")').click();
  const topField = styleRoot
    .locator("div[x-data='borderColorField()']")
    .filter({ has: page.locator('input[name="style[border_top_color]"]') })
    .first();
  await expect(swatchRow(topField).locator("button")).toHaveCount(
    PRESETS.length + 2,
  );
});

test("tapping a swatch fills the field and recents survive a reload", async ({
  page,
}) => {
  // Seed stored recents up-front so this test is independent of the first:
  // the swatches must hydrate from localStorage on a fresh page load.
  await page.goto("/up", { waitUntil: "domcontentloaded", timeout: 60_000 });
  await page.evaluate(
    (key) => localStorage.setItem(key, JSON.stringify(["#abcdef", "#123456"])),
    STORAGE_KEY,
  );

  const { field } = await openBordersControls(page);
  const row = swatchRow(field);

  // Recents hydrated from localStorage on page load.
  await expect(row.locator("button")).toHaveCount(PRESETS.length + 2);

  // Tapping the most recent swatch (first after the presets) fills the
  // submitted hidden input AND the visible picker.
  const hidden = field.locator('input[name="style[border_color]"]');
  await row.locator("button").nth(PRESETS.length).click();
  await expect(hidden).toHaveValue("#abcdef");
  await expect(field.locator('input[type="color"]')).toHaveValue("#abcdef");

  // Tapping a preset swatch works too and is not added to recents.
  await row.locator("button").nth(1).click();
  await expect(hidden).toHaveValue(PRESETS[1]);
  const stored = await page.evaluate(
    (key) => JSON.parse(localStorage.getItem(key) || "[]"),
    STORAGE_KEY,
  );
  expect(stored).toEqual(["#abcdef", "#123456"]);
});
