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

// Regression guard for the logged-in account controls inside the MOBILE
// hamburger drawer of the Sayzio public header (task: "Make sure the mobile
// menu's account controls can't silently break").
//
// The desktop counterpart (header-account-menu.spec.ts) pins the desktop
// account dropdown. But the same header renders a PARALLEL logged-in section
// inside the mobile hamburger drawer (header.blade.php @auth block, ~lines
// 371-390) with its OWN "Dashboard" link and "Sign out" logout form. That
// surface was unguarded — a future markup/CSS change to the drawer could drop
// or break the logout action on mobile without anyone noticing until a user
// reports it. Per memory note user-sidebar-dual-nav, these parallel nav blocks
// tend to drift, so this headless spec pins the invariant.
//
// On a mobile viewport it logs in as the demo user, opens the hamburger menu,
// and asserts the logged-in drawer shows:
//   1. a working "Dashboard" link (href -> /user/dashboard),
//   2. a "Sign out" logout form with the correct action (route('user.logout')),
//      method POST, and a non-empty CSRF token,
//   3. that submitting that form actually logs the user out (the authed drawer
//      controls disappear and the logged-out CTAs return).
// Checked in BOTH dark and light mode (theme = the server `light-mode` html
// class — see memory user-layout-theme-in-playwright).
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral e2e server, since localhost:80 hits the Express api-server —
// see the sibling home/dashboard specs).

// iPhone-ish portrait, comfortably below Tailwind's `lg` (1024px) breakpoint so
// the desktop nav is hidden and the hamburger drawer is the only nav surface.
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

// A light marketing page that renders `public.layouts.site` (and thus the shared
// `public.partials.header`). Deliberately not the home page — its cold render is
// the heaviest in the suite. `/contact` is already warmed by the runner.
const HEADER_PAGE = "/contact";

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
 * Idempotently prepare the demo user so login works and doesn't bounce through
 * the slow onboarding wizard: active + email-verified + `onboarded_at` set (see
 * memory "1inme browser e2e fast login"). Self-bootstrapping on a fresh runner.
 */
function seedFixtures(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Plan;
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
$u->status = 'active';
if ($u->email_verified_at === null) { $u->email_verified_at = now(); }
if ($u->onboarded_at === null) { $u->onboarded_at = now(); }
$u->save();

echo 'SEED_OK';
`.trim();

  const out = runTinkerSeed(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("Mobile account-menu seed failed, output:\n" + out);
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
 * html class the CSS keys off. Bulletproof and mode-deterministic — setting the
 * `1inme_theme` cookie does not reliably flip the rendered theme (see memory
 * user-layout-theme-in-playwright).
 */
async function setTheme(page: Page, mode: "dark" | "light"): Promise<void> {
  await page.evaluate((m) => {
    const cl = document.documentElement.classList;
    if (m === "light") cl.add("light-mode");
    else cl.remove("light-mode");
  }, mode);
}

/** Load the header page fresh and wait for the mobile hamburger toggle. */
async function gotoHeaderPage(page: Page): Promise<void> {
  await page.goto(HEADER_PAGE, { timeout: 120_000 });
  await page
    .getByRole("button", { name: "Toggle menu" })
    .waitFor({ state: "visible", timeout: 120_000 });
}

/** Open the hamburger drawer (the only nav entry point on mobile). */
async function openDrawer(page: Page): Promise<void> {
  const toggle = page.getByRole("button", { name: "Toggle menu" });
  await expect(
    toggle,
    "the mobile hamburger toggle must be visible on a small viewport",
  ).toBeVisible();
  await toggle.click();
}

// The mobile drawer's logout form. On a mobile viewport the desktop header
// (and its own logout form) is `display:none`, so `:visible` reliably targets
// the drawer form once the drawer is open.
const DRAWER_LOGOUT_FORM = 'form[action$="/user/logout"]:visible';

test.describe("public header mobile account controls", () => {
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

  // Non-destructive assertions: the drawer exposes a working Dashboard link and
  // a correctly-wired Sign out logout form. Run in both themes.
  for (const mode of ["dark", "light"] as const) {
    test(`drawer shows a Dashboard link and a valid Sign out form (${mode} mode)`, async ({
      page,
    }) => {
      await gotoHeaderPage(page);
      await setTheme(page, mode);
      await openDrawer(page);

      // 1) A working Dashboard link into the app.
      const dashboard = page.getByRole("link", { name: "Dashboard" });
      await expect(
        dashboard,
        `[${mode}] the drawer must show a Dashboard link when logged in`,
      ).toBeVisible();
      await expect(
        dashboard,
        `[${mode}] the Dashboard link must point at /user/dashboard`,
      ).toHaveAttribute("href", /\/user\/dashboard$/);

      // 2) A logout form with the correct action + POST method.
      const form = page.locator(DRAWER_LOGOUT_FORM);
      await expect(
        form,
        `[${mode}] the drawer must show the Sign out logout form`,
      ).toBeVisible();
      await expect(
        form,
        `[${mode}] the logout form action must be route('user.logout')`,
      ).toHaveAttribute("action", /\/user\/logout$/);
      await expect(
        form,
        `[${mode}] the logout form must POST`,
      ).toHaveAttribute("method", /post/i);

      // 3) A present, non-empty CSRF token (the @csrf hidden field).
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

      // The submit control is labelled "Sign out".
      await expect(
        form.getByRole("button", { name: "Sign out" }),
        `[${mode}] the logout form's Sign out button must be present`,
      ).toBeVisible();
    });
  }

  // Destructive: actually submit the logout form and prove it ends the session.
  // Runs last (Playwright executes tests in definition order) so it never pulls
  // the session out from under the non-destructive tests above. On a retry the
  // shared context may already be logged out, so re-establish the session first.
  test("submitting Sign out logs the user out", async ({ page }) => {
    await gotoHeaderPage(page);
    await openDrawer(page);

    let form = page.locator(DRAWER_LOGOUT_FORM);
    // Wait for the drawer's open animation to reveal the logout form. Only if it
    // never appears (a prior/failed attempt already logged this shared context
    // out) do we re-login and re-open the drawer. A bare `.count()` here would
    // race the drawer transition and wrongly re-login while still signed in.
    try {
      await expect(form).toBeVisible({ timeout: 15_000 });
    } catch {
      await loginAsDemo(page);
      await gotoHeaderPage(page);
      await openDrawer(page);
      form = page.locator(DRAWER_LOGOUT_FORM);
      await expect(
        form,
        "the drawer logout form must be present before submitting it",
      ).toBeVisible();
    }

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

    // Reload the header: the drawer's authed controls must be gone and the
    // logged-out CTAs must be back — proof the session was actually ended.
    await gotoHeaderPage(page);
    await openDrawer(page);
    await expect(
      page.locator(DRAWER_LOGOUT_FORM),
      "the logout form must be gone after signing out",
    ).toHaveCount(0);
    await expect(
      page.getByRole("link", { name: "Dashboard" }),
      "the Dashboard link must be gone after signing out",
    ).toHaveCount(0);
    await expect(
      page.getByRole("link", { name: "Login" }),
      "the logged-out Login CTA must return in the drawer after signing out",
    ).toBeVisible();
  });
});
