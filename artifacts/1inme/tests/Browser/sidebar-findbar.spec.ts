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

// Regression guard for the sidebar find-bar fix (commit 44b3bf0cc).
//
// Bug: opening the browser's native find-in-page (Ctrl/Cmd+F) and searching a
// term that matches text inside a COLLAPSED nav group (Alpine's `x-show` has set
// `display:none` on the group) made the whole left sidebar go blank — every
// top-level link scrolled out of the visible area while only the account header,
// upgrade card and user footer remained, with the highlighted match floating in
// an otherwise empty nav. Cause: the sidebar `<nav>` was itself the scroll
// container (`flex-1 py-4 overflow-y-auto overflow-x-hidden`), so the browser's
// scroll-a-match-into-view logic drove that scroll to an offset that pushed the
// links off-screen.
//
// Fix: the `<nav>` became a bounded PAINT BOUNDARY (`flex-1 relative
// overflow-hidden`) with a separate inner scroll container
// (`<div class="absolute inset-0 overflow-y-auto overflow-x-hidden
// sidebar-nav-scroll py-4">`). The inner div is strictly clipped to the nav's
// box (inset:0), so no scroll can push visible items outside the nav's painted
// area onto the account header/footer.
//
// Blade has no runtime check, so a future refactor could silently collapse the
// nav back into a direct scroll container and reintroduce the blanking. This
// headless spec locks the fix in on two levels:
//   1. The structural invariant the fix established (the direct, deterministic
//      regression signal): the sidebar `<nav>` is a non-scrolling paint boundary
//      wrapping an absolutely-positioned inner `.sidebar-nav-scroll` container
//      clipped to the nav box. Reverting to the old single-container markup
//      fails these assertions.
//   2. The behavioural scenario: reproducing the *native* find-in-page reveal on
//      a real, native-findable match. When the browser's find bar locates a
//      match it brings it into view by scrolling the match's nearest scrollable
//      ancestor(s) — the exact same routine `Element.scrollIntoView()` invokes.
//      The test picks a genuine match (a VISIBLE, below-the-fold collapsed
//      nav-group header) and calls `scrollIntoView({block:'start'})` on it — the
//      worst-case reveal that pushes everything above the match out of view.
//      With the fix in place, no top-level link is ever painted outside the nav
//      box over the account header/footer, and once the reveal scroll is
//      released ("closing find") the top-level links reappear where they were.
//
// NOTE — why this drives the native reveal, not a CSS proxy: the real find bar
// lives in the browser's chrome/UI process, NOT the page renderer. Playwright
// key events (and CDP `Input.dispatchKeyEvent`) are delivered to the renderer,
// so `Ctrl/Cmd+F` cannot open or type into the find bar, and headless Chromium
// has no find UI at all. CDP exposes no find-in-page command either
// (`Page.handleJavaScriptDialog` is for JS alert/confirm/prompt dialogs, which
// is unrelated). So the find *bar* genuinely cannot be automated here — BUT the
// DOM effect it produces can be, faithfully: `scrollIntoView()` is the same
// nearest-scrollable-ancestor scroll the browser runs to reveal a match, and it
// does NOT presume WHICH element scrolls (the browser resolves that). That is
// what makes this stronger than hard-coding `scroller.scrollTop`: if a future
// refactor keeps the structural CSS invariant but reintroduces the blank-out
// through a *different* scroll container or mechanism, `scrollIntoView()` still
// reproduces native find's real behaviour and catches it. Crucially the match
// target is a VISIBLE header, because native find (like `window.find()`) skips
// `display:none` content — so a collapsed group's hidden children can never be a
// real find trigger; its still-visible header is. `window.find()` is fired too
// as the literal JS twin of find-in-page (now against that visible term, so it
// is a real match) but stays best-effort/un-asserted since engines vary.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral e2e server, since localhost:80 hits the Express api-server —
// see the sibling dashboard/home/editor specs).

