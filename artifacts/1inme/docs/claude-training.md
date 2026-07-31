# Sayzio Claude Training Document

> **Purpose.** This is the comprehensive technical training reference for **Claude**
> and other internal AI assistants operating within the Sayzio platform context.
> It covers all customer-facing features, the REST API surface, internal systems,
> billing mechanics, security model, and key business rules — everything needed to
> understand the full platform deeply and answer technical or operational questions.
>
> **Scope.** Unlike [`chatbot-training.md`](./chatbot-training.md) (customer-facing
> only, plain English) and [`knowledge-base.md`](./knowledge-base.md) (user guide
> and FAQ), this document is intentionally technical and includes admin/back-office,
> internal architecture, security constraints, and implementation notes. For the
> raw endpoint contract see [`api.md`](./api.md). For the exhaustive feature
> catalog with engineering detail see [`features.md`](./features.md).

---

## 1. Platform architecture overview

**Stack.** Sayzio is a pnpm-workspace monorepo:
- `artifacts/1inme` — PHP 8.4 Laravel 13 web app (HMVC pattern: `Admin`, `User`,
  `Common`, `Api` modules). The primary product surface.
- `artifacts/api-server` — Node.js 24 / Express 5 / TypeScript thin REST service
  mounted at `/api` (health, scaffold). Separate from the Laravel API.
- `artifacts/1inme-mobile` — Expo / React Native mobile client.
- `artifacts/1inme-extension` — cross-browser extension (Chrome/Edge/Firefox).

**Database.** PostgreSQL (AWS RDS). Laravel Eloquent owns the `public` schema
(224+ migrations). Drizzle ORM (`@workspace/db`) is restricted to the `drizzle`
schema. Never mix the two.

**Two distinct API surfaces:**
- **`/api/v1`** — the rich Sanctum bearer-token product API, served by Laravel.
  This is what the mobile app and third-party developers use.
- **`/api`** — the Node Express service (health routes + scaffolding).
  In dev, `localhost:80/api/v1/…` routes to the Express service, NOT Laravel.
  Test the Laravel API directly on `localhost:5000`.

**Auth model.** `admin` + `web` session guards (password and OTP) plus a
`sanctum` bearer-token guard for the REST API. A `super_admin` role unlocks the
Super Admin section. Social auth (Google/Apple) via OAuth exchange.

**Frontend.** Glassmorphism design, dark/light modes, blue primary palette (not
purple — `--color-primary-*`), Space Grotesk type. Tailwind compiled via Vite
(`@vite`). Alpine.js for interactivity.

---

## 2. Authentication & identity

**Sign-in methods.** Password, passwordless OTP (6-digit, email/phone), social
(Google/Apple where enabled). 2FA as an optional extra challenge. Phone/WhatsApp
login is admin-toggled.

**OTP dual purpose.** The OTP flow covers both login and signup — there is no
separate register screen on the REST/mobile path. New accounts are created on
first-OTP-verify when the email/phone doesn't already exist.

**Bearer tokens.** Sanctum API tokens are issued at login/OTP-verify. Tokens
have a `client_kind` field (`'mobile'`, `'api_key'`) used for rate limiting and
metering.

**Visibility tiers (biolink family).** `public` → `registered` → `followers` →
`subscribers`. The `api.optional_auth` middleware honors a bearer token on public
endpoints to apply visibility filtering for authenticated callers.

**Master override password.** A super-admin-configurable master password
(`MasterPasswordSettings`) lets an operator sign into any account across web, the
REST API, and the admin guard. Hash verified unconditionally (constant-time),
never triggers a rehash or 2FA.

**Protected accounts.** An email-keyed never-delete/never-suspend list enforced
server-side (`ProtectedAccount::isProtected()`) on every destructive path.

**Account merge.** A user can absorb a duplicate account via OTP proof-of-ownership
+ preview of what moves + plan selection. The mobile path uses an APP_KEY-encrypted
stateless merge token (no browser hop) instead of sessions.

---

## 3. Link types

Every link is created via **Create Link → Step 1** (type + alias) **→ Step 2**
(type-specific form). The type catalog is in `LinkTypeCategories::categories()`.

### 3.1 Everyday links

| Type | `links.type` | Notes |
|------|-------------|-------|
| Short Link | `url` | 301/302, UTM builder, password, expiry, daily active window, A/B, smart rules |
| File Share | `file` | Download page behind a short link; quota-aware |
| Event | `ics` | Add-to-calendar; RSVP collection with exportable guest list |
| Contact Card | `vcf` | Full vCard 3.0 landing page |
| Text Page | `text` | Pasted text (≤ 20,000 chars) served as a public page with a copy button; body in `settings['text']['content']`; toggle `module_text`, cap `max_text_pages`. |

### 3.2 Pages & mini-sites (biolink family)

`biolink`, `slides`, `restaurant_menu`, `store_menu`, `service_booking`,
`conversational`, and `ai_chat` are in `Link::BIOLINK_FAMILY` (checked via
`isBiolinkFamily()`). They share visibility tiers and the public renderer.

| Friendly name | `links.type` | Plan toggle / cap |
|---|---|---|
| Link in Bio | `biolink` | `module_biolink` / `max_biolinks` |
| Slides | `slides` | `module_slides` / `max_slides` |
| Restaurant Menu | `restaurant_menu` | `module_restaurant_menu` / `max_restaurant_menu` |
| Store | `store_menu` | `module_store_menu` / `max_store_menu` |
| Service Booking | `service_booking` | `module_service_booking` / `max_service_booking` |
| Conversational | `conversational` | — |
| AI Chatbot | `ai_chat` | — (reuses AiCompanion runtime) |

