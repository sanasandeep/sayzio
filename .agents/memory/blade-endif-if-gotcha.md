---
name: Blade @endif@if same-line gotcha
description: Two Blade directives concatenated with no separator (e.g. @endif@if) — the second silently fails to compile and produces an orphan endif PHP parse error.
---

Blade's directive regex requires a non-word-boundary (`\B`) immediately before `@`. When a directive follows another directive that ends in a word char with NO separator — classically `@endif@if(...)` — the boundary between `f` and `@` IS a word boundary, so `\B` fails and the SECOND directive (`@if`) is left as literal text while its matching `@endif` still compiles to `<?php endif; ?>`. Result: literal `@if(...)` in the HTML + an orphan `<?php endif; ?>` → "unexpected token endif" PHP parse error → 500 when that branch renders.

**Why:** Discovered a pre-existing instance in `artifacts/1inme/resources/views/common/biolink.blade.php` (reviews_wall block, `@endif@if(!empty($rev['is_pinned']))`) while linting compiled views for an unrelated change. `{{ ... }}@if(...)` is fine (`}}` ends in non-word char), only directive-directly-after-directive breaks.

**How to apply:** When `php -l` on a compiled Blade view reports "unexpected endif", grep the source for `@endif@`/`@endforeach@`/etc. with no space. Fix = insert a space or newline between the two directives. Also: to lint ONE view region past an unrelated pre-existing error, read the source, `compileString()` it with the offending concatenation neutralized in-memory, and `php -l` the result — don't edit the real file just to test.
