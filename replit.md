# Overview

"Sayzio" is a pnpm workspace monorepo for a link-management SaaS platform. It lets creators, businesses, and individuals create, manage, track, and brand links, biolinks (mini-websites), and QR codes — with deep customization, detailed analytics, and tracking.

Docs: exhaustive feature catalog in `artifacts/1inme/docs/features.md`; end-user guide in `docs/knowledge-base.md`; REST API reference in `docs/api.md`.

# User Preferences

I prefer iterative development. I want to be asked before making major changes. I do not want changes to the folder `artifacts/1inme/resources/views/vendor/`.

# System Architecture

A pnpm workspace monorepo combining a PHP 8.4 Laravel app (Sayzio) and Node.js 24 / Express 5 API services. PostgreSQL is the primary database (AWS RDS), accessed via Drizzle ORM (Node) and Laravel Eloquent (PHP). TypeScript 5.9 across Node components, Zod for validation, Orval for API codegen from OpenAPI, esbuild for CJS bundling.

Artifacts (see registered artifacts list):
- `artifacts/1inme` — Laravel app (the product)
- `artifacts/1inme-com` — React/Vite marketing site
- `artifacts/1inme-mobile` — Expo/React Native mobile app
- `artifacts/1inme-deck` — slide deck
- `artifacts/api-server` — Express API service
- `lib/*` — shared libraries (`db`, `api-spec`, `api-zod`, `api-client-react`)

## Sayzio Laravel App (`artifacts/1inme/`)

### Core
- HMVC pattern with `Admin`, `User`, `Common`, `Api` modules.
- Auth: `admin` + `web` session guards (password and OTP) plus a `sanctum` bearer-token guard for the REST API. A `super_admin` role unlocks a dedicated Super Admin section.

### REST API (`/api/v1`)
Sanctum bearer-token API covering auth, profile, link CRUD, public biolink resolution (visibility tiers: public/registered/followers/subscribers), feed, follow/subscribe, subscribers, discovery. Rate-limited auth/subscribe endpoints. Unified envelope: `{data}` on success, `{error:{message,code,details?}}` on failure (incl. 422). `api.optional_auth` honors a bearer token on public endpoints for visibility filtering. Docs: `artifacts/1inme/docs/api.md`.

API usage metering: developer API-key calls (`client_kind='api_key'`) metered monthly by `MeterApiUsage` against the plan allowance; overage paid from the coin wallet, else HTTP 402. State in `api_usage_counters`; once-per-period 80%/100%/overage-unavailable warnings (email + `api.usage_warning` in-app).

### UI/UX
Glassmorphism design, dark/light modes, purple palette, Space Grotesk type. 3-mode collapsible sidebar; glassmorphic header with breadcrumbs, live search, notifications. Tailwind is compiled via Vite (`@vite`), not the CDN.

### Biolink Customization
- **Block styling**: per-block styling (11 properties, 10 templates) + global themes with overrides; image styling (10 mask shapes, borders, 6 shadows); trackable image-block destination links; per-block display rules (schedule/location/device/OS/browser/language); card container block to group children.
- **First-paint defaults**: new blocks get placeholder text/media + a seeded `_style`; a `_placeholder` flag drives a banner and clears on first save. Defaults in `app/Modules/User/Support/BlockDefaults.php`, applied only at creation by `BiolinkBlockController::store()`.
- **Editor**: split "Blocks" (drag-and-drop, grid-span, device preview) and "Settings" (appearance, layout, block-theme, SEO/OG/PWA/branding/custom CSS-JS). Custom branding/favicon/CSS-JS are plan-gated.
- **Pricing & smart upgrade**: `/pricing` + in-app `/user/upgrade` share `PlanRecommender` (usage gauges → recommended plan via ≥70% binding-cap rule); pricing page also has comparison matrix, coin packages, competitor section (reduced-motion aware).
- **AI biolink builder**: `AiBiolinkBuilderService` turns a prompt (+images/links) into a full page via OpenAI, constrained to a safe block subset and the user's plan-allowed types; charged against the `biolink_builder` AI-credit feature with auto-refund on parse failure.
- **Onboarding (persona wizard)**: first-run flow presented as discrete staged steps with a visible stepper — Welcome → Pick persona → Choose template → Connect WhatsApp (optional) → Done. Web is `OnboardingController` + `OnboardingSteps` (shared step model) + `onboarding/_stepper` partial (stages driven client-side by Alpine on one page; persona filters templates; apply stamps `onboarded_at` + creates biolink + lands in editor; skip → dashboard; resume-where-you-left-off; onboarding gate/redirect middleware). Mobile mirrors the same staged setup in `app/setup.tsx`, reached from the launch gate when REST onboarding status reports `onboarded_at` null (pre-auth intro splash slides are separate).

