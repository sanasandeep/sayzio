// Regression test for the tier-switch confirmation toast on the native
// manage-subscription screen (app/manage-subscription.tsx, Task #3042).
//
// When a fan returns to this screen after completing a tier change in the
// creator's subscribe flow, the screen refetches on focus and shows a brief
// "You're now on <tier> for <creator>" toast — but ONLY when a real switch
// happened. The comparison is subtle and easy to regress:
//   - it must SKIP the very first focus (the initial mount fetch), or every
//     fan would see a phantom "you switched" on arrival;
//   - it must fire when an existing creator's active tier id actually changes;
//   - it must stay silent when the tier is unchanged (the fan backed out);
//   - it must ignore a brand-new / previously-unknown creator (no false toast
//     for a subscription that wasn't there before);
//   - it keys by creator HANDLE, not subscription row id, so a switch that
//     re-creates the subscription row (new id, same creator) still registers.
//
// A regression here either shows a false "you switched" message or silently
// drops a real one. Following the source-driven convention used by
// test-auth-flow.mjs / test-stats-range.mjs, this test lifts the REAL focus
// callback out of the screen source, strips its TS annotations, and runs it
// against injected refs/mocks — exercising the actual shipped logic, not a
// re-implementation.
//
// Run via `node scripts/test-tier-switch-toast.mjs` (package script
// `test:tier-switch-toast`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedCall } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(
  join(__dirname, "..", "app", "manage-subscription.tsx"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Lift the REAL focus-effect callback out of the screen.
//
// It is the arrow function passed to `useFocusEffect(useCallback(() => {...},
// [refetch, showToast]))`. We grab the arrow function verbatim, strip the
// (simple) TS annotations so it runs as plain JS, then build a factory that
// closes over the injected `didMount`, `latestData`, `refetch`, `showToast`
// — the exact dependencies the real screen wires in. This runs the shipped
// source, not a copy.
// ---------------------------------------------------------------------------
function loadFocusCallback() {
  const m = src.match(
    /useFocusEffect\(\s*useCallback\((\(\) => \{[\s\S]*?\n {4}\}),\s*\[refetch, showToast\]\)/,
  );
  assert.ok(m, "could not find the useFocusEffect callback in manage-subscription.tsx");
  const body = m[1]
    // `new Map<string, number | null>()` → `new Map()`
    .replace(/new Map<[^>]*>\(\)/g, "new Map()")
    // `(res.data ?? []) as SubscriptionState[]` → drop the cast
    .replace(/ as SubscriptionState\[\]/g, "");

  // Evaluated via the shared resilient helper (scripts/lib/extract.mjs) so a
  // NEW free variable in the focus callback warns actionably instead of
  // hard-crashing the mobile-unit chain with a raw ReferenceError.
  return (deps) =>
    runExtractedCall(
      `(${body})`,
      {
        didMount: deps.didMount,
        latestData: deps.latestData,
        refetch: deps.refetch,
        showToast: deps.showToast,
      },
      "useFocusEffect",
      { test: "test-tier-switch-toast" },
    );
}

const makeCallback = loadFocusCallback();

// Build a SubscriptionState-shaped row (only the fields the callback reads).
function sub({ id, handle, name, tierId, tierName }) {
  return {
    id,
    creator: { handle, name },
    tier: tierId == null ? null : { id: tierId, name: tierName ?? `Tier ${tierId}` },
  };
}

// Run one focus with the given mount state + before/after data, then settle
// the microtask the refetch().then(...) chain schedules so we can read the
// resulting toasts. Returns what the callback did.
async function focusOnce({ mounted, before, after }) {
  const didMount = { current: mounted };
  const latestData = { current: before };
  let refetchCalls = 0;
  const refetch = () => {
    refetchCalls += 1;
    return Promise.resolve({ data: after });
  };
  const toasts = [];
  const showToast = (msg) => toasts.push(msg);

  const cb = makeCallback({ didMount, latestData, refetch, showToast });
  cb();
  // Flush the .then() that compares before vs after.
  await new Promise((r) => setTimeout(r, 0));
  return { didMount, refetchCalls, toasts };
}

// ===========================================================================
// 1. First focus (initial mount) shows NO toast and does not even refetch —
//    the didMount ref must swallow the first invocation.
// ===========================================================================
console.log("[test-tier-switch-toast] first focus is silent");
{
  const before = [sub({ id: 1, handle: "alice", name: "Alice", tierId: 1 })];
  // Even if the data "changed", the first focus must be skipped entirely.
  const after = [sub({ id: 1, handle: "alice", name: "Alice", tierId: 2 })];
  const r = await focusOnce({ mounted: false, before, after });
  assert.equal(r.didMount.current, true, "first focus must flip didMount to true");
  assert.equal(r.refetchCalls, 0, "first focus must not refetch (it returns early)");
  assert.deepEqual(r.toasts, [], "no toast may show on the first focus");
}
ok("first focus skips the refetch + comparison entirely (no phantom toast)");

// ===========================================================================
// 2. A real tier change for an existing creator fires the toast.
// ===========================================================================
console.log("[test-tier-switch-toast] tier change fires the toast");
{
  const before = [sub({ id: 1, handle: "alice", name: "Alice", tierId: 1, tierName: "Bronze" })];
  const after = [sub({ id: 1, handle: "alice", name: "Alice", tierId: 2, tierName: "Gold" })];
  const r = await focusOnce({ mounted: true, before, after });
  assert.equal(r.refetchCalls, 1, "a subsequent focus must refetch");
  assert.equal(r.toasts.length, 1, "a real tier change must show exactly one toast");
  assert.match(
    r.toasts[0],
    /Gold/,
    "the toast must name the new tier",
  );
  assert.match(
    r.toasts[0],
    /Alice/,
    "the toast must name the creator",
  );
}
ok("a changed tier id for an existing creator shows the switch toast");

// ===========================================================================
// 3. An unchanged tier stays silent (the fan opened the subscribe flow but
//    backed out without switching).
// ===========================================================================
console.log("[test-tier-switch-toast] unchanged tier is silent");
{
  const before = [sub({ id: 1, handle: "alice", name: "Alice", tierId: 2 })];
  const after = [sub({ id: 1, handle: "alice", name: "Alice", tierId: 2 })];
  const r = await focusOnce({ mounted: true, before, after });
  assert.equal(r.refetchCalls, 1, "the screen still refetches on focus");
  assert.deepEqual(r.toasts, [], "an unchanged tier must NOT show a toast");
}
ok("an unchanged tier shows no toast (backed out of the switch)");

// ===========================================================================
// 4. A brand-new / previously-unknown creator does not fire a toast — only a
//    creator present BEFORE the refetch can register a switch.
// ===========================================================================
console.log("[test-tier-switch-toast] new creator is ignored");
{
  const before = [sub({ id: 1, handle: "alice", name: "Alice", tierId: 1 })];
  // Alice unchanged; Bob is newly added (was not in `before`).
  const after = [
    sub({ id: 1, handle: "alice", name: "Alice", tierId: 1 }),
    sub({ id: 2, handle: "bob", name: "Bob", tierId: 5 }),
  ];
  const r = await focusOnce({ mounted: true, before, after });
  assert.deepEqual(
    r.toasts,
    [],
    "a creator not present before the refetch must not trigger a toast",
  );
}
ok("a newly added creator is ignored (not treated as a switch)");

// ===========================================================================
// 5. Keyed by handle, not subscription row id: a switch that re-creates the
//    subscription row (new id, same creator handle) still registers.
// ===========================================================================
console.log("[test-tier-switch-toast] keyed by handle, not row id");
{
  const before = [sub({ id: 10, handle: "alice", name: "Alice", tierId: 1, tierName: "Bronze" })];
  // Same creator, brand-new subscription row id, different tier.
  const after = [sub({ id: 99, handle: "alice", name: "Alice", tierId: 3, tierName: "Platinum" })];
  const r = await focusOnce({ mounted: true, before, after });
  assert.equal(r.toasts.length, 1, "a re-created row for the same creator must still toast");
  assert.match(r.toasts[0], /Platinum/, "the toast names the new tier after a row re-create");
}
ok("a re-created subscription row (new id, same handle) still registers the switch");

// ===========================================================================
// 6. Source-wiring guards — pin the invariants the scenarios above rely on so
//    the screen can't quietly drift away from them.
// ===========================================================================
console.log("[test-tier-switch-toast] source wiring");
assert.ok(
  /const didMount = useRef\(false\)/.test(src),
  "didMount must start false so the first focus is skipped",
);
assert.ok(
  /if \(!didMount\.current\) \{\s*didMount\.current = true;\s*return;/.test(src),
  "the callback must return early on the first focus",
);
assert.ok(
  /if \(!h \|\| !before\.has\(h\)\) continue;/.test(src),
  "the comparison must skip creators not present before the refetch",
);
assert.ok(
  /before\.get\(h\) !== \(s\.tier\?\.id \?\? null\)/.test(src),
  "the switch is detected by comparing the active tier id per creator",
);
assert.ok(
  /\.handle\?\.toLowerCase\(\)/.test(src),
  "creators must be keyed by lowercased handle on both sides of the compare",
);
ok("screen wires didMount skip, before-set guard, tier-id compare, and handle keying");

console.log(`\n[test-tier-switch-toast] all ${passed} checks passed`);
