import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Page,
} from "@playwright/test";

// Regression guard for the redesigned step-by-step first-run onboarding wizard
// (task: "confirm the new step-by-step onboarding can't leave users stuck").
//
// Onboarding was rebuilt from the old two-page persona/template flow into a
// single Alpine-driven page that walks discrete, visibly-stepped stages —
// Welcome -> Pick your persona -> Choose a template — with a persistent
// "Skip setup" escape hatch and a live template preview modal whose "Use this
// template" button creates the user's first biolink and drops them into the
// editor. Because the stage machine (stepKey / stepIndex), the persona ->
// template auto-advance, and the two terminal exits (apply a template / skip to
// the dashboard) are ALL client-side Alpine + Blade with no runtime check, a
// future edit could silently strand a brand-new user on a dead stage — the one
// place a broken flow is most costly and least visible (it only bites people on
// their very first session).
//
// This headless spec logs in as the demo user seeded into a FRESH,
// non-onboarded state and asserts the flow can't get stuck:
//   1. the visible stepper renders (Welcome / Pick your persona / Choose a
//      template) and the wizard opens on the Welcome stage,
//   2. advancing from Welcome and picking a persona moves the wizard on to the
//      template stage (the auto-advance that keeps the flow moving),
//   3. "Use this template" reaches its outcome — the biolink block editor,
//   4. "Skip setup" reaches its outcome — the dashboard (never bounced back
//      into onboarding).
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral e2e server, since localhost:80 hits the Express api-server —
// see the sibling specs).

// All tests share a single logged-in browser context (the demo-login route is
// rate-limited at throttle:5,1, so a login per test would trip the limit).
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

// A known, plan-unlocked template this spec seeds so the "Use this template"
// path always has a usable card to click regardless of what else is in the DB.
const TEMPLATE_NAME = "E2E Onboarding Starter";
// Marker biolink the reset step (re-)creates empty so applyTemplate reuses it
// instead of creating another one (keeps the demo user's real links untouched).
const MARKER_BIOLINK_TITLE = "E2E Onboarding Blank Page";

/**
 * Run a `php artisan tinker` seed, retrying on a transient failure. Over the
 * distant RDS the tinker process occasionally fails to connect — a hard
 * "Command failed" with no PHP error in the output — which would flake the
 * whole spec at seed time. A couple of quick retries absorb that blip; a
 * genuine PHP error fails every attempt and is then surfaced.
 */
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
 * Idempotently prepare the demo user + a plan-unlocked template so the wizard
 * can run end to end: the user is active + email-verified so login works, and a
 * `plan_tier = null` (open to all) active PageTemplate named TEMPLATE_NAME
 * always exists for the "Use this template" path. Done via `php artisan tinker`
 * so the spec is self-bootstrapping on a fresh runner.
 */
