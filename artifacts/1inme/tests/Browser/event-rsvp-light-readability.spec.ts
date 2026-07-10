import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";

// Readability regression guard for the two PUBLIC event surfaces that share the
// same Blade partials on OPPOSITE themes (see repo memory
// `event-page-shared-rich-content-theming.md`):
//
//   * the RSVP page  (`common/rsvp-form.blade.php`)   — a light Bootstrap card
//     on a white `.rsvp-body`; and
//   * the ticketed/general event page (`common/event-page.blade.php`) — a
//     dark-glass Tailwind page (site layout) with a `.ev-rich` override wrapper,
//     toggleable into light mode.
//
// Both `@include('common.partials.event-rich-content')` (cover/hashtags/
// Interested widget/Similar-events/More-from-this-host) and
// `common.partials.event-host-card` (the "Hosted by" card). Those partials are
// edited frequently across event tasks, and a colour tuned for one surface can
// silently wash text out on the other — dark-theme text left on the white RSVP
// card, or a missing `html.light-mode .ev-rich` pair leaving light-theme text on
// the white event card. The static `event-light-mode` gate only checks that
// every base `.ev-rich` colour rule HAS a light-mode pair; it can't see the
// actually-rendered contrast. This spec renders each surface and asserts the key
// text is both PRESENT and legible (WCAG contrast above a "not near-invisible"
// floor) against its real, composited background.
//
// Self-bootstrapping: seeds a demo host + three RSVP-available `ics` events via
// `php artisan tinker` so "Similar events" and "More from this host" both
// populate, then drives the pages headlessly. Runs against the Laravel app
// (APP_URL); localhost:80 hits the Express api-server, not Laravel.

const APP_BASE = process.env.APP_URL || "http://localhost:5000";
const CONSENT_COOKIE = "1inme_cookie_consent";

// Primary event under test + its two siblings (same host, overlapping hashtags)
// that feed the two recommendation sections.
const ALIAS = "e2e-evt-readability";

