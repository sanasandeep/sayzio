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
import { loginAsDemoAdmin } from "./login-as-demo-admin";

// Shared context: admin guard + web guard coexist on the same session, and
// both demo-login routes are rate-limited (throttle:5,1), so we log in once.
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

// Per-run unique fixture identifiers. The RDS (and the app_settings row the
// admin Block Designs store lives in) is SHARED across parallel task
// environments, so both the biolink alias and the variant name must be unique
// per run; stale leftovers are pruned age-aware in the seeder.
const RUN = `${Date.now().toString(36)}${process.pid.toString(36)}`;
const ALIAS = `e2e-adm-variant-${RUN}`;
// Name embeds the creation epoch (base36 seconds) so the seeder can prune
// stale (>2h) leftovers from crashed runs WITHOUT tearing down a concurrent
// run in another environment: ZZE2EAdmVar-<epoch36>-<run>
const NAME_PREFIX = "ZZE2EAdmVar";
const VARIANT_NAME = `${NAME_PREFIX}-${Math.floor(Date.now() / 1000).toString(36)}-${RUN}`;

// Distinctive style payload we can recognize on the public page.
const BG_HEX = "#123456"; // rgb(18, 52, 86)
const TEXT_HEX = "#fedcba"; // rgb(254, 220, 186)
const STYLE_JSON = JSON.stringify(
  { bg_color: BG_HEX, text_color: TEXT_HEX, border_radius: 22 },
  null,
  2,
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
 * Seed the demo user + a fresh biolink with a single `link` block, prune
 * stale fixture links AND stale admin custom variants left by earlier runs
 * (>2h old, judged by the epoch token baked into the variant name so a
 * concurrent run in another environment is never torn down mid-flight).
 */
function seedFixtures(): { linkId: number; blockId: number } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Services\\WorkspaceContext;
use App\\Modules\\User\\Support\\AdminBlockDesigns;
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

// Prune stale fixture links from earlier runs (>2h so a concurrent suite in
// another environment sharing the RDS is never torn down mid-run).
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', 'e2e-adm-variant-%')
  ->where('created_at', '<', now()->subHours(2))
  ->pluck('id');
if ($stale->isNotEmpty()) {
  BiolinkBlock::whereIn('link_id', $stale)->delete();
  Link::withoutGlobalScope('workspace')->whereIn('id', $stale)->delete();
}

// Prune stale fixture variants: name carries a base36 creation epoch.
foreach (AdminBlockDesigns::customVariants(true) as $v) {
  $name = (string) ($v['name'] ?? '');
  if (!str_starts_with($name, '${NAME_PREFIX}-')) continue;
  $parts = explode('-', $name);
  $ts = intval($parts[1] ?? '', 36);
  if ($ts > 0 && $ts < time() - 7200) {
    AdminBlockDesigns::deleteVariant($v['key']);
  }
}

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Admin Variant', 'is_active' => true,
]);
$blk = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'link', 'sort_order' => 0, 'is_active' => true,
  'settings' => ['text' => 'Visit my website', 'url' => 'https://example.com'],
]);

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'blockId' => $blk->id]);
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/IDS=(\{.*\})/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return JSON.parse(m[1]);
}

let ids: { linkId: number; blockId: number };

