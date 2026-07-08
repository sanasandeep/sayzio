---
name: Hidden legacy form fields clobber serialized values
description: display:none leftover markup inside a form still serializes; duplicate input names let dead fields override live ones (PHP last-wins).
---

**Rule:** when extracting a form section into a partial, DELETE the old inline markup — never keep it wrapped in `<div style="display:none">`. Hidden elements still serialize into FormData/normal submits, and with duplicate input names PHP keeps the LAST value, so dead fields (often with broken Alpine `:value` bindings evaluating to empty) silently clobber the live ones.

**Why:** the biolink Appearance live-preview draft push (`/preview-draft`) never recorded `background_type=template` because a display:none legacy copy of the background section kept a second `background_type` hidden input whose Alpine scope lacked `bgType` (console showed "bgType is not defined") — its empty value overrode the partial's real value.

**How to apply:** if a live-preview/draft POST "fires but has no effect", grep the rendered page for duplicate `name="..."` inputs inside the same form; also treat "X is not defined" Alpine page errors as a signal of orphaned duplicate markup. Debug recipe that worked: standalone playwright script importing `pkg from /home/runner/workspace/node_modules/.pnpm/@playwright+test@…/index.js` (workspace pnpm strictness blocks normal import from /tmp scripts).
