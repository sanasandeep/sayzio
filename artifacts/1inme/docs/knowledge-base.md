# 1INME Knowledge Base & FAQ

This is the complete end-user guide to **1INME**, written in plain language for
creators, businesses, and everyday users. It is the single source of truth for
training a 1INME support chatbot: every user-facing feature is explained in terms
of *what it is*, *why you'd use it*, and *how to use it* step by step. A large
[FAQ](#faq) follows the feature guide.

> This document is intentionally non-technical. For the developer REST API, see
> [`api.md`](./api.md). For internal architecture, see the `docs/` folder at the
> repo root.

---

## Table of contents

1. [What is 1INME?](#1-what-is-1inme)
2. [Getting started: account & sign-in](#2-getting-started-account--sign-in)
3. [Your profile](#3-your-profile)
4. [Plans, upgrades & billing](#4-plans-upgrades--billing)
5. [Coin wallet & AI credits](#5-coin-wallet--ai-credits)
6. [Links: the basics](#6-links-the-basics)
7. [Every link type explained](#7-every-link-type-explained)
8. [Link management: aliases, A/B tests, smart links, domains](#8-link-management-aliases-ab-tests-smart-links-domains)
9. [The biolink editor](#9-the-biolink-editor)
10. [Biolink blocks catalog](#10-biolink-blocks-catalog)
11. [Biolink settings (appearance, SEO, branding, PWA)](#11-biolink-settings)
12. [AI biolink builder & the wizard](#12-ai-biolink-builder--the-wizard)
13. [QR Studio Pro](#13-qr-studio-pro)
14. [Analytics](#14-analytics)
15. [Audience & engagement](#15-audience--engagement)
16. [Reviews](#16-reviews)
17. [Referrals](#17-referrals)
18. [Creator monetization](#18-creator-monetization)
19. [18+ adult content](#19-adult-content)
20. [AI tools: Mind, Personas, Companions, Coach](#20-ai-tools)
21. [Tools: Forms, Contact cards, Contacts & Dialer, Files, Resume, Calendar](#21-tools)
22. [Restaurant menu & orders](#22-restaurant-menu--orders)
23. [Inbox & messages](#23-inbox--messages)
24. [Notifications & digests](#24-notifications--digests)
25. [Organizing your work: Projects, Workspaces, Team, Client portals](#25-organizing-your-work)
26. [Security & sessions](#26-security--sessions)
27. [Settings & integrations](#27-settings--integrations)
28. [The mobile app & browser extension](#28-mobile-app--browser-extension)
29. [FAQ](#faq)

---

## 1. What is 1INME?

**What it is.** 1INME is an all-in-one link-management platform. From one
account you can create short links, a "link in bio" mini-site, QR codes, digital
contact cards, file-share links, event pages, resumes, restaurant menus, review
pages, and more — then track how they perform and even get paid through them.

**Why use it.** Instead of juggling a link shortener, a bio-link tool, a QR
generator, a form builder, an email-collector, and a payment processor, 1INME
puts them all in one place with shared analytics and a single public profile
(your handle, e.g. `1in.me/@yourname`).

**Who it's for.** Creators, small businesses, freelancers, restaurants, and
anyone who wants a polished, trackable online presence.

---

## 2. Getting started: account & sign-in

### Registering

**What it is.** Creating your 1INME account.

**How to use it.**
1. Go to the sign-up page and enter your name and email (a phone number may be
   allowed depending on platform settings).
2. Set a password, or use the passwordless option (see OTP below).
3. You'll land in a short **onboarding** flow that asks what kind of page you
   want (e.g. Creator, Business) and offers a starter template so you can launch
   your first page in minutes. You can skip it and explore on your own.

### Signing in

1INME supports several sign-in methods:

- **Password login** — email + password.
- **One-time code (OTP / passwordless)** — choose "sign in with a code", and
  1INME emails (or texts) you a **6-digit code**. Enter it to sign in without a
  password. This is handy on a new device or if you've forgotten your password.
- **Social sign-in** — "Continue with" buttons for supported providers (e.g.
  Google/Apple), if your platform has them enabled.
- **Two-factor authentication (2FA)** — if you've turned it on, you'll be asked
  for an extra challenge code after your password.

**Note.** Which methods appear depends on your platform's configuration; email is
always available, while phone/WhatsApp login is an optional toggle set by the
administrator.

### Onboarding

**What it is.** A guided first-run experience.

**Why use it.** It gets you from empty account to a real, shareable page fast.

**How to use it.** Pick a persona/goal, choose a template, and 1INME drops in a
starter layout you can immediately edit. You can re-run or skip it anytime.

---

## 3. Your profile

**What it is.** Your public creator identity — handle, display name, bio,
avatar, and discoverability settings. Find it under **Profile** / **Creator
Profile** in the sidebar.

**Why use it.** Your handle (`@yourname`) is the address people use to find you,
and it powers the public **Creators** directory.

**How to use it.**
1. Open **Profile** from the sidebar.
2. Set your **display name**, **handle**, **bio** (up to ~500 characters), and
   upload an **avatar**.
3. Set your **timezone** and **language** so scheduling and daily digests arrive
   at the right time.
4. Toggle **"Show me in the public Creators directory"** if you want to be
   discoverable (this may require a specific plan).

---

## 4. Plans, upgrades & billing

**What it is.** 1INME offers several subscription tiers that unlock higher limits
(more links, biolinks, storage, contacts) and premium features (custom branding,
custom CSS/JS, custom domains, etc.).

**Why use it.** Free accounts are capped; upgrading raises those caps and unlocks
pro features.

**How to use it.**
1. Open **Plans** (in-app) or visit the public **Pricing** page.
2. 1INME shows a **personalised recommendation**: it measures how close you are to
   your current limits (links, biolinks, projects, storage, contacts, files) and
   highlights a recommended plan, with a "Recommended" ribbon and a smart-upgrade
   banner when you're near a cap.
3. Compare plans in the **feature comparison matrix**, switch currency (e.g.
   USD/INR) if offered, then choose a plan and check out.
4. Payment is handled by the configured payment gateway; once paid, your new
   limits and features apply immediately.

**Good to know.** The pricing page also shows **coin packages** and a
side-by-side competitor comparison so you can see where 1INME stands.

---

## 5. Coin wallet & AI credits

### Coins

**What it is.** A prepaid in-app balance you can top up by buying **coin
packages**.

**Why use it.** Coins pay for optional extras — for example unlocking certain
add-ons, or covering developer-API usage beyond your plan's monthly allowance.

**How to use it.**
1. Open your **Wallet**.
2. Buy a coin package (some include bonus coins).
3. Spend coins on supported add-ons; your balance and a **recent transactions**
   ledger (showing each change and running balance) are always visible.

### AI credits

**What it is.** A separate metered balance that powers 1INME's AI features.

**Why use it.** AI features (the AI biolink builder, Ask Coach, AI Personas/Minds,
resume tailoring and cover letters, the card/brochure scanner, voice assistant,
etc.) consume **AI credits** as you use them.

**How to use it.** Just use the AI features — each call is billed to the right
balance automatically, and there's a credit ledger so you can see where credits
went. If you ever run out, you'll get a friendly prompt to top up before the
action runs (so you're never charged for something that can't complete). Some AI
runs that fail are automatically refunded.

---

## 6. Links: the basics

**What it is.** A "link" in 1INME is any shareable item you create — from a plain
short URL to a full mini-site. Everything lives under **All Links**.

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

## 7. Every link type explained

All link types are created the same way (pick the type in **Create Link**). Here's
what each one is for:

| Type | What it is / why use it |
| --- | --- |
| **Short Link** | Shorten any long URL with a custom alias and track clicks. The everyday workhorse. |
| **Link in Bio (Biolink)** | A customizable mini-site that gathers all your links, media, and content in one page — your "link in bio". Built with the [biolink editor](#9-the-biolink-editor). |
| **File Share** | Host a downloadable file behind a branded download page and short link. |
| **QR Code** | A trackable QR code; design it in [QR Studio Pro](#13-qr-studio-pro). |
| **Event** | A page visitors can add to their calendar in one tap, with RSVP collection. |
| **Contact Card (vCard)** | A digital business card; visitors tap "Save Contact" to download your `.vcf`. |
| **SMS** | A tap-to-text link that pre-composes a message to a number. |
| **WiFi** | A tap-to-join link/QR that connects visitors to a WiFi network. |
| **PDF** | Share a PDF behind a viewer/download page. |
| **Conversational** | A guided, chat-style page that walks visitors through your links one message at a time on a fixed script. |
| **Slides** | A swipeable, story-style deck served from a single link — great for presentations or portfolios. |
| **AI Chatbot (AI Chat)** | A full-page AI assistant that answers visitors' questions about you, powered by your AI Companion/Mind. |
| **Restaurant Menu** | A digital menu with categories, items, prices, photos, and optional table-side ordering. See [Restaurant menu](#22-restaurant-menu--orders). |
| **Resume / Portfolio** | A shareable, professional resume page with PDF download and AI tooling. |
| **Reviews Page** | A standalone wall for collecting and showcasing star reviews. |
| **Paid Page** | A monetized landing page that shows your posts, tiers, and tips, gated by visibility/payment. |
| **Product / Storefront** | Sell digital or physical products with native checkout (also available as a biolink block). |
| **Social** | Link and manage your connected social accounts. |

**Visibility tiers.** Biolink-family pages can be set to **Public**, **Registered
users only**, **Followers only**, or **Subscribers only**, and you can also
password-protect some pages. Visitors who don't meet the tier are blocked or
prompted to follow/subscribe.

---

## 8. Link management: aliases, A/B tests, smart links, domains

### Aliases

Every link has a primary **alias** (the slug). You can also add **additional
aliases** — alternative slugs that serve the same page without a redirect.

### A/B testing

**What it is.** Run two or more variants of a link and split traffic between them.

**How to use it.** Create an A/B test, define variants with weighted traffic
(e.g. a 50/50 split), watch the stats, then **declare a winner** to promote the
best-performing variant.

### Smart links & routing rules

**What it is.** Rules that send different visitors to different destinations.

**Why use it.** Show the right page based on who's visiting.

**How to use it.** Add **smart rules** to a link to route by:
- **Country / geo** (by visitor's country)
- **Device** (mobile / tablet / desktop)
- **Language** (browser language)
- **Time** (time-of-day windows, with timezone support)
- **A/B split** (weighted random)

### Custom domains

**What it is.** Use your own domain (e.g. `links.yourbrand.com`) instead of the
default 1INME domain.

**How to use it.** Open **Custom Domains**, add and verify your domain by setting
the DNS records shown, then choose it when creating links. Some plans also offer
shared **global domains**.

### Splash pages ("Intros") & link insurance

- **Intros / Splash pages** — optional interstitial pages shown briefly before a
  visitor reaches the final destination (great for announcements or branding).
- **Link insurance** — monitors a link's destination and can automatically fail
  over to a backup if the primary URL goes down, so your link never dead-ends.
- **Backlinks** — a radar that tracks where your links are being shared across
  the web (works alongside the browser extension).

---

## 9. The biolink editor

**What it is.** The visual editor for building a "link in bio" page out of
**blocks**. It's split into two pages: **Blocks** and **Settings**.

**Why use it.** It's how you assemble and style your mini-site without code.

**The Blocks page.**
1. Click **Add block** to open the block picker (organized by category).
2. **Drag and drop** blocks to reorder them by grabbing the handle.
3. Drop blocks **inside** Card or Grid containers to build complex layouts; inside
   a container you can set a block's **grid span** (e.g. full width, half width).
4. Use the **device preview** (mobile / tablet / desktop) on the side to see your
   page update live as you edit.
5. Newly added blocks arrive pre-filled with friendly placeholder text/media and
   a starter style, and show a "we dropped in placeholder content" banner — just
   edit and save, and the banner clears automatically.

**The Settings page.** See [section 11](#11-biolink-settings).

---

## 10. Biolink blocks catalog

Blocks are grouped into categories in the picker. Highlights:

**Essentials**
- **Link Button** / **Featured Link** — buttons to external URLs (featured is a
  larger, highlighted call-to-action).
- **Heading** / **Logo Heading** — titles, optionally with a logo.
- **Rich Text** / **Markdown** — formatted text.
- **Bulleted / Numbered List**, **Pricing List** — lists, including a tiered
  pricing list with "featured" highlights.
- **Alert Banner**, **Badge**, **Divider / Spacer**, **Link Group**.

**Layout & profile**
- **Card Container** — a styled card that holds other blocks inside it.
- **Grid / Auto-Fit Grid** — arrange child blocks in columns.
- **Card Carousel / Scrolling Cards** — swipeable card series.
- **Profile Card** (Classic, Cover, Stats, Badges) — your identity block with
  avatar, name, bio, and optional socials/stats/verified badges.

**Media**
- **Image / Image Grid / Image Slider** — photos, mosaics, galleries (with mask
  shapes, borders, and shadows; image blocks can carry a trackable destination
  URL).
- **Video / Header Video**, **Audio Player / Playlist**, **File Download**.
- **Embeds** for YouTube, Vimeo, Spotify, Apple Music, SoundCloud, Instagram,
  TikTok, X/Twitter, Pinterest, and more.

**Engagement (interactive)**
- **FAQ** (simple & accordion), **Poll**, **Quiz** (with live results),
  **Testimonials**, **Reviews / Reviews Wall**, **Timeline**.
- **AI Companion** — embed an AI chatbot directly on the page.
- **Buzz / Social Proof** — small popups showing recent activity.

**Commerce**
- **Product / Service**, **Catalog / Storefront**, **Coupon**, **Limited Offer**
  (with countdown), and tip/membership blocks (**Donation**, **Buy Me a Coffee**,
  **Ko-fi**, **Patreon**).

**Contact & lead capture**
- **Email Collector** / **Phone Collector**, **Contact Form**, **WhatsApp Chat /
  Button / Number**, **Direct Message** (to your 1INME inbox).

**Social profiles & feeds**
- **Social Icons / Hub**, platform **feeds** (YouTube, Instagram, TikTok, X), and
  **RSS Feed**.

### Per-block styling & display rules

- **Block styling** — each block has its own style controls (font, colors, corner
  radius, shadow, effects) plus ready-made templates; you can also set a global
  theme and let individual blocks override it.
- **Display / visibility settings** — show or hide a block based on a **schedule**,
  **location**, **device**, **OS**, **browser**, or **language**, so different
  visitors see different blocks.

---

## 11. Biolink settings

Open the **Settings** page of the biolink editor to control the whole page:

- **Appearance** — global background (color, gradient, image, or video), font
  family, and primary text color.
- **Layout** — content max-width, page padding, and block spacing per device.
- **Block theme** — set a global block theme (colors, radius, shadows,
  glassmorphism) or pick a pre-designed template; you can save looks as **themes**
  and even **schedule** a theme to apply for a date range (e.g. a holiday look).
- **SEO** — page title, description, and keywords for search engines.
- **Open Graph** — control how the page looks when shared (preview image, social
  card).
- **PWA (install as an app)** — enable a manifest so visitors can install your
  biolink to their home screen like an app.
- **Branding** — custom favicon and toggling the "Powered by 1INME" badge
  (plan-gated).
- **Custom CSS / JS** — inject your own code for advanced styling/behavior
  (plan-gated, Pro feature).

---

## 12. AI biolink builder & the wizard

### AI biolink builder

**What it is.** Describe the page you want in plain language and AI assembles it
for you.

**How to use it.**
1. Open the AI builder and type a prompt (e.g. "A page for my coffee shop with my
   menu, hours, and Instagram"). You can also attach images or links.
2. AI generates a complete page using safe, ready-to-use block types, and
   automatically appends any image/link you supplied that it didn't already place.
3. Review and edit the result like any biolink. (This uses AI credits; if the
   result can't be built, you're refunded.)

### Biolink wizard

**What it is.** A step-by-step guided builder (no AI prompt required).

**How to use it.** Answer a few questions:
1. **Category** — what the page is for (Creator, Business, Restaurant, etc.).
2. **Page type** — narrow it down (e.g. Business → Online Store).
3. **Industry** — optional tailoring.
4. **Questions** — specifics (e.g. "What's your menu URL?"), which the wizard turns
   into relevant blocks.

---

## 13. QR Studio Pro

**What it is.** A dedicated QR-code builder with deep design control and live
preview. Find it under **QR Codes**.

**Why use it.** Branded, scannable QR codes for print, packaging, signage, and
table tents — all trackable.

**How to use it.**
1. Pick a **content type** (16 supported: URL, WiFi, vCard, WhatsApp, email,
   phone, SMS, crypto, and more).
2. Enter the payload (e.g. WiFi network name + password).
3. Design it: choose from 30+ **design templates**, customize **dot shapes**, set
   **eye styling** (each of the three corner "eyes" can have its own outer/inner
   shape and color), add a **logo**, and apply a **frame** with a call-to-action.
4. Watch the **scannability checker** — it grades contrast, logo-vs-error-
   correction coverage, quiet zone, and risky shape combinations, and warns you
   before you create something that won't scan.
5. **Export** as PNG or SVG, print-ready **PDF** (configurable size/DPI/bleed), or
   generate many codes at once with **bulk CSV** export (downloads as a ZIP).
6. Attach a trackable link so scans flow into your analytics (geo, device,
   heatmap).

---

## 14. Analytics

**What it is.** Performance data for your links and pages, both workspace-wide
(**Stats**) and per link.

**Why use it.** See what's working — where clicks come from, which blocks get
tapped, and whether visitors come back.

**What you get.**
- **Clicks/visits** — total and unique counts over time.
- **Geographic heatmap** — a map of where your visitors are, powered by an
  interactive vector-tile map. Coordinates are saved for clicks and page visits.
- **Live visitors** — a real-time "visitors right now" indicator.
- **Block-level analytics** — taps/clicks on individual biolink blocks.
- **Referrers, UTMs, devices, browsers, OS** — traffic-source breakdowns.
- **Retention / returning visitors** — see how many visitors come back, including
  follower/subscriber cohorts.
- **RSVPs, poll/quiz results** — exportable response data from event and
  interactive blocks.

**How to use it.** Open a link and choose **Analytics**, or open **Stats** for the
whole workspace. You can **reset** a link's counters if you want a clean start.

**Pixel tracking.** Under **Pixel**, add third-party tracking pixels (Meta,
Google Analytics, GTM, LinkedIn, X, Pinterest, TikTok, Snapchat, Quora) so visits
and conversions also flow to your own marketing tools.

---

## 15. Audience & engagement

### Follow & subscribe

- **Follow** — other 1INME users can follow you; your updates then appear in their
  feed. Manage your **Followers** and who you follow from the sidebar.
- **Subscribe (Leads)** — visitors join your email or WhatsApp list via subscribe
  blocks on your biolink. These become **Leads**.

### Leads / subscriber management

**What it is.** Your built-in CRM for the people who've subscribed. Find it under
**Leads**.

**How to use it.**
1. Add an **Email Subscribe**, **WhatsApp Channel**, or **WhatsApp Number** block
   to a biolink.
2. Visitors enter their details and appear instantly in **Leads**.
3. Filter by **type** (email/WhatsApp), **status** (active/unsubscribed), or
   **source** (which page they came from).
4. Use **Compose** to send a message to a segment (configure your own SMTP and/or
   WhatsApp sending details in **Leads → Settings**, and optionally enable a
   **welcome email**).
5. **Export** your list (respects the current filter).

### Feed & discovery

- **My Feed** — updates from creators you follow (new posts, pinned posts, profile
  and link updates).
- **Discover creators** — a public directory to find and follow others.

### My Posts (creator feed)

**What it is.** Publish posts/updates that show up in your followers' feeds and on
your paid/creator page.

**How to use it.** Open **My Posts**, write a post, and publish (team workspaces
can route posts through an approval step). You can schedule posts and edit them.

### Buzz (social proof widgets)

**What it is.** Embeddable notification popups that build trust by showing live
activity. Find them under **Buzz**.

**Why use it.** "Someone just subscribed" or "120 people viewed this today" nudges
increase conversions.

**How to use it.** Create a Buzz campaign, pick a notification **type** (there are
7, e.g. recent activity, live visitor count, review popups), customize the design,
set targeting rules, and attach it to a biolink.

---

## 16. Reviews

**What it is.** A Google-style reviews system available two ways: a standalone
**Reviews Page** link type, or a **Reviews Wall** block inside any biolink.

**Why use it.** Collect and showcase social proof, and pull in your existing
ratings from Google and Trustpilot.

**How to use it.**
1. Add a **Reviews Page** or a **Reviews Wall** block.
2. Visitors leave a star rating and text — **no login required**. They can attach
   photos/audio/video and answer your custom questions. A spam check and honeypot
   run automatically.
3. (Optional) Turn on **customer verification** so reviews from people you can
   match (by email link, subscriber, or contact) are trusted and unverified ones
   are held back.
4. Imported reviews from **Google** and **Trustpilot** are merged into the same
   feed (read-only).
5. **Moderate** from the **Reviews** area: **Approve**, **Hide**, **Pin** to the
   top, **Reply** publicly, or **Delete** native reviews. You can do this on the
   web or in the mobile app.

---

## 17. Referrals

**What it is.** Invite friends to 1INME and both of you earn rewards. Find it under
**Referrals**.

**Why use it.** Free subscription time for spreading the word.

**How to use it.**
1. Copy your **referral code** or **referral link**.
2. Share it; when a friend signs up and activates a plan, you both earn rewards
   (e.g. free subscription days).
3. Track **clicks**, **signups**, and **conversions** on the Referrals page.

**Note.** Self-referrals (referring yourself with a second account) are not
rewarded.

---

## 18. Creator monetization

**What it is.** Tools to get paid by your audience — payouts, paid pages, product
sales, tips, and paid DMs. The **Monetization** / **Earnings & Payouts** area is
your hub.

**1INME's fee is 0%.** 1INME doesn't take a platform cut — you keep 100% of what
fans pay, minus only the payment processor's own fee.

### Payouts

**How to use it.**
1. Open **Earnings & Payouts** (`/user/payouts`).
2. Pick a payout **processor**: **Stripe Connect**, **PayPal**, **Razorpay**,
   **CCBill**, or **Segpay**.
3. Complete the processor's **hosted onboarding** (ID/KYC is handled on their
   secure site).
4. Set one connection as your **default**. Your dashboard shows whether payouts
   and charges are enabled.

### Ways to earn

- **Paid Page** — a monetized page showing your posts, tiers, and a tip option.
- **Tiers & promos** — subscription tiers for exclusive content, plus discount
  codes.
- **Product storefront** — sell digital/physical products with native checkout;
  manage **Orders** and fulfillment.
- **Tips** — let fans send one-off tips.
- **Paid DMs** — charge for direct messages.

The **Monetization** dashboard rolls up your earnings, subscribers, payments, and
orders in one place.

---

## 19. Adult content (18+)

**What it is.** An optional mode for creators who publish adult (18+) content,
with the legal and payment safeguards that requires. Find it under
`/user/adult-content`.

**How to use it.**
1. Toggle the **18+** switch and complete the three-part consent dialog: you
   confirm you're of legal age, that no minors are involved, and that you
   understand payouts are **locked to adult-friendly processors** (CCBill or
   Segpay).
2. Connect an adult-friendly payout processor.
3. Publish your 18+ content.

**Visitor experience.** Visitors must pass a 30-day age-gate screen before viewing
an 18+ profile, and 18+ profiles are hidden from the public Creators directory
unless a visitor opts to show adult content.

---

## 20. AI tools

1INME includes several AI helpers (all metered with **AI credits**):

- **Ask Coach / Performance Coach** — an AI assistant that reviews your account
  and answers "how do I improve?" questions, including actionable suggestions.
- **AI Personas** — configurable AI personalities for automated interactions.
- **AI Companions** — AI chatbots you can embed on a biolink (as a block) or run
  as a full-page **AI Chatbot** link, so visitors can ask questions about you and
  get dynamic answers. The **owner** pays for visitor chats, not the visitor.
- **AI Mind / Minds** — a private knowledge base: upload documents and links to
  "train" your AI so its answers reflect your real information.

Other AI helpers appear inside specific tools — e.g. **resume tailoring** and
**cover-letter generation** in the Resume builder, and a **card/brochure scanner**
that extracts contact details from a photo.

---

## 21. Tools

### Forms

**What it is.** A drag-and-drop form builder with **21 field types** (text, email,
phone, dropdown, rating, scale, signature, file upload, section/group, and more).
Find it under **Forms**.

**How to use it.**
1. Click **New Form**, give it a title.
2. In the **Form Builder**, drag fields from the palette and configure each (e.g.
   options for a dropdown, max file size for uploads).
3. Customize the design and set up **notifications** (email, SMS, or webhook) for
   new submissions.
4. Save, then share the public URL, grab the **embed** code, or add the form to a
   biolink. View entries under **Submissions**.

### Contact cards (vCard)

**What it is.** A full digital business card (vCard 3.0) with multiple emails,
phones, URLs, addresses, and social profiles.

**How to use it.** Create a **Contact Card** link (or a vCard block), fill in your
details, and share it. Visitors tap **Save Contact** to download a standards-
compliant `.vcf`.

### Contacts & Dialer

**What it is.** A personal address book plus an in-app dialer. Find them under
**Contacts** and **Dialer**.

**How to use it.**
1. In **Contacts**, optionally connect **Google Contacts** for two-way sync (it
   keeps both sides in step and runs automatically in the background).
2. Open the **Dialer** for a number pad with **T9 search** (type digits to find
   names), **speed dial** favorites, and **recent/frequent** contacts.
3. **Caller ID lookup** can resolve a phone number to a 1INME profile, and contacts
   whose verified phone matches a 1INME user get their biolink attached
   automatically.
4. Calls and emails open your device's native dialer/mail (`tel:` / `mailto:`) —
   there's no in-app VOIP.

### Files / Vault

**What it is.** Your personal file storage for images, video, audio, and documents
used across links and posts. Find it under **Files** / **Vault**.

**How to use it.** Drag and drop to upload; a progress bar shows your **used vs.
limit** storage. Upgrade your plan for more quota. Files you upload here can be
reused anywhere in 1INME.

### Resume / Portfolio

**What it is.** A standalone resume builder that doubles as a shareable resume
link with PDF download. Find it under **Resume / Portfolio**.

**How to use it.**
1. Build your resume section by section; you can keep multiple named **versions**.
2. Use the AI tools: **Tailor to a job** (paste a job description and get suggested
   edits/keywords), **Generate cover letter**, and **import** an existing resume.
3. Check **ATS readiness** for formatting issues that hurt applicant-tracking
   systems.
4. Create a **Resume** link to share it publicly (you can point it at a specific
   version), or download the PDF.

### Calendar & events

- **Events** — create **Event** links; visitors add them to their calendar and
  RSVP, and you can view/export the guest list.
- **Calendar Sync** — connect Google/Microsoft calendars to manage availability.

### Projects & Task Boards

- **Projects** — group related links, files, and work together to stay organized.
- **Task Boards / Tasks** — lightweight task tracking inside the workspace.

---

## 22. Restaurant menu & orders

**What it is.** A dedicated digital-menu page type for restaurants and cafes, with
optional table-side ordering. It has its own builder (it doesn't use the block
editor).

**How to use it (owner).**
1. Create a **Restaurant Menu** link.
2. Build **Categories**, then add **Items** (name, description, price, photo).
3. Set display options, order mode, currency, and accent color.
4. If you want table ordering, define **Tables** — each gets its own unique QR/URL
   so a diner's order is tied to their table.
5. When orders come in, manage them in the near-real-time **Orders Dashboard**,
   moving each from **Pending → Preparing → Served → Paid/Cancelled**.

**Visitor experience.** Diners scan the table QR (or open the menu link), browse,
and tap **Place Order**.

**Mobile.** The restaurant menu has a full native builder in the 1INME mobile app
too — no need to switch to the web.

---

## 23. Inbox & messages

**What it is.** A unified **Inbox** that gathers messages sent to you — biolink
direct messages, form submissions, and similar inbound communication.

**How to use it.** Open **Inbox** to read and triage incoming messages in one
place. (Paid DMs, if enabled, also flow through your messaging.)

---

## 24. Notifications & digests

**What it is.** In-app and email notifications keep you posted on activity (new
subscribers, reviews, comments, security alerts, API usage warnings, and more).

**How to use it.**
- Open **Notifications** to see your activity feed; mark items read, dismiss them
  (dismissed items are restorable for 30 days), or mark all read.
- Manage **notification preferences** per channel so you only get what you want.
- **Digests** are periodic email summaries of your activity; 1INME won't send an
  empty digest, and you can send yourself a sample to preview the format.

---

## 25. Organizing your work

For teams and agencies, 1INME scales beyond a single user:

- **Workspaces** — separate environments for different brands/projects, each with
  its own branding and settings.
- **Team & staff** — invite members and assign roles/permissions (e.g. Owner,
  Admin, Editor, Viewer). Owners can enforce 2FA for everyone and review a
  sensitive-action **audit log**.
- **Client portals** — give external clients a limited area to view shared
  boards, files, and deliverables without full account access.
- **Workspace Vault** — a shared secure store for the workspace.

---

## 26. Security & sessions

**What it is.** Controls to keep your account safe. Find them under **Security**
(and **Linked identifiers** / **Verification**).

**How to use it.**
- **Active sessions/devices** — see every device signed in to your account and
  **revoke** any one (or all others) with a click.
- **Recent logins** — review recent sign-ins with time, device, location, and IP.
  If something looks wrong, tap **"This wasn't me"** to revoke it. 1INME emails you
  when there's a login from a new device, browser, or country.
- **Two-factor authentication (2FA)** — turn on an extra challenge code at sign-in.
- **Verification** — verify your identity/badges where applicable.

---

## 27. Settings & integrations

- **Integrations** — connect third-party services (e.g. Google Contacts, social
  accounts, and the various embeds/pixels). Available integrations depend on your
  platform's configuration.
- **Connected Accounts** — link your social and OAuth accounts.
- **API keys** — generate developer API keys to use the 1INME REST API (usage is
  metered against your plan's monthly allowance; overage can be paid from coins).
  See [`api.md`](./api.md) for the developer reference.
- **Pixel** — manage marketing tracking pixels (see [Analytics](#14-analytics)).

---

## 28. Mobile app & browser extension

- **1INME mobile app** — most creator features have native parity in the mobile
  app, including links, biolink editing, QR Studio, restaurant menus, reviews
  moderation, payouts, and the 18+ toggle. Sign in with email/OTP or social.
- **Browser extension** — helps with things like saving links and powering the
  Backlinks radar from your browser.

---

# FAQ

### Account & sign-in

**How do I sign in without a password?**
Choose the "sign in with a code" option. 1INME sends a 6-digit one-time code to
your email (or phone, if enabled). Enter it to sign in — no password needed.

**I forgot my password. What now?**
Use the one-time-code (OTP) sign-in to get back in, then set a new password from
your account settings.

**Can I use Google/Apple to sign in?**
Yes, if your platform has social sign-in enabled — look for the "Continue with…"
buttons on the sign-in page.

**How do I turn on two-factor authentication?**
Go to **Security** and enable 2FA. After that, you'll enter an extra challenge
code each time you sign in.

**Why don't I see WhatsApp/phone login?**
Phone/WhatsApp login is an optional method that an administrator must enable;
email sign-in is always available.

**How do I change my handle?**
Open **Profile**, edit the handle field, and save. Your handle is your public
address (`@yourname`) and is used in the Creators directory.

### Plans, coins & AI credits

**What's the difference between coins and AI credits?**
**Coins** are a general prepaid balance (top up by buying coin packages) used for
add-ons and developer-API overage. **AI credits** specifically power AI features
like the AI builder, Ask Coach, Personas/Minds, and resume AI tools.

**What happens if I run out of AI credits?**
Before an AI action runs, 1INME checks you can afford it; if not, you'll be
prompted to top up rather than being charged for something that can't finish.
Failed AI runs are typically refunded.

**Which plan should I choose?**
Open **Plans** — 1INME measures how close you are to your limits and recommends a
plan, with a comparison matrix so you can decide. Upgrade when you're near a cap
or need a pro feature (custom domain, custom CSS/JS, custom branding, etc.).

**Do my new plan features apply right away?**
Yes — once payment succeeds, higher limits and unlocked features apply
immediately.

### Links

**What's the difference between a short link and a biolink?**
A **short link** redirects to one destination URL. A **biolink** is a whole
mini-site (a page of blocks) at your own address — your "link in bio".

**Can I use my own domain?**
Yes. Add and verify it under **Custom Domains**, then pick it when creating links.

**Can two slugs point to the same page?**
Yes — add **additional aliases** to a link; they serve the same page with no
redirect.

**How do I send mobile and desktop visitors to different places?**
Use **smart rules** on the link to route by device (and you can also route by
country, language, or time of day).

**How do I A/B test a link?**
Create an A/B test with weighted variants (e.g. 50/50), watch the stats, then
**declare a winner** to promote the best one.

**What if my destination URL goes down?**
Turn on **link insurance**, which monitors the destination and can fail over to a
backup automatically.

**Can I see where my link has been shared?**
Yes — the **Backlinks** radar tracks where your links appear across the web.

### Biolink editor

**How do I add and reorder blocks?**
On the **Blocks** page, click **Add block** to pick from the catalog, then drag
blocks by their handle to reorder. Drop blocks into Card/Grid containers for
layouts.

**My new block has placeholder text — is that a problem?**
No. New blocks come pre-filled with placeholder content and a starter style so
the page never looks empty. Edit the block and save; the "placeholder" banner
clears automatically.

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
Yes. Use the **AI biolink builder** — describe what you want (and attach images/
links) and it assembles the page. Prefer no prompt? Use the **wizard** and answer
a few questions.

**Can I save and schedule different looks?**
Yes — save the current look as a **theme**, then optionally **schedule** a theme
to apply for a date range.

### QR codes

**Will my fancy QR code still scan?**
QR Studio Pro has a built-in **scannability checker** that grades contrast, logo
coverage, quiet zone, and risky shape choices, and warns you before you create
one that won't scan.

**Can I generate many QR codes at once?**
Yes — use **bulk CSV** export; 1INME builds all the codes and downloads them as a
ZIP.

**Can I track QR scans?**
Yes — attach a trackable link so scans flow into your analytics (geo, device,
heatmap).

**What can a QR code contain?**
16 content types, including URL, WiFi, vCard, WhatsApp, email, phone, SMS, and
crypto.

### Analytics

**Where do I see my stats?**
Open a specific link and choose **Analytics**, or open **Stats** for everything in
the workspace.

**Can I see where my visitors are?**
Yes — the **geographic heatmap** maps visitor locations, and **live visitors**
shows who's on your page right now.

**Can I track conversions in my own marketing tools?**
Yes — add tracking **pixels** (Meta, Google, TikTok, etc.) under **Pixel**.

**Can I reset a link's numbers?**
Yes — use **reset analytics** on the link.

### Audience, reviews & referrals

**How do people subscribe to me?**
Add an **Email Subscribe** or **WhatsApp** block to your biolink; subscribers show
up instantly under **Leads**, where you can segment, message, and export them.

**Can I email my subscribers from 1INME?**
Yes — use **Compose** in **Leads**. Configure your own SMTP/WhatsApp sending
details in **Leads → Settings**, and optionally enable a welcome email.

**How do reviews work?**
Add a **Reviews Page** or **Reviews Wall** block. Visitors leave star ratings
(no login), and you **approve/hide/pin/reply** from the **Reviews** area. You can
also import Google and Trustpilot reviews into the same feed.

**Do reviewers need an account?**
No — native reviews require no login. They can add photos/audio/video and answer
your custom questions; spam protection runs automatically.

**How do referrals reward me?**
Share your **referral code/link**; when a friend signs up and activates a plan,
you both earn rewards (e.g. free subscription days). Referring yourself with a
second account doesn't count.

### Monetization

**How much does 1INME take from my earnings?**
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

**I publish adult content — what do I need to do?**
Enable the **18+** toggle and complete the consent dialog. Note payouts for 18+
content are locked to adult-friendly processors (CCBill or Segpay), and visitors
must pass an age gate.

### Tools

**How many field types do forms support?**
21 — including text, email, phone, dropdown, rating, scale, signature, file
upload, and section/group fields. You can get notified by email, SMS, or webhook.

**Can I sync my phone contacts?**
Yes — connect **Google Contacts** for two-way sync, then use the **Dialer** (T9
search, speed dial, recents). Calls/emails use your device's native apps.

**Can 1INME build my resume?**
Yes — the **Resume / Portfolio** builder supports multiple versions plus AI
**tailoring to a job**, **cover-letter** generation, import, and an **ATS
readiness** check, and publishes as a shareable link with PDF download.

**How does the restaurant menu's table ordering work?**
Define **Tables**, each with its own QR/URL. Diners scan, browse, and **Place
Order**; you manage orders in the **Orders Dashboard** through Pending → Preparing
→ Served → Paid/Cancelled.

**Where do messages people send me go?**
To your **Inbox** — it gathers biolink DMs and form submissions in one place.

### AI features

**What is an AI Companion vs. an AI Mind?**
A **Mind** is a private knowledge base you fill with your documents/links. A
**Companion** is the chatbot that answers visitors using that knowledge — you can
embed it as a block or run it as a full-page **AI Chatbot** link.

**Who pays when a visitor chats with my AI Companion?**
You do (the owner), from your AI credits — visitors don't pay.

**What is Ask Coach?**
An AI assistant that reviews your account and gives plain-language advice on how
to improve your links and pages.

### Account safety

**How do I see what devices are signed in?**
Open **Security** to view active sessions and recent logins; revoke any device, or
tap "This wasn't me" on a suspicious login.

**Will I be warned about suspicious sign-ins?**
Yes — 1INME emails you about logins from a new device, browser, or country.

### Teams & organization

**Can I add team members?**
Yes — invite them under **Team** and assign roles (Owner/Admin/Editor/Viewer).
Owners can enforce 2FA and review an audit log.

**Can I share work with a client without giving full access?**
Yes — use **Client portals** to share boards/files/deliverables in a limited area.

**How do I keep different brands separate?**
Use **Workspaces** — each is its own environment with its own branding and
settings.

### Mobile & developer

**Is there a mobile app?**
Yes — most features have native parity, including links, the biolink editor, QR
Studio, restaurant menus, reviews moderation, and payouts.

**Does 1INME have an API?**
Yes — generate **API keys** in settings and see [`api.md`](./api.md). API usage is
metered against your plan; overage can be covered by coins.
