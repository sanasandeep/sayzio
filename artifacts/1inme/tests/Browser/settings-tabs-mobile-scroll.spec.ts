import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test as base, type BrowserContext, type Page } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * Task #6784 — the settings sub-tab pill bar must be usable on phones:
 * - At 390px wide the bar is horizontally scrollable (no clipping-off tabs
 *   that can't be reached, no wrapping to a second row).
 * - Every tab (including the extra "Default Colors" tab on template design
 *   sessions — the widest case) can be scrolled into view.
 * - The active tab is auto-scrolled into view on load and after an AJAX
 *   tab swap.
 * - No visible scrollbar on the bar; desktop layout is unchanged.
 */

let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

const RUN_ID = `${Date.now().toString(36)}${Math.floor(Math.random() * 1e4)}`;
const ALIAS = `e2e-tabs-scroll-${RUN_ID}`;

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

function seedFixture(): { linkId: number } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
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

// Prune stale fixtures from earlier runs (shared RDS).
Link::withoutGlobalScope('workspace')->where('alias', 'like', 'e2e-tabs-scroll-%')->delete();

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Tabs Scroll', 'is_active' => true,
]);
// Template design session => extra "Default Colors" tab (widest bar).
$settings = $bio->settings ?? [];
$settings['_template_draft'] = [
  'template_id' => 0,
  'template_name' => 'E2E Tabs Scroll Draft',
  'started_at' => now()->toIso8601String(),
];
$bio->settings = $settings;
$bio->save();
echo 'LINKID=' . $bio->id;
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/LINKID=(\d+)/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return { linkId: Number(m[1]) };
}

test.describe.configure({ mode: "serial" });

let fixture: { linkId: number };

test.beforeAll(async ({ browser }) => {
  fixture = seedFixture();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
});

const ALL_TABS = [
  "Appearance",
  "Layout",
  "Block Theme",
  "Default Colors",
  "Themes",
  "Advanced",
  "Embed",
  "Intro",
];

async function barMetrics(page: Page) {
  return page.evaluate(() => {
    const scroller = document.querySelector(".settings-tabs-scroll") as HTMLElement;
    const bar = scroller.querySelector(".settings-tabs") as HTMLElement;
    const tabs = Array.from(bar.querySelectorAll(".settings-tab")) as HTMLElement[];
    const tops = tabs.map((t) => t.getBoundingClientRect().top);
    return {
      scrollWidth: scroller.scrollWidth,
      clientWidth: scroller.clientWidth,
      scrollLeft: scroller.scrollLeft,
      singleRow: Math.max(...tops) - Math.min(...tops) < 4,
      tabCount: tabs.length,
    };
  });
}

async function activeTabVisibleInBar(page: Page) {
  return page.evaluate(() => {
    const scroller = document.querySelector(".settings-tabs-scroll") as HTMLElement;
    const active = scroller.querySelector(".settings-tab.is-active") as HTMLElement;
    if (!active) return false;
    const s = scroller.getBoundingClientRect();
    const a = active.getBoundingClientRect();
    return a.left >= s.left - 1 && a.right <= s.right + 1;
  });
}

test("phone width: bar scrolls horizontally, all tabs reachable, no wrap", async ({
  page,
}) => {
  test.setTimeout(300_000);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`/user/links/${fixture.linkId}/settings/appearance`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  await expect(page.locator(".settings-tabs")).toBeVisible({ timeout: 60_000 });

  const m = await barMetrics(page);
  expect(m.tabCount).toBe(ALL_TABS.length);
  // The bar overflows at 390px and stays a single row (no wrapping).
  expect(m.scrollWidth).toBeGreaterThan(m.clientWidth + 20);
  expect(m.singleRow).toBe(true);
  // Scroller never exceeds the viewport width.
  expect(m.clientWidth).toBeLessThanOrEqual(390);

  // Active (first) tab is visible on load.
  expect(await activeTabVisibleInBar(page)).toBe(true);

  // Every tab, including the last, can be scrolled into view.
  for (const label of ALL_TABS) {
    const tab = page.locator(".settings-tab", { hasText: label }).first();
    await tab.scrollIntoViewIfNeeded();
    await expect(tab).toBeVisible();
  }
  const after = await barMetrics(page);
  expect(after.scrollLeft).toBeGreaterThan(0); // it actually scrolled

  // No visible scrollbar on the scroller.
  const scrollbarHidden = await page.evaluate(() => {
    const el = document.querySelector(".settings-tabs-scroll") as HTMLElement;
    const cs = getComputedStyle(el);
    return cs.scrollbarWidth === "none" || el.offsetHeight - el.clientHeight === 0;
  });
  expect(scrollbarHidden).toBe(true);
});

