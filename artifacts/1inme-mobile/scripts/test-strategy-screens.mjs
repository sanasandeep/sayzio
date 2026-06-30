// Regression guard for the mobile "AI Digital Performer Specialist" SCREEN
// wiring — the parts a real-device pass with the AI engine ON would exercise,
// covered here WITHOUT a device or any live OpenAI call.
//
// test-strategy-stream.mjs already pins the lib/api network contract (SSE
// parsing, apply-confirm, dismiss, authed export). This test covers the
// complementary surface: the three screens under app/marketing-strategist/*
// and the shared AiDisabledNotice. The task ("Confirm the Performer
// Specialist on a real device with AI turned on") lists two screen behaviours
// that the stream test does not touch and that would otherwise only be caught
// by a human on a phone:
//
//   1. Engine-off and plan-locked accounts must render the CORRECT
//      AiDisabledNotice variant. The server signals engine-off with a
//      200 `{ ai_enabled: false }` (no throw) and plan-locked with a 403, so
//      each screen must branch: ai_enabled === false → variant="engine",
//      errorStatus === 403 → variant="plan". A drift here means a paying user
//      sees the wrong "AI is off" explanation, or a locked user sees a broken
//      list instead of an upgrade nudge.
//   2. Generating a strategy must navigate to the new detail page, applying a
//      suggestion must go through a confirm prompt and then refresh, and
//      export must offer both md + pdf. These are the "it actually does
//      something" wires behind the buttons.
//
// Like the sibling tests this is SOURCE-DRIVEN (no headless RN render): it
// executes the REAL pure helpers (buildPayload, aiFeatureBlurb) against mocks
// and asserts the REAL screen source can't drift away from the wiring above.
// Run via `node scripts/test-strategy-screens.mjs` (script `test:strategy-screens`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const read = (...p) => readFileSync(join(root, ...p), "utf8");

const apiSrc = read("lib", "api", "marketingStrategist.ts");
const noticeSrc = read("components", "AiDisabledNotice.tsx");
const listSrc = read("app", "marketing-strategist", "index.tsx");
const newSrc = read("app", "marketing-strategist", "new.tsx");
const detailSrc = read("app", "marketing-strategist", "[id].tsx");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ===========================================================================
// 1. The REAL buildPayload — the body every estimate/generate request sends.
//    It must trim the goal-derived params, DROP empty / whitespace-only ones
//    (so the backend's `nullable|string` rules aren't fed ""), and pass the
//    chosen sources through untouched. This is the request a live generate
//    rides on; getting it wrong silently changes what the AI is asked.
// ===========================================================================
console.log("[test-strategy-screens] buildPayload trims + omits empties");
function loadBuildPayload() {
  const start = apiSrc.indexOf("function buildPayload(");
  assert.ok(start !== -1, "buildPayload must exist in marketingStrategist.ts");
  // Brace-match the function body.
  const open = apiSrc.indexOf("{", start);
  let depth = 0;
  let i = open;
  for (; i < apiSrc.length; i++) {
    if (apiSrc[i] === "{") depth += 1;
    else if (apiSrc[i] === "}") {
      depth -= 1;
      if (depth === 0) {
        i += 1;
        break;
      }
    }
  }
  let js = apiSrc.slice(start, i);
  js = js
    .replace(
      "function buildPayload(input: StrategyCreateInput): Record<string, unknown> {",
      "function buildPayload(input) {",
    )
    .replace("const parameters: Record<string, string> = {};", "const parameters = {};");
  // eslint-disable-next-line no-new-func
  return new Function(`${js}\n return buildPayload;`)();
}
{
  const buildPayload = loadBuildPayload();
  const out = buildPayload({
    goal: "Grow revenue",
    sources: ["links", "analytics"],
    parameters: {
      budget: "  $500 / month ",
      audience: "",
      timeframe: "   ",
      tone: "bold",
    },
  });
  assert.deepEqual(
    out,
    {
      goal: "Grow revenue",
      sources: ["links", "analytics"],
      parameters: { budget: "$500 / month", tone: "bold" },
    },
    "buildPayload must trim values and drop empty/whitespace params while keeping sources",
  );

  const bare = buildPayload({ goal: "g", sources: [], parameters: {} });
  assert.deepEqual(
    bare,
    { goal: "g", sources: [], parameters: {} },
    "buildPayload must produce an empty parameters object when none are set",
  );
}
ok("buildPayload trims, omits empty params, and forwards sources");

