# How to Add Features

Practical, step-by-step recipes for the most common changes in this monorepo. They
reference real paths. Read [`conventions.md`](./conventions.md) before you start, and
[`architecture.md`](./architecture.md) if you need the bigger picture.

> Decide which surface you're changing first. Most product features live in the
> **Laravel app** (`artifacts/1inme`). The **Node api-server** is for contract-first
> TypeScript endpoints. The mobile app and extension are clients.

---

## 1. Add an endpoint to the Laravel REST API (`/api/v1`)

The product REST API lives in `artifacts/1inme/app/Modules/Api/`.

1. **Route** — add it in `artifacts/1inme/routes/api.php`. Public, viewer-aware
   routes use the `api.optional_auth` middleware; private ones use `auth:sanctum`.
2. **Controller** — add/extend a controller in
   `app/Modules/Api/Controllers/`. `use` the `ApiResponses` trait and return through
   its helpers so every response uses the envelope:
   ```php
   use App\Modules\Api\Controllers\Concerns\ApiResponses;

   class WidgetController extends \Illuminate\Routing\Controller
   {
       use ApiResponses;

       public function index(Request $request)
       {
           $data = $request->validate([/* ... */]);   // 422 envelope on failure
           return $this->ok([/* payload */]);          // {data: ...}
       }
   }
   ```
   - Success: `ok($data)` / `created($data)` / `noContent()`.
   - Errors: `fail($msg, $status, $code, $details)`, or the shortcuts
     `notFound()` / `forbidden()` / `unauthorized()`.
   - Validation throwing `ValidationException` is auto-converted to the `422`
     envelope by `bootstrap/app.php` — you don't catch it yourself.
3. **Output shaping** — if you return a model, add/extend a class in
   `app/Modules/Api/Resources/` (e.g. follow `UserResource::toArray()`), rather than
   returning the Eloquent model directly.
4. **Auth** — to issue a token (login/register flows), use
   `App\Modules\Api\Support\SessionTokenIssuer::issue(...)`.
5. **Document it** — update [`artifacts/1inme/docs/api.md`](../artifacts/1inme/docs/api.md).
6. **Verify** — hit it through the proxy: `curl localhost:80/api/v1/<route>`.

---

## 2. Add a page/feature to the Laravel user or admin app

1. **Route** — add it under `routes/modules/user.php` (or `admin.php`). The user
   area is session-authenticated (`web` guard); admin uses the `admin` guard.
2. **Controller** — add to the matching module:
   `app/Modules/User/Controllers/` or `app/Modules/Admin/Controllers/`.
3. **Service** — put non-trivial business logic in a service class
   (`app/Modules/<Module>/Services/` or the cross-cutting `app/Services/`), not in
   the controller.
4. **View** — add Blade templates under `resources/views/user/...` or
   `resources/views/admin/...`. Reuse Blade components in
   `resources/views/components/` and partials in `resources/views/partials/`.
   - ⚠️ Never edit `resources/views/vendor/` (see [`conventions.md`](./conventions.md)).
5. **Plan gating** — if the feature is plan-limited, resolve capabilities via
   `EffectivePlanFeatures` / `PlanRecommender` rather than hard-coding plan checks
   (see [`common-patterns.md`](./common-patterns.md)).

---

## 3. Add or change a database table/column (Laravel — `public` schema)

Laravel owns the `public` schema. Use migrations:

1. Create a migration in `artifacts/1inme/database/migrations/` following the
   existing timestamp-prefixed naming
   (`YYYY_MM_DD_NNNNNN_describe_change.php`).
2. Update the affected Eloquent model(s) (`app/Models` or
   `app/Modules/*/Models`) — `$fillable`, casts, relations.
3. Run the migration with artisan inside the app
   (`php artisan migrate` from `artifacts/1inme`). In production the deploy runs
   `php artisan migrate --force` automatically.
4. Update factories/seeders in `database/factories` / `database/seeders` if needed.

Do **not** model `public` tables with Drizzle — that's Laravel's territory.

---

## 4. Add a table for a Node service (Drizzle — `drizzle` schema)

Only for data owned by Node services. It must live in the dedicated `drizzle`
Postgres schema or it will break the schema-ownership guarantee.

1. Create a file under `lib/db/src/schema/` (one table per file) and declare it on
   the `drizzle` schema:
   ```ts
   import { pgSchema, serial, text } from "drizzle-orm/pg-core";
   import { createInsertSchema } from "drizzle-zod";
   import { z } from "zod/v4";

   const drizzle = pgSchema("drizzle");

   export const widgetsTable = drizzle.table("widgets", {
     id: serial("id").primaryKey(),
     name: text("name").notNull(),
   });

   export const insertWidgetSchema = createInsertSchema(widgetsTable).omit({ id: true });
   export type InsertWidget = z.infer<typeof insertWidgetSchema>;
   export type Widget = typeof widgetsTable.$inferSelect;
   ```
