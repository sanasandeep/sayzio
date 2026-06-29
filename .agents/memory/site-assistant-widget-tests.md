---
name: Site assistant widget tests
description: How the two "Zio Bot" assistant front-ends are unit-tested (sign-out + 401 session-expiry recovery) under vitest/jsdom in artifacts/1inme-com.
---

Both Zio Bot front-ends (marketing React widget `artifacts/1inme-com/src/components/site-assistant.tsx` and the in-app Laravel blade widget `artifacts/1inme/resources/views/common/partials/site-assistant.blade.php`) share the `/assistant/*` contract and must recover from auth loss the same way: a 401 on a gated call OR an explicit sign-out re-shows the in-chat login gate **in place** while preserving the anonymous `sa_visitor_token_v1` + visible conversation. There is no separate error bubble on a 401.

Tests live in `artifacts/1inme-com` (one vitest/jsdom toolchain covers both, enforcing the lockstep). Run via `pnpm --filter @workspace/1inme-com run test`; also registered as validation command `test:1inme-com`.

**Why a standalone `vitest.config.ts`:** never import `vite.config.ts` — it throws unless `PORT`/`BASE_PATH` are set (supplied by the dev/build workflow, not the test runner). The vitest config only re-declares the `@` and `@assets` aliases + `@vitejs/plugin-react`.

**React widget tests** (`site-assistant.test.tsx`): wrap in `<ThemeProvider defaultTheme="light">` to keep `useIsDark` off the system-theme `matchMedia` path; stub `global.fetch` and branch bootstrap on whether `init.headers.Authorization` is present (with token → `auth_required:false`; without → `auth_required:true`). Pre-seed localStorage tokens. Gate is detected by the "Send code" button; sign-out control by aria-label "Sign out".

**Blade widget test** (`site-assistant-blade.test.ts`) is **source-driven** (no JS toolchain on the Laravel side): read the sibling blade file, slice the single `<script>` body, strip `@php…@endphp` and every balanced-paren `@json(...)` → `""` (safe: all @json feed localized strings; the one tooltip array is `Array.isArray`-guarded), then execute the IIFE via `new Function(code)()` against a hand-built `#site-assistant-root` with all `data-*` URL attrs. The send path uses `fetch(streamUrl)` (NOT EventSource) and checks `res.status===401` → `handleUnauthorized()` → `showLoginGate()`; assert the `.sa-input-row` gains class `sa-gate` + a `.sa-gate-input` appears, `#sa-input` is gone, and `.sa-msg.error` count stays 0.

**How to apply:** when changing either widget's auth/gate/sign-out behavior, update both front-ends and these tests in lockstep. `vitest.setup.ts` polyfills `matchMedia` + `requestAnimationFrame` (jsdom lacks them) and clears localStorage between tests.
