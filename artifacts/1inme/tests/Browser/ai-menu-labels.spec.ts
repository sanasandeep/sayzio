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

// Spot-check for the AI menu rename (display copy only; routes/keys unchanged):
//   "AI Growth Coach"  -> "AI Link Optimizer"
//   "Knowledge Bases"  -> "AI Minds"        (the Minds feature's user-facing label)
//
// The static guard (scripts/src/check-ai-tool-names.ts) already keeps the
// SOURCE honest, but this suite verifies the RENDERED surfaces:
//   1. The desktop <aside> sidebar of the authenticated app shell shows the new
//      labels — and neither old label — with no truncation/overflow of any nav
//      label (the renamed labels changed length, so overflow is the plausible
//      regression).
//   2. The mobile hamburger drawer (the SECOND parallel nav menu in
//      user/layouts/app.blade.php — memory note user-sidebar-dual-nav) shows the
//      same new labels, also without overflow.
//   3. /pricing and /user/upgrade render the "AI Minds" plan-limit label and no
//      stale copy.
//
// The Expo DrawerSidebar labels are asserted at source level by the static
// guard (components/DrawerSidebar.tsx renders static strings), so this suite
// focuses on the Laravel-rendered surfaces where layout overflow can occur.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner boots a
// dedicated ephemeral e2e server — see run-validation.sh).

const NEW_LABELS = ["AI Minds", "AI Link Optimizer"];
const OLD_LABELS = [/AI Growth Coach/i, /Growth Coach/i, /Knowledge Bases/i];

const DESKTOP_VIEWPORT = { width: 1440, height: 900 };
const MOBILE_VIEWPORT = { width: 400, height: 850 };

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

/** Run a `php artisan tinker` seed, retrying transient distant-RDS blips. */
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
 * Idempotently prepare the demo user: active + verified + onboarded (so login
 * lands on the dashboard, not the wizard) and holding the `user-admin` web
 * role so the gated "AI Minds" sidebar entry (settings.view) renders.
 */
