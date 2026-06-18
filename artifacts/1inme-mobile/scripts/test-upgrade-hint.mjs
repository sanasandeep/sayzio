// Smoke test for the mobile "upgrade hint" flow — the logic that turns a
// plan-gated API error into a pre-highlighted /upgrade screen.
//
// The chain under test (parity with the Laravel side, see
// artifacts/1inme/tests/Feature/PlanGateApiHintTest.php):
//
//   1. The REST API stamps `error.details.recommended_plan` (slug) +
//      `recommended_plan_name` + `feature` on plan-gated rejections.
//   2. `upgradeHintFromError` (lib/upgradePrompt.ts) parses those into a
//      hint; `upgradeRoute` attaches them as `?plan=` / `?feature=` params.
//   3. `resolveRecommended` (app/upgrade.tsx) turns the `?plan=` param back
//      into the Plan to highlight + scroll to; with NO param it returns
//      null so the screen falls back to the generic free/popular view.
//
// Following the convention in test-citation-href.mjs / test-block-cache.mjs
// we avoid a full TS test runner: the helpers are pure, so we extract their
// bodies from the real source and strip the (simple) TS annotations so they
// run as plain JS. This keeps the test honest — it exercises the shipped
// source, not a re-implementation.
//
// Run via `node scripts/test-upgrade-hint.mjs` (package script
// `test:upgrade-hint`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const promptSrc = readFileSync(join(root, "lib", "upgradePrompt.ts"), "utf8");
const screenSrc = readFileSync(join(root, "app", "upgrade.tsx"), "utf8");

// Pull a top-level `function NAME(...) { ... }` body out of `src`, matching
// up to the first closing brace at column 0 (all our targets are top-level).
function extractFn(src, name) {
  const re = new RegExp(`(?:export )?function ${name}\\b[\\s\\S]*?\\n\\}`, "m");
  const m = src.match(re);
  if (!m) throw new Error(`could not find ${name}`);
  return m[0];
}

// --- lib/upgradePrompt.ts: asApiError + str + upgradeHintFromError + upgradeRoute
const promptJs = [
  extractFn(promptSrc, "asApiError"),
  extractFn(promptSrc, "str"),
  extractFn(promptSrc, "upgradeHintFromError"),
  extractFn(promptSrc, "upgradeRoute"),
]
  .join("\n")
  .replace(/: unknown/g, "")
  .replace(/: Partial<ApiError> \| null/g, "")
  .replace(/ as Partial<ApiError>/g, "")
  .replace(/: string \| undefined/g, "")
  .replace(/: UpgradeHint \| undefined/g, "")
  .replace(/\?: UpgradeHint/g, "")
  .replace(/: Record<string, string>/g, "")
  .replace(/ as Record<string, unknown>/g, "")
  .replace(/export function/g, "function");

// eslint-disable-next-line no-new-func
const prompt = new Function(
  `${promptJs}; return { upgradeHintFromError, upgradeRoute };`,
)();

// --- app/upgrade.tsx: resolveRecommended
const screenJs = extractFn(screenSrc, "resolveRecommended")
  .replace(
    /function resolveRecommended\([\s\S]*?\):\s*Plan \| null \{/,
    "function resolveRecommended(plans, planSlug, feature) {",
  )
  .replace(/\(p: Plan\): boolean/g, "(p)");

// eslint-disable-next-line no-new-func
const screen = new Function(
  `${screenJs}; return { resolveRecommended };`,
)();

const { upgradeHintFromError, upgradeRoute } = prompt;
const { resolveRecommended } = screen;

// ---------------------------------------------------------------------------
// 1. upgradeHintFromError parses the exact envelope the Laravel planGate()
//    stamps: { error: { details: { feature, recommended_plan, recommended_plan_name } } }
// ---------------------------------------------------------------------------
const gateError = {
  status: 402,
  code: "plan_upgrade_required",
  message: "Smart links are not available on your current plan.",
  details: {
    feature: "link_smart_rules",
    recommended_plan: "pro",
    recommended_plan_name: "Pro",
  },
};
const hint = upgradeHintFromError(gateError);
assert.deepEqual(
  hint,
  { planSlug: "pro", planName: "Pro", feature: "link_smart_rules" },
  "hint should carry the recommended plan slug/name + feature",
);

// No details (older server / non-plan error) → no hint, screen stays generic.
assert.equal(
  upgradeHintFromError({ status: 422, message: "Bad input" }),
  undefined,
  "an error without details yields no hint",
);
// feature-only (no qualifying plan) still yields a usable hint for fallback.
assert.deepEqual(
  upgradeHintFromError({ details: { feature: "max_links" } }),
  { planSlug: undefined, planName: undefined, feature: "max_links" },
  "feature-only details still resolve a hint",
);

// ---------------------------------------------------------------------------
// 2. upgradeRoute attaches the hint as `?plan=` (+ `?feature=`) params so the
//    screen receives the slug; with NO hint it routes to the bare screen.
// ---------------------------------------------------------------------------
assert.deepEqual(
  upgradeRoute(hint),
  { pathname: "/upgrade", params: { plan: "pro", feature: "link_smart_rules" } },
  "route should attach ?plan=<slug> and ?feature=",
);
assert.equal(
  upgradeRoute(undefined),
  "/upgrade",
  "no hint → bare /upgrade route (generic view)",
);

// ---------------------------------------------------------------------------
// 3. resolveRecommended: the `?plan=<slug>` param highlights/scrolls to that
//    plan; no param falls back to the generic view (null = no highlight).
// ---------------------------------------------------------------------------
const plans = [
  {
    id: 1,
    slug: "free",
    is_current: true,
    is_popular: false,
    monthly: { amount_minor: 0 },
    features_map: { link_smart_rules: 0, max_links: 1 },
  },
  {
    id: 2,
    slug: "starter",
    is_current: false,
    is_popular: false,
    monthly: { amount_minor: 900 },
    features_map: { link_smart_rules: 0, max_links: 5 },
  },
  {
    id: 3,
    slug: "pro",
    is_current: false,
    is_popular: true,
    monthly: { amount_minor: 1500 },
    features_map: { link_smart_rules: 1, max_links: 50 },
  },
];

// (a) Explicit slug from `?plan=pro` → that exact plan is highlighted.
assert.equal(
  resolveRecommended(plans, "pro", "link_smart_rules")?.id,
  3,
  "?plan=pro should resolve the Pro plan to highlight + scroll to",
);

// (b) No param at all → null, screen shows the generic free/popular pair.
assert.equal(
  resolveRecommended(plans, undefined, undefined),
  null,
  "no ?plan / ?feature → no recommendation (generic fallback view)",
);

// (c) feature-only fallback (slug missing): pick the cheapest plan that
//     RAISES the current numeric cap — proves the screen still highlights
//     sensibly when the server couldn't name a plan but sent the feature.
assert.equal(
  resolveRecommended(plans, undefined, "max_links")?.id,
  2,
  "feature-only max_links should pick the cheapest plan above the current cap (starter)",
);
// Boolean feature-only → cheapest plan with the flag truthy.
assert.equal(
  resolveRecommended(plans, undefined, "link_smart_rules")?.id,
  3,
  "feature-only boolean flag should pick the cheapest unlocking plan (pro)",
);

// (d) Unknown slug with no feature → null (don't invent a recommendation).
assert.equal(
  resolveRecommended(plans, "does-not-exist", undefined),
  null,
  "an unknown ?plan slug with no feature falls back to the generic view",
);

console.log("ok — upgrade hint flow (error → hint → route → resolveRecommended)");
