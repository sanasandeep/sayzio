import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Page,
} from "@playwright/test";

// Regression guard for the sidebar find-bar fix (commit 44b3bf0cc) on the ADMIN
// back-office layout.
//
// The find-bar fix changed BOTH the user sidebar (user/layouts/app.blade.php)
// and the admin sidebar (admin/partials/sidebar.blade.php) to the same
// paint-boundary structure: the `<nav>` became a bounded, non-scrolling paint
// boundary (`flex-1 relative overflow-hidden`) wrapping a separate inner scroll
// container (`<div class="absolute inset-0 overflow-y-auto overflow-x-hidden
// sidebar-nav-scroll py-4">`). The inner div is strictly clipped to the nav's
// box (inset:0), so the browser's scroll-a-match-into-view logic (native
// find-in-page) can never push a top-level link off the nav's painted area and
// over the account header/footer, blanking the sidebar.
//
// The sibling `sidebar-findbar.spec.ts` locks this in for the USER sidebar only.
// The admin layout shares the exact same markup pattern but nothing guarded it,
// so an admin-layout refactor could silently collapse the admin `<nav>` back
// into a direct scroll container and reintroduce the blanking there. This spec
// closes that gap on two levels:
//   1. The structural invariant the fix established (the direct, deterministic
//      regression signal): the admin sidebar `<nav>` is a non-scrolling paint
//      boundary wrapping an absolutely-positioned inner `.sidebar-nav-scroll`
//      container clipped to the nav box. Reverting to the old single-container
//      markup fails these assertions.
//   2. The behavioural scenario: the admin nav is long enough to scroll, so we
//      drive the find-in-page reveal scroll (the browser scrolls the match's
//      nearest scrollable ancestor to bring an off-screen match into view) and
//      assert no top-level admin link is ever painted over the account header,
//      and that releasing the scroll ("closing find") restores the links.
//
// NOTE on the admin sidebar shape: unlike the user sidebar it has no collapsible
// (Alpine x-show) nav groups — its links are flat. So there is no
// "display:none match inside a collapsed group" here; the relevant precondition
// is simply that the nav OVERFLOWS (scrollHeight > clientHeight), which is what
// makes the find-reveal scroll meaningful. window.find() is fired too as the
// literal JS twin of find-in-page, but it is best-effort only and not asserted.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral e2e server, since localhost:80 hits the Express api-server —
// see the sibling specs).

// All tests share one logged-in (admin-guard) browser context.
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
 * fails every attempt and is surfaced. (Mirrors the sibling specs.)
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
 * Idempotently ensure the demo admin exists and is active so the admin
 * demo-login route (AuthController::demoLogin, non-prod only) logs straight into
 * the back-office dashboard as a super-admin — which renders the FULL admin
 * sidebar (every section), exactly the deep, overflowing nav this spec needs.
 */
function seedFixtures(): void {
  const php = `
use App\\Modules\\Admin\\Models\\Admin;
use App\\Modules\\Admin\\Models\\Role;
use Illuminate\\Support\\Facades\\Hash;

$role = Role::firstOrCreate(
  ['slug' => 'super-admin'],
  ['name' => 'Super Admin', 'guard' => 'admin']
);
$a = Admin::where('email', 'sayzioapp@gmail.com')->first();
if (!$a) {
  $a = Admin::create([
    'name' => 'Admin', 'email' => 'sayzioapp@gmail.com',
    'password' => Hash::make('password'), 'role_id' => $role->id,
    'status' => 'active',
  ]);
}
$a->status = 'active';
if (!$a->role_id) { $a->role_id = $role->id; }
$a->save();

echo 'SEED_OK';
`.trim();

  const out = runTinkerSeed(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("Admin sidebar find-bar seed failed, output:\n" + out);
  }
}

/**
 * Log in through the non-prod admin demo-login route. We build the POST from the
 * admin login page's CSRF token and wait only for the demo-login POST response,
 * not the heavy post-login render, so the suite isn't blocked.
 */
