---
name: Drawer file-input fires an early sticker-less autosave
description: In the biolink edit drawer, uploads that later sync a hidden input trigger TWO autosaves; e2e must match the POST body, not just the URL.
---

# Drawer file-input double autosave

In the biolink editor drawer, a `type=file` input's own `change` event fires an
immediate autosave (300ms debounce) BEFORE the async upload response returns and
the Alpine component syncs its hidden JSON input (which fires a second autosave
~800ms later).

**Why:** an e2e that waits for "any ok POST to /blocks/{id}" matches the FIRST,
payload-less save, navigates away, and the public page renders without the new
data — flaky/false failure even though the feature works.

**How to apply:** in Playwright, gate the save wait on the request body, e.g.
`r.request().postData()?.includes("file_id")` (works for multipart FormData
too), before navigating to assert the public render.
