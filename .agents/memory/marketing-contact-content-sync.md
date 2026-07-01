---
name: Contact details brand-default sync
description: How marketing + mobile get contact details and the THREE places the brand defaults live in lockstep.
---
Both the marketing site's Contact page AND the mobile Contact screen
(`artifacts/1inme-mobile/app/info/contact.tsx`) read contact details from the
Laravel product app at `GET /api/v1/site/contact` (served by the Api
`SiteContentController::contact()`), exactly mirroring `/site/about` and the
blog feed pattern.

**How to apply:**
- Both clients are plain `fetch()` (base resolved via a `VITE_*` override →
  LOGIN_URL origin on marketing; `getBaseUrl()` on mobile). It is NOT part of
  the OpenAPI/Orval codegen — do not add it to api-spec.
- Brand contact defaults exist in THREE places that must stay in lockstep: the
  PHP `contactExtraDefault()`, the marketing TS `DEFAULT_CONTACT_CONTENT`, and
  the mobile `DEFAULT_CONTACT_CONTENT` in `lib/api/siteContent.ts`. Keep ALL
  rendered fields identical (address, email, hours, social, map, and a
  DELIBERATELY BLANK phone — no fake number). Every phone row is guarded so a
  blank phone renders NO row.
- Mobile-specific: `fetchContactContent()` returns a non-null `ContactContent`
  always (per-field merge with defaults; whole-default on non-OK/empty/throw),
  and the screen seeds `useState(DEFAULT_CONTACT_CONTENT)` so the first paint
  and any failed fetch show real details, never a blank card. Only successful
  fetches are cached (transient failure retries next mount).

**Why:** a fetch failure or a one-sided defaults edit must never surface stale
placeholders (an old support@ address, a made-up phone, the wrong city). The
duplicate defaults are the fallback shown when the API is unreachable, so if
<<<<<<< HEAD
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

Mobile (`1inme-mobile/lib/api/siteContent.ts`) now includes `DEFAULT_CONTACT_CONTENT`
to ensure parity. fetchContactContent() merges server results with these defaults
and returns the full default on failure, ensuring the screen never renders a blank
card.
