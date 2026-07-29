---
name: Dropzone browseType vs accept + plan overrides
description: Why the dropzone Stock/image tabs can silently vanish; locator gotcha for Alpine x-for buttons
---
- `dropzone-input.blade.php` derives `$browseType` from the accept string. UploadPolicy emits extension lists (".jpg,.png,…"), not MIME types, so detection must match extensions too.
- **Plan `upload_limits` overrides can blank the extension list → accept='' → browseType 'all' → image-only tabs (Stock) hidden.** Image-typed dropzone includes should pass an explicit `'browseType' => 'image'`.
- **Why:** demo account's plan override hid the Stock tab in e2e even after regex fix.
- Playwright: `getByRole("button", {name})` can resolve 0 for Alpine x-for-rendered buttons whose label is a child `x-text` span; use CSS `button:has(span:text-is("Label"))` instead.
