// Source-driven test for the native Plans & billing screen
// (app/plans.tsx, Task #4509).
//
// The "Upgrade on the web" flow hands off to the OS browser and relies on the
// user coming back to the app afterwards. Nothing an external browser session
// does can push the freshly-purchased plan back into the app, so the screen
// leans on an AppState `change` -> `active` listener (via the shared
// useForegroundRefresh hook) to invalidate the billing queries the moment the
// app returns to the foreground. If that wiring regresses, a user who upgrades
// on the web comes back to a stale screen: the "CURRENT" badge still sits on
// the old (free) plan and the "Upgrade on the web" CTA never disappears.
//
// This test verifies, against the REAL shipped source (not a copy):
//   1. Returning to the foreground invalidates ALL THREE billing queries
//      (plans, subscription, downgrade) and refreshes auth — so the plan
//      state is re-fetched from the server.
//   2. The per-plan render rules that decide the CURRENT badge and the
//      "Upgrade on the web" CTA: a paid-plan user sees the badge on the
//      plan whose `is_current` is true, and does NOT see the upgrade CTA on
//      that same current-plan row (only on the other plans).
//
// Following the source-driven convention used by test-tier-switch-toast.mjs /
// test-upgrade-hint.mjs we lift real expressions out of the screen and run
// them via the shared resilient helper (scripts/lib/extract.mjs) so a new
// screen variable warns actionably instead of hard-crashing the chain.
//
// Run via `node scripts/test-plans-foreground-refresh.mjs` (package script
// `test:plans-foreground-refresh`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedCall } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(join(__dirname, "..", "app", "plans.tsx"), "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ===========================================================================
// 1. Foreground refresh: lift the REAL callback passed to
//    useForegroundRefresh(() => {...}) and run it against a mock queryClient +
//    refreshAuth. Assert it invalidates every billing query and refreshes auth.
// ===========================================================================
console.log("[test-plans-foreground-refresh] foreground refresh invalidations");

function loadForegroundCallback() {
  const m = src.match(/useForegroundRefresh\((\(\) => \{[\s\S]*?\n {2}\})\)/);
  assert.ok(m, "could not find the useForegroundRefresh callback in plans.tsx");
  return m[1];
}

const foregroundBody = loadForegroundCallback();

const invalidated = [];
let refreshAuthCalls = 0;
const qc = {
  invalidateQueries: ({ queryKey }) => {
    invalidated.push(queryKey);
  },
};
const refreshAuth = () => {
  refreshAuthCalls += 1;
};

const cb = runExtractedCall(
  `(${foregroundBody})`,
  { qc, refreshAuth },
  "useForegroundRefresh",
  { test: "test-plans-foreground-refresh" },
);
cb();

// Normalise the invalidated keys to comparable strings.
const keys = invalidated.map((k) => JSON.stringify(k));
assert.ok(
  keys.includes(JSON.stringify(["billing", "plans"])),
  "returning to the foreground must invalidate the plans query",
);
assert.ok(
  keys.includes(JSON.stringify(["billing", "subscription"])),
  "returning to the foreground must invalidate the subscription query",
);
assert.ok(
  keys.includes(JSON.stringify(["billing", "downgrade"])),
  "returning to the foreground must invalidate the downgrade query",
);
assert.equal(
  refreshAuthCalls,
  1,
  "returning to the foreground must refresh auth so the plan badge/gating updates",
);
ok("foreground resume invalidates plans + subscription + downgrade and refreshes auth");

