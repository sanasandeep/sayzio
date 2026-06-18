---
name: Biolink button-style layouts (link_layout)
description: How to add new biolink link-family button styles (icon/image placement) end-to-end without them silently breaking.
---

Adding a new biolink button *style* (icon/image placement, badge shapes) is a
four-surface change that must move in lockstep. Visual treatments
(gradient/outline/transparent/dotted) come "for free" from the existing `_style`
payload (gradient `bg_color`, `border_*`, `effect`) — only icon/image
**placement** needs new code.

**The four surfaces:**
1. Renderer branch: `common/blocks/link.blade.php` — add an `@elseif` for the new
   `link_layout` token. Keep `bio-btn` + `$btnInline`. Derive any badge accent as
   `border_color → text_color → #7c3aed`; always graceful-fallback when icon/thumb missing.
2. Sanitizer allowlist: `BiolinkBlockController::sanitizeBlockStyle()` — the
   `link_layout` enum. **Why:** any value not in this allowlist is silently
   stripped on save, so the layout persists in the gallery click but never renders.
3. Catalog: `BlockVariantCatalog.php` — add the variant to a bundle, wire the
   bundle into `forType` for the link family, and bump `VERSION` (drives variant
   drift migration). The "Designs" gallery renders REAL live previews, so new
   layouts appear correctly with no preview-specific code.
4. Mobile mirror: `1inme-mobile/lib/blockVariants.ts` — metadata-only mirror
   (key/name/tags/preview). Variant **keys must stay in sync** with the PHP
   catalog for gallery selected-state parity. The mobile renderer does NOT honor
   `link_layout` placement — unknown values degrade to the variant's colors (no break).

**How to apply:** when a task says "add button styles / block styles" to biolinks,
touch all four. Forgetting #2 is the classic silent failure (saves, never renders).
