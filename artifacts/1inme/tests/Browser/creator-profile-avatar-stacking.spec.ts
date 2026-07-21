import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";

/**
 * Guard: the creator-profile hero avatar must never slip BEHIND the cover
 * banner again.
 *
 * History: the hero paints an absolutely-positioned cover <img> over the
 * banner band, and the avatar row is pulled up into that band with -mt-12.
 * Without `relative z-10` on the avatar container
 * (resources/views/public/creator-profile.blade.php), the cover image wins
 * the paint order and covers the top half of the avatar — and, worse, eats
 * its pointer events. This spec seeds a published creator WITH a cover image
 * and asserts via document.elementFromPoint that the top hit-target at the
 * avatar's center is inside the avatar container, not the cover <img>.
 *
 * Self-bootstrapping (php artisan tinker), per-run unique handle to avoid
 * shared-RDS collisions across parallel task envs, with stale-prefix pruning.
 */

const HANDLE_PREFIX = "e2eavst";
const HANDLE =
  HANDLE_PREFIX +
  Date.now().toString(36) +
  Math.random().toString(36).slice(2, 6);
// Separate fixture for the subscribe-enabled sibling test so the two tests
// never share state (and prune independently).
const SUB_HANDLE_PREFIX = "e2eavsb";
const SUB_HANDLE =
  SUB_HANDLE_PREFIX +
  Date.now().toString(36) +
  Math.random().toString(36).slice(2, 6);

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

