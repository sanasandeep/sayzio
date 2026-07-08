---
name: Biolink block instant live preview
description: How the editor→iframe live-patch channel works and its gotchas (baseline capture, ack fallback, e2e reload counting).
---

# Biolink block instant live preview channel

The biolink editor posts a `1inme-block-live` message (full settings/style form state + dotted changed-keys diff) into the phone preview iframe on each keystroke; the public page patches DOM in place for known types/fields and acks `handled`. Debounced autosave skips the iframe reload only when the last ack was `handled=true`.

**Gotchas:**
- The preview iframe loads the signed owner URL with `?_preview=1` — `_editBlock` is NEVER on it (nothing appends it). Any preview-side listener gated only on `_editBlock` silently never runs; gate on `_editBlock || _preview`.
- E2e "no reload happened" assertion: don't count iframe `load` events (hidden tablet/desktop device iframes lazy-load and fire spurious loads). Instead stamp `document.body` with a data attribute after the iframe renders and assert the stamp survives (or, for the fallback path, disappears).
- The video/image URL drawer fields are the file-upload widget: the NAMED input is hidden (Alpine `x-model`), the user types into a plain `input[type=url]` — Playwright must fill that visible input.
- First e2e test needs the public alias page warmed in `beforeAll` (cold render over distant RDS can exceed 60s) plus `test.setTimeout(180_000)`.
- The diff is baseline-vs-current: the baseline MUST be captured when the drawer form loads (in `_initDrawerAutoSave`'s post-load timeout), not only after saves — otherwise the first-keystroke diff is null and no live message ever posts (silent: everything still "works" via the reload path).
- Preview acks `handled=true` only if EVERY changed key is covered by a handler/`LIVE_STYLE_KEYS`; anything else falls back to `_refreshEditPreview()`. New block types need a `LIVE_HANDLERS` entry in `common/biolink.blade.php`.
- E2e reload counting: the heading can render before the iframe `load` event fires (subresources still streaming), so wait for `contentDocument.readyState === 'complete'` before installing a load-event counter, or the initial load counts as a spurious "reload".
- Style inputs in the drawer are behind a collapsed "Block Styling" toggle + tabs (`[data-style-root]`); Playwright must click both open before filling.
- Long (>2 min) specs can't run inside the 120s bash cap: run via a temporary console workflow (`configureWorkflow` + `getWorkflowStatus` polling); detached/nohup bash processes get reaped when the launching session ends, and `pgrep -f` self-matches the polling command.
- Repeater blocks (socials rows, progress rows, list items) are covered by the same spec; dotted repeater keys use numeric segments matched as `*` (e.g. `settings.platforms.*.url`). Socials live hrefs go through the click tracker `?to=` param — assert the decoded href contains the new destination, not equality.
- The spec's `beforeAll` (tinker seed + demo-login + public-page warm) can exceed Playwright's default 60s hook budget on a cold env; it calls `test.setTimeout(240_000)` inside the hook. Autosave waits use 60s.
- Autosave-wait race: `waitForAutosave` called AFTER `fill()` can miss the debounced (~800ms) POST when the intervening live-patch assertion polls slowly — arm the `page.waitForResponse` promise BEFORE typing, await it after the patch assertion (grouped-socials test shows the pattern).
- `socials_multi` public markup flattens all groups' platforms into ONE anchor list; the live handler computes the flat index by counting `settings[groups][g][platforms][n][name]` keys of prior groups from the full form snapshot (handlers get the snapshot as 4th arg).
- Workflow limit (10) can block temp workflows; to run a subset of a serial spec, temporarily mark tests `test.only`, run the existing workflow, then revert.
