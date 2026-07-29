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

/**
 * Sticker drag-to-place on the image block editor (Task #5945 stage).
 *
 * Verifies the Alpine drag stage in image-style-settings.blade.php actually
 * works in a browser: a pointer drag on a sticker re-anchors it to the
 * nearest position preset and writes clamped dx/dy offsets into the hidden
 * style[_photo_stickers] JSON input.
 */

// Shared logged-in context (demo-login is rate-limited at throttle:5,1).
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

// Per-run unique alias: environments share the RDS, fixed aliases collide.
const ALIAS_PREFIX = "e2e-sticker-drag-";
const ALIAS = ALIAS_PREFIX + Date.now().toString(36) + process.pid.toString(36);

// 1x1 transparent PNG; keeps the sticker <img> deterministic (no network).
const STICKER_URL =
  "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";

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
 * Seed the demo user plus a biolink holding a single image block that already
 * has one sticker anchored top_right. Also prunes stale fixture links from
 * prior runs (>2h old) so the shared RDS doesn't accumulate rows.
 */
function seedFixtures(): { linkId: number; blockId: number } {
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

// Prune stale fixtures from earlier runs sharing this RDS.
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}%')
  ->where('created_at', '<', now()->subHours(2))->get();
foreach ($stale as $sl) { BiolinkBlock::where('link_id', $sl->id)->delete(); $sl->delete(); }

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Sticker Drag', 'is_active' => true,
]);

$img = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'image', 'sort_order' => 0, 'is_active' => true,
  'settings' => [
    'url' => '',
    'alt' => 'Sticker drag fixture',
    '_style' => [
      '_photo_stickers' => [[
        'file_id' => 0,
        'url' => '${STICKER_URL}',
        'pos' => 'top_right', 'size' => 64, 'rotate' => 0, 'dx' => 0, 'dy' => 0,
      ]],
    ],
  ],
]);

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'blockId' => $img->id]);
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

type Sticker = {
  pos: string;
  dx: number;
  dy: number;
  size: number;
};

const STAGE_SEL = 'div[x-ref="dragStage"]';
// The draggable element is the sticker wrapper (Task #5949 wrapped the img in
// a div carrying the pointerdown handler plus a corner resize handle).
// Grabbing at the wrapper's CENTER keeps the drag away from that handle.
const STICKER_SEL = `${STAGE_SEL} div.absolute.group`;
const HIDDEN_SEL = 'input[name="style[_photo_stickers]"]';

// Mirror of the Blade component's anchorBase() so the spec can predict the
// nearest-anchor outcome from the measured stage dimensions.
function anchorBase(
  pos: string,
  S: number,
  W: number,
  H: number,
): { x: number; y: number } {
  switch (pos) {
    case "top_left":
      return { x: -10, y: -10 };
    case "bottom_left":
      return { x: -10, y: H - S + 10 };
    case "bottom_right":
      return { x: W - S + 10, y: H - S + 10 };
    case "center_left":
      return { x: -12, y: H / 2 - S / 2 };
    case "center_right":
      return { x: W - S + 12, y: H / 2 - S / 2 };
    default:
      return { x: W - S + 10, y: -10 }; // top_right
  }
}

const ALL_POS = [
  "top_left",
  "top_right",
  "bottom_left",
  "bottom_right",
  "center_left",
  "center_right",
];

function predict(
  left: number,
  top: number,
  S: number,
  W: number,
  H: number,
): Sticker {
  let best: { pos: string; dx: number; dy: number; d: number } | null = null;
  for (const pos of ALL_POS) {
    const b = anchorBase(pos, S, W, H);
    const dx = left - b.x;
    const dy = top - b.y;
    const d = dx * dx + dy * dy;
    if (!best || d < best.d) best = { pos, dx, dy, d };
  }
  return {
    pos: best!.pos,
    dx: Math.max(-80, Math.min(80, Math.round(best!.dx))),
    dy: Math.max(-80, Math.min(80, Math.round(best!.dy))),
    size: S,
  };
}

async function readStickers(page: Page): Promise<Sticker[]> {
  const raw = await page.locator(HIDDEN_SEL).inputValue();
  return raw ? (JSON.parse(raw) as Sticker[]) : [];
}

