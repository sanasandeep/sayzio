// Regression test for the mobile first-run setup flow (app/setup.tsx).
//
// setup.tsx is the mobile mirror of the web onboarding wizard: the same
// discrete, visibly-stepped stages (Welcome → Persona → Template → WhatsApp
// optional → Done) that a brand-new user walks exactly once, gated on the
// server's `onboarded_at` being null. The single most costly failure here is a
// flow that STRANDS a first-time user — a stage that never advances, or a
// "finish"/"skip" that doesn't actually mark onboarding complete server-side,
// which would loop the user straight back into setup on their next launch.
//
// Like test-auth-flow.mjs, this is a source-driven test (NOT a headless
// browser click-through): it lifts the REAL logic out of the source and runs
// it against injected mocks, so it exercises the actual code rather than a
// re-implementation, and it stays in lockstep with the file as it evolves.
// It proves:
//   1. The stage machine's ordered stages are correct and the optional
//      WhatsApp stage only appears when a number isn't already verified
//      (the same "Step X of Y" honesty as the web stepper).
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

function loadSetupFns(env) {
  const js = [
    grabFn("finishCoreSetup", ""),
    grabFn("applyDesign", "design: WizardStartingDesign"),
    grabFn("skipTemplate", ""),
  ]
    .join("\n\n")
    // Drop the one param type annotation and the `as ApiError` cast so it runs
    // as plain JS.
    .replace(/\(design: WizardStartingDesign\) =>/, "(design) =>")
    .replace(/ as ApiError/g, "");

  const names = [
    "completeOnboarding",
    "refresh",
    "goStage",
    "whatsappNeeded",
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
  return new Function("whatsappNeeded", `return (${factory})();`);
}

{
  const stagesFor = loadStages();
  assert.deepEqual(
    stagesFor(true),
    ["welcome", "persona", "template", "whatsapp", "done"],
    "when WhatsApp is needed the stages include it, in order",
  );
  assert.deepEqual(
    stagesFor(false),
    ["welcome", "persona", "template", "done"],
    "when a number is already verified the WhatsApp stage is omitted",
  );

  // stepIndex is the stage's position in the ordered list (clamped at 0), so
  // the visible "Step X of Y" and the stepper dots can never point off the end.
  assert.ok(
    /const stepIndex = Math\.max\(0, stages\.indexOf\(stage\)\);/.test(setupSrc),
    "stepIndex must be Math.max(0, stages.indexOf(stage))",
  );
  const stages = stagesFor(true);
  const stepIndexOf = (stage) => Math.max(0, stages.indexOf(stage));
  assert.equal(stepIndexOf("welcome"), 0, "welcome is step 0");
  assert.equal(stepIndexOf("template"), 2, "template is step 2");
  assert.equal(stepIndexOf("done"), 4, "done is the last step");
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
ok("stages are correctly ordered, WhatsApp is optional, and stepIndex tracks the stage");

{
  // The optional WhatsApp stage is honestly gated: present only when no number
  // is verified, and defaulting to 'needed' before the status query resolves.
  assert.ok(
    /!whatsappStatus\.data\.has_whatsapp_number/.test(setupSrc),
    "whatsappNeeded must key off has_whatsapp_number",
  );
  assert.ok(
    /whatsappStatus\.data\s*\?[\s\S]*?:\s*true;/.test(setupSrc),
    "whatsappNeeded must default to true until the status query resolves",
  );
}
ok("the WhatsApp stage is gated on the verified-number status");

// ===========================================================================
// 3. finishCoreSetup calls completeOnboarding() and then advances — and is
//    resilient if the call fails (never traps the user).
// ===========================================================================
console.log("[test-onboarding-setup] finishCoreSetup");

async function runFinish({ whatsappNeeded, throwOnComplete = false }) {
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
  const s = await runFinish({ whatsappNeeded: true });
  assert.equal(s.completeCalls, 1, "finishCoreSetup must call completeOnboarding once");
  assert.deepEqual(
    s.goStage,
    ["whatsapp"],
    "with WhatsApp needed, finishing advances to the WhatsApp stage",
  );
}
{
  const s = await runFinish({ whatsappNeeded: false });
  assert.equal(s.completeCalls, 1, "finishCoreSetup must call completeOnboarding once");
  assert.deepEqual(
    s.goStage,
    ["done"],
    "with no WhatsApp stage, finishing advances straight to Done",
  );
}
{
  // A failed completeOnboarding must NOT trap the user on the current stage.
  const s = await runFinish({ whatsappNeeded: false, throwOnComplete: true });
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
    /import \{ completeOnboarding \} from "@\/lib\/api\/profile";/.test(setupSrc),
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

console.log(`\n[test-onboarding-setup] all ${passed} checks passed`);
