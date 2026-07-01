---
name: Legacy "soft" design tokens in 1inme dashboard
description: Why older inner pages use --border-soft/--bg-soft/--surface-soft/--accent-soft and how they resolve
---

# Legacy "soft" tokens (1inme Laravel dashboard)

Many older signed-in inner pages (analytics/visitors, billing, security, tasks, …)
style cards with `var(--border-soft)`, `var(--bg-soft)`, `var(--surface-soft)`,
`var(--accent-soft)`. These are NOT the canonical tokens — the canonical dashboard
tokens are `--border-glass` / `--bg-glass` / `--c-primary-soft`
(`common/partials/theme-styles.blade.php`).

The soft tokens are now defined ONCE, as aliases, in the `:root` (dark default)
block of theme-styles:
`--bg-soft: var(--bg-glass); --surface-soft: var(--bg-glass); --border-soft: var(--border-glass); --accent-soft: var(--c-primary-soft);`
A single alias in `:root` is enough — it references vars that `html.light-mode`
re-defines, so the alias auto-flips per mode (var() resolves at computed-value time
against the same element's cascaded value). No light-mode duplicate needed.

**Why this matters:** before the alias, these vars were undefined globally. An
undefined CSS var with no fallback makes the whole declaration
"guaranteed-invalid", so `border-color: var(--border-soft)` fell back to
`currentColor` (near-black text color → wrong dark borders) and
`background: var(--bg-soft)` fell back to transparent. It reads like a per-page
dark-border bug but is really a missing token.

**How to apply:** prefer the canonical glass tokens on new work. If you touch a
page that already uses a `*-soft` token, it's fine — it's aliased. Never reference
a NEW undefined design token without a fallback or it silently falls back to
currentColor/transparent. `feed/index.blade.php` deliberately re-defines
`--border-soft`/`--bg-card` in its own scoped `:root` (paper-white feed); that
local override still wins on that page.