/** Drag the sticker so its top-left lands at stage-relative (left, top). */
async function dragStickerTo(
  page: Page,
  left: number,
  top: number,
): Promise<void> {
  const stage = page.locator(STAGE_SEL);
  const sticker = page.locator(STICKER_SEL);
  const stageBox = (await stage.boundingBox())!;
  const stBox = (await sticker.boundingBox())!;

  // Grab the sticker at its center; moving the pointer by a delta moves the
  // sticker top-left by the same delta (startDrag preserves the offset).
  const startX = stBox.x + stBox.width / 2;
  const startY = stBox.y + stBox.height / 2;
  const deltaX = stageBox.x + left - stBox.x;
  const deltaY = stageBox.y + top - stBox.y;

  await page.mouse.move(startX, startY);
  await page.mouse.down();
  // A few intermediate moves so pointermove handlers fire naturally.
  await page.mouse.move(startX + deltaX / 2, startY + deltaY / 2, { steps: 4 });
  await page.mouse.move(startX + deltaX, startY + deltaY, { steps: 4 });
  await page.mouse.up();
}

test.describe.configure({ mode: "serial" });

test("pointer drag re-anchors the sticker and writes clamped dx/dy JSON", async ({
  page,
}) => {
  test.setTimeout(240_000);

  // The block editor is a heavy Blade render over the distant RDS.
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".block-card", { timeout: 45_000 });

  // Open the image block's inline editor and expand the Image Styling section.
  await page.click(`[data-block-id="${ids.blockId}"] .edit-btn`);
  const body = page.locator(`[data-inline-editor-body="${ids.blockId}"]`);
  await expect(body.locator("form")).toBeVisible({ timeout: 30_000 });
  await body.getByRole("button", { name: /Image Styling/i }).click();

  // Drag stage + the seeded sticker become visible.
  const stage = page.locator(STAGE_SEL);
  await expect(stage).toBeVisible({ timeout: 15_000 });
  const sticker = page.locator(STICKER_SEL);
  await expect(sticker).toBeVisible();
  await stage.scrollIntoViewIfNeeded();

  // Wait for the ResizeObserver to have measured the stage (sticker gets a
  // real previewStyle width once stageW/H are non-zero).
  const stageBox = (await stage.boundingBox())!;
  expect(stageBox.width).toBeGreaterThan(100);
  expect(stageBox.height).toBeGreaterThan(100);
  const W = stageBox.width;
  const H = stageBox.height;
  const S = 64;

  // Baseline: hidden input still carries the seeded top_right sticker.
  const before = await readStickers(page);
  expect(before).toHaveLength(1);
  expect(before[0].pos).toBe("top_right");
  expect(before[0].dx).toBe(0);
  expect(before[0].dy).toBe(0);

  // ── Drag 1: to the exact bottom_left anchor point ──────────────────────
  const blBase = anchorBase("bottom_left", S, W, H);
  await dragStickerTo(page, blBase.x, blBase.y);

  await expect
    .poll(async () => (await readStickers(page))[0]?.pos, { timeout: 10_000 })
    .toBe("bottom_left");
  let st = (await readStickers(page))[0];
  // Dropped on the anchor itself: offsets are (near) zero. Small tolerance
  // for sub-pixel bounding-box rounding.
  expect(Math.abs(st.dx)).toBeLessThanOrEqual(3);
  expect(Math.abs(st.dy)).toBeLessThanOrEqual(3);

  // ── Drag 2: far from every anchor along X → dx clamps at the ±80 cap ──
  // Target: bottom band, 30px left of horizontal center. Nearest anchor is
  // bottom_left with a raw dx of ~(W/2 - S/2 - 30 + 10) >> 80.
  const targetLeft = W / 2 - S / 2 - 30;
  const targetTop = H - S + 10;
  const expected = predict(targetLeft, targetTop, S, W, H);
  expect(expected.pos).toBe("bottom_left");
  expect(expected.dx).toBe(80); // proves the raw remainder exceeded the cap

  await dragStickerTo(page, targetLeft, targetTop);

  await expect
    .poll(async () => (await readStickers(page))[0]?.dx, { timeout: 10_000 })
    .toBe(80);
  st = (await readStickers(page))[0];
  expect(st.pos).toBe("bottom_left");
  expect(Math.abs(st.dy - expected.dy)).toBeLessThanOrEqual(3);
  // Clamp bounds always hold.
  expect(st.dx).toBeLessThanOrEqual(80);
  expect(st.dx).toBeGreaterThanOrEqual(-80);
  expect(st.dy).toBeLessThanOrEqual(80);
  expect(st.dy).toBeGreaterThanOrEqual(-80);
});
