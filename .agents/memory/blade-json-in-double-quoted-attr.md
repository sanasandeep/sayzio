---
name: Blade @json inside double-quoted HTML attributes
description: "@json emits literal double quotes that truncate double-quoted attributes (x-data/@click), silently breaking Alpine; use @js instead"
---

Rule: never use `@json(...)` inside a double-quoted HTML attribute (`x-data="..."`, `@click="..."`, `:style="..."`). Use `@js(...)` (Js::from), which escapes for HTML-attribute context.

**Why:** `@json` outputs raw JSON with literal `"` characters; the browser ends the attribute at the first one, leaving Alpine expressions truncated. Symptoms are silent: the component never initialises (x-if templates never stamp → element has zero height and reads as "hidden" to Playwright), or click handlers become broken expressions that do nothing. This broke the audience-prompt widget in both its `x-data` and its `@click="pick(...)"` buttons (July 2026).

**How to apply:** when a Blade partial's Alpine behaviour mysteriously does nothing, inspect the *rendered* attribute soup (Playwright error-context shows the mangled `<div \="" ...>` attributes). Sweep candidates via `grep -rn '="[a-zA-Z]*(@json' resources/views`.

Related: route() names inside user-module blades must carry the `user.` group prefix (`route('user.links.…')`); a missing prefix throws RouteNotFoundException as a 500 only when that card actually renders — e2e through the real page catches it.

**Guard:** automated since July 2026 — `pnpm --filter @workspace/scripts run check:blade-json-in-attr` (scripts/src/check-blade-json-in-attr.ts, registered as the `blade-json-in-attr` validation workflow) fails on any `@json(` inside a double-quoted attribute; single-quoted attrs, `<script>` bodies, `@@json`, Blade comments and `@verbatim` are exempt.
