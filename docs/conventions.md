# Conventions

Rules and conventions for working in this monorepo. When in doubt, match the
surrounding code. See also [`common-patterns.md`](./common-patterns.md) and
[`how-to-add-features.md`](./how-to-add-features.md).

## The hard "do not" list

- **Do not edit `artifacts/1inme/resources/views/vendor/`.** This is a stated user
  preference in [`replit.md`](../replit.md).
- **Do not hand-edit generated files.** Anything under
  `lib/api-zod/src/generated/**`, `lib/api-client-react/src/generated/**`, or any
  `dist/` folder is generated. Regenerate the API ones with
  `pnpm --filter @workspace/api-spec run codegen`.
- **Do not change the OpenAPI `info.title`.** A transformer pins it to `"Api"`; the
  generated filenames (`api.ts`) and the barrel exports depend on it.
- **Do not run `pnpm dev` / `pnpm run dev` at the repo root.** There is no root `dev`
  script by design; apps run via Replit workflows that inject `PORT` / `BASE_PATH`.
- **Do not call service ports directly** for ad-hoc requests — go through the proxy at
  `localhost:80` (Expo is the exception: use `$REPLIT_EXPO_DEV_DOMAIN`).
- **Do not model Laravel's `public` tables with Drizzle**, and do not let Drizzle
  manage anything outside the `drizzle` schema.
- **Do not import one artifact from another.** Share via a `lib/*` package.
- **Do not use `console.log` in Node server code.** Use `req.log` / the singleton
  `logger`.
- **Do not edit `artifact.toml` / `.replit` by hand** — use the Replit artifacts/
  workflows skills.

## Repository layout rules

- `artifacts/*` are deployable apps (leaf workspace packages).
- `lib/*` are shared libraries (composite packages that emit declarations).
- `scripts` is a single shared-utilities leaf package.
- Artifacts must not depend on each other; extract shared code into a lib.

## TypeScript

- Workspace package names use the `@workspace/` prefix.
- `lib/*` packages are **composite**: their `tsconfig.json` sets `composite`,
  `declarationMap`, `emitDeclarationOnly`, and they appear in the root
  `tsconfig.json` `references`. If a lib imports another lib, add that to its own
  `references`.
- `artifacts/*` and `scripts` are **leaf** packages typechecked with `--noEmit`;
  never add them to the root `tsconfig.json` references, and do not make them
  composite (declaration emit from apps causes TS2742 portability errors).
- Most packages extend `tsconfig.base.json` (strict-ish defaults:
  `strictNullChecks`, `isolatedModules`, `moduleResolution: bundler`,
  `noImplicitAny`, `customConditions: ["workspace"]`). Expo uses its own base.
- Trust `pnpm run typecheck` over the editor/LSP when they disagree. Missing
  `@workspace/db` exports usually mean stale lib declarations → run
  `pnpm run typecheck:libs`.

## Dependency management

- Use **pnpm** only (a `preinstall` guard rejects npm/yarn).
- Shared versions are pinned once in `pnpm-workspace.yaml` `catalog:` and referenced
  as `"catalog:"` per package. Prefer `catalog:` for any dependency that has an entry;
  `pnpm add` resolves it automatically.
- Each package declares its own dependencies — they are not shared implicitly.
- Dependency placement:
  - **Static/client-only artifacts** (Vite React apps): everything → `devDependencies`.
  - **Server artifacts**: runtime imports (`express`, `drizzle-orm`, `pg`) →
    `dependencies`; build tools and `@types/*` → `devDependencies`.
  - **Libraries**: shared runtimes (`react`, `react-dom`) → `peerDependencies`.
- Do not use `pnpm add --no-frozen-lockfile`.
- Root dependencies are for repo-level tooling only (`typescript`, `prettier`).

## PHP / Laravel (`artifacts/1inme`)

- Follow the **HMVC module** structure: features live under
  `app/Modules/{Admin,User,Common,Api}/` in `Controllers/`, `Services/`, `Models/`,
  `Middleware/`, `Requests/`, etc. Cross-cutting logic goes in `app/Services/`.
