---
name: claude-training ⇄ api.md cross-link guard
description: How the §12 anchor-drift guard behaves and why coverage warnings can be silent
---

# claude-training ⇄ api.md cross-link drift guard

`scripts/src/check-claude-training-api-index.ts` fails CI when a
`](./api.md#anchor)` cross-link in `artifacts/1inme/docs/claude-training.md`
(§12) points at an api.md heading slug that no longer exists.

**Slug algorithm** = GitHub / `github-slugger`: lower-case, strip everything
except `\p{L}\p{N}\p{M}\p{Pc}`/`-`/space, spaces→hyphens, dedupe repeats with
`-1/-2`. Must skip fenced code blocks — api.md's curl examples contain
`# Register`-style shell comments that are NOT headings on GitHub.

**Why coverage can look silent:** the "api.md `##` group with no cross-link"
pass is a *warning only* and is fully suppressed while the §12 index has ZERO
`api.md#` cross-links (nothing to be inconsistent with yet). The §12 route→notes
cross-link index was authored in a separate task and may not be merged into a
given isolated env — so `✓ ... all 0 api.md cross-link(s) resolve` is EXPECTED,
not a broken guard. Use `--strict` to promote coverage gaps to failures once the
index is complete.

**CI flake note:** under the full parallel validation suite this guard (and
sibling tsx-based checks like `alpine-line-comments`) can fail with
`Cannot find module '/tsx/dist/cli.mjs'` / `Cannot fork` / `spawn sh EAGAIN` —
these are resource-exhaustion flakes from ~55 workflows forking at once, NOT the
guard itself. It passes in isolation.
