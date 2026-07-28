---
name: Blade @endif@if juxtaposed doesn't compile
description: Adjacent @endif@if(...) inside an attribute value is left raw in output; build class strings with a {{ }} expression instead.
---

# Blade `@endif@if` juxtaposition silently emits raw directives

`class="@if($a)x @endif@if($b)y@endif"` compiles the FIRST @if but leaves the second as literal `@if($b)y@endif` text in the HTML — the class never applies, and `curl | grep` for the class name still "finds" it (inside the raw directive), so server-side greps look green while the browser DOM has no such class.

**Why:** Blade's directive tokenizer mis-parses `@endif@if` when they touch with no whitespace between.

**How to apply:** for conditional class lists in attributes, use a single expression: `class="{{ trim(($a ? 'x ' : '') . ($b ? 'y' : '')) }}"`, or put whitespace between directives. When verifying, check the rendered DOM (Playwright `classList`), not just grep of the HTML.
