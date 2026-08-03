---
name: Font Awesome glyph pollutes accessible names in Playwright
description: getByRole exact-name matches fail on buttons that contain an <i class="fa..."> icon.
---

Font Awesome renders its glyph via CSS `::before` content, and that content is
included in accessible-name computation. A button like
`<i class="fab fa-whatsapp"></i> WhatsApp` therefore has the accessible name
`"\uf232 WhatsApp"`, so `getByRole("button", { name: "WhatsApp", exact: true })`
resolves ZERO elements and times out with a bare "waiting for …" log — even
though the button is plainly visible in the failure screenshot.

**How to apply:** for any 1inme button/link that carries an FA icon, match the
name by substring or regex (`{ name: /WhatsApp/ }`), never `exact: true`.
Hidden duplicates (other tabs' buttons with the same text) are excluded from
getByRole, so the regex usually stays unique; add `.filter()` when it doesn't.