// A "not near-invisible" WCAG contrast floor. The regression this guards against
// (same-ish colour on same-ish background) collapses contrast toward ~1.0–1.4;
// intentional muted/secondary text on these surfaces still clears ~2.5–4.5. 2.0
// cleanly separates the two so a genuine wash-out fails while legitimate muted
// copy passes. (WCAG AA normal-text is 4.5 and large-text 3.0 — deliberately
// stricter than needed here; this is a blunt "can a human read it at all" bar.)
const MIN_CONTRAST = 2.0;

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/**
 * Run a `php artisan tinker` seed, retrying on a transient RDS connect blip
 * (mirrors the pattern other self-bootstrapping specs in this suite use). A
 * genuine PHP error fails every attempt and is surfaced via the rethrow.
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
 * Idempotently (re)seed a demo host with a filled organizer profile (so the
 * RICH host card variant renders — the more complex of the two) plus three
 * public, RSVP-available `ics` events sharing hashtags. The extra two events
 * make both "Similar events" (hashtag match, any host) and "More from this
 * host" (same user) populate on the primary event's pages.
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
$rid = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
if ($rid) { $u->roles()->syncWithoutDetaching([$rid]); $u->flushPermissionCache(); }
if ($u->onboarded_at === null) { $u->onboarded_at = now(); }
// Fill the organizer profile so event-host-card renders its RICH variant
// (name + description + website + contact), exercising more of that shared
// partial than the bare fallback card.
$u->organizer_profile = [
  'name' => 'Aurora Events Co',
  'description' => 'We host design and community meetups.',
  'website' => 'https://example.com',
  'contact_email' => 'host@example.com',
];
$u->save();
$ws = app(WorkspaceContext::class)->resolve($u);

$events = [
  ['e2e-evt-readability',   'Design Systems Meetup',        'San Francisco, CA', '+7 days'],
  ['e2e-evt-readability-2', 'Community Coffee & Design',     'San Francisco, CA', '+14 days'],
  ['e2e-evt-readability-3', 'Meetup: Portfolio Reviews',     'Oakland, CA',       '+21 days'],
];
foreach ($events as $i => [$alias, $title, $loc, $when]) {
  $link = Link::query()->withoutWorkspaceScope()->updateOrCreate(
    ['alias' => $alias],
    [
      'workspace_id' => $ws->id, 'user_id' => $u->id, 'created_by_user_id' => $u->id,
      'type' => 'ics', 'title' => $title,
      'is_active' => true, 'visibility' => 'public', 'is_demo' => true,
      'total_clicks' => 20, 'unique_clicks' => 12,
      'settings' => ['event_category' => 'community', 'is_online' => false, 'rsvp_enabled' => true],
    ]
  );
  // Clear any ticket tiers so isRsvpAvailable() stays true (RSVP surface).
  $link->eventTicketTiers()->delete();
  IcsData::updateOrCreate(
    ['link_id' => $link->id],
    [
      'event_name' => $title,
      'description' => 'Join us for ' . $title . ' in ' . $loc . '.',
      'location' => $loc, 'organizer' => $u->name,
      'start_date' => \\Carbon\\Carbon::parse($when),
      'end_date' => \\Carbon\\Carbon::parse($when)->addHours(2),
      'timezone' => 'UTC', 'latitude' => null, 'longitude' => null,
      'hashtags' => ['design', 'meetup', 'community'],
      // Null cover + empty gallery: keep the render free of external image
      // fetches (the e2e box may have no outbound internet); recommendation
      // cards fall back to the bundled local placeholder SVG.
      'cover_image_url' => null, 'gallery' => [],
    ]
  );
}
echo 'SEED_OK';
`;
  const out = runTinkerSeed(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("Event readability seed did not confirm SEED_OK:\n" + out);
  }
}

type LowContrast = {
  tag: string;
  cls: string;
  text: string;
  color: string;
  bg: string;
  ratio: number;
};

/**
 * Scan every visible element that owns direct (non-whitespace) text within the
 * given scope selectors and return those whose composited text-vs-background
 * contrast falls below `min`. Runs entirely in the page so it reflects the live
 * computed styles of whichever theme is active.
 *
 * Background resolution walks ancestors compositing semi-transparent layers over
 * an opaque base; if any ancestor paints a gradient/image background (which we
 * can't reduce to a single colour) the element is skipped rather than guessed —
 * so cover images, map tiles and gradient hero bands never cause false alarms.
 */
