---
name: Blade inline-script JS parse guard
description: How the check-blade-inline-scripts guard neutralizes Blade and its false-positive traps
---

`artifacts/1inme/scripts/check-blade-inline-scripts.mjs` (composer `check:blade-inline-scripts`, validation `blade-inline-js`) extracts inline `<script>` blocks from Blade views and vm-compiles them to catch the "syntax error kills whole editor page" class.

**Why:** a bad `\'` escape in one inline script blanked the entire Social Proof editor; `node --check`-style static parsing catches this pre-ship.

Key neutralization lessons (hard-won false positives):
- Blank `@php`/`<?php ?>`/Blade comments over the WHOLE file BEFORE script-tag extraction — literal `<script` strings inside PHP (e.g. sanitizer regexes) otherwise match as tags.
- Script attrs can contain Blade echoes with `->` (`<script{!! $__attrs($pixel->type) !!}>`), so the attr regex must consume `{!! !!}`/`{{ }}`/quoted strings, not `[^>]*`.
- Echo placeholder must be an IDENTIFIER (`__bladeEcho`), not `null` — echoes appear in identifier/object-key positions (`function {{ $id }}()`).
- Loops are blanked (body appears once, still valid in object-literal position); conditionals need TWO compile passes: pass 1 maps to real `if(true){}` blocks (checks all branches, avoids duplicate-lexical clashes), pass 2 keeps only the first branch (handles expression-position `var x = @if … @else … @endif`). Report only if both fail.
- Escape hatches: `{{-- blade-js-check: skip --}}` marker or ALLOWLIST in the script; `views/vendor/` is excluded (user preference).
