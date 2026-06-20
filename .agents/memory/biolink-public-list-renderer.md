---
name: Biolink public list renderer is string-only
description: The public biolink page renders list blocks with its own inline renderer that expects plain-string items, diverging from the editor partial.
---

The public biolink page (`artifacts/1inme/resources/views/common/biolink.blade.php`)
renders `list` / `list_numbered` blocks with an **inline** loop that does
`{{ $item }}` — i.e. it expects each `settings.items` entry to be a plain string.

`resources/views/common/blocks/list.blade.php` (used by the editor-side
`biolink-block-render` partial) instead normalizes both legacy strings AND the
newer `{text, icon}` array shape.

**Why:** these two renderers disagree. If you write list items as
`['text' => ..., 'icon' => ...]` arrays, the public page throws
`htmlspecialchars(): array given` and the WHOLE biolink view 500s (a single
block error fails the entire compiled view).

**How to apply:** when seeding or programmatically writing `list` blocks that
must render on the public page, store `settings.items` as a flat `string[]`.
