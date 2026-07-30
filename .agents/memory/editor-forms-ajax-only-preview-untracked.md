---
name: Editor block forms are AJAX-only; owner previews untracked
description: Biolink editor no longer server-renders hidden per-block settings forms, and signed owner previews are excluded from all analytics.
---

# Editor block forms are AJAX-only; owner previews untracked

**Rule 1:** The biolink editor page must NOT server-render hidden per-block `<template id="editForm_...">` settings forms. The AJAX `editForm` endpoint is the only source of the drawer form; the failure path is a Retry button (`_showEditFormError`), not a baked fallback.
**Why:** On a 14-block page the hidden forms cost ~27 extra queries, ~21s render, and ~9MB of HTML that was discarded the moment the drawer fetched a fresh copy.
**How to apply:** Never re-introduce per-block includes of `block-settings-form` on the editor page; new per-block UI must be lazy/AJAX.

**Rule 2:** The Forms / Buzz / AI Companions picker lists are read only through `EditorPaletteLists` (cached per owner+workspace, busted by the three models' booted() hooks). The settings-form partial consumes plain arrays (`$f['id']`), lazily falling back to the helper — do not add direct Eloquent queries inside partial branches.

**Rule 3:** Valid signed owner previews (`?_preview=1` + `hasValidSignatureWhileIgnoring([_draft,_t,_sim_country,_sim_device])`) are excluded from analytics end to end: RedirectController skips `trackingService->track()`, and the biolink engagement script returns early on `_preview` in the query string (like `__E2E__`).
**Why:** Editor device-preview iframes are the owner viewing a draft; tracking them polluted click/engagement stats and slowed preview first paint.
**How to apply:** Any new tracking hook on public biolink renders must honor the same owner-preview skip.
