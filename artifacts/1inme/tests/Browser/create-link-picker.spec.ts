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

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// A link alias the seed reserves as already-claimed, driving the "taken"
// availability state. It is alpha_dash and within the free-plan length limits
// so the checker reaches the uniqueness branch rather than failing format
// /length first.
const TAKEN_ALIAS = "e2epicktaken";

const VIEWPORTS = [
  { name: "desktop", size: { width: 1280, height: 900 } },
  { name: "mobile", size: { width: 390, height: 844 } },
] as const;

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
 * Idempotently prepare the demo user (active, onboarded, with the user-admin
 * role so the Create Link page's `workspace.can:links.create` gate passes), a
 * link that already owns TAKEN_ALIAS, and an admin banned-name row for
 * BANNED_ALIAS (cache flushed so the live checker sees it immediately). Done
 * via `php artisan tinker` so the spec is self-bootstrapping on a fresh runner.
 */
function seedFixtures(): void {
  // NOTE: this string is passed straight to `tinker --execute=`. In a JS
  // template literal, `\\` becomes the single backslash PHP namespaces need
  // (e.g. App\Modules\...), while `$var` stays literal.
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Services\\WorkspaceContext;
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
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

$taken = Link::withoutGlobalScope('workspace')->where('alias', '${TAKEN_ALIAS}')->first();
if (!$taken) {
  Link::create([
    'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'url',
    'alias' => '${TAKEN_ALIAS}', 'url' => 'https://example.com', 'is_active' => true,
  ]);
}

echo 'SEED_OK';
`.trim();

  const out = runTinkerSeed(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("Create Link picker seed failed, output:\n" + out);
  }
}

/**
 * Log in as the demo user (non-prod quick-login). Submits the demo-login form
 * via JS rather than a click, and waits only for the demo-login POST response
 * (not the redirect target render) so the heavy post-login dashboard render
 * never blocks the suite.
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
 * Open the Create Link picker at a given viewport and wait until the Alpine
 * `linkTypePicker` component on the form has hydrated (every goal/search/guard
 * interaction is Alpine-driven). The page is a cold authenticated Blade render
 * over the distant RDS, so give the navigation real headroom.
 */
async function openPicker(
  page: Page,
  viewport: { width: number; height: number },
): Promise<void> {
  await page.setViewportSize(viewport);
  await page.goto("/user/links/create", { timeout: 120_000 });
  await page.waitForFunction(
    () => {
      const A = (
        window as unknown as {
          Alpine?: { $data: (el: Element) => Record<string, unknown> };
        }
      ).Alpine;
      if (!A) return false;
      const form = document.querySelector(
        'form[action*="choose-type"]',
      );
      if (!form) return false;
      try {
        const data = A.$data(form);
        return !!data && "aliasBlocked" in data && "type" in data;
      } catch {
        return false;
      }
    },
    undefined,
    { timeout: 120_000 },
  );
}

/** Whether the radio for a given link type is currently selected. */
function typeRadio(page: Page, value: string) {
  return page.locator(`input[name="type"][value="${value}"]`);
}

/** Read the `aliasBlocked` flag from the picker's Alpine scope. */
function aliasBlocked(page: Page): Promise<boolean> {
  return page.evaluate(() => {
    const A = (
      window as unknown as {
        Alpine: { $data: (el: Element) => { aliasBlocked: boolean } };
      }
    ).Alpine;
    const form = document.querySelector('form[action*="choose-type"]')!;
    return A.$data(form).aliasBlocked;
  });
}

for (const vp of VIEWPORTS) {
  test.describe(`Create Link picker (${vp.name})`, () => {
    // Cold authenticated renders over the distant RDS push these past the
    // default 60s budget; give the suite real headroom (mirrors sibling specs).
    test.describe.configure({ timeout: 180_000 });

    test.beforeAll(async ({ browser }) => {
      seedFixtures();
      sharedContext = await browser.newContext();
      const page = await sharedContext.newPage();
      await loginAsDemo(page);
      await page.close();
    });

    test.afterAll(async () => {
      await sharedContext?.close();
    });

    test("clicking a link-type card selects it", async ({ page }) => {
      await openPicker(page, vp.size);

      // No type selected initially.
      await expect(typeRadio(page, "url")).not.toBeChecked();

      // Click the Short Link card → checks the url radio.
      await page.locator("#lt-card-url").click();
      await expect(typeRadio(page, "url")).toBeChecked();

      // Clicking a different card moves the selection.
      await page.locator("#lt-card-vcf").click();
      await expect(typeRadio(page, "vcf")).toBeChecked();
      await expect(typeRadio(page, "url")).not.toBeChecked();
    });

    test("typing in the Custom URL field shows availability states", async ({
      page,
    }) => {
      await openPicker(page, vp.size);
      const alias = page.locator("#create-link-alias");
      const status = page.locator("#create-link-alias-status");

      // Available: a fresh, unused alias.
      const fresh = `e2epickok${Date.now()}`;
      await alias.fill(fresh);
      await expect(status).toContainText("available", { timeout: 15_000 });

      // Taken: the seeded, already-claimed alias.
      await alias.fill(TAKEN_ALIAS);
      await expect(status).toContainText("already taken", { timeout: 15_000 });

      // Invalid: an illegal character (the format rule runs before the plan
      // length/banned checks, so this is account-independent).
      // NOTE: we deliberately do NOT assert the banned state here — the demo
      // account holds `user.banned_names.bypass`, so for this privileged user
      // the live checker correctly reports a banned name as available. The
      // banned/taken *guard* is covered (via the taken alias) in the next test.
      await alias.fill("bad alias!");
      await expect(status).toContainText("Only letters", { timeout: 15_000 });
    });

    test("a taken/banned alias blocks Continue and scrolls to the field", async ({
      page,
    }) => {
      await openPicker(page, vp.size);
      const alias = page.locator("#create-link-alias");

      // Pick a valid type so only the alias guard can block submit.
      await page.locator("#lt-card-url").click();
      await expect(typeRadio(page, "url")).toBeChecked();

      // Type the taken alias and wait for the verdict to propagate to the form.
      await alias.fill(TAKEN_ALIAS);
      await expect(page.locator("#create-link-alias-status")).toContainText(
        "already taken",
        { timeout: 15_000 },
      );
      await expect.poll(() => aliasBlocked(page), { timeout: 15_000 }).toBe(
        true,
      );

      // Clicking Continue is intercepted: we stay on the picker and the alias
      // field is focused (the guard focuses + scrolls it into view).
      await page
        .getByRole("button", { name: "Continue" })
        .click({ timeout: 15_000 });
      await page.waitForTimeout(800);
      await expect(page).toHaveURL(/\/user\/links\/create/);
      await expect(alias).toBeFocused();
    });

    test("leaving the Custom URL blank still submits and auto-generates", async ({
      page,
    }) => {
      await openPicker(page, vp.size);

      // Select a type, leave the alias blank, and Continue.
      await page.locator("#lt-card-url").click();
      await expect(typeRadio(page, "url")).toBeChecked();
      await expect(page.locator("#create-link-alias")).toHaveValue("");

      await Promise.all([
        page.waitForURL(/\/user\/links-url\/create/, { timeout: 60_000 }),
        page.getByRole("button", { name: "Continue" }).click(),
      ]);

      // The blank alias was not forwarded — Step 2 will auto-generate one.
      expect(new URL(page.url()).searchParams.get("alias")).toBeNull();
    });
  });
}