2. Export it from `lib/db/src/schema/index.ts` (`export * from "./widgets";`).
3. Apply to the DB: `pnpm --filter @workspace/db run push` (or `run push-force`).
4. Rebuild lib declarations: `pnpm run typecheck:libs`.
5. Use it from a service via `import { db, widgetsTable } from "@workspace/db";`.

---

## 5. Add an endpoint to the Node `api-server` (contract-first)

The TypeScript service is contract-first: define it in OpenAPI, generate, then
implement.

1. **Contract** — edit `lib/api-spec/openapi.yaml` to add the path + request/
   response schemas. **Do not change `info.title`** (codegen relies on it being
   `"Api"`).
2. **Generate** — `pnpm --filter @workspace/api-spec run codegen`. This regenerates
   `lib/api-zod/src/generated/**` (Zod) and
   `lib/api-client-react/src/generated/**` (React Query hooks).
3. **Route** — add a router file in `artifacts/api-server/src/routes/` and mount it
   in `src/routes/index.ts`. Validate with the generated Zod schema, mirroring
   `health.ts`:
   ```ts
   import { Router, type IRouter } from "express";
   import { SomeResponse } from "@workspace/api-zod";

   const router: IRouter = Router();
   router.get("/widgets", (req, res) => {
     const data = SomeResponse.parse(/* ... */);
     res.json(data);
   });
   export default router;
   ```
4. **Logging** — use `req.log` in handlers, the singleton `logger` elsewhere. Never
   `console.log`.
5. **Verify** — `pnpm --filter @workspace/api-server run typecheck`, then
   `curl localhost:80/api/<route>` (through the proxy).

Clients get the new typed hook automatically from `@workspace/api-client-react`.

---

## 6. Add a biolink block type (Laravel)

Biolink blocks are a major extension point.

1. **Render template** — add a Blade partial in
   `artifacts/1inme/resources/views/common/blocks/<block>.blade.php` (one file per
   block type; see the existing set, e.g. `link.blade.php`, `image.blade.php`).
2. **First-paint defaults** — register the block's structural style + placeholder
   payload in `app/Modules/User/Support/BlockDefaults.php` so newly-created blocks
   render with friendly placeholder content. Defaults are applied **only at creation
   time** (by the block store controller); saved blocks are never overwritten.
   Colours are left to the active biolink theme — only set structural tokens (radius,
   padding, shadow, etc.) there.
3. **Block type registry / variants** — wire the new type into the block type
   registry and (optionally) variant catalog used by the editor.
4. **Placeholder media** — if the block shows media, add a placeholder asset under
   `artifacts/1inme/public/block-placeholders/`.

See `replit.md` ("Biolink Customization") for the full block styling model.

---

## 7. Add a mobile screen (Expo)

1. Add a file under `artifacts/1inme-mobile/app/` — the path is the route
   (expo-router). Use route groups `(auth)` / `(tabs)` where appropriate.
2. Call the API via the per-domain wrappers in `lib/api/` (e.g. `lib/api/links.ts`),
   which sit on top of `@workspace/api-client-react`. Base URL + bearer token are
   already wired globally in `app/_layout.tsx` via `setBaseUrl` /
   `setAuthTokenGetter`.
3. Verify: `pnpm --filter @workspace/1inme-mobile run typecheck`. Preview via the
   Expo dev domain (`$REPLIT_EXPO_DEV_DOMAIN`), not the shared proxy.

---

## 8. Add a shared library (`lib/*`)

Only when functionality must be shared across artifacts (artifacts must not import
each other).

1. Create `lib/<name>/` with a `package.json` named `@workspace/<name>`.
2. Give its `tsconfig.json` `composite`, `declarationMap`, and
   `emitDeclarationOnly` (it emits declarations).
3. Add it to the root `tsconfig.json` `references` array. If it imports another lib,
   add that lib to its own `references` too.
4. Declare shared runtimes (`react`, `react-dom`) as `peerDependencies`; use
   `catalog:` for any dependency that has a catalog entry.
5. `pnpm run typecheck:libs` to build declarations.

---

## 9. Add a new artifact

Prefer the Replit **artifacts skill** (`createArtifact` / `verifyAndReplaceArtifactToml`
/ `presentArtifact`). New work belongs in an existing artifact only if it's the same
product; otherwise create a new one. Do **not** add leaf artifacts to the root
`tsconfig.json` references, and do not hand-edit `artifact.toml` / `.replit` —
use the skill.

---

## Verifying your work

- TypeScript: `pnpm run typecheck` (full) or
  `pnpm --filter @workspace/<name> run typecheck` (one package).
- Prefer `typecheck` over `build` for verification — `build` needs workflow-provided
  env (`PORT`, `BASE_PATH`) and can fail from bash even when types are fine.
- HTTP: always curl through the proxy at `localhost:80` (except Expo).
- Do **not** run `pnpm dev` at the repo root — use Replit workflows to run apps.
