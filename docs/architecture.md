# Architecture

This document explains how the pieces of the monorepo fit together at runtime and at
build time. For where files live, see [`file-structure.md`](./file-structure.md). For
the product overview, see [`replit.md`](../replit.md).

## High-level shape

The system is a pnpm-workspace monorepo containing multiple deployable **artifacts**
that share a small set of **libs**:

- A **Laravel app** (`artifacts/1inme`) is the core product. It serves the web UI
  (user + admin) and a full REST API at `/api/v1`.
- A **Node/Express service** (`artifacts/api-server`) is a separate, contract-first
  TypeScript API mounted at `/api` (currently health + scaffold).
- **Clients**: an Expo mobile app, a browser extension, a slide deck, and a design
  canvas.
- A single **PostgreSQL** database is shared, with strict schema ownership rules.
- An **OpenAPI contract** (`lib/api-spec`) drives codegen for typed server validation
  and typed client hooks.

```
Browser / Mobile / Extension
        │  HTTPS
        ▼
Replit shared reverse proxy  ── routes by path prefix (most-specific wins)
        ├── "/"          → artifacts/1inme        (Laravel, :5000)
        ├── "/api"       → artifacts/api-server    (Express, :8080)
        ├── "/mobile/"   → artifacts/1inme-mobile  (Expo, :23680)
        └── "/__mockup"  → artifacts/mockup-sandbox (Vite canvas, :8081)

artifacts/1inme  ──Eloquent──▶ Postgres (public schema)
artifacts/api-server ──Drizzle──▶ Postgres (drizzle schema only)
```

## Request routing (the proxy)

A global reverse proxy routes traffic to each artifact by the `paths` declared in its
`.replit-artifact/artifact.toml`. Rules to remember:

- **Match is most-specific-first**, so `/api` (api-server) and `/api/v1/...` (the
  Laravel REST API mounted under the `1inme` app at `/`) both resolve correctly even
  though they share a prefix. The Laravel app owns `/` and therefore everything under
  it that isn't claimed by a more specific artifact path; the `api-server` artifact
  claims `/api`.
- **Paths are not rewritten.** Each service must handle its own full base path. The
  Express app mounts its router at `/api` (`app.use("/api", router)`), so
  `GET /api/healthz` maps to the `healthz` route.
- For ad-hoc local requests (e.g. `curl`), always go through the proxy at
  `localhost:80`, never a service port directly:
  - Correct: `localhost:80/api/healthz`
  - Wrong: `localhost:8080/api/healthz`
- The **Expo** artifact is the exception — reach it locally via
  `$REPLIT_EXPO_DEV_DOMAIN`.
- Published apps are exposed over HTTPS on the domains in `$REPLIT_DOMAINS`.

Each service binds to the `PORT` env var assigned by the workflow (Laravel `5000`,
api-server `8080`, mobile `23680`, deck `22245`, canvas `8081`). Do not hard-code
ports.

## The two API surfaces

There are **two distinct HTTP APIs**. Don't confuse them:

1. **Laravel REST API at `/api/v1`** (in `artifacts/1inme`, served at `/`).
   This is the rich product API: auth, profile, links, biolinks, feed, follow/
   subscribe, discovery, payouts, etc. It uses **Laravel Sanctum** bearer tokens
   (`auth:sanctum`) plus an optional-auth middleware for public, viewer-aware
   endpoints. Full reference: [`artifacts/1inme/docs/api.md`](../artifacts/1inme/docs/api.md).

2. **Node Express API at `/api`** (in `artifacts/api-server`).
   A separate, smaller TypeScript service. Currently exposes `GET /api/healthz`
   plus scaffolding for adding contract-first routes. It validates payloads with the
   generated Zod schemas from `@workspace/api-zod`.

Both are real services routed by the proxy; they do not share code beyond the libs.

### Laravel app bootstrapping & the JSON envelope

`artifacts/1inme/bootstrap/app.php` wires routing and middleware:

- Routes: `web.php` (web), `api.php` (with `apiPrefix: 'api'`), `console.php`,
  and `webhooks.php` (grouped under `web` middleware).
- Middleware aliases are registered here, e.g. `api.optional_auth` →
  `OptionalSanctum`, plus workspace and onboarding gates.
- **CSRF exemptions** include `api/*`, `webhooks/*`, and several tracking/visitor
  endpoints.
- **Exception → JSON envelope**: for any `api/*` request, exceptions are rendered as
  the unified error envelope. Validation failures become `422` with
  `{error:{message, code:"validation_failed", details}}`; auth/authorization/not-
  found/method/throttle each map to their status and code. AI-credit exhaustion maps
  to `402` with a friendly top-up message.

The success/error envelope itself is produced by the `ApiResponses` trait
(`app/Modules/Api/Controllers/Concerns/ApiResponses.php`): `ok()`/`created()` wrap in
`{data: ...}`; `fail()`/`notFound()`/`forbidden()`/`unauthorized()` wrap in
`{error: {message, code?, details?}}`. See [`common-patterns.md`](./common-patterns.md).

### Node API server runtime

- `src/index.ts` requires `PORT`, validates it, and listens. In development it will
  detect and terminate a stale previous instance holding the port (self-heal); in
  production it exits if the port is held.
- `src/app.ts` installs `pino-http` request logging (with a trimmed serializer),
  `cors()`, JSON + urlencoded body parsing, then mounts the router at `/api`.