async function findLowContrast(
  page: Page,
  scopeSelectors: string[],
  min: number,
): Promise<LowContrast[]> {
  return page.evaluate(
    ({ scopeSelectors, min }) => {
      function parseRGBA(
        s: string,
      ): { r: number; g: number; b: number; a: number } | null {
        const m = s.match(/rgba?\(([^)]+)\)/);
        if (!m) return null;
        const p = m[1].split(",").map((x) => parseFloat(x.trim()));
        if (p.length < 3 || p.some((n) => Number.isNaN(n))) return null;
        return { r: p[0], g: p[1], b: p[2], a: p.length >= 4 ? p[3] : 1 };
      }
      type RGB = { r: number; g: number; b: number };
      function over(
        fg: { r: number; g: number; b: number; a: number },
        bg: RGB,
      ): RGB {
        return {
          r: fg.r * fg.a + bg.r * (1 - fg.a),
          g: fg.g * fg.a + bg.g * (1 - fg.a),
          b: fg.b * fg.a + bg.b * (1 - fg.a),
        };
      }
      function relLum({ r, g, b }: RGB): number {
        const a = [r, g, b].map((v) => {
          const c = v / 255;
          return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2];
      }
      function contrast(a: RGB, b: RGB): number {
        const l1 = relLum(a);
        const l2 = relLum(b);
        const hi = Math.max(l1, l2);
        const lo = Math.min(l1, l2);
        return (hi + 0.05) / (lo + 0.05);
      }
      // Resolve the opaque background behind `el` by compositing ancestor
      // background-colour layers. Returns null when a gradient/image is in the
      // stack (undeterminable → skip the element).
      function resolveBg(el: HTMLElement): RGB | null {
        const layers: { r: number; g: number; b: number; a: number }[] = [];
        let node: HTMLElement | null = el;
        while (node) {
          const cs = getComputedStyle(node);
          if (cs.backgroundImage && cs.backgroundImage !== "none") return null;
          const c = parseRGBA(cs.backgroundColor);
          if (c && c.a > 0) {
            layers.push(c);
            if (c.a >= 0.999) break;
          }
          if (node === document.documentElement) break;
          node = node.parentElement;
        }
        // Base: assume white if we never hit an opaque layer (light surfaces).
        let base: RGB = { r: 255, g: 255, b: 255 };
        for (let i = layers.length - 1; i >= 0; i--) {
          const l = layers[i];
          base = l.a >= 0.999 ? { r: l.r, g: l.g, b: l.b } : over(l, base);
        }
        return base;
      }
      function hasDirectText(el: HTMLElement): boolean {
        for (const n of Array.from(el.childNodes)) {
          if (n.nodeType === Node.TEXT_NODE && (n.textContent || "").trim()) {
            return true;
          }
        }
        return false;
      }
      function visible(el: HTMLElement): boolean {
        const cs = getComputedStyle(el);
        if (
          cs.display === "none" ||
          cs.visibility === "hidden" ||
          parseFloat(cs.opacity) < 0.05
        ) {
          return false;
        }
        const r = el.getBoundingClientRect();
        return r.width > 1 && r.height > 1;
      }

      const seen = new Set<Element>();
      const out: LowContrast[] = [];
      for (const sel of scopeSelectors) {
        for (const root of Array.from(
          document.querySelectorAll<HTMLElement>(sel),
        )) {
          const candidates = [root, ...Array.from(root.querySelectorAll("*"))];
          for (const el of candidates as HTMLElement[]) {
            if (seen.has(el)) continue;
            seen.add(el);
            if (!hasDirectText(el)) continue;
            if (!visible(el)) continue;
            const cs = getComputedStyle(el);
            const fg = parseRGBA(cs.color);
            if (!fg) continue;
            const bg = resolveBg(el);
            if (!bg) continue; // gradient/image behind — undeterminable
            const composited =
              fg.a >= 0.999 ? { r: fg.r, g: fg.g, b: fg.b } : over(fg, bg);
            const ratio = contrast(composited, bg);
            if (ratio < min) {
              out.push({
                tag: el.tagName,
                cls: String(el.className || "").slice(0, 100),
                text: (el.textContent || "").trim().slice(0, 60),
                color: cs.color,
                bg: `rgb(${Math.round(bg.r)},${Math.round(bg.g)},${Math.round(
                  bg.b,
                )})`,
                ratio: Math.round(ratio * 100) / 100,
              });
            }
          }
        }
      }
      return out;
    },
    { scopeSelectors, min },
  );
}

/** Pre-seed a dismissed cookie-consent decision so its scroll-locking banner
 *  never mounts over the site-layout event page. */
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