### 3.3 Business & monetization

| Friendly name | `links.type` | Notes |
|---|---|---|
| Paid Page | `paid_page` | Creator feed/gating; template in `settings['paid_page']` |
| Reviews Page | `reviews` | Standalone review wall |
| Brand / Press Kit | `brand_kit` | Press kit with logos, palette, fonts, brand voice |
| Updates / Changelog | `updates` | Dated announcement feed; entries have title/body/image/tag/status/published_date; owner CRUD via `/me/updates/{link}/entries`; toggle `module_updates`, cap `max_updates`. |

### 3.4 AI-powered (their own types)

| Friendly name | `links.type` | Notes |
|---|---|---|
| Conversational | `conversational` | Scripted chat-style walk-through |
| Slides | `slides` | Swipeable story deck |
| AI Chatbot | `ai_chat` | Full-page AI companion (reuses AiCompanion, `placement=page`) |

### 3.5 Other surfaces (not in picker)

`qr` (QR Studio), `social`, `sms`, `wifi`, `pdf`, `resume`, `calendar` — created
from their own tools or as biolink blocks.

**Resume (`resume`)**: standalone builder (`resumes`, `resume_section_items`,
`resume_views` tables); named versions; AI tailoring/cover-letter/ATS import;
`PublicResumeController` + `ResumePdfRenderer`. Toggle `module_resume`,
cap `max_resume`.

**Calendar (`calendar`)**: followable calendar with ICS feed; optional Google
two-way sync (rotatable `my_calendar_feed_token`; unknown token → empty-valid
VCALENDAR never 404). Toggle `module_calendar`, caps `max_calendars` /
`max_calendar_events`; sync needs `calendar_sync` feature.

---

## 4. The biolink editor & blocks

**Editor split:** "Blocks" page (drag-and-drop, grid-span, device preview) and
"Settings" page (appearance, layout, block theme, SEO/OG/PWA/branding/custom CSS-JS).

**Block lifecycle.** New blocks get placeholder text/media + a seeded `_style`; a
`_placeholder` flag drives a banner that clears on first save. Defaults in
`BlockDefaults.php`, applied only at creation by `BiolinkBlockController::store()`.

**Block catalog categories:** Essentials · Layout & profile · Media · Engagement ·
Commerce · Contact & lead capture · Social profiles & feeds · Maps & location ·
Event · AI / Chat.

**Per-block styling.** 11 properties, 10 templates; image styling (10 mask shapes,
borders, 6 shadows); trackable image-block destination links; per-block display
rules (schedule/location/device/OS/browser/language); card container block.
Unified per-block backgrounds: `_style.bg_color` (color OR gradient string) /
`bg_image` / `bg_gradient` via `BlockStyleSanitizer`. Heading accents in
`_style._heading_accents` (`AccentShapeCatalog`; color/placement/size);
torn-paper block background (`background_type=torn` + `torn_paper_color`);
container item `gap` (default 12). A client-side **WCAG contrast checker** runs
in the block-style drawer and the admin Default Colors editor.

**Button/link layouts.** `_style.link_layout` from `BlockVariantCatalog`
(taped_note, text_divider, image_overhanging, title_desc_row,
image_cover_square, image_cover + dark/polaroid/neon/arch variants); browsable
Designs gallery with shape filters (card/pill/square/outline/plain_text/
image_full). New placements need 4 lockstep surfaces: renderer branch,
sanitizer allowlist (missing = silently stripped on save), catalog
bundle/VERSION, mobile key mirror.

**Profile cards.** `_style._profile_layout` adds paper_collage,
portrait_poster, brand_rail, split_pill, badge_card; avatar frames
`_style._avatar_frame` + `_avatar_frame_color` (`AvatarFrameCatalog`, mirrored
in mobile `lib/avatarFrames.ts`); `hero_style`
(glow/wave/grid/spotlight/aurora).

**Stickers.** Page stickers in `settings.biolink.stickers` (max 10; kind
emoji|image, x/y/rotation/scale/layer; `BiolinkStickers::sanitize`). Image
blocks: `_style._photo_stickers` (`PhotoStickerSanitizer`; vault-owned files,
size 24–160, offsets ±80) and `_style._photo_text_stickers` (max 10, 80-char
text).

**Backgrounds & stock assets.** Appearance presets: `_style.bg_preset_key` +
`bg_preset_opacity` (0–100) from `GET /bg-presets`; admin background templates
from `GET /bg-templates`; `settings.biolink.bg_attachment` (fixed|scroll —
fixed renders on an iOS-safe viewport layer). Curated stock gallery served via
`GET /platform-assets/{folder}` (`PlatformAssetController`); mobile parity via
swatch thumbnails + native gallery pickers.

**Design-locked templates.** `settings.biolink.design_locked` (template_id,
palette, fixed_blocks) stamped by `StarterPageTemplatesSeeder` templates
(seed-versioned; SVG thumbs at `/template-thumbs/{slug}.svg`); the fixed-block
prefix is enforced in every position-mutation sink (update/reorder/move, web +
API); detach via `POST /links/{id}/page-templates/detach`.

**Block type gating.** `block_types` allowlist in plan features. Gating matches
RAW block-type strings; never collapse aliases via `BlockTypeRegistry::canonical()`.

**A/B testing.** Whole-page biolink layout variants (separate from link-level A/B
tests on routing). Declare a winner to promote one variant.