### Link Types
`links.type` spans short, biolink, file, qr, event, vcard, social, sms, wifi, pdf and richer page types. The marketing "What you can create" showcase lists 10 headline types; `featuresLinkTypesFromSections()` (`SitePagesContent`) keeps home cards in sync with the Features `link-types` category. A public `/demos` gallery links seeded `demo-type-*` explainer pages.
- **Conversational family** (`conversational`/`slides`/`ai_chat`): distinct types — scripted one-message walk-through, swipeable story, and standalone AI Companion surface (reuses the AI Companion runtime).
- **Resume/Portfolio (`resume`)**: standalone Resume Builder bridged to a shareable link; own tables (`resumes`, `resume_section_items`, `resume_views`) with named versions; `PublicResumeController` + `ResumePdfRenderer`; AI tailoring/cover-letter/import; web + mobile share models.
- **Restaurant Menu (`restaurant_menu`)**: dedicated builder (Categories → Items, physical Tables with per-table QR `?t={code}`); visitor ordering → near-real-time staff Orders Dashboard (Pending→Preparing→Served→Paid/Cancelled). Owner-set coupon codes (single per order) + one menu-level GST/tax (added-on or inclusive) drive a live itemized **estimated** bill (`RestaurantBillCalculator` is the single source of truth; snapshotted on orders); "estimated bill, not actual bill" disclaimer everywhere, no payment collected. Full mobile parity via `/api/v1/restaurant/*` (incl. `/restaurant/{alias}/quote`).
- **Store (`store_menu`)**: dedicated builder mirroring `restaurant_menu` adapted to store vocabulary (Categories → Products), **without** physical tables/per-table QR, **without** online payment (order-request only), and **without** tax/coupon. Visitor ordering (name/contact/note) → near-real-time owner Order Requests Dashboard (New→Accepted→Packing→Ready→Completed/Cancelled); order total is the simple line-item sum, no payment collected. Single store QR; `accepting_orders` pause toggle; optional server-built `wa.me` link; instant alerts via `store.new_order` notification + email. Tables `store_menus`/`store_categories`/`store_products`/`store_orders`/`store_order_items`; full mobile parity via `/api/v1/store/*`.
- **Paid Page (`paid_page`)**: standalone type reusing the creator feed/gating; per-link template in `settings['paid_page']`, gated via `links.visibility`.

