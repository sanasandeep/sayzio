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

// Regression guard for the logged-in account controls of the IN-APP shell on a
// MOBILE viewport (task: "Make sure the signed-in app sidebar's account controls
// cannot silently break on mobile").
//
// The authenticated app shell (user/layouts/app.blade.php) renders TWO parallel
// nav menus — a desktop `<aside>` (hidden below `lg`) and a separate mobile
// hamburger drawer + header ⋮ overflow menu. Per memory note user-sidebar-dual-nav
// these parallel blocks tend to drift: a markup/CSS edit to the mobile surfaces
// could silently drop or mis-wire the consolidated Settings hub entry or the
// sign-out action without anyone noticing until a user reports it. The desktop
// aside is unaffected on mobile (display:none) and its own controls are already
// exercised elsewhere; the mobile drawer + ⋮ menu had no browser e2e guard.
//
// On a mobile viewport this logs in as the demo user, lands on /user/dashboard
// (the in-app shell), and asserts:
//   1. the hamburger drawer exposes exactly ONE consolidated "Settings" entry,
//      wired into the /user/settings/{tab} hub (route('user.profile.edit') now
//      resolves to /user/settings/profile — memory settings-hub-consolidation);
//   2. the header ⋮ overflow menu (the mobile sign-out surface — the drawer
//      itself has no logout form) shows a Settings link into the same hub and a
//      correctly-wired Sign out logout form (action = route('user.logout'),
//      POST, non-empty CSRF token);
//   3. submitting that Sign out form actually ends the session (the authed
//      mobile controls vanish and the logged-out CTA returns).
// The non-destructive assertions run in BOTH dark and light mode (theme = the
// server `light-mode` html class — see memory user-layout-theme-in-playwright).
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral e2e server, since localhost:80 hits the Express api-server —
// see the sibling dashboard/header specs).

// iPhone-ish portrait, comfortably below Tailwind's `lg` (1024px) breakpoint so
// the desktop aside is hidden and the hamburger drawer + ⋮ menu are the only nav.
const MOBILE_VIEWPORT = { width: 400, height: 850 };

// All tests share a single logged-in browser context (the demo-login route is
// rate-limited at throttle:5,1, so a login per test would trip the limit). The
// destructive logout test runs last and re-establishes the session on retry.
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

/**
 * Run a `php artisan tinker` seed, retrying on a transient failure. Over the
 * distant RDS the tinker process occasionally fails to connect with no PHP
 * error; a couple of quick retries absorb that blip, a genuine PHP error fails
 * every attempt and is surfaced.
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
 * Idempotently prepare the demo user so login works and lands directly on the
 * dashboard: active + email-verified + `onboarded_at` set (so the onboarding
 * soft gate doesn't bounce login through the slow wizard — see memory "1inme
 * browser e2e fast login"). Also attach the `user-admin` web role so the
 * account/settings nav group renders (settings.view). Self-bootstrapping on a
 * fresh runner.
 *
 * NOTE: this string is passed straight to `tinker --execute=`. In a JS template
 * literal, `\\` becomes the single backslash PHP namespaces need (e.g.
 * App\Modules\...), while `$var` stays literal.
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
    throw new Error("Mobile in-app account seed failed, output:\n" + out);
  }
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
 * Force the site into the requested theme by toggling the exact `light-mode`
 * html class the CSS keys off. Setting the `1inme_theme` cookie does not
 * reliably flip the rendered theme (see memory user-layout-theme-in-playwright).
 */
async function setTheme(page: Page, mode: "dark" | "light"): Promise<void> {
  await page.evaluate((m) => {
    const cl = document.documentElement.classList;
    if (m === "light") cl.add("light-mode");
    else cl.remove("light-mode");
  }, mode);
}

// The in-app header's mobile hamburger toggle and ⋮ overflow button carry no
// accessible name (icon-only), so target them by their FontAwesome glyph. Both
// are `lg:hidden`, so on a mobile viewport there's exactly one of each.
const HAMBURGER = "button.lg\\:hidden:has(i.fa-bars)";
const OVERFLOW = "button:has(i.fa-ellipsis-v)";

/** Open /user/dashboard and wait until the in-app header hamburger is visible. */
async function gotoDashboard(page: Page): Promise<void> {
  await page.goto("/user/dashboard", { timeout: 120_000 });
  await page
    .locator(HAMBURGER)
    .waitFor({ state: "visible", timeout: 120_000 });
}

// The mobile hamburger drawer overlay (Alpine `x-show="mobileMenu"`).
const DRAWER = 'div[x-show="mobileMenu"]';

/** Open the hamburger drawer and wait for its nav to be visible. */
async function openDrawer(page: Page): Promise<void> {
  await page.locator(HAMBURGER).click();
  await expect(
    page.locator(`${DRAWER} nav`),
    "the mobile drawer nav must be visible after tapping the hamburger",
  ).toBeVisible();
}

// The mobile ⋮ overflow menu's logout form. The desktop aside's logout form is
// `display:none` on a mobile viewport, so `:visible` reliably targets the ⋮ one
// once the menu is open.
const OVERFLOW_LOGOUT_FORM = 'form[action$="/user/logout"]:visible';

/** Open the header ⋮ overflow menu and wait for its logout form to appear. */
async function openOverflowMenu(page: Page): Promise<void> {
  await page.locator(OVERFLOW).click();
  await expect(
    page.locator(OVERFLOW_LOGOUT_FORM),
    "the ⋮ overflow menu must reveal the Sign out form",
  ).toBeVisible();
}

