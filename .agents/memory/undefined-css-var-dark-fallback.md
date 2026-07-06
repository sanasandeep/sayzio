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

## Sweep results & scope discipline (the follow-up)

The sweep found a whole second family of undefined aliases used across inner
app pages — `--surface`, `--surface-1`, `--surface-2`, `--surface-glass`,
`--border`, `--text`, `--bg-input`, `--bg-card-alt`, `--sidebar-bg`,
`--color-primary-soft` (api-keys, delivery-projects, contacts, resume, dialer,
settings layout, leads, slides/creator-profile editors). Fixed by adding them
as `var()` aliases of existing theme tokens in the `:root` block of
`theme-styles.blade.php`, right beside the pre-existing "legacy soft aliases"
(`--bg-soft`/`--surface-soft`/…). **Alias-once trick:** you only declare them
in `:root` as e.g. `--surface: var(--bg-glass)` — no `html.light-mode` copy
needed, because `--bg-glass` is itself redefined under `html.light-mode` and
custom-property substitution resolves lazily at use-time on each element.

**Scope gotcha — three separate theming systems.** `theme-styles.blade.php`
is loaded ONLY by the user/admin app layouts + their auth pages. It does NOT
govern (a) the marketing site (`public.layouts.site`, its own light-mode via
`marketing-anim.css`) or (b) public biolink pages (user-themed, no app
light/dark toggle). So undefined-var hits like `--page-bg` (marketing pricing
rail) and `--card-bg`/`--card-fg`/`--bg-color` (community blocks,
verified-avatar on biolinks) are in DIFFERENT systems — leave them out of a
theme-styles fix. Also skip theme-neutral accent fallbacks (`--sa-accent`,
`--cc-accent`, `--danger`, `--accent-danger` → blue/red that read fine on both
themes); "undefined var" alone isn't a bug unless the frozen literal is
wrong-theme for a bg/text/border surface.

**Biolink-system fix pattern (the community-block variant).** The biolink
theming system exposes NO CSS custom properties — the public page
(`common/biolink.blade.php`) resolves theme colors into PHP vars that are in
scope for every block partial rendered through `biolink-block-render`:
`$fontColor` (theme text hex, `#ffffff` or `#212529`), `$btnColor` (accent),
`$bgColor` (page background). So the fix for undefined `var(--card-fg/--card-bg/
--card-border/--accent/--bg-color, ...)` in `partials/community/*` +
`common/blocks/verified-avatar.blade.php` is NOT a `:root` alias — it's to echo
those PHP vars inline, deriving translucent surfaces by hex-alpha concat
(`{{ $fontColor }}0d` = ~5% tint, `1a` = ~10%, `33` = ~20%), exactly as the
rest of `biolink.blade.php` already does (`{{ $fontColor }}cc` etc.). Set
`color: {{ $fontColor }}` on each block's ROOT container so AJAX-injected rows
(`public/js/community-public.js`, no explicit color) inherit a legible color.

**Grep caveat:** a var appearing "defined" globally can still be undefined in
the app scope — `--border`/`--text` are declared only in standalone
`common/embed/card.blade.php` + `common/splash.blade.php` (own `:root`), so
they were genuinely undefined on app pages despite showing up in a naive
codebase-wide definitions grep. Cross-check definitions per rendering scope,
not codebase-wide.

## Now enforced by a CI guard

`scripts/src/check-undefined-css-var-fallback.ts` (npm `check:undefined-css-var`,
registered validation `undefined-css-var`) makes this invariant enforceable: it
scans every non-vendor blade under `artifacts/1inme/resources/views`, finds each
`var(--name, <#hex|rgb|rgba|hsl literal>)` reference, and per RENDER SCOPE
(app / standalone / excluded — mirrors the three-theming-systems split above)
resolves whether `--name` is declared (theme-styles for the app shell, the
page's own `:root` for standalone, an inline `style="--x:"` component var, or
the intentional neutral-accent allowlist). **When it fails, DON'T weaken the
parser** — either declare the token in the right scope (the alias-once trick),
or, if the frozen literal is genuinely theme-neutral (blue/red accent, low-alpha
tint over a theme-flipping bg) or an intentional single-theme standalone page,
add it to `NEUTRAL_ACCENT_VARS` / `FILE_ALLOWLIST` WITH A REASON. Component-var
families (`--tile-*`, `--sc-*`, `--rbp-c`, …) are auto-recognised only if some
instance sets them inline; a member that no instance overrides (e.g.
`--tile-bg-from`/`--tile-border`) falls through to the neutral-accent allowlist.