### Functional Systems
- **Files**: per-user storage with quotas + AJAX API; reusable drag-and-drop dropzone.
- **Subscriptions**: collect/manage email + WhatsApp subscribers from biolinks (CRUD, export, compose/send).
- **Analytics heatmap**: click origins via MapLibre GL + Carto tiles; coords persisted for `link_clicks` / `page_sessions`.
- **Forms**: builder with 21 field types, design customization, email/SMS/webhook notifications, biolink integration.
- **Digital Cards (VCF)**: full vCard 3.0 editor (multiple emails/phones/URLs/addresses/socials).
- **QR Studio Pro**: 16 content types; browser SVG engine (`public/js/qr-studio/engine.js`) with 30+ templates, per-corner eye styling, scannability checker, framed gradients, PDF export, bulk CSV→ZIP. Design vocabulary sanitized via `QrCodeDesignSanitizer`; catalog/registry keep PHP+JS in lockstep. REST parity at `/api/v1/qr-codes`; scan analytics via an attached trackable `link_id`.
- **Social Proof**: embeddable notification widget engine (7 types) with design, targeting, biolink integration.
- **Contacts & Dialer**: address book with two-way Google Contacts sync (People API, incremental); number-pad dialer, phone→biolink resolution via `linked_identifiers`, silent biolink auto-attach (with detach memory). Bulk CSV/vCard import is a parse→preview→confirm flow (skip rows before commit; large files processed via background job with progress + plan-cap checks). Scheduled `contacts:sync` every 30 min; `tel:`/`mailto:` only.
- **Reviews**: a standalone Reviews page (`reviews`) and an embeddable `reviews_wall` block. `ReviewFeed` merges approved native reviews (public, with attachments + custom answers, honeypot/spam check, optional `ReviewVerifier`) with imported Google Places / Trustpilot reviews (adapters, scheduled `reviews:sync`, preview mode when keys absent). Owner moderation on web + mobile (`/api/v1/me/reviews/*`).
- **Schema Health**: `SchemaHealth` diffs migration files vs the `migrations` table; surfaced via hourly `db:check-pending-migrations` alerts, an admin-dashboard banner, and a `GET /up/schema` probe (503 when out of date). Mobile admins can view drift and run a one-click column repair (`/api/v1/admin/schema-health/*`) with an audit trail. Deploy policy (`.replit-artifact/artifact.toml`): keep serving on `migrate --force` failure.
- **Admin Integrations Hub**: `/admin/integrations` (`IntegrationsController`) consolidates third-party credentials with status badges; `IntegrationCatalog` assembles statuses. `PlatformServiceSettings` brings Google Places/Trustpilot keys, Google Contacts OAuth, and S3 user-content storage under admin control (mirrors `MailSettings`: `app_settings` storage, encrypted secrets, admin→config→env fallback, `applyRuntimeConfig()` at boot so readers need no changes).
- **Admin Mail/SMTP**: `MailSettingsController` + `MailSettings` (encrypted password, runtime `config('mail.*')` override, connection verify + test email). Mobile parity at `/api/v1/admin/mail-settings`.
- **Banned names / reserved handles**: admin-managed blocklist (single + bulk add, restore-defaults, export, conflict listing + force-rename of existing offenders); enforced everywhere a handle is chosen via the `NotBannedName` rule plus a live `AliasAvailability` checker that returns availability + suggested alternatives.
- **Custom domains**: user-owned domains (added + DNS-verified per account) and admin-provided shared **global** domains (`is_global`) coexist; the create-link domain picker (`domains/available`) lists both, flagging verification state and the default host.
- **Creator Payouts & 18+**: `/user/payouts` dashboard with 5 hosted-onboarding adapters (Stripe Connect, PayPal, Razorpay Route, CCBill, Segpay); 0% platform fee; `creator_payment_connections` per (user, provider); preview mode when keys absent. 18+ toggle at `/user/adult-content` requires three-checkbox consent + audit stamps; visitor age gate on `/@handle`; `/creators` hides 18+ unless `?show_adult=1`; admin moderation at `/admin/adult-moderation`. Mobile parity via `/api/v1/payouts` + `/api/v1/adult-content`.

## Sayzio Marketing Site (`artifacts/1inme-com`)
Standalone React + Vite + Tailwind site, separate from Laravel. A **gateway**: no auth/checkout of its own — all login/signup/pricing CTAs route to the main app via `src/config.ts`; copy mirrors Laravel `SitePagesContent`. Ships legal pages, a changelog, official social links, and a blog that reads the live Laravel DB-driven blog at runtime (CORS-open `/blogs/feed.json` + `/blogs/feed/{slug}.json`). Contact form submits via `@workspace/api-client-react` to the Laravel admin inbox (rate-limited, honeypot, `mailto:` fallback).

# External Dependencies

- **Tooling**: pnpm (monorepo), Express 5 (API), esbuild (bundling), Orval (API codegen), Zod (validation)
- **Database / ORM**: PostgreSQL (AWS RDS) with Drizzle ORM (Node) + Laravel Eloquent (PHP)
- **Frontend**: Tailwind CSS, Alpine.js; Google Fonts (CDN)
- **AI / Voice**: OpenAI (chat/embeddings + all AI-credit features), Whisper (STT), ElevenLabs (TTS)
- **Reviews**: Google Places, Trustpilot (adapters; absent keys ⇒ preview mode)
- **Payments**: PayPal; creator payouts via Stripe Connect, PayPal, Razorpay Route, CCBill, Segpay (hosted onboarding)
- **Email/SMTP**: admin-configurable SMTP (DB-backed, overrides runtime mail config)
- **Mapping**: MapLibre GL JS, Carto (vector tiles), Google Maps, Yandex Maps
- **Storage**: local public disk, S3 (optional)
- **Tracking pixels**: Facebook, Google Analytics, GTM, LinkedIn, Twitter, Pinterest, TikTok, Snapchat, Quora
- **Embeds**: Instagram, TikTok, Twitter, Pinterest, Snapchat (social); Spotify, Apple Music, SoundCloud, Tidal, Mixcloud, Anchor FM (music); YouTube, Vimeo, Twitch, Kick (video); Typeform, Calendly, Discord (widgets)
