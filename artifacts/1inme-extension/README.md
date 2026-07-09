# Sayzio Browser Extension

Cross-browser MV3 extension (Chrome, Firefox, Edge) for [Sayzio](https://sayzio.app).

> **Related docs:** [REST API reference](../1inme/docs/api.md) · [Mobile app](../1inme-mobile/docs/mobile-app.md)

Primary actions on any page you visit:

1. **Shorten & copy** — turns the current tab's URL into a Sayzio short link, copies it to your clipboard, and shows a toast with a deep link to analytics. When shortening you can attach **smart rules** (geo / device / language / time routing, plan-gated by `link_smart_rules`) and start an **A/B test** straight from the popup.
2. **Turn into bio-link page** — scrapes the current page's title, description, OG image, and outbound/social links, creates a draft bio-link in your Sayzio workspace pre-filled with header + link blocks, and opens the bio-link editor so you can refine and publish.
3. **Save contact** — extracts the page author/business contact (vCard `.vcf`, hCard microformats, JSON-LD `Person`/`Organization`, or a heuristic email/phone scrape) and saves it to your Sayzio address book in one click (`POST /api/v1/contacts`). Default tags and the active workspace for saves are configurable in **Settings**.
4. **Backlink radar** (opt-in) — quietly notices when a page you're browsing links **to you** (one of your short links, your bio-link username path, or any of your verified custom domains) and surfaces a "This page links to you" card in the popup with one-click **Save**, **Open**, and **Thank** actions. A **Backlinks** tab keeps a filterable history with CSV export.

New in this release:

5. **Notifications** — a 🔔 tab in the popup polls `/api/v1/notifications` every 30 s and shows an unread badge. High-signal events (new subscriber, form submission, restaurant/store order, payment received, review received) also fire a native browser notification. Mark-all-read is one click.
6. **Click-to-dial** (opt-in) — when turned on in Settings, a content script scans pages for phone numbers and adds a hover overlay that shows the matching Sayzio contact, bio-link, and recent activity. The lookup is relayed to the background service worker so the API token stays safe.
7. **Capture reviews** — detect the Google Maps or Trustpilot business on the current page and pull reviews into your Sayzio Reviews wall in one click (`POST /api/v1/me/reviews/capture-source`).
8. **Add to existing bio-link** — pick any of your bio-links from a dropdown and append the current page as a new link block, without leaving the browser tab.
9. **Quick QR** — choose a preset style and generate a QR code for the current URL directly from the popup (`POST /api/v1/qr-codes`).
10. **Add to calendar** — auto-extracts JSON-LD / Microdata event data from the page and pre-fills a form to add the event to any of your Sayzio calendars.
11. **Dual-mode page → bio-link** — the "Turn into bio-link page" button now opens a mode picker: **Quick** (instant, no AI credits) or **AI-powered** (uses the AI Biolink Builder from your wallet).

A **right-click context menu** mirrors these actions:

- **Shorten this page with Sayzio** (right-click anywhere on a page)
- **Shorten link with Sayzio** (right-click on any link)
- **Turn page into Sayzio bio-link** (right-click anywhere on a page)
- **Save contact with Sayzio** (right-click anywhere on a page)
- **Add this page to a bio-link** (right-click anywhere on a page)
- **Design QR for this page / this link** (right-click anywhere or on a link)
- **Add page event to Sayzio calendar** (right-click anywhere on a page)
- **Capture reviews for this business** (right-click anywhere on a page)

## Permissions

From `src/manifest.chrome.json` / `src/manifest.firefox.json` (both MV3):

- `permissions`: `activeTab`, `storage`, `contextMenus`, `scripting`, `notifications`, `tabs`, `alarms`.
- `host_permissions`: `<all_urls>` (Chrome/Edge also declare `optional_host_permissions` for `http(s)://*/*`).
- **Static content script**: `content-handshake.js`, matched against `https://sayzio.app/extension/handshake*`.
- **Dynamically registered content scripts** (via the `scripting` API): the backlink **radar** against `http(s)://*/*` (minus muted hosts); the **click-to-dial** detector (opt-in via Settings) against `http(s)://*/*`; and a **handshake** script against your configured `webBaseUrl` when it differs from the default.
- **Injected-on-demand content scripts** (via `executeScript` inside popup flows): `content-event-extract.js` (JSON-LD/Microdata event detection for Add-to-calendar) and `content-review-detect.js` (Google Maps / Trustpilot business detection for Capture reviews). These run only when the relevant popup view is opened, never passively.
- **Background**: a module service worker (`background.js`) on Chrome/Edge; Firefox uses a non-service-worker background `scripts` entry (`browser_specific_settings.gecko`, `strict_min_version` 115). The worker manages context menus, orchestrates auth handshakes, coordinates radar scans, performs clipboard writes via injected scripting, and runs two `alarms`-driven periodic syncs (~30 s each): the pending-thanks queue and the notifications unread count (which also sets the extension badge).

## Build

```bash
# Single browser
pnpm --filter @workspace/1inme-extension run build:chrome
pnpm --filter @workspace/1inme-extension run build:firefox
pnpm --filter @workspace/1inme-extension run build:edge

# All three at once
pnpm --filter @workspace/1inme-extension run build:all
```

Outputs land under `artifacts/1inme-extension/dist/`:

- `dist/chrome/` (unpacked) and `dist/1inme-extension-chrome.zip`
- `dist/firefox/` and `dist/1inme-extension-firefox.zip`
- `dist/edge/` and `dist/1inme-extension-edge.zip`

Chrome and Edge share one manifest; Firefox uses a sibling manifest with `browser_specific_settings.gecko` and a non-service-worker background script for MV3-on-Gecko compatibility.

## Side-loading for testing

### Chrome

1. Open `chrome://extensions`
2. Toggle **Developer mode** on (top right)
3. Click **Load unpacked** and choose `artifacts/1inme-extension/dist/chrome/`

### Microsoft Edge

1. Open `edge://extensions`
2. Toggle **Developer mode** on (left sidebar)
3. Click **Load unpacked** and choose `artifacts/1inme-extension/dist/edge/`

## Auto-pixel retargeting

Add your **Meta**, **TikTok** and **Google Ads** pixel IDs once in
**Settings → Tracking pixels** in the popup. From then on every short
link you create from the extension fires those pixels on click, so any
visitor who clicks one of your links lands in your ad-platform
retargeting audiences — even when the click happens on a third-party
site you don't control.

How it works:

- When a short link is opted-in to **auto-pixel** (default for any
  workspace that has at least one pixel ID configured) and the
  workspace has pixels saved, the redirect serves a tiny <5KB
  interstitial that loads the configured pixel scripts, fires
  `PageView` + a custom `LinkClick` event with the link slug, then
  `window.location.replace`s to the destination.
- Workspaces with **no** pixels configured stay direct 302s — zero
  perf cost, no interstitial.
- The **Auto-pixel** toggle in the popup (next to the "Pixels: …"
  badge) lets you turn the behavior off for the next link you
  create. Each row in the **Recent links** list also has a per-link
  toggle.

### Where to find each ID

- **Meta Pixel ID** — Events Manager → Data sources → your pixel.
  15–16 digits.
- **TikTok Pixel ID** — TikTok Ads → Tools → Events → Web Events.
  Alphanumeric, e.g. `C7XXXXXXXXXXXXXXXX`.
- **Google Ads Conversion ID + Label** — Google Ads → Tools →
  Conversions → your action → Tag setup. ID looks like
  `AW-1234567890`; the label is a short alphanumeric token.

### What's sent

Pixels receive only the click context:

- Page URL of the interstitial (the short-link URL)
- Referrer (the site the click happened on)
- A per-day SHA-256 hash of the visitor's IP + user-agent (used only
  to dedupe fires server-side — never raw)

