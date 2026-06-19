# Common Patterns

Reusable patterns the codebase relies on. Follow these so new code matches the rest
of the repo. For how-to recipes see [`how-to-add-features.md`](./how-to-add-features.md);
for rules see [`conventions.md`](./conventions.md).

## API response envelope (Laravel `/api/v1`)

Every REST response uses a unified envelope. Success payloads are wrapped in `data`,
errors in `error`:

```jsonc
// success
{ "data": { /* ... */ } }
// error
{ "error": { "message": "...", "code": "some_code", "details": { /* optional */ } } }
```

Produce them with the `ApiResponses` trait
(`app/Modules/Api/Controllers/Concerns/ApiResponses.php`) — never build the envelope
by hand:

```php
use App\Modules\Api\Controllers\Concerns\ApiResponses;

return $this->ok($payload);            // 200 {data: ...}
return $this->created($payload);       // 201 {data: ...}
return $this->noContent();             // 204
return $this->fail('Bad input', 400, 'bad_input', $details);
return $this->notFound();              // 404 {error:{code:"not_found"}}
return $this->forbidden();             // 403
return $this->unauthorized('Invalid credentials', 'invalid_credentials'); // 401
```

Thrown framework exceptions on `api/*` routes are converted to the same envelope by
the handlers in `bootstrap/app.php`:

- `ValidationException` → `422` `code:"validation_failed"` with field `details`.
- `AuthenticationException` → `401`, `AuthorizationException` → `403`,
  not-found → `404`, method-not-allowed → `405`, throttle → `429`.
- `InsufficientCoinsForAiException` → `402` with a top-up CTA.

So in controllers you usually just `$request->validate([...])` and return
`$this->ok(...)`; the error paths are handled for you.

## Optional (viewer-aware) auth

Public endpoints whose response varies by the (optional) viewer use the
`api.optional_auth` middleware (`OptionalSanctum`). It resolves a bearer token if one
is present and binds the user for `auth()` helpers, but does **not** 401 when absent.
Use it for feeds, public biolinks, and discovery so visibility tiers (public /
registered / followers / subscribers) can be applied when a token is supplied.

For strictly authenticated endpoints use `auth:sanctum` instead.

## Output resources (Laravel)

Shape API output through a static resource class in `app/Modules/Api/Resources/`
rather than returning Eloquent models directly. Follow `UserResource::toArray()`:
a public base array, plus extra fields when `$self === true`. This keeps private
fields (email, plan, billing) from leaking to other viewers.

## Contract-first codegen client

The typed API is generated from `lib/api-spec/openapi.yaml`:

```
openapi.yaml ──orval──▶ @workspace/api-client-react  (React Query hooks + fetch)
             └─orval──▶ @workspace/api-zod            (Zod schemas for the server)
```

- **Clients** (React web / Expo) import generated hooks from
  `@workspace/api-client-react`.
- **The Node server** imports schemas from `@workspace/api-zod` and validates with
  `.parse(...)` (see `artifacts/api-server/src/routes/health.ts`).
- Regenerate with `pnpm --filter @workspace/api-spec run codegen`. Never hand-edit
  files under `src/generated/`.
- Don't change the OpenAPI `info.title` — a transformer pins it to `"Api"` so output
  is named `api.ts`, which the barrels assume.

### The custom fetch mutator

`lib/api-client-react/src/custom-fetch.ts` is the single fetch implementation behind
every generated hook. It provides:

- `setBaseUrl(url)` — prepend a base URL to relative requests (used by Expo to target
  the remote API). Web leaves it unset (same-origin, baseUrl `/api`).
- `setAuthTokenGetter(getter)` — supply a bearer token attached as
  `Authorization: Bearer <token>` when present (Expo only; web relies on session
  cookies — do not use this on web).
- Robust body/accept handling and a typed `ApiError` (with `status`, `data`,
  `response`) plus `ResponseParseError` for non-JSON bodies.

Mobile wires both setters once in `artifacts/1inme-mobile/app/_layout.tsx`:

```ts
import { setAuthTokenGetter, setBaseUrl } from "@workspace/api-client-react";
setBaseUrl(getBaseUrl());                               // from lib/api.ts (EXPO_PUBLIC_*)
setAuthTokenGetter(async () => (await getToken()) ?? null);
```

