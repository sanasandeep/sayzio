---
name: mockup-sandbox BASE_URL public assets
description: Why public-asset image refs silently 404 in mockup-sandbox previews
---

In the mockup-sandbox vite preview, `import.meta.env.BASE_URL` can arrive WITHOUT
a trailing slash (it mirrors `BASE_PATH="/__mockup"`), so the common idiom
`` `${import.meta.env.BASE_URL}images/foo.webp` `` produces `/__mockupimages/foo.webp`
→ 404 in the browser while the file itself serves fine at
`/__mockup/images/foo.webp` (curl 200). Symptom: images render blank/transparent
in the iframe even though `curl` of the correct path returns `200 image/webp`.

**Why:** the app's own JS loads (so base is applied to module URLs and the page
renders), which makes it look like base is fine — but the string-concatenated
asset path drops the separator. Easy to misread as a corrupt-image or
not-served-yet problem.

**How to apply:** normalize the base before concatenating, e.g.
`const ASSET_BASE = (import.meta.env.BASE_URL || "/").replace(/\/?$/, "/")`.
Static `public/` assets are served at base; vite default public dir is
`<artifact>/public`. To decisively diagnose, compare the browser's computed
`src` against the known-good `/__mockup/images/...` URL — do not trust that
`curl 200` means the browser is requesting the same string.
