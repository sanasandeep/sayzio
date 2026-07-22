---
name: Blade compile crash classes (pre-launch sweep)
description: The recurring Blade source patterns that compile to invalid PHP and 500 a page at render; how to spot and fix each robustly.
---

Several Blade source patterns compile to broken PHP (page 500s on render) yet pass a casual eye. They are NOT all PCRE-limit related — confirmed by raising `pcre.backtrack_limit`/`pcre.recursion_limit` (no effect). Fix the source, never bump ini.

**Crash classes & robust fixes:**
- `@php($x = ...)` parenthesized short-form silently drops its expression in full-file context (compiles to bare `<?php` + literal `($x=...)`), even though the same line compiles fine in isolation. → Use block form `@php $x = ...; @endphp` (raw-extracted, robust).
- `@json($collection->map(fn(...) => [ 'a'=>, 'b'=>, 'c'=>, 'd'=>... ]))` with nested array/closure literals **truncates** the directive arg (balanced-paren regex bails) — reproduces in isolation. → Move the expression into a `@php ... @endphp` block var, then `@json($var)`.
- Literal `{{ '{{token}}' }}` — the inner `}}` closes the echo early → unbalanced parens downstream. → Use `@{{token}}` (Blade emits literal `{{token}}`). Grep the whole file; there is usually more than one.
- Attached directive `word@if(...)...@endif` — `@if` glued to a preceding word fails the `\B` boundary, orphaning `@endif`. → Replace the whole optional fragment with an inline expression: `{{ $cond ? ' ...' : '' }}`, or insert a `{{ '' }}` spacer right before the glued directive (keeps rendered text byte-identical). See also blade-endif-if-gotcha.md. This class caused the July 2026 prod-wide 500 on the notifications page (`you@endif`); when only ONE side of the pair is glued you get a compile error, when BOTH are glued the page compiles but shows literal `@if(...)` text to users. Sweep fast with: `grep -rEn "[A-Za-z0-9_]@(if|endif|endforeach|else|elseif|endunless)" resources/views`.
- Missing `@endif` (count `@if` vs `@endif`) — an opened conditional + its `<div>`s never closed; the next section nests inside until PHP parse error. → Find the unclosed block and add the closers in lockstep with the open tags.
- `{{ $var }}` written as illustrative prose inside a plain `/* ... */` CSS comment or `<!-- ... -->` HTML comment (e.g. explaining "this partial bakes colors via inline style=\"…{{ $var }}…\"") — Blade doesn't know it's a comment unless it's `{{-- --}}`; it compiles to a live echo of an undefined variable → `ErrorException: Undefined variable $var`, 500s the whole page even though the page LOOKS fine and other content renders. Reword to avoid `{{ }}` syntax entirely, or use `{{-- --}}` / `@{{ }}` if the literal must appear.

**How to find them fast:** the real gate `BladeViewsCompileTest` buffers all output and exceeds the 120s tool cap. Instead drive a one-off PHP script that bootstraps the app, `Blade::compileString(file_get_contents($view))` for every `*.blade.php`, and `php -l` the compiled output — reports every broken view in one pass (~80–110s for ~730 views). One broken view can mask a second error in the same file (php -l stops at the first), so re-run until clean.

**Why:** these are launch-blocking 500s on real admin/user/public routes; static compile-lint catches the whole class without needing a DB or auth context, which the isolated env lacks.

## Blade {{-- --}} comment inside an @php block
A `{{--  --}}` comment placed inside an `@php ... @endphp` block (e.g. inside a PHP array literal) is NOT reliably stripped before PHP compilation and can produce "unexpected token \"{\"" parse errors in the compiled view. Use a plain PHP `//` comment inside @php blocks.
