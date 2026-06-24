import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Locator,
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

const ALIAS = "e2e-card-gallery-preview";

// Unique token stamped into every fixture template name so the gallery search
// (server-side `q` ilike on name/description) narrows the rendered grid to
// exactly our no-thumbnail fixtures, ignoring the rest of the seeded library.
const TOKEN = "ZZE2EPrev";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/**
 * One representative block type per shape family the
 * TemplatePreviewLayoutBuilder::cellFor() contract emits. These mirror the
 * page-picker coverage in WebTemplatePreviewLayoutTest so the Alpine renderer
 * is exercised through the same shape switch the Blade picker is.
 */
const SHAPE_TYPES: Record<string, string> = {
  avatar: "profile_card_v1",
  pill: "link",
  media: "image",
  form: "email_subscribe",
  list_rows: "list",
  heading: "heading",
  text_lines: "paragraph",
  dot_row: "socials",
};

const SHAPES = Object.keys(SHAPE_TYPES);

/**
 * Idempotently (re)seed the demo user, a biolink they own, and one card
 * template per shape family. Each fixture has NO `thumbnail_url` and a snapshot
 * holding a single full-span child of the shape's representative type, so the
 * gallery is forced to draw the shape-aware `preview_layout` blueprint mock
 * (not a static image, not the empty fallback icon). `plan_tier` is left null
 * so none are ever locked, regardless of the demo user's plan.
 *
 * Done via `php artisan tinker` so the spec is self-bootstrapping on a fresh
 * runner — it only needs the Laravel app running with migrations applied.
 * Returns the biolink id for the editor URL.
 */
