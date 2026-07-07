# File Structure

This document maps the repository so you can find things fast. Paths are relative
to the repo root unless noted.

## Repository root

```
.
├── artifacts/            # Deployable applications (one folder per artifact)
├── lib/                  # Shared workspace libraries (@workspace/*)
├── scripts/              # Shared utility scripts (@workspace/scripts)
├── docs/                 # This documentation set
├── pnpm-workspace.yaml   # Workspace package globs, dependency catalog, overrides
├── package.json          # Root task orchestration + shared dev tooling
├── tsconfig.base.json    # Shared strict TS defaults (extended by most packages)
├── tsconfig.json         # Root TS "solution" file — references composite libs only
├── replit.md             # Product overview + user preferences (not duplicated here)
└── .agents/memory/       # Cross-session agent memory (index + topic files)
```

Root `package.json` scripts that matter:

- `pnpm run typecheck` — canonical full typecheck: builds composite libs, then
  typechecks every artifact + `scripts`.
- `pnpm run typecheck:libs` — `tsc --build` for the composite libs only.
- `pnpm run build` — typecheck, then run each package's `build` if present.

There is **no** root `dev` script by design — apps run via Replit workflows, not
`pnpm dev` at the root.

## `artifacts/` — deployable applications

Each artifact has a `.replit-artifact/artifact.toml` describing its service(s),
ports, and proxy path(s). The shared proxy routes by path prefix.

### `artifacts/1inme/` — Laravel web app (the core product)

PHP 8.4 Laravel app following an HMVC module pattern. Served at `/` on port `5000`.

```
artifacts/1inme/
├── app/
│   ├── Actions/              # Single-purpose action classes
│   ├── Console/              # Artisan commands
│   ├── Events/  Listeners/   # Domain events + handlers
│   ├── Http/                 # Global HTTP layer (base Controller, middleware)
│   ├── Jobs/                 # Queued jobs
│   ├── Mail/                 # Mailables
│   ├── Models/               # Eloquent models shared across modules
│   ├── Providers/            # Service providers (incl. AppServiceProvider)
│   ├── Services/             # Cross-cutting domain services (see below)
│   └── Modules/              # HMVC modules — the bulk of the app
│       ├── Admin/            # Admin + Super Admin area
│       ├── User/             # Authenticated user app (dashboard, biolinks, etc.)
│       ├── Common/           # Public-facing + shared (biolink rendering, etc.)
│       └── Api/              # The /api/v1 REST API (Sanctum bearer)
├── bootstrap/app.php         # App bootstrap: routing, middleware aliases, exception → JSON envelope
├── config/                   # Laravel config (auth guards, etc.)
├── database/
│   ├── migrations/           # 224+ migrations (own the public schema)
│   ├── factories/  seeders/  # Test data + seeders
│   └── data/                 # Static data fixtures
├── routes/
│   ├── web.php               # Public + marketing web routes
│   ├── api.php               # /api/v1 routes (loaded with apiPrefix "api")
│   ├── webhooks.php          # Webhook routes (web middleware, CSRF-exempt)
│   ├── console.php           # Scheduler + console routes
│   └── modules/
│       ├── admin.php         # Admin module routes
│       └── user.php          # User module routes
├── resources/views/          # Blade templates
│   ├── admin/  user/         # Per-area page templates
│   ├── common/               # Public pages incl. biolink rendering
│   │   └── blocks/           # One Blade partial per biolink block type
│   ├── components/           # Blade components
│   ├── partials/  emails/    # Shared partials and email templates
│   └── vendor/               # ⚠️ DO NOT EDIT (see conventions.md)
├── public/                   # Web root + static assets (incl. block-placeholders/)
├── docs/                     # App-specific docs: api.md, blade-lint.md
├── openapi.yaml              # (App-local OpenAPI; the workspace contract lives in lib/api-spec)
├── composer.json             # PHP dependencies
├── artisan                   # Laravel CLI entrypoint
└── vite.config.js            # Front-end asset bundling (Tailwind, Alpine)
```

Inside each `app/Modules/<Module>/` you'll typically find: `Controllers/`,
`Middleware/`, `Models/`, `Services/`, `Requests/`, `Providers/`, plus module-specific
folders such as `Support/`, `Resources/`, `Concerns/`, `Traits/`, `Rules/`.

Notable locations in the Laravel app:

- **REST API** — `app/Modules/Api/`:
  - `Controllers/` (56 controllers; e.g. `AuthController`, `BiolinkController`,
    `LinkController`, `FeedController`).
  - `Controllers/Concerns/ApiResponses.php` — the success/error envelope trait.
  - `Middleware/OptionalSanctum.php` — optional bearer auth (`api.optional_auth`),
    `Middleware/TouchSessionToken.php`.
  - `Resources/` — array shaping (e.g. `UserResource`, `LinkResource`).
  - `Support/SessionTokenIssuer.php` — issues Sanctum personal access tokens.
- **Cross-cutting services** — `app/Services/`: `PlanRecommender.php`,
  `PricingResolver.php`, `EffectivePlanFeatures.php`, `TaxCalculator.php`,
  `InvoiceService.php`, `SafeHtml.php`, `UploadPolicy.php`, plus subfolders
  `AI/`, `Billing/`, `CreatorPayouts/`, `Monetization/`, `Dm/`, `Resume/`.
- **Biolink block defaults** — `app/Modules/User/Support/BlockDefaults.php`
  (first-paint defaults applied only at block creation).

