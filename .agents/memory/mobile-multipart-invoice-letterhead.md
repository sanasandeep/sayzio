---
name: Mobile multipart invoice/receipt submits
description: How the 1inme-mobile invoices.ts client submits JSON vs multipart (letterhead upload) to the same Laravel endpoints
---

`apiFetch` always sends `Content-Type: application/json`, so any mobile call that needs a file (e.g. a per-invoice letterhead override) cannot go through it — it must build its own `fetch` with `FormData`, matching the existing `voiceAssistant.turn` pattern (RN's `FormData` entry shape is `{uri, name, type}`, and RN sets the multipart boundary itself, so never set `Content-Type` manually).

For endpoints that are normally JSON POST/PATCH but occasionally need a file, branch: send plain JSON when no file/removal flag is present (fast path, matches every other billing call), and build FormData only when a letterhead is attached or being removed. PATCH via FormData needs an explicit `_method=PATCH` field since RN can't send a real multipart PATCH body reliably through some proxies.

**Why:** `artifacts/1inme/app/Modules/Api/Controllers/BillingController.php` invoice/receipt endpoints accept both JSON bodies and an optional `letterhead` file upload (mirroring the web controller); the mobile client needed both paths without duplicating two full endpoints.

**How to apply:** the shared token/base-URL helpers live in `@/lib/secure` (`getToken`) and `@/lib/api` (`getBaseUrl`), not `@/lib/auth` — check imports against `lib/api.ts`'s own usage before assuming a helper's home module.

**Nested fields (arrays of objects, e.g. `line_items`) must NOT be `JSON.stringify`'d into a single FormData field.** Laravel's `array`/`'foo.*.bar'` validation rules only see indexed form fields (`line_items[0][label]`, `line_items[0][amount_minor]`, ...), not one JSON string — a naive `fd.append(key, JSON.stringify(value))` silently fails that validation the moment a multipart branch (e.g. a file upload) is taken on an endpoint that otherwise works fine as plain JSON. Recursively flatten arrays/objects into bracket-notation FormData keys instead (booleans as `"1"`/`"0"`) whenever building FormData for a Laravel endpoint that also accepts nested JSON.
