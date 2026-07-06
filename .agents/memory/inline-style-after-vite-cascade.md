---
name: Inline <style> after @vite overrides Tailwind responsive utilities
description: A plain CSS rule in a Blade layout's inline <style> block (loaded after @vite) can silently defeat Tailwind's hidden/lg:hidden/sm:flex classes on the same element.
---

A base CSS rule like `.foo { display: flex; }` defined in an inline `<style>`
block that appears in the HTML *after* `@vite(['resources/css/app.css', ...])`
has the same specificity as Tailwind's `.hidden`/`.lg\:hidden`/`.sm\:flex`
utilities. On a specificity tie, later source order wins — so the plain,
unconditional rule silently overrides the responsive utility class on any
element using both, making "hide on desktop" / "hide below lg" classes
appear broken even though they're correctly applied in markup.

**Why:** Found in `artifacts/1inme/resources/views/user/layouts/app.blade.php`
— `.header-icon-btn { display: flex; }` (no media query) was defined after
`@vite`, so it overrode `lg:hidden` on the mobile hamburger button, keeping
it visible on desktop despite the correct Tailwind class being present.

**How to apply:** When a "hide at breakpoint X" Tailwind class appears to have
no effect on an element, check whether a shared component class (e.g. a
`.btn`/`.icon-btn`-style helper in an inline `<style>` block) sets `display`
unconditionally. Fix by removing the unconditional `display` from the base
rule and adding the needed `flex`/`hidden lg:flex`/etc. utility classes
explicitly to every usage, so visibility is controlled purely by Tailwind's
responsive classes, not fought over by a same-specificity plain rule.
