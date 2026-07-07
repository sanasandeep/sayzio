import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

// Regression guard for the top gap above the cross-page "Discover Events"
// promo band (`common/partials/events-hero-band.blade.php`), rendered on
// every `public/layouts/site.blade.php` marketing page.
//
// The gap fix relies on THREE CSS rules working together in the right order:
//   1. `.ehb-band { margin-top: calc(-1 * var(--mkt-nav-h)) }` — pulls the
//      band flush with the viewport top, behind the floating nav (the band
//      lives OUTSIDE <main>, so it misses `.mkt-site-main`'s own pull-up);
//   2. `.ehb-band { padding-top: calc(var(--mkt-nav-h) + …) }` — pushes the
//      band's CONTENT back down so it still clears the nav; and
//   3. `.has-ehb-band .mkt-site-main { margin-top: 0 }` — cancels <main>'s
//      pull-up (its normal job is now done by the band), keyed off the body
//      class the partial adds synchronously via an inline <script>.
//
// Any future edit to the site layout, the band partial, or `--mkt-nav-h`
// (marketing-anim.css) that breaks this three-way coordination silently
// reintroduces either the bare body-background gap above the band or content
// hidden under the nav — with no automated signal. This spec pins the live
// computed geometry so a regression fails immediately, in BOTH dark and
// light mode (the coordination is theme-independent, but the light-mode
// remap layer is exactly the kind of stylesheet a future change lands in).
//
// Self-bootstrapping: the band only renders when upcoming public `ics`
// events exist (EventsHeroBandComposer), so the spec idempotently seeds a
// demo host + three future events via `php artisan tinker` first.
//
// Runs against the Laravel app on :5000 — localhost:80 hits the Express
// api-server, not Laravel (see APP_URL override below).

const APP_BASE = process.env.APP_URL || "http://localhost:5000";
const CONSENT_COOKIE = "1inme_cookie_consent";

// A stable, band-showing marketing page: site layout, not the events
// directory (which suppresses the band because it has the full hero), and
// cheap to render (no maps/embeds like the home page).
const PAGE_PATH = "/about";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/**
 * Run a `php artisan tinker` seed, retrying on a transient RDS connect blip
 * (mirrors the pattern other self-bootstrapping specs in this suite use).
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
 * Idempotently (re)seed the demo user with three upcoming, public,
 * directory-visible `ics` events so EventsHeroBandComposer's featured query
 * is guaranteed non-empty and the band renders on every site-layout page.
 *
 * NOTE: passed straight to `tinker --execute=`. In this JS template literal
 * `\\` becomes the single backslash PHP namespaces need, while `$var` stays
 * literal (only `${...}` interpolates).
 */
