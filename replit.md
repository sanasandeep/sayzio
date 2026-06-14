# Overview

This project, "1INME," is a pnpm workspace monorepo for a comprehensive SaaS platform specializing in link management. It provides tools for creating, managing, tracking, and branding links, biolinks (mini-websites), and QR codes. The platform aims to serve creators, businesses, and individuals by offering a premium user experience, extensive customization options, detailed analytics, and robust tracking capabilities to enhance online presence and engagement.

# User Preferences

I prefer iterative development. I want to be asked before making major changes. I do not want changes to the folder `artifacts/1inme/resources/views/vendor/`.

# System Architecture

The project utilizes a pnpm workspace monorepo. The architecture is composed of a PHP 8.4 Laravel application (1INME) and Node.js 24 Express 5 API services. PostgreSQL serves as the primary database, integrated with Drizzle ORM for Node.js services and Laravel Eloquent for the 1INME application. TypeScript 5.9 is used across Node.js components, with Zod for data validation and Orval for API code generation from OpenAPI specifications. esbuild is used for CJS bundling.

## 1INME Laravel App (`artifacts/1inme/`)

### Core Architecture
The Laravel application follows a HMVC pattern with `Admin`, `User`, `Common`, and `Api` modules. Authentication uses `admin` and `web` guards (session-based, supporting both password and OTP logins) plus a `sanctum` guard for the bearer-token REST API. A `super_admin` role provides access to a dedicated "Super Admin" section for plan management.

### REST API (`/api/v1`)
Bearer-token REST API powered by Laravel Sanctum, mounted at `/api/v1`. Covers authentication (register/login/logout/me), profile, link CRUD, public biolink resolution with full visibility-tier enforcement (public/registered/followers/subscribers), feed (anon and authed, with follow/subscribe-aware filtering), follow/unfollow, subscriber management, public discovery, and biolink subscribe. Rate-limited on auth and subscribe endpoints. All responses use a unified envelope: `{data: ...}` on success and `{error: {message, code, details?}}` on failure (including validation 422). Optional-auth middleware (`api.optional_auth`) honors a bearer token on public endpoints to apply visibility filtering. Documentation: `artifacts/1inme/docs/api.md`.

### API Usage Metering & Warnings
Developer API-key calls (tokens stamped `client_kind = 'api_key'`) are metered monthly by `MeterApiUsage` middleware against the plan's `api_calls_monthly` allowance (-1/bypass = unlimited); overage beyond the allowance is paid from the coin wallet (1 coin buys `wallet.api_overage_calls_per_coin` calls) and rejected with HTTP 402 when the wallet is disabled or out of coins. Per-(user, month) state lives in `api_usage_counters`. Proactive warnings (email + in-app via the `api.usage_warning` notification type) fire **once per period**: at 80% of allowance, at 100% (now on overage), and when overage can no longer be covered (calls being rejected). Dedup is tracked by `warned_80_at` / `warned_100_at` / `overage_unavailable_notified_at` stamps on the counter row (stamped atomically under the row lock; emails delivered after the transaction commits, best-effort).

### UI/UX Design
The UI features a glassmorphism design with dark/light modes, a purple color palette, Space Grotesk typography, and animated elements. Navigation includes a 3-mode collapsible sidebar, a glassmorphic header with breadcrumbs, live search, and notifications.

### Biolink Customization
The platform offers advanced biolink customization, including:
- **Block Styling System**: Per-block styling with 11 properties, 10 templates, and global themes with overrides.
- **First-Paint Block Defaults**: Newly-created blocks (web or mobile) immediately render with friendly placeholder text in every text field, a working placeholder media URL for media blocks (image/video/audio/document), and a populated `_style` payload (font, colors, radius, shadow, effect). Defaults live in `app/Modules/User/Support/BlockDefaults.php` and are applied only at creation time by `BiolinkBlockController::store()` — saved blocks remain untouched. A `_placeholder` flag drives a "We dropped in placeholder content" banner in both editors; the flag is cleared automatically on first save by `update()`. Variant picks from `BlockVariantCatalog` still fully replace seeded `_style`. Placeholder image assets live in `public/block-placeholders/`.
- **Image Styling System**: 10 mask/crop shapes, customizable borders, and 6 shadow types for image blocks.
- **Trackable Block Links**: Image blocks can have trackable destination URLs with analytics capture.
- **Block Display Settings**: Per-block visibility based on schedule, location, device, OS, browser, and language.
- **Card Container Block**: Allows grouping of child blocks within a customizable card.
- **Biolink Editor**: Split into "Blocks" (drag-and-drop, grid-span, device preview) and "Settings" pages (appearance, layout, block-theme, advanced settings including SEO, Open Graph, PWA, branding, custom CSS/JS).
- **Plan-Gated Features**: Custom branding, favicon, and custom CSS/JS injection based on user plans.
- **Pricing & Smart Upgrade**: Public `/pricing` and in-app `/user/upgrade` share a `PlanRecommender` service (`app/Services/PlanRecommender.php`) that computes per-user usage gauges (links, biolinks, projects, storage, contacts, files), picks a recommended plan via a binding-cap rule (>=70% on any limit) with sensible fallbacks (next tier → most popular), and powers a personalised smart-upgrade banner + Recommended ribbons. The pricing page also renders a feature comparison matrix, a coin packages section, and a polished head-to-head competitor section (compact mode, with an animated VS hero) — all motion respects `prefers-reduced-motion`.

