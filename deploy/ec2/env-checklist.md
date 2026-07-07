# Environment Variable Checklist (EC2)

A complete audit of every environment variable the Sayzio platform reads,
grep-audited across `artifacts/1inme/config/`, `artifacts/1inme/app/`, and
`artifacts/api-server` + `lib/db`. Use this to build:

- `/var/www/sayzio/artifacts/1inme/.env` — the Laravel app (see the annotated
  `artifacts/1inme/.env.example` as a starting point)
- `/etc/sayzio/api-server.env` — the Express API server (consumed via
  `EnvironmentFile=` in `sayzio-api.service`)

These paths and every variable below are identical on Ubuntu and Amazon
Linux 2023 — nothing in the env files is distro-specific.

Legend:

- **Secret** — treat as sensitive; never commit, restrict file perms (`chmod 640`).
- **DB-backed** — a fallback only: the admin panel stores the live value in
  `app_settings` (Admin → Integrations / Mail settings) and overrides the env
  at boot. You can leave these env vars empty on EC2 and configure them in the
  admin UI instead.
- **Replit-only** — used only by the Replit environment; NOT needed on EC2.

---

## 1. Laravel app (`artifacts/1inme/.env`)

### Core (required)

| Variable | Secret | Notes |
|---|---|---|
| `APP_NAME` | | Display name (default `Sayzio`). |
| `APP_ENV` | | `production` on EC2. |
| `APP_KEY` | ✅ | **Reuse the existing key from the Replit deployment** — it encrypts stored secrets (admin SMTP password, platform service keys, merge tokens). A new key makes those unreadable. |
| `APP_PREVIOUS_KEYS` | ✅ | Only if you ever rotate `APP_KEY` (comma-separated old keys). |
| `APP_DEBUG` | | `false` in production. |
| `APP_URL` | | `https://yourdomain.com` — used in emails, OAuth callbacks, QR links. |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` / `APP_FAKER_LOCALE` | | Defaults `en` / `en` / `en_US`. |
| `APP_MAINTENANCE_DRIVER` / `APP_MAINTENANCE_STORE` | | Defaults `file` / `database`. |
| `PLATFORM_DEFAULT_TIMEZONE` | | Default `Asia/Kolkata`. |
| `BCRYPT_ROUNDS` | | Default 12. |

### Database (required — points at the existing AWS RDS)

| Variable | Secret | Notes |
|---|---|---|
| `DB_CONNECTION` | | `pgsql`. |
| `DB_HOST` | | The RDS endpoint hostname. |
| `DB_PORT` | | `5432`. |
| `DB_DATABASE` | | RDS database name. |
| `DB_USERNAME` | ✅ | |
| `DB_PASSWORD` | ✅ | |
| `DB_SSLMODE` | | `require` for RDS (it enforces SSL). |
| `DB_PERSISTENT` | | `true` (default) reuses the PDO connection per FPM worker — keep it on if RDS is in another region; with same-VPC RDS either setting is fine. |
| `DB_URL` | ✅ | Alternative single-URL form; unused if the discrete vars are set. |

### Logging

| Variable | Secret | Notes |
|---|---|---|
| `LOG_CHANNEL` / `LOG_STACK` / `LOG_LEVEL` | | `stack` / `daily` / `warning` recommended in prod (daily keeps 14 days by default, `LOG_DAILY_DAYS`). |
| `LOG_DEPRECATIONS_CHANNEL` | | `null`. |
| `LOG_SLACK_WEBHOOK_URL`, `PAPERTRAIL_URL`, `PAPERTRAIL_PORT`, ... | ✅ | Only if you use those log channels; otherwise skip. |

### Session / cache / queue (required)

| Variable | Secret | Notes |
|---|---|---|
| `SESSION_DRIVER` | | `database`. |
| `SESSION_LIFETIME` / `SESSION_ENCRYPT` / `SESSION_PATH` / `SESSION_DOMAIN` | | Defaults fine. |
| `SESSION_SECURE_COOKIE` | | Set `true` once HTTPS is live. |
| `CACHE_STORE` | | `database` (no Redis required; Redis vars exist but are unused unless you switch drivers). |
| `QUEUE_CONNECTION` | | `database` — the systemd `sayzio-queue.service` worker drains it. |
| `BROADCAST_CONNECTION` | | `log`. |

### Mail (DB-backed fallback)

Admin → Mail settings (`MailSettings`, `app_settings` storage with encrypted
password) overrides all of these at boot. Set env values only as the initial
fallback before the admin config exists.

| Variable | Secret | Notes |
|---|---|---|
| `MAIL_MAILER` | | `smtp`. |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_ENCRYPTION` (`MAIL_SCHEME`) | | DB-backed. |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | ✅ | DB-backed. |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | | DB-backed. |

