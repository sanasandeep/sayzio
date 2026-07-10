// Regression test: the ZioSplash mascot swap must keep working.
//
// The mascot no longer branches on platform between an animated WebP and a
// still PNG. It now renders ONE static PNG (assets/images/zio-mascot-new.png)
// wrapped in MascotFloat, a code-driven gentle vertical float. The float is
// disabled when the OS reports reduce-motion. See components/ZioSplash.tsx.
//
// This test guards three things that manual review would otherwise be the
// only defence for:
//   1. MASCOT_SRC points at the new asset (zio-mascot-new.png) and the file
//      actually exists on disk.
//   2. With reduce-motion OFF, MascotFloat starts the looping float
//      (withRepeat drives the shared value) and applies the animated style.
//   3. With reduce-motion ON, MascotFloat does NOT start any animation and
//      applies no animated style (the mascot stays static).
//
// Following the source-driven convention (see test-splash-once.mjs and
// scripts/lib/extract.mjs), we lift the REAL MascotFloat effect body and the
// REAL wrapper-style ternary out of the shipped source and run them in a tiny
// harness with recording mocks for the reanimated helpers. A refactor that
// drops the `if (reduced) return;` guard, stops seeding the float, or removes
// the reduced-aware style would fail here instead of shipping silently.
//
// Run via `node scripts/test-splash-mascot.mjs` (package script
// `test:splash-mascot`, wired into the `test:unit` gate).

import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

import { runExtractedCall } from "./lib/extract.mjs";

const TEST = "test-splash-mascot";
const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const splashSrc = readFileSync(
  join(root, "components", "ZioSplash.tsx"),
  "utf8",
);

// --- 1. The new asset is wired up and present on disk ---------------------

const mascotSrcLine = splashSrc.match(
  /const MASCOT_SRC\s*=\s*require\(\s*["']([^"']+)["']\s*\);/,
);
assert.ok(
  mascotSrcLine,
  "ZioSplash.tsx must define MASCOT_SRC via require() for the splash mascot",
);
assert.match(
  mascotSrcLine[1],
  /zio-mascot-new\.png$/,
  `MASCOT_SRC must point at the new mascot asset, got: ${mascotSrcLine[1]}`,
);

const assetRel = mascotSrcLine[1].replace(/^@\//, "");
const assetPath = join(root, assetRel);
assert.ok(
  existsSync(assetPath),
  `the mascot asset referenced by MASCOT_SRC must exist on disk: ${assetRel}`,
);

// The mascot must actually be rendered inside the MascotFloat wrapper, using
// MASCOT_SRC — not a leftover platform-branched source.
assert.match(
  splashSrc,
  /<MascotFloat[\s\S]*?<Image[\s\S]*?source=\{MASCOT_SRC\}[\s\S]*?<\/MascotFloat>/,
  "the splash must render <Image source={MASCOT_SRC}> inside <MascotFloat>",
);

// --- 2. Lift the real MascotFloat float logic -----------------------------

const floatBlock = splashSrc.match(
  /function MascotFloat\(([\s\S]*?)\n\/\/ ─/,
);
assert.ok(
  floatBlock,
  "could not find the MascotFloat component in ZioSplash.tsx",
);
const floatSrc = floatBlock[0];

// The effect that drives the float. We lift its body verbatim so the real
// `if (reduced) return;` guard and the real withRepeat/withTiming call are
// exercised — not a paraphrase.
const effectMatch = floatSrc.match(
  /useEffect\(\(\) => \{([\s\S]*?)\}, \[reduced, ty\]\);/,
);
assert.ok(
  effectMatch,
  "could not find the MascotFloat useEffect (deps [reduced, ty]) in ZioSplash.tsx",
);
const effectBody = effectMatch[1];

// The wrapper style ternary — the animated style must only apply when motion
// is allowed.
const styleMatch = floatSrc.match(/style=\{\[styles\.mascotWrap,\s*([^\]]+?)\]\}/);
assert.ok(
  styleMatch,
  "could not find the MascotFloat wrapper style array in ZioSplash.tsx",
);
const styleExpr = styleMatch[1].trim();

// --- 3. Run the lifted code with recording reanimated mocks ---------------

const FLOAT_STYLE = Symbol("floatStyle");

function runFloat(reduced) {
  const calls = { withTiming: 0, withRepeat: 0 };
  const ty = { value: 0 };

  const withTiming = (target, opts) => {
    calls.withTiming += 1;
    return { kind: "timing", target, opts };
  };
  const withRepeat = (anim, count, reverse) => {
    calls.withRepeat += 1;
    return { kind: "repeat", anim, count, reverse };
  };
  const Easing = {
    inOut: (fn) => fn,
    sin: (t) => t,
    ease: (t) => t,
    linear: (t) => t,
  };

  const scope = {
    reduced,
    ty,
    withTiming,
    withRepeat,
    Easing,
    floatStyle: FLOAT_STYLE,
  };

  // Run the effect (wrapped as a function so its `return;` only exits the
  // effect, letting us inspect state afterwards).
  const effectFn = runExtractedCall(`() => {${effectBody}}`, scope, "MascotFloat effect", {
    test: TEST,
  });
  effectFn();

  // Evaluate the wrapper style ternary in the same scope.
  const style = runExtractedCall(styleExpr, scope, "MascotFloat style", {
    test: TEST,
  });

  return { calls, ty, style };
}

// reduce-motion OFF: float starts and the animated style is applied.
const on = runFloat(false);
assert.equal(
  on.calls.withRepeat,
  1,
  "with reduce-motion off, MascotFloat must start the looping float via withRepeat()",
);
assert.equal(
  on.calls.withTiming,
  1,
  "with reduce-motion off, MascotFloat must build the float step via withTiming()",
);
assert.equal(
  on.ty.value.kind,
  "repeat",
  "with reduce-motion off, the float shared value must be driven by withRepeat(withTiming(...))",
);
assert.equal(
  on.ty.value.count,
  -1,
  "the float must loop forever (withRepeat count -1)",
);
assert.equal(
  on.style,
  FLOAT_STYLE,
  "with reduce-motion off, the animated floatStyle must be applied to the mascot wrapper",
);

// reduce-motion ON: no animation, static mascot.
const off = runFloat(true);
assert.equal(
  off.calls.withRepeat,
  0,
  "with reduce-motion on, MascotFloat must NOT start any animation (withRepeat not called)",
);
assert.equal(
  off.calls.withTiming,
  0,
  "with reduce-motion on, MascotFloat must NOT build any float step (withTiming not called)",
);
assert.equal(
  off.ty.value,
  0,
  "with reduce-motion on, the float shared value must stay at its static resting value",
);
assert.notEqual(
  off.style,
  FLOAT_STYLE,
  "with reduce-motion on, the animated floatStyle must NOT be applied (mascot stays static)",
);

console.log("test-splash-mascot: all assertions passed");
