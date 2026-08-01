# Ask Zio Training Document

> **Purpose.** This is the training document for **Ask Zio** — Sayzio's
> customer-facing AI assistant. It is a single, self-contained, customer-facing
> reference written in plain English. Ask Zio uses this document to answer
> user questions about what Sayzio does, how features work, and what to do next.
>
> **Scope.** Every customer-facing feature is explained from the user's point of
> view — *what it is*, *what it does*, and *how to use it* — followed by a large
> FAQ. Admin/back-office tools, internal architecture, and raw API endpoint
> contracts are intentionally excluded here (those live in
> [`claude-training.md`](./claude-training.md) and [`api.md`](./api.md)).
> Plans, the coin wallet, coins, and add-ons are explained conceptually
> (how they relate to features), not as a price list.
>
> **Sibling docs.** [`knowledge-base.md`](./knowledge-base.md) is the
> user-guide/FAQ for the help center. [`claude-training.md`](./claude-training.md)
> is the comprehensive technical training doc for internal AI assistants.

---

## Table of contents

**Part 1 — Feature reference**
1. [What Sayzio is](#1-what-1inme-is)
2. [Getting started: accounts & sign-in](#2-getting-started-accounts--sign-in)
3. [Your profile & public handle](#3-your-profile--public-handle)
4. [Plans, coins & add-ons (concepts)](#4-plans-coins--add-ons-concepts)
5. [Links: the basics](#5-links-the-basics)
6. [Every link type](#6-every-link-type)
7. [Link management & routing](#7-link-management--routing)
8. [The biolink editor & blocks](#8-the-biolink-editor--blocks)
9. [Biolink settings (appearance, SEO, branding, PWA)](#9-biolink-settings)
10. [AI biolink builder & wizard](#10-ai-biolink-builder--wizard)
11. [QR Studio Pro](#11-qr-studio-pro)
12. [Analytics (incl. Audience Insights)](#12-analytics)
13. [Audience & engagement](#13-audience--engagement)
14. [Reviews](#14-reviews)
15. [Referrals](#15-referrals)
16. [Creator monetization & payouts](#16-creator-monetization--payouts)
17. [18+ adult content](#17-adult-content)
18. [AI tools](#18-ai-tools)
19. [Forms](#19-forms)
20. [Digital contact cards (vCard)](#20-digital-contact-cards-vcard)
21. [Social proof (Buzz)](#21-social-proof-buzz)
22. [Contacts & dialer](#22-contacts--dialer)
23. [Scan a card or brochure](#23-scan-a-card-or-brochure)
24. [Restaurant menu, Store & Service Booking](#24-restaurant-menu-store--service-booking)
   - [24a. Restaurant menu](#24a-restaurant-menu)
   - [24b. Store (order-request storefront)](#24b-store-order-request-storefront)
   - [24c. Service Booking (appointment requests)](#24c-service-booking-appointment-requests)
25. [Resume / portfolio](#25-resume--portfolio)
26. [Files / Vault](#26-files--vault)
27. [Inbox & messages](#27-inbox--messages)
28. [Notifications & digests](#28-notifications--digests)
29. [Organizing your work: projects, workspaces, teams, client portals](#29-organizing-your-work)
30. [Security & sessions](#30-security--sessions)
31. [Settings & integrations](#31-settings--integrations)
32. [Mobile app & browser extension](#32-mobile-app--browser-extension)

**Part 2 — [Q&A / FAQ](#part-2--qa--faq)**

---

# Part 1 — Feature reference

## 1. What Sayzio is

**What it is.** Sayzio is an all-in-one link-management platform. From a single
account you can create short links, a "link in bio" mini-site, QR codes, digital
contact cards, file-share links, event pages, resumes, restaurant menus, review
pages, monetized pages, and more — then customize, brand, track how they perform,
and even get paid through them.

**Why people use it.** Instead of juggling a separate link shortener, bio-link
tool, QR generator, form builder, email collector, and payment processor, Sayzio
puts them all in one place with shared analytics under a single public handle
(for example, `sayzio.app/@yourname`).

**Who it's for.** Creators, small businesses, freelancers, restaurants, and
anyone who wants a polished, trackable online presence.

---

## 2. Getting started: accounts & sign-in

**Registering.** Go to the sign-up page and enter your name and email (a phone
number may be allowed depending on platform settings). Set a password, or use the
passwordless option. New accounts land in a short **onboarding** flow that asks
what kind of page you want (e.g. Creator, Business) and offers a starter template
so you can launch your first page in minutes — you can skip it and explore on your
own.

**Ways to sign in.**
- **Password login** — email + password.
- **One-time code (OTP / passwordless)** — choose "sign in with a code" and Sayzio
  emails (or texts) you a **6-digit code**. Enter it to sign in without a
  password. Handy on a new device or when you've forgotten your password.
- **Social sign-in** — "Continue with" buttons for supported providers (e.g.
  Google/Apple), if enabled on your platform.
- **Two-factor authentication (2FA)** — if you've turned it on, you'll be asked
  for an extra challenge code after your password.

Which methods appear depends on the platform's configuration. Email is always
available; phone/WhatsApp login is an optional toggle.

**Onboarding.** A guided first-run experience: pick a persona/goal, choose a
template, and Sayzio drops in a starter layout you can immediately edit. You can
re-run or skip it anytime.

---

## 3. Your profile & public handle

**What it is.** Your public creator identity — handle, display name, bio, avatar,
and discoverability settings, found under **Profile** / **Creator Profile**.

**What it does.** Your handle (`@yourname`) is the address people use to find you,
and it powers the public **Creators** directory.

**How to use it.** Open **Profile**, set your display name, handle, bio (up to
about 500 characters), and upload an avatar. Set your timezone and language so
scheduling and daily digests arrive at the right time. Toggle "Show me in the
public Creators directory" if you want to be discoverable (this may require a
specific plan).

**Showcase, theme & preview.** The Creator settings tab lets you dress up your
public `/@handle` page with **featured links** (shared display style, drag to
reorder, per-link hide toggle), **tabs**, **highlights**, and a **call-to-action
button**, plus a **profile theme color**. Your avatar defaults to your account
profile photo unless you set a profile-specific one, and hovering a creator's
name elsewhere shows a **mini profile card**. While editing you get a **live
preview** with Small / Medium / Large density and a dark/light toggle.

**Verified badges.** Under **Settings → Verification & Badges** you can apply
for an account-level verified badge (different badge "tick" types exist):
submit your official name, a purpose message, and proof attachments; a reviewer
approves or rejects and you're notified either way. After approval your
**verified name and photo are locked** — changing them requires
re-verification.

---

## 4. Plans, coins & add-ons (concepts)

Sayzio's pricing is built from a few simple pieces. The emphasis below is on *what
they are and how they relate to features* — not specific prices.

**Plans.** Sayzio offers several subscription tiers. Higher tiers raise your
**limits** (how many links, biolinks, projects, contacts, files, and how much
storage you get) and unlock **premium features** (custom branding, custom
CSS/JS, custom domains, certain link types, and more). Free accounts are capped;
upgrading raises those caps. When you open **Plans** (in-app) or the public
**Pricing** page, Sayzio shows a **personalized recommendation**: it measures how
close you are to your current limits and highlights a recommended plan, with a
comparison matrix so you can decide. Plan changes apply immediately once payment
succeeds.

**Coins (coin wallet).** A prepaid in-app balance you top up by buying **coin
packages** (some include bonus coins). Coins pay for optional extras — for
example certain add-ons, or developer-API usage beyond your plan's monthly
allowance. Your balance and a running transaction ledger are always visible in
your **Wallet**.

**Coins for AI.** Coins also power Sayzio's AI features (the
AI biolink builder, AI Coach, AI Agents/AI Minds, resume tailoring and cover
letters, the card/brochure scanner, the voice assistant, and visitor chats with
your Chat Widget). Each AI action is billed automatically. Before an action
runs, Sayzio checks you can afford it — if your balance is too low you're prompted
to top up rather than charged for something that can't finish. Many failed AI
runs are automatically refunded, and there's a coin ledger so you can see where
coins went.

**Add-ons.** Optional extras you can attach to your plan to expand specific
limits or capabilities beyond what your base tier includes. They're billed
alongside your subscription.

**How they relate.** Your **plan** sets your everyday feature limits; **coins**
top up optional extras, overage and every AI feature;
**add-ons** extend particular limits without changing your whole plan.

---

## 5. Links: the basics

**What a link is.** In Sayzio a "link" is any shareable item you create — from a
plain short URL to a full mini-site. Everything lives under **All Links**.

**How to create a link.**
1. Click **Create Link** / **New Link**.
2. **Step 1** — pick a **type** (short link, biolink, QR, file, event, etc.) and
   either choose a custom **alias** (the slug after your domain) or leave it blank
   to auto-generate one.
3. **Step 2** — fill in the type-specific form (e.g. the destination URL for a
   short link, or the file for a file link).
4. Save. Your link is live immediately and appears in **All Links**.

**Managing links.** In **All Links** you can search, filter by type/project/
status, edit, duplicate, move into a project, reset analytics, or delete a link.

---

## 6. Every link type

All link types are created the same way (pick the type in **Create Link**). The
picker groups them into **Everyday links**, **Pages & mini-sites**, **Business &
monetization**, and **AI-powered**, plus some types created from their own tools
or as biolink blocks.

| Type | What it is / why use it |
| --- | --- |
| **Short Link** | Shorten any long URL with a custom alias and track clicks — the everyday workhorse. |
| **Link in Bio (Biolink)** | A customizable mini-site that gathers all your links, media, and content on one page — your "link in bio". Built with the biolink editor. |
| **File Share** | Host a downloadable file behind a branded download page and short link. |
| **QR Code** | A trackable QR code, designed in QR Studio Pro. |
| **Event** | A page visitors can add to their calendar in one tap, with RSVP collection. |
| **Contact Card (vCard)** | A digital business card; visitors tap "Save Contact" to download your `.vcf`. |
| **Text Page** | Paste or type any text and share it as a clean page behind a short link. Visitors can select the text or copy it all with one tap. |
| **SMS** | A tap-to-text link that pre-composes a message to a number. |
| **WiFi** | A tap-to-join link/QR that connects visitors to a WiFi network. |
| **PDF** | Share a PDF behind a viewer/download page. |
| **Conversational** | A guided, chat-style page that walks visitors through your links one message at a time on a fixed script. |
| **Slides** | A swipeable, story-style deck served from a single link — great for presentations or portfolios. The editor supports inline in-slide block editing, a live device preview, per-slide background colors/images, and auto-play (web and mobile). |
| **AI Chatbot** | A full-page AI assistant that answers visitors' questions about you, powered by your Chat Widget and AI Minds. |
| **Restaurant Menu** | A digital menu with categories, items, prices, photos, and optional table-side ordering. |
| **Resume / Portfolio** | A shareable, professional resume page with PDF download and AI tooling. |
| **Reviews Page** | A standalone wall for collecting and showcasing star reviews. |
| **Paid Page** | A monetized landing page that automatically shows your posts, tiers, and tips, gated by visibility/payment. |
| **Product / Storefront** | Sell digital or physical products with native checkout (also available as a biolink block). |
| **Store** | An order-request storefront: list products in categories and take orders (name, contact, note) with **no online payment** — you fulfil them offline from an owner dashboard. |
| **Service Booking** | An appointment-request page: list services with duration and price, publish your availability, and let visitors request a slot. You confirm bookings from a dashboard — no payment is collected, any total shown is an estimate. |
| **Calendar** | A followable calendar of your events with an ICS feed; on supported plans it stays in two-way sync with your connected Google calendar. |
| **Updates / Changelog** | A dated announcement feed for product updates, release notes, or announcements. Each entry has a title, body, tag (feature / fix / improvement / breaking / announcement), date, and draft/published status. |
| **Brand / Press Kit** | A shareable page with your logos, colours, fonts, brand voice, and boilerplate — handy for press and partners. |
| **Social** | Link and manage your connected social accounts. |

**The biolink family.** Link in Bio, Conversational, Slides, AI Chatbot,
Restaurant Menu, Store, and Service Booking belong to the "biolink family" — they
share the page-editor flow, settings, visibility tiers, and the public renderer
(Restaurant Menu, Store, and Service Booking use their own dedicated builders).
Resume, Reviews, Paid Page, Calendar, and Brand/Press Kit are standalone types
rendered by their own pages.

**Visibility tiers.** Biolink-family pages can be set to **Public**, **Registered
users only**, **Followers only**, or **Subscribers only**, and some pages can be
**password-protected**. Visitors who don't meet the tier are blocked or prompted
to follow/subscribe.

---

## 7. Link management & routing

These tools apply to short links and, where relevant, the broader link set.

- **Aliases.** Every link has a primary **alias** (the slug). You can add
  **additional aliases** — alternative slugs that serve the same page without a
  redirect.
- **A/B testing.** Run two or more weighted variants of a link with sticky
  assignment (e.g. a 50/50 split), watch the stats, then **declare a winner** to
  promote the best-performing variant. Whole-page biolink layouts can be A/B
  tested too.
- **Smart links / routing rules.** Send different visitors to different
  destinations based on **country/geo**, **device** (mobile/tablet/desktop),
  **language**, **time-of-day window** (timezone-aware), or an **A/B split**.
- **Geo & device targeting.** Restrict or block specific countries, or target
  specific devices (plan-gated).
- **Scheduling & limits.** Set a scheduled start, an expiry date, a maximum-click
  cap, expire-on-first-click, and a **daily active window** (multi-slot,
  per-day, timezone-aware) with a computed "next opening". When a link isn't
  reachable, visitors see a clear reason (inactive, expired, limit reached,
  scheduled, or outside open hours).
- **Custom domains.** Add and verify your own domain (e.g.
  `links.yourbrand.com`) with a **step-by-step setup** showing the exact DNS
  records to copy; Sayzio **checks propagation automatically** and flips the
  domain to verified on its own — no manual re-verify needed. Then choose it
  when creating links. Some plans also offer shared **global domains**.
- **Splash pages ("Intros").** Optional interstitial pages shown briefly before a
  visitor reaches the final destination — great for announcements or branding.
- **Link insurance.** Monitors a link's destination and automatically fails over
  to a backup URL if the primary goes down, restoring it when the destination
  recovers, so your link never dead-ends.
- **AR business card.** Optional augmented-reality card experience; AR scans are
  tracked as a traffic source.
- **Auto-pixel.** Automatically fire your tracking pixels (e.g. Meta, TikTok,
  Google Ads) when a link is clicked.
- **Backlinks radar.** Tracks where your links are being shared across the web
  (works alongside the browser extension).

---

## 8. The biolink editor & blocks

**What it is.** The visual, no-code editor for building a "link in bio" page out
of **blocks**. It's split into two pages: **Blocks** and **Settings**.

**The Blocks page.**
1. Click **Add block** to open the block picker (organized by category).
2. **Drag and drop** blocks by their handle to reorder them.
3. Drop blocks **inside** Card or Grid containers to build complex layouts; inside
   a container you can set a block's **grid span** (e.g. full width, half width).
4. Use the **device preview** (mobile / tablet / desktop) to see your page update
   live as you edit.
5. Newly added blocks arrive pre-filled with friendly placeholder text/media and a
   starter style, and show a "we dropped in placeholder content" banner — just
   edit and save, and the banner clears automatically.
6. Link-style blocks let you **pick one of your existing links** as the
   destination, or paste any URL and hit **Fetch details** to auto-fill the
   title, description and image from the page, shown as a preview card you can
   **Apply** or **Dismiss** (web and mobile).

**Block catalog (highlights).**
- **Essentials** — Link Button, Featured Link, Heading / Logo Heading, Rich Text,
  Markdown, Bulleted / Numbered List, Pricing List, Alert Banner, Badge,
  Divider / Spacer, Link Group.
- **Layout & profile** — Card Container, Grid / Auto-Fit Grid, Card Carousel /
  Scrolling Cards, Profile Card (Classic / Cover / Stats / Badges identity
  layouts with avatar, name, bio, optional socials/stats/verified badges, plus
  newer looks: Paper Collage, Portrait Poster, Brand Rail, Split Pill, Badge
  Card — with optional decorative **avatar frames** like starburst, scalloped,
  zigzag, wavy, double ring, dotted ring, petal in any color, and **hero photo
  styles** like glow, wave, grid, spotlight, aurora).
- **Media** — Image / Image Grid / Image Slider (mask shapes, borders, shadows,
  and an optional trackable destination link; plus drag-to-place **photo
  stickers** from your vault and up to 10 short **text overlays** layered right
  on the photo), Video / Header Video, Audio
  Player / Playlist, File Download, plus embeds (YouTube, Vimeo, Spotify, Apple
  Music, SoundCloud, Instagram, TikTok, X/Twitter, Pinterest, and more).
- **Engagement** — FAQ (simple & accordion), Poll, Quiz (with live results),
  Testimonials, Reviews / Reviews Wall, Timeline, Chat Widget (embedded
  chatbot), and Buzz / Social Proof.
- **Commerce** — Product / Service, Catalog / Storefront, Coupon, Limited Offer
  (with countdown), Donation, Buy Me a Coffee, Ko-fi, Patreon.
- **Contact & lead capture** — Email Collector / Phone Collector, Contact Form,
  WhatsApp Chat / Button / Number, and Direct Message (to your Sayzio inbox).
- **Social profiles & feeds** — Social Icons / Hub, platform feeds (YouTube,
  Instagram, TikTok, X), and RSS Feed.

**Per-block styling & display rules.** Each block has its own style controls
(font, colors, corner radius, shadow, effects) plus ready-made templates; you can
set a global theme and let individual blocks override it. Every block also has a
unified **background picker** (solid color, gradient, or image for just that
block), headings can carry small **decorative accents**, and blocks support a
**torn-paper background** edge. A live **contrast (readability) check** warns
when your text and background colors would be hard to read. Link/button blocks
have a **Designs gallery** with shape filters (card, pill, square, outline,
plain text, full image) and looks like Taped Note, Text Divider, Overhanging
Image, Title + Description Row, and Square Image Cover. Card/grid containers
have an **item gap** control. **Display rules** let
you show or hide a block by schedule, location, device, OS, browser, or language,
so different visitors see different blocks.

**Page stickers.** Decorate the whole page with up to **10 stickers** (an emoji
or one of your images): drag each anywhere, then rotate, resize, and layer it.

---

## 9. Biolink settings

Open the **Settings** page of the biolink editor to control the whole page:

- **Appearance** — global background (color, gradient, image, or video), font
  family, and primary text color. A **Presets** gallery offers curated
  background looks with an opacity dial, and a **Fixed / Scroll** toggle
  controls whether the background stays put while the page scrolls. Image
  pickers include a **Stock** tab with a curated gallery of ready-to-use photos
  and textures.
- **Layout** — content max-width, page padding, and block spacing per device.
- **Block theme** — set a global block theme (colors, radius, shadows,
  glassmorphism) or pick a pre-designed template; save looks as **themes** and
  even **schedule** a theme to apply for a date range (e.g. a holiday look).
- **SEO** — page title, description, and keywords for search engines.
- **Open Graph** — control how the page looks when shared (preview image, social
  card).
- **PWA (install as an app)** — enable a manifest so visitors can add your biolink
  to their home screen like an app.
- **Branding** — custom favicon and toggling the "Powered by Sayzio" badge
  (plan-gated).
- **Custom CSS / JS** — inject your own code for advanced styling/behavior
  (plan-gated, Pro feature).

---

## 10. AI biolink builder & wizard

**AI biolink builder.** Describe the page you want in plain language and AI
assembles it for you. Open the AI builder, type a prompt (e.g. "A page for my
coffee shop with my menu, hours, and Instagram"), optionally attach images or
links, and AI generates a complete page using safe, ready-to-use block types,
appending any image/link you supplied that it didn't already place. Review and
edit the result like any biolink. (This uses coins; if the result can’t be
built, you're refunded.)

**Biolink wizard.** A step-by-step guided builder with no AI prompt required.
Answer a few questions — **Category** (what the page is for), **Page type**
(narrow it down), **Industry** (optional), and **Questions** (specifics like
"What's your menu URL?") — and the wizard turns your answers into relevant blocks.

---

## 11. QR Studio Pro

**What it is.** A dedicated QR-code builder with deep design control and live
preview, found under **QR Codes**.

**Why use it.** Branded, scannable QR codes for print, packaging, signage, and
table tents — all trackable.

**How to use it.**
1. Pick a **content type** — 16 are supported, including URL, WiFi, vCard,
   WhatsApp, email, phone, SMS, location, event, crypto, and more.
2. Enter the payload (e.g. a WiFi network name + password).
3. Design it: choose from 30+ **design templates**, customize **dot shapes**, set
   **eye styling** (each of the three corner "eyes" can have its own outer/inner
   shape and color), add a **logo**, and apply a **frame** with a call-to-action.
4. Watch the **scannability checker** — it grades contrast, logo-vs-error-
   correction coverage, quiet zone, and risky shape combinations, and warns you
   before you create something that won't scan.
5. **Export** as PNG or SVG, a print-ready **PDF** (configurable size/DPI/bleed),
   or generate many codes at once with **bulk CSV** export (downloads as a ZIP).
6. Attach a trackable link so scans flow into your analytics (geo, device,
   heatmap).

---

## 12. Analytics

**What it is.** Performance data for your links and pages, both workspace-wide
(**Stats**) and per link.

**What you get.**
- **Clicks/visits** — total and unique counts over time.
- **Live visitors** — a real-time "visitors right now" indicator.
- **Geographic heatmap** — an interactive map of where your visitors are;
  coordinates are saved for clicks and page visits.
- **Block-level analytics** — taps/clicks on individual biolink blocks.
- **Source attribution** — referrers, UTMs, devices, browsers, and OS; sources
  include web, QR, AR, and NFC.
- **Retention / returning visitors** — how many visitors come back, including
  follower/subscriber cohorts.
- **Exports** — RSVPs and poll/quiz results.
- **Reset** — clear a link's counters for a clean start.

**How to use it.** Open a link and choose **Analytics**, or open **Stats** for the
whole workspace.

**Pixel tracking.** Under **Pixel**, add third-party tracking pixels (Meta,
Google Analytics, GTM, LinkedIn, X, Pinterest, TikTok, Snapchat, Quora) so visits
and conversions also flow to your own marketing tools.

**Exporting your data.** Export analytics (link clicks, follower/subscriber lists,
slide stats) to CSV — the analytics CSV export is a paid plan feature; the simpler
**Export links** on the My Links page is free on every plan. Each plan retains
analytics history for a set window; older detail beyond it is pruned, but running
totals stay.

### Audience Insights (Visitor Type Estimation)

**What it is.** An AI feature that estimates the *type* of people visiting your
Link in Bio page, returning a percentage split across five personas: Student,
Professional / Employee, Business Owner, Creator / Artist, and Other.

**Why use it.** Knowing your audience mix helps you tailor your blocks, language,
and offers without running a survey — for example, a page skewing toward Business
Owners might benefit from a testimonials block and a pricing table.

**Privacy.** The AI only sees aggregate counts (referrer domain, geographic region,
device type, browser language, time-of-day distribution, block engagement) that
Sayzio already collects. No individual visitor is identified, and no third-party
data is used.

**How to use it.**
1. Open a Link in Bio → **Analytics** → **Audience Insights** panel.
   On mobile: tap the link → **Analytics** → **Audience Insights** tab.
2. Click **Estimate audience**. AI runs the analysis and shows the breakdown.
3. To refresh after making big changes, click **Re-estimate → Force refresh**.
   This bypasses the 10-minute result cache and runs a fresh analysis.

**What it costs.** A small number of coins per estimate (shown before you
confirm). If the analysis fails, the coins are automatically refunded. Re-running
within 10 minutes returns the cached result at no charge.

**Plan requirement.** Audience Insights is a paid feature; an upgrade prompt
appears if your plan doesn't include it.

---

## 13. Audience & engagement

**Follow & subscribe.** Other Sayzio users can **follow** you, so your updates
appear in their feed. Visitors can **subscribe** to your email or WhatsApp list
via subscribe blocks on your biolink — these become **Leads**.

**Leads / subscriber management.** Your built-in CRM for people who've subscribed,
found under **Leads**.
1. Add an **Email Subscribe**, **WhatsApp Channel**, or **WhatsApp Number** block
   to a biolink.
2. Visitors enter their details and appear instantly in **Leads**.
3. Filter by **type** (email/WhatsApp), **status** (active/unsubscribed), or
   **source** (which page they came from).
4. Use **Compose** to broadcast to a segment (configure your own SMTP and/or
   WhatsApp sending details in **Leads → Settings**, and optionally enable a
   **welcome email** with double opt-in).
5. **Export** your list as CSV (respects the current filter).

**Feed & discovery.**
- **My Feed** — updates from creators you follow (new and pinned posts, profile
  and link updates).
- **Discover creators** — a public directory to find and follow others (18+
  profiles are hidden unless a visitor opts to show adult content).

**My Posts (creator feed).** Publish posts/updates that show up in your followers'
feeds and on your paid/creator page. Open **My Posts**, write a post, and publish.
You can schedule posts and edit them; team workspaces can route posts through an
approval step.

**Engagement primitives.** RSVPs, poll/quiz votes, reactions and comments, and
block taps all feed into your analytics.

---

## 14. Reviews

**What it is.** A Google-style reviews system available two ways: a standalone
**Reviews Page** link type, or a **Reviews Wall** block inside any biolink.

**Why use it.** Collect and showcase social proof, and pull in your existing
ratings from Google and Trustpilot.

**How to use it.**
1. Add a **Reviews Page** or a **Reviews Wall** block.
2. Visitors leave a star rating (1–5) and text — **no login required**. They can
   attach photos/audio/video and answer your custom questions. A spam check and
   honeypot run automatically.
3. (Optional) Turn on **customer verification** so reviews from people you can
   match (by email link, subscriber, or contact) are trusted and unverified ones
   are held back.
4. Imported reviews from **Google** and **Trustpilot** are merged into the same
   feed (read-only).
5. **Moderate** from the **Reviews** area: **Approve**, **Hide**, **Pin** to the
   top, **Reply** publicly, or **Delete** native reviews — on the web or in the
   mobile app.

---

## 15. Referrals

**What it is.** Invite friends to Sayzio and both of you earn rewards. Found under
**Referrals**.

**How to use it.**
1. Copy your **referral code** or **referral link**.
2. Share it; when a friend signs up **and** activates a plan, you both earn
   rewards (e.g. free subscription days).
3. Track **clicks**, **signups**, and **conversions** on the Referrals page.

**Note.** Self-referrals (referring yourself with a second account) are not
rewarded.

---

## 16. Creator monetization & payouts

**What it is.** Tools to get paid by your audience — payouts, paid pages, product
sales, tips, and paid DMs. The **Monetization** / **Earnings & Payouts** area is
your hub, and it rolls up your earnings, subscribers, payments, and orders in one
place.

**Sayzio's fee is 0%.** Sayzio doesn't take a platform cut — you keep 100% of what
fans pay, minus only the payment processor's own fee.

**Payouts.**
1. Open **Earnings & Payouts**.
2. Pick a payout **processor**: **Stripe Connect**, **PayPal**, **Razorpay**,
   **CCBill**, or **Segpay**.
3. Complete the processor's **hosted onboarding** (ID/KYC happens on their secure
   site).
4. Set one connection as your **default**. Your dashboard shows whether payouts
   and charges are enabled.

**Ways to earn.**
- **Paid Page** — a monetized page showing your posts, subscription tiers, and a
  tip option.
- **Tiers & promos** — subscription tiers for exclusive content, plus discount
  codes.
- **Product storefront** — sell digital or physical products with native
  checkout; manage **Orders** and fulfillment from the dashboard. (Also available
  as a biolink **Product** block, which requires the e-commerce feature on your
  plan.)
- **Tips** — let fans send one-off tips.
- **Paid DMs** — charge for direct messages.
- **Client invoicing** — bill your own clients under a **billing company** brand:
  issue branded invoices and receipts (downloadable PDFs), and optionally send
  client emails from your own domain via per-company SMTP.

---

## 17. Adult content (18+)

**What it is.** An optional mode for creators who publish adult (18+) content,
with the legal and payment safeguards that requires. Found under
**Adult content**.

**How to use it.**
1. Toggle the **18+** switch and complete the three-part consent dialog: confirm
   you're of legal age, that no minors are involved, and that you understand
   payouts are **locked to adult-friendly processors** (CCBill or Segpay).
2. Connect an adult-friendly payout processor.
3. Publish your 18+ content.

**Visitor experience.** Visitors must pass a 30-day age-gate screen before viewing
an 18+ profile, and 18+ profiles are hidden from the public Creators directory
unless a visitor opts to show adult content.

---

## 18. AI tools

Sayzio includes several AI helpers, all metered with **coins**:

- **AI Coach** — an AI assistant that reviews your account (analytics, biolinks)
  and answers "how do I improve?" questions with actionable, plain-language growth
  advice. (Previously labelled *Account Assistant* and *AI Growth Coach*.)
- **Zio Bot** (Site Assistant) — a conversational helper available as a chat
  widget on the Sayzio website and inside the app. Click the chat icon to open it.
  Zio Bot can answer questions, guide you to features, or connect you with support.
  You can log in or create an account right inside the chat via a one-time code, and
  you can request to be contacted via WhatsApp, a callback, or email using **Quick
  Contact**. Zio Bot uses coins.
- **Persona Generator** — creates a brand persona that shapes the tone and
  personality your AI uses when it writes or replies on your behalf.
- **AI Agents** — configurable agents you can create and switch between, each with
  its own prompt, tone, and knowledge for different audiences.
- **Chat Widgets** — AI chatbots you can embed on a biolink (as a block) or run
  as a full-page **AI Chatbot** link, so visitors can ask questions about you and
  get dynamic answers. The **owner** pays for visitor chats, not the visitor.
- **AI Chat** — a chat assistant that helps you draft content and answer questions
  about your account.
- **AI Minds / AI Note Summarizer** (formerly **Knowledge Bases**) — build and manage private AI Minds:
  upload documents and links to "train" your AI so its answers reflect your real
  information, and summarize raw notes into clear next steps with **AI Note Summarizer**.
- **Voice assistant** — a hands-free assistant that listens (speech-to-text), takes
  an AI turn, and can speak its reply (text-to-speech); also offers dictation.
- **AI Marketing Strategist** — generates a full organic + paid marketing plan
  tailored to your account, with one-click actions you can apply and a chat to
  refine it.
- **AI Brand Kits & On-Brand AI** — generate a cohesive brand identity (palette,
  fonts, voice, taglines, bio) and apply it to your biolinks and QR codes. A
  **Brand Consistency Score** audits how on-brand your pages are, and On-Brand AI
  keeps AI-written content in your brand voice.
- **AI Brand Studio** — describe what you need in plain language and get a whole
  on-brand asset kit — bio page, short links, QR codes, a form and a digital
  card — planned by AI and reviewed by you before anything is created. Bulk mode
  can create many variations of one asset type at once (limit depends on your
  plan). You can save asset combinations as reusable combos (up to 20; rename or
  delete them anytime) to reuse with one tap. A failed run is automatically
  refunded, and if you discard a planned kit before confirming it, the coins
  spent on planning are returned to your wallet automatically.
- **AI QR Art** — turn a plain QR code into on-brand artwork that still scans (a
  built-in check verifies scannability before you use it).
- **Inbox Agent** — categorizes and prioritizes incoming messages, drafts replies,
  and can auto-send confident, safe replies on autopilot (see
  [Inbox & messages](#27-inbox--messages)).
- **Competitor Biolink Teardown** — paste a competitor's public page URL and get an
  AI-scored analysis: an overall score (0–100), strengths, weaknesses, missing
  elements, call-to-action quality, and concrete recommendations. One tap on
  **"Build a better version"** hands those findings to the AI biolink builder to
  assemble an improved page for you. Available on web and mobile.

**AI Mind sync sources.** Besides pasting text, uploading documents, and
adding FAQs or links, an AI Mind can stay in sync with outside systems through
two additional connection sources — both respect your plan limits, and any
credentials you enter are encrypted at rest.

- **Webhook (inbound)** — Sayzio generates a unique inbound URL and a secret token.
  Copy both into any external system. When that system POSTs content to the URL,
  Sayzio verifies the token, stores the payload, and re-trains the AI Mind
  automatically. The source shows its **Last received** timestamp so you can
  confirm the connection is live. For security, the token is shown only once (copy
  it immediately; use **Regenerate** to issue a new one if lost).
- **API connector (outbound)** — enter an endpoint URL, choose an authentication
  method (none, header API key, or bearer token), and set a refresh interval.
  Sayzio fetches the endpoint on that schedule, turns the response into text, and
  re-trains the AI Mind.

Other AI helpers appear inside specific tools — **resume tailoring** and
**cover-letter generation** in the Resume builder, the **AI biolink builder**, and
the **Scan a card or brochure** tool (see below).

**When AI is unavailable.** If you see "AI scanning/feature is currently disabled
by your administrator" and the button is disabled, AI isn't available on your
platform right now. This is controlled by an administrator and has two underlying
causes: the AI engine is turned off, or no AI provider key is configured. Once an
admin enables the engine and adds a key, it works with no change on your side.

---

## 19. Forms

**What it is.** A drag-and-drop form builder with **21 field types** (text, email,
phone, number, dropdown/select, radio, checkbox, rating, scale, signature, file
upload, date, plus structural sections/page breaks for multi-step forms). Found
under **Forms**.

**How to use it.**
1. Click **New Form** and give it a title.
2. In the **Form Builder**, drag fields from the palette and configure each (e.g.
   options for a dropdown, max file size for uploads).
3. Customize the design (Light/Dark/Glass themes plus custom CSS) and set up
   **notifications** — email, SMS, or webhook — for new submissions.
4. Save, then share the public URL, grab the **embed** code (JS/iframe), or add
   the form to a biolink. View entries under **Submissions**, where you can
   filter unread/starred, export to CSV, and erase an individual submitter's data.

**Paid forms.** Forms can charge for submissions — either a fixed price or a
per-field/per-option total that updates live as the visitor fills it in.

---

## 20. Digital contact cards (vCard)

**What it is.** A full digital business card (vCard 3.0) with multiple emails,
phones, URLs, addresses, and social profiles.

**How to use it.** Create a **Contact Card** link (or a vCard block), fill in your
details, add a profile photo, and share it. Visitors tap **Save Contact** to
download a standards-compliant `.vcf`. Smart-redirect rules, scheduling, and an
optional themed preview page before download are all supported.

---

## 21. Social proof (Buzz)

**What it is.** Embeddable notification popups that build trust by showing live
activity. Found under **Buzz**.

**Why use it.** Nudges like "Someone just subscribed" or "120 people viewed this
today" increase conversions.

**How to use it.** Create a Buzz campaign, pick a notification **type** (there are
7, e.g. recent activity, live visitor counter, informational, coupon, email/phone
collector, custom HTML, review popups), customize the design (animation, colors,
shadow, position) and targeting (delay/interval, per-device, per-page), and attach
it to a specific biolink or pin it globally via one embed script.

---

## 22. Contacts & dialer

**What it is.** A personal address book plus an in-app dialer with identity
resolution. Found under **Contacts** and **Dialer**.

**How to use it.**
1. In **Contacts**, optionally connect **Google Contacts** for two-way sync that
   keeps both sides in step and runs automatically in the background. You can also
   bulk-import contacts from CSV or VCF.
2. Open the **Dialer** for a number pad with **T9 search** (type digits to find
   names), **speed dial** favorites, and **recent/frequent** contacts, plus call
   logging with outcomes and notes.
3. **Caller-ID lookup** can resolve a phone number to a Sayzio profile, and
   contacts whose verified phone matches a Sayzio user get that user's biolink
   attached automatically.
4. Calls and emails open your device's native dialer/mail (`tel:` / `mailto:`) —
   there's no in-app calling.
5. **On the mobile app** the dialer can place real device calls with
   **dual-SIM support** (per-call SIM picker or a default SIM) and an optional
   **direct call** setting; incoming calls show a **caller-ID alert** with a
   spam warning for numbers you've flagged. Calls are logged into the contact's
   history/timeline, and you can attach **notes and tasks with reminder alarms**
   and review them in **agenda views**.
6. **Notes are unified** — your notes and checklists are one account-level store
   shared across web, the mobile app, and **Zio Browser** (which shows a
   per-site note-count badge and keeps notes available offline). Notes with a
   reminder time can also appear on **My Calendar** automatically.

---

## 23. Scan a card or brochure

**What it is.** An AI tool that reads a photo of a **business card** or a small
**brochure** (image or PDF) and pulls out the name, contact details, social
handles, tagline, and brand logo — so you can save a new **contact** and/or
**seed a biolink page** without typing it all by hand. Found under
**Contacts → Scan a card or brochure**, and also offered as a shortcut from the
biolink **wizard**.

**Why use it.** Skip manual data entry — snap a card and get a ready-to-save
contact in seconds, or turn a company brochure into a head start on a biolink
page.

**How to use it.**
1. **Upload** one or more images or PDFs (e.g. the front and back of a card, or a
   few brochure pages at once).
2. **Scan with AI** — the AI reads every visible field and shows a progress step.
3. **Review & edit** everything on the review screen. Nothing is saved until you
   confirm; each field is fully editable, with **confidence indicators** and a
   soft **duplicate warning** if an email or phone already matches an existing
   contact.
4. **Save** — choose **Save as a contact**, **Seed a biolink page draft**, or
   both. A biolink draft drops you into the wizard with the details (and the
   detected logo as the avatar) pre-filled.

**Supported inputs.** JPG, PNG, WebP, or PDF; up to **6 files** per scan, **10 MB**
each, and PDFs are processed up to **4 pages** (longer PDFs are rejected so you
can split them).

**What it costs.** Scanning is an AI feature, so it uses **coins** from your
coin wallet. Sayzio checks you can afford it before running, so you're never
charged for a scan that can't finish; if the extraction fails after charging, the
coins are refunded automatically.

**Plan limits.** Saving an extracted **contact** counts against your plan's
contact limit — if you're at the cap, you'll be asked to upgrade, but you can
still **seed just the biolink draft** without using a contact slot.

---

## 24. Restaurant menu, Store & Service Booking

### 24a. Restaurant menu

**What it is.** A dedicated digital-menu page type for restaurants and cafes, with
optional table-side ordering. It has its own builder (it doesn't use the block
editor).

**How to use it (owner).**
1. Create a **Restaurant Menu** link.
2. Build **Categories**, then add **Items** (name, description, price, photo).
3. Set display options, order mode, currency, accent color, and optionally a
   **GST/tax rate** (added on top or marked as already included) and **coupon
   codes** (percentage off or a fixed amount; one coupon applies per order).
4. For table ordering, define **Tables** — each gets its own unique QR/URL so a
   diner's order is tied to their table.
5. When orders come in, manage them in the near-real-time **Orders Dashboard**,
   moving each from **Pending → Preparing → Served → Paid/Cancelled**.

**Visitor experience.** Diners scan the table QR (or open the menu link), browse,
optionally enter a coupon code, and see a live **estimated bill** (subtotal, any
discount, the GST line, and an estimated total) before tapping **Place Order**.

> **Important.** The bill shown is an **estimate, not the actual bill**. Sayzio
> does not collect payment — diners settle directly with the restaurant.

**Mobile.** The restaurant menu has a full native builder in the Sayzio mobile app
too — including coupon entry and the live estimated bill — no need to switch to
the web.

---

### 24b. Store (order-request storefront)

**What it is.** A product catalog page where visitors browse, place orders, and
leave their contact details — the owner fulfills orders offline. There is **no
online payment**, **no tax/GST**, and **no coupon codes** (unlike the Restaurant
Menu). It has its own dedicated builder.

**Why use it.** Great for small shops, home businesses, local sellers, and anyone
who wants a clean "catalog + order request" page without a full ecommerce setup.

**How to use it (owner).**
1. Create a **Store** link.
2. Build **Categories**, then add **Products** (name, description, price, photo).
3. Set your currency, accent color, and optionally a WhatsApp number (Sayzio can
   build a `wa.me` link so you're notified when an order arrives).
4. Toggle **Accepting orders** on/off to pause when needed.
5. When orders arrive, manage them in the **Order Requests Dashboard**, moving each
   from **New → Accepted → Packing → Ready → Completed / Cancelled**. The order
   total is the simple sum of line items — no tax, no coupon.

**Visitor experience.** Visitors browse categories and products, enter their name,
contact details, and an optional note, and submit an order. No account or payment
required. You receive an in-app notification and email.

**Mobile.** The store builder and order requests dashboard have full native parity
in the Sayzio mobile app.

---

### 24c. Service Booking (appointment requests)

**What it is.** An appointment-request page where visitors browse your services
and request a time slot. You confirm or decline bookings from a dashboard. It has
its own dedicated builder.

**Why use it.** Ideal for freelancers, coaches, therapists, personal trainers, and
any service provider who wants a simple "here's what I offer, book a slot" page.

**How to use it (owner).**
1. Create a **Service Booking** link.
2. Add **Services** (name, description, duration, price/rate).
3. Set your **weekly availability** (which days and hours) and any blocked dates.
4. When booking requests come in, manage them in the **Bookings Dashboard**:
   confirm or decline each request and add notes.

**Paid bookings.** You can optionally require payment at booking time — choose
**Full payment** (the full service price is collected upfront) or a **Deposit**
(a fixed amount or percentage of the price). If payment mode is set to **None**,
no payment is collected and any total shown is an estimate only. Payment is
processed through your connected payout provider.

**Appointment reminders.** Configure automatic reminders sent to visitors before
their appointment — for example 24 hours ahead and again 1 hour before.

**Staff notifications.** Staff members can each have an optional email address;
when set, they're emailed automatically when a booking assigned to them is
placed, rescheduled, or cancelled (for paid bookings, the "new booking" email
waits for payment confirmation).

**Visitor experience.** Visitors pick a service, choose an available slot on a
calendar, enter their name and contact details, and submit the request. They see
"request sent — awaiting confirmation" after submitting.

**Mobile.** The service booking builder and bookings dashboard have full native
parity in the Sayzio mobile app.

---

## 25. Resume / portfolio

**What it is.** A standalone resume builder that doubles as a shareable resume
link with PDF download. Found under **Resume / Portfolio**.

**How to use it.**
1. Build your resume section by section (experience, education, skills, projects,
   certifications, awards, languages); keep multiple named **versions** and set a
   default.
2. Use the AI tools: **Tailor to a job** (paste a job description and get
   suggested edits/keywords), **Generate cover letter**, and **import** an
   existing resume.
3. Check **ATS readiness** for formatting issues that hurt applicant-tracking
   systems.
4. Create a **Resume** link to share it publicly (you can point it at a specific
   version, and add a password or expiration), or download the PDF.

---

## 26. Files / Vault

**What it is.** Your personal file storage for images, video, audio, and documents
used across links and posts. Found under **Files** / **Vault**.

**How to use it.** Drag and drop to upload (or import from a URL); a progress bar
shows your **used vs. limit** storage. Files you upload here can be reused anywhere
in Sayzio. Storage is quota-aware (with auto-optimization to reclaim space), files
are served securely, and uploads are scanned so anything flagged is held until you
confirm. Upgrade your plan for more quota.

---

## 27. Inbox & messages

**What it is.** A unified **Inbox** that gathers messages sent to you — biolink
direct messages, form submissions, and similar inbound communication (and paid
DMs, if enabled).

**How to use it.** Open **Inbox** to read and triage incoming messages in one
place.

**AI assistance (Inbox Agent).** On supported plans, Sayzio categorizes and
prioritizes incoming messages, drafts suggested replies you can edit and send,
and — if you switch on **autopilot** — sends confident, safe replies for you
(clearly marked "Sent by AI"). Autopilot never auto-sends on spam, sensitive
topics, or low-confidence messages; those always wait for you in a review queue.
All inbox AI is billed to the workspace owner.

---

## 28. Notifications & digests

**What it is.** In-app and email notifications keep you posted on activity (new
subscribers, reviews, comments, security alerts, and more).

**How to use it.**
- Open **Notifications** to see your activity feed; mark items read, dismiss them
  (dismissed items are restorable for 30 days), or mark all read.
- Manage **notification preferences** per channel so you only get what you want.
- **Digests** are periodic email summaries of your activity; Sayzio won't send an
  empty digest, and you can send yourself a sample to preview the format.

---

## 29. Organizing your work

For teams and agencies, Sayzio scales beyond a single user:

- **Workspaces** — separate environments for different brands/projects, each with
  its own branding and settings; you can belong to multiple workspaces.
- **Folders** — group related links into colored, Finder-style folders
  (formerly called "Projects"). Each folder has a name, a color from a preset
  palette, and an optional description; in the My Links grid view the folder
  color tints its link cards. New accounts start with demo folders (Marketing,
  Social, Docs, Partners).
- **List or grid view** — switch My Links between a detailed list and a card
  grid; the choice is remembered per device on web and mobile.
- **Team & roles** — invite members and assign roles/permissions (e.g. Owner,
  Admin, Editor, Viewer). Owners can enforce 2FA for everyone and review a
  sensitive-action **audit log**.
- **Client portals** — give external clients a limited area to view shared
  boards, files, and deliverables via magic link or password, without full
  account access.
- **Workspace Vault** — a shared secure store for the workspace.
- **Task boards** — lightweight task tracking inside the workspace.

---

## 30. Security & sessions

**What it is.** Controls to keep your account safe, found under **Security** (with
**Linked identifiers** / **Verification**).

**How to use it.**
- **Active sessions/devices** — see every device signed in to your account and
  **revoke** any one (or all others) with a click.
- **Recent logins** — review recent sign-ins with time, device, location, and IP.
  If something looks wrong, tap **"This wasn't me"** to revoke it. Sayzio emails
  you about logins from a new device, browser, or country.
- **Two-factor authentication (2FA)** — turn on an extra challenge code at
  sign-in (owners can enforce it for a whole team).
- **Verification** — verify your identity/badges where applicable; a verified
  phone/email also powers the dialer's identity resolution.

---

## 31. Settings & integrations

- **Integrations** — connect third-party services (e.g. Google Contacts, social
  accounts, and the various embeds and pixels). Which integrations are available
  depends on your platform's configuration.
- **Connected Accounts** — link your social and OAuth accounts.
- **API keys** — generate developer API keys to use the Sayzio REST API (usage is
  metered against your plan's monthly allowance; overage can be paid from coins).
- **Pixel** — manage marketing tracking pixels (see [Analytics](#12-analytics)).
- **Calendar sync** — connect Google/Microsoft calendars to manage availability
  alongside Event links.

---

## 32. Mobile app & browser extension

- **Sayzio mobile app** — most creator features have native parity in the mobile
  app, including links, biolink editing, QR Studio, restaurant menus, reviews
  moderation, payouts, the 18+ toggle, AI Coach, AI Agent chat, and a
  floating-mic voice assistant. Sign in with email/OTP or social. On Android
  you can **download the APK directly** from the platform's own domain at
  **/android** — no app store needed.
- **Zio Extension (browser extension)** — helps with things like saving and shortening links
  (including "Shorten as A/B test") and powering the Backlinks radar from your
  browser.

---

# Part 2 — Q&A / FAQ

## Account & sign-in

**How do I sign in without a password?**
Choose the "sign in with a code" option. Sayzio sends a 6-digit one-time code to
your email (or phone, if enabled). Enter it to sign in — no password needed.

**I forgot my password. What now?**
Use the one-time-code (OTP) sign-in to get back in, then set a new password from
your account settings.

**Can I use Google or Apple to sign in?**
Yes, if your platform has social sign-in enabled — look for the "Continue with…"
buttons on the sign-in page.

**How do I turn on two-factor authentication?**
Go to **Security** and enable 2FA. After that, you'll enter an extra challenge
code each time you sign in.

**Why don't I see WhatsApp or phone login?**
Phone/WhatsApp login is an optional method an administrator must enable; email
sign-in is always available.

**How do I change my handle?**
Open **Profile**, edit the handle field, and save. Your handle is your public
address (`@yourname`) and is used in the Creators directory.

**How do I become discoverable in the Creators directory?**
In **Profile**, toggle "Show me in the public Creators directory" (this may
require a specific plan).

## Plans & coins

**What do coins pay for?**
**Coins** are a general prepaid balance (top up by buying coin packages) used for
add-ons, developer-API overage and every AI feature — the AI builder, AI Coach,
AI Agents/AI Minds, the card scanner, and resume AI tools all spend coins.

**What are add-ons?**
Optional extras you attach to your plan to expand specific limits or capabilities
beyond your base tier; they're billed alongside your subscription.

**What happens if I run out of coins?**
Before an AI action runs, Sayzio checks you can afford it; if not, you're prompted
to top up rather than charged for something that can't finish. Failed AI runs are
typically refunded automatically.

**Which plan should I choose?**
Open **Plans** — Sayzio measures how close you are to your limits and recommends a
plan, with a comparison matrix so you can decide. Upgrade when you're near a cap
or need a pro feature (custom domain, custom CSS/JS, custom branding, etc.).

**Do my new plan features apply right away?**
Yes — once payment succeeds, higher limits and unlocked features apply
immediately.

## Links

**What's the difference between a short link and a biolink?**
A **short link** redirects to one destination URL. A **biolink** is a whole
mini-site (a page of blocks) at your own address — your "link in bio".

**Can I use my own domain?**
Yes. Add and verify it under **Custom Domains** by setting the DNS records shown,
then pick it when creating links. Some plans also offer shared global domains.

**Can two slugs point to the same page?**
Yes — add **additional aliases** to a link; they serve the same page with no
redirect.

**How do I send mobile and desktop visitors to different places?**
Use **smart rules** on the link to route by device (you can also route by country,
language, or time of day).

**How do I A/B test a link?**
Create an A/B test with weighted variants (e.g. 50/50), watch the stats, then
**declare a winner** to promote the best one.

**Can I schedule a link or make it expire?**
Yes — set a scheduled start, an expiry date, a maximum-click cap,
expire-on-first-click, or a daily active window.

**What if my destination URL goes down?**
Turn on **link insurance**, which monitors the destination and can fail over to a
backup automatically, then restores it when the destination recovers.

**Can I see where my link has been shared?**
Yes — the **Backlinks** radar tracks where your links appear across the web.

**Who can see my biolink page?**
You control that with **visibility tiers**: Public, Registered users only,
Followers only, or Subscribers only — and you can password-protect some pages.

## Biolink editor

**How do I add and reorder blocks?**
On the **Blocks** page, click **Add block** to pick from the catalog, then drag
blocks by their handle to reorder. Drop blocks into Card/Grid containers for
layouts.

**My new block has placeholder text — is that a problem?**
No. New blocks come pre-filled with placeholder content and a starter style so the
page never looks empty. Edit the block and save; the "placeholder" banner clears
automatically.

**Can I make a block show only to some visitors?**
Yes — each block has **display settings** to show/hide it by schedule, location,
device, OS, browser, or language.

**How do I change my page's background and fonts?**
On the **Settings** page under **Appearance**, set the background (color,
gradient, image, or video), font, and text color.

**Can I add custom CSS or JavaScript?**
Yes, on a Pro plan — open **Settings → Custom CSS/JS**.

**How do I make my biolink installable like an app?**
Enable the **PWA / manifest** option in **Settings**; visitors can then add it to
their home screen.

**Can AI build my page for me?**
Yes. Use the **AI biolink builder** — describe what you want (and attach
images/links) and it assembles the page. Prefer no prompt? Use the **wizard** and
answer a few questions.

**Can I save and schedule different looks?**
Yes — save the current look as a **theme**, then optionally **schedule** a theme
to apply for a date range.

## QR codes

**Will my fancy QR code still scan?**
QR Studio Pro has a built-in **scannability checker** that grades contrast, logo
coverage, quiet zone, and risky shape choices, and warns you before you create one
that won't scan.

**Can I generate many QR codes at once?**
Yes — use **bulk CSV** export; Sayzio builds all the codes and downloads them as a
ZIP.

**Can I track QR scans?**
Yes — attach a trackable link so scans flow into your analytics (geo, device,
heatmap).

**What can a QR code contain?**
16 content types, including URL, WiFi, vCard, WhatsApp, email, phone, SMS, and
crypto.

## Analytics

**Where do I see my stats?**
Open a specific link and choose **Analytics**, or open **Stats** for everything in
the workspace.

**Can I see where my visitors are?**
Yes — the **geographic heatmap** maps visitor locations, and **live visitors**
shows who's on your page right now.

**Can I track conversions in my own marketing tools?**
Yes — add tracking **pixels** (Meta, Google, TikTok, and more) under **Pixel**.

**Can I reset a link's numbers?**
Yes — use **reset analytics** on the link.

**Can I tell new visitors from returning ones?**
Yes — analytics include returning-visitor/retention data, including
follower/subscriber cohorts.

## Audience, reviews & referrals

**How do people subscribe to me?**
Add an **Email Subscribe** or **WhatsApp** block to your biolink; subscribers show
up instantly under **Leads**, where you can segment, message, and export them.

**Can I email my subscribers from Sayzio?**
Yes — use **Compose** in **Leads**. Configure your own SMTP/WhatsApp sending
details in **Leads → Settings**, and optionally enable a welcome email.

**How do reviews work?**
Add a **Reviews Page** or **Reviews Wall** block. Visitors leave star ratings (no
login), and you **approve/hide/pin/reply** from the **Reviews** area. You can also
import Google and Trustpilot reviews into the same feed.

**Do reviewers need an account?**
No — native reviews require no login. They can add photos/audio/video and answer
your custom questions; spam protection runs automatically.

**How do referrals reward me?**
Share your **referral code/link**; when a friend signs up and activates a plan,
you both earn rewards (e.g. free subscription days). Referring yourself with a
second account doesn't count.

## Monetization

**How much does Sayzio take from my earnings?**
0%. You keep everything except the payment processor's own fee.

**How do I get paid?**
Open **Earnings & Payouts**, connect a processor (Stripe Connect, PayPal,
Razorpay, CCBill, or Segpay) via its hosted onboarding, and set a default.

**How do I sell products?**
Add a **Product/Storefront** block or create a storefront link, then manage
incoming **Orders** and fulfillment from the dashboard.

**What's a Paid Page?**
A monetized page that automatically shows your posts, tiers, and a tip option,
gated by visibility/payment.

**Can I charge for direct messages?**
Yes — **paid DMs** let you charge for a direct message; they flow through your
messaging/inbox.

**I publish adult content — what do I need to do?**
Enable the **18+** toggle and complete the consent dialog. Payouts for 18+ content
are locked to adult-friendly processors (CCBill or Segpay), and visitors must pass
an age gate.

## Tools

**How many field types do forms support?**
21 — including text, email, phone, dropdown, rating, scale, signature, file
upload, and section/group fields. You can be notified by email, SMS, or webhook.

**Can a form charge money?**
Yes — forms can charge a fixed price or a per-field/per-option total that updates
live as the visitor fills it in.

**Can I sync my phone contacts?**
Yes — connect **Google Contacts** for two-way sync, then use the **Dialer** (T9
search, speed dial, recents). Calls/emails use your device's native apps.

**Can Sayzio build my resume?**
Yes — the **Resume / Portfolio** builder supports multiple versions plus AI
**tailoring to a job**, **cover-letter** generation, import, and an **ATS
readiness** check, and publishes as a shareable link with PDF download.

**How does the restaurant menu's table ordering work?**
Define **Tables**, each with its own QR/URL. Diners scan, browse, optionally enter a
coupon code, see a live estimated bill (subtotal, discount, GST, total), and **Place
Order**. You manage orders in the **Orders Dashboard** through Pending →
Preparing → Served → Paid/Cancelled. The bill is an estimate only — Sayzio does
not collect payment.

**What's the difference between Restaurant Menu and Store?**
The **Restaurant Menu** supports table-side ordering (per-table QR), coupon codes,
and a GST/tax rate, and shows a live estimated bill. The **Store** is a product
catalog for offline-fulfilled orders with no tax, no coupons, and no physical
tables — just a clean catalog → order-request flow. Use Restaurant Menu for food
service; use Store for product-based businesses.

**How does the Store work?**
Create a **Store** link, build Categories and Products, then manage incoming order
requests in your **Order Requests Dashboard**. Visitors browse, add products to a
cart, enter their name and contact, and submit the request — no account, no payment.
You can toggle **Accepting orders** off to pause the store without taking it down.
You get an in-app notification and email for each new request. Statuses: New →
Accepted → Packing → Ready → Completed / Cancelled.

**Does the Store collect payment?**
No. The Store is an order-request tool only — no payment is collected. Customers
settle directly with you through whatever method you agree (bank transfer, cash on
delivery, etc.).

**What is Service Booking?**
A **Service Booking** page lets visitors browse your services (e.g. coaching,
haircuts, personal training) and request an available time slot. You confirm or
decline each request from a Bookings Dashboard. No payment is collected — any
pricing shown is for reference only.

**How do I set my availability in Service Booking?**
In the Service Booking builder, set your **weekly availability** (which days and
hours you're open for each service) and block off specific dates (holidays, time
off). Visitors only see slots that fall within your available hours.

**Can I use Service Booking to take payment?**
Yes — you can optionally require a **deposit** or **full payment** at booking time.
Go to your Service Booking settings and set the **Payment mode**. For a deposit,
choose whether it's a fixed amount or a percentage of the service price. Payment is
processed via your connected payout provider. If payment mode is set to **None**, no
payment is collected and any total shown is an estimate only.

**What's the Competitor Biolink Teardown?**
It's an AI tool that analyzes a competitor's public page. Paste their URL, and you
get an overall 0–100 score, strengths, weaknesses, missing elements, a CTA quality
assessment, and concrete recommendations to beat it. Tap **"Build a better version"**
and the AI biolink builder turns those findings into a draft page for you.

**Does the Competitor Teardown cost anything?**
Yes — it uses coins (drawn from your wallet), and your plan must include
it. If the analysis fails, coins are automatically refunded.

**What are Audience Insights?**
Audience Insights estimates the type of people visiting your Link in Bio page —
Student, Professional / Employee, Business Owner, Creator / Artist, or Other —
shown as a percentage split. It helps you tailor your page without running a
survey. The AI only uses aggregate analytics data Sayzio already collects; no
individual visitor is identified.

**How often can I run Audience Insights?**
Re-running within 10 minutes returns the cached result at no charge. To force a
fresh analysis sooner, click **Re-estimate → Force refresh**. Each new estimate
uses a small number of coins.

**What's the Files/Vault for?**
It's your personal storage for images, video, audio, and documents you reuse
across links and posts; a progress bar shows used vs. limit storage.

**Where do messages people send me go?**
To your **Inbox** — it gathers biolink DMs and form submissions in one place.

## AI features

**What's a Chat Widget vs. an AI Mind?**
An **AI Mind** is a private store you fill with your documents/links. A
**Chat Widget** is the chatbot that answers visitors using that knowledge — you can
embed it as a block or run it as a full-page **AI Chatbot** link.

**Who pays when a visitor chats with my Chat Widget?**
You do (the owner), from your coins — visitors don’t pay.

**What is the AI Coach?**
An AI assistant that reviews your account (analytics, biolinks) and gives
plain-language advice on how to grow and improve your links and pages.
(Previously labelled *Account Assistant* and *AI Growth Coach*.)

**What is Zio Bot?**
Zio Bot is Sayzio's built-in site assistant — the chat icon you see on the website
and inside the app. Open it to get help navigating features, ask questions, or
request to be contacted by support. If you're not signed in you can log in or sign
up right inside the chat using a one-time code. Zio Bot uses your coins.

**What does the voice assistant do?**
It listens to you (speech-to-text), takes an AI turn, and can speak its reply
(text-to-speech); it also offers dictation. Like other AI features, it uses
coins.

**Why is "Scan a card or brochure" disabled?**
You'll see "AI scanning is currently disabled by your administrator" when AI
scanning isn't available on your platform. It's controlled by an admin and has two
causes: the AI engine is turned off, or no AI provider key is configured. Once an
admin fixes either, scanning works automatically — nothing to change on your side.

**How much does a card scan cost?**
A scan uses **coins** from your wallet. Sayzio checks you can afford it
before running, so you're never charged for a scan that can't finish — and if the
extraction fails after charging, the coins are refunded automatically.

**What file types can I scan?**
JPG, PNG, WebP, and PDF. You can send up to 6 files per scan (e.g. both sides of a
card), 10 MB each, and PDFs are read up to 4 pages.

**What happens if the scan gets a detail wrong?**
Nothing is saved until you confirm. Every field on the review screen is editable,
shows confidence indicators, and warns you about possible duplicate contacts — so
you can fix anything before saving.

**Does scanning save a contact automatically?**
No. After scanning you choose what to do: **save as a contact**, **seed a biolink
page draft**, or both. Saving a contact counts against your plan's contact limit;
if you're at the cap, you can still seed just the biolink draft.

## Account safety

**How do I see what devices are signed in?**
Open **Security** to view active sessions and recent logins; revoke any device, or
tap "This wasn't me" on a suspicious login.

**Will I be warned about suspicious sign-ins?**
Yes — Sayzio emails you about logins from a new device, browser, or country.

## Teams & organization

**Can I add team members?**
Yes — invite them under **Team** and assign roles (Owner/Admin/Editor/Viewer).
Owners can enforce 2FA and review an audit log.

**Can I share work with a client without giving full access?**
Yes — use **Client portals** to share boards/files/deliverables in a limited area.

**How do I keep different brands separate?**
Use **Workspaces** — each is its own environment with its own branding and
settings.

## Notifications

**Why didn't I get a digest email?**
Sayzio won't send an empty digest. You can send yourself a sample to preview the
format, and manage which notifications you receive per channel under
**Notifications**.

## Mobile & developer

**Is there a desktop app?**
Yes — the **Zio Browser** is a desktop app (Windows / macOS / Linux) you can
download from the **/download** page. It supports workspace profiles (isolated
sessions per workspace), a Device Lab for side-by-side device previews, offline
access to your links and dashboard, a built-in **ad blocker** (with strength
setting and per-site allow/block lists), a **My Files** pane for your Sayzio
storage, your unified **notes** (with a per-site badge and offline access), a
T9 **dialpad**, built-in viewers for `.txt`/Markdown/JSON/CSV files, and
quick-create tiles for the newest page types. On Linux it ships as an AppImage
and a `.deb` package (x64).

**Is there a mobile app?**
Yes — most features have native parity, including links, the biolink editor, QR
Studio, restaurant menus, reviews moderation, and payouts. Sign in with email/OTP
or social.

**Does Sayzio have an API?**
Yes — generate **API keys** in settings. API usage is metered against your plan;
overage can be covered by coins.
