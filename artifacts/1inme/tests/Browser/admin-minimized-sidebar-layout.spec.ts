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
import { loginAsDemoAdmin } from "./login-as-demo-admin";

// Task: admin minimized-sidebar layout fixes.
//  1. The sidebar edge toggle handle must be fully visible (not clipped by
//     the sidebar's overflow) and hit-testable, in both modes and after
//     scrolling the main content.
//  2. The header theme toggle renders as a square bordered button
//     (.header-icon-btn defined in shared theme-styles).
//  3. Collapsed mode renders EVERY permitted menu item as a centered icon,
//     stacked vertically (regression: `nav > *` was a flex ROW, clipping all
//     but the first icons), and hover tooltips escape the overflow clipping.

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

let sharedContext: BrowserContext;

const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

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

function seedFixtures(): void {
  const php = `
use App\\Modules\\Admin\\Models\\Admin;
use App\\Modules\\Admin\\Models\\Role;
use Illuminate\\Support\\Facades\\Hash;

$role = Role::firstOrCreate(
  ['slug' => 'super-admin'],
  ['name' => 'Super Admin', 'guard' => 'admin']
);
$a = Admin::where('email', '${DEMO_LOGIN_EMAIL}')->first();
if (!$a) {
  $a = Admin::create([
    'name' => 'Admin', 'email' => '${DEMO_LOGIN_EMAIL}',
    'password' => Hash::make('password'), 'role_id' => $role->id,
    'status' => 'active',
  ]);
}
$a->status = 'active';
if (!$a->role_id) { $a->role_id = $role->id; }
$a->save();
echo 'SEED_OK';
`.trim();

  const out = runTinkerSeed(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("Admin seed failed, output:\n" + out);
  }
}

async function openDashboard(page: Page): Promise<void> {
  await page.goto("/admin", { timeout: 120_000 });
  await page
    .locator("aside .nav-label", { hasText: /^Dashboard$/ })
    .first()
    .waitFor({ state: "attached", timeout: 120_000 });
  await page.waitForTimeout(500);
}

/** The center of the toggle's OUTER half must hit-test to the toggle itself
 *  (proves it is neither clipped by sidebar overflow nor covered by the
 *  sticky header). */
async function assertToggleHittable(page: Page): Promise<void> {
  const result = await page.evaluate(() => {
    const btn = document.querySelector<HTMLElement>(
      "aside .sidebar-edge-toggle",
    );
    if (!btn) return { ok: false, why: "no toggle" };
    const aside = btn.closest("aside")!;
    const a = aside.getBoundingClientRect();
    const r = btn.getBoundingClientRect();
    if (r.width < 20 || r.height < 20)
      return { ok: false, why: `tiny rect ${r.width}x${r.height}` };
    // Outer half: horizontally beyond the sidebar's right edge.
    const outerX = (a.right + r.right) / 2;
    const y = r.top + r.height / 2;
    const el = document.elementFromPoint(outerX, y);
    const hit = !!el && (el === btn || btn.contains(el));
    return {
      ok: hit && r.right > a.right + 8,
      why: `hit=${hit} el=${el?.tagName}.${(el as HTMLElement)?.className} r.right=${r.right} a.right=${a.right}`,
    };
  });
  expect(result.ok, result.why).toBe(true);
}

