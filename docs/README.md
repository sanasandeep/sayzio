# Sayzio Monorepo Documentation

This is the technical documentation for the **Sayzio** project — a pnpm-workspace
monorepo for a link-management SaaS platform (links, biolinks/mini-sites, QR codes,
forms, analytics, and more).

These docs describe how the repository is laid out, how the pieces fit together,
and the conventions you should follow when changing it. They are aimed at engineers
working in the codebase.

> For a high-level **product** overview and user preferences, see the root
> [`replit.md`](../replit.md). This documentation set does not repeat it.

## Documentation index

| Doc | What it covers |
| --- | --- |
| [`README.md`](./README.md) | This index, project overview, and the architecture diagram. |
| [`file-structure.md`](./file-structure.md) | Where everything lives — every top-level folder, each artifact, and each shared lib. |
| [`architecture.md`](./architecture.md) | How the system fits together: services, the shared Postgres DB, the OpenAPI contract pipeline, request routing, auth. |
| [`how-to-add-features.md`](./how-to-add-features.md) | Step-by-step recipes for common changes (new API endpoint, new Laravel page, new DB column, new biolink block, etc.). |
| [`common-patterns.md`](./common-patterns.md) | Reusable patterns the codebase relies on (API envelope, codegen client, logging, block defaults, plan gating). |
| [`conventions.md`](./conventions.md) | Naming, TypeScript/PHP rules, commands, and the do-nots. |

## What is in this repo

The monorepo contains several deployable **artifacts** plus shared **libs**:

| Artifact | Path | Kind | Stack | Route |
| --- | --- | --- | --- | --- |
| **Sayzio** | `artifacts/1inme/` | web | PHP 8.4 / Laravel (HMVC) | `/` |
| **API Server** | `artifacts/api-server/` | api | Node.js 24 / Express 5 / TypeScript | `/api` |
| **Sayzio Mobile** | `artifacts/1inme-mobile/` | mobile | Expo / React Native (expo-router) | `/mobile/` |
| **Sayzio Extension** | `artifacts/1inme-extension/` | (build-only) | Vite browser extension (Chrome/Firefox/Edge) | n/a |
| **Canvas** | `artifacts/mockup-sandbox/` | design | Vite component preview | `/__mockup` |

| Lib | Path | Purpose |
| --- | --- | --- |
| `@workspace/api-spec` | `lib/api-spec/` | The OpenAPI contract (`openapi.yaml`) and Orval codegen config. Source of truth for the typed API. |
| `@workspace/api-zod` | `lib/api-zod/` | Zod schemas generated from the OpenAPI spec (consumed by the Node API server). |
| `@workspace/api-client-react` | `lib/api-client-react/` | React Query hooks + a custom fetch mutator generated from the OpenAPI spec (consumed by mobile/web React clients). |
| `@workspace/db` | `lib/db/` | Drizzle ORM client + schema for Node services. **Owns only the `drizzle` Postgres schema** — Laravel owns `public`. |

The bulk of the product surface area lives in the **Laravel `1inme` app**. The Node
`api-server` is a thin, contract-first REST surface. The mobile app and browser
extension are clients.

## Architecture at a glance

```
                            ┌─────────────────────────────────────────────┐
                            │         Replit shared reverse proxy          │
                            │        (path-based routing, :80 / HTTPS)     │
                            └───────────────┬─────────────────────────────┘
            ┌───────────────────────────────┼────────────────────────────────┐
            │ "/"                            │ "/api"                          │ "/mobile/", "/__mockup"
            ▼                                ▼                                 ▼
   ┌──────────────────┐          ┌────────────────────┐          ┌────────────────────────┐
   │  Sayzio (Laravel) │          │  API Server (Node) │          │  Mobile / Deck / Canvas │
   │  artifacts/1inme │          │ artifacts/api-server│         │  (React / Expo / Vite)  │
   │  :5000           │          │  :8080             │          │                         │
   │                  │          │                    │          │                         │
   │  • web + admin   │          │  • /api/healthz    │          │  Mobile consumes the    │
   │    (session)     │          │  • Zod-validated   │          │  generated React Query  │
   │  • REST API      │          │    routes          │          │  client (api-client-    │
   │    /api/v1       │          │                    │          │  react) over HTTPS.     │
   │    (Sanctum)     │          │                    │          │                         │
   └────────┬─────────┘          └─────────┬──────────┘          └────────────────────────┘
            │ Eloquent                      │ Drizzle (drizzle schema only)
            │ (owns public schema)          │
            ▼                               ▼
   ┌─────────────────────────────────────────────────────┐
   │                  PostgreSQL (shared)                  │
   │   public schema → Laravel migrations (224+)          │
   │   drizzle schema → @workspace/db (drizzle-kit push)  │
   └─────────────────────────────────────────────────────┘

   Contract-first codegen:
   lib/api-spec/openapi.yaml ──orval──▶ lib/api-zod (Zod, used by Node server)
                             └─orval──▶ lib/api-client-react (React Query hooks + fetch)
```

Key points:

- **One Postgres database, two owners.** Laravel migrations own every real table in
  the `public` schema. `@workspace/db` (Drizzle) is restricted to a dedicated
  `drizzle` schema so the two never collide. See [`architecture.md`](./architecture.md).
- **Contract-first API.** `lib/api-spec/openapi.yaml` is the source of truth. Running
  codegen regenerates both the server-side Zod schemas and the client-side React
  Query hooks. Never hand-edit generated files.
- **Two API surfaces.** The Laravel app exposes the rich product REST API at
  `/api/v1` (Sanctum bearer auth); the Node `api-server` is a separate, smaller
  Express service mounted at `/api` (currently health + scaffold). They are distinct
  services routed by the proxy.
- **Path-based routing.** The Replit proxy routes by path prefix to each artifact's
  service. Most-specific path wins (`/api` and `/api/v1` resolve correctly alongside `/`).

## Getting oriented quickly

1. Read [`file-structure.md`](./file-structure.md) to learn where things live.
2. Read [`architecture.md`](./architecture.md) for how requests, data, and codegen flow.
3. When you start making changes, follow the recipes in
   [`how-to-add-features.md`](./how-to-add-features.md) and the rules in
   [`conventions.md`](./conventions.md).

## Related, more specific docs

- [`artifacts/1inme/docs/api.md`](../artifacts/1inme/docs/api.md) — the full
  `/api/v1` REST reference for the Laravel app (endpoints, envelope, auth,
  visibility tiers). This documentation set links to it rather than duplicating it.
- [`artifacts/1inme/docs/knowledge-base.md`](../artifacts/1inme/docs/knowledge-base.md) —
  the end-user knowledge base + FAQ covering every user-facing feature in plain
  language (used as help-center and chatbot training material, not a developer reference).
- [`artifacts/1inme/docs/chatbot-training.md`](../artifacts/1inme/docs/chatbot-training.md) —
  the **Ask Zio** AI assistant training document: customer-facing, plain English,
  user point of view only. No admin/back-office or API detail.
- [`artifacts/1inme/docs/claude-training.md`](../artifacts/1inme/docs/claude-training.md) —
  comprehensive technical training for internal AI assistants (Claude): all
  customer features + REST API surface + internal/admin systems + billing +
  security model.
- [`artifacts/1inme/docs/blade-lint.md`](../artifacts/1inme/docs/blade-lint.md) —
  Blade template linting notes for the Laravel app.
