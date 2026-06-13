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

  const out = execFileSync(
    "php",
    ["artisan", "tinker", "--execute=" + php],
    { cwd: ARTIFACT_ROOT, encoding: "utf8" },
  );
  const m = out.match(/LINKID=(\d+) CARDID=(\d+)/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return { linkId: Number(m[1]), cardId: Number(m[2]) };
}

/** Log in as the demo user (non-prod quick-login) via the login form. */
async function loginAsDemo(page: Page): Promise<void> {
  await page.goto("/user/login");
  await Promise.all([
    page.waitForURL(/\/user\/dashboard/),
    page.locator('form[action$="/user/demo-login"] button[type="submit"]').click(),
  ]);
}

/** Open the block editor for a link with the test hooks armed. */
async function openEditor(page: Page, linkId: number): Promise<void> {
  await page.addInitScript(() => {
    (window as unknown as { __E2E__: boolean }).__E2E__ = true;
  });
  await page.goto(`/user/links/${linkId}/blocks`);
  await page.waitForFunction(
    () => !!(window as unknown as { __editorTest?: unknown }).__editorTest,
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

/** Ordered top-level block-type labels currently rendered on the canvas. */
async function topLevelLabels(page: Page): Promise<string[]> {
  const wrappers = page.locator("#blockList > .block-card-wrapper");
  const n = await wrappers.count();
  const out: string[] = [];
  for (let i = 0; i < n; i++) {
    out.push(
      (
        await wrappers.nth(i).locator("span.text-sm.font-semibold").first().innerText()
      ).trim(),
    );
  }
  return out;
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
  await expect(page.locator(cardSelector)).toHaveCount(before + 1);
  // …and a success toast confirms the persisted creation.
  await expect(page.getByText("Block added").last()).toBeVisible();
}

test.describe("biolink editor — palette drag-and-drop block creation", () => {
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

    expect(await topLevelLabels(page)).toEqual(["Divider", "Spacer", "Card Container"]);

    await dropPalette(page, "heading", null, 0);

    expect(await topLevelLabels(page)).toEqual([
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

    expect(await topLevelLabels(page)).toEqual([
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

    expect(await topLevelLabels(page)).toEqual([
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
    expect(await topLevelLabels(page)).toEqual(["Divider", "Spacer", "Card Container"]);
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
