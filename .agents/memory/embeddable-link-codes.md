---
name: Embeddable link codes
description: How "embed any link on external sites" works — public CORS endpoints, card vs iframe split, visibility gating.
---

# Embeddable link codes

Creators copy a snippet from a link's Settings to embed it on third-party sites. Two render modes:
- **Page-style** (biolink family + paid_page/resume/reviews) → responsive `<iframe>` that 302-redirects to the canonical short URL (the page sets its own framing headers + tracks).
- **Card types** (`Link::EMBED_CARD_TYPES`: url/file/pdf/ics/vcf, see `isEmbedCard()`/`embedKind()`) → a compact self-contained card with an intent-labeled button: Open / Download / Add to calendar / Save contact / View (from `embedAction()`).

**Endpoints** (anonymous, CORS-open `*`, `X-Frame-Options: ALLOWALL`): `/embed/link/{alias}/card`, `/iframe`, `/embed.js`, plus an OPTIONS preflight (204). Served by `PublicEmbedController`; routes registered in `routes/web.php` BEFORE the `{alias}` catch-all (catch-all already excludes the `embed` prefix). Card view is a full standalone HTML doc (`resources/views/common/embed/card.blade.php`) with inline CSS, auto light/dark, and a postMessage height beacon for auto-resize.

**Visibility:** embeds are anonymous cross-origin, so there is NO follower/subscriber resolution — any non-public link renders a "View on site" gated card, never the content. `isAccessible()` false → "unavailable" card. Missing alias → 404 but STILL carries CORS + framing headers.

**Tracking:** reused for free — the card button and the page iframe both navigate to `getShortUrl()`, which runs the existing click path. No new tracking code.

**Gotcha:** the controller must use the `type_label` ACCESSOR (`$link->type_label`), NOT `$link->typeLabel()` — `typeLabel(?string $type)` is a STATIC needing the type arg; calling it with no arg throws "Too few arguments" → 500.

**Settings UI:** reusable partial `resources/views/user/links/partials/embed-panel.blade.php` (script + iframe snippets, Alpine clipboard copy w/ execCommand fallback, live preview iframe). Biolink gets a dedicated 'embed' settings sub-tab (`settingsEmbed()` in `BiolinkBlockController`, route `user.links.settings.embed`, AJAX-swapped into `#settings-tab-content`). Non-biolink edit screens (edit.blade.php, edit-vcf, edit-ics) include the panel after `</form>`. Snippet strings come from `Link::embedScriptSnippet()` / `embedIframeSnippet()`, based on `embedBaseUrl()` (config `app.url`). Surfaced in `public/demos.blade.php`.
