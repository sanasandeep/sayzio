// Source-driven regression tests for the CTA gradient button variant.
//
// The high-intent "cta" variant (electric blue → cyan gradient) is applied on
// key conversion screens. There is no visual snapshot coverage, so this test
// pins the wiring in source:
//   1. Button.tsx still defines the "cta" variant and renders it with the
//      dedicated colors.ctaGradient (not the generic primary gradient).
//   2. constants/colors.ts declares ctaGradient for BOTH dark & light palettes.
//   3. Each key screen still passes variant="cta" on its high-intent button,
//      asserted per-button (by label / surrounding context) so a swap back to
//      "primary" on any single one fails loudly.
//
// Follows the test-wizard-flow.mjs convention: no RN runner, just read the
// real sources and assert. Run via `node scripts/test-cta-variant.mjs`
// (package script `test:cta-variant`).

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
// 1. Button component: "cta" variant exists and uses the dedicated gradient.
// ---------------------------------------------------------------------------
{
  const src = read("components", "Button.tsx");
  assert.match(src, /"cta"/, 'Button.tsx no longer mentions the "cta" variant');
  assert.match(
    src,
    /variant\s*===\s*"cta"\s*\?\s*colors\.ctaGradient/,
    "Button.tsx must render variant=cta with colors.ctaGradient",
  );
  // cta must share the gradient (LinearGradient) rendering branch with primary.
  assert.match(
    src,
    /variant\s*===\s*"primary"\s*\|\|\s*variant\s*===\s*"cta"/,
    "cta must stay in the gradient rendering branch alongside primary",
  );
  assert.match(src, /LinearGradient/, "gradient buttons must use expo-linear-gradient");
  ok("Button.tsx keeps the cta variant on the ctaGradient LinearGradient branch");
}

// ---------------------------------------------------------------------------
// 2. Theme: ctaGradient declared in both palettes.
// ---------------------------------------------------------------------------
{
  const src = read("constants", "colors.ts");
  const decls = src.match(/ctaGradient:\s*\[[^\]]+\]/g) ?? [];
  assert.ok(
    decls.length >= 2,
    `colors.ts must declare ctaGradient in both dark & light palettes (found ${decls.length})`,
  );
  for (const d of decls) {
    assert.ok(
      d.includes(",") && /\[\s*brand\./.test(d),
      `ctaGradient must be a multi-stop brand gradient, got: ${d}`,
    );
  }
  ok("colors.ts declares ctaGradient (multi-stop) for both palettes");
}

// ---------------------------------------------------------------------------
// 3. Per-screen assertions: each high-intent button keeps variant="cta".
// Each entry pins ONE button via an anchor snippet that must appear within
// `window` chars of a variant="cta" occurrence, so removing the variant from
// any single button fails with a precise message.
// ---------------------------------------------------------------------------
function assertCtaNear(file, src, anchor, label) {
  const idx = src.indexOf(anchor);
  assert.ok(idx !== -1, `${file}: anchor not found: ${anchor}`);
  const windowSrc = src.slice(Math.max(0, idx - 400), idx + anchor.length + 400);
  assert.ok(
    windowSrc.includes('variant="cta"'),
    `${file}: button near "${anchor}" must use variant="cta" (regression: swapped to another variant?)`,
  );
  ok(`${file} — ${label} uses variant="cta"`);
}

const screens = [
  {
    file: "app/(auth)/verify.tsx",
    buttons: [['label="Verify and sign in"', "Verify and sign in"]],
  },
  {
    file: "app/setup.tsx",
    buttons: [
      [`label="Let's go"`, "welcome Let's go"],
      ['label="Send verification code"', "WhatsApp send code"],
      ['label="Verify & connect"', "WhatsApp verify & connect"],
      ['label="Save & continue"', "privacy save & continue"],
      ['createdLinkId != null ? "Start editing my page"', "done-step finish"],
    ],
  },
  {
    file: "app/onboarding.tsx",
    buttons: [
      ['hasPresets ? "Open the AI designer"', "final onboarding CTA"],
      ['index === total - 1 ? "Get started" : "Continue"', "slide Continue/Get started"],
    ],
  },
  {
    file: "app/plans.tsx",
    buttons: [
      ['label="Buy" variant="cta"', "purchase-modal Buy"],
      ['resume.isPending ? "Resuming…" : "Resume"', "resume-subscription"],
    ],
  },
  {
    file: "app/monetization/tip.tsx",
    buttons: [['label="Send tip"', "Send tip"]],
  },
  {
    file: "app/dm/tip.tsx",
    buttons: [['label="Send tip"', "DM Send tip"]],
  },
];

for (const { file, buttons } of screens) {
  const src = read(...file.split("/"));
  for (const [anchor, label] of buttons) {
    assertCtaNear(file, src, anchor, label);
  }
}

// ---------------------------------------------------------------------------
// 4. Safety net: no screen in the key set lost ALL its cta usages.
// ---------------------------------------------------------------------------
for (const { file } of screens) {
  const src = read(...file.split("/"));
  assert.ok(
    src.includes('variant="cta"'),
    `${file} must render at least one variant="cta" button`,
  );
}
ok("every key screen renders at least one cta button");

console.log(`\ntest-cta-variant: ${passed} checks passed`);