// ===========================================================================
// 2. The REAL aiFeatureBlurb — each screen passes FEATURE_LABEL into the
//    disabled notice, which looks up a per-feature one-liner. Both the
//    internal "Performer Specialist" label (what the screens use) AND the
//    legacy "Marketing Strategist" label must resolve, and an unknown /
//    missing label must return null (so the notice falls back cleanly).
// ===========================================================================
console.log("[test-strategy-screens] aiFeatureBlurb resolves the feature labels");
function loadAiFeatureBlurb() {
  const start = noticeSrc.indexOf("const FEATURE_BLURBS");
  assert.ok(start !== -1, "FEATURE_BLURBS map must exist");
  const fnStart = noticeSrc.indexOf("export function aiFeatureBlurb", start);
  assert.ok(fnStart !== -1, "aiFeatureBlurb must exist");
  const retIdx = noticeSrc.indexOf("return FEATURE_BLURBS[feature]", fnStart);
  const end = noticeSrc.indexOf("}", retIdx) + 1;
  let js = noticeSrc.slice(start, end);
  js = js
    .replace("const FEATURE_BLURBS: Record<string, string> = {", "const FEATURE_BLURBS = {")
    .replace(
      "export function aiFeatureBlurb(feature?: string): string | null {",
      "function aiFeatureBlurb(feature) {",
    );
  // eslint-disable-next-line no-new-func
  return new Function(`${js}\n return aiFeatureBlurb;`)();
}
{
  const aiFeatureBlurb = loadAiFeatureBlurb();
  const performer = aiFeatureBlurb("Performer Specialist");
  assert.ok(
    performer && /marketing strategy|organic|paid/i.test(performer),
    "the Performer Specialist blurb must describe the feature",
  );
  assert.ok(
    aiFeatureBlurb("Marketing Strategist"),
    "the legacy Marketing Strategist label must still resolve",
  );
  assert.equal(aiFeatureBlurb("Nope"), null, "an unknown label must be null");
  assert.equal(aiFeatureBlurb(undefined), null, "a missing label must be null");
}
ok("aiFeatureBlurb resolves Performer Specialist + Marketing Strategist, null otherwise");

// ===========================================================================
// 3. AiDisabledNotice variant copy: the "engine" variant must explain the
//    admin master switch, the "plan" variant must point at the plan. A swap
//    would send a paying user to "ask an admin" and a locked user to "wait
//    for the engine".
// ===========================================================================
console.log("[test-strategy-screens] AiDisabledNotice variant copy");
{
  assert.match(
    noticeSrc,
    /variant === "plan"[\s\S]*?isn.t included on your plan yet/,
    "the plan heading must say the feature isn't on the plan",
  );
  assert.match(
    noticeSrc,
    /variant === "plan"[\s\S]*?plan doesn.t unlock it yet/,
    "the plan message must point the user at their plan",
  );
  assert.match(
    noticeSrc,
    /AI engine isn.t enabled[\s\S]*?only an administrator can turn on/,
    "the engine (default) message must explain the admin master switch",
  );
}
ok("engine variant explains the admin switch; plan variant points at the plan");

// ===========================================================================
// 4. Screen gating: every screen must use the Performer Specialist label and
//    branch engine-off (ai_enabled === false → variant="engine") and
//    plan-locked (errorStatus === 403 → variant="plan"). The detail screen
//    only needs the plan branch (the server 404s a strategy when the engine
//    is off, which the list/new screens gate first).
// ===========================================================================
console.log("[test-strategy-screens] screen engine/plan gating");
function assertScreenGate(label, src, { engine }) {
  assert.match(
    src,
    /const FEATURE_LABEL = "Performer Specialist";/,
    `${label}: must use the Performer Specialist feature label`,
  );
  assert.match(
    src,
    /import \{ AiDisabledNotice \} from "@\/components\/AiDisabledNotice";/,
    `${label}: must import AiDisabledNotice`,
  );
  if (engine) {
    assert.match(
      src,
      /ai_enabled === false[\s\S]*?<AiDisabledNotice[\s\S]*?variant="engine"/,
      `${label}: engine-off (ai_enabled === false) must render the engine variant`,
    );
  }
  assert.match(
    src,
    /errorStatus\([\s\S]*?\) === 403[\s\S]*?<AiDisabledNotice[\s\S]*?variant="plan"/,
    `${label}: a 403 must render the plan variant`,
  );
}
assertScreenGate("list", listSrc, { engine: true });
assertScreenGate("new", newSrc, { engine: true });
assertScreenGate("detail", detailSrc, { engine: false });
ok("list/new gate engine + plan; detail gates plan — all via the Performer Specialist label");

