---
name: x-teleport template needs an x-data root
description: Alpine never processes x-teleport templates included in plain (non-Alpine) markup; the template needs its own x-data.
---

**Rule:** A `<template x-teleport="body">` that is not inside any `x-data` component is silently never processed — no console error, the teleported clone is just never created, so the modal/popup never mounts. Add an empty `x-data` to the `<template>` itself (`<template x-data x-teleport="body">`).

**Why:** `Alpine.start()` only calls `initTree()` on roots matching `[x-data]`/`[x-init]`; directives on elements outside any root are never visited. Manual `Alpine.initTree(tpl)` in the console making the clone appear is the definitive diagnostic. This bit the store-badge "coming soon" popup (`store-buttons.blade.php`): the teleport "fix" for the blank-modal bug shipped dead in both the homepage dialer section and the footer.

**How to apply:** Any self-contained teleported overlay included via a Blade partial into plain markup must carry `x-data` on the template. Symptom signature: template exists in DOM, Alpine loaded, zero console errors, dispatching the open event does nothing. Guarded by `tests/Browser/store-coming-soon-modal.spec.ts` (asserts overlay parent === body, card visible/opaque/in-viewport in both contexts).
