---
name: Blade comment echo guard
description: Why Blade echoes inside plain comments crash pages, and how the guard avoids false positives.
---

# Blade `{{ }}` inside plain comments still compiles

Blade only treats `{{-- --}}` as a comment. A plain HTML `<!-- -->` or C-style
`/* */` comment is transparent to the compiler: any `{{ $var }}` / `{!! $x !!}`
inside it still compiles to a live PHP echo. An undefined variable there throws
`ErrorException` and 500s the whole page. Fix an offender by switching to a Blade
comment `{{-- ... --}}` or escaping with `@{{ ... }}`.

Guard: `scripts/src/check-blade-comment-echo.ts`
(`pnpm --filter @workspace/scripts run check:blade-comment-echo`), wired as the
`blade-comment-echo` validation.

## Precision gotcha (took 2 iterations)
**Why:** A naive `/\*[\s\S]*?\*\//` comment-span regex produces massive false
positives, because `/*` and `*/` appear OUTSIDE real comments all over blade:
- `accept="image/*"` in an HTML attribute
- `'/*'`, `'*/*'` (wildcard mime) inside JS/CSS strings

Each stray `/*` opens a fake comment that swallows every real echo up to the next
`*/`.

**How to apply:** Detect the two comment kinds separately, never with one greedy
regex.
- `<!-- -->`: scan the whole source (an HTML comment can wrap a `<script>` whose
  echoes still compile); blank them before the `/* */` pass so nothing double-counts.
- `/* */`: only inside `<style>`/`<script>` blocks, via a string-aware char
  walker (`findBlockComments`) that skips `'`, `"`, `` ` `` literals — so `/*`
  inside a string is not a comment opener.
- Blank `{{-- --}}` and `@verbatim ... @endverbatim` first (echoes there don't
  compile), preserving newlines so reported line/col stay accurate.
