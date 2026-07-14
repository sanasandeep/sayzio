import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";

/**
 * Guards the public biolink renderer's `preset` background branch.
 *
 * The public page (common/biolink.blade.php) resolves the preset CSS
 * server-side via BgPresetCatalog::css() and inlines it verbatim into the
 * <style> block's `body { ... }` rule. A refactor that drops the branch (or
 * the catalog lookup) would silently render every preset-background biolink
 * on the dark fallback — this spec catches that at the browser level.
 *
 * Covers one preset from each catalog group:
 *   - gradients: gradient_zero
 *   - abstract:  abstract_one
 *   - patterns:  abs_back_1
 *
 * No login needed — these are public pages. Fixtures use per-run unique
 * aliases (shared RDS across parallel task envs) with stale-prefix pruning.
 */

const ALIAS_PREFIX = "e2e-bgpre-";
const RUN_ID = Date.now().toString(36);

// One preset per catalog group. The CSS is read from the live catalog during
// seeding (not hardcoded here) so the assertion can never drift from source.
const PRESETS = [
  { group: "gradients", key: "gradient_zero" },
  { group: "abstract", key: "abstract_one" },
  { group: "patterns", key: "abs_back_1" },
] as const;

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

type Fixture = { alias: string; group: string; key: string; css: string };

/**
 * Seed one active biolink per preset (background_type=preset + bg_preset_key)
 * owned by a dedicated fixture user, prune stale fixtures from earlier runs,
 * and report each preset's catalog CSS (base64, JSON) for the assertions.
 */
function seedFixtures(): Fixture[] {
  const keys = PRESETS.map((p) => p.key);
  const aliases = PRESETS.map((p) => `${ALIAS_PREFIX}${p.key.replace(/_/g, "-")}-${RUN_ID}`);
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Support\\BgPresetCatalog;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;

// Prune stale fixtures from earlier runs (shared RDS, per-run aliases).
Link::withoutGlobalScope('workspace')
    ->where('alias', 'like', '${ALIAS_PREFIX}%')
    ->where('created_at', '<', now()->subHours(2))
    ->delete();

$u = User::where('email', 'e2e-bgpreset@example.com')->first();
if (!$u) {
  $u = User::create([
    'name' => 'E2E BgPreset', 'email' => 'e2e-bgpreset@example.com',
    'password' => Hash::make(bin2hex(random_bytes(16))),
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

$keys = ${JSON.stringify(keys)};
$aliases = ${JSON.stringify(aliases)};
$out = [];
foreach ($keys as $i => $key) {
  $css = BgPresetCatalog::css($key);
  if (!$css) { throw new RuntimeException('Unknown preset key: ' . $key); }
  $bio = Link::create([
    'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
    'alias' => $aliases[$i], 'title' => 'E2E BG Preset ' . $key,
    'is_active' => true,
    'settings' => ['biolink' => [
      'background_type' => 'preset',
      'bg_preset_key' => $key,
    ]],
  ]);
  $out[] = ['alias' => $aliases[$i], 'key' => $key, 'css_b64' => base64_encode($css)];
}
echo 'FIXTURES_JSON=' . json_encode($out) . '=END';
`.trim();

  const out = runTinker(php);
  const m = out.match(/FIXTURES_JSON=(\[.*?\])=END/s);
  if (!m) throw new Error("Seed failed, tinker output:\n" + out);
  const raw = JSON.parse(m[1]) as { alias: string; key: string; css_b64: string }[];
  return raw.map((r, i) => ({
    alias: r.alias,
    key: r.key,
    group: PRESETS[i].group,
    css: Buffer.from(r.css_b64, "base64").toString("utf8"),
  }));
}

let fixtures: Fixture[];

test.beforeAll(() => {
  // Tinker over a distant RDS — keep it out of the per-test timeout budget.
  fixtures = seedFixtures();
});

for (const preset of PRESETS) {
  test(`public biolink page renders the '${preset.key}' preset background (${preset.group})`, async ({
    page,
  }) => {
    const fx = fixtures.find((f) => f.key === preset.key)!;

    const resp = await page.goto(`/${fx.alias}`, {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });
    expect(resp?.ok()).toBe(true);

    // 1) The catalog CSS must be inlined verbatim in a <style> block — this is
    //    exactly what the blade's `{!! $bgPresetCss !!}` branch emits.
    const styleTexts = await page
      .locator("style")
      .evaluateAll((els) => els.map((el) => el.textContent ?? ""));
    const containing = styleTexts.filter((t) => t.includes(fx.css));
    expect(
      containing.length,
      `expected a <style> block to contain the '${fx.key}' catalog CSS verbatim`,
    ).toBeGreaterThan(0);

    // The preset CSS must live inside the `body { ... }` rule of that block.
    const styleWithCss = containing[0];
    const bodyRuleIdx = styleWithCss.indexOf("body {");
    expect(bodyRuleIdx).toBeGreaterThanOrEqual(0);
    expect(styleWithCss.indexOf(fx.css)).toBeGreaterThan(bodyRuleIdx);

    // 2) The browser must actually apply it: every preset in the catalog is
    //    gradient-based, so the computed background-image on <body> must
    //    contain at least one gradient (the dark fallback has none).
    const bgImage = await page.evaluate(
      () => getComputedStyle(document.body).backgroundImage,
    );
    expect(bgImage).toMatch(/gradient\(/);

    // 3) Sanity: the fallback-only branch (unknown key) sets background-color
    //    only; a preset page must not be sitting on the bare dark fallback
    //    with no background-image.
    expect(bgImage).not.toBe("none");
  });
}
