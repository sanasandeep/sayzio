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

// Regression guard for the logged-in account dropdown in the Sayzio PUBLIC
// header (task: "Catch header account-menu styling breaks before they ship").
//
// The dropdown just had a regression (Task #3278): the panel reused the floating
// navbar's `.mkt-navbar-bar` width/centering rules, which are responsive
// percentage widths (80–92% of the container). Applied to the tiny relatively-
// positioned account-button wrapper, that collapsed the panel to a few dozen
// pixels — squashing it and wrapping the "Sign out" label onto two lines. The
// fix decoupled the panel chrome into `.mkt-acct-menu` (background/blur only) so
// the Tailwind `w-44` narrow width applies cleanly (see marketing-anim.css and
// header.blade.php ~line 255-273). Nothing stops a future CSS/class edit from
// silently re-breaking it, so this headless spec pins the invariant.
//
// It logs in as the demo user, lands on a marketing page that renders the shared
// public header, clicks the account (user) icon in the DESKTOP header, and
// asserts the dropdown:
//   1. opens at its intended narrow width (Tailwind w-44 = 176px), i.e. NOT
//      squashed to a sliver and NOT stretched to the navbar's width,
//   2. does NOT carry the navbar's `.mkt-navbar-bar` class (the exact #3278
//      regression), and its width stays far below the navbar bar's width,
//   3. renders "Sign out" on a single line (one client rect, no overflow).
// Checked in BOTH dark and light mode (theme = the server `light-mode` html
// class — see memory user-layout-theme-in-playwright).
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral e2e server, since localhost:80 hits the Express api-server —
// see the sibling home/dashboard specs).

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

// A light marketing page that renders `public.layouts.site` (and thus the shared
// `public.partials.header`). Deliberately not the home page — its cold render is
// the heaviest in the suite. `/contact` is already warmed by the runner.
const HEADER_PAGE = "/contact";

// Tailwind `w-44` = 11rem = 176px. Allow a little slack for borders/rounding.
const EXPECTED_WIDTH = 176;
const WIDTH_MIN = 160; // below this = squashed (the #3278 signature)
const WIDTH_MAX = 220; // above this = stretched toward the navbar width

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
    throw new Error("Account-menu seed failed, output:\n" + out);
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

type AcctMenuState = {
  panelWidth: number;
  navbarWidth: number;
  hasNavbarClass: boolean;
  overflowsHorizontally: boolean;
  signOutRectCount: number;
  signOutText: string;
};

/**
 * Open the desktop account dropdown and read back the geometry that proves it is
 * neither squashed nor stretched, plus whether "Sign out" wrapped. The number of
 * client rects for the label is the wrap signal: an inline element that stays on
 * one visual line has exactly one client rect; wrapping to two lines yields two.
 */
async function readAcctMenu(page: Page): Promise<AcctMenuState> {
  const trigger = page.getByRole("button", { name: "Account menu" });
  await expect(trigger, "the desktop account-menu trigger must be visible").toBeVisible();
  await trigger.click();

  const panel = page.locator(".mkt-acct-menu");
  await expect(panel, "the account dropdown panel must open").toBeVisible();

  const state = await panel.evaluate((el) => {
    const rect = el.getBoundingClientRect();
    const navbar = document.querySelector<HTMLElement>(".mkt-navbar-bar");
    const signOut = Array.from(el.querySelectorAll<HTMLElement>("span")).find(
      (s) => s.textContent?.trim() === "Sign out",
    );
    return {
      panelWidth: Math.round(rect.width),
      navbarWidth: navbar
        ? Math.round(navbar.getBoundingClientRect().width)
        : 0,
      hasNavbarClass: el.classList.contains("mkt-navbar-bar"),
      overflowsHorizontally: el.scrollWidth > el.clientWidth + 1,
      signOutRectCount: signOut ? signOut.getClientRects().length : 0,
      signOutText: signOut ? signOut.textContent?.trim() || "" : "",
    };
  });

  // Close it again so the next mode starts from a clean state.
  await trigger.click();
  await expect(panel).toBeHidden();

  return state;
}

test.describe("public header account dropdown", () => {
  // Cold authenticated renders over the distant RDS push past the default 60s
  // budget; give the suite real headroom (mirrors sibling specs).
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

  for (const mode of ["dark", "light"] as const) {
    test(`opens at its narrow width with "Sign out" on one line (${mode} mode)`, async ({
      page,
    }) => {
      await page.goto(HEADER_PAGE, { timeout: 120_000 });
      // The account dropdown only exists in the desktop header when logged in;
      // its trigger presence also confirms the @auth branch rendered.
      await page
        .getByRole("button", { name: "Account menu" })
        .waitFor({ state: "visible", timeout: 120_000 });

      await setTheme(page, mode);

      const s = await readAcctMenu(page);

      // 1) The panel must NOT reuse the navbar bar's class (the #3278 root
      //    cause) — that class carries the responsive percentage widths that
      //    squashed it.
      expect(
        s.hasNavbarClass,
        `[${mode}] account dropdown must not carry the .mkt-navbar-bar class`,
      ).toBe(false);

      // 2) It opens at its intended narrow width (w-44 ≈ 176px): wide enough to
      //    not be squashed, narrow enough to not have stretched.
      expect(
        s.panelWidth,
        `[${mode}] account dropdown width ${s.panelWidth}px is out of the expected ~${EXPECTED_WIDTH}px band (squashed or stretched)`,
      ).toBeGreaterThanOrEqual(WIDTH_MIN);
      expect(
        s.panelWidth,
        `[${mode}] account dropdown width ${s.panelWidth}px is out of the expected ~${EXPECTED_WIDTH}px band (squashed or stretched)`,
      ).toBeLessThanOrEqual(WIDTH_MAX);

      // 3) And it stays far below the full navbar bar width (decoupled sizing).
      if (s.navbarWidth > 0) {
        expect(
          s.panelWidth,
          `[${mode}] account dropdown (${s.panelWidth}px) should be much narrower than the navbar bar (${s.navbarWidth}px)`,
        ).toBeLessThan(s.navbarWidth / 2);
      }

      // 4) "Sign out" sits on a single line: exactly one client rect and no
      //    horizontal overflow inside the panel.
      expect(
        s.signOutText,
        `[${mode}] the "Sign out" label must be present in the dropdown`,
      ).toBe("Sign out");
      expect(
        s.signOutRectCount,
        `[${mode}] "Sign out" wrapped onto ${s.signOutRectCount} lines (expected 1) — the panel is too narrow`,
      ).toBe(1);
      expect(
        s.overflowsHorizontally,
        `[${mode}] account dropdown content overflows horizontally`,
      ).toBe(false);
    });
  }
});
