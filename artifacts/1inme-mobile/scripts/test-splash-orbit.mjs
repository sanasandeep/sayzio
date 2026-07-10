// Regression test: the ZioSplash orbiting rings (RingRotor) and the pulsing
// halo behind the mascot (MascotHalo) must FREEZE when the OS reports
// reduce-motion — exactly like the mascot float already does
// (see test-splash-mascot.mjs).
//
// Both systems share the same accessibility guard: an effect that bails out
// with `if (reduced) return;` before seeding an infinite `withRepeat`
// animation, plus a style that only applies the animated transform when
// motion is allowed. If a refactor drops either guard, the splash would keep
// spinning / pulsing for reduce-motion users with nothing to catch it.
//
// This test guards, for each system:
//   1. With reduce-motion OFF, the effect starts the looping animation
//      (withRepeat drives the shared value) and the animated style applies.
//   2. With reduce-motion ON, the effect starts NO animation, the shared
//      value stays at its static resting value, and the animated style is
//      NOT applied.
//
// Following the source-driven convention (see test-splash-mascot.mjs and
// scripts/lib/extract.mjs), we lift the REAL effect bodies and the REAL
// style ternaries out of the shipped source and run them in a tiny harness
// with recording mocks for the reanimated helpers — not a paraphrase.
//
// Run via `node scripts/test-splash-orbit.mjs` (package script
// `test:splash-orbit`, wired into the `test:unit` gate).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

import { runExtractedCall } from "./lib/extract.mjs";

const TEST = "test-splash-orbit";
const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const splashSrc = readFileSync(
  join(root, "components", "ZioSplash.tsx"),
  "utf8",
);

// Recording reanimated helpers shared by both harnesses.
function makeReanimated() {
  const calls = { withTiming: 0, withRepeat: 0 };
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
  return { calls, withTiming, withRepeat, Easing };
}

// ─── RingRotor: the three orbiting rings of tool tiles ─────────────────────

const rotorBlock = splashSrc.match(/function RingRotor\(([\s\S]*?)\n\/\/ ─/);
assert.ok(rotorBlock, "could not find the RingRotor component in ZioSplash.tsx");
const rotorSrc = rotorBlock[0];

// The effect that drives the ring rotation. Lifted verbatim so the real
// `if (reduced) return;` guard and the real withRepeat/withTiming call run.
const rotorEffectMatch = rotorSrc.match(
  /useEffect\(\(\) => \{([\s\S]*?)\}, \[reduced, deg, duration, dir\]\);/,
);
assert.ok(
  rotorEffectMatch,
  "could not find the RingRotor useEffect (deps [reduced, deg, duration, dir]) in ZioSplash.tsx",
);
const rotorEffectBody = rotorEffectMatch[1];

// The rotor style ternary — the spinning transform must only apply when
// motion is allowed.
const rotorStyleMatch = rotorSrc.match(
  /style=\{\[styles\.rotor,\s*([^\]]+?)\]\}/,
);
assert.ok(
  rotorStyleMatch,
  "could not find the RingRotor rotor style array in ZioSplash.tsx",
);
const rotorStyleExpr = rotorStyleMatch[1].trim();

const ROTOR_STYLE = Symbol("rotorStyle");

function runRotor(reduced) {
  const { calls, withTiming, withRepeat, Easing } = makeReanimated();
  const deg = { value: 0 };

  const scope = {
    reduced,
    deg,
    duration: 22000,
    dir: 1,
    withTiming,
    withRepeat,
    Easing,
    rotorStyle: ROTOR_STYLE,
  };

  const effectFn = runExtractedCall(
    `() => {${rotorEffectBody}}`,
    scope,
    "RingRotor effect",
    { test: TEST },
  );
  effectFn();

  const style = runExtractedCall(rotorStyleExpr, scope, "RingRotor style", {
    test: TEST,
  });

  return { calls, deg, style };
}

// reduce-motion OFF: rotation starts and the spinning style is applied.
{
  const on = runRotor(false);
  assert.equal(
    on.calls.withRepeat,
    1,
    "with reduce-motion off, RingRotor must start the looping rotation via withRepeat()",
  );
  assert.equal(
    on.calls.withTiming,
    1,
    "with reduce-motion off, RingRotor must build the rotation step via withTiming()",
  );
  assert.equal(
    on.deg.value.kind,
    "repeat",
    "with reduce-motion off, the rotor shared value must be driven by withRepeat(withTiming(...))",
  );
  assert.equal(
    on.deg.value.count,
    -1,
    "the ring rotation must loop forever (withRepeat count -1)",
  );
  assert.equal(
    on.style,
    ROTOR_STYLE,
    "with reduce-motion off, the animated rotorStyle must be applied to the rotor",
  );
}

