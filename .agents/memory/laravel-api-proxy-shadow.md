---
name: Laravel /api/v1 shadowed by Express api-server on the shared proxy
description: Why curling localhost:80/api/v1/... returns Express 404s instead of the 1inme Laravel API, and how to test Laravel routes.
---

The shared dev proxy (localhost:80) routes by most-specific path. When the
`api-server` Express artifact claims `paths = ["/api"]`, any request to
`localhost:80/api/v1/...` is matched by Express (more specific than `/`),
NOT Laravel — you get a plain `Cannot GET /api/v1/...` HTML page or a
pino-logged 404 from api-server.

**Current state (fixed):** `artifact.toml` narrows the claim to only
`paths = ["/api/healthz", "/api/contact"]`, so `/api/v1/*` falls through to
the `/`-mounted Laravel app on the shared proxy. The guard
`pnpm --filter @workspace/scripts run check:api-server-paths` (registered as
the `api-server-paths` validation) fails if those paths are ever widened back.

**previewPath matters in PRODUCTION:** the deployed edge router routes by the
artifact's `previewPath` prefix even when `[[services]].paths` is narrow.
With `previewPath = "/api"` every prod `/api/v1/*` request still hit Express
(502 upstream_unavailable) despite narrowed paths; fix was pinning
`previewPath = "/api/healthz"`. The guard script now checks previewPath too.
The Express fallthrough proxy also retries localhost/[::1] variants and
surfaces the fetch error code in the 502 `details.cause` for prod diagnosis.

**Why:** Laravel's `/api/v1` REST API (auth, links, qr-codes, etc.) lives
inside the `/`-mounted Laravel app, but `/api` is owned by a different
artifact on the shared proxy in production. The Express fallthrough proxy
cannot reach localhost:5000 in a published deployment, so a broad `/api` claim
causes 502 `upstream_unavailable` on every `/api/v1/*` request from the
mobile app.

**How to apply:** To smoke-test Laravel `/api/v1` routes from the shell, hit
the Laravel app's own port directly: `curl localhost:5000/api/v1/...`. An
unauthenticated sanctum route correctly returns
`401 {"error":{"code":"unauthenticated"}}` there — that confirms the route is
registered and middleware runs. Do NOT conclude a Laravel API route is broken
just because `localhost:80/api/v1/...` 404s; that's the proxy shadow, not your
route. (`php artisan route:list` is the authoritative check.)