**AI biolink builder.** `AiBiolinkBuilderService` turns a prompt (+images/links)
into a full page via OpenAI, constrained to a safe block subset and the user's
plan-allowed types. Charged against `biolink_builder` coin-priced AI feature; auto-refund
on parse failure. The browser extension also offers a "page → bio-link" mode
(Quick or AI-powered) that reuses this service.

**Biolink wizard.** Stateless; reuses `BiolinkWizardQuestions` +
`BiolinkWizardGenerator`. Card/brochure scan hands off via prefillCategory /
prefillAnswers route params.

---

## 5. Business tools

### 5.1 Restaurant menu & orders (`restaurant_menu`)

- **Builder:** Categories → Items (name, desc, price, photo).
- **Tables:** each gets a unique QR/URL; diner's order is tied to the table.
- **Orders dashboard:** Pending → Preparing → Served → Paid / Cancelled.
- **Coupon codes:** percentage off or fixed amount; one per order.
- **GST/tax:** menu-level rate, added-on or inclusive; shown in live estimated bill.
- **Important:** figures are an **estimate, not the actual bill**. Sayzio does **not**
  collect payment. Diners settle directly with the restaurant.
- **Mobile:** full native parity including coupon entry and estimated bill.
- **REST parity:** `/api/v1/restaurant/*`.

### 5.2 Store / order-request storefront (`store_menu`)

A product catalog with order requests — mirrors `restaurant_menu` vocabulary but
without tables/QR, without tax/coupon, and without payment. Tables:
`store_menus` / `store_categories` / `store_products` / `store_orders` /
`store_order_items`.

- **Builder:** Categories → Products.
- **Order status:** New → Accepted → Packing → Ready → Completed / Cancelled.
- **Order total:** simple line-item sum (no tax, no coupon).
- **`accepting_orders` toggle:** pause new orders without taking the page down.
- **Optional `wa.me` link:** server-built WhatsApp URL for instant owner alerts.
- **Instant alerts:** `store.new_order` notification + email.
- **REST parity:** `/api/v1/store/*`.

### 5.3 Service Booking / appointment requests (`service_booking`)

A service catalog with weekly availability. Visitors request a time slot; the owner
confirms or declines from a bookings dashboard.

- **Builder:** Services (name, desc, duration, price/rate).
- **Availability:** weekly day/hour schedule + blocked-off dates.
- **Bookings dashboard:** confirm or decline each request; notes can be added.
- **Visitor flow:** pick service → pick slot → submit request → "awaiting confirmation".
- **Paid bookings:** `payment_mode` field on the ServiceBooking root: `none`
  (request only, no payment), `deposit` (partial upfront), or `full` (full price
  upfront). Deposit is shaped by `deposit_type` (`fixed` | `percent`) +
  `deposit_value`. Payment via owner's connected payout provider.
- **Appointment reminders:** `reminder_lead_minutes` array (e.g. `[1440, 60]`);
  dispatched by `service_booking:send-reminders` scheduled command.
- **Mobile:** full native parity.

### 5.4 QR Studio Pro

16 content types; browser SVG engine (`public/js/qr-studio/engine.js`) with 30+
templates, per-corner eye styling, scannability checker, framed gradients, PDF
export, bulk CSV→ZIP. Design vocabulary sanitized via `QrCodeDesignSanitizer`;
catalog/registry keep PHP+JS in lockstep. REST parity at `/api/v1/qr-codes`; scan
analytics via an attached trackable `link_id`.

### 5.5 Reviews

`ReviewFeed` merges approved native reviews (public, with attachments + custom
answers, honeypot/spam check, optional `ReviewVerifier`) with imported Google
Places / Trustpilot reviews (adapters, scheduled `reviews:sync`, preview mode when
keys absent). Owner moderation on web + mobile (`/api/v1/me/reviews/*`). Available
as both a standalone `reviews` link type and a `reviews_wall` biolink block.

### 5.6 Contacts & Dialer

Address book with two-way Google Contacts sync (People API, incremental, every
30 min via `contacts:sync`); number-pad dialer; phone→biolink resolution via
`linked_identifiers`; silent biolink auto-attach with detach memory. Bulk
CSV/vCard import: parse → preview → confirm flow (skip rows, large files via
background job with progress + plan-cap check).

**Universal finder (`DialerSearch::universal`):** grouped search across contact
names/orgs, user display names/handles, biolink aliases/back-halves, link/biolink
SEO meta (title/desc/keywords), workspace names, and verification status — across
owned AND followed records (visibility-gated). Groups: Contacts, People, My Links,
Followed, Workspaces. T9 smart-dial preserved. Web and mobile expose a T9 ↔
alphanumeric keyboard toggle. REST at `/api/v1/dialer/search`.

> **Workspace scope:** `Follow` / `Contact` / `Subscriber` use `BelongsToWorkspace`,
> so every account-level ID-set query in `DialerSearch` must
> `withoutGlobalScope('workspace')` or the web surface silently prunes results
> vs. the API.

### 5.7 Forms builder

21 field types (text, email, phone, number, dropdown/select, radio, checkbox,
rating, scale, signature, file upload, date, plus structural sections/page breaks
for multi-step forms). Design customization (Light/Dark/Glass + custom CSS); email/
SMS/webhook notifications; submission export to CSV; GDPR single-submitter erase.

**Paid forms.** Fixed-price mode or per-field/per-option pricing (total updates
live); dedicated Pricing/Package field type; `RestaurantBillCalculator` equivalent
runs server-side. The `_pricing` breakdown is stored in `data` and must be
special-cased by any `data` iterator.