// reduce-motion ON: no rotation, static ring.
{
  const off = runRotor(true);
  assert.equal(
    off.calls.withRepeat,
    0,
    "with reduce-motion on, RingRotor must NOT start any rotation (withRepeat not called)",
  );
  assert.equal(
    off.calls.withTiming,
    0,
    "with reduce-motion on, RingRotor must NOT build any rotation step (withTiming not called)",
  );
  assert.equal(
    off.deg.value,
    0,
    "with reduce-motion on, the rotor shared value must stay at its static resting value",
  );
  assert.notEqual(
    off.style,
    ROTOR_STYLE,
    "with reduce-motion on, the animated rotorStyle must NOT be applied (rings stay static)",
  );
}

// ─── MascotHalo: the pulsing halo behind the mascot ────────────────────────

const haloBlock = splashSrc.match(/function MascotHalo\(([\s\S]*?)\n\/\/ ─/);
assert.ok(haloBlock, "could not find the MascotHalo component in ZioSplash.tsx");
const haloSrc = haloBlock[0];

const haloEffectMatch = haloSrc.match(
  /useEffect\(\(\) => \{([\s\S]*?)\}, \[reduced, scale\]\);/,
);
assert.ok(
  haloEffectMatch,
  "could not find the MascotHalo useEffect (deps [reduced, scale]) in ZioSplash.tsx",
);
const haloEffectBody = haloEffectMatch[1];

// The bloom style ternary — the pulsing bloom must fall back to a static
// opacity when motion is off, only applying the animated bloomStyle otherwise.
const bloomStyleMatch = haloSrc.match(
  /style=\{\[styles\.bloom,\s*([^\]]+?)\]\}/,
);
assert.ok(
  bloomStyleMatch,
  "could not find the MascotHalo bloom style array in ZioSplash.tsx",
);
const bloomStyleExpr = bloomStyleMatch[1].trim();

const BLOOM_STYLE = Symbol("bloomStyle");

function runHalo(reduced) {
  const { calls, withTiming, withRepeat, Easing } = makeReanimated();
  // The halo/bloom share a single scale shared value that rests at 1.
  const scale = { value: 1 };

  const scope = {
    reduced,
    scale,
    withTiming,
    withRepeat,
    Easing,
    bloomStyle: BLOOM_STYLE,
  };

  const effectFn = runExtractedCall(
    `() => {${haloEffectBody}}`,
    scope,
    "MascotHalo effect",
    { test: TEST },
  );
  effectFn();

  const style = runExtractedCall(bloomStyleExpr, scope, "MascotHalo style", {
    test: TEST,
  });

  return { calls, scale, style };
}

// reduce-motion OFF: pulse starts and the animated bloom style is applied.
{
  const on = runHalo(false);
  assert.equal(
    on.calls.withRepeat,
    1,
    "with reduce-motion off, MascotHalo must start the looping pulse via withRepeat()",
  );
  assert.equal(
    on.calls.withTiming,
    1,
    "with reduce-motion off, MascotHalo must build the pulse step via withTiming()",
  );
  assert.equal(
    on.scale.value.kind,
    "repeat",
    "with reduce-motion off, the halo scale must be driven by withRepeat(withTiming(...))",
  );
  assert.equal(
    on.scale.value.count,
    -1,
    "the halo pulse must loop forever (withRepeat count -1)",
  );
  assert.equal(
    on.scale.value.reverse,
    true,
    "the halo pulse must reverse each cycle (withRepeat reverse true)",
  );
  assert.equal(
    on.style,
    BLOOM_STYLE,
    "with reduce-motion off, the animated bloomStyle must be applied to the bloom",
  );
}

// reduce-motion ON: no pulse, static halo.
{
  const off = runHalo(true);
  assert.equal(
    off.calls.withRepeat,
    0,
    "with reduce-motion on, MascotHalo must NOT start any pulse (withRepeat not called)",
  );
  assert.equal(
    off.calls.withTiming,
    0,
    "with reduce-motion on, MascotHalo must NOT build any pulse step (withTiming not called)",
  );
  assert.equal(
    off.scale.value,
    1,
    "with reduce-motion on, the halo scale must stay at its static resting value (1)",
  );
  assert.notEqual(
    off.style,
    BLOOM_STYLE,
    "with reduce-motion on, the animated bloomStyle must NOT be applied (halo stays static)",
  );
}

