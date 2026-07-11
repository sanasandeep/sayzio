// Regression test: the shared ScrollReveal primitive (used by every /info/*
// screen via InfoPage.tsx — help, terms, privacy, nfc — and by AboutPage.tsx)
// must NEVER leave a section stuck permanently invisible at opacity 0.
//
// After the entrance-animation change, each ScrollReveal section starts at
// opacity 0 and only animates to visible once its view measures near/inside
// the viewport (or the OS Reduce Motion setting forces an instant reveal). The
// silent-content-loss risk: if the reveal trigger never fires — measurement
// timing, web-vs-native quirks, a null/immeasurable ref, or off-screen content
// the user never scrolls into view — the section could stay invisible forever.
// Plain typechecking can't catch that.
//
// Following the source-driven convention (see test-splash-orbit.mjs and
// scripts/lib/extract.mjs) we lift the REAL applyReveal / reveal / effect
// bodies out of the shipped ScrollReveal.tsx and run them in a tiny harness
// with recording mocks for the reanimated helpers, setTimeout, and the view
// ref's measure() — not a paraphrase. Each ScrollReveal section renders the
// SAME InfoPage/AboutPage text content, so proving the primitive always ends
// at opacity 1 proves every /info/* screen's copy becomes visible.
//
// Guards, end to end:
//   1. Reduce Motion ON  → opacity/translate initialise to their visible
//      resting values (1 / 0) and the section reveals immediately — no timers,
//      no animation needed.
//   2. Reduce Motion OFF, view in viewport → the scroll/measure trigger reveals
//      the section, animating opacity to 1.
//   3. Reduce Motion OFF, measure() silently drops its callback → the failsafe
//      timer still reveals the section (opacity animates to 1). THIS is the
//      assertion that fails if the safety net is removed and a section is left
//      stuck at opacity 0.
//   4. Reduce Motion OFF, off-screen content never scrolled into view → same
//      failsafe backstop reveals it.
//   5. Reduce Motion OFF, immeasurable ref (null / no measure fn) → reveal
//      immediately rather than risk a stuck section.
//
// Run via `node scripts/test-info-scroll-reveal.mjs` (package script
// `test:info-scroll-reveal`, wired into the `test:unit` gate).

import assert from "node:assert/strict";
import { readFileSync, readdirSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join, relative, sep } from "node:path";

import { runExtractedCall } from "./lib/extract.mjs";

const TEST = "test-info-scroll-reveal";
const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const srcPath = join(root, "components", "ScrollReveal.tsx");
const src = readFileSync(srcPath, "utf8");

// ---------------------------------------------------------------------------
// Consumer enumeration — EVERY screen that renders content through ScrollReveal
// inherits the primitive's "never stuck invisible" guarantee (scenarios 3–5
// below). This audit scans the whole mobile app for ScrollReveal consumers and
// pins them against a known-covered list. When a NEW screen starts using
// ScrollReveal (a newly-added hidden-then-revealed surface), this test fails
// loudly so the author confirms the failsafe covers it and adds it here —
// rather than the coverage claim silently going stale. Each consumer is also
// checked to actually WRAP content in <ScrollReveal>, so a consumer that merely
// imports the symbol without routing content through it can't slip by.
// ---------------------------------------------------------------------------

// Screens/components proven to route their content through ScrollReveal, and so
// covered by the primitive's reveal guarantee. Paths are posix-relative to the
// mobile app root.
const KNOWN_CONSUMERS = new Set([
  "components/InfoPage.tsx", // help / terms / privacy / nfc — all /info/* screens
  "components/AboutPage.tsx", // the About screen
  "app/info/contact.tsx", // the Contact screen
]);

// The primitive's own module isn't a "consumer".
const PRIMITIVE_REL = "components/ScrollReveal.tsx";

function walkTsx(dir) {
  const out = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    if (entry.name === "node_modules" || entry.name.startsWith(".")) continue;
    const full = join(dir, entry.name);
    if (entry.isDirectory()) out.push(...walkTsx(full));
    else if (entry.name.endsWith(".tsx")) out.push(full);
  }
  return out;
}