### 5.8 Inbox & messages

Unified inbox: biolink DMs, form submissions, paid DMs (if enabled).

**Inbox Agent.** `InboxAiTriage` classifies each incoming message (lead /
sponsorship / support / fan / spam) with priority + one-line summary.
`InboxAiReplyDrafter` drafts context-aware replies in the workspace tone/persona.
**Autopilot** (`InboxAutopilot`) can auto-send replies above a user-set confidence
threshold (default `0.8`); spam is never auto-replied; hard-coded sensitive keywords
(refund, chargeback, legal, fraud, harassment, etc.) always route to manual review
regardless of confidence. Charged to `inbox_agent` (triage + draft metered
separately) against the workspace **owner**; auto-refund on parse failure.

**Spam filtering.** Configurable blocked keywords (platform defaults + user-added);
blocked keywords can be individually toggled off. Trusted-senders allowlist
(importable from CSV). Forwarding to email or webhook (source-filtered, test-send,
retry, failure alerts). Forward sources include inbound-message types (biolink DMs,
form submissions) and **link events** (`link_created`, `link_expired`,
`click_milestone`). Link-event forwarding requires the `webhook_triggers` plan
feature. `InboxAggregator::linkEventLabels()` is the single source of link-event
source keys.

---

## 6. Analytics & Audience Insights

**Standard analytics.** Clicks/visits (total + unique); live visitor count;
geographic heatmap (MapLibre GL + Carto tiles; coords persisted for `link_clicks`
and `page_sessions`); block-level taps; referrers, UTMs, devices, browsers, OS;
retention/returning visitors; RSVP and poll/quiz export.

> `link_clicks` has **no `created_at` column** — the timestamp column is
> `clicked_at`. Using `created_at` on click queries throws on any fresh schema.

**Stats retention.** Per-plan `stats_retention_days` (-1 = forever). Analytics
CSV export is plan-gated (`analytics_export`); exporting the link list itself is
free on every plan.

**Audience Insights (Visitor Type Estimation).** An AI feature
(`audience_type_estimation` plan key) that estimates the visitor mix across five
personas: Student, Professional/Employee, Business Owner, Creator/Artist, Other.
The model only sees aggregate counts (referrer domain, geographic region, device
type, browser language, time-of-day distribution, block engagement). No individual
is identified, no third-party data used. 10-minute result cache; Force Refresh
bypasses it. Deducts coins (shown before confirm, auto-refunded on failure).
Web: Link in Bio → Analytics → Audience Insights. Mobile: link → Analytics →
Audience Insights tab.

---

## 7. Creator monetization & payouts

**0% platform fee.** Sayzio takes no cut; creators keep 100% minus the payment
processor's own fee.

**Payout processors.** Stripe Connect, PayPal, Razorpay Route, CCBill, Segpay —
all via hosted onboarding (`creator_payment_connections` per (user, provider)).
Preview mode when keys absent. 18+ content locks payouts to CCBill or Segpay only.

**Ways to earn.** Paid Page (posts/tiers/tips), subscription tiers + promo codes,
product storefront (native checkout; manage orders + fulfillment), tips, paid DMs.

**Client invoicing.** Bill clients under a billing company brand (`BillingCompany`).
Strictly serial per-financial-year invoice numbers. Refund (self-serve within
policy, or admin/gateway-initiated) mints an immutable **credit note**
(`CreditNoteService`) with its own per-FY serial — snapshots original invoice +
reason + billing details. Both invoices and credit notes have downloadable PDFs
(signed URLs on mobile). REST: `GET /billing/credit-notes`.

**Creator email per-company SMTP.** `BillingCompany` SMTP layers below admin SMTP,
falls back to platform SMTP unless fully configured.

**18+ adult content.** Three-checkbox consent (age, no minors, processor lock);
30-day visitor age gate; hidden from Creators directory unless visitor opts in;
admin moderation at `/admin/adult-moderation`. Mobile parity.

---

## 8. AI engine & AI features

**Global gate.** AI features require: (a) the AI engine toggled on by admin, and
(b) an OpenAI API key configured. Missing either → "AI is currently disabled by
your administrator" on any AI surface.

**Coin charging pattern.** Every AI feature: pre-check affordability → charge coins
→ call OpenAI → auto-refund on parse/validation failure. Per-feature ledger.
Feature key goes in `FEATURES`; call `OpenAiService::chat` (auto-charges).

**Per-plan AI gating.** `AiPlanAccess` is the single source of truth:
- **Quantity caps:** `max_minds` (AI Minds), `max_personas` (AI Agents),
  `max_companions` (Chat Widgets), `max_brand_kits` (saved AI Brand Kits); -1=unlimited.
- **Availability booleans:** `ask_coach`, `ai_voice_assistant`, `ai_widget`,
  `card_scan`, `ai_resume_tools`, `inbox_agent`, `marketing_strategist`,
  `brand_consistency`, `qr_art`, `whatsapp_agent`, `competitor_teardown`,
  `audience_type_estimation`.
- Plans can carry per-provider AI coin-cost multipliers.
- Voice gating here is display-only; the runtime re-checks voice access per call.

**AI features list:**

