import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
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

// Per-run unique alias (shared RDS across parallel task environments —
// a fixed alias collides with another environment running this spec).
const ALIAS_PREFIX = "e2e-slides-edit-";
const ALIAS = `${ALIAS_PREFIX}${Date.now().toString(36)}${process.pid % 1000}`;

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
 * Seed the demo user plus a slides-mode biolink with ONE existing paragraph
 * block and a draft one-slide deck hosting it. Also prunes stale fixtures
 * from prior runs (this spec creates a fresh alias every run).
 */
function seedFixtures(): { linkId: number } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Models\\LinkSlideDeck;
use App\\Modules\\User\\Models\\LinkSlide;
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

// Prune stale fixture links (>2h) from earlier runs of this spec.
Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}%')
  ->where('created_at', '<', now()->subHours(2))
  ->get()
  ->each(function ($l) {
    LinkSlideDeck::withoutGlobalScope('workspace')->where('link_id', $l->id)->get()
      ->each(function ($d) { $d->slides()->delete(); $d->delete(); });
    BiolinkBlock::where('link_id', $l->id)->delete();
    $l->delete();
  });

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Slides Block Create', 'is_active' => true,
  'settings' => ['biolink' => ['mode' => 'slides']],
]);

$p = BiolinkBlock::create([
  'link_id' => $bio->id, 'workspace_id' => $bio->workspace_id,
  'type' => 'paragraph', 'sort_order' => 0, 'is_active' => true,
  'settings' => ['text' => 'Existing paragraph body'],
]);

$deck = LinkSlideDeck::create([
  'link_id' => $bio->id, 'workspace_id' => $bio->workspace_id,
  'version' => 1, 'is_published' => false,
  'settings' => ['theme' => ['background' => '#0f172a', 'accent' => '#5c83ff', 'text' => '#f8fafc'], 'transition' => 'slide', 'auto_advance' => 0, 'loop' => false],
]);
LinkSlide::create([
  'deck_id' => $deck->id, 'sort_order' => 0, 'title' => 'First slide',
  'block_ids' => [$p->id],
  'background' => ['type' => 'color', 'color' => '#0f172a'],
  'animation' => ['enter' => 'fade', 'duration_ms' => 400],
  'transition' => 'slide', 'settings' => [],
]);

echo 'IDS=' . json_encode(['linkId' => $bio->id]);
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/IDS=(\{.*\})/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return JSON.parse(m[1]);
}

let ids: { linkId: number };

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

test.describe("slides editor — in-slide block creation flow", () => {
  test("creates a Heading block from a slide, toggles auto-play, publishes, public page reflects both", async ({
    page,
  }) => {
    // Slow-write env: editor page + several sequential RDS POSTs.
    test.setTimeout(180_000);

    await page.goto(`/user/links/${ids.linkId}/slides`, {
      waitUntil: "domcontentloaded",
    });

    const card = page.locator("#sl-slides .sl-slide-card").first();
    await expect(card).toBeVisible();

    // The seeded paragraph is already hosted on the slide as a chip.
    await expect(card.locator(".sl-block-chip")).toHaveCount(1);
    await expect(card.locator(".sl-block-chip").first()).toContainText(
      "paragraph",
    );

    // The attach-picker must NOT offer the already-attached paragraph —
    // with the only existing block attached, its placeholder collapses to
    // the "all attached" message.
    await expect(
      card.locator(".sl-add-block option", {
        hasText: "All existing blocks are on this slide",
      }),
    ).toHaveCount(1);

    // --- Create a brand-new Heading block from within the slide. ---
    // The picker POSTs to the shared biolink block store endpoint; the
    // original bug was this flow silently no-op'ing, so we assert the
    // network round-trip AND the visible chip + feedback line.
    const blockStore = page.waitForResponse(
      (r) =>
        r.request().method() === "POST" &&
        r.url().includes(`/user/links/${ids.linkId}/blocks`) &&
        r.status() === 200,
      { timeout: 60_000 },
    );
    await card.locator(".sl-new-block").selectOption("heading");
    const storeJson = await (await blockStore).json();
    expect(storeJson.success).toBeTruthy();
    expect(storeJson.block?.type).toBe("heading");

    // Chip for the new heading appears (label derives from placeholder text).
    await expect(card.locator(".sl-block-chip")).toHaveCount(2);
    await expect(
      card.locator(".sl-block-chip", { hasText: "heading" }),
    ).toHaveCount(1);

    // Transient feedback line confirms creation + attachment.
    const feedback = card.locator(".sl-block-feedback");
    await expect(feedback).toBeVisible();
    await expect(feedback).toContainText("Created");
    await expect(feedback).toContainText("added it to this slide");

    // Let the debounced autosave from the creation settle before we start
    // toggling deck settings (avoids interleaved draft saves).
    await page.waitForResponse(
      (r) =>
        r.request().method() === "POST" &&
        r.url().includes(`/user/links/${ids.linkId}/slides`) &&
        r.status() === 200,
      { timeout: 60_000 },
    );

    // --- Toggle auto-play on at 3 sec/slide. ---
    const autoOn = page.locator("#sl-auto-on");
    await expect(autoOn).not.toBeChecked();
    const autoSecs = page.locator("#sl-auto");
    await expect(autoSecs).toBeDisabled();
    await autoOn.check();
    await expect(autoSecs).toBeEnabled();
    await autoSecs.fill("3");

    // --- Publish. ---
    const publishResp = page.waitForResponse(
      (r) =>
        r.request().method() === "POST" &&
        r.url().includes(`/user/links/${ids.linkId}/slides`) &&
        r.request().postData()?.includes('"is_published":true') === true &&
        r.status() === 200,
      { timeout: 60_000 },
    );
    await page.locator("#sl-publish").click();
    const pubJson = await (await publishResp).json();
    expect(pubJson.is_published).toBe(true);

    await expect(page.locator("#sl-status-pill")).toHaveClass(/\blive\b/);
    await expect(page.locator("#sl-status-pill")).toContainText("Published v");

    // --- Public page reflects the published snapshot. ---
    await page.goto(`/${ALIAS}`, { waitUntil: "domcontentloaded" });

    // The newly created heading block renders with its placeholder text.
    await expect(page.locator(".sl-slide").first()).toContainText(
      "Hello, I'm new here",
    );
    // The pre-existing paragraph is still there too.
    await expect(page.locator(".sl-slide").first()).toContainText(
      "Existing paragraph body",
    );

    // Auto-play made it into the public runtime: AUTO_MS is 3s in ms.
    const html = await page.content();
    expect(html).toContain("const AUTO_MS = 3000");
  });
});