- Routes return data validated against `@workspace/api-zod` (e.g. `health.ts` does
  `HealthCheckResponse.parse({ status: "ok" })`).
- Logging uses a singleton pino `logger` (`src/lib/logger.ts`) with redaction of
  `authorization`/`cookie`/`set-cookie`. **Never use `console.log` in server code** —
  use `req.log` in handlers and `logger` elsewhere.

## Authentication model

The Laravel app uses multiple guards (see `artifacts/1inme/config/auth.php` and
`replit.md`):

- **`web`** guard — session-based, for the authenticated user app. Supports password
  and OTP login.
- **`admin`** guard — session-based, for the admin / Super Admin area.
- **`sanctum`** guard — bearer-token auth for the `/api/v1` REST API. Tokens are
  issued by `app/Modules/Api/Support/SessionTokenIssuer.php`.

Public, viewer-aware endpoints use the `api.optional_auth` middleware
(`OptionalSanctum`): if a valid bearer token is present it resolves the user and
applies visibility filtering (public / registered / followers / subscribers);
otherwise the request proceeds unauthenticated.

A `super_admin` role unlocks a dedicated Super Admin section (e.g. plan management).

## Data layer & schema ownership

There is **one PostgreSQL database with two owners** — this is the most important
architectural constraint in the repo:

- **Laravel owns the `public` schema.** Every real product table is created and
  migrated by Laravel (`artisan migrate`, 224+ migrations in
  `artifacts/1inme/database/migrations/`). Eloquent models live under
  `app/Models` and `app/Modules/*/Models`.
- **`@workspace/db` (Drizzle) owns only the `drizzle` schema.** Its
  `drizzle.config.ts` sets `schemaFilter: ["drizzle"]` precisely so drizzle-kit never
  introspects (or proposes dropping) Laravel's `public` tables. Any Drizzle table you
  add **must** be declared with `pgSchema('drizzle')` for this guarantee to hold.
  Drizzle changes are applied via `pnpm --filter @workspace/db run push` (drizzle-kit
  push), not SQL migration files.

`lib/db/src/index.ts` builds a `pg` `Pool` + `drizzle(...)` instance from
`DATABASE_URL` (throwing if it's missing) and re-exports the schema. The Node
`api-server` is the primary consumer.

> Why the split exists: without the `schemaFilter`, drizzle-kit would see Laravel's
> tables, find the Drizzle schema "empty", and generate `DROP` statements for
> everything — which the deployment validator flags as destructive. The dedicated
> `drizzle` schema keeps the two ORMs from ever stepping on each other.

## Contract-first API pipeline (codegen)

The typed API is generated from a single source of truth:

```
lib/api-spec/openapi.yaml
        │  pnpm --filter @workspace/api-spec run codegen   (Orval)
        ├──────────────▶ lib/api-client-react/src/generated/   (React Query hooks)
        │                 + custom-fetch mutator (baseUrl "/api")
        └──────────────▶ lib/api-zod/src/generated/             (Zod schemas + types)
```

`lib/api-spec/orval.config.ts` defines two outputs:

- **`api-client-react`** — `client: "react-query"`, split mode, `baseUrl: "/api"`,
  and a custom **fetch mutator** (`lib/api-client-react/src/custom-fetch.ts`). The
  mutator (`customFetch`) handles base-URL prefixing, bearer-token injection, body/
  accept handling, and rich error parsing (`ApiError`).
- **`zod`** — Zod schemas + TypeScript types, with coercion for query/param/body/
  response and `useDates`/`useBigInt` enabled. Consumed by the Node server for
  request/response validation.

A `titleTransformer` forces the OpenAPI `info.title` to `"Api"` so generated outputs
are named `api.ts` (other code imports assume this). **Do not change `info.title`.**

Clients (web/mobile React) consume `@workspace/api-client-react`; the server consumes
`@workspace/api-zod`. The mobile app additionally calls `setBaseUrl()` /
`setAuthTokenGetter()` (from the client lib) in `app/_layout.tsx` to point at the
remote API and attach the stored bearer token.

## Build & type system

- `lib/*` packages are **composite** and emit declarations via `tsc --build`
  (`pnpm run typecheck:libs`). They are listed in the root `tsconfig.json`
  `references`.
- `artifacts/*` and `scripts` are **leaf** packages, typechecked with
  `tsc --noEmit`. They must not import each other — share via a lib instead.
- `pnpm run typecheck` is the canonical full check (libs first, then leaves).
- The Node `api-server` bundles with esbuild (`build.mjs`, using
  `esbuild-plugin-pino`); production runs the bundled `dist/index.mjs` directly.
- Incremental builds: each package writes a gitignored `.tsbuildinfo`.

If the editor/LSP and the CLI disagree about cross-package types, trust
`pnpm run typecheck`. Missing `@workspace/db` exports usually mean stale lib
declarations — run `pnpm run typecheck:libs`.

## Deployment notes

- **Laravel** (`artifacts/1inme`): production build runs `composer install`
  (no-dev, optimized) and clears config/route/view caches; production run executes
  `php artisan migrate --force` (best-effort) then serves on `5000`.
- **api-server**: production builds with esbuild then runs `node dist/index.mjs`
  on `8080`; startup health is `/api/healthz`.
- **deck / mobile / canvas**: built per their `artifact.toml` (deck → static
  `dist/public`; mobile → Expo build + serve).

See the Replit `deployment` skill for publishing specifics. This doc does not cover
provisioning.
