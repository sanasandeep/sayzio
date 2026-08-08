import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Frame,
  type Page,
} from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

// Shared logged-in context (demo-login is rate-limited at throttle:5,1).
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

// Per-run unique alias: fixed aliases collide across parallel task
// environments on the shared RDS. Stale rows from prior runs are pruned by
// prefix in the seeder.
const ALIAS_PREFIX = "e2e-prevfocus-";
const ALIAS = `${ALIAS_PREFIX}${Date.now().toString(36)}`;

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

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
 * Seed the demo user plus a biolink with three simple blocks, returning the
 * ids the spec needs. Stale fixtures from prior runs (same alias prefix) are
 * pruned first so the shared RDS doesn't accumulate rows.
 */
function seedFixtures(): { linkId: number; blockA: number; blockB: number } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
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
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

// Prune stale fixtures from earlier runs of this spec.
Link::withoutGlobalScope('workspace')
  ->where('user_id', $u->id)
  ->where('alias', 'like', '${ALIAS_PREFIX}%')
  ->get()
  ->each(function ($l) { BiolinkBlock::where('link_id', $l->id)->delete(); $l->delete(); });

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Preview Focus', 'is_active' => true,
]);

$a = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'heading', 'sort_order' => 0, 'is_active' => true, 'settings' => ['text' => 'Focus Heading A']]);
$b = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'paragraph', 'sort_order' => 1, 'is_active' => true, 'settings' => ['text' => 'Focus Body B']]);
// A tall run of spacer-ish paragraphs between B and the end so scrolling is
// actually required to bring blocks into view inside the phone preview.
for ($i = 0; $i < 12; $i++) {
  BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'paragraph', 'sort_order' => 2 + $i, 'is_active' => true, 'settings' => ['text' => str_repeat('Filler paragraph line ' . $i . '. ', 12)]]);
}

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'blockA' => $a->id, 'blockB' => $b->id]);
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/IDS=(\{.*\})/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return JSON.parse(m[1]);
}

let ids: ReturnType<typeof seedFixtures>;

test.beforeAll(async ({ browser }) => {
  ids = seedFixtures();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
});

function cardSel(id: number): string {
  return `.block-card-wrapper[data-block-id="${id}"]`;
}
function editBtnSel(id: number): string {
  return `[data-block-id="${id}"] .edit-btn`;
}

async function gotoEditor(page: Page): Promise<Frame> {
  // The editor is a heavy Blade render over the distant RDS; a cold hit can
  // exceed the default nav budget, and third-party subresources can hold the
  // `load` event open. DOMContentLoaded + explicit waits are the signal we
  // actually need.
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".block-card", { timeout: 45_000 });

  // Wait for the phone-preview iframe to load the signed public preview
  // (`?_preview=1`) and render the seeded blocks. The iframe starts blank and
  // gets its src set by editor JS, so poll page.frames().
  let frame: Frame | undefined;
  await expect
    .poll(
      () => {
        frame = page
          .frames()
          .find(
            (f) => f.url().includes(ALIAS) && f.url().includes("_preview=1"),
          );
        return Boolean(frame);
      },
      { timeout: 60_000, message: "preview iframe never loaded" },
    )
    .toBe(true);
  await frame!.waitForSelector(`[data-block-id="${ids.blockA}"]`, {
    timeout: 60_000,
  });
  return frame!;
}

/** The inline outline style set by the preview focus listener. */
function outlineOf(frame: Frame, blockId: number): Promise<string> {
  return frame.evaluate((id) => {
    const el = document.querySelector<HTMLElement>(
      `[data-block-id="${id}"]`,
    );
    return el ? el.style.outline : "<missing>";
  }, blockId);
}

/**
 * Vertical distance of the block's center from the viewport center, as a
 * fraction of viewport height (0 = perfectly centered), measured inside the
 * preview iframe. scrollIntoView({block:'center'}) clamps at document edges,
 * so "near center" is asserted loosely.
 */
function centerOffset(frame: Frame, blockId: number): Promise<number> {
  return frame.evaluate((id) => {
    const el = document.querySelector<HTMLElement>(
      `[data-block-id="${id}"]`,
    );
    if (!el) return 999;
    const r = el.getBoundingClientRect();
    const mid = r.top + r.height / 2;
    return Math.abs(mid - window.innerHeight / 2) / window.innerHeight;
  }, blockId);
}

test.describe.configure({ mode: "serial" });

test("hovering a block card scrolls the preview to the block without any highlight ring", async ({
  page,
}) => {
  test.setTimeout(240_000);
  const frame = await gotoEditor(page);

  // Nothing highlighted before any hover.
  expect(await outlineOf(frame, ids.blockA)).toBe("");

  // Hover block B's card (block A starts near the top, so B gives a real
  // scroll) and wait past the 150ms debounce; the focus postMessage then
  // drives a smooth scroll in the iframe.
  await page.hover(cardSel(ids.blockB));
  await expect
    .poll(() => centerOffset(frame, ids.blockB), {
      timeout: 15_000,
      message: "preview never scrolled to block B on hover",
    })
    .toBeLessThan(0.45);

  // The ring was removed per owner request: no outline may ever be drawn.
  expect(await outlineOf(frame, ids.blockB)).toBe("");

  // Hovering a different card scrolls again — still no outline anywhere.
  await page.hover(cardSel(ids.blockA));
  await expect
    .poll(() => centerOffset(frame, ids.blockA), { timeout: 15_000 })
    .toBeLessThan(0.45);
  expect(await outlineOf(frame, ids.blockA)).toBe("");
  expect(await outlineOf(frame, ids.blockB)).toBe("");
});

test("opening the edit drawer scrolls the preview to the block without highlighting it", async ({
  page,
}) => {
  test.setTimeout(240_000);
  const frame = await gotoEditor(page);

  // Open block B's inline edit drawer — openEditDrawer posts an immediate
  // focus for the edited block (no debounce on this path).
  await page.click(editBtnSel(ids.blockB));
  await expect(
    page.locator(`[data-inline-editor="${ids.blockB}"]`),
  ).toBeVisible();
  await expect
    .poll(() => centerOffset(frame, ids.blockB), {
      timeout: 15_000,
      message: "opening the drawer never scrolled the preview to the block",
    })
    .toBeLessThan(0.45);

  // No ring is ever drawn — even while the drawer stays open.
  await page.waitForTimeout(600); // past any debounce
  expect(await outlineOf(frame, ids.blockB)).toBe("");

  // Close the drawer (edit button toggles) — still nothing highlighted.
  await page.click(editBtnSel(ids.blockB));
  await expect(
    page.locator(`[data-inline-editor="${ids.blockB}"]`),
  ).toBeHidden();
  expect(await outlineOf(frame, ids.blockB)).toBe("");
});
