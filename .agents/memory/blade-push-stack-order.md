---
name: Blade @push must execute before the target @stack
description: A partial's @push('head') silently renders nothing if included after the layout already flushed @stack('head')
---

Blade's `@push`/`@stack` is execution-order dependent, not document-order
dependent: `@stack('name')` renders whatever has been pushed *so far* at the
moment it executes. If a layout calls `@stack('head')` early (e.g. right
after the page `<title>`) and a partial included later in the body does
`@push('head')`, that content is silently dropped — no error, the CSS/JS
just never appears.

**Why:** discovered adding a shared promo-band partial included in the
`<body>` of a site layout whose `@stack('head')` was already flushed earlier
in `<head>`; the partial's pushed `<style>` block never rendered, causing
a CSS-dependent slide-rotator to show all slides at once instead of one.

**How to apply:** before using `@push('head')`/`@push('scripts')` inside a
partial, check where the layout calls the matching `@stack(...)` relative to
where the partial gets `@include`d. If the partial is included after the
stack is flushed, either (a) move the include earlier, or (b) just inline
the `<style>`/`<script>` directly in the partial's own markup instead of
pushing — inline tags work regardless of include position. `@push('scripts')`
against a `@stack('scripts')` near the end of `<body>` is usually safe since
most includes happen earlier in the page.