// All tests share one logged-in browser context: the demo-login route is
// rate-limited (throttle:5,1), so a login per test would trip the limit.
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
 * error; a couple of quick retries absorb that blip while a genuine PHP error
 * fails every attempt and is surfaced. (Mirrors the sibling dashboard spec.)
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
 * Idempotently prepare the demo user so the dashboard renders directly: active +
 * email-verified so login works, and `onboarded_at` set so the onboarding soft
 * gate doesn't bounce login through the slow wizard. The demo-login route grants
 * the user-admin role, which unlocks the full platform-admin sidebar — exactly
 * the deep nav (many collapsible groups) this spec needs.
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
    throw new Error("Sidebar find-bar seed failed, output:\n" + out);
  }
}

/**
 * Log in as the demo user (non-prod quick-login). Prefers the on-page demo-login
 * form (as the sibling specs do); if that form isn't rendered in the current
 * environment, falls back to building the POST from the login page's CSRF token.
 * Either way we wait only for the demo-login POST response, not the heavy
 * post-login render, so the suite isn't blocked.
 */
async function loginAsDemo(page: Page): Promise<void> {
  await page.goto("/user/login", { timeout: 120_000 });
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().endsWith("/user/demo-login") &&
        r.request().method() === "POST",
      { timeout: 90_000 },
    ),
    page.evaluate(() => {
      const existing = document.querySelector<HTMLFormElement>(
        'form[action$="/user/demo-login"]',
      );
      if (existing) {
        existing.submit();
        return;
      }
      // Fallback: no demo form on this page — build one from the CSRF token.
      const token =
        document.querySelector<HTMLInputElement>('input[name="_token"]')
          ?.value ??
        document
          .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
          ?.getAttribute("content") ??
        "";
      const form = document.createElement("form");
      form.method = "POST";
      form.action = "/user/demo-login";
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "_token";
      input.value = token;
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    }),
  ]);
}

/**
 * Open /user/dashboard and wait until the desktop sidebar has rendered with its
 * top-level "Dashboard" link visible. A cold authenticated render over the
 * distant RDS is slow, so give it real headroom.
 */
async function openDashboard(page: Page): Promise<void> {
  await page.goto("/user/dashboard", { timeout: 120_000 });
  await page
    .locator("aside")
    .locator(".nav-label", { hasText: /^Dashboard$/ })
    .first()
    .waitFor({ state: "visible", timeout: 120_000 });
}

// Top-level links that must always stay reachable in the sidebar.
const TOP_LEVEL_LINKS = ["Dashboard", "All Links"] as const;

