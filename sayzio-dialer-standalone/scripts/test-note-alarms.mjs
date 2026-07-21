// Source-driven test for local note alarms (lib/localReminders.ts), locking
// in the invariants that keep an edited/shared note from ever leaving a
// stale alarm on a device:
//
//   - syncNoteAlarm ALWAYS cancels the existing alarm first (cancel-before-
//     schedule), so a changed remind_at replaces rather than duplicates,
//   - past / invalid / null remind_at values cancel and never re-schedule,
//   - denied notification permission still cancels but never schedules,
//   - identifiers are always dialer-note-{id} (per-note replacement key),
//   - rearmNoteAlarms skips done notes, null remind_at, and (via
//     syncNoteAlarm) past-due reminders; it only ever touches res.notes
//     (owned), never res.shared,
//   - rearmNoteAlarms is a no-op on web/simulators and swallows API errors.
//
// Static guards additionally pin the wiring: the Notes screen's load() loop
// syncs-or-cancels every owned note, and the tab layout re-arms once per
// signed-in launch.
//
// Run via `node scripts/test-note-alarms.mjs` (package script
// `test:note-alarms`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedStatements } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");
const src = readFileSync(join(root, "lib", "localReminders.ts"), "utf8");
const notesSrc = readFileSync(join(root, "app", "(tabs)", "notes.tsx"), "utf8");
const layoutSrc = readFileSync(
  join(root, "app", "(tabs)", "_layout.tsx"),
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
console.log("[test-note-alarms] static source guards");

// The identifier must be keyed by note id so re-scheduling REPLACES the old
// alarm instead of stacking a second one with stale content.
assert.ok(
  /const noteReminderId = \(noteId: number\) => `dialer-note-\$\{noteId\}`;/.test(
    src,
  ),
  "alarm identifiers must be keyed dialer-note-{id}",
);
ok("identifier is always dialer-note-{id}");

// Cancel must come BEFORE any early return in syncNoteAlarm — even a null /
// past remind_at must drop the previously-scheduled alarm.
const syncStart = src.indexOf("export async function syncNoteAlarm");
const cancelIdx = src.indexOf("cancelScheduledNotificationAsync", syncStart);
const firstReturnIdx = src.indexOf("if (!remindAtIso) return;", syncStart);
assert.ok(
  syncStart > -1 && cancelIdx > -1 && firstReturnIdx > -1 && cancelIdx < firstReturnIdx,
  "syncNoteAlarm must cancel before its first early return",
);
ok("syncNoteAlarm cancels before any early return (source order)");

// Wiring: the Notes screen's load() loop must sync-or-cancel every owned
// note, and the tab layout must re-arm on signed-in launch.
assert.ok(
  /for \(const n of res\.notes\) \{\s*if \(n\.remind_at && !n\.done\) \{\s*void syncNoteAlarm\(n\.id, n\.remind_at, noteAlarmTitle\(n\), n\.body\);\s*\} else \{\s*void cancelNoteAlarm\(n\.id\);/.test(
    notesSrc,
  ),
  "notes.tsx load() must sync future/open reminders and cancel the rest",
);
assert.ok(
  !/res\.shared[\s\S]{0,200}(syncNoteAlarm|cancelNoteAlarm)/.test(notesSrc),
  "the alarm loop must only touch res.notes (owned), never res.shared",
);
assert.ok(
  /if \(!signedIn\) return;\s*void rearmNoteAlarmsOnLaunch\(\);/.test(layoutSrc),
  "_layout.tsx must re-arm note alarms once per signed-in launch",
);
ok("wiring: notes.tsx sync/cancel loop over owned notes + launch re-arm");

// rearmNoteAlarms must read res.notes only — shared notes never get local
// alarms, so they can never go stale locally.
assert.ok(
  /const \{ notes \} = await listNotes\(\);/.test(src),
  "rearmNoteAlarms must destructure only `notes` from listNotes()",
);
assert.ok(
  !/shared/.test(src),
  "localReminders.ts must never touch shared notes",
);
ok("rearmNoteAlarms only ever re-arms owned notes");

// ===========================================================================
// Lift the REAL module and drive it against mocks.
// ===========================================================================
console.log("[test-note-alarms] behavioural checks on the lifted module");

function loadModuleSource() {
  const start = src.indexOf("const noteReminderId");
  assert.ok(start > -1, "could not find noteReminderId in lib/localReminders.ts");
  let body = src.slice(start);
  // Strip the TypeScript annotations so the block evaluates as plain JS.
  // Every strip asserts it applied — fail loudly if the source changes shape.
  const strips = [
    [
      /const noteReminderId = \(noteId: number\) =>/,
      "const noteReminderId = (noteId) =>",
    ],
    [
      /async function canSchedule\(\): Promise<boolean>/,
      "async function canSchedule()",
    ],
    [
      /let status = \(current as \{ status\?: string \}\)\.status;/,
      "let status = current.status;",
    ],
    [
      /status = \(requested as \{ status\?: string \}\)\.status;/,
      "status = requested.status;",
    ],
    [
      /export async function syncNoteAlarm\(\s*noteId: number,\s*remindAtIso: string \| null,\s*title: string,\s*body: string \| null,\s*\): Promise<void>/,
      "async function syncNoteAlarm(noteId, remindAtIso, title, body)",
    ],
    [
      /export async function rearmNoteAlarms\(\): Promise<void>/,
      "async function rearmNoteAlarms()",
    ],
    [
      /export async function rearmNoteAlarmsOnLaunch\(\): Promise<void>/,
      "async function rearmNoteAlarmsOnLaunch()",
    ],
    [
      /export async function rearmNoteAlarmsOnForeground\(\): Promise<void>/,
      "async function rearmNoteAlarmsOnForeground()",
    ],
    [
      /export async function cancelNoteAlarm\(noteId: number\): Promise<void>/,
      "async function cancelNoteAlarm(noteId)",
    ],
  ];
  for (const [pattern, replacement] of strips) {
    assert.ok(
      pattern.test(body),
      `expected localReminders source to match ${pattern} — update the strip step`,
    );
    body = body.replace(pattern, replacement);
  }
  assert.ok(
    !/: (string|number|boolean|Promise|void)\b/.test(body),
    "unexpected extra type annotations in localReminders — update the strip step",
  );
  return body;
}
const moduleSrc = loadModuleSource();

// Harness: evaluate the lifted module with a controllable Notifications /
// Device / Platform / listNotes. `state` records every native call in order
// so cancel-before-schedule ordering is directly assertable.
function mount({
  platform = "android",
  isDevice = true,
  permission = "granted",
  notes = [],
  listNotesError = null,
} = {}) {
  const state = { ops: [], listCalls: 0, permissionRequests: 0 };
  const scope = {
    Platform: { OS: platform },
    Device: { isDevice },
    Notifications: {
      getPermissionsAsync: async () => ({ status: permission }),
      requestPermissionsAsync: async () => {
        state.permissionRequests += 1;
        return { status: permission };
      },
      cancelScheduledNotificationAsync: async (id) => {
        state.ops.push({ op: "cancel", id });
      },
      scheduleNotificationAsync: async ({ identifier, content, trigger }) => {
        state.ops.push({ op: "schedule", id: identifier, content, trigger });
      },
      SchedulableTriggerInputTypes: { DATE: "date" },
    },
    listNotes: async () => {
      state.listCalls += 1;
      if (listNotesError) throw listNotesError;
      return { notes, shared: [{ id: 999, remind_at: FUTURE, done: false }] };
    },
  };
  const api = runExtractedStatements(
    moduleSrc,
    "({ syncNoteAlarm, rearmNoteAlarms, cancelNoteAlarm })",
    scope,
    "localReminders",
    { test: "test-note-alarms" },
  );
  return { state, ...api };
}

const FUTURE = new Date(Date.now() + 60 * 60 * 1000).toISOString();
const PAST = new Date(Date.now() - 60 * 60 * 1000).toISOString();

// --- syncNoteAlarm: cancel-before-schedule ------------------------------------
{
  const h = mount();
  await h.syncNoteAlarm(42, FUTURE, "Title", "Body");
  assert.deepEqual(
    h.state.ops.map((o) => o.op),
    ["cancel", "schedule"],
    "sync must cancel the old alarm BEFORE scheduling the new one",
  );
  assert.equal(h.state.ops[0].id, "dialer-note-42");
  const sched = h.state.ops[1];
  assert.equal(sched.id, "dialer-note-42", "schedule reuses the same identifier");
  assert.equal(sched.content.title, "Title");
  assert.equal(sched.content.body, "Body");
  assert.deepEqual(sched.content.data, { type: "dialer.note_due", note_id: 42 });
  assert.equal(sched.trigger.type, "date");
  assert.equal(sched.trigger.date.toISOString(), FUTURE);
  ok("syncNoteAlarm cancels first, then schedules under dialer-note-{id}");
}

// --- syncNoteAlarm: past / invalid / null times cancel and never schedule ----
{
  for (const [label, when] of [
    ["past remind_at", PAST],
    ["exactly-now remind_at", new Date().toISOString()],
    ["invalid remind_at", "not-a-date"],
    ["null remind_at", null],
  ]) {
    const h = mount();
    await h.syncNoteAlarm(7, when, "T", null);
    assert.deepEqual(
      h.state.ops,
      [{ op: "cancel", id: "dialer-note-7" }],
      `${label} must cancel the stale alarm and schedule nothing`,
    );
  }
  ok("past/now/invalid/null times: stale alarm cancelled, nothing scheduled");
}

// --- syncNoteAlarm: permission denied still cancels, never schedules ---------
{
  const h = mount({ permission: "denied" });
  await h.syncNoteAlarm(5, FUTURE, "T", null);
  assert.deepEqual(
    h.state.ops,
    [{ op: "cancel", id: "dialer-note-5" }],
    "denied permission must still cancel but never schedule",
  );
  assert.equal(h.state.permissionRequests, 1, "a denied status is re-requested once");
  ok("denied notification permission: cancel-only");
}

// --- cancelNoteAlarm ----------------------------------------------------------
{
  const h = mount();
  await h.cancelNoteAlarm(11);
  assert.deepEqual(h.state.ops, [{ op: "cancel", id: "dialer-note-11" }]);
  ok("cancelNoteAlarm drops exactly dialer-note-{id}");
}

// --- rearmNoteAlarms filtering -------------------------------------------------
{
  const notes = [
    { id: 1, done: false, remind_at: FUTURE, title: "Keep me", body: "b", kind: "note" },
    { id: 2, done: true, remind_at: FUTURE, title: "Done", body: null, kind: "note" },
    { id: 3, done: false, remind_at: null, title: "No reminder", body: null, kind: "note" },
    { id: 4, done: false, remind_at: PAST, title: "Overdue", body: null, kind: "note" },
    { id: 5, done: false, remind_at: FUTURE, title: "", body: null, kind: "checklist" },
  ];
  const h = mount({ notes });
  await h.rearmNoteAlarms();
  const scheduled = h.state.ops.filter((o) => o.op === "schedule");
  assert.deepEqual(
    scheduled.map((o) => o.id).sort(),
    ["dialer-note-1", "dialer-note-5"],
    "only open notes with a FUTURE remind_at may be (re)scheduled",
  );
  // done (2) and null remind_at (3) are skipped entirely — not even a cancel
  // touch; the past-due note (4) flows through syncNoteAlarm, which cancels
  // its stale alarm but never re-schedules it.
  const touched = new Set(h.state.ops.map((o) => o.id));
  assert.ok(!touched.has("dialer-note-2"), "done notes are skipped entirely");
  assert.ok(!touched.has("dialer-note-3"), "null remind_at notes are skipped entirely");
  assert.deepEqual(
    h.state.ops.filter((o) => o.id === "dialer-note-4"),
    [{ op: "cancel", id: "dialer-note-4" }],
    "past-due notes get their stale alarm cancelled, never re-armed",
  );
  assert.ok(
    !touched.has("dialer-note-999"),
    "shared notes must never gain local alarms",
  );
  // Checklist fallback title (empty title → kind-based default).
  assert.equal(
    scheduled.find((o) => o.id === "dialer-note-5").content.title,
    "To-do reminder",
    "untitled checklist notes fall back to the checklist title",
  );
  ok("rearm filtering: done / null / past skipped, shared untouched, titles fall back");
}

// --- rearmNoteAlarms: no-op on web / simulators, swallows API errors ---------
{
  const web = mount({ platform: "web" });
  await web.rearmNoteAlarms();
  assert.equal(web.state.listCalls, 0, "web must never hit the API");
  assert.deepEqual(web.state.ops, [], "web must never touch notifications");

  const sim = mount({ isDevice: false });
  await sim.rearmNoteAlarms();
  assert.equal(sim.state.listCalls, 0, "simulators must be a no-op");

  const offline = mount({ listNotesError: new Error("network down") });
  await offline.rearmNoteAlarms(); // must not throw
  assert.deepEqual(offline.state.ops, [], "an offline launch schedules nothing");
  ok("web/simulator no-op; offline/signed-out launch never throws");
}

console.log(`\n[test-note-alarms] all ${passed} checks passed`);