const toPosix = (p) => relative(root, p).split(sep).join("/");

// A file "uses" ScrollReveal only if it renders the <ScrollReveal> element (or
// its provider) — a bare mention in a comment/doc string doesn't count.
const usesScrollReveal = (text) =>
  /<ScrollReveal[\s/>]/.test(text) || /ScrollRevealCtx\.Provider/.test(text);

const discovered = [];
for (const abs of [...walkTsx(join(root, "components")), ...walkTsx(join(root, "app"))]) {
  const rel = toPosix(abs);
  if (rel === PRIMITIVE_REL) continue;
  if (usesScrollReveal(readFileSync(abs, "utf8"))) discovered.push(rel);
}

// No NEW, unlisted consumer — a newly-added hidden-then-revealed surface must be
// consciously acknowledged as covered by the failsafe.
const newConsumers = discovered.filter((rel) => !KNOWN_CONSUMERS.has(rel));
assert.deepEqual(
  newConsumers,
  [],
  "new ScrollReveal consumer(s) found that aren't in KNOWN_CONSUMERS: " +
    `[${newConsumers.join(", ")}]. Any new screen that starts a section hidden ` +
    "and reveals it via ScrollReveal inherits the failsafe below — confirm that, " +
    "then add it to KNOWN_CONSUMERS in test-info-scroll-reveal.mjs so its coverage " +
    "is tracked.",
);

// Every listed consumer must still exist AND actually wrap content in
// ScrollReveal (guarding against a consumer that stops routing content through
// the safe primitive, which would silently void its reveal guarantee).
for (const rel of KNOWN_CONSUMERS) {
  assert.ok(
    discovered.includes(rel),
    `KNOWN_CONSUMERS lists ${rel} but it no longer renders content through ` +
      "<ScrollReveal> — either restore the ScrollReveal wrapper or remove it " +
      "from KNOWN_CONSUMERS (and confirm its content still can't stay invisible).",
  );
}

// Sanity: InfoPage renders its title/body TEXT through ScrollReveal, anchoring
// the coverage claim that the primitive's reveal guarantee keeps each /info/*
// screen's copy visible.
const infoSrc = readFileSync(join(root, "components", "InfoPage.tsx"), "utf8");
assert.ok(
  /<ScrollReveal[\s>]/.test(infoSrc) &&
    /styles\.body/.test(infoSrc) &&
    /styles\.title/.test(infoSrc),
  "InfoPage must render its title/body text inside <ScrollReveal> — the reveal " +
    "guarantee is what keeps help/terms/privacy/nfc copy visible",
);

// ---------------------------------------------------------------------------
// Lift the REAL bodies out of ScrollReveal.tsx.
// ---------------------------------------------------------------------------

function lift(re, label) {
  const m = src.match(re);
  assert.ok(m, `could not find the ${label} in ScrollReveal.tsx`);
  return m[1];
}

// The shared-value initialisers — the resting values a section starts at.
const opacityInitExpr = lift(
  /const opacity = useSharedValue\(([\s\S]*?)\);/,
  "opacity useSharedValue initialiser",
)
  .trim()
  .replace(/,$/, "");
const translateYInitExpr = lift(
  /const translateY = useSharedValue\(\s*([\s\S]*?)\s*\);/,
  "translateY useSharedValue initialiser",
)
  .trim()
  .replace(/,$/, "");

// applyReveal — the unconditional reveal (idempotent via `triggered`).
const applyRevealBody = lift(
  /const applyReveal = useCallback\(\(\) => \{([\s\S]*?)\}, \[delay, reduceMotion, opacity, translateY, translateX\]\);/,
  "applyReveal useCallback body",
);

// reveal — the measure-gated trigger with its immeasurable-ref fallback.
const revealBody = lift(
  /const reveal = useCallback\(\(\) => \{([\s\S]*?)\}, \[windowHeight, applyReveal\]\);/,
  "reveal useCallback body",
);

