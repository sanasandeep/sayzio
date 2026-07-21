// Source-driven test for the Dialer's silent contacts auto-sync
// (hooks/useContactAutoSync.ts, mounted in app/(tabs)/_layout.tsx).
//
// The hook imports the device address book on app open (prompting for the
// contacts permission the FIRST time only), re-imports on every foreground
// resume WITHOUT prompting, and triggers the account's Google Contacts sync
// on each run. Because it runs with no UI at all, a regression here is
// invisible in manual testing: it could start re-prompting on every resume,
// hammer the device/API (throttle or single-flight guard lost), stop
// refreshing after real changes, or forget the persisted fingerprint so
// every cold start re-uploads the whole address book.
//
// Adapted from the main app's harness
// (artifacts/1inme-mobile/scripts/test-contact-auto-sync.mjs); the standalone
// differences covered here:
//   - the MOUNT sync passes requestPermission: true (first-open prompt) while
//     foreground resumes pass requestPermission: false (never re-prompt),
//   - every run also triggers googleContacts.sync(), whose failures are
//     swallowed silently and whose success refreshes the contacts list even
//     when the device import found nothing new,
//   - only the ["contacts"] query is invalidated (the standalone's
//     duplicate-count screen invalidates its own key on demand).
//
// Shared behavior asserted like the main app's harness:
//   - 60s throttle, single-flight guard, only 'active' AppState transitions,
//   - no invalidation on { ok: false } or thrown errors (when Google sync
//     also fails), none after cleanup (unmount),
//   - fingerprint passed as unchangedFingerprint, remembered from successful
//     AND "unchanged" outcomes, survives fingerprint-less failures,
//   - persisted per user: cold restart seeds from storage, account switch
//     resets the seed/throttle and never reuses the old user's fingerprint,
//   - enabled=false and userId=null keep the hook fully inert,
//   - no Alert anywhere (fully silent).
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
  join(__dirname, "..", "app", "(tabs)", "_layout.tsx"),
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

// First-open prompt vs silent resumes: exactly one runSync(true) (the mount
// path) and the AppState listener must resume with runSync(false).
const promptRuns = hookSrc.match(/runSync\(true\)/g) ?? [];
assert.equal(
  promptRuns.length,
  1,
  "exactly one runSync(true) — only the mount sync may prompt for permission",
);
assert.ok(
  /state === "active"\) void runSync\(false\)/.test(hookSrc),
  "foreground resumes must run silently (runSync(false)) — never re-prompt",
);
ok("mount sync may prompt once; foreground resumes never re-prompt");

