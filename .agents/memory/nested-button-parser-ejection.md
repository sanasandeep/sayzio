---
name: Nested <button> parser ejection
description: A <button> inside another <button> makes the HTML parser force-close the outer one, misnesting the whole tree — panels visually "float" out of their layout column.
---

# Nested `<button>` parser ejection

**Rule:** never nest a `<button>` (or other button start tag) inside an open `<button>`. The HTML parser force-closes the outer button at the inner one; the stray closing tags then close ancestor `<div>`s early, so everything after that point in the source is ejected to a different DOM parent.

**Why:** the admin Block Defaults editor put a "clear" button inside each collapsible section-header button. Server HTML looked balanced, but in the browser every card after the first was ejected from the form column — showing up as a floating panel at the top-right (theme-independent, so it looked like a CSS bug). Same family as the nested-`<form>` gotcha ([nested-form-breaks-outer-layout.md](nested-form-breaks-outer-layout.md)).

**How to apply:** for a secondary action inside a clickable header, make the inner control a `<span role="button" tabindex="0">` with `@click.stop` + `@keydown.enter`, or make the outer element a non-button. Diagnose with `document.querySelectorAll('button button')` / a containment check (`col.contains(card)`) in the live DOM — served-HTML inspection will NOT show the problem.

**Companion caret bug:** `background:` shorthand on a select resets `background-repeat` to `repeat` while a higher-specificity layout rule (`[data-app-layout] select`) still supplies the chevron `background-image` → a strip of tiled carets. Use `background-color:` in component select/input rules.