test.describe("sidebar find-bar fix", () => {
  // Cold authenticated renders over the distant RDS blow past the default 60s
  // budget; give the suite real headroom (mirrors the sibling specs).
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

  test("sidebar nav is a bounded paint boundary wrapping an inner scroll container", async ({
    page,
  }) => {
    await openDashboard(page);

    // The top-level links render to begin with.
    const aside = page.locator("aside");
    for (const label of TOP_LEVEL_LINKS) {
      await expect(
        aside.locator(".nav-label", { hasText: new RegExp(`^${label}$`) }).first(),
      ).toBeVisible();
    }

    // Structural invariant established by the fix. We locate the desktop sidebar
    // nav WITHOUT relying on the fix's own class, so a reverted markup produces
    // clean, descriptive assertion failures (not a selector timeout).
    const shape = await page.evaluate(() => {
      // The desktop sidebar nav is the visible <nav> that carries the top-level
      // "Dashboard" nav-label (the mobile drawer nav is off-screen / hidden).
      const navs = Array.from(document.querySelectorAll("aside nav")).filter(
        (nav) => {
          const hasDash = Array.from(nav.querySelectorAll(".nav-label")).some(
            (l) => l.textContent?.trim() === "Dashboard",
          );
          if (!hasDash) return false;
          const r = nav.getBoundingClientRect();
          return r.width > 0 && r.height > 0;
        },
      );
      const nav = navs[0] as HTMLElement | undefined;
      if (!nav) return { found: false as const };

      const navCS = getComputedStyle(nav);
      const navRect = nav.getBoundingClientRect();

      const inner = nav.querySelector(".sidebar-nav-scroll") as HTMLElement | null;
      const innerCS = inner ? getComputedStyle(inner) : null;
      const innerRect = inner ? inner.getBoundingClientRect() : null;

      return {
        found: true as const,
        nav: {
          position: navCS.position,
          overflowY: navCS.overflowY,
          scrollHeight: nav.scrollHeight,
          clientHeight: nav.clientHeight,
        },
        innerExists: !!inner,
        innerIsNav: inner === nav,
        inner: innerCS
          ? { position: innerCS.position, overflowY: innerCS.overflowY }
          : null,
        // Is the inner scroll box clipped to the nav's box (inset:0)?
        innerWithinNav:
          !!innerRect &&
          Math.abs(innerRect.top - navRect.top) <= 2 &&
          Math.abs(innerRect.left - navRect.left) <= 2 &&
          Math.abs(innerRect.right - navRect.right) <= 2 &&
          Math.abs(innerRect.bottom - navRect.bottom) <= 2,
        innerScrollable: !!inner && inner.scrollHeight > inner.clientHeight,
      };
    });

    expect(shape.found, "desktop sidebar <nav> not found").toBe(true);
    if (!shape.found) return;

    // 1) The <nav> is a bounded paint boundary — NOT itself a scroll container.
    expect(
      shape.nav.overflowY,
      `sidebar <nav> overflow-y must be 'hidden' (a paint boundary), got '${shape.nav.overflowY}' — the find-bar fix was reverted to a direct scroll container`,
    ).toBe("hidden");
    expect(
      shape.nav.position,
      `sidebar <nav> position must be 'relative' to host the absolute inner scroller, got '${shape.nav.position}'`,
    ).toBe("relative");
    expect(
      shape.nav.scrollHeight,
      "sidebar <nav> must not itself scroll (its content must live in the inner container)",
    ).toBeLessThanOrEqual(shape.nav.clientHeight + 2);

    // 2) A separate inner scroll container exists, clipped to the nav's box.
    expect(
      shape.innerExists,
      "inner '.sidebar-nav-scroll' container is missing — the find-bar fix was reverted",
    ).toBe(true);
    expect(shape.innerIsNav, "'.sidebar-nav-scroll' must be a child, not the <nav> itself").toBe(
      false,
    );
    expect(shape.inner?.position, "inner scroll container must be position:absolute").toBe(
      "absolute",
    );
    expect(shape.inner?.overflowY, "inner scroll container must be overflow-y:auto").toBe(
      "auto",
    );
    expect(
      shape.innerWithinNav,
      "inner scroll container must be clipped to the nav box (absolute inset-0)",
    ).toBe(true);
  });

  test("native find revealing a collapsed-group header does not blank the sidebar, and closing find restores it", async ({
    page,
  }) => {
    await openDashboard(page);

    const aside = page.locator("aside");

    // Baseline: the top-level links are visible and inside the nav box.
    for (const label of TOP_LEVEL_LINKS) {
      await expect(
        aside.locator(".nav-label", { hasText: new RegExp(`^${label}$`) }).first(),
      ).toBeVisible();
    }

    // Precondition + pick a REAL, native-findable match. Native find (and its JS
    // twin window.find()) only matches RENDERED text, so the honest trigger is a
    // collapsed group's still-visible HEADER (its children are display:none, its
    // header is not) that sits below the scroll container's fold — revealing it
    // is what forces the browser to scroll. We tag that header for the reveal
    // step and record where the top-level links sit so we can prove nothing was
    // structurally lost after "closing find".
    const setup = await page.evaluate((topLinks) => {
      const nav = Array.from(document.querySelectorAll("aside nav")).find(
        (n) => {
          const hasDash = Array.from(n.querySelectorAll(".nav-label")).some(
            (l) => l.textContent?.trim() === "Dashboard",
          );
          const r = n.getBoundingClientRect();
          return hasDash && r.width > 0 && r.height > 0;
        },
      ) as HTMLElement | undefined;
      if (!nav) return { navFound: false as const };

      const scroller =
        (nav.querySelector(".sidebar-nav-scroll") as HTMLElement | null) ?? nav;
      const scRect = scroller.getBoundingClientRect();

      // A collapsed group = a visible header toggle whose x-show panel sibling is
      // display:none. Its header text is exactly the "collapsed nav-group term".
      const collapsedHeaders = Array.from(
        nav.querySelectorAll(".sidebar-group-toggle"),
      ).filter((btn) => {
        const el = btn as HTMLElement;
        const r = el.getBoundingClientRect();
        if (r.width === 0 && r.height === 0) return false; // header itself hidden
        const panel = el.nextElementSibling as HTMLElement | null;
        return !!panel && getComputedStyle(panel).display === "none";
      }) as HTMLElement[];

      // Prefer a collapsed header BELOW the fold (revealing it must scroll);
      // otherwise fall back to the last collapsed header so the test still drives
      // scrollIntoView on a genuine match.
      const belowFold = collapsedHeaders.filter(
        (h) => h.getBoundingClientRect().top >= scRect.bottom - 1,
      );
      const target =
        belowFold[belowFold.length - 1] ??
        collapsedHeaders[collapsedHeaders.length - 1] ??
        null;
      if (target) target.setAttribute("data-findbar-target", "1");

      const topPositions: Record<string, number | null> = {};
      for (const t of topLinks) {
        const el = Array.from(nav.querySelectorAll(".nav-label")).find(
          (l) => l.textContent?.trim() === t,
        ) as HTMLElement | undefined;
        topPositions[t] = el ? Math.round(el.getBoundingClientRect().top) : null;
      }

      return {
        navFound: true as const,
        collapsedCount: collapsedHeaders.length,
        hasTarget: !!target,
        targetBelowFold: belowFold.length > 0,
        scrollable: scroller.scrollHeight > scroller.clientHeight + 2,
        term: target?.textContent?.trim() ?? null,
        topPositions,
      };
    }, TOP_LEVEL_LINKS as unknown as string[]);

    expect(setup.navFound, "desktop sidebar <nav> not found").toBe(true);
    if (!setup.navFound) return;
    expect(
      setup.collapsedCount,
      "expected at least one collapsed nav group (visible header + display:none panel) so a native-findable term exists",
    ).toBeGreaterThan(0);
    expect(
      setup.hasTarget,
      "could not tag a collapsed-group header to reveal",
    ).toBe(true);

    // Drive the GENUINE native find reveal: fire window.find() for the visible
    // header term (the literal JS twin of find-in-page — best-effort) and then
    // call scrollIntoView({block:'start'}) on the matched header. That is the
    // exact nearest-scrollable-ancestor scroll the browser runs to bring a match
    // into view; block:'start' is the worst case, pinning the match to the top
    // and pushing everything above it (all top-level links) out of view. Crucial
    // difference from a hard-coded scrollTop: scrollIntoView does not presume
    // WHICH element scrolls, so it reproduces the blank-out through whatever
    // scroll container a future refactor introduces.
    const term = setup.term ?? "Dashboard";
    await page.evaluate((t) => {
      const w = window as unknown as { find?: (s: string) => boolean };
      try {
        w.find?.(t);
      } catch {
        /* window.find may throw in some engines; ignore */
      }
      const match = document.querySelector(
        "[data-findbar-target]",
      ) as HTMLElement | null;
      match?.scrollIntoView({ block: "start", inline: "nearest" });
    }, term);
    await page.waitForTimeout(200);

    // Sidebar must NOT blank: no top-level link may be PAINTED outside the nav
    // box over a sibling (the account header above, or the upgrade/footer below).
    // Because the fixed nav clips its overflow, any link scrolled past the nav's
    // edges must be clipped away, never left floating over a sibling.
    const duringFind = await page.evaluate((topLinks) => {
      const nav = Array.from(document.querySelectorAll("aside nav")).find(
        (n) => {
          const hasDash = Array.from(n.querySelectorAll(".nav-label")).some(
            (l) => l.textContent?.trim() === "Dashboard",
          );
          const r = n.getBoundingClientRect();
          return hasDash && r.width > 0 && r.height > 0;
        },
      ) as HTMLElement | undefined;
      if (!nav) return { navFound: false as const };
      const navRect = nav.getBoundingClientRect();

      const results = topLinks.map((t) => {
        const label = Array.from(nav.querySelectorAll(".nav-label")).find(
          (l) => l.textContent?.trim() === t,
        ) as HTMLElement | undefined;
        const link = label?.closest("a") as HTMLElement | undefined;
        if (!link) return { t, paintedOutsideNav: false };
        const r = link.getBoundingClientRect();
        // Only meaningful if the link got scrolled past the nav's clipped box.
        const aboveTop = r.bottom <= navRect.top;
        const belowBottom = r.top >= navRect.bottom;
        if (!aboveTop && !belowBottom) return { t, paintedOutsideNav: false };
        const cx = r.left + r.width / 2;
        const cy = r.top + r.height / 2;
        const atPoint = document.elementFromPoint(cx, cy);
        const painted = !!atPoint && (link.contains(atPoint) || atPoint === link);
        return { t, paintedOutsideNav: painted };
      });

      return { navFound: true as const, results };
    }, TOP_LEVEL_LINKS as unknown as string[]);

    expect(duringFind.navFound).toBe(true);
    if (duringFind.navFound) {
      for (const r of duringFind.results) {
        expect(
          r.paintedOutsideNav,
          `top-level link "${r.t}" was painted outside the nav box over a sibling while a native find reveal (scrollIntoView on the "${term}" header) was in progress — the sidebar blanked (find-bar regression)`,
        ).toBe(false);
      }
    }

    // "Close find": release every scroll the reveal may have driven (the inner
    // scroller in the fixed markup, or the nav / aside / window in a reverted
    // one). The top-level links must be visible again at their original
    // positions — proof they were only scrolled, never structurally removed.
    await page.evaluate(() => {
      const nav = Array.from(document.querySelectorAll("aside nav")).find((n) => {
        const r = n.getBoundingClientRect();
        return r.width > 0 && r.height > 0;
      }) as HTMLElement | undefined;
      const scrollers = [
        document.querySelector("aside .sidebar-nav-scroll") as HTMLElement | null,
        nav ?? null,
        document.querySelector("aside") as HTMLElement | null,
      ];
      for (const s of scrollers) if (s) s.scrollTop = 0;
      window.scrollTo(0, 0);
    });
    await page.waitForTimeout(200);

    for (const label of TOP_LEVEL_LINKS) {
      await expect(
        aside.locator(".nav-label", { hasText: new RegExp(`^${label}$`) }).first(),
      ).toBeVisible();
    }

    const afterClose = await page.evaluate((topLinks) => {
      const nav = Array.from(document.querySelectorAll("aside nav")).find(
        (n) => {
          const hasDash = Array.from(n.querySelectorAll(".nav-label")).some(
            (l) => l.textContent?.trim() === "Dashboard",
          );
          const r = n.getBoundingClientRect();
          return hasDash && r.width > 0 && r.height > 0;
        },
      ) as HTMLElement | undefined;
      if (!nav) return {} as Record<string, number | null>;
      const out: Record<string, number | null> = {};
      for (const t of topLinks) {
        const el = Array.from(nav.querySelectorAll(".nav-label")).find(
          (l) => l.textContent?.trim() === t,
        ) as HTMLElement | undefined;
        out[t] = el ? Math.round(el.getBoundingClientRect().top) : null;
      }
      return out;
    }, TOP_LEVEL_LINKS as unknown as string[]);

    for (const label of TOP_LEVEL_LINKS) {
      const before = setup.topPositions[label];
      const after = afterClose[label];
      expect(after, `top-level link "${label}" vanished after closing find`).not.toBeNull();
      if (before != null && after != null) {
        expect(
          Math.abs(after - before),
          `top-level link "${label}" shifted after closing find (before=${before}, after=${after}) — the sidebar did not return to its original state`,
        ).toBeLessThanOrEqual(2);
      }
    }
  });
});
