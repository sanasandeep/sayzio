// Regression test: the first-run onboarding carousel (app/onboarding.tsx) has
// its OWN start-hidden entrance animations that do NOT go through the shared
// ScrollReveal primitive (covered by test-info-scroll-reveal.mjs). Because this
// is the VERY FIRST screen a brand-new user sees, a slide stuck at opacity 0
// would be a maximally-visible failure — a blank onboarding slide.
//
// Two families are guarded here, both lifted from the REAL shipped source so a
// future refactor that breaks the reveal fails loudly instead of silently
// shipping an invisible slide:
//
//   1. SlideCard — the glass card holding the category chip, title, and body.
//      It initialises cardOpacity = useSharedValue(active ? 1 : 0) and reveals
//      via a useEffect gated on the `active` prop. The active slide MUST always
//      end at opacity 1; an inactive slide MUST reset to its ready-to-animate
//      state (opacity 0, translateY 28) so re-entering it feels fresh — and,
//      critically, so it becomes visible again the moment it turns active.
//
//   2. FloatingIcon — the bobbing feature-icon tiles. Each initialises
//      opacity = useSharedValue(0) and fades in via an UNCONDITIONAL mount
//      effect (the fade runs BEFORE the `if (reduced) return;` bail-out that
//      only skips the float loop). So the icon must reach opacity 1 regardless
//      of the OS Reduce Motion setting — reduce-motion freezes the bob, never
//      the reveal.
//
// Following the source-driven convention (see test-splash-orbit.mjs,
// test-info-scroll-reveal.mjs, and scripts/lib/extract.mjs) we lift the REAL
// initialisers + effect bodies and run them in a tiny harness with recording
// mocks for the reanimated helpers — not a paraphrase.
//
// Run via `node scripts/test-onboarding-slide-reveal.mjs` (package script
// `test:onboarding-slide-reveal`, wired into the `test:unit` gate).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

import { runExtractedCall } from "./lib/extract.mjs";

const TEST = "test-onboarding-slide-reveal";
const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const src = readFileSync(join(root, "app", "onboarding.tsx"), "utf8");

// ---------------------------------------------------------------------------
// Recording reanimated helpers. withTiming/withSpring tag their target so we
// can assert which value a shared value was animated TOWARD; withDelay passes
// its inner animation through; withRepeat/withSequence are captured so the
// float-loop path is inert. A plain number (e.g. the reset 0 / 28) passes
// through unchanged.
// ---------------------------------------------------------------------------
function makeReanimated() {
  const calls = { withTiming: 0, withSpring: 0, withDelay: 0, withRepeat: 0 };
  return {
    calls,
    withTiming: (target, opts) => {
      calls.withTiming += 1;
      return { kind: "timing", target, opts };
    },
    withSpring: (target, opts) => {
      calls.withSpring += 1;
      return { kind: "spring", target, opts };
    },
    withDelay: (delay, anim) => {
      calls.withDelay += 1;
      return { kind: "delay", delay, inner: anim };
    },
    withRepeat: (anim, count, reverse) => {
      calls.withRepeat += 1;
      return { kind: "repeat", anim, count, reverse };
    },
    withSequence: (...anims) => ({ kind: "sequence", anims }),
    Easing: {
      inOut: (fn) => fn,
      out: (fn) => fn,
      sin: (t) => t,
      quad: (t) => t,
      ease: (t) => t,
      linear: (t) => t,
    },
  };
}

// The value a shared value settled on — unwrap withDelay(withX(target)) /
// withX(target). A plain number passes through.
function settledTarget(v) {
  if (v && v.kind === "delay") return v.inner ? settledTarget(v.inner) : undefined;
  if (v && (v.kind === "timing" || v.kind === "spring")) return v.target;
  return v;
}

function lift(re, label) {
  const m = src.match(re);
  assert.ok(m, `could not find the ${label} in app/onboarding.tsx`);
  return m[1];
}

// ---------------------------------------------------------------------------
// SlideCard — the glass content card, revealed when its slide becomes active.
// ---------------------------------------------------------------------------

