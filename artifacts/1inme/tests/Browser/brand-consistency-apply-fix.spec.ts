import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Page,
} from "@playwright/test";

// All tests share a single logged-in browser context (the demo-login route is
// rate-limited at throttle:5,1, so a login per test would trip the limit).
// Each test gets a fresh page from that context; the suite runs serially.
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

const ALIAS = "e2e-brand-consistency";
const KIT_SLUG = "e2e-brand-consistency";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/**
 * Run a `php artisan tinker` seed, retrying on a transient failure. Over the
 * distant RDS the tinker process occasionally fails to connect — a hard
 * "Command failed" with no PHP error in the output — which would flake the
 * whole spec at seed time. A couple of quick retries absorb that blip without
 * masking a real seed bug (a genuine PHP error fails every attempt and is then
 * surfaced via the rethrown error).
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
 * Idempotently (re)seed the demo user, a DEFAULT Brand Kit, and a deliberately
 * off-brand biolink — the exact fixture the Brand Consistency card audits.
 *
 * The audit scores EVERY biolink in the active workspace against the default
 * kit, so to make a single "apply fix" take the overall score to 100 we first
 * bring every OTHER biolink in the workspace on-brand (applying the kit, the
 * same thing the fix does), then blank our test page's appearance so it is the
 * one and only finding. The kit's targets mirror AiBrandKitService::applyToBiolink
 * (button color ← palette.primary, body font ← fonts.body, text color ← darkest
 * neutral, block theme ← block_theme key), so an applied page scores 100 with
 * the AI engine OFF — see BrandConsistencyService.
 *
 * Done via `php artisan tinker` so the spec is self-bootstrapping on a fresh
 * runner — it only needs the Laravel app running with migrations applied.
 * Returns the biolink id.
 */
function seedFixtures(): number {
  // NOTE: this string is passed straight to `tinker --execute=`. In a JS
  // template literal, `\\` becomes the single backslash PHP namespaces need
  // (e.g. App\Modules\...), while `$var` stays literal (only `${...}` would
  // interpolate). Do NOT write `\\$` — that yields invalid `\$var` PHP.
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BrandKit;
use App\\Modules\\User\\Services\\WorkspaceContext;
use App\\Services\\Brand\\AiBrandKitService;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

$u = User::where('email', 'demo@1inme.com')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => 'demo@1inme.com',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
$rid = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
if ($rid) { $u->roles()->syncWithoutDetaching([$rid]); $u->flushPermissionCache(); }
// Mark onboarded so the post-login RedirectToOnboarding soft gate doesn't bounce
// us through the heavy onboarding wizard before the page renders.
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

// A default Brand Kit with every dimension the audit checks. Make it the SOLE
// default so BrandKit::defaultFor() (orderBy is_default desc, id desc) picks it.
$config = [
  'palette' => ['primary' => '#3B5BDB', 'secondary' => '#5C7CFA', 'accent' => '#F783AC', 'neutrals' => ['#F8F9FA', '#212529']],
  'fonts' => ['heading' => 'Poppins', 'body' => 'Inter'],
  'voice' => ['tone' => 'Warm and confident', 'descriptors' => ['friendly', 'premium']],
  'taglines' => ['Shine brighter'],
  'bio' => 'A modern studio helping creators look the part.',
  'block_theme' => 'minimal',
];
BrandKit::where('user_id', $u->id)->update(['is_default' => false]);
$kit = BrandKit::where('user_id', $u->id)->where('slug', '${KIT_SLUG}')->first();
if (!$kit) { $kit = new BrandKit(); $kit->user_id = $u->id; $kit->slug = '${KIT_SLUG}'; }
$kit->name = 'E2E Consistency Kit';
$kit->config = $config;
$kit->is_default = true;
$kit->save();
$kit = $kit->fresh();

// The deliberately off-brand test page (idempotent).
$bio = Link::withoutGlobalScope('workspace')->where('alias', '${ALIAS}')->first();
if (!$bio) {
  $bio = Link::create([
    'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
    'alias' => '${ALIAS}', 'title' => 'E2E Brand Consistency', 'is_active' => true,
  ]);
} else {
  $bio->user_id = $u->id; $bio->workspace_id = $ws?->id; $bio->save();
}

// Bring EVERY other biolink in this workspace on-brand (mirrors the controller's
// audited set: user-owned, type=biolink, active workspace) so the only finding
// is our test page and a single apply-fix can take the score to 100.
$svc = app(AiBrandKitService::class);
$others = Link::withoutGlobalScope('workspace')
  ->where('workspace_id', $ws?->id)
  ->where('user_id', $u->id)
  ->where('type', 'biolink')
  ->get();
foreach ($others as $o) {
  if ($o->id === $bio->id) { continue; }
  $svc->applyToBiolink($kit, $o);
}

// Reset the test page to a blank (off-brand) appearance so it surfaces a finding.
$bio->settings = [];
$bio->save();

echo 'LINKID=' . $bio->id;
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/LINKID=(\d+)/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return Number(m[1]);
}

