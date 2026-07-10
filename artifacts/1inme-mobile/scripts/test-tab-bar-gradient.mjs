// Source-driven regression test for the floating tab bar's active indicator.
//
// The active "pill" circle behind the focused tab is painted with a
// LinearGradient driven by the `colors.brandGradient` theme token. There is
// no visual snapshot coverage on the mobile app, so a token rename, an
// accidental removal of the expo-linear-gradient import, or a swap of the
// gradient for a solid backgroundColor would silently ship a
// transparent / broken indicator. This pins the wiring in source:
//   1. FloatingTabBar.tsx still imports LinearGradient from
//      expo-linear-gradient.
//   2. The active circle renders a LinearGradient whose colors prop is
//      exactly colors.brandGradient (not a solid backgroundColor).
//   3. The circle keeps its scale-pop animation wiring (withSpring on
//      circleScale) so the "animation still looks right" half of the task
//      is guarded too.
//   4. constants/colors.ts declares brandGradient as a multi-stop gradient
//      for BOTH the dark and light palettes.
//
// Follows the source-driven convention (see test-cta-variant.mjs): no RN
// runner, just read the real shipped sources and assert. Run via
// `node scripts/test-tab-bar-gradient.mjs` (package script
// `test:tab-bar-gradient`, wired into the `test:unit` gate that the
// `mobile-unit` workflow runs).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

const read = (...p) => readFileSync(join(root, ...p), "utf8");

// ---------------------------------------------------------------------------
// 1. FloatingTabBar still imports LinearGradient from expo-linear-gradient.
// ---------------------------------------------------------------------------
const barSrc = read("components", "FloatingTabBar.tsx");
{
  assert.match(
    barSrc,
    /import\s*\{\s*LinearGradient\s*\}\s*from\s*"expo-linear-gradient"/,
    "FloatingTabBar.tsx must import LinearGradient from expo-linear-gradient",
  );
  ok("FloatingTabBar imports LinearGradient from expo-linear-gradient");
}

// ---------------------------------------------------------------------------
// 2. The active circle renders a LinearGradient wired to colors.brandGradient,
//    and does NOT fall back to a solid backgroundColor on the circle.
// ---------------------------------------------------------------------------
{
  assert.match(
    barSrc,
    /<LinearGradient/,
    "FloatingTabBar.tsx must render a <LinearGradient> for the active circle",
  );
  assert.match(
    barSrc,
    /<LinearGradient[\s\S]*?colors=\{colors\.brandGradient\}/,
    "the active circle's LinearGradient must use colors={colors.brandGradient}",
  );

  // The circle style block must not carry a solid backgroundColor — that would
  // mean the gradient was swapped for / masked by a flat fill.
  const circleStyle = barSrc.match(/circle:\s*\{[\s\S]*?\}/);
  assert.ok(circleStyle, "styles.circle block not found");
  assert.doesNotMatch(
    circleStyle[0],
    /backgroundColor/,
    "styles.circle must not set a solid backgroundColor (gradient only)",
  );
  ok("active circle paints colors.brandGradient via LinearGradient, no solid fill");
}

// ---------------------------------------------------------------------------
// 3. The circle keeps its animated scale-pop wiring (spring on circleScale).
// ---------------------------------------------------------------------------
{
  assert.match(
    barSrc,
    /circleScale\.value\s*=\s*withSpring/,
    "FloatingTabBar.tsx must animate circleScale with withSpring (scale pop)",
  );
  assert.match(
    barSrc,
    /transform:\s*\[\{\s*scale:\s*circleScale\.value\s*\}\]/,
    "the circle's animated style must apply scale: circleScale.value",
  );
  ok("active circle keeps its withSpring scale-pop animation wiring");
}

// ---------------------------------------------------------------------------
// 4. brandGradient declared as a multi-stop gradient in both palettes.
// ---------------------------------------------------------------------------
{
  const colorsSrc = read("constants", "colors.ts");
  const decls = colorsSrc.match(/brandGradient:\s*\[[^\]]+\]/g) ?? [];
  assert.ok(
    decls.length >= 2,
    `colors.ts must declare brandGradient in both dark & light palettes (found ${decls.length})`,
  );
  for (const d of decls) {
    assert.ok(
      d.includes(",") && /\[\s*brand\./.test(d),
      `brandGradient must be a multi-stop brand gradient, got: ${d}`,
    );
  }
  ok("colors.ts declares brandGradient (multi-stop) for both palettes");
}

console.log(`\n${passed} checks passed — tab bar gradient wiring intact.`);