// The permission flag flows straight through to importDeviceContacts.
assert.ok(
  /importDeviceContacts\(\{\s*requestPermission,/.test(hookSrc),
  "importDeviceContacts must receive the runSync requestPermission flag",
);
ok("requestPermission flag flows into importDeviceContacts");

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

// Google Contacts sync trigger is part of every run.
assert.ok(
  /await googleContacts\.sync\(\)/.test(hookSrc),
  "each run must trigger the account's Google Contacts sync",
);
ok("Google Contacts sync triggered on every run");

// Tabs layout wiring: gated on a signed-in session, keyed per user id.
assert.ok(
  /import \{ useContactAutoSync \} from "@\/hooks\/useContactAutoSync"/.test(
    layoutSrc,
  ),
  "app/(tabs)/_layout.tsx must import the useContactAutoSync hook",
);
assert.ok(
  /useContactAutoSync\(ready && !!user, user\?\.id \?\? null\)/.test(layoutSrc),
  "tabs layout must enable the sync only when auth is ready AND signed in, keyed per user id",
);
ok("tabs layout mounts the hook gated on ready && !!user, keyed by user id");

// ===========================================================================
// Lift the REAL effect body and drive it against mocks.
// ===========================================================================
console.log("[test-contact-auto-sync] behavioural checks on the lifted effect");

function loadEffectBody() {
  const m = hookSrc.match(
    /useEffect\((\(\) => \{[\s\S]*?\n {2}\}), \[enabled, qc, userId\]\);/,
  );
  assert.ok(m, "could not find the useEffect body in useContactAutoSync.ts");
  // The body is TypeScript; strip the one type annotation so it evaluates
  // as plain JS (fail loudly if the signature changes).
  const body = m[1].replace(
    "async (requestPermission: boolean) =>",
    "async (requestPermission) =>",
  );
  assert.ok(
    !body.includes(": boolean"),
    "unexpected extra type annotations in the effect body — update the strip step",
  );
  return body;
}
const effectBody = loadEffectBody();

// Harness: mount the lifted effect with a controllable clock, AppState,
// import outcome and Google-sync outcome. Returns handles to advance time,
// fire foreground events, resolve pending calls, and inspect invalidations.
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
    // Each import / google-sync call pushes a deferred; tests resolve them.
    pending: [],
    gPending: [],
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
    googleContacts: {
      sync: () =>
        new Promise((resolve, reject) => {
          state.gPending.push({ resolve, reject });
        }),
    },
    // Android-only fire-and-forget helpers — noop here; the caller-ID sync
    // and identified-call drain have their own dedicated test harnesses.
    flushPendingSpamReports: async () => {
      state.flushCalls = (state.flushCalls ?? 0) + 1;
    },
    syncCallerDirectory: async () => {},
    drainIdentifiedCalls: async () => 0,
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
  // Finish a run: settle the import, then settle the follow-up Google sync.
  state.finishRun = async (importOutcome, { google = "fail" } = {}) => {
    const d = state.pending.shift();
    if (importOutcome instanceof Error) d.reject(importOutcome);
    else d.resolve(importOutcome);
    await tick();
    const g = state.gPending.shift();
    assert.ok(g, "every run must trigger googleContacts.sync()");
    if (google === "ok") g.resolve({});
    else g.reject(new Error("no google account connected"));
    await tick();
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

// --- no user id: hook stays inert (nothing to key the persistence on) -------
{
  const h = mount({ userId: null });
  await tick();
  assert.equal(h.importCalls.length, 0, "no userId must mean no sync at all");
  assert.equal(h.listeners.length, 0, "no userId must mean no AppState listener");
  ok("userId=null: hook is inert");
}

// --- happy path: mount prompt-sync + invalidation on success ----------------
{
  const h = mount();
  await tick(); // the mount sync first awaits the persisted-fingerprint read
  assert.equal(h.importCalls.length, 1, "must sync once on mount");
  assert.deepEqual(
    h.importCalls[0],
    { requestPermission: true, unchangedFingerprint: null },
    "the mount sync may prompt (first open) and has no fingerprint yet",
  );
  await h.finishRun({ ok: true, imported: 3, fingerprint: "fp-a" });
  assert.deepEqual(
    h.invalidated,
    [["contacts"]],
    "a successful import must invalidate exactly the contacts query",
  );
  assert.equal(
    h.stored[1],
    "fp-a",
    "a successful import must persist the fingerprint for this user",
  );
  ok("mount sync prompts once, invalidates contacts and persists the fingerprint");

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

  // --- after the minute a resume re-syncs, silently -------------------------
  h.foreground();
  await tick();
  assert.equal(h.importCalls.length, 2, "resume after 60s must re-sync");
  assert.deepEqual(
    h.importCalls[1],
    { requestPermission: false, unchangedFingerprint: "fp-a" },
    "foreground resumes must never prompt and must carry the remembered fingerprint",
  );
  ok("resume never re-prompts and passes the remembered fingerprint");

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

  // --- failure paths (import fails, Google sync also fails) -----------------
  h.invalidated.length = 0;
  await h.finishRun({ ok: false, reason: "denied" });
  assert.equal(
    h.invalidated.length,
    0,
    "a failed import ({ ok: false }) with no Google sync must not invalidate",
  );
  // Thrown import error: swallowed silently, no invalidation, guard released.
  h.now = 600_000;
  h.foreground();
  await tick();
  assert.equal(h.importCalls.length, 3, "guard must release after a failed run");
  await h.finishRun(new Error("device exploded"));
  assert.equal(
    h.invalidated.length,
    0,
    "a thrown import error must be swallowed with no invalidation",
  );
  ok("no invalidation when import and Google sync both fail; guard releases");

  // --- Google sync success refreshes even when the import found nothing -----
  h.now = 660_000;
  h.foreground();
  await tick();
  await h.finishRun({ ok: false, reason: "unchanged", fingerprint: "fp-a" }, { google: "ok" });
  assert.deepEqual(
    h.invalidated,
    [["contacts"]],
    "a successful Google Contacts sync must refresh the contacts list",
  );
  ok("Google sync success refreshes contacts even with an unchanged device book");

  // --- cleanup: unmount removes the listener and mutes late successes -------
  h.invalidated.length = 0;
  h.now = 900_000;
  h.foreground();
  await tick();
  assert.equal(h.importCalls.length, 5);
  h.cleanup();
  assert.equal(h.removed, 1, "cleanup must remove the AppState listener");
  await h.finishRun({ ok: true, fingerprint: "fp-late" }, { google: "ok" });
  assert.equal(
    h.invalidated.length,
    0,
    "a success that lands after unmount must not invalidate queries",
  );
  ok("cleanup removes the listener and late successes after unmount are muted");
}

// --- skip path: unchanged address book keeps the fingerprint ----------------
{
  const h = mount();
  await tick();
  assert.equal(h.importCalls.length, 1);
  // First sync succeeds and establishes the fingerprint.
  await h.finishRun({ ok: true, imported: 5, fingerprint: "fp-1" });
  h.invalidated.length = 0;

  // Unchanged book, no Google account: nothing refreshes, fingerprint kept.
  h.now = 61_000;
  h.foreground();
  await tick();
  assert.deepEqual(
    h.importCalls[1],
    { requestPermission: false, unchangedFingerprint: "fp-1" },
    "second run must offer the established fingerprint for skipping",
  );
  await h.finishRun({ ok: false, reason: "unchanged", fingerprint: "fp-1" });
  assert.equal(
    h.invalidated.length,
    0,
    "an unchanged address book (and failed Google sync) must not invalidate",
  );
  ok("unchanged outcome causes no invalidation (POST skipped upstream)");

  // A failed run without a fingerprint must NOT clobber the remembered one.
  h.now = 122_000;
  h.foreground();
  await tick();
  await h.finishRun({ ok: false, reason: "denied" });
  h.now = 183_000;
  h.foreground();
  await tick();
  assert.deepEqual(
    h.importCalls[3],
    { requestPermission: false, unchangedFingerprint: "fp-1" },
    "fingerprint must survive an outcome without a fingerprint (e.g. denied)",
  );
  // And a changed book (fresh fingerprint) rolls it forward.
  await h.finishRun({ ok: true, imported: 6, fingerprint: "fp-2" });
  h.now = 244_000;
  h.foreground();
  await tick();
  assert.deepEqual(
    h.importCalls[4],
    { requestPermission: false, unchangedFingerprint: "fp-2" },
    "a changed book must roll the remembered fingerprint forward",
  );
  await h.finishRun({ ok: false, reason: "unchanged", fingerprint: "fp-2" });
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
    { requestPermission: true, unchangedFingerprint: "fp-cold" },
    "the first import after a restart must carry the persisted fingerprint",
  );
  await h.finishRun({ ok: false, reason: "unchanged", fingerprint: "fp-cold" });
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
    { requestPermission: true, unchangedFingerprint: "fp-user1" },
    "user 1 must be seeded from user 1's persisted fingerprint",
  );
  await h1.finishRun({ ok: false, reason: "unchanged", fingerprint: "fp-user1" });
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
    { requestPermission: true, unchangedFingerprint: "fp-user2" },
    "user 2 must never inherit user 1's fingerprint",
  );
  await h2.finishRun({ ok: true, imported: 9, fingerprint: "fp-user2-new" });
  assert.equal(h2.stored[2], "fp-user2-new", "success persists into user 2's slot");
  assert.equal(h2.stored[1], "fp-user1", "user 1's persisted slot is untouched");
  h2.cleanup();
  ok("fingerprint is per-user: account switch resets, seeds and persists per id");
}

console.log(`\n[test-contact-auto-sync] all ${passed} checks passed`);
