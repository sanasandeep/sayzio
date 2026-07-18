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

// Regression guard for the admin sidebar label-clipping bug.
//
// Bug: when localStorage '1inme_admin_sidebar' holds a value that is NOT one
// of the two valid modes ('full' | 'icons'), the Alpine sidebarWidth getter
// previously returned 0.  The sidebar rendered at width:0 but WITHOUT the
// `.collapsed` CSS class (which is only applied for 'icons'), so nav label
// text overflowed the zero-width box.  Because .sidebar-v2 had no
// overflow:hidden, that overflow was visible — clipped only by whatever sat
// adjacent to the sidebar — producing the "es"/"ent"/"sts" fragments seen in
// the Safari screenshots from July 2026.
//
// Fix: two changes in admin/layouts/app.blade.php:
//   1. The sidebarMode initialiser now sanitises the localStorage value to the
//      set {'full','icons'}, falling back to 'full' for any unknown value.
//   2. .sidebar-v2 now carries overflow:hidden, so even during CSS transitions
//      or any edge-case width mismatch, nav content cannot bleed outside the
//      sidebar box.
//
// This spec exercises the Edit Plan page at a narrow desktop viewport (~1024px)
// because that is the exact environment the original bug was photographed in.
// It covers four scenarios:
//   1. A valid 'full' mode — sidebar shows full labels at full width.
//   2. A valid 'icons' mode — sidebar shows no labels (icon-only).
//   3. An INVALID stale localStorage value — must still render cleanly (no
//      label overflow/clip) after the sanitisation fix.
//   4. The toggle button switches between both modes without leaving clipped
//      labels in either direction.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points
// it at the ephemeral e2e server).

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
  // Note: $role, $a are PHP variables — plain $ is safe inside a JS template
  // literal (only ${...} is a JS substitution). DEMO_LOGIN_EMAIL is the JS
  // substitution.
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
    throw new Error("Admin sidebar label-clip seed failed, output:\n" + out);
  }
}