function seedCreatorWithCover(
  prefix: string,
  handle: string,
  withTier = false,
): void {
  // NOTE: passed straight to `tinker --execute=`; `$var` stays literal in a
  // JS template literal, `\\` becomes the single backslash PHP needs.
  const tierPhp = withTier
    ? `
\\App\\Modules\\User\\Models\\SubscriptionTier::create([
  'user_id' => $u->id,
  'name' => 'Supporter',
  'slug' => \\App\\Modules\\User\\Models\\SubscriptionTier::makeSlug($u->id, 'Supporter'),
  'is_free' => false,
  'is_active' => true,
  'sort_order' => 0,
  'price_monthly_cents' => 500,
  'currency' => 'USD',
]);`
    : "";
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\Admin\\Models\\Plan;
use Illuminate\\Support\\Facades\\Hash;

// Prune stale fixtures from prior runs (handles are per-run unique).
User::where('handle', 'like', '${prefix}%')
  ->where('created_at', '<', now()->subDay())
  ->get()->each->delete();

$free = Plan::where('slug', 'free')->first();
$u = User::create([
  'name' => 'Avatar Stacking Fixture',
  'email' => '${handle}@example.test',
  'password' => Hash::make('password'),
  'plan_id' => $free?->id,
  'status' => 'active',
  'email_verified_at' => now(),
]);
$u->forceFill([
  'handle' => '${handle}',
  'profile_published' => true,
  // Any storage-ish path works: the <img> element renders (and hit-tests)
  // even if the file 404s, which is exactly the overlap we guard against.
  'cover_image' => 'creator-covers/e2e-avatar-stacking.png',
])->save();
${tierPhp}
echo 'SEEDED=' . $u->id;
`.trim();

  const out = execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
  if (!/SEEDED=\d+/.test(out)) {
    throw new Error("Seed failed, output:\n" + out);
  }
}

test("creator profile hero: avatar stays above the cover banner", async ({
  page,
}) => {
  // Cold first render over the distant RDS can be slow; give it room.
  test.setTimeout(240_000);
  seedCreatorWithCover(HANDLE_PREFIX, HANDLE);

  await page.goto(`/@${HANDLE}`, {
    waitUntil: "domcontentloaded",
    timeout: 180_000,
  });

  // The cover <img> is present (fixture has cover_image set), so the
  // overlap scenario this spec guards is actually on the page.
  const cover = page.locator("header .absolute.inset-0").first();
  await expect(cover).toBeAttached();

  // Avatar container: the pulled-up row under the banner. Keyed off -mt-12
  // so a refactor that renames it fails loudly here instead of silently
  // dropping the guard.
  const container = page.locator('header div[class*="-mt-12"]').first();
  await expect(container).toBeVisible();

  const result = await container.evaluate((el) => {
    const cs = getComputedStyle(el);
    // The avatar is the first w-24 box (img when set, initials div otherwise).
    const avatar = el.querySelector<HTMLElement>('[class*="w-24"]');
    if (!avatar) return { ok: false, why: "avatar element not found" };
    const r = avatar.getBoundingClientRect();
    avatar.scrollIntoView({ block: "center" });
    const r2 = avatar.getBoundingClientRect();
    const cx = r2.left + r2.width / 2;
    // Probe the TOP half of the avatar — the part overlapping the banner
    // band, which is exactly what the cover <img> used to paint over.
    const cyTop = r2.top + r2.height / 4;
    const cyMid = r2.top + r2.height / 2;
    const hitTop = document.elementFromPoint(cx, cyTop);
    const hitMid = document.elementFromPoint(cx, cyMid);
    const coverImg = document.querySelector("header .absolute.inset-0");
    return {
      ok: true,
      position: cs.position,
      zIndex: cs.zIndex,
      avatarHeight: r.height,
      topHitInsideContainer: !!hitTop && el.contains(hitTop),
      midHitInsideContainer: !!hitMid && el.contains(hitMid),
      topHitIsCover: hitTop === coverImg,
      midHitIsCover: hitMid === coverImg,
    };
  });

  expect(result.ok).toBe(true);
  // The stacking-context guard itself: the container must establish a
  // positioned, positively-z-indexed context above the cover image.
  expect((result as any).position).not.toBe("static");
  expect(Number((result as any).zIndex)).toBeGreaterThanOrEqual(1);
  // And the behavioral proof: the top hit-target over the avatar (including
  // the half that overlaps the banner) is the avatar, never the cover <img>.
  expect((result as any).topHitIsCover).toBe(false);
  expect((result as any).midHitIsCover).toBe(false);
  expect((result as any).topHitInsideContainer).toBe(true);
  expect((result as any).midHitInsideContainer).toBe(true);

  // ── Action buttons guard ──────────────────────────────────────────
  // The Follow/Subscribe buttons sit on the RIGHT side of the same -mt-12
  // row. If a refactor ever splits them into their own container without a
  // stacking context, the cover <img> would silently swallow their taps —
  // a direct conversion loss. Assert via elementFromPoint that the visitor
  // Follow button is the top hit-target, not the cover image.
  const followBtn = container
    .locator("button", { hasText: /Follow/i })
    .first();
  await expect(followBtn).toBeVisible();

  const btnResult = await followBtn.evaluate((el) => {
    el.scrollIntoView({ block: "center" });
    const r = el.getBoundingClientRect();
    const cx = r.left + r.width / 2;
    const cy = r.top + r.height / 2;
    // Also probe the top edge of the button — the part most likely to
    // overlap the banner band on narrow layouts.
    const cyTop = r.top + Math.min(2, r.height / 4);
    const hitMid = document.elementFromPoint(cx, cy);
    const hitTop = document.elementFromPoint(cx, cyTop);
    const coverImg = document.querySelector("header .absolute.inset-0");
    const inside = (hit: Element | null) =>
      !!hit && (el === hit || el.contains(hit) || hit.contains(el));
    return {
      midHitIsCover: hitMid === coverImg,
      topHitIsCover: hitTop === coverImg,
      midHitInsideButton: inside(hitMid),
      topHitInsideButton: inside(hitTop),
    };
  });

  expect(btnResult.midHitIsCover).toBe(false);
  expect(btnResult.topHitIsCover).toBe(false);
  expect(btnResult.midHitInsideButton).toBe(true);
  expect(btnResult.topHitInsideButton).toBe(true);

  // ── Tip button guard ─────────────────────────────────────────────
  // The Tip button ([data-cp-open-tip]) renders in the same action row for
  // any creator who can accept tips (the blade's `canAcceptTips ?? true`
  // default makes it present for this fixture). If its markup ever gains a
  // wrapper without a stacking context, the cover <img> would swallow its
  // taps. Same elementFromPoint proof as Follow/Subscribe.
  const tipBtn = container.locator("[data-cp-open-tip]").first();
  await expect(tipBtn).toBeVisible();

  const tipResult = await tipBtn.evaluate((el) => {
    el.scrollIntoView({ block: "center" });
    const r = el.getBoundingClientRect();
    const cx = r.left + r.width / 2;
    const cy = r.top + r.height / 2;
    // Also probe the top edge — the part most likely to overlap the banner
    // band on narrow layouts.
    const cyTop = r.top + Math.min(2, r.height / 4);
    const hitMid = document.elementFromPoint(cx, cy);
    const hitTop = document.elementFromPoint(cx, cyTop);
    const coverImg = document.querySelector("header .absolute.inset-0");
    const inside = (hit: Element | null) =>
      !!hit && (el === hit || el.contains(hit) || hit.contains(el));
    return {
      midHitIsCover: hitMid === coverImg,
      topHitIsCover: hitTop === coverImg,
      midHitInsideButton: inside(hitMid),
      topHitInsideButton: inside(hitTop),
    };
  });

  expect(tipResult.midHitIsCover).toBe(false);
  expect(tipResult.topHitIsCover).toBe(false);
  expect(tipResult.midHitInsideButton).toBe(true);
  expect(tipResult.topHitInsideButton).toBe(true);
});

test("creator profile hero: Subscribe CTA stays above the cover banner", async ({
  page,
}) => {
  // Subscribe only renders for creators with an active paid tier — the plain
  // free-plan fixture above never exercises it. Seed a subscribe-enabled
  // creator so the highest-value button on the page is actually on the page,
  // then prove via elementFromPoint that its taps land on it, not the cover.
  test.setTimeout(240_000);
  seedCreatorWithCover(SUB_HANDLE_PREFIX, SUB_HANDLE, true);

  await page.goto(`/@${SUB_HANDLE}`, {
    waitUntil: "domcontentloaded",
    timeout: 180_000,
  });

  const cover = page.locator("header .absolute.inset-0").first();
  await expect(cover).toBeAttached();

  const container = page.locator('header div[class*="-mt-12"]').first();
  await expect(container).toBeVisible();

  // Guest viewer + active paid tier + no subscription ⇒ the Subscribe <a>
  // (not the "Subscribed" manage link) renders in the hero action row.
  const subscribeCta = container
    .locator("a", { hasText: /Subscribe/i })
    .first();
  await expect(subscribeCta).toBeVisible();

  const ctaResult = await subscribeCta.evaluate((el) => {
    el.scrollIntoView({ block: "center" });
    const r = el.getBoundingClientRect();
    const cx = r.left + r.width / 2;
    const cy = r.top + r.height / 2;
    // Also probe the top edge — the part most likely to overlap the banner
    // band on narrow layouts.
    const cyTop = r.top + Math.min(2, r.height / 4);
    const hitMid = document.elementFromPoint(cx, cy);
    const hitTop = document.elementFromPoint(cx, cyTop);
    const coverImg = document.querySelector("header .absolute.inset-0");
    const inside = (hit: Element | null) =>
      !!hit && (el === hit || el.contains(hit) || hit.contains(el));
    return {
      midHitIsCover: hitMid === coverImg,
      topHitIsCover: hitTop === coverImg,
      midHitInsideCta: inside(hitMid),
      topHitInsideCta: inside(hitTop),
    };
  });

  expect(ctaResult.midHitIsCover).toBe(false);
  expect(ctaResult.topHitIsCover).toBe(false);
  expect(ctaResult.midHitInsideCta).toBe(true);
  expect(ctaResult.topHitInsideCta).toBe(true);
});