function seedFixtures(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\IcsData;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;

$u = User::where('email', 'demo@1inme.com')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => 'demo@1inme.com',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

$events = [
  ['e2e-ehb-band-1', 'Aurora Nights Live',      '+5 days'],
  ['e2e-ehb-band-2', 'Makers Market Weekend',   '+12 days'],
  ['e2e-ehb-band-3', 'Rooftop Cinema Evening',  '+19 days'],
];
foreach ($events as [$alias, $title, $when]) {
  $link = Link::query()->withoutWorkspaceScope()->updateOrCreate(
    ['alias' => $alias],
    [
      'workspace_id' => $ws->id, 'user_id' => $u->id, 'created_by_user_id' => $u->id,
      'type' => 'ics', 'title' => $title,
      'is_active' => true, 'visibility' => 'public', 'is_demo' => true,
      'settings' => ['event_category' => 'community', 'is_online' => false, 'rsvp_enabled' => true],
    ]
  );
  IcsData::updateOrCreate(
    ['link_id' => $link->id],
    [
      'event_name' => $title,
      'description' => 'Join us for ' . $title . '.',
      'location' => 'San Francisco, CA', 'organizer' => $u->name,
      'start_date' => \\Carbon\\Carbon::parse($when),
      'end_date' => \\Carbon\\Carbon::parse($when)->addHours(2),
      'timezone' => 'UTC',
      'hashtags' => ['community'],
      // Null cover: keeps the band render free of external image fetches
      // (the e2e box may have no outbound internet); slides fall back to
      // the bundled local placeholder SVG.
      'cover_image_url' => null, 'gallery' => [],
    ]
  );
}
echo 'SEED_OK';
`;
  const out = runTinkerSeed(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("Events hero band seed did not confirm SEED_OK:\n" + out);
  }
}

/** Pre-seed a dismissed cookie-consent decision so its banner never mounts. */
async function seedConsent(page: Page): Promise<void> {
  const decision = JSON.stringify({
    v: 1,
    t: Date.now(),
    c: { analytics: true, marketing: false, functional: false },
  });
  await page.context().addCookies([
    {
      name: CONSENT_COOKIE,
      value: encodeURIComponent(decision),
      domain: "localhost",
      path: "/",
    },
  ]);
}

/** Force the site into dark mode before any page script runs. */
async function preferDark(page: Page): Promise<void> {
  await page.addInitScript(() => {
    try {
      localStorage.setItem("1inme_theme", "dark");
    } catch (e) {
      /* ignore */
    }
  });
}

type BandGeometry = {
  hasBodyClass: boolean;
  navHVar: number; // resolved px value of --mkt-nav-h
  navBottom: number; // actual rendered nav's bottom edge (viewport coords)
  bandTop: number; // .ehb-band top edge (viewport coords, at scrollY 0)
  contentTop: number; // band's inner content container top edge
  mainMarginTop: string; // computed margin-top of .mkt-site-main
  scrollY: number;
};

/**
 * Read the live geometry that the three coordinated CSS rules produce. Runs
 * entirely in the page so it reflects whichever theme/stylesheets are active.
 */
async function readBandGeometry(page: Page): Promise<BandGeometry> {
  // Geometry is defined at the top of the document; settle there first.
  await page.evaluate(() => window.scrollTo(0, 0));
  await page
    .waitForFunction(() => window.scrollY === 0, undefined, { timeout: 3000 })
    .catch(() => {
      /* asserted via scrollY below */
    });

  return page.evaluate(() => {
    // Resolve the calc() value of --mkt-nav-h through a probe element so we
    // get real pixels (rem-dependent), not the raw calc string.
    const probe = document.createElement("div");
    probe.style.cssText =
      "position:absolute;visibility:hidden;height:var(--mkt-nav-h);width:1px;";
    document.body.appendChild(probe);
    const navHVar = probe.getBoundingClientRect().height;
    probe.remove();

    const navs = Array.from(document.querySelectorAll("nav"));
    const nav =
      navs.find((n) => {
        const pos = getComputedStyle(n).position;
        return pos === "sticky" || pos === "fixed";
      }) || navs[0];

    const band = document.querySelector<HTMLElement>(".ehb-band");
    const content = band
      ? band.querySelector<HTMLElement>(":scope > div")
      : null;
    const main = document.querySelector<HTMLElement>("main.mkt-site-main");

    return {
      hasBodyClass: document.body.classList.contains("has-ehb-band"),
      navHVar,
      navBottom: nav ? nav.getBoundingClientRect().bottom : -1,
      bandTop: band ? band.getBoundingClientRect().top : Number.NaN,
      contentTop: content
        ? content.getBoundingClientRect().top
        : Number.NaN,
      mainMarginTop: main ? getComputedStyle(main).marginTop : "MISSING",
      scrollY: window.scrollY,
    };
  });
}

/** Assert the full three-rule coordination for whichever mode is active. */
function assertGeometry(g: BandGeometry, mode: string): void {
  expect(g.scrollY, `${mode}: geometry must be read at scroll top`).toBe(0);

  // The body-class hook the partial adds synchronously — rule 3's key.
  expect(
    g.hasBodyClass,
    `${mode}: body must carry has-ehb-band while the band renders`,
  ).toBe(true);

  // --mkt-nav-h must resolve to a sane, non-zero height (≈ 0.85rem + 4rem +
  // 2px ≈ 80px at the default 16px root). If a refactor drops/renames the
  // variable, the probe collapses to 0 and rules 1+2 both silently die.
  expect(
    g.navHVar,
    `${mode}: --mkt-nav-h must resolve to a real nav height (got ${g.navHVar}px)`,
  ).toBeGreaterThan(40);

  // Rule 1: the band's top edge sits at the viewport top (y=0) — flush
  // behind the nav, NOT pushed down below it (the gap regression leaves it
  // at ~navH with bare body background showing above).
  expect(
    Math.abs(g.bandTop),
    `${mode}: band top edge must be at y=0 / viewport top (got ${g.bandTop}px; ` +
      `a value near ${Math.round(g.navHVar)}px means the top gap is back)`,
  ).toBeLessThanOrEqual(2);

  // Rule 2: the band's CONTENT still clears the floating nav — both the
  // CSS-variable amount the padding compensates with and the nav's actual
  // rendered bottom edge (guards --mkt-nav-h drifting out of sync with the
  // real bar height).
  expect(
    g.contentTop,
    `${mode}: band content (top ${g.contentTop}px) must clear the ` +
      `--mkt-nav-h reserve (${g.navHVar}px) — content is hiding under the nav`,
  ).toBeGreaterThanOrEqual(g.navHVar - 2);
  expect(g.navBottom, `${mode}: a nav bar must render`).toBeGreaterThan(0);
  expect(
    g.contentTop,
    `${mode}: band content (top ${g.contentTop}px) must clear the rendered ` +
      `nav's bottom edge (${g.navBottom}px)`,
  ).toBeGreaterThanOrEqual(g.navBottom - 2);

  // Rule 3: with the band present, <main> must NOT keep its own pull-up
  // (it would slide up underneath the band).
  expect(
    g.mainMarginTop,
    `${mode}: .has-ehb-band must cancel .mkt-site-main's negative margin-top`,
  ).toBe("0px");
}

test.describe("events hero band — no top gap, content clears the nav", () => {
  test.beforeAll(() => {
    seedFixtures();
  });

  test(`band sits flush at y=0 with content below the nav (${PAGE_PATH}, dark & light)`, async ({
    page,
  }) => {
    await seedConsent(page);
    await preferDark(page);
    await page.goto(`${APP_BASE}${PAGE_PATH}`, {
      waitUntil: "domcontentloaded",
    });

    // The band only renders when upcoming events exist — the beforeAll seed
    // guarantees that, so a missing band is itself a failure.
    await page.locator(".ehb-band").waitFor({ state: "visible" });

    // ---- Dark mode ----
    const isDark = await page.evaluate(
      () => !document.documentElement.classList.contains("light-mode"),
    );
    expect(isDark, "page should start in dark mode").toBe(true);
    assertGeometry(await readBandGeometry(page), "dark");

    // ---- Light mode: flip the real site switch, re-assert. ----
    await page.waitForFunction(
      () =>
        typeof (window as unknown as { inmeToggleTheme?: unknown })
          .inmeToggleTheme === "function",
    );
    await page.evaluate(() =>
      (
        window as unknown as { inmeToggleTheme: () => boolean }
      ).inmeToggleTheme(),
    );
    await page.waitForFunction(() =>
      document.documentElement.classList.contains("light-mode"),
    );
    assertGeometry(await readBandGeometry(page), "light");
  });
});
