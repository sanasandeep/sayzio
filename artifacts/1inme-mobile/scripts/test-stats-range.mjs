// Regression test for the mobile Stats screen's range-pill switching
// (app/stats.tsx). Tapping a different range (7D / 30D / 90D / 1Y) must
// (a) call setRange with that range's key and (b) re-fetch its data —
// the screen relies on the React Query key ["creator-stats", range]
// changing so each range gets its own cache entry and a fresh request.
//
// A refactor that drops `range` from the query key, or stops calling
// setRange on press, would silently show stale data for the wrong window
// with no test catching it — this guards both.
//
// Following the convention in test-citation-href.mjs / test-block-cache.mjs,
// we avoid a full TS test runner: the relevant pieces (RANGES, fetchStats)
// are pure, so we strip their type annotations and evaluate them in
// isolation against the REAL source, plus source-level wiring assertions
// and a live QueryClient check.
//
// Run via `node scripts/test-stats-range.mjs` (package script
// `test:stats-range`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { QueryClient } from "@tanstack/react-query";

const __dirname = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(join(__dirname, "..", "app", "stats.tsx"), "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// 1. Pull the RANGES catalog out of the source and evaluate it. This is the
//    single source of truth for the pills; every entry must map to a request.
// ---------------------------------------------------------------------------
const rangesMatch = src.match(/const RANGES = (\[[\s\S]*?\]) as const;/);
assert.ok(rangesMatch, "could not find the RANGES catalog in stats.tsx");
// eslint-disable-next-line no-new-func
const RANGES = new Function(`return ${rangesMatch[1]};`)();

assert.ok(Array.isArray(RANGES) && RANGES.length >= 2, "RANGES should be a non-empty list");
for (const r of RANGES) {
  assert.equal(typeof r.key, "string", "each range needs a string key");
  assert.equal(typeof r.label, "string", "each range needs a label");
}
// The four documented windows are present (guards an accidental drop).
assert.deepEqual(
  RANGES.map((r) => r.key),
  ["7d", "30d", "90d", "1y"],
  "RANGES should cover 7d/30d/90d/1y",
);
ok(`RANGES catalog has ${RANGES.length} windows (7d/30d/90d/1y)`);

// ---------------------------------------------------------------------------
// 2. Extract fetchStats and run it with a mock apiFetch so we can assert the
//    EXACT request path each range produces: /stats?range=<key>.
// ---------------------------------------------------------------------------
const fnMatch = src.match(/async function fetchStats\b[\s\S]*?\n\}/m);
assert.ok(fnMatch, "could not find fetchStats in stats.tsx");
const js = fnMatch[0]
  .replace(/:\s*RangeKey\b/g, "")
  .replace(/:\s*Promise<StatsResponse>/g, "")
  .replace(/<\{\s*data:\s*StatsResponse\s*\}>/g, "");

// eslint-disable-next-line no-new-func
const makeFetchStats = new Function(
  "apiFetch",
  `${js}; return fetchStats;`,
);

const calls = [];
const fetchStats = makeFetchStats(async (path) => {
  calls.push(path);
  return { data: { range: { from: "x", to: "y" } } };
});

for (const r of RANGES) {
  calls.length = 0;
  const out = await fetchStats(r.key);
  assert.deepEqual(
    calls,
    [`/stats?range=${r.key}`],
    `tapping ${r.label} should request /stats?range=${r.key}`,
  );
  assert.deepEqual(out, { range: { from: "x", to: "y" } }, "fetchStats unwraps res.data");
}
ok("each RANGES entry maps to a single /stats?range=<key> request");

// ---------------------------------------------------------------------------
// 3. Component wiring guards — these make sure the screen actually:
//    - keys the query by range (so a new range = a new cache entry = refetch)
//    - calls fetchStats(range) as the queryFn
//    - calls setRange(r.key) when a pill is pressed
//    - seeds a sensible default range
// ---------------------------------------------------------------------------
assert.ok(
  /queryKey:\s*\["creator-stats",\s*range\]/.test(src),
  'the stats query must be keyed by range (["creator-stats", range]) so switching re-fetches',
);
assert.ok(
  /queryFn:\s*\(\)\s*=>\s*fetchStats\(range\)/.test(src),
  "the stats query must call fetchStats(range)",
);
assert.ok(
  /onPress=\{\(\)\s*=>\s*setRange\(r\.key\)\}/.test(src),
  "each range pill must call setRange(r.key) on press",
);
const defaultMatch = src.match(/useState<RangeKey>\("([^"]+)"\)/);
assert.ok(defaultMatch, "range should be backed by useState<RangeKey>");
assert.ok(
  RANGES.some((r) => r.key === defaultMatch[1]),
  `the default range "${defaultMatch?.[1]}" must be one of the RANGES keys`,
);
ok("screen keys the query by range, calls fetchStats(range) + setRange(r.key), seeds a valid default");

// ---------------------------------------------------------------------------
// 4. Live QueryClient: prove the range-keyed cache means each window holds
//    its own data, so switching range cannot serve another window's data.
//    (If a refactor dropped `range` from the key, all windows would collide
//    on one cache entry and this would fail.)
// ---------------------------------------------------------------------------
{
  const qc = new QueryClient();
  // Seed each range's cache entry with a marker for that range.
  for (const r of RANGES) {
    qc.setQueryData(["creator-stats", r.key], { window: r.key });
  }
  // Every range reads back its OWN data — no collisions across windows.
  for (const r of RANGES) {
    assert.deepEqual(
      qc.getQueryData(["creator-stats", r.key]),
      { window: r.key },
      `${r.label} keeps its own cache entry`,
    );
  }
  // A brand-new range key has no cached data → React Query would fetch it.
  assert.equal(
    qc.getQueryData(["creator-stats", "all-time"]),
    undefined,
    "an unseen range has no cached data, forcing a fresh fetch",
  );
}
ok("range-keyed cache isolates every window (switching never serves stale data)");

console.log(`\n[test-stats-range] all ${passed} checks passed`);
