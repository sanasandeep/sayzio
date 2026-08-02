---
name: Public event page Bootstrap fallbacks
description: Shared event partials are Bootstrap-styled but the public event page is Tailwind-only; scoped .ev-rich utility fallbacks are required.
---
The shared partials `common/partials/event-rich-content.blade.php` and `event-page-recommendations.blade.php` are written with Bootstrap utility classes (`row g-3`, `ratio ratio-16x9`, `d-flex`, `badge`, `btn`, `w-100`…) because the RSVP page loads real Bootstrap. The public event page (`common/event-page.blade.php`) uses the Tailwind marketing layout where those classes don't exist — without fallbacks the recommendation cards collapse (images at natural size, no grid, unstyled Interested buttons).

**Rule:** any Bootstrap class a shared event partial uses must have a scoped `.ev-rich` fallback in event-page.blade.php's `@push('head')` style block. Never restyle the partials themselves (they must stay correct on the Bootstrap RSVP page).

**Gotchas:**
- `.ev-rich .h-100 { height:auto !important }` exists to un-stretch cards; media that needs full-fill (ratio children, 72px list thumbs) needs its own explicit width/height counter-rules.
- Keep the `.ratio > *` fill rule limited to img/video/iframe — the price badge is also a direct child of `.ratio` and must keep its corner positioning.
