---
name: Nested <form> silently closes the outer form
description: Why served-HTML-looks-right but rendered layout is broken on big Blade forms
---

Rule: never place a `<form>` inside another `<form>` in Blade views. The HTML parser drops the inner `<form>` start tag and its `</form>` CLOSES the OUTER form — every later sibling (fieldsets, sticky save bars) falls out of the form's grid/DOM subtree.

**Why:** On the Creator Profile editor, gap + sticky-save fixes were present in the served HTML (curl-grep verified) yet invisible in the browser; a small nested "send sample" form mid-page had ejected the lower half of the form from the CSS grid. Two fix attempts were wasted on styles.

**How to apply:**
- Symptom signature: served HTML contains the styles/classes but a real browser shows neither, AND a locator like `form .x` finds nothing while the element exists in the page snapshot → suspect early-closed form.
- Fix pattern: keep only a `<button type="submit" form="some-id">` inline and declare the small standalone `<form id="some-id">` AFTER the main form.
- Verification must be a real browser (Playwright computed styles/geometry via the managed validation runner), never curl-grep.