// ===========================================================================
// 5. Generate flow: a successful create() must invalidate the list cache and
//    NAVIGATE to the freshly-created strategy's detail page. Estimate must
//    surface the returned worst-case cost. Both flows route plan-locked
//    failures into the upgrade prompt rather than a dead error string.
// ===========================================================================
console.log("[test-strategy-screens] generate + estimate wiring");
{
  assert.match(
    newSrc,
    /const res = await marketingStrategist\.create\(buildInput\(\)\);/,
    "generate must call create() with the built input",
  );
  assert.match(
    newSrc,
    /router\.replace\(`\/marketing-strategist\/\$\{res\.strategy\.id\}`/,
    "a successful generate must navigate to the new strategy's detail page",
  );
  assert.match(
    newSrc,
    /const res = await marketingStrategist\.estimate\(buildInput\(\)\);[\s\S]*?setEstimate\(res\.estimate\);/,
    "estimate must surface the returned worst-case cost",
  );
  assert.match(
    newSrc,
    /if \(isPlanLockedError\(e\)\) \{\s*showUpgradePrompt\(e\);/,
    "plan-locked generate/estimate must open the upgrade prompt",
  );
}
ok("generate navigates to detail, estimate sets the cost, plan-lock → upgrade prompt");

// ===========================================================================
// 6. Apply flow: applying a suggestion is state-changing, so the screen must
//    CONFIRM first (an Alert), then call applySuggestion and REFRESH the
//    detail so the suggestion flips to "applied". A live chat reply must also
//    refresh so the persisted transcript replaces the streamed buffer.
// ===========================================================================
console.log("[test-strategy-screens] apply-confirm + refresh wiring");
{
  assert.match(
    detailSrc,
    /Alert\.alert\(\s*"Apply suggestion\?"/,
    "applying a suggestion must prompt for confirmation first",
  );
  assert.match(
    detailSrc,
    /await marketingStrategist\.applySuggestion\(s\.id\);\s*refreshShow\(\);/,
    "a confirmed apply must call applySuggestion then refresh the detail",
  );
  assert.match(
    detailSrc,
    /await marketingStrategist\.dismissSuggestion\(s\.id\);\s*refreshShow\(\);/,
    "dismiss must refresh the detail too",
  );
  assert.match(
    detailSrc,
    /onDone: \(\{ message \}\) => \{[\s\S]*?refreshShow\(\);/,
    "a completed chat stream must refresh so the persisted message replaces the buffer",
  );
  assert.match(
    detailSrc,
    /if \(isPlanLockedError\(e\)\) showUpgradePrompt\(e\);/,
    "a plan-locked apply must open the upgrade prompt",
  );
}
ok("apply confirms then refreshes; dismiss + chat-done refresh; plan-lock → upgrade prompt");

// ===========================================================================
// 7. Export flow: the detail screen must offer BOTH formats and hand each to
//    exportStrategy (which carries the bearer token — pinned in the sibling
//    test). A missing format would leave a dead "Export" button.
// ===========================================================================
console.log("[test-strategy-screens] export offers md + pdf");
{
  assert.match(
    detailSrc,
    /import \{[\s\S]*?exportStrategy,[\s\S]*?\} from "@\/lib\/api\/marketingStrategist";/,
    "detail must import exportStrategy",
  );
  assert.match(
    detailSrc,
    /onPress: \(\) => onExport\("md"\)/,
    "export prompt must offer Markdown",
  );
  assert.match(
    detailSrc,
    /onPress: \(\) => onExport\("pdf"\)/,
    "export prompt must offer PDF",
  );
  assert.match(
    detailSrc,
    /exportStrategy\(id, format, strategy\.title\)/,
    "onExport must call exportStrategy with the chosen format",
  );
}
ok("export prompt offers md + pdf and routes through exportStrategy");

console.log(`\n[test-strategy-screens] all ${passed} checks passed`);
