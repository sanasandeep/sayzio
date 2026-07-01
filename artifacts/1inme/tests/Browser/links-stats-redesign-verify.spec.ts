import { execFileSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Page,
} from "@playwright/test";

// Visual verification of the bento redesign of the Links index and Stats home
// (Task #3217) in a real, logged-in session. This spec is NOT an unattended
// gate — it is a one-off confirmation pass (Task #3223) that renders both pages
// with realistic data and captures screenshots for review across dark mode,
// light mode and reduced-motion. It shares one logged-in context (demo-login is
// rate-limited) and runs serially. Kept lean (domcontentloaded + explicit
// waits, minimal navigations) so it finishes within the validation budget over
// the distant RDS.
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);
const SHOT_DIR = path.join(
  ARTIFACT_ROOT,
  "tests/Browser/.artifacts/links-stats",
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
 * Idempotently prepare the demo user (active, onboarded, user-admin role so the
 * links.create / posts.view workspace gates pass), a SECOND owned Team
 * workspace (so the bulk-select bar + per-row move-to-workspace dropdown render
 * — the exact surfaces the redesign risked clipping), a spread of link types
 * with click counts (so the hero pulse + 3 metric tiles show real numbers and
 * the list rows exercise the type-badge/file-icon paths), plus a couple of
 * published posts + a follower (so the Stats KPI tiles, chart and top-posts
 * table all render populated instead of empty).
 */
function seedFixtures(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Workspace;
use App\\Modules\\User\\Models\\CreatorPost;
use App\\Modules\\User\\Models\\Follow;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

$u = User::where('email', 'demo@1inme.com')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first() ?? Plan::defaultPlan();
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

// A second OWNED workspace so index.blade's \$__canMove becomes true.
$team = Workspace::where('owner_user_id', $u->id)->where('is_personal', false)->first();
if (!$team) {
  Workspace::create([
    'owner_user_id' => $u->id, 'name' => 'Demo Team',
    'slug' => 'demo-team-' . $u->id, 'is_personal' => false,
  ]);
}

// A spread of link types with click counts.
$seedLinks = [
  ['type' => 'url',     'alias' => 'demo-verify-url',   'title' => 'My marketing landing page', 'long_url' => 'https://example.com/landing', 'total_clicks' => 1284, 'is_active' => true],
  ['type' => 'biolink', 'alias' => 'demo-verify-bio',   'title' => 'Creator link in bio',        'long_url' => null,                          'total_clicks' => 842,  'is_active' => true],
  ['type' => 'file',    'alias' => 'demo-verify-file',  'title' => 'Press kit (PDF)',            'long_url' => null,                          'total_clicks' => 96,   'is_active' => true],
  ['type' => 'vcf',     'alias' => 'demo-verify-vcard', 'title' => 'Digital business card',       'long_url' => null,                          'total_clicks' => 31,   'is_active' => false],
  ['type' => 'url',     'alias' => 'demo-verify-promo', 'title' => 'Summer promo redirect',       'long_url' => 'https://example.com/promo',   'total_clicks' => 7,    'is_active' => true],
];
foreach ($seedLinks as $row) {
  $existing = Link::withoutGlobalScope('workspace')->where('alias', $row['alias'])->first();
  if (!$existing) {
    Link::create(array_merge($row, ['user_id' => $u->id, 'workspace_id' => $ws?->id]));
  }
}

// Stats data: a follower and a couple of published posts with engagement.
$other = User::where('email', '!=', 'demo@1inme.com')->first();
if ($other && !Follow::where('creator_id', $u->id)->where('follower_id', $other->id)->exists()) {
  Follow::create(['creator_id' => $u->id, 'follower_id' => $other->id, 'created_at' => now()->subDays(2)]);
}
if (CreatorPost::withoutGlobalScope('workspace')->where('user_id', $u->id)->whereNotNull('published_at')->count() < 2) {
  CreatorPost::create(['user_id' => $u->id, 'title' => 'Behind the scenes of my new drop', 'body' => 'A quick look.', 'post_type' => 'text', 'published_at' => now()->subDays(1), 'reactions_count' => 54, 'comments_count' => 12]);
  CreatorPost::create(['user_id' => $u->id, 'title' => 'Q&A: how I grew my audience', 'body' => 'Answering your questions.', 'post_type' => 'text', 'published_at' => now()->subDays(3), 'reactions_count' => 33, 'comments_count' => 7]);
}

echo 'SEED_OK';
`.trim();

  const out = runTinkerSeed(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("Links/Stats verify seed failed, output:\n" + out);
  }
}

async function loginAsDemo(page: Page): Promise<void> {
  await page.goto("/user/login");
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().endsWith("/user/demo-login") && r.request().method() === "POST",
    ),
    page.evaluate(() => {
      const form = document.querySelector<HTMLFormElement>(
        'form[action$="/user/demo-login"]',
      );
      form?.submit();
    }),
  ]);
}

/** The app keys its theme off the `light-mode` html class (added server-side
 * when the `1inme_theme` cookie != 'dark'). We drive the mode by toggling that
 * exact class the CSS + Chart.js theming read, so the screenshot reflects the
 * real rendered look regardless of cookie plumbing. */
async function applyMode(page: Page, mode: "dark" | "light"): Promise<void> {
  await page.evaluate((m) => {
    const el = document.documentElement;
    if (m === "dark") el.classList.remove("light-mode");
    else el.classList.add("light-mode");
  }, mode);
  // Give Chart.js's MutationObserver a beat to re-theme before the shot.
  await page.waitForTimeout(300);
}

async function shoot(page: Page, name: string): Promise<void> {
  const file = path.join(SHOT_DIR, `${name}.png`);
  await page.screenshot({ path: file, fullPage: true });
  expect(fs.existsSync(file)).toBeTruthy();
}

test.beforeAll(async ({ browser }) => {
  fs.mkdirSync(SHOT_DIR, { recursive: true });
  seedFixtures();
  sharedContext = await browser.newContext({
    viewport: { width: 1280, height: 900 },
  });
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
});

test("Links index: hero, metric tiles, filters and list render", async ({
  page,
}) => {
  await page.goto("/user/links", { waitUntil: "domcontentloaded" });

  await expect(page.getByRole("heading", { name: "My Links" })).toBeVisible();
  // Hero pulse orb + 3 metric tiles.
  await expect(page.locator(".bento-hero .pulse-orb")).toBeVisible();
  await expect(page.locator(".bento .bento-tile")).toHaveCount(3);
  // Filters bar.
  await expect(page.locator('form input[name="search"]')).toBeVisible();
  await expect(page.locator('select[name="type"]')).toBeVisible();
  // Seeded link rows are present.
  await expect(page.locator("[data-link-id]").first()).toBeVisible();

  await applyMode(page, "dark");
  await shoot(page, "links-dark");
  await applyMode(page, "light");
  await shoot(page, "links-light");
});

test("Links index: move-to-workspace dropdown opens over rows, not clipped", async ({
  page,
}) => {
  await page.goto("/user/links", { waitUntil: "domcontentloaded" });

  // Scope to a single per-row dropdown: click the first row's move button, then
  // its panel is that button's immediate following sibling (avoids matching the
  // hidden bulk-move <select> or the outer list x-data wrapper).
  const btn = page
    .locator('button[title="Move to another workspace"]')
    .first();
  await expect(btn).toBeVisible();
  await btn.click();

  const panel = btn.locator("xpath=following-sibling::div").first();
  await expect(panel).toBeVisible();
  // The panel's move-target submit button must be visible (i.e. the menu is not
  // clipped away or rendered underneath the following row).
  await expect(panel.locator('button[type="submit"]').first()).toBeVisible();

  // Confirm the open panel actually hit-tests on top at its own centre point —
  // not obscured by a card underneath it.
  const box = await panel.boundingBox();
  expect(box).not.toBeNull();
  if (box) {
    const onTop = await page.evaluate(
      ({ x, y }) => {
        const el = document.elementFromPoint(x, y);
        return !!el && !!el.closest('div[class*="absolute"]');
      },
      { x: box.x + box.width / 2, y: box.y + 12 },
    );
    expect(onTop).toBeTruthy();
  }

  await shoot(page, "links-move-dropdown");
});

test("Stats home renders across every range (dark + light)", async ({
  page,
}) => {
  // Four heavy authenticated navigations + two full-page screenshots over the
  // distant RDS comfortably exceed the default 60s per-test budget.
  test.setTimeout(240000);
  for (const range of ["7d", "30d", "90d", "all"]) {
    const resp = await page.goto(`/user/stats?range=${range}`, {
      waitUntil: "domcontentloaded",
      timeout: 90000,
    });
    expect(resp?.status()).toBeLessThan(400);
    await expect(
      page.getByRole("heading", { name: "Stats home" }),
    ).toBeVisible();
    await expect(page.locator(".bento-hero .pulse-orb")).toBeVisible();
    // 4 KPI tiles + chart canvas.
    await expect(page.locator(".bento .bento-tile")).toHaveCount(4);
    await expect(page.locator("#statsChart")).toBeVisible();

    // Capture dark + light for the default range only (the others are asserted
    // structurally above; screenshotting all four would blow the time budget).
    if (range === "30d") {
      await applyMode(page, "dark");
      await shoot(page, "stats-dark");
      await applyMode(page, "light");
      await shoot(page, "stats-light");
    }
  }
});

test("Links & Stats render with reduced-motion enabled", async ({
  browser,
}) => {
  const ctx = await browser.newContext({
    viewport: { width: 1280, height: 900 },
    reducedMotion: "reduce",
    storageState: await sharedContext.storageState(),
  });
  const page = await ctx.newPage();

  await page.goto("/user/links", { waitUntil: "domcontentloaded" });
  await expect(page.getByRole("heading", { name: "My Links" })).toBeVisible();
  await expect(page.locator(".bento .bento-tile")).toHaveCount(3);
  await shoot(page, "links-reduced-motion");

  await page.goto("/user/stats", { waitUntil: "domcontentloaded" });
  await expect(page.getByRole("heading", { name: "Stats home" })).toBeVisible();
  await expect(page.locator(".bento .bento-tile")).toHaveCount(4);
  await shoot(page, "stats-reduced-motion");

  await page.close();
  await ctx.close();
});
