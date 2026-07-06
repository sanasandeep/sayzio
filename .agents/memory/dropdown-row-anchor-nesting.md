---
name: Dropdown rows as anchors can't reuse a richer per-item partial
description: When each row of a header/nav dropdown list is itself an <a> (click-row-to-open pattern), you can't reuse a full-page list partial whose per-item markup embeds its own action links/buttons.
---

When converting a "view full list" page into a compact header dropdown (e.g. wallet dropdown, notifications dropdown) where clicking anywhere on a row navigates/marks-read/opens something, each row must render as a single `<a>` (or a single click handler). The full index/list page's per-item partial often contains its own nested links, buttons, or rich HTML (action buttons, "quoted" sub-links, multiple CTAs) — that markup cannot be reused verbatim inside the dropdown row, because you cannot nest `<a>`/interactive elements inside another `<a>` without breaking click semantics and HTML validity.

**How to apply:** for the dropdown, extract only the *icon* into a shared partial (safe to reuse — no nested interactivity), and add a small "flat single-line preview" method/helper (plain text/string, no HTML, no nested links) specifically for the dropdown row's body text. Keep the full page's richer per-item template untouched. This came up doing 1inme's header notifications dropdown (mirroring the existing wallet dropdown): `UserNotification::previewText()` was added as a link-free summary, separate from the index page's per-type rich markup.
