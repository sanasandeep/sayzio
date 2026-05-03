# 1INME Browser Extension

Cross-browser MV3 extension (Chrome, Firefox, Edge) for [1INME](https://1inme.com).

Two primary actions on any page you visit:

1. **Shorten & copy** — turns the current tab's URL into a 1INME short link, copies it to your clipboard, and shows a toast with a deep link to analytics.
2. **Turn into bio-link page** — scrapes the current page's title, description, OG image, and outbound/social links, creates a draft bio-link in your 1INME workspace pre-filled with header + link blocks, and opens the bio-link editor so you can refine and publish.

A **right-click context menu** mirrors both actions:

- **Shorten this page with 1INME** (right-click anywhere on a page)
- **Shorten link with 1INME** (right-click on any link)
- **Turn page into 1INME bio-link** (right-click anywhere on a page)

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

- **Sign in with 1INME** — opens `https://1inme.com/extension/handshake` in a new tab. After you're logged in there, the page exposes a freshly-minted Sanctum token in a JSON `<script>` tag; the extension's content script captures it via `browser.runtime.sendMessage` and stores it in `browser.storage.local`. The handshake tab then closes itself.
- **Email + password** — calls `POST /api/v1/auth/login` directly from the popup.

The bearer token is attached as `Authorization: Bearer …` to every API call. A 401 clears the stored token and re-prompts.

**Sign out** from the popup calls `POST /api/v1/auth/logout` (which deletes the token server-side) and clears local storage.

## Settings

Open the gear icon in the popup header to override the API/web base URLs (default `https://1inme.com`). Useful when testing against a local 1INME workflow — point both URLs at your dev domain (e.g. `https://<repl-id>.replit.dev` and `https://<repl-id>.replit.dev/api/v1`).

## Storage shape

Persisted under `browser.storage.local`:

```ts
{
  apiBaseUrl: string,
  webBaseUrl: string,
  token: string | null,
  user: { id, name, email, handle? } | null,
  workspaceId: number | null,
  workspaces: Array<{ id, name }>,
}
```

## Store submission (out of scope here, but the steps you'll need)

Each store needs slightly different supporting material; the build output is the same zip in every case.

- **Chrome Web Store** — pay the one-time $5 developer registration fee, create a listing, upload `1inme-extension-chrome.zip`, supply 1280×800 screenshots, a 440×280 promo tile, a privacy policy URL, and answer the data-collection questionnaire.
- **Edge Add-ons** — register in Partner Center (free), upload `1inme-extension-edge.zip`, fill in store listing copy and screenshots.
- **Firefox AMO** — sign in to addons.mozilla.org, upload `1inme-extension-firefox.zip`, agree to the developer agreement; AMO will sign the package and produce the persistent `.xpi`.

The extension already ships icons sized 16/32/48/128 (under `public/icons/`) — replace these with higher-res master assets before submitting.

## Troubleshooting

- **"Not signed in" toast** — open the popup and sign in. The 401 path also clears the stored token automatically.
- **Clipboard copy did nothing** — Chrome blocks clipboard writes from service workers, so the extension injects a small copy helper into the active tab. On `chrome://*` and Web Store pages the injection is blocked; click the toast and copy manually.
- **Bio-link blocks missing** — the API returns the new draft regardless; failing block inserts (often plan limits) are skipped silently. Open the editor to add them by hand.