function seedFixtures(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Plan;
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
$u->status = 'active';
if ($u->email_verified_at === null) { $u->email_verified_at = now(); }
if ($u->onboarded_at === null) { $u->onboarded_at = now(); }
$u->save();

$rid = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
if ($rid) { $u->roles()->syncWithoutDetaching([$rid]); $u->flushPermissionCache(); }

echo 'SEED_OK';
`.trim();

  const out = runTinkerSeed(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("AI menu labels seed failed, output:\n" + out);
  }
}

/**
 * Assert none of the visible label spans inside `scope` overflow their box.
 * Truncation from the renamed (longer) labels would show up as
 * scrollWidth > clientWidth on the label span (the sidebar has no intentional
 * ellipsis on nav labels). A 1px tolerance absorbs subpixel rounding.
 */
async function expectNoLabelOverflow(scope: Locator, what: string): Promise<void> {
  const overflows = await scope.evaluateAll((els) =>
    els
      .filter((el) => {
        const cs = getComputedStyle(el as HTMLElement);
        if (cs.display === "none" || cs.visibility === "hidden") return false;
        return (el as HTMLElement).scrollWidth > (el as HTMLElement).clientWidth + 1;
      })
      .map((el) => `"${(el.textContent ?? "").trim()}" (${(el as HTMLElement).scrollWidth}px > ${(el as HTMLElement).clientWidth}px)`),
  );
  expect(overflows, `${what}: no nav label may overflow/truncate — offenders: ${overflows.join(", ")}`).toEqual([]);
}

/** Expand the collapsible "AI" nav group inside the given nav scope. */
async function expandAiGroup(page: Page, scope: Locator): Promise<void> {
  const toggle = scope
    .locator("button.sidebar-group-toggle")
    .filter({ has: page.locator("span", { hasText: /^\s*AI\s*(Off)?\s*$/ }) })
    .first();
  const expanded = await toggle.getAttribute("aria-expanded");
  if (expanded !== "true") {
    await toggle.click();
  }
}

test.describe("AI menu labels (rename spot-check)", () => {
  // Cold authenticated renders over the distant RDS need real headroom.
  test.describe.configure({ timeout: 180_000 });

  test.afterEach(async () => {
    await sharedContext?.close();
  });

  test("desktop sidebar shows AI Minds + AI Link Optimizer, no old labels, no overflow", async ({
    browser,
  }) => {
    seedFixtures();
    sharedContext = await browser.newContext({ viewport: DESKTOP_VIEWPORT });
    const page = await sharedContext.newPage();
    await loginAsDemo(page);
    await page.goto("/user/dashboard", { timeout: 120_000 });

    const aside = page.locator("aside").first();
    await aside.waitFor({ state: "visible", timeout: 120_000 });
    await expandAiGroup(page, aside);

    for (const label of NEW_LABELS) {
      const link = aside.locator("a.sidebar-link").filter({
        has: page.locator("span.nav-label", { hasText: new RegExp(`^${label}$`) }),
      });
      await expect(link, `desktop aside must show a "${label}" nav entry`).toHaveCount(1);
      await expect(link.first(), `"${label}" nav entry must be visible`).toBeVisible();
    }

    const asideText = (await aside.innerText()).replace(/\s+/g, " ");
    for (const old of OLD_LABELS) {
      expect(asideText, `desktop aside must not contain stale label ${old}`).not.toMatch(old);
    }

    await expectNoLabelOverflow(aside.locator("span.nav-label"), "desktop aside");
    await page.close();
  });

  test("mobile drawer shows AI Minds + AI Link Optimizer, no old labels, no overflow", async ({
    browser,
  }) => {
    seedFixtures();
    sharedContext = await browser.newContext({ viewport: MOBILE_VIEWPORT });
    const page = await sharedContext.newPage();
    await loginAsDemo(page);
    await page.goto("/user/dashboard", { timeout: 120_000 });

    // Open the hamburger drawer (icon-only button; see dashboard-mobile-account).
    const hamburger = page.locator("button.lg\\:hidden:has(i.fa-bars)");
    await hamburger.waitFor({ state: "visible", timeout: 120_000 });
    await hamburger.click();
    const drawer = page.locator('div[x-show="mobileMenu"]');
    await expect(drawer.locator("nav"), "mobile drawer nav must open").toBeVisible();

    await expandAiGroup(page, drawer);

    for (const label of NEW_LABELS) {
      const link = drawer.locator("a.sidebar-link").filter({
        has: page.locator("span", { hasText: new RegExp(`^${label}$`) }),
      });
      await expect(link, `mobile drawer must show a "${label}" nav entry`).toHaveCount(1);
      await expect(link.first(), `"${label}" drawer entry must be visible`).toBeVisible();
    }

    const drawerText = (await drawer.innerText()).replace(/\s+/g, " ");
    for (const old of OLD_LABELS) {
      expect(drawerText, `mobile drawer must not contain stale label ${old}`).not.toMatch(old);
    }

    await expectNoLabelOverflow(
      drawer.locator("a.sidebar-link > span"),
      "mobile drawer",
    );
    await page.close();
  });

  test("/pricing and /user/upgrade show the AI Minds label and no stale copy", async ({
    browser,
  }) => {
    seedFixtures();
    sharedContext = await browser.newContext({ viewport: DESKTOP_VIEWPORT });
    const page = await sharedContext.newPage();

    await page.goto("/pricing", { timeout: 120_000, waitUntil: "domcontentloaded" });
    const pricingText = (await page.locator("body").innerText()).replace(/\s+/g, " ");
    expect(pricingText, "/pricing must show the AI Minds plan-limit label").toContain("AI Minds");
    for (const old of OLD_LABELS) {
      expect(pricingText, `/pricing must not contain stale label ${old}`).not.toMatch(old);
    }

    await loginAsDemo(page);
    await page.goto("/user/upgrade", { timeout: 120_000, waitUntil: "domcontentloaded" });
    const upgradeText = (await page.locator("body").innerText()).replace(/\s+/g, " ");
    expect(upgradeText, "/user/upgrade must show the AI Minds plan-limit label").toContain("AI Minds");
    for (const old of OLD_LABELS) {
      expect(upgradeText, `/user/upgrade must not contain stale label ${old}`).not.toMatch(old);
    }
    await page.close();
  });
});
