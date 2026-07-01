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