async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto("/admin/login", { timeout: 120_000 });
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().endsWith("/admin/demo-login") &&
        r.request().method() === "POST",
      { timeout: 90_000 },
    ),
    page.evaluate(() => {
      const token =
        document.querySelector<HTMLInputElement>('input[name="_token"]')
          ?.value ??
        document
          .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
          ?.getAttribute("content") ??
        "";
      const form = document.createElement("form");
      form.method = "POST";
      form.action = "/admin/demo-login";
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
 * Open the admin dashboard and wait until the desktop sidebar has rendered with
 * its top-level "Dashboard" link visible. A cold authenticated render over the
 * distant RDS is slow, so give it real headroom.
 */
async function openAdminDashboard(page: Page): Promise<void> {
  await page.goto("/admin", { timeout: 120_000 });
  await page
    .locator("aside")
    .locator(".nav-label", { hasText: /^Dashboard$/ })
    .first()
    .waitFor({ state: "visible", timeout: 120_000 });
}

// Top-level links that must always stay reachable in the admin sidebar.
const TOP_LEVEL_LINKS = ["Dashboard", "Users"] as const;

test.describe("admin sidebar find-bar fix", () => {
  // Cold authenticated renders over the distant RDS blow past the default 60s
  // budget; give the suite real headroom (mirrors the sibling specs).
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(async ({ browser }) => {
    seedFixtures();
    sharedContext = await browser.newContext();
    const page = await sharedContext.newPage();
    await loginAsAdmin(page);
    await page.close();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  test("admin sidebar nav is a bounded paint boundary wrapping an inner scroll container", async ({
    page,
  }) => {
    await openAdminDashboard(page);

    // The top-level links render to begin with.
    const aside = page.locator("aside");
    for (const label of TOP_LEVEL_LINKS) {
      await expect(
        aside
          .locator(".nav-label", { hasText: new RegExp(`^${label}$`) })
          .first(),
      ).toBeVisible();
    }

    // Structural invariant established by the fix. We locate the desktop sidebar
    // nav WITHOUT relying on the fix's own class, so a reverted markup produces
    // clean, descriptive assertion failures (not a selector timeout).
    const shape = await page.evaluate(() => {
      // The desktop sidebar nav is the visible <nav> that carries the top-level
      // "Dashboard" nav-label.
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

      const inner = nav.querySelector(
        ".sidebar-nav-scroll",
      ) as HTMLElement | null;
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

    expect(shape.found, "admin desktop sidebar <nav> not found").toBe(true);
    if (!shape.found) return;

    // 1) The <nav> is a bounded paint boundary — NOT itself a scroll container.
    expect(
      shape.nav.overflowY,
      `admin sidebar <nav> overflow-y must be 'hidden' (a paint boundary), got '${shape.nav.overflowY}' — the find-bar fix was reverted to a direct scroll container`,
    ).toBe("hidden");
    expect(
      shape.nav.position,
      `admin sidebar <nav> position must be 'relative' to host the absolute inner scroller, got '${shape.nav.position}'`,
    ).toBe("relative");
    expect(
      shape.nav.scrollHeight,
      "admin sidebar <nav> must not itself scroll (its content must live in the inner container)",
    ).toBeLessThanOrEqual(shape.nav.clientHeight + 2);

    // 2) A separate inner scroll container exists, clipped to the nav's box.
    expect(
      shape.innerExists,
      "inner '.sidebar-nav-scroll' container is missing — the find-bar fix was reverted",
    ).toBe(true);
    expect(
      shape.innerIsNav,
      "'.sidebar-nav-scroll' must be a child, not the <nav> itself",
    ).toBe(false);
    expect(
      shape.inner?.position,
      "inner scroll container must be position:absolute",
    ).toBe("absolute");
    expect(
      shape.inner?.overflowY,
      "inner scroll container must be overflow-y:auto",
    ).toBe("auto");
    expect(
      shape.innerWithinNav,
      "inner scroll container must be clipped to the nav box (absolute inset-0)",
    ).toBe(true);
    // The admin nav is deep; it must actually overflow, else the find-reveal
    // scroll (the behaviour the fix protects) would be a no-op to test.
    expect(
      shape.innerScrollable,
      "admin sidebar inner scroll container must overflow (the admin nav is long) so the find-reveal scenario is real",
    ).toBe(true);
  });

  test("driving the find-reveal scroll does not blank the admin sidebar, and releasing it restores the links", async ({
    page,
  }) => {
    await openAdminDashboard(page);

    const aside = page.locator("aside");

    // Baseline: the top-level links are visible.
    for (const label of TOP_LEVEL_LINKS) {
      await expect(
        aside
          .locator(".nav-label", { hasText: new RegExp(`^${label}$`) })
          .first(),
      ).toBeVisible();
    }

    // Precondition + baseline snapshot: confirm the nav overflows (so the
    // find-reveal scroll is meaningful), pick a below-the-fold link label as the
    // "match" term, and record where the top-level links sit so we can prove
    // nothing moved after "closing find".
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

      const scroller = nav.querySelector(
        ".sidebar-nav-scroll",
      ) as HTMLElement | null;
      const overflows = !!scroller && scroller.scrollHeight > scroller.clientHeight;

      // A label is "below the fold" if its top is beyond the scroller's visible
      // bottom edge — exactly the match a find-in-page would scroll into view.
      let belowFoldTerm: string | null = null;
      if (scroller) {
        const sRect = scroller.getBoundingClientRect();
        const labels = Array.from(nav.querySelectorAll(".nav-label"));
        for (let i = labels.length - 1; i >= 0; i--) {
          const el = labels[i] as HTMLElement;
          const txt = el.textContent?.trim() ?? "";
          if (!txt) continue;
          const r = el.getBoundingClientRect();
          if (r.top > sRect.bottom) {
            belowFoldTerm = txt;
            break;
          }
        }
      }

      const topPositions: Record<string, number | null> = {};
      for (const t of topLinks) {
        const el = Array.from(nav.querySelectorAll(".nav-label")).find(
          (l) => l.textContent?.trim() === t,
        ) as HTMLElement | undefined;
        topPositions[t] = el ? Math.round(el.getBoundingClientRect().top) : null;
      }

      return {
        navFound: true as const,
        overflows,
        belowFoldTerm,
        topPositions,
      };
    }, TOP_LEVEL_LINKS as unknown as string[]);

    expect(setup.navFound, "admin desktop sidebar <nav> not found").toBe(true);
    if (!setup.navFound) return;
    expect(
      setup.overflows,
      "expected the admin sidebar nav to overflow so the find-reveal scenario is exercised",
    ).toBe(true);

    // Drive the find-in-page reveal: fire window.find() for a below-the-fold
    // term (best-effort literal twin of find-in-page) and scroll the nav's
    // scroll container fully — the exact layout effect of the browser scrolling
    // a matched element into view.
    const term = setup.belowFoldTerm ?? "Branding";
    await page.evaluate((t) => {
      const w = window as unknown as { find?: (s: string) => boolean };
      try {
        w.find?.(t);
      } catch {
        /* window.find may throw in some engines; ignore */
      }
      const scroller = document.querySelector(
        "aside .sidebar-nav-scroll",
      ) as HTMLElement | null;
      if (scroller) scroller.scrollTop = scroller.scrollHeight;
    }, term);
    await page.waitForTimeout(200);

    // Sidebar must NOT blank: no top-level link may be PAINTED over the account
    // header (the area above the nav). Because the nav clips its overflow, any
    // link scrolled above the nav top must be clipped away, not floating over
    // the header.
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
        if (!link) return { t, paintedOverHeader: false };
        const r = link.getBoundingClientRect();
        // Only meaningful if the link got scrolled above the nav's top edge.
        if (r.bottom > navRect.top) return { t, paintedOverHeader: false };
        const cx = r.left + r.width / 2;
        const cy = r.top + r.height / 2;
        const atPoint = document.elementFromPoint(cx, cy);
        const painted = !!atPoint && (link.contains(atPoint) || atPoint === link);
        return { t, paintedOverHeader: painted };
      });

      return { navFound: true as const, results };
    }, TOP_LEVEL_LINKS as unknown as string[]);

    expect(duringFind.navFound).toBe(true);
    if (duringFind.navFound) {
      for (const r of duringFind.results) {
        expect(
          r.paintedOverHeader,
          `top-level admin link "${r.t}" was painted over the account header while a below-the-fold match was revealed — the sidebar blanked (find-bar regression)`,
        ).toBe(false);
      }
    }

    // "Close find": release the reveal scroll. The top-level links must be
    // visible again at their original positions — the sidebar is unchanged.
    await page.evaluate(() => {
      const scroller = document.querySelector(
        "aside .sidebar-nav-scroll",
      ) as HTMLElement | null;
      if (scroller) scroller.scrollTop = 0;
    });
    await page.waitForTimeout(200);

    for (const label of TOP_LEVEL_LINKS) {
      await expect(
        aside
          .locator(".nav-label", { hasText: new RegExp(`^${label}$`) })
          .first(),
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
      expect(
        after,
        `top-level admin link "${label}" vanished after closing find`,
      ).not.toBeNull();
      if (before != null && after != null) {
        expect(
          Math.abs(after - before),
          `top-level admin link "${label}" shifted after closing find (before=${before}, after=${after}) — the sidebar did not return to its original state`,
        ).toBeLessThanOrEqual(2);
      }
    }
  });
});
