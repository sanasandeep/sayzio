// Smoke test for the audience-estimate insufficient-coins handling on the
// mobile analytics screen (app/links/[id]/analytics.tsx).
//
// Regression under test: `handlePlanLockedError` (lib/upgradePrompt.ts)
// classifies ALL plan-lock codes — including `insufficient_credits` — as
// upgrade prompts. Running out of coins is a wallet top-up problem, not a
// plan problem, so the estimate mutation's onError MUST branch on
// `insufficient_credits` BEFORE delegating to handlePlanLockedError, or the
// user gets sent down the plan-upgrade UX instead of the top-up hint.
//
// Following the convention in test-upgrade-hint.mjs we extract the shipped
// onError handler body from the real source (no re-implementation), strip
// the simple TS annotations, and run it with stubs.
//
// Run via `node scripts/test-audience-coin-warning.mjs` (package script
// `test:audience-coin-warning`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenSrc = readFileSync(
  join(root, "app", "links", "[id]", "analytics.tsx"),
  "utf8",
);
const promptSrc = readFileSync(join(root, "lib", "upgradePrompt.ts"), "utf8");

// --- Sanity: upgradePrompt still classifies insufficient_credits as a
// plan-lock code. If this ever changes, the ordering below stops being
// load-bearing (but stays harmless).
assert.ok(
  /PLAN_LOCK_CODES = new Set<string>\(\[[\s\S]*?"insufficient_credits"[\s\S]*?\]\)/.test(
    promptSrc,
  ),
  "expected PLAN_LOCK_CODES to include insufficient_credits (update this test if the classification moved)",
);

// --- Extract the estimate mutation's onError handler body via brace match.
const onErrorStart = screenSrc.indexOf("onError: (e) => {");
assert.ok(onErrorStart > -1, "could not find estimate onError handler");
let depth = 0;
let end = -1;
for (let i = screenSrc.indexOf("{", onErrorStart); i < screenSrc.length; i++) {
  const ch = screenSrc[i];
  if (ch === "{") depth++;
  else if (ch === "}") {
    depth--;
    if (depth === 0) {
      end = i + 1;
      break;
    }
  }
}
assert.ok(end > -1, "could not brace-match onError body");
const handlerSrc = screenSrc.slice(onErrorStart, end);

// Ordering guard: the insufficient_credits branch must come first.
const insuffIdx = handlerSrc.indexOf('"insufficient_credits"');
const planLockIdx = handlerSrc.indexOf("handlePlanLockedError(");
assert.ok(insuffIdx > -1, "onError lacks an insufficient_credits branch");
assert.ok(planLockIdx > -1, "onError lacks handlePlanLockedError delegation");
assert.ok(
  insuffIdx < planLockIdx,
  "insufficient_credits must be checked BEFORE handlePlanLockedError (which swallows it as a plan-upgrade prompt)",
);

// --- Behavior: run the shipped handler with stubs.
const handlerJs = handlerSrc
  .replace(/^onError: /, "const onError = ")
  .replace(/\(e as \{ code\?: string \}\)/g, "e")
  .replace(/\(e as \{ message\?: string \}\)/g, "e");

function runHandler(err) {
  const calls = {
    setError: [],
    setUpgradeMsg: [],
    invalidated: 0,
    planLocked: 0,
  };
  const setError = (m) => calls.setError.push(m);
  const setUpgradeMsg = (m) => calls.setUpgradeMsg.push(m);
  const qc = { invalidateQueries: () => calls.invalidated++ };
  const linkId = 7;
  const handlePlanLockedError = (e) => {
    calls.planLocked++;
    // Mirror the real classifier: any PLAN_LOCK_CODES code returns true.
    return ["plan_upgrade_required", "insufficient_credits"].includes(e?.code);
  };
  const upgradeHintFromError = () => null;
  const fn = new Function(
    "setError",
    "setUpgradeMsg",
    "qc",
    "linkId",
    "handlePlanLockedError",
    "upgradeHintFromError",
    `${handlerJs}; return onError;`,
  )(setError, setUpgradeMsg, qc, linkId, handlePlanLockedError, upgradeHintFromError);
  fn(err);
  return calls;
}

// 1. insufficient_credits → top-up message, never the plan-upgrade path.
{
  const calls = runHandler({
    code: "insufficient_credits",
    message: "Not enough coins to run this estimate. Top up your wallet and try again.",
  });
  assert.equal(calls.planLocked, 0, "must not reach handlePlanLockedError");
  assert.equal(calls.setError.length, 1);
  assert.match(calls.setError[0], /Top up/i);
  assert.equal(calls.setUpgradeMsg.length, 0, "no upgrade messaging");
  assert.equal(calls.invalidated, 1, "refreshes analytics for a fresh balance");
}

// 2. plan_upgrade_required still routes through the plan-lock path.
{
  const calls = runHandler({ code: "plan_upgrade_required" });
  assert.equal(calls.planLocked, 1);
  assert.equal(calls.setUpgradeMsg.length, 1);
  assert.equal(calls.setError.length, 0);
}

// 3. Unknown errors fall through to the generic message.
{
  const calls = runHandler({ message: "boom" });
  assert.equal(calls.setError.length, 1);
  assert.equal(calls.setError[0], "boom");
}

console.log("test-audience-coin-warning: all assertions passed");
