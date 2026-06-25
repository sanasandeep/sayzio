---
name: Brand-sweep protected tokens
description: When mass-renaming the product brand (e.g. 1INME→Sayzio), which "1INME" strings are functional and must NOT be renamed.
---

# Brand sweep: rename display text, shield functional tokens

A product rename is mostly a display-string swap, but a naive global
find/replace breaks runtime behavior. During the 1INME→Sayzio sweep these
tokens were intentionally LEFT as `1INME`/`1inme` because they are wire
identifiers, not user-facing brand text:

- **Domains**: `1in.me`, `1inme.com` (the rename explicitly keeps these; a
  separate task wires `sayzio.app`).
- **HTTP headers**: `X-1INME-Client`, `X-1INME-Visitor-Id`, `X-1INME-Signature`.
- **Mobile user-agent / app id**: `1INMEMobileApp`, `KEY_1INME_APP` / `1inme_app`.
- **Analytics channel constant**: the stored `1inme_app` channel value (its
  human display label DID change to "Sayzio app"; the stored enum did not).
- **Bot/feature UA tokens**: `1INMEMindBot`, `1INME-AR`, `1INME-LinkInsurance`.
- **Real external social handles**: `@1INMEOfficial`, `/company/1INMEOfficial`.
- **Storage/cookie keys**: `1inme.auth.*`, `1inme_session`.
- **Expo identity**: app.json `slug`/`scheme`; repo dir paths `artifacts/1inme*`.

**Why:** changing a stored enum/header/UA/cookie/social handle silently breaks
already-persisted rows, signed requests, OAuth callbacks, and live social links —
none of which a brand rename intends to touch.

**How to apply:** rename only display copy (titles, labels, marketing text,
`APP_NAME`, lowercase brand keyword tokens in SEO/content classes). For each
candidate match ask "is this shown to a human, or matched/stored by a machine?"
Machine tokens stay. Logos/favicons are user-provided files swapped in place
under each artifact's `public/`/`assets/` keeping existing FILENAMES so no code
refs change (main app logos default to `public/branding/logo-{light,dark}.png`
via DomainBranding; light = dark-text/light-bg, dark = white-text/dark-bg).