No PII is sent. Only browser-side pixel events fire — there's no
server-side Conversions API call in v1.

### Verifying pixels fire

Use **Settings → Tracking pixels → Test pixels (open a link)** to
open one of your recent short links in a new tab, then check
[Meta Pixel Helper](https://chrome.google.com/webstore/detail/meta-pixel-helper/fdgfkebogiimcoedlicjlajpkdmockpc),
[TikTok Pixel Helper](https://chrome.google.com/webstore/detail/tiktok-pixel-helper/aelgobmabdmlfmiblddjfnjodalhidnn),
or [Google Tag Assistant](https://chrome.google.com/webstore/detail/google-tag-assistant-lega/kejbdjndbnbjgmefkgdddjlbokphdefk).

### Firefox

1. Open `about:debugging#/runtime/this-firefox`
2. Click **Load Temporary Add-on…**
3. Choose any file inside `artifacts/1inme-extension/dist/firefox/` (e.g. `manifest.json`)

Temporary add-ons in Firefox are removed when the browser closes — sign the zip with [`web-ext`](https://github.com/mozilla/web-ext) for a persistent install.

## Sign in

Two options from the popup:

- **Sign in with Sayzio** — opens `https://sayzio.app/extension/handshake` in a new tab. After you're logged in there, the page exposes a freshly-minted Sanctum token in a JSON `<script>` tag; the extension's content script captures it via `browser.runtime.sendMessage` and stores it in `browser.storage.local`. The handshake tab then closes itself.
- **Email + password** — calls `POST /api/v1/auth/login` directly from the popup.

The bearer token is attached as `Authorization: Bearer …` to every API call. A 401 clears the stored token and re-prompts.

**Sign out** from the popup calls `POST /api/v1/auth/logout` (which deletes the token server-side) and clears local storage.

## Settings

Open the gear icon in the popup header to override the API/web base URLs (default `https://sayzio.app`). Useful when testing against a local Sayzio workflow — point both URLs at your dev domain (e.g. `https://<repl-id>.replit.dev` and `https://<repl-id>.replit.dev/api/v1`). The Settings tab also surfaces contact-save preferences (default tags, one-click toggle, contact workspace) and the workspace tracking-pixels badge.

## Storage shape

Persisted under `browser.storage.local`. The canonical `ExtSettings` type lives in `src/lib/storage.ts`:

```ts
{
  apiBaseUrl: string,
  webBaseUrl: string,
  token: string | null,
  user: { id, name, email, handle?,
          capabilities?: { link_smart_rules?, max_smart_rules? } } | null,
  workspaceId: number | null,
  workspaces: Array<{ id, name }>,

  // Contacts: "Save contact" preferences
  contactDefaultTags: string[],        // applied client-side before POST /contacts
  contactAllowOneClick: boolean,       // gates the one-click save button
  contactWorkspaceId: number | null,   // overrides the active workspace for saves

  // Backlink radar
  radarEnabled: boolean,
  radarOnboarded: boolean,
  radarDisabledHosts: string[],

  // Thank-you templates (max 3) + last-write-wins sync metadata
  thankTemplates: ThankTemplate[],
  thankTemplatesUpdatedAtMs: number | null,
  thankTemplatesLastServerTs: number | null,  // optimistic-concurrency token
  thankTemplatesWorkspaceId: number | null,

  // Queued thank-yous (Backlinks tab) + sync metadata
  pendingThanks: PendingThank[],
  pendingThanksUpdatedAtMs: number | null,
  pendingThanksWorkspaceId: number | null,
  pendingThanksSeenIds: string[],

  // Per-host "author book": cached email / X handle / LinkedIn URL,
  // keyed by host (or "host|path"), capped at AUTHOR_BOOK_MAX (500)
  authorBook: Record<string, AuthorBookEntry>,
}
```

Two radar caches are stored under their own keys (not part of `ExtSettings`):

```ts
// key: "radarProperties" — cached "known properties" payload (TTL-bounded)
{ short_link_hosts, biolink_hosts, biolink_username_path, custom_domain_hosts,
  slug_hashes, slug_hash_prefix_len, slug_hash_algo, cached_at,
  cache_ttl_seconds, fetched_at_ms }

// key: "radarTabMatches" — per-tab match state, cleared on navigation/close
Record<string, { pageUrl, pageTitle, matches[], scannedAt,
                 author?: { email, xHandle, linkedinUrl } }>
```

## Backlink radar — data flow & privacy

The radar is **off by default**. On first install the popup shows a one-screen
opt-in. You can flip it on/off any time in **Settings**, and you can mute
specific hosts (e.g. `mybank.com`) so the radar's content script never even
loads on them.

When enabled:

1. A small **content script** (`content-radar.js`) is registered against
   `http(s)://*/*` (excluding muted hosts) and runs `document_idle`. It
   collects outbound `<a href>` URLs and their anchor text, plus (for the
   **Thank** action) the page author's public contact handles — email
   (`mailto:`/regex), X handle (`rel=author`/meta), and LinkedIn URL. **No
   other page text, body content, cookies, or PII is read or transmitted.**
2. The harvested URLs and any sniffed author contacts are sent over
   `runtime.sendMessage` to the background service worker. The page itself
   never sees the creator's "known properties" list or any account data.
   Sniffed author contacts are cached per host in the local **author book** so
   the Thank composer can pre-fill instantly on repeat backlinks from the same
   publisher.
3. The background worker fetches `GET /api/v1/me/properties` once per hour
   and caches it locally. The payload includes:
   - the platform's short-link hosts and the user's verified custom domains
     (exact host match)
   - the user's bio-link username path (e.g. `/handle`)
   - **hashed** prefixes (12 hex chars of SHA-256) of every short-link
     alias the user owns — so the full slug list never lives in the
     extension's memory and `/me/properties` traffic doesn't expose
     it either. Path segments of harvested URLs are hashed the same way
     and looked up in the set.
4. When at least one href matches, the toolbar action gets a numeric
   badge + tinted color, and the popup's **Page** tab shows a
   "This page links to you" card with **Save**, **Open**, and **Thank**
   actions per match.
5. Only matches the user explicitly **Saves** are sent to
   `POST /api/v1/backlinks` — the page URL, anchor text, matched URL, and
   matched property type. Nothing else leaves the browser.
6. Per-tab match state is dropped when the tab navigates away or closes.

## Store submission (out of scope here, but the steps you'll need)

Each store needs slightly different supporting material; the build output is the same zip in every case.

- **Chrome Web Store** — pay the one-time $5 developer registration fee, create a listing, upload `1inme-extension-chrome.zip`, supply 1280×800 screenshots, a 440×280 promo tile, a privacy policy URL, and answer the data-collection questionnaire.
- **Edge Add-ons** — register in Partner Center (free), upload `1inme-extension-edge.zip`, fill in store listing copy and screenshots.
- **Firefox AMO** — sign in to addons.mozilla.org, upload `1inme-extension-firefox.zip`, agree to the developer agreement; AMO will sign the package and produce the persistent `.xpi`.

The extension already ships icons sized 16/32/48/128 (under `public/icons/`) — replace these with higher-res master assets before submitting.

Once a listing is live, paste its direct URL into **Admin → Marketing settings → Browser extension store links**. The web install card (Settings → Connected Accounts & Apps) and the mobile Browser extension page both read those settings (via `ExtensionStoreLinks` / `GET /api/v1/extension/stores`), so they switch from store *search* links to the real listing without a code deploy.

## Troubleshooting

- **"Not signed in" toast** — open the popup and sign in. The 401 path also clears the stored token automatically.
- **Clipboard copy did nothing** — Chrome blocks clipboard writes from service workers, so the extension injects a small copy helper into the active tab. On `chrome://*` and Web Store pages the injection is blocked; click the toast and copy manually.
- **Bio-link blocks missing** — the API returns the new draft regardless; failing block inserts (often plan limits) are skipped silently. Open the editor to add them by hand.
