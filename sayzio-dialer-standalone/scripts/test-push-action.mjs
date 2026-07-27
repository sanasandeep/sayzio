// Source-driven unit test for the standalone dialer's push-tap routing
// (lib/push.ts) — specifically the note-reminder deep-link added in
// Task #5554: tapping a fired note/to-do reminder must land on the Notes
// tab with the note's id so the screen can open its editor sheet (own
// notes) or highlight it in the list (shared notes), and the "Mark done"
// notification quick action must be wired end to end.
//
// Following the scripts/test-*.mjs convention we test the SHIPPED source:
// the pure helpers (parseNoteId + decidePushAction) are lifted out of
// lib/push.ts (types stripped) and evaluated in isolation, plus structural
// assertions pin the category/action wiring across push.ts and
// localReminders.ts.
//
// Run via `node scripts/test-push-action.mjs` (package script
// `test:push-action`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");
const pushSrc = readFileSync(join(root, "lib", "push.ts"), "utf8");
const remindersSrc = readFileSync(join(root, "lib", "localReminders.ts"), "utf8");
const notesScreenSrc = readFileSync(
  join(root, "app", "(tabs)", "notes.tsx"),
  "utf8",
);

// ---------------------------------------------------------------------------
// Lift parseNoteId + decidePushAction out of push.ts (the rest of the module
// imports expo-notifications / expo-router which we can't load here).
// ---------------------------------------------------------------------------
function lift(name, headerRe) {
  const re = new RegExp(`export function ${name}\\b[\\s\\S]*?\\n\\}\\n`, "m");
  const m = pushSrc.match(re);
  if (!m) throw new Error(`could not find ${name} in lib/push.ts`);
  return m[0].replace(headerRe, `function ${name}(data) {`);
}

// parseNoteId's header ends `): number | null {`; decidePushAction's return
// type is a multi-line object literal ending `} {` (union members are
// separated by `|`, so the lazy match can't trip early — same trick as the
// main app's scripts/test-push-action.mjs).
const parseNoteIdJs = lift(
  "parseNoteId",
  /export function parseNoteId[\s\S]*?\)\s*:\s*number \| null\s*\{/m,
);
const parseCallJs = lift(
  "parseCallRequestNumber",
  /export function parseCallRequestNumber[\s\S]*?\)\s*:\s*string \| null\s*\{/m,
);
const decideJs = lift(
  "decidePushAction",
  /export function decidePushAction[\s\S]*?\}\s*\{/m,
);

// eslint-disable-next-line no-new-func
const { parseNoteId, parseCallRequestNumber, decidePushAction } = new Function(
  `${parseNoteIdJs}\n${parseCallJs}\n${decideJs}\nreturn { parseNoteId, parseCallRequestNumber, decidePushAction };`,
)();

// 1. A note-due payload routes into the Notes tab, never the web fallback —
//    even when the server also stamped a `url`.
assert.deepEqual(
  decidePushAction({
    notification_id: 42,
    type: "dialer.note_due",
    note_id: 7,
    url: "/user/dialer/notes",
  }),
  { markReadId: 42, navigation: { kind: "note", noteId: 7 } },
  "note_due should deep-link in-app even when a url is present",
);

// 2. note_id arriving as a numeric string is coerced.
assert.deepEqual(
  decidePushAction({ type: "dialer.note_due", note_id: "12" }).navigation,
  { kind: "note", noteId: 12 },
  "string note_id should coerce to a number",
);

// 3. Unusable note_id falls through to the normal branches.
assert.deepEqual(
  decidePushAction({ type: "dialer.note_due", note_id: "abc" }).navigation,
  { kind: "route", path: "/(tabs)/dialer" },
  "bad note_id should fall back to the dialer home",
);
assert.equal(parseNoteId({ type: "dialer.note_due" }), null);
assert.equal(parseNoteId({ type: "dialer.note_due", note_id: 0 }), null);
assert.equal(parseNoteId({ type: "other", note_id: 5 }), null);
assert.equal(parseNoteId(undefined), null);

// 4. Non-note payloads keep their existing behavior.
assert.deepEqual(
  decidePushAction({ notification_id: 1, url: "https://1in.me/x" }),
  { markReadId: 1, navigation: { kind: "open", target: "https://1in.me/x" } },
  "url payloads without note_due still open the web target",
);
assert.deepEqual(
  decidePushAction({ notification_id: 7, type: "new_follower" }),
  { markReadId: 7, navigation: { kind: "route", path: "/(tabs)/dialer" } },
  "no url + non-note type should route to the dialer home",
);

// ---------------------------------------------------------------------------
// Structural wiring: "Mark done" quick action + Notes-screen deep link.
// ---------------------------------------------------------------------------

// Local note alarms carry the note-reminder category so the quick action
// shows on the notification.
assert.match(
  remindersSrc,
  /categoryIdentifier:\s*NOTE_REMINDER_CATEGORY/,
  "localReminders must stamp categoryIdentifier on scheduled note alarms",
);
assert.match(
  remindersSrc,
  /export const NOTE_REMINDER_CATEGORY/,
  "category constant must live in localReminders (avoids an import cycle)",
);

// push.ts registers the category with a Mark done action and handles the
// action by completing the note + cancelling its alarm, without navigating.
assert.match(
  pushSrc,
  /setNotificationCategoryAsync\(NOTE_REMINDER_CATEGORY/,
  "push.ts must register the note-reminder category",
);
assert.match(
  pushSrc,
  /actionIdentifier === NOTE_MARK_DONE_ACTION/,
  "the response listener must branch on the Mark done action",
);
assert.match(
  pushSrc,
  /updateNote\(noteId,\s*\{\s*done:\s*true\s*\}\)/,
  "Mark done must complete the note via the API",
);
assert.match(
  pushSrc,
  /\.then\(\(\)\s*=>\s*cancelNoteAlarm\(noteId\)\)/,
  "Mark done must drop the local alarm after completing the note",
);

// A tapped note reminder must navigate to the Notes tab with the note id.
assert.match(
  pushSrc,
  /pathname:\s*"\/\(tabs\)\/notes"/,
  "note navigation must target the Notes tab",
);

// The Notes screen must consume the param: own notes open the editor sheet,
// shared/foreign notes only highlight.
assert.match(
  notesScreenSrc,
  /useLocalSearchParams<\{\s*noteId\?/,
  "notes screen must read the noteId deep-link param",
);
assert.match(
  notesScreenSrc,
  /if \(own\) openEdit\(own\);/,
  "own notes must open the editor sheet directly",
);
assert.match(
  notesScreenSrc,
  /setHighlightId\(noteId\)/,
  "the deep-linked note must flash-highlight in the list",
);

console.log("test-push-action: all assertions passed");
