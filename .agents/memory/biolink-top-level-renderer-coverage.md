---
name: Biolink top-level renderer coverage
description: How public biolink top-level blocks render, why types went blank, and what templates:check-designs now enforces.
---

# Top-level public biolink renderer reachability

Public biolink pages render TOP-LEVEL blocks via a long inline `@if/@elseif`
chain in `common/biolink.blade.php` (inside the `@forelse($blocks ...)`). A type
with no branch there used to fall through to a BLANK wrapper with no error — the
exact bug class that shipped `buy_me_coffee` blank.

The partial dispatch table in `common/partials/biolink-block-render.blade.php`
(`$__blockPartials`) is the canonical per-block renderer, BUT it is only reachable
at top level if the inline chain delegates to it. The card/grid CHILD loop always
delegated (`['block' => $childBlock, ...]`); the top-level chain did NOT until a
catch-all `@else` was added that delegates the top-level `$block`
(`['block' => $block, ...]`). So a partial-table entry alone does NOT mean a type
renders at top level — it needs either an inline branch or that fallthrough.

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
