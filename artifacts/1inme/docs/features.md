# Sayzio Feature Catalog

This is the exhaustive, engineering-verified catalog of **every** feature in the
Sayzio platform: each link type (grouped by the product categories shown in the
"Create Link" picker), every major functional system, the admin/back-office
surfaces, the AI engine, billing, and the cross-surface artifacts (web app,
REST API, mobile app, marketing site, slide deck).

It complements two sibling docs and intentionally does **not** duplicate them:

- **Developer REST API reference** — endpoint-by-endpoint contract:
  [`api.md`](./api.md). Throughout this catalog, REST coverage is summarized and
  cross-linked rather than re-listed.
- **End-user guide & FAQ** — plain-language "what / why / how":
  [`knowledge-base.md`](./knowledge-base.md).
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
6. [Creator monetization & payouts](#6-creator-monetization--payouts)
7. [18+ adult content](#7-18-adult-content)
8. [AI engine & AI features](#8-ai-engine--ai-features)
9. [Pricing, plans, coins & AI credits](#9-pricing-plans-coins--ai-credits)
10. [Teams, workspaces, projects & client portals](#10-teams-workspaces-projects--client-portals)
11. [Security & sessions](#11-security--sessions)
12. [Admin / back-office systems](#12-admin--back-office-systems)
13. [REST API surface](#13-rest-api-surface)
14. [Cross-surface artifacts](#14-cross-surface-artifacts)
    - [14.1 Marketing site](#141-marketing-site-artifacts1inme-com)
    - [14.2 Mobile app](#142-mobile-app-artifacts1inme-mobile)
    - [14.3 Slide deck](#143-slide-deck-artifacts1inme-deck)

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
| **Resume / Portfolio** | `resume` | A shareable resume/portfolio page with PDF download (see [§5.11](#511-resume--portfolio)). |

These (plus the AI types below) form the **biolink family** (`Link::BIOLINK_FAMILY`,
checked via `isBiolinkFamily()`), which share the block editor, settings,
visibility tiers, and the public renderer.

- **Link in Bio (`biolink`)** — the flagship page builder; full block catalog,
  per-block styling, global themes, SEO/OG/PWA/branding/custom CSS-JS, AI builder
  and wizard. Detailed in [§4](#4-the-biolink-editor--blocks). *Web · REST · Mobile.*
- **Slides (`slides`)** — swipeable story deck; shares the biolink editor/Step 2
  flow; subject to its own per-type plan toggle (`module_slides`) and cap
  (`max_slides`). *Web · REST · Mobile.*
- **Restaurant Menu (`restaurant_menu`)** — uses its own dedicated builder (not
  the block editor); see [§5.10](#510-restaurant-menu--orders). Toggle
  `module_restaurant_menu`, cap `max_restaurant_menu`.
- **Resume / Portfolio (`resume`)** — bridges the link to the user's standalone
  resume builder record (`ensureResume()`); see
  [§5.11](#511-resume--portfolio). Toggle `module_resume`, cap `max_resume`.

**Visibility tiers** (biolink-family): **public**, **registered** users only,
**followers** only, **subscribers** only — plus optional password protection.
Visitors who don't meet the tier are blocked or prompted to follow/subscribe.

### 2.3 Business & monetization

> *Grow your reputation and earn from your audience.*

| Friendly name | `links.type` | What it is |
| --- | --- | --- |
| **Bizs Profile / Paid Page** | `paid_page` | A themeable home that auto-shows your posts, tiers & tips. |
| **Reviews Page** | `reviews` | Collect and showcase reviews from your audience (see [§5.9](#59-reviews)). |

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
- **AR business card** — `ar_enabled` / `ar_settings` for an AR card experience;
  AR scans are tracked as an analytics source.
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

*Web · REST (link CRUD, rules, A/B, NFC writes) · Mobile.*

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
  (countdown), Donation, Buy Me a Coffee, Ko-fi, Patreon.
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
  (mobile reads the same capability).
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
  API (`GoogleContactsSyncService`); scheduled `contacts:sync` every 30 min.
- **Dialer** — number pad with **T9 smart-search** (keypad-spelled names), speed
  dial, recents/frequent; call logging with outcomes/notes. `DialerData` is the
  single read/transform source for web + API.
- **Identity resolution** — resolve a phone number to a Sayzio biolink profile
  (`/dialer/lookup`); contacts whose verified phone matches a user get their
  biolink auto-attached (with detach memory) via `linked_identifiers`.
- **Import / management** — bulk CSV / VCF import (async for large files),
  spam/block flagging. Calls/emails open the device's native `tel:` / `mailto:`
  (no in-app VOIP).
- **Scan a card or brochure** — AI tool (see [§8](#8-ai-engine--ai-features))
  that extracts contact details + logo from a photo/PDF to save a contact and/or
  seed a biolink draft.

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
- **Orders Dashboard** — near-real-time staff workflow with incremental polling;
  statuses Pending → Preparing → Served → Paid/Cancelled (plus
  confirmed/delivered/completed states).

*Web · REST (public `GET /restaurant/{alias}`, `POST /restaurant/{alias}/order`,
`GET /restaurant/order/{token}`; owner orders + `/poll` + status PATCH) · Mobile
(full native builder + orders polling).*

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
`card_scan`, `resume_import`, `resume_tailor`, plus the `biolink_builder`.

- **AI biolink builder** (`AiBiolinkBuilderService`) — turns a prompt (+ optional
  images/links) into a full page, constrained to a safe block subset and the
  user's plan-allowed types; charged to `biolink_builder` with auto-refund on
  parse failure.
- **Knowledge Bases / Note Summarizer** — private RAG knowledge base: `AiMindSource`
  (PDF / web / text) chunked and embedded (`AiMindChunk`) to ground answers; the
  single-base view is surfaced to users as **Note Summarizer**.
- **AI Agents / Persona Generator / Chat Widgets** — customizable agents with system
  prompts and chat history (`CompanionThread`); a Chat Widget can be embedded as a
  biolink **block** or run as a full-page **AI Chatbot** (`ai_chat`) link, and the
  direct owner chat is surfaced as **AI Chat**. The **owner** pays for visitor chats.
- **Account Assistant / Growth Coach** — self-support AI grounded in an account
  snapshot (analytics, biolinks) with actionable suggestions.
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

**Per-plan AI gating** (`AiPlanAccess`) — a single source of truth gates the
first-class AI features per plan in two shapes: **quantity** caps for Knowledge
Bases / AI Agents / Chat Widgets (`max_minds` / `max_personas` / `max_companions`;
`-1` = unlimited) and **availability** booleans for the Account Assistant
(`ask_coach`), the voice assistant (`ai_voice_assistant`), the Chat Widget
(`ai_widget`), card/brochure scan (`card_scan`), and AI resume tools
(`ai_resume_tools`). When a plan row predates a key it falls back to the legacy
global admin cap / allow-list so nothing regresses; the plan-limit bypass
permission lifts every cap. Plans can also carry per-provider AI **coin-cost
multipliers** that scale the base per-call coin cost. (Voice gating here is
display-only — the runtime still re-checks voice access per call.)

**API usage metering** — developer API-key calls (`client_kind='api_key'`) are
metered monthly (`api_usage_counters`) against the plan allowance by
`MeterApiUsage`; overage is paid from the coin wallet, else HTTP 402. Once-per-
period 80% / 100% / overage-unavailable warnings (email + in-app
`api.usage_warning`).

*Web · REST (`/ai/*`, Voice, Growth Coach, Chat Widgets) · Mobile (Account Assistant,
AI Agent chat, floating-mic voice assistant).*

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

- **Sessions/devices** — list every signed-in device; revoke one or all others.
- **Recent logins** — time/device/location/IP; "This wasn't me" revokes; email
  alerts on new device/browser/country.
- **2FA** — optional extra challenge at sign-in (owner-enforceable for teams).
- **Verification & linked identifiers** — identity/badge verification; verified
  phone/email power dialer identity resolution.
- **Auth methods** — password, passwordless **OTP** (6-digit, email/optionally
  phone), social sign-in (Google/Apple where enabled).

*Web · REST (`/auth/*`, sessions) · Mobile (OTP + social exchange; the OTP path
covers both login and signup — no separate register screen).*

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
  banned-name rules; adult-content moderation. A Sanctum token's web user is
  bridged to a back-office Admin by email (`User::adminAccount`), authorizing
  `/api/v1/admin/*` from mobile (the mobile admin↔user "switch" is navigation, no
  re-login).
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
- **Platform & AI** — wallet/coins, AI (Knowledge Bases, voice, Growth Coach), onboarding slides.
- **Back-office** — users, roles, protected accounts, mail settings, schema
  health.

Developer **API keys** (`client_kind='api_key'`) are metered monthly against the
plan allowance with coin-wallet overage (see [§8](#8-ai-engine--ai-features)).

> **Dev note:** `localhost:80/api/v1/...` hits the Express api-server, not the
> 1inme Laravel app — test the Laravel API on `localhost:5000`.

---

## 14. Cross-surface artifacts

### 14.1 Marketing site (`artifacts/1inme-com`)

A standalone React + Vite + Tailwind site, separate from Laravel. It is a
**gateway**: it has no auth/checkout of its own — all login/signup/pricing CTAs
route to the main app via `src/config.ts` (`LOGIN` / `SIGNUP` / `PRICING`).

- **Home** — role cycler hero ("I am a [Creator/Artist/Coach…]") with primary
  "make it free" CTA and a feature tour; copy mirrors Laravel `SitePagesContent`.
- **Blog** — reads the **live DB-driven Laravel blog** at runtime via CORS-open
  `/blogs/feed.json` + `/blogs/feed/{slug}.json` (no static array).
- **Changelog** — versioned New/Improved/Fixed timeline.
- **Contact** — multi-field form with server-side honeypot, submitting via
  `@workspace/api-client-react` to the Laravel admin inbox (rate-limited,
  `mailto:` fallback).
- **Legal** — Terms, Privacy, GDPR, Cookies, Refunds via a shared `LegalPage`
  layout; plus official social links.

### 14.2 Mobile app (`artifacts/1inme-mobile`)

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
- **AI** — Account Assistant, AI Agent chat, floating-mic voice assistant.
- **Reviews** — moderation parity.
- **Admin hub** — manage users, roles, protected accounts, mail settings, schema
  health.
- **Engagement** — native poll voting, RSVPs, block taps reported via API for
  analytics parity; mobile dashboard fetches visit/click trends.

### 14.3 Slide deck (`artifacts/1inme-deck`)

A modular React presentation organized by a manifest
(`src/data/slides-manifest.json`).

- **Sales** — Problem (fragmentation), Cost (annual-savings story), ROI, Next
  Steps / CTAs.
- **Product** — end-to-end journey, "What you can create" (link-type tour),
  "Identity as the spine".
- **Investor** — market size, business model, traction, moat, team, the ask.
- **Persona decks** — Creators, Coaches, Sales Pros (day-in-the-life + outcomes).

---

*Verified against the merged Sayzio codebase. For endpoint contracts see
[`api.md`](./api.md); for the plain-language user guide see
[`knowledge-base.md`](./knowledge-base.md).*
