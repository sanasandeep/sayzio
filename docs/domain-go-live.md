# Domain go-live checklist — `1in.me` as the primary domain

`1in.me` is the single primary domain for the 1INME product. The Laravel app,
mobile app, and browser extension all default to it. Every production-only value
below is **config, not code** — the code already targets `1in.me` (directly or as
the fallback), so going live is a matter of setting these on the live build/server.

> The `1inme.com` **marketing site** (`artifacts/1inme-com`) is intentionally
> untouched and is not part of this checklist.

## 1. Laravel app (`artifacts/1inme`)

Set in the live server `.env` (the dev `.env` deliberately stays on
`http://localhost:5000`):

- `APP_URL=https://1in.me`
  - Drives all generated links, short URLs, QR-code targets, email links, and
    OAuth/callback URLs. Everything that calls `url()` / `route()` / `config('app.url')`
    follows it — including the global-domain `cname_target` fallback in the seeders.
- `APP_ENV=production`
- `APP_DEBUG=false`
- `SESSION_DOMAIN` — leave `null` unless you need cookies shared across
  subdomains; if so set `.1in.me`.
- `SANCTUM_STATEFUL_DOMAINS` — only needed if cookie-based SPA auth is used from a
  browser origin; set to `1in.me,www.1in.me`. (The bearer-token REST API does not
  require this.)
- DB credentials: `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` /
  `DB_PASSWORD` for the production database.
- Mail/SMTP: `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` /
  `MAIL_ENCRYPTION` and `MAIL_FROM_ADDRESS` (sending identity left as-is — change
  only if the user later asks).

Run migrations on deploy (`php artisan migrate --force`). This includes the new
migration that marks `1in.me` as the primary global domain on databases that were
seeded before the change. `sayzio.app` (and any admin-added domains) remain
available as selectable non-primary global domains.

## 2. Mobile app (`artifacts/1inme-mobile`)

The API base resolver falls back to `1in.me` when no env override is set, and the
app-link hosts (iOS `associatedDomains`, Android `intentFilters`), the deep-link
host allowlist, and the expo-router `origin` all point to `1in.me`. For a
production build, set the EAS / build-time env:

- `EXPO_PUBLIC_API_BASE_URL=https://1in.me/api/v1`
  **or** `EXPO_PUBLIC_DOMAIN=1in.me`
  (Either one overrides the fallback. Leave both unset and the app still defaults
  to `https://1in.me`.)

Universal-link / app-link verification on the live server:

- iOS: host `https://1in.me/.well-known/apple-app-site-association` (and
  `www.1in.me`) for `applinks` against bundle id `com.oneinme.app`.
- Android: host `https://1in.me/.well-known/assetlinks.json` (and `www.1in.me`)
  for package `com.oneinme.app`.

## 3. Browser extension (`artifacts/1inme-extension`)

Defaults already point to `1in.me`:

- Default API base: `https://1in.me/api/v1`
- Default web base: `https://1in.me`
- Popup "reset to default" restores the same two values.
- Login handshake content script matches `https://1in.me/extension/handshake*`
  in both the Chrome and Firefox manifests.

Build and package as usual; no per-build env is required. (Users can still
override the API/web base in the popup settings for local testing.)