### Functional Systems
- **File Management**: Per-user file storage with quota management and an AJAX API for operations.
- **File Upload Dropzone**: Reusable component for drag-and-drop file uploads.
- **AJAX Block Editor**: All biolink block operations are AJAX-driven for a fluid UX.
- **Subscription System**: Collects and manages email and WhatsApp subscribers from biolinks, with CRUD for subscribers, export, settings, and message composition/sending.
- **Geographic Heatmap**: Displays click origins on link analytics pages using MapLibre GL JS and Carto vector tiles, persisting geographic coordinates for `link_clicks` and `page_sessions`.
- **Forms (1INME Forms)**: A comprehensive form builder with 21 field types, drag-and-drop interface, design customization, notification settings (email, SMS, webhooks), and biolink integration.
- **Digital Cards (VCF)**: Full vCard 3.0 editor expanding beyond basic contact info to include multiple emails, phones, URLs, addresses, and social profiles, with RFC-compliant vCard generation.
- **QR Studio Pro**: A dedicated QR code builder supporting 16 content types with extensive design customization, live preview, and download options. The browser-side SVG engine (`public/js/qr-studio/engine.js`) supports 30+ design templates, per-corner eye styling (independent TL/TR/BL outer+inner shape/color with a combined fallback), a live scannability checker (`analyzeScannability()` grading contrast, logo-vs-ECC coverage, quiet zone, and risky shape combos), framed gradients (frame `<defs>` are merged so gradients survive frames), print-ready PDF export (jsPDF, configurable size/DPI/bleed), and bulk CSV generation (JSZip → ZIP download). Design vocabulary is sanitized through a single shared source of truth — `app/Modules/User/Support/QrCodeDesignSanitizer.php` (`sanitize()`/`defaultDesign()`) — used by both the web and API controllers, and the design/eye/frame catalog (`QrCodeCatalog`) plus type registry (`QrCodeTypeRegistry`) keep PHP and JS in lockstep via shared IDs. Full REST parity at `/api/v1/qr-codes` (index/show/catalog/store/update/bulk/destroy) returns the unified `{data}`/`{error}` envelope and an `encoded` field per QR; mobile (`1inme-mobile`) consumes the catalog/presets and the new endpoints via `lib/api/qr.ts`. Scan analytics are delivered by attaching a trackable link (`link_id`) so scans flow through the existing link-click pipeline (geo/device/heatmap).
- **Social Proof System**: A standalone, embeddable notification widget engine with 7 types (e.g., recent activity, visitor count), customizable design, targeting rules, and biolink integration.
- **Contacts & Dialer**: Per-user address book with two-way Google Contacts sync (People API v1, incremental via syncToken), a number-pad Dialer with search and recent lookups, a Dialer Profile page that resolves a phone number to a 1INME biolink via `linked_identifiers`, and silent auto-attach of biolinks to contacts whose E.164 phone matches a verified user. Detached biolinks are remembered in `contacts.detached_biolink_user_ids` so subsequent syncs don't re-attach them. Scheduled `contacts:sync` runs every 30 min. Calls/email use `tel:` / `mailto:` only (no VOIP).
- **Creator Payouts & 18+ Adult Content (Task #1208)**: Multi-processor creator payout dashboard at `/user/payouts` with 5 hosted-onboarding adapters (Stripe Connect, PayPal, Razorpay Route, CCBill, Segpay). 1INME platform fee is 0% — only the provider's processing fee applies. The registry-driven `PayoutProviderAdapter` returns hosted-onboarding URLs and a graceful "preview" mode when API keys are absent, so the flow is fully demonstrable in dev. The `creator_payment_connections` table tracks one row per (user, provider) with `is_default`, `adult_friendly`, `payouts_enabled`, `charges_enabled`, and `status_reason`. The 18+ adult-content toggle at `/user/adult-content` requires a three-checkbox consent dialog (age, legal/no-minors, processor lock) and stamps `adult_content_enabled_at` + `age_verified_at` for audit. Enabling 18+ auto-demotes a SFW default. Visitor-side, `/@handle` shows a 30-day-cookie age gate before any 18+ profile, and the `/creators` directory hides 18+ profiles unless `?show_adult=1` is set. Admin moderation lives at `/admin/adult-moderation` (suspend/restore the public 18+ tag while preserving consent timestamps). Mobile parity: Expo screen at `app/payouts.tsx` with the same connect/sync/default/disconnect flow, opening hosted onboarding via `expo-web-browser`, plus the inline 18+ consent toggle. Mobile API endpoints live under `/api/v1/payouts` and `/api/v1/adult-content` (auth:sanctum).

# External Dependencies

- **Monorepo Tool**: pnpm
- **API Framework**: Express 5
- **Database**: PostgreSQL
- **ORM**: Drizzle ORM, Laravel Eloquent
- **Validation**: Zod
- **API Codegen**: Orval
- **Build Tool**: esbuild
- **Frontend Frameworks**: Tailwind CSS, Alpine.js
- **Fonts**: Google Fonts CDN
- **Tracking Pixels**: Facebook, Google Analytics, GTM, LinkedIn, Twitter, Pinterest, TikTok, Snapchat, Quora
- **Social Embeds**: Instagram, TikTok, Twitter, Pinterest, Snapchat
- **Music/Streaming Embeds**: Spotify, Apple Music, SoundCloud, Tidal, Mixcloud, Anchor FM
- **Video Platforms Embeds**: YouTube, Vimeo, Twitch, Kick
- **Integration Widgets**: Typeform, Calendly, Discord
- **Payment Gateways**: PayPal
- **Mapping Services**: MapLibre GL JS, Carto (vector tiles), Google Maps, Yandex Maps
- **Storage**: Local public disk, S3 (optional)