// Isolate the SlideCard component block so the effect regex can't accidentally
// capture a same-shaped effect from a sibling component.
const slideCardBlock = src.match(/function SlideCard\(([\s\S]*?)\n\}\n/);
assert.ok(slideCardBlock, "could not find the SlideCard component in app/onboarding.tsx");
const slideCardSrc = slideCardBlock[0];

const slideCardLift = (re, label) => {
  const m = slideCardSrc.match(re);
  assert.ok(m, `could not find the ${label} in SlideCard`);
  return m[1];
};

// The shared-value initialisers — the resting values the card starts at, keyed
// off the `active` prop.
const cardOpacityInitExpr = slideCardLift(
  /const cardOpacity = useSharedValue\(([\s\S]*?)\);/,
  "cardOpacity useSharedValue initialiser",
)
  .trim()
  .replace(/,$/, "");
const cardYInitExpr = slideCardLift(
  /const cardY = useSharedValue\(([\s\S]*?)\);/,
  "cardY useSharedValue initialiser",
)
  .trim()
  .replace(/,$/, "");

// The reveal effect, gated on `active`.
const slideCardEffectBody = slideCardLift(
  /useEffect\(\(\) => \{([\s\S]*?)\}, \[active\]\);/,
  "SlideCard reveal useEffect body (deps [active])",
);

function runSlideCard(active) {
  const rea = makeReanimated();

  const cardOpacity = {
    value: runExtractedCall(
      cardOpacityInitExpr,
      { active },
      "cardOpacity initialiser",
      { test: TEST },
    ),
  };
  const cardY = {
    value: runExtractedCall(cardYInitExpr, { active }, "cardY initialiser", {
      test: TEST,
    }),
  };

  const scope = { ...rea, active, cardOpacity, cardY };
  const effect = runExtractedCall(
    `() => {${slideCardEffectBody}}`,
    scope,
    "SlideCard reveal effect",
    { test: TEST },
  );
  effect();

  return { cardOpacity, cardY, calls: rea.calls };
}

// Active slide: starts visible AND is revealed to opacity 1 by the effect.
{
  const h = runSlideCard(true);
  assert.equal(
    settledTarget(h.cardOpacity.value),
    1,
    "the active onboarding slide's card must ALWAYS end at opacity 1 — the first " +
      "screen a new user sees must never be blank",
  );
  assert.equal(
    settledTarget(h.cardY.value),
    0,
    "the active slide's card must animate to its resting translateY (0)",
  );
}

// Active slide, initial resting value BEFORE the effect: already visible so
// there is no flash of blank card even if the effect were somehow skipped.
{
  const initialOpacity = runExtractedCall(
    cardOpacityInitExpr,
    { active: true },
    "cardOpacity initialiser (active resting value)",
    { test: TEST },
  );
  assert.equal(
    initialOpacity,
    1,
    "the active slide's card must INITIALISE to opacity 1 (visible on first " +
      "paint), so it can never flash blank before the reveal effect runs",
  );
}

// Inactive slide: resets to the ready-to-animate state (hidden, offset down),
// so that when it later becomes active the effect above brings it back to 1.
{
  const h = runSlideCard(false);
  assert.equal(
    settledTarget(h.cardOpacity.value),
    0,
    "an inactive slide's card must reset to opacity 0 (ready to animate in) — " +
      "and the active-path assertion proves it returns to 1 once activated",
  );
  assert.equal(
    settledTarget(h.cardY.value),
    28,
    "an inactive slide's card must reset to its ready-to-animate translateY (28)",
  );
}

// ---------------------------------------------------------------------------
// FloatingIcon — the bobbing feature-icon tiles. The fade-in is UNCONDITIONAL:
// it runs before the `if (reduced) return;` that only skips the float loop, so
// the icon must become visible in BOTH motion states.
// ---------------------------------------------------------------------------

const floatingBlock = src.match(/function FloatingIcon\(([\s\S]*?)\n\}\n/);
assert.ok(floatingBlock, "could not find the FloatingIcon component in app/onboarding.tsx");
const floatingSrc = floatingBlock[0];