### File storage / S3 (DB-backed fallback)

Admin → Integrations (`PlatformServiceSettings`) can store the S3 credentials;
env values are the fallback.

| Variable | Secret | Notes |
|---|---|---|
| `FILESYSTEM_DISK` | | Default disk; `s3` in production. Beware: an explicit `local` here silently overrides config defaults. |
| `USER_CONTENT_DISK` | | `s3` to back user uploads with S3/CloudFront; `local` for on-disk. |
| `ADMIN_ASSETS_DISK` | | Default `admin_assets`; leave unset. |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | ✅ | DB-backed. Prefer an **EC2 instance profile (IAM role)** where possible; the SDK picks it up when these are empty. |
| `AWS_DEFAULT_REGION` / `AWS_BUCKET` / `AWS_URL` / `AWS_ENDPOINT` | | `AWS_URL` = CloudFront domain for public URLs. |
| `AWS_USE_PATH_STYLE_ENDPOINT` | | `true` for dotted bucket names (e.g. `1in.me`). |

### Third-party services (optional; most are DB-backed or preview-mode when absent)

| Variable | Secret | DB-backed | Notes |
|---|---|---|---|
| `GOOGLE_PLACES_API_KEY` | ✅ | ✅ | Reviews import; absent = preview mode. |
| `TRUSTPILOT_API_KEY` | ✅ | ✅ | Reviews import; absent = preview mode. |
| `GOOGLE_CONTACTS_CLIENT_ID` / `GOOGLE_CONTACTS_CLIENT_SECRET` | ✅ | ✅ | Google Contacts sync OAuth. |
| `GOOGLE_CALENDAR_CLIENT_ID` / `GOOGLE_CALENDAR_CLIENT_SECRET` | ✅ | | Google Calendar sync OAuth. |
| `MICROSOFT_CALENDAR_CLIENT_ID` / `MICROSOFT_CALENDAR_CLIENT_SECRET` / `MICROSOFT_CALENDAR_TENANT` | ✅ | | Outlook calendar sync. |
| `GOOGLE_CLIENT_ID` | | | Google sign-in (web OAuth client id). |
| `FACEBOOK_APP_ID` / `FACEBOOK_APP_SECRET` | ✅ | | Facebook login/connected apps. |
| `TWITCH_CLIENT_ID` / `TWITCH_CLIENT_SECRET` | ✅ | | Twitch connected app. |
| `YOUTUBE_API_KEY` | ✅ | | YouTube connected app data. |
| `CONNECTED_APPS_GA_ENABLED` | | | Feature toggle for the GA connected app. |
| `REPLICATE_API_TOKEN` / `REPLICATE_QR_MODEL` | ✅ | ✅ (AiEngineSettings) | AI Artistic QR; absent = preview/disabled. |
| `MSG91_AUTH_KEY` / `MSG91_SENDER_ID` / `MSG91_ROUTE` / `MSG91_TEMPLATE_ID` | ✅ | | SMS OTP via MSG91. |
| `SENDGRID_API_KEY` / `POSTMARK_API_KEY` | ✅ | | Only if you use those mail transports. |
| `WHATSAPP_PHONE_NUMBER_ID` / `WHATSAPP_ACCESS_TOKEN` / `WHATSAPP_APP_SECRET` / `WHATSAPP_WEBHOOK_VERIFY_TOKEN` | ✅ | | WhatsApp Business API (OTP + agent). |
| `WHATSAPP_TEMPLATE_NAME` / `WHATSAPP_TEMPLATE_LANG` / `WHATSAPP_GRAPH_VERSION` / `WHATSAPP_AGENT_ENABLED` | | | WhatsApp plain config. |
| `REVENUECAT_REST_API_KEY` / `REVENUECAT_PROJECT_ID` | ✅ | | Mobile IAP verification. |

Note: **OpenAI / ElevenLabs keys and PayPal credentials are fully admin-DB-backed**
(Admin → Integrations / AI engine settings) — there is no env fallback for
them; configure them in the admin panel.

### Billing / invoicing

| Variable | Secret | Notes |
|---|---|---|
| `MERCHANT_LEGAL_NAME` / `MERCHANT_ADDRESS` / `MERCHANT_COUNTRY` / `MERCHANT_GST_STATE` / `MERCHANT_GSTIN` / `MERCHANT_VATIN` / `MERCHANT_SUPPORT_EMAIL` | | Invoice letterhead identity. |
| `FY_START_MONTH` / `INVOICE_PREFIX` / `REFUND_DEDUPE_SECONDS` | | Defaults fine. |
| `BILLING_ACTIVATION_SECRET` | ✅ | Webhook activation signing secret — copy from current deployment. |

### Platform / security

