---
name: Moving public/js helper scripts into the Vite build
description: How to safely convert 1inme's asset('js/...') classic scripts to @vite bundling without breaking runtime timing
---

Moving a `public/js/*.js` helper script into `resources/js/` + the Vite `input`
array (loaded via `@vite([...])`) content-hashes it and makes a missing/renamed
file FAIL the build instead of a silent 404 + degraded page. That is the goal.

**The one non-obvious trap: `@vite` emits `<script type="module">`, which is
DEFERRED.** A classic `<script src=asset(...)>` (no defer) runs immediately
during parse. So when a page has an INLINE `<script>` that calls a global the
helper defines (e.g. `AnalyticsCharts.createTrendChart(...)` right after loading
`analytics-charts.js`), converting the helper to `@vite` makes the inline script
run BEFORE the module → `X is undefined`. Fix: wrap the inline consumer in
`document.addEventListener('DOMContentLoaded', ...)` (deferred modules always
finish before DOMContentLoaded fires).

Helpers that only self-run or only assign `window.X` (Alpine `x-data` factories
like `mapPinPicker`, self-executing IIFEs like `marketing-anim`/`community-public`)
convert cleanly — Alpine auto-starts on DOMContentLoaded, after the module ran.

**Left on `asset('js/...')` on purpose (documented in vite.config.js):**
- `public/js/vendor/*` (alpine, alpine-collapse, chart.umd, jsqr, leaflet):
  third-party, self-hosted+pinned (no CDN SPOF/SRI drift, see cdn-alpine-spof.md);
  Alpine must stay a classic global `<script defer>`, not an ES module.
- `public/js/qr-studio/engine.js`: large standalone public QR engine, one stable
  globally-cached URL across many embedding pages.
- `public/js/social-proof-widget.js`: embeddable widget for third-party sites,
  needs a stable non-hashed URL.

**Stack caveat:** `common/biolink.blade.php` is a standalone full HTML doc with
NO `@stack('scripts')`; only site/user/admin layouts flush that stack. A
`@push('scripts')` from an included partial (e.g. biolink-block-render) is
silently discarded there — so community-public only actually loads via the
user-app-layout path (editor preview). Converting inside the existing
`@push('scripts')` is behavior-preserving.