function seedFixtures(): void {
  // NOTE: this string is passed straight to `tinker --execute=`. In a JS
  // template literal, `\\` becomes the single backslash PHP namespaces need
  // (e.g. App\Modules\...), while `$var` stays literal.
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\Admin\\Models\\Plan;
use App\\Modules\\Admin\\Models\\PageTemplate;
use Illuminate\\Support\\Facades\\Hash;

$free = Plan::where('slug', 'free')->first();
$u = User::where('email', 'demo@1inme.com')->first();
if (!$u) {
  $u = User::create([
    'name' => 'Demo User', 'email' => 'demo@1inme.com',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
$u->status = 'active';
if ($u->email_verified_at === null) { $u->email_verified_at = now(); }
$u->save();

PageTemplate::updateOrCreate(
  ['slug' => 'e2e-onboarding-starter'],
  [
    'name' => '${TEMPLATE_NAME}',
    'category' => 'personal',
    'description' => 'Seeded by the onboarding e2e spec.',
    'plan_tier' => null,
    'recommended_personas' => [],
    'is_active' => true,
    'sort_order' => 0,
    'snapshot' => ['meta' => ['seed_version' => 1], 'blocks' => [], 'settings' => []],
  ]
);

echo 'SEED_OK';
`.trim();

  const out = runTinkerSeed(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("Onboarding seed failed, output:\n" + out);
  }
}

/**
 * Return the demo user to a pristine, NEVER-ONBOARDED state so each test starts
 * a genuinely first-run wizard: clear `onboarded_at`, the persona, and the
 * resume hint, then (re-)create a single empty marker biolink. applyTemplate
 * prefers an existing empty biolink over creating a new one, so seeding one
 * here means "Use this template" lands in the editor without touching any of
 * the demo user's real links or hitting plan caps.
 */
function resetOnboarding(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;

$u = User::where('email', 'demo@1inme.com')->firstOrFail();
$settings = $u->settings ?? [];
unset($settings['last_previewed_template_id']);
$u->forceFill(['onboarded_at' => null, 'persona' => null, 'settings' => $settings])->save();

// Drop any stale marker biolink from a previous run (it now has blocks) and
// recreate a fresh empty one for applyTemplate to reuse.
Link::where('user_id', $u->id)->where('title', '${MARKER_BIOLINK_TITLE}')->delete();
Link::create([
  'user_id' => $u->id,
  'type' => 'biolink',
  'alias' => Link::generateAlias(),
  'title' => '${MARKER_BIOLINK_TITLE}',
  'is_active' => true,
]);

echo 'RESET_OK';
`.trim();

  const out = runTinkerSeed(php);
  if (!out.includes("RESET_OK")) {
    throw new Error("Onboarding reset failed, output:\n" + out);
  }
}

/**
 * Deactivate every currently-active PageTemplate so the onboarding template
 * stage renders with a genuinely empty catalog — the exact condition a real
 * deployment hits when no template matches a persona/plan (or none are
 * seeded at all). Returns a restore fn that re-activates ONLY the templates
 * this call turned off, so the shared seeded starter (and any admin-disabled
 * templates) are left exactly as they were for the sibling specs.
 */
function deactivateAllTemplates(): () => void {
  const php = `
use App\\Modules\\Admin\\Models\\PageTemplate;

$ids = PageTemplate::where('is_active', true)->pluck('id')->all();
PageTemplate::where('is_active', true)->update(['is_active' => false]);
echo 'DEACT_IDS:' . json_encode(array_values($ids));
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/DEACT_IDS:(\[[^\]]*\])/);
  if (!m) {
    throw new Error("Deactivating templates failed, output:\n" + out);
  }
  const ids = JSON.parse(m[1]) as number[];

  return () => {
    if (ids.length === 0) return;
    const restorePhp = `
use App\\Modules\\Admin\\Models\\PageTemplate;
PageTemplate::whereIn('id', [${ids.join(",")}])->update(['is_active' => true]);
echo 'REACT_OK';
`.trim();
    const restoreOut = runTinkerSeed(restorePhp);
    if (!restoreOut.includes("REACT_OK")) {
      throw new Error("Re-activating templates failed, output:\n" + restoreOut);
    }
  };
}

/**
 * Restore the demo user to an onboarded state on the way out so any sibling
 * spec that assumes the demo user is already onboarded (the common case) isn't
 * bounced through the onboarding gate by leftover state from this spec.
 */
function restoreOnboarded(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;

$u = User::where('email', 'demo@1inme.com')->first();
if ($u) {
  if ($u->onboarded_at === null) { $u->onboarded_at = now(); }
  $u->save();
  Link::where('user_id', $u->id)->where('title', '${MARKER_BIOLINK_TITLE}')->delete();
}
echo 'RESTORE_OK';
`.trim();

  runTinkerSeed(php);
}

/**
 * Log in as the demo user (non-prod quick-login). Submits the demo-login form
 * via JS rather than a click, and waits only for the demo-login POST response
 * (not the redirect target render) so the heavy post-login render never blocks
 * the suite (see memory "1inme browser e2e fast login").
 */
async function loginAsDemo(page: Page): Promise<void> {
  await page.goto("/user/login");
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().endsWith("/user/demo-login") &&
        r.request().method() === "POST",
      { timeout: 90_000 },
    ),
    page.evaluate(() => {
      const form = document.querySelector<HTMLFormElement>(
        'form[action$="/user/demo-login"]',
      );
      if (!form) throw new Error("demo-login form not found");
      form.submit();
    }),
  ]);
}

/**
 * Open the onboarding wizard and wait until Alpine has hydrated the stage
 * machine: the progress stepper is attached and the Welcome stage's primary
 * CTA is visible (x-cloak lifts only once the onboarding() component runs). A
 * cold authenticated render over the distant RDS is slow, so give it headroom.
 */
