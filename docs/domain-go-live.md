# Domain go-live checklist — `sayzio.app` as the primary domain

`sayzio.app` is the **primary** platform domain for the 1INME product, and
`1in.me` remains a fully-working, selectable global domain (existing `1in.me`
links keep resolving forever). Both appear in the in-app domain picker. The
Laravel app and mobile app treat **both** as platform hosts; the canonical
short-link prefix / base URL resolves to `sayzio.app` in production.

Almost everything below is **config, not code** — the code already treats both
brand domains as platform hosts and prefers `sayzio.app` as canonical whenever
it is one of the live serving hosts. Going live is mostly a matter of pointing
DNS at the deployment and setting the production env values.

## 0. DNS — point both brand domains at the deployment

On your DNS provider, create records so both apex domains (and their `www.`
hosts) resolve to the Replit deployment:

- `sayzio.app` — apex `A`/`ALIAS`/`ANAME` (or `CNAME` if your provider supports
  CNAME-flattening at the apex) pointing at the deployment target Replit shows
  in **Deployments → Settings → Custom domain**. Add the verification `TXT`
  record Replit displays.
- `www.sayzio.app` — `CNAME` to `sayzio.app` (or to the deployment target).
- `1in.me` / `www.1in.me` — keep the existing records pointing at the same
  deployment so legacy links keep resolving.

After DNS propagates, finish verification in the Replit deployment UI for each
custom domain. TLS certificates are issued automatically by the platform.

## 1. Replit deployment — register the custom domains

In **Deployments → Settings → Custom domains**, add (in this order of priority):

1. `sayzio.app` (primary)
2. `www.sayzio.app`
3. `1in.me` (legacy, keep)
4. `www.1in.me` (legacy, keep)

Replit injects the verified domains into `REPLIT_DOMAINS` (comma-separated). The
app's `PlatformHosts::primary()` automatically prefers the brand primary
(`sayzio.app`) whenever it appears among the serving hosts, so the canonical
base URL becomes `https://sayzio.app` once the custom domain is live — no code
change required.

## 2. Laravel app (`artifacts/1inme`)

Set in the live server / deployment `.env` (the dev `.env` deliberately stays on
`http://localhost:5000` so the Replit preview iframe keeps working):

- `APP_URL=https://sayzio.app`
  - Drives generated links, short URLs, QR-code targets, email links, and
    OAuth/callback URLs. Everything that calls `url()` / `route()` /
    `config('app.url')` follows it — including the global-domain `cname_target`
    fallback in the seeders.
  - `1in.me` links still resolve regardless of `APP_URL`: both brand domains are
    treated as platform hosts in `PlatformHosts`, and alias resolution matches
    the shared platform namespace (links with no domain, plus links bound to any
    admin-global domain).
- `APP_ENV=production`
- `APP_DEBUG=false`
- `SESSION_DOMAIN` — leave `null` unless you need cookies shared across
  subdomains; if so set `.sayzio.app`.
- `SANCTUM_STATEFUL_DOMAINS` — only needed if cookie-based SPA auth is used from
  a browser origin; set to `sayzio.app,www.sayzio.app,1in.me,www.1in.me`. (The
  bearer-token REST API does not require this.)
- DB credentials: `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` /
  `DB_PASSWORD` for the production database.
- Mail/SMTP: `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` /
  `MAIL_ENCRYPTION` and `MAIL_FROM_ADDRESS`.

Run migrations on deploy (`php artisan migrate --force`). This includes the
migration that promotes `sayzio.app` to the **primary** global domain and marks
both `sayzio.app` and `1in.me` as verified+active so they appear in the in-app
domain picker. `1in.me` (and any admin-added domains) remain selectable
non-primary global domains.

## 3. Mobile app (`artifacts/1inme-mobile`)

App-link hosts already cover both brand domains in `app.json`:

- iOS `ios.associatedDomains`: `applinks:sayzio.app`, `applinks:www.sayzio.app`,
  `applinks:1in.me`, `applinks:www.1in.me`.
- Android `android.intentFilters`: `https` hosts `sayzio.app`, `www.sayzio.app`,
  `1in.me`, `www.1in.me`.

For a production build, set the EAS / build-time env to point the API at the
primary domain:

- `EXPO_PUBLIC_API_BASE_URL=https://sayzio.app/api/v1`
  **or** `EXPO_PUBLIC_DOMAIN=sayzio.app`

> Note: the in-app fallback host (`lib/api.ts` `FALLBACK_HOST`) is intentionally
> left as `1in.me` for now, since it is the guaranteed-live host during the
> transition. Once `sayzio.app` is fully live and verified, either flip
> `FALLBACK_HOST` to `sayzio.app` or (preferred) always set
> `EXPO_PUBLIC_DOMAIN`/`EXPO_PUBLIC_API_BASE_URL` in the production build so the
> fallback is never used.

Universal-link / app-link verification on the live server (the
`/.well-known/...` files are served host-agnostically, so they work for every
host that resolves to the app):

- iOS: `https://sayzio.app/.well-known/apple-app-site-association` (and
  `www.sayzio.app`, `1in.me`, `www.1in.me`) for `applinks` against bundle id
  `com.oneinme.app`.
- Android: `https://sayzio.app/.well-known/assetlinks.json` (and the other three
  hosts) for package `com.oneinme.app`. Android App Links require the
  `ANDROID_SHA256_FINGERPRINTS` env to be set on the server to your release
  signing certificate fingerprint(s).
