import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";

const ALIAS = "e2e-slides-demo";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/**
 * Seed (idempotently) a published 2-slide deck on a biolink with a known
 * alias so the browser test has something to assert against.
 *
 * Done via `php artisan tinker --execute=...` so the spec is fully
 * self-bootstrapping: a fresh CI runner only needs the Laravel app
 * running + migrations applied. Re-running the seed is a no-op once the
 * alias exists (the helper exits early with EXISTS).
 */
function seedSlidesBiolink(): void {
  // NOTE: this string is passed straight to `tinker --execute=`. In a JS
  // template literal, `\\` becomes the single backslash PHP namespaces need
  // (e.g. App\Modules\...), while `$var` stays literal (only `${...}` would
  // interpolate). Do NOT write `\\$` — that yields invalid `\$var` PHP, which
  // is what intermittently tripped the psysh / PHP 8.4 ParseErrorException.
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Models\\LinkSlideDeck;
use App\\Modules\\User\\Models\\LinkSlide;
use App\\Modules\\User\\Controllers\\SlideDeckController;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Str;
use Illuminate\\Support\\Facades\\Hash;

$alias = '${ALIAS}';
$bio = Link::withoutGlobalScope('workspace')->where('alias', $alias)->first();
if ($bio) {
  $deckExists = LinkSlideDeck::withoutGlobalScope('workspace')
    ->where('link_id', $bio->id)->where('is_published', true)->exists();
  if ($deckExists) { echo 'OK_EXISTS'; return; }
}

if (!$bio) {
  $u = User::create([
    'name' => 'Slides E2E', 'email' => 'slides-e2e+'.Str::random(4).'@ex.com',
    'password' => Hash::make('x'), 'status' => 'active',
  ]);
  $ws = app(WorkspaceContext::class)->resolve($u);
  $bio = Link::create([
    'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
    'alias' => $alias, 'title' => 'E2E Slides Demo', 'is_active' => true,
    'settings' => ['biolink' => ['mode' => 'slides']],
  ]);
}

$blocks = BiolinkBlock::where('link_id', $bio->id)->orderBy('sort_order')->get();
if ($blocks->count() < 2) {
  BiolinkBlock::create(['link_id'=>$bio->id,'workspace_id'=>$bio->workspace_id,'type'=>'paragraph','sort_order'=>0,'is_active'=>true,'settings'=>['text'=>'AAA-first-slide-body']]);
  BiolinkBlock::create(['link_id'=>$bio->id,'workspace_id'=>$bio->workspace_id,'type'=>'paragraph','sort_order'=>1,'is_active'=>true,'settings'=>['text'=>'BBB-second-slide-body']]);
  $blocks = BiolinkBlock::where('link_id', $bio->id)->orderBy('sort_order')->get();
}
$b1 = $blocks[0]; $b2 = $blocks[1];

$deck = LinkSlideDeck::create([
  'link_id' => $bio->id, 'workspace_id' => $bio->workspace_id,
  'version' => 1, 'is_published' => true,
  'settings' => ['theme' => ['background' => '#0f172a', 'accent' => '#8b5cf6', 'text' => '#f8fafc'], 'transition' => 'slide', 'auto_advance' => 0, 'loop' => false],
]);
LinkSlide::create(['deck_id'=>$deck->id,'sort_order'=>0,'title'=>'AAA-First-Slide','block_ids'=>[$b1->id],'background'=>['type'=>'color','color'=>'#0f172a'],'animation'=>['enter'=>'fade','duration_ms'=>400],'transition'=>'slide','settings'=>[]]);
LinkSlide::create(['deck_id'=>$deck->id,'sort_order'=>1,'title'=>'BBB-Second-Slide','block_ids'=>[$b2->id],'background'=>['type'=>'color','color'=>'#0f172a'],'animation'=>['enter'=>'fade','duration_ms'=>400],'transition'=>'slide','settings'=>[]]);
$deck->load('slides');
$deck->published_snapshot = SlideDeckController::buildSnapshot($deck, $bio);
$deck->save();
echo 'OK_CREATED';
`.trim();

  execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    stdio: "inherit",
  });
}

test.describe("biolink slides mode (web)", () => {
  test.beforeAll(() => {
    seedSlidesBiolink();
  });

  test("renders both slides and advances on keyboard + swipe, pings tracker", async ({
    page,
  }) => {
    const trackerHits: string[] = [];
    page.on("request", (req) => {
      if (req.method() === "POST" && req.url().includes(`/sl/${ALIAS}/view`)) {
        trackerHits.push(req.url());
      }
    });

    await page.goto(`/${ALIAS}`);

    // Two .sl-slide sections, with the seeded titles + bodies.
    const slides = page.locator(".sl-slide");
    await expect(slides).toHaveCount(2);
    await expect(page.locator('.sl-slide[data-i="0"]')).toContainText(
      "AAA-First-Slide",
    );
    await expect(page.locator('.sl-slide[data-i="0"]')).toContainText(
      "AAA-first-slide-body",
    );
    await expect(page.locator('.sl-slide[data-i="1"]')).toContainText(
      "BBB-Second-Slide",
    );
    await expect(page.locator('.sl-slide[data-i="1"]')).toContainText(
      "BBB-second-slide-body",
    );

    // Initial runtime state: slide 0 is active, counter "1 / 2".
    await expect(page.locator('.sl-slide[data-i="0"]')).toHaveClass(
      /\bis-active\b/,
    );
    await expect(page.locator('.sl-slide[data-i="1"]')).not.toHaveClass(
      /\bis-active\b/,
    );
    await expect(page.locator("#sl-counter")).toHaveText("1 / 2");

    // ArrowRight advances to slide 1.
    await page.keyboard.press("ArrowRight");
    await expect(page.locator('.sl-slide[data-i="1"]')).toHaveClass(
      /\bis-active\b/,
    );
    await expect(page.locator('.sl-slide[data-i="0"]')).not.toHaveClass(
      /\bis-active\b/,
    );
    await expect(page.locator("#sl-counter")).toHaveText("2 / 2");

    // ArrowLeft goes back to slide 0.
    await page.keyboard.press("ArrowLeft");
    await expect(page.locator('.sl-slide[data-i="0"]')).toHaveClass(
      /\bis-active\b/,
    );
    await expect(page.locator("#sl-counter")).toHaveText("1 / 2");

    // Synthesize a swipe-left touch gesture on #sl-deck. The inline JS
    // listens for touchstart/touchend with a >60px horizontal swing
    // within 800ms, so we dispatch fully-formed TouchEvents.
    await page.evaluate(() => {
      const deck = document.getElementById("sl-deck")!;
      const mk = (x: number) => ({
        identifier: 1,
        target: deck,
        clientX: x,
        clientY: 400,
        pageX: x,
        pageY: 400,
        screenX: x,
        screenY: 400,
        radiusX: 1,
        radiusY: 1,
        rotationAngle: 0,
        force: 1,
      });
      const start = new TouchEvent("touchstart", {
        bubbles: true,
        cancelable: true,
        // @ts-expect-error — Touch ctor exists in Chromium
        changedTouches: [new Touch(mk(300))],
        // @ts-expect-error
        touches: [new Touch(mk(300))],
        // @ts-expect-error
        targetTouches: [new Touch(mk(300))],
      });
      const end = new TouchEvent("touchend", {
        bubbles: true,
        cancelable: true,
        // @ts-expect-error
        changedTouches: [new Touch(mk(100))],
        touches: [],
        targetTouches: [],
      });
      deck.dispatchEvent(start);
      deck.dispatchEvent(end);
    });
    await expect(page.locator('.sl-slide[data-i="1"]')).toHaveClass(
      /\bis-active\b/,
    );
    await expect(page.locator("#sl-counter")).toHaveText("2 / 2");

    // The inline tracker fires on every show() — it must have hit at
    // least once during the navigation above.
    expect(trackerHits.length).toBeGreaterThan(0);
  });
});