test.describe("admin minimized sidebar layout", () => {
  test.describe.configure({ timeout: 240_000 });

  test.beforeAll(async ({ browser }) => {
    seedFixtures();
    sharedContext = await browser.newContext({
      viewport: { width: 1440, height: 900 },
    });
    const loginPage = await sharedContext.newPage();
    await loginAsDemoAdmin(loginPage);
    await loginPage.close();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  test("edge toggle is fully visible and hit-testable in both modes and after scroll", async ({
    page,
  }) => {
    await page.addInitScript(() => {
      localStorage.setItem("1inme_admin_sidebar", "full");
    });
    await openDashboard(page);

    await assertToggleHittable(page);

    // Scroll the main content — sticky header must not cover the handle.
    await page.evaluate(() => {
      const main = document.querySelector("main, .main-content-v2");
      (main ?? window).scrollTo?.(0, 600);
      window.scrollTo(0, 600);
    });
    await page.waitForTimeout(200);
    await assertToggleHittable(page);

    // Collapse via the handle itself (proves it's clickable), re-check.
    await page.locator("aside .sidebar-edge-toggle").click();
    await page.waitForTimeout(600);
    const collapsed = await page.evaluate(() =>
      document
        .querySelector("aside.sidebar-v2")!
        .classList.contains("collapsed"),
    );
    expect(collapsed).toBe(true);
    await assertToggleHittable(page);

    // Expand back.
    await page.locator("aside .sidebar-edge-toggle").click();
    await page.waitForTimeout(600);
    const expanded = await page.evaluate(
      () =>
        !document
          .querySelector("aside.sidebar-v2")!
          .classList.contains("collapsed"),
    );
    expect(expanded).toBe(true);
  });

  test("theme toggle is a square bordered header button", async ({ page }) => {
    await openDashboard(page);
    const styles = await page.evaluate(() => {
      const btn = document.querySelector<HTMLElement>(
        "header .header-icon-btn",
      );
      if (!btn) return null;
      const cs = getComputedStyle(btn);
      const r = btn.getBoundingClientRect();
      return {
        w: Math.round(r.width),
        h: Math.round(r.height),
        borderWidth: cs.borderTopWidth,
        borderStyle: cs.borderTopStyle,
        radius: cs.borderTopLeftRadius,
      };
    });
    expect(styles, "theme toggle button not found in header").not.toBeNull();
    expect(styles!.w).toBe(32);
    expect(styles!.h).toBe(32);
    expect(styles!.borderStyle).toBe("solid");
    expect(styles!.borderWidth).toBe("1px");
    expect(styles!.radius).toBe("8px");
  });

  test("collapsed mode shows every menu item as a stacked centered icon with escaping tooltips", async ({
    page,
  }) => {
    await page.addInitScript(() => {
      localStorage.setItem("1inme_admin_sidebar", "full");
    });
    await openDashboard(page);

    const expandedCount = await page
      .locator("aside .sidebar-nav-scroll .sidebar-link")
      .count();
    expect(expandedCount).toBeGreaterThan(20);

    await page.locator("aside .sidebar-edge-toggle").click();
    await page.waitForTimeout(600);

    // Every link renders inside the 72px rail, stacked vertically.
    const layout = await page.evaluate(() => {
      const aside = document.querySelector<HTMLElement>("aside.sidebar-v2")!;
      const a = aside.getBoundingClientRect();
      const links = Array.from(
        aside.querySelectorAll<HTMLElement>(".sidebar-nav-scroll .sidebar-link"),
      );
      let horizontallyContained = 0;
      const tops = new Set<number>();
      for (const l of links) {
        const r = l.getBoundingClientRect();
        if (r.width === 0) continue;
        if (r.left >= a.left - 1 && r.right <= a.right + 1)
          horizontallyContained++;
        tops.add(Math.round(r.top));
      }
      return {
        total: links.length,
        horizontallyContained,
        distinctRows: tops.size,
        asideWidth: Math.round(a.width),
      };
    });
    expect(layout.asideWidth).toBe(72);
    expect(layout.horizontallyContained).toBe(layout.total);
    // Vertically stacked: as many distinct row positions as links (a flex-row
    // regression collapses them onto one or two rows).
    expect(layout.distinctRows).toBeGreaterThanOrEqual(layout.total - 2);

    // Hover a link mid-list: tooltip must become visible OUTSIDE the rail.
    const link = page
      .locator("aside .sidebar-nav-scroll .sidebar-link")
      .nth(5);
    await link.hover();
    await page.waitForTimeout(300);
    const tip = await page.evaluate(() => {
      const aside = document.querySelector<HTMLElement>("aside.sidebar-v2")!;
      const a = aside.getBoundingClientRect();
      const links = aside.querySelectorAll<HTMLElement>(
        ".sidebar-nav-scroll .sidebar-link",
      );
      const t = links[5].querySelector<HTMLElement>(".sidebar-tooltip")!;
      const cs = getComputedStyle(t);
      const r = t.getBoundingClientRect();
      return {
        opacity: cs.opacity,
        position: cs.position,
        left: Math.round(r.left),
        railRight: Math.round(a.right),
        width: Math.round(r.width),
      };
    });
    expect(Number(tip.opacity)).toBeGreaterThan(0.9);
    // position:fixed re-anchoring is what lets the tooltip escape the nav
    // scroller's overflow clipping (it is pointer-events:none, so a hit test
    // cannot be used here).
    expect(tip.position).toBe("fixed");
    expect(tip.left).toBeGreaterThan(tip.railRight);
    expect(tip.width).toBeGreaterThan(30);

    // Expand restores full menu.
    await page.locator("aside .sidebar-edge-toggle").click();
    await page.waitForTimeout(600);
    const restored = await page
      .locator("aside .sidebar-nav-scroll .sidebar-link")
      .count();
    expect(restored).toBe(expandedCount);
  });
});