test.beforeAll(async ({ browser }) => {
  // Tinker seed + two guard logins over the distant RDS can exceed 60s cold.
  test.setTimeout(240_000);
  ids = seedFixtures();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemoAdmin(page);
  await page
    .waitForLoadState("load", { timeout: 90_000 })
    .catch(() => undefined);
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

test.describe.configure({ mode: "serial" });
// Admin form render, editor render and public render each cross the distant
// RDS; cold first navigations can take 30-45s apiece.
test.setTimeout(300_000);

test("admin-created variant flows into the Designs gallery, applies, and renders publicly", async ({
  page,
}) => {
  // ---------- 1. Admin creates a custom variant through the real UI ----------
  await page.goto("/admin/block-designs/variants/create", {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  const form = page.locator('form[action$="/admin/block-designs/variants"]');
  await expect(form).toBeVisible({ timeout: 60_000 });

  await form.locator('input[name="name"]').fill(VARIANT_NAME);
  // Scope the design to the `link` block type only.
  await form.locator('input[name="types[]"][value="link"]').check();
  await form.locator('textarea[name="style_json"]').fill(STYLE_JSON);
  // "Enabled" is checked by default; assert rather than assume.
  await expect(form.locator('input[name="enabled"]')).toBeChecked();

  await Promise.all([
    page.waitForURL(/\/admin\/block-designs(\?|$)/, { timeout: 90_000 }),
    form.getByRole("button", { name: "Create variant" }).click(),
  ]);
  // The index lists the freshly created custom variant by name.
  await expect(page.getByText(VARIANT_NAME).first()).toBeVisible({
    timeout: 60_000,
  });

  // ---------- 2. User sees it in the editor Designs gallery ----------
  await page.addInitScript(() => {
    (window as unknown as { __E2E__: boolean }).__E2E__ = true;
  });
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  await page.waitForSelector(".block-card", { timeout: 60_000 });

  // Open the block's edit drawer (retry: slow Alpine init over distant RDS).
  const drawerForm = page.locator(
    `[data-inline-editor-body="${ids.blockId}"] form`,
  );
  for (let attempt = 1; attempt <= 3; attempt++) {
    await page.click(`[data-block-id="${ids.blockId}"] .edit-btn`);
    try {
      await expect(drawerForm).toBeVisible({ timeout: 20_000 });
      break;
    } catch (err) {
      if (attempt === 3) throw err;
    }
  }

  // Expand "Block Styling" — the Designs tab is the default active tab.
  await drawerForm.getByRole("button", { name: /Block Styling/ }).click();

  // The admin variant is server-rendered into the gallery grid with an
  // adm_* key and our unique name.
  const variantCard = drawerForm
    .locator('button[data-variant-key^="adm_"]')
    .filter({ hasText: VARIANT_NAME });
  await expect(variantCard).toHaveCount(1, { timeout: 30_000 });
  await variantCard.scrollIntoViewIfNeeded();

  // ---------- 3. Apply it to the block ----------
  const applyResponse = page.waitForResponse(
    (r) =>
      r.url().includes(`/blocks/${ids.blockId}/apply-variant`) &&
      r.request().method() === "POST",
    { timeout: 90_000 },
  );
  await variantCard.click();
  const resp = await applyResponse;
  expect(resp.ok()).toBeTruthy();
  const body = (await resp.json()) as { success?: boolean };
  expect(body.success).toBe(true);

  // Selected state: the checkmark badge on the applied card becomes visible
  // (opacity flips from 0 to 1 when currentVariant matches).
  await expect(async () => {
    const opacity = await variantCard
      .locator("div.absolute.top-1\\.5.left-1\\.5")
      .evaluate((el) => getComputedStyle(el).opacity);
    expect(Number(opacity)).toBeGreaterThan(0.9);
  }).toPass({ timeout: 20_000 });

  // ---------- 4. The variant styling renders on the public page ----------
  await page.goto(`/${ALIAS}`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  // Button-like blocks (`link`) carry the inline style on the <a> itself.
  const anchor = page.locator(`[data-block-id="${ids.blockId}"] a`).first();
  await expect(anchor).toBeVisible({ timeout: 60_000 });
  await expect(async () => {
    const styles = await anchor.evaluate((el) => {
      const cs = getComputedStyle(el);
      return { bg: cs.backgroundColor, color: cs.color };
    });
    expect(styles.bg).toContain("18, 52, 86");
    expect(styles.color).toContain("254, 220, 186");
  }).toPass({ timeout: 30_000 });
});