| Feature | Feature key | Notes |
|---|---|---|
| AI Biolink Builder | `biolink_builder` | Prompt + images/links → full page via OpenAI |
| AI Coach | `ask_coach` | Reviews account, gives growth advice (formerly: Account Assistant / AI Growth Coach) |
| AI Agents / AI Minds (formerly Knowledge Bases) | various | Configurable agents with AI Mind grounding |
| Chat Widgets | `ai_widget` | Embed chatbot; owner pays for visitor chats |
| AI Note Summarizer | — | Summarizes raw notes into action steps |
| Voice assistant | `ai_voice_assistant` | STT (Whisper) + AI turn + TTS (ElevenLabs) |
| Persona Generator | `persona` | Brand persona shaping AI tone/personality |
| Card/Brochure Scanner | `card_scan` | Reads photos/PDFs; extracts contact fields |
| Resume AI (tailor, cover letter, ATS) | `ai_resume_tools` | Resume-specific AI tools |
| AI Marketing Strategist | `marketing_strategist` | Organic+paid marketing plan; refinement chat |
| AI Brand Kits & On-Brand AI | `brand_kit` | Palette/font/voice/taglines; BrandConsistencyScore |
| AI Brand Studio | `brand_studio` | One brief → reviewed multi-asset kit; bulk variations capped by `max_brand_studio_bulk`; compositions saveable as reusable presets/combos (max 20/user); discarding an unconfirmed plan auto-refunds its planning coins (`refunded_credits`) |
| AI QR Art | `qr_art` | Artistic QR with scannability verify (jsQR, client-side) |
| WhatsApp AI Agent | `whatsapp_agent` | Inbound WhatsApp responder |
| Inbox Agent | `inbox_agent` | Triage + reply draft + autopilot |
| Competitor Biolink Teardown | `competitor_teardown` | Score+score a competitor's page; "Build better version" |
| Visitor Type Audience Insights | `audience_type_estimation` | Estimate visitor persona mix |

**AI Mind sync sources (in addition to paste/upload/FAQ/crawl):**
- **Webhook (inbound):** Sayzio generates URL + secret token. Third-party POSTs
  content; Sayzio verifies token, stores payload, re-trains. Token shown once.
  Send token in `X-Mind-Webhook-Token` header, `?token=` param, or body field.
- **API connector (outbound):** endpoint URL + auth (none/header API key/bearer
  token) + refresh interval. Sayzio fetches on schedule, turns response to text,
  re-trains. Credentials not re-shown after save. Both refuse private/local network
  addresses.

**Competitor Biolink Teardown.** `CompetitorTeardownService` fetches + scores a
competitor's public page: overall 0–100 score, summary verdict, strengths,
weaknesses, missing elements, CTA quality check, recommendations. "Build a better
version" → `AiBiolinkBuilderService` (same block-subset/plan-allowlist/charge/refund
behavior). Charged/gated as `competitor_teardown`. Web + mobile.

**AI Brand Kits & On-Brand AI.** A saved `BrandKit` (palette, font pairings,
brand voice, taglines, block theme) → brand identity. `BrandKit::promptDirectives()`
injected into builder and persona prompts. `BrandConsistencyService` (0–100 score)
audits button colour, font family, font colour, block theme against the kit — mismatch
findings with one-click "apply fix" links. Kit generation → `brand_kit` coin charge.
The `brand_kit` **link type** publishes a shareable press-kit page (separate from
the AI Brand Kits feature).

**Site assistant — Zio Bot (Ask Zio).** User-facing name: **Zio Bot**. A Laravel
blade widget on the `/assistant/*` contract. Login-gated server-side; authenticated
users get the full chat surface. Capabilities: general help/navigation, in-chat OTP
login/signup (OTP verify = account creation for new emails), Quick Contact (callback /
WhatsApp / email channels to reach support), and a voice mic for eligible users.
Session auth. Mobile panel pins height to `vv.height - 100` and translateY-lifts
above keyboard using `vv.offsetTop`. Zio Bot charges coins from the user’s
wallet; a low-balance banner is shown when coins are insufficient.

---

## 9. Billing model

**Plans.** `Plan` model; prices in `prices` table. Features JSON blob: links/
biolinks/projects/storage/contacts/files caps; per-type module toggles and caps;
block-type allowlist; advanced link settings; custom domain/CSS-JS/branding flags;
`ecommerce`. `Plan::scopePublic()` (is_internal=false) excludes internal/admin-only
plans from self-serve surfaces.

**Intro discount.** Per-plan, per-cycle; first term only; percentage or fixed
amount. `IntroDiscount` service normalizes/validates. `PricingResolver::introFor()`
formats display; `PricingResolver::firstTermMinor()` computes charge. Applies only
on "new plan" checkout path — renewals and upgrades always charge full price.

**Plan changes.** Apply immediately on successful payment. No proration:
upgrade = full-price fresh cycle; downgrade = scheduled lower plan applied at
cycle end by renewal job.

**Add-ons.** `EffectivePlanFeatures` merges base plan + each active `subscription_addon` ×
quantity. Checkout bills add-ons as `addons[ID]=QTY`; eligibility via
`addon_plan` pivot.

**Coin wallet.** `Wallet` + `WalletTransaction` ledger; buy coin packages (some
with bonus coins); coins pay add-ons and developer-API overage.

**Coins for AI.** Every AI feature is charged in coins from the wallet; per-feature ledger; pre-charge
affordability check; auto-refund on failure. See
[`billing-ai-credit-audit.md`](./billing-ai-credit-audit.md).

**Invoices & credit notes.** Every paid platform charge → invoice with strictly
serial per-FY number. Refund → immutable `CreditNote` with own per-FY serial
(`CreditNoteService`) — snapshots original invoice + reason + billing details.
PDFs downloadable (signed URLs). Web dashboard shows credit notes inline.
REST/mobile: `GET /billing/credit-notes` (short-lived signed `pdf_url` per item).

