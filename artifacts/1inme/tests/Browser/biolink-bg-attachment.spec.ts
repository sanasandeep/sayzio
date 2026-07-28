import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";

/**
 * Guards the Background Effects → Position (Fixed / Scroll, `bg_attachment`)
 * behavior on the live public biolink page.
 *
 * - "Fixed": the background renders on a dedicated `.bg-page-fixed`
 *   position:fixed layer behind the content (mobile-Safari-safe — no
 *   `background-attachment: fixed`), so it stays pinned while content scrolls.
 * - "Scroll": the background stays on the scrolling <body> (no fixed layer),
 *   so it moves with the content.
 *
 * Assertions are on computed layout/position (bounding rects before/after a
 * real scroll), not just CSS strings.
 *
 * No login needed — public pages. Fixtures use per-run unique aliases
 * (shared RDS across parallel task envs) with stale-prefix pruning.
 */

const ALIAS_PREFIX = "e2e-bgatt-";
const RUN_ID = Date.now().toString(36);
const PRESET_KEY = "gradient_zero";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

function runTinker(php: string): string {
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

let aliases: { fixed: string; scroll: string };

function seedFixtures(): { fixed: string; scroll: string } {
  const fixedAlias = `${ALIAS_PREFIX}fixed-${RUN_ID}`;
  const scrollAlias = `${ALIAS_PREFIX}scroll-${RUN_ID}`;
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;

// Prune stale fixtures from earlier runs (shared RDS, per-run aliases).
Link::withoutGlobalScope('workspace')
    ->where('alias', 'like', '${ALIAS_PREFIX}%')
    ->where('created_at', '<', now()->subHours(2))
    ->delete();

$u = User::where('email', 'e2e-bgattach@example.com')->first();
if (!$u) {
  $u = User::create([
    'name' => 'E2E BgAttach', 'email' => 'e2e-bgattach@example.com',
    'password' => Hash::make(bin2hex(random_bytes(16))),
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

foreach ([['${fixedAlias}', 'fixed'], ['${scrollAlias}', 'scroll']] as [$alias, $attach]) {
  Link::create([
    'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
    'alias' => $alias, 'title' => 'E2E BG Attach ' . $attach,
    'is_active' => true,
    'settings' => ['biolink' => [
      'background_type' => 'preset',
      'bg_preset_key' => '${PRESET_KEY}',
      'bg_attachment' => $attach,
    ]],
  ]);
}
echo 'SEEDED_OK=END';
`.trim();

  const out = runTinker(php);
  if (!out.includes("SEEDED_OK=END")) {
    throw new Error("Seed failed, tinker output:\n" + out);
  }
  return { fixed: fixedAlias, scroll: scrollAlias };
}

test.beforeAll(() => {
  // Tinker over a distant RDS — keep it out of the per-test timeout budget.
  aliases = seedFixtures();
});

async function visit(page: import("@playwright/test").Page, alias: string) {
  const resp = await page.goto(`/${alias}`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  expect(resp?.ok()).toBe(true);
  // Make the page tall enough to actually scroll regardless of block count.
  await page.evaluate(() => {
    const spacer = document.createElement("div");
    spacer.style.height = "3000px";
    spacer.id = "e2e-scroll-spacer";
    document.body.appendChild(spacer);
  });
}

test("Fixed position: background layer stays pinned while content scrolls", async ({
  page,
}) => {
  await visit(page, aliases.fixed);

  const layer = page.locator(".bg-page-fixed");
  await expect(layer).toHaveCount(1);

  // Computed layout: the layer is viewport-pinned and paints the preset.
  const info = await layer.evaluate((el) => {
    const cs = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    return { position: cs.position, bgImage: cs.backgroundImage, top: r.top };
  });
  expect(info.position).toBe("fixed");
  expect(info.bgImage).toMatch(/gradient\(/);
  expect(info.top).toBe(0);

  // Scroll the page — the fixed layer's viewport rect must not move.
  await page.evaluate(() => window.scrollTo(0, 800));
  await page.waitForFunction(() => window.scrollY >= 800);
  const topAfter = await layer.evaluate(
    (el) => el.getBoundingClientRect().top,
  );
  expect(topAfter).toBe(0);

  // The content container still sits above the layer.
  const containerZ = await page
    .locator(".biolink-container")
    .evaluate((el) => getComputedStyle(el).zIndex);
  expect(Number(containerZ)).toBeGreaterThanOrEqual(1);
});

test("Scroll position: background stays on the scrolling body (no fixed layer)", async ({
  page,
}) => {
  await visit(page, aliases.scroll);

  // No fixed background layer is rendered.
  await expect(page.locator(".bg-page-fixed")).toHaveCount(0);

  // The preset paints on <body> and its attachment is scroll — the background
  // is part of the scrolling document, i.e. it moves with the content.
  const bodyInfo = await page.evaluate(() => {
    const cs = getComputedStyle(document.body);
    return { bgImage: cs.backgroundImage, attachment: cs.backgroundAttachment };
  });
  expect(bodyInfo.bgImage).toMatch(/gradient\(/);
  expect(bodyInfo.attachment).toBe("scroll");

  // Computed-layout proof: the body's border-box (which the background paints
  // into) moves up in viewport coordinates when the page scrolls.
  const topBefore = await page.evaluate(
    () => document.body.getBoundingClientRect().top,
  );
  await page.evaluate(() => window.scrollTo(0, 800));
  await page.waitForFunction(() => window.scrollY >= 800);
  const topAfter = await page.evaluate(
    () => document.body.getBoundingClientRect().top,
  );
  expect(topAfter).toBeLessThan(topBefore - 700);
});