## Mobile API layer

Mobile screens don't call generated hooks directly everywhere — they go through
per-domain wrappers in `artifacts/1inme-mobile/lib/api/` (e.g. `links.ts`,
`biolinks.ts`, `payouts.ts`). The base URL resolves from `EXPO_PUBLIC_API_BASE_URL`
or `EXPO_PUBLIC_DOMAIN` via `lib/api.ts` `getBaseUrl()`. Add a new domain wrapper
file when you add a feature area, keeping screens thin.

## Server logging (Node)

Use structured pino logging — **never `console.log`** in server code:

- In Express handlers: `req.log.info({ ... }, "message")`.
- Outside requests: import the singleton `logger` from `src/lib/logger.ts`.
- The logger redacts `authorization`, `cookie`, and `set-cookie`. In dev it
  pretty-prints; in production it emits JSON.

(The only intentional `console.error`/`console.log` in `api-server` is in the
port self-heal path in `index.ts`, where pino's worker may not flush before
`process.exit` — don't copy that pattern for normal logging.)

## Port binding & self-heal (Node)

Services read `PORT` from the environment (never hard-code it). `api-server`'s
`index.ts` additionally detects a stale previous instance holding the port in
development and terminates it before retrying — so restarts don't fail with
`EADDRINUSE`. In production it exits instead of killing peers.

## Schema ownership (Postgres)

One database, two owners — see [`architecture.md`](./architecture.md):

- Laravel migrations own the `public` schema (Eloquent).
- `@workspace/db` (Drizzle) owns only the `drizzle` schema. Every Drizzle table must
  declare `pgSchema('drizzle')`; apply changes with `pnpm --filter @workspace/db run
  push`. Drizzle never touches Laravel's tables (enforced by
  `schemaFilter: ["drizzle"]` in `drizzle.config.ts`).

## Biolink block first-paint defaults (Laravel)

New biolink blocks render immediately with friendly placeholder content. The pattern:

- `app/Modules/User/Support/BlockDefaults.php` returns per-type **structural** style
  tokens + placeholder text/media. Colours are deliberately omitted so the active
  biolink theme resolves them at render time.
- Defaults are applied **only at creation** (block store), never on update — saved
  blocks are left untouched. A `_placeholder` flag drives the "we dropped in
  placeholder content" banner and is cleared on first real save.
- Selecting a variant from the variant catalog fully replaces the seeded `_style`.

When adding a block type, add its defaults here (see recipe #6 in
[`how-to-add-features.md`](./how-to-add-features.md)).

## Plan gating & smart upgrade (Laravel)

Plan limits and recommendations go through shared services, not ad-hoc checks:

- `app/Services/EffectivePlanFeatures.php` — resolve a user's effective feature flags/
  limits (e.g. `planFeatureEnabled('link_smart_rules')`).
- `app/Services/PlanRecommender.php` — computes per-user usage gauges and a single
  recommended plan (binding-cap rule at ≥70% usage, with fallbacks). The same result
  powers the public `/pricing` banner and in-app `/user/upgrade`. Per-user usage
  counts are cached (short TTL) and event-busted on writes.
- `app/Services/PricingResolver.php` — pricing/coin-package resolution.

Gate UI and API features on these so behaviour stays consistent across surfaces.

## Browser extension targets

The extension builds for multiple browsers from two manifests
(`src/manifest.chrome.json`, `src/manifest.firefox.json`) via `scripts/build.mjs`
(`build:chrome` / `build:firefox` / `build:edge` / `build:all`). Shared logic lives
in `src/lib/` (with co-located `*.test.ts` run by `tsx --test`). Keep
browser-specific differences in the manifests, not scattered through the code.

## Dependency catalog

Shared dependency versions are pinned once in `pnpm-workspace.yaml` under `catalog:`
and referenced as `"catalog:"` in each package. When adding a dependency that already
has a catalog entry, use `catalog:` rather than a literal version. `pnpm add` picks
this up automatically. See [`conventions.md`](./conventions.md) for dep placement
rules.
