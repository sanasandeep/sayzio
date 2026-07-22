---
name: PHP empty array → JS array pitfall in @js()
description: Alpine state seeded via @js(PHP []) becomes a JS array; string keys silently dropped by JSON.stringify.
---

Rule: when seeding an Alpine/JS object map from PHP data that may be an empty array (e.g. `$override['content'] ?? []`), cast with `(object)` inside `@js(...)` so it serializes as `{}` not `[]`.

**Why:** PHP's `[]` json-encodes to a JS array. Assigning string keys onto a JS array works for property access but `JSON.stringify` drops them, so downstream sync (e.g. writing a JSON textarea from the map) silently emits `"[]"`. Also `Object.keys(arr).length` misbehaves for emptiness checks.

**How to apply:** any `@js($assoc ?? [])` where the value is used as a keyed map in Alpine — cast `(object)`. Symptom to recognize: UI edits update but serialized JSON stays `[]`.