/**
 * Log in as the demo user (non-prod quick-login). Submits the demo-login form
 * via JS rather than a click, and waits only for the demo-login POST response
 * (not the redirect target render) so the heavy post-login dashboard render
 * never blocks the suite — see 1inme-browser-e2e-fast-login.
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

/** The numeric score shown inside the consistency gauge ring (the only span.absolute.inset-0). */
function gaugeScore(page: Page) {
  return page.locator("span.absolute.inset-0");
}

test.describe("brand consistency — score gauge + apply-fix round-trip", () => {
  // The login redirect and the brand-kits page are Blade renders over a distant
  // RDS, so lift the per-test/hook ceiling well above the shared 60s.
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(async ({ browser }) => {
    sharedContext = await browser.newContext();
    // Seed before the first login so the demo user is already marked onboarded —
    // otherwise the post-login soft gate bounces us through the heavy wizard.
    seedFixtures();
    const page = await sharedContext.newPage();
    await loginAsDemo(page);
    await page.close();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  test.beforeEach(() => {
    // Re-seed so each attempt starts from the off-brand state (a prior run's
    // apply-fix made the test page on-brand).
    seedFixtures();
  });

  test("renders the score gauge + a finding, and apply-fix brings the page on-brand", async ({
    page,
  }) => {
    await page.goto("/user/brand-kits");

    // The Brand Consistency card renders with its gauge below 100 (our test page
    // is off-brand) and a per-link finding headline.
    await expect(
      page.getByRole("heading", { name: "Brand Consistency Score" }),
    ).toBeVisible();

    await expect(gaugeScore(page)).toBeVisible();
    const before = Number((await gaugeScore(page).innerText()).trim());
    expect(Number.isFinite(before)).toBe(true);
    expect(before).toBeLessThan(100);

    // The off-brand page surfaces a finding ("… is N% on-brand") with its mismatch
    // chips and an "Apply fix" button.
    await expect(page.getByText(/ is \d+% on-brand$/)).toBeVisible();
    const applyFix = page.getByRole("button", { name: /Apply fix/ });
    await expect(applyFix.first()).toBeVisible();

    // Click "Apply fix". The POST reuses the brand-kit apply-biolink route and
    // 302-redirects to the heavy editor; submit the finding's form via JS and
    // wait only for the POST response so the editor render never blocks us.
    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes("/apply/biolink/") &&
          r.request().method() === "POST",
        { timeout: 90_000 },
      ),
      page.evaluate(() => {
        const btn = Array.from(
          document.querySelectorAll<HTMLButtonElement>("button"),
        ).find((b) => (b.textContent ?? "").includes("Apply fix"));
        const form = btn?.closest("form");
        if (!form) throw new Error("Apply fix form not found");
        form.submit();
      }),
    ]);

    // Re-audit: reload the brand-kits page and confirm the fix took the page
    // on-brand — the gauge now reads 100, there are no findings, and the
    // all-clear message shows.
    await page.goto("/user/brand-kits");
    await expect(
      page.getByRole("heading", { name: "Brand Consistency Score" }),
    ).toBeVisible();
    await expect(gaugeScore(page)).toHaveText("100");
    await expect(page.getByRole("button", { name: /Apply fix/ })).toHaveCount(0);
    await expect(
      page.getByText("Every Link in Bio matches your Brand Kit. Nice and tidy."),
    ).toBeVisible();
  });
});
