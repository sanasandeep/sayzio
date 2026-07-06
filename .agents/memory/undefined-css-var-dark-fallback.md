---
name: Undefined CSS variable with dark fallback
description: A theme-aware var() call whose variable is never actually defined anywhere silently pins a component to its dark literal fallback forever, even though the surrounding text uses properly-themed vars — producing dark-on-dark in light mode.
---

## The bug shape

`background: var(--bg-glass-card, #14162a)` looks theme-aware because it's a
CSS variable with a fallback, but if `--bg-glass-card` is never defined in
any `:root`/`html.light-mode` block, the fallback `#14162a` (dark) always
wins in every theme. Meanwhile sibling elements using genuinely-defined vars
like `--text-primary` correctly flip to dark values in light mode — so the
component ends up with dark text on a (still-dark) card, i.e. it *looks*
themed but never actually re-themes.

**Why:** Someone likely renamed or removed a variable definition elsewhere,
or copy-pasted a `var(--x, fallback)` pattern without ever adding `--x` to
the theme root blocks. `grep`-ing for the variable *name* in isolation won't
catch this — you must confirm it's actually *defined* somewhere, not just
*referenced*.

**How to apply:** When investigating a light/dark-mode legibility bug on a
modal/card/backdrop, don't assume `var(--something, #hex)` is theme-safe just
because it has the `var()` wrapper — grep the CSS variable name against the
`:root` / `html.light-mode` definition blocks in
`theme-styles.blade.php` and confirm it's actually declared in both. If it
isn't, the fix is either (a) wire up the missing variable definition, or (b)
replace it with a purpose-built class using an inline `<style>` block (dark
literal as base rule + `html.light-mode <class>` override), mirroring the
existing `.gsm-backdrop`/`.gsm-panel` pattern — see
`blade-push-stack-order.md` for why `@push('styles')` won't work in a
mid-body `@include`d partial.

A dedicated task-follow-up sweep (grep `var(--*, #` / `var(--*, rgb` across
`resources/views`, cross-check each var name against real definitions) is a
cheap way to find other instances of this class of bug across the codebase.
