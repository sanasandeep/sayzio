// Smoke test for decidePushAction — the pure helper the push response
// listener uses to decide (a) which in-app row to mark read and (b) where
// to send the user when they tap a push.
//
// This guards push deep-linking + mark-read (task #1706): a tapped push
// must mark the originating row read and open the exact same target the
// in-app row uses, falling back gracefully when no target is present.
//
// Run via `node scripts/test-push-action.mjs` (also wired into the package
// script `test:push-action`). We intentionally avoid a full TS test
// runner — the helper is pure, so we extract its body from push.ts and
// evaluate it in isolation.

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(join(__dirname, "..", "lib", "push.ts"), "utf8");

// Pull just the decidePushAction body out of push.ts (the rest of the
// module imports expo-notifications / expo-router which we can't load here).
// Capture the whole function: up to a `}` alone on its own line followed
// by a newline (the body close). The multi-line return type ends in `} {`
// (not `}\n`), so it won't trip the lazy match early.
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

// 1. URL present → deep-link to that target, and mark the row read.
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

// 2. notification_id arriving as a numeric string is coerced.
assert.deepEqual(
  decidePushAction({ notification_id: "42", url: "https://1in.me/x" }),
  {
    markReadId: 42,
    navigation: { kind: "open", target: "https://1in.me/x" },
  },
  "string notification_id should coerce to a number",
);

// 3. No url, api.usage_warning type → fall back to the usage screen.
assert.deepEqual(
  decidePushAction({ notification_id: 7, type: "api.usage_warning" }),
  { markReadId: 7, navigation: { kind: "route", path: "/api-usage" } },
  "api.usage_warning without a url should route to /api-usage",
);

// 3b. No url, expected_columns_missing type → deep-link to the admin
// dashboard (where the schema-health warning + Repair action live).
assert.deepEqual(
  decidePushAction({ notification_id: 9, type: "expected_columns_missing" }),
  { markReadId: 9, navigation: { kind: "route", path: "/admin" } },
  "expected_columns_missing without a url should route to /admin",
);

// 4. No url, other/absent type → fall back to the notifications list.
assert.deepEqual(
  decidePushAction({ notification_id: 7, type: "new_follower" }),
  { markReadId: 7, navigation: { kind: "route", path: "/notifications" } },
  "no url + non-usage type should route to /notifications",
);

// 5. Missing / unusable notification_id → markReadId is null (no mark-read).
assert.equal(decidePushAction({}).markReadId, null);
assert.equal(decidePushAction(undefined).markReadId, null);
assert.equal(decidePushAction({ notification_id: "  " }).markReadId, null);
assert.equal(decidePushAction({ notification_id: "abc" }).markReadId, null);

// 6. Empty url string is ignored (treated as no target).
assert.deepEqual(
  decidePushAction({ notification_id: 1, url: "" }).navigation,
  { kind: "route", path: "/notifications" },
  "empty url should not be treated as a deep-link target",
);

console.log("test-push-action: all assertions passed");
