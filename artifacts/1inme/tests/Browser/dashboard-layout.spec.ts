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
import { loginAsDemo } from "./login-as-demo";

// Regression guard for the redesigned user dashboard (task: "Lock in the new
// dashboard layout so future edits can't silently break it").
//
// The dashboard was rebuilt into a glass "bento" command center: a live-pulse
// hero tile anchoring the grid, a set of metric/action tiles, and three
// Alpine-driven view tabs (Overview / Traffic / Growth) that swap the panel
// below. It was verified once by hand (blade compile + a one-off Playwright
// pass), but nothing stops a future Blade/CSS edit from silently dropping the
// hero, breaking a tile, wiring the tabs wrong, or reintroducing a retired
// purple accent (the brand moved to a blue ramp). Because Blade has no runtime
// check, that would only surface when a user reports it.
//
// This headless spec logs in as the demo user, lands on /user/dashboard, and
// asserts the layout invariant:
//   1. the live-pulse hero tile renders,
//   2. the three headline metric/action tiles render (Total Clicks, Recent
//      Links, Quick Actions),
//   3. the Overview / Traffic / Growth tabs actually switch the visible panel,
//   4. no purple accent appears — neither in the rendered (computed) colors nor
//      as a retired purple token in the dashboard markup.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral e2e server, since localhost:80 hits the Express api-server —
// see the sibling home/editor specs).

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

// The retired purple ramp (must never creep back onto a primary UI surface).
// Kept in lockstep with scripts/src/check-brand-color.ts BANNED_HEX_PATTERN and
// its rgb equivalents; the brand accent is now blue (#3d6bff).
const RETIRED_PURPLE = {
  hexes: ["7c3aed", "8b5cf6", "a78bfa"],
  rgb: [
    [124, 58, 237],
    [139, 92, 246],
    [167, 139, 250],
  ] as const,
};

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
 * Idempotently prepare the demo user so the dashboard renders directly: active
 * + email-verified so login works, and `onboarded_at` set so the
 * RedirectToOnboarding soft gate doesn't bounce the login through the slow
 * onboarding wizard (see memory "1inme browser e2e fast login"). Done via
 * `php artisan tinker` so the spec is self-bootstrapping on a fresh runner.
 */
function seedFixtures(): void {
  // NOTE: this string is passed straight to `tinker --execute=`. In a JS
  // template literal, `\\` becomes the single backslash PHP namespaces need
  // (e.g. App\Modules\...), while `$var` stays literal.
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
    throw new Error("Dashboard layout seed failed, output:\n" + out);
  }
}

/**
 * Log in as the demo user (non-prod quick-login). Submits the demo-login form
 * via JS rather than a click, and waits only for the demo-login POST response
 * (not the redirect target render) so the heavy post-login dashboard render
 * never blocks the suite (see memory "1inme browser e2e fast login").
 */
/**
 * Open /user/dashboard and wait until the bento hero tile is attached AND the
 * dashboard-tabs Alpine component has hydrated (tab switching is Alpine-driven).
 * A cold authenticated render over the distant RDS is slow, so give it real
 * headroom.
 */
async function openDashboard(page: Page): Promise<void> {
  await page.goto("/user/dashboard", { timeout: 120_000 });
  await page.locator(".bento-hero").waitFor({ state: "attached", timeout: 120_000 });
  // Wait for Alpine to hydrate the tablist so :aria-selected / x-show bindings
  // are live before we drive the tabs.
  await page.waitForFunction(
    () => {
      const A = (
        window as unknown as {
          Alpine?: { $data: (el: Element) => Record<string, unknown> };
        }
      ).Alpine;
      if (!A) return false;
      const tab = document.querySelector('[role="tab"]');
      if (!tab) return false;
      // Walk up to the x-data root and confirm the `tab` state exists.
      let el: Element | null = tab;
      while (el) {
        try {
          const data = A.$data(el);
          if (data && "tab" in data) return true;
        } catch {
          // not an Alpine root; keep walking up
        }
        el = el.parentElement;
      }
      return false;
    },
    undefined,
    { timeout: 120_000 },
  );
}

