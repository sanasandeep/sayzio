---
name: Alpine x-data attribute truncated by literal double-quotes
description: Literal " inside a double-quoted x-data/x-init attribute (even inside a /* */ comment) silently truncates the HTML attribute, breaking the whole Alpine component.
---

# Alpine x-data attribute truncated by literal double-quotes

A Blade element written as `x-data="{ ... }"` uses the double-quote as the HTML
attribute delimiter. Any **literal `"`** in the STATIC blade text inside that
attribute — including inside a `/* ... */` block comment or a JS string — is
emitted raw (Blade only escapes `{{ }}` echoes, not static text). The browser
HTML parser then ends the attribute at that first inner `"`, so Alpine receives a
**truncated** expression (e.g. an unterminated `/*` comment) and throws
`SyntaxError: Invalid or unexpected token` / `Unexpected token '}'`. The whole
component fails to initialize, so every helper (`money`, `cycle`, `intro`, ...)
becomes "is not defined" and all reactive bindings render blank.

**Why:** This is the SAME visible symptom as the `//`-line-comment-kills-Alpine
bug (blank prices on `/pricing`) but a different root cause. It surfaces only in
the browser (no server error). On `/pricing` it was a comment reading
`the struck-through "Was"` inside the section `x-data`.

**How to apply:**
- Never put a literal `"` inside a double-quoted `x-data`/`x-init`/`@click`/`:class`
  attribute. Use single quotes (`'Was'`) or curly quotes in prose comments.
- Prefer `/* */` over `//` in inline Alpine expressions (Alpine inlines to one line).
- To reproduce/diagnose: fetch the served HTML, slice the attribute value at the
  first unescaped `"` after `x-data="`, HTML-unescape it, and `node --check` it —
  it reproduces exactly what the browser+Alpine see.
- The runtime guard is `artifacts/1inme/tests/Browser/pricing-price-visible.spec.ts`
  (asserts every `.plan-card .plan-price .price-num` is a real currency amount on
  both cycles + no Alpine expression / pageerror). Static complements exist:
  `alpine-line-comments`, `blade-json-in-attr` validation workflows — neither
  catches a literal `"` in a comment.