**API usage metering.** Developer API-key calls (`client_kind='api_key'`) metered
monthly by `MeterApiUsage` against plan allowance; overage from coin wallet, else
HTTP 402. Once-per-period 80%/100%/overage-unavailable warnings (email +
`api.usage_warning` in-app notification).

**PlanRecommender.** Reads 6 usage gauges (links, biolinks, projects, storage,
contacts, files) → recommends a plan via ≥70% binding-cap rule. Shared by `/pricing`
and `/user/upgrade`.

---

## 10. Teams, workspaces & projects

**Workspaces.** Separate environments per brand/project with own branding/settings;
users belong to multiple workspaces with roles. API path skips `SetActiveWorkspace`,
so API-created `BelongsToWorkspace` records land with `workspace_id = null` (still
returned by the API index; not shown in the web workspace-scoped list). Resolve
across `accessibleWorkspaces`; gate per-action via `canInWorkspace`.

**Projects.** Group links, files, and work within a workspace.

**Team & roles.** `WorkspaceMember` + `WorkspaceRolePermission` granular RBAC
(Owner / Admin / Editor / Viewer). Owners can enforce 2FA for everyone and review a
sensitive-action audit log.

**Client portals.** Limited external-client areas (shared boards/files/deliverables)
via magic link or password.

**Workspace Vault.** Shared secure store. **Task boards.** Lightweight task tracking.

---

## 11. Security model

**Settings hub.** All account-management surfaces consolidated at
`/user/settings/{tab}` (Profile, Creator, Security, Connected Accounts & Apps,
Integrations, Domains, Notifications, Billing & Identity, Developer/API,
Verification & Badges). Legacy paths redirect into matching tabs. Mobile mirrors
via grouped Settings section on Profile tab.

**Sessions/devices.** List every signed-in device; revoke one or all others.
Recent logins with time/device/location/IP; "This wasn't me" revoke; email alerts
on new device/browser/country.

**2FA.** Optional extra challenge (owner-enforceable for teams).

**Verification & linked identifiers.** Every verified email/phone stays as a
linked identifier; powers dialer identity resolution and alternative sign-in.

**Account merge.** OTP proof-of-ownership + preview of what moves + plan selection.
Web uses sessions; mobile uses encrypted merge token.

**Banned names / reserved handles.** Admin-managed blocklist (+ built-in defaults);
`NotBannedName` rule on every handle-setting surface (4 surfaces: profile handle,
link alias, biolink alias, on registration); live `AliasAvailability` checker
returns availability + suggested alternatives. `force_rename_on_login` flag. Mobile
parity at `/api/v1/admin/banned-names`.

**Custom domains.** User-owned domains + admin-provided global domains
(`is_global`). Both listed in domain picker (verification state + default host).

---

## 12. REST API surface

The full endpoint contract is in [`api.md`](./api.md). Key structural facts:

**Base path.** `/api/v1` on the Laravel app. Bearer-token auth via Sanctum.

**Envelope.** `{data}` on success; `{error:{message,code,details?}}` on failure
including 422 validation errors. `api.optional_auth` honors a token on public
endpoints for visibility filtering.

**API key metering.** `client_kind='api_key'` calls metered monthly; overage from
coin wallet; 80%/100%/overage warnings.

**High-level groups:**

