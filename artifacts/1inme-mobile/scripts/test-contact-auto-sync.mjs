// Source-driven test for the silent background contacts auto-sync
// (hooks/useContactAutoSync.ts, mounted via ContactAutoSync in app/_layout.tsx).
//
// The hook silently re-imports the device address book on app start and on
// every foreground resume while signed in and unlocked. Because it runs with
// no UI at all, a regression here is invisible in manual testing: it could
// start prompting for permission (requestPermission drifting to true), hammer
// the device/API (throttle or single-flight guard lost), refresh screens on a
// FAILED import, or start showing alerts.
//
// Following the source-driven convention (test-plans-foreground-refresh.mjs)
// this lifts the REAL effect body out of the shipped hook and drives it
// against a mock query client, a mock AppState, a controllable clock, and a
// mock importDeviceContacts, asserting:
//   1. importDeviceContacts is ALWAYS called with requestPermission: false
//      (the manual Contacts-screen import stays the only prompting path).
//   2. The 60s throttle holds: a foreground bounce within a minute of the
//      last run does nothing; after the minute a resume re-syncs.
//   3. The single-flight guard holds: a resume while an import is still
//      in flight never starts a second concurrent import.
//   4. ["contacts"] and ["contact-duplicate-count"] are invalidated only on a
//      successful import — not on { ok: false }, not on a thrown error, and
//      not after the effect was cleaned up (unmounted).
//   4b. Change detection: the hook passes the last known fingerprint as
//      unchangedFingerprint, remembers the fingerprint from successful AND
//      "unchanged" outcomes, and an unchanged address book never invalidates
//      queries (the POST was skipped inside importDeviceContacts).
//   4c. Persistence across restarts: on mount the in-memory fingerprint is
//      seeded from the per-user persisted copy (so a cold app start with an
//      unchanged address book skips the POST too), every fingerprint outcome
//      is persisted for that user, and a different userId resets the seed so
//      switching accounts still syncs.
//   5. No alert is ever shown (the hook source never references Alert).
//   6. enabled=false means no sync and no AppState listener at all.
//
// Run via `node scripts/test-contact-auto-sync.mjs` (package script
// `test:contact-auto-sync`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedCall } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const hookSrc = readFileSync(
  join(__dirname, "..", "hooks", "useContactAutoSync.ts"),
  "utf8",
);
const layoutSrc = readFileSync(
  join(__dirname, "..", "app", "_layout.tsx"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ===========================================================================
// Static source guards
// ===========================================================================
console.log("[test-contact-auto-sync] static source guards");

// The silent path must never prompt: the ONLY importDeviceContacts call in the
// hook passes requestPermission: false.
const importCalls = hookSrc.match(/importDeviceContacts\(([^)]*)\)/g) ?? [];
assert.ok(importCalls.length >= 1, "hook must call importDeviceContacts");
for (const call of importCalls) {
  assert.ok(
    /requestPermission:\s*false/.test(call),
    `every importDeviceContacts call in the hook must pass requestPermission: false — got ${call}`,
  );
}
ok("hook only ever calls importDeviceContacts with requestPermission: false");

// Truly silent: no Alert anywhere in the hook.
assert.ok(
  !/\bAlert\b/.test(hookSrc),
  "the silent auto-sync hook must never import or use Alert",
);
ok("hook never references Alert (fully silent)");

// The throttle constant the behavioural model below relies on.
assert.ok(
  /const MIN_INTERVAL_MS = 60_?000;/.test(hookSrc),
  "the throttle interval must stay at 60 seconds (MIN_INTERVAL_MS = 60_000)",
);
ok("60s throttle constant present");

// Root layout wiring: the hook is mounted via ContactAutoSync, gated on a
// signed-in, unlocked session.
assert.ok(
  /import \{ useContactAutoSync \} from "@\/hooks\/useContactAutoSync"/.test(
    layoutSrc,
  ),
  "_layout.tsx must import the useContactAutoSync hook",
);
assert.ok(
  /useContactAutoSync\(Boolean\(user && token && !locked\), user\?\.id \?\? null\)/.test(
    layoutSrc,
  ),
  "ContactAutoSync must enable the sync only when signed in AND unlocked, keyed per user id",
);
assert.ok(
  /<ContactAutoSync \/>/.test(layoutSrc),
  "_layout.tsx must actually mount <ContactAutoSync />",
);
ok("root layout mounts ContactAutoSync gated on user && token && !locked");

// ===========================================================================
// Lift the REAL effect body and drive it against mocks.
// ===========================================================================
console.log("[test-contact-auto-sync] behavioural checks on the lifted effect");

function loadEffectBody() {
  const m = hookSrc.match(
    /useEffect\((\(\) => \{[\s\S]*?\n {2}\}), \[enabled, qc, userId\]\);/,
  );
  assert.ok(m, "could not find the useEffect body in useContactAutoSync.ts");
  return m[1];
}
const effectBody = loadEffectBody();

// Harness: mount the lifted effect with a controllable clock, AppState, and
// import outcome. Returns handles to advance time, fire foreground events,
// resolve pending imports, and inspect calls/invalidations.
// `stored` maps userId -> persisted fingerprint (the AsyncStorage mock);
// `refs` lets a second mount reuse the SAME refs, modelling a re-run of the
// effect within one component instance (e.g. an account switch).
function mount({ enabled = true, userId = 1, stored = {}, refs = null } = {}) {
  const state = {
    now: 0,
    importCalls: [],
    invalidated: [],
    listeners: [],
    removed: 0,
    // Each import call pushes a deferred here; tests resolve them explicitly.
    pending: [],
    stored,
  };

  const sharedRefs = refs ?? {
    running: { current: false },
    lastRun: { current: -60_000 }, // far enough back that the mount sync runs at t=0
    lastFingerprint: { current: null },
    lastUserId: { current: null },
  };
  state.refs = sharedRefs;

  const scope = {
    enabled,
    userId,
    ...sharedRefs,
    MIN_INTERVAL_MS: 60_000,
    Date: { now: () => state.now },
    qc: {
      invalidateQueries: ({ queryKey }) => state.invalidated.push(queryKey),
    },
    importDeviceContacts: (opts) => {
      state.importCalls.push(opts);
      return new Promise((resolve, reject) => {
        state.pending.push({ resolve, reject });
      });
    },
    getStoredContactSyncFingerprint: async (uid) => state.stored[uid] ?? null,
    setStoredContactSyncFingerprint: async (uid, fp) => {
      state.stored[uid] = fp;
    },
    AppState: {
      addEventListener: (event, cb) => {
        assert.equal(event, "change", "hook must listen to AppState 'change'");
        state.listeners.push(cb);
        return {
          remove: () => {
            state.removed += 1;
          },
        };
      },
    },
  };

  const effect = runExtractedCall(`(${effectBody})`, scope, "useEffect", {
    test: "test-contact-auto-sync",
  });
  state.cleanup = effect();
  state.foreground = (appState = "active") => {
    for (const cb of state.listeners) cb(appState);
  };
  return state;
}

const tick = () => new Promise((r) => setImmediate(r));

// --- disabled: nothing happens at all --------------------------------------
{
  const h = mount({ enabled: false });
  await tick();
  assert.equal(h.importCalls.length, 0, "disabled hook must not import");
  assert.equal(h.listeners.length, 0, "disabled hook must not listen to AppState");
  ok("enabled=false: no import, no AppState listener");
}

// --- happy path: mount sync + invalidation on success -----------------------
{
  const h = mount();
  await tick(); // the mount sync first awaits the persisted-fingerprint read
  assert.equal(h.importCalls.length, 1, "must sync once on mount");
  assert.deepEqual(
    h.importCalls[0],
    { requestPermission: false, unchangedFingerprint: null },
    "first runtime call must pass requestPermission: false and no fingerprint yet",
  );
  h.pending[0].resolve({ ok: true, imported: 3, fingerprint: "fp-a" });
  await tick();
  assert.deepEqual(
    h.invalidated.map((k) => JSON.stringify(k)).sort(),
    [JSON.stringify(["contact-duplicate-count"]), JSON.stringify(["contacts"])].sort(),
    "a successful import must invalidate contacts + contact-duplicate-count (and nothing else)",
  );
  assert.equal(
    h.stored[1],
    "fp-a",
    "a successful import must persist the fingerprint for this user",
  );
  ok("mount sync runs silently, invalidates both queries and persists the fingerprint");

  // --- throttle: bounce within 60s is a no-op -------------------------------
  h.now = 30_000;
  h.foreground();
  await tick();
  assert.equal(h.importCalls.length, 1, "resume 30s after last run must be throttled");
  // Non-active states never trigger either.
  h.now = 120_000;
  h.foreground("background");
  h.foreground("inactive");
  await tick();
  assert.equal(h.importCalls.length, 1, "only 'active' may trigger a sync");
  ok("60s throttle holds and only 'active' transitions trigger");

  // --- after the minute a resume re-syncs -----------------------------------
  h.foreground();
  await tick();
  assert.equal(h.importCalls.length, 2, "resume after 60s must re-sync");
  assert.deepEqual(
    h.importCalls[1],
    { requestPermission: false, unchangedFingerprint: "fp-a" },
    "later runs must pass the remembered fingerprint so unchanged books skip the POST",
  );
  ok("hook passes the fingerprint from the last successful sync as unchangedFingerprint");

  // --- single-flight: resume while in flight never doubles up ---------------
  h.now = 300_000; // well past the throttle window
  h.foreground();
  h.foreground();
  await tick();
  assert.equal(
    h.importCalls.length,
    2,
    "a resume while an import is still in flight must not start a second one",
  );
  ok("single-flight guard holds while an import is in flight");

  // --- failure paths never invalidate ---------------------------------------
  h.invalidated.length = 0;
  h.pending[1].resolve({ ok: false, reason: "denied" });
  await tick();
  assert.equal(
    h.invalidated.length,
    0,
    "a failed import ({ ok: false }) must not invalidate any queries",
  );
  // Thrown import error: swallowed silently, no invalidation, guard released.
  h.now = 600_000;
  h.foreground();
  await tick();
  assert.equal(h.importCalls.length, 3, "guard must release after a failed run");
  h.pending[2].reject(new Error("device exploded"));
  await tick();
  assert.equal(
    h.invalidated.length,
    0,
    "a thrown import error must be swallowed with no invalidation",
  );
  ok("no invalidation on ok:false or thrown errors; guard releases after each run");

  // --- cleanup: unmount removes the listener and mutes late successes -------
  h.now = 900_000;
  h.foreground();
  await tick();
  assert.equal(h.importCalls.length, 4);
  h.cleanup();
  assert.equal(h.removed, 1, "cleanup must remove the AppState listener");
  h.pending[3].resolve({ ok: true });
  await tick();
  assert.equal(
    h.invalidated.length,
    0,
    "a success that lands after unmount must not invalidate queries",
  );
  ok("cleanup removes the listener and late successes after unmount are muted");
}

// --- skip path: unchanged address book never refreshes, fingerprint kept ----
{
  const h = mount();
  await tick();
  assert.equal(h.importCalls.length, 1);
  // First sync succeeds and establishes the fingerprint.
  h.pending[0].resolve({ ok: true, imported: 5, fingerprint: "fp-1" });
  await tick();
  h.invalidated.length = 0;

  // Unchanged book: importDeviceContacts skipped the POST and reports
  // "unchanged" — no query invalidation, fingerprint retained.
  h.now = 61_000;
  h.foreground();
  await tick();
  assert.deepEqual(
    h.importCalls[1],
    { requestPermission: false, unchangedFingerprint: "fp-1" },
    "second run must offer the established fingerprint for skipping",
  );
  h.pending[1].resolve({ ok: false, reason: "unchanged", fingerprint: "fp-1" });
  await tick();
  assert.equal(
    h.invalidated.length,
    0,
    "an unchanged address book must not invalidate any queries",
  );
  ok("unchanged outcome causes no invalidation (POST skipped upstream)");

  // A failed run without a fingerprint must NOT clobber the remembered one.
  h.now = 122_000;
  h.foreground();
  await tick();
  h.pending[2].resolve({ ok: false, reason: "denied" });
  await tick();
  h.now = 183_000;
  h.foreground();
  await tick();
  assert.deepEqual(
    h.importCalls[3],
    { requestPermission: false, unchangedFingerprint: "fp-1" },
    "fingerprint must survive an outcome without a fingerprint (e.g. denied)",
  );
  // And a changed book (fresh fingerprint) rolls it forward.
  h.pending[3].resolve({ ok: true, imported: 6, fingerprint: "fp-2" });
  await tick();
  h.now = 244_000;
  h.foreground();
  await tick();
  assert.deepEqual(
    h.importCalls[4],
    { requestPermission: false, unchangedFingerprint: "fp-2" },
    "a changed book must roll the remembered fingerprint forward",
  );
  h.pending[4].resolve({ ok: false, reason: "unchanged", fingerprint: "fp-2" });
  await tick();
  h.cleanup();
  ok("fingerprint survives failures and rolls forward on a changed book");
}

// --- persisted seed: cold restart with an unchanged book skips the POST -----
{
  // A previous session persisted "fp-cold" for user 1; a fresh mount (fresh
  // refs = app restart) must seed the very FIRST import call with it so an
  // unchanged address book never even attempts the bulk POST.
  const h = mount({ userId: 1, stored: { 1: "fp-cold" } });
  await tick();
  assert.equal(h.importCalls.length, 1, "cold start must still run one import");
  assert.deepEqual(
    h.importCalls[0],
    { requestPermission: false, unchangedFingerprint: "fp-cold" },
    "the first import after a restart must carry the persisted fingerprint",
  );
  h.pending[0].resolve({ ok: false, reason: "unchanged", fingerprint: "fp-cold" });
  await tick();
  assert.equal(
    h.invalidated.length,
    0,
    "an unchanged book on cold start must not invalidate anything",
  );
  assert.equal(h.stored[1], "fp-cold", "unchanged outcome keeps the persisted copy");
  h.cleanup();
  ok("cold restart seeds the fingerprint from storage so unchanged books skip the POST");
}

// --- per-user: switching accounts never reuses the old user's fingerprint ---
{
  const stored = { 1: "fp-user1", 2: "fp-user2" };
  const h1 = mount({ userId: 1, stored });
  await tick();
  assert.deepEqual(
    h1.importCalls[0],
    { requestPermission: false, unchangedFingerprint: "fp-user1" },
    "user 1 must be seeded from user 1's persisted fingerprint",
  );
  h1.pending[0].resolve({ ok: false, reason: "unchanged", fingerprint: "fp-user1" });
  await tick();
  h1.cleanup();

  // Same component instance (same refs), new signed-in user: the in-memory
  // fingerprint and throttle reset, and the seed comes from user 2's slot.
  const h2 = mount({ userId: 2, stored, refs: h1.refs });
  await tick();
  assert.equal(
    h2.importCalls.length,
    1,
    "switching accounts must sync immediately (throttle reset on user change)",
  );
  assert.deepEqual(
    h2.importCalls[0],
    { requestPermission: false, unchangedFingerprint: "fp-user2" },
    "user 2 must never inherit user 1's fingerprint",
  );
  h2.pending[0].resolve({ ok: true, imported: 9, fingerprint: "fp-user2-new" });
  await tick();
  assert.equal(h2.stored[2], "fp-user2-new", "success persists into user 2's slot");
  assert.equal(h2.stored[1], "fp-user1", "user 1's persisted slot is untouched");
  h2.cleanup();
  ok("fingerprint is per-user: account switch resets, seeds and persists per id");
}

// --- no user id: hook stays inert (nothing to key the persistence on) -------
{
  const h = mount({ userId: null });
  await tick();
  assert.equal(h.importCalls.length, 0, "no userId must mean no sync at all");
  assert.equal(h.listeners.length, 0, "no userId must mean no AppState listener");
  ok("userId=null: hook is inert");
}

console.log(`\n[test-contact-auto-sync] all ${passed} checks passed`);
