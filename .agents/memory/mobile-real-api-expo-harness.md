---
name: Real-API expo-web e2e harness pattern
description: How to run a mobile expo-web Playwright harness against the real Laravel API (stock-image pick was the first)
---

Pattern (see `artifacts/1inme-mobile/scripts/test-stock-image-real-api-e2e.mjs`):
- Boot Laravel yourself: `php -S 127.0.0.1:<freePort> vendor/laravel/framework/.../resources/server.php` with cwd=`public/` and `PHP_CLI_SERVER_WORKERS=8`; warm on `/up`. (artisan serve strips DB_* env; php -S inherits.)
- Seed via `php artisan tinker --execute` (demo user sayzioapp@gmail.com + per-run unique alias + stale-prefix prune) and mint a real Sanctum token with `createToken()->plainTextToken`; echo a `SEED_JSON:` marker line.
- Bake `EXPO_PUBLIC_API_BASE_URL=http://127.0.0.1:<laravelPort>` via `acquireServer(label, null, extraEnv)` — must always boot a throwaway server (an APP_URL-warmed server has the wrong baked base URL). Laravel CORS is wildcard on `api/*`, so cross-origin apiFetch just works.
- CloudFront asset CDN serves NO Access-Control-Allow-Origin header, so the WEB `importVaultFileFromUrl` browser-fetch is CORS-blocked. Shim with `context.route(/cloudfront|amazonaws/)` + `route.fetch()` and add the header — real bytes, only the header is added. Native uses FileSystem and has no CORS.
- Run under the managed validation runner; **a SKIP exits 0 and the run reports PASSED — always grep the run's log file for the literal PASS line.**
- `StockImageGalleryPicker` tiles are plain Pressables with `accessibilityLabel` and NO role — locate with `getByLabel`, not `getByRole('button')`.