function seedGalleryFixtures(): number {
  const shapeMap = JSON.stringify(SHAPE_TYPES);

  // NOTE: this string is passed straight to `tinker --execute=`. In a JS
  // template literal, `\\` becomes the single backslash PHP namespaces need
  // (e.g. App\Modules\...), while `$var` stays literal (only `${...}` would
  // interpolate). Do NOT write `\\$` — that yields invalid `\$var` PHP.
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\Admin\\Models\\CardTemplate;
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
// Mark onboarded so the post-login RedirectToOnboarding soft gate doesn't bounce
// us through the heavy onboarding wizard before the editor (active-user state).
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

$bio = Link::withoutGlobalScope('workspace')->where('alias', '${ALIAS}')->first();
if (!$bio) {
  $bio = Link::create([
    'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
    'alias' => '${ALIAS}', 'title' => 'E2E Card Gallery Preview', 'is_active' => true,
  ]);
} else {
  $bio->user_id = $u->id; $bio->workspace_id = $ws?->id; $bio->save();
}

// Wipe any prior fixtures from earlier runs so the count is deterministic.
CardTemplate::where('name', 'like', '${TOKEN}%')->delete();

$map = json_decode('${shapeMap}', true);
$sort = 0;
foreach ($map as $shape => $type) {
  CardTemplate::create([
    'name' => '${TOKEN} ' . $shape,
    'slug' => 'zze2e-prev-' . str_replace('_', '-', $shape),
    'category' => 'general',
    'description' => 'shape coverage fixture',
    'thumbnail_url' => null,
    'plan_tier' => null,
    'is_active' => true,
    'sort_order' => $sort++,
    'snapshot' => ['children' => [
      ['type' => $type, 'settings' => ['_style' => ['grid_span' => 12]]],
    ]],
  ]);
}

echo 'LINKID=' . $bio->id;
`.trim();

  const out = execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
  const m = out.match(/LINKID=(\d+)/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return Number(m[1]);
}

/**
 * Log in as the demo user (non-prod quick-login). Submits the demo-login form
 * via JS rather than a click, and waits only for the demo-login POST response
 * (not the redirect target render) so the heavy post-login dashboard render
 * never blocks the suite — see the inline note below.
 */
async function loginAsDemo(page: Page): Promise<void> {
  await page.goto("/user/login");
  // Wait only for the demo-login POST itself — Laravel sets the authenticated
  // session cookie on its 302 response, so the context is logged in as soon as
  // that returns. We deliberately do NOT wait for the redirect target to render
  // (the dashboard is a heavy ~20-30s cold Blade render over the distant RDS,
  // and openCardGallery navigates straight to the editor anyway).
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
 * Open the block editor, switch the special panel to the Card Templates
 * gallery filtered to our fixtures, and wait for the Alpine `x-for` to render
 * every fixture tile. Drives the real Alpine component (search → controller
 * fetch → reactive render) the way clicking the "Cards" tab would.
 */
async function openCardGallery(page: Page, linkId: number): Promise<void> {
  await page.goto(`/user/links/${linkId}/blocks`);

  // Wait for Alpine to have initialised the editor root component.
  await page.waitForFunction(() => {
    const el = document.querySelector('[x-data="biolinkEditor()"]');
    const A = (window as unknown as { Alpine?: { $data: (e: Element) => unknown } })
      .Alpine;
    if (!el || !A) return false;
    const data = A.$data(el) as { loadCardTemplates?: unknown } | undefined;
    return typeof data?.loadCardTemplates === "function";
  });

  // Open the gallery, scope it to our fixtures via the search box, and force a
  // reload so the server-side `q` filter returns just the fixture set.
  await page.evaluate((token) => {
    const el = document.querySelector('[x-data="biolinkEditor()"]')!;
    const data = (
      window as unknown as { Alpine: { $data: (e: Element) => Record<string, unknown> } }
    ).Alpine.$data(el) as {
      specialSearch: string;
      specialMode: string;
      specialOpen: boolean;
      cardCategory: string;
      loadCardTemplates: (force: boolean) => void;
    };
    data.specialSearch = token;
    data.specialMode = "templates";
    data.specialOpen = true;
    data.cardCategory = "all";
    data.loadCardTemplates(true);
  }, TOKEN);
}

/** The card-gallery grid (gap-3 distinguishes it from the gap-2 form/buzz grids). */
function galleryGrid(page: Page): Locator {
  return page.locator("div.grid.grid-cols-1.gap-3");
}

/** The single rendered fixture tile for a given shape family. */
function tileForShape(page: Page, shape: string): Locator {
  return galleryGrid(page)
    .locator("> div")
    .filter({ hasText: `${TOKEN} ${shape}` });
}

test.describe("biolink editor — card gallery shape-aware preview", () => {
  // The login redirect and the block-editor page are heavy Blade renders over a
  // distant RDS, so lift the per-test/hook ceiling well above the shared 90s.
  test.describe.configure({ timeout: 180_000 });

  let linkId: number;

  test.beforeAll(async ({ browser }) => {
    sharedContext = await browser.newContext();
    // Seed before the first login so the demo user is already marked onboarded —
    // otherwise the post-login soft gate bounces us through the heavy onboarding
    // wizard and the editor render alone can blow the budget.
    seedGalleryFixtures();
    const page = await sharedContext.newPage();
    await loginAsDemo(page);
    await page.close();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  test.beforeEach(() => {
    linkId = seedGalleryFixtures();
  });

  test("renders every no-thumbnail card as its shape mock, not a blank tile", async ({
    page,
  }) => {
    await openCardGallery(page, linkId);

    const grid = galleryGrid(page);

    // The gallery narrowed to exactly our fixture set (one tile per shape).
    await expect(grid.locator("> div")).toHaveCount(SHAPES.length);

    // None of them fell through to the empty-snapshot fallback icon
    // (<template x-if="!t.thumbnail_url && !(t.preview_layout||[]).length">),
    // which would mean the blueprint came back empty and the tile is blank.
    await expect(grid.locator(".fa-square-poll-vertical")).toHaveCount(0);

    // Each shape branch's distinctive, drawn-cell-only markup. Because Alpine
    // x-if REMOVES non-matching branches from the DOM, the presence of these
    // nodes proves the matching shape branch actually drew its cell — a
    // featureless box would have none of them. The placeholder copy
    // ('Your Headline', '@yourhandle', …) is emitted by the builder ONLY inside
    // a drawn cell, so it doubles as proof the cell rendered.

    // heading → sample headline line.
    const heading = tileForShape(page, "heading");
    await expect(heading.locator(".tpl-prev-heading")).toBeVisible();
    await expect(heading.locator(".tpl-prev-heading")).toHaveText(
      "Your Headline",
    );

    // text_lines → clamped paragraph copy.
    const text = tileForShape(page, "text_lines");
    await expect(text.locator(".tpl-prev-text")).toBeVisible();
    await expect(text.locator(".tpl-prev-text")).toContainText(
      "A short intro about you and what you share here.",
    );

    // pill → rounded button shell carrying the link label.
    const pill = tileForShape(page, "pill");
    await expect(pill.locator(".tpl-prev-pill")).toBeVisible();
    await expect(pill.locator(".tpl-prev-pill")).toContainText(
      "Visit my website",
    );

    // avatar → circular placeholder image + handle line on the right.
    const avatar = tileForShape(page, "avatar");
    await expect(
      avatar.locator('img[src*="block-placeholders/avatar.svg"]'),
    ).toBeVisible();
    await expect(avatar).toContainText("@yourhandle");

    // media → tall image cell with the real placeholder image.
    const media = tileForShape(page, "media");
    await expect(
      media.locator('img[src*="block-placeholders/image.svg"]'),
    ).toBeVisible();

    // form → stacked input lines + a centered, labelled submit button.
    const form = tileForShape(page, "form");
    await expect(form.locator(".tpl-prev-pill")).toBeVisible();
    await expect(form.locator(".tpl-prev-pill")).toContainText("Subscribe");

    // list_rows → dotted sample-text rows.
    const list = tileForShape(page, "list_rows");
    await expect(list.locator(".tpl-prev-list").first()).toBeVisible();
    await expect(list.locator(".tpl-prev-list").first()).toHaveText(
      "First item",
    );

    // dot_row → a row of exactly five fixed 5px circular dots.
    const dots = tileForShape(page, "dot_row");
    await expect(
      dots.locator('div[style*="width: 5px; height: 5px;"]'),
    ).toHaveCount(5);
  });
});