// The hook is what actually binds AppState "change" -> "active", so confirm the
// screen imports and calls it (not a hand-rolled listener that could drift).
assert.ok(
  /import \{ useForegroundRefresh \} from "@\/hooks\/useForegroundRefresh"/.test(src),
  "screen must import the shared useForegroundRefresh hook",
);
assert.ok(
  /useForegroundRefresh\(/.test(src),
  "screen must call useForegroundRefresh so AppState resume triggers a refresh",
);
ok("screen wires the shared AppState foreground-refresh hook");

// ===========================================================================
// 2. Per-plan render rules: model the exact source conditions that gate the
//    CURRENT badge and the "Upgrade on the web" CTA, then prove a paid-plan
//    user sees the badge on the current plan and no CTA on that same row.
// ===========================================================================
console.log("[test-plans-foreground-refresh] CURRENT badge + upgrade CTA gating");

// Guard the two source conditions the model below relies on, so the screen
// can't quietly change which flag drives the badge / CTA without this failing.
assert.ok(
  /const isCurrent = plan\.is_current;/.test(src),
  "each plan row's `isCurrent` must be driven by plan.is_current",
);
assert.ok(
  /\{isCurrent \? \(/.test(src),
  "the CURRENT badge must render only when isCurrent is true",
);
assert.ok(
  /\{!isCurrent \? \(/.test(src),
  "the upgrade CTA must render only when isCurrent is false",
);
assert.ok(
  /label="Upgrade on the web"/.test(src),
  "the non-current CTA must be the 'Upgrade on the web' button",
);
ok("source gates badge on isCurrent and 'Upgrade on the web' CTA on !isCurrent");

// A paid-plan user: the Pro plan is the current one. Model the row-level
// decision exactly as the screen does (badge when is_current, CTA otherwise).
const plans = [
  { id: 1, name: "Free", is_current: false, monthly: { amount_minor: 0 } },
  { id: 2, name: "Starter", is_current: false, monthly: { amount_minor: 900 } },
  { id: 3, name: "Pro", is_current: true, monthly: { amount_minor: 1500 } },
];

function renderRow(plan) {
  const isCurrent = plan.is_current;
  return {
    name: plan.name,
    showsBadge: isCurrent, // {isCurrent ? <CURRENT badge> : null}
    showsUpgradeCta: !isCurrent, // {!isCurrent ? <Upgrade on the web> : null}
  };
}

const rows = plans.map(renderRow);
const current = rows.filter((r) => r.showsBadge);

assert.equal(current.length, 1, "exactly one plan row may show the CURRENT badge");
assert.equal(
  current[0].name,
  "Pro",
  "the CURRENT badge must sit on the plan the user is actually on (Pro)",
);
assert.equal(
  renderRow(plans[2]).showsUpgradeCta,
  false,
  "the current (Pro) row must NOT show the 'Upgrade on the web' CTA",
);
// Every OTHER plan still offers the upgrade path.
assert.deepEqual(
  rows.filter((r) => r.showsUpgradeCta).map((r) => r.name),
  ["Free", "Starter"],
  "only non-current plans show the upgrade CTA",
);
ok("paid-plan user: CURRENT badge on the right plan, no upgrade CTA on that row");

// ===========================================================================
// 3. onPaidPlan gating: lift the real expressions the screen uses to decide
//    the paid-only surfaces (downgrade / cancel entry points). A paid user
//    qualifies; a free-plan user does not.
// ===========================================================================
console.log("[test-plans-foreground-refresh] onPaidPlan derivation");

const currentPlanExpr = "plans.find((p) => p.is_current)";
const onPaidPlanExpr =
  "!!currentPlan && (currentPlan.monthly?.amount_minor ?? 0) > 0";

function isOnPaidPlan(planList) {
  const currentPlan = runExtractedCall(
    currentPlanExpr,
    { plans: planList },
    "currentPlan",
    { test: "test-plans-foreground-refresh" },
  );
  return runExtractedCall(
    onPaidPlanExpr,
    { currentPlan },
    "onPaidPlan",
    { test: "test-plans-foreground-refresh" },
  );
}

assert.equal(
  isOnPaidPlan(plans),
  true,
  "a user whose current plan has a positive monthly price is on a paid plan",
);
assert.equal(
  isOnPaidPlan([
    { id: 1, name: "Free", is_current: true, monthly: { amount_minor: 0 } },
    { id: 3, name: "Pro", is_current: false, monthly: { amount_minor: 1500 } },
  ]),
  false,
  "a user on the free plan is NOT on a paid plan (no downgrade/cancel surfaces)",
);
// Guard the source keeps deriving these the way the model above assumes.
assert.ok(
  /const currentPlan = plans\.find\(\(p\) => p\.is_current\);/.test(src),
  "currentPlan must be derived from plans.find(is_current)",
);
assert.ok(
  /const onPaidPlan =\s*!!currentPlan && \(currentPlan\.monthly\?\.amount_minor \?\? 0\) > 0;/.test(
    src,
  ),
  "onPaidPlan must be gated on a positive current-plan monthly price",
);
ok("onPaidPlan is true for a paid current plan and false on free");

console.log(`\n[test-plans-foreground-refresh] all ${passed} checks passed`);