// The reveal effect — schedules the 80ms attempt AND the failsafe, subscribes.
const effectBody = lift(
  /useEffect\(\(\) => \{([\s\S]*?)\}, \[reveal, applyReveal, ctx, reduceMotion\]\);/,
  "reveal useEffect body",
);

// ---------------------------------------------------------------------------
// Harness helpers.
// ---------------------------------------------------------------------------

// Recording reanimated helpers. withTiming/withSpring tag their target so we
// can assert what value a shared value was animated TOWARD; withDelay passes
// its inner animation through so `sharedValue.value` ends up as that tag.
function makeReanimated() {
  return {
    withTiming: (target, opts) => ({ kind: "timing", target, opts }),
    withSpring: (target, opts) => ({ kind: "spring", target, opts }),
    withDelay: (delay, anim) => ({ kind: "delay", delay, inner: anim }),
  };
}

// The value a shared value settled on — unwrap the withDelay(withX(target))
// tag produced above. A plain number (e.g. the initial 0) passes through.
function settledTarget(v) {
  if (v && v.kind === "delay") return v.inner ? v.inner.target : undefined;
  if (v && (v.kind === "timing" || v.kind === "spring")) return v.target;
  return v;
}

// Evaluate the initial resting value expression for a given reduceMotion.
function initialValue(expr, reduceMotion, direction = "up") {
  return runExtractedCall(
    expr,
    { reduceMotion, direction },
    "shared-value initialiser",
    { test: TEST },
  );
}

// Build a fully wired ScrollReveal harness for one scenario.
//   measure: "in" | "out" | "dropped" | "none-fn" | "null-ref"
function makeHarness({ reduceMotion, direction = "up", measure = "in" }) {
  const rea = makeReanimated();
  const delay = 80;
  const windowHeight = 800;

  const triggered = { current: false };
  const setRevealedCalls = [];
  const setRevealed = (v) => setRevealedCalls.push(v);

  const opacity = { value: initialValue(opacityInitExpr, reduceMotion) };
  const translateY = {
    value: initialValue(translateYInitExpr, reduceMotion, direction),
  };
  const translateX = { value: 0 };

  // The view ref, whose measure() behaviour drives each scenario.
  let ref;
  if (measure === "null-ref") {
    ref = { current: null };
  } else if (measure === "none-fn") {
    ref = { current: {} }; // no measure function at all
  } else {
    const pageY =
      measure === "in"
        ? windowHeight * 0.5 // inside the viewport → should reveal on measure
        : windowHeight * 3; // far below the fold (only reachable via failsafe)
    ref = {
      current: {
        measure: (cb) => {
          if (measure === "dropped") return; // silently never invokes cb
          cb(0, 0, 0, 0, 0, pageY);
        },
      },
    };
  }

  // Recording setTimeout — capture (fn, ms) so the test can fire the 80ms
  // trigger and the 2200ms failsafe deterministically.
  const timers = [];
  const setTimeout = (fn, ms) => {
    timers.push({ fn, ms });
    return timers.length; // fake id
  };
  const clearTimeout = () => {};

  // A no-op scroll registry (ctx) whose subscribe returns an unsubscribe.
  const subscribeCalls = [];
  const ctx = {
    subscribe: (l) => {
      subscribeCalls.push(l);
      return () => {};
    },
    getY: () => 0,
  };

  const scope = {
    ...rea,
    delay,
    windowHeight,
    reduceMotion,
    triggered,
    setRevealed,
    opacity,
    translateY,
    translateX,
    ref,
    ctx,
    setTimeout,
    clearTimeout,
  };

  const applyReveal = runExtractedCall(
    `() => {${applyRevealBody}}`,
    scope,
    "applyReveal",
    { test: TEST },
  );
  scope.applyReveal = applyReveal;

  const reveal = runExtractedCall(`() => {${revealBody}}`, scope, "reveal", {
    test: TEST,
  });
  scope.reveal = reveal;

  const effect = runExtractedCall(`() => {${effectBody}}`, scope, "effect", {
    test: TEST,
  });

  return {
    opacity,
    triggered,
    setRevealedCalls,
    timers,
    subscribeCalls,
    runEffect: () => effect(),
    fireTimerAt: (ms) => {
      const t = timers.find((x) => x.ms === ms);
      assert.ok(t, `expected a timer scheduled at ${ms}ms but none was found`);
      t.fn();
    },
  };
}

