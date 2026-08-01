---
name: mobile apiFetch envelope unwrapping
description: The 1inme-mobile apiFetch helper returns the raw API envelope; callers must unwrap {data}.
---

`artifacts/1inme-mobile/lib/api.ts` `apiFetch<T>()` returns the **raw** JSON body, NOT the unwrapped payload. The 1inme REST API (Api controllers via `ApiResponses::ok()/created()`) wraps every success payload under a top-level `data` key.

**Rule:** a typed API helper must request `apiFetch<{ data: X }>(...)` and `return res.data` (see `lib/api/aiBuilder.ts` for the canonical pattern). Calling `apiFetch<X>(...)` directly and reading `res.someField` silently reads `undefined` on every successful response — screens render empty/not-found even though the server returned data, and TypeScript won't catch it.

**Why:** typecheck passes either way (the body is `unknown`-shaped at runtime), so this is a functional-only bug. SSE streaming endpoints are exempt — their event frames carry data directly, not under `data`.

**Null-body trap:** `apiFetch` returns `null` for a 2xx with an empty body (204, proxy hiccup). Every envelope reader must optional-chain the FIRST hop too (`res?.data?.x`), not just `res.data?.x`, or a stray empty 200 throws `Cannot read properties of null` at runtime (typecheck won't catch it unless the generic is typed `<...| null>`). Applies to all AuthContext readers (sendOtp/verifyOtp/demoLogin/socialLogin).

**Nested-envelope trap:** some endpoints wrap the payload one level deeper than `{data: X}` — e.g. contact `show` and follow-up set/clear return `{data: {contact: X}}` while contact notes/tags PATCHes return `{data: X}` directly. A helper unwrapping only one level typechecks green and every mocked-API e2e passes, but the screen crashes/renders empty against the real API. Check the Laravel controller's `$this->ok([...])` shape per endpoint; the sayzio-dialer-standalone copy of a shared lib may already have the correct unwrap (diff against it). Only a real-API harness catches these — mocked harnesses encode the same wrong assumption as the client.

**Prefix trap:** `apiFetch` itself prepends `/api/v1` to the given path. A helper passing `/api/v1/...` silently double-prefixes (`/api/v1/api/v1/...`) and 404s on every call — typecheck-green. New API lib files must use relative paths (`/me/...`, `/links`); the updates.ts lib shipped with this bug until the source-driven CRUD test caught it.
