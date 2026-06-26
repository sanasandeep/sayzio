---
name: Brand-color regression guard
description: The validation step that blocks the retired purple palette from re-entering primary UI surfaces, and why some surfaces are excluded.
---

# Brand-color guard

Validation step `brand-color` (script `scripts/src/check-brand-color.ts`) fails
CI if the RETIRED purple ramp (the three retired hexes + their rgb forms +
`violet-`/`purple-<shade>` Tailwind classes) appears in a PRIMARY UI surface of
the four product artifacts. The live brand accent is BLUE (`--color-primary-*`).
The script itself is the source of truth for the exact patterns, scanned roots,
and the documented allowlist (run it with `-- --explain`).

**Why some surfaces are excluded (intentional, not a miss):** purple is a
legitimate member of MULTI-COLOR categorical palettes — block-style pickers,
template galleries, decorative pitch-deck slides — and of palette/seed DATA, not
the brand accent. Re-coloring those would break the intended rainbow.

**Post-build complement:** the source-level guard cannot catch purple that
lands in a COMPILED stylesheet from content the source scan doesn't cover —
all the built CSS dirs are gitignored. Validation step `brand-color-build`
(`scripts/src/check-brand-color-build.ts`) closes that gap for THREE artifacts:
- `1inme` (Laravel): `view:clear` then rebuild, scan `public/build/assets/*.css`.
  The stale compiled-blade cache regenerates pre-sweep tokens (see
  `tailwind-scans-compiled-views.md`). Uses the `\b`-anchored hex pattern
  (unchanged — upgrading it would newly flag the biolink categorical palette's
  alpha purple in compiled CSS).
- `1inme-com` / `1inme-deck` (Vite): build (needs PORT/BASE_PATH env), scan
  `dist/public/assets/*.css`. These use an ALPHA-AWARE hex pattern
  (`hexPatternWithAlpha`) because Tailwind v4 normalizes arbitrary
  `rgba(124,58,237,.18)` into 8-digit `#7c3aedXX`, which the `\b` pattern misses.

Only HEX (6- and 8-digit) + rgb() survive compilation; Tailwind v4 compiles
`violet-`/`purple-<shade>` UTILITY classes to oklch theme vars (never hex), so
the source-level guard owns those.

**Deck decorative palette honoring:** the deck's `src/pages/**` slides
intentionally use the `7c3aed` wash (source guard allow-lists that dir). The
build guard mirrors it by ATTRIBUTION: a retired color in the deck's compiled
CSS is allowed iff that color is also used in `src/pages/**` source
(`RETIRED_COLORS` + per-color grep). Marketing has no decorative dir, so ANY
retired purple there fails. Gap: if chrome reuses a color the slides also use,
the build guard allows it — but the source guard catches chrome regressions.

**How to apply:**
- If a NEW intentional categorical surface trips the guard, add it to the
  script's ALLOWLIST with a reason — don't widen the banned regex or recolor a
  deliberate palette. Everything not allow-listed must use brand blue.
- Don't trust a per-line grep when sweeping a file: inline `style="..."` hexes
  hide on lines separate from the Tailwind class hits. Run the full guard.
- spawnSync needs a large `maxBuffer` + the deck `pages/**` rg exclude, or the
  deck's ~180 decorative slides overflow the default 1MB buffer (ENOBUFS).
- If `brand-color-build` fails but `brand-color` passes, the offender is a stale
  compiled-blade cache leaking pre-sweep tokens — `view:clear` then rebuild.