- Keep controllers thin; put business logic in service classes.
- REST API controllers `use` the `ApiResponses` trait and return through its helpers
  (envelope is mandatory). Shape output via `app/Modules/Api/Resources/` classes, not
  raw models.
- Migrations are timestamp-prefixed
  (`YYYY_MM_DD_NNNNNN_describe_change.php`) and own the `public` schema.
- Route files: public `routes/web.php`, REST `routes/api.php`, module routes
  `routes/modules/{admin,user}.php`, webhooks `routes/webhooks.php`, scheduler
  `routes/console.php`. New `api/*` routes are CSRF-exempt (configured in
  `bootstrap/app.php`).
- Blade templates live in `resources/views/`; reuse `components/` and `partials/`.
  Biolink block templates are one file per block in `resources/views/common/blocks/`.
  See [`artifacts/1inme/docs/blade-lint.md`](../artifacts/1inme/docs/blade-lint.md)
  for Blade linting.
- Auth guards: `web` (user session), `admin` (admin session), `sanctum` (API bearer).
  `super_admin` role unlocks the Super Admin area.

## Node / Express (`artifacts/api-server`)

- Contract-first: define endpoints in `lib/api-spec/openapi.yaml`, run codegen, then
  implement validated routes (mirror `src/routes/health.ts`).
- One router file per concern under `src/routes/`, mounted in `src/routes/index.ts`.
- Validate inputs/outputs with `@workspace/api-zod` schemas (`.parse(...)`).
- Read `PORT` from the environment; never hard-code it.
- Logging via pino only (`req.log` / `logger`); secrets are redacted.

## Mobile (`artifacts/1inme-mobile`)

- expo-router file-based routing: a file under `app/` is a route; use `(auth)` /
  `(tabs)` groups.
- Per-domain API wrappers live in `lib/api/`; base URL + token are wired globally in
  `app/_layout.tsx`. Resolve URLs via `lib/api.ts` (`EXPO_PUBLIC_*` env), not
  hard-coded hosts.
- Preview via `$REPLIT_EXPO_DEV_DOMAIN`, not the shared proxy.

## Browser extension (`artifacts/1inme-extension`)

- Two manifests (`manifest.chrome.json`, `manifest.firefox.json`); build per target
  with `scripts/build.mjs` (`build:chrome|firefox|edge|all`).
- Shared logic in `src/lib/` with co-located `*.test.ts` (run via `pnpm --filter
  @workspace/1inme-extension run test`, i.e. `tsx --test`).

## Routing & ports

- The reverse proxy routes by path prefix (most-specific wins). Services handle their
  own full base path (no rewriting).
- Each service binds the `PORT` it's given. Known dev ports: Laravel `5000`,
  api-server `8080`, deck `22245`, mobile `23680`, canvas `8081`.
- In app code prefer relative URLs / the artifact base path
  (`import.meta.env.BASE_URL` for Vite, the API URL helper for Expo) — never
  root-relative URLs that escape the artifact's path prefix.

## Commands cheat-sheet

| Task | Command |
| --- | --- |
| Full typecheck | `pnpm run typecheck` |
| Typecheck libs only | `pnpm run typecheck:libs` |
| Typecheck one package | `pnpm --filter @workspace/<name> run typecheck` |
| Regenerate API client + zod | `pnpm --filter @workspace/api-spec run codegen` |
| Apply Drizzle schema | `pnpm --filter @workspace/db run push` (`run push-force`) |
| Build extension | `pnpm --filter @workspace/1inme-extension run build:all` |
| Run an app | use Replit workflows (not `pnpm dev`) |
| Ad-hoc HTTP check | `curl localhost:80/<path>` (Expo: `$REPLIT_EXPO_DEV_DOMAIN`) |

Prefer `typecheck` over `build` for verification — `build` needs workflow-provided
env (`PORT`, `BASE_PATH`) and may fail from a plain shell even when types are fine.

## Documentation

- This `docs/` set is the workspace-wide technical reference. Keep app-specific deep
  references where they live (e.g. [`artifacts/1inme/docs/api.md`](../artifacts/1inme/docs/api.md))
  and link to them rather than duplicating.
- The product overview and user preferences live in [`replit.md`](../replit.md); do
  not duplicate it here.
