import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";

/**
 * e2e: audience prompt flow on a public biolink (Task: confirm the prompt
 * appears and the visitor type saves into analytics).
 *
 * Covers the full chain no controller-level test can reach end-to-end:
 *   Alpine renders the prompt → visitor taps a persona → the card collapses
 *   to the "Thanks!" message → the ap_type_{linkId} cookie + localStorage
 *   persist → the POST /{alias}/track/identify lands (after the async
 *   startSession populates window.__SESSION_ID__) → page_sessions.visitor_type
 *   is written → the owner's analytics page (/user/links/{id}) surfaces the
 *   persona in the Audience Insights section.
 *
 * Self-bootstrapping: seeds its own biolink (owned by the demo user, with
 * settings.biolink.audience_prompt.enabled=true and the consent banner OFF so
 * no consent gate applies) via `php artisan tinker`. The alias is unique per
 * run (shared-RDS parallel envs collide on fixed aliases — see repo memory
 * e2e-shared-rds-fixture-aliases); stale fixtures from prior runs are pruned
 * in the seed.
 */

const ALIAS_PREFIX = "e2e-audp-";
const ALIAS =
  ALIAS_PREFIX +
  Date.now().toString(36) +
  Math.random().toString(36).slice(2, 6);

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

let sharedContext: Awaited<
  ReturnType<import("@playwright/test").Browser["newContext"]>
>;
let linkId: number;

function runTinkerSeed(php: string): string {
  return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
}

/**
 * Seed a fresh biolink owned by the demo user with the audience prompt
 * enabled. Returns the new link's id (needed for the ap_type_{id} cookie key
 * and the /user/links/{id} analytics URL).
 */
function seedAudiencePromptBiolink(): number {
  // NOTE: passed straight to `tinker --execute=`. In a JS template literal,
  // `\\` becomes the single backslash PHP namespaces need, while `$var` stays
  // literal (only `${"$"}{...}` would interpolate). Do NOT write `\\$`.
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

// Seed under the SAME account demo-login authenticates
// (AuthController::demoLogin -> sazioapp@gmail.com). Owning the fixture as
// any other email trips the analytics page's owner guard
// (\$link->user_id !== workspace_owner_id()) and 403s before the card renders.
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
// Mark onboarded so the post-login soft gate doesn't bounce through the wizard.
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

// Prune stale fixtures from previous runs (aliases are per-run unique).
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}%')
  ->where('created_at', '<', now()->subDay())
  ->get();
foreach ($stale as $s) { $s->delete(); }

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Audience Prompt', 'is_active' => true,
  'settings' => ['biolink' => [
    'audience_prompt' => ['enabled' => true],
    // Consent banner OFF: engagement tracking + the identify POST need no
    // consent, which is the default owner configuration this spec pins.
    'privacy' => ['consent_banner_enabled' => false],
  ]],
]);
BiolinkBlock::create([
  'link_id' => $bio->id, 'workspace_id' => $bio->workspace_id,
  'type' => 'paragraph', 'sort_order' => 0, 'is_active' => true,
  'settings' => ['text' => 'Audience prompt e2e fixture body'],
]);
echo 'LINKID=' . $bio->id;
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/LINKID=(\d+)/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return Number(m[1]);
}

/**
 * Log in as the demo user (non-prod quick-login). Submits the demo-login form
 * via JS and waits only for the POST response (not the heavy post-login
 * dashboard render) — see 1inme-browser-e2e-fast-login.
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

test.describe("audience prompt — public tap through to Audience Insights", () => {
  // Blade renders over a distant RDS; lift the ceiling above the shared 60s.
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(async ({ browser }) => {
    linkId = seedAudiencePromptBiolink();
    sharedContext = await browser.newContext();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  test("prompt renders, persona tap collapses to Thanks, cookie + identify POST land", async () => {
    const page = await sharedContext.newPage();

    // The identify POST fires asynchronously after startSession() resolves
    // (the widget retries until window.__SESSION_ID__ exists), so arm the
    // waiter BEFORE tapping and give it room for the up-to-8×700ms retry loop.
    const identifyResponse = page.waitForResponse(
      (r) =>
        r.url().includes(`/${ALIAS}/track/identify`) &&
        r.request().method() === "POST",
      { timeout: 30_000 },
    );

    await page.goto(`/${ALIAS}`);

    // Prompt card renders with the default question + persona buttons.
    const wrap = page.locator("#audience-prompt-wrap");
    await expect(wrap).toBeVisible();
    await expect(wrap).toContainText("What best describes you?");
    const studentBtn = wrap.getByRole("button", { name: "Student" });
    await expect(studentBtn).toBeVisible();

    // Tap "Student".
    await studentBtn.click();

    // Card collapses to the Thanks message (question + buttons gone).
    await expect(wrap).toContainText("Thanks for letting us know!");
    await expect(wrap).not.toContainText("What best describes you?");
    await expect(studentBtn).toHaveCount(0);

    // ap_type_{linkId} cookie is set to the picked persona.
    const cookies = await sharedContext.cookies();
    const apCookie = cookies.find((c) => c.name === `ap_type_${linkId}`);
    expect(apCookie?.value).toBe("student");

    // localStorage mirror (drives the "already answered" collapse on revisit).
    const stored = await page.evaluate(
      (id) => localStorage.getItem("ap_type_" + id),
      linkId,
    );
    expect(stored).toBe("student");

    // The identify POST reached the server and updated the page_session row.
    const res = await identifyResponse;
    expect(res.status()).toBe(200);
    const body = (await res.json()) as { ok: boolean };
    expect(body.ok).toBe(true);

    // A revisit shows the collapsed Thanks state immediately (persistence).
    await page.goto(`/${ALIAS}`);
    await expect(wrap).toContainText("Thanks for letting us know!");
    await expect(
      wrap.getByRole("button", { name: "Student" }),
    ).toHaveCount(0);

    await page.close();
  });

  test("owner analytics page shows the persona under Audience Insights", async () => {
    const page = await sharedContext.newPage();
    await loginAsDemo(page);

    // The per-link analytics page is heavy on a cold cache (stats queries over
    // the distant RDS), so allow a generous first-render budget and don't wait
    // for every subresource — the Audience Insights card is server-rendered.
    await page.goto(`/user/links/${linkId}`, {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });

    const card = page
      .locator(".section-card", { hasText: "Audience Insights" })
      .first();
    await expect(card).toBeVisible();

    // The fixture link is fresh (unique alias), so the one identified session
    // from the previous test is the entire breakdown: the self-identified
    // pill counts 1 and the Student persona row renders at 100%.
    await expect(card).toContainText("1 self-identified");
    await expect(card).toContainText("Student");
    await expect(card).toContainText(
      "Visitors who self-identified their persona via the audience prompt.",
    );

    await page.close();
  });
});
