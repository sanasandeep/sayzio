---
name: Laravel /api/v1 shadowed by Express api-server on the shared proxy
description: Why curling localhost:80/api/v1/... returns Express 404s instead of the 1inme Laravel API, and how to test Laravel routes.
---

The shared dev proxy (localhost:80) routes by most-specific path. The `api-server`
Express artifact claims `paths = ["/api"]`, while the 1inme Laravel app claims
`paths = ["/"]`. So any request to `localhost:80/api/v1/...` is matched by the
Express api-server (more specific than `/`), NOT Laravel — you get a plain
`Cannot GET /api/v1/...` HTML page or a pino-logged 404 from api-server.

**Why:** Laravel's `/api/v1` REST API (auth, links, qr-codes, etc.) lives inside
the `/`-mounted Laravel app, but `/api` is owned by a different artifact on the
shared proxy. The mobile app reaches Laravel via its own dev domain
(EXPO_PUBLIC_DOMAIN / EXPO_PUBLIC_API_BASE_URL), not the shared proxy path.

**How to apply:** To smoke-test Laravel `/api/v1` routes from the shell, hit the
Laravel app's own port directly: `curl localhost:5000/api/v1/...`. An
unauthenticated sanctum route correctly returns `401 {"error":{"code":"unauthenticated"}}`
there — that confirms the route is registered and middleware runs. Do NOT
conclude a Laravel API route is broken just because `localhost:80/api/v1/...`
404s; that's the proxy shadow, not your route. (`php artisan route:list` is the
authoritative check that a route is registered.)