const floatingLift = (re, label) => {
  const m = floatingSrc.match(re);
  assert.ok(m, `could not find the ${label} in FloatingIcon`);
  return m[1];
};

const iconOpacityInitExpr = floatingLift(
  /const opacity = useSharedValue\(([\s\S]*?)\);/,
  "FloatingIcon opacity useSharedValue initialiser",
)
  .trim()
  .replace(/,$/, "");

const floatingEffectBody = floatingLift(
  /useEffect\(\(\) => \{([\s\S]*?)\}, \[reduced\]\);/,
  "FloatingIcon mount useEffect body (deps [reduced])",
);

// The fade-in must not be gated behind the reduce-motion bail-out: assert the
// opacity assignment appears BEFORE any `if (reduced) return;` in the body, so
// a future edit that moves it below the guard (which would leave reduce-motion
// users staring at invisible icons) fails here.
{
  const opacityIdx = floatingEffectBody.indexOf("opacity.value");
  const reducedGuardIdx = floatingEffectBody.search(/if\s*\(\s*reduced\s*\)\s*return/);
  assert.ok(
    opacityIdx >= 0,
    "the FloatingIcon effect must assign opacity.value (the fade-in reveal)",
  );
  assert.ok(
    reducedGuardIdx >= 0,
    "the FloatingIcon effect is expected to have an `if (reduced) return;` guard " +
      "for the float loop",
  );
  assert.ok(
    opacityIdx < reducedGuardIdx,
    "the FloatingIcon fade-in must run BEFORE the `if (reduced) return;` guard — " +
      "otherwise reduce-motion users would be left with invisible icons",
  );
}

function runFloatingIcon(reduced) {
  const rea = makeReanimated();
  const opacity = {
    value: runExtractedCall(
      iconOpacityInitExpr,
      {},
      "FloatingIcon opacity initialiser",
      { test: TEST },
    ),
  };
  const ty = { value: 0 };

  // `def`, `startDelay`, etc. are free vars in the effect; the extract proxy
  // defaults unknowns to null, but supply the numeric ones the fade-in reads.
  const scope = {
    ...rea,
    reduced,
    opacity,
    ty,
    startDelay: 0,
    def: { floatAmplitude: 10, floatDuration: 1000 },
  };
  const effect = runExtractedCall(
    `() => {${floatingEffectBody}}`,
    scope,
    "FloatingIcon mount effect",
    { test: TEST },
  );
  effect();

  return { opacity, ty, calls: rea.calls };
}

// The icon opacity starts hidden (0) — proving the reveal is what makes it
// visible, and that a broken reveal would leave it blank.
{
  const initial = runExtractedCall(
    iconOpacityInitExpr,
    {},
    "FloatingIcon opacity initialiser (resting value)",
    { test: TEST },
  );
  assert.equal(
    initial,
    0,
    "FloatingIcon opacity must initialise to 0 (hidden) — the mount effect is " +
      "the sole thing that reveals it, so the reveal must be unconditional",
  );
}

// Reveal must reach opacity 1 in BOTH motion states.
for (const reduced of [false, true]) {
  const h = runFloatingIcon(reduced);
  assert.equal(
    settledTarget(h.opacity.value),
    1,
    `FloatingIcon must fade to opacity 1 on mount even when reduced=${reduced} — ` +
      "reduce-motion freezes the bob, never the reveal",
  );
}

// With reduce-motion ON, the float loop must NOT be seeded (only the fade-in
// runs); with motion OFF it should. This anchors the "before the guard" claim
// above to observable behaviour, not just textual ordering.
{
  const on = runFloatingIcon(false);
  assert.ok(
    on.calls.withRepeat >= 1,
    "with reduce-motion off, FloatingIcon must seed its bobbing float loop (withRepeat)",
  );
  const off = runFloatingIcon(true);
  assert.equal(
    off.calls.withRepeat,
    0,
    "with reduce-motion on, FloatingIcon must NOT seed any float loop — but the " +
      "fade-in (asserted above) must still have revealed it",
  );
}

console.log("test-onboarding-slide-reveal: all assertions passed");
