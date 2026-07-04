---
name: Prod php -S needs a static-file router
description: Why 1inme production served every image/CSS as HTML, and the server.php router fix
---

# Production `php -S` swallows static assets without a router

**Symptom:** on the LIVE published 1inme site every image (logo, orbit node icons,
mascot) is broken and the page looks unstyled, on BOTH Safari and Chrome — but dev
renders perfectly. Requests to `/branding/*.png`, `/images/zio-nodes/*.png`, and
even `/build/*.css` return HTTP 200 but `Content-Type: text/html` and ~1.1 MB
(the home-page HTML), not the actual file bytes.

**Root cause:** the production run command served the app with
`php -S 0.0.0.0:5000 -t public public/index.php`. Using Laravel's `public/index.php`
as the php-built-in-server ROUTER makes the framework handle EVERY request; it has
no static-file passthrough, so asset URLs get routed into Laravel and come back as
HTML. Dev worked only because it uses `php artisan serve`, whose own router serves
real files first.

**Why not just use `php artisan serve` in prod:** artisan serve spawns a CHILD
`php -S` that strips all env vars except a small allow-list (see
`artisan-serve-env-passthrough.md`), which breaks DB_*/runtime config in production.
So prod must invoke `php -S` directly (env passes through) BUT with a router that
serves statics.

**Fix:** added `artifacts/1inme/server.php` — a tiny router mirroring Laravel's
artisan-serve router: `return false` for an existing file under `public/` (built-in
server streams it with the correct Content-Type), else `require public/index.php`.
Prod run command now ends `-t public server.php` (edited via the artifacts skill
`verifyAndReplaceArtifactToml`, never hand-edited).

**Why:** this bug is invisible in dev and in local `curl localhost:5000` (dev uses
artisan serve). To reproduce/verify, run the EXACT prod invocation
(`php -S 127.0.0.1:PORT -t public public/index.php`) and curl an asset — a
`text/html` content-type on a `.png`/`.css` is the tell. After the fix the same
curl returns `image/png` / `text/css` with real byte sizes.

**How to apply:** any Laravel artifact served by raw `php -S` in production needs a
static-aware router script; pointing php -S straight at `index.php` will silently
serve all assets as HTML. The `URL::forceScheme('https')` fix in
AppServiceProvider is complementary (ensures asset URLs are https, avoiding
mixed-content blocks) but was NOT the cause here.
