// Source-driven test for the identified-call drain (lib/callerId.ts →
// drainIdentifiedCalls), the path that turns rings the native call-screening
// service saw while the JS runtime was dead into structured call-history
// entries on the matched Sayzio contacts (POST /contacts/{id}/calls) and
// local "unknown caller" entries for numbers not in contacts.
//
// The drain runs silently on every foreground (via useContactAutoSync), so a
// regression here is invisible in manual testing: it could clear the native
// queue without persisting anything (calls silently lost after a phone
// restart or app update), stop matching numbers whose formatting differs
// from the stored contact, drop unknown callers, or wedge behind the
// in-flight guard forever.
//
// Coverage:
//   - queue parsing: garbage JSON, non-array payloads and malformed entries
//     (missing n / non-numeric ts) are dropped without touching the API,
//   - last-9-digit contact matching (formatting/country-prefix agnostic,
//     mirroring the native CallerIdStore.normalizeKey),
//   - one POST per contact regardless of how many calls queued up,
//   - unmatched events land in the local unknown-callers list (deduped by
//     number+ts) instead of being dropped,
//   - count-based queue clearing: clearIdentifiedCallQueue(drained) is called
//     with the number of valid events read, and ONLY after every event was
//     persisted somewhere,
//   - partial failure: a failed POST aborts the drain WITHOUT clearing the
//     queue so the events retry on the next foreground (server dedupes),
//   - in-flight guard: a second call while a drain is running returns 0
//     without touching the queue, and the guard releases afterwards,
//   - non-Android / missing native module → inert no-op.
//
// Run via `node scripts/test-identified-call-drain.mjs` (package script
// `test:identified-call-drain`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedStatements } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(join(__dirname, "..", "lib", "callerId.ts"), "utf8");
const hookSrc = readFileSync(
  join(__dirname, "..", "hooks", "useContactAutoSync.ts"),
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
console.log("[test-identified-call-drain] static source guards");

// The queue must be cleared by COUNT (events read), never unconditionally —
// events that arrive while a drain is in flight must survive to the next one.
assert.ok(
  /clearIdentifiedCallQueue\(drained\)/.test(src),
  "queue clearing must be count-based: clearIdentifiedCallQueue(drained)",
);
ok("queue clearing is count-based (clearIdentifiedCallQueue(drained))");

// The in-flight guard must release in a finally block so a thrown error can
// never wedge the drain off forever.
assert.ok(
  /\} finally \{\s*drainInFlight = false;/.test(src),
  "drainInFlight must be released in a finally block",
);
ok("in-flight guard released in finally (a failure can't wedge the drain)");

// Wiring: the drain must run on every contact auto-sync (foreground/mount)
// and refresh open contact views when something was logged.
assert.ok(
  /void drainIdentifiedCalls\(\)\.then\(\(logged\) => \{/.test(hookSrc),
  "useContactAutoSync must fire drainIdentifiedCalls on every run",
);
assert.ok(
  /logged > 0[\s\S]{0,120}queryKey: \["contacts"\][\s\S]{0,120}queryKey: \["contact"\]/.test(
    hookSrc,
  ),
  "a positive drain must invalidate the contacts + contact queries",
);
ok("drain wired into useContactAutoSync with contact-query refresh");

// ===========================================================================
// Lift the REAL drain (plus its helpers) and drive it against mocks.
// ===========================================================================
console.log("[test-identified-call-drain] behavioural checks on the lifted drain");

function loadDrainSource() {
  const start = src.indexOf("function phoneKey");
  assert.ok(start > -1, "could not find phoneKey in lib/callerId.ts");
  let body = src.slice(start);
  assert.ok(
    body.includes("export async function drainIdentifiedCalls"),
    "drainIdentifiedCalls must follow phoneKey in lib/callerId.ts",
  );
  // Strip the TypeScript annotations so the block evaluates as plain JS.
  // Every strip asserts it applied — fail loudly if the source changes shape.
  const strips = [
    [/function phoneKey\(number: string\): string/, "function phoneKey(number)"],
    [/export type UnknownCall = \{[\s\S]*?\};\n/, ""],
    [
      /async function readUnknownCalls\(\): Promise<UnknownCall\[\]>/,
      "async function readUnknownCalls()",
    ],
    [/\(e\): e is UnknownCall =>/, "(e) =>"],
    [
      /async function writeUnknownCalls\(calls: UnknownCall\[\]\): Promise<void>/,
      "async function writeUnknownCalls(calls)",
    ],
    [
      /export async function getUnknownCalls\(\): Promise<UnknownCall\[\]>/,
      "async function getUnknownCalls()",
    ],
    [
      /export async function dismissUnknownCall\(call: UnknownCall\): Promise<void>/,
      "async function dismissUnknownCall(call)",
    ],
    [
      /export async function dismissUnknownCallsForNumber\(\s*number: string,\s*\): Promise<void>/,
      "async function dismissUnknownCallsForNumber(number)",
    ],
    [
      /async function appendUnknownCalls\(\s*events: IdentifiedCallEvent\[\],\s*\): Promise<number>/,
      "async function appendUnknownCalls(events)",
    ],
    [
      /export async function drainIdentifiedCalls\(\): Promise<number>/,
      "async function drainIdentifiedCalls()",
    ],
    [/let events: IdentifiedCallEvent\[\] = \[\];/, "let events = [];"],
    [/\(e\): e is IdentifiedCallEvent =>/, "(e) =>"],
    [/new Map<string, Contact>\(\)/, "new Map()"],
    [
      /const pending = new Map<\s*number,\s*\{ calls: \{ number: string; occurred_at: string \}\[\] \}\s*>\(\);/,
      "const pending = new Map();",
    ],
    [/const unknown: IdentifiedCallEvent\[\] = \[\];/, "const unknown = [];"],
  ];
  for (const [pattern, replacement] of strips) {
    assert.ok(
      pattern.test(body),
      `expected drain source to match ${pattern} — update the strip step`,
    );
    body = body.replace(pattern, replacement);
  }
  assert.ok(
    !/: (string|number|boolean|Promise|IdentifiedCallEvent|Contact|UnknownCall)\b/.test(
      body,
    ),
    "unexpected extra type annotations in the drain source — update the strip step",
  );
  return body;
}
const drainSrc = loadDrainSource();

// Harness: evaluate the lifted drain with a controllable native module,
// contacts API and AsyncStorage. `scope` stays live (the extract proxy reads
// it per access), so tests can swap the queue / API behaviour between calls.
function mount({ platform = "android", native = true } = {}) {
  const state = {
    queueRaw: "[]",
    queueReads: 0,
    cleared: [], // counts passed to clearIdentifiedCallQueue
    contacts: [],
    listCalls: 0,
    listGate: null, // set to a promise to hold listContacts open (in-flight test)
    posts: [], // { id, calls }
    failPostFor: new Set(), // contact ids whose POST rejects
    storage: new Map(), // in-memory AsyncStorage (unknown-callers list)
  };
  const scope = {
    Platform: { OS: platform },
    ZioTelephony: native
      ? {
          getIdentifiedCallQueue: () => {
            state.queueReads += 1;
            return state.queueRaw;
          },
          clearIdentifiedCallQueue: (count) => {
            state.cleared.push(count);
          },
        }
      : null,
    AsyncStorage: {
      getItem: async (key) => state.storage.get(key) ?? null,
      setItem: async (key, value) => {
        state.storage.set(key, value);
      },
    },
    listContacts: async () => {
      state.listCalls += 1;
      if (state.listGate) await state.listGate;
      return { items: state.contacts };
    },
    logContactCalls: async (id, calls) => {
      if (state.failPostFor.has(id)) throw new Error("POST 500");
      state.posts.push({ id, calls });
    },
  };
  state.drain = runExtractedStatements(
    drainSrc,
    "drainIdentifiedCalls",
    scope,
    "drainIdentifiedCalls",
    { test: "test-identified-call-drain" },
  );
  state.unknownCalls = () =>
    JSON.parse(state.storage.get("zio_unknown_calls_v1") ?? "[]");
  return state;
}

// --- unsupported platforms are inert ----------------------------------------
{
  const ios = mount({ platform: "ios" });
  assert.equal(await ios.drain(), 0, "iOS must be a no-op");
  assert.equal(ios.queueReads, 0, "iOS must never read the native queue");
  const noNative = mount({ native: false });
  assert.equal(await noNative.drain(), 0, "missing native module must be a no-op");
  assert.equal(noNative.listCalls, 0, "no native module → no API traffic");
  ok("non-Android / missing native module: fully inert");
}

// --- queue parsing: garbage and malformed entries ----------------------------
{
  const h = mount();
  h.queueRaw = "not json {";
  assert.equal(await h.drain(), 0, "garbage JSON must yield 0");
  h.queueRaw = JSON.stringify({ nope: true });
  assert.equal(await h.drain(), 0, "non-array payload must yield 0");
  h.queueRaw = "";
  assert.equal(await h.drain(), 0, "empty raw string must yield 0");
  assert.equal(h.listCalls, 0, "unparseable queues must never hit the API");
  assert.deepEqual(h.cleared, [], "unparseable queues must never clear");
  // Malformed entries are filtered; with nothing valid left, no clear either.
  h.queueRaw = JSON.stringify([
    null,
    { name: "No number" },
    { n: 123, ts: 1 },
    { n: "+15550001111", ts: "yesterday" },
  ]);
  assert.equal(await h.drain(), 0, "all-malformed queue must yield 0");
  assert.deepEqual(h.cleared, [], "an all-malformed queue must not clear");
  ok("garbage JSON / non-arrays / malformed entries: dropped, no API, no clear");
}

// --- happy path: match, one POST per contact, unknowns kept, count clear -----
{
  const h = mount();
  h.contacts = [
    { id: 7, phones: [{ value_e164: "+15550001111", value: null }] },
    { id: 9, phones: [{ value_e164: null, value: "0987 654 3210" }] },
  ];
  const t1 = Date.UTC(2026, 6, 20, 10, 30);
  const t2 = Date.UTC(2026, 6, 20, 11, 0);
  const t3 = Date.UTC(2026, 6, 20, 12, 0);
  h.queueRaw = JSON.stringify([
    // Different formatting than the stored contact — last-9-digit match.
    { n: "(555) 000-1111", name: "VIP", ts: t1 },
    { n: "+1 555 000 1111", ts: t2 },
    // Country-prefixed vs local-0 form of the second contact's number.
    { n: "+91 98765 43210", ts: t3 },
    { n: "+15559999999", ts: t3 }, // no matching contact — kept as unknown
    { bad: true }, // malformed — filtered before counting
  ]);
  const logged = await h.drain();
  assert.equal(logged, 4, "3 matched + 1 unknown events must be persisted");
  assert.equal(h.posts.length, 2, "one POST per contact, however many calls");
  const p7 = h.posts.find((p) => p.id === 7);
  assert.deepEqual(
    p7.calls,
    [
      { number: "(555) 000-1111", occurred_at: new Date(t1).toISOString() },
      { number: "+1 555 000 1111", occurred_at: new Date(t2).toISOString() },
    ],
    "matched rings must batch into one structured POST per contact",
  );
  const p9 = h.posts.find((p) => p.id === 9);
  assert.deepEqual(
    p9.calls,
    [{ number: "+91 98765 43210", occurred_at: new Date(t3).toISOString() }],
    "last-9-digit match works across country-prefix formatting",
  );
  assert.deepEqual(
    h.unknownCalls(),
    [{ number: "+15559999999", ts: t3 }],
    "the unmatched event must land in the local unknown-callers list",
  );
  assert.deepEqual(
    h.cleared,
    [4],
    "clear must be count-based on the 4 VALID events read (malformed excluded)",
  );
  ok("last-9-digit matching, per-contact batched POST, unknowns kept, count clear");

  // --- replay: matched events re-POST (server dedupes), unknowns dedupe -----
  h.queueRaw = JSON.stringify([
    { n: "(555) 000-1111", ts: t1 },
    { n: "+15559999999", ts: t3 }, // same unknown ring again
  ]);
  h.posts.length = 0;
  assert.equal(
    await h.drain(),
    1,
    "a replay logs only the re-POSTed matched event (unknown dedupes locally)",
  );
  assert.equal(
    h.posts.length,
    1,
    "matched replays still POST — the server dedupes on (contact, number, occurred_at)",
  );
  assert.deepEqual(
    h.unknownCalls(),
    [{ number: "+15559999999", ts: t3 }],
    "unknown callers dedupe on (number, ts) — no duplicate entry",
  );
  assert.deepEqual(
    h.cleared,
    [4, 2],
    "the queue still clears — every event IS persisted somewhere",
  );
  ok("replay: matched events re-POST for server dedupe, unknowns dedupe locally");
}

// --- partial failure: queue must NOT clear when a POST fails ------------------
{
  const h = mount();
  h.contacts = [
    { id: 1, phones: [{ value_e164: "+15550000001", value: null }] },
    { id: 2, phones: [{ value_e164: "+15550000002", value: null }] },
  ];
  h.failPostFor.add(2);
  const ts = Date.UTC(2026, 6, 21, 9, 0);
  h.queueRaw = JSON.stringify([
    { n: "+15550000001", ts },
    { n: "+15550000002", ts },
  ]);
  assert.equal(await h.drain(), 0, "a failed POST must abort with 0");
  assert.deepEqual(
    h.cleared,
    [],
    "the queue must NOT clear when any POST fails — events retry next foreground",
  );
  // Retry after the API recovers: everything lands (the server dedupes the
  // replayed contact-1 call) and only then does the queue clear.
  h.failPostFor.clear();
  assert.equal(await h.drain(), 2, "the retry must persist both events");
  assert.deepEqual(h.cleared, [2], "the retry clears the full drained count");
  ok("partial failure keeps the queue; retry lands everything and then clears");
}

// --- in-flight guard ----------------------------------------------------------
{
  const h = mount();
  h.contacts = [
    { id: 3, phones: [{ value_e164: "+15550000003", value: null }] },
  ];
  h.queueRaw = JSON.stringify([{ n: "+15550000003", ts: 1_000 }]);
  let release;
  h.listGate = new Promise((r) => {
    release = r;
  });
  const first = h.drain();
  await new Promise((r) => setImmediate(r));
  assert.equal(
    await h.drain(),
    0,
    "a drain while one is in flight must bail out immediately",
  );
  assert.equal(h.queueReads, 1, "the concurrent call must not re-read the queue");
  release();
  assert.equal(await first, 1, "the original drain still completes");
  // Guard released: a later drain runs again.
  h.listGate = null;
  await h.drain();
  assert.equal(h.queueReads, 2, "the guard must release after the drain settles");
  ok("in-flight guard: concurrent calls bail, guard releases afterwards");
}

console.log(`\n[test-identified-call-drain] all ${passed} checks passed`);
