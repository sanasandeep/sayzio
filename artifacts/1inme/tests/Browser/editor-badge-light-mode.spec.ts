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

const ALIAS = "e2e-badge-lightmode";

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

function seedFixtures(): { linkId: number; blockA: number } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
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
    'alias' => '${ALIAS}', 'title' => 'E2E Badge Light Mode', 'is_active' => true,
  ]);
} else {
  $bio->user_id = $u->id; $bio->workspace_id = $ws?->id; $bio->save();
}

BiolinkBlock::where('link_id', $bio->id)->delete();
// grid_span 6 so the width chip "½" is the ACTIVE one and the span badge shows.
$a = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'heading', 'sort_order' => 0, 'is_active' => true, 'settings' => ['text' => 'Hello A', '_style' => ['grid_span' => 6]]]);

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'blockA' => $a->id]);
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

async function gotoEditor(page: Page, mode: "light" | "dark"): Promise<void> {
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
  });
  // Theme is a server-rendered html class; force it client-side (the cookie
  // path is unreliable under Playwright — see memory).
  await page.evaluate((m) => {
    document.documentElement.classList[m === "dark" ? "remove" : "add"](
      "light-mode",
    );
  }, mode);
  await page.waitForSelector(".block-card", { timeout: 45_000 });
}

async function colorOf(page: Page, selector: string): Promise<string> {
  return page
    .locator(selector)
    .first()
    .evaluate((el) => getComputedStyle(el).color);
}

test("light mode: active width chip uses dark high-contrast blue", async ({
  page,
}) => {
  await gotoEditor(page, "light");
  const active = page.locator(".span-btn.active").first();
  await expect(active).toBeVisible();
  expect(await active.evaluate((el) => getComputedStyle(el).color)).toBe(
    "rgb(35, 66, 199)",
  );
});

test("dark mode: active width chip keeps original pale blue", async ({
  page,
}) => {
  await gotoEditor(page, "dark");
  const active = page.locator(".span-btn.active").first();
  await expect(active).toBeVisible();
  expect(await active.evaluate((el) => getComputedStyle(el).color)).toBe(
    "rgb(144, 172, 255)",
  );
});

test("light mode: Display Settings section pills darken per tint", async ({
  page,
}) => {
  await gotoEditor(page, "light");
  // Open the inline block editor (loads the settings form incl. the
  // Display Settings cards via AJAX).
  await page
    .locator(`[data-block-id="${ids.blockA}"] .edit-btn`)
    .click();
  await page.waitForSelector(".block-settings-form", { timeout: 20_000 });

  // Header pill next to "Display Settings" (blue tint).
  expect(await colorOf(page, '[style*="rgba(188,207,255"]')).toBe(
    "rgb(35, 66, 199)",
  );
  // Limits & Scarcity pill (rose tint).
  expect(await colorOf(page, '[style*="rgba(254,205,211"]')).toBe(
    "rgb(190, 18, 60)",
  );
  // Audience & Location pill (cyan tint).
  expect(await colorOf(page, '[style*="rgba(165,243,252"]')).toBe(
    "rgb(14, 116, 144)",
  );
  // Device & Browser pill (pink tint).
  expect(await colorOf(page, '[style*="rgba(251,207,232"]')).toBe(
    "rgb(190, 24, 93)",
  );
});

test("dark mode: Display Settings section pills keep pastel colors", async ({
  page,
}) => {
  await gotoEditor(page, "dark");
  await page
    .locator(`[data-block-id="${ids.blockA}"] .edit-btn`)
    .click();
  await page.waitForSelector(".block-settings-form", { timeout: 20_000 });

  expect(await colorOf(page, '[style*="rgba(188,207,255"]')).toBe(
    "rgba(188, 207, 255, 0.85)",
  );
  expect(await colorOf(page, '[style*="rgba(254,205,211"]')).toBe(
    "rgba(254, 205, 211, 0.85)",
  );
});
