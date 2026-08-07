---
name: Unclosed Alpine <template x-if> swallows the rest of the page
description: An unclosed <template> tag makes the HTML parser move ALL subsequent markup inside the inert template — page truncates silently, Alpine dies.
---

# Unclosed `<template x-if>` swallows the rest of the page

An unclosed `<template>` (e.g. `<template x-if="...">` without `</template>`) does not error: the HTML parser places **everything after it** inside the template's inert content fragment. Symptoms: the page visually truncates right after the widget, later Alpine components never mount, no console error.

**Why:** `<template>` content is parsed but never rendered; the parser only closes it at `</template>` or EOF.

**How to apply:** after adding any `<template x-if/x-for>` block to a large Blade view, verify the closing tag and load the page in a real browser (or e2e) — grep alone won't catch it. Note `p.scenarios.expected`-style guards need `x-if` (not `x-show`) when the bound object key may not exist.
