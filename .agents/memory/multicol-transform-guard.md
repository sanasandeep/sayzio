---
name: Multicol-item transform guard
description: Why transforming a CSS multi-column item vanishes it, and how the guard decides what to flag vs exempt.
---

# Multicol-item transform guard (vanishing showcase card)

**Rule:** never apply `transform` (non-`none`) or `will-change: transform` to a direct child of a CSS `columns-*` container — a browser rendering bug blanks the hovered/animated fragment. Put all motion on an inner wrapper (e.g. `.showcase-inner`).

**Why:** the home-page "What you can create" showcase cards vanished on hover because the hover transform sat on the multicol item itself. Guard: `scripts/src/check-multicol-transform.ts` (`check:multicol-transform`, registered validation `multicol-transform`).

**How to apply / guard semantics:**
- Multicol item classes are derived from the MARKUP (direct children of any `columns-*` element), so renaming the card class won't dodge the guard.
- Exempt: `transform: none`, the `@media (prefers-reduced-motion: reduce)` kill-switch, pseudo-element subjects (`::before/::after`), descendants of the card, and `transition: transform`.
- Keyframe animations that transform are flagged only when referenced from a subject carrying a class EXCLUSIVE to multicol children (e.g. `.showcase-card`). Shared utilities like `.reveal` legitimately animate transforms elsewhere — flagging them would be a permanent false positive. Known residual: `.reveal`'s `revealAuto` keyframes still transform the cards for ~0.8s at load (see follow-up).
- Extending coverage = add files to `SCAN_FILES`; the scanner is file-generic.