test.describe("public event surfaces — light-mode readability", () => {
  test.beforeAll(() => {
    seedFixtures();
  });

  test("RSVP page (light Bootstrap): key text present and legible on the white card", async ({
    page,
  }) => {
    await seedConsent(page);
    await page.goto(`${APP_BASE}/${ALIAS}/rsvp`, {
      waitUntil: "domcontentloaded",
    });
    await page.locator(".rsvp-body").waitFor({ state: "visible" });

    // ---- Key surfaces are PRESENT. ----
    await expect(
      page.locator(".rsvp-body").getByText("Hosted by"),
      "host card should render",
    ).toBeVisible();
    await expect(
      page.locator("#event-interest-widget"),
      "Interested widget should render",
    ).toBeVisible();
    await expect(
      page.locator(".rsvp-body").getByText("#design", { exact: false }).first(),
      "hashtag chip should render",
    ).toBeVisible();
    await expect(
      page.getByText("Will you attend?"),
      "RSVP form should render",
    ).toBeVisible();
    await expect(
      page.getByRole("button", { name: /Send RSVP/i }),
      "submit button should render",
    ).toBeVisible();
    // At least one recommendation section (both should populate given siblings).
    await expect(
      page
        .getByText(/Similar events|More from this host/)
        .first(),
      "a recommendation section should render",
    ).toBeVisible();

    // ---- Everything in the white card body reads legibly. ----
    const offenders = await findLowContrast(page, [".rsvp-body"], MIN_CONTRAST);
    expect(
      offenders,
      `RSVP page has near-invisible text on the white card: ${JSON.stringify(
        offenders,
        null,
        2,
      )}`,
    ).toEqual([]);
  });

  test("event page (dark glass): shared partials legible on the dark theme", async ({
    page,
  }) => {
    await seedConsent(page);
    // The site layout's head script only stays on the dark theme when there is
    // no `1inme_theme` cookie/localStorage AND `prefers-color-scheme` is not
    // light. Playwright emulates `prefers-color-scheme: light` by default, which
    // would flip the page into light-mode, so emulate dark BEFORE navigating to
    // pin the dark-glass theme this test is meant to cover.
    await page.emulateMedia({ colorScheme: "dark" });
    await page.goto(`${APP_BASE}/${ALIAS}`, { waitUntil: "domcontentloaded" });
    await page.locator(".ev-rich").first().waitFor({ state: "visible" });

    // Confirm we are actually on the dark theme.
    const isDark = await page.evaluate(
      () => !document.documentElement.classList.contains("light-mode"),
    );
    expect(isDark, "event page should render on the dark theme").toBe(true);

    // ---- Key shared-partial surfaces present. ----
    await expect(
      page.getByText("Hosted by").first(),
      "host card should render",
    ).toBeVisible();
    await expect(
      page.locator("#event-interest-widget"),
      "Interested widget should render",
    ).toBeVisible();
    await expect(
      page.getByText(/Similar events|More from this host/).first(),
      "a recommendation section should render",
    ).toBeVisible();

    // ---- No washed-out text in the .ev-rich / .ev-card surfaces. ----
    const offenders = await findLowContrast(
      page,
      [".ev-rich", ".ev-card"],
      MIN_CONTRAST,
    );
    expect(
      offenders,
      `event page (dark) has near-invisible text in the shared event partials: ${JSON.stringify(
        offenders,
        null,
        2,
      )}`,
    ).toEqual([]);
  });

  test("event page (light mode): shared partials legible after theme flip", async ({
    page,
  }) => {
    await seedConsent(page);
    await page.goto(`${APP_BASE}/${ALIAS}`, { waitUntil: "domcontentloaded" });
    await page.locator(".ev-rich").first().waitFor({ state: "visible" });

    // Flip the real theme switch: the CSS + `.ev-rich` overrides all key off the
    // `html.light-mode` class (see repo memory user-layout-theme-in-playwright).
    await page.evaluate(() =>
      document.documentElement.classList.add("light-mode"),
    );
    await page.waitForFunction(() =>
      document.documentElement.classList.contains("light-mode"),
    );

    const offenders = await findLowContrast(
      page,
      [".ev-rich", ".ev-card"],
      MIN_CONTRAST,
    );
    expect(
      offenders,
      `event page (light) has near-invisible text — a shared .ev-rich rule is missing its html.light-mode pair: ${JSON.stringify(
        offenders,
        null,
        2,
      )}`,
    ).toEqual([]);
  });
});