/**
 * Scan the dashboard for any RENDERED purple accent. Walks every element inside
 * the bento stage, reads its resolved color / background / border / outline
 * colors (including the ::before and ::after pseudo-elements that draw the tile
 * accent bars and glow orbs), converts each to HSL, and flags any that lands in
 * the purple hue band with real saturation. The brand blue (#3d6bff, hue ~226)
 * sits safely below the 250° floor, so this isolates the retired purple ramp
 * (hue ~255-262) without false-positiving on the intended blue/cyan/emerald.
 */
async function findRenderedPurple(
  page: Page,
): Promise<{ selectorHint: string; prop: string; value: string }[]> {
  return page.evaluate(() => {
    const parse = (c: string): [number, number, number] | null => {
      const m = c.match(/rgba?\(([^)]+)\)/);
      if (!m) return null;
      const parts = m[1].split(/[,\s/]+/).filter(Boolean).map(Number);
      if (parts.length < 3 || parts.some((n) => Number.isNaN(n))) return null;
      // If it's fully transparent it isn't rendered as color.
      if (parts.length >= 4 && parts[3] === 0) return null;
      return [parts[0], parts[1], parts[2]];
    };
    const toHsl = ([r, g, b]: [number, number, number]) => {
      const rn = r / 255,
        gn = g / 255,
        bn = b / 255;
      const max = Math.max(rn, gn, bn),
        min = Math.min(rn, gn, bn);
      const l = (max + min) / 2;
      const d = max - min;
      let h = 0;
      let s = 0;
      if (d !== 0) {
        s = d / (1 - Math.abs(2 * l - 1));
        switch (max) {
          case rn:
            h = 60 * (((gn - bn) / d) % 6);
            break;
          case gn:
            h = 60 * ((bn - rn) / d + 2);
            break;
          default:
            h = 60 * ((rn - gn) / d + 4);
        }
      }
      if (h < 0) h += 360;
      return { h, s, l };
    };
    const isPurple = (c: string): boolean => {
      const rgb = parse(c);
      if (!rgb) return false;
      const { h, s, l } = toHsl(rgb);
      // Purple hue band with meaningful saturation, excluding near-black/white.
      return h >= 250 && h <= 320 && s >= 0.25 && l >= 0.12 && l <= 0.92;
    };
    const props = [
      "color",
      "backgroundColor",
      "borderTopColor",
      "borderRightColor",
      "borderBottomColor",
      "borderLeftColor",
      "outlineColor",
    ] as const;

    const offenders: { selectorHint: string; prop: string; value: string }[] =
      [];
    const stage = document.querySelector(".bento-stage");
    if (!stage) return offenders;
    const hintFor = (el: Element): string => {
      const cls =
        typeof el.className === "string" && el.className
          ? "." + el.className.trim().split(/\s+/).slice(0, 2).join(".")
          : "";
      return el.tagName.toLowerCase() + cls;
    };
    const check = (el: Element, pseudo: string | undefined) => {
      const cs = getComputedStyle(el, pseudo);
      for (const p of props) {
        const v = cs[p as keyof CSSStyleDeclaration] as unknown as string;
        if (typeof v === "string" && isPurple(v)) {
          offenders.push({
            selectorHint: hintFor(el) + (pseudo ?? ""),
            prop: p,
            value: v,
          });
        }
      }
    };
    const all = stage.querySelectorAll("*");
    for (const el of Array.from(all)) {
      check(el, undefined);
      check(el, "::before");
      check(el, "::after");
    }
    return offenders;
  });
}