async function openOnboarding(page: Page): Promise<void> {
  await page.goto("/user/onboarding", { timeout: 120_000 });
  await page
    .locator('nav[aria-label="Onboarding progress"]')
    .waitFor({ state: "attached", timeout: 120_000 });
  await expect(
    page.getByRole("button", { name: /Let's go/ }),
  ).toBeVisible({ timeout: 120_000 });
}

test.describe("first-run onboarding wizard", () => {
  // Cold authenticated renders over the distant RDS push these past the default
  // 60s budget; give the suite real headroom (mirrors sibling specs).
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(async ({ browser }) => {
    seedFixtures();
    sharedContext = await browser.newContext();
    const page = await sharedContext.newPage();
    await loginAsDemo(page);
    await page.close();
  });

  test.afterAll(async () => {
    restoreOnboarded();
    await sharedContext?.close();
  });

  test("the stepper renders and picking a persona advances to the template stage", async ({
    page,
  }) => {
    resetOnboarding();
    await openOnboarding(page);

    // 1) The visible progress stepper renders all three core stage labels.
    const stepper = page.locator('nav[aria-label="Onboarding progress"]');
    await expect(stepper).toBeVisible();
    await expect(stepper.getByText("Welcome", { exact: true })).toBeVisible();
    await expect(
      stepper.getByText("Pick your persona", { exact: true }),
    ).toBeVisible();
    await expect(
      stepper.getByText("Choose a template", { exact: true }),
    ).toBeVisible();

    // 2) The wizard opens on the Welcome stage; the persona picker isn't shown
    //    yet and the template grid isn't shown yet.
    const grid = page.locator("#onboarding-template-grid");
    const personaHeading = page.getByRole("heading", { name: "Who are you?" });
    await expect(personaHeading).toBeHidden();
    await expect(grid).toBeHidden();

    // Advance off Welcome -> Persona.
    await page.getByRole("button", { name: /Let's go/ }).click();
    await expect(personaHeading).toBeVisible();
    const personaCard = page.locator("[data-persona-card]").first();
    await expect(personaCard).toBeVisible();

    // 3) Picking a persona auto-advances to the template stage (the wizard
    //    keeps moving — it doesn't strand the user on the persona list).
    await personaCard.click();
    await expect(grid).toBeVisible({ timeout: 30_000 });
    await expect(personaHeading).toBeHidden();
    // And the terminal action for this stage is present, so the user can exit.
    await expect(page.getByText(TEMPLATE_NAME).first()).toBeVisible();
  });

  test('"Use this template" reaches its outcome: the biolink editor', async ({
    page,
  }) => {
    resetOnboarding();
    await openOnboarding(page);

    // Welcome -> Persona -> Template (skip persona so ALL templates show,
    // guaranteeing the seeded starter card is present without an AJAX refresh).
    await page.getByRole("button", { name: /Let's go/ }).click();
    await page
      .getByRole("button", { name: /Skip — show all templates/ })
      .click();

    const grid = page.locator("#onboarding-template-grid");
    await expect(grid).toBeVisible({ timeout: 30_000 });

    // Open the live preview for the seeded, plan-unlocked template.
    await grid.getByText(TEMPLATE_NAME).first().click();

    // The preview modal exposes the terminal "Use this template" action.
    const useBtn = page.getByRole("button", { name: /Use this template/ });
    await expect(useBtn).toBeVisible({ timeout: 30_000 });

    // Clicking it applies the template and drops the user into the block editor
    // (/user/links/{id}/blocks) — the flow's non-skip terminal outcome.
    await Promise.all([
      page.waitForURL(/\/user\/links\/\d+\/blocks(\?|$)/, {
        timeout: 120_000,
      }),
      useBtn.click(),
    ]);
    expect(page.url()).toMatch(/\/user\/links\/\d+\/blocks(\?|$)/);
  });

  test('"Skip setup" reaches its outcome: the dashboard', async ({ page }) => {
    resetOnboarding();
    await openOnboarding(page);

    // The persistent "Skip setup" escape hatch posts to go-to-dashboard and
    // must land the user on the dashboard — never bounce them back into
    // onboarding (which would trap a user who wants to skip).
    await Promise.all([
      page.waitForURL("**/user/dashboard", { timeout: 120_000 }),
      page
        .locator('form[action$="/onboarding/go-to-dashboard"] button[type="submit"]')
        .click(),
    ]);
    expect(page.url()).toContain("/user/dashboard");
    expect(page.url()).not.toContain("/user/onboarding");
  });

  test("template stage with NO active templates still offers an escape to the dashboard", async ({
    page,
  }) => {
    // Real deployments can have zero active templates for a persona/plan (or
    // none seeded at all). The template stage must NEVER become a dead end with
    // no actionable control — it has to keep an escape hatch so a first-run user
    // can still finish. Turn off every active template for the duration of this
    // test, then restore them so the sibling specs still see the seeded starter.
    resetOnboarding();
    const restoreTemplates = deactivateAllTemplates();
    try {
      await openOnboarding(page);

      // Welcome -> skip persona -> template (skip so the persona filter can't
      // be blamed; the whole catalog is empty here).
      await page.getByRole("button", { name: /Let's go/ }).click();
      await page
        .getByRole("button", { name: /Skip — show all templates/ })
        .click();

      const grid = page.locator("#onboarding-template-grid");
      await expect(grid).toBeVisible({ timeout: 30_000 });

      // The template stage renders the empty-state card (not a blank grid) and
      // surfaces an actionable escape to the dashboard.
      await expect(
        grid.getByText("No templates available yet"),
      ).toBeVisible({ timeout: 30_000 });
      const continueBtn = grid.getByRole("button", {
        name: /Continue to dashboard/,
      });
      await expect(continueBtn).toBeVisible();

      // The persistent "Skip setup" header escape is ALSO still present at this
      // stage — belt and braces so an empty catalog can never strand the user.
      await expect(
        page.getByRole("button", { name: /Skip setup/ }),
      ).toBeVisible();

      // Taking the in-stage escape lands the user on the dashboard — never
      // bounced back into onboarding.
      await Promise.all([
        page.waitForURL("**/user/dashboard", { timeout: 120_000 }),
        continueBtn.click(),
      ]);
      expect(page.url()).toContain("/user/dashboard");
      expect(page.url()).not.toContain("/user/onboarding");
    } finally {
      restoreTemplates();
    }
  });
});
