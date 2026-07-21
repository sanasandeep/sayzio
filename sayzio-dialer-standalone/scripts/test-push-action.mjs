// Smoke test for decidePushAction — the pure helper the push response
// listener uses to decide (a) which in-app row to mark read and (b) where
// to send the user when they tap a push.
//
// The standalone dialer's local note reminders (lib/localReminders.ts)
// carry `type: "dialer.note_due"` + `note_id`; a tap must land on the
// Notes tab deep-linked to that note. Server pushes stamp a `url` and
// deep-link like the in-app row; anything else falls back to the dialer
// home. A rename of the data key or route would otherwise only surface
// on-device — this harness catches it in CI.
//
// Run via `node scripts/test-push-action.mjs` (also wired into the package
// script `test:push-action`). Mirrors the main app's
// artifacts/1inme-mobile/scripts/test-push-action.mjs: the helper is pure,
// so we extract its body from lib/push.ts and evaluate it in isolation
// (the rest of the module imports expo-notifications / expo-router which
// we can't load here).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(join(__dirname, "..", "lib", "push.ts"), "utf8");

// Pull just the decidePushAction body out of push.ts. Capture the whole
// function: up to a `}` alone on its own line followed by a newline (the
// body close). The multi-line return type ends in `} {` (not `}\n`), so
// it won't trip the lazy match early.
const re = /export function decidePushAction\b[\s\S]*?\n\}\n/m;
const m = src.match(re);
if (!m) throw new Error("could not find decidePushAction in push.ts");

// Strip the TS type annotations so the body is valid plain JS. The
// signature (params + multi-line return type) runs up to the body's
// opening brace — replace the whole thing with a plain JS header.
const js = m[0]
  .replace(/export function decidePushAction[\s\S]*?\}\s*\{/m, "function decidePushAction(data) {")
  .replace(/:\s*string\b/g, "");

// eslint-disable-next-line no-new-func
const { decidePushAction } = new Function(
  `${js}; return { decidePushAction };`,
)();

// 1. dialer.note_due with a numeric note_id → deep-link to that note on
// the Notes tab. Local reminders carry no notification_id → no mark-read.
assert.deepEqual(
  decidePushAction({ type: "dialer.note_due", note_id: 12 }),
  {
    markReadId: null,
    navigation: { kind: "route", path: "/(tabs)/notes?noteId=12" },
  },
  "note_due with numeric note_id should route to the specific note",
);

// 2. note_id arriving as a numeric string is coerced.
assert.deepEqual(
  decidePushAction({ type: "dialer.note_due", note_id: "34" }),
  {
    markReadId: null,
    navigation: { kind: "route", path: "/(tabs)/notes?noteId=34" },
  },
  "string note_id should coerce to a number in the route",
);

// 3. note_due without a usable note_id → land on the Notes tab (no param).
assert.deepEqual(
  decidePushAction({ type: "dialer.note_due" }).navigation,
  { kind: "route", path: "/(tabs)/notes" },
  "note_due without note_id should still route to the notes tab",
);
assert.deepEqual(
  decidePushAction({ type: "dialer.note_due", note_id: "abc" }).navigation,
  { kind: "route", path: "/(tabs)/notes" },
  "note_due with a non-numeric note_id should route to the notes tab",
);

// 4. URL present → deep-link to that target (wins over note_due routing),
// and mark the originating row read.
assert.deepEqual(
  decidePushAction({
    notification_id: 42,
    url: "/user/links/9/restaurant/orders",
    type: "restaurant.new_order",
  }),
  {
    markReadId: 42,
    navigation: { kind: "open", target: "/user/links/9/restaurant/orders" },
  },
  "url present should open the target and mark the row read",
);
assert.deepEqual(
  decidePushAction({ notification_id: "42", url: "https://1in.me/x" }),
  {
    markReadId: 42,
    navigation: { kind: "open", target: "https://1in.me/x" },
  },
  "string notification_id should coerce to a number",
);

// 5. Empty / absent data → fall back to the dialer home, nothing to mark.
assert.deepEqual(
  decidePushAction({}),
  { markReadId: null, navigation: { kind: "route", path: "/(tabs)/dialer" } },
  "empty data should fall back to the dialer home",
);
assert.deepEqual(
  decidePushAction(undefined),
  { markReadId: null, navigation: { kind: "route", path: "/(tabs)/dialer" } },
  "missing data should fall back to the dialer home",
);

// 6. Unknown type without a url → dialer home; unusable notification_id
// values never mark-read.
assert.deepEqual(
  decidePushAction({ notification_id: 7, type: "new_follower" }).navigation,
  { kind: "route", path: "/(tabs)/dialer" },
  "no url + non-note type should route to the dialer home",
);
assert.equal(decidePushAction({ notification_id: "  " }).markReadId, null);
assert.equal(decidePushAction({ notification_id: "abc" }).markReadId, null);

// 7. Empty url string is ignored (treated as no target).
assert.deepEqual(
  decidePushAction({ notification_id: 1, url: "" }).navigation,
  { kind: "route", path: "/(tabs)/dialer" },
  "empty url should not be treated as a deep-link target",
);

console.log("test-push-action: all assertions passed");