// ─── MascotHalo ring: the halo's OWN style branch ──────────────────────────
// The bloom above gates its animated style behind a `reduced ?` ternary, but
// the halo RING applies haloStyle UNCONDITIONALLY
// (style={[styles.halo, haloStyle]}) and relies SOLELY on the shared `scale`
// staying frozen at its resting value under reduce-motion to avoid pulsing.
// That coupling is invisible to the bloom assertions above, so this section
// guards it directly: (1) the halo ring style branch really applies haloStyle,
// (2) the halo transform is a PURE function of the shared `scale` value (no
// independent animation source), and (3) with reduce-motion on the frozen
// scale yields a static (scale 1) transform. If a later refactor gave the halo
// its own shared value, (2) would break — catching a halo that keeps pulsing
// for reduce-motion users even while the shared `scale` is frozen.

// The haloStyle worklet — lifted verbatim so the REAL transform/opacity math
// runs against a controlled `scale` value.
const haloStyleMatch = haloSrc.match(
  /const haloStyle = useAnimatedStyle\(\(\) => \(([\s\S]*?)\)\);/,
);
assert.ok(
  haloStyleMatch,
  "could not find the MascotHalo haloStyle useAnimatedStyle worklet in ZioSplash.tsx",
);
const haloStyleExpr = haloStyleMatch[1].trim();

// The halo ring style array — note there is NO reduce-motion ternary here, so
// its stillness depends entirely on the shared `scale` being frozen.
const haloRingStyleMatch = haloSrc.match(
  /style=\{\[styles\.halo,\s*([^\]]+?)\]\}/,
);
assert.ok(
  haloRingStyleMatch,
  "could not find the MascotHalo halo ring style array in ZioSplash.tsx",
);
const haloRingStyleExpr = haloRingStyleMatch[1].trim();

const HALO_STYLE = Symbol("haloStyle");

// (1) The halo ring branch must apply the animated haloStyle unconditionally.
{
  const applied = runExtractedCall(
    haloRingStyleExpr,
    { reduced: true, haloStyle: HALO_STYLE },
    "MascotHalo halo ring style",
    { test: TEST },
  );
  assert.equal(
    applied,
    HALO_STYLE,
    "the halo ring must apply haloStyle; with no reduce-motion ternary here, its stillness depends entirely on the shared scale staying frozen",
  );
}

// (2) The halo transform must be a PURE function of the shared `scale` value:
// inject a sentinel scale and confirm the worklet's transform scale tracks it
// exactly. If the halo grew its own shared value, this would no longer hold.
{
  const scale = { value: 3.5 };
  const styleOut = runExtractedCall(
    haloStyleExpr,
    { scale },
    "MascotHalo haloStyle worklet",
    { test: TEST },
  );
  assert.equal(
    styleOut.transform[0].scale,
    3.5,
    "the halo transform scale must derive solely from the shared scale value (no independent animation source)",
  );
}

// (3) reduce-motion ON: after the effect runs, the shared scale stays frozen
// at 1, so the halo's own style branch yields a static (scale 1) transform —
// the halo ring cannot pulse independently of the frozen shared value.
{
  const { calls, withTiming, withRepeat, Easing } = makeReanimated();
  const scale = { value: 1 };
  const scope = { reduced: true, scale, withTiming, withRepeat, Easing };

  const effectFn = runExtractedCall(
    `() => {${haloEffectBody}}`,
    scope,
    "MascotHalo effect (halo ring)",
    { test: TEST },
  );
  effectFn();

  assert.equal(
    calls.withRepeat,
    0,
    "with reduce-motion on, no pulse animation may be seeded for the halo ring",
  );
  assert.equal(
    scale.value,
    1,
    "with reduce-motion on, the shared scale must stay frozen at its resting value (1)",
  );

  const styleOut = runExtractedCall(
    haloStyleExpr,
    { scale },
    "MascotHalo haloStyle worklet (reduce-motion)",
    { test: TEST },
  );
  assert.equal(
    styleOut.transform[0].scale,
    1,
    "with reduce-motion on, the halo ring transform must be static (scale 1), never animated",
  );
}

console.log("test-splash-orbit: all assertions passed");
