# Sayzio Feature Catalog

This is the exhaustive, engineering-verified catalog of **every** feature in the
Sayzio platform: each link type (grouped by the product categories shown in the
"Create Link" picker), every major functional system, the admin/back-office
surfaces, the AI engine, billing, and the cross-surface artifacts (web app,
REST API, mobile app, marketing site, slide deck).

It complements several sibling docs and intentionally does **not** duplicate them:

- **Developer REST API reference** — endpoint-by-endpoint contract:
  [`api.md`](./api.md). Throughout this catalog, REST coverage is summarized and
  cross-linked rather than re-listed.
- **End-user guide & FAQ** — plain-language "what / why / how":
  [`knowledge-base.md`](./knowledge-base.md).
- **Ask Zio training doc** — customer-facing AI assistant training (user point of
  view only): [`chatbot-training.md`](./chatbot-training.md).
- **Claude training doc** — comprehensive technical training for internal AI
  assistants (all features + API surface + internal systems):
  [`claude-training.md`](./claude-training.md).
- **AI credit billing internals** — [`billing-ai-credit-audit.md`](./billing-ai-credit-audit.md).

> **Parity legend.** Each feature notes where it is available:
> **Web** (Laravel app, `artifacts/1inme`) · **REST** (the `/api/v1` Sanctum API,
> see [`api.md`](./api.md)) · **Mobile** (the Expo app, `artifacts/1inme-mobile`).
> "Full parity" means the same capability is reachable on all three surfaces.

---

## Table of contents

