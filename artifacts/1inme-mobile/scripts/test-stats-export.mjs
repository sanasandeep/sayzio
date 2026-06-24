// Regression test for the mobile Stats screen's CSV-export paid gate
// (app/stats.tsx). The "Export CSV" control must only appear when the
// user's plan includes the `analytics_export` feature; otherwise an
// upgrade card is shown. The gate reads:
//
//   const canExport = profileQ.data?.capabilities?.analytics_export ?? true;
//
// The `?? true` fallback is load-bearing: it mirrors the server helper's
// default-true behaviour so the control stays visible while the plan
// capabilities are still unknown (no flicker / false-lock for paying
// users). A refactor that flips that default, or that gates on the wrong
// capability key, would either hide export from paying users or expose
// it to users who never paid — with no test catching it. This guards
// both the gating expression and the JSX branch it drives.
//
// Following the convention in test-stats-range.mjs / test-block-cache.mjs,
// we avoid a full TS test runner: the gate is a pure expression, so we
// extract it from the REAL source and evaluate it against mock profile
// data, plus source-level wiring assertions for the two JSX branches.
//
// Run via `node scripts/test-stats-export.mjs` (package script
// `test:stats-export`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(join(__dirname, "..", "app", "stats.tsx"), "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// 1. Pull the exact `canExport` gating expression out of the source and
//    evaluate it as a pure function of the profile query result. We capture
//    the right-hand side verbatim so the test fails if the key or the
//    fallback ever changes.
// ---------------------------------------------------------------------------
const gateMatch = src.match(/const canExport =\s*([^;]+);/);
assert.ok(gateMatch, "could not find the `canExport` gate in stats.tsx");
const gateExpr = gateMatch[1].trim();

// It must read the analytics_export capability with a default-true fallback.
assert.ok(
  /profileQ\.data\?\.capabilities\?\.analytics_export\s*\?\?\s*true/.test(gateExpr),
  "canExport must gate on `capabilities.analytics_export` with a `?? true` fallback",
);
ok("canExport reads capabilities.analytics_export with a `?? true` fallback");

// eslint-disable-next-line no-new-func
const computeCanExport = new Function("profileQ", `return ${gateExpr};`);

// ---------------------------------------------------------------------------
// 2. Drive the gate through all three states the screen must handle.
// ---------------------------------------------------------------------------

// (a) Feature present → Export CSV shown.
assert.equal(
  computeCanExport({ data: { capabilities: { analytics_export: true } } }),
  true,
  "plan includes analytics_export → export enabled",
);
ok("feature present → canExport is true (Export CSV shown)");

// (b) Feature absent → upgrade card shown.
assert.equal(
  computeCanExport({ data: { capabilities: { analytics_export: false } } }),
  false,
  "plan lacks analytics_export → export gated behind upgrade card",
);
ok("feature absent → canExport is false (upgrade card shown)");

// (c) Capabilities unknown → defaults to showing export (no false-lock /
//     flicker while the profile query is still loading or omits the key).
assert.equal(computeCanExport({ data: undefined }), true, "no profile data yet → default-true");
assert.equal(computeCanExport({}), true, "profile query result missing → default-true");
assert.equal(
  computeCanExport({ data: { capabilities: {} } }),
  true,
  "capabilities present but key absent → default-true",
);
assert.equal(
  computeCanExport({ data: { capabilities: undefined } }),
  true,
  "capabilities undefined → default-true",
);
ok("capabilities unknown → canExport defaults to true (Export CSV shown)");

// ---------------------------------------------------------------------------
// 3. JSX wiring guards — the gate must actually drive the two branches:
//    `canExport` → an Export CSV button; otherwise the paid upgrade card
//    with an "Upgrade plan" CTA that routes to /upgrade.
// ---------------------------------------------------------------------------
assert.ok(
  /\{canExport \?\s*\(/.test(src),
  "the Export CSV / upgrade card must be rendered conditionally on `canExport`",
);
ok("screen branches on canExport (? Export CSV : upgrade card)");

const exportBtnMatch = src.match(/<Button\b[\s\S]*?label="Export CSV"[\s\S]*?\/>/);
assert.ok(exportBtnMatch, "the canExport branch must render an Export CSV button");
assert.ok(
  /\/user\/stats\/export\?range=/.test(exportBtnMatch[0]),
  "the Export CSV button must open the /user/stats/export?range= URL",
);
ok("canExport branch renders an Export CSV button hitting /user/stats/export");

// The locked branch must surface the paid-feature copy + an Upgrade CTA.
assert.ok(
  /Stats CSV export is a paid feature/.test(src),
  "the locked branch must label CSV export as a paid feature",
);
assert.ok(
  /label="Upgrade plan"\s+onPress=\{\(\)\s*=>\s*router\.push\("\/upgrade"\)\}/.test(src),
  "the locked branch must offer an Upgrade plan CTA routing to /upgrade",
);
ok("locked branch shows the paid-feature card + Upgrade plan → /upgrade CTA");

console.log(`\n[test-stats-export] all ${passed} checks passed`);