test.describe("in-app mobile account controls", () => {
  // Cold authenticated renders over the distant RDS push past the default 60s
  // budget; give the suite real headroom (mirrors sibling specs).
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(async ({ browser }) => {
    seedFixtures();
    sharedContext = await browser.newContext({ viewport: MOBILE_VIEWPORT });
    const page = await sharedContext.newPage();
    await loginAsDemo(page);
    await page.close();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  // Non-destructive assertions across both themes: the drawer exposes exactly
  // one consolidated Settings entry into the hub, and the ⋮ menu exposes a
  // Settings link + a correctly-wired Sign out form.
  for (const mode of ["dark", "light"] as const) {
    test(`drawer shows a single Settings hub entry (${mode} mode)`, async ({
      page,
    }) => {
      await gotoDashboard(page);
      await setTheme(page, mode);
      await openDrawer(page);

      const drawerNav = page.locator(`${DRAWER} nav`);

      // The collapsible "Account" group holds the Settings entry; on the
      // dashboard it renders collapsed, so expand it first. Target the toggle by
      // its label span whose text is exactly "Account": an accessible-name match
      // is unreliable here because the group toggles carry a FontAwesome chevron
      // whose CSS `::before` glyph folds into the computed name, and a plain
      // substring "Account" also catches the sibling "Billing & Accounting"
      // toggle. (The coins/badges "Account" section lives OUTSIDE the nav, so
      // scoping to the nav is otherwise unambiguous.)
      await drawerNav
        .locator("button.sidebar-group-toggle")
        .filter({ has: page.locator("span", { hasText: /^Account$/ }) })
        .click();

      // Exactly ONE consolidated Settings entry (Task #3220 collapsed the old
      // Profile / Verification / Devices / API-keys links into this single hub).
      const settings = drawerNav.getByRole("link", { name: "Settings" });
      await expect(
        settings,
        `[${mode}] the drawer must show exactly one Settings entry`,
      ).toHaveCount(1);
      await expect(
        settings,
        `[${mode}] the Settings entry must be visible once its group is expanded`,
      ).toBeVisible();
      // Wired into the /user/settings/{tab} hub (route('user.profile.edit')).
      await expect(
        settings,
        `[${mode}] the Settings entry must link into the /user/settings/{tab} hub`,
      ).toHaveAttribute("href", /\/user\/settings\//);
    });

    test(`⋮ menu shows a Settings link and a valid Sign out form (${mode} mode)`, async ({
      page,
    }) => {
      await gotoDashboard(page);
      await setTheme(page, mode);
      await openOverflowMenu(page);

      // The ⋮ menu's Settings link points into the same hub.
      const settingsLink = page
        .getByRole("link", { name: "Settings" })
        .filter({ has: page.locator("i.fa-sliders") });
      await expect(
        settingsLink,
        `[${mode}] the ⋮ menu must show a Settings link`,
      ).toBeVisible();
      await expect(
        settingsLink,
        `[${mode}] the ⋮ menu Settings link must point into /user/settings/{tab}`,
      ).toHaveAttribute("href", /\/user\/settings\//);

      // A logout form with the correct action + POST method.
      const form = page.locator(OVERFLOW_LOGOUT_FORM);
      await expect(
        form,
        `[${mode}] the logout form action must be route('user.logout')`,
      ).toHaveAttribute("action", /\/user\/logout$/);
      await expect(
        form,
        `[${mode}] the logout form must POST`,
      ).toHaveAttribute("method", /post/i);

      // A present, non-empty CSRF token (the @csrf hidden field).
      const token = form.locator('input[name="_token"]');
      await expect(
        token,
        `[${mode}] the logout form must carry a CSRF token`,
      ).toHaveCount(1);
      const tokenValue = await token.inputValue();
      expect(
        tokenValue,
        `[${mode}] the CSRF token must be present and non-empty`,
      ).not.toBe("");

      // The submit control is labelled "Logout".
      await expect(
        form.getByRole("button", { name: "Logout" }),
        `[${mode}] the logout form's submit button must be present`,
      ).toBeVisible();
    });
  }

  // Destructive: actually submit the ⋮ menu's logout form and prove it ends the
  // session. Runs last (Playwright executes tests in definition order) so it
  // never pulls the session out from under the non-destructive tests above. On a
  // retry the shared context may already be logged out, so re-establish first.
  test("submitting Sign out logs the user out", async ({ page }) => {
    await gotoDashboard(page);

    // The overflow ⋮ button is only present when authenticated (the header
    // renders logged-out CTAs otherwise). If a prior/failed attempt already
    // logged this shared context out, re-establish the session first.
    if ((await page.locator(OVERFLOW).count()) === 0) {
      await loginAsDemo(page);
      await gotoDashboard(page);
    }
    await openOverflowMenu(page);

    const form = page.locator(OVERFLOW_LOGOUT_FORM);

    // Submit via JS and wait only for the logout POST (not the redirect render),
    // mirroring the fast-login pattern so the heavy post-logout render never
    // blocks the suite (see memory e2e-slow-redirect-click-autowait).
    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().endsWith("/user/logout") &&
          r.request().method() === "POST",
        { timeout: 90_000 },
      ),
      form.evaluate((f) => (f as HTMLFormElement).submit()),
    ]);

    // Logout redirects to the login page (route('user.login')). Landing there —
    // with the in-app shell's authed ⋮ logout form gone and the demo-login form
    // back — is proof the session actually ended.
    await page.waitForURL(/\/user\/login/, { timeout: 90_000 });
    await expect(
      page.locator(OVERFLOW_LOGOUT_FORM),
      "the in-app logout form must be gone after signing out",
    ).toHaveCount(0);
    await expect(
      page.locator('form[action$="/user/demo-login"]'),
      "the login page (proof of a logged-out session) must render after signing out",
    ).toBeVisible();
  });
});