function seedPlan(): string {
  const php = `
use App\\Modules\\Admin\\Models\\Plan;
$p = Plan::where('slug', 'free')->first()
  ?? Plan::where('is_default', true)->first()
  ?? Plan::first();
echo 'PLAN_ID:' . ($p ? $p->id : '0');
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/PLAN_ID:(\d+)/);
  if (!m || m[1] === "0") {
    throw new Error(
      "Could not find a plan to use for the Edit Plan URL; output:\n" + out,
    );
  }
  return m[1];
}

/**
 * Navigate to the admin Edit Plan page and wait until the sidebar's "Dashboard"
 * nav label is attached to the DOM (proves Alpine has mounted and the sidebar
 * is in its final rendered state).
 */
async function openEditPlan(page: Page, planId: string): Promise<void> {
  await page.goto(`/admin/plans/${planId}/edit`, { timeout: 120_000 });
  await page
    .locator("aside .nav-label", { hasText: /^Dashboard$/ })
    .first()
    .waitFor({ state: "attached", timeout: 120_000 });
  // A brief pause lets Alpine finish the width transition before we measure.
  await page.waitForTimeout(450);
}

/**
 * Measure the sidebar's rendered pixel width and report any nav-label text that
 * visually overflows the sidebar box.
 *
 * A label "overflows" when its rendered right edge (getBoundingClientRect)
 * exceeds the sidebar's right edge by more than 1px (sub-pixel tolerance).
 * The sidebar is position:fixed so its rect is viewport-absolute — a reliable
 * reference.  Labels with opacity:0, display:none, or zero dimensions are
 * excluded because they are intentionally hidden (icon-only mode).
 */
async function measureSidebar(page: Page): Promise<{
  sidebarWidth: number;
  overflowingLabels: string[];
}> {
  return page.evaluate(() => {
    const aside = document.querySelector<HTMLElement>("aside");
    if (!aside) return { sidebarWidth: 0, overflowingLabels: [] };

    const asideRect = aside.getBoundingClientRect();
    const labels = Array.from(
      aside.querySelectorAll<HTMLElement>(".nav-label"),
    );

    const overflowingLabels: string[] = [];
    for (const label of labels) {
      const cs = getComputedStyle(label);
      if (
        cs.opacity === "0" ||
        cs.display === "none" ||
        cs.visibility === "hidden"
      )
        continue;
      const rect = label.getBoundingClientRect();
      if (rect.width === 0 && rect.height === 0) continue;
      if (rect.right > asideRect.right + 1) {
        overflowingLabels.push(
          (label.textContent ?? "").trim() ||
            `[label at x=${Math.round(rect.left)}]`,
        );
      }
    }

    return {
      sidebarWidth: Math.round(asideRect.width),
      overflowingLabels,
    };
  });
}

test.describe("admin sidebar label clipping — Edit Plan page at 1024px", () => {
  test.describe.configure({ timeout: 240_000 });

  let planId = "1";

  test.beforeAll(async ({ browser }) => {
    seedFixtures();
    planId = seedPlan();

    // Use a narrow desktop viewport that matches the original bug report.
    sharedContext = await browser.newContext({
      viewport: { width: 1024, height: 768 },
    });
    const loginPage = await sharedContext.newPage();
    await loginAsDemoAdmin(loginPage);
    await loginPage.close();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  test("full mode: sidebar is 260px wide with no label overflow", async ({
    page,
  }) => {
    await page.addInitScript(() => {
      localStorage.setItem("1inme_admin_sidebar", "full");
    });
    await openEditPlan(page, planId);

    const { sidebarWidth, overflowingLabels } = await measureSidebar(page);

    expect(
      sidebarWidth,
      `sidebar must be 260px in full mode, got ${sidebarWidth}px`,
    ).toBe(260);
    expect(
      overflowingLabels,
      `no nav label should overflow the sidebar box in full mode; overflowing: ${overflowingLabels.join(", ")}`,
    ).toHaveLength(0);
  });

  test("icons mode: sidebar is 72px wide with no label overflow", async ({
    page,
  }) => {
    await page.addInitScript(() => {
      localStorage.setItem("1inme_admin_sidebar", "icons");
    });
    await openEditPlan(page, planId);

    const { sidebarWidth, overflowingLabels } = await measureSidebar(page);

    expect(
      sidebarWidth,
      `sidebar must be 72px in icons mode, got ${sidebarWidth}px`,
    ).toBe(72);
    expect(
      overflowingLabels,
      `no nav label should overflow the sidebar box in icons mode; overflowing: ${overflowingLabels.join(", ")}`,
    ).toHaveLength(0);
  });

  test("invalid/stale localStorage value falls back to full mode — no label overflow (regression scenario)", async ({
    page,
  }) => {
    // This is the exact regression scenario: a stale value like 'collapsed'
    // previously caused sidebarWidth=0 with no collapsed class, making label
    // text overflow the zero-width box.
    await page.addInitScript(() => {
      localStorage.setItem("1inme_admin_sidebar", "collapsed");
    });
    await openEditPlan(page, planId);

    const { sidebarWidth, overflowingLabels } = await measureSidebar(page);

    expect(
      sidebarWidth,
      `invalid stored mode ('collapsed') must fall back to full (260px), got ${sidebarWidth}px — localStorage sanitisation missing`,
    ).toBe(260);
    expect(
      overflowingLabels,
      `no nav label should overflow the sidebar with an invalid stored mode; overflowing: ${overflowingLabels.join(", ")}`,
    ).toHaveLength(0);
  });

  test("toggling full ↔ icons leaves no clipped labels in either direction", async ({
    page,
  }) => {
    await page.addInitScript(() => {
      localStorage.setItem("1inme_admin_sidebar", "full");
    });
    await openEditPlan(page, planId);

    const toggleBtn = page.locator(
      "aside button[aria-label='Toggle sidebar']",
    );

    // Collapse to icons mode.
    await toggleBtn.click();
    await page.waitForTimeout(450); // let the CSS transition complete

    const icons = await measureSidebar(page);
    expect(
      icons.sidebarWidth,
      `after collapsing to icons mode the sidebar must be 72px, got ${icons.sidebarWidth}px`,
    ).toBe(72);
    expect(
      icons.overflowingLabels,
      `no label should overflow after collapsing; overflowing: ${icons.overflowingLabels.join(", ")}`,
    ).toHaveLength(0);

    // Expand back to full mode.
    await toggleBtn.click();
    await page.waitForTimeout(450);

    const full = await measureSidebar(page);
    expect(
      full.sidebarWidth,
      `after expanding back to full mode the sidebar must be 260px, got ${full.sidebarWidth}px`,
    ).toBe(260);
    expect(
      full.overflowingLabels,
      `no label should overflow after expanding; overflowing: ${full.overflowingLabels.join(", ")}`,
    ).toHaveLength(0);
  });
});