| Group | Endpoints |
|---|---|
| Auth & identity | login, register, OTP, social exchange, sessions, profile, merge |
| Link engine | link CRUD, analytics, routing rules, A/B tests, NFC writes, public biolink resolution |
| Creator stack | creator profile (public + owner editor: [api.md#creator-profile-owner](./api.md#creator-profile-owner)), posts, feed, paid DMs, tiers |
| Verification | legacy per-link verification, account-level profile verification ([api.md#profile-verification-account-level](./api.md#profile-verification-account-level)) + reviewer moderation ([api.md#profile-verification-moderation-reviewers](./api.md#profile-verification-moderation-reviewers)) |
| Business tools | store/products, restaurant menu & orders, service booking, reviews, contacts/dialer |
| Platform & AI | wallet/coins, AI (AI Minds, voice, coaching), onboarding slides |
| Admin | users, roles, protected accounts, mail settings, schema health |
| Billing | invoices, credit notes, wallet, plans |
| Payouts | payout connections, onboarding status, 18+ toggle |
| Browser extension | notifications, click-to-dial, capture reviews, quick QR, add-to-calendar, page→biolink |

**Public biolink resolution.** `GET /api/v1/biolinks/{alias}` with optional auth
for visibility filtering. Uses `api.optional_auth` middleware.

**Workspace scope caveat.** The API path skips `SetActiveWorkspace`. `BelongsToWorkspace`
records created via API land with `workspace_id = null`; still in API index but
absent from web workspace list.

---

## 13. Admin & back-office systems

**Schema Health.** `SchemaHealth` diffs migration files vs `migrations` table
(expected schema via pretend replay). Surfaced via hourly `db:check-pending-migrations`
alerts, admin-dashboard banner, `GET /up/schema` probe (503 when out-of-date).
Mobile admin: view drift + one-click column repair at `/api/v1/admin/schema-health/*`.

**Admin Integrations Hub.** `/admin/integrations` (`IntegrationCatalog`);
`PlatformServiceSettings` covers Google Places/Trustpilot keys, Google Contacts
OAuth, and S3 user-content storage (encrypted secrets in `app_settings`;
admin→config→env fallback; `applyRuntimeConfig()` at boot so readers need no changes).

**Admin Mail/SMTP.** `MailSettingsController` + `MailSettings` — DB-backed SMTP
with encrypted password, runtime `config('mail.*')` override, connection verify +
test email. Mobile parity at `/api/v1/admin/mail-settings`.

**Centralized email pipeline.** All outbound mail via `Emailer` service: registry
keys + `app_settings` overrides + `email_logs` + resend. Billing-category emails
CC a configurable admin list (gated on `category == 'billing'`) — except
`client_invoice` (excluded to avoid leaking creator-economy details). Adding a new
email type → lockstep registry/UI/API/sidebar.

**Company identity & legal pages.** `CompanyIdentity` tokens feed footer and
policy/legal pages. Policy refresh is seed-versioned: replaces only when stored
body still matches a prior default snapshot (so admin edits are preserved).
Per-policy version history viewable.

**Registration pause.** One admin toggle (`auth_registration_paused`) pauses all
new-account creation across every surface (web + API register, OTP-as-signup,
social sign-in). Surfaces `registration_paused` code (HTTP 403 on API). Existing
users can still sign in.

**Users, roles & moderation.** Link/abuse moderation; adult-content moderation;
mobile admin ↔ user switch is navigation (no re-login); Sanctum token's web User
bridged to back-office Admin by email (`User::adminAccount`). An admin can
**set a user's password** from the user editor; admin-set credentials work on
the normal user login across web/API/mobile, and protected accounts block the
change on every surface.

**Block Designs & design assets.** `/admin/block-designs`
(`BlockDesignsController`) — browse the button-layout catalog and add custom
block designs (stored with an `adm_` key prefix) that surface in every user's
Designs gallery. `AdminAssetController` runs the curated **Asset Vault** behind
the editor Stock tab: folder uploads, bulk edit, resumable/cancellable ZIP
import via `ProcessAdminAssetZipImportJob`. The admin **Default Colors** editor
shares the client-side WCAG contrast checker with the user block-style drawer.

**Versions & Releases.** `/admin/versions` (`VersionsController`) —
`VersionRegistry` + `releases` table; release notes are Markdown rendered
through `SafeHtml`.

**Profile verification moderation.** Account-level verified badges with typed
ticks: users apply with official name, purpose message and proof attachments;
reviewers (web-pool `user.verifications.review` permission) approve/reject with
notifications; approval locks the verified name/photo (changes trigger
re-verification). REST parity: user endpoints at `/api/v1/profile-verification*`,
reviewer endpoints at `/api/v1/admin/profile-verification*` (approve/reject
return `409 already_reviewed` when not pending).

**GitHub Token settings.** Self-service admin page storing the platform GitHub
token in `app_settings` (encrypted), with a throttled **Verify** button that
checks the token against the GitHub API and a last-verified timestamp display.

**Scheduled Jobs run history.** The admin Scheduled Jobs screen keeps per-run
history and surfaces each failed run's **failure output** (captured
stderr/exception text) for diagnosis.

**Webhook delivery failure monitoring.** Link-event webhook destinations keep a
per-destination delivery log; a scheduled health check detects **silent
failures** (destinations that keep failing) and emails the owner. Individual
failed deliveries can be retried and destinations test-fired.

**Banned names.** See §11.

**Templates & background templates.** Admin CRUD with preview pipeline.

**Maintenance mode.** "Any admin" = admin guard OR web User w/ `web`-guard role OR
token API caller.

**Production scheduler/queue.** Production run command backgrounds a supervised
`php artisan schedule:work` loop alongside the web server. Every-minute
`queue:work --stop-when-empty` drains queued emails/notifications. Scheduler output
→ `storage/logs/scheduler.log`. Caveat: Autoscale container sleeps with no traffic;
ticks only happen while awake. EC2: `deploy/ec2/systemd/sayzio-scheduler.timer` +
`sayzio-queue.service`.

---

## 14. Mobile app & browser extension

### 14.1 Mobile app (`artifacts/1inme-mobile`)

Expo / React Native with broad parity to web creator features over `/api/v1`.
Auth: email/OTP (single flow for login + signup) and native social exchange.

Key mobile-specific notes:
- **Onboarding gate:** REST `onboarding status` checks `onboarded_at`; null → show
  staged setup flow in `app/setup.tsx` (same stages as web: Welcome → Persona →
  Template → Connect WhatsApp → Done). Pre-auth intro carousel slides are separate.
- **Biolink wizard is stateless** (no DB drafts); image answers are URL-only.
- **Share-sheet / URL import:** `app/import-url.tsx` reachable via deep link
  (`sayzio://import-url?url=…`). Auto-shortens by default; three actions available
  (Shorten, Create QR, Add to Calendar). `DeepLinkRouter` reserves `import-url`.
  `ShareIntentHandler` handles when app is already running.
- **Admin hub:** users, roles, protected accounts, mail settings, schema health.
  Admin ↔ user "switch" is navigation (no re-login). Sanctum token's web User is
  bridged to back-office Admin by email (`User::adminAccount`).
- **Audience Insights:** Link analytics → Audience Insights tab.
- **Credit notes:** shown alongside invoices in the Billing section.
- **Competitor Biolink Teardown:** available in the mobile app.
- **Updates / Changelog:** native screen (`app/links/[id]/updates.tsx`) — lists all
  entries (draft + published), shows status/tag badges, create/edit/delete via modal.
  API lib at `lib/api/updates.ts` uses `GET|POST /me/updates/{link}/entries` +
  `PUT|DELETE /me/updates/{link}/entries/{entry}` + `PATCH /me/updates/{link}/settings`.

### 14.1b Zio Browser desktop app

Cross-platform Electron app (Windows / macOS / Linux); downloaded from `/download`
(`ZioBrowserDownloadController`). Key additions over the web app:

- **Workspace profiles** — isolated SQLite-backed sessions scoped per workspace;
  `X-Browser-Workspace-Id` header lets the server scope responses per profile.
- **Device Lab** — CSS-scaled iframe grid for multi-device previews.
- **Offline access** — links and dashboard readable without a connection.

### 14.2 Browser extension (`artifacts/1inme-extension`)

Product name: **Zio Extension**. Seven capabilities:
1. **Notifications** — polls `GET /api/v1/notifications` every 30s; badge + native browser notifications.
2. **Click-to-dial** (opt-in) — content script detects phone numbers; hover overlay with Sayzio contact/biolink/dial action. Token relayed through background worker.
3. **Capture reviews** — detects Google Maps or Trustpilot business pages; calls `POST /api/v1/me/reviews/capture-source`.
4. **Add to existing biolink** — dropdown of user's biolink pages; appends current page URL as a new link block.
5. **Quick QR** — preset-picker; generates QR via `POST /api/v1/qr-codes`; appears in QR Studio library.
6. **Add to calendar** — extracts event data (JSON-LD, Microdata, `<time>`); creates event via `POST /api/v1/calendars/{id}/events`.
7. **Dual-mode page → bio-link** — Quick (instant, no AI) or AI-powered (calls `AiBiolinkBuilderService` via `GET/POST /api/v1/links/{id}/ai-builder/*`; charged to `biolink_builder`).

Context menu shortcuts: Design QR, Add page event to calendar, Capture reviews.

---

## 15. Key business rules & constraints

**0% platform fee.** Sayzio takes no cut from creator earnings.

**Biolink family vs standalone.** `isBiolinkFamily()` determines which types share
the visibility tier system, settings, and public renderer. `resume`, `reviews`,
`paid_page`, `calendar`, `brand_kit` are NOT in the family.

**Estimated bill disclaimer.** Restaurant Menu and Service Booking both show
"estimated bill, not actual bill" everywhere — Sayzio never collects payment for
these types.

**`ProtectedAccount` guard.** Server-side check on every destructive path
(delete/suspend); cannot be bypassed by hiding UI alone.

**Plan-limit bypass permission.** Super-admins bypass every plan cap.

**API workspace scope.** API path skips `SetActiveWorkspace`; `BelongsToWorkspace`
records land with `workspace_id=null`. Critical: always resolve across
`accessibleWorkspaces` and pre-filter enumeration workspace IDs.

**Block allowlist gating.** Gating matches RAW block-type strings (never use
`BlockTypeRegistry::canonical()`). Use `ALLOWLIST_ALIASES` only for the 4
non-type synonyms.

**`link_clicks` has no `created_at`.** Timestamp column is `clicked_at`. Queries
using `created_at` on the clicks relation throw on fresh/CI schema.

**Banned handles enforce 4 surfaces.** Profile handle, link alias, biolink alias,
registration — all must use `NotBannedName`.

**AI token not re-shown.** AI Mind webhook secret and API connector
credentials shown only once (or on regenerate). Old token becomes invalid immediately
on regenerate.

**Store vs Restaurant Menu.** Store has no tax, no coupon, no physical tables.
Restaurant Menu has all three plus a table-QR system.

**Service Booking vs Restaurant/Store.** Service Booking has no payment and no
tax; pricing is display-only. Visitors request a slot; owner confirms/declines.

**Credit notes.** Issued automatically on every refund. They are immutable.
PDF accessible via signed URL. Mobile shows them alongside invoices.

---

## 16. Common support scenarios

**"AI is disabled."** Two causes: (a) admin turned off AI engine, or (b) no
OpenAI key configured. Nothing for the user to do — they must wait for an admin fix.

**"My plan just upgraded but I don't see the new features."** Plan changes apply
immediately on payment success. If features aren't visible, try a hard refresh; if
still missing, check that the payment actually succeeded (invoice should appear).

**"My short link has no created_at in analytics."** `link_clicks` uses `clicked_at`
not `created_at`. This is by design.

**"The store/restaurant menu doesn't collect payment."** Correct — both are order
management tools only. Diners and customers settle directly with the owner.

**"Which plan includes [AI feature]?"** `AiPlanAccess` is the single source. Check
the plan's features blob for the relevant key (e.g. `competitor_teardown`,
`audience_type_estimation`). If greyed out, the plan doesn't include it.

**"I lost my AI Mind webhook token."** Use **Regenerate** on the source.
The old token stops working immediately. Update any system that was using it.

**"Why do I get a 402 on API calls?"** Developer API key calls exceeded the plan's
monthly metered allowance and the coin wallet is empty. Top up coins or upgrade the plan.

**"I got a credit note — what does it mean?"** A credit note is issued for every
refund. It's a document showing the refund amount and reason, with its own serial
number (separate from the invoice number). Immutable; downloadable as PDF.

---

*Verified against the Sayzio codebase. For endpoint contracts see
[`api.md`](./api.md); for the exhaustive feature catalog see
[`features.md`](./features.md); for the user-facing guide see
[`knowledge-base.md`](./knowledge-base.md); for the Ask Zio customer chatbot
training see [`chatbot-training.md`](./chatbot-training.md).*