1. [Platform overview](#1-platform-overview)
2. [Link types](#2-link-types)
   - [2.1 Everyday links](#21-everyday-links)
   - [2.2 Pages & mini-sites](#22-pages--mini-sites)
   - [2.3 Business & monetization](#23-business--monetization)
   - [2.4 AI-powered](#24-ai-powered)
   - [2.5 Other link surfaces & block-driven types](#25-other-link-surfaces--block-driven-types)
3. [Link management & routing](#3-link-management--routing)
4. [The biolink editor & blocks](#4-the-biolink-editor--blocks)
5. [Functional systems](#5-functional-systems)
   - [5.1 Files / Vault & quotas](#51-files--vault--quotas)
   - [5.2 Subscriptions (Leads)](#52-subscriptions-leads)
   - [5.3 Analytics & geographic heatmap](#53-analytics--geographic-heatmap)
   - [5.4 Forms builder](#54-forms-builder)
   - [5.5 Digital contact cards (vCard)](#55-digital-contact-cards-vcard)
   - [5.6 QR Studio Pro](#56-qr-studio-pro)
   - [5.7 Social Proof (Buzz)](#57-social-proof-buzz)
   - [5.8 Contacts & Dialer](#58-contacts--dialer)
   - [5.9 Reviews](#59-reviews)
   - [5.10 Restaurant menu & orders](#510-restaurant-menu--orders)
   - [5.11 Resume / Portfolio](#511-resume--portfolio)
   - [5.12 Audience, feed & engagement](#512-audience-feed--engagement)
   - [5.13 Inbox, notifications & digests](#513-inbox-notifications--digests)
   - [5.14 Referrals](#514-referrals)
   - [5.15 Calendar sync & followable calendars](#515-calendar-sync--followable-calendars)
   - [5.16 Persona onboarding](#516-persona-onboarding)
   - [5.17 Visitor Type Audience Insights](#517-visitor-type-audience-insights)
6. [Creator monetization & payouts](#6-creator-monetization--payouts)
7. [18+ adult content](#7-18-adult-content)
8. [AI engine & AI features](#8-ai-engine--ai-features)
9. [Pricing, plans, coins & AI credits](#9-pricing-plans-coins--ai-credits)
10. [Teams, workspaces, projects & client portals](#10-teams-workspaces-projects--client-portals)
11. [Security & sessions](#11-security--sessions)
12. [Admin / back-office systems](#12-admin--back-office-systems)
13. [REST API surface](#13-rest-api-surface)
14. [Cross-surface artifacts](#14-cross-surface-artifacts)
    - [14.1 Mobile app](#141-mobile-app-artifacts1inme-mobile)
    - [14.2 Browser extension](#142-browser-extension-artifacts1inme-extension)

---

## 1. Platform overview

Sayzio is an all-in-one link-management SaaS. From one account a creator,
business, or individual can create short links, biolinks (mini-sites), QR codes,
digital contact cards, file/event/resume/menu/review pages, and monetized pages —
then customize, brand, track, and get paid through them, all under a single
public handle (`1in.me/@yourname`).

The catalog of creatable link types is the single source of truth in
`LinkTypeCategories::categories()` and is grouped into four product categories:
**Everyday links**, **Pages & mini-sites**, **Business & monetization**, and
**AI-powered**. Several other link surfaces exist as `links.type` values that are
created from their own tools or as biolink blocks (see
[§2.5](#25-other-link-surfaces--block-driven-types)).

---

## 2. Link types

Every type is created from **Create Link** (Step 1: name + type + optional alias;
Step 2: a focused, type-specific form). Friendly names (shown in the picker) can
differ from the underlying `links.type` value — both are listed below.

### 2.1 Everyday links

> *Quick, single-purpose links you can share anywhere in seconds.*

| Friendly name | `links.type` | What it is |
| --- | --- | --- |
| **Short Link** | `url` | Shorten any URL with a custom alias and click tracking. |
| **File Share** | `file` | Host a downloadable file behind a branded download page + short link. |
| **Event** | `ics` | A calendar event visitors add in one tap, with RSVP collection. |
| **Contact Card** | `vcf` | A digital business card visitors can save instantly (see [§5.5](#55-digital-contact-cards-vcard)). |

**Short Link (`url`)** — destination URL with 301/302 redirect choice; UTM
builder (source/medium/campaign/term/content); password protection;
expiry (date, max-click cap, or expire-on-first-click) with optional expiry URL;
scheduled start; optional interstitial **preview page**; **open in app**
deep-link toggle (plan-gated, defaults on for `url`); smart routing rules;
attachable tracking pixels; A/B test variants (also driven by the browser
extension's "Shorten as A/B test"). *Web · REST · Mobile.*

**File Share (`file`)** — upload served behind a branded **download page**
(`FileLink.show_download_page`); quota-aware; smart rules supported. *Web · REST · Mobile.*

**Event (`ics`)** — generates an add-to-calendar event; **RSVP** collection with
exportable guest list; optional preview page. *Web · REST · Mobile.*

**Contact Card (`vcf`)** — full vCard 3.0 landing page; details in
[§5.5](#55-digital-contact-cards-vcard).

### 2.2 Pages & mini-sites

> *Full, customizable pages that live at a single link — no website needed.*

| Friendly name | `links.type` | What it is |
| --- | --- | --- |
| **Link in Bio** | `biolink` | A mini-site of links, blocks & media on one page. |
| **Slides** | `slides` | A swipeable, story-style deck served from one link. |
| **Restaurant Menu** | `restaurant_menu` | A digital menu with sections, items & prices (see [§5.10](#510-restaurant-menu--orders)). |
| **Store** | `store_menu` | A product catalog with categories & order requests, no payment (see [§5.10b](#510b-store-menu--order-requests)). |
| **Service Booking** | `service_booking` | Bookable services with availability — visitors request a slot (see [§5.11b](#511b-service-booking--appointment-requests)). |
| **Resume / Portfolio** | `resume` | A shareable resume/portfolio page with PDF download (see [§5.11](#511-resume--portfolio)). |
| **Calendar** | `calendar` | A followable calendar of events visitors can subscribe to (see [§5.15](#515-calendar-sync--followable-calendars)). |

`biolink`, `slides`, `restaurant_menu`, `store_menu` and `service_booking` — plus
the AI types `conversational` / `ai_chat` below — make up the **biolink family**
(`Link::BIOLINK_FAMILY`, checked via `isBiolinkFamily()`), which share the biolink
visibility tiers and gating. `biolink` and `slides` also share the block editor,
while `restaurant_menu` / `store_menu` / `service_booking` use their own dedicated
builders. `resume` and `calendar` are **not** in the family — each has its own
standalone builder and public renderer.

- **Link in Bio (`biolink`)** — the flagship page builder; full block catalog,
  per-block styling, global themes, SEO/OG/PWA/branding/custom CSS-JS, AI builder
  and wizard. Detailed in [§4](#4-the-biolink-editor--blocks). *Web · REST · Mobile.*
- **Slides (`slides`)** — swipeable story deck; shares the biolink editor/Step 2
  flow; subject to its own per-type plan toggle (`module_slides`) and cap
  (`max_slides`). *Web · REST · Mobile.*
- **Restaurant Menu (`restaurant_menu`)** — uses its own dedicated builder (not
  the block editor); see [§5.10](#510-restaurant-menu--orders). Toggle
  `module_restaurant_menu`, cap `max_restaurant_menu`.
- **Store (`store_menu`)** — uses its own dedicated builder (not the block
  editor); a product catalog with order requests (no payment, no tax/coupon, no
  tables); see [§5.10b](#510b-store-menu--order-requests). Toggle
  `module_store_menu`, cap `max_store_menu`.
- **Service Booking (`service_booking`)** — uses its own dedicated builder (not
  the block editor); publish a service catalog with weekly availability and a
  bookings dashboard where visitors request a time slot; see
  [§5.11b](#511b-service-booking--appointment-requests). Toggle
  `module_service_booking`, cap `max_service_booking`.
- **Resume / Portfolio (`resume`)** — bridges the link to the user's standalone
  resume builder record (`ensureResume()`); see
  [§5.11](#511-resume--portfolio). Toggle `module_resume`, cap `max_resume`.
- **Calendar (`calendar`)** — a followable calendar of events with an ICS feed
  and optional Google two-way sync; see
  [§5.15](#515-calendar-sync--followable-calendars). Toggle `module_calendar`,
  caps `max_calendars` / `max_calendar_events`; two-way sync additionally requires
  the `calendar_sync` feature.

**Visibility tiers** (biolink-family): **public**, **registered** users only,
**followers** only, **subscribers** only — plus optional password protection.
Visitors who don't meet the tier are blocked or prompted to follow/subscribe.

### 2.3 Business & monetization

> *Grow your reputation and earn from your audience.*

| Friendly name | `links.type` | What it is |
| --- | --- | --- |
| **Bizs Profile / Paid Page** | `paid_page` | A themeable home that auto-shows your posts, tiers & tips. |
| **Reviews Page** | `reviews` | Collect and showcase reviews from your audience (see [§5.9](#59-reviews)). |
| **Brand / Press Kit** | `brand_kit` | A shareable press kit — logo downloads, colours, fonts & brand voice. |
| **Updates / Changelog** | `updates` | A dated announcement feed with optional follower notifications (see [§5.11c](#511c-updates--changelog)). |

**Paid Page (`paid_page`)** — a standalone type that repackages the creator
monetization stack (posts / tiers / PPV / tipping) into a single page. On
creation a starting **template** is seeded into `settings['paid_page']` (catalog
in `PaidPageTemplates`: 10 categories — gradient, neon, minimal, nature, dark,
playful, luxury, animated, retro, glass — with `aurora` as the default). The
page defaults to public and is gated via `links.visibility`. The dedicated
editor lets the owner switch template + gating. Toggle `module_paid_page`, cap
`max_paid_page`. **Mobile** renders Paid Pages natively via template "mobile
tokens" (`PaidPageTemplates::mobileTokens`), with feed reactions/comments reusing
handle-keyed creator endpoints. *Web · REST (`/paid-page/{alias}`, `/paid-page/{alias}/feed`) · Mobile.*

**Reviews Page (`reviews`)** — standalone review-collection surface; details in
[§5.9](#59-reviews). Toggle `module_reviews`, cap `max_reviews`.

**Brand / Press Kit (`brand_kit`)** — a standalone, shareable brand/press kit page
presenting logo downloads, the colour palette, font pairings, brand voice, and
boilerplate copy. It is rendered publicly by `RedirectController` from a template
(`BrandKitPageTemplates`) stored in `settings['brand_kit']`, which can be **seeded
from the owner's saved AI Brand Kit** (see [§8](#8-ai-engine--ai-features)). It is
not part of the biolink family — it has its own public renderer. Toggle
`module_brand_kit`, cap `max_brand_kit_pages` (distinct from the AI brand-kit save
limit `max_brand_kits`). *Web.*

**Updates / Changelog (`updates`)** — a public dated announcement feed for product
updates, release notes, and announcements. Each **entry** carries a title, optional
rich body, optional image, a publish date, and a tag (`feature`, `fix`,
`improvement`, `breaking`, `announcement`). Published entries are shown
chronologically (newest first) on the public page; when an entry is first published,
opted-in followers are notified. Entries can be **drafted** before publishing.
Toggle `module_updates`, cap `max_updates`. *Web · REST · Mobile.*

### 2.4 AI-powered

> *Let AI answer and guide your visitors for you.*

| Friendly name | `links.type` | What it is |
| --- | --- | --- |
| **AI Chatbot** | `ai_chat` | An AI assistant that answers your visitors for you. |
| **Conversational** | `conversational` | A guided, chat-style page that responds as visitors tap. |

- **AI Chatbot (`ai_chat`)** — a full-page AI assistant surface. It reuses the
  AI Chat runtime (placement = page) rather than introducing a new runtime;
  bound to a Chat Widget via the `ai_companion_links` pivot. The **owner** pays for
  visitor chats (AI credits), not the visitor. Toggle `module_ai_chat`, cap
  `max_ai_chat`. See [§8](#8-ai-engine--ai-features). *Web · REST · Mobile.*
- **Conversational (`conversational`)** — a scripted, one-message-at-a-time
  walk-through of your links; part of the conversational family alongside
  `slides`. Toggle `module_conversational`, cap `max_conversational`. *Web · REST · Mobile.*

### 2.5 Other link surfaces & block-driven types

Beyond the create-picker catalog, `links.type` spans additional values used by
other tools or surfaced as biolink blocks / QR content types:

- **QR Code** — designed in [QR Studio Pro](#56-qr-studio-pro); a QR attaches a
  trackable `link_id` so scans flow into analytics.
- **Product / Storefront** — sell digital/physical products with native
  checkout; available as a biolink **Product** block (gated by the `ecommerce`
  plan feature) and as `/store/*` routes; manage **Orders** & fulfillment. See
  [§6](#6-creator-monetization--payouts).
- **Social** — link/manage connected social accounts.
- **SMS / WiFi / PDF** — tap-to-text, tap-to-join-WiFi, and PDF viewer surfaces,
  also expressible as QR content types and biolink blocks.
- **Splash / RSVP** — interstitial splash pages and RSVP collection surfaces.

---

## 3. Link management & routing

All applicable to short links and (where relevant) the broader link set.

- **Aliases** — every link has a primary `alias` (on the link row); **additional
  aliases** (`link_aliases`) serve the same page with no redirect. Host-aware
  resolution scopes aliases per custom domain (`Link::resolveByAlias`).
- **A/B testing** — weighted variants (`ab_variants`) with sticky assignment;
  declare a **winner** (`settings.ab_test.winner_variant_id`) to promote it.
  Biolink **layout** A/B tests (`biolink_experiments`) snapshot whole-page
  variants for the public renderer.
- **Smart links / routing rules** — route visitors by **country/geo**, **device**
  (mobile/tablet/desktop), **language**, **time-of-day window** (timezone-aware),
  or **A/B split**. Works on every link type.
- **Geo & device targeting** — country restrictions / blocklist
  (`isCountryBlocked`), device targeting allowlist (plan-gated).
- **Scheduling & limits** — scheduled start (`start_at`), expiry date, max-click
  cap, expire-on-first-click, and a **daily active window** (multi-slot,
  per-day, timezone-aware) with a computed "next opening". An inaccessible link
  surfaces a typed `unavailabilityReason` (inactive / expired / limit_reached /
  scheduled / closed_hours).
- **Custom domains** — add & verify your own domain (DNS records) or use shared
  **global domains** tagged per plan (`Domain::availableTo`).
- **Splash pages ("Intros")** — reusable interstitial pages shown before the
  destination; reconciled with per-type preview pages via `interstitialMode()`.
- **Link insurance** — monitors a destination on a cadence and auto-fails-over to
  a backup URL on consecutive failures, auto-restoring on recovery; tracks
  primary vs failover serve counts.
- **Auto-pixel** — auto-fire workspace tracking pixels (Meta/TikTok/Google Ads)
  on click.
- **Moderation** — admin spam/abuse moderation state with reason/note and an
  appeal flow.
- **Backlinks radar** — tracks where your links are shared across the web (works
  with the browser extension).
- **Embeddable link codes** — every link exposes anonymous, CORS-open embed
  endpoints (`/embed/link/{alias}/card`, `/iframe`, `/embed.js`, plus an `OPTIONS`
  preflight) so a link can be dropped into any external site: page-style types
  resolve to an **iframe** (302 to the short URL), action types (short / file /
  event / contact) render a compact intent **card**, and `embed.js` injects a
  sized iframe loader. Non-public links fall back to a "view on site" prompt
  (`PublicEmbedController`; copy uses the `$link->type_label` accessor).
- **Bulk biolink mail-merge** — generate many biolink pages at once from a pasted
  or uploaded spreadsheet, substituting `{{token}}` placeholders per row
  (`BulkBiolinkController` + `MailMergeSheet`). The batch is gated against **both**
  `max_links` and `max_biolinks`, and the locked starting template is chosen by
  plan tier. Entry points mirror the bulk-URL flow (the create hub + the index).
- **My Links list + CSV export** — the link list page paginates (per-page choices
  15/30/50/100 on web) with a "Showing X–Y of Z" count, and exports the current
  (filtered) list to CSV from web (`/user/links/export`), REST
  (`GET /api/v1/links/export.csv`) and mobile (share sheet). The export honours the
  active `type`/search filters, is streamed and chunked for large accounts, and is
  **not** plan-gated — exporting your own link list is data portability, distinct
  from the plan-gated analytics CSV exports (see §5).

*Web · REST (link CRUD, rules, A/B, NFC writes, list CSV export) · Mobile.*

---

## 4. The biolink editor & blocks

The editor is split into **Blocks** and **Settings** pages.

**Blocks page** — block picker organized by category; drag-and-drop reorder;
drop blocks inside **Card** / **Grid** containers with per-child **grid span**;
device preview (mobile/tablet/desktop). New blocks arrive with **first-paint
defaults** (`BlockDefaults`): placeholder text/media + a seeded `_style` and a
`_placeholder` flag that drives a banner and clears on first real edit
(defaults applied only at creation by `BiolinkBlockController::store()`).

**Block catalog** (`BiolinkBlock::TYPES` + `BlockTypeRegistry`; variants in
`BlockVariantCatalog`) — grouped in the picker:

- **Essentials** — Link Button, Featured Link, Heading / Logo Heading, Rich Text,
  Markdown, Bulleted / Numbered List, Pricing List, Alert Banner, Badge,
  Divider / Spacer, Link Group.
- **Layout & profile** — Card Container, Grid / Auto-Fit Grid, Card Carousel /
  Scrolling Cards, Profile Card (Classic / Cover / Stats / Badges identity
  layouts).
- **Media** — Image / Image Grid / Image Slider (10 mask shapes, borders, 6
  shadows, trackable destination link), Video / Header Video, Audio Player /
  Playlist, File Download, plus embeds (YouTube, Vimeo, Spotify, Apple Music,
  SoundCloud, Instagram, TikTok, X/Twitter, Pinterest, and more).
- **Engagement** — FAQ (simple & accordion), Poll, Quiz (live results),
  Testimonials, Reviews / Reviews Wall, Timeline, Chat Widget (embedded chatbot),
  Buzz / Social Proof.
- **Commerce** — Product / Service, Catalog / Storefront, Coupon, Limited Offer
  (countdown), Donation, Buy Me a Coffee, Ko-fi, Patreon, **Tip Jar** (native
  preset-amount tipping via the platform payment stack; 0% platform fee; settings:
  `title`, `message`, `button_text`, `amounts` array, `allow_custom`).
- **Contact & lead capture** — Email Collector / Phone Collector, Contact Form,
  WhatsApp Chat / Button / Number, Direct Message (to your Sayzio inbox).
- **Social profiles & feeds** — Social Icons / Hub, platform feeds (YouTube,
  Instagram, TikTok, X), RSS Feed.
- **Maps & location** — Map, Yandex Map, and a **Location** block (`map_location`)
  with an interactive pin **picker**: drag-to-place on a vendored Leaflet map with
  Nominatim forward/reverse geocoding (`public/js/map-pin-picker.js`), storing
  address + lat/lng + label + zoom + an optional "Get directions" action. Mobile
  uses a WebView map that posts the chosen point back; coordinates stay plain
  strings/numbers (no PHP shape change).

**Per-block styling & display rules** — each block has 11 style properties with
10 ready-made templates, plus global themes that individual blocks may override;
**display rules** show/hide a block by schedule, location, device, OS, browser,
or language. Block layout placements are kept in lockstep across renderer,
sanitizer allowlist, catalog version, and mobile keys: **button-style layouts**
(the `_style.link_layout` variant — e.g. `plain_text`, `image_cover` — read by the
public renderer; a value missing from the sanitizer allowlist is silently
stripped on save) and **profile-card identity layouts** (dispatched on
`_style._profile_layout`, falling back by block type).

**Settings page** — Appearance (background color/gradient/image/video, font,
text color); Layout (max-width, padding, per-device spacing); Block theme (global
theme + pre-designed templates; save looks as **themes**; schedule a theme for a
date range); SEO (title/description/keywords); Open Graph; **PWA** (installable
manifest); Branding (custom favicon + "Powered by Sayzio" toggle, plan-gated);
Custom CSS/JS (plan-gated). SEO trio / favicon / OG image are canonical Link
columns.

**Rendering** — all public blocks (top-level + card children) dispatch through a
single unified renderer (`common/partials/biolink-block-render`); render coverage
is validator-enforced so no block placement renders blank.

**AI builder & wizard** — see [§8](#8-ai-engine--ai-features). The **wizard**
(Category → Page type → Industry → Questions) is stateless on mobile and reuses
the same question/generator services as web.

*Web · REST (block CRUD, taps, poll votes) · Mobile (native create/edit/theme).*

---

## 5. Functional systems

### 5.1 Files / Vault & quotas

Per-user storage ("Vault") for images, video, audio, documents.

- **Quotas** — bytes-used vs plan limit, with auto-downscaling / re-optimization
  to reclaim space.
- **AJAX dropzone** — reusable drag-and-drop uploader; plus a **URL import** mode
  (with SSRF protection) that fetches a remote asset into the Vault.
- **Secure serving** — files served via an access-controlled route; publicly
  reachable only when referenced by an active biolink, form, or splash page.
- **Security scanning** — virus/phishing scan queue; flagged files are blocked
  unless the owner explicitly confirms.

*Web · REST · Mobile (used by uploads across surfaces).*

### 5.2 Subscriptions (Leads)

Collect & manage email + WhatsApp subscribers from biolink subscribe blocks.

- **Multi-channel CRUD** — name/email/WhatsApp (channel or number); search,
  filter by type/status/source, manual status toggle.
- **Compose & send** — broadcast to a filtered segment via custom SMTP and/or
  WhatsApp; double opt-in and customizable **welcome email** with per-user SMTP.
- **Export** — CSV (respects the current filter), including source + timestamps.

*Web · REST · Mobile.*

### 5.3 Analytics & geographic heatmap

Workspace-wide (**Stats**) and per-link analytics.

- **Clicks/visits** — total + unique over time; **live visitors** indicator;
  new-vs-returning detection (IP first-seen).
- **Geographic heatmap** — click/visit origins on an interactive vector-tile map
  (MapLibre GL + Carto); coordinates persisted on `link_clicks` and
  `page_sessions`.
- **Block-level analytics** — taps/clicks on individual biolink blocks.
- **Source attribution** — referrers, UTMs, devices, browsers, OS; source tags
  include `web`, `qr`, `ar`, NFC.
- **Retention** — returning-visitor cohorts incl. follower/subscriber segments.
- **Exports** — RSVPs and poll/quiz results.
- **Reset** — clear a link's counters for a clean start.
- **Pixel tracking** — Meta, Google Analytics, GTM, LinkedIn, X, Pinterest,
  TikTok, Snapchat, Quora.
- **CSV export (plan-gated)** — link click logs, follower/subscriber lists, slide
  analytics, and the creator-stats dashboard export to CSV only when the plan's
  `analytics_export` feature is on; otherwise the export action is blocked
  (mobile reads the same capability). The **My Links list** export (§3) is
  separate and always available on every plan.
- **History retention (per plan)** — how far back analytics can be viewed is
  bounded by the plan's `stats_retention_days` (default 365; values below 30 are
  raised to 30 on save; `-1` = kept forever). A scheduled stats-history pruning
  command (`PruneStatsHistory`, backed by `StatsRetentionPolicy`) trims older
  click/session rows to the **global maximum** retention across active plans,
  under an `stats.hard_max_days` admin floor; it defaults to a no-op when
  retention is unconfigured, so it never mass-deletes unexpectedly.
- **Live heatmap (REST)** — the web SSE heatmap stream has a pollable mobile
  parity endpoint that returns recent coordinate points since a cursor (the first
  poll seeds the last few minutes; later polls return only newer points). See
  [`api.md`](./api.md).
- **Scale & retention internals** — the click hot path is decoupled (async
  writes, counter-delta folding, daily rollups, operator-gated table
  partitioning, and a retention hard cap). The full operations guide lives in
  [`scaling-tracking.md`](./scaling-tracking.md) and is not duplicated here.

*Web · REST (`/biolinks/{alias}/visit`, `/tap`, `/links/{id}/analytics`,
`/links/{id}/heatmap`, `/links/{id}/heatmap/live`) · Mobile.*

### 5.4 Forms builder

Drag-and-drop builder with **21 field types** (text, email, phone, number,
dropdown/select, radio, checkbox, rating, scale, signature, file upload, date,
plus structural sections / page breaks for multi-step forms).

- **Design** — themed editor (Light/Dark/Glass) + custom CSS.
- **Notifications** — email (custom SMTP), SMS, and webhooks (custom
  method/headers).
- **Integration** — public URL, JS/iframe embed, or biolink block.
- **Submissions** — unread/starred filtering, CSV export, and a per-submitter
  eraser for GDPR.
- **Paid forms** — collect payment on submission in two ways that can combine: a
  **fixed** price, or **per-field** pricing (`mode = per_field`) where individual
  fields carry `option_prices` (stored in minor units) so the charge varies with
  what the submitter selects, on top of an optional base fee. A dedicated
  **Pricing / Package** field stores a structured `_pricing` breakdown on the
  submission, which every submission consumer (CSV export, notification emails,
  owner UI) special-cases; the per-field and pricing-field charges sum together.
  The builder is the only surface that works in dollars — totals/line items are
  computed server-side in cents.

*Web · REST · Mobile.*

### 5.5 Digital contact cards (vCard)

vCard 3.0 editor and shareable digital business card.

- **Rich profile** — multiple emails / phones / URLs / addresses, social
  profiles, profile photo.
- **Save Contact** — visitors download a standards-compliant `.vcf`.
- **Smart features** — smart-redirect rules, scheduling, optional themed preview
  page before download.

*Web · REST · Mobile.*

### 5.6 QR Studio Pro

Browser-side SVG QR engine with deep design control and live preview.

- **16 content types** (`QrCodeTypeRegistry`): text, url, phone, sms, email,
  whatsapp, facetime, location, wifi, event, vcard, crypto, paypal, upi, epc, pix.
- **Design** (`QrCodeCatalog`): 30+ design templates/presets, a large library of
  dot shapes, per-corner **eye styling** (each of the three eyes: own outer/inner
  shape + color), logo embedding, gradient + framed call-to-action frames, font
  catalog.
- **Scannability checker** — grades contrast, logo-vs-error-correction coverage,
  quiet zone, and risky shape combinations, warning before you create a code that
  won't scan.
- **Export** — PNG, SVG, print-ready **PDF** (configurable size/DPI/bleed), and
  **bulk CSV → ZIP** for many codes at once.
- **Tracking** — attach a trackable `link_id` so scans feed geo/device/heatmap
  analytics. Design vocabulary is sanitized (`QrCodeDesignSanitizer`); the
  PHP + JS catalogs/registries are kept in lockstep.

*Web · REST (`/api/v1/qr-codes`) · Mobile.*

### 5.7 Social Proof (Buzz)

Embeddable notification-widget engine to lift conversions.

- **7+ types** — recent activity, live counter, informational, coupon, collector
  (email/phone), custom HTML, review popups.
- **Design & targeting** — animation, colors, shadow, position; delay/interval;
  per-device / per-page display logic.
- **Integration** — pin to specific biolinks or globally via one embed script;
  pin a notification as a **badge** on the public Creators directory.
- **Impression metering (per plan)** — Buzz views are capped monthly by the
  plan's `max_buzz_impressions` (default unlimited / `-1`). Usage is tracked in a
  period-scoped counter (`BuzzImpressionMeter` / `BuzzImpressionCounter`, separate
  from the cumulative widget impression count); once the monthly allowance is
  used up, the public widget config stops serving until the next period.

*Web · REST · Mobile.*

### 5.8 Contacts & Dialer

A personal CRM plus an in-app dialer with identity resolution.

- **Google Contacts sync** — two-way, incremental, background sync via the People
  API (`GoogleContactsSyncService`); scheduled `contacts:sync` every 30 min. The
  user connects/disconnects via a hosted OAuth flow and can trigger a manual sync;
  status (connected account, last-sync time, in-progress flag) surfaces on web and
  the mobile API.
- **Connected apps (CRM sync)** — plan-gated on `connected_apps`. Two-way sync
  with **Salesforce, HubSpot and Zoho**: push new leads, subscribers and form
  submissions to the CRM and pull CRM contacts back into Sayzio (`CrmSyncService`,
  `PushLeadToCrmJob`), with field mapping and a scheduled background sync. Also
  **forwards click & conversion events to Google Analytics 4**
  (`ForwardAnalyticsEventJob`). Managed at `/user/settings/connections/apps` on
  web and `/api/v1/connected-apps` on mobile; the admin supplies each provider's
  OAuth credentials in the Integrations Hub. Included on Business and higher, or
  sold as the **Connected Apps (CRM Sync)** add-on on Professional+.
- **Dialer** — number pad with **T9 smart-search** (keypad-spelled names), speed
  dial, recents/frequent; call logging with outcomes/notes. `DialerData` is the
  single read/transform source for web + API.
- **Identity resolution & biolink auto-attach** — resolve a phone number to a
  Sayzio biolink profile (`/dialer/lookup`); contacts whose verified phone matches
  a registered user get that user's biolink **auto-attached** to the contact via
  `linked_identifiers`. Auto-attach has **detach memory** — if you manually detach
  a biolink it won't be re-attached on the next sync. A contact can also carry a
  **manual profile** (channels / socials / location) you set by hand.
- **Plan-based contact limits** — the address book is capped by the plan's
  `contacts_max` (and lead) allowance, enforced on create, bulk add, and import.
- **Bulk-import preview workflow** — CSV / VCF import is staged: the upload is
  **parsed** into a token-keyed preview where you fix or skip individual rows
  before committing; **confirm** enqueues the import job, and large imports run
  async with pollable progress (rows read/processed, created/updated/error counts).
  Calls/emails open the device's native `tel:` / `mailto:` (no in-app VOIP).
- **Scan a card or brochure** — AI tool (see [§8](#8-ai-engine--ai-features))
  that extracts contact details + logo from a photo/PDF to save a contact and/or
  seed a biolink draft.
- **Zio Dialer (standalone companion app)** — the T9 dialer, quick channels and
  caller-ID experience is also distributed as its own dedicated mobile app
  download, in addition to being built into the main Sayzio app; both surfaces
  read/write the same account data.

*Web · REST · Mobile.*

### 5.9 Reviews

A Google-style reviews system, two ways: the standalone **Reviews Page**
(`reviews` link type) and the **Reviews Wall** biolink block.

- **Native reviews** — star rating (1–5) + text, **no login required**; optional
  attachments (image/audio/video via `ReviewMedia`); custom questions
  (`ReviewQuestion` / `ReviewAnswer`); honeypot + IP/text **spam check**; optional
  customer **verification** (`ReviewVerifier`) that trusts matchable reviewers
  (email link / subscriber / contact) and holds back unverified ones; preview
  mode when provider keys are absent.
- **Imported reviews** — Google Places + Trustpilot adapters merge read-only into
  the same feed; scheduled `reviews:sync`.
- **Owner moderation** — approve, hide, pin, reply, delete.

*Web · REST (public `GET /reviews/{alias}`, `/summary`, `POST /reviews/{alias}`;
owner `/me/reviews/*`) · Mobile (full moderation parity).*

### 5.10 Restaurant menu & orders

A dedicated builder for the `restaurant_menu` type (does not use the block
editor).

- **Structure** — `RestaurantMenu` → categories → items (name, description,
  price, photo); display options, order mode, currency, accent color.
- **Tables** — physical tables each with a unique code and per-table QR
  (`order_url?t={code}`) so an order is tied to its table.
- **Visitor ordering** — browse + Place Order (customer name, notes, quantities).
- **Coupons & estimated bill** — the owner sets per-menu **coupon codes**
  (percentage or fixed amount, single coupon per order) and one menu-level
  **GST/tax** rate (added-on or tax-inclusive, custom label). Visitors see a live
  itemized estimate — subtotal, coupon discount, GST line (or "incl." note), and
  estimated total — computed by `RestaurantBillCalculator` (single source of
  truth). Every surface carries an **"estimated bill, not the actual bill"**
  disclaimer; no payment is collected.
- **Orders Dashboard** — near-real-time staff workflow with incremental polling;
  statuses Pending → Preparing → Served → Paid/Cancelled (plus
  confirmed/delivered/completed states). Each order shows the snapshotted
  coupon/discount/GST breakdown.

*Web · REST (public `GET /restaurant/{alias}`, `POST /restaurant/{alias}/quote`,
`POST /restaurant/{alias}/order`, `GET /restaurant/order/{token}`; owner orders +
`/poll` + status PATCH) · Mobile (full native builder + ordering with live bill
+ orders polling).*

### 5.10b Store menu & order requests

A dedicated builder for the `store_menu` type (does not use the block editor).
Mirrors the restaurant menu adapted to store vocabulary — **products** instead of
dishes — **without** physical tables/per-table QR, **without** online payment
(order-request only), and **without** tax or coupons.

- **Structure** — `StoreMenu` → categories → products (name, description, price,
  photo, out-of-stock flag); display options, order mode, currency, accent color.
- **Single store QR** — one QR for the whole store (no per-table codes).
- **Visitor ordering** — browse + Request Order (customer name, contact, note,
  quantities). The total is the simple sum of line items; **no payment is
  collected** — the order is a request the owner fulfils offline.
- **Order Requests Dashboard** — near-real-time owner workflow with incremental
  polling; statuses New → Accepted → Packing → Ready → Completed (or Cancelled).
- **Pause intake** — an `accepting_orders` toggle lets the owner stop taking new
  requests without unpublishing the store.
- **Optional WhatsApp** — when the owner sets a WhatsApp number and enables order
  mode, a `wa.me` deep link is built server-side. Instant owner alerts via the
  `store.new_order` notification + email.

*Web · REST (public `GET /store/{alias}`, `POST /store/{alias}/order`,
`GET /store/orders/{token}/status`; owner menu CRUD + orders + `/poll` + status
POST) · Mobile (full native builder + ordering + requests polling).*

### 5.11 Resume / Portfolio

Standalone resume builder bridged to a shareable `resume` link.

- **Versions** — multiple named versions; set a default; duplicate/delete.
- **Sections** — experience, education, skills, projects, certifications, awards,
  languages (own tables: `resumes`, `resume_section_items`, `resume_views`).
- **Publishing** — public/private + password + expiration; a stable
  `/{handle}/resume.pdf` endpoint (`ResumePdfRenderer`); point a link at a
  specific version.
- **AI tools** — tailor-to-a-job, cover-letter generation, import an existing
  resume; ATS-readiness check.

*Web · REST (`/resume`, `/resume/versions`, header/summary/template/color,
items CRUD + reorder, publishing) · Mobile (shares the `ResumePresenter`).*

### 5.11b Service booking & appointment requests

A dedicated builder for the `service_booking` type (does **not** use the block
editor) that turns a link into an appointment-request page.

- **Structure** — a `ServiceBooking` root (mode, slot length, lead time, how many
  days ahead the calendar opens, timezone, currency, accent colour, optional
  GST/tax in `settings`) → **categories** → **services** (name, description,
  price, duration, photo, unavailable flag).
- **Availability** — weekly recurring windows
  (`service_booking_availability_rules`) plus specific **blocked dates**; the
  provider is treated as a single resource (one booking at a time).
- **Visitor flow** — browse services, pick one or more, see a live duration +
  estimated bill, then choose an open slot and submit contact details. Slots are
  computed by `SlotAvailabilityService`, which excludes anything outside the
  availability windows, before the lead time, beyond the booking window, or
  overlapping an existing pending / confirmed / completed request. A submitted
  request lands as **pending** with a `public_token` the visitor uses to track
  status.
- **Bookings Dashboard** — near-real-time owner workflow with incremental polling;
  statuses **Pending → Confirmed → Completed** (or **Declined** / **Cancelled**).
  Terminal cancel/decline transitions release the slot back to the public calendar.
- **Notifications** — owner alert on each new request
  (`service_booking.new_request`; in-app + push + email); the visitor gets an
  immediate confirmation email, status-change emails, and an optional WhatsApp
  click-to-chat link.
- **Paid bookings** — each service can optionally require upfront payment with three
  `payment_mode` values: `none` (request only, no payment), `deposit` (partial
  amount collected at booking), or `full` (full service price collected). Deposit
  can be `fixed` (flat amount) or `percent` (percentage of the service price),
  controlled by `deposit_type` + `deposit_value`. Payment is processed via the
  owner's connected payout provider; no payment is collected if `payment_mode` is
  `none`.
- **Appointment reminders** — `reminder_lead_minutes` is an array of minutes-before
  values (e.g. `[1440, 60]` for 24 h and 1 h ahead) at which the visitor receives
  an automated reminder. Reminders are sent via the scheduled
  `service_booking:send-reminders` command.

*Web · REST (public `GET /service-booking/{alias}`, `POST /service-booking/{alias}/slots`,
`/quote`, `/book`, `GET /service-booking/bookings/{token}/status`; owner
`/service-booking/links/{link}/bookings` + `/poll` + status + full `/config/*`
CRUD, including `/config/settings` accepting `payment_mode`, `deposit_type`,
`deposit_value`, `reminder_lead_minutes`) · Mobile (native builder, ordering &
bookings polling).*

### 5.11c Updates / Changelog

A dated announcement feed for the `updates` link type. Each **entry** carries:
a `title`, optional Markdown-rendered `body`, optional `image`, a `published_date`,
a `status` (`draft` or `published`), and a `tag` (`feature` / `fix` /
`improvement` / `breaking` / `announcement`).

- **Public renderer** — entries appear newest-first; visitors can subscribe to
  be notified of new entries.
- **Owner dashboard** — create, edit, and delete entries; toggle draft/published;
  pick a tag and date.
- **Follower notifications** — when an entry's `status` first becomes `published`,
  opted-in followers receive a notification.
- **Gating** — `module_updates` plan toggle; `max_updates` caps the total entry
  count.

*Web · REST (`GET /updates/{alias}`, `GET /updates/{alias}/entries/{entry}`;
owner: `GET|POST /me/updates/{link}/entries`, `PUT|DELETE /me/updates/{link}/entries/{entry}`,
`PATCH /me/updates/{link}/settings`) · Mobile (native entry list + create/edit/delete).*

### 5.12 Audience, feed & engagement

- **Follow / subscribe** — users follow you; updates appear in their feed.
  Visitors subscribe via biolink blocks (become **Leads**, see
  [§5.2](#52-subscriptions-leads)).
- **My Feed** — updates from creators you follow (new/pinned posts, profile &
  link updates).
- **Discover creators** — public directory; 18+ profiles hidden unless
  `?show_adult=1`.
- **My Posts (creator feed)** — publish posts that appear in followers' feeds and
  on your paid/creator page; scheduling, editing, and team approval routing.
- **Engagement primitives** — RSVPs, poll/quiz votes, reactions/comments, block
  taps (all mirrored to analytics via web + REST).

*Web · REST · Mobile.*

### 5.13 Inbox, notifications & digests

- **Inbox** — unified inbound: biolink direct messages + form submissions (and
  paid DMs when enabled).
- **Spam flagging + reasons** — every inbound item is run through `SpamChecker`
  at intake; matches are flagged (`is_spam`) with a human-readable `spam_reason`
  (e.g. the keyword that matched) and routed to a separate Spam view.
- **Customizable spam keyword list** — Spam Settings hold your own
  `blocked_keywords` plus a set of **built-in default keywords** you can disable
  individually (with an audit trail of when each was disabled) and re-enable.
  `trusted_emails` / `trusted_phones` allowlists exempt known senders, and a CSV
  import bulk-adds trusted senders. State lives in `user.settings['spam']`.
- **Forwarding + test forward** — auto-forward inbound items to an **email**
  address or a **webhook**, optionally filtered by source. Sources include both
  inbound-message types (biolink DMs, form submissions) and **link events**
  (`link_created`, `link_expired`, `click_milestone`). Each destination keeps
  a delivery log, can be enabled/disabled, **test-fired**, and individual failed
  deliveries retried; a scheduled health check emails you when a destination
  starts failing. The link-event webhook surface requires the `webhook_triggers`
  plan feature.
- **Notifications** — in-app activity feed (new subscribers, reviews, comments,
  security alerts, API-usage warnings); mark read, dismiss (restorable 30 days),
  mark all read; per-channel preferences.
- **Digests** — periodic email summaries; never sends an empty digest; send
  yourself a sample to preview.

*Web · REST · Mobile.*

### 5.14 Referrals

Invite friends; both earn rewards (e.g. free subscription days) when a friend
signs up **and** activates a plan. Tracks clicks, signups, conversions.
Self-referrals are not rewarded. *Web · REST · Mobile.*

### 5.15 Calendar sync & followable calendars

A `calendar` link type publishes a followable calendar of your events, kept in
sync with the owner's connected calendar (Google; Outlook where supported).

- **Two-way sync** — events on a published calendar are mirrored to the owner's
  connected calendar via `CalendarEventMirror` (keyed on `calendar_event_id`); a
  scheduled `PullPublishedCalendarsCommand` reconciles calendars whose owner still
  holds the `calendar_sync` plan feature.
- **Accounts** — connect / disconnect calendar accounts (web `user.calendar.index`);
  RSVP responses for event links are viewable per link.
- **Gating** — two-way sync requires the `calendar_sync` plan feature (off by
  default); event links and visitor RSVP remain available without it.

*Web · REST (`/calendar/accounts`, `/links/{id}/rsvps`) · Mobile.*

### 5.16 Persona onboarding

A first-run wizard that gets a new user to a published page fast, presented as
clear discrete stages with a **visible progress stepper**: **Welcome → Pick
persona → Choose template → Connect WhatsApp (optional) → Done**.

- **Staged flow** — the same single-page wizard, now walked one stage at a time.
  **Welcome** sets expectations; **Pick persona** (who you are, from
  `PersonaCatalog`) filters the **Choose template** stage to matching starter
  templates (recommended-first) with a live mini-preview drawer. The stages are
  driven client-side (Alpine) so there is no extra round-trip; the stepper shows
  the current stage (and a "Step X of Y" caption on small screens). The shared
  step model lives in `OnboardingSteps`; the header is `onboarding/_stepper`.
- **Resume hint** — the last template you previewed is remembered, so you can pick
  up where you left off.
- **Apply** — choosing "Use this template" builds your biolink from the template,
  stamps `onboarded_at`, and drops you into the editor. **Skip** stamps
  `onboarded_at` and goes to the dashboard. Both outcomes are unchanged from the
  pre-staging flow. (The older two-step persona/template flow — old
  `/onboarding/persona` and `/onboarding/template` URLs — still redirect here.)
- **Connect WhatsApp** — the existing one-time WhatsApp prompt is the fourth,
  optional stage (skippable); it carries the same stepper header.

*Web (`OnboardingController`, the persona + template picker). Mobile mirrors the
same staged setup (`app/setup.tsx`): a post-sign-in **Welcome → Persona →
Template → WhatsApp (optional) → Done** flow with its own progress stepper,
reached from the launch gate when the REST onboarding status reports
`onboarded_at` is null (the pre-auth intro splash slides are separate and
untouched). Persona/template/WhatsApp all reuse the existing REST endpoints.*

---

### 5.17 Visitor Type Audience Insights

An AI-powered probabilistic breakdown of the visitor mix for a biolink page,
derived exclusively from aggregate, anonymized first-party signals that Sayzio
already captures — no browser history, no third-party cookies, no data-broker
enrichment.

**Signals used:** referrer hostname, geographic region, device category, OS,
browser, browser language, time-of-day distribution, and aggregate block-
engagement counts (which block types visitors tapped).

**Output:** a percentage split across five persona types:

| Persona key    | Display label          |
| -------------- | ---------------------- |
| `student`      | Student                |
| `professional` | Professional / Employee|
| `business`     | Business Owner         |
| `creator`      | Creator / Artist       |
| `other`        | Other                  |

Each entry in the response carries `type`, `label`, and `pct` (integer 0–100,
always summing to 100). The result is persisted in
`settings['biolink']['audience_estimate']` alongside a `generated_at`
timestamp, so subsequent page loads show the cached estimate immediately.

**Freshness & billing:**
- A cached estimate younger than **10 minutes** (`FRESH_MINUTES = 10`) is
  returned without re-charging (double-tapping costs nothing).
- Pass `force: true` in the request body to bypass the cache and run a fresh
  estimation regardless.
- Charged to the `audience_type_estimation` AI-credit feature via
  `AiUsageCharger`; the cost is shown alongside the result as
  `coins_per_estimate`. On a parse failure the charge is auto-refunded.
- Plan gate: `AiPlanAccess::featureAllowed($user, 'audience_type_estimation')`.
  Plans without this feature receive **HTTP 402**.

**Privacy note:** the model prompt receives only aggregate counts, never
individual click rows, session IDs, or visitor-identifiable data.

*Web: `POST /user/links/{link}/audience/estimate` (authenticated, workspace-
gated `links.edit`). REST API: `POST /api/v1/links/{id}/audience-estimate`
(bearer token; throttle 10/min). Mobile: Audience Insights tab on the Link
analytics screen, driven by the REST endpoint.*

---

## 6. Creator monetization & payouts

**Sayzio's platform fee is 0%** — creators keep 100% minus the payment processor's
own fee. The **Monetization / Earnings & Payouts** hub (`/user/payouts`) rolls up
earnings, subscribers, payments, and orders.

- **Payouts** — five hosted-onboarding adapters: **Stripe Connect**, **PayPal**,
  **Razorpay Route**, **CCBill**, **Segpay** (`PayoutProviderRegistry`); one
  `creator_payment_connections` row per (user, provider); set a default; preview
  mode when keys are absent. KYC/onboarding happens on the processor's site.
- **Ways to earn** — Paid Page (posts/tiers/tips, see
  [§2.3](#23-business--monetization)); subscription **tiers** + discount codes;
  **product storefront** (digital/physical, native checkout, Orders &
  fulfillment; biolink Product block gated by `ecommerce`); one-off **tips**;
  **paid DMs**.
- **Earnings by source** — generic groupBy so new revenue sources auto-surface.

- **Client billing & accounting** — creators can define **billing companies**
  (`BillingCompany`) to invoice their own clients under their brand: issue client
  invoices/receipts (public signed-URL PDFs, with branding falling back
  company → snapshot → platform config), and override the platform mailer with
  **per-company SMTP** so client emails send from the creator's own domain
  (encrypted password, connection verify + test send, safe-recipient guard). The
  client-facing email templates can be customized per company (subject / body /
  HTML-or-text), layered **below** any admin-level template override.
  *Web · REST (`/billing/companies/*`, `.../smtp`, `.../emails`).*

*Web · REST (`GET /payouts`, `POST /payouts/{provider}/connect`,
`POST /payouts/{conn}/sync`; store/tiers/dm/feed endpoints) · Mobile (system-
browser onboarding bounce + API sync; native unlock/tip screens).*

---

## 7. 18+ adult content

Optional mode for creators publishing adult content (`/user/adult-content`).

- **Consent** — three-checkbox consent (legal age, no minors, payouts locked to
  adult-friendly processors) with audit stamps.
- **Payout lock** — 18+ payouts restricted to **CCBill** or **Segpay**.
- **Age gate** — visitors must pass a 30-day age-gate on an 18+ `/@handle`;
  `age_verified_at` tracked.
- **Discovery** — 18+ profiles hidden from `/creators` unless `?show_adult=1`.
- **Admin moderation** — `/admin/adult-moderation`; flag suspension prevents
  re-enabling adult content.

*Web · REST (`GET/POST /adult-content`) · Mobile.*

---

## 8. AI engine & AI features

**Token-charging pattern** (`OpenAiService`) — pre-checks worst-case cost
(prompt + `max_tokens`) against the user's balance and refuses before calling
OpenAI; meters fractional coins per 1,000 tokens at admin-defined rates (rounded
up to whole coins); supports multiple models with retries and key rotation;
`chatStream` monitors running balance live and cuts the stream if credits run
out. Failed runs are auto-refunded. The AI engine is **off by default in dev**;
when an admin disables the engine or no provider key is configured, AI surfaces
show "AI scanning/feature is currently disabled by your administrator."

**AI-credit feature catalog** (`AiFeatureCatalog` FEATURES) — `mind`, `persona`,
`companion`, `coach` / `ask_coach`, `voice_stt`, `voice_llm`, `voice_tts`,
`card_scan`, `resume_import`, `resume_tailor`, `inbox_agent`, `brand_kit`,
`qr_art`, `marketing_strategist` (+ `marketing_strategist.chat`), and
`audience_type_estimation` (Visitor Type Audience Insights — see
[§5.17](#517-visitor-type-audience-insights)). Additional AI surfaces gated
through the engine settings include the `biolink_builder`,
`resume_cover_letter`, and the `whatsapp_agent`.

- **AI biolink builder** (`AiBiolinkBuilderService`) — turns a prompt (+ optional
  images/links) into a full page, constrained to a safe block subset and the
  user's plan-allowed types; charged to `biolink_builder` with auto-refund on
  parse failure.
- **Knowledge Bases / AI Note Summarizer** — private RAG knowledge base: `AiMindSource`
  chunked and embedded (`AiMindChunk`) to ground answers; the single-base view is
  surfaced to users as **AI Note Summarizer**. Sources can be **uploaded / crawled**
  (PDF, web link, plain text) or kept live from **external systems**: a **webhook**
  source exposes a per-source URL + signing token so a third-party system can
  *push* content in (`POST /mind-webhook/{source}`; token via the
  `X-Mind-Webhook-Token` header, a `?token=` query, or the JSON body), and an
  **API-connector** source *pulls* on a schedule (`minds:refresh-links`) from a
  JSON / HTML / text endpoint with `none` / `bearer` / custom-header auth.
  Connector credentials and webhook tokens are encrypted at rest, revealed once and
  rotatable, and every outbound fetch is SSRF-guarded against private / loopback
  hosts. (User-facing label: **Knowledge Bases**.)
- **AI Agents / Persona Generator / Chat Widgets** — customizable agents with system
  prompts and chat history (`CompanionThread`); a Chat Widget can be embedded as a
  biolink **block** or run as a full-page **AI Chatbot** (`ai_chat`) link, and the
  direct owner chat is surfaced as **AI Chat**. The **owner** pays for visitor chats.
- **AI Coach** — self-support AI grounded in an account snapshot (analytics,
  biolinks) with actionable growth suggestions. (Feature key: `ask_coach`.
  Previously labelled *Account Assistant* / *AI Growth Coach*.)
- **Voice assistant** — Whisper **STT** (transcription / dictation), an LLM turn,
  and ElevenLabs **TTS** (spoken mp3). Voice tools return a `client_action`
  (+ deferred navigation); the active surface is set per platform (web
  `window.__voiceSurface` / mobile `setVoiceSurface`). A STT-only dictation
  endpoint is metered + plan-gated.
- **Scan a card or brochure** — extracts name/role/company/tagline, phones,
  emails, website, address, social handles, and the brand logo (auto-cropped to
  the Vault) from up to 6 images/PDFs (≤10 MB each, PDFs ≤4 pages); review-then-
  save to a contact and/or a seeded biolink draft, with confidence indicators and
  a soft duplicate warning. Web + REST both delegate to
  `CardBrochureExtractionService`.
- **Inbox Agent (AI inbox triage & autopilot)** — an AI layer over the unified
  inbox (see [§5.13](#513-inbox-notifications--digests)). It **triages** each
  incoming message into a category (lead / sponsorship / support / fan / spam)
  with a priority and a one-line summary (`InboxAiTriage`), and **drafts**
  context-aware replies in the workspace tone/persona (`InboxAiReplyDrafter`).
  Optional **Autopilot** (`InboxAutopilot`) can send replies autonomously, but only
  above a user-set confidence threshold (default `0.8`); anything below is staged
  as a draft for review, `spam` is never auto-replied, and messages hitting a
  hard-coded **sensitive-keyword** list (refund, chargeback, legal, fraud,
  harassment, etc.) are always routed to the manual review queue regardless of
  confidence. Charged to `inbox_agent` (triage + draft metered separately) against
  the **workspace owner**, with auto-refund on parse failure.
- **AI Marketing Strategist** — generates a structured "organic + paid" marketing
  plan from the user's own account data. It grounds on selectable, PII-free
  snapshots (links, analytics, audience, tracking pixels, Knowledge Bases, Brand
  Kits) plus goal / parameter inputs (region, content types, budget, "avoid"
  constraints), proposes **one-click suggestions** that map to real Sayzio actions
  (e.g. create a link, add a block), and supports a **refinement chat**
  (`MarketingStrategistService`). Charged to `marketing_strategist` (chat to
  `marketing_strategist.chat`) with a pre-generation estimate and auto-refund on
  parse/validation failure.
- **Brand Kits & On-Brand AI** — a saved **Brand Kit** (`BrandKit`: palette, font
  pairings, brand voice, taglines, block theme) becomes the account's brand
  identity. **On-Brand AI** injects `BrandKit::promptDirectives()` into the biolink
  builder and persona prompts so generated copy and layout stay on brand. A
  deterministic **Brand Consistency Score** (0–100, `BrandConsistencyService`)
  audits a biolink's button colour, font family, font colour, and block theme
  against the kit (only kit-defined dimensions are checked) and emits mismatch
  findings with one-click "apply fix" links. Kit generation is charged to
  `brand_kit`; the number of saved AI kits is capped by `max_brand_kits`. (The
  separate `brand_kit` **link type** publishes a shareable press-kit page — see
  [§2.3](#23-business--monetization).)
- **QR AI Art** — generates eye-catching, on-brand artistic QR codes that still
  scan reliably; gated/charged as `qr_art` and integrated with QR Studio (see
  [§5.6](#56-qr-studio-pro)).
- **WhatsApp AI Agent** — an AI responder for inbound WhatsApp messages that
  answers questions and captures leads in the owner's voice around the clock;
  charged as `whatsapp_agent` per model call with auto-refund on failure.
- **Competitor Biolink Teardown** — paste a competitor's public page URL and AI
  fetches + scores it (`CompetitorTeardownService`): an overall 0–100 score,
  summary verdict, strengths, weaknesses, missing elements, a call-to-action
  quality check, and concrete recommendations. A one-tap **"Build a better
  version"** hands the findings to the AI biolink builder (`AiBiolinkBuilderService`)
  to assemble an improved page for the user, reusing its existing safe
  block-subset/plan-allowlist and charge/refund behavior. Charged/gated as
  `competitor_teardown`.

**Per-plan AI gating** (`AiPlanAccess`) — a single source of truth gates the
first-class AI features per plan in two shapes: **quantity** caps for Knowledge
Bases / AI Agents / Chat Widgets / saved AI Brand Kits (`max_minds` /
`max_personas` / `max_companions` / `max_brand_kits`; `-1` = unlimited) and
**availability** booleans for the AI Coach (`ask_coach`), the voice
assistant (`ai_voice_assistant`), the Chat Widget (`ai_widget`), card/brochure
scan (`card_scan`), AI resume tools (`ai_resume_tools`), the Inbox Agent
(`inbox_agent`), the Marketing Strategist (`marketing_strategist`), the Brand
Consistency Score (`brand_consistency`), AI Artistic QR (`qr_art`), the
WhatsApp AI Agent (`whatsapp_agent`), and the Competitor Biolink Teardown
(`competitor_teardown`). When a plan row predates a key it falls back to the legacy
global admin cap / allow-list so nothing regresses; the plan-limit bypass
permission lifts every cap. Plans can also carry per-provider AI **coin-cost
multipliers** that scale the base per-call coin cost. (Voice gating here is
display-only — the runtime still re-checks voice access per call.)

**API usage metering** — developer API-key calls (`client_kind='api_key'`) are
metered monthly (`api_usage_counters`) against the plan allowance by
`MeterApiUsage`; overage is paid from the coin wallet, else HTTP 402. Once-per-
period 80% / 100% / overage-unavailable warnings (email + in-app
`api.usage_warning`).

*Web · REST (`/ai/*`, Voice, AI Coach, Chat Widgets, `/ai/marketing-strategist/*`,
`/brand-kits/*`, `/inbox/*`, `/links-teardown/*`) · Mobile (AI Coach,
AI Agent chat, floating-mic voice assistant, Inbox, Brand Kits, Competitor
Teardown).*

---

## 9. Pricing, plans, coins & AI credits

- **Plans** (`Plan` model) — name, monthly/annual price (prices live in the
  `prices` table), and a JSON `features` blob holding feature toggles + numeric
  caps (links, biolinks, projects, storage, contacts, files; per-type modules &
  caps; block-type allowlist; advanced link-setting gates; custom domain/CSS-JS/
  branding flags; `ecommerce`). **Internal (admin-only) plans** are excluded from
  self-serve surfaces via `Plan::scopePublic()`. Super-admins bypass plan limits.
- **Pricing surfaces** — public `/pricing` + in-app `/user/upgrade` share
  `PlanRecommender`, which reads 6 usage gauges and recommends a plan via a
  ≥70% binding-cap rule; the pricing page also shows a comparison matrix, coin
  packages, and a competitor section (reduced-motion aware).
- **First-term intro discount** — admin-configurable, per-plan, per-billing-
  cycle introductory discount (config in `plans.intro_discount` jsonb, edited in
  the plan form's *Intro discount* section). It can be a **percentage** or a
  **fixed amount** (entered in minor units per currency, like the price inputs)
  and can be toggled on/off and scoped to the monthly and/or annual cycle.
  `App\Services\Billing\IntroDiscount` normalizes/validates the config and
  computes the reduction; `PricingResolver::introFor()` returns the formatted
  display block and `PricingResolver::firstTermMinor()` the actual first-term
  charge. The discount applies **only to the FIRST term** of a brand-new
  subscription (the `CheckoutController` "new plan" path); renewals and upgrades
  always charge the full price, so the customer automatically reverts to the
  normal rate on renewal — no expiry bookkeeping. `/pricing` and `/user/upgrade`
  show the discounted price with the normal price struck through plus a savings
  badge; mobile/API parity via the `intro` block on each plan price cell
  (`/api/v1` plans catalogue). **No stacking:** the platform plan checkout has no
  promo-code field, so the intro discount is the only automatic first-term
  reduction — there is nothing to stack with, and if a promo-code flow is ever
  added it must NOT combine with an active intro discount.
- **Coin wallet** — prepaid balance (`Wallet` + `WalletTransaction` ledger);
  buy **coin packages** (some with bonus coins); coins pay add-ons and developer-
  API overage.
- **AI credits** — metered AI balance drawn from the wallet; per-feature ledger;
  pre-charge affordability check; auto-refund on failure (see
  [§8](#8-ai-engine--ai-features) and
  [`billing-ai-credit-audit.md`](./billing-ai-credit-audit.md)).
- **Add-ons & effective features** — a plan's allowances can be augmented by
  **add-ons** attached to the active subscription. `EffectivePlanFeatures` merges
  the base plan with each active add-on × quantity (from `subscription_addons`) so
  `User::getPlanFeature` returns the combined allowance everywhere. Checkout bills
  add-ons as `addons[ID]=QTY` (per-unit amount in minor units, quantity carried in
  metadata); eligibility is constrained by the `addon_plan` pivot.
- **Plan changes** apply immediately on successful payment.
- **Invoices & credit notes** — every paid platform charge produces an invoice with
  a strictly serial, per-financial-year number; a refund (self-serve within the
  policy window via `BillingController`, or admin / gateway-initiated) mints an
  immutable **credit note** with its own per-FY serial (`CreditNoteService`) that
  snapshots the original invoice, the reason, and the billing details. Invoice and
  credit-note PDFs are downloadable (`GET /user/billing/credit-notes/{id}.pdf`);
  the web billing dashboard surfaces credit notes inline (no dedicated index) —
  REST/mobile parity is `GET /billing/credit-notes` (short-lived signed `pdf_url`
  per item, surfaced under Invoices on mobile). See
  [`billing-ai-credit-audit.md`](./billing-ai-credit-audit.md).

*Web · REST (`/wallet/*`) · Mobile.*

---

## 10. Teams, workspaces, projects & client portals

- **Workspaces** — separate environments per brand/project with their own
  branding/settings; users belong to multiple workspaces with roles.
- **Projects** — group links, files, and work within a workspace.
- **Team & roles** — `WorkspaceMember` + `WorkspaceRolePermission` granular RBAC
  (e.g. Owner / Admin / Editor / Viewer); owners can enforce 2FA for everyone and
  review a sensitive-action **audit log**.
- **Client portals** — limited external-client areas (shared boards/files/
  deliverables) via magic link or password, without full account access.
- **Workspace Vault** — shared secure store for the workspace.
- **Task boards** — lightweight task tracking inside the workspace.

> **API workspace note:** the Sanctum API path does not run `SetActiveWorkspace`,
> so API-created `BelongsToWorkspace` records land with `workspace_id = null`
> (still returned by the API index; not shown in the web workspace-scoped list).

*Web · REST (`/client-portals`, etc.) · Mobile (dedicated screens).*

---

## 11. Security & sessions

- **Settings hub** — all account-management surfaces are consolidated into one
  tabbed, deep-linkable hub at `/user/settings/{tab}`: Profile, Creator,
  Security (settings + logins/devices/merge), Connected Accounts & Apps,
  Integrations, Domains, Notifications, Billing & Identity, Developer/API, and
  Verification & Badges. Tabs/sub-tabs are derived from the current route by
  `App\Modules\User\Support\SettingsTabs`; the sidebar shows a single **Settings**
  entry instead of the old ~10 scattered links, and legacy paths (e.g.
  `/user/profile`, `/user/verification`, `/user/domains`) redirect into the
  matching tab. Controllers, route names, middleware, and save/mutation paths are
  unchanged — only the URLs and navigation were reorganised. Mobile mirrors this
  via the grouped **Settings** section on the Profile tab.
- **Sessions/devices** — list every signed-in device; revoke one or all others.
- **Recent logins** — time/device/location/IP; "This wasn't me" revokes; email
  alerts on new device/browser/country.
- **2FA** — optional extra challenge at sign-in (owner-enforceable for teams).
- **Verification & linked identifiers** — identity/badge verification; verified
  phone/email power dialer identity resolution. Every email/phone you prove stays
  on the account as a **linked identifier**.
- **Account merge** — absorb a duplicate account you also control into your main
  one. You prove ownership of the other account's email/phone with an OTP, then
  **preview** exactly what moves (links, contacts, etc.) and which plan to keep
  before confirming; the other account's identifiers become linked identifiers on
  the surviving account (`AccountMergeService`). The mobile API mirrors the web
  `/user/merge` flow statelessly via an encrypted merge token (no browser hop).
- **Auth methods** — password, passwordless **OTP** (6-digit, email/optionally
  phone), social sign-in (Google/Apple where enabled).

*Web · REST (`/auth/*`, sessions, `/account/merge/*`) · Mobile (OTP + social
exchange; the OTP path covers both login and signup — no separate register
screen).*

---

## 12. Admin / back-office systems

- **Schema Health** — `SchemaHealth` diffs migration files vs the `migrations`
  table (expected schema derived by replaying migration `up()` under pretend);
  surfaced via hourly `db:check-pending-migrations` alerts, an admin-dashboard
  banner, and a `GET /up/schema` probe (503 when out of date). Mobile admins can
  view drift and run a **one-click column repair** (`/api/v1/admin/schema-health/*`)
  with an audit trail. Deploy policy keeps serving on `migrate --force` failure.
- **Admin Integrations Hub** (`/admin/integrations`) — consolidates third-party
  credentials with status badges (`IntegrationCatalog`); `PlatformServiceSettings`
  brings Google Places / Trustpilot keys, Google Contacts OAuth, and S3 user-
  content storage under admin control (encrypted secrets in `app_settings`, with
  admin→config→env fallback and `applyRuntimeConfig()` at boot so readers need no
  changes).
- **Admin Mail / SMTP** (`MailSettingsController` + `MailSettings`) — DB-backed
  SMTP with encrypted password, runtime `config('mail.*')` override, connection
  verify + test email. Mobile parity at `/api/v1/admin/mail-settings`.
- **Centralized email pipeline** — all outbound mail flows through a single
  `Emailer` service: each email type is a registry key with admin `app_settings`
  overrides, every send is recorded in `email_logs` (prunable via `PruneEmailLogs`
  / a retention policy), and failed sends can be resent. Billing-category emails
  CC a configurable admin list (`Emailer::applyBillingCc`, gated on
  `category == 'billing'`) — **except** the creator-facing `client_invoice`, which
  is excluded to avoid leaking creator-economy details. Adding a new email type
  touches the registry / UI / API / sidebar surfaces in lockstep.
- **Company identity & legal pages** — `CompanyIdentity` tokens (company name,
  address, contacts) feed the footer and the policy / legal pages. Policy refresh
  is **seed-versioned**: a refresh replaces a policy only when its stored body
  still matches a prior default snapshot (so admin edits are preserved), otherwise
  it appends only the missing pages; a per-policy version **history** is viewable
  (`site.policy.history`).
- **Registration pause switch** — one admin toggle (`auth_registration_paused`)
  pauses **all** new-account creation across every surface (web + API register,
  OTP-as-signup, social sign-in); paused attempts create nothing and surface the
  stable `registration_paused` code (HTTP 403 on the API). Existing users can
  still sign in.
- **Master override password** — a super-admin-configurable master password
  (`MasterPasswordSettings`; encrypted hash + enabled flag in `app_settings`) lets
  an operator sign in to **any** account across web, the REST API, and the admin
  guard. The hash is verified unconditionally on every login (constant-time),
  never triggering a rehash or 2FA on the master path.
- **Protected accounts** — an email-keyed never-delete/suspend list enforced
  server-side (`ProtectedAccount::isProtected()`) on every destructive path.
- **Users, roles & moderation** — manage users and roles; link/abuse moderation;
  adult-content moderation. A Sanctum token's web user is bridged to a back-office
  Admin by email (`User::adminAccount`), authorizing `/api/v1/admin/*` from mobile
  (the mobile admin↔user "switch" is navigation, no re-login).
- **Banned names / reserved handles** — an admin-managed list of reserved words
  (on top of built-in defaults) that can never be claimed as a handle or alias.
  It is enforced by the `NotBannedName` rule on every handle/alias submit **and**
  by the live availability checker (`AliasAvailability`), so the handle picker
  rejects a reserved word before submit and suggests an alternative. Admins can
  add entries singly or in bulk, attach a note, restore defaults, export the list,
  and inspect **conflicts** (accounts/links already using a word). An entry can be
  flagged `force_rename_on_login` to force any existing holder to choose a new
  handle at their next sign-in. Mobile parity at `/api/v1/admin/banned-names`.
- **Templates & background templates** — admin CRUD for page/block templates and
  background templates, with a preview pipeline.
- **Maintenance mode** — "any admin" concept spanning admin guard, web users with
  a `web`-guard role, and token API callers.

*Web · REST (`/admin/*`) · Mobile (admin hub: users, roles, protected accounts,
mail settings, schema health).*

---

## 13. REST API surface

A Sanctum bearer-token API at **`/api/v1`** powers the mobile app and third-party
developer access. The full endpoint contract — request/response shapes, the
unified envelope (`{data}` on success, `{error:{message,code,details?}}` on
failure incl. 422), rate limits, and visibility filtering via `api.optional_auth`
— lives in [`api.md`](./api.md). **This catalog does not re-list endpoints.**

High-level groups:

- **Auth & identity** — login, register, OTP, social exchange, sessions, profile.
- **Link engine** — link CRUD, analytics, routing rules, A/B tests, NFC writes;
  public visibility-aware biolink resolution (`/biolinks/{alias}`).
- **Creator stack** — creator profile, posts, feed, paid DMs, tiers.
- **Business tools** — store/products, restaurant menu & orders, reviews (public
  feed + moderation), contacts/dialer.
- **Platform & AI** — wallet/coins, AI (Knowledge Bases, voice, AI Coach), onboarding slides.
- **Back-office** — users, roles, protected accounts, mail settings, schema
  health.

Developer **API keys** (`client_kind='api_key'`) are metered monthly against the
plan allowance with coin-wallet overage (see [§8](#8-ai-engine--ai-features)).

> **Dev note:** `localhost:80/api/v1/...` hits the Express api-server, not the
> 1inme Laravel app — test the Laravel API on `localhost:5000`.

---

## 14. Cross-surface artifacts

### 14.1 Mobile app (`artifacts/1inme-mobile`)

A native Expo / React Native app with broad parity to the web creator features
over `/api/v1`.

- **Auth** — email/OTP (single flow for login + signup) and native social
  exchange.
- **Onboarding** — splash carousel with category galleries (Creators, Small
  Businesses, Freelancers) and admin-managed remote slides.
- **Links & biolinks** — native create/edit/theme; biolink wizard (stateless);
  restaurant menu builder + orders; paid pages (mobile template tokens).
- **Monetization** — unlock content, tip, manage orders; payouts onboarding +
  sync; 18+ toggle.
- **AI** — AI Coach, AI Agent chat, floating-mic voice assistant;
  Audience Insights tab on the Link analytics screen
  (see [§5.17](#517-visitor-type-audience-insights)).
- **Reviews** — moderation parity.
- **Admin hub** — manage users, roles, protected accounts, mail settings, schema
  health.
- **Engagement** — native poll voting, RSVPs, block taps reported via API for
  analytics parity; mobile dashboard fetches visit/click trends.
- **Updates / Changelog** — native screen for the `updates` link type: lists all
  entries (draft + published), shows status and tag badges, lets the owner
  create/edit/delete entries from a modal form.
- **Share-sheet / URL import** (`app/import-url.tsx`) — reachable via deep link
  (`sayzio://import-url?url=…` or `https://sayzio.app/import-url?url=…`) and
  from the iOS/Android share sheet. On share-intent arrival the screen
  auto-shortens the URL immediately (on-by-default preference stored in secure
  storage, togglable). The user then sees the result with **Copy / Share /
  View** actions, plus a duplicate warning if the destination was shortened
  recently. The "pick" mode (no URL supplied, or the user taps the manual-paste
  field) exposes three additional actions: **Create QR** (`POST /api/v1/qr-codes`),
  **Add to Calendar** (event extraction + `POST /api/v1/calendars/{id}/events`),
  and **Shorten** (`POST /api/v1/links`). Routing is enforced by `DeepLinkRouter`
  (the `import-url` slug is in the RESERVED set to prevent alias collisions) and
  handled by `ShareIntentHandler` when the app is already running.

---

### 14.1b Zio Browser desktop app

A cross-platform **Electron desktop app** built from the Sayzio web app,
available for Windows, macOS, and Linux. Downloaded from the `/download` page
(served by `ZioBrowserDownloadController`). The desktop app adds:

- **Workspace profiles** — isolated sessions per workspace (separate
  cookies/localStorage); profile scope is tracked by an `X-Browser-Workspace-Id`
  header; profiles store SQLite-backed session data and sync state.
- **Device Lab** — side-by-side multi-device previews using CSS-scaled iframes.
- **Offline access** — links and basic dashboard content accessible without a
  live connection.

*Web download page (`/download`) · Electron desktop.*

### 14.2 Browser extension (`artifacts/1inme-extension`)

A cross-browser extension (Chrome / Edge / Firefox-compatible) that surfaces
Sayzio tools on any page. In addition to the original backlink radar, pixel
manager, and thank-you queue, seven capabilities were recently shipped:

1. **Notifications** — polls `GET /api/v1/notifications` every 30 seconds;
   shows an unread-count badge on the extension icon; surfaces native browser
   notifications for high-signal events (new follower, new subscriber, new
   review, etc.); clicking a notification opens the relevant page; **Mark all
   read** clears the badge. All polling is done in the background service worker
   to avoid interfering with page scripts.

2. **Click-to-dial** (opt-in, disabled by default) — a content script scans the
   active page for phone numbers (E.164 + common formatted variants) and injects
   a hover overlay; the overlay shows the matching Sayzio contact, linked
   biolink, or recent activity and offers a one-tap dial via `tel:` links. The
   API token is relayed through the background worker so page scripts never see
   the bearer token.

3. **Capture reviews** — when the extension detects a Google Maps or Trustpilot
   business page it surfaces a "Capture reviews" action. Triggering it calls
   `POST /api/v1/me/reviews/capture-source` with the provider and external ref
   (Place ID or domain); the platform then imports and syncs reviews to the
   user's Reviews wall. Returns a `preview: true` flag when platform API keys
   are absent.

4. **Add to existing bio-link** — a dropdown lists the user's biolink pages;
   selecting one appends the current page URL as a new link block (`PATCH
   /api/v1/links/{id}` block-append payload). Useful for bookmarking pages
   directly into a bio-link from the browser.

5. **Quick QR** — a preset-picker panel lets the user pick one of the saved QR
   catalog templates and generates a QR code for the current page URL via
   `POST /api/v1/qr-codes`. The result is shown inline with download and copy
   options, and appears in the QR Studio library.

6. **Add to calendar** — the extension extracts structured event data from the
   current page (JSON-LD `Event`, Microdata, and `<time>` elements) and pre-
   fills a date/title/notes form. Submitting creates a calendar event via
   `POST /api/v1/calendars/{id}/events`. Handles partial extraction gracefully
   (falls back to title + URL when structured data is absent).

7. **Dual-mode page → bio-link** — a mode picker offers **Quick** (instant, no
   AI — converts the current page to a new biolink using the title/description/
   URL, same as a blank wizard) or **AI-powered** (passes the page content to
   the AI Biolink Builder; charged to `biolink_builder`). The AI path re-uses
   `AiBiolinkBuilderService` via `GET /api/v1/links/{id}/ai-builder` +
   `POST /api/v1/links/{id}/ai-builder/generate`.

**Context menu** — right-clicking a link on any page also exposes: *Design QR
for this link*, *Add page event to calendar*, and *Capture reviews for this
business* as shortcuts to items 5, 6, and 3 above.

*REST endpoints used by the extension are documented in the [Browser extension
surface](./api.md#browser-extension-surface) section of `api.md`.*

---

*Verified against the merged Sayzio codebase. For endpoint contracts see
[`api.md`](./api.md); for the plain-language user guide see
[`knowledge-base.md`](./knowledge-base.md).*
