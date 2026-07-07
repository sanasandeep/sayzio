---
name: Brand-color regression guard
description: The validation step that blocks the retired purple palette from re-entering primary UI surfaces, and why some surfaces are excluded.
---

# Brand-color guard

Validation step `brand-color` (script `scripts/src/check-brand-color.ts`) fails
CI if the RETIRED purple ramp (the three retired hexes + their rgb forms +
`violet-`/`purple-<shade>` Tailwind classes) appears in a PRIMARY UI surface of
the product artifacts. The live brand accent is BLUE (`--color-primary-*`).
The script itself is the source of truth for the exact patterns, scanned roots,
and the documented allowlist (run it with `-- --explain`).

**Why some surfaces are excluded (intentional, not a miss):** purple is a
legitimate member of MULTI-COLOR categorical palettes — block-style pickers,
template galleries — and of palette/seed DATA, not the brand accent.
Re-coloring those would break the intended rainbow.

**Post-build complement:** the source-level guard cannot catch purple that
lands in a COMPILED stylesheet from content the source scan doesn't cover —
all the built CSS dirs are gitignored. Validation step `brand-color-build`
(`scripts/src/check-brand-color-build.ts`) closes that gap:
- `1inme` (Laravel): `view:clear` then rebuild, scan `public/build/assets/*.css`.
  The stale compiled-blade cache regenerates pre-sweep tokens (see
  `tailwind-scans-compiled-views.md`). Uses the `\b`-anchored hex pattern
  (unchanged — upgrading it would newly flag the biolink categorical palette's
  alpha purple in compiled CSS).

(The `1inme-deck` slides artifact and its decorative-palette attribution logic
were removed in July 2026 along with the artifact.)

Only HEX (6- and 8-digit) + rgb() survive compilation; Tailwind v4 compiles
`violet-`/`purple-<shade>` UTILITY classes to oklch theme vars (never hex), so
the source-level guard owns those.

**Pure matcher + tests:** the source guard's `main()` now lists files (rg
`--files`) and runs the exported pure `scanSource(relFile, src)` on each (mirrors
`check-ai-tool-names.ts`), instead of parsing a ripgrep `--vimgrep` dump.
`scanSource` = ALLOWLIST short-circuit → `matchLines` over `BANNED_REGEXES`.
Comments are BLANKED before matching (`blankComments`: block / blade / HTML /
`//`) — retired purple inside a comment does not render, so it is intentionally
NOT flagged. Regression tests live in `check-brand-color.test.ts` (picked up by
the `scripts-test` gate's `*.test.ts` glob; no `.replit` change needed).

**How to apply:**
- If a NEW intentional categorical surface trips the guard, add it to the
  script's ALLOWLIST with a reason — don't widen the banned regex or recolor a
  deliberate palette. Everything not allow-listed must use brand blue.
- Don't trust a per-line grep when sweeping a file: inline `style="..."` hexes
  hide on lines separate from the Tailwind class hits. Run the full guard.
- spawnSync needs a large `maxBuffer` or big scan roots can overflow the
  default 1MB buffer (ENOBUFS).
- If `brand-color-build` fails but `brand-color` passes, the offender is a stale
  compiled-blade cache leaking pre-sweep tokens — `view:clear` then rebuild.