// ---------------------------------------------------------------------------
// 1. Reduce Motion ON — sections start visible and reveal immediately.
// ---------------------------------------------------------------------------
{
  assert.equal(
    initialValue(opacityInitExpr, true),
    1,
    "with reduce motion on, opacity must INITIALISE to 1 (section visible on first paint, never a fade)",
  );
  assert.equal(
    initialValue(translateYInitExpr, true, "up"),
    0,
    "with reduce motion on, translateY must initialise to its resting value (0)",
  );

  const h = makeHarness({ reduceMotion: true });
  h.runEffect();
  assert.ok(
    h.setRevealedCalls.includes(true),
    "with reduce motion on, the effect must reveal the section immediately",
  );
  assert.equal(
    h.timers.length,
    0,
    "with reduce motion on, no timers are needed — the section is already visible",
  );
  assert.equal(
    settledTarget(h.opacity.value),
    1,
    "with reduce motion on, opacity stays at 1 (fully visible)",
  );
}

// ---------------------------------------------------------------------------
// 2. Reduce Motion OFF, view in the viewport — reveals via the measure trigger.
// ---------------------------------------------------------------------------
{
  const h = makeHarness({ reduceMotion: false, measure: "in" });
  assert.equal(
    settledTarget(h.opacity.value),
    0,
    "with reduce motion off, opacity must START at 0 (the pre-reveal state)",
  );
  h.runEffect();
  // Both the 80ms attempt and the 2200ms failsafe must be scheduled.
  assert.ok(
    h.timers.some((t) => t.ms === 80),
    "the reveal effect must schedule the 80ms measure attempt",
  );
  assert.ok(
    h.timers.some((t) => t.ms === 2200),
    "the reveal effect must schedule a failsafe timer as a visibility backstop",
  );
  h.fireTimerAt(80);
  assert.equal(
    settledTarget(h.opacity.value),
    1,
    "with the view in the viewport, the measure trigger must animate opacity to 1",
  );
  assert.ok(
    h.triggered.current,
    "revealing an in-viewport section must mark it triggered",
  );
}

// ---------------------------------------------------------------------------
// 3. Reduce Motion OFF, measure() silently drops its callback — the failsafe
//    is the ONLY thing that reveals the section. This is the core guard: it
//    fails if the safety net is removed and a section is left stuck invisible.
// ---------------------------------------------------------------------------
{
  const h = makeHarness({ reduceMotion: false, measure: "dropped" });
  h.runEffect();

  // Fire the normal 80ms attempt first: measure never calls back, so nothing
  // reveals — the section is still stuck at opacity 0 at this point.
  h.fireTimerAt(80);
  assert.equal(
    settledTarget(h.opacity.value),
    0,
    "when measure() drops its callback, the 80ms attempt cannot reveal the section on its own",
  );
  assert.equal(
    h.triggered.current,
    false,
    "a section whose measure callback never fires must remain un-triggered until the failsafe",
  );

  // Now the failsafe fires — the section MUST become visible.
  h.fireTimerAt(2200);
  assert.equal(
    settledTarget(h.opacity.value),
    1,
    "FAILSAFE: even when measure() never calls back, the section must end fully visible (opacity 1), never stuck at 0",
  );
  assert.ok(
    h.triggered.current,
    "the failsafe must mark the section triggered so it counts as revealed",
  );
}

// ---------------------------------------------------------------------------
// 4. Reduce Motion OFF, off-screen content never scrolled into view — the
//    measure trigger reports it below the fold, so only the failsafe reveals.
// ---------------------------------------------------------------------------
{
  const h = makeHarness({ reduceMotion: false, measure: "out" });
  h.runEffect();
  h.fireTimerAt(80);
  assert.equal(
    settledTarget(h.opacity.value),
    0,
    "off-screen content stays hidden under the normal measure trigger (below the fold)",
  );
  h.fireTimerAt(2200);
  assert.equal(
    settledTarget(h.opacity.value),
    1,
    "FAILSAFE: off-screen content the user never scrolls to must still become visible",
  );
}

