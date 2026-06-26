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

const ALIAS = "e2e-editor-dnd";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

type Baseline = { linkId: number; cardId: number };

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
 * Reset (idempotently re-create) a known biolink owned by the demo user with
 * a deterministic block layout so each test starts from the same state:
 *
 *   index 0: divider  (top-level)
 *   index 1: spacer   (top-level)
 *   index 2: card     (top-level Card Container) → 1 paragraph child
 *
 * Returns the link id (for the editor URL) and the card block id. Done via
 * `php artisan tinker` so the spec is self-bootstrapping on a fresh runner —
 * it only needs the Laravel app running with migrations applied.
 */
function resetEditorBiolink(): Baseline {
  // NOTE: this string is passed straight to `tinker --execute=`. In a JS
  // template literal, `\\` becomes the single backslash PHP namespaces need
  // (e.g. App\Modules\...), while `$var` stays literal (only `${...}` would
  // interpolate). Do NOT write `\\$` — that yields invalid `\$var` PHP.
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\BiolinkBlock;
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
// Mark onboarded so the post-login RedirectToOnboarding soft gate doesn't bounce
// us through the heavy onboarding wizard before the editor (active-user state).
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

$bio = Link::withoutGlobalScope('workspace')->where('alias', '${ALIAS}')->first();
if (!$bio) {
  $bio = Link::create([
    'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
    'alias' => '${ALIAS}', 'title' => 'E2E Editor DnD', 'is_active' => true,
  ]);
} else {
  $bio->user_id = $u->id; $bio->workspace_id = $ws?->id; $bio->save();
}

BiolinkBlock::withoutGlobalScope('workspace')->where('link_id', $bio->id)->delete();

$divider = BiolinkBlock::create(['link_id'=>$bio->id,'workspace_id'=>$bio->workspace_id,'type'=>'divider','sort_order'=>0,'is_active'=>true,'settings'=>[]]);
$spacer  = BiolinkBlock::create(['link_id'=>$bio->id,'workspace_id'=>$bio->workspace_id,'type'=>'spacer','sort_order'=>1,'is_active'=>true,'settings'=>[]]);
$card    = BiolinkBlock::create(['link_id'=>$bio->id,'workspace_id'=>$bio->workspace_id,'type'=>'card','sort_order'=>2,'is_active'=>true,'settings'=>['title'=>'My Card']]);
BiolinkBlock::create(['link_id'=>$bio->id,'workspace_id'=>$bio->workspace_id,'parent_id'=>$card->id,'type'=>'paragraph','sort_order'=>0,'is_active'=>true,'settings'=>['text'=>'child-para']]);

echo 'LINKID=' . $bio->id . ' CARDID=' . $card->id;
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/LINKID=(\d+) CARDID=(\d+)/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return { linkId: Number(m[1]), cardId: Number(m[2]) };
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
  // and openEditor navigates straight to the editor anyway).
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

/** Open the block editor for a link with the test hooks armed. */
async function openEditor(page: Page, linkId: number): Promise<void> {
  await page.addInitScript(() => {
    (window as unknown as { __E2E__: boolean }).__E2E__ = true;
  });
  // The editor is a heavy cold Blade render over the distant RDS (well past the
  // default 30s navigation/wait budget), and this suite reseeds via tinker before
  // every test so the render is always cold — give both the navigation and the
  // test-hook poll real headroom or later tests intermittently time out here.
  await page.goto(`/user/links/${linkId}/blocks`, { timeout: 120_000 });
  await page.waitForFunction(
    () => !!(window as unknown as { __editorTest?: unknown }).__editorTest,
    undefined,
    { timeout: 120_000 },
  );
}

/** The block-type label shown on a palette tile (matches the rendered card). */
async function paletteLabel(page: Page, type: string): Promise<string> {
  return (
    await page
      .locator(`.palette-block-item[data-block-type="${type}"] .palette-block-label`)
      .first()
      .innerText()
  ).trim();
}

/**
 * Ordered top-level block-type labels currently rendered on the canvas, read as
 * a single atomic DOM snapshot. Reading the whole list in one `page.evaluate`
 * (rather than per-wrapper locator round-trips) avoids a mid-re-render race: the
 * in-place insert briefly re-renders #blockList, and a multi-step read could
 * catch a node detaching between the count and the per-label read. Pair this
 * with `expect.poll` so the assertion re-snapshots until the list settles.
 */
async function topLevelLabels(page: Page): Promise<string[]> {
  return page.evaluate(() =>
    Array.from(
      document.querySelectorAll("#blockList > .block-card-wrapper"),
    ).map((w) =>
      (
        w.querySelector("span.text-sm.font-semibold")?.textContent ?? ""
      ).trim(),
    ),
  );
}

/**
 * Run a palette drop through the real onAdd pipeline. The editor inserts the
 * new block in place (no page reload), so wait for the controller round-trip to
 * land: a fresh block card appears in the target list and the "Block added"
 * success toast is shown.
 */
async function dropPalette(
  page: Page,
  type: string,
  parentCardId: number | null,
  index: number,
): Promise<void> {
  const cardSelector = parentCardId
    ? `.card-child-list[data-card-id="${parentCardId}"] > .child-block-card`
    : "#blockList > .block-card-wrapper";
  const before = await page.locator(cardSelector).count();

  await page.evaluate(
    ([t, p, i]) =>
      (
        window as unknown as {
          __editorTest: {
            simulatePaletteDrop: (
              type: string,
              parent: number | null,
              index: number,
            ) => boolean;
          };
        }
      ).__editorTest.simulatePaletteDrop(t as string, p as number | null, i as number),
    [type, parentCardId, index] as const,
  );

  // The in-place insert adds exactly one real block card to the target list…
  // A generous timeout: the store round-trip (controller create + block-card
  // partial render) is slow over the distant RDS, well past the default 10s
  // expect budget — too short a wait aborts the in-flight POST.
  await expect(page.locator(cardSelector)).toHaveCount(before + 1, {
    timeout: 60_000,
  });
  // …and a success toast confirms the persisted creation.
  await expect(page.getByText("Block added").last()).toBeVisible({
    timeout: 60_000,
  });
}

test.describe("biolink editor — palette drag-and-drop block creation", () => {
  // Cold editor renders plus slow store round-trips over the distant RDS push a
  // single drop test past the default 90s budget; give the suite real headroom
  // (mirrors the card-gallery preview spec).
  test.describe.configure({ timeout: 180_000 });

  let baseline: Baseline;

  test.beforeAll(async ({ browser }) => {
    sharedContext = await browser.newContext();
    const page = await sharedContext.newPage();
    await loginAsDemo(page);
    await page.close();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  test.beforeEach(() => {
    baseline = resetEditorBiolink();
  });

  test("drops a palette tile at the TOP of the list", async ({ page }) => {
    await openEditor(page, baseline.linkId);
    const heading = await paletteLabel(page, "heading");

    await expect.poll(() => topLevelLabels(page), { timeout: 60_000 }).toEqual([
      "Divider",
      "Spacer",
      "Card Container",
    ]);

    await dropPalette(page, "heading", null, 0);

    await expect.poll(() => topLevelLabels(page), { timeout: 60_000 }).toEqual([
      heading,
      "Divider",
      "Spacer",
      "Card Container",
    ]);
  });

  test("drops a palette tile BETWEEN two blocks", async ({ page }) => {
    await openEditor(page, baseline.linkId);
    const heading = await paletteLabel(page, "heading");

    await dropPalette(page, "heading", null, 1);

    await expect.poll(() => topLevelLabels(page), { timeout: 60_000 }).toEqual([
      "Divider",
      heading,
      "Spacer",
      "Card Container",
    ]);
  });

  test("drops a palette tile at the END of the list", async ({ page }) => {
    await openEditor(page, baseline.linkId);
    const heading = await paletteLabel(page, "heading");

    await dropPalette(page, "heading", null, 99);

    await expect.poll(() => topLevelLabels(page), { timeout: 60_000 }).toEqual([
      "Divider",
      "Spacer",
      "Card Container",
      heading,
    ]);
  });

  test("drops a palette tile INSIDE a Card Container", async ({ page }) => {
    await openEditor(page, baseline.linkId);
    const link = await paletteLabel(page, "link");

    const childList = page.locator(
      `.card-child-list[data-card-id="${baseline.cardId}"]`,
    );
    await expect(childList.locator("> .child-block-card")).toHaveCount(1);

    await dropPalette(page, "link", baseline.cardId, 0);

    const children = page.locator(
      `.card-child-list[data-card-id="${baseline.cardId}"] > .child-block-card`,
    );
    await expect(children).toHaveCount(2);
    // New block landed first inside the card → parented to the card.
    await expect(
      children.nth(0).locator("span.font-semibold").first(),
    ).toHaveText(link);
    // The top level did NOT gain a block — it went into the card, not the canvas.
    await expect.poll(() => topLevelLabels(page), { timeout: 60_000 }).toEqual([
      "Divider",
      "Spacer",
      "Card Container",
    ]);
  });

  test("rejects a card-type tile dropped inside a Card Container", async ({
    page,
  }) => {
    await openEditor(page, baseline.linkId);

    const canDrop = (type: string) =>
      page.evaluate(
        (t) =>
          (
            window as unknown as {
              __editorTest: { canDropInCard: (type: string) => boolean };
            }
          ).__editorTest.canDropInCard(t),
        type,
      );

    // A 'card' tile is rejected; an ordinary block (link) is accepted.
    expect(await canDrop("card")).toBe(false);
    expect(await canDrop("link")).toBe(true);
  });

  test("disables the drop animation under prefers-reduced-motion", async ({
    page,
  }) => {
    // Default (no preference): full drop animation on every sortable.
    await page.emulateMedia({ reducedMotion: "no-preference" });
    await openEditor(page, baseline.linkId);
    const normal = await page.evaluate(
      () =>
        (window as unknown as { __editorTest: { dropAnim: number } }).__editorTest
          .dropAnim,
    );
    expect(normal).toBe(250);

    // Reduced motion: drop animation collapses to 0 on top + card sortables.
    await page.emulateMedia({ reducedMotion: "reduce" });
    await openEditor(page, baseline.linkId);
    const reduced = await page.evaluate(() => {
      const t = (
        window as unknown as {
          __editorTest: {
            dropAnim: number;
            prefersReducedMotion: boolean;
            topAnimation: number | null;
            cardAnimation: (id: number) => number | null;
            cardIds: () => string[];
          };
        }
      ).__editorTest;
      return {
        dropAnim: t.dropAnim,
        prefersReducedMotion: t.prefersReducedMotion,
        topAnimation: t.topAnimation,
        cardAnimation: t.cardAnimation(Number(t.cardIds()[0])),
      };
    });
    expect(reduced.prefersReducedMotion).toBe(true);
    expect(reduced.dropAnim).toBe(0);
    expect(reduced.topAnimation).toBe(0);
    expect(reduced.cardAnimation).toBe(0);
  });
});