| Variable | Secret | Notes |
|---|---|---|
| `MASTER_OVERRIDE_PASSWORD_HASH` | ✅ | Super-admin master password bcrypt hash — copy from current deployment if in use. |
| `SANCTUM_STATEFUL_DOMAINS` / `SANCTUM_EXPIRATION_MINUTES` / `SANCTUM_TOKEN_PREFIX` | | Defaults fine; stateful domains default derives from `APP_URL`. |
| `PLATFORM_ROLE_ALERT_ROLES` / `PLATFORM_ROLE_ALERT_RECIPIENTS` | | Role-grant alert routing. |
| `DOMAIN_DRIFT_GRACE_HOURS` | | Custom-domain DNS drift grace (default 168). |
| `SSL_AUTO_ISSUE` | | **EC2-only.** `true` enables automatic Let's Encrypt issuance for verified custom/global domains via the scheduled `domains:issue-certificates` command (README Step 7). Leave unset/false on Replit. |
| `SSL_ISSUE_COMMAND` | | Issuance helper invocation (default `sudo -n /usr/local/sbin/sayzio-issue-cert`). |
| `SSL_CERTBOT_EMAIL` | | Optional Let's Encrypt account email, only needed if no certbot account exists on the box yet. |
| `SSL_ISSUE_TIMEOUT` / `SSL_RETRY_HOURS` / `SSL_ALERT_AFTER_ATTEMPTS` / `SSL_ALERT_COOLDOWN_HOURS` | | Issuance tuning: per-run timeout (300s), retry backoff (1h), admin-alert threshold (3 failures) and re-alert cooldown (24h). Defaults fine. |
| `ANDROID_PACKAGE_NAME` / `ANDROID_SHA256_FINGERPRINTS` / `APPLE_BUNDLE_ID` / `APPLE_TEAM_ID` | | Mobile deep-link association files (`assetlinks.json` / AASA). |
| `MONETIZATION_FORCE_PREVIEW` | | Dev/test toggle — leave unset in prod. |
| `UPLOAD_SCANNER_DISABLED` | | Dev/test toggle — leave unset in prod. |
| `ALLOW_DESTRUCTIVE_DB_COMMANDS` | | **Never set in production.** Guard that unlocks destructive artisan commands in dev. |

### Replit-only (NOT needed on EC2)

| Variable | Why it existed |
|---|---|
| `PORT` | Workflow-assigned port. On EC2, PHP-FPM has no port; the api-server's port is set in `/etc/sayzio/api-server.env`. |
| `REPL_ID`, `REPLIT_*` (`REPLIT_DOMAINS`, `REPLIT_DEV_DOMAIN`, ...) | Replit platform metadata. |
| `DATABASE_URL` | Replit's reserved built-in DB URL — only a fallback in the Node DB lib; use discrete `DB_*` instead. |
| `PHP_CLI_SERVER_WORKERS` | Only for `php -S` / `artisan serve`; PHP-FPM manages workers itself. |
| `BASE_PATH` | Build-time-only input to the marketing-site Vite build (deploy.sh passes it); not a runtime variable. |
| `VITE_KEEP_OUTDIR` | Dev Tailwind watch loop only. |

---

## 2. Express API server (`/etc/sayzio/api-server.env`)

```ini
NODE_ENV=production
PORT=8080
LOG_LEVEL=info

# Same RDS as the Laravel app (discrete vars preferred over DATABASE_URL)
DB_HOST=your-rds-endpoint.rds.amazonaws.com
DB_PORT=5432
DB_DATABASE=yourdb
DB_USERNAME=youruser          # secret
DB_PASSWORD=yourpassword      # secret
DB_SSLMODE=require

# Contact-form pipeline
CONTACT_ADMIN_TOKEN=           # secret — bearer token guarding the admin inbox endpoints
CONTACT_NOTIFICATION_TO=       # email address notified of new contact messages
LARAVEL_BACKEND_URL=https://yourdomain.com   # used to forward to the Laravel admin inbox

# SMTP for contact-form notification emails (optional; skip to disable emails)
SMTP_HOST=
SMTP_PORT=587
SMTP_SECURE=false
SMTP_USER=                     # secret
SMTP_PASS=                     # secret
SMTP_FROM=
```

Replit-only for the api-server: `DATABASE_URL` (fallback), `ALLOW_DESTRUCTIVE_DB_COMMANDS` (dev guard — never set in prod).

Permissions: `sudo chown root:sayzio /etc/sayzio/api-server.env && sudo chmod 640 /etc/sayzio/api-server.env`.

---

## 3. Marketing site

No runtime env — it is a static build. `BASE_PATH` is consumed at **build
time** by `deploy.sh` (`/1inme-com/` for path routing, `/` for a subdomain).
