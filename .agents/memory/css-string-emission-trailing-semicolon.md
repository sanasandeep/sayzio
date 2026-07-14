---
name: Catalog CSS strings emitted into Blade style blocks need a trailing semicolon
description: Raw multi-declaration CSS strings (e.g. BgPresetCatalog) end without ';'; emitting them mid-rule silently corrupts the next declaration.
---

# Emitting raw CSS strings inside a Blade `body{}` block

BgPresetCatalog (and similar catalogs) store CSS as `prop:a;prop:b` with NO
trailing semicolon. Emitting `{!! $css !!}` followed by another declaration
(e.g. `min-height: 100vh;`) makes the browser parse
`background-image: linear-gradient(...) min-height: 100vh` → the LAST catalog
property AND the following declaration are silently dropped (background-color
earlier in the string still applies, which masks the bug as "wrong-ish color,
no gradient").

**How to apply:** always `{!! rtrim($css, '; ') !!};` when interpolating stored
CSS declaration strings into a larger rule. Symptom to recognize: computed
backgroundImage `none` but the preset's background-color applied.

Found via the bg-preset live-preview e2e: the draft channel worked fine; the
public renderer was the broken layer.
