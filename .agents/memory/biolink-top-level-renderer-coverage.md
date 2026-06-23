---
name: Biolink top-level renderer coverage
description: How public biolink top-level blocks render, why types went blank, and what templates:check-designs now enforces.
---

# Top-level public biolink renderer reachability

After the rendering-unification commit, `common/biolink.blade.php` no longer holds
a per-type inline `@if/@elseif` chain: inside `@forelse($blocks ...)` it delegates
EVERY top-level block to the single dispatch partial via
`@include('common.partials.biolink-block-render', ['block' => $block, ...])`. The
per-type `@if/@elseif` chain (48+ branches, `in_array` groups,
`str_starts_with($block->type,'profile_card')`, `isContainerType` container branch)
plus the `$__blockPartials` map now ALL live in the partial — it is the single
source of truth, reached identically by top-level blocks and by card/grid children
(recursive `@include`). A type with no partial branch renders a BLANK wrapper with
no error — the bug class that shipped `buy_me_coffee` blank.

**Coverage checkers must read the partial, not the (now-empty) inline chain.** Two
places parse coverage and both had to learn this when the chain moved:
`CheckTemplateDesigns::checkTopLevelRenderers` and
`BlockRenderCoverage` (`topLevelCoverage`/`childCoverage`/`partialCoverage`). They
detect the top-level fallthrough (`'block' => $block`, `\b` excludes `$childBlock`)
and, when present, credit the partial's full coverage = `$__blockPartials` keys
PLUS the partial's own inline `@if/@elseif` branches. Forgetting to parse the
partial's inline branches (crediting only `$__blockPartials`) makes
`templates:check-designs` falsely fail for inline-only types (soundcloud, vcard,
resume, etc.).

**Why:** A type can be a known `BiolinkBlock::TYPES` entry, pass the variant-key
and known-type checks, AND have a partial — yet still render blank because the
top-level chain never reaches its partial. ~21 first-class types were in this
state simultaneously (ai_companion, menu, stats, tabs, accordion, etc.).

**How to apply:**
- The catch-all `@else` at the end of the inline chain is the safety net — keep it.
  It delegates unmatched top-level blocks to the partial dispatch table (proper
  partial, or a visible unknown-block card), so nothing renders blank.
- `templates:check-designs` (`CheckTemplateDesigns::checkTopLevelRenderers`)
  statically asserts every `TYPES` entry is reachable: inline `@if/@elseif`
  branch in `biolink.blade.php` (incl. `in_array`, `str_starts_with` prefixes like
  `profile_card`, and `isContainerType` → `CONTAINER_TYPES`) OR a partial-table
  entry — the latter credited ONLY when it detects the top-level fallthrough
  (`'block' => $block`, `\b` excludes `$childBlock`). It is DB-free, parses only
  `@if/@elseif` lines so `@php` helper arrays (skipWrap/btnLike) don't false-pass.
- Registered as the `template-designs` validation workflow.
- Adding a new first-class block type: give it an inline branch OR a partial-table
  entry, or the check fails non-zero.