// ---------------------------------------------------------------------------
// 5. Reduce Motion OFF, an immeasurable ref — reveal immediately rather than
//    gamble on a measurement that may never come.
// ---------------------------------------------------------------------------
for (const measure of ["null-ref", "none-fn"]) {
  const h = makeHarness({ reduceMotion: false, measure });
  h.runEffect();
  h.fireTimerAt(80);
  assert.equal(
    settledTarget(h.opacity.value),
    1,
    `with an immeasurable ref (${measure}), reveal must fall back to showing the section (opacity 1)`,
  );
}

// ---------------------------------------------------------------------------
// 6. AboutPage hero — the ONE other start-hidden entrance animation in the app
//    that does NOT go through ScrollReveal. Its opacity starts at 0 (motion on)
//    and is revealed by a mount effect. Unlike ScrollReveal it is NOT measure-
//    or scroll-gated, so it's "provably always revealed" — but only as long as
//    that stays true. This guard lifts the REAL hero initialiser + effect and
//    asserts: (a) it starts hidden with motion off / visible with motion on,
//    (b) the effect reveals opacity to 1 in BOTH motion states, and (c) the
//    effect has no measure()/scroll gate that could stop the reveal from firing.
//    If a future refactor gates the hero behind a trigger without a failsafe,
//    this fails instead of silently shipping a hero stuck at opacity 0.
// ---------------------------------------------------------------------------
{
  const aboutSrc = readFileSync(
    join(root, "components", "AboutPage.tsx"),
    "utf8",
  );

  const aboutLift = (re, label) => {
    const m = aboutSrc.match(re);
    assert.ok(m, `could not find the ${label} in AboutPage.tsx`);
    return m[1];
  };

  const heroOpacityInit = aboutLift(
    /const heroOpacity = useSharedValue\(([\s\S]*?)\);/,
    "heroOpacity useSharedValue initialiser",
  )
    .trim()
    .replace(/,$/, "");

  // Anchor to the hero effect specifically: the captured body must not span an
  // earlier useEffect (AboutPage has another effect above the hero one).
  const heroEffectBody = aboutLift(
    /useEffect\(\(\) => \{((?:(?!useEffect\()[\s\S])*?)\}, \[reduceMotion, heroOpacity, heroTranslateY\]\);/,
    "hero reveal useEffect body",
  );

  // The hero reveal must run unconditionally on mount — no measurement or
  // scroll position can gate it, so it can never be left un-fired.
  assert.ok(
    !/\.measure\(/.test(heroEffectBody) && !/scrollY|pageY|windowHeight/.test(heroEffectBody),
    "the AboutPage hero reveal effect must NOT be measure-/scroll-gated — it " +
      "reveals unconditionally on mount so the hero can never stay invisible",
  );

  for (const reduceMotion of [true, false]) {
    const rea = makeReanimated();
    const heroOpacity = {
      value: runExtractedCall(
        heroOpacityInit,
        { reduceMotion },
        "heroOpacity initialiser",
        { test: TEST },
      ),
    };
    const heroTranslateY = { value: 0 };

    // Initial resting value: hidden (0) with motion off, visible (1) on.
    assert.equal(
      heroOpacity.value,
      reduceMotion ? 1 : 0,
      `AboutPage hero opacity must initialise to ${reduceMotion ? 1 : 0} when reduceMotion=${reduceMotion}`,
    );

    const effect = runExtractedCall(
      `() => {${heroEffectBody}}`,
      { ...rea, reduceMotion, heroOpacity, heroTranslateY },
      "hero reveal effect",
      { test: TEST },
    );
    effect();

    assert.equal(
      settledTarget(heroOpacity.value),
      1,
      `AboutPage hero must end fully visible (opacity 1) after its mount effect (reduceMotion=${reduceMotion}) — never stuck at 0`,
    );
  }
}

console.log("test-info-scroll-reveal: all assertions passed");
