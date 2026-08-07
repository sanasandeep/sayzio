---
name: Admin sidebar mirrors user layout v3
description: Collapsed-sidebar CSS pitfalls shared between admin and user layouts, and where the theme-toggle button style lives.
---

# Admin sidebar mirrors user layout v3

The admin layout's sidebar CSS is a copy of the user layout's "v3" sidebar. When a
collapsed-mode bug is fixed in one, check the other — they drift.

**Known pitfalls (fixed once in each layout already):**
- `.sidebar-v2.collapsed nav > *` MUST be `flex-direction: column` — a plain
  `display:flex` row lays every link side-by-side inside the 72px rail, clipping all
  but the first icons (looked like "menu items missing").
- Do NOT put `overflow:hidden` on the `.sidebar-v2` shell — it clips the outer half of
  the `.sidebar-edge-toggle` handle and hover tooltips. The inner nav scroller does the
  clipping; label bleed in invalid-mode states is prevented by sanitizing the
  localStorage sidebar-mode value instead.
- Collapsed-mode `.sidebar-tooltip`s are clipped by the nav scroller's
  `overflow-x-hidden`; the admin sidebar re-anchors them `position:fixed` on hover via a
  tiny inline script (fixed coords work because the aside is a transformed,
  viewport-origin fixed element). They are `pointer-events:none`, so e2e must assert
  computed `position`/geometry, not `elementFromPoint`.

**Theme toggle:** `.header-icon-btn` canonical definition lives in
`common/partials/theme-styles.blade.php` (so admin header + auth pages get a proper
square button); the user layout and admin layout override size/radius locally.

**E2e:** admin dashboard route is `/admin` (route name `admin.dashboard`), NOT
`/admin/dashboard` (404s into the alias catch-all).
