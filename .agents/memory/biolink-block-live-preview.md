---
name: Biolink block instant live preview
description: How the editor→iframe live-patch channel works and its gotchas (baseline capture, ack fallback, e2e reload counting).
---

# Biolink block instant live preview channel

The biolink editor posts a `1inme-block-live` message (full settings/style form state + dotted changed-keys diff) into the phone preview iframe on each keystroke; the public page (in `?_editBlock` mode) patches DOM in place for known types/fields and acks `handled`. Debounced autosave skips the iframe reload only when the last ack was `handled=true`.

**Gotchas:**
- The diff is baseline-vs-current: the baseline MUST be captured when the drawer form loads (in `_initDrawerAutoSave`'s post-load timeout), not only after saves — otherwise the first-keystroke diff is null and no live message ever posts (silent: everything still "works" via the reload path).
- Preview acks `handled=true` only if EVERY changed key is covered by a handler/`LIVE_STYLE_KEYS`; anything else falls back to `_refreshEditPreview()`. New block types need a `LIVE_HANDLERS` entry in `common/biolink.blade.php`.
- E2e reload counting: the heading can render before the iframe `load` event fires (subresources still streaming), so wait for `contentDocument.readyState === 'complete'` before installing a load-event counter, or the initial load counts as a spurious "reload".
- Style inputs in the drawer are behind a collapsed "Block Styling" toggle + tabs (`[data-style-root]`); Playwright must click both open before filling.
- Long (>2 min) specs can't run inside the 120s bash cap: run via a temporary console workflow (`configureWorkflow` + `getWorkflowStatus` polling); detached/nohup bash processes get reaped when the launching session ends, and `pgrep -f` self-matches the polling command.
