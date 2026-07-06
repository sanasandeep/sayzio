// Regression test for the mobile first-run setup flow (app/setup.tsx).
//
// setup.tsx is the mobile mirror of the web onboarding wizard: the same
// discrete, visibly-stepped stages (Welcome → Persona → Template → WhatsApp
// optional → Privacy optional → Done) that a brand-new user walks exactly once,
// gated on the server's `onboarded_at` being null. The single most costly
// failure here is a flow that STRANDS a first-time user — a stage that never
// advances, or a "finish"/"skip" that doesn't actually mark onboarding complete
// server-side, which would loop the user straight back into setup on their next
// launch.
//
// Like test-auth-flow.mjs, this is a source-driven test (NOT a headless
// browser click-through): it lifts the REAL logic out of the source and runs
// it against injected mocks, so it exercises the actual code rather than a
// re-implementation, and it stays in lockstep with the file as it evolves.
// It proves:
//   1. The stage machine's ordered stages are correct and the optional
//      WhatsApp + Privacy stages appear ONLY when the server's onboarding-status
//      flags (`whatsapp_pending` / `privacy_pending`) say so — a list DERIVED
//      from those flags, never hardcoded (the same "Step X of Y" honesty as the
//      web stepper, which the flags are pinned to by the Laravel
//      OnboardingStatusStepLockstepTest). This is the drift guard: mobile can't
//      promise a stage the API won't ask for, nor hide one it does.
//   2. `stepIndex` is derived from the stage's position in that ordered list.
//   3. Finishing the core setup (`finishCoreSetup`) calls
//      `completeOnboarding()` and then advances — and stays resilient (still
//      advances) if that call fails, so a network blip can't trap the user.
//   4. Applying a template (`applyDesign`) builds the page and then finishes,
//      and skipping the template (`skipTemplate`) finishes — BOTH reaching
//      `completeOnboarding()`.
//   5. The persistent top-bar "Skip setup" escape hatch also calls
//      `completeOnboarding()` before leaving to the app.
//   6. `completeOnboarding()` itself POSTs the `/onboarding/complete` endpoint.
//
// Run via `node scripts/test-onboarding-setup.mjs` (package script
// `test:onboarding-setup`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const setupSrc = readFileSync(join(root, "app", "setup.tsx"), "utf8");
const profileSrc = readFileSync(
  join(root, "lib", "api", "profile.ts"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Lift the REAL finishCoreSetup / applyDesign / skipTemplate out of setup.tsx.
//
// Each is a `const NAME = async (...) => {...};` at 2-space indentation inside
// the component; the terminating `\n  };` is unique per function. We grab each
// verbatim, strip the (simple) TS annotations, and evaluate them together in
// one scope with injected mocks. applyDesign/skipTemplate call the REAL
// finishCoreSetup defined alongside them, so a successful apply/skip really
// runs the completeOnboarding path — not a stubbed copy.
// ---------------------------------------------------------------------------
function grabFn(name, sig) {
  const re = new RegExp(
    `const ${name} = async \\(${sig}\\) => \\{[\\s\\S]*?\\n  \\};`,
    "m",
  );
  const m = setupSrc.match(re);
  if (!m) throw new Error(`could not find ${name} in app/setup.tsx`);
  return m[0];
}

// Grab a simple (non-async) `const NAME = ... ;` arrow whose body has no inner
// semicolons — used for the tiny afterCoreStage / afterWhatsappStage helpers
// that finishCoreSetup + the WhatsApp stage rely on to pick the next stage.
function grabConst(name) {
  const re = new RegExp(`const ${name} = [\\s\\S]*?;`, "m");
  const m = setupSrc.match(re);
  if (!m) throw new Error(`could not find ${name} in app/setup.tsx`);
  return m[0];
}

function loadSetupFns(env) {
  const js = [
    // The next-stage helpers are defined alongside finishCoreSetup and encode
    // the WhatsApp-before-privacy order the real flow uses; include them so
    // finishCoreSetup exercises the REAL branching, not a stub.
    grabConst("afterCoreStage"),
    grabConst("afterWhatsappStage"),
    grabFn("finishCoreSetup", ""),
    grabFn("applyDesign", "design: WizardStartingDesign"),
    grabFn("skipTemplate", ""),
  ]
    .join("\n\n")
    // Drop the param type annotation, the StageKey return annotations, and the
    // `as ApiError` cast so it runs as plain JS.
    .replace(/\(design: WizardStartingDesign\) =>/, "(design) =>")
    .replace(/: StageKey/g, "")
    .replace(/ as ApiError/g, "");

  const names = [
    "completeOnboarding",
    "refresh",
    "goStage",
    "whatsappNeeded",
    "privacyNeeded",
    "generateWizardPage",
    "persona",
    "busy",
    "setBusy",
    "setError",
    "setCreatedLinkId",
  ];
  // The extracted functions also close over React state setters (setBusy,
  // setError, setCreatedLinkId, ...) whose effects don't matter to what we're
  // asserting. Default any unspecified setter to a no-op so a caller only has
  // to inject the dependencies it actually cares about — and so a newly-added
  // setter in setup.tsx can't turn into a stray ReferenceError that silently
  // diverts the flow (e.g. into applyDesign's catch, skipping completeOnboarding).
  const defaults = {
    refresh: () => {},
    goStage: () => {},
    setBusy: () => {},
    setError: () => {},
    setCreatedLinkId: () => {},
    // Optional-stage flags default off so a caller that only cares about the
    // core finish path gets the simple "advance to Done" behaviour.
    whatsappNeeded: false,
    privacyNeeded: false,
  };
  const merged = { ...defaults, ...env };
  // eslint-disable-next-line no-new-func
  return new Function(
    ...names,
    `${js}\n return { finishCoreSetup, applyDesign, skipTemplate };`,
  )(...names.map((n) => merged[n]));
}

// ===========================================================================
// 1 + 2. The ordered stage list and stepIndex derivation (the REAL useMemo
//        body), plus the honest optional-WhatsApp gate.
// ===========================================================================
console.log("[test-onboarding-setup] stage machine");

function loadStages() {
  // Capture the `() => [ ... ]` factory passed to the stages useMemo.
  const m = setupSrc.match(
    /const stages: StageKey\[\] = useMemo\(\s*(\(\) => \[[\s\S]*?\n    \])/,
  );
  if (!m) throw new Error("could not find the stages useMemo in setup.tsx");
  const factory = m[1].replace(/ as StageKey\[\]/g, "");
  // eslint-disable-next-line no-new-func
  return new Function(
    "whatsappNeeded",
    "privacyNeeded",
    `return (${factory})();`,
  );
}

{
  const stagesFor = loadStages();
  // The visible steps are DERIVED from the two server flags across all four
  // combinations — never a fixed list. Both optional stages, in order:
  assert.deepEqual(
    stagesFor(true, true),
    ["welcome", "persona", "template", "whatsapp", "privacy", "done"],
    "when both WhatsApp and privacy are pending, both stages appear in order",
  );
  // WhatsApp only:
  assert.deepEqual(
    stagesFor(true, false),
    ["welcome", "persona", "template", "whatsapp", "done"],
    "when only WhatsApp is pending, the privacy stage is omitted",
  );
  // Privacy only (number already verified):
  assert.deepEqual(
    stagesFor(false, true),
    ["welcome", "persona", "template", "privacy", "done"],
    "when only privacy is pending, the WhatsApp stage is omitted",
  );
  // Neither optional stage:
  assert.deepEqual(
    stagesFor(false, false),
    ["welcome", "persona", "template", "done"],
    "when nothing is pending, both optional stages are omitted",
  );

  // stepIndex is the stage's position in the ordered list (clamped at 0), so
  // the visible "Step X of Y" and the stepper dots can never point off the end.
  assert.ok(
    /const stepIndex = Math\.max\(0, stages\.indexOf\(stage\)\);/.test(setupSrc),
    "stepIndex must be Math.max(0, stages.indexOf(stage))",
  );
  const stages = stagesFor(true, true);
  const stepIndexOf = (stage) => Math.max(0, stages.indexOf(stage));
  assert.equal(stepIndexOf("welcome"), 0, "welcome is step 0");
  assert.equal(stepIndexOf("template"), 2, "template is step 2");
  assert.equal(stepIndexOf("privacy"), 4, "privacy is step 4 when both optional stages show");
  assert.equal(stepIndexOf("done"), 5, "done is the last step");
  assert.equal(
    stepIndexOf("nonexistent"),
    0,
    "an unknown stage clamps to 0 rather than -1",
  );

  // The Stepper is fed the derived stepIndex (so the indicator can't drift
  // from the real stage).
  assert.ok(
    /<Stepper[\s\S]*?stepIndex=\{stepIndex\}/.test(setupSrc),
    "the Stepper must be driven by the derived stepIndex",
  );
}
ok("stages are derived from both flags (all 4 combos), and stepIndex tracks the stage");

{
  // The optional stages are honestly gated on the server's onboarding-status
  // flags — the SAME flags the Laravel OnboardingStatusStepLockstepTest pins to
  // the web stepper/gate predicates — and both default to 'pending' before the
  // status query resolves (so a slow network can't hide a step the user owes).
  assert.ok(
    /whatsappNeeded = onboardingStatus\.data\s*\?[\s\S]*?whatsapp_pending[\s\S]*?:\s*true;/.test(
      setupSrc,
    ),
    "whatsappNeeded must be derived from onboardingStatus.data.whatsapp_pending, defaulting to true",
  );
  assert.ok(
    /privacyNeeded = onboardingStatus\.data\s*\?[\s\S]*?privacy_pending[\s\S]*?:\s*true;/.test(
      setupSrc,
    ),
    "privacyNeeded must be derived from onboardingStatus.data.privacy_pending, defaulting to true",
  );
  // Drift guard: the stages must NOT be gated on a stale client-only signal
  // (e.g. the old has_whatsapp_number probe) — that would let mobile diverge
  // from the server's authoritative flags.
  assert.ok(
    !/whatsappNeeded[\s\S]{0,80}has_whatsapp_number/.test(setupSrc),
    "whatsappNeeded must not be gated on the old client-only has_whatsapp_number probe",
  );
}
ok("both optional stages are gated on the server onboarding-status flags");

// ===========================================================================
// 3. finishCoreSetup calls completeOnboarding() and then advances — and is
//    resilient if the call fails (never traps the user).
// ===========================================================================
console.log("[test-onboarding-setup] finishCoreSetup");

async function runFinish({
  whatsappNeeded,
  privacyNeeded = false,
  throwOnComplete = false,
}) {
  const state = { completeCalls: 0, refreshCalls: 0, goStage: [] };
  const { finishCoreSetup } = loadSetupFns({
    completeOnboarding: async () => {
      state.completeCalls += 1;
      if (throwOnComplete) throw new Error("network down");
    },
    refresh: () => {
      state.refreshCalls += 1;
    },
    goStage: (key) => state.goStage.push(key),
    whatsappNeeded,
    privacyNeeded,
    generateWizardPage: async () => ({}),
    persona: { slug: "creator", category: "personal", page_type: "biolink" },
    busy: false,
    setBusy: () => {},
    setError: () => {},
  });
  await finishCoreSetup();
  return state;
}

{
  // WhatsApp comes first, so it wins whenever it's pending (even if privacy is
  // too) — the real afterCoreStage ordering.
  const s = await runFinish({ whatsappNeeded: true, privacyNeeded: true });
  assert.equal(s.completeCalls, 1, "finishCoreSetup must call completeOnboarding once");
  assert.deepEqual(
    s.goStage,
    ["whatsapp"],
    "with WhatsApp pending, finishing advances to the WhatsApp stage first",
  );
}
{
  // No WhatsApp but privacy pending ⇒ straight to the privacy stage.
  const s = await runFinish({ whatsappNeeded: false, privacyNeeded: true });
  assert.equal(s.completeCalls, 1, "finishCoreSetup must call completeOnboarding once");
  assert.deepEqual(
    s.goStage,
    ["privacy"],
    "with only privacy pending, finishing advances to the privacy stage",
  );
}
{
  const s = await runFinish({ whatsappNeeded: false, privacyNeeded: false });
  assert.equal(s.completeCalls, 1, "finishCoreSetup must call completeOnboarding once");
  assert.deepEqual(
    s.goStage,
    ["done"],
    "with no optional stages, finishing advances straight to Done",
  );
}
{
  // A failed completeOnboarding must NOT trap the user on the current stage.
  const s = await runFinish({
    whatsappNeeded: false,
    privacyNeeded: false,
    throwOnComplete: true,
  });
  assert.equal(s.completeCalls, 1, "completeOnboarding is still attempted");
  assert.deepEqual(
    s.goStage,
    ["done"],
    "even if completeOnboarding throws, the user still advances (not stuck)",
  );
}
ok("finishCoreSetup calls completeOnboarding, advances correctly, and survives a failure");

// ===========================================================================
// 4. applyDesign builds the page then finishes, and skipTemplate finishes —
//    BOTH reaching completeOnboarding via the real finishCoreSetup.
// ===========================================================================
console.log("[test-onboarding-setup] applyDesign / skipTemplate");

{
  const state = { completeCalls: 0, generate: [], createdLinkId: null };
  const persona = { slug: "creator", category: "personal", page_type: "biolink" };
  const { applyDesign } = loadSetupFns({
    completeOnboarding: async () => {
      state.completeCalls += 1;
    },
    refresh: () => {},
    goStage: () => {},
    whatsappNeeded: false,
    generateWizardPage: async (payload) => {
      state.generate.push(payload);
      return { id: 4242 };
    },
    persona,
    busy: false,
    setBusy: () => {},
    setError: () => {},
    setCreatedLinkId: (id) => {
      state.createdLinkId = id;
    },
  });

  await applyDesign({ id: "tpl-42", locked: false });
  assert.equal(state.generate.length, 1, "applyDesign must build the page once");
  assert.equal(
    state.createdLinkId,
    4242,
    "applyDesign must remember the new link id so the user lands in its editor",
  );
  assert.equal(
    state.generate[0].template_id,
    "tpl-42",
    "applyDesign must build from the chosen template id",
  );
  assert.equal(
    state.generate[0].persona,
    "creator",
    "applyDesign must build for the chosen persona",
  );
  assert.equal(
    state.completeCalls,
    1,
    "applying a template must reach completeOnboarding (via finishCoreSetup)",
  );
}
ok("applyDesign builds the page from the chosen template then completes onboarding");

{
  // Guard: with no persona chosen, applyDesign is a no-op (can't build or
  // finish) — it must not silently complete onboarding.
  const state = { completeCalls: 0, generate: 0 };
  const { applyDesign } = loadSetupFns({
    completeOnboarding: async () => {
      state.completeCalls += 1;
    },
    refresh: () => {},
    goStage: () => {},
    whatsappNeeded: false,
    generateWizardPage: async () => {
      state.generate += 1;
      return {};
    },
    persona: null,
    busy: false,
    setBusy: () => {},
    setError: () => {},
  });
  await applyDesign({ id: "tpl-1", locked: false });
  assert.equal(state.generate, 0, "no persona ⇒ no page build");
  assert.equal(state.completeCalls, 0, "no persona ⇒ onboarding is not completed");
}
ok("applyDesign is a no-op without a chosen persona");

{
  const state = { completeCalls: 0 };
  const { skipTemplate } = loadSetupFns({
    completeOnboarding: async () => {
      state.completeCalls += 1;
    },
    refresh: () => {},
    goStage: () => {},
    whatsappNeeded: false,
    generateWizardPage: async () => ({}),
    persona: null,
    busy: false,
    setBusy: () => {},
    setError: () => {},
  });
  await skipTemplate();
  assert.equal(
    state.completeCalls,
    1,
    "skipping the template must reach completeOnboarding (via finishCoreSetup)",
  );
}
ok("skipTemplate completes onboarding so a skipping user isn't looped back");

{
  // Guard: skipTemplate is inert while a build is already in flight.
  const state = { completeCalls: 0 };
  const { skipTemplate } = loadSetupFns({
    completeOnboarding: async () => {
      state.completeCalls += 1;
    },
    refresh: () => {},
    goStage: () => {},
    whatsappNeeded: false,
    generateWizardPage: async () => ({}),
    persona: null,
    busy: true,
    setBusy: () => {},
    setError: () => {},
  });
  await skipTemplate();
  assert.equal(state.completeCalls, 0, "a busy skipTemplate must be a no-op");
}
ok("skipTemplate is inert while busy");

// ===========================================================================
// 5. The persistent top-bar "Skip setup" escape hatch completes onboarding
//    before leaving to the app.
// ===========================================================================
console.log("[test-onboarding-setup] top-bar Skip setup");

{
  // The Skip setup control renders as an accessibilityRole="button" Text whose
  // handler completes onboarding, refreshes, then leaves via finishToApp.
  assert.ok(
    /Skip setup/.test(setupSrc),
    "a 'Skip setup' control must exist",
  );
  const handler = setupSrc.match(
    /accessibilityRole="button"\s*onPress=\{\(\) => \{([\s\S]*?)\}\}/,
  );
  assert.ok(handler, "the Skip setup control must have an onPress handler");
  assert.ok(
    /await completeOnboarding\(\);/.test(handler[1]),
    "top-bar Skip setup must call completeOnboarding()",
  );
  assert.ok(
    /finishToApp\(\);/.test(handler[1]),
    "top-bar Skip setup must leave to the app via finishToApp()",
  );
}
ok("the top-bar Skip setup completes onboarding then leaves to the app");

// ===========================================================================
// 6. completeOnboarding() POSTs /onboarding/complete (the server stamp the
//    whole flow relies on). Runs the REAL profile.ts function.
// ===========================================================================
console.log("[test-onboarding-setup] completeOnboarding endpoint");

{
  assert.ok(
    /import \{[^}]*\bcompleteOnboarding\b[^}]*\} from "@\/lib\/api\/profile";/.test(
      setupSrc,
    ),
    "setup.tsx must import completeOnboarding from the profile API module",
  );

  const m = profileSrc.match(
    /export async function completeOnboarding\(\): Promise<void> \{[\s\S]*?\n\}/,
  );
  assert.ok(m, "could not find completeOnboarding in lib/api/profile.ts");
  const js = m[0]
    .replace(/export async function completeOnboarding\(\): Promise<void> \{/, "async function completeOnboarding() {")
    .replace(/\n\}$/, "\n}");
  const calls = [];
  // eslint-disable-next-line no-new-func
  const fn = new Function(
    "apiFetch",
    `${js}\n return completeOnboarding;`,
  )(async (path, init) => {
    calls.push({ path, init });
    return null;
  });
  await fn();
  assert.equal(calls.length, 1, "completeOnboarding must make exactly one request");
  assert.equal(calls[0].path, "/onboarding/complete", "completeOnboarding endpoint");
  assert.equal(calls[0].init.method, "POST", "completeOnboarding must POST");
}
ok("completeOnboarding POSTs /onboarding/complete");

// ===========================================================================
// 7. The template stage survives an EMPTY catalog: when no starting designs
//    come back (a real deployment with zero active templates for the
//    persona/plan) the stage must still render an escape — an empty-state
//    message AND a "Skip for now" control that isn't hidden behind the
//    (empty) designs list. Otherwise a first-run user is stranded on a blank
//    template stage with nothing to tap. Mirrors the web empty-state card.
// ===========================================================================
console.log("[test-onboarding-setup] empty template catalog");

{
  // Isolate the template stage block so the assertions can't accidentally
  // match an unrelated stage.
  const stageMatch = setupSrc.match(
    /\{stage === "template" \? \([\s\S]*?\n {8}\) : null\}/,
  );
  assert.ok(stageMatch, "could not find the template stage block in setup.tsx");
  const stage = stageMatch[0];

  // The designs list is rendered only when it's non-empty; the else branch
  // (empty catalog OR nothing loaded) shows an explicit empty-state message.
  assert.match(
    stage,
    /designs\.data && designs\.data\.length > 0 \?/,
    "the template stage must gate the design list on a non-empty catalog",
  );
  assert.match(
    stage,
    /No templates to show right now/,
    "an empty catalog must render an explicit empty-state message, not a blank stage",
  );

  // The "Skip for now" escape must live OUTSIDE the designs conditional so it
  // renders whether or not any template came back — the always-available exit.
  assert.match(
    stage,
    /label="Skip for now"[\s\S]*?onPress=\{skipTemplate\}/,
    "the template stage must always render a 'Skip for now' control wired to skipTemplate",
  );

  // Guard: the "Skip for now" button must not be nested inside the
  // designs.data.length > 0 branch (which would hide it on an empty catalog).
  const designsTrueBranch = stage.match(
    /designs\.data && designs\.data\.length > 0 \? \(([\s\S]*?)\n {12}\) : \(/,
  );
  assert.ok(designsTrueBranch, "could not isolate the populated-designs branch");
  assert.ok(
    !/label="Skip for now"/.test(designsTrueBranch[1]),
    "'Skip for now' must NOT be inside the populated-designs branch (it has to survive an empty catalog)",
  );
}
ok("the template stage renders an empty-state message and an always-available Skip for now");

// ===========================================================================
// 8. applyDesign RECOVERS when creating the page fails mid-apply: it must
//    surface an error (the retry affordance) WITHOUT completing onboarding or
//    trapping the user — and the always-available "Skip for now" must still
//    finish onboarding afterwards so a failed template can never strand a
//    first-run user. Mirrors the web controller's catch → back-to-wizard-with
//    -error + persistent "Skip setup" escape.
// ===========================================================================
console.log("[test-onboarding-setup] applyDesign failure recovery");

{
  const state = { completeCalls: 0, error: null, busy: [], createdLinkId: "unset" };
  const persona = { slug: "creator", category: "personal", page_type: "biolink" };
  const fns = loadSetupFns({
    completeOnboarding: async () => {
      state.completeCalls += 1;
    },
    refresh: () => {},
    goStage: () => {},
    whatsappNeeded: false,
    // The page build fails — the exact "creating the starter page fails" edge.
    generateWizardPage: async () => {
      throw new Error("page create failed");
    },
    persona,
    busy: false,
    setBusy: (v) => state.busy.push(v),
    setError: (m) => {
      state.error = m;
    },
    setCreatedLinkId: (id) => {
      state.createdLinkId = id;
    },
  });

  await fns.applyDesign({ id: "tpl-x", locked: false });

  assert.ok(
    typeof state.error === "string" && state.error.length > 0,
    "a failed apply must surface an error message (the retry affordance), not fail silently",
  );
  assert.equal(
    state.completeCalls,
    0,
    "a failed page create must NOT mark onboarding complete (mobile parity with the web catch path)",
  );
  assert.equal(
    state.createdLinkId,
    "unset",
    "a failed apply must not remember a created link id",
  );
  assert.equal(
    state.busy[state.busy.length - 1],
    false,
    "busy must be reset after the failure so the user can retry or skip",
  );

  // The always-available "Skip for now" still escapes the user AFTER a failed
  // apply — reaching completeOnboarding via the real finishCoreSetup, so a
  // template that can't be applied never leaves a first-run user stranded.
  await fns.skipTemplate();
  assert.equal(
    state.completeCalls,
    1,
    "Skip for now still completes onboarding after a failed apply (user is never trapped)",
  );
}
ok("applyDesign surfaces an error on failure and stays escapable via Skip for now");

console.log(`\n[test-onboarding-setup] all ${passed} checks passed`);
