import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";

/**
 * Guards the remaining background branches of the public biolink renderer
 * (common/biolink.blade.php `body { ... }` rule): image, gradient, and the
 * slideshow/video fallback-image branch.
 *
 * The preset branch already has coverage (biolink-bg-preset-public-render
 * .spec.ts) and it caught a real bug — a missing semicolon invalidated the
 * whole background declaration in the browser. The other branches share the
 * same inline-<style> structure, so the same class of regression (a stray
 * character, a dropped @elseif, glued declarations) would silently render
 * every such biolink on the bare dark fallback. These specs assert the
 * COMPUTED style on <body>, not just the markup, so an invalid declaration
 * fails the test exactly like it fails in a real browser.
 *
 * No login needed — public pages. Fixtures use per-run unique aliases
 * (shared RDS across parallel task envs) with stale-prefix pruning.
 */

const ALIAS_PREFIX = "e2e-bgbr-";
const RUN_ID = Date.now().toString(36);

// The image URLs don't need to resolve — getComputedStyle reports the url()
// token whether or not the resource loads, which is exactly what the blade
// emits. Distinct filenames let each assertion prove ITS branch fired.
const BG_IMAGE_URL = "/images/e2e-bg-image.png";
const BG_FALLBACK_IMAGE_URL = "/images/e2e-bg-fallback.png";
const BG_GRADIENT =
  "linear-gradient(90deg, rgb(1, 2, 3) 0%, rgb(4, 5, 6) 100%)";
const BG_FALLBACK_COLOR = "#123456";

type Case = {
  key: string;
  label: string;
  biolink: Record<string, unknown>;
};

const CASES: Case[] = [
  // The image/gradient cases pin bg_attachment=scroll so the background stays
  // on the scrolling <body> (the default "fixed" now renders on a dedicated
  // .bg-page-fixed layer — covered by biolink-bg-preset-public-render.spec.ts
  // and biolink-bg-attachment.spec.ts).
  {
    key: "image",
    label: "image background (with fallback color)",
    biolink: {
      background_type: "image",
      background_image: BG_IMAGE_URL,
      bg_fallback_color: BG_FALLBACK_COLOR,
      bg_attachment: "scroll",
    },
  },
  {
    key: "gradient",
    label: "custom gradient background",
    biolink: {
      background_type: "gradient",
      background_gradient: BG_GRADIENT,
      bg_attachment: "scroll",
    },
  },
  {
    key: "slideshow",
    label: "slideshow fallback image (no slides yet)",
    biolink: {
      background_type: "slideshow",
      bg_fallback_color: BG_FALLBACK_COLOR,
      bg_fallback_image: BG_FALLBACK_IMAGE_URL,
    },
  },
  {
    key: "video",
    label: "video fallback image",
    biolink: {
      background_type: "video",
      bg_fallback_color: BG_FALLBACK_COLOR,
      bg_fallback_image: BG_FALLBACK_IMAGE_URL,
    },
  },
];

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

/** alias per case, seeded once for the whole file. */
let aliases: Record<string, string>;

function seedFixtures(): Record<string, string> {
  const caseAliases = CASES.map(
    (c) => `${ALIAS_PREFIX}${c.key}-${RUN_ID}`,
  );
  const settings = CASES.map((c) => c.biolink);
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

$u = User::where('email', 'e2e-bgbranches@example.com')->first();
if (!$u) {
  $u = User::create([
    'name' => 'E2E BgBranches', 'email' => 'e2e-bgbranches@example.com',
    'password' => Hash::make(bin2hex(random_bytes(16))),
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

$aliases = ${JSON.stringify(caseAliases)};
$settings = json_decode('${JSON.stringify(settings).replace(/\\/g, "\\\\").replace(/'/g, "\\'")}', true);
foreach ($aliases as $i => $alias) {
  Link::create([
    'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
    'alias' => $alias, 'title' => 'E2E BG Branch ' . $alias,
    'is_active' => true,
    'settings' => ['biolink' => $settings[$i]],
  ]);
}
echo 'SEEDED=' . json_encode($aliases) . '=END';
`.trim();

  const out = runTinker(php);
  const m = out.match(/SEEDED=(\[.*?\])=END/s);
  if (!m) throw new Error("Seed failed, tinker output:\n" + out);
  const seeded = JSON.parse(m[1]) as string[];
  const map: Record<string, string> = {};
  CASES.forEach((c, i) => {
    map[c.key] = seeded[i];
  });
  return map;
}

test.beforeAll(() => {
  // Tinker over a distant RDS — keep it out of the per-test timeout budget.
  aliases = seedFixtures();
});

/** Computed body background info from the live page. */
async function bodyBackground(page: import("@playwright/test").Page) {
  return page.evaluate(() => {
    const cs = getComputedStyle(document.body);
    return {
      image: cs.backgroundImage,
      color: cs.backgroundColor,
      size: cs.backgroundSize,
      position: cs.backgroundPosition,
    };
  });
}

async function visit(page: import("@playwright/test").Page, alias: string) {
  const resp = await page.goto(`/${alias}`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  expect(resp?.ok()).toBe(true);
}

test("public biolink renders an image background via the image branch", async ({
  page,
}) => {
  await visit(page, aliases.image);
  const bg = await bodyBackground(page);

  // The blade emits `background: <fallback> url('<img>') center/cover
  // no-repeat <attachment>` — the browser must have parsed it as VALID
  // (an invalid shorthand computes to backgroundImage 'none').
  expect(bg.image).toContain("e2e-bg-image.png");
  expect(bg.size).toBe("cover");
  // Fallback color rides along in the same shorthand: #123456 = rgb(18,52,86).
  expect(bg.color).toBe("rgb(18, 52, 86)");
});

test("public biolink renders a custom gradient via the gradient branch", async ({
  page,
}) => {
  await visit(page, aliases.gradient);
  const bg = await bodyBackground(page);

  // The seeded gradient must be applied — not the dark default gradient and
  // not a bare color fallback. rgb(1,2,3)/rgb(4,5,6) are unique markers.
  expect(bg.image).toMatch(/linear-gradient\(/);
  expect(bg.image).toContain("rgb(1, 2, 3)");
  expect(bg.image).toContain("rgb(4, 5, 6)");
});

for (const key of ["slideshow", "video"] as const) {
  test(`public biolink renders the fallback image for the ${key} branch`, async ({
    page,
  }) => {
    await visit(page, aliases[key]);
    const bg = await bodyBackground(page);

    // The slideshow/video branch paints bg_fallback_color plus (when set)
    // bg_fallback_image cover/center. If the branch or its inner @if breaks,
    // the image drops to 'none' or the color drops to the dark default.
    expect(bg.image).toContain("e2e-bg-fallback.png");
    expect(bg.size).toBe("cover");
    expect(bg.color).toBe("rgb(18, 52, 86)");
  });
}