test.describe("dashboard bento layout", () => {
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
    await sharedContext?.close();
  });

  test("the hero tile and the headline metric/action tiles render", async ({
    page,
  }) => {
    await openDashboard(page);

    // Scope every content assertion to the dashboard stage: the surrounding app
    // chrome (sidebar nav) repeats labels like "Backlinks" that would otherwise
    // collide with a bare page-level text match.
    const stage = page.locator(".bento-stage");

    // Live-pulse hero tile anchors the whole grid.
    await expect(stage.locator(".bento-hero")).toBeVisible();
    await expect(stage.locator(".bento-hero .pulse-orb")).toBeVisible();

    // The three headline metric/action tiles (Overview tab is the default).
    await expect(
      stage.getByText("Total Clicks", { exact: true }).first(),
    ).toBeVisible();
    await expect(
      stage.getByRole("heading", { name: "Recent Links" }),
    ).toBeVisible();
    await expect(
      stage.getByRole("heading", { name: "Quick Actions" }),
    ).toBeVisible();

    // The grid actually laid out multiple bento tiles (not a collapsed render).
    expect(await stage.locator(".bento-tile").count()).toBeGreaterThanOrEqual(3);
  });

  test("the Overview / Traffic / Growth tabs switch the visible panel", async ({
    page,
  }) => {
    await openDashboard(page);

    // Scope to the dashboard stage so the surrounding app chrome (sidebar nav,
    // which repeats labels like "Backlinks") can't shadow the panel markers.
    const stage = page.locator(".bento-stage");

    const overviewTab = stage.getByRole("tab", { name: "Overview" });
    const trafficTab = stage.getByRole("tab", { name: "Traffic" });
    const growthTab = stage.getByRole("tab", { name: "Growth" });
    await expect(overviewTab).toBeVisible();
    await expect(trafficTab).toBeVisible();
    await expect(growthTab).toBeVisible();

    // Panel-identifying content unique to each tab.
    const overviewMarker = stage.getByRole("heading", { name: "Quick Actions" });
    const trafficMarker = stage.getByRole("heading", {
      name: "Channel breakdown",
    });
    const growthMarker = stage.getByText("Backlinks", { exact: true }).first();

    // Overview is the default view for the demo user (no ?channel filter).
    await expect(overviewTab).toHaveAttribute("aria-selected", "true");
    await expect(overviewMarker).toBeVisible();
    await expect(trafficMarker).toBeHidden();
    await expect(growthMarker).toBeHidden();

    // Switch to Traffic: its panel shows, Overview's hides.
    await trafficTab.click();
    await expect(trafficTab).toHaveAttribute("aria-selected", "true");
    await expect(trafficMarker).toBeVisible();
    await expect(overviewMarker).toBeHidden();

    // Switch to Growth: its panel shows, Traffic's hides.
    await growthTab.click();
    await expect(growthTab).toHaveAttribute("aria-selected", "true");
    await expect(growthMarker).toBeVisible();
    await expect(trafficMarker).toBeHidden();
  });

  test("no purple accent appears on the dashboard", async ({ page }) => {
    await openDashboard(page);

    // 1) Nothing RENDERS as purple across all three tabs. x-cloak hides the
    //    inactive panels' colors from the pseudo-element scan, so reveal each
    //    tab in turn and scan while it's visible.
    for (const tabName of ["Overview", "Traffic", "Growth"] as const) {
      await page.getByRole("tab", { name: tabName }).click();
      // Let Alpine flip the x-show display before we read computed styles.
      await page.waitForTimeout(150);
      const offenders = await findRenderedPurple(page);
      expect(
        offenders,
        `purple accent rendered on the ${tabName} tab: ` +
          offenders
            .map((o) => `${o.selectorHint} ${o.prop}=${o.value}`)
            .join(", "),
      ).toEqual([]);
    }

    // 2) No retired purple TOKEN is present in the dashboard markup — this
    //    catches purple hidden in inline gradients / classes on a currently
    //    non-visible tile that the computed-color scan can't reach. Mirrors the
    //    source-level `brand-color` guard.
    const markup = await page.locator(".bento-stage").evaluate(
      (el) => (el as HTMLElement).outerHTML,
    );
    const hexRe = new RegExp(`#?\\b(${RETIRED_PURPLE.hexes.join("|")})\\b`, "i");
    expect(hexRe.test(markup), "retired purple hex in dashboard markup").toBe(
      false,
    );
    const classRe =
      /\b(?:bg|text|border|from|via|to|ring|fill|stroke|shadow|outline|decoration|caret|accent)-(?:purple|violet)-\d/i;
    expect(
      classRe.test(markup),
      "retired purple/violet Tailwind utility class in dashboard markup",
    ).toBe(false);
    for (const [r, g, b] of RETIRED_PURPLE.rgb) {
      const rgbRe = new RegExp(`rgba?\\(\\s*${r}\\s*[, ]\\s*${g}\\s*[, ]\\s*${b}\\b`, "i");
      expect(
        rgbRe.test(markup),
        `retired purple rgb(${r}, ${g}, ${b}) in dashboard markup`,
      ).toBe(false);
    }
  });
});