### `artifacts/api-server/` — Node/Express API service

TypeScript Express 5 service, served at `/api` on port `8080`. Built with esbuild.

```
artifacts/api-server/
├── src/
│   ├── index.ts          # Entrypoint: reads PORT, listens, self-heals stale ports
│   ├── app.ts            # Express app: pino-http, cors, json, mounts router at "/api"
│   ├── routes/
│   │   ├── index.ts      # Router barrel
│   │   └── health.ts     # GET /api/healthz, validated with @workspace/api-zod
│   ├── lib/logger.ts     # Singleton pino logger (with redaction)
│   └── middlewares/      # (scaffold)
├── build.mjs             # esbuild bundle config (CJS/ESM via esbuild-plugin-pino)
├── .replit-artifact/artifact.toml
├── package.json
└── tsconfig.json
```

### `artifacts/1inme-mobile/` — Expo / React Native app

File-based routing via expo-router. Served at `/mobile/` on port `23680`.

```
artifacts/1inme-mobile/
├── app/                  # expo-router screens (file = route)
│   ├── _layout.tsx       # Root layout: providers, setBaseUrl/setAuthTokenGetter wiring
│   ├── index.tsx         # Home
│   ├── (auth)/  (tabs)/  # Route groups
│   └── <feature>.tsx     # One screen per feature (links, biolink, payouts, etc.)
├── lib/
│   ├── api.ts            # getBaseUrl() / API URL helpers (EXPO_PUBLIC_* env)
│   └── api/              # Per-domain client wrappers (links.ts, biolinks.ts, ...)
├── components/  contexts/  hooks/   # UI + state
├── constants/  types/  assets/
├── server/serve.js       # Production static serve
├── scripts/              # Build + test scripts
├── app.json              # Expo config
└── package.json
```

The mobile app consumes `@workspace/api-client-react` (the generated React Query
client) and points it at the remote API via `setBaseUrl` + `setAuthTokenGetter` in
`app/_layout.tsx`.

### `artifacts/1inme-extension/` — browser extension

Vite-built MV3 extension for Chrome / Firefox / Edge. Build-only (no served
service / no `artifact.toml`).

```
artifacts/1inme-extension/
├── src/
│   ├── background/       # Service worker (index.ts, radar.ts)
│   ├── content/          # Content scripts (extract, handshake, radar)
│   ├── popup/            # React popup (App.tsx, main.tsx, index.html)
│   ├── lib/              # api.ts, storage.ts, browser.ts, hash.ts (+ *.test.ts)
│   ├── manifest.chrome.json
│   └── manifest.firefox.json
├── scripts/build.mjs     # Per-target build (chrome/firefox/edge/all)
├── public/icons/
└── package.json          # build:chrome / build:firefox / build:edge / test
```

### `artifacts/mockup-sandbox/` — component preview canvas

Design/preview artifact ("Canvas"), served at `/__mockup` on port `8081`. Used for
isolated UI component preview during design work.

## `lib/` — shared workspace libraries

```
lib/
├── api-spec/             # OpenAPI contract + Orval codegen config
│   ├── openapi.yaml      # ← source of truth for the typed API
│   ├── orval.config.ts   # Two outputs: api-client-react + api-zod
│   └── package.json      # script: codegen
├── api-zod/              # Generated Zod schemas (server-side validation)
│   ├── src/index.ts      # re-exports generated/api + generated/types
│   └── src/generated/    # ⚠️ generated — do not hand-edit
├── api-client-react/     # Generated React Query client + fetch mutator
│   ├── src/custom-fetch.ts   # customFetch + setBaseUrl + setAuthTokenGetter + ApiError
│   ├── src/index.ts          # re-exports generated hooks + the setters
│   └── src/generated/        # ⚠️ generated — do not hand-edit
└── db/                   # Drizzle client + schema (Node services only)
    ├── src/index.ts      # pool + db (drizzle), throws if DATABASE_URL unset
    ├── src/schema/index.ts   # add one export per table file
    └── drizzle.config.ts # schemaFilter: ["drizzle"] — never touches Laravel's public schema
```

`lib/*` packages are **composite** (they emit `.d.ts` via `tsc --build`) and are
listed in the root `tsconfig.json` `references`. Artifacts are leaf packages and are
**not** added there.

## `scripts/` — shared utilities

`@workspace/scripts` is a single leaf workspace package. Put shared utility scripts
in `scripts/src/` and add a matching npm script in `scripts/package.json`. It also
holds `post-merge.sh` (post-merge setup hook).

## Configuration & tooling files

- `pnpm-workspace.yaml` — package globs (`artifacts/*`, `lib/*`, `lib/integrations/*`,
  `scripts`), the dependency **catalog** (pinned shared versions referenced as
  `catalog:`), `minimumReleaseAge`, and platform-binary `overrides`.
- `tsconfig.base.json` — strict TS defaults (`strictNullChecks`, `isolatedModules`,
  `moduleResolution: bundler`, `customConditions: ["workspace"]`, etc.).
- `.npmrc`, `replit.nix`, `.replit` — environment/runtime config.

## Generated files (never hand-edit)

- `lib/api-zod/src/generated/**`
- `lib/api-client-react/src/generated/**`
- Anything under any `dist/` folder
- `*.tsbuildinfo` (gitignored TS build cache)

Regenerate the API ones with `pnpm --filter @workspace/api-spec run codegen`.
See [`how-to-add-features.md`](./how-to-add-features.md).
