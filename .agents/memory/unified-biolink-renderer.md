---
name: Unified biolink block renderer
description: How public biolink blocks are dispatched after the two render paths were merged.
---
Public biolink pages render every block through ONE partial: `common/partials/biolink-block-render.blade.php`. Both top-level blocks (included from `common/biolink.blade.php`) and card/grid children (recursive `@include` in the container branch) go through it.

Registration is single-source:
- Types with a dedicated partial in `common/blocks/` are listed in `$__blockPartials` → rendered via that partial. Add a type here once and it renders everywhere.
- Types without a dedicated partial yet live in the inline fallback chain inside the same file, guarded by an `@if(false)` anchor so every real branch stays an `@elseif`.
- Trailing `@else` keeps the legacy poll/comments hook + an unknown-type cube placeholder.

**Why:** previously top-level used a huge inline `@if/@elseif` chain in `biolink.blade.php` while card children used the dispatch table, so a new type had to be wired into BOTH or rendered inconsistently (e.g. blank at top level).
**How to apply:** to add/change a block's public rendering, edit only the partial. The partial relies on parent-scope vars ($link, $fontColor, $globalTheme, $socialIcons) shared via @include — don't expect them as explicit args.

## Slider public HTML embeds URLs JSON-escaped
image_slider/image_slider_v2 render images via json_encode inside an Alpine x-data attr, so picked URLs appear as https:\\/\\/... in the public HTML — e2e string asserts must accept the escaped form.
