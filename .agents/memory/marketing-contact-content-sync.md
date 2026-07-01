---
name: Marketing contact details sync
description: How the marketing Contact page gets its details and where the brand defaults live in duplicate.
---
The marketing site's Contact page reads contact details from the Laravel
product app at `GET /api/v1/site/contact` (served by the Api
`SiteContentController::contact()`), exactly mirroring `/site/about` and the
blog feed pattern.

**How to apply:**
- The marketing side is a plain `fetch()` (base resolved like the blog feed via
  a `VITE_*` override → LOGIN_URL origin). It is NOT part of the OpenAPI/Orval
  codegen — do not add it to api-spec.
- Brand contact defaults exist in TWO places that must stay in lockstep: the
  PHP `contactExtraDefault()` and the TS `DEFAULT_CONTACT_CONTENT` fallback.
  Keep ALL rendered/documented fields identical (address, email, hours, and a
  DELIBERATELY BLANK phone — no fake number). The TS fetcher only shows a
  "Call us" card when phone is non-empty.

**Why:** a fetch failure or a one-sided defaults edit must never surface stale
placeholders (an old support@ address, a made-up phone, the wrong city). The
duplicate defaults are the fallback shown when the API is unreachable, so if
they drift from the PHP source the marketing site silently misrepresents the
company. Coverage lives in a server feature test (defaults vs admin-override
paths) plus a frontend test (renders fetched values; falls back to brand
defaults on fetch failure).

**Drift guard:** `contact-content.sync.test.ts` (in `1inme-com/src/lib`, part of
the `test:1inme-com` validation via `vitest run`) reads the PHP source at
runtime, extracts `contactExtraDefault()`'s email/address/phone, and asserts
they equal `DEFAULT_CONTACT_CONTENT` — no hard-coded third copy. It scopes the
regex to the method body (keys like `email` recur in the form/messages
sub-arrays) and decodes PHP string escapes (double-quoted `\n` → newline). Only
email/address/phone are guarded: hours/social deliberately differ (the marketing
fallback keeps social blank). Renaming the PHP method or changing the return
shape makes the test throw a clear message, not silently pass.

Mobile (`1inme-mobile/lib/api/siteContent.ts`) has NO contact fallback const —
it returns null on fetch failure and renders nothing — so there is nothing to
guard there.
