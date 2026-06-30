---
name: mobile apiFetch envelope unwrapping
description: The 1inme-mobile apiFetch helper returns the raw API envelope; callers must unwrap {data}.
---

`artifacts/1inme-mobile/lib/api.ts` `apiFetch<T>()` returns the **raw** JSON body, NOT the unwrapped payload. The 1inme REST API (Api controllers via `ApiResponses::ok()/created()`) wraps every success payload under a top-level `data` key.

**Rule:** a typed API helper must request `apiFetch<{ data: X }>(...)` and `return res.data` (see `lib/api/aiBuilder.ts` for the canonical pattern). Calling `apiFetch<X>(...)` directly and reading `res.someField` silently reads `undefined` on every successful response — screens render empty/not-found even though the server returned data, and TypeScript won't catch it.

**Why:** typecheck passes either way (the body is `unknown`-shaped at runtime), so this is a functional-only bug. SSE streaming endpoints are exempt — their event frames carry data directly, not under `data`.