test("active tab auto-scrolled into view on deep tab load + after AJAX swap", async ({
  page,
}) => {
  test.setTimeout(300_000);
  await page.setViewportSize({ width: 390, height: 844 });
  // Load a far-right tab directly — it must be visible without user scroll.
  await page.goto(`/user/links/${fixture.linkId}/settings/embed`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  await expect(page.locator(".settings-tab.is-active")).toHaveText(/Embed/, {
    timeout: 60_000,
  });
  expect(await activeTabVisibleInBar(page)).toBe(true);

  // AJAX-swap back to a left tab, then to a right one; active stays in view
  // and the swap machinery still works (URL + content container update).
  const appearanceTab = page.locator(".settings-tab", { hasText: "Appearance" });
  await appearanceTab.scrollIntoViewIfNeeded();
  await appearanceTab.click();
  // The is-active flip happens only after the AJAX fetch completes — over the
  // distant RDS a cold settings render can take well beyond 10s.
  await expect(page.locator(".settings-tab.is-active")).toHaveText(/Appearance/, {
    timeout: 120_000,
  });
  await page.waitForURL(/settings\/appearance/, { timeout: 120_000 });
  expect(await activeTabVisibleInBar(page)).toBe(true);

  const introTab = page.locator(".settings-tab", { hasText: "Intro" });
  await introTab.scrollIntoViewIfNeeded();
  await introTab.click();
  await expect(page.locator(".settings-tab.is-active")).toHaveText(/Intro/, {
    timeout: 120_000,
  });
  await page.waitForURL(/splash/, { timeout: 120_000 });
  expect(await activeTabVisibleInBar(page)).toBe(true);
  await expect(page.locator("#settings-tab-content")).toBeVisible();
});

test("light mode phone width renders and scrolls the same", async ({ page }) => {
  test.setTimeout(300_000);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`/user/links/${fixture.linkId}/settings/advanced`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  await expect(page.locator(".settings-tabs")).toBeVisible({ timeout: 60_000 });
  await page.evaluate(() => document.documentElement.classList.add("light-mode"));

  const m = await barMetrics(page);
  expect(m.singleRow).toBe(true);
  expect(m.scrollWidth).toBeGreaterThan(m.clientWidth + 20);
  expect(await activeTabVisibleInBar(page)).toBe(true);
  // Active pill still visibly styled in light mode.
  const bg = await page
    .locator(".settings-tab.is-active")
    .evaluate((el) => getComputedStyle(el).backgroundImage);
  expect(bg).toContain("gradient");
});

test("desktop width: bar does not scroll (layout unchanged)", async ({ page }) => {
  test.setTimeout(300_000);
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(`/user/links/${fixture.linkId}/settings/appearance`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  await expect(page.locator(".settings-tabs")).toBeVisible({ timeout: 60_000 });
  const m = await barMetrics(page);
  expect(m.singleRow).toBe(true);
  expect(m.scrollWidth).toBeLessThanOrEqual(m.clientWidth + 1);
  // Pill container hugs its content (inline-flex), not stretched full width.
  const barWidth = await page
    .locator(".settings-tabs")
    .evaluate((el) => el.getBoundingClientRect().width);
  expect(barWidth).toBeLessThan(1200);
});
